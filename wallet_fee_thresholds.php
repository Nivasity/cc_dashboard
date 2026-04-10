<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = (int) ($_SESSION['nivas_adminRole'] ?? 0);
$allowedRoles = [1, 2, 3, 4];

if (!$finance_mgt_menu || !in_array($admin_role, $allowedRoles, true)) {
  header('Location: /');
  exit();
}

function ccWalletFeeThresholdsTableExists($conn) {
  static $exists = null;

  if ($exists !== null) {
    return $exists;
  }

  $result = mysqli_query($conn, "SHOW TABLES LIKE 'wallet_fee_thresholds'");
  $exists = $result && mysqli_num_rows($result) > 0;

  return $exists;
}

function ccWalletFeeFormatAmount($amount) {
  return number_format((float) $amount, 2);
}

function ccWalletFeeSetFlash($type, $message) {
  $_SESSION['wallet_fee_thresholds_flash'] = [
    'type' => $type,
    'message' => $message,
  ];
}

function ccWalletFeeGetFlash() {
  $flash = $_SESSION['wallet_fee_thresholds_flash'] ?? null;
  unset($_SESSION['wallet_fee_thresholds_flash']);
  return $flash;
}

function ccWalletFeeFetchThresholds($conn) {
  $thresholds = [];
  $sql = "SELECT id, label, min_subtotal, max_subtotal, fee_amount, status, created_at, updated_at
          FROM wallet_fee_thresholds
          ORDER BY min_subtotal ASC, max_subtotal ASC, id ASC";
  $result = mysqli_query($conn, $sql);

  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $thresholds[] = $row;
    }
  }

  return $thresholds;
}

function ccWalletFeeFindActiveOverlap($conn, $minSubtotal, $maxSubtotal, $excludeId = 0) {
  $excludeId = (int) $excludeId;
  $rows = [];
  $sql = "SELECT id, label, min_subtotal, max_subtotal
          FROM wallet_fee_thresholds
          WHERE status = 'active'";

  if ($excludeId > 0) {
    $sql .= " AND id != $excludeId";
  }

  $sql .= " ORDER BY min_subtotal ASC, id ASC";
  $result = mysqli_query($conn, $sql);
  if (!$result) {
    return null;
  }

  while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
  }

  $candidateMax = $maxSubtotal === null ? INF : (float) $maxSubtotal;
  $candidateMin = (float) $minSubtotal;

  foreach ($rows as $row) {
    $rowMin = (float) $row['min_subtotal'];
    $rowMax = ($row['max_subtotal'] === null || $row['max_subtotal'] === '') ? INF : (float) $row['max_subtotal'];

    if ($rowMin <= $candidateMax && $rowMax >= $candidateMin) {
      return $row;
    }
  }

  return null;
}

$tableExists = ccWalletFeeThresholdsTableExists($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$tableExists) {
    ccWalletFeeSetFlash('danger', 'The wallet_fee_thresholds table is not available in this database yet. Run the migration first.');
    header('Location: wallet_fee_thresholds.php');
    exit();
  }

  $action = trim((string) ($_POST['action'] ?? ''));

  try {
    if ($action === 'save') {
      $thresholdId = (int) ($_POST['threshold_id'] ?? 0);
      $label = trim((string) ($_POST['label'] ?? ''));
      $minSubtotalRaw = trim((string) ($_POST['min_subtotal'] ?? ''));
      $maxSubtotalRaw = trim((string) ($_POST['max_subtotal'] ?? ''));
      $feeAmountRaw = trim((string) ($_POST['fee_amount'] ?? ''));
      $status = trim((string) ($_POST['status'] ?? 'active'));

      if ($label !== '') {
        $label = substr($label, 0, 100);
      }

      if ($minSubtotalRaw === '' || !is_numeric($minSubtotalRaw)) {
        throw new Exception('Minimum subtotal must be a valid number.');
      }

      if ($feeAmountRaw === '' || !is_numeric($feeAmountRaw)) {
        throw new Exception('Fee amount must be a valid number.');
      }

      $minSubtotal = round((float) $minSubtotalRaw, 2);
      $maxSubtotal = null;

      if ($maxSubtotalRaw !== '') {
        if (!is_numeric($maxSubtotalRaw)) {
          throw new Exception('Maximum subtotal must be a valid number when provided.');
        }

        $maxSubtotal = round((float) $maxSubtotalRaw, 2);
      }

      $feeAmount = round((float) $feeAmountRaw, 2);

      if ($minSubtotal < 0) {
        throw new Exception('Minimum subtotal cannot be negative.');
      }

      if ($maxSubtotal !== null && $maxSubtotal < $minSubtotal) {
        throw new Exception('Maximum subtotal must be greater than or equal to the minimum subtotal.');
      }

      if ($feeAmount < 0) {
        throw new Exception('Fee amount cannot be negative.');
      }

      if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
      }

      if ($status === 'active') {
        $overlap = ccWalletFeeFindActiveOverlap($conn, $minSubtotal, $maxSubtotal, $thresholdId);
        if ($overlap) {
          $overlapLabel = trim((string) ($overlap['label'] ?? ''));
          if ($overlapLabel === '') {
            $overlapLabel = 'existing threshold #' . (int) $overlap['id'];
          }

          throw new Exception('This active range overlaps with ' . $overlapLabel . '. Adjust the range or mark one of them inactive.');
        }
      }

      $labelValue = $label === '' ? null : $label;

      if ($thresholdId > 0) {
        if ($maxSubtotal === null) {
          $stmt = mysqli_prepare($conn, "UPDATE wallet_fee_thresholds
                                        SET label = ?, min_subtotal = ?, max_subtotal = NULL, fee_amount = ?, status = ?
                                        WHERE id = ?");
        } else {
          $stmt = mysqli_prepare($conn, "UPDATE wallet_fee_thresholds
                                        SET label = ?, min_subtotal = ?, max_subtotal = ?, fee_amount = ?, status = ?
                                        WHERE id = ?");
        }
        if (!$stmt) {
          throw new Exception('Unable to prepare the threshold update statement.');
        }

        if ($maxSubtotal === null) {
          mysqli_stmt_bind_param($stmt, 'sddsi', $labelValue, $minSubtotal, $feeAmount, $status, $thresholdId);
        } else {
          mysqli_stmt_bind_param($stmt, 'sdddsi', $labelValue, $minSubtotal, $maxSubtotal, $feeAmount, $status, $thresholdId);
        }
        if (!mysqli_stmt_execute($stmt)) {
          throw new Exception('Failed to update the threshold.');
        }

        if (mysqli_stmt_affected_rows($stmt) < 0) {
          throw new Exception('Threshold update did not complete successfully.');
        }

        mysqli_stmt_close($stmt);
        ccWalletFeeSetFlash('success', 'Wallet fee threshold updated successfully.');
      } else {
        if ($maxSubtotal === null) {
          $stmt = mysqli_prepare($conn, "INSERT INTO wallet_fee_thresholds (label, min_subtotal, max_subtotal, fee_amount, status)
                                        VALUES (?, ?, NULL, ?, ?)");
        } else {
          $stmt = mysqli_prepare($conn, "INSERT INTO wallet_fee_thresholds (label, min_subtotal, max_subtotal, fee_amount, status)
                                        VALUES (?, ?, ?, ?, ?)");
        }
        if (!$stmt) {
          throw new Exception('Unable to prepare the threshold insert statement.');
        }

        if ($maxSubtotal === null) {
          mysqli_stmt_bind_param($stmt, 'sdds', $labelValue, $minSubtotal, $feeAmount, $status);
        } else {
          mysqli_stmt_bind_param($stmt, 'sddds', $labelValue, $minSubtotal, $maxSubtotal, $feeAmount, $status);
        }
        if (!mysqli_stmt_execute($stmt)) {
          throw new Exception('Failed to create the threshold.');
        }

        mysqli_stmt_close($stmt);
        ccWalletFeeSetFlash('success', 'Wallet fee threshold created successfully.');
      }
    } elseif ($action === 'delete') {
      $thresholdId = (int) ($_POST['threshold_id'] ?? 0);
      if ($thresholdId <= 0) {
        throw new Exception('Select a valid threshold to delete.');
      }

      $stmt = mysqli_prepare($conn, "DELETE FROM wallet_fee_thresholds WHERE id = ? LIMIT 1");
      if (!$stmt) {
        throw new Exception('Unable to prepare the threshold delete statement.');
      }

      mysqli_stmt_bind_param($stmt, 'i', $thresholdId);
      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to delete the threshold.');
      }

      if (mysqli_stmt_affected_rows($stmt) === 0) {
        throw new Exception('Threshold not found or already removed.');
      }

      mysqli_stmt_close($stmt);
      ccWalletFeeSetFlash('success', 'Wallet fee threshold deleted successfully.');
    } else {
      throw new Exception('Unsupported action supplied.');
    }
  } catch (Throwable $e) {
    ccWalletFeeSetFlash('danger', $e->getMessage());
  }

  header('Location: wallet_fee_thresholds.php');
  exit();
}

$flash = ccWalletFeeGetFlash();
$thresholds = $tableExists ? ccWalletFeeFetchThresholds($conn) : [];
$activeCount = 0;
$inactiveCount = 0;
$highestFee = 0.0;

foreach ($thresholds as $threshold) {
  if (($threshold['status'] ?? '') === 'active') {
    $activeCount++;
  } else {
    $inactiveCount++;
  }

  $highestFee = max($highestFee, (float) ($threshold['fee_amount'] ?? 0));
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Wallet Fees | Nivasity Command Center</title>
    <meta name="description" content="Configure wallet handling fee thresholds by subtotal band." />
    <?php include('partials/_head.php') ?>
  </head>
  <body>
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <?php include('partials/_sidebar.php') ?>
        <div class="layout-page">
          <?php include('partials/_navbar.php') ?>
          <div class="content-wrapper">
            <div class="container-xxl flex-grow-1 container-p-y">
              <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                  <h4 class="fw-bold py-3 mb-1"><span class="text-muted fw-light">Finances /</span> Wallet Fee Thresholds</h4>
                  <p class="mb-0 text-muted">Set wallet handling fees by subtotal band. Runtime checkout still clamps wallet fees below the active gateway fee.</p>
                </div>
                <?php if ($tableExists) { ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#thresholdModal" id="newThresholdBtn">
                  <i class='bx bx-plus me-1'></i> Add Threshold
                </button>
                <?php } ?>
              </div>

              <?php if ($flash) { ?>
              <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible" role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
              <?php } ?>

              <?php if (!$tableExists) { ?>
              <div class="alert alert-warning" role="alert">
                The <strong>wallet_fee_thresholds</strong> table does not exist in this environment yet. Run the wallet fee threshold migration in the main application database before using this screen.
              </div>
              <?php } else { ?>
              <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="text-muted d-block mb-1">Active bands</span>
                      <h3 class="mb-0"><?php echo (int) $activeCount; ?></h3>
                    </div>
                  </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="text-muted d-block mb-1">Inactive bands</span>
                      <h3 class="mb-0"><?php echo (int) $inactiveCount; ?></h3>
                    </div>
                  </div>
                </div>
                <div class="col-lg-4 col-md-12 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="text-muted d-block mb-1">Highest configured fee</span>
                      <h3 class="mb-0">&#8358;<?php echo htmlspecialchars(ccWalletFeeFormatAmount($highestFee)); ?></h3>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row g-4">
                <div class="col-xl-8">
                  <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                      <div>
                        <h5 class="mb-1">Configured Thresholds</h5>
                        <small class="text-muted">Active ranges should not overlap.</small>
                      </div>
                    </div>
                    <div class="card-body">
                      <div class="table-responsive text-nowrap">
                        <table class="table">
                          <thead class="table-secondary">
                            <tr>
                              <th>Label</th>
                              <th>Subtotal range</th>
                              <th>Wallet fee</th>
                              <th>Status</th>
                              <th>Updated</th>
                              <th>Actions</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php if (empty($thresholds)) { ?>
                            <tr>
                              <td colspan="6" class="text-center text-muted py-4">No thresholds have been configured yet.</td>
                            </tr>
                            <?php } ?>
                            <?php foreach ($thresholds as $threshold) { ?>
                            <?php
                              $thresholdId = (int) $threshold['id'];
                              $label = trim((string) ($threshold['label'] ?? ''));
                              $minSubtotal = (float) ($threshold['min_subtotal'] ?? 0);
                              $maxSubtotalValue = $threshold['max_subtotal'];
                              $feeAmount = (float) ($threshold['fee_amount'] ?? 0);
                              $status = (string) ($threshold['status'] ?? 'inactive');
                              $updatedAt = trim((string) ($threshold['updated_at'] ?? ''));
                              $maxDisplay = ($maxSubtotalValue === null || $maxSubtotalValue === '') ? 'And above' : '&#8358;' . ccWalletFeeFormatAmount($maxSubtotalValue);
                            ?>
                            <tr>
                              <td>
                                <span class="fw-semibold"><?php echo htmlspecialchars($label !== '' ? $label : 'Threshold #' . $thresholdId); ?></span>
                              </td>
                              <td>
                                <span>&#8358;<?php echo htmlspecialchars(ccWalletFeeFormatAmount($minSubtotal)); ?></span>
                                <span class="text-muted mx-1">to</span>
                                <span><?php echo $maxDisplay; ?></span>
                              </td>
                              <td>&#8358;<?php echo htmlspecialchars(ccWalletFeeFormatAmount($feeAmount)); ?></td>
                              <td>
                                <span class="badge bg-label-<?php echo $status === 'active' ? 'success' : 'secondary'; ?>">
                                  <?php echo htmlspecialchars(ucfirst($status)); ?>
                                </span>
                              </td>
                              <td><?php echo htmlspecialchars($updatedAt !== '' ? date('d M Y, h:i A', strtotime($updatedAt)) : '-'); ?></td>
                              <td>
                                <button
                                  type="button"
                                  class="btn btn-sm btn-outline-primary me-2 edit-threshold-btn"
                                  data-bs-toggle="modal"
                                  data-bs-target="#thresholdModal"
                                  data-id="<?php echo $thresholdId; ?>"
                                  data-label="<?php echo htmlspecialchars($label, ENT_QUOTES); ?>"
                                  data-min="<?php echo htmlspecialchars(number_format($minSubtotal, 2, '.', '')); ?>"
                                  data-max="<?php echo htmlspecialchars($maxSubtotalValue === null ? '' : number_format((float) $maxSubtotalValue, 2, '.', '')); ?>"
                                  data-fee="<?php echo htmlspecialchars(number_format($feeAmount, 2, '.', '')); ?>"
                                  data-status="<?php echo htmlspecialchars($status); ?>">
                                  Edit
                                </button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this wallet fee threshold?');">
                                  <input type="hidden" name="action" value="delete" />
                                  <input type="hidden" name="threshold_id" value="<?php echo $thresholdId; ?>" />
                                  <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                              </td>
                            </tr>
                            <?php } ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="col-xl-4">
                  <div class="card mb-4">
                    <div class="card-header">
                      <h5 class="mb-1">How it works</h5>
                    </div>
                    <div class="card-body">
                      <ul class="mb-0 ps-3">
                        <li class="mb-2">The checkout service matches the order subtotal against the highest active band that fits.</li>
                        <li class="mb-2">Leave maximum subtotal blank to create an open-ended top band.</li>
                        <li class="mb-2">If a wallet fee is higher than the current gateway fee, runtime checkout automatically reduces it below the gateway fee.</li>
                        <li>Inactive bands stay stored but are ignored by checkout.</li>
                      </ul>
                    </div>
                  </div>

                  <div class="card">
                    <div class="card-header">
                      <h5 class="mb-1">Range design</h5>
                    </div>
                    <div class="card-body">
                      <p class="text-muted mb-3">Keep active bands contiguous where possible so every subtotal resolves predictably.</p>
                      <div class="border rounded p-3 bg-lighter">
                        <small class="text-muted d-block mb-1">Example</small>
                        <div>&#8358;0.00 to &#8358;4,999.99 =&gt; &#8358;25.00</div>
                        <div>&#8358;5,000.00 to &#8358;19,999.99 =&gt; &#8358;50.00</div>
                        <div>&#8358;20,000.00 and above =&gt; custom fee</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php } ?>
            </div>

            <?php include('partials/_footer.php') ?>
            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <?php if ($tableExists) { ?>
    <div class="modal fade" id="thresholdModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="thresholdModalTitle">Add Wallet Fee Threshold</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="post">
            <div class="modal-body">
              <input type="hidden" name="action" value="save" />
              <input type="hidden" name="threshold_id" id="thresholdId" value="0" />
              <div class="mb-3">
                <label for="thresholdLabel" class="form-label">Label</label>
                <input type="text" class="form-control" id="thresholdLabel" name="label" maxlength="100" placeholder="e.g. Mid band" />
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="thresholdMinSubtotal" class="form-label">Minimum subtotal</label>
                  <input type="number" step="0.01" min="0" class="form-control" id="thresholdMinSubtotal" name="min_subtotal" required />
                </div>
                <div class="col-md-6">
                  <label for="thresholdMaxSubtotal" class="form-label">Maximum subtotal</label>
                  <input type="number" step="0.01" min="0" class="form-control" id="thresholdMaxSubtotal" name="max_subtotal" placeholder="Leave blank for no upper limit" />
                </div>
              </div>
              <div class="row g-3 mt-1">
                <div class="col-md-6">
                  <label for="thresholdFeeAmount" class="form-label">Wallet fee</label>
                  <input type="number" step="0.01" min="0" class="form-control" id="thresholdFeeAmount" name="fee_amount" required />
                </div>
                <div class="col-md-6">
                  <label for="thresholdStatus" class="form-label">Status</label>
                  <select class="form-select" id="thresholdStatus" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Threshold</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php } ?>

    <script src="assets/vendor/libs/jquery/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/vendor/libs/popper/popper.min.js"></script>
    <script src="assets/vendor/js/menu.min.js"></script>
    <script src="assets/js/main.js"></script>
    <?php if ($tableExists) { ?>
    <script>
      (function () {
        var modalTitle = document.getElementById('thresholdModalTitle');
        var thresholdId = document.getElementById('thresholdId');
        var thresholdLabel = document.getElementById('thresholdLabel');
        var thresholdMinSubtotal = document.getElementById('thresholdMinSubtotal');
        var thresholdMaxSubtotal = document.getElementById('thresholdMaxSubtotal');
        var thresholdFeeAmount = document.getElementById('thresholdFeeAmount');
        var thresholdStatus = document.getElementById('thresholdStatus');
        var newThresholdBtn = document.getElementById('newThresholdBtn');
        var editButtons = document.querySelectorAll('.edit-threshold-btn');
        var thresholdModal = document.getElementById('thresholdModal');

        function resetForm() {
          modalTitle.textContent = 'Add Wallet Fee Threshold';
          thresholdId.value = '0';
          thresholdLabel.value = '';
          thresholdMinSubtotal.value = '';
          thresholdMaxSubtotal.value = '';
          thresholdFeeAmount.value = '';
          thresholdStatus.value = 'active';
        }

        if (newThresholdBtn) {
          newThresholdBtn.addEventListener('click', resetForm);
        }

        editButtons.forEach(function (button) {
          button.addEventListener('click', function () {
            modalTitle.textContent = 'Edit Wallet Fee Threshold';
            thresholdId.value = this.getAttribute('data-id') || '0';
            thresholdLabel.value = this.getAttribute('data-label') || '';
            thresholdMinSubtotal.value = this.getAttribute('data-min') || '';
            thresholdMaxSubtotal.value = this.getAttribute('data-max') || '';
            thresholdFeeAmount.value = this.getAttribute('data-fee') || '';
            thresholdStatus.value = this.getAttribute('data-status') || 'active';
          });
        });

        if (thresholdModal) {
          thresholdModal.addEventListener('hidden.bs.modal', resetForm);
        }
      }());
    </script>
    <?php } ?>
  </body>
</html>
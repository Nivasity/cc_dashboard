<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = (int) ($_SESSION['nivas_adminRole'] ?? 0);
$allowedRoles = [1, 2, 3];

if (!$resource_mgt_menu || !in_array($admin_role, $allowedRoles, true)) {
  header('Location: /');
  exit();
}

function ccSystemAlertsTableExists($conn) {
  static $exists = null;

  if ($exists !== null) {
    return $exists;
  }

  $result = mysqli_query($conn, "SHOW TABLES LIKE 'system_alerts'");
  $exists = $result && mysqli_num_rows($result) > 0;

  return $exists;
}

function ccSystemAlertsSetFlash($type, $message) {
  $_SESSION['system_alerts_flash'] = [
    'type' => $type,
    'message' => $message,
  ];
}

function ccSystemAlertsGetFlash() {
  $flash = $_SESSION['system_alerts_flash'] ?? null;
  unset($_SESSION['system_alerts_flash']);
  return $flash;
}

function ccSystemAlertsNormalizeColor($value) {
  $value = strtolower(trim((string) $value));
  return in_array($value, ['red', 'green', 'info'], true) ? $value : 'red';
}

function ccSystemAlertsFormatInputDateTime($value) {
  if (!$value) {
    return '';
  }

  $timestamp = strtotime((string) $value);
  return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
}

function ccSystemAlertsFetchAll($conn) {
  $alerts = [];
  $sql = "SELECT id, title, message, alert_color, expiry_date, active, created_at
          FROM system_alerts
          ORDER BY active DESC, expiry_date ASC, id DESC";
  $result = mysqli_query($conn, $sql);

  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $alerts[] = $row;
    }
  }

  return $alerts;
}

function ccSystemAlertsFindById($conn, $alertId) {
  $alertId = (int) $alertId;
  if ($alertId <= 0) {
    return null;
  }

  $sql = "SELECT id, title, message, alert_color, expiry_date, active, created_at
          FROM system_alerts
          WHERE id = $alertId
          LIMIT 1";
  $result = mysqli_query($conn, $sql);
  if ($result && mysqli_num_rows($result) > 0) {
    return mysqli_fetch_assoc($result);
  }

  return null;
}

$tableExists = ccSystemAlertsTableExists($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$tableExists) {
    ccSystemAlertsSetFlash('danger', 'The system_alerts table is not available in this database yet. Run the migration first.');
    header('Location: system_alerts.php');
    exit();
  }

  $action = trim((string) ($_POST['action'] ?? ''));

  try {
    if ($action === 'save') {
      $alertId = (int) ($_POST['alert_id'] ?? 0);
      $title = trim((string) ($_POST['title'] ?? ''));
      $message = trim((string) ($_POST['message'] ?? ''));
      $alertColor = ccSystemAlertsNormalizeColor($_POST['alert_color'] ?? 'red');
      $expiryRaw = trim((string) ($_POST['expiry_date'] ?? ''));
      $active = isset($_POST['active']) ? 1 : 0;

      if ($title === '') {
        throw new Exception('Alert title is required.');
      }

      if ($message === '') {
        throw new Exception('Alert message is required.');
      }

      if (strlen($title) > 255) {
        $title = substr($title, 0, 255);
      }

      $expiryDate = DateTime::createFromFormat('Y-m-d\TH:i', $expiryRaw);
      if (!$expiryDate) {
        throw new Exception('Provide a valid expiry date and time.');
      }

      $expiryValue = $expiryDate->format('Y-m-d H:i:s');

      if ($alertId > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE system_alerts
                                      SET title = ?, message = ?, alert_color = ?, expiry_date = ?, active = ?
                                      WHERE id = ?
                                      LIMIT 1");
        if (!$stmt) {
          throw new Exception('Unable to prepare the alert update statement.');
        }

        mysqli_stmt_bind_param($stmt, 'ssssii', $title, $message, $alertColor, $expiryValue, $active, $alertId);
        if (!mysqli_stmt_execute($stmt)) {
          throw new Exception('Failed to update the system alert.');
        }
        mysqli_stmt_close($stmt);

        ccSystemAlertsSetFlash('success', 'System alert updated successfully.');
      } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO system_alerts (title, message, alert_color, expiry_date, active)
                                      VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
          throw new Exception('Unable to prepare the alert insert statement.');
        }

        mysqli_stmt_bind_param($stmt, 'ssssi', $title, $message, $alertColor, $expiryValue, $active);
        if (!mysqli_stmt_execute($stmt)) {
          throw new Exception('Failed to create the system alert.');
        }
        mysqli_stmt_close($stmt);

        ccSystemAlertsSetFlash('success', 'System alert created successfully.');
      }
    } elseif ($action === 'toggle') {
      $alertId = (int) ($_POST['alert_id'] ?? 0);
      if ($alertId <= 0) {
        throw new Exception('Select a valid system alert.');
      }

      $alert = ccSystemAlertsFindById($conn, $alertId);
      if (!$alert) {
        throw new Exception('The selected alert could not be found.');
      }

      $nextState = ((int) ($alert['active'] ?? 0) === 1) ? 0 : 1;
      $stmt = mysqli_prepare($conn, "UPDATE system_alerts SET active = ? WHERE id = ? LIMIT 1");
      if (!$stmt) {
        throw new Exception('Unable to prepare the alert status update.');
      }

      mysqli_stmt_bind_param($stmt, 'ii', $nextState, $alertId);
      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to update the alert status.');
      }
      mysqli_stmt_close($stmt);

      ccSystemAlertsSetFlash('success', $nextState === 1 ? 'System alert activated successfully.' : 'System alert deactivated successfully.');
    } elseif ($action === 'delete') {
      $alertId = (int) ($_POST['alert_id'] ?? 0);
      if ($alertId <= 0) {
        throw new Exception('Select a valid system alert to delete.');
      }

      $stmt = mysqli_prepare($conn, "DELETE FROM system_alerts WHERE id = ? LIMIT 1");
      if (!$stmt) {
        throw new Exception('Unable to prepare the alert delete statement.');
      }

      mysqli_stmt_bind_param($stmt, 'i', $alertId);
      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to delete the system alert.');
      }
      if (mysqli_stmt_affected_rows($stmt) === 0) {
        mysqli_stmt_close($stmt);
        throw new Exception('The selected alert was not found or has already been removed.');
      }
      mysqli_stmt_close($stmt);

      ccSystemAlertsSetFlash('success', 'System alert deleted successfully.');
    } else {
      throw new Exception('Unsupported action supplied.');
    }
  } catch (Throwable $e) {
    ccSystemAlertsSetFlash('danger', $e->getMessage());
  }

  header('Location: system_alerts.php');
  exit();
}

$flash = ccSystemAlertsGetFlash();
$alerts = $tableExists ? ccSystemAlertsFetchAll($conn) : [];
$editAlertId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editAlert = ($tableExists && $editAlertId > 0) ? ccSystemAlertsFindById($conn, $editAlertId) : null;

$activeCount = 0;
$inactiveCount = 0;
$expiredCount = 0;
$currentlyVisibleCount = 0;
$nowTs = time();

foreach ($alerts as $alert) {
  $isActive = (int) ($alert['active'] ?? 0) === 1;
  $expiryTs = strtotime((string) ($alert['expiry_date'] ?? ''));
  $isExpired = $expiryTs !== false && $expiryTs < $nowTs;

  if ($isActive) {
    $activeCount++;
  } else {
    $inactiveCount++;
  }

  if ($isExpired) {
    $expiredCount++;
  }

  if ($isActive && !$isExpired) {
    $currentlyVisibleCount++;
  }
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>System Alerts | Nivasity Command Center</title>
    <meta name="description" content="Create and manage system-wide alerts displayed across Nivasity." />
    <?php include('partials/_head.php') ?>
    <style>
      .system-alert-stat {
        border-radius: 1rem;
        border: 1px solid rgba(105, 108, 255, 0.1);
        background: #fff;
        padding: 1.1rem 1.2rem;
        box-shadow: 0 10px 24px rgba(67, 89, 113, 0.06);
      }

      .system-alert-stat .label {
        display: block;
        color: #8592a3;
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
      }

      .system-alert-stat .value {
        display: block;
        margin-top: 0.4rem;
        color: #566a7f;
        font-size: 1.7rem;
        font-weight: 800;
      }

      .system-alert-preview {
        border-radius: 0.85rem;
        border: 1px solid transparent;
        padding: 0.9rem 1rem;
      }

      .system-alert-preview.is-red {
        background: #fff1f2;
        border-color: #fecdd3;
        color: #b42318;
      }

      .system-alert-preview.is-green {
        background: #ecfdf3;
        border-color: #abefc6;
        color: #067647;
      }

      .system-alert-preview.is-info {
        background: #eff8ff;
        border-color: #b2ddff;
        color: #175cd3;
      }

      .system-alert-message {
        max-width: 520px;
        white-space: normal;
      }
    </style>
  </head>
  <body>
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <?php include('partials/_sidebar.php') ?>
        <div class="layout-page">
          <?php include('partials/_navbar.php') ?>
          <div class="content-wrapper">
            <div class="container-xxl flex-grow-1 container-p-y">
              <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Resources /</span> System Alerts</h4>

              <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars((string) ($flash['type'] ?? 'info')); ?> alert-dismissible" role="alert">
                  <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <?php if (!$tableExists): ?>
                <div class="alert alert-warning mb-4" role="alert">
                  The <strong>system_alerts</strong> table is not available in this database yet. Run the migration in [nivasity/sql/add_system_alerts.sql](nivasity/sql/add_system_alerts.sql) before using this page.
                </div>
              <?php endif; ?>

              <div class="row g-4 mb-4">
                <div class="col-md-3 col-sm-6">
                  <div class="system-alert-stat">
                    <span class="label">Total Alerts</span>
                    <span class="value"><?php echo count($alerts); ?></span>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="system-alert-stat">
                    <span class="label">Active</span>
                    <span class="value"><?php echo $activeCount; ?></span>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="system-alert-stat">
                    <span class="label">Visible Now</span>
                    <span class="value"><?php echo $currentlyVisibleCount; ?></span>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="system-alert-stat">
                    <span class="label">Expired</span>
                    <span class="value"><?php echo $expiredCount; ?></span>
                  </div>
                </div>
              </div>

              <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="mb-0"><?php echo $editAlert ? 'Edit System Alert' : 'Create System Alert'; ?></h5>
                  <?php if ($editAlert): ?>
                    <a href="system_alerts.php" class="btn btn-outline-secondary btn-sm">Cancel Edit</a>
                  <?php endif; ?>
                </div>
                <div class="card-body">
                  <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="alert_id" value="<?php echo (int) ($editAlert['id'] ?? 0); ?>">
                    <div class="col-md-6">
                      <label for="title" class="form-label">Title</label>
                      <input type="text" class="form-control" id="title" name="title" maxlength="255" value="<?php echo htmlspecialchars((string) ($editAlert['title'] ?? '')); ?>" required>
                    </div>
                    <div class="col-md-3">
                      <label for="alert_color" class="form-label">Color</label>
                      <select class="form-select" id="alert_color" name="alert_color">
                        <?php $selectedColor = ccSystemAlertsNormalizeColor($editAlert['alert_color'] ?? 'red'); ?>
                        <option value="red" <?php echo $selectedColor === 'red' ? 'selected' : ''; ?>>Red</option>
                        <option value="green" <?php echo $selectedColor === 'green' ? 'selected' : ''; ?>>Green</option>
                        <option value="info" <?php echo $selectedColor === 'info' ? 'selected' : ''; ?>>Info</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label for="expiry_date" class="form-label">Expiry Date</label>
                      <input type="datetime-local" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo htmlspecialchars(ccSystemAlertsFormatInputDateTime($editAlert['expiry_date'] ?? '')); ?>" required>
                    </div>
                    <div class="col-12">
                      <label for="message" class="form-label">Message</label>
                      <textarea class="form-control" id="message" name="message" rows="4" required><?php echo htmlspecialchars((string) ($editAlert['message'] ?? '')); ?></textarea>
                    </div>
                    <div class="col-12">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="active" name="active" <?php echo ((int) ($editAlert['active'] ?? 1) === 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="active">Show this alert immediately</label>
                      </div>
                    </div>
                    <div class="col-12">
                      <button type="submit" class="btn btn-primary"><?php echo $editAlert ? 'Update Alert' : 'Create Alert'; ?></button>
                    </div>
                  </form>
                </div>
              </div>

              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="mb-0">Existing Alerts</h5>
                  <span class="text-muted small">Live users only see alerts that are active and not expired.</span>
                </div>
                <div class="card-body">
                  <?php if (empty($alerts)): ?>
                    <div class="text-center py-5 text-muted">No system alerts have been created yet.</div>
                  <?php else: ?>
                    <div class="table-responsive text-nowrap">
                      <table class="table">
                        <thead class="table-light">
                          <tr>
                            <th>ID</th>
                            <th>Alert</th>
                            <th>Status</th>
                            <th>Expiry</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($alerts as $alert): ?>
                            <?php
                              $isActive = (int) ($alert['active'] ?? 0) === 1;
                              $expiryTs = strtotime((string) ($alert['expiry_date'] ?? ''));
                              $isExpired = $expiryTs !== false && $expiryTs < $nowTs;
                              $color = ccSystemAlertsNormalizeColor($alert['alert_color'] ?? 'red');
                            ?>
                            <tr>
                              <td><?php echo (int) $alert['id']; ?></td>
                              <td>
                                <div class="system-alert-preview is-<?php echo htmlspecialchars($color); ?>">
                                  <div class="fw-semibold mb-1"><?php echo htmlspecialchars((string) $alert['title']); ?></div>
                                  <div class="system-alert-message"><?php echo nl2br(htmlspecialchars((string) $alert['message'])); ?></div>
                                </div>
                              </td>
                              <td>
                                <?php if ($isActive): ?>
                                  <span class="badge bg-label-success mb-1">Active</span>
                                <?php else: ?>
                                  <span class="badge bg-label-secondary mb-1">Inactive</span>
                                <?php endif; ?>
                                <br>
                                <?php if ($isExpired): ?>
                                  <span class="badge bg-label-danger">Expired</span>
                                <?php else: ?>
                                  <span class="badge bg-label-info">Live Window</span>
                                <?php endif; ?>
                              </td>
                              <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime((string) $alert['expiry_date']))); ?></td>
                              <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime((string) $alert['created_at']))); ?></td>
                              <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                  <a href="system_alerts.php?edit=<?php echo (int) $alert['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                  <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="alert_id" value="<?php echo (int) $alert['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning"><?php echo $isActive ? 'Deactivate' : 'Activate'; ?></button>
                                  </form>
                                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this system alert?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="alert_id" value="<?php echo (int) $alert['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                  </form>
                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php include('partials/_footer.php') ?>
            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="assets/vendor/libs/jquery/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/vendor/libs/popper/popper.min.js"></script>
    <script src="assets/vendor/js/menu.min.js"></script>
    <script src="assets/js/main.js"></script>
  </body>
</html>
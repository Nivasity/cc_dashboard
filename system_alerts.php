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

function ccSystemAlertsIsAjaxRequest() {
  return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function ccSystemAlertsBuildPayload(array $alert, $nowTs = null) {
  if ($nowTs === null) {
    $nowTs = time();
  }

  $color = ccSystemAlertsNormalizeColor($alert['alert_color'] ?? 'red');
  $active = (int) ($alert['active'] ?? 0) === 1;
  $expiryTs = strtotime((string) ($alert['expiry_date'] ?? ''));
  $isExpired = $expiryTs !== false && $expiryTs < $nowTs;

  return [
    'id' => (int) ($alert['id'] ?? 0),
    'title' => (string) ($alert['title'] ?? ''),
    'message' => (string) ($alert['message'] ?? ''),
    'alert_color' => $color,
    'active' => $active ? 1 : 0,
    'is_expired' => $isExpired,
    'expiry_date' => (string) ($alert['expiry_date'] ?? ''),
    'expiry_input' => ccSystemAlertsFormatInputDateTime($alert['expiry_date'] ?? ''),
    'expiry_display' => !empty($alert['expiry_date']) ? date('M d, Y h:i A', strtotime((string) $alert['expiry_date'])) : '',
    'created_at' => (string) ($alert['created_at'] ?? ''),
    'created_display' => !empty($alert['created_at']) ? date('M d, Y h:i A', strtotime((string) $alert['created_at'])) : '',
  ];
}

function ccSystemAlertsBuildStatsSummary(array $alerts) {
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

  return [
    'total' => count($alerts),
    'active' => $activeCount,
    'inactive' => $inactiveCount,
    'expired' => $expiredCount,
    'visible_now' => $currentlyVisibleCount,
  ];
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
  $isAjaxRequest = ccSystemAlertsIsAjaxRequest();

  if (!$tableExists) {
    $message = 'The system_alerts table is not available in this database yet. Run the migration first.';
    if ($isAjaxRequest) {
      http_response_code(400);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode([
        'status' => 'error',
        'message' => $message,
      ]);
      exit();
    }

    ccSystemAlertsSetFlash('danger', $message);
    header('Location: system_alerts.php');
    exit();
  }

  $action = trim((string) ($_POST['action'] ?? ''));
  $response = [
    'status' => 'success',
    'message' => '',
  ];

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
      $savedAlertId = 0;

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

        $savedAlertId = $alertId;
        $response['message'] = 'System alert updated successfully.';
        $response['mode'] = 'update';
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
        $savedAlertId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $response['message'] = 'System alert created successfully.';
        $response['mode'] = 'create';
      }

      $savedAlert = ccSystemAlertsFindById($conn, $savedAlertId);
      if ($savedAlert) {
        $response['alert'] = ccSystemAlertsBuildPayload($savedAlert);
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

      $updatedAlert = ccSystemAlertsFindById($conn, $alertId);
      if ($updatedAlert) {
        $response['alert'] = ccSystemAlertsBuildPayload($updatedAlert);
      }
      $response['message'] = $nextState === 1 ? 'System alert activated successfully.' : 'System alert deactivated successfully.';
      $response['mode'] = 'toggle';
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

      $response['message'] = 'System alert deleted successfully.';
      $response['mode'] = 'delete';
      $response['deleted_id'] = $alertId;
    } else {
      throw new Exception('Unsupported action supplied.');
    }
  } catch (Throwable $e) {
    if ($isAjaxRequest) {
      http_response_code(400);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
      ]);
      exit();
    }

    ccSystemAlertsSetFlash('danger', $e->getMessage());
    header('Location: system_alerts.php');
    exit();
  }

  if ($isAjaxRequest) {
    header('Content-Type: application/json; charset=utf-8');
    $response['stats'] = ccSystemAlertsBuildStatsSummary(ccSystemAlertsFetchAll($conn));
    echo json_encode($response);
    exit();
  }

  if (($response['message'] ?? '') !== '') {
    ccSystemAlertsSetFlash('success', $response['message']);
  }

  header('Location: system_alerts.php');
  exit();
}

$flash = ccSystemAlertsGetFlash();
$alerts = $tableExists ? ccSystemAlertsFetchAll($conn) : [];
$statsSummary = ccSystemAlertsBuildStatsSummary($alerts);
$nowTs = time();
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

              <div id="systemAlertsFeedback"></div>

              <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars((string) ($flash['type'] ?? 'info')); ?> alert-dismissible" role="alert">
                  <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <?php if (!$tableExists): ?>
                <div class="alert alert-warning mb-4" role="alert">
                  The <strong>system_alerts</strong> table is not available in this database yet. Run the system alerts migration first.
                </div>
              <?php endif; ?>

              <div class="row g-4 mb-4">
                <div class="col-md-3 col-sm-6">
                  <div class="system-alert-stat">
                    <span class="label">Total Alerts</span>
                    <span class="value" id="system-alerts-total"><?php echo (int) ($statsSummary['total'] ?? 0); ?></span>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="system-alert-stat">
                    <span class="label">Active</span>
                    <span class="value" id="system-alerts-active"><?php echo (int) ($statsSummary['active'] ?? 0); ?></span>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="system-alert-stat">
                    <span class="label">Visible Now</span>
                    <span class="value" id="system-alerts-visible"><?php echo (int) ($statsSummary['visible_now'] ?? 0); ?></span>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="system-alert-stat">
                    <span class="label">Expired</span>
                    <span class="value" id="system-alerts-expired"><?php echo (int) ($statsSummary['expired'] ?? 0); ?></span>
                  </div>
                </div>
              </div>

              <div class="card mb-4">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                  <div>
                    <h5 class="mb-1">Create and manage system-wide alerts</h5>
                    <p class="text-muted mb-0">Use a modal form to publish new alerts without leaving the alerts table view.</p>
                  </div>
                  <button type="button" class="btn btn-primary" id="openCreateSystemAlert">
                    Create System Alert
                  </button>
                </div>
              </div>

              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="mb-0">Existing Alerts</h5>
                  <span class="text-muted small">Live users only see alerts that are active and not expired.</span>
                </div>
                <div class="card-body">
                  <div id="systemAlertsEmptyState" class="text-center py-5 text-muted<?php echo empty($alerts) ? '' : ' d-none'; ?>">No system alerts have been created yet.</div>
                  <div id="systemAlertsTableWrap" class="table-responsive text-nowrap<?php echo empty($alerts) ? ' d-none' : ''; ?>">
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
                        <tbody id="systemAlertsTableBody">
                          <?php foreach ($alerts as $alert): ?>
                            <?php
                              $isActive = (int) ($alert['active'] ?? 0) === 1;
                              $expiryTs = strtotime((string) ($alert['expiry_date'] ?? ''));
                              $isExpired = $expiryTs !== false && $expiryTs < $nowTs;
                              $color = ccSystemAlertsNormalizeColor($alert['alert_color'] ?? 'red');
                            ?>
                            <tr id="system-alert-row-<?php echo (int) $alert['id']; ?>">
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
                                  <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary js-edit-alert"
                                    data-alert-id="<?php echo (int) $alert['id']; ?>"
                                    data-alert-title="<?php echo htmlspecialchars((string) $alert['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-alert-message="<?php echo htmlspecialchars((string) $alert['message'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-alert-color="<?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-alert-expiry="<?php echo htmlspecialchars(ccSystemAlertsFormatInputDateTime($alert['expiry_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-alert-active="<?php echo $isActive ? '1' : '0'; ?>">
                                    Edit
                                  </button>
                                  <button type="button" class="btn btn-sm btn-outline-warning js-toggle-alert" data-alert-id="<?php echo (int) $alert['id']; ?>"><?php echo $isActive ? 'Deactivate' : 'Activate'; ?></button>
                                  <button type="button" class="btn btn-sm btn-outline-danger js-delete-alert" data-alert-id="<?php echo (int) $alert['id']; ?>">Delete</button>
                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                </div>
              </div>

              <div class="modal fade" id="systemAlertModal" tabindex="-1" aria-labelledby="systemAlertModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="systemAlertModalLabel">Create System Alert</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post" id="systemAlertForm">
                      <div class="modal-body">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="alert_id" id="system_alert_id" value="">
                        <div class="row g-3">
                          <div class="col-md-6">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" maxlength="255" value="" required>
                          </div>
                          <div class="col-md-3">
                            <label for="alert_color" class="form-label">Color</label>
                            <select class="form-select" id="alert_color" name="alert_color">
                              <option value="red" selected>Red</option>
                              <option value="green">Green</option>
                              <option value="info">Info</option>
                            </select>
                          </div>
                          <div class="col-md-3">
                            <label for="expiry_date" class="form-label">Expiry Date</label>
                            <input type="datetime-local" class="form-control" id="expiry_date" name="expiry_date" value="" required>
                          </div>
                          <div class="col-12">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                          </div>
                          <div class="col-12">
                            <div class="form-check form-switch">
                              <input class="form-check-input" type="checkbox" id="active" name="active" checked>
                              <label class="form-check-label" for="active">Show this alert immediately</label>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="systemAlertSubmitButton">Create Alert</button>
                      </div>
                    </form>
                  </div>
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
    <script>
      $(function () {
        var modalElement = document.getElementById('systemAlertModal');
        var alertModal = modalElement && window.bootstrap ? new bootstrap.Modal(modalElement) : null;
        var form = $('#systemAlertForm');
        var submitButton = $('#systemAlertSubmitButton');
        var feedbackWrap = $('#systemAlertsFeedback');
        var emptyState = $('#systemAlertsEmptyState');
        var tableWrap = $('#systemAlertsTableWrap');
        var tableBody = $('#systemAlertsTableBody');

        function escapeHtml(value) {
          return $('<div>').text(value == null ? '' : String(value)).html();
        }

        function nl2brHtml(value) {
          return escapeHtml(value).replace(/\n/g, '<br>');
        }

        function showFeedback(type, message) {
          feedbackWrap.html(
            '<div class="alert alert-' + type + ' alert-dismissible" role="alert">' +
              escapeHtml(message) +
              '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>'
          );
        }

        function updateStats(stats) {
          if (!stats) {
            return;
          }

          $('#system-alerts-total').text(stats.total || 0);
          $('#system-alerts-active').text(stats.active || 0);
          $('#system-alerts-visible').text(stats.visible_now || 0);
          $('#system-alerts-expired').text(stats.expired || 0);
        }

        function syncTableState() {
          var hasRows = tableBody.find('tr').length > 0;
          emptyState.toggleClass('d-none', hasRows);
          tableWrap.toggleClass('d-none', !hasRows);
        }

        function resetAlertForm() {
          if (form.length && form[0]) {
            form[0].reset();
          }
          $('#system_alert_id').val('');
          $('#alert_color').val('red');
          $('#active').prop('checked', true);
          $('#systemAlertModalLabel').text('Create System Alert');
          submitButton.text('Create Alert');
        }

        function fillAlertForm(button) {
          $('#system_alert_id').val(button.data('alert-id'));
          $('#title').val(button.data('alert-title'));
          $('#message').val(button.data('alert-message'));
          $('#alert_color').val(button.data('alert-color'));
          $('#expiry_date').val(button.data('alert-expiry'));
          $('#active').prop('checked', String(button.data('alert-active')) === '1');
          $('#systemAlertModalLabel').text('Edit System Alert');
          submitButton.text('Update Alert');
        }

        function renderAlertRow(alert) {
          var activeBadge = alert.active ? '<span class="badge bg-label-success mb-1">Active</span>' : '<span class="badge bg-label-secondary mb-1">Inactive</span>';
          var windowBadge = alert.is_expired ? '<span class="badge bg-label-danger">Expired</span>' : '<span class="badge bg-label-info">Live Window</span>';
          var toggleLabel = alert.active ? 'Deactivate' : 'Activate';

          return '' +
            '<tr id="system-alert-row-' + alert.id + '">' +
              '<td>' + alert.id + '</td>' +
              '<td>' +
                '<div class="system-alert-preview is-' + escapeHtml(alert.alert_color) + '">' +
                  '<div class="fw-semibold mb-1">' + escapeHtml(alert.title) + '</div>' +
                  '<div class="system-alert-message">' + nl2brHtml(alert.message) + '</div>' +
                '</div>' +
              '</td>' +
              '<td>' + activeBadge + '<br>' + windowBadge + '</td>' +
              '<td>' + escapeHtml(alert.expiry_display) + '</td>' +
              '<td>' + escapeHtml(alert.created_display) + '</td>' +
              '<td class="text-end">' +
                '<div class="d-inline-flex flex-wrap justify-content-end gap-2">' +
                  '<button type="button" class="btn btn-sm btn-outline-primary js-edit-alert"' +
                    ' data-alert-id="' + alert.id + '"' +
                    ' data-alert-title="' + escapeHtml(alert.title) + '"' +
                    ' data-alert-message="' + escapeHtml(alert.message) + '"' +
                    ' data-alert-color="' + escapeHtml(alert.alert_color) + '"' +
                    ' data-alert-expiry="' + escapeHtml(alert.expiry_input) + '"' +
                    ' data-alert-active="' + (alert.active ? '1' : '0') + '">Edit</button>' +
                  '<button type="button" class="btn btn-sm btn-outline-warning js-toggle-alert" data-alert-id="' + alert.id + '">' + toggleLabel + '</button>' +
                  '<button type="button" class="btn btn-sm btn-outline-danger js-delete-alert" data-alert-id="' + alert.id + '">Delete</button>' +
                '</div>' +
              '</td>' +
            '</tr>';
        }

        function saveAlertResponse(response) {
          if (!response.alert) {
            updateStats(response.stats);
            syncTableState();
            return;
          }

          var rowHtml = renderAlertRow(response.alert);
          var existingRow = $('#system-alert-row-' + response.alert.id);
          if (existingRow.length) {
            existingRow.replaceWith(rowHtml);
          } else {
            tableBody.prepend(rowHtml);
          }

          updateStats(response.stats);
          syncTableState();
        }

        $('#openCreateSystemAlert').on('click', function () {
          resetAlertForm();
          if (alertModal) {
            alertModal.show();
          }
        });

        $(document).on('click', '.js-edit-alert', function () {
          resetAlertForm();
          fillAlertForm($(this));
          if (alertModal) {
            alertModal.show();
          }
        });

        form.on('submit', function (event) {
          event.preventDefault();

          var originalText = submitButton.text();
          submitButton.prop('disabled', true).text('Saving...');

          $.ajax({
            url: 'system_alerts.php',
            type: 'POST',
            dataType: 'json',
            data: form.serialize(),
            success: function (response) {
              saveAlertResponse(response);
              showFeedback('success', response.message || 'System alert saved successfully.');
              if (alertModal) {
                alertModal.hide();
              }
              resetAlertForm();
            },
            error: function (xhr) {
              var response = xhr.responseJSON || {};
              showFeedback('danger', response.message || 'Unable to save the system alert right now.');
            },
            complete: function () {
              submitButton.prop('disabled', false).text(originalText);
            }
          });
        });

        $(document).on('click', '.js-toggle-alert', function () {
          var button = $(this);
          var originalText = button.text();
          button.prop('disabled', true).text('Working...');

          $.ajax({
            url: 'system_alerts.php',
            type: 'POST',
            dataType: 'json',
            data: {
              action: 'toggle',
              alert_id: button.data('alert-id')
            },
            success: function (response) {
              saveAlertResponse(response);
              showFeedback('success', response.message || 'System alert updated successfully.');
            },
            error: function (xhr) {
              var response = xhr.responseJSON || {};
              showFeedback('danger', response.message || 'Unable to update the system alert status right now.');
            },
            complete: function () {
              button.prop('disabled', false).text(originalText);
            }
          });
        });

        $(document).on('click', '.js-delete-alert', function () {
          var button = $(this);
          if (!window.confirm('Delete this system alert?')) {
            return;
          }

          var originalText = button.text();
          button.prop('disabled', true).text('Deleting...');

          $.ajax({
            url: 'system_alerts.php',
            type: 'POST',
            dataType: 'json',
            data: {
              action: 'delete',
              alert_id: button.data('alert-id')
            },
            success: function (response) {
              if (response.deleted_id) {
                $('#system-alert-row-' + response.deleted_id).remove();
              }
              updateStats(response.stats);
              syncTableState();
              showFeedback('success', response.message || 'System alert deleted successfully.');
            },
            error: function (xhr) {
              var response = xhr.responseJSON || {};
              showFeedback('danger', response.message || 'Unable to delete the system alert right now.');
            },
            complete: function () {
              button.prop('disabled', false).text(originalText);
            }
          });
        });

        if (modalElement) {
          modalElement.addEventListener('hidden.bs.modal', function () {
            resetAlertForm();
          });
        }
      });
    </script>
  </body>
</html>
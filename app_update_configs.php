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

function ccAppUpdateConfigsTableExists($conn) {
  static $exists = null;

  if ($exists !== null) {
    return $exists;
  }

  $result = mysqli_query($conn, "SHOW TABLES LIKE 'app_update_configs'");
  $exists = $result && mysqli_num_rows($result) > 0;

  return $exists;
}

function ccAppUpdateConfigsSetFlash($type, $message) {
  $_SESSION['app_update_configs_flash'] = [
    'type' => $type,
    'message' => $message,
  ];
}

function ccAppUpdateConfigsGetFlash() {
  $flash = $_SESSION['app_update_configs_flash'] ?? null;
  unset($_SESSION['app_update_configs_flash']);
  return $flash;
}

function ccAppUpdateConfigsTrimmed($value, $maxLength = 0) {
  $value = trim((string) $value);
  if ($maxLength > 0 && strlen($value) > $maxLength) {
    return substr($value, 0, $maxLength);
  }

  return $value;
}

function ccAppUpdateConfigsValidateUrl($value, $label) {
  if (!filter_var($value, FILTER_VALIDATE_URL)) {
    throw new Exception($label . ' must be a valid URL.');
  }
}

function ccAppUpdateConfigsFetchAll($conn) {
  $rows = [];
  $sql = "SELECT * FROM app_update_configs ORDER BY id DESC";
  $result = mysqli_query($conn, $sql);

  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $rows[] = $row;
    }
  }

  return $rows;
}

function ccAppUpdateConfigsFindById($conn, $configId) {
  $configId = (int) $configId;
  if ($configId <= 0) {
    return null;
  }

  $sql = "SELECT * FROM app_update_configs WHERE id = $configId LIMIT 1";
  $result = mysqli_query($conn, $sql);
  if ($result && mysqli_num_rows($result) > 0) {
    return mysqli_fetch_assoc($result);
  }

  return null;
}

function ccAppUpdateConfigsSummary(array $configs) {
  $latest = $configs[0] ?? null;

  return [
    'total' => count($configs),
    'latest_android_version' => $latest['android_latest_version'] ?? '--',
    'latest_ios_version' => $latest['ios_latest_version'] ?? '--',
    'android_required' => isset($latest['android_required']) && (int) $latest['android_required'] === 1,
    'ios_required' => isset($latest['ios_required']) && (int) $latest['ios_required'] === 1,
  ];
}

$tableExists = ccAppUpdateConfigsTableExists($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$tableExists) {
    ccAppUpdateConfigsSetFlash('danger', 'The app_update_configs table is not available yet. Run the migration first.');
    header('Location: app_update_configs.php');
    exit();
  }

  $action = trim((string) ($_POST['action'] ?? ''));

  try {
    if ($action === 'save') {
      $configId = (int) ($_POST['config_id'] ?? 0);

      $androidLatestVersion = ccAppUpdateConfigsTrimmed($_POST['android_latest_version'] ?? '', 50);
      $androidMinimumVersion = ccAppUpdateConfigsTrimmed($_POST['android_minimum_version'] ?? '', 50);
      $androidStoreUrl = ccAppUpdateConfigsTrimmed($_POST['android_store_url'] ?? '', 500);
      $androidTitle = ccAppUpdateConfigsTrimmed($_POST['android_title'] ?? '', 255);
      $androidMessage = trim((string) ($_POST['android_message'] ?? ''));
      $androidRequired = isset($_POST['android_required']) ? 1 : 0;

      $iosLatestVersion = ccAppUpdateConfigsTrimmed($_POST['ios_latest_version'] ?? '', 50);
      $iosMinimumVersion = ccAppUpdateConfigsTrimmed($_POST['ios_minimum_version'] ?? '', 50);
      $iosStoreUrl = ccAppUpdateConfigsTrimmed($_POST['ios_store_url'] ?? '', 500);
      $iosTitle = ccAppUpdateConfigsTrimmed($_POST['ios_title'] ?? '', 255);
      $iosMessage = trim((string) ($_POST['ios_message'] ?? ''));
      $iosRequired = isset($_POST['ios_required']) ? 1 : 0;

      if ($androidLatestVersion === '' || $androidMinimumVersion === '' || $androidStoreUrl === '' || $androidTitle === '' || $androidMessage === '') {
        throw new Exception('All Android fields are required.');
      }

      if ($iosLatestVersion === '' || $iosMinimumVersion === '' || $iosStoreUrl === '' || $iosTitle === '' || $iosMessage === '') {
        throw new Exception('All iOS fields are required.');
      }

      ccAppUpdateConfigsValidateUrl($androidStoreUrl, 'Android store URL');
      ccAppUpdateConfigsValidateUrl($iosStoreUrl, 'iOS store URL');

      if ($configId > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE app_update_configs
                                      SET android_latest_version = ?, android_minimum_version = ?, android_store_url = ?, android_title = ?, android_message = ?, android_required = ?,
                                          ios_latest_version = ?, ios_minimum_version = ?, ios_store_url = ?, ios_title = ?, ios_message = ?, ios_required = ?
                                      WHERE id = ?
                                      LIMIT 1");
        if (!$stmt) {
          throw new Exception('Unable to prepare the app update configuration update.');
        }

        mysqli_stmt_bind_param(
          $stmt,
          'sssssisssssii',
          $androidLatestVersion,
          $androidMinimumVersion,
          $androidStoreUrl,
          $androidTitle,
          $androidMessage,
          $androidRequired,
          $iosLatestVersion,
          $iosMinimumVersion,
          $iosStoreUrl,
          $iosTitle,
          $iosMessage,
          $iosRequired,
          $configId
        );

        if (!mysqli_stmt_execute($stmt)) {
          throw new Exception('Failed to update the app update configuration.');
        }
        mysqli_stmt_close($stmt);

        ccAppUpdateConfigsSetFlash('success', 'App update configuration updated successfully.');
      } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO app_update_configs
                                      (android_latest_version, android_minimum_version, android_store_url, android_title, android_message, android_required,
                                       ios_latest_version, ios_minimum_version, ios_store_url, ios_title, ios_message, ios_required)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
          throw new Exception('Unable to prepare the app update configuration insert.');
        }

        mysqli_stmt_bind_param(
          $stmt,
          'sssssisssssi',
          $androidLatestVersion,
          $androidMinimumVersion,
          $androidStoreUrl,
          $androidTitle,
          $androidMessage,
          $androidRequired,
          $iosLatestVersion,
          $iosMinimumVersion,
          $iosStoreUrl,
          $iosTitle,
          $iosMessage,
          $iosRequired
        );

        if (!mysqli_stmt_execute($stmt)) {
          throw new Exception('Failed to create the app update configuration.');
        }
        mysqli_stmt_close($stmt);

        ccAppUpdateConfigsSetFlash('success', 'App update configuration created successfully.');
      }
    } elseif ($action === 'delete') {
      $configId = (int) ($_POST['config_id'] ?? 0);
      if ($configId <= 0) {
        throw new Exception('Select a valid app update configuration to delete.');
      }

      $stmt = mysqli_prepare($conn, "DELETE FROM app_update_configs WHERE id = ? LIMIT 1");
      if (!$stmt) {
        throw new Exception('Unable to prepare the app update configuration delete.');
      }

      mysqli_stmt_bind_param($stmt, 'i', $configId);
      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to delete the app update configuration.');
      }
      mysqli_stmt_close($stmt);

      ccAppUpdateConfigsSetFlash('success', 'App update configuration deleted successfully.');
    } else {
      throw new Exception('Unsupported action supplied.');
    }
  } catch (Throwable $e) {
    ccAppUpdateConfigsSetFlash('danger', $e->getMessage());
  }

  header('Location: app_update_configs.php');
  exit();
}

$flash = ccAppUpdateConfigsGetFlash();
$configs = $tableExists ? ccAppUpdateConfigsFetchAll($conn) : [];
$summary = ccAppUpdateConfigsSummary($configs);
$editingConfig = null;

if ($tableExists && isset($_GET['edit'])) {
  $editingConfig = ccAppUpdateConfigsFindById($conn, (int) $_GET['edit']);
}

$formValues = [
  'config_id' => $editingConfig['id'] ?? '',
  'android_latest_version' => $editingConfig['android_latest_version'] ?? '1.0.1',
  'android_minimum_version' => $editingConfig['android_minimum_version'] ?? '1.0.0',
  'android_store_url' => $editingConfig['android_store_url'] ?? 'https://play.google.com/store/apps/details?id=com.nivasity.app',
  'android_title' => $editingConfig['android_title'] ?? 'Update available',
  'android_message' => $editingConfig['android_message'] ?? 'A newer version is available.',
  'android_required' => isset($editingConfig['android_required']) ? (int) $editingConfig['android_required'] === 1 : false,
  'ios_latest_version' => $editingConfig['ios_latest_version'] ?? '1.0.1',
  'ios_minimum_version' => $editingConfig['ios_minimum_version'] ?? '1.0.0',
  'ios_store_url' => $editingConfig['ios_store_url'] ?? 'https://apps.apple.com/app/id1234567890',
  'ios_title' => $editingConfig['ios_title'] ?? 'Update available',
  'ios_message' => $editingConfig['ios_message'] ?? 'A newer version is available.',
  'ios_required' => isset($editingConfig['ios_required']) ? (int) $editingConfig['ios_required'] === 1 : false,
];
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>App Update Configs | Nivasity Command Center</title>
    <meta name="description" content="Manage Android and iOS app update prompt configuration for the public API." />
    <?php include('partials/_head.php') ?>
    <style>
      .app-update-stat {
        border-radius: 1rem;
        border: 1px solid rgba(105, 108, 255, 0.1);
        background: #fff;
        padding: 1.1rem 1.2rem;
        box-shadow: 0 10px 24px rgba(67, 89, 113, 0.06);
      }

      .app-update-stat .label {
        display: block;
        color: #8592a3;
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
      }

      .app-update-stat .value {
        display: block;
        margin-top: 0.4rem;
        color: #566a7f;
        font-size: 1.5rem;
        font-weight: 800;
      }

      .app-platform-card {
        border: 1px solid #e7e7ff;
        border-radius: 1rem;
        background: #fcfcff;
        padding: 1rem;
        height: 100%;
      }

      .app-platform-card h6 {
        font-size: 0.95rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #566a7f;
      }

      .config-message-preview {
        white-space: normal;
        max-width: 420px;
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
              <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Resources /</span> App Update Configs</h4>

              <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars((string) ($flash['type'] ?? 'info')); ?> alert-dismissible" role="alert">
                  <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <?php if (!$tableExists): ?>
                <div class="alert alert-warning mb-4" role="alert">
                  The <strong>app_update_configs</strong> table is not available in this database yet. Run the migration first.
                </div>
              <?php endif; ?>

              <div class="row g-4 mb-4">
                <div class="col-md-3 col-sm-6">
                  <div class="app-update-stat">
                    <span class="label">Total Records</span>
                    <span class="value"><?php echo (int) ($summary['total'] ?? 0); ?></span>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="app-update-stat">
                    <span class="label">Latest Android</span>
                    <span class="value"><?php echo htmlspecialchars((string) ($summary['latest_android_version'] ?? '--')); ?></span>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="app-update-stat">
                    <span class="label">Latest iOS</span>
                    <span class="value"><?php echo htmlspecialchars((string) ($summary['latest_ios_version'] ?? '--')); ?></span>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="app-update-stat">
                    <span class="label">Forced Update</span>
                    <span class="value"><?php echo (($summary['android_required'] ?? false) || ($summary['ios_required'] ?? false)) ? 'Yes' : 'No'; ?></span>
                  </div>
                </div>
              </div>

              <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <div>
                    <h5 class="mb-1"><?php echo $editingConfig ? 'Edit app update configuration' : 'Create a new app update configuration'; ?></h5>
                    <p class="text-muted mb-0">The public API returns the newest record only, so the latest entry becomes the live app update prompt.</p>
                  </div>
                  <?php if ($editingConfig): ?>
                    <a href="app_update_configs.php" class="btn btn-outline-secondary">Cancel Edit</a>
                  <?php endif; ?>
                </div>
                <div class="card-body">
                  <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="config_id" value="<?php echo htmlspecialchars((string) $formValues['config_id']); ?>">
                    <div class="row g-4">
                      <div class="col-lg-6">
                        <div class="app-platform-card">
                          <h6 class="mb-3">Android</h6>
                          <div class="row g-3">
                            <div class="col-md-6">
                              <label class="form-label" for="android_latest_version">Latest Version</label>
                              <input type="text" class="form-control" id="android_latest_version" name="android_latest_version" maxlength="50" value="<?php echo htmlspecialchars((string) $formValues['android_latest_version']); ?>" required>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="android_minimum_version">Minimum Version</label>
                              <input type="text" class="form-control" id="android_minimum_version" name="android_minimum_version" maxlength="50" value="<?php echo htmlspecialchars((string) $formValues['android_minimum_version']); ?>" required>
                            </div>
                            <div class="col-12">
                              <label class="form-label" for="android_store_url">Store URL</label>
                              <input type="url" class="form-control" id="android_store_url" name="android_store_url" maxlength="500" value="<?php echo htmlspecialchars((string) $formValues['android_store_url']); ?>" required>
                            </div>
                            <div class="col-12">
                              <label class="form-label" for="android_title">Popup Title</label>
                              <input type="text" class="form-control" id="android_title" name="android_title" maxlength="255" value="<?php echo htmlspecialchars((string) $formValues['android_title']); ?>" required>
                            </div>
                            <div class="col-12">
                              <label class="form-label" for="android_message">Popup Message</label>
                              <textarea class="form-control" id="android_message" name="android_message" rows="4" required><?php echo htmlspecialchars((string) $formValues['android_message']); ?></textarea>
                            </div>
                            <div class="col-12">
                              <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="android_required" name="android_required" <?php echo !empty($formValues['android_required']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="android_required">Require Android update</label>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="col-lg-6">
                        <div class="app-platform-card">
                          <h6 class="mb-3">iOS</h6>
                          <div class="row g-3">
                            <div class="col-md-6">
                              <label class="form-label" for="ios_latest_version">Latest Version</label>
                              <input type="text" class="form-control" id="ios_latest_version" name="ios_latest_version" maxlength="50" value="<?php echo htmlspecialchars((string) $formValues['ios_latest_version']); ?>" required>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="ios_minimum_version">Minimum Version</label>
                              <input type="text" class="form-control" id="ios_minimum_version" name="ios_minimum_version" maxlength="50" value="<?php echo htmlspecialchars((string) $formValues['ios_minimum_version']); ?>" required>
                            </div>
                            <div class="col-12">
                              <label class="form-label" for="ios_store_url">Store URL</label>
                              <input type="url" class="form-control" id="ios_store_url" name="ios_store_url" maxlength="500" value="<?php echo htmlspecialchars((string) $formValues['ios_store_url']); ?>" required>
                            </div>
                            <div class="col-12">
                              <label class="form-label" for="ios_title">Popup Title</label>
                              <input type="text" class="form-control" id="ios_title" name="ios_title" maxlength="255" value="<?php echo htmlspecialchars((string) $formValues['ios_title']); ?>" required>
                            </div>
                            <div class="col-12">
                              <label class="form-label" for="ios_message">Popup Message</label>
                              <textarea class="form-control" id="ios_message" name="ios_message" rows="4" required><?php echo htmlspecialchars((string) $formValues['ios_message']); ?></textarea>
                            </div>
                            <div class="col-12">
                              <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="ios_required" name="ios_required" <?php echo !empty($formValues['ios_required']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="ios_required">Require iOS update</label>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                      <button type="submit" class="btn btn-primary"><?php echo $editingConfig ? 'Update Configuration' : 'Create Configuration'; ?></button>
                      <?php if ($editingConfig): ?>
                        <a href="app_update_configs.php" class="btn btn-outline-secondary">Cancel</a>
                      <?php endif; ?>
                    </div>
                  </form>
                </div>
              </div>

              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="mb-0">Saved Configurations</h5>
                  <span class="text-muted small">The row with the highest ID is currently used by <code>/app/update-config.php</code>.</span>
                </div>
                <div class="card-body">
                  <?php if (empty($configs)): ?>
                    <div class="text-center py-5 text-muted">No app update configurations have been created yet.</div>
                  <?php else: ?>
                    <div class="table-responsive text-nowrap">
                      <table class="table">
                        <thead class="table-light">
                          <tr>
                            <th>ID</th>
                            <th>Android</th>
                            <th>iOS</th>
                            <th>Created</th>
                            <th>Live</th>
                            <th class="text-end">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($configs as $index => $config): ?>
                            <?php $isLatest = $index === 0; ?>
                            <tr>
                              <td><?php echo (int) $config['id']; ?></td>
                              <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) $config['android_latest_version']); ?></div>
                                <div class="text-muted small">Min: <?php echo htmlspecialchars((string) $config['android_minimum_version']); ?></div>
                                <div class="config-message-preview small mt-1"><?php echo htmlspecialchars((string) $config['android_title']); ?>: <?php echo htmlspecialchars((string) $config['android_message']); ?></div>
                                <?php if ((int) ($config['android_required'] ?? 0) === 1): ?>
                                  <span class="badge bg-label-danger mt-2">Required</span>
                                <?php else: ?>
                                  <span class="badge bg-label-info mt-2">Optional</span>
                                <?php endif; ?>
                              </td>
                              <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) $config['ios_latest_version']); ?></div>
                                <div class="text-muted small">Min: <?php echo htmlspecialchars((string) $config['ios_minimum_version']); ?></div>
                                <div class="config-message-preview small mt-1"><?php echo htmlspecialchars((string) $config['ios_title']); ?>: <?php echo htmlspecialchars((string) $config['ios_message']); ?></div>
                                <?php if ((int) ($config['ios_required'] ?? 0) === 1): ?>
                                  <span class="badge bg-label-danger mt-2">Required</span>
                                <?php else: ?>
                                  <span class="badge bg-label-info mt-2">Optional</span>
                                <?php endif; ?>
                              </td>
                              <td><?php echo !empty($config['created_at']) ? htmlspecialchars(date('M d, Y h:i A', strtotime((string) $config['created_at']))) : '--'; ?></td>
                              <td>
                                <?php if ($isLatest): ?>
                                  <span class="badge bg-label-success">Latest API Record</span>
                                <?php else: ?>
                                  <span class="badge bg-label-secondary">History</span>
                                <?php endif; ?>
                              </td>
                              <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                  <a href="app_update_configs.php?edit=<?php echo (int) $config['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                  <form method="post" onsubmit="return confirm('Delete this app update configuration?');" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="config_id" value="<?php echo (int) $config['id']; ?>">
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
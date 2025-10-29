<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = $_SESSION['nivas_adminRole'];
if (!in_array((int) $admin_role, [1, 2], true)) {
  header('Location: index.php');
  exit();
}

$admins = [];
$admin_query = mysqli_query($conn, "SELECT id, first_name, last_name, email FROM admins ORDER BY first_name, last_name");
if ($admin_query) {
  while ($row = mysqli_fetch_assoc($admin_query)) {
    $admins[] = $row;
  }
}

$actions = [];
$action_query = mysqli_query($conn, "SELECT DISTINCT action FROM audit_logs ORDER BY action");
if ($action_query) {
  while ($row = mysqli_fetch_assoc($action_query)) {
    if (!empty($row['action'])) {
      $actions[] = $row['action'];
    }
  }
}

$entities = [];
$entity_query = mysqli_query($conn, "SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type");
if ($entity_query) {
  while ($row = mysqli_fetch_assoc($entity_query)) {
    if (!empty($row['entity_type'])) {
      $entities[] = $row['entity_type'];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Audit Logs | Nivasity Command Center</title>
  <meta name="description" content="" />
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
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">System /</span> Audit Logs</h4>
            <div class="card mb-4">
              <div class="card-body">
                <form id="auditFilterForm" class="row g-3 align-items-end">
                  <div class="col-sm-6 col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" />
                  </div>
                  <div class="col-sm-6 col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" />
                  </div>
                  <div class="col-sm-6 col-md-3">
                    <label for="admin_id" class="form-label">Administrator</label>
                    <select id="admin_id" name="admin_id" class="form-select">
                      <option value="">All Admins</option>
                      <?php foreach ($admins as $admin) { ?>
                        <option value="<?php echo (int) $admin['id']; ?>"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-sm-6 col-md-3">
                    <label for="action" class="form-label">Action</label>
                    <select id="action" name="action" class="form-select">
                      <option value="">All Actions</option>
                      <?php foreach ($actions as $action) { ?>
                        <option value="<?php echo htmlspecialchars($action); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $action))); ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-sm-6 col-md-3">
                    <label for="entity_type" class="form-label">Entity</label>
                    <select id="entity_type" name="entity_type" class="form-select">
                      <option value="">All Entities</option>
                      <?php foreach ($entities as $entity) { ?>
                        <option value="<?php echo htmlspecialchars($entity); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $entity))); ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-sm-6 col-md-3">
                    <label for="limit" class="form-label">Max Records</label>
                    <input type="number" id="limit" name="limit" class="form-control" value="200" min="10" max="1000" />
                  </div>
                  <div class="col-12">
                    <button type="submit" id="refreshAuditLogs" class="btn btn-secondary me-2">Search</button>
                    <button type="button" id="resetAuditFilters" class="btn btn-outline-secondary">Reset</button>
                  </div>
                </form>
              </div>
            </div>
            <div class="card">
              <div class="card-body">
                <div class="table-responsive text-nowrap">
                  <table class="table audit-table">
                    <thead class="table-secondary">
                      <tr>
                        <th>Timestamp</th>
                        <th>Administrator</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Entity ID</th>
                        <th>Details</th>
                        <th>IP Address</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
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
  <script src="assets/vendor/libs/jquery/jquery.js"></script>
  <script src="assets/vendor/js/bootstrap.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="assets/vendor/libs/popper/popper.js"></script>
  <script src="assets/vendor/js/menu.js"></script>
  <script src="assets/js/ui-toasts.js"></script>
  <script src="assets/js/main.js"></script>
  <script src="model/functions/audit_logs.js"></script>
</body>
</html>

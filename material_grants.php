<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : null;
if ($admin_role === null || $admin_role !== 6) {
  header('Location: index.php');
  exit();
}

$admin_school = isset($admin_['school']) ? (int) $admin_['school'] : 0;
$admin_faculty = isset($admin_['faculty']) ? (int) $admin_['faculty'] : 0;
$admin_scope_ready = ($admin_school > 0 && $admin_faculty > 0);
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-navbar-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Material Grants | Nivasity Command Center</title>
  <meta name="description" content="" />
  <?php include('partials/_head.php') ?>
</head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-page">
      <?php include('partials/_navbar.php') ?>
      <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">
          <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Grant Management /</span> Material Grants</h4>

          <?php if (!$admin_scope_ready) { ?>
            <div class="card">
              <div class="card-body">
                <div class="alert alert-warning mb-0">
                  This account is missing school/faculty assignment. Contact a super admin to assign both fields for Role 6.
                </div>
              </div>
            </div>
          <?php } else { ?>
            <div class="card">
              <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                  <h5 class="mb-0">Downloaded Materials</h5>
                  <small class="text-muted">Manage and grant downloaded material exports</small>
                </div>
                <div class="mt-3 mt-md-0">
                  <select id="statusFilter" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" selected>Pending</option>
                    <option value="granted">Granted</option>
                  </select>
                </div>
              </div>
              <div class="card-body">
                <div class="table-responsive text-nowrap">
                  <table class="table" id="grantsTable">
                    <thead class="table-secondary">
                      <tr>
                        <th>Export Code</th>
                        <th>Material</th>
                        <th>HOC Name</th>
                        <th>Students Count</th>
                        <th>Total Amount</th>
                        <th>Downloaded At</th>
                        <th>Status</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody class="table-border-bottom-0"></tbody>
                  </table>
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

  <?php if ($admin_scope_ready) { ?>
  <div class="modal fade" id="grantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Confirm Grant</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to grant this material export?</p>
          <div id="grantDetails"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="confirmGrant">Grant</button>
        </div>
      </div>
    </div>
  </div>
  <?php } ?>

  <script src="assets/vendor/libs/jquery/jquery.js"></script>
  <script src="assets/vendor/js/bootstrap.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="assets/vendor/libs/popper/popper.js"></script>
  <script src="assets/vendor/js/menu.js"></script>
  <script src="assets/js/ui-toasts.js"></script>
  <script src="assets/js/main.js"></script>
  <script>
    let table;
    let currentExportId = null;
    const grantScopeReady = <?php echo $admin_scope_ready ? 'true' : 'false'; ?>;

    $(document).ready(function() {
      if (!grantScopeReady) {
        return;
      }

      table = $('#grantsTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
          url: 'model/material_grants.php?action=list',
          data: function(d) {
            d.status = $('#statusFilter').val();
          }
        },
        columns: [
          { data: 'code' },
          {
            data: null,
            render: function(data) {
              return data.manual_title + ' (' + data.manual_code + ')';
            }
          },
          {
            data: null,
            render: function(data) {
              return data.hoc_first_name + ' ' + data.hoc_last_name;
            }
          },
          { data: 'students_count' },
          {
            data: 'total_amount',
            render: function(data) {
              return 'NGN ' + parseInt(data, 10).toLocaleString();
            }
          },
          {
            data: 'downloaded_at',
            render: function(data) {
              const date = new Date(data);
              return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
            }
          },
          {
            data: 'grant_status',
            render: function(data, type, row) {
              if (data === 'granted') {
                const grantedDate = row.granted_at ? new Date(row.granted_at).toLocaleDateString() : '';
                return '<span class="badge bg-success">Granted</span><br><small class="text-muted">' + grantedDate + '</small>';
              }
              return '<span class="badge bg-warning">Pending</span>';
            }
          },
          {
            data: null,
            orderable: false,
            render: function(data) {
              if (data.grant_status === 'pending') {
                return '<button class="btn btn-sm btn-primary grant-btn" data-id="' + data.id + '" data-code="' + data.code + '">Grant</button>';
              }
              return '<span class="text-muted">Granted</span>';
            }
          }
        ],
        order: [[5, 'desc']]
      });

      $('#statusFilter').on('change', function() {
        table.ajax.reload();
      });

      $('#grantsTable').on('click', '.grant-btn', function() {
        currentExportId = $(this).data('id');
        const code = $(this).data('code');
        $('#grantDetails').html('<strong>Export Code:</strong> ' + code);
        $('#grantModal').modal('show');
      });

      $('#confirmGrant').on('click', function() {
        if (!currentExportId) return;

        $.ajax({
          url: 'model/material_grants.php?action=grant',
          method: 'POST',
          data: { export_id: currentExportId },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              $('#grantModal').modal('hide');
              table.ajax.reload();
              showGrantToast('Success', response.message || 'Material export granted successfully', 'success');
            } else {
              showGrantToast('Error', response.message || 'Failed to grant export', 'error');
            }
          },
          error: function(xhr) {
            const response = xhr.responseJSON || {};
            showGrantToast('Error', response.message || 'An error occurred while granting the export', 'error');
          }
        });
      });

      function showGrantToast(title, message, type) {
        const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
        const toast = $('<div class="bs-toast toast fade show ' + bgClass + '" role="alert" aria-live="assertive" aria-atomic="true">' +
          '<div class="toast-header">' +
          '<i class="bx bx-bell me-2"></i>' +
          '<div class="me-auto fw-semibold">' + title + '</div>' +
          '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>' +
          '</div>' +
          '<div class="toast-body">' + message + '</div>' +
          '</div>');

        $('body').append(toast);
        setTimeout(function() {
          toast.remove();
        }, 3000);
      }
    });
  </script>
</body>

</html>

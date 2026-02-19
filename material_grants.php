<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = $_SESSION['nivas_adminRole'];

// Only allow admin role 6 to access this page
if ($admin_role != 6) {
  header('Location: index.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Material Grants | Nivasity Command Center</title>
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
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Material Grants /</span> Grant Management</h4>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
              <div class="col-lg-4 col-md-6 mb-4">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex justify-content-between">
                      <div class="card-info">
                        <p class="card-text">Total Materials</p>
                        <div class="d-flex align-items-end mb-2">
                          <h4 class="mb-0 me-2" id="totalCount">0</h4>
                        </div>
                      </div>
                      <div class="card-icon">
                        <span class="badge bg-label-primary rounded p-2">
                          <i class="bx bx-book bx-sm"></i>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4 col-md-6 mb-4">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex justify-content-between">
                      <div class="card-info">
                        <p class="card-text">Pending Grants</p>
                        <div class="d-flex align-items-end mb-2">
                          <h4 class="mb-0 me-2" id="pendingCount">0</h4>
                        </div>
                      </div>
                      <div class="card-icon">
                        <span class="badge bg-label-warning rounded p-2">
                          <i class="bx bx-time bx-sm"></i>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4 col-md-6 mb-4">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex justify-content-between">
                      <div class="card-info">
                        <p class="card-text">Granted</p>
                        <div class="d-flex align-items-end mb-2">
                          <h4 class="mb-0 me-2" id="grantedCount">0</h4>
                        </div>
                      </div>
                      <div class="card-icon">
                        <span class="badge bg-label-success rounded p-2">
                          <i class="bx bx-check-circle bx-sm"></i>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Material Grants Table -->
            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Bought Materials</h5>
                <div>
                  <select id="statusFilter" class="form-select" style="width: auto; display: inline-block;">
                    <option value="all">All Status</option>
                    <option value="pending" selected>Pending</option>
                    <option value="granted">Granted</option>
                  </select>
                </div>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-striped" id="grantsTable">
                    <thead>
                      <tr>
                        <th>Ref ID</th>
                        <th>Material</th>
                        <th>Course Code</th>
                        <th>Student</th>
                        <th>Matric No</th>
                        <th>School</th>
                        <th>Department</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody id="grantsTableBody">
                      <tr>
                        <td colspan="11" class="text-center">
                          <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                          </div>
                        </td>
                      </tr>
                    </tbody>
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

  <?php include('partials/_scripts.php') ?>
  
  <script>
    $(document).ready(function() {
      // Load statistics
      loadStats();
      
      // Load grants
      loadGrants();
      
      // Status filter change
      $('#statusFilter').on('change', function() {
        loadGrants();
      });
      
      function loadStats() {
        $.ajax({
          url: 'model/material_grants.php?stats=1',
          type: 'GET',
          dataType: 'json',
          success: function(response) {
            if (response.status === 'success') {
              $('#totalCount').text(response.stats.total);
              $('#pendingCount').text(response.stats.pending);
              $('#grantedCount').text(response.stats.granted);
            }
          }
        });
      }
      
      function loadGrants() {
        const status = $('#statusFilter').val();
        
        $.ajax({
          url: 'model/material_grants.php?list=1&status=' + status,
          type: 'GET',
          dataType: 'json',
          success: function(response) {
            if (response.status === 'success') {
              const tbody = $('#grantsTableBody');
              tbody.empty();
              
              if (response.data.length === 0) {
                tbody.append(`
                  <tr>
                    <td colspan="11" class="text-center">No records found</td>
                  </tr>
                `);
                return;
              }
              
              response.data.forEach(function(grant) {
                const statusBadge = grant.status === 'granted' 
                  ? '<span class="badge bg-success">Granted</span>' 
                  : '<span class="badge bg-warning">Pending</span>';
                
                const actionBtn = grant.status === 'pending'
                  ? `<button class="btn btn-sm btn-primary grant-btn" data-id="${grant.id}">Grant</button>`
                  : `<small class="text-muted">By: ${grant.granter_first_name || ''} ${grant.granter_last_name || ''}</small>`;
                
                const buyerName = `${grant.buyer_first_name || ''} ${grant.buyer_last_name || ''}`;
                const dateStr = new Date(grant.created_at).toLocaleDateString();
                
                tbody.append(`
                  <tr>
                    <td>${grant.manual_bought_ref_id}</td>
                    <td>${grant.material_title || 'N/A'}</td>
                    <td>${grant.course_code || 'N/A'}</td>
                    <td>${buyerName}</td>
                    <td>${grant.buyer_matric || 'N/A'}</td>
                    <td>${grant.school_name || 'N/A'}</td>
                    <td>${grant.dept_name || 'N/A'}</td>
                    <td>₦${parseFloat(grant.price || 0).toLocaleString()}</td>
                    <td>${statusBadge}</td>
                    <td>${dateStr}</td>
                    <td>${actionBtn}</td>
                  </tr>
                `);
              });
              
              // Initialize DataTable if not already initialized
              if (!$.fn.DataTable.isDataTable('#grantsTable')) {
                $('#grantsTable').DataTable({
                  order: [[9, 'desc']], // Order by date
                  pageLength: 25
                });
              }
            }
          },
          error: function() {
            $('#grantsTableBody').html(`
              <tr>
                <td colspan="11" class="text-center text-danger">Error loading data</td>
              </tr>
            `);
          }
        });
      }
      
      // Handle grant action
      $(document).on('click', '.grant-btn', function() {
        const grantId = $(this).data('id');
        const btn = $(this);
        
        if (!confirm('Are you sure you want to grant this material?')) {
          return;
        }
        
        btn.prop('disabled', true).text('Processing...');
        
        $.ajax({
          url: 'model/material_grants.php',
          type: 'POST',
          data: {
            grant_action: 1,
            grant_id: grantId
          },
          dataType: 'json',
          success: function(response) {
            if (response.status === 'success') {
              alert(response.message);
              loadStats();
              loadGrants();
            } else {
              alert('Error: ' + response.message);
              btn.prop('disabled', false).text('Grant');
            }
          },
          error: function() {
            alert('Error processing request');
            btn.prop('disabled', false).text('Grant');
          }
        });
      });
    });
  </script>
</body>
</html>

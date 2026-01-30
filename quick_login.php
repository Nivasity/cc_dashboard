<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = $_SESSION['nivas_adminRole'];

// Check authorization - only admin roles 1, 2, 3 can access
if (!in_array((int)$admin_role, [1, 2, 3], true)) {
  header('Location: index.php');
  exit();
}
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Quick Login | Nivasity Command Center</title>

  <meta name="description" content="" />

  <?php include('partials/_head.php') ?>
</head>

<body>
  <!-- Layout wrapper -->
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

      <!-- Menu -->
      <?php include('partials/_sidebar.php') ?>
      <!-- / Menu -->

      <!-- Layout container -->
      <div class="layout-page">

        <!-- Navbar -->
        <?php include('partials/_navbar.php') ?>
        <!-- / Navbar -->

        <!-- Content wrapper -->
        <div class="content-wrapper">
          <!-- Content -->

          <div class="container-xxl flex-grow-1 container-p-y">
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Customer Management / Students /</span> Quick Login</h4>

            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Quick Login Links</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createLoginModal">
                      <i class="bx bx-plus me-1"></i> Create New Login Link
                    </button>
                  </div>
                  <div class="card-body">
                    <div class="alert alert-info">
                      <i class="bx bx-info-circle me-2"></i>
                      Quick login links are valid for 24 hours and allow students to access the system without entering credentials.
                    </div>
                    <div class="table-responsive">
                      <table class="table table-hover" id="quickLoginTable">
                        <thead>
                          <tr>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Matric No.</th>
                            <th>School</th>
                            <th>Department</th>
                            <th>Login Link</th>
                            <th>Expiry Date/Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                          </tr>
                        </thead>
                        <tbody id="loginCodesTableBody">
                          <tr>
                            <td colspan="9" class="text-center">
                              <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                              </div>
                              Loading...
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- / Content -->

          <!-- Footer -->
          <?php include('partials/_footer.php') ?>
          <!-- / Footer -->

          <div class="content-backdrop fade"></div>
        </div>
        <!-- Content wrapper -->

        <!-- Create Login Modal -->
        <div class="modal fade" id="createLoginModal" tabindex="-1" aria-labelledby="createLoginModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="createLoginModalLabel">Create Quick Login Link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form id="createLoginForm">
                <div class="modal-body">
                  <div class="mb-3">
                    <label for="studentEmail" class="form-label">Student Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="studentEmail" name="email" placeholder="Enter student email" required>
                    <div class="form-text">Enter the student's registered email address</div>
                  </div>
                  
                  <div id="studentDetails" style="display: none;">
                    <hr>
                    <h6 class="mb-3">Student Details</h6>
                    <input type="hidden" id="studentId" name="student_id">
                    <div class="row">
                      <div class="col-md-6 mb-2">
                        <strong>Name:</strong> <span id="studentName"></span>
                      </div>
                      <div class="col-md-6 mb-2">
                        <strong>Phone:</strong> <span id="studentPhone"></span>
                      </div>
                      <div class="col-md-6 mb-2">
                        <strong>Matric No:</strong> <span id="studentMatric"></span>
                      </div>
                      <div class="col-md-6 mb-2">
                        <strong>School:</strong> <span id="studentSchool"></span>
                      </div>
                      <div class="col-md-12 mb-2">
                        <strong>Department:</strong> <span id="studentDept"></span>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                  <button type="submit" class="btn btn-primary" id="createLinkBtn" disabled>Create Link</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Success Modal for showing created link -->
        <div class="modal fade" id="linkCreatedModal" tabindex="-1" aria-labelledby="linkCreatedModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="linkCreatedModalLabel">
                  <i class="bx bx-check-circle me-2"></i>Login Link Created Successfully
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label fw-bold">Login Link:</label>
                  <div class="input-group">
                    <input type="text" class="form-control" id="createdLink" readonly>
                    <button class="btn btn-outline-primary" type="button" id="copyLinkBtn">
                      <i class="bx bx-copy me-1"></i>Copy
                    </button>
                  </div>
                </div>
                <div class="alert alert-warning mb-0">
                  <i class="bx bx-time me-2"></i>
                  <strong>Note:</strong> This link will expire in 24 hours at <span id="linkExpiry"></span>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>

      </div>
      <!-- / Layout container -->
    </div>
    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
  </div>
  <!-- / Layout wrapper -->

  <!-- Core JS -->
  <!-- build:js assets/vendor/js/core.js -->
  <script src="assets/vendor/libs/jquery/jquery.js"></script>
  <script src="assets/vendor/libs/popper/popper.js"></script>
  <script src="assets/vendor/js/bootstrap.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

  <script src="assets/vendor/js/menu.js"></script>
  <!-- endbuild -->

  <!-- Vendors JS -->
  <script src="assets/js/ui-toasts.js"></script>

  <!-- Main JS -->
  <script src="assets/js/main.js"></script>

  <script>
    $(document).ready(function() {
      // Load login codes on page load
      loadLoginCodes();

      // Search student when email is entered
      let searchTimeout;
      $('#studentEmail').on('input', function() {
        clearTimeout(searchTimeout);
        const email = $(this).val().trim();
        
        if (email.length > 0 && validateEmail(email)) {
          searchTimeout = setTimeout(function() {
            searchStudent(email);
          }, 500);
        } else {
          $('#studentDetails').hide();
          $('#createLinkBtn').prop('disabled', true);
        }
      });

      // Create login link form submission
      $('#createLoginForm').on('submit', function(e) {
        e.preventDefault();
        createLoginLink();
      });

      // Copy link button with modern Clipboard API
      $('#copyLinkBtn').on('click', function() {
        const linkInput = document.getElementById('createdLink');
        const link = linkInput.value;
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
          // Modern Clipboard API
          navigator.clipboard.writeText(link).then(() => {
            $(this).html('<i class="bx bx-check me-1"></i>Copied!');
            setTimeout(() => {
              $(this).html('<i class="bx bx-copy me-1"></i>Copy');
            }, 2000);
          }).catch(() => {
            // Fallback for older browsers
            fallbackCopy(linkInput);
          });
        } else {
          // Fallback for older browsers
          fallbackCopy(linkInput);
        }
      });
      
      function fallbackCopy(input) {
        input.select();
        try {
          document.execCommand('copy');
          $('#copyLinkBtn').html('<i class="bx bx-check me-1"></i>Copied!');
          setTimeout(() => {
            $('#copyLinkBtn').html('<i class="bx bx-copy me-1"></i>Copy');
          }, 2000);
        } catch (err) {
          console.error('Copy failed', err);
        }
      }
    });

    function validateEmail(email) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function searchStudent(email) {
      $.ajax({
        url: 'model/quick_login.php',
        method: 'POST',
        data: { action: 'search_student', email: email },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            const student = response.student;
            $('#studentId').val(student.id);
            $('#studentName').text(student.first_name + ' ' + student.last_name);
            $('#studentPhone').text(student.phone);
            $('#studentMatric').text(student.matric_no || 'N/A');
            $('#studentSchool').text(student.school_name);
            $('#studentDept').text(student.dept_name);
            $('#studentDetails').show();
            $('#createLinkBtn').prop('disabled', false);
          } else {
            $('#studentDetails').hide();
            $('#createLinkBtn').prop('disabled', true);
            if (email.length > 5) {
              showToast('bg-danger', response.message || 'Student not found');
            }
          }
        },
        error: function() {
          $('#studentDetails').hide();
          $('#createLinkBtn').prop('disabled', true);
        }
      });
    }

    function createLoginLink() {
      const studentId = $('#studentId').val();
      
      $('#createLinkBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Creating...');
      
      $.ajax({
        url: 'model/quick_login.php',
        method: 'POST',
        data: { action: 'create_link', student_id: studentId },
        dataType: 'json',
        success: function(response) {
          $('#createLinkBtn').prop('disabled', false).html('Create Link');
          
          if (response.success) {
            $('#createLoginModal').modal('hide');
            $('#createLoginForm')[0].reset();
            $('#studentDetails').hide();
            
            // Show success modal with link
            $('#createdLink').val(response.link);
            $('#linkExpiry').text(new Date(response.expiry).toLocaleString());
            $('#linkCreatedModal').modal('show');
            
            // Reload table
            loadLoginCodes();
            
            showToast('bg-success', 'Login link created successfully!');
          } else {
            showToast('bg-danger', response.message || 'Failed to create login link');
          }
        },
        error: function() {
          $('#createLinkBtn').prop('disabled', false).html('Create Link');
          showToast('bg-danger', 'An error occurred. Please try again.');
        }
      });
    }

    function loadLoginCodes() {
      $.ajax({
        url: 'model/quick_login.php',
        method: 'GET',
        data: { action: 'list_codes' },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            displayLoginCodes(response.codes);
          } else {
            $('#loginCodesTableBody').html('<tr><td colspan="9" class="text-center text-danger">Failed to load data</td></tr>');
          }
        },
        error: function() {
          $('#loginCodesTableBody').html('<tr><td colspan="9" class="text-center text-danger">Error loading data</td></tr>');
        }
      });
    }

    function displayLoginCodes(codes) {
      const tbody = $('#loginCodesTableBody');
      tbody.empty();
      
      if (codes.length === 0) {
        tbody.html('<tr><td colspan="9" class="text-center text-muted">No quick login links created yet</td></tr>');
        return;
      }
      
      codes.forEach(function(code) {
        const statusBadge = getStatusBadge(code.status);
        const expiryDate = new Date(code.expiry_datetime).toLocaleString();
        
        const row = `
          <tr>
            <td>${code.first_name} ${code.last_name}</td>
            <td>${code.email}</td>
            <td>${code.matric_no || 'N/A'}</td>
            <td>${code.school_name || 'N/A'}</td>
            <td>${code.dept_name || 'N/A'}</td>
            <td>
              <div class="input-group input-group-sm" style="max-width: 250px;">
                <input type="text" class="form-control form-control-sm" value="${code.link}" readonly>
                <button class="btn btn-outline-secondary btn-sm copy-btn" data-link="${code.link}" title="Copy link">
                  <i class="bx bx-copy"></i>
                </button>
              </div>
            </td>
            <td>${expiryDate}</td>
            <td>${statusBadge}</td>
            <td>
              ${code.status !== 'deleted' ? `
                <button class="btn btn-sm btn-danger delete-btn" data-id="${code.id}" title="Delete">
                  <i class="bx bx-trash"></i>
                </button>
              ` : '-'}
            </td>
          </tr>
        `;
        tbody.append(row);
      });
      
      // Attach copy button handlers with modern Clipboard API
      $('.copy-btn').on('click', function() {
        const link = $(this).data('link');
        const btn = $(this);
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
          // Modern Clipboard API
          navigator.clipboard.writeText(link).then(() => {
            btn.html('<i class="bx bx-check"></i>');
            setTimeout(() => {
              btn.html('<i class="bx bx-copy"></i>');
            }, 1500);
          }).catch(() => {
            // Fallback for older browsers
            fallbackCopyFromData(link, btn);
          });
        } else {
          // Fallback for older browsers
          fallbackCopyFromData(link, btn);
        }
      });
      
      function fallbackCopyFromData(text, btn) {
        const temp = $('<input>');
        $('body').append(temp);
        temp.val(text).select();
        try {
          document.execCommand('copy');
          btn.html('<i class="bx bx-check"></i>');
          setTimeout(() => {
            btn.html('<i class="bx bx-copy"></i>');
          }, 1500);
        } catch (err) {
          console.error('Copy failed', err);
        }
        temp.remove();
      }
      
      // Attach delete button handlers
      $('.delete-btn').on('click', function() {
        const codeId = $(this).data('id');
        deleteLoginCode(codeId);
      });
    }

    function getStatusBadge(status) {
      const badges = {
        'active': '<span class="badge bg-success">Active</span>',
        'expired': '<span class="badge bg-warning">Expired</span>',
        'used': '<span class="badge bg-info">Used</span>',
        'deleted': '<span class="badge bg-danger">Deleted</span>'
      };
      return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    }

    function deleteLoginCode(codeId) {
      if (!confirm('Are you sure you want to delete this login link?')) {
        return;
      }
      
      $.ajax({
        url: 'model/quick_login.php',
        method: 'POST',
        data: { action: 'delete_code', code_id: codeId },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            showToast('bg-success', 'Login link deleted successfully');
            loadLoginCodes();
          } else {
            showToast('bg-danger', response.message || 'Failed to delete login link');
          }
        },
        error: function() {
          showToast('bg-danger', 'An error occurred. Please try again.');
        }
      });
    }
  </script>
</body>

</html>

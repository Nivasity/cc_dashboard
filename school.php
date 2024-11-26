<?php
session_start();
include('model/config.php');
include('model/page_config.php');

?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Schools | Nivasity Command Center</title>

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
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">School Management /</span> Schools</h4>

            <div class="row">
              <div class="col-md-12">
                <ul class="nav nav-pills flex-row mb-3" role="tablist">
                  <li class="nav-item">
                    <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab"
                      data-bs-target="#navs-top-school" aria-controls="navs-top-school" aria-selected="false"><i
                        class="bx bxs-school me-1"></i> Schools</button>
                  </li>
                  <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab"
                      data-bs-target="#navs-top-departments" aria-controls="navs-top-departments" aria-selected="false"><i
                        class='bx bxs-graduation me-1'></i>Departments</button>
                  </li>
                </ul>
                <div class="tab-content p-0 p-sm-3">
                  <div class="card mb-4 tab-pane fade active show" id="navs-top-school" role="tabpanel">
                    <div class="card-body">
                      <div class="table-responsive text-nowrap">
                        <table class="table">
                          <thead class="table-secondary">
                            <tr>
                              <th>Name</th>
                              <th>Short Name</th>
                              <th>Depts</th>
                              <th>Students</th>
                              <th>Status</th>
                              <th>Actions</th>
                            </tr>
                          </thead>
                          <tbody id="schools_table" class="table-border-bottom-0">

                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>

                  <div class="card mb-4 tab-pane fade" id="navs-top-departments" role="tabpanel">
                    <div class="card-header">
                      <form id="selectSchoolForm">
                        <div class="row mb-3">
                          <div class="col-sm-8 mb-3 mb-sm-0">
                            <select id="school" name="school" class="form-select" required>
                              <option value="0">Select School</option>
                            </select>
                          </div>
                          <div class="col-sm-3">
                            <button id="submitBtn2" type="submit" class="btn btn-secondary w-100">Search</button>
                          </div>
                        </div>
                      </form>
                    </div>
                    <hr class="my-0" />
                    <div class="card-body">
                      <div class="table-responsive text-nowrap">
                        <table class="table dept_table">
                          <thead class="table-secondary">
                            <tr>
                              <th>Name</th>
                              <th>Students</th>
                              <th>HOC</th>
                              <th>Status</th>
                              <th>Actions</th>
                            </tr>
                          </thead>
                          <tbody id="depts_table" class="table-border-bottom-0">
                          
                          </tbody>
                        </table>
                      </div>
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


        <!-- Button to Open the Modal -->
        <button type="button" class="btn btn-primary new_formBtn" data-bs-toggle="modal"
          data-bs-target="#newSchoolModal">
          <i class="bx bx-plus fs-3"></i>
        </button>

        <!-- Modal -->
        <div class="modal fade" id="newSchoolModal" tabindex="-1" aria-labelledby="newSchoolModalLabel"
          aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="newSchoolModalLabel">Add New School</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                  aria-label="Close"></button>
              </div>
              <form id="newSchoolForm">
                <input type="hidden" name="school_id" value="0" required>

                <div class="modal-body">
                  <!-- Your form goes here -->
                  <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" name="name" class="form-control"
                      placeholder="Enter school full name" required>
                  </div>
                  <div class="mb-3">
                    <label for="code" class="form-label">Short Name</label>
                    <input type="text" name="code" class="form-control"
                      placeholder="Enter school short name" required>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                  <button id="submitBtn" type="submit" class="btn btn-primary">Save changes</button>
                </div>
              </form>
            </div>
          </div>
        </div>


        <div class="modal fade" id="newDeptModal" tabindex="-1" aria-labelledby="newDeptModalLabel"
          aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="newDeptModalLabel">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                  aria-label="Close"></button>
              </div>
              <form id="newDeptForm">
                <input type="hidden" name="school_id" value="0" required>
                <input type="hidden" name="dept_id" value="0" required>

                <div class="modal-body">
                  <!-- Your form goes here -->
                  <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" name="name" class="form-control"
                      placeholder="Enter department name" required>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                  <button id="submitBtn3" type="submit" class="btn btn-primary">Save changes</button>
                </div>
              </form>
            </div>
          </div>
        </div>

      </div>
      <!-- / Layout page -->
    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
  </div>
  <!-- / Layout wrapper -->

  <!-- Core JS -->
  <!-- build:js assets/vendor/js/core.js -->
  <script src="assets/vendor/libs/jquery/jquery.js"></script>
  <script src="assets/vendor/js/bootstrap.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="assets/vendor/libs/popper/popper.js"></script>

  <script src="assets/vendor/js/menu.js"></script>
  <!-- endbuild -->

  <!-- Vendors JS -->
  <script src="assets/js/ui-toasts.js"></script>

  <!-- Main JS -->
  <script src="assets/js/main.js"></script>
  <script src="model/functions/schools.js"></script>

  <script>
    $(document).ready(function() {
      fetchSchools();
      fetchSchools2();

      $('.form-select').select2({
        theme: "bootstrap-5",
        width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
        placeholder: $(this).data('placeholder'),
        closeOnSelect: false
      });
    });
  </script>
</body>

</html>
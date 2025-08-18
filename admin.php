<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$roles_query = mysqli_query($conn, "SELECT id, name FROM admin_roles WHERE status = 'active'");
$schools_query = mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active'");
$admins_query = mysqli_query($conn, "SELECT a.*, r.name AS role_name, s.name AS school_name FROM admins a LEFT JOIN admin_roles r ON a.role = r.id LEFT JOIN schools s ON a.school = s.id");
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Admins | Nivasity Command Center</title>
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
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Admin Management /</span> Admins</h4>
            <div class="card">
              <div class="card-body">
                <div class="table-responsive text-nowrap">
                  <table class="table">
                    <thead class="table-secondary">
                      <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>School</th>
                        <th>Status</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody class="table-border-bottom-0" id="admins_table">
                      <?php while($admin = mysqli_fetch_array($admins_query)) { ?>
                      <tr>
                        <td class="text-uppercase"><?php echo $admin['first_name'] . ' ' . $admin['last_name']; ?></td>
                        <td><?php echo $admin['email']; ?></td>
                        <td><?php echo $admin['role_name']; ?></td>
                        <td><?php echo $admin['school_name'] ? $admin['school_name'] : '-'; ?></td>
                        <td><span class="fw-bold badge bg-label-<?php echo $admin['status'] == 'active' ? 'success' : 'danger'; ?>"><?php echo ucfirst($admin['status']); ?></span></td>
                        <td>
                          <div class="dropdown">
                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded"></i></button>
                            <div class="dropdown-menu">
                              <a class="dropdown-item editAdmin" href="javascript:void(0);"
                                data-id="<?php echo $admin['id']; ?>"
                                data-first="<?php echo $admin['first_name']; ?>"
                                data-last="<?php echo $admin['last_name']; ?>"
                                data-email="<?php echo $admin['email']; ?>"
                                data-phone="<?php echo $admin['phone']; ?>"
                                data-gender="<?php echo $admin['gender']; ?>"
                                data-role="<?php echo $admin['role']; ?>"
                                data-school="<?php echo $admin['school']; ?>">
                                <i class="bx bx-edit-alt me-1"></i> Edit
                              </a>
                              <a class="dropdown-item deleteAdmin" href="javascript:void(0);" data-id="<?php echo $admin['id']; ?>">
                                <i class="bx bx-trash me-1"></i> Delete
                              </a>
                            </div>
                          </div>
                        </td>
                      </tr>
                      <?php } ?>
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

  <button type="button" class="btn btn-primary new_formBtn" data-bs-toggle="modal" data-bs-target="#newAdminModal">
    <i class="bx bx-plus fs-3"></i>
  </button>

  <div class="modal fade" id="newAdminModal" tabindex="-1" aria-labelledby="newAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="newAdminModalLabel">Add New Admin</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="adminForm">
          <input type="hidden" name="admin_id" value="0">
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="phone" class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="gender" class="form-label">Gender</label>
                <select name="gender" class="form-select" required>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label for="role" class="form-label">Role</label>
                <select name="role" id="role" class="form-select" required>
                  <?php mysqli_data_seek($roles_query,0); while($role = mysqli_fetch_array($roles_query)) { ?>
                    <option value="<?php echo $role['id']; ?>"><?php echo $role['name']; ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label for="school" class="form-label">School</label>
                <select name="school" id="school" class="form-select">
                  <option value="0">Select School</option>
                  <?php while($school = mysqli_fetch_array($schools_query)) { ?>
                    <option value="<?php echo $school['id']; ?>"><?php echo $school['name']; ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" class="form-control">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" id="submitBtn" class="btn btn-primary">Save changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Core JS -->
  <script src="assets/vendor/libs/jquery/jquery.js"></script>
  <script src="assets/vendor/js/bootstrap.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="assets/vendor/libs/popper/popper.js"></script>
  <script src="assets/vendor/js/menu.js"></script>

  <!-- Main JS -->
  <script src="assets/js/ui-toasts.js"></script>
  <script src="assets/js/main.js"></script>
  <script src="model/functions/admins.js"></script>
</body>
</html>

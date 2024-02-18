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

  <title>Account settings - Profile | Nivasity Command Center</title>

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
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Customer Management /</span> Students</h4>

            <div class="row">
              <div class="col-md-12">
                <ul class="nav nav-pills flex-row mb-3" role="tablist">
                  <li class="nav-item">
                    <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab"
                      data-bs-target="#navs-top-profile" aria-controls="navs-top-profile" aria-selected="false"><i
                        class="bx bx-user me-1"></i> Profile</button>
                  </li>
                  <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab"
                      data-bs-target="#navs-top-verify" aria-controls="navs-top-verify" aria-selected="false"><i
                        class="bx bx-user-check me-1"></i> Verify Student</button>
                  </li>
                  <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab"
                      data-bs-target="#navs-top-email" aria-controls="navs-top-email" aria-selected="false"><i
                        class="bx bx-envelope me-1"></i> Email Students</button>
                  </li>
                </ul>
                <div class="tab-content p-0 p-sm-3">
                  <div class="card mb-4 tab-pane fade active show" id="navs-top-profile" role="tabpanel">
                    <!-- Account -->
                    <div class="card-header">
                      <h5>Profile Details</h5>
                      <form>
                        <div class="row mb-3">
                          <div class="col-sm-8 mb-3 mb-sm-0">
                            <div class="input-group">
                              <input type="text" class="form-control" id="basic-icon-default-fullname"
                                placeholder="Student Email / Phone / Matric no." aria-label="John Doe"
                                aria-describedby="basic-icon-default-fullname2" />
                            </div>
                          </div>
                          <div class="col-sm-3">
                            <button type="submit" class="btn btn-secondary w-100">Search</button>
                          </div>
                        </div>
                      </form>
                    </div>
                    <hr class="my-0" />
                    <div class="card-body">
                      <form id="formAccountSettings" method="POST" onsubmit="return false">
                        <div class="row">
                          <div class="mb-3 col-md-6">
                            <label for="firstName" class="form-label">First Name</label>
                            <input class="form-control" type="text" id="firstName" name="firstName" value="John"
                              autofocus />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input class="form-control" type="text" name="lastName" id="lastName" value="Doe" />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="email" class="form-label">E-mail</label>
                            <input class="form-control" type="text" id="email" name="email" value="john.doe@example.com"
                              placeholder="john.doe@example.com" />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="phoneNumber">Phone Number</label>
                            <div class="input-group">
                              <input type="text" id="phoneNumber" name="phoneNumber" class="form-control"
                                placeholder="+202 555 0111" />
                            </div>
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="school" class="form-label">School Name</label>
                            <input type="text" class="form-control" id="school" name="school"
                              value="ThemeSelection" />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="dept" class="form-label">Department</label>
                            <input type="text" class="form-control" id="dept" name="dept" placeholder="Computer Science" />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="matric_no" class="form-label">Matric Number</label>
                            <input class="form-control" type="text" id="matric_no" name="matric_no" placeholder="190303003" />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="role">Role</label>
                            <select id="role" class="select2 form-select">
                              <option value="student">Student</option>
                              <option value="hoc">HOC</option>
                            </select>
                          </div>
                        </div>
                        <div class="mt-2">
                          <button type="submit" class="btn btn-primary me-2">Save changes</button>
                          <button type="reset" class="btn btn-outline-secondary">Cancel</button>
                        </div>
                      </form>
                    </div>
                    <!-- /Account -->
                  </div>

                  <div class="card mb-4 tab-pane fade" id="navs-top-verify" role="tabpanel">
                    <div class="card-header">
                      <h5>Verify Student</h5>
                      <form>
                        <div class="row mb-3">
                          <div class="col-sm-8 mb-3 mb-sm-0">
                            <div class="input-group">
                              <input type="text" class="form-control" id="basic-icon-default-fullname"
                                placeholder="Student Email / Phone / Matric no." aria-label="John Doe"
                                aria-describedby="basic-icon-default-fullname2" />
                            </div>
                          </div>
                          <div class="col-sm-3">
                            <button type="submit" class="btn btn-secondary w-100">Search</button>
                          </div>
                        </div>
                      </form>
                    </div>
                    <hr class="my-0" />
                    <div class="card-body">
                      <dl class="row mt-2">
                        <dt class="col-sm-3">Full Name</dt>
                        <dd class="col-sm-9 text-uppercase">
                          Samuel Akinyemi
                        </dd>
                        <dt class="col-sm-3">Phone Number</dt>
                        <dd class="col-sm-9">
                          +2347048706198
                        </dd>
                        <dt class="col-sm-3">Email</dt>
                        <dd class="col-sm-9">
                          <p>akinyemisamuel170@gmail.com</p>
                        </dd>

                        <dt class="col-sm-3">School Name</dt>
                        <dd class="col-sm-9 text-uppercase">
                          University of lagos
                        </dd>
                        <dt class="col-sm-3">Department</dt>
                        <dd class="col-sm-9">
                          Computer Science
                        </dd>
                        <dt class="col-sm-3">Matric Number</dt>
                        <dd class="col-sm-9">
                          <p>1903030300</p>
                        </dd>

                        <dt class="col-sm-3">Account Name</dt>
                        <dd class="col-sm-9 text-uppercase">
                          SAMUEL AYOMIDE AKINYEMI
                        </dd>
                        <dt class="col-sm-3">Account number</dt>
                        <dd class="col-sm-9">
                          1454746632
                        </dd>
                        <dt class="col-sm-3">Bank Name</dt>
                        <dd class="col-sm-9">
                          <p>Access Bank</p>
                        </dd>

                        <dt class="col-sm-3 text-primary">Student Status</dt>
                        <dd class="col-sm-3">
                          <select id="defaultSelect" class="form-select">
                            <option value="verified">Verified</option>
                            <option value="inreview">Inreview</option>
                            <option value="deactivate">Deactivated</option>
                          </select>
                        </dd>

                        <!-- Status -->
                        <dt class="col-sm-8 text-primary mt-3">
                          <button type="submit" class="btn btn-primary me-2">Save changes</button>
                        </dt>

                      </dl>
                    </div>
                  </div>

                  <div class="card mb-4 tab-pane fade" id="navs-top-email" role="tabpanel">
                    <div class="card-header">
                      <h5>Email Student(s)</h5>
                      <form>
                        <div class="row mb-3">
                          <div class="col-sm-5 mb-3 mb-sm-0">
                            <div class="input-group">
                              <select id="defaultSelect" class="form-select">
                                <option value="1">Selected Student</option>
                                <option value="2">All Students</option>
                              </select>
                            </div>
                          </div>
                        </div>
                      </form>
                    </div>
                    <hr class="my-0" />
                    <div class="card-body">
                      <form>
                        <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="basic-icon-default-email">Student Email</label>
                          <div class="col-sm-10">
                            <div class="input-group input-group-merge">
                              <span id="subject" class="input-group-text"><i class="bx bx-user"></i></span>
                              <input type="text" id="basic-icon-default-email" class="form-control"
                                placeholder="student@example.com" aria-label="john.doe"
                                aria-describedby="basic-icon-default-email2">
                            </div>
                          </div>
                        </div>
                        <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="subject">Subject</label>
                          <div class="col-sm-10">
                            <div class="input-group input-group-merge">
                              <span class="input-group-text"><i class="bx bx-envelope"></i></span>
                              <input type="text" class="form-control" id="subject" placeholder="John Doe"
                                aria-label="John Doe" aria-describedby="subject">
                            </div>
                          </div>
                        </div>
                        <div class="row mb-3">
                          <label class="col-sm-2 form-label" for="basic-icon-default-message">Message</label>
                          <div class="col-sm-10">
                            <div class="input-group">
                              <textarea id="basic-icon-default-message" class="form-control" rows="7"
                                placeholder="Message" aria-label="Message"
                                aria-describedby="basic-icon-default-message2"></textarea>
                            </div>
                          </div>
                        </div>
                        <div class="row justify-content-end">
                          <div class="col-sm-10">
                            <button type="submit" class="btn btn-primary">Send</button>
                          </div>
                        </div>
                      </form>
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
      </div>
      <!-- / Layout page -->
    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
  </div>
  <!-- / Layout wrapper -->

  <div class="buy-now">
    <a href="https://themeselection.com/products/sneat-bootstrap-html-admin-template/" target="_blank"
      class="btn btn-danger btn-buy-now">Upgrade to Pro</a>
  </div>

  <!-- Core JS -->
  <!-- build:js assets/vendor/js/core.js -->
  <script src="assets/vendor/libs/jquery/jquery.js"></script>
  <script src="assets/vendor/libs/popper/popper.js"></script>
  <script src="assets/vendor/js/bootstrap.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

  <script src="assets/vendor/js/menu.js"></script>
  <!-- endbuild -->

  <!-- Vendors JS -->

  <!-- Main JS -->
  <script src="assets/js/main.js"></script>

  <!-- Page JS -->
  <script src="assets/js/pages-account-settings-account.js"></script>

  <!-- Place this tag in your head or just before your close body tag. -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
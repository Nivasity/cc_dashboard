<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = $_SESSION['nivas_adminRole'];
$admin_school = $admin_['school'];
$admin_faculty = $admin_['faculty'] ?? 0;
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Students | Nivasity Command Center</title>

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
                  <?php if ($admin_role != 5) { ?>
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
                  <?php } ?>
                </ul>
                <div class="tab-content p-0 p-sm-3">
                  <div class="card mb-4 tab-pane fade active show" id="navs-top-profile" role="tabpanel">
                    <!-- Account -->
                    <div class="card-header">
                      <h5>Profile Details</h5>
                      <form id="search_profile-form">
                        <div class="row mb-3">
                          <div class="col-sm-8 mb-3 mb-sm-0">
                            <div class="input-group">
                              <input type="text" class="form-control" id="student_data" name="student_data"
                                placeholder="Student Email / Phone / Matric no."
                                aria-label="Student Email / Phone / Matric no." aria-describedby="student_data" />
                            </div>
                          </div>
                          <div class="col-sm-3">
                            <button type="submit" class="btn btn-secondary w-100 search_profile-btn">Search</button>
                          </div>
                        </div>
                        <?php if ($admin_role == 5) { ?>
                        <input type="hidden" name="admin_school" value="<?php echo $admin_school; ?>" />
                        <?php if ($admin_faculty) { ?>
                        <input type="hidden" name="admin_faculty" value="<?php echo $admin_faculty; ?>" />
                        <?php } ?>
                        <?php } ?>
                      </form>
                    </div>
                    <hr class="my-0" />
                    <div class="card-body">
                      <form id="profile-form" style="display: none;">
                        <input type="hidden" name="edit_profile" value="0" />
                        <div class="row">
                          <div class="mb-3 col-md-6">
                            <label for="first_name" class="form-label">First Name</label>
                            <input class="form-control" type="text" id="first_name" name="first_name" readonly />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input class="form-control" type="text" id="last_name" name="last_name" readonly />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="email" class="form-label">E-mail</label>
                            <input class="form-control" type="text" id="email" name="email" readonly />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="phone">Phone Number</label>
                            <div class="input-group">
                              <input type="text" id="phone" name="phone" class="form-control" readonly />
                            </div>
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="school" class="form-label">School Name</label>
                            <select id="school" name="school" class="form-select select_special" required <?php echo $admin_role == 5 ? 'disabled' : ''; ?>>
                            </select>
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="depts" class="form-label">Department</label>
                            <select id="depts" name="dept" class="form-select select_special" required <?php echo $admin_role == 5 ? 'disabled' : ''; ?>>
                              <option value="0">Select Department</option>
                            </select>
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="matric_no" class="form-label">Matric Number</label>
                            <input class="form-control" type="text" id="matric_no" name="matric_no" required <?php echo $admin_role == 5 ? 'readonly' : ''; ?> />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="admissionYear" class="form-label">Admission Year</label>
                            <select id="admissionYear" name="adm_year" class="form-select select_special" <?php echo $admin_role == 5 ? 'disabled' : ''; ?>>
                              <option value="">Select Admission Year</option>
                            </select>
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="role">Role</label>
                            <select id="role" name="role" class="form-select select_special" required <?php echo $admin_role == 5 ? 'disabled' : ''; ?>>
                              <option value="student">Student</option>
                              <option value="hoc">HOC</option>
                            </select>
                          </div>
                        </div>
                        <?php if ($admin_role != 5) { ?>
                        <div class="mt-2">
                          <button type="submit" class="btn btn-primary me-2 profile-form-btn">Save changes</button>
                        </div>
                        <?php } ?>
                      </form>
                    </div>
                    <!-- /Account -->
                  </div>

                  <div class="card mb-4 tab-pane fade" id="navs-top-verify" role="tabpanel">
                    <div class="card-header">
                      <h5>Verify Student</h5>
                      <form id="search_verify-form">
                        <div class="row mb-3">
                          <div class="col-sm-8 mb-3 mb-sm-0">
                            <div class="input-group">
                              <input type="text" class="form-control" id="student_data" name="student_data"
                                placeholder="Student Email / Phone / Matric no."
                                aria-label="Student Email / Phone / Matric no." aria-describedby="student_data" />
                            </div>
                          </div>
                          <div class="col-sm-3">
                            <button type="submit" class="btn btn-secondary w-100 search_verify-btn">Search</button>
                          </div>
                        </div>
                        <?php if ($admin_role == 5) { ?>
                        <input type="hidden" name="admin_school" value="<?php echo $admin_school; ?>" />
                        <?php if ($admin_faculty) { ?>
                        <input type="hidden" name="admin_faculty" value="<?php echo $admin_faculty; ?>" />
                        <?php } ?>
                        <?php } ?>
                      </form>
                    </div>
                    <hr class="my-0" />
                    <div class="card-body">
                      <dl class="row mt-2 profile_info" style="display: none;">
                        <dt class="col-sm-3">Full Name - Role</dt>
                        <dd class="col-sm-9 text-uppercase student_fullname">
                          Samuel Akinyemi
                        </dd>
                        <dt class="col-sm-3">Phone Number</dt>
                        <dd class="col-sm-9 student_phone">
                          +2347048706198
                        </dd>
                        <dt class="col-sm-3">Email</dt>
                        <dd class="col-sm-9">
                          <p class="student_email">akinyemisamuel170@gmail.com</p>
                        </dd>

                        <dt class="col-sm-3">School Name</dt>
                        <dd class="col-sm-9 text-uppercase student_sch">
                          University of lagos
                        </dd>
                        <dt class="col-sm-3">Department</dt>
                        <dd class="col-sm-9 student_dept">
                          Computer Science
                        </dd>
                        <dt class="col-sm-3">Matric Number</dt>
                        <dd class="col-sm-9">
                          <p class="student_matric">1903030300</p>
                        </dd>

                        <dt class="col-sm-3">Account Name</dt>
                        <dd class="col-sm-9 text-uppercase acct_no">
                          SAMUEL AYOMIDE AKINYEMI
                        </dd>
                        <dt class="col-sm-3">Account number</dt>
                        <dd class="col-sm-9 acct_name">
                          1454746632
                        </dd>
                        <dt class="col-sm-3">Bank Name</dt>
                        <dd class="col-sm-9">
                          <p class="acct_bank text-uppercase">Access Bank</p>
                        </dd>

                        <form id="verify-form">
                          <input type="hidden" id="student_fname" name="student_fname"  />
                          <input type="hidden" id="student_role" name="student_role"  />
                          <input type="hidden" id="student_email_" name="student_email_"  />

                          <dt class="col-sm-3 text-primary">Student Status</dt>
                          <dd class="col-sm-3">
                            <select class="form-select select_special student_status" name="student_status">
                              <option value="verified">Verified</option>
                              <option value="unverified">Unverified</option>
                              <option value="inreview">Inreview</option>
                              <option value="denied">Temporary Deactivated</option>
                              <option value="deactivate">Deleted</option>
                            </select>
                          </dd>

                          <!-- Status -->
                          <dt class="col-sm-8 text-primary mt-3">
                            <button type="submit" class="btn btn-primary me-2 verify-form-btn">Save changes</button>
                          </dt>
                        </form>

                      </dl>
                    </div>
                  </div>

                  <!-- Email Students Tab - Uses BREVO Email Service -->
                  <!-- BREVO (formerly Sendinblue) is the email service provider -->
                  <!-- Emails are sent via BREVO SMTP and credits are validated via BREVO API -->
                  <div class="card mb-4 tab-pane fade" id="navs-top-email" role="tabpanel">
                    <h5 class="card-header">Email Student(s)</h5>
                    <hr class="my-0" />
                    <div class="card-body">
                      <!-- Form submits to model/support.php which handles BREVO email sending -->
                      <form id="email-form">
                        <input type="hidden" name="email_customer" value="1" />
                        <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="recipient_type">Send To</label>
                          <div class="col-sm-10">
                            <select id="recipient_type" name="recipient_type" class="form-select" required>
                              <option value="">Select Recipients</option>
                              <option value="single">Single Student</option>
                              <option value="all_students">All Students</option>
                              <option value="all_hoc">All HOCs</option>
                              <option value="all_students_hoc">All Students + HOCs</option>
                              <option value="school">Students of a School</option>
                              <option value="faculty">Students of a Faculty</option>
                              <option value="dept">Students of a Department</option>
                            </select>
                          </div>
                        </div>
                        <div class="row mb-3" id="single_email_row" style="display: none;">
                          <label class="col-sm-2 col-form-label" for="cus_email">Student Email</label>
                          <div class="col-sm-10">
                            <div class="input-group input-group-merge">
                              <span class="input-group-text"><i class="bx bx-user"></i></span>
                              <input type="text" id="cus_email" name="cus_email" class="form-control"
                                placeholder="customer@example.com" aria-label="customer@example.com"
                                aria-describedby="student_data">
                            </div>
                          </div>
                        </div>
                        <div class="row mb-3" id="school_select_row" style="display: none;">
                          <label class="col-sm-2 col-form-label" for="email_school">School</label>
                          <div class="col-sm-10">
                            <select id="email_school" name="email_school" class="form-select select_special">
                              <option value="">Select School</option>
                            </select>
                          </div>
                        </div>
                        <div class="row mb-3" id="faculty_select_row" style="display: none;">
                          <label class="col-sm-2 col-form-label" for="email_faculty">Faculty</label>
                          <div class="col-sm-10">
                            <select id="email_faculty" name="email_faculty" class="form-select select_special">
                              <option value="">Select Faculty</option>
                            </select>
                          </div>
                        </div>
                        <div class="row mb-3" id="dept_select_row" style="display: none;">
                          <label class="col-sm-2 col-form-label" for="email_dept">Department</label>
                          <div class="col-sm-10">
                            <select id="email_dept" name="email_dept" class="form-select select_special">
                              <option value="">Select Department</option>
                            </select>
                          </div>
                        </div>
                        <div class="row mb-3" id="student_count_row" style="display: none;">
                          <label class="col-sm-2 col-form-label">Total Recipients</label>
                          <div class="col-sm-10">
                            <div class="alert alert-info mb-0" role="alert">
                              <strong><span id="student_count">0</span> student(s)</strong> will receive this email
                            </div>
                          </div>
                        </div>
                        <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="subject">Subject</label>
                          <div class="col-sm-10">
                            <div class="input-group input-group-merge">
                              <span class="input-group-text"><i class="bx bx-envelope"></i></span>
                              <input type="text" class="form-control" id="subject" name="subject" placeholder="Re: Thanks for... "
                                aria-label="John Doe" aria-describedby="subject" required>
                            </div>
                          </div>
                        </div>
                        <div class="row mb-3">
                          <label class="col-sm-2 form-label" for="message">Message</label>
                          <div class="col-sm-10">
                            <!-- Markdown Helper Card -->
                            <div class="card mb-2">
                              <div class="card-header p-2">
                                <a class="d-flex align-items-center text-decoration-none" data-bs-toggle="collapse" href="#markdownHelper" role="button" aria-expanded="false" aria-controls="markdownHelper">
                                  <i class="bx bx-info-circle me-2"></i>
                                  <span class="fw-semibold">Markdown Formatting Guide</span>
                                  <i class="bx bx-chevron-down ms-auto"></i>
                                </a>
                              </div>
                              <div class="collapse" id="markdownHelper">
                                <div class="card-body p-3">
                                  <div class="row">
                                    <div class="col-md-6">
                                      <h6 class="text-primary mb-2"><i class="bx bx-bold me-1"></i>Text Formatting</h6>
                                      <ul class="list-unstyled mb-3">
                                        <li class="mb-2">
                                          <code>**bold text**</code> → <strong>bold text</strong>
                                        </li>
                                        <li class="mb-2">
                                          <code>*italic text*</code> → <em>italic text</em>
                                        </li>
                                        <li class="mb-2">
                                          <code>~~strikethrough~~</code> → <del>strikethrough</del>
                                        </li>
                                      </ul>
                                      
                                      <h6 class="text-primary mb-2"><i class="bx bx-link me-1"></i>Links</h6>
                                      <ul class="list-unstyled mb-3">
                                        <li class="mb-2">
                                          <code>[Link Text](https://example.com)</code>
                                        </li>
                                      </ul>
                                    </div>
                                    <div class="col-md-6">
                                      <h6 class="text-primary mb-2"><i class="bx bx-list-ul me-1"></i>Lists</h6>
                                      <ul class="list-unstyled mb-3">
                                        <li class="mb-2">
                                          <strong>Numbered:</strong><br>
                                          <code>1. First item<br>2. Second item</code>
                                        </li>
                                        <li class="mb-2">
                                          <strong>Bullets:</strong><br>
                                          <code>- First item<br>- Second item</code>
                                        </li>
                                      </ul>
                                      
                                      <h6 class="text-primary mb-2"><i class="bx bx-heading me-1"></i>Headings</h6>
                                      <ul class="list-unstyled mb-3">
                                        <li class="mb-2">
                                          <code># Heading 1</code><br>
                                          <code>## Heading 2</code>
                                        </li>
                                      </ul>
                                    </div>
                                  </div>
                                  <div class="alert alert-info mb-0 p-2" role="alert">
                                    <small><i class="bx bx-info-circle me-1"></i>Your message will be automatically converted to HTML format when sent.</small>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <div class="input-group">
                              <textarea id="message" name="message" class="form-control" rows="7"
                                placeholder="Message (Supports Markdown formatting)" aria-label="Message"
                                aria-describedby="message" required></textarea>
                            </div>
                          </div>
                        </div>
                        <div class="row justify-content-end">
                          <div class="col-sm-10">
                            <button type="submit" class="btn btn-primary email-form-btn">Send</button>
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

  <!-- Core JS -->
  <!-- build:js assets/vendor/js/core.js -->
  <script src="assets/vendor/libs/jquery/jquery.js"></script>
  <script src="assets/vendor/libs/popper/popper.js"></script>
  <script src="assets/vendor/js/bootstrap.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

  <script src="assets/vendor/js/menu.js"></script>
  <!-- endbuild -->

  <!-- Vendors JS -->
  <script src="assets/js/ui-toasts.js"></script>

  <!-- Main JS -->
  <script src="assets/js/main.js"></script>

  <!-- Page JS -->
  <script src="assets/js/pages-account-settings-account.js"></script>

  <script type="text/javascript">
    // Get the 'tab' parameter from the URL
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');

    // Populate Admission Year select with ranges like 2019/2020
    function getAdmissionYears() {
      var $selectElement = $('#admissionYear');
      var currentYear = new Date().getFullYear();
      var startYear = 2019;
      // Clear existing generated options but keep placeholder
      $selectElement.find('option').not('[value=""]').remove();
      for (var year = currentYear + 1; year >= startYear; year--) {
        var range = (year - 1) + '/' + year;
        $selectElement.append($('<option/>', { value: range, text: range }));
      }
    }

    if (tabParam) {
      // Deactivate all tabs and their content
      $('.nav-link').removeClass('active');
      $('.tab-pane').removeClass('active show');

      // Activate the target tab and content
      $(`button[data-bs-target="#navs-top-${tabParam}"]`).addClass('active');
      $(`#navs-top-${tabParam}`).addClass('active show');
    }

    $(document).ready(function () {
      // Flag to track when loading student profile to avoid race conditions
      var loadingStudentProfile = false;
      
      // Initialize admission year options
      getAdmissionYears();
      $.ajax({
        type: 'GET',
        url: 'model/getInfo.php',
        data: { get_data: 'schools' },
        success: function (data) {
          // Get the select element
          var school_select = $('#school');

          // Iterate through the departments and add options
          $.each(data.schools, function (index, schools) {
            // Append each department as an option to the select element
            school_select.append($('<option>', {
              value: schools.id,
              text: schools.name
            }));
          });
        }
      });

      $('#search_profile-form').submit(function (event) {
        event.preventDefault();

        var button = $('.search_profile-btn');
        var originalText = button.html();

        $('#profile-form').hide(500);

        button.html('<div class="spinner-border spinner-border-sm text-white mx-auto" role="status"><span class="visually-hidden">Loading...</span>');
        button.prop('disabled', true);

        setTimeout(function () {
          $.ajax({
            type: 'POST',
            url: 'model/student.php',
            data: $('#search_profile-form').serialize(),
            success: function (data) {
              if (data.status == 'success') {
                // Set flag to prevent school change handler from loading departments
                loadingStudentProfile = true;

                $('#first_name').val(data.student_fn);
                $('#last_name').val(data.student_ln);
                $('#email').val(data.student_email);
                $('#phone').val(data.student_phone);
                $('#school').val(data.student_sch).trigger('change');
                $('#matric_no').val(data.student_matric);
                // Ensure admission years are populated
                if ($('#admissionYear option').length <= 1) {
                  getAdmissionYears();
                }
                // Set selected admission year; append if not in list
                (function() {
                  var admVal = data.student_adm_year;
                  var $sel = $('#admissionYear');
                  if (admVal && $sel.find('option').filter(function(){ return $(this).val() === admVal; }).length === 0) {
                    $sel.append($('<option>', { value: admVal, text: admVal }));
                  }
                  $sel.val(admVal).trigger('change');
                })();
                $('#role').val(data.student_role).trigger('change');

                $.ajax({
                  type: 'POST',
                  url: 'model/getInfo.php',
                  data: { get_data: 'depts', school: data.student_sch },
                  success: function (data_) {
                    // Get the select element
                    var dept = $('#depts');
                    dept.empty();

                    // Iterate through the departments and add options
                    $.each(data_.departments, function (index, departments) {
                      // Append each department as an option to the select element
                      dept.append($('<option>', {
                        value: departments.id,
                        text: departments.name
                      }));
                    });
                    // Set the department value immediately after options are loaded
                    // This ensures Select2 has the options available when setting the value
                    dept.val(data.student_dept).trigger('change');
                    // Reset flag after department is set
                    loadingStudentProfile = false;
                  },
                  error: function() {
                    // Reset flag on error to prevent it from staying true indefinitely
                    loadingStudentProfile = false;
                  }
                });

                showToast('bg-success', data.message); 

                $('#profile-form').show(500);
              } else {
                showToast('bg-danger', data.message);
                // Reset flag on error to prevent it from staying true indefinitely
                loadingStudentProfile = false;
              }

              button.html(originalText);
              button.prop("disabled", false);
            },
            error: function() {
              showToast('bg-danger', 'An error occurred while searching for the student.');
              // Reset flag in case of network error
              loadingStudentProfile = false;
              button.html(originalText);
              button.prop("disabled", false);
            }
          });
          }, 1000);
        });

      $('#search_verify-form').submit(function (event) {
        event.preventDefault();

        var button = $('.search_verify-btn');
        var originalText = button.html();

        $('.profile_info').hide(500);

        button.html('<div class="spinner-border spinner-border-sm text-white mx-auto" role="status"><span class="visually-hidden">Loading...</span>');
        button.prop('disabled', true);

        setTimeout(function () {
          $.ajax({
            type: 'POST',
            url: 'model/student.php',
            data: $('#search_verify-form').serialize(),
            success: function (data) {
              if (data.status == 'success') {
                showToast('bg-success', data.message);

                $('.student_fullname').html(data.student_fn + ' ' + data.student_ln + ' - <span class="badge bg-info fw-bold">' + data.student_role + '</span>');
                $('.student_email').html(data.student_email);
                $('#student_fname').val(data.student_fn);
                $('#student_role').val(data.student_role);
                $('#student_email_').val(data.student_email);
                $('.student_phone').html(data.student_phone);
                $('.student_sch').html(data.student_sch);
                $('.student_matric').html(data.student_matric);
                $('.student_status').val(data.student_status).trigger('change');
                $('.acct_no').html(data.acct_no);
                $('.acct_name').html(data.acct_name);

                // Fetch data from the JSON file
                $.getJSON('model/all-banks-NG-flw.json', function (data_) {
                  var bankCode = data.acct_bank;

                  var selectedBank = data_.data.find(function (bank) {
                    return bank.code === bankCode;
                  });

                  if (selectedBank) {
                    $('.acct_bank').html(selectedBank.name);
                  } else {
                    $('.acct_bank').html('Unknown Bank');
                  }
                });


                $.ajax({
                  type: 'GET',
                  url: 'model/getInfo.php',
                  data: { get_data: 'school_dept', school: data.student_sch, dept: data.student_dept, },
                  success: function (data_) {
                    $('.student_dept').html(data_.departments);
                    $('.student_sch').html(data_.schools);
                  }
                });

                $('.profile_info').show(500);
              } else {
                showToast('bg-danger', data.message);
              }

              button.html(originalText);
              button.prop("disabled", false);
            }
          });
        }, 1000);
      });

      $('#profile-form').submit(function (event) {
        event.preventDefault();

        var button = $('.profile-form-btn');
        var originalText = button.html();

        button.html('<div class="spinner-border spinner-border-sm text-white mx-auto" role="status"><span class="visually-hidden">Loading...</span>');
        button.prop('disabled', true);

        $.ajax({
          type: 'POST',
          url: 'model/student.php',
          data: $('#profile-form').serialize(),
          success: function (data) {
            if (data.status == 'success') {
              showToast('bg-success', data.message);
            } else {
              showToast('bg-danger', data.message);
            }

            button.html(originalText);
            button.prop("disabled", false);
          }
        });
      });

      $('#verify-form').submit(function (event) {
        event.preventDefault();

        var button = $('.verify-form-btn');
        var originalText = button.html();

        button.html('<div class="spinner-border spinner-border-sm text-white mx-auto" role="status"><span class="visually-hidden">Loading...</span>');
        button.prop('disabled', true);

        $.ajax({
          type: 'POST',
          url: 'model/student.php',
          data: $('#verify-form').serialize(),
          success: function (data) {
            if (data.status == 'success') {
              showToast('bg-success', data.message);
            } else {
              showToast('bg-danger', data.message);
            }

            button.html(originalText);
            button.prop("disabled", false);
          }
        });
      });

      // Handle recipient type change
      $('#recipient_type').change(function () {
        var type = $(this).val();
        
        // Hide all conditional rows
        $('#single_email_row').hide();
        $('#school_select_row').hide();
        $('#faculty_select_row').hide();
        $('#dept_select_row').hide();
        $('#student_count_row').hide();
        
        // Reset form elements
        $('#cus_email').val('').removeAttr('required');
        $('#email_school').val('').trigger('change');
        $('#email_faculty').val('').trigger('change');
        $('#email_dept').val('').trigger('change');
        $('#student_count').text('0');
        
        // Show appropriate fields based on selection
        if (type === 'single') {
          $('#single_email_row').show();
          $('#cus_email').attr('required', 'required');
          $('#student_count').text('1');
          $('#student_count_row').show();
        } else if (type === 'all_students' || type === 'all_hoc' || type === 'all_students_hoc') {
          calculateStudentCount();
          $('#student_count_row').show();
        } else if (type === 'school') {
          $('#school_select_row').show();
          loadSchoolsForEmail();
        } else if (type === 'faculty') {
          $('#school_select_row').show();
          $('#faculty_select_row').show();
          loadSchoolsForEmail();
        } else if (type === 'dept') {
          $('#school_select_row').show();
          $('#dept_select_row').show();
          loadSchoolsForEmail();
        }
      });

      // Load schools for email form
      function loadSchoolsForEmail() {
        $.ajax({
          type: 'GET',
          url: 'model/getInfo.php',
          data: { get_data: 'schools' },
          success: function (data) {
            var school_select = $('#email_school');
            school_select.empty().append($('<option>', { value: '', text: 'Select School' }));
            
            $.each(data.schools, function (index, school) {
              // Only show active schools
              if (school.status === 'active') {
                school_select.append($('<option>', {
                  value: school.id,
                  text: school.name
                }));
              }
            });
          }
        });
      }

      // Handle school selection for email
      $('#email_school').change(function () {
        var schoolId = $(this).val();
        var recipientType = $('#recipient_type').val();
        
        $('#email_faculty').empty().append($('<option>', { value: '', text: 'Select Faculty' }));
        $('#email_dept').empty().append($('<option>', { value: '', text: 'Select Department' }));
        
        if (schoolId && recipientType === 'school') {
          calculateStudentCount();
        } else if (schoolId && recipientType === 'faculty') {
          // Load faculties
          $.ajax({
            type: 'POST',
            url: 'model/getInfo.php',
            data: { get_data: 'faculties', school: schoolId },
            success: function (data) {
              if (data.status === 'success' && data.faculties) {
                $.each(data.faculties, function (index, faculty) {
                  // Only show active faculties
                  if (faculty.status === 'active') {
                    $('#email_faculty').append($('<option>', {
                      value: faculty.id,
                      text: faculty.name
                    }));
                  }
                });
              }
            }
          });
        } else if (schoolId && recipientType === 'dept') {
          // Load departments
          $.ajax({
            type: 'POST',
            url: 'model/getInfo.php',
            data: { get_data: 'depts', school: schoolId },
            success: function (data) {
              if (data.status === 'success' && data.departments) {
                $.each(data.departments, function (index, dept) {
                  // Only show active departments
                  if (dept.status === 'active') {
                    $('#email_dept').append($('<option>', {
                      value: dept.id,
                      text: dept.name
                    }));
                  }
                });
              }
            }
          });
        }
      });

      // Handle faculty selection
      $('#email_faculty').change(function () {
        if ($(this).val()) {
          calculateStudentCount();
        }
      });

      // Handle department selection
      $('#email_dept').change(function () {
        if ($(this).val()) {
          calculateStudentCount();
        }
      });

      // Calculate student count based on selection
      function calculateStudentCount() {
        var recipientType = $('#recipient_type').val();
        var schoolId = $('#email_school').val();
        var facultyId = $('#email_faculty').val();
        var deptId = $('#email_dept').val();
        
        $.ajax({
          type: 'POST',
          url: 'model/support.php',
          data: {
            get_student_count: 1,
            recipient_type: recipientType,
            school_id: schoolId,
            faculty_id: facultyId,
            dept_id: deptId
          },
          success: function (data) {
            if (data.status === 'success') {
              $('#student_count').text(data.count);
              $('#student_count_row').show();
            }
          }
        });
      }

      $('#email-form').submit(function (event) {
        event.preventDefault();

        var button = $('.email-form-btn');
        var originalText = button.html();
        var recipientType = $('#recipient_type').val();
        
        // Validate recipient type selection
        if (!recipientType) {
          showToast('bg-danger', 'Please select recipient type');
          return;
        }

        // Validate single email if selected
        if (recipientType === 'single' && !$('#cus_email').val()) {
          showToast('bg-danger', 'Please enter student email');
          return;
        }

        // Validate selections for filtered recipients
        if (recipientType === 'school' && !$('#email_school').val()) {
          showToast('bg-danger', 'Please select a school');
          return;
        }
        if (recipientType === 'faculty' && (!$('#email_school').val() || !$('#email_faculty').val())) {
          showToast('bg-danger', 'Please select school and faculty');
          return;
        }
        if (recipientType === 'dept' && (!$('#email_school').val() || !$('#email_dept').val())) {
          showToast('bg-danger', 'Please select school and department');
          return;
        }

        button.html('<div class="spinner-border spinner-border-sm text-white mx-auto" role="status"><span class="visually-hidden">Loading...</span>');
        button.prop('disabled', true);

        $.ajax({
          type: 'POST',
          url: 'model/support.php',
          data: $('#email-form').serialize(),
          success: function (data) {
            if (data.status == 'success') {
              showToast('bg-success', data.message);

              $('#email-form')[0].reset();
              $('#recipient_type').trigger('change');
            } else {
              showToast('bg-danger', data.message);
            }

            button.html(originalText);
            button.prop("disabled", false);
          }
        });
      });

      $('#school').change(function (event) {
        // Skip loading departments if we're in the middle of loading a student profile
        if (loadingStudentProfile) {
          return;
        }
        student_sch = $('#school').val();
  
        $.ajax({
          type: 'POST',
          url: 'model/getInfo.php',
          headers: {
            'Cache-Control': 'no-store, no-cache, must-revalidate'
          },
          data: { get_data: 'depts', school: student_sch },
          success: function (data_) {
            // Get the select element
            var dept = $('#depts');
            dept.empty();
  
            // Iterate through the departments and add options
            $.each(data_.departments, function (index, departments) {
              // Append each department as an option to the select element
              dept.append($('<option>', {
                value: departments.id,
                text: departments.name
              }));
            });
          }
        });
      });
      
      $('.select_special').select2({
        theme: "bootstrap-5",
        width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
        placeholder: $(this).data('placeholder'),
        closeOnSelect: false
      });
    });
  </script>
</body>

</html>

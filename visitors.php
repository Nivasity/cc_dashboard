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

  <title>Public Users | Nivasity Command Center</title>

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
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Customer Management /</span> Public Users</h4>

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
                        class="bx bx-user-check me-1"></i> Verify User</button>
                  </li>
                  <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab"
                      data-bs-target="#navs-top-email" aria-controls="navs-top-email" aria-selected="false"><i
                        class="bx bx-envelope me-1"></i> Email Users</button>
                  </li>
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
                              <input type="text" class="form-control" id="user_data" name="user_data"
                                placeholder="User Email / Phone"
                                aria-label="User Email / Phone" aria-describedby="user_data" />
                            </div>
                          </div>
                          <div class="col-sm-3">
                            <button type="submit" class="btn btn-secondary w-100 search_profile-btn">Search</button>
                          </div>
                        </div>
                      </form>
                    </div>
                    <hr class="my-0" />
                    <div class="card-body">
                      <form id="profile-form" style="display: none;">
                        <input type="hidden" name="edit_profile" value="0" />
                        <div class="row">
                          <div class="mb-3 col-md-6">
                            <label for="first_name" class="form-label">First Name</label>
                            <input class="form-control" type="text" id="first_name" name="first_name" required />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input class="form-control" type="text" id="last_name" name="last_name" required />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="email" class="form-label">E-mail</label>
                            <input class="form-control" type="text" id="email" name="email" readonly />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="phone">Phone Number</label>
                            <div class="input-group">
                              <input type="text" id="phone" name="phone" class="form-control" required />
                            </div>
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="business_name">business_name</label>
                            <div class="input-group">
                              <input type="text" id="business_name" name="business_name" class="form-control" required />
                            </div>
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="business_address">business_address</label>
                            <div class="input-group">
                              <input type="text" id="business_address" name="business_address" class="form-control" required />
                            </div>
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="web_url">web_url</label>
                            <div class="input-group">
                              <input type="text" id="web_url" name="web_url" class="form-control" required />
                            </div>
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="work_email">work_email</label>
                            <div class="input-group">
                              <input type="text" id="work_email" name="work_email" class="form-control" required />
                            </div>
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="socials">socials</label>
                            <div class="input-group">
                              <input type="text" id="socials" name="socials" class="form-control" required />
                            </div>
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="role">Role</label>
                            <select id="role" name="role" class="form-select" required>
                              <option value="visitor">Visitor</option>
                              <option value="org_admin">Business Owner</option>
                            </select>
                          </div>
                        </div>
                        <div class="mt-2">
                          <button type="submit" class="btn btn-primary me-2 profile-form-btn">Save changes</button>
                        </div>
                      </form>
                    </div>
                    <!-- /Account -->
                  </div>

                  <div class="card mb-4 tab-pane fade" id="navs-top-verify" role="tabpanel">
                    <div class="card-header">
                      <h5>Verify User</h5>
                      <form id="search_verify-form">
                        <div class="row mb-3">
                          <div class="col-sm-8 mb-3 mb-sm-0">
                            <div class="input-group">
                              <input type="text" class="form-control" id="user_data" name="user_data"
                                placeholder="User Email / Phone"
                                aria-label="User Email / Phone" aria-describedby="user_data" />
                            </div>
                          </div>
                          <div class="col-sm-3">
                            <button type="submit" class="btn btn-secondary w-100 search_verify-btn">Search</button>
                          </div>
                        </div>
                      </form>
                    </div>
                    <hr class="my-0" />
                    <div class="card-body">
                      <dl class="row mt-2 profile_info" style="display: none;">
                        <dt class="col-sm-3">Full Name - Role</dt>
                        <dd class="col-sm-9 text-uppercase user_fullname">
                          Samuel Akinyemi
                        </dd>
                        <dt class="col-sm-3">Phone Number</dt>
                        <dd class="col-sm-9 user_phone">
                          +2347048706198
                        </dd>
                        <dt class="col-sm-3">Email</dt>
                        <dd class="col-sm-9">
                          <p class="user_email">akinyemisamuel170@gmail.com</p>
                        </dd>

                        <dt class="col-sm-3">Business Name</dt>
                        <dd class="col-sm-9 text-uppercase business_name">
                          University of lagos
                        </dd>
                        <dt class="col-sm-3">Business Address</dt>
                        <dd class="col-sm-9 business_address">
                          Computer Science
                        </dd>
                        <dt class="col-sm-3">Website</dt>
                        <dd class="col-sm-9 web_url">
                          Computer Science
                        </dd>
                        <dt class="col-sm-3">Contact Email</dt>
                        <dd class="col-sm-9 work_email">
                          Computer Science
                        </dd>
                        <dt class="col-sm-3">Social Media</dt>
                        <dd class="col-sm-9">
                          <p class="socials">akinyemisamuel170@gmail.com</p>
                        </dd>

                        <dt class="col-sm-3 hideables">Account Name</dt>
                        <dd class="col-sm-9 text-uppercase acct_no hideables">
                          SAMUEL AYOMIDE AKINYEMI
                        </dd>
                        <dt class="col-sm-3">Account number</dt>
                        <dd class="col-sm-9 acct_name hideables">
                          1454746632
                        </dd>
                        <dt class="col-sm-3 hideables">Bank Name</dt>
                        <dd class="col-sm-9 hideables">
                          <p class="acct_bank text-uppercase hideables">Access Bank</p>
                        </dd>

                        <form id="verify-form">
                          <input type="hidden" id="user_email_" name="user_email_" />
                          <dt class="col-sm-3 text-primary">User Status</dt>
                          <dd class="col-sm-3">
                            <select class="form-select user_status" name="user_status">
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

                  <div class="card mb-4 tab-pane fade" id="navs-top-email" role="tabpanel">
                    <h5 class="card-header">Email User(s)</h5>
                    <hr class="my-0" />
                    <div class="card-body">
                      <form id="email-form">
                        <input type="hidden" name="email_customer" value="1" />
                        <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="cus_email">User Email</label>
                          <div class="col-sm-10">
                            <div class="input-group input-group-merge">
                              <span class="input-group-text"><i class="bx bx-user"></i></span>
                              <input type="text" id="cus_email" name="cus_email" class="form-control"
                                placeholder="customer@example.com" aria-label="customer@example.com"
                                aria-describedby="user_data">
                            </div>
                          </div>
                        </div>
                        <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="subject">Subject</label>
                          <div class="col-sm-10">
                            <div class="input-group input-group-merge">
                              <span class="input-group-text"><i class="bx bx-envelope"></i></span>
                              <input type="text" class="form-control" id="subject" name="subject" placeholder="Re: Thanks for... "
                                aria-label="John Doe" aria-describedby="subject">
                            </div>
                          </div>
                        </div>
                        <div class="row mb-3">
                          <label class="col-sm-2 form-label" for="message">Message</label>
                          <div class="col-sm-10">
                            <div class="input-group">
                              <textarea id="message" name="message" class="form-control" rows="7"
                                placeholder="Message" aria-label="Message"
                                aria-describedby="message"></textarea>
                            </div>
                          </div>
                        </div>
                        <div class="row justify-content-end">
                          <div class="col-sm-10">
                            <button type="submit" class="btn btn-primary email-form-btn">Send</button>
                            <select class="form-select user_status w-25 d-inline" name="user_status">
                              <option value="verified">Verified</option>
                              <option value="unverified">Unverified</option>
                              <option value="inreview">Inreview</option>
                              <option value="denied">Temporary Deactivated</option>
                              <option value="deactivate">Deleted</option>
                            </select>
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

    if (tabParam) {
      // Deactivate all tabs and their content
      $('.nav-link').removeClass('active');
      $('.tab-pane').removeClass('active show');

      // Activate the target tab and content
      $(`button[data-bs-target="#navs-top-${tabParam}"]`).addClass('active');
      $(`#navs-top-${tabParam}`).addClass('active show');
    }

    $(document).ready(function() {
      $('#search_profile-form').submit(function(event) {
        event.preventDefault();

        var button = $('.search_profile-btn');
        var originalText = button.html();

        $('#profile-form').hide(500);

        button.html('<div class="spinner-border spinner-border-sm text-white mx-auto" role="status"><span class="visually-hidden">Loading...</span>');
        button.prop('disabled', true);

        setTimeout(function() {
          $.ajax({
            type: 'POST',
            url: 'model/user.php',
            data: $('#search_profile-form').serialize(),
            success: function(data) {
              if (data.status == 'success') {
                showToast('bg-success', data.message);

                $('#first_name').val(data.user_fn);
                $('#last_name').val(data.user_ln);
                $('#email').val(data.user_email);
                $('#phone').val(data.user_phone);
                $('#role').val(data.user_role);
                $('#business_name').val(data.business_name);
                $('#business_address').val(data.business_address);
                $('#web_url').val(data.web_url);
                $('#work_email').val(data.work_email);
                $('#socials').val(data.socials);

                $('#profile-form').show(500);
              } else {
                showToast('bg-danger', data.message);
              }

              button.html(originalText);
              button.prop("disabled", false);
            }
          });
        }, 1000);
      });

      $('#search_verify-form').submit(function(event) {
        event.preventDefault();

        var button = $('.search_verify-btn');
        var originalText = button.html();

        $('.profile_info').hide(500);

        button.html('<div class="spinner-border spinner-border-sm text-white mx-auto" role="status"><span class="visually-hidden">Loading...</span>');
        button.prop('disabled', true);

        setTimeout(function() {
          $.ajax({
            type: 'POST',
            url: 'model/user.php',
            data: $('#search_verify-form').serialize(),
            success: function(data) {
              if (data.status == 'success') {
                showToast('bg-success', data.message);

                $('.user_fullname').html(data.user_fn + ' ' + data.user_ln + ' - <span class="badge bg-info fw-bold">' + data.user_role + '</span>');
                $('.user_email').html(data.user_email);
                $('#user_email_').val(data.user_email);
                $('.user_phone').html(data.user_phone);
                $('.user_status').val(data.user_status);
                $('.acct_no').html(data.acct_no);
                $('.acct_name').html(data.acct_name);
                $('.business_name').html(data.business_name);
                $('.business_address').html(data.business_address);
                $('.web_url').val(data.web_url);
                $('.work_email').html(data.work_email);
                $('.socials').html(data.socials);

                if (data.user_role == 'visitor') {
                  $('.hideables').hide();
                } else {
                  $('.hideables').show();
                  // Fetch data from the JSON file
                  $.getJSON('model/all-banks-NG-flw.json', function(data_) {
                    var bankCode = data.acct_bank;

                    var selectedBank = data_.data.find(function(bank) {
                      return bank.code === bankCode;
                    });

                    if (selectedBank) {
                      $('.acct_bank').html(selectedBank.name);
                    } else {
                      $('.acct_bank').html('Unknown Bank');
                    }
                  });
                }

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

      $('#profile-form').submit(function(event) {
        event.preventDefault();

        var button = $('.profile-form-btn');
        var originalText = button.html();

        button.html('<div class="spinner-border spinner-border-sm text-white mx-auto" role="status"><span class="visually-hidden">Loading...</span>');
        button.prop('disabled', true);

        $.ajax({
          type: 'POST',
          url: 'model/user.php',
          data: $('#profile-form').serialize(),
          success: function(data) {
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

      $('#verify-form').submit(function(event) {
        event.preventDefault();

        var button = $('.verify-form-btn');
        var originalText = button.html();

        button.html('<div class="spinner-border spinner-border-sm text-white mx-auto" role="status"><span class="visually-hidden">Loading...</span>');
        button.prop('disabled', true);

        $.ajax({
          type: 'POST',
          url: 'model/user.php',
          data: $('#verify-form').serialize(),
          success: function(data) {
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

      $('#email-form').submit(function(event) {
        event.preventDefault();

        var button = $('.email-form-btn');
        var originalText = button.html();

        button.html('<div class="spinner-border spinner-border-sm text-white mx-auto" role="status"><span class="visually-hidden">Loading...</span>');
        button.prop('disabled', true);

        $.ajax({
          type: 'POST',
          url: 'model/support.php',
          data: $('#email-form').serialize(),
          success: function(data) {
            if (data.status == 'success') {
              showToast('bg-success', data.message);

              $('#email-form')[0].reset();
            } else {
              showToast('bg-danger', data.message);
            }

            button.html(originalText);
            button.prop("disabled", false);
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
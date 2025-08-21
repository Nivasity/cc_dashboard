<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$profileMsg = '';
$passwordMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['update_profile'])) {
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);

    $picture = $admin_image;
    if (!empty($_FILES['upload']['name'])) {
      $extension = pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION);
      $picture = 'user' . time() . '.' . $extension;
      $destination = "assets/images/users/$picture";
      if (!is_dir('assets/images/users')) {
        mkdir('assets/images/users', 0755, true);
      }
      if ($admin_image !== 'user.jpg' && file_exists("assets/images/users/$admin_image")) {
        unlink("assets/images/users/$admin_image");
      }
      move_uploaded_file($_FILES['upload']['tmp_name'], $destination);
    }

    mysqli_query($conn, "UPDATE admins SET first_name = '$firstname', last_name = '$lastname', email = '$email', phone = '$phone', profile_pic = '$picture' WHERE id = $admin_id");
    if (mysqli_affected_rows($conn) >= 1) {
      $profileMsg = 'Profile updated successfully.';
      $admin_image = $picture;
      $admin_email = $email;
      $admin_phone = $phone;
      $f_name = $firstname;
      $l_name = $lastname;
    } else {
      $profileMsg = 'No changes were made.';
    }
  } elseif (isset($_POST['change_password'])) {
    $curr = md5($_POST['password']);
    $new = md5($_POST['new_password']);
    $user_query = mysqli_query($conn, "SELECT id FROM admins WHERE id = $admin_id AND password = '$curr'");
    if (mysqli_num_rows($user_query) == 1) {
      mysqli_query($conn, "UPDATE admins SET password = '$new' WHERE id = $admin_id");
      if (mysqli_affected_rows($conn) >= 1) {
        $passwordMsg = 'Password successfully changed.';
      } else {
        $passwordMsg = 'Unable to change password.';
      }
    } else {
      $passwordMsg = 'Current password is incorrect.';
    }
  }
}

$profile_pic_path = file_exists("assets/images/users/$admin_image") ? "assets/images/users/$admin_image" : "assets/img/avatars/user.png";

?>

<!DOCTYPE html>

<html
  lang="en"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="assets/"
  data-template="vertical-menu-template-free"
>
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>My Profile | Nivasity Command Center</title>

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
              <h4 class="fw-bold py-3 mb-4">My Profile</h4>

              <div class="row">
                <div class="col-md-12">
                  <div class="card mb-4">
                    <h5 class="card-header">Profile Details</h5>
                    <!-- Account -->
                    <div class="card-body">
                      <div class="d-flex align-items-start align-items-sm-center gap-4">
                        <img
                          src="<?php echo $profile_pic_path; ?>"
                          alt="user-avatar"
                          class="d-block rounded"
                          height="100"
                          width="100"
                          id="uploadedAvatar"
                        />
                        <div class="button-wrapper">
                          <label for="upload" class="btn btn-primary me-2 mb-4" tabindex="0">
                            <span class="d-none d-sm-block">Upload new photo</span>
                            <i class="bx bx-upload d-block d-sm-none"></i>
                            <input
                              type="file"
                              id="upload"
                              name="upload"
                              class="account-file-input"
                              hidden
                              accept="image/png, image/jpeg"
                            />
                          </label>
                          <button type="button" class="btn btn-outline-secondary account-image-reset mb-4">
                            <i class="bx bx-reset d-block d-sm-none"></i>
                            <span class="d-none d-sm-block">Reset</span>
                          </button>

                          <p class="text-muted mb-0">Allowed JPG, GIF or PNG. Max size of 800K</p>
                        </div>
                      </div>
                    </div>
                    <hr class="my-0" />
                    <div class="card-body">
                      <form id="formAccountSettings" method="POST" enctype="multipart/form-data">
                        <div class="row">
                          <div class="mb-3 col-md-6">
                            <label for="firstName" class="form-label">First Name</label>
                            <input
                              class="form-control"
                              type="text"
                              id="firstName"
                              name="firstname"
                              value="<?php echo htmlspecialchars($f_name); ?>"
                              autofocus
                            />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input class="form-control" type="text" name="lastname" id="lastName" value="<?php echo htmlspecialchars($l_name); ?>" />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="email" class="form-label">E-mail</label>
                            <input
                              class="form-control"
                              type="email"
                              id="email"
                              name="email"
                              value="<?php echo htmlspecialchars($admin_email); ?>"
                              placeholder="john.doe@example.com"
                            />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="phoneNumber">Phone Number</label>
                            <div class="input-group input-group-merge">
                              <span class="input-group-text">NG (+234)</span>
                              <input
                                type="text"
                                id="phoneNumber"
                                name="phone"
                                class="form-control"
                                placeholder="704 506 5564"
                                value="<?php echo htmlspecialchars($admin_phone); ?>"
                              />
                            </div>
                          </div>
                        </div>
                        <div class="mt-2">
                          <button type="submit" class="btn btn-primary me-2" name="update_profile">Save changes</button>
                          <button type="reset" class="btn btn-outline-secondary">Cancel</button>
                        </div>
                      </form>
                      <?php if ($profileMsg) { echo '<div class="alert alert-info mt-2">' . $profileMsg . '</div>'; } ?>
                    </div>
                    <!-- /Account -->
                    <div class="card">
                      <h5 class="card-header">Change Password</h5>
                      <div class="card-body">
                        <form id="formAccountDeactivation" method="POST">
                          <div class="row">
                            <div class="mb-3 col-md-6">
                              <div class="form-password-toggle">
                                <label for="password" class="form-label">Current Password</label>
                                <div class="input-group input-group-merge">
                                  <input type="password" class="form-control" id="password" name="password" placeholder="···········" aria-describedby="password">
                                  <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                                </div>
                              </div>
                            </div>
                            <div class="mb-3 col-md-6">
                              <div class="form-password-toggle">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group input-group-merge">
                                  <input type="password" class="form-control" id="new_password" name="new_password" placeholder="··········" aria-describedby="new_password">
                                  <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                                </div>
                              </div>
                            </div>
                          </div>
                          <button type="submit" class="btn btn-secondary" name="change_password">Submit</button>
                        </form>
                        <?php if ($passwordMsg) { echo '<div class="alert alert-info mt-2">' . $passwordMsg . '</div>'; } ?>
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
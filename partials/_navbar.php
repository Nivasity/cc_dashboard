<?php
$nav_pic = file_exists("assets/images/users/$admin_image") ? "assets/images/users/$admin_image" : "assets/img/avatars/user.png";
$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;
$role6_only = $admin_role === 6;
?>
<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
  <?php if (!$role6_only) { ?>
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
      <i class="bx bx-menu bx-sm"></i>
    </a>
  </div>
  <?php } ?>

  <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
    <!-- Search -->
    <div class="navbar-nav align-items-center">
      <div class="nav-item d-flex align-items-center cc-global-search" data-global-search-container>
        <i class="bx bx-search fs-4 lh-0"></i>
        <input
          type="text"
          id="globalSearchInput"
          class="form-control border-0 shadow-none"
          placeholder="Search academics, finance, materials, tickets..."
          aria-label="Global search"
          autocomplete="off"
          data-global-search-input />
        <div class="cc-global-search-dropdown card d-none" data-global-search-dropdown>
          <div class="cc-global-search-results" data-global-search-results></div>
        </div>
      </div>
    </div>
    <!-- /Search -->

    <ul class="navbar-nav flex-row align-items-center ms-auto">
      <li class="nav-item me-2">
        <button
          type="button"
          class="btn btn-sm btn-outline-secondary d-flex align-items-center"
          data-theme-toggle
          aria-label="Toggle light and dark theme"
          title="Toggle light and dark theme">
          <i class="bx bx-moon me-1" data-theme-toggle-icon></i>
          <span class="d-none d-md-inline" data-theme-toggle-label>Dark</span>
        </button>
      </li>
      <!-- User -->
      <li class="nav-item navbar-dropdown dropdown-user dropdown">
        <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-offset="0,8">
          <div class="avatar avatar-online">
            <img src="<?php echo $nav_pic; ?>" alt class="w-px-40 h-auto rounded-circle" />
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end dropdown-user popper-safe-dropdown">
          <li>
            <a class="dropdown-item" href="#">
              <div class="d-flex">
                <div class="flex-shrink-0 me-3">
                  <div class="avatar avatar-online">
                    <img src="<?php echo $nav_pic; ?>" alt class="w-px-40 h-auto rounded-circle" />
                  </div>
                </div>
                <div class="flex-grow-1">
                  <span class="fw-semibold d-block"><?php echo htmlspecialchars($admin_name); ?></span>
                  <small class="text-muted">Admin</small>
                </div>
              </div>
            </a>
          </li>
          <li>
            <div class="dropdown-divider"></div>
          </li>
          <?php if (!$role6_only) { ?>
            <li>
              <a class="dropdown-item" href="profile.php">
                <i class="bx bx-user me-2"></i>
                <span class="align-middle">My Profile</span>
              </a>
            </li>
            <li>
              <div class="dropdown-divider"></div>
            </li>
          <?php } ?>
          <li>
            <a class="dropdown-item" href="signin.html?logout=1">
              <i class="bx bx-power-off me-2"></i>
              <span class="align-middle">Log Out</span>
            </a>
          </li>
        </ul>
      </li>
      <!--/ User -->
    </ul>
  </div>
</nav>

<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : '';
?>

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
  <div class="app-brand">
    <a href="/" class="app-brand-link">
      <img class="img-fluid app-brand-logo " src="assets/img/nivasity_cc_logo_exp.png">
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
      <i class="bx bx-chevron-left bx-sm align-middle"></i>
    </a>
  </div>

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    <!-- Dashboard -->
    <li class="menu-item <?php echo $current_page == 'index.php' ? 'active' : '' ?>">
      <a href="/" class="menu-link">
        <i class="menu-icon tf-icons bx bx-home-circle"></i>
        <div data-i18n="Analytics">Dashboard</div>
      </a>
    </li>
    <!-- My Profile -->
    <!-- <li class="menu-item"> -->
      <!-- <a href="profile.php" class="menu-link"> -->
      <!-- <a href="#" class="menu-link">
        <i class="menu-icon tf-icons bx bx-user-circle"></i>
        <div data-i18n="My Profile">My Profile</div>
      </a>
    </li> -->

    <?php if ($admin_mgt_menu){ ?>
      <!-- Admin Management -->
      <li class="menu-header small text-uppercase"><span class="menu-header-text">Admin Management</span></li>
      <li class="menu-item">
        <a href="javascript:void(0);" class="menu-link menu-toggle">
          <i class="menu-icon tf-icons bx bx-group"></i>
          <div data-i18n="Admin Management">Admins</div>
        </a>
        <ul class="menu-sub">
          <li class="menu-item">
            <a href="admin.php" class="menu-link">
              <div data-i18n="Admins">Profiles</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="admin.php" class="menu-link">
              <div data-i18n="Admins">Roles</div>
            </a>
          </li>
        </ul>
      </li>
      <li class="menu-item">
        <a href="sign_up_key.php" class="menu-link">
          <i class="menu-icon tf-icons bx bx-key"></i>
          <div data-i18n="Admin Management">Sign Up Keys</div>
        </a>
      </li>
    <?php } ?>
    
    <?php if ($customer_mgt_menu) { ?>
      <!-- Customer Management -->
      <li class="menu-header small text-uppercase"><span class="menu-header-text">Customer Management</span></li>
      <?php if ($_SESSION['nivas_adminRole'] == 5) { ?>
        <li class="menu-item <?php echo $current_page == 'school.php' ? 'active open' : ''; ?>">
          <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bxs-school"></i>
            <div data-i18n="Schools">Schools</div>
          </a>
          <ul class="menu-sub">
            <li class="menu-item <?php echo $current_page == 'school.php' && $current_tab == 'faculties' ? 'active' : ''; ?>">
              <a href="school.php?tab=faculties" class="menu-link">
                <div data-i18n="Schools">Faculties</div>
              </a>
            </li>
            <li class="menu-item <?php echo $current_page == 'school.php' && $current_tab == 'departments' ? 'active' : ''; ?>">
              <a href="school.php?tab=departments" class="menu-link">
                <div data-i18n="Schools">Departments</div>
              </a>
            </li>
          </ul>
        </li>
      <?php } elseif ($sch_mgt_menu) { ?>
        <li class="menu-item <?php echo $current_page == 'school.php' ? 'active open' : ''; ?>">
          <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bxs-school"></i>
            <div data-i18n="Customer Management">Schools</div>
          </a>
          <ul class="menu-sub">
            <li class="menu-item <?php echo $current_page == 'school.php' && $current_tab == '' ? 'active' : ''; ?>">
              <a href="school.php" class="menu-link">
              <div data-i18n="Schools">School List</div>
              </a>
            </li>
            <li class="menu-item <?php echo $current_page == 'school.php' && $current_tab == 'faculties' ? 'active' : ''; ?>">
              <a href="school.php?tab=faculties" class="menu-link">
                <div data-i18n="Schools">Faculties</div>
              </a>
            </li>
            <li class="menu-item <?php echo $current_page == 'school.php' && $current_tab == 'departments' ? 'active' : ''; ?>">
              <a href="school.php?tab=departments" class="menu-link">
                <div data-i18n="Schools">Departments</div>
              </a>
            </li>
          </ul>
        </li>
      <?php } ?>
      <?php if ($student_mgt_menu) { ?>
        <li class="menu-item <?php echo $current_page == 'students.php' ? 'active open' : ''; ?>">
          <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bx-user-pin"></i>
            <div data-i18n="Customer Management">Students</div>
          </a>
          <ul class="menu-sub">
            <li class="menu-item <?php echo $current_page == 'students.php' && $current_tab == '' ? 'active' : ''; ?>">
              <a href="students.php" class="menu-link">
                <div>Student Profile</div>
              </a>
            </li>
            <?php if ($admin_role != 5) { ?>
            <li class="menu-item <?php echo $current_page == 'students.php' && $current_tab == 'verify' ? 'active' : ''; ?>">
              <a href="students.php?tab=verify" class="menu-link">
                <div>Verify Student</div>
              </a>
            </li>
            <li class="menu-item <?php echo $current_page == 'students.php' && $current_tab == 'email' ? 'active' : ''; ?>">
              <a href="students.php?tab=email" class="menu-link">
                <div>Email Students</div>
              </a>
            </li>
            <?php } ?>
          </ul>
        </li>
      <?php } ?>
      <?php if ($public_mgt_menu) { ?>
        <li class="menu-item <?php echo $current_page == 'visitors.php' ? 'active open' : ''; ?>">
          <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bx-group"></i>
            <div data-i18n="Customer Management">Public Users</div>
          </a>
          <ul class="menu-sub">
            <li class="menu-item <?php echo $current_page == 'visitors.php' && $current_tab == '' ? 'active' : ''; ?>">
              <a href="visitors.php" class="menu-link">
                <div>User Profile</div>
              </a>
            </li>
            <li class="menu-item <?php echo $current_page == 'visitors.php' && $current_tab == 'verify' ? 'active' : ''; ?>">
              <a href="visitors.php?tab=verify" class="menu-link">
                <div>Verify User</div>
              </a>
            </li>
            <li class="menu-item <?php echo $current_page == 'visitors.php' && $current_tab == 'email' ? 'active' : ''; ?>">
              <a href="visitors.php?tab=email" class="menu-link">
                <div>Email User</div>
              </a>
            </li>
          </ul>
        </li>
      <?php } ?>
      <?php if ($support_mgt_menu) { ?>
        <li class="menu-item">
          <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bx-support"></i>
            <div data-i18n="Customer Management">Support Tickets</div>
          </a>
          <ul class="menu-sub">
            <li class="menu-item">
              <!-- <a href="open_tickets.php" class="menu-link"> -->
              <a href="#" class="menu-link">
                <div data-i18n="Support Tickets">Opened Tickets</div>
              </a>
            </li>
            <li class="menu-item">
              <!-- <a href="closed_tickets.php" class="menu-link"> -->
              <a href="#" class="menu-link">
                <div data-i18n="Support Tickets">Closed Tickets</div>
              </a>
            </li>
          </ul>
        </li>
      <?php } ?>
    <?php } ?>
    
    <?php if ($finance_mgt_menu){ ?>
      <!-- Financial report -->
      <li class="menu-header small text-uppercase"><span class="menu-header-text">Financial report</span></li>
      <li class="menu-item">
        <a href="transactions.php" class="menu-link">
          <i class="menu-icon tf-icons bx bx-transfer"></i>
          <div data-i18n="Financial report">Transactions</div>
        </a>
      </li>
    <?php } ?>

    <?php if ($resource_mgt_menu){ ?>
      <!-- Resources Management -->
      <li class="menu-header small text-uppercase"><span class="menu-header-text">Resources Management</span></li>
      <li class="menu-item <?php echo $current_page == 'course_materials.php' ? 'active' : ''; ?>">
        <a href="course_materials.php" class="menu-link">
          <i class="menu-icon tf-icons bx bx-book"></i>
          <div data-i18n="Course Materials">Course Materials</div>
        </a>
      </li>
    <?php } ?>

    <!-- Sign Out -->
    <li class="menu-header small text-uppercase"></li>
    <li class="menu-item">
      <a href="signin.html?logout=1" class="menu-link">
        <i class="menu-icon tf-icons bx bx-log-out"></i>
        <div data-i18n="Sign Out">Sign Out</div>
      </a>
    </li>
  </ul>
</aside>
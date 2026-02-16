<?php
session_start();
include('model/config.php');
include('model/page_config.php');

// Dashboard metrics
$admin_role = (int)($_SESSION['nivas_adminRole'] ?? 0);
$admin_school = (int)($admin_['school'] ?? 0);
$admin_faculty = (int)($admin_['faculty'] ?? 0);
$school_clause = ($admin_role == 5 && $admin_school > 0) ? " AND school = $admin_school" : '';

// Time range filtering
$range = $_GET['range'] ?? '24h';
switch ($range) {
  case 'weekly':
    $current_start = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 14 DAY)";
    $prev_end = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
    break;
  case 'monthly':
    $current_start = "DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 2 MONTH)";
    $prev_end = "DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    break;
  case 'quarter':
    $current_start = "DATE_SUB(NOW(), INTERVAL 3 MONTH)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 6 MONTH)";
    $prev_end = "DATE_SUB(NOW(), INTERVAL 3 MONTH)";
    break;
  case 'yearly':
    $current_start = "DATE_SUB(NOW(), INTERVAL 1 YEAR)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 2 YEAR)";
    $prev_end = "DATE_SUB(NOW(), INTERVAL 1 YEAR)";
    break;
  default:
    $range = '24h';
    $current_start = "DATE_SUB(NOW(), INTERVAL 1 DAY)";
    $prev_start = "DATE_SUB(NOW(), INTERVAL 2 DAY)";
    $prev_end = "DATE_SUB(NOW(), INTERVAL 1 DAY)";
}

$range_labels = [
  '24h' => 'Past 24 Hrs',
  'weekly' => 'Past 7 Days',
  'monthly' => 'Past 30 Days',
  'quarter' => 'Past 90 Days',
  'yearly' => 'Past 365 Days'
];
$range_label = $range_labels[$range] ?? 'Past 24 Hrs';

// Count unverified HOCs
$hoc_count = mysqli_fetch_assoc(
  mysqli_query(
    $conn,
    "SELECT COUNT(*) AS count FROM users WHERE role = 'hoc' AND status = 'inreview'{$school_clause}"
  )
)["count"];

// Support ticket statistics (v2)
$support_open_sql = "SELECT COUNT(*) AS count FROM support_tickets_v2 st JOIN users u ON st.user_id = u.id WHERE st.status = 'open'";
if ($admin_role == 5 && $admin_school > 0) {
  $support_open_sql .= " AND u.school = $admin_school";
}
$open_tickets = mysqli_fetch_assoc(mysqli_query($conn, $support_open_sql))["count"];

$support_total_sql = "SELECT COUNT(*) AS count FROM support_tickets_v2 st JOIN users u ON st.user_id = u.id";
if ($admin_role == 5 && $admin_school > 0) {
  $support_total_sql .= " WHERE u.school = $admin_school";
}
$total_tickets = mysqli_fetch_assoc(mysqli_query($conn, $support_total_sql))["count"];
$resolved_tickets = $total_tickets - $open_tickets;
$resolved_percent = $total_tickets > 0 ? round(($resolved_tickets / $total_tickets) * 100, 2) : 0;

// Financial statistics
$revenue_base = "SELECT COALESCE(SUM(t.profit),0) AS total FROM transactions t WHERE t.status = 'successful'";
if ($admin_role == 5 && $admin_school > 0) {
  $revenue_base .= " AND EXISTS (SELECT 1 FROM manuals_bought b JOIN manuals m ON b.manual_id = m.id LEFT JOIN depts d ON m.dept = d.id WHERE b.ref_id = t.ref_id AND b.status='successful' AND b.school_id = $admin_school";
  if ($admin_faculty != 0) {
    $revenue_base .= " AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";
  }
  $revenue_base .= ")";
}
// Revenue and sales within selected range (for cards)
$revenue_sql = $revenue_base . " AND t.created_at >= $current_start";
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, $revenue_sql))["total"];
$prev_revenue_sql = $revenue_base . " AND t.created_at >= $prev_start AND t.created_at < $current_start";
$prev_revenue = mysqli_fetch_assoc(mysqli_query($conn, $prev_revenue_sql))["total"];

if ($admin_role == 5) {
  $total_revenue *= 0.1;
  $prev_revenue *= 0.1;
}

$growth_diff = $total_revenue - $prev_revenue;
$growth_percent = $prev_revenue > 0
  ? (abs($growth_diff) / $prev_revenue) * 100
  : ($total_revenue > 0 ? 100 : 0);
$growth_percent = round($growth_percent, 2);
$growth_sign = $growth_diff >= 0 ? '+' : '-';
$revenue_class = $growth_diff >= 0 ? 'text-success' : 'text-danger';
$revenue_icon = $growth_diff >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt';

$sales_base = "SELECT COALESCE(SUM(t.amount),0) AS total FROM transactions t WHERE t.status = 'successful'";
if ($admin_role == 5 && $admin_school > 0) {
  $sales_base .= " AND EXISTS (SELECT 1 FROM manuals_bought b JOIN manuals m ON b.manual_id = m.id LEFT JOIN depts d ON m.dept = d.id WHERE b.ref_id = t.ref_id AND b.status='successful' AND b.school_id = $admin_school";
  if ($admin_faculty != 0) {
    $sales_base .= " AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";
  }
  $sales_base .= ")";
}
$sales_sql = $sales_base . " AND t.created_at >= $current_start";
$total_sales = mysqli_fetch_assoc(mysqli_query($conn, $sales_sql))["total"];
$sales_prev_sql = $sales_base . " AND t.created_at >= $prev_start AND t.created_at < $current_start";
$prev_sales = mysqli_fetch_assoc(mysqli_query($conn, $sales_prev_sql))["total"];
$sales_diff = $total_sales - $prev_sales;
$sales_growth_percent = $prev_sales > 0
  ? (abs($sales_diff) / $prev_sales) * 100
  : ($total_sales > 0 ? 100 : 0);
$sales_growth_percent = round($sales_growth_percent, 2);
$sales_growth_sign = $sales_diff >= 0 ? '+' : '-';
$sales_class = $sales_diff >= 0 ? 'text-success' : 'text-danger';
$sales_icon = $sales_diff >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt';

// School manager (role 5) specific snapshot metrics
$fac_count = 0;
$dept_count = 0;
$open_materials = 0;
if ($admin_role == 5 && $admin_school > 0) {
  $fac_count = 1;
  // Faculties count: only relevant when manager is at school level (no specific faculty assigned)
  if ($admin_faculty == 0) {
    $fac_count = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM faculties WHERE status='active' AND school_id = $admin_school"))['count'] ?? 0);
  }
  // Departments scoped: by school if no faculty, else within assigned faculty
  if ($admin_faculty == 0) {
    $dept_count = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM depts WHERE status='active' AND school_id = $admin_school"))['count'] ?? 0);
  } else {
    $dept_count = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM depts WHERE status='active' AND school_id = $admin_school AND faculty_id = $admin_faculty"))['count'] ?? 0);
  }
  // Open materials: status open; scoped by faculty if assigned
  if ($admin_faculty == 0) {
    $open_materials = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM manuals m WHERE m.status='open' AND m.school_id = $admin_school"))['count'] ?? 0);
  } else {
    $open_materials = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM manuals m LEFT JOIN depts d ON m.dept = d.id WHERE m.status='open' AND m.school_id = $admin_school AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))"))['count'] ?? 0);
  }
}

// Annual revenue comparison for growth chart
$selected_year = $_GET['year'] ?? date('Y');
$curr_year = (int)$selected_year;
$prev_year = $curr_year - 1;

$annual_sql = $revenue_base . " AND YEAR(t.created_at) = $curr_year";
$annual_total_revenue = mysqli_fetch_assoc(mysqli_query($conn, $annual_sql))["total"];
$annual_prev_sql = $revenue_base . " AND YEAR(t.created_at) = $prev_year";
$annual_prev_revenue = mysqli_fetch_assoc(mysqli_query($conn, $annual_prev_sql))["total"];
if ($admin_role == 5) {
  $annual_total_revenue *= 0.1;
  $annual_prev_revenue *= 0.1;
}
$annual_growth_percent = 0;
if ($annual_prev_revenue == 0) {
  $annual_growth_percent = $annual_total_revenue > 0 ? 100 : 0;
} elseif ($annual_total_revenue > $annual_prev_revenue) {
  $annual_growth_percent = (($annual_total_revenue - $annual_prev_revenue) / $annual_prev_revenue) * 100;
}
$annual_growth_percent = round($annual_growth_percent, 2);

if ($admin_role == 5 && $admin_school > 0 && $admin_faculty != 0) {
  $users_sql = "SELECT COUNT(*) AS count FROM users u LEFT JOIN depts d ON u.dept = d.id WHERE u.school = $admin_school AND d.faculty_id = $admin_faculty";
} else {
  $users_sql = "SELECT COUNT(*) AS count FROM users WHERE 1{$school_clause}";
}
$total_users = mysqli_fetch_assoc(mysqli_query($conn, $users_sql))["count"];

// Additional metrics (current year only)
$transactions_base = "SELECT COALESCE(SUM(t.amount),0) AS total FROM transactions t";
$transactions_where = " WHERE t.status = 'successful' AND YEAR(t.created_at) = YEAR(CURDATE())";
if ($admin_role == 5 && $admin_school > 0) {
  $transactions_where .= " AND EXISTS (SELECT 1 FROM manuals_bought b JOIN manuals m ON b.manual_id = m.id LEFT JOIN depts d ON m.dept = d.id WHERE b.ref_id = t.ref_id AND b.status='successful' AND b.school_id = $admin_school";
  if ($admin_faculty != 0) {
    $transactions_where .= " AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";
  }
  $transactions_where .= ")";
}
$transactions_sql = $transactions_base . $transactions_where;
$transactions_amount = mysqli_fetch_assoc(mysqli_query($conn, $transactions_sql))["total"];

// Monthly revenue data for chart
$monthly_current = [];
$monthly_previous = [];
for ($m = 1; $m <= 12; $m++) {
  $month_sql = $revenue_base . " AND YEAR(t.created_at) = $curr_year AND MONTH(t.created_at) = $m";
  $month_total = mysqli_fetch_assoc(mysqli_query($conn, $month_sql))["total"];
  $prev_month_sql = $revenue_base . " AND YEAR(t.created_at) = $prev_year AND MONTH(t.created_at) = $m";
  $prev_month_total = mysqli_fetch_assoc(mysqli_query($conn, $prev_month_sql))["total"];
  if ($admin_role == 5) {
    $month_total *= 0.1;
    $prev_month_total *= 0.1;
  }
  $monthly_current[] = (int)$month_total;
  $monthly_previous[] = (int)$prev_month_total;
}

?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Dashboard - Nivasity Command Center</title>

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
            <div class="row">
              <div class="col-lg-8 mb-4 order-0">
                <div class="card">
                  <div class="d-flex align-items-end row">
                    <div class="col-sm-7">
                      <div class="card-body">
                        <h5 class="card-title text-primary">Hello <?php echo htmlspecialchars($f_name); ?>! 🎉</h5>
                        <p class="mb-4">
                          <?php if ((int)$admin_role === 5): ?>
                            Your <?php echo ($admin_faculty != 0 ? 'faculty/college' : 'school'); ?> at a glance:<br>
                            <span class="fw-bold"><?php echo number_format((int)$total_users); ?></span> active students<br>
                            <span class="fw-bold"><?php echo (int)$fac_count; ?></span> faculties •
                            <span class="fw-bold"><?php echo (int)$dept_count; ?></span> departments •
                            <span class="fw-bold"><?php echo (int)$open_materials; ?></span> open materials
                          <?php else: ?>
                            We have got: <br>
                            <span class="fw-bold"><?php echo (int)$hoc_count; ?></span> new HOCs waiting to be verified<br>
                            <span class="fw-bold"><?php echo (int)$open_tickets; ?></span> open support tickets
                          <?php endif; ?>
                        </p>
                      </div>
                    </div>
                    <div class="col-sm-5 text-center text-sm-left">
                      <div class="card-body pb-0 px-0 px-md-4">
                        <img src="assets/img/illustrations/man-with-laptop-light.png" height="140" alt="View Badge User"
                          data-app-dark-img="illustrations/man-with-laptop-dark.png"
                          data-app-light-img="illustrations/man-with-laptop-light.png" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4 col-md-4 order-1">
                <div class="row">
                  <div class="col-lg-6 col-md-12 col-6 mb-4">
                    <div class="card">
                      <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                          <div class="avatar flex-shrink-0">
                            <img src="assets/img/icons/unicons/chart-success.png" alt="chart success" class="rounded" />
                          </div>
                          <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle range-display" type="button" id="rangeDropdownRevenue" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                              <?php echo $range_label; ?>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="rangeDropdownRevenue">
                              <a class="dropdown-item range-option" href="#" data-range="24h">Past 24 Hrs</a>
                              <a class="dropdown-item range-option" href="#" data-range="weekly">Past 7 Days</a>
                              <a class="dropdown-item range-option" href="#" data-range="monthly">Past 30 Days</a>
                              <a class="dropdown-item range-option" href="#" data-range="quarter">Past 90 Days</a>
                            </div>
                          </div>
                        </div>
                        <span><?php echo ($admin_role == 5 ? 'Your Commission' : 'Total Revenue'); ?></span>
                          <h3 id="total-revenue-amount" class="card-title text-nowrap mb-1">₦<?php echo number_format($total_revenue); ?></h3>
                          <small id="total-revenue-growth" class="<?php echo $revenue_class; ?> fw-semibold"><i class="bx <?php echo $revenue_icon; ?>"></i> <?php echo $growth_sign . $growth_percent; ?>%</small>
                      </div>
                    </div>
                  </div>
                  <div class="col-lg-6 col-md-12 col-6 mb-4">
                    <div class="card">
                      <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                          <div class="avatar flex-shrink-0">
                            <img src="assets/img/icons/unicons/wallet-info.png" alt="Credit Card" class="rounded" />
                          </div>
                          <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle range-display" type="button" id="rangeDropdownSales" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                              <?php echo $range_label; ?>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="rangeDropdownSales">
                              <a class="dropdown-item range-option" href="#" data-range="24h">Past 24 Hrs</a>
                              <a class="dropdown-item range-option" href="#" data-range="weekly">Past 7 Days</a>
                              <a class="dropdown-item range-option" href="#" data-range="monthly">Past 30 Days</a>
                              <a class="dropdown-item range-option" href="#" data-range="quarter">Past 90 Days</a>
                            </div>
                          </div>
                        </div>
                        <span><?php echo ($admin_role == 5 ? 'School Sales' : 'Sales'); ?></span>
                          <h3 id="total-sales-amount" class="card-title text-nowrap mb-1">₦<?php echo number_format($total_sales); ?></h3>
                          <small id="total-sales-growth" class="<?php echo $sales_class; ?> fw-semibold"><i class="bx <?php echo $sales_icon; ?>"></i> <?php echo $sales_growth_sign . $sales_growth_percent; ?>%</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Total Revenue -->
              <div class="col-12 col-lg-8 order-2 order-md-3 order-lg-2 mb-4">
                <div class="card">
                  <div class="row row-bordered g-0">
                    <div class="col-md-8">
                      <h5 class="card-header m-0 me-2 pb-3">Total Revenue</h5>
                      <div id="totalRevenueChart" class="px-2"
                        data-curr-year="<?php echo $curr_year; ?>"
                        data-prev-year="<?php echo $prev_year; ?>"
                        data-current='<?php echo json_encode($monthly_current); ?>'
                        data-prev='<?php echo json_encode($monthly_previous); ?>'></div>
                    </div>
                    <div class="col-md-4">
                      <div class="card-body">
                        <div class="text-center">
                          <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                              id="growthReportId" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                              <?php echo $curr_year; ?>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="growthReportId">
                              <?php for ($y = date('Y'); $y > date('Y') - 5; $y--): ?>
                                <a class="dropdown-item year-option" href="#" data-year="<?php echo $y; ?>"><?php echo $y; ?></a>
                              <?php endfor; ?>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div id="growthChart" data-growth="<?php echo $annual_growth_percent; ?>"></div>
                      <div id="growth-chart-text" class="text-center fw-semibold pt-3 mb-2"><?php echo round($annual_growth_percent); ?>% <?php echo ($admin_role == 5 ? 'School Growth' : 'Company Growth'); ?></div>

                      <div class="d-flex px-xxl-4 px-lg-2 p-4 gap-xxl-3 gap-lg-1 gap-3 justify-content-between">
                        <div class="d-flex">
                          <div class="me-2">
                              <span class="badge bg-label-primary p-2"><i class="bx bx-dollar text-primary"></i></span>
                          </div>
                          <div class="d-flex flex-column">
                            <small id="current-year-label"><?php echo $curr_year; ?></small>
                              <h6 id="current-year-amount" class="mb-0">₦<?php echo number_format($annual_total_revenue); ?></h6>
                          </div>
                        </div>
                        <div class="d-flex">
                          <div class="me-2">
                              <span class="badge bg-label-info p-2"><i class="bx bx-wallet text-info"></i></span>
                          </div>
                          <div class="d-flex flex-column">
                            <small id="prev-year-label"><?php echo $prev_year; ?></small>
                              <h6 id="prev-year-amount" class="mb-0">₦<?php echo number_format($annual_prev_revenue); ?></h6>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!--/ Total Revenue -->
              <div class="col-12 col-md-8 col-lg-4 order-3 order-md-2">
                <div class="row">
                  <div class="col-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                        <div class="card-title d-flex align-items-start">
                          <div class="avatar flex-shrink-0">
                            <img src="assets/img/icons/unicons/paypal.png" alt="Credit Card" class="rounded" />
                          </div>
                        </div>
                            <span class="d-block mb-1">Total Users</span>
                            <h3 class="card-title text-nowrap mb-2"><?php echo number_format($total_users); ?></h3>
                          <small class="text-muted fw-semibold">Registered</small>
                      </div>
                      </div>
                    </div>
                    <div class="col-6 mb-4">
                      <div class="card">
                      <div class="card-body">
                        <div class="card-title d-flex align-items-start">
                          <div class="avatar flex-shrink-0">
                            <img src="assets/img/icons/unicons/cc-primary.png" alt="Credit Card" class="rounded" />
                          </div>
                        </div>
                          <span class="fw-semibold d-block mb-1">Transactions</span>
                          <h3 class="card-title mb-2">₦<?php echo number_format($transactions_amount); ?></h3>
                        <small class="text-muted fw-semibold">Total amount</small>
                      </div>
                      </div>
                    </div>
                  <?php if ((int)$admin_role !== 5): ?>
                  <div class="col-12 mb-4">
                    <div class="card">
                      <div class="card-body">
                        <div class="d-flex justify-content-between flex-sm-row flex-column gap-3">
                          <div class="d-flex flex-sm-column flex-row align-items-start justify-content-between">
                            <div class="card-title">
                              <h5 class="text-nowrap mb-2">Support Ticket Status</h5>
                              <span class="badge bg-label-primary rounded-pill">Open Tickets</span>
                            </div>
                            <div class="mt-sm-auto">
                              <small class="text-muted text-nowrap fw-semibold"><?php echo $resolved_percent; ?>% Resolved</small>
                              <h3 class="mb-0"><?php echo $open_tickets; ?></h3>
                            </div>
                          </div>
                          <div id="ticketStatusChart" data-resolved-percent="<?php echo $resolved_percent; ?>"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            
            <!-- Latest Transactions and Internal Tickets -->
            <div class="row">
              <!-- Latest Transactions Card -->
              <div class="col-12 col-lg-6 mb-4 d-flex">
                <div class="card w-100">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Latest Transactions</h5>
                    <a href="transactions.php" class="btn btn-sm btn-outline-primary">View All Transactions</a>
                  </div>
                  <div class="table-responsive text-nowrap">
                    <table class="table">
                      <thead class="table-light">
                        <tr>
                          <th>Ref ID</th>
                          <th>Student</th>
                          <th>Amount</th>
                          <th>Status</th>
                          <th>Date</th>
                        </tr>
                      </thead>
                      <tbody id="latestTransactionsTable" class="table-border-bottom-0">
                        <tr>
                          <td colspan="5" class="text-center">Loading...</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- Latest Internal Support Tickets Card -->
              <div class="col-12 col-lg-6 mb-4 d-flex">
                <div class="card w-100">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Latest Internal Support Tickets</h5>
                    <a href="admin_tickets.php" class="btn btn-sm btn-outline-primary">View All Internal Messages</a>
                  </div>
                  <div class="table-responsive text-nowrap">
                    <table class="table">
                      <thead class="table-light">
                        <tr>
                          <th>Code</th>
                          <th>Subject</th>
                          <th>Priority</th>
                          <th>Status</th>
                          <th>Date</th>
                        </tr>
                      </thead>
                      <tbody id="latestAdminTicketsTable" class="table-border-bottom-0">
                        <tr>
                          <td colspan="5" class="text-center">Loading...</td>
                        </tr>
                      </tbody>
                    </table>
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
  <script src="assets/vendor/libs/apex-charts/apexcharts.js"></script>

  <!-- Main JS -->
  <script src="assets/js/main.js"></script>

  <!-- Page JS -->
  <script src="assets/js/dashboards-analytics.js"></script>
  <script src="assets/js/range.js"></script>

  <!-- Dashboard Latest Data JS -->
  <script>
    // Helper function to escape HTML
    function escapeHtml(text) {
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    $(document).ready(function() {
      // Fetch latest transactions
      $.ajax({
        url: 'model/dashboard_latest_transactions.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
          if (response.status === 'success' && response.transactions.length > 0) {
            let html = '';
            response.transactions.forEach(function(txn) {
              let statusBadge = '';
              const status = escapeHtml(txn.status);
              if (txn.status === 'successful') {
                statusBadge = '<span class="badge bg-label-success">Success</span>';
              } else if (txn.status === 'pending') {
                statusBadge = '<span class="badge bg-label-warning">Pending</span>';
              } else if (txn.status === 'failed') {
                statusBadge = '<span class="badge bg-label-danger">Failed</span>';
              } else if (txn.status === 'refunded') {
                statusBadge = '<span class="badge bg-label-info">Refunded</span>';
              } else {
                statusBadge = '<span class="badge bg-label-secondary">' + status + '</span>';
              }
              
              html += '<tr>' +
                '<td><small>' + escapeHtml(txn.ref_id) + '</small></td>' +
                '<td><strong>' + escapeHtml(txn.student) + '</strong><br><small class="text-muted">' + escapeHtml(txn.matric) + '</small></td>' +
                '<td>₦' + Number(txn.amount).toLocaleString() + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td><small>' + escapeHtml(txn.date) + '<br>' + escapeHtml(txn.time) + '</small></td>' +
                '</tr>';
            });
            $('#latestTransactionsTable').html(html);
          } else {
            $('#latestTransactionsTable').html('<tr><td colspan="5" class="text-center text-muted">No transactions found</td></tr>');
          }
        },
        error: function() {
          $('#latestTransactionsTable').html('<tr><td colspan="5" class="text-center text-danger">Error loading transactions</td></tr>');
        }
      });

      // Fetch latest internal support tickets
      $.ajax({
        url: 'model/dashboard_latest_admin_tickets.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
          if (response.status === 'success' && response.tickets.length > 0) {
            let html = '';
            response.tickets.forEach(function(ticket) {
              let priorityBadge = '';
              const priority = escapeHtml(ticket.priority);
              if (ticket.priority === 'urgent') {
                priorityBadge = '<span class="badge bg-label-danger">Urgent</span>';
              } else if (ticket.priority === 'high') {
                priorityBadge = '<span class="badge bg-label-warning">High</span>';
              } else if (ticket.priority === 'medium') {
                priorityBadge = '<span class="badge bg-label-info">Medium</span>';
              } else if (ticket.priority === 'low') {
                priorityBadge = '<span class="badge bg-label-secondary">Low</span>';
              } else {
                priorityBadge = '<span class="badge bg-label-secondary">' + priority + '</span>';
              }
              
              let statusBadge = '';
              const status = escapeHtml(ticket.status);
              if (ticket.status === 'open') {
                statusBadge = '<span class="badge bg-label-success">Open</span>';
              } else if (ticket.status === 'pending') {
                statusBadge = '<span class="badge bg-label-warning">Pending</span>';
              } else if (ticket.status === 'resolved') {
                statusBadge = '<span class="badge bg-label-info">Resolved</span>';
              } else if (ticket.status === 'closed') {
                statusBadge = '<span class="badge bg-label-secondary">Closed</span>';
              } else {
                statusBadge = '<span class="badge bg-label-secondary">' + status + '</span>';
              }
              
              const subject = escapeHtml(ticket.subject);
              const truncatedSubject = subject.length > 30 ? subject.substring(0, 30) + '...' : subject;
              
              html += '<tr>' +
                '<td><small>' + escapeHtml(ticket.code) + '</small></td>' +
                '<td><strong>' + truncatedSubject + '</strong></td>' +
                '<td>' + priorityBadge + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td><small>' + escapeHtml(ticket.date) + '<br>' + escapeHtml(ticket.time) + '</small></td>' +
                '</tr>';
            });
            $('#latestAdminTicketsTable').html(html);
          } else {
            $('#latestAdminTicketsTable').html('<tr><td colspan="5" class="text-center text-muted">No tickets found</td></tr>');
          }
        },
        error: function() {
          $('#latestAdminTicketsTable').html('<tr><td colspan="5" class="text-center text-danger">Error loading tickets</td></tr>');
        }
      });
    });
  </script>

  <!-- Place this tag in your head or just before your close body tag. -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>

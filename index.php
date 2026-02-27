<?php
session_start();
include('model/config.php');
include('model/page_config.php');
include_once('model/dashboard_revenue_range.php');

// Dashboard metrics
$admin_role = (int)($_SESSION['nivas_adminRole'] ?? 0);
$admin_school = (int)($admin_['school'] ?? 0);
$admin_faculty = (int)($admin_['faculty'] ?? 0);
$school_clause = ($admin_role == 5 && $admin_school > 0) ? " AND school = $admin_school" : '';

// Time range filtering
$range_payload = get_dashboard_revenue_range_payload(
  $conn,
  $admin_role,
  $admin_school,
  $admin_faculty,
  $_GET['range'] ?? '24h'
);
$range = (string) ($range_payload['range'] ?? '24h');
$range_label = (string) ($range_payload['range_label'] ?? '24 Hours');

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
$total_revenue = (int) ($range_payload['total_revenue'] ?? 0);
$prev_revenue = (int) ($range_payload['prev_revenue'] ?? 0);
$growth_percent = (float) ($range_payload['growth_percent'] ?? 0);
$growth_sign = (string) ($range_payload['growth_sign'] ?? '+');
$revenue_class = (string) ($range_payload['revenue_class'] ?? 'text-success');
$revenue_icon = (string) ($range_payload['revenue_icon'] ?? 'bx-up-arrow-alt');
$total_sales = (int) ($range_payload['total_sales'] ?? 0);
$prev_sales = (int) ($range_payload['prev_sales'] ?? 0);
$sales_growth_percent = (float) ($range_payload['sales_growth_percent'] ?? 0);
$sales_growth_sign = (string) ($range_payload['sales_growth_sign'] ?? '+');
$sales_class = (string) ($range_payload['sales_class'] ?? 'text-success');
$sales_icon = (string) ($range_payload['sales_icon'] ?? 'bx-up-arrow-alt');
$chart_categories = $range_payload['chart_categories'] ?? ['24 Hours'];
$chart_current = $range_payload['chart_current'] ?? [0];
$chart_previous = $range_payload['chart_previous'] ?? [0];
$chart_current_label = (string) ($range_payload['chart_current_label'] ?? 'Current Period');
$chart_previous_label = (string) ($range_payload['chart_previous_label'] ?? 'Previous Period');

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
$revenue_base = dashboard_build_amount_base_sql('t.profit', $admin_role, $admin_school, $admin_faculty);
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
                              <a class="dropdown-item range-option" href="#" data-range="24h">24 Hours</a>
                              <a class="dropdown-item range-option" href="#" data-range="7d">7 Days</a>
                              <a class="dropdown-item range-option" href="#" data-range="30d">30 Days</a>
                              <a class="dropdown-item range-option" href="#" data-range="90d">90 Days</a>
                              <a class="dropdown-item range-option" href="#" data-range="this_year">This Year</a>
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
                              <a class="dropdown-item range-option" href="#" data-range="24h">24 Hours</a>
                              <a class="dropdown-item range-option" href="#" data-range="7d">7 Days</a>
                              <a class="dropdown-item range-option" href="#" data-range="30d">30 Days</a>
                              <a class="dropdown-item range-option" href="#" data-range="90d">90 Days</a>
                              <a class="dropdown-item range-option" href="#" data-range="this_year">This Year</a>
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
                      <div class="card-header d-flex align-items-center justify-content-between m-0 me-2 pb-3">
                        <h5 class="m-0">Total Revenue</h5>
                        <div class="dropdown">
                          <button class="btn btn-sm btn-outline-primary dropdown-toggle range-display" type="button" id="rangeDropdownRevenueChart" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <?php echo $range_label; ?>
                          </button>
                          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="rangeDropdownRevenueChart">
                            <a class="dropdown-item range-option" href="#" data-range="24h">24 Hours</a>
                            <a class="dropdown-item range-option" href="#" data-range="7d">7 Days</a>
                            <a class="dropdown-item range-option" href="#" data-range="30d">30 Days</a>
                            <a class="dropdown-item range-option" href="#" data-range="90d">90 Days</a>
                            <a class="dropdown-item range-option" href="#" data-range="this_year">This Year</a>
                          </div>
                        </div>
                      </div>
                      <div id="totalRevenueChart" class="px-2"
                        data-categories='<?php echo json_encode($chart_categories); ?>'
                        data-current-label="<?php echo htmlspecialchars($chart_current_label); ?>"
                        data-prev-label="<?php echo htmlspecialchars($chart_previous_label); ?>"
                        data-current='<?php echo json_encode($chart_current); ?>'
                        data-prev='<?php echo json_encode($chart_previous); ?>'></div>
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

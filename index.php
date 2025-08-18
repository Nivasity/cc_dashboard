<?php
session_start();
include('model/config.php');
include('model/page_config.php');

// Dashboard metrics
$admin_role = $_SESSION['nivas_adminRole'];
$admin_school = $admin_['school'];
$school_clause = ($admin_role == 5) ? " AND school = $admin_school" : '';

// Count unverified HOCs
$hoc_count = mysqli_fetch_assoc(
  mysqli_query(
    $conn,
    "SELECT COUNT(*) AS count FROM users WHERE role = 'hoc' AND status = 'unverified'{$school_clause}"
  )
)["count"];

// Count open support tickets
$support_sql = "SELECT COUNT(*) AS count FROM support_tickets st JOIN users u ON st.user_id = u.id WHERE st.status = 'open'";
if ($admin_role == 5) {
  $support_sql .= " AND u.school = $admin_school";
}
$support_count = mysqli_fetch_assoc(mysqli_query($conn, $support_sql))["count"];

// Financial statistics
$revenue_base = "SELECT COALESCE(SUM(t.profit),0) AS total FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status = 'successful'";
if ($admin_role == 5) {
  $revenue_base .= " AND u.school = $admin_school";
}
$curr_year = date('Y');
$prev_year = $curr_year - 1;

$revenue_sql = $revenue_base . " AND YEAR(t.created_at) = $curr_year";
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, $revenue_sql))["total"];
$prev_revenue_sql = $revenue_base . " AND YEAR(t.created_at) = $prev_year";
$prev_revenue = mysqli_fetch_assoc(mysqli_query($conn, $prev_revenue_sql))["total"];

if ($admin_role == 5) {
  $total_revenue *= 0.1;
  $prev_revenue *= 0.1;
}

$growth_percent = $prev_revenue > 0 ? (($total_revenue - $prev_revenue) / $prev_revenue) * 100 : 0;
$growth_percent = round($growth_percent, 2);

$sales_sql = "SELECT COALESCE(SUM(t.amount),0) AS total FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status = 'successful' AND DATE(t.created_at) = CURDATE()";
if ($admin_role == 5) {
  $sales_sql .= " AND u.school = $admin_school";
}
$total_sales = mysqli_fetch_assoc(mysqli_query($conn, $sales_sql))["total"];

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
                          We have got: <br><span class="fw-bold"><?php echo $hoc_count; ?></span> new HOCs waiting to be verified
                          <br><span class="fw-bold"><?php echo $support_count; ?></span> opened support tickets
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
                            <button class="btn p-0" type="button" id="cardOpt3" data-bs-toggle="dropdown"
                              aria-haspopup="true" aria-expanded="false">
                              <i class="bx bx-dots-vertical-rounded"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt3">
                              <a class="dropdown-item" href="javascript:void(0);">View More</a>
                              <a class="dropdown-item" href="javascript:void(0);">Delete</a>
                            </div>
                          </div>
                        </div>
                        <span class="fw-semibold d-block mb-1">Total Revenue</span>
                        <h3 class="card-title mb-2">₦<?php echo number_format($total_revenue); ?></h3>
                        <small class="text-success fw-semibold"><i class="bx bx-up-arrow-alt"></i> +72.80%</small>
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
                            <button class="btn p-0" type="button" id="cardOpt6" data-bs-toggle="dropdown"
                              aria-haspopup="true" aria-expanded="false">
                              <i class="bx bx-dots-vertical-rounded"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt6">
                              <a class="dropdown-item" href="javascript:void(0);">View More</a>
                              <a class="dropdown-item" href="javascript:void(0);">Delete</a>
                            </div>
                          </div>
                        </div>
                        <span>Sales</span>
                        <h3 class="card-title text-nowrap mb-1">₦<?php echo number_format($total_sales); ?></h3>
                        <small class="text-success fw-semibold"><i class="bx bx-up-arrow-alt"></i> +28.42%</small>
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
                      <div id="totalRevenueChart" class="px-2"></div>
                    </div>
                    <div class="col-md-4">
                      <div class="card-body">
                        <div class="text-center">
                          <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                              id="growthReportId" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                              2022
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="growthReportId">
                              <a class="dropdown-item" href="javascript:void(0);">2021</a>
                              <a class="dropdown-item" href="javascript:void(0);">2020</a>
                              <a class="dropdown-item" href="javascript:void(0);">2019</a>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div id="growthChart" data-growth="<?php echo $growth_percent; ?>"></div>
                      <div class="text-center fw-semibold pt-3 mb-2"><?php echo round($growth_percent); ?>% Company Growth</div>

                      <div class="d-flex px-xxl-4 px-lg-2 p-4 gap-xxl-3 gap-lg-1 gap-3 justify-content-between">
                        <div class="d-flex">
                          <div class="me-2">
                              <span class="badge bg-label-primary p-2"><i class="bx bx-dollar text-primary"></i></span>
                          </div>
                          <div class="d-flex flex-column">
                            <small><?php echo $curr_year; ?></small>
                              <h6 class="mb-0">₦<?php echo number_format($total_revenue); ?></h6>
                          </div>
                        </div>
                        <div class="d-flex">
                          <div class="me-2">
                              <span class="badge bg-label-info p-2"><i class="bx bx-wallet text-info"></i></span>
                          </div>
                          <div class="d-flex flex-column">
                            <small><?php echo $prev_year; ?></small>
                              <h6 class="mb-0">₦<?php echo number_format($prev_revenue); ?></h6>
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
                        <div class="card-title d-flex align-items-start justify-content-between">
                          <div class="avatar flex-shrink-0">
                            <img src="assets/img/icons/unicons/paypal.png" alt="Credit Card" class="rounded" />
                          </div>
                          <div class="dropdown">
                            <button class="btn p-0" type="button" id="cardOpt4" data-bs-toggle="dropdown"
                              aria-haspopup="true" aria-expanded="false">
                              <i class="bx bx-dots-vertical-rounded"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt4">
                              <a class="dropdown-item" href="javascript:void(0);">View More</a>
                              <a class="dropdown-item" href="javascript:void(0);">Delete</a>
                            </div>
                          </div>
                        </div>
                          <span class="d-block mb-1">Payments</span>
                          <h3 class="card-title text-nowrap mb-2">₦2,456</h3>
                        <small class="text-danger fw-semibold"><i class="bx bx-down-arrow-alt"></i> -14.82%</small>
                      </div>
                    </div>
                  </div>
                  <div class="col-6 mb-4">
                    <div class="card">
                      <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                          <div class="avatar flex-shrink-0">
                            <img src="assets/img/icons/unicons/cc-primary.png" alt="Credit Card" class="rounded" />
                          </div>
                          <div class="dropdown">
                            <button class="btn p-0" type="button" id="cardOpt1" data-bs-toggle="dropdown"
                              aria-haspopup="true" aria-expanded="false">
                              <i class="bx bx-dots-vertical-rounded"></i>
                            </button>
                            <div class="dropdown-menu" aria-labelledby="cardOpt1">
                              <a class="dropdown-item" href="javascript:void(0);">View More</a>
                              <a class="dropdown-item" href="javascript:void(0);">Delete</a>
                            </div>
                          </div>
                        </div>
                          <span class="fw-semibold d-block mb-1">Transactions</span>
                          <h3 class="card-title mb-2">₦14,857</h3>
                        <small class="text-success fw-semibold"><i class="bx bx-up-arrow-alt"></i> +28.14%</small>
                      </div>
                    </div>
                  </div>
                  
                  
                  <div class="col-12 mb-4">
                    <div class="card">
                      <div class="card-body">
                        <div class="d-flex justify-content-between flex-sm-row flex-column gap-3">
                          <div class="d-flex flex-sm-column flex-row align-items-start justify-content-between">
                            <div class="card-title">
                              <h5 class="text-nowrap mb-2">Profile Report</h5>
                              <span class="badge bg-label-warning rounded-pill">Year 2021</span>
                            </div>
                            <div class="mt-sm-auto">
                              <small class="text-success text-nowrap fw-semibold"><i class="bx bx-chevron-up"></i>
                                68.2%</small>
                                <h3 class="mb-0">₦84,686k</h3>
                            </div>
                          </div>
                          <div id="profileReportChart"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <!-- Order Statistics -->
              <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-4">
                <div class="card h-100">
                  <div class="card-header d-flex align-items-center justify-content-between pb-0">
                    <div class="card-title mb-0">
                      <h5 class="m-0 me-2">Order Statistics</h5>
                      <small class="text-muted">42.82k Total Sales</small>
                    </div>
                    <div class="dropdown">
                      <button class="btn p-0" type="button" id="orederStatistics" data-bs-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-vertical-rounded"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end" aria-labelledby="orederStatistics">
                        <a class="dropdown-item" href="javascript:void(0);">Select All</a>
                        <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
                        <a class="dropdown-item" href="javascript:void(0);">Share</a>
                      </div>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <div class="d-flex flex-column align-items-center gap-1">
                        <h2 class="mb-2">8,258</h2>
                        <span>Total Orders</span>
                      </div>
                      <div id="orderStatisticsChart"></div>
                    </div>
                    <ul class="p-0 m-0">
                      <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-mobile-alt"></i></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <h6 class="mb-0">Electronic</h6>
                            <small class="text-muted">Mobile, Earbuds, TV</small>
                          </div>
                          <div class="user-progress">
                            <small class="fw-semibold">82.5k</small>
                          </div>
                        </div>
                      </li>
                      <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-success"><i class="bx bx-closet"></i></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <h6 class="mb-0">Fashion</h6>
                            <small class="text-muted">T-shirt, Jeans, Shoes</small>
                          </div>
                          <div class="user-progress">
                            <small class="fw-semibold">23.8k</small>
                          </div>
                        </div>
                      </li>
                      <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-info"><i class="bx bx-home-alt"></i></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <h6 class="mb-0">Decor</h6>
                            <small class="text-muted">Fine Art, Dining</small>
                          </div>
                          <div class="user-progress">
                            <small class="fw-semibold">849k</small>
                          </div>
                        </div>
                      </li>
                      <li class="d-flex">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-secondary"><i class="bx bx-football"></i></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <h6 class="mb-0">Sports</h6>
                            <small class="text-muted">Football, Cricket Kit</small>
                          </div>
                          <div class="user-progress">
                            <small class="fw-semibold">99</small>
                          </div>
                        </div>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
              <!--/ Order Statistics -->

              <!-- Expense Overview -->
              <div class="col-md-6 col-lg-4 order-1 mb-4">
                <div class="card h-100">
                  <div class="card-header">
                    <ul class="nav nav-pills" role="tablist">
                      <li class="nav-item">
                        <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab"
                          data-bs-target="#navs-tabs-line-card-income" aria-controls="navs-tabs-line-card-income"
                          aria-selected="true">
                          Income
                        </button>
                      </li>
                      <li class="nav-item">
                        <button type="button" class="nav-link" role="tab">Expenses</button>
                      </li>
                      <li class="nav-item">
                        <button type="button" class="nav-link" role="tab">Profit</button>
                      </li>
                    </ul>
                  </div>
                  <div class="card-body px-0">
                    <div class="tab-content p-0">
                      <div class="tab-pane fade show active" id="navs-tabs-line-card-income" role="tabpanel">
                        <div class="d-flex p-4 pt-3">
                          <div class="avatar flex-shrink-0 me-3">
                            <img src="assets/img/icons/unicons/wallet.png" alt="User" />
                          </div>
                          <div>
                            <small class="text-muted d-block">Total Balance</small>
                            <div class="d-flex align-items-center">
                                <h6 class="mb-0 me-1">₦459.10</h6>
                              <small class="text-success fw-semibold">
                                <i class="bx bx-chevron-up"></i>
                                42.9%
                              </small>
                            </div>
                          </div>
                        </div>
                        <div id="incomeChart"></div>
                        <div class="d-flex justify-content-center pt-4 gap-2">
                          <div class="flex-shrink-0">
                            <div id="expensesOfWeek"></div>
                          </div>
                          <div>
                            <p class="mb-n1 mt-1">Expenses This Week</p>
                              <small class="text-muted">₦39 less than last week</small>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!--/ Expense Overview -->

              <!-- Transactions -->
              <div class="col-md-6 col-lg-4 order-2 mb-4">
                <div class="card h-100">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title m-0 me-2">Transactions</h5>
                    <div class="dropdown">
                      <button class="btn p-0" type="button" id="transactionID" data-bs-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-vertical-rounded"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end" aria-labelledby="transactionID">
                        <a class="dropdown-item" href="javascript:void(0);">Last 28 Days</a>
                        <a class="dropdown-item" href="javascript:void(0);">Last Month</a>
                        <a class="dropdown-item" href="javascript:void(0);">Last Year</a>
                      </div>
                    </div>
                  </div>
                  <div class="card-body">
                    <ul class="p-0 m-0">
                      <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                          <img src="assets/img/icons/unicons/paypal.png" alt="User" class="rounded" />
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <small class="text-muted d-block mb-1">Paypal</small>
                            <h6 class="mb-0">Send money</h6>
                          </div>
                          <div class="user-progress d-flex align-items-center gap-1">
                            <h6 class="mb-0">₦82.6</h6>
                            <span class="text-muted">NGN</span>
                          </div>
                        </div>
                      </li>
                      <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                          <img src="assets/img/icons/unicons/wallet.png" alt="User" class="rounded" />
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <small class="text-muted d-block mb-1">Wallet</small>
                            <h6 class="mb-0">Mac'D</h6>
                          </div>
                          <div class="user-progress d-flex align-items-center gap-1">
                            <h6 class="mb-0">₦270.69</h6>
                            <span class="text-muted">NGN</span>
                          </div>
                        </div>
                      </li>
                      <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                          <img src="assets/img/icons/unicons/chart.png" alt="User" class="rounded" />
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <small class="text-muted d-block mb-1">Transfer</small>
                            <h6 class="mb-0">Refund</h6>
                          </div>
                          <div class="user-progress d-flex align-items-center gap-1">
                            <h6 class="mb-0">₦637.91</h6>
                            <span class="text-muted">NGN</span>
                          </div>
                        </div>
                      </li>
                      <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                          <img src="assets/img/icons/unicons/cc-success.png" alt="User" class="rounded" />
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <small class="text-muted d-block mb-1">Credit Card</small>
                            <h6 class="mb-0">Ordered Food</h6>
                          </div>
                          <div class="user-progress d-flex align-items-center gap-1">
                            <h6 class="mb-0">₦-838.71</h6>
                            <span class="text-muted">NGN</span>
                          </div>
                        </div>
                      </li>
                      <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                          <img src="assets/img/icons/unicons/wallet.png" alt="User" class="rounded" />
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <small class="text-muted d-block mb-1">Wallet</small>
                            <h6 class="mb-0">Starbucks</h6>
                          </div>
                          <div class="user-progress d-flex align-items-center gap-1">
                            <h6 class="mb-0">₦203.33</h6>
                            <span class="text-muted">NGN</span>
                          </div>
                        </div>
                      </li>
                      <li class="d-flex">
                        <div class="avatar flex-shrink-0 me-3">
                          <img src="assets/img/icons/unicons/cc-warning.png" alt="User" class="rounded" />
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <small class="text-muted d-block mb-1">Mastercard</small>
                            <h6 class="mb-0">Ordered Food</h6>
                          </div>
                          <div class="user-progress d-flex align-items-center gap-1">
                            <h6 class="mb-0">₦-92.45</h6>
                            <span class="text-muted">NGN</span>
                          </div>
                        </div>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
              <!--/ Transactions -->
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

  <!-- Place this tag in your head or just before your close body tag. -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
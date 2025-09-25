<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = $_SESSION['nivas_adminRole'];
$admin_school = $admin_['school'];
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Support Tickets | Nivasity Command Center</title>
  <meta name="description" content="" />
  <?php include('partials/_head.php') ?>
  <style>
    .ticket-message { white-space: pre-line; }
  </style>
  </head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php include('partials/_sidebar.php') ?>
      <div class="layout-page">
        <?php include('partials/_navbar.php') ?>
        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Support /</span> Tickets</h4>

            <div class="card">
              <div class="card-body">
                <ul class="nav nav-pills mb-3" id="ticketTabs" role="tablist">
                  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="open-tab" data-status="open" type="button">Open</button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="closed-tab" data-status="closed" type="button">Closed</button>
                  </li>
                </ul>

                <div class="table-responsive text-nowrap">
                  <table class="table">
                    <thead class="table-secondary">
                      <tr>
                        <th>Ticket</th>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Date &amp; Time</th>
                        <th>Status</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody class="table-border-bottom-0"></tbody>
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

  <!-- Ticket Modal -->
  <div class="modal fade" id="ticketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Ticket Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <small class="text-muted">Ticket Code</small>
              <div class="fw-bold" id="t_code">#-</div>
            </div>
            <div class="col-md-6">
              <small class="text-muted">Status</small>
              <div class="fw-bold" id="t_status"><span class="badge bg-label-secondary">-</span></div>
            </div>
            <div class="col-md-12">
              <small class="text-muted">Student</small>
              <div class="fw-bold text-uppercase" id="t_student">-</div>
              <div class="small" id="t_email">-</div>
            </div>
            <div class="col-md-12">
              <small class="text-muted">Subject</small>
              <div class="fw-bold" id="t_subject">-</div>
            </div>
            <div class="col-md-12">
              <small class="text-muted">Message</small>
              <div class="ticket-message" id="t_message">-</div>
            </div>

            <div class="col-md-12" id="responseBlock" style="display: none;">
              <small class="text-muted">Response</small>
              <div class="ticket-message border rounded p-2" id="t_response">-</div>
            </div>

            <form id="respondForm" class="col-md-12" style="display: none;">
              <input type="hidden" name="code" id="r_code" />
              <div class="mb-3">
                <label for="r_message" class="form-label">Write a response</label>
                <textarea class="form-control" id="r_message" name="response" rows="4" placeholder="Type your response..."></textarea>
              </div>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="r_close" name="mark_closed" checked>
                <label class="form-check-label" for="r_close">Mark ticket as closed</label>
              </div>
              <button type="submit" class="btn btn-primary">Send Response</button>
            </form>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-warning" id="reopenBtn" style="display: none;">Reopen Ticket</button>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/vendor/libs/jquery/jquery.js"></script>
  <script src="assets/vendor/js/bootstrap.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="assets/vendor/libs/popper/popper.js"></script>
  <script src="assets/vendor/js/menu.js"></script>
  <script src="assets/js/ui-toasts.js"></script>
  <script src="assets/js/main.js"></script>
  <script>
    const adminRole = <?php echo (int)$admin_role; ?>;
    const adminSchool = <?php echo (int)$admin_school; ?>;
  </script>
  <script src="model/functions/support.js"></script>
</body>
</html>


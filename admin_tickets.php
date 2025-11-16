<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = $_SESSION['nivas_adminRole'];
$admin_id = $_SESSION['nivas_adminId'];

// Fetch admins and roles for assignment dropdowns
$roles = [];
$admins = [];

$roleRes = mysqli_query($conn, "SELECT id, name FROM admin_roles ORDER BY name ASC");
while ($row = mysqli_fetch_assoc($roleRes)) {
  $roles[] = $row;
}

$adminRes = mysqli_query($conn, "SELECT id, first_name, last_name, email FROM admins ORDER BY first_name, last_name");
while ($row = mysqli_fetch_assoc($adminRes)) {
  $admins[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Internal Tickets | Nivasity Command Center</title>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
              <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Support /</span> Internal Tickets</h4>
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAdminTicketModal">
                New Internal Ticket
              </button>
            </div>

            <div class="card">
              <div class="card-body">
                <div class="row mb-3 g-2 align-items-center">
                  <div class="col-auto">
                    <label class="form-label mb-0 me-2">Status:</label>
                  </div>
                  <div class="col-auto">
                    <select id="adminTicketStatusFilter" class="form-select form-select-sm">
                      <option value="open">Open</option>
                      <option value="pending">Pending</option>
                      <option value="resolved">Resolved</option>
                      <option value="closed">Closed</option>
                      <option value="">All</option>
                    </select>
                  </div>
                  <div class="col-auto">
                    <label class="form-label mb-0 me-2">Assignment:</label>
                  </div>
                  <div class="col-auto">
                    <select id="adminTicketAssignmentFilter" class="form-select form-select-sm">
                      <option value="mine">My tickets</option>
                      <option value="all">All tickets</option>
                    </select>
                  </div>
                </div>

                <div class="table-responsive text-nowrap">
                  <table class="table" id="adminTicketsTable">
                    <thead class="table-secondary">
                      <tr>
                        <th>Code</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Assigned To</th>
                        <th>Date &amp; Time</th>
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

  <!-- New Internal Ticket Modal -->
  <div class="modal fade" id="newAdminTicketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <form id="adminNewTicketForm" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title">New Internal Ticket</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label" for="adt_subject">Subject</label>
                <input type="text" class="form-control" id="adt_subject" name="subject" placeholder="Ticket subject" required>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="adt_priority">Priority</label>
                <select class="form-select" id="adt_priority" name="priority">
                  <option value="low">Low</option>
                  <option value="medium" selected>Medium</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="adt_category">Category (optional)</label>
                <select class="form-select" id="adt_category" name="category">
                  <option value="">-- Select Category --</option>
                  <option value="Payments or Transactions">Payments or Transactions</option>
                  <option value="Account or Access Issues">Account or Access Issues</option>
                  <option value="Materials or Events">Materials or Events</option>
                  <option value="Department Requests">Department Requests</option>
                  <option value="Technical and Other Issues">Technical and Other Issues</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="adt_related_ticket_code">Related User Ticket (code, optional)</label>
                <input type="text" class="form-control" id="adt_related_ticket_code" name="related_ticket_code" placeholder="e.g. TCK-2025-0001">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="adt_assigned_admin_id">Assign to Admin (optional)</label>
                <select class="form-select" id="adt_assigned_admin_id" name="assigned_admin_id">
                  <option value="">-- None --</option>
                  <?php foreach ($admins as $a): ?>
                    <option value="<?php echo (int)$a['id']; ?>">
                      <?php echo htmlspecialchars(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))); ?>
                      (<?php echo htmlspecialchars($a['email']); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="adt_assigned_role_id">Assign to Role (optional)</label>
                <select class="form-select" id="adt_assigned_role_id" name="assigned_role_id">
                  <option value="">-- None --</option>
                  <?php foreach ($roles as $r): ?>
                    <option value="<?php echo (int)$r['id']; ?>">
                      <?php echo htmlspecialchars($r['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-12">
                <label class="form-label" for="adt_message">Message</label>
                <textarea class="form-control" id="adt_message" name="message" rows="4" placeholder="Describe the issue..." required></textarea>
              </div>
              <div class="col-md-12">
                <label class="form-label" for="adt_attachments">Attachments (optional)</label>
                <input type="file" class="form-control" id="adt_attachments" name="attachments[]" multiple>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Ticket</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Admin Ticket Modal -->
  <div class="modal fade" id="adminTicketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Internal Ticket Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <small class="text-muted">Ticket Code</small>
              <div class="fw-bold" id="adt_view_code">#-</div>
            </div>
            <div class="col-md-6">
              <small class="text-muted">Status</small>
              <div class="fw-bold" id="adt_view_status"><span class="badge bg-label-secondary">-</span></div>
            </div>
            <div class="col-md-6">
              <small class="text-muted">Created By</small>
              <div class="fw-bold" id="adt_view_created_by">-</div>
            </div>
            <div class="col-md-6">
              <small class="text-muted">Assigned To</small>
              <div class="fw-bold" id="adt_view_assigned_to">-</div>
            </div>
            <div class="col-md-12">
              <small class="text-muted">Subject</small>
              <div class="fw-bold" id="adt_view_subject">-</div>
            </div>
            <div class="col-md-12">
              <small class="text-muted">Conversation</small>
              <div id="adt_view_conversation" class="border rounded p-2" style="max-height: 320px; overflow-y: auto;"></div>
            </div>

            <form id="adminRespondForm" class="col-md-12" enctype="multipart/form-data">
              <input type="hidden" name="code" id="adt_view_code_input" />
              <div class="mb-3">
                <label for="adt_view_response" class="form-label">Add a message</label>
                <textarea class="form-control" id="adt_view_response" name="response" rows="4" placeholder="Type your message..."></textarea>
              </div>
              <div class="mb-3">
                <label for="adt_view_attachments" class="form-label">Attachments (optional)</label>
                <input type="file" class="form-control" id="adt_view_attachments" name="attachments[]" multiple>
              </div>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="adt_view_close" name="mark_closed">
                <label class="form-check-label" for="adt_view_close">Mark ticket as closed</label>
              </div>
              <button type="submit" class="btn btn-primary">Send</button>
            </form>
          </div>
        </div>
        <!-- <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div> -->
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
    const ccAdminId = <?php echo (int)$admin_id; ?>;
  </script>
  <script src="model/functions/admin_support.js"></script>
</body>
</html>

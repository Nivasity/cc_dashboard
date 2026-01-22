<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = $_SESSION['nivas_adminRole'];

// Restrict access to admin roles 1, 2, and 3 only
if (!in_array($admin_role, [1, 2, 3])) {
  header('Location: index.php');
  exit();
}

// Fetch notifications from the database
$notifications_sql = "SELECT n.id, n.user_id, n.title, n.body, n.type, n.data, n.created_at, n.read_at, 
                      u.first_name, u.last_name, u.email, u.matric_no 
                      FROM notifications n 
                      LEFT JOIN users u ON n.user_id = u.id 
                      ORDER BY n.created_at DESC";
$notifications_query = mysqli_query($conn, $notifications_sql);
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Notifications | Nivasity Command Center</title>
  <meta name="description" content="" />
  <?php include('partials/_head.php') ?>
</head>
<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php include('partials/_sidebar.php') ?>
      <div class="layout-page">
        <?php include('partials/_navbar.php') ?>
        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Resources Management /</span> Notifications</h4>
            
            <!-- Send Custom Notification Card -->
            <div class="card mb-4">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Send Custom Notification</h5>
              </div>
              <div class="card-body">
                <form id="sendNotificationForm" class="row g-3">
                  <div class="col-md-12">
                    <label for="notificationTitle" class="form-label">Title</label>
                    <input type="text" class="form-control" id="notificationTitle" name="title" placeholder="Enter notification title" required>
                  </div>
                  <div class="col-md-12">
                    <label for="notificationBody" class="form-label">Message</label>
                    <textarea class="form-control" id="notificationBody" name="body" rows="3" placeholder="Enter notification message" required></textarea>
                  </div>
                  <div class="col-md-6">
                    <label for="notificationType" class="form-label">Type</label>
                    <select class="form-select" id="notificationType" name="type">
                      <option value="general">General</option>
                      <option value="announcement">Announcement</option>
                      <option value="urgent">Urgent</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="targetType" class="form-label">Target</label>
                    <select class="form-select" id="targetType" name="target_type">
                      <option value="broadcast">All Students (Broadcast)</option>
                      <option value="school">Students by School</option>
                      <option value="user">Specific User (by Email)</option>
                    </select>
                  </div>
                  <div class="col-md-6" id="schoolSelectDiv" style="display: none;">
                    <label for="schoolSelect" class="form-label">Select School</label>
                    <select class="form-select" id="schoolSelect" name="school_id">
                      <option value="">Select a school...</option>
                      <?php
                      $schools_query = mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active' ORDER BY name");
                      while ($school = mysqli_fetch_array($schools_query)) {
                        echo '<option value="' . $school['id'] . '">' . htmlspecialchars($school['name']) . '</option>';
                      }
                      ?>
                    </select>
                  </div>
                  <div class="col-md-6" id="userEmailDiv" style="display: none;">
                    <label for="userEmail" class="form-label">User Email</label>
                    <input type="email" class="form-control" id="userEmail" name="user_email" placeholder="student@example.com">
                  </div>
                  <div class="col-12">
                    <button type="submit" class="btn btn-primary">Send Notification</button>
                  </div>
                </form>
              </div>
            </div>

            <!-- Notifications Log Card -->
            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Notification Log</h5>
              </div>
              <div class="card-body">
                <div class="table-responsive text-nowrap">
                  <table class="table" id="notificationsTable">
                    <thead class="table-secondary">
                      <tr>
                        <th>ID</th>
                        <th>Recipient</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created At</th>
                      </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                      <?php while ($notif = mysqli_fetch_array($notifications_query)) { ?>
                      <tr>
                        <td><?php echo $notif['id']; ?></td>
                        <td>
                          <?php 
                          if ($notif['first_name']) {
                            echo htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']);
                            if ($notif['matric_no']) {
                              echo '<br><small class="text-muted">' . htmlspecialchars($notif['matric_no']) . '</small>';
                            }
                          } else {
                            echo '<span class="text-muted">Unknown User</span>';
                          }
                          ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($notif['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars(substr($notif['body'], 0, 100)) . (strlen($notif['body']) > 100 ? '...' : ''); ?></td>
                        <td><span class="badge bg-label-<?php 
                          echo $notif['type'] === 'urgent' ? 'danger' : 
                               ($notif['type'] === 'announcement' ? 'warning' : 'info'); 
                        ?>"><?php echo ucfirst($notif['type']); ?></span></td>
                        <td>
                          <?php if ($notif['read_at']) { ?>
                            <span class="badge bg-label-success">Read</span>
                          <?php } else { ?>
                            <span class="badge bg-label-secondary">Unread</span>
                          <?php } ?>
                        </td>
                        <td><?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></td>
                      </tr>
                      <?php } ?>
                    </tbody>
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
  <!-- Core JS -->
  <script src="assets/vendor/libs/jquery/jquery.js"></script>
  <script src="assets/vendor/js/bootstrap.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="assets/vendor/libs/popper/popper.js"></script>
  <script src="assets/vendor/js/menu.js"></script>
  <!-- Main JS -->
  <script src="assets/js/ui-toasts.js"></script>
  <script src="assets/js/main.js"></script>
  <script src="model/functions/notifications.js"></script>
</body>
</html>

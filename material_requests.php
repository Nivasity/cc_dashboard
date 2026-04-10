<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = (int) ($_SESSION['nivas_adminRole'] ?? 0);
$admin_school = (int) ($admin_['school'] ?? 0);

if (!$resource_mgt_menu) {
  header('Location: /');
  exit();
}

function ccMaterialRequestTableExists($conn, $tableName) {
  static $cache = [];
  $tableName = strtolower(trim((string) $tableName));

  if ($tableName === '') {
    return false;
  }

  if (array_key_exists($tableName, $cache)) {
    return $cache[$tableName];
  }

  $tableNameSafe = mysqli_real_escape_string($conn, $tableName);
  $rs = mysqli_query($conn, "SHOW TABLES LIKE '$tableNameSafe'");
  $cache[$tableName] = $rs && mysqli_num_rows($rs) > 0;
  return $cache[$tableName];
}

function ccMaterialRequestDeptNames($conn, $deptIdsJson) {
  $deptIds = json_decode((string) $deptIdsJson, true);
  if (!is_array($deptIds) || empty($deptIds)) {
    return [];
  }

  $deptIds = array_values(array_unique(array_filter(array_map('intval', $deptIds))));
  if (empty($deptIds)) {
    return [];
  }

  $deptList = implode(',', $deptIds);
  $names = [];
  $rs = mysqli_query($conn, "SELECT id, name FROM depts WHERE id IN ($deptList)");
  if ($rs) {
    while ($row = mysqli_fetch_assoc($rs)) {
      $names[(int) $row['id']] = (string) ($row['name'] ?? '');
    }
  }

  $resolved = [];
  foreach ($deptIds as $deptId) {
    if (isset($names[$deptId]) && $names[$deptId] !== '') {
      $resolved[] = $names[$deptId];
    }
  }

  return $resolved;
}

function ccMaterialRequestFacultyNames($conn, $facultyIdsJson) {
  $facultyIds = json_decode((string) $facultyIdsJson, true);
  if (!is_array($facultyIds) || empty($facultyIds)) {
    return [];
  }

  $facultyIds = array_values(array_unique(array_filter(array_map('intval', $facultyIds))));
  if (empty($facultyIds)) {
    return [];
  }

  $facultyList = implode(',', $facultyIds);
  $names = [];
  $rs = mysqli_query($conn, "SELECT id, name FROM faculties WHERE id IN ($facultyList)");
  if ($rs) {
    while ($row = mysqli_fetch_assoc($rs)) {
      $names[(int) $row['id']] = (string) ($row['name'] ?? '');
    }
  }

  $resolved = [];
  foreach ($facultyIds as $facultyId) {
    if (isset($names[$facultyId]) && $names[$facultyId] !== '') {
      $resolved[] = $names[$facultyId];
    }
  }

  return $resolved;
}

function ccMaterialRequestAudienceLabel($conn, array $row) {
  $scope = (string) ($row['scope'] ?? 'school');
  if ($scope === 'school') {
    return 'All students in school';
  }
  if ($scope === 'faculty') {
    $facultyName = trim((string) ($row['target_faculty_name'] ?? ''));
    return $facultyName !== '' ? 'All students in ' . $facultyName : 'Selected faculty';
  }
  if ($scope === 'selected_faculties') {
    $facultyNames = ccMaterialRequestFacultyNames($conn, $row['target_faculty_ids_json'] ?? '[]');
    return empty($facultyNames) ? 'Selected faculties' : implode(', ', $facultyNames);
  }
  if ($scope === 'my_department') {
    $deptName = trim((string) ($row['target_department_name'] ?? ''));
    return $deptName !== '' ? 'Only ' . $deptName : 'Only one department';
  }

  $deptNames = ccMaterialRequestDeptNames($conn, $row['target_dept_ids_json'] ?? '[]');
  return empty($deptNames) ? 'Selected departments' : implode(', ', $deptNames);
}

function ccMaterialRequestSetFlash($type, $message) {
  $_SESSION['material_requests_flash'] = [
    'type' => $type,
    'message' => $message,
  ];
}

function ccMaterialRequestGetFlash() {
  $flash = $_SESSION['material_requests_flash'] ?? null;
  unset($_SESSION['material_requests_flash']);
  return $flash;
}

$tablesReady = ccMaterialRequestTableExists($conn, 'material_requests') && ccMaterialRequestTableExists($conn, 'material_request_votes');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$tablesReady) {
    ccMaterialRequestSetFlash('danger', 'Material requests tables are not available in this database yet.');
    header('Location: material_requests.php');
    exit();
  }

  $action = trim((string) ($_POST['action'] ?? ''));
  if ($action === 'resolve') {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $resolutionNote = trim((string) ($_POST['resolution_note'] ?? ''));
    $resolvedManualId = (int) ($_POST['resolved_manual_id'] ?? 0);

    if ($requestId <= 0) {
      ccMaterialRequestSetFlash('danger', 'Select a valid material request to resolve.');
    } else {
      $resolutionNoteSafe = mysqli_real_escape_string($conn, $resolutionNote);
      $manualValue = $resolvedManualId > 0 ? $resolvedManualId : 'NULL';
      $scopeSchoolFilter = ($admin_role == 5 && $admin_school > 0) ? " AND school_id = $admin_school" : '';
      $updateSql = "UPDATE material_requests
                    SET status = 'resolved',
                        resolution_note = " . ($resolutionNote !== '' ? "'$resolutionNoteSafe'" : "NULL") . ",
                        resolved_manual_id = $manualValue,
                        resolved_by_admin_id = " . (int) $admin_id . ",
                        resolved_at = NOW(),
                        updated_at = NOW()
                    WHERE id = $requestId$scopeSchoolFilter
                    LIMIT 1";

      if (mysqli_query($conn, $updateSql) && mysqli_affected_rows($conn) >= 1) {
        ccMaterialRequestSetFlash('success', 'Material request marked as resolved.');
      } else {
        ccMaterialRequestSetFlash('danger', 'Unable to mark that request as resolved right now.');
      }
    }
  }

  header('Location: material_requests.php');
  exit();
}

$flash = ccMaterialRequestGetFlash();
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$schoolFilter = isset($_GET['school']) ? (int) $_GET['school'] : 0;

if ($admin_role == 5 && $admin_school > 0) {
  $schoolFilter = $admin_school;
}

$schoolsQuery = $admin_role == 5
  ? mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active' AND id = $admin_school ORDER BY name")
  : mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active' ORDER BY name");

$whereParts = ['1 = 1'];
if ($schoolFilter > 0) {
  $whereParts[] = 'mr.school_id = ' . $schoolFilter;
}
if (in_array($statusFilter, ['open', 'under_review', 'resolved'], true)) {
  $whereParts[] = "mr.status = '" . mysqli_real_escape_string($conn, $statusFilter) . "'";
}

$whereSql = implode(' AND ', $whereParts);
$summaryWhereSql = $whereSql;

$summaryCounts = [
  'open' => 0,
  'under_review' => 0,
  'resolved' => 0,
];

if ($tablesReady) {
  $summaryRs = mysqli_query($conn, "SELECT mr.status, COUNT(mr.id) AS total FROM material_requests mr WHERE $summaryWhereSql GROUP BY mr.status");
  if ($summaryRs) {
    while ($row = mysqli_fetch_assoc($summaryRs)) {
      $statusKey = (string) ($row['status'] ?? '');
      if (isset($summaryCounts[$statusKey])) {
        $summaryCounts[$statusKey] = (int) ($row['total'] ?? 0);
      }
    }
  }
}

$requests = [];
if ($tablesReady) {
  $requestsSql = "SELECT
                    mr.*, 
                    s.name AS school_name,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS requester_name,
                    f.name AS target_faculty_name,
                    d.name AS target_department_name,
                    (
                      SELECT COUNT(v.id)
                      FROM material_request_votes v
                      WHERE v.request_id = mr.id
                    ) AS upvote_count
                  FROM material_requests mr
                  LEFT JOIN schools s ON s.id = mr.school_id
                  LEFT JOIN users u ON u.id = mr.requester_user_id
                  LEFT JOIN faculties f ON f.id = mr.target_faculty_id
                  LEFT JOIN depts d ON d.id = mr.target_department_id
                  WHERE $whereSql
                  ORDER BY FIELD(mr.status, 'under_review', 'open', 'resolved') ASC, mr.updated_at DESC, mr.id DESC";
  $requestsRs = mysqli_query($conn, $requestsSql);
  if ($requestsRs) {
    while ($row = mysqli_fetch_assoc($requestsRs)) {
      $expectedBuyers = max(1, (int) ($row['expected_buyers_count'] ?? 0));
      $upvoteCount = (int) ($row['upvote_count'] ?? 0);
      $progressPercent = round(($upvoteCount / $expectedBuyers) * 100, 1);
      $row['upvote_count'] = $upvoteCount;
      $row['expected_buyers_count'] = $expectedBuyers;
      $row['progress_percent'] = $progressPercent;
      $row['threshold_met'] = $progressPercent >= (float) ($row['threshold_percent'] ?? 40);
      $row['audience_label'] = ccMaterialRequestAudienceLabel($conn, $row);
      $requests[] = $row;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Material Requests | Nivasity Command Center</title>
    <meta name="description" content="Review student material requests and mark them resolved when materials are posted." />
    <?php include('partials/_head.php') ?>
    <style>
      .request-summary-card {
        border: 1px solid rgba(31, 31, 31, 0.06);
        border-radius: 1rem;
      }

      .request-summary-card .label {
        color: #7f746b;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
      }

      .request-summary-card .value {
        color: #1f1f1f;
        font-size: 1.75rem;
        font-weight: 800;
      }

      .request-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.35rem 0.7rem;
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }

      .request-status-badge.open {
        background: rgba(255, 145, 0, 0.12);
        color: #d67400;
      }

      .request-status-badge.under_review {
        background: rgba(27, 120, 242, 0.12);
        color: #1b78f2;
      }

      .request-status-badge.resolved {
        background: rgba(18, 166, 102, 0.12);
        color: #14895b;
      }

      .request-progress .progress {
        height: 0.7rem;
        border-radius: 999px;
        background: #eef1f6;
      }

      .request-progress .progress-bar {
        background: linear-gradient(135deg, #ff9a1f 0%, #ff8400 100%);
      }
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
              <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                <div>
                  <h4 class="fw-bold py-3 mb-1"><span class="text-muted fw-light">Resources Management /</span> Material Requests</h4>
                  <p class="text-muted mb-0">Review student demand, watch 40% threshold progress, and mark requests resolved after the material is posted.</p>
                </div>
              </div>

              <?php if ($flash) { ?>
              <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible" role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
              <?php } ?>

              <?php if (!$tablesReady) { ?>
              <div class="alert alert-warning" role="alert">
                Material request tables do not exist in this environment yet. Run the latest material request SQL migration first.
              </div>
              <?php } else { ?>
              <div class="row mb-4">
                <div class="col-md-4 mb-4">
                  <div class="card request-summary-card h-100">
                    <div class="card-body">
                      <span class="label">Open</span>
                      <div class="value"><?php echo (int) $summaryCounts['open']; ?></div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4 mb-4">
                  <div class="card request-summary-card h-100">
                    <div class="card-body">
                      <span class="label">Under Review</span>
                      <div class="value"><?php echo (int) $summaryCounts['under_review']; ?></div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4 mb-4">
                  <div class="card request-summary-card h-100">
                    <div class="card-body">
                      <span class="label">Resolved</span>
                      <div class="value"><?php echo (int) $summaryCounts['resolved']; ?></div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card mb-4">
                <div class="card-body">
                  <form method="get" class="row g-3">
                    <div class="col-md-4">
                      <select name="school" class="form-select" <?php if ($admin_role == 5) echo 'disabled'; ?>>
                        <?php if ($admin_role != 5) { ?>
                          <option value="0">All Schools</option>
                        <?php } ?>
                        <?php if ($schoolsQuery) { while ($school = mysqli_fetch_assoc($schoolsQuery)) { ?>
                          <option value="<?php echo (int) $school['id']; ?>" <?php echo $schoolFilter == (int) $school['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($school['name']); ?></option>
                        <?php } } ?>
                      </select>
                      <?php if ($admin_role == 5) { ?>
                        <input type="hidden" name="school" value="<?php echo (int) $schoolFilter; ?>" />
                      <?php } ?>
                    </div>
                    <div class="col-md-4">
                      <select name="status" class="form-select">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="under_review" <?php echo $statusFilter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                        <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                      </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                      <button type="submit" class="btn btn-secondary">Filter</button>
                      <a href="material_requests.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                  </form>
                </div>
              </div>

              <div class="card">
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table align-middle">
                      <thead class="table-secondary">
                        <tr>
                          <th>Material</th>
                          <th>School</th>
                          <th>Audience</th>
                          <th>Requested By</th>
                          <th>Demand</th>
                          <th>Status</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($requests)) { ?>
                          <tr>
                            <td colspan="7" class="text-center text-muted py-4">No material requests match the current filters.</td>
                          </tr>
                        <?php } ?>
                        <?php foreach ($requests as $request) { ?>
                          <tr>
                            <td>
                              <div class="fw-semibold"><?php echo htmlspecialchars((string) $request['material_title']); ?></div>
                              <small class="text-muted"><?php echo htmlspecialchars((string) $request['material_code']); ?></small>
                              <?php if (trim((string) ($request['resolution_note'] ?? '')) !== '') { ?>
                                <div class="small text-muted mt-1"><?php echo htmlspecialchars((string) $request['resolution_note']); ?></div>
                              <?php } ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($request['school_name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($request['audience_label'] ?? '')); ?></td>
                            <td>
                              <div><?php echo htmlspecialchars(trim((string) ($request['requester_name'] ?? '')) ?: 'Student'); ?></div>
                              <small class="text-muted"><?php echo htmlspecialchars(date('d M Y', strtotime((string) $request['created_at']))); ?></small>
                            </td>
                            <td>
                              <div class="fw-semibold mb-1"><?php echo (int) $request['upvote_count']; ?> / <?php echo (int) $request['expected_buyers_count']; ?> upvotes</div>
                              <div class="request-progress">
                                <div class="progress mb-1">
                                  <div class="progress-bar" role="progressbar" style="width: <?php echo min(100, max(0, (float) $request['progress_percent'])); ?>%"></div>
                                </div>
                              </div>
                              <small class="text-muted"><?php echo htmlspecialchars(number_format((float) $request['progress_percent'], 1)); ?>% of expected buyers<?php echo !empty($request['threshold_met']) ? ' - threshold met' : ''; ?></small>
                            </td>
                            <td>
                              <span class="request-status-badge <?php echo htmlspecialchars((string) $request['status']); ?>">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $request['status']))); ?>
                              </span>
                            </td>
                            <td>
                              <?php if ((string) $request['status'] !== 'resolved') { ?>
                                <button
                                  type="button"
                                  class="btn btn-sm btn-primary js-open-resolve-modal"
                                  data-bs-toggle="modal"
                                  data-bs-target="#resolveRequestModal"
                                  data-request-id="<?php echo (int) $request['id']; ?>"
                                  data-material-title="<?php echo htmlspecialchars((string) $request['material_title'], ENT_QUOTES); ?>"
                                  data-material-code="<?php echo htmlspecialchars((string) $request['material_code'], ENT_QUOTES); ?>">
                                  Mark Resolved
                                </button>
                              <?php } else { ?>
                                <span class="text-success fw-semibold">Resolved</span>
                              <?php } ?>
                            </td>
                          </tr>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              <?php } ?>
            </div>

            <div class="modal fade" id="resolveRequestModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Mark Request Resolved</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <form method="post">
                    <div class="modal-body">
                      <input type="hidden" name="action" value="resolve" />
                      <input type="hidden" name="request_id" id="resolveRequestId" value="0" />
                      <div class="mb-3">
                        <label class="form-label">Material</label>
                        <div class="form-control bg-light" id="resolveRequestTitle">Selected request</div>
                      </div>
                      <div class="mb-3">
                        <label class="form-label" for="resolveManualId">Resolved Material ID</label>
                        <input type="number" class="form-control" name="resolved_manual_id" id="resolveManualId" min="0" placeholder="Optional" />
                      </div>
                      <div>
                        <label class="form-label" for="resolutionNote">Resolution note</label>
                        <textarea class="form-control" name="resolution_note" id="resolutionNote" rows="4" placeholder="Optional note for what was done"></textarea>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Mark Resolved</button>
                    </div>
                  </form>
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

    <script src="assets/vendor/libs/jquery/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/vendor/libs/popper/popper.min.js"></script>
    <script src="assets/vendor/js/menu.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
      $(document).on('click', '.js-open-resolve-modal', function () {
        var title = $(this).data('material-title') || 'Selected request';
        var code = $(this).data('material-code') || '';
        $('#resolveRequestId').val($(this).data('request-id') || 0);
        $('#resolveRequestTitle').text(code ? title + ' (' + code + ')' : title);
      });
    </script>
  </body>
</html>
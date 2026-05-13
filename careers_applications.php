<?php
session_start();
include('model/config.php');
include('model/page_config.php');
include('model/careers.php');

$admin_role = (int) ($_SESSION['nivas_adminRole'] ?? 0);

if (!$resource_mgt_menu || !in_array($admin_role, [1, 2, 3], true)) {
  header('Location: /');
  exit();
}

function ccCareerApplicationsSetFlash($type, $message)
{
  $_SESSION['career_applications_flash'] = [
    'type' => $type,
    'message' => $message,
  ];
}

function ccCareerApplicationsGetFlash()
{
  $flash = $_SESSION['career_applications_flash'] ?? null;
  unset($_SESSION['career_applications_flash']);
  return $flash;
}

function ccCareerApplicationsStatusBadge($status)
{
  $status = ccCareersNormalizeApplicationStatus($status);
  if ($status === 'submitted') {
    return 'secondary';
  }
  if ($status === 'shortlisted') {
    return 'info';
  }
  if ($status === 'interview') {
    return 'warning';
  }
  if ($status === 'offer') {
    return 'primary';
  }
  if ($status === 'hired') {
    return 'success';
  }

  return 'danger';
}

function ccCareerApplicationsBuildStats(array $applications)
{
  $stats = [
    'submitted' => 0,
    'shortlisted' => 0,
    'interview' => 0,
    'offer' => 0,
    'hired' => 0,
    'rejected' => 0,
  ];

  foreach ($applications as $application) {
    $status = ccCareersNormalizeApplicationStatus($application['status'] ?? 'submitted');
    if (isset($stats[$status])) {
      $stats[$status]++;
    }
  }

  return $stats;
}

function ccCareerApplicationsMatchesFilters(array $application, array $filters)
{
  if ($filters['status'] !== '' && ccCareersNormalizeApplicationStatus($application['status'] ?? 'submitted') !== $filters['status']) {
    return false;
  }

  if ($filters['opening_id'] > 0 && (int) ($application['opening_id'] ?? 0) !== $filters['opening_id']) {
    return false;
  }

  if ($filters['campus_affiliation'] !== '' && ccCareersNormalizeCampus($application['campus_affiliation'] ?? 'other') !== $filters['campus_affiliation']) {
    return false;
  }

  if ($filters['assigned_admin_id'] > 0 && (int) ($application['assigned_admin_id'] ?? 0) !== $filters['assigned_admin_id']) {
    return false;
  }

  if ($filters['search'] !== '') {
    $needle = mb_strtolower($filters['search']);
    $haystack = mb_strtolower(
      trim((string) ($application['full_name'] ?? '')) . ' ' .
      trim((string) ($application['email'] ?? '')) . ' ' .
      trim((string) ($application['code'] ?? '')) . ' ' .
      trim((string) ($application['opening_title'] ?? ''))
    );
    if (mb_strpos($haystack, $needle) === false) {
      return false;
    }
  }

  $createdAt = trim((string) ($application['created_at'] ?? ''));
  if ($filters['created_from'] !== '' && $createdAt !== '') {
    $fromBoundary = $filters['created_from'] . ' 00:00:00';
    if ($createdAt < $fromBoundary) {
      return false;
    }
  }

  if ($filters['created_to'] !== '' && $createdAt !== '') {
    $toBoundary = $filters['created_to'] . ' 23:59:59';
    if ($createdAt > $toBoundary) {
      return false;
    }
  }

  return true;
}

function ccCareerApplicationsRedirectUrl($applicationId = 0)
{
  $query = trim((string) ($_POST['return_query'] ?? ''));
  if ($query !== '') {
    return 'careers_applications.php?' . ltrim($query, '?');
  }

  if ($applicationId > 0) {
    return 'careers_applications.php?application=' . $applicationId;
  }

  return 'careers_applications.php';
}

$tablesReady = ccCareersTablesReady($conn);

$admins = [];
$adminsQuery = mysqli_query($conn, "SELECT id, first_name, last_name, email FROM admins WHERE status = 'active' ORDER BY first_name ASC, last_name ASC");
if ($adminsQuery) {
  while ($row = mysqli_fetch_assoc($adminsQuery)) {
    $admins[] = $row;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$tablesReady) {
    ccCareerApplicationsSetFlash('danger', 'Careers workflow tables are not available in this database yet. Apply the careers schema first.');
    header('Location: careers_applications.php');
    exit();
  }

  $action = trim((string) ($_POST['action'] ?? ''));

  try {
    if ($action === 'update_review') {
      $applicationId = (int) ($_POST['application_id'] ?? 0);
      $application = ccCareersFetchApplicationById($conn, $applicationId);
      if (!$application) {
        throw new Exception('The selected application could not be found.');
      }

      $nextStatus = ccCareersNormalizeApplicationStatus($_POST['status'] ?? ($application['status'] ?? 'submitted'));
      $assignedAdminId = (int) ($_POST['assigned_admin_id'] ?? 0);
      $assignedAdminValue = $assignedAdminId > 0 ? $assignedAdminId : null;
      $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));
      $historyNote = trim((string) ($_POST['history_note'] ?? ''));

      $currentStatus = ccCareersNormalizeApplicationStatus($application['status'] ?? 'submitted');
      $currentAssignedAdminId = (int) ($application['assigned_admin_id'] ?? 0);
      $currentNotes = trim((string) ($application['admin_notes'] ?? ''));

      $notesValue = $adminNotes !== '' ? "'" . mysqli_real_escape_string($conn, $adminNotes) . "'" : 'NULL';
      $assignedSqlValue = $assignedAdminValue !== null ? (int) $assignedAdminValue : 'NULL';
      $statusSafe = mysqli_real_escape_string($conn, $nextStatus);

      $updateSql = "UPDATE career_applications
                    SET status = '$statusSafe',
                        assigned_admin_id = $assignedSqlValue,
                        reviewed_by_admin_id = " . (int) $admin_id . ",
                        admin_notes = $notesValue,
                        updated_at = NOW()
                    WHERE id = $applicationId
                    LIMIT 1";
      if (!mysqli_query($conn, $updateSql)) {
        throw new Exception('Unable to update the career application right now.');
      }

      if ($currentStatus !== $nextStatus) {
        ccCareersInsertHistory(
          $conn,
          $applicationId,
          'status_changed',
          $currentStatus,
          $nextStatus,
          $historyNote !== '' ? $historyNote : 'Application status updated.',
          (int) $admin_id,
          $assignedAdminValue
        );
      }

      if ($currentAssignedAdminId !== ($assignedAdminValue ?? 0)) {
        ccCareersInsertHistory(
          $conn,
          $applicationId,
          'assignment_changed',
          $currentStatus,
          $nextStatus,
          'Application reviewer updated.',
          (int) $admin_id,
          $assignedAdminValue
        );
      }

      if ($historyNote !== '' && $currentStatus === $nextStatus) {
        ccCareersInsertHistory(
          $conn,
          $applicationId,
          'note',
          $currentStatus,
          $nextStatus,
          $historyNote,
          (int) $admin_id,
          $assignedAdminValue
        );
      }

      if ($currentNotes !== $adminNotes && $historyNote === '' && $currentStatus === $nextStatus && $currentAssignedAdminId === ($assignedAdminValue ?? 0)) {
        ccCareersInsertHistory(
          $conn,
          $applicationId,
          'note',
          $currentStatus,
          $nextStatus,
          'Internal notes updated.',
          (int) $admin_id,
          $assignedAdminValue
        );
      }

      ccCareerApplicationsSetFlash('success', 'Career application updated successfully.');
      header('Location: ' . ccCareerApplicationsRedirectUrl($applicationId));
      exit();
    }

    throw new Exception('Unsupported action supplied.');
  } catch (Throwable $e) {
    ccCareerApplicationsSetFlash('danger', $e->getMessage());
    header('Location: ' . ccCareerApplicationsRedirectUrl((int) ($_POST['application_id'] ?? 0)));
    exit();
  }
}

$flash = ccCareerApplicationsGetFlash();
$openings = ccCareersOpeningsTableExists($conn) ? ccCareersFetchOpenings($conn) : [];
$allApplications = ccCareersApplicationsTableExists($conn) ? ccCareersFetchApplications($conn) : [];
$stats = ccCareerApplicationsBuildStats($allApplications);

$filters = [
  'status' => trim((string) ($_GET['status'] ?? '')),
  'opening_id' => isset($_GET['opening']) ? (int) $_GET['opening'] : 0,
  'campus_affiliation' => trim((string) ($_GET['campus'] ?? '')),
  'assigned_admin_id' => isset($_GET['assigned_admin']) ? (int) $_GET['assigned_admin'] : 0,
  'created_from' => trim((string) ($_GET['created_from'] ?? '')),
  'created_to' => trim((string) ($_GET['created_to'] ?? '')),
  'search' => trim((string) ($_GET['search'] ?? '')),
];

if ($filters['status'] !== '') {
  $filters['status'] = ccCareersNormalizeApplicationStatus($filters['status']);
}
if ($filters['campus_affiliation'] !== '') {
  $filters['campus_affiliation'] = ccCareersNormalizeCampus($filters['campus_affiliation']);
}

$applications = array_values(array_filter($allApplications, static function ($application) use ($filters) {
  return ccCareerApplicationsMatchesFilters($application, $filters);
}));

$selectedApplicationId = isset($_GET['application']) ? (int) $_GET['application'] : 0;
$selectedApplication = $selectedApplicationId > 0 ? ccCareersFetchApplicationById($conn, $selectedApplicationId) : null;
$selectedResponses = $selectedApplication ? ccCareersDecodeResponsesJson($selectedApplication['responses_json'] ?? null) : [];
$selectedHistory = $selectedApplication ? ccCareersFetchApplicationHistory($conn, $selectedApplicationId) : [];
$returnQuery = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Careers Applications | Nivasity Command Center</title>
    <meta name="description" content="Review and filter applications submitted from the public Nivasity careers page." />
    <?php include('partials/_head.php') ?>
    <style>
      .career-queue-stat {
        border-radius: 1rem;
        border: 1px solid rgba(105, 108, 255, 0.08);
        box-shadow: 0 10px 24px rgba(67, 89, 113, 0.06);
      }

      .career-queue-stat .label {
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
        color: #8592a3;
        text-transform: uppercase;
        letter-spacing: 0.06em;
      }

      .career-queue-stat .value {
        display: block;
        margin-top: 0.4rem;
        font-size: 1.75rem;
        font-weight: 800;
        color: #566a7f;
      }

      .application-detail-card {
        position: sticky;
        top: 96px;
      }

      .detail-section + .detail-section {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(67, 89, 113, 0.12);
      }

      @media (max-width: 1199px) {
        .application-detail-card {
          position: static;
        }
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
              <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Resources /</span> Careers Applications</h4>

              <?php if ($flash) { ?>
              <div class="alert alert-<?php echo htmlspecialchars((string) ($flash['type'] ?? 'info')); ?> alert-dismissible" role="alert">
                <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
              <?php } ?>

              <?php if (!$tablesReady) { ?>
              <div class="alert alert-warning mb-4" role="alert">
                The careers workflow tables are not available in this database yet. Apply the careers schema before using this screen.
              </div>
              <?php } ?>

              <div class="row g-4 mb-4">
                <?php foreach ($stats as $statusKey => $statusValue) { ?>
                <div class="col-xl-2 col-md-4 col-sm-6">
                  <div class="card career-queue-stat h-100">
                    <div class="card-body">
                      <span class="label"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $statusKey))); ?></span>
                      <span class="value"><?php echo (int) $statusValue; ?></span>
                    </div>
                  </div>
                </div>
                <?php } ?>
              </div>

              <div class="card mb-4">
                <div class="card-body">
                  <form method="get" class="row g-3">
                    <div class="col-md-2">
                      <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php foreach (ccCareersApplicationStatuses() as $statusOption) { ?>
                          <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $filters['status'] === $statusOption ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $statusOption))); ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <select name="opening" class="form-select">
                        <option value="0">All Openings</option>
                        <?php foreach ($openings as $opening) { ?>
                          <option value="<?php echo (int) $opening['id']; ?>" <?php echo $filters['opening_id'] === (int) $opening['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($opening['title'] ?? 'Opening')); ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <select name="campus" class="form-select">
                        <option value="">All Campuses</option>
                        <?php foreach (ccCareersCampusOptions() as $campusOption) { ?>
                          <option value="<?php echo htmlspecialchars($campusOption); ?>" <?php echo $filters['campus_affiliation'] === $campusOption ? 'selected' : ''; ?>><?php echo htmlspecialchars(ccCareersCampusLabel($campusOption)); ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <select name="assigned_admin" class="form-select">
                        <option value="0">All Reviewers</option>
                        <?php foreach ($admins as $adminRow) { ?>
                          <option value="<?php echo (int) $adminRow['id']; ?>" <?php echo $filters['assigned_admin_id'] === (int) $adminRow['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(trim((string) (($adminRow['first_name'] ?? '') . ' ' . ($adminRow['last_name'] ?? '')))); ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <input type="date" class="form-control" name="created_from" value="<?php echo htmlspecialchars($filters['created_from']); ?>" />
                    </div>
                    <div class="col-md-2">
                      <input type="date" class="form-control" name="created_to" value="<?php echo htmlspecialchars($filters['created_to']); ?>" />
                    </div>
                    <div class="col-md-8">
                      <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" placeholder="Search by applicant, email, code, or opening" />
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                      <button type="submit" class="btn btn-secondary flex-fill">Filter</button>
                      <a href="careers_applications.php" class="btn btn-outline-secondary flex-fill">Reset</a>
                    </div>
                  </form>
                </div>
              </div>

              <div class="row g-4 align-items-start">
                <div class="col-xl-7">
                  <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-3">
                      <div>
                        <h5 class="mb-1">Application Queue</h5>
                        <p class="text-muted mb-0">Review incoming applications and move them through the hiring pipeline.</p>
                      </div>
                      <span class="badge bg-dark">Filtered: <?php echo count($applications); ?></span>
                    </div>
                    <div class="card-body">
                      <div class="table-responsive text-nowrap">
                        <table class="table align-middle">
                          <thead class="table-light">
                            <tr>
                              <th>Applicant</th>
                              <th>Opening</th>
                              <th>Campus</th>
                              <th>Status</th>
                              <th>Assigned</th>
                              <th>Created</th>
                              <th>Action</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php if (empty($applications)) { ?>
                            <tr>
                              <td colspan="7" class="text-center text-muted py-4">No applications match the current filters.</td>
                            </tr>
                            <?php } ?>
                            <?php foreach ($applications as $application) { ?>
                            <tr>
                              <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($application['full_name'] ?? 'Applicant')); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($application['email'] ?? '')); ?></div>
                                <div class="small text-muted">#<?php echo htmlspecialchars((string) ($application['code'] ?? '')); ?></div>
                              </td>
                              <td>
                                <div><?php echo htmlspecialchars((string) ($application['opening_title'] ?? 'Opening')); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars((string) ($application['level_text'] ?? '')); ?></small>
                              </td>
                              <td>
                                <div><?php echo htmlspecialchars(ccCareersCampusLabel($application['campus_affiliation'] ?? 'other')); ?></div>
                                <?php if (!empty($application['school_name'])) { ?>
                                  <small class="text-muted"><?php echo htmlspecialchars((string) $application['school_name']); ?></small>
                                <?php } ?>
                              </td>
                              <td>
                                <span class="badge bg-label-<?php echo ccCareerApplicationsStatusBadge($application['status'] ?? 'submitted'); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($application['status'] ?? 'submitted')))); ?></span>
                              </td>
                              <td><?php echo htmlspecialchars(trim((string) ($application['assigned_admin_name'] ?? '')) ?: 'Unassigned'); ?></td>
                              <td>
                                <div><?php echo !empty($application['created_at']) ? htmlspecialchars(date('d M Y', strtotime((string) $application['created_at']))) : '-'; ?></div>
                                <small class="text-muted"><?php echo !empty($application['created_at']) ? htmlspecialchars(date('h:i A', strtotime((string) $application['created_at']))) : ''; ?></small>
                              </td>
                              <td>
                                <a href="careers_applications.php?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['application' => (int) $application['id']]))); ?>" class="btn btn-sm btn-outline-primary">View</a>
                              </td>
                            </tr>
                            <?php } ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="col-xl-5">
                  <div class="card application-detail-card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-3">
                      <div>
                        <h5 class="mb-1">Application Detail</h5>
                        <p class="text-muted mb-0">Review responses, assign an owner, and update hiring status.</p>
                      </div>
                      <?php if ($selectedApplication) { ?>
                        <span class="badge bg-dark">#<?php echo htmlspecialchars((string) ($selectedApplication['code'] ?? '')); ?></span>
                      <?php } ?>
                    </div>
                    <div class="card-body">
                      <?php if (!$selectedApplication) { ?>
                        <div class="text-center text-muted py-5">
                          Select an application from the queue to review it here.
                        </div>
                      <?php } else { ?>
                        <div class="detail-section pt-0 mt-0 border-0">
                          <div class="row g-3">
                            <div class="col-md-6">
                              <small class="text-muted d-block">Applicant</small>
                              <div class="fw-semibold"><?php echo htmlspecialchars((string) ($selectedApplication['full_name'] ?? '')); ?></div>
                              <div class="small text-muted"><?php echo htmlspecialchars((string) ($selectedApplication['email'] ?? '')); ?></div>
                            </div>
                            <div class="col-md-6">
                              <small class="text-muted d-block">Opening</small>
                              <div class="fw-semibold"><?php echo htmlspecialchars((string) ($selectedApplication['opening_title'] ?? '')); ?></div>
                              <div class="small text-muted"><?php echo htmlspecialchars((string) ($selectedApplication['team'] ?? '')); ?></div>
                            </div>
                            <div class="col-md-6">
                              <small class="text-muted d-block">Campus</small>
                              <div><?php echo htmlspecialchars(ccCareersCampusLabel($selectedApplication['campus_affiliation'] ?? 'other')); ?></div>
                              <?php if (!empty($selectedApplication['school_name'])) { ?><div class="small text-muted"><?php echo htmlspecialchars((string) $selectedApplication['school_name']); ?></div><?php } ?>
                            </div>
                            <div class="col-md-6">
                              <small class="text-muted d-block">Availability</small>
                              <div><?php echo htmlspecialchars((string) ($selectedApplication['availability_text'] ?? '')); ?></div>
                            </div>
                            <div class="col-md-6">
                              <small class="text-muted d-block">Phone</small>
                              <div><?php echo htmlspecialchars((string) ($selectedApplication['phone'] ?? '')); ?></div>
                            </div>
                            <div class="col-md-6">
                              <small class="text-muted d-block">Portfolio</small>
                              <?php if (!empty($selectedApplication['portfolio_url'])) { ?>
                                <a href="<?php echo htmlspecialchars((string) $selectedApplication['portfolio_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars((string) $selectedApplication['portfolio_url']); ?></a>
                              <?php } else { ?>
                                <div class="text-muted">Not provided</div>
                              <?php } ?>
                            </div>
                          </div>
                        </div>

                        <div class="detail-section">
                          <h6 class="mb-3">Motivation</h6>
                          <div class="text-muted"><?php echo nl2br(htmlspecialchars((string) ($selectedApplication['motivation_text'] ?? ''))); ?></div>
                        </div>

                        <div class="detail-section">
                          <h6 class="mb-3">Role-Specific Responses</h6>
                          <?php if (empty($selectedResponses)) { ?>
                            <div class="text-muted">No role-specific responses were submitted.</div>
                          <?php } else { ?>
                            <div class="d-flex flex-column gap-3">
                              <?php foreach ($selectedResponses as $responseItem) { ?>
                                <div>
                                  <div class="fw-semibold mb-1"><?php echo htmlspecialchars((string) ($responseItem['question'] ?? 'Question')); ?></div>
                                  <div class="text-muted"><?php echo nl2br(htmlspecialchars((string) ($responseItem['answer'] ?? ''))); ?></div>
                                </div>
                              <?php } ?>
                            </div>
                          <?php } ?>
                        </div>

                        <div class="detail-section">
                          <h6 class="mb-3">Review Actions</h6>
                          <form method="post">
                            <input type="hidden" name="action" value="update_review" />
                            <input type="hidden" name="application_id" value="<?php echo (int) ($selectedApplication['id'] ?? 0); ?>" />
                            <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($returnQuery); ?>" />

                            <div class="row g-3">
                              <div class="col-md-6">
                                <label class="form-label" for="reviewStatus">Status</label>
                                <select class="form-select" id="reviewStatus" name="status">
                                  <?php foreach (ccCareersApplicationStatuses() as $statusOption) { ?>
                                    <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo (($selectedApplication['status'] ?? 'submitted') === $statusOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $statusOption))); ?></option>
                                  <?php } ?>
                                </select>
                              </div>
                              <div class="col-md-6">
                                <label class="form-label" for="assignedAdmin">Assigned Reviewer</label>
                                <select class="form-select" id="assignedAdmin" name="assigned_admin_id">
                                  <option value="0">Unassigned</option>
                                  <?php foreach ($admins as $adminRow) { ?>
                                    <option value="<?php echo (int) $adminRow['id']; ?>" <?php echo (int) ($selectedApplication['assigned_admin_id'] ?? 0) === (int) $adminRow['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(trim((string) (($adminRow['first_name'] ?? '') . ' ' . ($adminRow['last_name'] ?? '')))); ?></option>
                                  <?php } ?>
                                </select>
                              </div>
                              <div class="col-12">
                                <label class="form-label" for="adminNotes">Internal Notes</label>
                                <textarea class="form-control" id="adminNotes" name="admin_notes" rows="4" placeholder="Visible only inside Command Center"><?php echo htmlspecialchars((string) ($selectedApplication['admin_notes'] ?? '')); ?></textarea>
                              </div>
                              <div class="col-12">
                                <label class="form-label" for="historyNote">Timeline Note</label>
                                <textarea class="form-control" id="historyNote" name="history_note" rows="3" placeholder="Optional note for this update"></textarea>
                              </div>
                            </div>

                            <div class="mt-3 d-flex gap-2">
                              <button type="submit" class="btn btn-primary">Save Review Update</button>
                              <a href="careers_applications.php<?php echo $returnQuery !== '' ? '?' . htmlspecialchars($returnQuery) : ''; ?>" class="btn btn-outline-secondary">Refresh</a>
                            </div>
                          </form>
                        </div>

                        <div class="detail-section">
                          <h6 class="mb-3">History</h6>
                          <?php if (empty($selectedHistory)) { ?>
                            <div class="text-muted">No review history recorded yet.</div>
                          <?php } else { ?>
                            <div class="d-flex flex-column gap-3">
                              <?php foreach ($selectedHistory as $historyRow) { ?>
                                <div class="border rounded p-3">
                                  <div class="d-flex justify-content-between gap-3 flex-wrap mb-2">
                                    <div>
                                      <span class="badge bg-label-<?php echo $historyRow['event_type'] === 'status_changed' ? 'primary' : ($historyRow['event_type'] === 'assignment_changed' ? 'info' : 'secondary'); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($historyRow['event_type'] ?? 'note')))); ?></span>
                                      <?php if (!empty($historyRow['to_status'])) { ?>
                                        <span class="small text-muted ms-2"><?php echo htmlspecialchars((string) ($historyRow['from_status'] ?? '')); ?> &rarr; <?php echo htmlspecialchars((string) ($historyRow['to_status'] ?? '')); ?></span>
                                      <?php } ?>
                                    </div>
                                    <small class="text-muted"><?php echo !empty($historyRow['created_at']) ? htmlspecialchars(date('d M Y h:i A', strtotime((string) $historyRow['created_at']))) : ''; ?></small>
                                  </div>
                                  <div class="small text-muted mb-1">By: <?php echo htmlspecialchars(trim((string) ($historyRow['changed_by_name'] ?? '')) ?: 'System'); ?></div>
                                  <?php if (!empty($historyRow['assigned_admin_name'])) { ?><div class="small text-muted mb-1">Assigned: <?php echo htmlspecialchars((string) $historyRow['assigned_admin_name']); ?></div><?php } ?>
                                  <?php if (!empty($historyRow['note'])) { ?><div><?php echo nl2br(htmlspecialchars((string) $historyRow['note'])); ?></div><?php } ?>
                                </div>
                              <?php } ?>
                            </div>
                          <?php } ?>
                        </div>
                      <?php } ?>
                    </div>
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

    <script src="assets/vendor/libs/jquery/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/vendor/libs/popper/popper.min.js"></script>
    <script src="assets/vendor/js/menu.min.js"></script>
    <script src="assets/js/main.js"></script>
  </body>
</html>

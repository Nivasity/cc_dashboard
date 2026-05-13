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

function ccCareerOpeningsSetFlash($type, $message)
{
  $_SESSION['career_openings_flash'] = [
    'type' => $type,
    'message' => $message,
  ];
}

function ccCareerOpeningsGetFlash()
{
  $flash = $_SESSION['career_openings_flash'] ?? null;
  unset($_SESSION['career_openings_flash']);
  return $flash;
}

function ccCareerOpeningsStatusBadge($status)
{
  $status = ccCareersNormalizeOpeningStatus($status);
  if ($status === 'published') {
    return 'success';
  }
  if ($status === 'archived') {
    return 'dark';
  }

  return 'secondary';
}

function ccCareerOpeningsBuildStats(array $openings)
{
  $stats = [
    'total' => count($openings),
    'draft' => 0,
    'published' => 0,
    'archived' => 0,
    'live_now' => 0,
  ];

  foreach ($openings as $opening) {
    $status = ccCareersNormalizeOpeningStatus($opening['status'] ?? 'draft');
    if (isset($stats[$status])) {
      $stats[$status]++;
    }
    if (ccCareersOpeningIsPublic($opening)) {
      $stats['live_now']++;
    }
  }

  return $stats;
}

$openingsTableExists = ccCareersOpeningsTableExists($conn);
$applicationsTableExists = ccCareersApplicationsTableExists($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$openingsTableExists) {
    ccCareerOpeningsSetFlash('danger', 'The career_openings table is not available yet. Apply the careers schema first.');
    header('Location: careers_openings.php');
    exit();
  }

  $action = trim((string) ($_POST['action'] ?? ''));
  $redirectUrl = 'careers_openings.php';

  try {
    if ($action === 'save') {
      $openingId = (int) ($_POST['opening_id'] ?? 0);
      $title = ccCareersTrimmed($_POST['title'] ?? '', 160);
      $team = ccCareersTrimmed($_POST['team'] ?? '', 120);
      $slugInput = ccCareersTrimmed($_POST['slug'] ?? '', 160);
      $summary = ccCareersTrimmed($_POST['summary'] ?? '', 255);
      $description = trim((string) ($_POST['description'] ?? ''));
      $employmentType = ccCareersNormalizeEmploymentType($_POST['employment_type'] ?? 'internship');
      $internshipDuration = ccCareersTrimmed($_POST['internship_duration'] ?? '', 80);
      $eligibilityText = trim((string) ($_POST['eligibility_text'] ?? ''));
      $questions = ccCareersNormalizeQuestionsFromText($_POST['questions_text'] ?? '');
      $questionsJson = ccCareersEncodeQuestionsJson($questions);
      $status = ccCareersNormalizeOpeningStatus($_POST['status'] ?? 'draft');
      $isPublic = isset($_POST['is_public']) ? 1 : 0;
      $sortOrder = (int) ($_POST['sort_order'] ?? 0);
      $applicationOpenAtRaw = trim((string) ($_POST['application_open_at'] ?? ''));
      $applicationCloseAtRaw = trim((string) ($_POST['application_close_at'] ?? ''));
      $applicationOpenAt = ccCareersParseDateTimeOrNull($applicationOpenAtRaw);
      $applicationCloseAt = ccCareersParseDateTimeOrNull($applicationCloseAtRaw);

      if ($title === '') {
        throw new Exception('Opening title is required.');
      }
      if ($summary === '') {
        throw new Exception('Opening summary is required.');
      }
      if ($description === '') {
        throw new Exception('Opening description is required.');
      }
      if ($applicationOpenAtRaw !== '' && $applicationOpenAt === null) {
        throw new Exception('Provide a valid application opening date.');
      }
      if ($applicationCloseAtRaw !== '' && $applicationCloseAt === null) {
        throw new Exception('Provide a valid application closing date.');
      }
      if ($applicationOpenAt !== null && $applicationCloseAt !== null && strtotime($applicationCloseAt) < strtotime($applicationOpenAt)) {
        throw new Exception('Application closing date cannot be earlier than the opening date.');
      }

      $slug = ccCareersEnsureUniqueSlug($conn, $slugInput !== '' ? $slugInput : $title, $openingId);
      $slugSafe = mysqli_real_escape_string($conn, $slug);
      $titleSafe = mysqli_real_escape_string($conn, $title);
      $teamValue = $team !== '' ? "'" . mysqli_real_escape_string($conn, $team) . "'" : 'NULL';
      $summarySafe = mysqli_real_escape_string($conn, $summary);
      $descriptionSafe = mysqli_real_escape_string($conn, $description);
      $employmentTypeSafe = mysqli_real_escape_string($conn, $employmentType);
      $durationValue = $internshipDuration !== '' ? "'" . mysqli_real_escape_string($conn, $internshipDuration) . "'" : 'NULL';
      $eligibilityValue = $eligibilityText !== '' ? "'" . mysqli_real_escape_string($conn, $eligibilityText) . "'" : 'NULL';
      $questionsValue = trim((string) $questionsJson) !== '' ? "'" . mysqli_real_escape_string($conn, (string) $questionsJson) . "'" : 'NULL';
      $applicationOpenValue = $applicationOpenAt !== null ? "'" . mysqli_real_escape_string($conn, $applicationOpenAt) . "'" : 'NULL';
      $applicationCloseValue = $applicationCloseAt !== null ? "'" . mysqli_real_escape_string($conn, $applicationCloseAt) . "'" : 'NULL';
      $statusSafe = mysqli_real_escape_string($conn, $status);

      if ($openingId > 0) {
        $sql = "UPDATE career_openings
                SET slug = '$slugSafe',
                    title = '$titleSafe',
                    team = $teamValue,
                    summary = '$summarySafe',
                    description = '$descriptionSafe',
                    employment_type = '$employmentTypeSafe',
                    internship_duration = $durationValue,
                    eligibility_text = $eligibilityValue,
                    questions_json = $questionsValue,
                    application_open_at = $applicationOpenValue,
                    application_close_at = $applicationCloseValue,
                    status = '$statusSafe',
                    is_public = $isPublic,
                    sort_order = $sortOrder,
                    updated_by_admin_id = " . (int) $admin_id . ",
                    updated_at = NOW()
                WHERE id = $openingId
                LIMIT 1";

        if (!mysqli_query($conn, $sql)) {
          throw new Exception('Unable to update this career opening right now.');
        }

        ccCareerOpeningsSetFlash('success', 'Career opening updated successfully.');
        $redirectUrl .= '?edit=' . $openingId;
      } else {
        $sql = "INSERT INTO career_openings
                  (slug, title, team, summary, description, employment_type, internship_duration, eligibility_text, questions_json, application_open_at, application_close_at, status, is_public, sort_order, created_by_admin_id, updated_by_admin_id, created_at, updated_at)
                VALUES
                  ('$slugSafe', '$titleSafe', $teamValue, '$summarySafe', '$descriptionSafe', '$employmentTypeSafe', $durationValue, $eligibilityValue, $questionsValue, $applicationOpenValue, $applicationCloseValue, '$statusSafe', $isPublic, $sortOrder, " . (int) $admin_id . ", " . (int) $admin_id . ", NOW(), NOW())";

        if (!mysqli_query($conn, $sql)) {
          throw new Exception('Unable to create this career opening right now.');
        }

        $newId = (int) mysqli_insert_id($conn);
        ccCareerOpeningsSetFlash('success', 'Career opening created successfully.');
        $redirectUrl .= '?edit=' . $newId;
      }
    } elseif ($action === 'toggle_public') {
      $openingId = (int) ($_POST['opening_id'] ?? 0);
      $opening = ccCareersFetchOpeningById($conn, $openingId);
      if (!$opening) {
        throw new Exception('The selected career opening was not found.');
      }

      $nextState = (int) ($opening['is_public'] ?? 0) === 1 ? 0 : 1;
      if (!mysqli_query($conn, "UPDATE career_openings SET is_public = $nextState, updated_by_admin_id = " . (int) $admin_id . ", updated_at = NOW() WHERE id = $openingId LIMIT 1")) {
        throw new Exception('Unable to update the public visibility for this opening.');
      }

      ccCareerOpeningsSetFlash('success', $nextState === 1 ? 'Opening is now visible on /careers once it is published.' : 'Opening hidden from /careers.');
    } elseif ($action === 'set_status') {
      $openingId = (int) ($_POST['opening_id'] ?? 0);
      $nextStatus = ccCareersNormalizeOpeningStatus($_POST['next_status'] ?? 'draft');
      if (!mysqli_query($conn, "UPDATE career_openings SET status = '" . mysqli_real_escape_string($conn, $nextStatus) . "', updated_by_admin_id = " . (int) $admin_id . ", updated_at = NOW() WHERE id = $openingId LIMIT 1")) {
        throw new Exception('Unable to update the opening status right now.');
      }

      ccCareerOpeningsSetFlash('success', 'Opening status updated to ' . ucfirst($nextStatus) . '.');
    } elseif ($action === 'delete') {
      $openingId = (int) ($_POST['opening_id'] ?? 0);
      if ($openingId <= 0) {
        throw new Exception('Select a valid opening to delete.');
      }

      if ($applicationsTableExists) {
        $appsResult = mysqli_query($conn, "SELECT COUNT(id) AS total_rows FROM career_applications WHERE opening_id = $openingId");
        $appsRow = $appsResult ? mysqli_fetch_assoc($appsResult) : ['total_rows' => 0];
        if ((int) ($appsRow['total_rows'] ?? 0) > 0) {
          throw new Exception('This opening already has applications. Archive it instead of deleting it.');
        }
      }

      if (!mysqli_query($conn, "DELETE FROM career_openings WHERE id = $openingId LIMIT 1")) {
        throw new Exception('Unable to delete this opening right now.');
      }

      ccCareerOpeningsSetFlash('success', 'Career opening deleted successfully.');
      if (isset($_GET['edit']) && (int) $_GET['edit'] === $openingId) {
        $redirectUrl = 'careers_openings.php';
      }
    } else {
      throw new Exception('Unsupported action supplied.');
    }
  } catch (Throwable $e) {
    ccCareerOpeningsSetFlash('danger', $e->getMessage());
  }

  header('Location: ' . $redirectUrl);
  exit();
}

$flash = ccCareerOpeningsGetFlash();
$openings = $openingsTableExists ? ccCareersFetchOpenings($conn) : [];
$stats = ccCareerOpeningsBuildStats($openings);
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingOpening = $editingId > 0 ? ccCareersFetchOpeningById($conn, $editingId) : null;
$editingQuestions = $editingOpening ? implode("\n", ccCareersDecodeQuestionsJson($editingOpening['questions_json'] ?? null)) : '';
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Careers Openings | Nivasity Command Center</title>
    <meta name="description" content="Create, publish, and manage career openings shown on the public Nivasity careers page." />
    <?php include('partials/_head.php') ?>
    <style>
      .career-stat-card {
        border-radius: 1rem;
        border: 1px solid rgba(105, 108, 255, 0.08);
        box-shadow: 0 10px 24px rgba(67, 89, 113, 0.06);
      }

      .career-stat-card .label {
        display: block;
        font-size: 0.8rem;
        font-weight: 700;
        color: #8592a3;
        text-transform: uppercase;
        letter-spacing: 0.06em;
      }

      .career-stat-card .value {
        display: block;
        margin-top: 0.45rem;
        font-size: 1.85rem;
        font-weight: 800;
        color: #566a7f;
      }

      .opening-summary {
        max-width: 360px;
        white-space: normal;
      }

      .opening-form-card {
        position: sticky;
        top: 96px;
      }

      @media (max-width: 1199px) {
        .opening-form-card {
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
              <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Resources /</span> Careers Openings</h4>

              <?php if ($flash) { ?>
              <div class="alert alert-<?php echo htmlspecialchars((string) ($flash['type'] ?? 'info')); ?> alert-dismissible" role="alert">
                <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
              <?php } ?>

              <?php if (!$openingsTableExists) { ?>
              <div class="alert alert-warning mb-4" role="alert">
                The <strong>career_openings</strong> table is not available in this database yet. Apply the careers schema before using this screen.
              </div>
              <?php } ?>

              <div class="row g-4 mb-4">
                <div class="col-md-3 col-sm-6">
                  <div class="card career-stat-card h-100">
                    <div class="card-body">
                      <span class="label">Total Openings</span>
                      <span class="value"><?php echo (int) ($stats['total'] ?? 0); ?></span>
                    </div>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="card career-stat-card h-100">
                    <div class="card-body">
                      <span class="label">Published</span>
                      <span class="value"><?php echo (int) ($stats['published'] ?? 0); ?></span>
                    </div>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="card career-stat-card h-100">
                    <div class="card-body">
                      <span class="label">Live on Careers</span>
                      <span class="value"><?php echo (int) ($stats['live_now'] ?? 0); ?></span>
                    </div>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6">
                  <div class="card career-stat-card h-100">
                    <div class="card-body">
                      <span class="label">Draft / Archived</span>
                      <span class="value"><?php echo (int) (($stats['draft'] ?? 0) + ($stats['archived'] ?? 0)); ?></span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row g-4 align-items-start">
                <div class="col-xl-7">
                  <div class="card">
                    <div class="card-header d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                      <div>
                        <h5 class="mb-1">Openings Posted from Command Center</h5>
                        <p class="text-muted mb-0">Only openings that are both <strong>published</strong> and <strong>public</strong> appear on <code>/careers</code>.</p>
                      </div>
                      <a href="careers_openings.php" class="btn btn-outline-primary">Create New Opening</a>
                    </div>
                    <div class="card-body">
                      <div class="table-responsive text-nowrap">
                        <table class="table align-middle">
                          <thead class="table-light">
                            <tr>
                              <th>Opening</th>
                              <th>Status</th>
                              <th>Window</th>
                              <th>Updated</th>
                              <th class="text-end">Actions</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php if (empty($openings)) { ?>
                            <tr>
                              <td colspan="5" class="text-center text-muted py-4">No career openings have been created yet.</td>
                            </tr>
                            <?php } ?>
                            <?php foreach ($openings as $opening) { ?>
                              <?php
                                $openingId = (int) ($opening['id'] ?? 0);
                                $status = ccCareersNormalizeOpeningStatus($opening['status'] ?? 'draft');
                                $isPublic = (int) ($opening['is_public'] ?? 0) === 1;
                                $isLive = ccCareersOpeningIsPublic($opening);
                              ?>
                            <tr>
                              <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($opening['title'] ?? 'Untitled Opening')); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars((string) ($opening['team'] ?? 'General')); ?><?php if (!empty($opening['employment_type'])) { ?> &middot; <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $opening['employment_type']))); ?><?php } ?></div>
                                <div class="opening-summary text-muted small mt-1"><?php echo htmlspecialchars((string) ($opening['summary'] ?? '')); ?></div>
                              </td>
                              <td>
                                <span class="badge bg-label-<?php echo ccCareerOpeningsStatusBadge($status); ?> mb-1"><?php echo htmlspecialchars(ucfirst($status)); ?></span><br>
                                <?php if ($isPublic) { ?>
                                  <span class="badge bg-label-info mb-1">Public</span><br>
                                <?php } else { ?>
                                  <span class="badge bg-label-secondary mb-1">Hidden</span><br>
                                <?php } ?>
                                <?php if ($isLive) { ?>
                                  <span class="badge bg-label-success">Live Now</span>
                                <?php } else { ?>
                                  <span class="badge bg-label-warning">Not Live</span>
                                <?php } ?>
                              </td>
                              <td>
                                <div class="small text-muted">Open</div>
                                <div><?php echo !empty($opening['application_open_at']) ? htmlspecialchars(date('d M Y h:i A', strtotime((string) $opening['application_open_at']))) : 'Immediately'; ?></div>
                                <div class="small text-muted mt-2">Close</div>
                                <div><?php echo !empty($opening['application_close_at']) ? htmlspecialchars(date('d M Y h:i A', strtotime((string) $opening['application_close_at']))) : 'No deadline'; ?></div>
                              </td>
                              <td>
                                <div><?php echo !empty($opening['updated_at']) ? htmlspecialchars(date('d M Y', strtotime((string) $opening['updated_at']))) : '-'; ?></div>
                                <div class="text-muted small"><?php echo !empty($opening['updated_by_name']) ? htmlspecialchars(trim((string) $opening['updated_by_name'])) : 'System'; ?></div>
                              </td>
                              <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                  <a href="careers_openings.php?edit=<?php echo $openingId; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                  <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_public" />
                                    <input type="hidden" name="opening_id" value="<?php echo $openingId; ?>" />
                                    <button type="submit" class="btn btn-sm btn-outline-info"><?php echo $isPublic ? 'Hide' : 'Show'; ?></button>
                                  </form>
                                  <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="set_status" />
                                    <input type="hidden" name="opening_id" value="<?php echo $openingId; ?>" />
                                    <input type="hidden" name="next_status" value="<?php echo $status === 'published' ? 'archived' : 'published'; ?>" />
                                    <button type="submit" class="btn btn-sm btn-outline-warning"><?php echo $status === 'published' ? 'Archive' : 'Publish'; ?></button>
                                  </form>
                                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this opening? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete" />
                                    <input type="hidden" name="opening_id" value="<?php echo $openingId; ?>" />
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                  </form>
                                </div>
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
                  <div class="card opening-form-card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2">
                      <div>
                        <h5 class="mb-1"><?php echo $editingOpening ? 'Edit Opening' : 'Create Opening'; ?></h5>
                        <p class="text-muted mb-0">Define what appears on <code>/careers</code> and what applicants must answer.</p>
                      </div>
                      <?php if ($editingOpening) { ?>
                        <a href="careers_openings.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                      <?php } ?>
                    </div>
                    <div class="card-body">
                      <form method="post">
                        <input type="hidden" name="action" value="save" />
                        <input type="hidden" name="opening_id" value="<?php echo (int) ($editingOpening['id'] ?? 0); ?>" />

                        <div class="row g-3">
                          <div class="col-md-8">
                            <label class="form-label" for="openingTitle">Opening Title</label>
                            <input type="text" class="form-control" id="openingTitle" name="title" maxlength="160" value="<?php echo htmlspecialchars((string) ($editingOpening['title'] ?? '')); ?>" required />
                          </div>
                          <div class="col-md-4">
                            <label class="form-label" for="openingTeam">Team</label>
                            <input type="text" class="form-control" id="openingTeam" name="team" maxlength="120" value="<?php echo htmlspecialchars((string) ($editingOpening['team'] ?? '')); ?>" placeholder="Operations" />
                          </div>

                          <div class="col-md-7">
                            <label class="form-label" for="openingSlug">Slug</label>
                            <input type="text" class="form-control" id="openingSlug" name="slug" maxlength="160" value="<?php echo htmlspecialchars((string) ($editingOpening['slug'] ?? '')); ?>" placeholder="student-community-intern" />
                            <div class="form-text">Leave blank to generate from the title.</div>
                          </div>
                          <div class="col-md-5">
                            <label class="form-label" for="openingEmploymentType">Type</label>
                            <select class="form-select" id="openingEmploymentType" name="employment_type">
                              <?php foreach (ccCareersEmploymentTypes() as $type) { ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo (($editingOpening['employment_type'] ?? 'internship') === $type) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $type))); ?></option>
                              <?php } ?>
                            </select>
                          </div>

                          <div class="col-12">
                            <label class="form-label" for="openingSummary">Summary</label>
                            <input type="text" class="form-control" id="openingSummary" name="summary" maxlength="255" value="<?php echo htmlspecialchars((string) ($editingOpening['summary'] ?? '')); ?>" placeholder="3-month internship for 200L+ students helping campus growth." required />
                          </div>

                          <div class="col-md-6">
                            <label class="form-label" for="openingDuration">Internship Duration</label>
                            <input type="text" class="form-control" id="openingDuration" name="internship_duration" maxlength="80" value="<?php echo htmlspecialchars((string) ($editingOpening['internship_duration'] ?? '3 months')); ?>" placeholder="3 months" />
                          </div>
                          <div class="col-md-3">
                            <label class="form-label" for="openingStatus">Status</label>
                            <select class="form-select" id="openingStatus" name="status">
                              <?php foreach (ccCareersOpeningStatuses() as $statusOption) { ?>
                                <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo (($editingOpening['status'] ?? 'draft') === $statusOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($statusOption)); ?></option>
                              <?php } ?>
                            </select>
                          </div>
                          <div class="col-md-3">
                            <label class="form-label" for="openingSortOrder">Sort Order</label>
                            <input type="number" class="form-control" id="openingSortOrder" name="sort_order" value="<?php echo (int) ($editingOpening['sort_order'] ?? 0); ?>" />
                          </div>

                          <div class="col-md-6">
                            <label class="form-label" for="openingOpenAt">Applications Open</label>
                            <input type="datetime-local" class="form-control" id="openingOpenAt" name="application_open_at" value="<?php echo htmlspecialchars(ccCareersFormatDateTimeInput($editingOpening['application_open_at'] ?? '')); ?>" />
                          </div>
                          <div class="col-md-6">
                            <label class="form-label" for="openingCloseAt">Applications Close</label>
                            <input type="datetime-local" class="form-control" id="openingCloseAt" name="application_close_at" value="<?php echo htmlspecialchars(ccCareersFormatDateTimeInput($editingOpening['application_close_at'] ?? '')); ?>" />
                          </div>

                          <div class="col-12">
                            <label class="form-label" for="openingDescription">Description</label>
                            <textarea class="form-control" id="openingDescription" name="description" rows="6" required><?php echo htmlspecialchars((string) ($editingOpening['description'] ?? '')); ?></textarea>
                          </div>

                          <div class="col-12">
                            <label class="form-label" for="openingEligibility">Eligibility / Notes</label>
                            <textarea class="form-control" id="openingEligibility" name="eligibility_text" rows="3" placeholder="200L and above. Certificate after internship. Ambassador consideration based on performance."><?php echo htmlspecialchars((string) ($editingOpening['eligibility_text'] ?? '')); ?></textarea>
                          </div>

                          <div class="col-12">
                            <label class="form-label" for="openingQuestions">Role-Specific Questions</label>
                            <textarea class="form-control" id="openingQuestions" name="questions_text" rows="5" placeholder="One question per line"><?php echo htmlspecialchars($editingQuestions); ?></textarea>
                            <div class="form-text">These questions are rendered on the public careers form in the same order.</div>
                          </div>

                          <div class="col-12">
                            <div class="form-check form-switch">
                              <input class="form-check-input" type="checkbox" id="openingIsPublic" name="is_public" <?php echo !empty($editingOpening) && (int) ($editingOpening['is_public'] ?? 0) === 1 ? 'checked' : ''; ?> />
                              <label class="form-check-label" for="openingIsPublic">Allow this opening to appear on the public careers page when published</label>
                            </div>
                          </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4">
                          <button type="submit" class="btn btn-primary"><?php echo $editingOpening ? 'Save Changes' : 'Create Opening'; ?></button>
                          <?php if ($editingOpening) { ?>
                            <a href="careers_openings.php" class="btn btn-outline-secondary">Cancel</a>
                          <?php } ?>
                        </div>
                      </form>
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

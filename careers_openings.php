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

function ccCareerOpeningsIsAjaxRequest()
{
  $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

  return $requestedWith === 'xmlhttprequest' || (string) ($_REQUEST['ajax'] ?? '') === '1';
}

function ccCareerOpeningsRespondJson(array $payload, int $statusCode = 200)
{
  http_response_code($statusCode);
  header('Content-Type: application/json');
  echo json_encode($payload);
  exit();
}

function ccCareerOpeningsFormatDateLabel($value, $fallback)
{
  $value = trim((string) $value);
  if ($value === '') {
    return $fallback;
  }

  $timestamp = strtotime($value);
  if ($timestamp === false) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }

  return htmlspecialchars(date('d M Y', $timestamp), ENT_QUOTES, 'UTF-8');
}

function ccCareerOpeningsFormatDateTimeLabel($value, $fallback)
{
  $value = trim((string) $value);
  if ($value === '') {
    return $fallback;
  }

  $timestamp = strtotime($value);
  if ($timestamp === false) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }

  return htmlspecialchars(date('d M Y h:i A', $timestamp), ENT_QUOTES, 'UTF-8');
}

function ccCareerOpeningsRenderStatsCards(array $stats)
{
  $cards = [
    ['label' => 'Total Openings', 'value' => (int) ($stats['total'] ?? 0)],
    ['label' => 'Published', 'value' => (int) ($stats['published'] ?? 0)],
    ['label' => 'Live on Careers', 'value' => (int) ($stats['live_now'] ?? 0)],
    ['label' => 'Draft / Archived', 'value' => (int) (($stats['draft'] ?? 0) + ($stats['archived'] ?? 0))],
  ];

  ob_start();
  foreach ($cards as $card) {
    ?>
    <div class="col-md-3 col-sm-6">
      <div class="card career-stat-card h-100">
        <div class="card-body">
          <span class="label"><?php echo htmlspecialchars((string) $card['label'], ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="value"><?php echo (int) $card['value']; ?></span>
        </div>
      </div>
    </div>
    <?php
  }

  return ob_get_clean();
}

function ccCareerOpeningsRenderTableRows(array $openings)
{
  ob_start();

  if (empty($openings)) {
    ?>
    <tr>
      <td colspan="5" class="text-center text-muted py-4">No career openings have been created yet.</td>
    </tr>
    <?php
    return ob_get_clean();
  }

  foreach ($openings as $opening) {
    $openingId = (int) ($opening['id'] ?? 0);
    $status = ccCareersNormalizeOpeningStatus($opening['status'] ?? 'draft');
    $isPublic = (int) ($opening['is_public'] ?? 0) === 1;
    $isLive = ccCareersOpeningIsPublic($opening);
    $statusActionLabel = $status === 'published' ? 'Archive' : 'Publish';
    $nextStatus = $status === 'published' ? 'archived' : 'published';
    $statusLoadingText = $status === 'published' ? 'Archiving...' : 'Publishing...';
    ?>
    <tr>
      <td>
        <div class="fw-semibold"><?php echo htmlspecialchars((string) ($opening['title'] ?? 'Untitled Opening'), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="text-muted small"><?php echo htmlspecialchars((string) ($opening['team'] ?? 'General'), ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($opening['employment_type'])) { ?> &middot; <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $opening['employment_type'])), ENT_QUOTES, 'UTF-8'); ?><?php } ?></div>
        <div class="opening-summary text-muted small mt-1"><?php echo htmlspecialchars((string) ($opening['summary'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
      </td>
      <td>
        <span class="badge bg-label-<?php echo ccCareerOpeningsStatusBadge($status); ?> mb-1"><?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></span><br>
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
        <div><?php echo ccCareerOpeningsFormatDateTimeLabel($opening['application_open_at'] ?? '', 'Immediately'); ?></div>
        <div class="small text-muted mt-2">Close</div>
        <div><?php echo ccCareerOpeningsFormatDateTimeLabel($opening['application_close_at'] ?? '', 'No deadline'); ?></div>
      </td>
      <td>
        <div><?php echo ccCareerOpeningsFormatDateLabel($opening['updated_at'] ?? '', '-'); ?></div>
        <div class="text-muted small"><?php echo !empty($opening['updated_by_name']) ? htmlspecialchars(trim((string) $opening['updated_by_name']), ENT_QUOTES, 'UTF-8') : 'System'; ?></div>
      </td>
      <td class="text-end">
        <div class="d-inline-flex flex-wrap justify-content-end gap-2">
          <button type="button" class="btn btn-sm btn-outline-primary career-opening-edit-btn" data-opening-id="<?php echo $openingId; ?>">Edit</button>
          <button type="button" class="btn btn-sm btn-outline-info career-opening-action-btn" data-action="toggle_public" data-opening-id="<?php echo $openingId; ?>" data-loading-text="Updating..."><?php echo $isPublic ? 'Hide' : 'Show'; ?></button>
          <button type="button" class="btn btn-sm btn-outline-warning career-opening-action-btn" data-action="set_status" data-opening-id="<?php echo $openingId; ?>" data-next-status="<?php echo htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8'); ?>" data-loading-text="<?php echo htmlspecialchars($statusLoadingText, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusActionLabel, ENT_QUOTES, 'UTF-8'); ?></button>
          <button type="button" class="btn btn-sm btn-outline-danger career-opening-action-btn" data-action="delete" data-opening-id="<?php echo $openingId; ?>" data-confirm-message="Delete this opening? This cannot be undone." data-loading-text="Deleting...">Delete</button>
        </div>
      </td>
    </tr>
    <?php
  }

  return ob_get_clean();
}

function ccCareerOpeningsSerializeOpening(array $opening)
{
  return [
    'id' => (int) ($opening['id'] ?? 0),
    'title' => (string) ($opening['title'] ?? ''),
    'team' => (string) ($opening['team'] ?? ''),
    'slug' => (string) ($opening['slug'] ?? ''),
    'summary' => (string) ($opening['summary'] ?? ''),
    'description' => (string) ($opening['description'] ?? ''),
    'employment_type' => ccCareersNormalizeEmploymentType($opening['employment_type'] ?? 'internship'),
    'internship_duration' => (string) ($opening['internship_duration'] ?? '3 months'),
    'eligibility_text' => (string) ($opening['eligibility_text'] ?? ''),
    'status' => ccCareersNormalizeOpeningStatus($opening['status'] ?? 'draft'),
    'sort_order' => (int) ($opening['sort_order'] ?? 0),
    'is_public' => (int) ($opening['is_public'] ?? 0),
    'application_open_at' => ccCareersFormatDateTimeInput($opening['application_open_at'] ?? ''),
    'application_close_at' => ccCareersFormatDateTimeInput($opening['application_close_at'] ?? ''),
    'questions_text' => implode("\n", ccCareersDecodeQuestionsJson($opening['questions_json'] ?? null)),
  ];
}

function ccCareerOpeningsBuildAjaxViewState($conn, $openingsTableExists)
{
  $openings = $openingsTableExists ? ccCareersFetchOpenings($conn) : [];
  $stats = ccCareerOpeningsBuildStats($openings);

  return [
    'stats_html' => ccCareerOpeningsRenderStatsCards($stats),
    'table_html' => ccCareerOpeningsRenderTableRows($openings),
    'stats' => $stats,
  ];
}

$openingsTableExists = ccCareersOpeningsTableExists($conn);
$applicationsTableExists = ccCareersApplicationsTableExists($conn);
$isAjaxRequest = ccCareerOpeningsIsAjaxRequest();

if ($isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'GET') {
  $action = trim((string) ($_GET['action'] ?? 'list'));

  if ($action === 'list') {
    ccCareerOpeningsRespondJson(array_merge([
      'success' => true,
    ], ccCareerOpeningsBuildAjaxViewState($conn, $openingsTableExists)));
  }

  if ($action === 'get') {
    if (!$openingsTableExists) {
      ccCareerOpeningsRespondJson([
        'success' => false,
        'message' => 'The career_openings table is not available yet. Apply the careers schema first.',
      ], 503);
    }

    $openingId = (int) ($_GET['opening_id'] ?? 0);
    if ($openingId <= 0) {
      ccCareerOpeningsRespondJson([
        'success' => false,
        'message' => 'Select a valid opening to edit.',
      ], 400);
    }

    $opening = ccCareersFetchOpeningById($conn, $openingId);
    if (!$opening) {
      ccCareerOpeningsRespondJson([
        'success' => false,
        'message' => 'The selected career opening was not found.',
      ], 404);
    }

    ccCareerOpeningsRespondJson([
      'success' => true,
      'opening' => ccCareerOpeningsSerializeOpening($opening),
    ]);
  }

  ccCareerOpeningsRespondJson([
    'success' => false,
    'message' => 'Unsupported action supplied.',
  ], 400);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$openingsTableExists) {
    if ($isAjaxRequest) {
      ccCareerOpeningsRespondJson([
        'success' => false,
        'message' => 'The career_openings table is not available yet. Apply the careers schema first.',
      ], 503);
    }

    ccCareerOpeningsSetFlash('danger', 'The career_openings table is not available yet. Apply the careers schema first.');
    header('Location: careers_openings.php');
    exit();
  }

  $action = trim((string) ($_POST['action'] ?? ''));
  $redirectUrl = 'careers_openings.php';
  $successMessage = '';
  $affectedOpeningId = 0;

  try {
    if ($action === 'save') {
      $openingId = (int) ($_POST['opening_id'] ?? 0);
      $affectedOpeningId = $openingId;
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

        $successMessage = 'Career opening updated successfully.';
      } else {
        $sql = "INSERT INTO career_openings
                  (slug, title, team, summary, description, employment_type, internship_duration, eligibility_text, questions_json, application_open_at, application_close_at, status, is_public, sort_order, created_by_admin_id, updated_by_admin_id, created_at, updated_at)
                VALUES
                  ('$slugSafe', '$titleSafe', $teamValue, '$summarySafe', '$descriptionSafe', '$employmentTypeSafe', $durationValue, $eligibilityValue, $questionsValue, $applicationOpenValue, $applicationCloseValue, '$statusSafe', $isPublic, $sortOrder, " . (int) $admin_id . ", " . (int) $admin_id . ", NOW(), NOW())";

        if (!mysqli_query($conn, $sql)) {
          throw new Exception('Unable to create this career opening right now.');
        }

        $newId = (int) mysqli_insert_id($conn);
        $affectedOpeningId = $newId;
        $successMessage = 'Career opening created successfully.';
      }
    } elseif ($action === 'toggle_public') {
      $openingId = (int) ($_POST['opening_id'] ?? 0);
      $affectedOpeningId = $openingId;
      $opening = ccCareersFetchOpeningById($conn, $openingId);
      if (!$opening) {
        throw new Exception('The selected career opening was not found.');
      }

      $nextState = (int) ($opening['is_public'] ?? 0) === 1 ? 0 : 1;
      if (!mysqli_query($conn, "UPDATE career_openings SET is_public = $nextState, updated_by_admin_id = " . (int) $admin_id . ", updated_at = NOW() WHERE id = $openingId LIMIT 1")) {
        throw new Exception('Unable to update the public visibility for this opening.');
      }

      $successMessage = $nextState === 1 ? 'Opening is now visible on /careers once it is published.' : 'Opening hidden from /careers.';
    } elseif ($action === 'set_status') {
      $openingId = (int) ($_POST['opening_id'] ?? 0);
      $affectedOpeningId = $openingId;
      $nextStatus = ccCareersNormalizeOpeningStatus($_POST['next_status'] ?? 'draft');
      if (!mysqli_query($conn, "UPDATE career_openings SET status = '" . mysqli_real_escape_string($conn, $nextStatus) . "', updated_by_admin_id = " . (int) $admin_id . ", updated_at = NOW() WHERE id = $openingId LIMIT 1")) {
        throw new Exception('Unable to update the opening status right now.');
      }

      $successMessage = 'Opening status updated to ' . ucfirst($nextStatus) . '.';
    } elseif ($action === 'delete') {
      $openingId = (int) ($_POST['opening_id'] ?? 0);
      if ($openingId <= 0) {
        throw new Exception('Select a valid opening to delete.');
      }

      $affectedOpeningId = $openingId;

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

      $successMessage = 'Career opening deleted successfully.';
    } else {
      throw new Exception('Unsupported action supplied.');
    }
  } catch (Throwable $e) {
    if ($isAjaxRequest) {
      ccCareerOpeningsRespondJson([
        'success' => false,
        'message' => $e->getMessage(),
      ], 400);
    }

    ccCareerOpeningsSetFlash('danger', $e->getMessage());
    header('Location: ' . $redirectUrl);
    exit();
  }

  if ($isAjaxRequest) {
    $responsePayload = array_merge([
      'success' => true,
      'message' => $successMessage,
    ], ccCareerOpeningsBuildAjaxViewState($conn, true));
    if ($affectedOpeningId > 0) {
      $responsePayload['opening_id'] = $affectedOpeningId;
    }

    ccCareerOpeningsRespondJson($responsePayload);
  }

  ccCareerOpeningsSetFlash('success', $successMessage);
  header('Location: ' . $redirectUrl);
  exit();
}

$flash = ccCareerOpeningsGetFlash();
$openings = $openingsTableExists ? ccCareersFetchOpenings($conn) : [];
$stats = ccCareerOpeningsBuildStats($openings);
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

      .career-opening-modal .modal-content {
        max-height: calc(100vh - 2rem);
      }

      .career-opening-modal .modal-body {
        overflow-y: auto;
        max-height: calc(100vh - 12rem);
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

              <div id="careerOpeningsAlertHost">
                <?php if ($flash) { ?>
                <div class="alert alert-<?php echo htmlspecialchars((string) ($flash['type'] ?? 'info')); ?> alert-dismissible" role="alert">
                  <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php } ?>
              </div>

              <?php if (!$openingsTableExists) { ?>
              <div class="alert alert-warning mb-4" role="alert">
                The <strong>career_openings</strong> table is not available in this database yet. Apply the careers schema before using this screen.
              </div>
              <?php } ?>

              <div class="row g-4 mb-4" id="careerOpeningsStats">
                <?php echo ccCareerOpeningsRenderStatsCards($stats); ?>
              </div>

              <div class="row g-4 align-items-start">
                <div class="col-12">
                  <div class="card">
                    <div class="card-header d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                      <div>
                        <h5 class="mb-1">Openings Posted from Command Center</h5>
                        <p class="text-muted mb-0">Only openings that are both <strong>published</strong> and <strong>public</strong> appear on <code>/careers</code>.</p>
                      </div>
                      <button type="button" class="btn btn-primary" id="careerOpeningCreateBtn" <?php echo !$openingsTableExists ? 'disabled' : ''; ?>>Create New Opening</button>
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
                          <tbody id="careerOpeningsTableBody">
                            <?php echo ccCareerOpeningsRenderTableRows($openings); ?>
                          </tbody>
                        </table>
                      </div>
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

    <div class="modal fade career-opening-modal" id="careerOpeningModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <form id="careerOpeningModalForm">
            <input type="hidden" name="action" value="save" />
            <input type="hidden" name="opening_id" id="careerOpeningId" value="0" />
            <div class="modal-header">
              <div>
                <h5 class="modal-title" id="careerOpeningModalLabel">Create New Opening</h5>
                <p class="text-muted mb-0 small">Define what appears on <code>/careers</code> and what applicants must answer.</p>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label" for="openingTitle">Opening Title</label>
                  <input type="text" class="form-control" id="openingTitle" name="title" maxlength="160" required />
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="openingTeam">Team</label>
                  <input type="text" class="form-control" id="openingTeam" name="team" maxlength="120" placeholder="Operations" />
                </div>

                <div class="col-md-7">
                  <label class="form-label" for="openingSlug">Slug</label>
                  <input type="text" class="form-control" id="openingSlug" name="slug" maxlength="160" placeholder="student-community-intern" />
                  <div class="form-text">Leave blank to generate from the title.</div>
                </div>
                <div class="col-md-5">
                  <label class="form-label" for="openingEmploymentType">Type</label>
                  <select class="form-select" id="openingEmploymentType" name="employment_type">
                    <?php foreach (ccCareersEmploymentTypes() as $type) { ?>
                      <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $type))); ?></option>
                    <?php } ?>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label" for="openingSummary">Summary</label>
                  <input type="text" class="form-control" id="openingSummary" name="summary" maxlength="255" placeholder="3-month internship for 200L+ students helping campus growth." required />
                </div>

                <div class="col-md-6">
                  <label class="form-label" for="openingDuration">Duration</label>
                  <input type="text" class="form-control" id="openingDuration" name="internship_duration" maxlength="80" value="3 months" placeholder="3 months / permanent / contract-based" />
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="openingStatus">Status</label>
                  <select class="form-select" id="openingStatus" name="status">
                    <?php foreach (ccCareersOpeningStatuses() as $statusOption) { ?>
                      <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $statusOption === 'draft' ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($statusOption)); ?></option>
                    <?php } ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="openingSortOrder">Sort Order</label>
                  <input type="number" class="form-control" id="openingSortOrder" name="sort_order" value="0" />
                </div>

                <div class="col-md-6">
                  <label class="form-label" for="openingOpenAt">Applications Open</label>
                  <input type="datetime-local" class="form-control" id="openingOpenAt" name="application_open_at" />
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="openingCloseAt">Applications Close</label>
                  <input type="datetime-local" class="form-control" id="openingCloseAt" name="application_close_at" />
                </div>

                <div class="col-12">
                  <label class="form-label" for="openingDescription">Description</label>
                  <textarea class="form-control" id="openingDescription" name="description" rows="6" required></textarea>
                </div>

                <div class="col-12">
                  <label class="form-label" for="openingEligibility">Eligibility / Notes</label>
                  <textarea class="form-control" id="openingEligibility" name="eligibility_text" rows="3" placeholder="200L and above. Certificate after internship. Ambassador consideration based on performance."></textarea>
                </div>

                <div class="col-12">
                  <label class="form-label" for="openingQuestions">Role-Specific Questions</label>
                  <textarea class="form-control" id="openingQuestions" name="questions_text" rows="5" placeholder="One question per line"></textarea>
                  <div class="form-text">These questions are rendered on the public careers form in the same order.</div>
                </div>

                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="openingIsPublic" name="is_public" />
                    <label class="form-check-label" for="openingIsPublic">Allow this opening to appear on the public careers page when published</label>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" id="careerOpeningSubmitBtn">Create Opening</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="assets/vendor/libs/jquery/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/vendor/libs/popper/popper.min.js"></script>
    <script src="assets/vendor/js/menu.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
      $(function () {
        const pageUrl = 'careers_openings.php';
        const modalElement = document.getElementById('careerOpeningModal');
        const openingModal = new bootstrap.Modal(modalElement);
        const $form = $('#careerOpeningModalForm');
        const $submitBtn = $('#careerOpeningSubmitBtn');
        const $modalLabel = $('#careerOpeningModalLabel');
        const $stats = $('#careerOpeningsStats');
        const $tableBody = $('#careerOpeningsTableBody');
        const $alertHost = $('#careerOpeningsAlertHost');
        const createButtonLabel = 'Create Opening';
        const editButtonLabel = 'Save Changes';

        function buildAlertHtml(type, message) {
          return `
            <div class="alert alert-${type} alert-dismissible" role="alert">
              ${message}
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          `;
        }

        function showAlert(type, message) {
          $alertHost.html(buildAlertHtml(type, message));
          const container = document.querySelector('.container-xxl');
          if (container) {
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }

        function extractErrorMessage(xhr, fallbackMessage) {
          if (xhr.responseJSON && xhr.responseJSON.message) {
            return xhr.responseJSON.message;
          }

          return fallbackMessage;
        }

        function resetOpeningForm() {
          $form[0].reset();
          $('#careerOpeningId').val('0');
          $('#openingDuration').val('3 months');
          $('#openingStatus').val('draft');
          $('#openingSortOrder').val('0');
          $('#openingIsPublic').prop('checked', false);
          $modalLabel.text('Create New Opening');
          $submitBtn.text(createButtonLabel).prop('disabled', false);
        }

        function populateOpeningForm(opening) {
          resetOpeningForm();
          $('#careerOpeningId').val(String(opening.id || 0));
          $('#openingTitle').val(opening.title || '');
          $('#openingTeam').val(opening.team || '');
          $('#openingSlug').val(opening.slug || '');
          $('#openingSummary').val(opening.summary || '');
          $('#openingDescription').val(opening.description || '');
          $('#openingEmploymentType').val(opening.employment_type || 'internship');
          $('#openingDuration').val(opening.internship_duration || '3 months');
          $('#openingEligibility').val(opening.eligibility_text || '');
          $('#openingQuestions').val(opening.questions_text || '');
          $('#openingStatus').val(opening.status || 'draft');
          $('#openingSortOrder').val(String(opening.sort_order || 0));
          $('#openingOpenAt').val(opening.application_open_at || '');
          $('#openingCloseAt').val(opening.application_close_at || '');
          $('#openingIsPublic').prop('checked', Number(opening.is_public || 0) === 1);
          $modalLabel.text('Edit Opening');
          $submitBtn.text(editButtonLabel);
        }

        function updateOpeningsView(response) {
          if (typeof response.stats_html === 'string') {
            $stats.html(response.stats_html);
          }
          if (typeof response.table_html === 'string') {
            $tableBody.html(response.table_html);
          }
        }

        $('#careerOpeningCreateBtn').on('click', function () {
          resetOpeningForm();
          openingModal.show();
        });

        $(modalElement).on('hidden.bs.modal', function () {
          resetOpeningForm();
        });

        $(document).on('click', '.career-opening-edit-btn', function () {
          const $button = $(this);
          const openingId = Number($button.data('opening-id') || 0);
          const originalText = $button.text();

          if (!openingId) {
            showAlert('danger', 'Select a valid opening to edit.');
            return;
          }

          $button.prop('disabled', true).text('Loading...');

          $.ajax({
            url: pageUrl,
            method: 'GET',
            dataType: 'json',
            data: {
              ajax: 1,
              action: 'get',
              opening_id: openingId,
            },
          }).done(function (response) {
            if (!response.success || !response.opening) {
              showAlert('danger', response.message || 'Unable to load this opening right now.');
              return;
            }

            populateOpeningForm(response.opening);
            openingModal.show();
          }).fail(function (xhr) {
            showAlert('danger', extractErrorMessage(xhr, 'Unable to load this opening right now.'));
          }).always(function () {
            $button.prop('disabled', false).text(originalText);
          });
        });

        $form.on('submit', function (event) {
          event.preventDefault();

          const isEditing = Number($('#careerOpeningId').val() || 0) > 0;
          $submitBtn.prop('disabled', true).text(isEditing ? 'Saving...' : 'Creating...');

          $.ajax({
            url: `${pageUrl}?ajax=1`,
            method: 'POST',
            dataType: 'json',
            data: $form.serialize(),
          }).done(function (response) {
            updateOpeningsView(response);
            showAlert('success', response.message || 'Opening saved successfully.');
            openingModal.hide();
          }).fail(function (xhr) {
            showAlert('danger', extractErrorMessage(xhr, 'Unable to save this opening right now.'));
            $submitBtn.prop('disabled', false).text(isEditing ? editButtonLabel : createButtonLabel);
          });
        });

        $(document).on('click', '.career-opening-action-btn', function () {
          const $button = $(this);
          const openingId = Number($button.data('opening-id') || 0);
          const action = String($button.data('action') || '');
          const nextStatus = String($button.data('next-status') || '');
          const confirmMessage = String($button.data('confirm-message') || '');
          const loadingText = String($button.data('loading-text') || 'Processing...');
          const originalText = $button.text();

          if (!openingId || !action) {
            showAlert('danger', 'This action could not be completed. Refresh the page and try again.');
            return;
          }

          if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
          }

          $button.prop('disabled', true).text(loadingText);

          $.ajax({
            url: `${pageUrl}?ajax=1`,
            method: 'POST',
            dataType: 'json',
            data: {
              action: action,
              opening_id: openingId,
              next_status: nextStatus,
            },
          }).done(function (response) {
            updateOpeningsView(response);
            showAlert('success', response.message || 'Opening updated successfully.');
          }).fail(function (xhr) {
            showAlert('danger', extractErrorMessage(xhr, 'Unable to update this opening right now.'));
          }).always(function () {
            $button.prop('disabled', false).text(originalText);
          });
        });
      });
    </script>
  </body>
</html>

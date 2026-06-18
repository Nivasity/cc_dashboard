<?php
session_start();
include('model/config.php');
include('model/page_config.php');
include('model/surveys.php');

$admin_role = (int) ($_SESSION['nivas_adminRole'] ?? 0);

if (!$resource_mgt_menu || !in_array($admin_role, [1, 2, 3], true)) {
  header('Location: /');
  exit();
}

// Load LLM config for AI chat availability indicator
$llmConfigPath = __DIR__ . '/config/llm.php';
$llmAvailable = false;
if (file_exists($llmConfigPath)) {
  require_once $llmConfigPath;
  $llmAvailable = defined('GEMINI_API_KEY') && trim((string) GEMINI_API_KEY) !== '' && GEMINI_API_KEY !== 'your_gemini_api_key_here';
}

// Flash messages
function ccSurveysSetFlash($type, $message) {
  $_SESSION['surveys_flash'] = ['type' => $type, 'message' => $message];
}
function ccSurveysGetFlash() {
  $flash = $_SESSION['surveys_flash'] ?? null;
  unset($_SESSION['surveys_flash']);
  return $flash;
}

$tablesReady = ccSurveysTablesReady($conn);

// ─── Handle POST actions ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$tablesReady) {
    ccSurveysSetFlash('danger', 'Survey tables are not available in this database yet. Run the migration first.');
    header('Location: surveys.php');
    exit();
  }

  $action = trim((string) ($_POST['action'] ?? ''));

  try {
    // Create survey
    if ($action === 'create_survey') {
      $title = trim((string) ($_POST['title'] ?? ''));
      $description = trim((string) ($_POST['description'] ?? ''));
      $questionsJson = trim((string) ($_POST['questions_json'] ?? ''));
      $status = trim((string) ($_POST['status'] ?? 'draft'));
      $expiryDate = trim((string) ($_POST['expiry_date'] ?? ''));
      $allowDuplicate = isset($_POST['allow_duplicate_email']) ? 1 : 0;

      if ($title === '') throw new Exception('Survey title is required.');
      if ($questionsJson === '') throw new Exception('Survey JSON is required.');

      // Validate JSON
      $decoded = json_decode($questionsJson, true);
      if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
      }

      $surveyId = ccSurveysCreate($conn, $title, $description, $questionsJson, $status, $expiryDate ?: null, $allowDuplicate, (int) $admin_id);
      if ($surveyId === 0) throw new Exception('Failed to create survey.');

      ccSurveysSetFlash('success', 'Survey created successfully.');
      header('Location: surveys.php?survey=' . $surveyId);
      exit();
    }

    // Update survey
    if ($action === 'update_survey') {
      $surveyId = (int) ($_POST['survey_id'] ?? 0);
      $title = trim((string) ($_POST['title'] ?? ''));
      $description = trim((string) ($_POST['description'] ?? ''));
      $questionsJson = trim((string) ($_POST['questions_json'] ?? ''));
      $status = trim((string) ($_POST['status'] ?? 'draft'));
      $expiryDate = trim((string) ($_POST['expiry_date'] ?? ''));
      $allowDuplicate = isset($_POST['allow_duplicate_email']) ? 1 : 0;

      if ($surveyId <= 0) throw new Exception('Invalid survey ID.');
      if ($title === '') throw new Exception('Survey title is required.');
      if ($questionsJson === '') throw new Exception('Survey JSON is required.');

      $decoded = json_decode($questionsJson, true);
      if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
      }

      $ok = ccSurveysUpdate($conn, $surveyId, $title, $description, $questionsJson, $status, $expiryDate ?: null, $allowDuplicate, (int) $admin_id);
      if (!$ok) throw new Exception('Failed to update survey.');

      ccSurveysSetFlash('success', 'Survey updated successfully.');
      header('Location: surveys.php?survey=' . $surveyId);
      exit();
    }

    // Update status only
    if ($action === 'update_status') {
      $surveyId = (int) ($_POST['survey_id'] ?? 0);
      $status = trim((string) ($_POST['status'] ?? ''));
      if ($surveyId <= 0 || $status === '') throw new Exception('Invalid request.');
      ccSurveysUpdateStatus($conn, $surveyId, $status, (int) $admin_id);
      ccSurveysSetFlash('success', 'Survey status updated to ' . ucfirst($status) . '.');
      header('Location: surveys.php?survey=' . $surveyId);
      exit();
    }

    // Delete survey
    if ($action === 'delete_survey') {
      $surveyId = (int) ($_POST['survey_id'] ?? 0);
      if ($surveyId <= 0) throw new Exception('Invalid survey ID.');
      ccSurveysDelete($conn, $surveyId);
      ccSurveysSetFlash('success', 'Survey deleted successfully.');
      header('Location: surveys.php');
      exit();
    }

    throw new Exception('Unsupported action.');
  } catch (Throwable $e) {
    ccSurveysSetFlash('danger', $e->getMessage());
    header('Location: surveys.php' . (isset($surveyId) && $surveyId > 0 ? '?survey=' . $surveyId : ''));
    exit();
  }
}

$flash = ccSurveysGetFlash();
$allSurveys = $tablesReady ? ccSurveysFetchAll($conn) : [];
$globalStats = $tablesReady ? ccSurveysBuildGlobalStats($conn) : ['total_surveys' => 0, 'published_surveys' => 0, 'total_responses' => 0, 'responses_today' => 0, 'responses_this_week' => 0];

// Selected survey
$selectedSurveyId = isset($_GET['survey']) ? (int) $_GET['survey'] : 0;
$selectedSurvey = $selectedSurveyId > 0 ? ccSurveysFetchById($conn, $selectedSurveyId) : null;
$selectedResponses = $selectedSurvey ? ccSurveysFetchResponses($conn, $selectedSurveyId) : [];
$selectedStats = $selectedSurvey ? ccSurveysBuildStats($conn, $selectedSurveyId) : null;
$selectedQuestionMap = [];
if ($selectedSurvey) {
  $qJson = json_decode($selectedSurvey['questions_json'] ?? '{}', true);
  $selectedQuestionMap = ccSurveysExtractQuestionMap($qJson ?: []);
}

// Selected response detail
$selectedResponseId = isset($_GET['response']) ? (int) $_GET['response'] : 0;
$selectedResponse = $selectedResponseId > 0 ? ccSurveysFetchResponseById($conn, $selectedResponseId) : null;

// Editor mode
$editorMode = isset($_GET['editor']);
$editSurveyId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editSurvey = $editSurveyId > 0 ? ccSurveysFetchById($conn, $editSurveyId) : null;

// Bearer token for JS (AI chat)
$bearerToken = defined('API_BEARER_TOKEN') ? (string) API_BEARER_TOKEN : '';
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Surveys | Nivasity Command Center</title>
    <meta name="description" content="Manage surveys, view responses, and analyze feedback with AI." />
    <?php include('partials/_head.php') ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css" />
    <style>
      .survey-stat-card {
        border-radius: 1rem;
        border: 1px solid rgba(105, 108, 255, 0.08);
        box-shadow: 0 10px 24px rgba(67, 89, 113, 0.06);
      }
      .survey-stat-card .label {
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
        color: #8592a3;
        text-transform: uppercase;
        letter-spacing: 0.06em;
      }
      .survey-stat-card .value {
        display: block;
        margin-top: 0.4rem;
        font-size: 1.75rem;
        font-weight: 800;
        color: #566a7f;
      }
      .response-detail-card { position: sticky; top: 96px; }
      .detail-section + .detail-section {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(67, 89, 113, 0.12);
      }
      @media (max-width: 1199px) {
        .response-detail-card { position: static; }
      }
      .json-editor {
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
        white-space: pre;
        tab-size: 2;
        resize: vertical;
        min-height: 300px;
      }
      .ai-chat-messages {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid rgba(67, 89, 113, 0.12);
        border-radius: 0.5rem;
        padding: 1rem;
        background: rgba(67, 89, 113, 0.02);
      }
      .ai-msg { margin-bottom: 1rem; }
      .ai-msg:last-child { margin-bottom: 0; }
      .ai-msg-user { text-align: right; }
      .ai-msg-user .ai-bubble {
        display: inline-block;
        background: #696cff;
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: 1rem 1rem 0 1rem;
        max-width: 80%;
        text-align: left;
      }
      .ai-msg-bot .ai-bubble {
        display: inline-block;
        background: rgba(67, 89, 113, 0.08);
        padding: 0.75rem 1rem;
        border-radius: 1rem 1rem 1rem 0;
        max-width: 90%;
        text-align: left;
      }
      .ai-msg-bot .ai-bubble p:last-child { margin-bottom: 0; }
      .survey-link-copy {
        cursor: pointer;
        color: #696cff;
        text-decoration: underline;
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
              <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Resources /</span> Surveys</h4>

              <?php if ($flash) { ?>
              <div class="alert alert-<?php echo htmlspecialchars((string) ($flash['type'] ?? 'info')); ?> alert-dismissible" role="alert">
                <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
              <?php } ?>

              <?php if (!$tablesReady) { ?>
              <div class="alert alert-warning mb-4" role="alert">
                The survey tables are not available in this database yet. Run <code>sql/add_surveys_tables.sql</code> first.
              </div>
              <?php } ?>

              <!-- ═══ SURVEY EDITOR ═══ -->
              <?php if ($editorMode || $editSurvey) { ?>
              <div class="card mb-4">
                <div class="card-header">
                  <h5 class="mb-1"><?php echo $editSurvey ? 'Edit Survey' : 'Create New Survey'; ?></h5>
                  <p class="text-muted mb-0">Define the survey name, paste the questions JSON, set status and expiry.</p>
                </div>
                <div class="card-body">
                  <form method="post">
                    <input type="hidden" name="action" value="<?php echo $editSurvey ? 'update_survey' : 'create_survey'; ?>" />
                    <?php if ($editSurvey) { ?>
                    <input type="hidden" name="survey_id" value="<?php echo (int) $editSurvey['id']; ?>" />
                    <?php } ?>

                    <div class="row g-3 mb-3">
                      <div class="col-md-6">
                        <label class="form-label" for="surveyTitle">Survey Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="surveyTitle" name="title" required value="<?php echo htmlspecialchars((string) ($editSurvey['title'] ?? '')); ?>" placeholder="e.g. Student Feedback 2026" />
                      </div>
                      <div class="col-md-3">
                        <label class="form-label" for="surveyStatus">Status</label>
                        <select class="form-select" id="surveyStatus" name="status">
                          <?php foreach (ccSurveysStatuses() as $statusOpt) { ?>
                          <option value="<?php echo htmlspecialchars($statusOpt); ?>" <?php echo (($editSurvey['status'] ?? 'draft') === $statusOpt) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($statusOpt)); ?></option>
                          <?php } ?>
                        </select>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label" for="surveyExpiry">Expiry Date</label>
                        <input type="datetime-local" class="form-control" id="surveyExpiry" name="expiry_date" value="<?php echo htmlspecialchars(!empty($editSurvey['expiry_date']) ? date('Y-m-d\TH:i', strtotime($editSurvey['expiry_date'])) : ''); ?>" />
                      </div>
                    </div>

                    <div class="mb-3">
                      <label class="form-label" for="surveyDescription">Description</label>
                      <textarea class="form-control" id="surveyDescription" name="description" rows="2" placeholder="Optional description shown on the welcome screen"><?php echo htmlspecialchars((string) ($editSurvey['description'] ?? '')); ?></textarea>
                    </div>

                    <div class="mb-3">
                      <label class="form-label" for="surveyJson">Survey Questions JSON <span class="text-danger">*</span></label>
                      <textarea class="form-control json-editor" id="surveyJson" name="questions_json" required placeholder='Paste the full survey JSON here (with "questions" or "sections" array)...'><?php echo htmlspecialchars((string) ($editSurvey['questions_json'] ?? '')); ?></textarea>
                      <small class="text-muted">Paste the full JSON including <code>"title"</code>, <code>"description"</code>, and either <code>"questions"</code> or <code>"sections"</code>. See <code>_template.json</code> for format reference.</small>
                    </div>

                    <div class="mb-3">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="allowDuplicate" name="allow_duplicate_email" <?php echo (!empty($editSurvey['allow_duplicate_email'])) ? 'checked' : ''; ?> />
                        <label class="form-check-label" for="allowDuplicate">Allow duplicate email submissions</label>
                      </div>
                    </div>

                    <div class="d-flex gap-2">
                      <button type="submit" class="btn btn-primary"><?php echo $editSurvey ? 'Update Survey' : 'Create Survey'; ?></button>
                      <a href="surveys.php" class="btn btn-outline-secondary">Cancel</a>
                      <?php if ($editSurvey) { ?>
                      <button type="button" class="btn btn-outline-danger ms-auto" onclick="if(confirm('Delete this survey and all its responses? This cannot be undone.')){document.getElementById('deleteSurveyForm').submit();}">Delete Survey</button>
                      <?php } ?>
                    </div>
                  </form>

                  <?php if ($editSurvey) { ?>
                  <form id="deleteSurveyForm" method="post" class="d-none">
                    <input type="hidden" name="action" value="delete_survey" />
                    <input type="hidden" name="survey_id" value="<?php echo (int) $editSurvey['id']; ?>" />
                  </form>
                  <?php } ?>
                </div>
              </div>

              <?php } else { ?>

              <!-- ═══ GLOBAL STATS ═══ -->
              <div class="row g-4 mb-4">
                <?php
                  $statCards = [
                    ['label' => 'Total Surveys', 'value' => $globalStats['total_surveys'], 'icon' => 'bx bx-bar-chart-alt-2'],
                    ['label' => 'Published', 'value' => $globalStats['published_surveys'], 'icon' => 'bx bx-check-circle'],
                    ['label' => 'Total Responses', 'value' => $globalStats['total_responses'], 'icon' => 'bx bx-message-square-dots'],
                    ['label' => 'Today', 'value' => $globalStats['responses_today'], 'icon' => 'bx bx-calendar-check'],
                    ['label' => 'This Week', 'value' => $globalStats['responses_this_week'], 'icon' => 'bx bx-trending-up'],
                  ];
                  foreach ($statCards as $sc) { ?>
                <div class="col-xl col-md-4 col-sm-6">
                  <div class="card survey-stat-card h-100">
                    <div class="card-body">
                      <span class="label"><i class="<?php echo $sc['icon']; ?> me-1"></i><?php echo htmlspecialchars($sc['label']); ?></span>
                      <span class="value"><?php echo (int) $sc['value']; ?></span>
                    </div>
                  </div>
                </div>
                <?php } ?>
              </div>

              <!-- ═══ ACTION BAR ═══ -->
              <div class="d-flex justify-content-between align-items-center mb-4 gap-3 flex-wrap">
                <div class="d-flex gap-2 flex-wrap">
                  <a href="surveys.php?editor" class="btn btn-primary"><i class="bx bx-plus me-1"></i>New Survey</a>
                  <?php if ($selectedSurvey) { ?>
                  <a href="surveys.php?edit=<?php echo (int) $selectedSurvey['id']; ?>" class="btn btn-outline-primary"><i class="bx bx-edit me-1"></i>Edit Survey</a>
                  <a href="API/surveys/?admin=1&export=csv&survey_id=<?php echo (int) $selectedSurvey['id']; ?>" class="btn btn-outline-secondary" target="_blank"><i class="bx bx-download me-1"></i>Download CSV</a>
                  <?php } ?>
                </div>

                <!-- Survey selector -->
                <form method="get" class="d-flex gap-2">
                  <select name="survey" class="form-select" onchange="this.form.submit()" style="min-width: 260px;">
                    <option value="">— Select a survey —</option>
                    <?php foreach ($allSurveys as $s) { ?>
                    <option value="<?php echo (int) $s['id']; ?>" <?php echo $selectedSurveyId === (int) $s['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($s['title']); ?> (<?php echo (int) ($s['response_count'] ?? 0); ?> responses) [<?php echo htmlspecialchars(ucfirst($s['status'])); ?>]
                    </option>
                    <?php } ?>
                  </select>
                </form>
              </div>

              <?php if ($selectedSurvey) { ?>
              <!-- ═══ SURVEY-SPECIFIC STATS ═══ -->
              <div class="row g-4 mb-4">
                <?php
                  $surveyStatCards = [
                    ['label' => 'Total Responses', 'value' => $selectedStats['total']],
                    ['label' => 'Today', 'value' => $selectedStats['today']],
                    ['label' => 'This Week', 'value' => $selectedStats['this_week']],
                    ['label' => 'This Month', 'value' => $selectedStats['this_month']],
                  ];
                  foreach ($surveyStatCards as $ssc) { ?>
                <div class="col-xl-3 col-md-6">
                  <div class="card survey-stat-card h-100">
                    <div class="card-body">
                      <span class="label"><?php echo htmlspecialchars($ssc['label']); ?></span>
                      <span class="value"><?php echo (int) $ssc['value']; ?></span>
                    </div>
                  </div>
                </div>
                <?php } ?>
              </div>

              <!-- Survey info bar -->
              <div class="card mb-4">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                  <div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($selectedSurvey['title']); ?></h5>
                    <span class="badge bg-label-<?php echo ccSurveysStatusBadge($selectedSurvey['status']); ?>"><?php echo htmlspecialchars(ucfirst($selectedSurvey['status'])); ?></span>
                    <?php if (!empty($selectedSurvey['expiry_date'])) { ?>
                    <span class="text-muted ms-2 small"><i class="bx bx-time-five"></i> Expires: <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($selectedSurvey['expiry_date']))); ?></span>
                    <?php } ?>
                  </div>
                  <div class="ms-auto d-flex gap-2 flex-wrap">
                    <span class="survey-link-copy small" onclick="navigator.clipboard.writeText('https://nivasity.com/survey/<?php echo htmlspecialchars($selectedSurvey['slug']); ?>').then(()=>this.textContent='Copied!').catch(()=>{});" title="Click to copy survey link">
                      <i class="bx bx-link"></i> nivasity.com/survey/<?php echo htmlspecialchars($selectedSurvey['slug']); ?>
                    </span>
                    <!-- Quick status change -->
                    <?php if ($selectedSurvey['status'] === 'published') { ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="update_status" />
                      <input type="hidden" name="survey_id" value="<?php echo (int) $selectedSurvey['id']; ?>" />
                      <input type="hidden" name="status" value="closed" />
                      <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Close this survey? It will stop accepting responses.');">Close Survey</button>
                    </form>
                    <?php } elseif ($selectedSurvey['status'] === 'closed' || $selectedSurvey['status'] === 'draft') { ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="update_status" />
                      <input type="hidden" name="survey_id" value="<?php echo (int) $selectedSurvey['id']; ?>" />
                      <input type="hidden" name="status" value="published" />
                      <button type="submit" class="btn btn-sm btn-outline-success">Publish Survey</button>
                    </form>
                    <?php } ?>
                  </div>
                </div>
              </div>

              <!-- ═══ RESPONSES TABLE + DETAIL ═══ -->
              <div class="row g-4 align-items-start">
                <div class="<?php echo $selectedResponse ? 'col-xl-7' : 'col-12'; ?>">
                  <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-3">
                      <div>
                        <h5 class="mb-1">Responses</h5>
                        <p class="text-muted mb-0">Click a row to view detailed answers.</p>
                      </div>
                      <span class="badge bg-dark"><?php echo count($selectedResponses); ?></span>
                    </div>
                    <div class="card-body">
                      <div class="table-responsive text-nowrap">
                        <table class="table align-middle" id="responsesTable">
                          <thead class="table-light">
                            <tr>
                              <th>#</th>
                              <th>Name</th>
                              <th>Email</th>
                              <th>Phone</th>
                              <th>Submitted</th>
                              <th>Action</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php if (empty($selectedResponses)) { ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No responses yet.</td></tr>
                            <?php } ?>
                            <?php foreach ($selectedResponses as $idx => $resp) { ?>
                            <tr class="<?php echo $selectedResponseId === (int) $resp['id'] ? 'table-active' : ''; ?>">
                              <td><?php echo $idx + 1; ?></td>
                              <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars(($resp['first_name'] ?? '') . ' ' . ($resp['last_name'] ?? '')); ?></div>
                              </td>
                              <td><div class="small text-muted"><?php echo htmlspecialchars($resp['email'] ?? ''); ?></div></td>
                              <td><?php echo htmlspecialchars($resp['phone'] ?? '—'); ?></td>
                              <td>
                                <div><?php echo !empty($resp['created_at']) ? htmlspecialchars(date('d M Y', strtotime($resp['created_at']))) : '-'; ?></div>
                                <small class="text-muted"><?php echo !empty($resp['created_at']) ? htmlspecialchars(date('h:i A', strtotime($resp['created_at']))) : ''; ?></small>
                              </td>
                              <td>
                                <a href="surveys.php?survey=<?php echo $selectedSurveyId; ?>&response=<?php echo (int) $resp['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                              </td>
                            </tr>
                            <?php } ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>

                <?php if ($selectedResponse) { ?>
                <div class="col-xl-5">
                  <div class="card response-detail-card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-3">
                      <div>
                        <h5 class="mb-1">Response Detail</h5>
                        <p class="text-muted mb-0">Full answers from this respondent.</p>
                      </div>
                      <a href="surveys.php?survey=<?php echo $selectedSurveyId; ?>" class="btn btn-sm btn-outline-secondary"><i class="bx bx-x"></i></a>
                    </div>
                    <div class="card-body">
                      <!-- Contact info -->
                      <div class="detail-section pt-0 mt-0 border-0">
                        <div class="row g-3">
                          <div class="col-md-6">
                            <small class="text-muted d-block">Name</small>
                            <div class="fw-semibold"><?php echo htmlspecialchars(($selectedResponse['first_name'] ?? '') . ' ' . ($selectedResponse['last_name'] ?? '')); ?></div>
                          </div>
                          <div class="col-md-6">
                            <small class="text-muted d-block">Email</small>
                            <div><?php echo htmlspecialchars($selectedResponse['email'] ?? ''); ?></div>
                          </div>
                          <div class="col-md-6">
                            <small class="text-muted d-block">Phone</small>
                            <div><?php echo htmlspecialchars($selectedResponse['phone'] ?? '—'); ?></div>
                          </div>
                          <div class="col-md-6">
                            <small class="text-muted d-block">Submitted</small>
                            <div><?php echo !empty($selectedResponse['created_at']) ? htmlspecialchars(date('d M Y, h:i A', strtotime($selectedResponse['created_at']))) : '-'; ?></div>
                          </div>
                        </div>
                      </div>

                      <!-- Answers -->
                      <div class="detail-section">
                        <h6 class="mb-3">Answers</h6>
                        <?php
                          $detailAnswers = json_decode($selectedResponse['responses_json'] ?? '{}', true);
                          if (!is_array($detailAnswers)) $detailAnswers = [];

                          // Get question map from the joined survey data
                          $detailQMap = [];
                          if (!empty($selectedResponse['survey_questions_json'])) {
                            $dqJson = json_decode($selectedResponse['survey_questions_json'], true);
                            $detailQMap = ccSurveysExtractQuestionMap($dqJson ?: []);
                          } elseif (!empty($selectedQuestionMap)) {
                            $detailQMap = $selectedQuestionMap;
                          }

                          if (empty($detailAnswers)) { ?>
                            <div class="text-muted">No answers recorded.</div>
                          <?php } else { ?>
                            <div class="d-flex flex-column gap-3">
                              <?php foreach ($detailAnswers as $qId => $answer) {
                                $qLabel = $detailQMap[$qId] ?? $qId;
                                if (is_array($answer)) $answer = implode(', ', $answer);
                              ?>
                              <div>
                                <div class="fw-semibold mb-1"><?php echo htmlspecialchars((string) $qLabel); ?></div>
                                <div class="text-muted"><?php echo nl2br(htmlspecialchars((string) $answer)); ?></div>
                              </div>
                              <?php } ?>
                            </div>
                          <?php } ?>
                      </div>
                    </div>
                  </div>
                </div>
                <?php } ?>
              </div>

              <!-- ═══ AI CHAT ═══ -->
              <?php if ($selectedSurvey && count($selectedResponses) > 0) { ?>
              <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <div>
                    <h5 class="mb-1"><i class="bx bx-bot me-1"></i>AI Survey Analyst</h5>
                    <p class="text-muted mb-0">Ask questions about this survey's <?php echo count($selectedResponses); ?> responses. Powered by Gemini.</p>
                  </div>
                  <?php if (!$llmAvailable) { ?>
                  <span class="badge bg-label-warning">Not Configured</span>
                  <?php } else { ?>
                  <span class="badge bg-label-success">Ready</span>
                  <?php } ?>
                </div>
                <div class="card-body">
                  <?php if (!$llmAvailable) { ?>
                  <div class="alert alert-warning mb-0">
                    <i class="bx bx-info-circle me-1"></i>
                    To use the AI analyst, copy <code>config/llm.example.php</code> to <code>config/llm.php</code> and add your Gemini API key.
                  </div>
                  <?php } else { ?>
                  <div id="aiChatMessages" class="ai-chat-messages mb-3">
                    <div class="ai-msg ai-msg-bot">
                      <div class="ai-bubble">
                        <p>👋 Hi! I can analyze the <strong><?php echo count($selectedResponses); ?></strong> responses for "<strong><?php echo htmlspecialchars($selectedSurvey['title']); ?></strong>".</p>
                        <p>Try asking me things like:</p>
                        <ul class="mb-0">
                          <li>Summarize the key themes from responses</li>
                          <li>What are the most common answers for each question?</li>
                          <li>Show a breakdown of responses by category</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                  <form id="aiChatForm" class="d-flex gap-2" onsubmit="return sendAiChat(event);">
                    <input type="text" class="form-control" id="aiChatInput" placeholder="Ask about survey responses..." autocomplete="off" />
                    <button type="submit" class="btn btn-primary" id="aiChatBtn"><i class="bx bx-send"></i></button>
                  </form>
                  <?php } ?>
                </div>
              </div>
              <?php } ?>

              <?php } else { ?>
              <!-- No survey selected -->
              <?php if (empty($allSurveys)) { ?>
              <div class="card">
                <div class="card-body text-center py-5">
                  <i class="bx bx-bar-chart-alt-2 text-muted" style="font-size: 3rem;"></i>
                  <h5 class="mt-3">No Surveys Yet</h5>
                  <p class="text-muted">Create your first survey to start collecting feedback.</p>
                  <a href="surveys.php?editor" class="btn btn-primary"><i class="bx bx-plus me-1"></i>Create Survey</a>
                </div>
              </div>
              <?php } else { ?>
              <!-- Survey list table -->
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center gap-3">
                  <div>
                    <h5 class="mb-1">All Surveys</h5>
                    <p class="text-muted mb-0">Select a survey to view its responses.</p>
                  </div>
                  <a href="surveys.php?editor" class="btn btn-primary btn-sm"><i class="bx bx-plus me-1"></i>New Survey</a>
                </div>
                <div class="card-body">
                  <div class="table-responsive text-nowrap">
                    <table class="table align-middle" id="surveysListTable">
                      <thead class="table-light">
                        <tr>
                          <th>Title</th>
                          <th>Slug</th>
                          <th>Status</th>
                          <th>Responses</th>
                          <th>Expiry</th>
                          <th>Created</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($allSurveys as $s) { ?>
                        <tr>
                          <td class="fw-semibold"><?php echo htmlspecialchars($s['title']); ?></td>
                          <td><code><?php echo htmlspecialchars($s['slug']); ?></code></td>
                          <td><span class="badge bg-label-<?php echo ccSurveysStatusBadge($s['status']); ?>"><?php echo htmlspecialchars(ucfirst($s['status'])); ?></span></td>
                          <td><?php echo (int) ($s['response_count'] ?? 0); ?></td>
                          <td><?php echo !empty($s['expiry_date']) ? htmlspecialchars(date('d M Y', strtotime($s['expiry_date']))) : '—'; ?></td>
                          <td><?php echo !empty($s['created_at']) ? htmlspecialchars(date('d M Y', strtotime($s['created_at']))) : '-'; ?></td>
                          <td>
                            <div class="d-flex gap-1">
                              <a href="surveys.php?survey=<?php echo (int) $s['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                              <a href="surveys.php?edit=<?php echo (int) $s['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="bx bx-edit"></i></a>
                            </div>
                          </td>
                        </tr>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              <?php } ?>
              <?php } ?>

              <?php } /* end editor mode else */ ?>

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
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
      $(document).ready(function() {
        if ($('#responsesTable tbody tr').length > 1 || ($('#responsesTable tbody tr').length === 1 && !$('#responsesTable tbody tr td[colspan]').length)) {
          $('#responsesTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            language: { search: 'Search responses:' }
          });
        }
        if ($('#surveysListTable tbody tr').length > 0) {
          $('#surveysListTable').DataTable({
            order: [[5, 'desc']],
            pageLength: 25,
            language: { search: 'Search surveys:' }
          });
        }
      });

      // AI Chat
      const BEARER_TOKEN = <?php echo json_encode($bearerToken); ?>;
      const SURVEY_ID = <?php echo $selectedSurveyId; ?>;
      const API_BASE = 'API/surveys/';

      function sendAiChat(e) {
        e.preventDefault();
        const input = document.getElementById('aiChatInput');
        const question = input.value.trim();
        if (!question) return false;

        const messagesEl = document.getElementById('aiChatMessages');
        const btn = document.getElementById('aiChatBtn');

        // Add user message
        messagesEl.innerHTML += `<div class="ai-msg ai-msg-user"><div class="ai-bubble">${escapeHtml(question)}</div></div>`;
        messagesEl.innerHTML += `<div class="ai-msg ai-msg-bot" id="aiLoading"><div class="ai-bubble"><i class="bx bx-loader-alt bx-spin me-1"></i>Analyzing...</div></div>`;
        messagesEl.scrollTop = messagesEl.scrollHeight;
        input.value = '';
        btn.disabled = true;

        fetch(API_BASE + '?admin=1', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + BEARER_TOKEN
          },
          body: JSON.stringify({
            action: 'ai_chat',
            survey_id: SURVEY_ID,
            question: question
          })
        })
        .then(res => res.json())
        .then(data => {
          const loadingEl = document.getElementById('aiLoading');
          if (loadingEl) loadingEl.remove();

          if (data.success && data.data && data.data.answer) {
            const html = typeof marked !== 'undefined' ? marked.parse(data.data.answer) : escapeHtml(data.data.answer);
            messagesEl.innerHTML += `<div class="ai-msg ai-msg-bot"><div class="ai-bubble">${html}</div></div>`;
          } else {
            messagesEl.innerHTML += `<div class="ai-msg ai-msg-bot"><div class="ai-bubble text-danger">${escapeHtml(data.message || 'Something went wrong.')}</div></div>`;
          }
          messagesEl.scrollTop = messagesEl.scrollHeight;
          btn.disabled = false;
          input.focus();
        })
        .catch(err => {
          const loadingEl = document.getElementById('aiLoading');
          if (loadingEl) loadingEl.remove();
          messagesEl.innerHTML += `<div class="ai-msg ai-msg-bot"><div class="ai-bubble text-danger">Network error: ${escapeHtml(err.message)}</div></div>`;
          messagesEl.scrollTop = messagesEl.scrollHeight;
          btn.disabled = false;
        });

        return false;
      }

      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      }
    </script>
  </body>
</html>

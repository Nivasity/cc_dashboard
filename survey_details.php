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
  header('Content-Type: application/json'); // Set header for AJAX
  $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
  
  if (!$tablesReady) {
    if ($isAjax) {
      echo json_encode(['status' => 'error', 'message' => 'Survey tables are not available in this database yet. Run the migration first.']);
      exit();
    }
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
      if ($isAjax) {
        echo json_encode(['status' => 'success', 'message' => 'Survey created successfully.', 'redirect' => 'surveys.php?survey=' . $surveyId]);
        exit();
      }
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
      if ($isAjax) {
        echo json_encode(['status' => 'success', 'message' => 'Survey updated successfully.', 'redirect' => 'surveys.php?survey=' . $surveyId]);
        exit();
      }
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
      if ($isAjax) {
        echo json_encode(['status' => 'success', 'message' => 'Survey status updated to ' . ucfirst($status) . '.', 'redirect' => 'surveys.php?survey=' . $surveyId]);
        exit();
      }
      header('Location: surveys.php?survey=' . $surveyId);
      exit();
    }

    // Delete survey
    if ($action === 'delete_survey') {
      $surveyId = (int) ($_POST['survey_id'] ?? 0);
      if ($surveyId <= 0) throw new Exception('Invalid survey ID.');
      ccSurveysDelete($conn, $surveyId);
      ccSurveysSetFlash('success', 'Survey deleted successfully.');
      if ($isAjax) {
        echo json_encode(['status' => 'success', 'message' => 'Survey deleted successfully.', 'redirect' => 'surveys.php']);
        exit();
      }
      header('Location: surveys.php');
      exit();
    }

    throw new Exception('Unsupported action.');
  } catch (Throwable $e) {
    if ($isAjax) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      exit();
    }
    ccSurveysSetFlash('danger', $e->getMessage());
    header('Location: surveys.php' . (isset($surveyId) && $surveyId > 0 ? '?survey=' . $surveyId : ''));
    exit();
  }
}

$flash = ccSurveysGetFlash();

// Selected survey
$selectedSurveyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($selectedSurveyId <= 0) {
    header('Location: surveys.php');
    exit();
}
$selectedSurvey = ccSurveysFetchById($conn, $selectedSurveyId);
if (!$selectedSurvey) {
    ccSurveysSetFlash('danger', 'Survey not found.');
    header('Location: surveys.php');
    exit();
}

$selectedResponses = ccSurveysFetchResponses($conn, $selectedSurveyId);
$selectedStats = ccSurveysBuildStats($conn, $selectedSurveyId);
$selectedQuestionMap = [];
if ($selectedSurvey) {
  $qJson = json_decode($selectedSurvey['questions_json'] ?? '{}', true);
  $selectedQuestionMap = ccSurveysExtractQuestionMap($qJson ?: []);
}

// Selected response detail
$selectedResponseId = isset($_GET['response']) ? (int) $_GET['response'] : 0;
$selectedResponse = $selectedResponseId > 0 ? ccSurveysFetchResponseById($conn, $selectedResponseId) : null;

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
              <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Surveys /</span> <?php echo htmlspecialchars($selectedSurvey['title']); ?></h4>

              <?php if ($flash = ccSurveysGetFlash()) { ?>
              <div class="alert alert-<?php echo htmlspecialchars((string) ($flash['type'] ?? 'info')); ?> alert-dismissible" role="alert">
                <?php echo htmlspecialchars((string) ($flash['message'] ?? '')); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
              <?php } ?>

              <div class="mb-3">
                <a href="surveys.php" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Surveys</a>
              </div>

              <!-- ═══ TOP CARDS ═══ -->
              <div class="row g-4 mb-4">
                <!-- Left Column: Survey Info -->
                <div class="col-lg-6">
                  <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                      <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                          <h5 class="mb-1"><?php echo htmlspecialchars($selectedSurvey['title']); ?></h5>
                          <span class="badge bg-label-<?php echo ccSurveysStatusBadge($selectedSurvey['status']); ?>"><?php echo htmlspecialchars(ucfirst($selectedSurvey['status'])); ?></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="downloadCsv(event, <?php echo (int) $selectedSurvey['id']; ?>)"><i class="bx bx-download me-1"></i>CSV</button>
                      </div>
                      
                      <div class="mb-3">
                        <span class="survey-link-copy small" onclick="navigator.clipboard.writeText('https://nivasity.com/survey/<?php echo htmlspecialchars($selectedSurvey['slug']); ?>').then(()=>this.textContent='Copied!').catch(()=>{});" title="Click to copy survey link">
                          <i class="bx bx-link"></i> nivasity.com/survey/<?php echo htmlspecialchars($selectedSurvey['slug']); ?>
                        </span>
                        <?php if (!empty($selectedSurvey['expiry_date'])) { ?>
                        <div class="text-muted small mt-1"><i class="bx bx-time-five"></i> Expires: <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($selectedSurvey['expiry_date']))); ?></div>
                        <?php } ?>
                      </div>
                      
                      <div class="d-flex gap-2 flex-wrap mt-auto pt-3 border-top">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="editSurvey()"><i class="bx bx-edit me-1"></i>Edit Survey</button>
                        <?php if ($selectedSurvey['status'] === 'published') { ?>
                        <form method="post" class="d-inline ajax-form">
                          <input type="hidden" name="action" value="update_status" />
                          <input type="hidden" name="survey_id" value="<?php echo (int) $selectedSurvey['id']; ?>" />
                          <input type="hidden" name="status" value="closed" />
                          <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Close this survey?');">Close Survey</button>
                        </form>
                        <?php } elseif ($selectedSurvey['status'] === 'closed' || $selectedSurvey['status'] === 'draft') { ?>
                        <form method="post" class="d-inline ajax-form">
                          <input type="hidden" name="action" value="update_status" />
                          <input type="hidden" name="survey_id" value="<?php echo (int) $selectedSurvey['id']; ?>" />
                          <input type="hidden" name="status" value="published" />
                          <button type="submit" class="btn btn-sm btn-outline-success">Publish Survey</button>
                        </form>
                        <?php } ?>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Right Column: Analytics -->
                <div class="col-lg-6">
                  <div class="card h-100">
                    <div class="card-body">
                      <h6 class="mb-3 text-muted">Analytics</h6>
                      <div class="row g-3">
                        <?php
                          $surveyStatCards = [
                            ['label' => 'Total Responses', 'value' => $selectedStats['total']],
                            ['label' => 'Today', 'value' => $selectedStats['today']],
                            ['label' => 'This Week', 'value' => $selectedStats['this_week']],
                            ['label' => 'This Month', 'value' => $selectedStats['this_month']],
                          ];
                          foreach ($surveyStatCards as $ssc) { ?>
                        <div class="col-6">
                          <div class="survey-stat-card p-3 h-100 bg-lighter rounded text-center">
                            <span class="label mb-1"><?php echo htmlspecialchars($ssc['label']); ?></span>
                            <span class="value mb-0" style="font-size:1.5rem;"><?php echo (int) $ssc['value']; ?></span>
                          </div>
                        </div>
                        <?php } ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ═══ RESPONSES TABLE ═══ -->
              <div class="row g-4 align-items-start">
                <div class="col-12">
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
                              <th>Submitted</th>
                              <th>Action</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($selectedResponses as $idx => $resp) { ?>
                            <tr style="cursor: pointer;" onclick="openResponseModal(<?php echo $idx; ?>)">
                              <td><?php echo $idx + 1; ?></td>
                              <td><div class="fw-semibold"><?php echo htmlspecialchars(($resp['first_name'] ?? '') . ' ' . ($resp['last_name'] ?? '')); ?></div></td>
                              <td><div class="small text-muted"><?php echo htmlspecialchars($resp['email'] ?? ''); ?></div></td>
                              <td><?php echo !empty($resp['created_at']) ? htmlspecialchars(date('d M Y', strtotime($resp['created_at']))) : '-'; ?></td>
                              <td><button type="button" class="btn btn-sm btn-outline-primary" onclick="openResponseModal(<?php echo $idx; ?>); event.stopPropagation();">View</button></td>
                            </tr>
                            <?php } ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ═══ AI CHAT ═══ -->
              <?php if (count($selectedResponses) > 0) { ?>
              <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <div>
                    <h5 class="mb-1"><i class="bx bx-bot me-1"></i>AI Survey Analyst</h5>
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
                    To use the AI analyst, please configure <code>config/llm.php</code> with your Gemini API key.
                  </div>
                  <?php } else { ?>
                  <div id="aiChatMessages" class="ai-chat-messages mb-3">
                    <div class="ai-msg ai-msg-bot"><div class="ai-bubble">Ask me about this survey's responses!</div></div>
                  </div>
                  <form id="aiChatForm" class="d-flex gap-2" onsubmit="return sendAiChat(event);">
                    <input type="text" class="form-control" id="aiChatInput" placeholder="Ask question..." />
                    <button type="submit" class="btn btn-primary" id="aiChatBtn"><i class="bx bx-send"></i></button>
                  </form>
                  <?php } ?>
                </div>
              </div>
              <?php } ?>
            </div>
            <?php include('partials/_footer.php') ?>
            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <!-- ═══ SURVEY EDITOR MODAL ═══ -->
    <div class="modal fade" id="surveyEditorModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="surveyModalTitle">Edit Survey</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="post" class="ajax-form">
            <div class="modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
              <input type="hidden" name="action" value="update_survey" id="modalAction" />
              <input type="hidden" name="survey_id" value="<?php echo (int) $selectedSurvey['id']; ?>" id="modalSurveyId" />

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label" for="surveyTitle">Survey Title <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="surveyTitle" name="title" required value="<?php echo htmlspecialchars((string) ($selectedSurvey['title'] ?? '')); ?>" placeholder="e.g. Student Feedback 2026" />
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="surveyStatus">Status</label>
                  <select class="form-select" id="surveyStatus" name="status">
                    <?php foreach (ccSurveysStatuses() as $statusOpt) { ?>
                    <option value="<?php echo htmlspecialchars($statusOpt); ?>" <?php echo (($selectedSurvey['status'] ?? 'draft') === $statusOpt) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($statusOpt)); ?></option>
                    <?php } ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="surveyExpiry">Expiry Date</label>
                  <input type="datetime-local" class="form-control" id="surveyExpiry" name="expiry_date" value="<?php echo htmlspecialchars(!empty($selectedSurvey['expiry_date']) ? date('Y-m-d\TH:i', strtotime($selectedSurvey['expiry_date'])) : ''); ?>" />
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label" for="surveyDescription">Description</label>
                <textarea class="form-control" id="surveyDescription" name="description" rows="2" placeholder="Optional description shown on the welcome screen"><?php echo htmlspecialchars((string) ($selectedSurvey['description'] ?? '')); ?></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label" for="surveyJson">Survey Questions JSON <span class="text-danger">*</span></label>
                <textarea class="form-control json-editor" id="surveyJson" name="questions_json" required placeholder='Paste the full survey JSON here...'><?php echo htmlspecialchars((string) ($selectedSurvey['questions_json'] ?? '')); ?></textarea>
                <small class="text-muted d-block mt-1">Paste the full JSON including <code>"title"</code>, <code>"description"</code>, and either <code>"questions"</code> or <code>"sections"</code>.</small>
                <a href="assets/surveys/_template.json" download class="btn btn-sm btn-outline-info mt-2"><i class="bx bx-download me-1"></i>Download Template JSON</a>
              </div>

              <div class="mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="allowDuplicate" name="allow_duplicate_email" <?php echo (!empty($selectedSurvey['allow_duplicate_email'])) ? 'checked' : ''; ?> />
                  <label class="form-check-label" for="allowDuplicate">Allow duplicate email submissions</label>
                </div>
              </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger" onclick="if(confirm('Delete this survey and all its responses? This cannot be undone.')){document.getElementById('deleteSurveyForm').submit();}">Delete Survey</button>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Update Survey</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <form id="deleteSurveyForm" method="post" class="d-none ajax-form">
      <input type="hidden" name="action" value="delete_survey" />
      <input type="hidden" name="survey_id" id="deleteSurveyId" value="<?php echo (int) $selectedSurvey['id']; ?>" />
    </form>

    <!-- ═══ RESPONSE DETAILS MODAL ═══ -->
    <div class="modal fade" id="responseModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header d-flex justify-content-between align-items-center">
            <h5 class="modal-title" id="responseModalTitle">Response Details</h5>
            <div class="d-flex gap-2 align-items-center">
              <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" id="btnPrevResponse" onclick="navigateResponse(-1)"><i class="bx bx-chevron-left"></i></button>
              <span id="responseCounter" class="text-muted small"></span>
              <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" id="btnNextResponse" onclick="navigateResponse(1)"><i class="bx bx-chevron-right"></i></button>
              <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
          </div>
          <div class="modal-body">
            <!-- Contact Info -->
            <div class="row g-3 mb-4 pb-3 border-bottom">
              <div class="col-md-6">
                <small class="text-muted d-block">Name</small>
                <div class="fw-semibold" id="respName"></div>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Email</small>
                <div id="respEmail"></div>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Phone</small>
                <div id="respPhone"></div>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Submitted</small>
                <div id="respDate"></div>
              </div>
            </div>
            <!-- Answers -->
            <h6 class="mb-3">Answers</h6>
            <div id="respAnswers" class="d-flex flex-column gap-3"></div>
          </div>
        </div>
      </div>
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
            order: [[0, 'asc']],
            pageLength: 25,
            language: { search: 'Search responses:' }
          });
        }
      });

      // AJAX form submission handler
      $(document).on('submit', '.ajax-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        if (!$btn.length) {
          // If no submit button (e.g. triggered via JS), just use any primary button or a generic variable
          $btn = $('#modalSubmitBtn');
        }
        var originalText = $btn.html();
        if ($btn.length) {
          $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...');
        }

        $.ajax({
          url: $form.attr('action') || window.location.href,
          method: 'POST',
          data: $form.serialize(),
          dataType: 'json',
          success: function(res) {
            if (res.status === 'success') {
              if (res.redirect) {
                window.location.href = res.redirect;
              } else {
                window.location.reload();
              }
            } else {
              alert(res.message || 'An error occurred.');
              if ($btn.length) $btn.prop('disabled', false).html(originalText);
            }
          },
          error: function(xhr) {
            console.error('AJAX Error:', xhr.responseText);
            alert('Network error. Please try again.');
            if ($btn.length) $btn.prop('disabled', false).html(originalText);
          }
        });
      });



      const RESPONSES = <?php echo json_encode($selectedResponses); ?>;
      const QUESTION_MAP = <?php echo json_encode($selectedQuestionMap); ?>;

      let currentResponseIdx = -1;
      let responseModalInstance = null;

      function openResponseModal(idx) {
        if (idx < 0 || idx >= RESPONSES.length) return;
        currentResponseIdx = idx;
        const resp = RESPONSES[idx];
        
        document.getElementById('respName').textContent = (resp.first_name || '') + ' ' + (resp.last_name || '');
        document.getElementById('respEmail').textContent = resp.email || '';
        document.getElementById('respPhone').textContent = resp.phone || '—';
        
        const d = new Date(resp.created_at);
        document.getElementById('respDate').textContent = resp.created_at ? d.toLocaleString() : '-';

        const answersContainer = document.getElementById('respAnswers');
        answersContainer.innerHTML = '';
        
        let answers = {};
        try {
          answers = JSON.parse(resp.responses_json || '{}');
        } catch(e){}
        
        for (const [qId, answer] of Object.entries(answers)) {
          const qLabel = QUESTION_MAP[qId] || qId;
          const ansText = Array.isArray(answer) ? answer.join(', ') : answer;
          
          const div = document.createElement('div');
          div.innerHTML = `<div class="fw-semibold mb-1">${escapeHtml(qLabel)}</div><div class="text-muted">${escapeHtml(String(ansText)).replace(/\n/g, '<br>')}</div>`;
          answersContainer.appendChild(div);
        }
        
        if (Object.keys(answers).length === 0) {
          answersContainer.innerHTML = '<div class="text-muted">No answers recorded.</div>';
        }

        document.getElementById('responseCounter').textContent = `${idx + 1} of ${RESPONSES.length}`;
        document.getElementById('btnPrevResponse').disabled = (idx === 0);
        document.getElementById('btnNextResponse').disabled = (idx === RESPONSES.length - 1);
        
        if (!responseModalInstance) {
          responseModalInstance = new bootstrap.Modal(document.getElementById('responseModal'));
        }
        responseModalInstance.show();
      }

      function navigateResponse(dir) {
        const nextIdx = currentResponseIdx + dir;
        if (nextIdx >= 0 && nextIdx < RESPONSES.length) {
          openResponseModal(nextIdx);
        }
      }

      function editSurvey() {
        document.getElementById('surveyModalTitle').textContent = 'Edit Survey';
        document.getElementById('modalAction').value = 'update_survey';
        document.getElementById('surveyTitle').value = <?php echo json_encode((string) ($selectedSurvey['title'] ?? '')); ?>;
        document.getElementById('surveyStatus').value = <?php echo json_encode((string) ($selectedSurvey['status'] ?? 'draft')); ?>;
        
        let expiry = '';
        <?php if (!empty($selectedSurvey['expiry_date'])) { ?>
          const d = new Date(<?php echo json_encode($selectedSurvey['expiry_date']); ?>);
          const tzOffset = d.getTimezoneOffset() * 60000; 
          expiry = (new Date(d - tzOffset)).toISOString().slice(0, 16);
        <?php } ?>
        document.getElementById('surveyExpiry').value = expiry;
        
        document.getElementById('surveyDescription').value = <?php echo json_encode((string) ($selectedSurvey['description'] ?? '')); ?>;
        document.getElementById('surveyJson').value = <?php echo json_encode((string) ($selectedSurvey['questions_json'] ?? '')); ?>;
        document.getElementById('allowDuplicate').checked = <?php echo !empty($selectedSurvey['allow_duplicate_email']) ? 'true' : 'false'; ?>;
        document.getElementById('modalSubmitBtn').textContent = 'Update Survey';

        let surveyIdInput = document.getElementById('modalSurveyId');
        if (!surveyIdInput) {
          surveyIdInput = document.createElement('input');
          surveyIdInput.type = 'hidden';
          surveyIdInput.name = 'survey_id';
          surveyIdInput.id = 'modalSurveyId';
          document.querySelector('#surveyEditorModal form').appendChild(surveyIdInput);
        }
        surveyIdInput.value = <?php echo (int) $selectedSurvey['id']; ?>;

        const deleteBtn = document.querySelector('.modal-footer .btn-outline-danger');
        if (deleteBtn) {
          deleteBtn.style.display = 'block';
          deleteBtn.setAttribute('onclick', `if(confirm('Delete this survey and all its responses? This cannot be undone.')){ document.getElementById('deleteSurveyId').value = <?php echo (int) $selectedSurvey['id']; ?>; document.getElementById('deleteSurveyForm').submit(); }`);
        } else {
          const footer = document.querySelector('.modal-footer');
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'btn btn-outline-danger';
          btn.textContent = 'Delete Survey';
          btn.onclick = function() {
            if(confirm('Delete this survey and all its responses? This cannot be undone.')){ 
              document.getElementById('deleteSurveyId').value = <?php echo (int) $selectedSurvey['id']; ?>; 
              document.getElementById('deleteSurveyForm').submit(); 
            }
          };
          footer.insertBefore(btn, footer.firstChild);
        }

        const deleteForm = document.getElementById('deleteSurveyForm');
        if (!deleteForm) {
          const form = document.createElement('form');
          form.id = 'deleteSurveyForm';
          form.method = 'post';
          form.className = 'd-none ajax-form';
          form.innerHTML = `
            <input type="hidden" name="action" value="delete_survey" />
            <input type="hidden" name="survey_id" id="deleteSurveyId" value="<?php echo (int) $selectedSurvey['id']; ?>" />
          `;
          document.body.appendChild(form);
        } else {
          document.getElementById('deleteSurveyId').value = <?php echo (int) $selectedSurvey['id']; ?>;
        }

        const myModal = new bootstrap.Modal(document.getElementById('surveyEditorModal'));
        myModal.show();
      }

      function downloadCsv(e, surveyId) {
        e.preventDefault();
        const btn = e.currentTarget;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i>';
        btn.classList.add('disabled');
        
        fetch('API/surveys/?admin=1&export=csv&survey_id=' + surveyId, {
          method: 'GET',
          headers: {
            'Authorization': 'Bearer ' + BEARER_TOKEN
          }
        })
        .then(res => {
          if (!res.ok) throw new Error('Network response was not ok');
          return res.blob();
        })
        .then(blob => {
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `survey_${surveyId}_responses.csv`;
          document.body.appendChild(a);
          a.click();
          a.remove();
          window.URL.revokeObjectURL(url);
          btn.innerHTML = originalHtml;
          btn.classList.remove('disabled');
        })
        .catch(err => {
          console.error(err);
          alert('Failed to download CSV');
          btn.innerHTML = originalHtml;
          btn.classList.remove('disabled');
        });
      }

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

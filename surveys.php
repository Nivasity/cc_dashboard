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
$allSurveys = $tablesReady ? ccSurveysFetchAll($conn) : [];
$globalStats = $tablesReady ? ccSurveysBuildGlobalStats($conn) : ['total_surveys' => 0, 'published_surveys' => 0, 'total_responses' => 0, 'responses_today' => 0, 'responses_this_week' => 0];

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

              <!-- Survey list table -->
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center gap-3">
                  <div>
                    <h5 class="mb-1">All Surveys</h5>
                    <p class="text-muted mb-0">Select a survey to view its responses.</p>
                  </div>
                  <button type="button" class="btn btn-primary btn-sm" onclick="resetSurveyModal()" data-bs-toggle="modal" data-bs-target="#surveyEditorModal"><i class="bx bx-plus me-1"></i>New Survey</button>
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
                        <tr style="cursor: pointer;" onclick="window.location='survey_details.php?id=<?php echo (int) $s['id']; ?>';">
                          <td class="fw-semibold"><?php echo htmlspecialchars($s['title']); ?></td>
                          <td><code><?php echo htmlspecialchars($s['slug']); ?></code></td>
                          <td><span class="badge bg-label-<?php echo ccSurveysStatusBadge($s['status']); ?>"><?php echo htmlspecialchars(ucfirst($s['status'])); ?></span></td>
                          <td><?php echo (int) ($s['response_count'] ?? 0); ?></td>
                          <td><?php echo !empty($s['expiry_date']) ? htmlspecialchars(date('d M Y', strtotime($s['expiry_date']))) : '—'; ?></td>
                          <td><?php echo !empty($s['created_at']) ? htmlspecialchars(date('d M Y', strtotime($s['created_at']))) : '-'; ?></td>
                          <td>
                            <div class="d-flex gap-1">
                              <a href="survey_details.php?id=<?php echo (int) $s['id']; ?>" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();">View</a>
                              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="editSurvey(<?php echo (int) $s['id']; ?>); event.stopPropagation();"><i class="bx bx-edit"></i></button>
                            </div>
                          </td>
                        </tr>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- Floating + Button -->
              <button type="button" class="btn btn-primary new_formBtn" onclick="resetSurveyModal()" data-bs-toggle="modal" data-bs-target="#surveyEditorModal" aria-label="Create new survey">
                <i class='bx bx-plus fs-3'></i>
              </button>

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
            <h5 class="modal-title" id="surveyModalTitle"><?php echo $editSurvey ? 'Edit Survey' : 'Create New Survey'; ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="post" class="ajax-form">
            <div class="modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
              <input type="hidden" name="action" value="<?php echo $editSurvey ? 'update_survey' : 'create_survey'; ?>" id="modalAction" />
              <?php if ($editSurvey) { ?>
              <input type="hidden" name="survey_id" value="<?php echo (int) $editSurvey['id']; ?>" id="modalSurveyId" />
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
                <textarea class="form-control json-editor" id="surveyJson" name="questions_json" required placeholder='Paste the full survey JSON here...'><?php echo htmlspecialchars((string) ($editSurvey['questions_json'] ?? '')); ?></textarea>
                <small class="text-muted d-block mt-1">Paste the full JSON including <code>"title"</code>, <code>"description"</code>, and either <code>"questions"</code> or <code>"sections"</code>.</small>
                <a href="assets/surveys/_template.json" download class="btn btn-sm btn-outline-info mt-2"><i class="bx bx-download me-1"></i>Download Template JSON</a>
              </div>

              <div class="mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="allowDuplicate" name="allow_duplicate_email" <?php echo (!empty($editSurvey['allow_duplicate_email'])) ? 'checked' : ''; ?> />
                  <label class="form-check-label" for="allowDuplicate">Allow duplicate email submissions</label>
                </div>
              </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
              <?php if ($editSurvey) { ?>
                <button type="button" class="btn btn-outline-danger" onclick="if(confirm('Delete this survey and all its responses? This cannot be undone.')){document.getElementById('deleteSurveyForm').submit();}">Delete Survey</button>
              <?php } else { ?>
                <div></div>
              <?php } ?>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="modalSubmitBtn"><?php echo $editSurvey ? 'Update Survey' : 'Create Survey'; ?></button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php if ($editSurvey) { ?>
    <form id="deleteSurveyForm" method="post" class="d-none ajax-form">
      <input type="hidden" name="action" value="delete_survey" />
      <input type="hidden" name="survey_id" value="<?php echo (int) $editSurvey['id']; ?>" />
    </form>
    <?php } ?>

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

        <?php if ($editorMode || $editSurvey) { ?>
        // Auto-open modal if we are in editor mode via URL
        var myModal = new bootstrap.Modal(document.getElementById('surveyEditorModal'));
        myModal.show();
        <?php } ?>
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

      function resetSurveyModal() {
        document.getElementById('surveyModalTitle').textContent = 'Create New Survey';
        document.getElementById('modalAction').value = 'create_survey';
        document.getElementById('surveyTitle').value = '';
        document.getElementById('surveyStatus').value = 'draft';
        document.getElementById('surveyExpiry').value = '';
        document.getElementById('surveyDescription').value = '';
        document.getElementById('surveyJson').value = '';
        document.getElementById('allowDuplicate').checked = false;
        document.getElementById('modalSubmitBtn').textContent = 'Create Survey';

        // Remove hidden survey_id input if it exists
        const surveyIdInput = document.getElementById('modalSurveyId');
        if (surveyIdInput) {
          surveyIdInput.remove();
        }
        
        // Remove the delete button if it exists
        const deleteBtn = document.querySelector('.modal-footer .btn-outline-danger');
        if (deleteBtn) {
          deleteBtn.style.display = 'none';
        }
      }

      }

      const ALL_SURVEYS = <?php echo json_encode($allSurveys, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;

      function editSurvey(id) {
        const survey = ALL_SURVEYS.find(s => parseInt(s.id) === parseInt(id));
        if (!survey) return;

        document.getElementById('surveyModalTitle').textContent = 'Edit Survey';
        document.getElementById('modalAction').value = 'update_survey';
        document.getElementById('surveyTitle').value = survey.title || '';
        document.getElementById('surveyStatus').value = survey.status || 'draft';
        
        let expiry = '';
        if (survey.expiry_date) {
          // Format as YYYY-MM-DDThh:mm
          const d = new Date(survey.expiry_date);
          const tzOffset = d.getTimezoneOffset() * 60000; 
          const localISOTime = (new Date(d - tzOffset)).toISOString().slice(0, 16);
          expiry = localISOTime;
        }
        document.getElementById('surveyExpiry').value = expiry;
        
        document.getElementById('surveyDescription').value = survey.description || '';
        document.getElementById('surveyJson').value = survey.questions_json || '';
        document.getElementById('allowDuplicate').checked = !!survey.allow_duplicate_email;
        document.getElementById('modalSubmitBtn').textContent = 'Update Survey';

        let surveyIdInput = document.getElementById('modalSurveyId');
        if (!surveyIdInput) {
          surveyIdInput = document.createElement('input');
          surveyIdInput.type = 'hidden';
          surveyIdInput.name = 'survey_id';
          surveyIdInput.id = 'modalSurveyId';
          document.querySelector('#surveyEditorModal form').appendChild(surveyIdInput);
        }
        surveyIdInput.value = survey.id;

        const deleteBtn = document.querySelector('.modal-footer .btn-outline-danger');
        if (deleteBtn) {
          deleteBtn.style.display = 'block';
          deleteBtn.setAttribute('onclick', `if(confirm('Delete this survey and all its responses? This cannot be undone.')){ document.getElementById('deleteSurveyId').value = ${survey.id}; document.getElementById('deleteSurveyForm').submit(); }`);
        } else {
          // If the button doesn't exist (because the page loaded with no editSurvey), we should create it or just submit directly
          const footer = document.querySelector('.modal-footer');
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'btn btn-outline-danger';
          btn.textContent = 'Delete Survey';
          btn.onclick = function() {
            if(confirm('Delete this survey and all its responses? This cannot be undone.')){ 
              document.getElementById('deleteSurveyId').value = survey.id; 
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
            <input type="hidden" name="survey_id" id="deleteSurveyId" value="${survey.id}" />
          `;
          document.body.appendChild(form);
        } else {
          document.getElementById('deleteSurveyId').value = survey.id;
        }

        const myModal = new bootstrap.Modal(document.getElementById('surveyEditorModal'));
        myModal.show();
      }
    </script>
  </body>
</html>

<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;
if (!in_array($admin_role, [1, 2, 4], true)) {
  header('Location: index.php');
  exit();
}

$schools = [];
$schools_query = mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active' ORDER BY name");
if ($schools_query) {
  while ($school = mysqli_fetch_assoc($schools_query)) {
    $schools[] = $school;
  }
}

$initial_school_id = isset($schools[0]['id']) ? (int) $schools[0]['id'] : 0;
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>School Settlements | Nivasity Command Center</title>
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
              <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 py-3 mb-4">
                <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Payments /</span> School Settlements</h4>
                <button type="button" id="refreshSettlementSnapshot" class="btn btn-outline-secondary">
                  Refresh Snapshot
                </button>
              </div>

              <div id="settlementPageAlert" class="alert d-none" role="alert"></div>

              <div class="card mb-4">
                <div class="card-body">
                  <form id="stageSettlementForm" class="row g-3">
                    <div class="col-md-4">
                      <label for="settlementSchool" class="form-label">School</label>
                      <select id="settlementSchool" name="school_id" class="form-select">
                        <?php if (empty($schools)) { ?>
                          <option value="0">No active schools</option>
                        <?php } else { ?>
                          <?php foreach ($schools as $school) { ?>
                            <option value="<?php echo (int) $school['id']; ?>" <?php echo (int) $school['id'] === $initial_school_id ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars($school['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                          <?php } ?>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label for="settlementScheduledFor" class="form-label">Scheduled For</label>
                      <input type="date" id="settlementScheduledFor" name="scheduled_for" class="form-control" value="<?php echo date('Y-m-d'); ?>" />
                    </div>
                    <div class="col-md-5">
                      <label for="settlementStageNotes" class="form-label">Stage Notes</label>
                      <input type="text" id="settlementStageNotes" name="notes" class="form-control" placeholder="Optional note for this manual transfer batch" />
                    </div>
                    <div class="col-12 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                      <div class="text-muted small" id="stageSettlementHint">Select a school to view settlement readiness.</div>
                      <button type="submit" class="btn btn-primary" id="stageSettlementBtn" disabled>Stage Settlement Batch</button>
                    </div>
                  </form>
                </div>
              </div>

              <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-2">Settlement Account</span>
                      <div id="settlementAccountSummary" class="text-muted">No school selected.</div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-2">School Wallet</span>
                      <div id="settlementWalletSummary" class="text-muted">No snapshot loaded.</div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-4 col-md-12 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <span class="fw-semibold d-block mb-2">Settlement Readiness</span>
                      <div id="settlementReadinessSummary" class="text-muted">No snapshot loaded.</div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card mb-4 d-none" id="activeBatchCard">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between gap-3">
                  <div>
                    <h5 class="mb-1">Active Settlement Batch</h5>
                    <small class="text-muted" id="activeBatchMeta">Pending batch details</small>
                  </div>
                  <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success d-none" id="completeActiveBatchBtn">Complete Batch</button>
                    <button type="button" class="btn btn-outline-danger d-none" id="failActiveBatchBtn">Mark Failed</button>
                  </div>
                </div>
                <div class="card-body">
                  <div id="activeBatchSummary" class="mb-3"></div>
                  <div class="table-responsive text-nowrap">
                    <table class="table table-sm">
                      <thead class="table-light">
                        <tr>
                          <th>Source Ref</th>
                          <th>Allocated</th>
                          <th>Current Outstanding</th>
                          <th>Ledger Status</th>
                          <th>Item Status</th>
                          <th>Created</th>
                        </tr>
                      </thead>
                      <tbody id="activeBatchItemsTable"></tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="card mb-4">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between gap-3">
                  <div>
                    <h5 class="mb-1">Next Settlement Preview</h5>
                    <small class="text-muted">Rows shown here are staged from <code>school_payable_ledger</code> outstanding balances, not raw purchase totals.</small>
                  </div>
                  <div class="text-muted small" id="previewSummaryText">No preview loaded.</div>
                </div>
                <div class="card-body">
                  <div class="table-responsive text-nowrap">
                    <table class="table table-sm" id="settlementPreviewDataTable">
                      <thead class="table-light">
                        <tr>
                          <th>Source Ref</th>
                          <th>Outstanding</th>
                          <th>To Allocate</th>
                          <th>Ledger Status</th>
                          <th>Created</th>
                        </tr>
                      </thead>
                      <tbody id="settlementPreviewTable"></tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="card mb-4">
                <div class="card-header">
                  <h5 class="mb-0">Recent Settlement Batches</h5>
                </div>
                <div class="card-body">
                  <div class="table-responsive text-nowrap">
                    <table class="table table-sm">
                      <thead class="table-light">
                        <tr>
                          <th>Batch Ref</th>
                          <th>Scheduled</th>
                          <th>Status</th>
                          <th>Total</th>
                          <th>Records</th>
                          <th>Provider</th>
                          <th>Provider Ref</th>
                          <th>Created</th>
                          <th>Finished</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody id="recentSettlementBatchesTable"></tbody>
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

    <div class="modal fade" id="completeSettlementModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Complete Settlement Batch</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="completeSettlementForm">
            <div class="modal-body">
              <input type="hidden" id="completeSettlementBatchId" name="batch_id" value="0" />
              <div class="mb-3">
                <label for="completeSettlementProviderReference" class="form-label">Paystack Transfer Reference</label>
                <input type="text" id="completeSettlementProviderReference" name="provider_reference" class="form-control" required />
                <div class="form-text">This value is verified against Paystack before the batch can be completed, then stored in <code>settlement_batches.provider_reference</code>.</div>
              </div>
              <div class="mb-3">
                <label for="completeSettlementNotes" class="form-label">Completion Notes</label>
                <textarea id="completeSettlementNotes" name="notes" class="form-control" rows="3" placeholder="Optional note about the completed transfer"></textarea>
              </div>
              <div class="alert alert-warning mb-0">
                Completion verifies the Paystack transfer reference, re-checks current outstanding amounts for every staged ledger row, and blocks the batch if either check fails.
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-success">Confirm Completion</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="failSettlementModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Fail or Cancel Settlement Batch</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="failSettlementForm">
            <div class="modal-body">
              <input type="hidden" id="failSettlementBatchId" name="batch_id" value="0" />
              <div class="mb-3">
                <label for="failSettlementReason" class="form-label">Reason</label>
                <textarea id="failSettlementReason" name="reason" class="form-control" rows="3" placeholder="Explain why this batch should be released"></textarea>
              </div>
              <div class="alert alert-secondary mb-0">
                Failing a pending batch only releases the reservation. It does not change wallet or ledger balances because nothing has been settled yet.
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger">Mark Failed</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="assets/vendor/libs/jquery/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/vendor/libs/popper/popper.min.js"></script>
    <script src="assets/vendor/js/menu.min.js"></script>
    <script src="assets/js/ui-toasts.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
      var initialSettlementSchoolId = <?php echo $initial_school_id; ?>;
      var activeSettlementBatchId = 0;
      var settlementPreviewDataTable = null;

      function settlementMoney(value) {
        return '₦ ' + Number(value || 0).toLocaleString();
      }

      function settlementEscapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
      }

      function settlementBadgeClass(status) {
        switch ((status || '').toLowerCase()) {
          case 'completed':
          case 'settled':
            return 'success';
          case 'pending':
          case 'processing':
          case 'partially_settled':
            return 'warning';
          case 'failed':
          case 'reversed':
            return 'danger';
          case 'carry_forward':
            return 'info';
          default:
            return 'secondary';
        }
      }

      function settlementShowToast(status, message) {
        if (typeof showToast === 'function') {
          showToast(status === 'success' ? 'bg-success' : 'bg-danger', message || 'Done');
        }
      }

      function settlementSetPageAlert(type, message) {
        var $alert = $('#settlementPageAlert');
        if (!message) {
          $alert.addClass('d-none').removeClass('alert-success alert-danger alert-warning alert-info').text('');
          return;
        }

        $alert.removeClass('d-none alert-success alert-danger alert-warning alert-info')
          .addClass('alert-' + type)
          .html(message);
      }

      function resetSettlementPreviewDataTable() {
        if (settlementPreviewDataTable) {
          settlementPreviewDataTable.destroy();
          settlementPreviewDataTable = null;
        }
      }

      function initializeSettlementPreviewDataTable() {
        resetSettlementPreviewDataTable();
        settlementPreviewDataTable = new DataTable('#settlementPreviewDataTable', {
          order: [[4, 'desc']],
          pageLength: 10,
          language: {
            emptyTable: 'No stageable ledger rows to preview.'
          }
        });
      }

      function settlementBatchActionButtons(batch) {
        var status = (batch.status || '').toLowerCase();
        if (!(status === 'pending' || status === 'processing')) {
          return '<span class="text-muted">-</span>';
        }

        return '' +
          '<div class="d-flex gap-2">' +
            '<button type="button" class="btn btn-sm btn-success settlement-complete-btn" data-batch-id="' + Number(batch.id || 0) + '">Complete</button>' +
            '<button type="button" class="btn btn-sm btn-outline-danger settlement-fail-btn" data-batch-id="' + Number(batch.id || 0) + '">Fail</button>' +
          '</div>';
      }

      function renderSettlementAccount(account) {
        var html = '';
        if (!account) {
          html = '<span class="text-danger">No active school settlement account found.</span>';
        } else {
          html = '' +
            '<div class="fw-semibold">' + settlementEscapeHtml(account.acct_name || 'N/A') + '</div>' +
            '<div>' + settlementEscapeHtml(account.bank || 'N/A') + ' / ' + settlementEscapeHtml(account.acct_number || 'N/A') + '</div>' +
            '<div class="text-muted small mt-1">Gateway: ' + settlementEscapeHtml(account.gateway || 'paystack') + '</div>' +
            '<div class="text-muted small">Subaccount: ' + settlementEscapeHtml(account.subaccount_code || 'N/A') + '</div>';
        }
        $('#settlementAccountSummary').html(html);
      }

      function renderSettlementWallet(wallet) {
        wallet = wallet || {};
        var html = '' +
          '<div class="mb-1">Current Balance: <span class="fw-semibold">' + settlementMoney(wallet.current_balance) + '</span></div>' +
          '<div class="mb-1">Pending Payout: <span class="fw-semibold">' + settlementMoney(wallet.pending_payout_balance) + '</span></div>' +
          '<div class="mb-1">Carry Forward: <span class="fw-semibold">' + settlementMoney(wallet.carry_forward_balance) + '</span></div>' +
          '<div class="text-muted small">Wallet Status: ' + settlementEscapeHtml(wallet.status || 'unknown') + '</div>';
        $('#settlementWalletSummary').html(html);
      }

      function renderSettlementReadiness(summary, school) {
        summary = summary || {};
        var html = '' +
          '<div class="mb-1">School: <span class="fw-semibold">' + settlementEscapeHtml((school || {}).name || 'N/A') + '</span></div>' +
          '<div class="mb-1">All Outstanding: <span class="fw-semibold">' + settlementMoney(summary.all_outstanding_amount) + '</span> across ' + Number(summary.all_outstanding_records || 0) + ' rows</div>' +
          '<div class="mb-1">Stageable Now: <span class="fw-semibold">' + settlementMoney(summary.stageable_amount) + '</span> across ' + Number(summary.stageable_records || 0) + ' rows</div>' +
          '<div class="text-muted small">Per-school cap: ' + settlementMoney(summary.cap_per_school) + '</div>';

        if (Number(summary.has_active_batch || 0) === 1) {
          html += '<div class="text-warning small mt-2">A pending settlement batch exists for this school, so no new batch can be staged until that one is completed or failed.</div>';
        }

        $('#settlementReadinessSummary').html(html);
      }

      function renderSettlementPreview(preview) {
        preview = preview || { items: [], total_amount: 0, total_records: 0 };
        resetSettlementPreviewDataTable();
        var rows = [];
        $.each(preview.items || [], function (_, item) {
          rows.push(
            '<tr>' +
              '<td class="fw-semibold">' + settlementEscapeHtml(item.source_ref_id) + '</td>' +
              '<td>' + settlementMoney(item.outstanding_amount) + '</td>' +
              '<td>' + settlementMoney(item.allocated_amount) + '</td>' +
              '<td><span class="badge bg-label-' + settlementBadgeClass(item.status) + '">' + settlementEscapeHtml(item.status || 'pending') + '</span></td>' +
              '<td>' + settlementEscapeHtml(item.created_at || '') + '</td>' +
            '</tr>'
          );
        });

        if (!rows.length) {
          rows.push('<tr><td colspan="5" class="text-center text-muted py-4">No stageable ledger rows to preview.</td></tr>');
        }

        $('#settlementPreviewTable').html(rows.join(''));
        $('#previewSummaryText').text(
          'Preview total: ' + settlementMoney(preview.total_amount || 0) + ' across ' + Number(preview.total_records || 0) + ' rows'
        );
        initializeSettlementPreviewDataTable();
      }

      function renderActiveBatch(batch) {
        var $card = $('#activeBatchCard');
        var $completeBtn = $('#completeActiveBatchBtn');
        var $failBtn = $('#failActiveBatchBtn');

        activeSettlementBatchId = batch && batch.id ? Number(batch.id) : 0;
        if (!batch || !batch.id) {
          $card.addClass('d-none');
          $('#activeBatchSummary').empty();
          $('#activeBatchItemsTable').empty();
          $completeBtn.addClass('d-none');
          $failBtn.addClass('d-none');
          return;
        }

        var status = (batch.status || '').toLowerCase();
        var canAct = status === 'pending' || status === 'processing';
        $('#activeBatchMeta').text((batch.batch_reference || '') + ' • ' + settlementMoney(batch.total_amount || 0) + ' • ' + Number(batch.total_records || 0) + ' rows');

        $('#activeBatchSummary').html(
          '<div class="row g-3">' +
            '<div class="col-md-4"><div class="small text-muted">Status</div><div><span class="badge bg-label-' + settlementBadgeClass(batch.status) + '">' + settlementEscapeHtml(batch.status || '') + '</span></div></div>' +
            '<div class="col-md-4"><div class="small text-muted">Provider</div><div>' + settlementEscapeHtml(batch.transfer_provider || 'paystack') + '</div></div>' +
            '<div class="col-md-4"><div class="small text-muted">Provider Ref</div><div>' + settlementEscapeHtml(batch.provider_reference || 'Pending manual confirmation') + '</div></div>' +
            '<div class="col-md-6"><div class="small text-muted">Scheduled For</div><div>' + settlementEscapeHtml(batch.scheduled_for || '') + '</div></div>' +
            '<div class="col-md-6"><div class="small text-muted">Notes</div><div>' + settlementEscapeHtml(batch.notes || 'No notes') + '</div></div>' +
          '</div>'
        );

        var rows = [];
        $.each(batch.items || [], function (_, item) {
          var rowClass = Number(item.over_allocated || 0) === 1 ? 'table-danger' : '';
          rows.push(
            '<tr class="' + rowClass + '">' +
              '<td class="fw-semibold">' + settlementEscapeHtml(item.source_ref_id || '') + '</td>' +
              '<td>' + settlementMoney(item.allocated_amount) + '</td>' +
              '<td>' + settlementMoney(item.current_outstanding) + '</td>' +
              '<td><span class="badge bg-label-' + settlementBadgeClass(item.ledger_status) + '">' + settlementEscapeHtml(item.ledger_status || '') + '</span></td>' +
              '<td><span class="badge bg-label-' + settlementBadgeClass(item.status) + '">' + settlementEscapeHtml(item.status || '') + '</span></td>' +
              '<td>' + settlementEscapeHtml(item.ledger_created_at || item.created_at || '') + '</td>' +
            '</tr>'
          );
        });

        if (!rows.length) {
          rows.push('<tr><td colspan="6" class="text-center text-muted py-4">No items found for this batch.</td></tr>');
        }

        $('#activeBatchItemsTable').html(rows.join(''));
        $completeBtn.toggleClass('d-none', !canAct).attr('data-batch-id', activeSettlementBatchId);
        $failBtn.toggleClass('d-none', !canAct).attr('data-batch-id', activeSettlementBatchId);
        $card.removeClass('d-none');
      }

      function renderRecentBatches(batches) {
        var rows = [];
        $.each(batches || [], function (_, batch) {
          rows.push(
            '<tr>' +
              '<td class="fw-semibold">' + settlementEscapeHtml(batch.batch_reference || '') + '</td>' +
              '<td>' + settlementEscapeHtml(batch.scheduled_for || '') + '</td>' +
              '<td><span class="badge bg-label-' + settlementBadgeClass(batch.status) + '">' + settlementEscapeHtml(batch.status || '') + '</span></td>' +
              '<td>' + settlementMoney(batch.total_amount) + '</td>' +
              '<td>' + Number(batch.total_records || batch.item_count || 0) + '</td>' +
              '<td>' + settlementEscapeHtml(batch.transfer_provider || '') + '</td>' +
              '<td>' + settlementEscapeHtml(batch.provider_reference || '-') + '</td>' +
              '<td>' + settlementEscapeHtml(batch.created_at || '') + '</td>' +
              '<td>' + settlementEscapeHtml(batch.completed_at || batch.failed_at || '-') + '</td>' +
              '<td>' + settlementBatchActionButtons(batch) + '</td>' +
            '</tr>'
          );
        });

        if (!rows.length) {
          rows.push('<tr><td colspan="10" class="text-center text-muted py-4">No settlement batches found for this school.</td></tr>');
        }

        $('#recentSettlementBatchesTable').html(rows.join(''));
      }

      function updateStageButton(snapshot) {
        var summary = snapshot.summary || {};
        var wallet = snapshot.wallet || {};
        var hasAccount = !!snapshot.settlement_account;
        var hasActiveBatch = Number(summary.has_active_batch || 0) === 1;
        var canStage = hasAccount && !hasActiveBatch && Number(summary.stageable_amount || 0) > 0 && Number(wallet.pending_payout_balance || 0) > 0;
        $('#stageSettlementBtn').prop('disabled', !canStage);

        var hint = 'Settlement staging uses outstanding amounts from school_payable_ledger.';
        if (!hasAccount) {
          hint = 'This school is missing an active settlement account.';
        } else if (hasActiveBatch) {
          hint = 'A pending settlement batch already exists for this school.';
        } else if (Number(summary.stageable_amount || 0) <= 0) {
          hint = 'There are no outstanding ledger rows eligible for settlement staging.';
        }

        $('#stageSettlementHint').text(hint);
      }

      function loadSettlementSnapshot() {
        var schoolId = Number($('#settlementSchool').val() || 0);
        if (schoolId <= 0) {
          settlementSetPageAlert('warning', 'No active school is available for settlement review.');
          return;
        }

        settlementSetPageAlert(null, '');
        $.ajax({
          url: 'model/school_settlements.php',
          method: 'GET',
          dataType: 'json',
          data: {
            action: 'snapshot',
            school_id: schoolId
          },
          success: function (response) {
            if ((response.status || '') !== 'success') {
              renderSettlementAccount(null);
              renderSettlementWallet({});
              renderSettlementReadiness({}, {});
              renderActiveBatch(null);
              renderSettlementPreview({ items: [] });
              renderRecentBatches([]);
              updateStageButton({ summary: {}, wallet: {} });
              settlementSetPageAlert('warning', settlementEscapeHtml(response.message || 'Unable to load settlement snapshot.'));
              return;
            }

            renderSettlementAccount(response.settlement_account || null);
            renderSettlementWallet(response.wallet || {});
            renderSettlementReadiness(response.summary || {}, response.school || {});
            renderActiveBatch(response.active_batch || null);
            renderSettlementPreview(response.preview || { items: [] });
            renderRecentBatches(response.recent_batches || []);
            updateStageButton(response);
          },
          error: function () {
            settlementSetPageAlert('danger', 'Failed to load school settlement snapshot.');
          }
        });
      }

      function openCompleteBatchModal(batchId) {
        $('#completeSettlementBatchId').val(batchId);
        $('#completeSettlementProviderReference').val('');
        $('#completeSettlementNotes').val('');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('completeSettlementModal')).show();
      }

      function openFailBatchModal(batchId) {
        $('#failSettlementBatchId').val(batchId);
        $('#failSettlementReason').val('');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('failSettlementModal')).show();
      }

      $('#settlementSchool').on('change', function () {
        loadSettlementSnapshot();
      });

      $('#refreshSettlementSnapshot').on('click', function () {
        loadSettlementSnapshot();
      });

      $('#stageSettlementForm').on('submit', function (event) {
        event.preventDefault();
        var $button = $('#stageSettlementBtn');
        $.ajax({
          url: 'model/school_settlements.php',
          method: 'POST',
          dataType: 'json',
          data: {
            action: 'stage_batch',
            school_id: $('#settlementSchool').val(),
            scheduled_for: $('#settlementScheduledFor').val(),
            notes: $('#settlementStageNotes').val()
          },
          beforeSend: function () {
            $button.prop('disabled', true).text('Staging...');
          },
          success: function (response) {
            if ((response.status || '') === 'success') {
              settlementShowToast('success', response.message || 'Settlement batch staged successfully.');
              $('#settlementStageNotes').val('');
              loadSettlementSnapshot();
              return;
            }

            settlementSetPageAlert('warning', settlementEscapeHtml(response.message || 'Unable to stage settlement batch.'));
            settlementShowToast('error', response.message || 'Unable to stage settlement batch.');
            if (response.batch && response.batch.id) {
              renderActiveBatch(response.batch);
            }
          },
          error: function () {
            settlementShowToast('error', 'Failed to stage settlement batch.');
          },
          complete: function () {
            $button.text('Stage Settlement Batch');
            loadSettlementSnapshot();
          }
        });
      });

      $(document).on('click', '.settlement-complete-btn', function () {
        openCompleteBatchModal(Number($(this).data('batch-id') || 0));
      });

      $(document).on('click', '.settlement-fail-btn', function () {
        openFailBatchModal(Number($(this).data('batch-id') || 0));
      });

      $('#completeActiveBatchBtn').on('click', function () {
        openCompleteBatchModal(Number($(this).attr('data-batch-id') || activeSettlementBatchId || 0));
      });

      $('#failActiveBatchBtn').on('click', function () {
        openFailBatchModal(Number($(this).attr('data-batch-id') || activeSettlementBatchId || 0));
      });

      $('#completeSettlementForm').on('submit', function (event) {
        event.preventDefault();
        var $form = $(this);
        var modal = bootstrap.Modal.getInstance(document.getElementById('completeSettlementModal'));
        $.ajax({
          url: 'model/school_settlements.php',
          method: 'POST',
          dataType: 'json',
          data: {
            action: 'complete_batch',
            batch_id: $('#completeSettlementBatchId').val(),
            provider_reference: $('#completeSettlementProviderReference').val(),
            notes: $('#completeSettlementNotes').val()
          },
          beforeSend: function () {
            $form.find('button[type="submit"]').prop('disabled', true).text('Completing...');
          },
          success: function (response) {
            if ((response.status || '') === 'success') {
              settlementShowToast('success', response.message || 'Settlement batch completed successfully.');
              if (modal) {
                modal.hide();
              }
              loadSettlementSnapshot();
              return;
            }

            var message = response.message || 'Unable to complete settlement batch.';
            if ((response.status || '') === 'outstanding_mismatch' && response.mismatches && response.mismatches.length) {
              var mismatchLines = [];
              $.each(response.mismatches, function (_, mismatch) {
                mismatchLines.push(
                  settlementEscapeHtml(mismatch.source_ref_id) +
                  ' allocated ' + settlementMoney(mismatch.allocated_amount) +
                  ' but only ' + settlementMoney(mismatch.current_outstanding) +
                  ' is still outstanding'
                );
              });
              message += '<br><br>' + mismatchLines.join('<br>');
            }
            settlementSetPageAlert('danger', message);
            settlementShowToast('error', response.message || 'Unable to complete settlement batch.');
            loadSettlementSnapshot();
          },
          error: function () {
            settlementShowToast('error', 'Failed to complete settlement batch.');
          },
          complete: function () {
            $form.find('button[type="submit"]').prop('disabled', false).text('Confirm Completion');
          }
        });
      });

      $('#failSettlementForm').on('submit', function (event) {
        event.preventDefault();
        var $form = $(this);
        var modal = bootstrap.Modal.getInstance(document.getElementById('failSettlementModal'));
        $.ajax({
          url: 'model/school_settlements.php',
          method: 'POST',
          dataType: 'json',
          data: {
            action: 'fail_batch',
            batch_id: $('#failSettlementBatchId').val(),
            reason: $('#failSettlementReason').val()
          },
          beforeSend: function () {
            $form.find('button[type="submit"]').prop('disabled', true).text('Updating...');
          },
          success: function (response) {
            if ((response.status || '') === 'success') {
              settlementShowToast('success', response.message || 'Settlement batch marked as failed.');
              if (modal) {
                modal.hide();
              }
              loadSettlementSnapshot();
              return;
            }

            settlementSetPageAlert('danger', settlementEscapeHtml(response.message || 'Unable to fail settlement batch.'));
            settlementShowToast('error', response.message || 'Unable to fail settlement batch.');
          },
          error: function () {
            settlementShowToast('error', 'Failed to fail settlement batch.');
          },
          complete: function () {
            $form.find('button[type="submit"]').prop('disabled', false).text('Mark Failed');
          }
        });
      });

      if (initialSettlementSchoolId > 0) {
        loadSettlementSnapshot();
      } else {
        settlementSetPageAlert('warning', 'No active schools are available for settlement review.');
      }
    </script>
  </body>
</html>
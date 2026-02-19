<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;
if ($admin_role !== 6) {
  header('Location: index.php');
  exit();
}

$admin_school = isset($admin_['school']) ? (int) $admin_['school'] : 0;
$admin_faculty = isset($admin_['faculty']) ? (int) $admin_['faculty'] : 0;
$admin_scope_ready = ($admin_school > 0 && $admin_faculty > 0);
$nav_pic = file_exists("assets/images/users/$admin_image") ? "assets/images/users/$admin_image" : "assets/img/avatars/user.png";
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-navbar-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Material Grants | Nivasity Command Center</title>
  <meta name="description" content="" />
  <?php include('partials/_head.php') ?>
  <style>
    .grant-action-wrap {
      position: sticky;
      bottom: 1rem;
      z-index: 10;
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(4px);
      border: 1px solid #e7e7ef;
      border-radius: 0.75rem;
      padding: 0.75rem;
      margin-top: 1rem;
    }

    .lookup-label {
      font-size: 0.78rem;
      color: #8592a3;
      margin-bottom: 0.25rem;
      display: block;
    }

    #grantButton {
      font-size: 1rem;
      font-weight: 700;
      padding: 0.9rem 1rem;
    }

    .summary-card h3 {
      margin-bottom: 0;
      font-size: 1.35rem;
    }

    .summary-card small {
      color: #8592a3;
    }
  </style>
</head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-page">
      <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">
          <div class="d-flex align-items-center justify-content-between py-3 mb-4">
            <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Grant Management /</span> Material Grants</h4>
            <div class="dropdown">
              <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-offset="0,8">
                <div class="avatar avatar-online">
                  <img src="<?php echo $nav_pic; ?>" alt class="w-px-40 h-auto rounded-circle" />
                </div>
              </a>
              <ul class="dropdown-menu dropdown-menu-end dropdown-user popper-safe-dropdown">
                <li>
                  <a class="dropdown-item" href="#">
                    <div class="d-flex">
                      <div class="flex-shrink-0 me-3">
                        <div class="avatar avatar-online">
                          <img src="<?php echo $nav_pic; ?>" alt class="w-px-40 h-auto rounded-circle" />
                        </div>
                      </div>
                      <div class="flex-grow-1">
                        <span class="fw-semibold d-block"><?php echo htmlspecialchars($admin_name); ?></span>
                        <small class="text-muted">Admin</small>
                      </div>
                    </div>
                  </a>
                </li>
                <li><div class="dropdown-divider"></div></li>
                <li>
                  <a class="dropdown-item" href="profile.php">
                    <i class="bx bx-user me-2"></i>
                    <span class="align-middle">My Profile</span>
                  </a>
                </li>
                <li><div class="dropdown-divider"></div></li>
                <li>
                  <a class="dropdown-item" href="signin.html?logout=1">
                    <i class="bx bx-power-off me-2"></i>
                    <span class="align-middle">Log Out</span>
                  </a>
                </li>
              </ul>
            </div>
          </div>

          <?php if (!$admin_scope_ready) { ?>
            <div class="card">
              <div class="card-body">
                <div class="alert alert-warning mb-0">
                  This account is missing school/faculty assignment. Contact a super admin to assign both fields for Role 6.
                </div>
              </div>
            </div>
          <?php } else { ?>
            <div class="card mb-4">
              <div class="card-body">
                <div class="row g-3 align-items-end">
                  <div class="col-md-3">
                    <label class="form-label" for="deptSelect">Department</label>
                    <select id="deptSelect" class="form-select"></select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="materialSelect">Material (Code &amp; Course Title)</label>
                    <select id="materialSelect" class="form-select"></select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="lookupInput">Export Code / Student Email / Matric Number</label>
                    <input type="text" id="lookupInput" class="form-control" placeholder="e.g. GXCZEFPJVY or student@email.com or 20XX/XXXX" />
                  </div>
                  <div class="col-md-1 d-grid">
                    <button class="btn btn-primary" id="lookupBtn">Lookup</button>
                  </div>
                </div>
                <div class="mt-2 text-muted small">
                  Materials are limited to open items and closed items posted within the last 30 days.
                </div>
              </div>
            </div>

            <div class="row g-3 mb-4 d-none" id="summaryRow">
              <div class="col-md-4">
                <div class="card summary-card">
                  <div class="card-body">
                    <small>Students Count</small>
                    <h3 id="studentsCount">0</h3>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card summary-card">
                  <div class="card-body">
                    <small>Material Price</small>
                    <h3 id="materialPrice">NGN 0</h3>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card summary-card">
                  <div class="card-body">
                    <small>Total Amount Paid</small>
                    <h3 id="totalAmount">NGN 0</h3>
                  </div>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                  <h5 class="mb-0">Lookup Results</h5>
                  <small id="lookupMeta" class="text-muted">No lookup performed yet.</small>
                </div>
                <div class="mt-2 mt-md-0 small text-muted" id="pendingMeta"></div>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-striped align-middle" id="resultTable">
                    <thead class="table-secondary">
                      <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Email</th>
                        <th>Matric No</th>
                        <th>Amount</th>
                        <th>Bought At</th>
                        <th>Grant Status</th>
                      </tr>
                    </thead>
                    <tbody id="resultBody">
                      <tr>
                        <td colspan="7" class="text-center text-muted py-4">Run a lookup to see student records.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="grant-action-wrap d-none" id="grantActionWrap">
                  <button class="btn btn-success w-100" id="grantButton">Grant</button>
                </div>
              </div>
            </div>
          <?php } ?>
        </div>

        <?php include('partials/_footer.php') ?>
        <div class="content-backdrop fade"></div>
      </div>
    </div>
  </div>

  <?php if ($admin_scope_ready) { ?>
    <div class="modal fade" id="grantConfirmModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirm Grant (Step 1 of 2)</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-2">You are about to grant all pending records in the current lookup.</p>
            <div id="grantConfirmDetails" class="small text-muted"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="toFinalConfirmBtn">Continue</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="grantFinalModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Final Confirmation (Step 2 of 2)</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-2">Type <strong>GRANT</strong> to finalize this action.</p>
            <input type="text" id="finalGrantInput" class="form-control" placeholder="Type GRANT" />
            <div class="form-text">This marks all pending records in the current selection as granted.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" id="confirmGrantBtn">Grant Now</button>
          </div>
        </div>
      </div>
    </div>
  <?php } ?>

  <script src="assets/vendor/libs/jquery/jquery.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="assets/vendor/js/bootstrap.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="assets/vendor/libs/popper/popper.js"></script>
  <script src="assets/vendor/js/menu.js"></script>
  <script src="assets/js/ui-toasts.js"></script>
  <script src="assets/js/main.js"></script>

  <?php if ($admin_scope_ready) { ?>
    <script>
      const deptSelect = document.getElementById('deptSelect');
      const materialSelect = document.getElementById('materialSelect');
      const $deptSelect = $('#deptSelect');
      const $materialSelect = $('#materialSelect');
      const lookupInput = document.getElementById('lookupInput');
      const lookupBtn = document.getElementById('lookupBtn');
      const resultBody = document.getElementById('resultBody');
      const summaryRow = document.getElementById('summaryRow');
      const studentsCountEl = document.getElementById('studentsCount');
      const materialPriceEl = document.getElementById('materialPrice');
      const totalAmountEl = document.getElementById('totalAmount');
      const lookupMeta = document.getElementById('lookupMeta');
      const pendingMeta = document.getElementById('pendingMeta');
      const grantActionWrap = document.getElementById('grantActionWrap');
      const grantButton = document.getElementById('grantButton');

      const grantConfirmModal = new bootstrap.Modal(document.getElementById('grantConfirmModal'));
      const grantFinalModal = new bootstrap.Modal(document.getElementById('grantFinalModal'));
      const grantConfirmDetails = document.getElementById('grantConfirmDetails');
      const finalGrantInput = document.getElementById('finalGrantInput');
      const toFinalConfirmBtn = document.getElementById('toFinalConfirmBtn');
      const confirmGrantBtn = document.getElementById('confirmGrantBtn');

      let currentLookup = null;
      $deptSelect.select2({
        theme: 'bootstrap-5',
        width: '100%'
      });
      $materialSelect.select2({
        theme: 'bootstrap-5',
        width: '100%'
      });

      function fmtAmount(value) {
        return 'NGN ' + Number(value || 0).toLocaleString();
      }

      function escapeHtml(value) {
        return String(value || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function showGrantToast(title, message, type) {
        const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
        const toast = $('<div class="bs-toast toast fade show ' + bgClass + '" role="alert" aria-live="assertive" aria-atomic="true">' +
          '<div class="toast-header">' +
          '<i class="bx bx-bell me-2"></i>' +
          '<div class="me-auto fw-semibold">' + title + '</div>' +
          '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>' +
          '</div>' +
          '<div class="toast-body">' + message + '</div>' +
          '</div>');

        $('body').append(toast);
        setTimeout(function() {
          toast.remove();
        }, 3500);
      }

      function resetResults(message) {
        currentLookup = null;
        summaryRow.classList.add('d-none');
        grantActionWrap.classList.add('d-none');
        pendingMeta.textContent = '';
        lookupMeta.textContent = message || 'No lookup performed yet.';
        resultBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Run a lookup to see student records.</td></tr>';
      }

      async function loadDepartments() {
        const res = await fetch('model/material_grants.php?action=departments');
        const data = await res.json();

        deptSelect.innerHTML = '';
        if (!data.success || !Array.isArray(data.departments) || data.departments.length === 0) {
          deptSelect.innerHTML = '<option value="">No departments available</option>';
          materialSelect.innerHTML = '<option value="">No materials available</option>';
          $deptSelect.trigger('change.select2');
          $materialSelect.trigger('change.select2');
          lookupBtn.disabled = true;
          resetResults('No departments available for your scope.');
          return;
        }

        data.departments.forEach(function(item) {
          const opt = document.createElement('option');
          opt.value = String(item.id);
          opt.textContent = item.name;
          deptSelect.appendChild(opt);
        });
        deptSelect.value = String(data.departments[0].id);
        $deptSelect.trigger('change.select2');

        await loadMaterials();
      }

      async function loadMaterials() {
        const deptId = deptSelect.value;
        const res = await fetch('model/material_grants.php?action=materials&dept_id=' + encodeURIComponent(deptId || '0'));
        const data = await res.json();

        materialSelect.innerHTML = '';
        if (!data.success || !Array.isArray(data.materials) || data.materials.length === 0) {
          materialSelect.innerHTML = '<option value="">No materials available</option>';
          $materialSelect.trigger('change.select2');
          lookupBtn.disabled = true;
          resetResults('No eligible materials available for this department.');
          return;
        }

        data.materials.forEach(function(item) {
          const opt = document.createElement('option');
          opt.value = String(item.id);
          opt.textContent = (item.title || '') + ' - ' + (item.course_code || '') + ' (#' + (item.code || '') + ')';
          materialSelect.appendChild(opt);
        });
        materialSelect.value = String(data.materials[0].id);
        $materialSelect.trigger('change.select2');

        lookupBtn.disabled = false;
        resetResults('Ready for lookup.');
      }

      function renderLookupResult(data) {
        const records = Array.isArray(data.records) ? data.records : [];
        if (records.length === 0) {
          resetResults('No records returned.');
          return;
        }

        const summary = data.summary || {};
        studentsCountEl.textContent = Number(summary.students_count || 0).toLocaleString();
        materialPriceEl.textContent = fmtAmount(summary.price || 0);
        totalAmountEl.textContent = fmtAmount(summary.total_amount || 0);
        summaryRow.classList.remove('d-none');

        const pendingCount = Number(summary.pending_count || 0);
        const grantedCount = Number(summary.granted_count || 0);
        pendingMeta.textContent = 'Pending: ' + pendingCount.toLocaleString() + ' | Granted: ' + grantedCount.toLocaleString();

        const manual = data.manual || {};
        const lookup = data.lookup || {};
        lookupMeta.textContent = (data.message || 'Lookup completed') + ' | Material: ' + (manual.code || '') + ' - ' + (manual.title || '');

        const rowsHtml = records.map(function(row, index) {
          const badgeClass = row.is_granted ? 'bg-success' : 'bg-warning';
          const statusText = row.is_granted ? 'Granted' : 'Pending';
          return '<tr>' +
            '<td>' + (index + 1) + '</td>' +
            '<td>' + escapeHtml(row.full_name) + '</td>' +
            '<td>' + escapeHtml(row.email) + '</td>' +
            '<td>' + escapeHtml(row.matric_no) + '</td>' +
            '<td>' + fmtAmount(row.price || 0) + '</td>' +
            '<td>' + escapeHtml(row.bought_at) + '</td>' +
            '<td><span class="badge ' + badgeClass + '">' + statusText + '</span></td>' +
          '</tr>';
        }).join('');

        resultBody.innerHTML = rowsHtml;

        currentLookup = {
          dept_id: deptSelect.value,
          manual_id: materialSelect.value,
          lookup_value: lookupInput.value.trim(),
          mode: lookup.mode,
          payload: lookup,
          summary: summary,
          records: records
        };

        if (pendingCount > 0) {
          grantActionWrap.classList.remove('d-none');
        } else {
          grantActionWrap.classList.add('d-none');
        }
      }

      async function runLookup() {
        const deptId = deptSelect.value;
        const manualId = materialSelect.value;
        const lookupValue = lookupInput.value.trim();

        if (!deptId) {
          showGrantToast('Error', 'Select a department.', 'error');
          return;
        }
        if (!manualId) {
          showGrantToast('Error', 'Select a material.', 'error');
          return;
        }
        if (!lookupValue) {
          showGrantToast('Error', 'Enter export code, email, or matric number.', 'error');
          return;
        }

        lookupBtn.disabled = true;
        lookupBtn.textContent = '...';

        try {
          const params = new URLSearchParams();
          params.set('dept_id', deptId);
          params.set('manual_id', manualId);
          params.set('lookup_value', lookupValue);

          const res = await fetch('model/material_grants.php?action=lookup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
          });

          const data = await res.json();
          if (!res.ok || !data.success) {
            resetResults(data.message || 'Lookup failed.');
            showGrantToast('Error', data.message || 'Lookup failed.', 'error');
            return;
          }

          renderLookupResult(data);
          showGrantToast('Success', data.message || 'Lookup completed.', 'success');
        } catch (error) {
          resetResults('Lookup failed.');
          showGrantToast('Error', 'Network error during lookup.', 'error');
        } finally {
          lookupBtn.disabled = false;
          lookupBtn.textContent = 'Lookup';
        }
      }

      function openGrantConfirmation() {
        if (!currentLookup) {
          showGrantToast('Error', 'Run lookup first.', 'error');
          return;
        }

        const pendingCount = Number(currentLookup.summary.pending_count || 0);
        if (pendingCount <= 0) {
          showGrantToast('Info', 'All records are already granted.', 'error');
          return;
        }

        const modeText = currentLookup.mode === 'export' ? 'Export lookup' : 'Single student lookup';
        grantConfirmDetails.textContent = modeText + ' | Pending records: ' + pendingCount.toLocaleString();
        finalGrantInput.value = '';
        grantConfirmModal.show();
      }

      async function performGrant() {
        if (!currentLookup || !currentLookup.payload) {
          showGrantToast('Error', 'Lookup context is missing.', 'error');
          return;
        }

        if (finalGrantInput.value.trim().toUpperCase() !== 'GRANT') {
          showGrantToast('Error', 'Type GRANT to continue.', 'error');
          return;
        }

        const payload = currentLookup.payload || {};
        const params = new URLSearchParams();
        params.set('mode', currentLookup.mode || '');
        params.set('manual_id', String(currentLookup.manual_id || ''));

        if (currentLookup.mode === 'export') {
          params.set('export_id', String(payload.export_id || '0'));
        } else {
          params.set('student_id', String(payload.student_id || '0'));
          params.set('bought_id', String(payload.bought_id || '0'));
        }

        confirmGrantBtn.disabled = true;
        confirmGrantBtn.textContent = 'Granting...';

        try {
          const res = await fetch('model/material_grants.php?action=grant', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
          });

          const data = await res.json();
          if (!res.ok || !data.success) {
            showGrantToast('Error', data.message || 'Grant failed.', 'error');
            return;
          }

          grantFinalModal.hide();
          showGrantToast('Success', data.message || 'Grant completed.', 'success');
          await runLookup();
        } catch (error) {
          showGrantToast('Error', 'Network error during grant action.', 'error');
        } finally {
          confirmGrantBtn.disabled = false;
          confirmGrantBtn.textContent = 'Grant Now';
        }
      }

      function onDepartmentChanged() {
        loadMaterials();
      }

      $deptSelect.on('change select2:select', onDepartmentChanged);

      lookupBtn.addEventListener('click', function() {
        runLookup();
      });

      lookupInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          runLookup();
        }
      });

      grantButton.addEventListener('click', function() {
        openGrantConfirmation();
      });

      toFinalConfirmBtn.addEventListener('click', function() {
        grantConfirmModal.hide();
        setTimeout(function() {
          grantFinalModal.show();
        }, 180);
      });

      confirmGrantBtn.addEventListener('click', function() {
        performGrant();
      });

      loadDepartments().catch(function() {
        showGrantToast('Error', 'Failed to initialize page data.', 'error');
        resetResults('Failed to initialize page data.');
      });
    </script>
  <?php } ?>
</body>

</html>

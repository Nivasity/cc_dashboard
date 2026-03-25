<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$admin_role = isset($_SESSION['nivas_adminRole']) ? (int)$_SESSION['nivas_adminRole'] : 0;
if (!in_array($admin_role, [1, 2, 3], true)) {
  header('Location: index.php');
  exit();
}

$schools_query = mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active' ORDER BY name");
$faculties_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' ORDER BY name");
$depts_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Granted Exports | Nivasity Command Center</title>
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
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Resources Management /</span> Granted Exports</h4>

            <div class="card mb-4" id="grantedExportsCard">
              <div class="card-body">
                <form id="filterForm" class="row g-3 mb-2">
                  <div class="col-md-3">
                    <select name="school" id="school" class="form-select">
                      <option value="0">All Schools</option>
                      <?php while ($school = mysqli_fetch_array($schools_query)) { ?>
                        <option value="<?php echo (int)$school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <select name="faculty" id="faculty" class="form-select">
                      <option value="0">All Faculties</option>
                      <?php while ($fac = mysqli_fetch_array($faculties_query)) { ?>
                        <option value="<?php echo (int)$fac['id']; ?>"><?php echo htmlspecialchars($fac['name']); ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <select name="dept" id="dept" class="form-select">
                      <option value="0">All Departments</option>
                      <?php while ($dept = mysqli_fetch_array($depts_query)) { ?>
                        <option value="<?php echo (int)$dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <select name="date_range" id="dateRange" class="form-select">
                      <option value="7">Last 7 Days</option>
                      <option value="30" selected>Last 30 Days</option>
                      <option value="90">Last 90 Days</option>
                      <option value="all">All Time</option>
                      <option value="custom">Custom Range</option>
                    </select>
                  </div>
                  <div class="col-md-6 d-none" id="customDateRange">
                    <div class="row g-2">
                      <div class="col-md-6">
                        <input type="date" class="form-control" id="startDate" name="start_date" placeholder="Start Date">
                      </div>
                      <div class="col-md-6">
                        <input type="date" class="form-control" id="endDate" name="end_date" placeholder="End Date">
                      </div>
                    </div>
                  </div>
                  <div class="col-12">
                    <button type="submit" class="btn btn-secondary">Search</button>
                  </div>
                </form>

                <div class="table-responsive text-nowrap mt-3">
                  <table class="table table-striped align-middle" id="grantedExportsTable">
                    <thead class="table-secondary">
                      <tr>
                        <th>Export Code</th>
                        <th>Material</th>
                        <th>School</th>
                        <th>No. of Students</th>
                        <th>Date Granted</th>
                        <th>Granted By</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody id="exportsTableBody">
                      <tr>
                        <td colspan="7" class="text-center text-muted py-4">Loading granted exports...</td>
                      </tr>
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

  <div class="modal fade" id="studentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="studentsModalTitle">Export Students</h5>
            <small class="text-muted" id="studentsModalMeta"></small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive text-nowrap">
            <table class="table table-striped align-middle mb-0" id="studentsModalTable">
              <thead class="table-secondary">
                <tr>
                  <th>Student Name</th>
                  <th>Email</th>
                  <th>Matric No</th>
                  <th>Faculty</th>
                  <th>Department</th>
                  <th>Amount</th>
                  <th>Bought At</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="studentsTableBody">
                <tr>
                  <td colspan="8" class="text-center text-muted py-4">Select an export to view students.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
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
    const filterForm = document.getElementById('filterForm');
    const schoolSelect = document.getElementById('school');
    const facultySelect = document.getElementById('faculty');
    const deptSelect = document.getElementById('dept');
    const dateRangeSelect = document.getElementById('dateRange');
    const customDateRange = document.getElementById('customDateRange');
    const exportsTableBody = document.getElementById('exportsTableBody');
    const studentsTableBody = document.getElementById('studentsTableBody');
    const studentsModalTitle = document.getElementById('studentsModalTitle');
    const studentsModalMeta = document.getElementById('studentsModalMeta');
    const studentsModal = new bootstrap.Modal(document.getElementById('studentsModal'));
    const initialFacultyOptions = facultySelect.innerHTML;
    const initialDeptOptions = deptSelect.innerHTML;
    let exportsDataTable = null;
    let studentsDataTable = null;

    function escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
      if (!value) {
        return 'N/A';
      }

      const date = new Date(value.replace(' ', 'T'));
      if (Number.isNaN(date.getTime())) {
        return value;
      }

      return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
      });
    }

    function formatCurrency(amount) {
      const numericAmount = Number(amount || 0);
      return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        maximumFractionDigits: 0
      }).format(numericAmount);
    }

    function truncateText(value, length = 25) {
      const text = String(value ?? '').trim();
      if (text.length <= length) {
        return text;
      }

      return `${text.slice(0, length)}...`;
    }

    function resetExportsDataTable() {
      if (exportsDataTable) {
        exportsDataTable.destroy();
        exportsDataTable = null;
      }
    }

    function initializeExportsDataTable() {
      resetExportsDataTable();
      exportsDataTable = new DataTable('#grantedExportsTable', {
        order: [[4, 'desc']],
        pageLength: 10,
        language: {
          emptyTable: 'No granted exports found for the selected filters.'
        },
        columnDefs: [
          { orderable: false, targets: 6 }
        ]
      });
    }

    function resetStudentsDataTable() {
      if (studentsDataTable) {
        studentsDataTable.destroy();
        studentsDataTable = null;
      }
    }

    function initializeStudentsDataTable() {
      resetStudentsDataTable();
      studentsDataTable = new DataTable('#studentsModalTable', {
        order: [[0, 'asc']],
        pageLength: 10,
        language: {
          emptyTable: 'No students found in this export.'
        }
      });
    }

    function toggleCustomDateRange() {
      const isCustom = dateRangeSelect.value === 'custom';
      customDateRange.classList.toggle('d-none', !isCustom);
    }

    async function loadFaculties() {
      const schoolId = schoolSelect.value || '0';
      if (schoolId === '0') {
        facultySelect.innerHTML = initialFacultyOptions;
        facultySelect.value = '0';
        return;
      }

      const body = new URLSearchParams({
        get_data: 'faculties',
        school: schoolId
      });

      const response = await fetch('model/getInfo.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      });
      const data = await response.json();

      facultySelect.innerHTML = '<option value="0">All Faculties</option>';
      if (data.status === 'success' && Array.isArray(data.faculties)) {
        data.faculties.forEach((faculty) => {
          facultySelect.insertAdjacentHTML(
            'beforeend',
            `<option value="${escapeHtml(faculty.id)}">${escapeHtml(faculty.name)}</option>`
          );
        });
      }
    }

    async function loadDepartments() {
      if ((schoolSelect.value || '0') === '0') {
        deptSelect.innerHTML = initialDeptOptions;
        deptSelect.value = '0';
        return;
      }

      const body = new URLSearchParams({
        get_data: 'depts',
        school: schoolSelect.value || '0',
        faculty: facultySelect.value || '0'
      });

      const response = await fetch('model/getInfo.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      });
      const data = await response.json();

      deptSelect.innerHTML = '<option value="0">All Departments</option>';
      if (data.status === 'success' && Array.isArray(data.departments)) {
        data.departments.forEach((dept) => {
          deptSelect.insertAdjacentHTML(
            'beforeend',
            `<option value="${escapeHtml(dept.id)}">${escapeHtml(dept.name)}</option>`
          );
        });
      }
    }

    async function fetchExports() {
      const params = new URLSearchParams(new FormData(filterForm));
      const response = await fetch(`model/granted_exports.php?action=list&${params.toString()}`);
      const data = await response.json();
      resetExportsDataTable();

      if (!data.success) {
        exportsTableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Failed to load granted exports.</td></tr>';
        return;
      }

      const rows = Array.isArray(data.exports) ? data.exports : [];
      exportsTableBody.innerHTML = rows.map((item) => {
        const materialLabel = escapeHtml(truncateText(item.title, 30));
        const courseCode = escapeHtml(item.course_code || 'N/A');
        const grantedBy = item.granted_by_name ? escapeHtml(item.granted_by_name) : 'N/A';
        const schoolLabel = escapeHtml(truncateText(item.school_name, 25));
        const facultyLabel = escapeHtml(item.faculty_name || 'N/A');
        return `
          <tr>
            <td><span class="badge bg-label-primary">${escapeHtml(item.code)}</span></td>
            <td>
              <div class="fw-semibold" title="${escapeHtml(item.title)}">${materialLabel}</div>
              <small class="text-muted d-block">${courseCode}</small>
              <small class="text-muted">HOC: ${escapeHtml(item.hoc_name || 'N/A')}</small>
            </td>
            <td>
              <div>${schoolLabel}</div>
              <small class="text-muted d-block">${facultyLabel}</small>
            </td>
            <td>${escapeHtml(item.students_count)}</td>
            <td>${escapeHtml(formatDate(item.granted_at))}</td>
            <td>${grantedBy}</td>
            <td>
              <button type="button" class="btn btn-sm btn-outline-primary show-list-btn" data-export-id="${escapeHtml(item.id)}" data-export-code="${escapeHtml(item.code)}">
                Show list
              </button>
            </td>
          </tr>
        `;
      }).join('');

      initializeExportsDataTable();
    }

    async function fetchStudents(exportId, exportCode) {
      studentsModalTitle.textContent = `Export Students - ${exportCode}`;
      studentsModalMeta.textContent = 'Loading students...';
      resetStudentsDataTable();
      studentsTableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading students...</td></tr>';
      studentsModal.show();

      const response = await fetch(`model/granted_exports.php?action=students&export_id=${encodeURIComponent(exportId)}`);
      const data = await response.json();

      if (!data.success) {
        studentsModalMeta.textContent = data.message || 'Failed to load student list.';
        studentsTableBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Failed to load student list.</td></tr>';
        return;
      }

      const exportMeta = data.export || {};
      studentsModalMeta.textContent = `${exportMeta.students_count || 0} student${Number(exportMeta.students_count || 0) === 1 ? '' : 's'} • Granted ${formatDate(exportMeta.granted_at)} • By ${exportMeta.granted_by_name || 'N/A'}`;

      const students = Array.isArray(data.students) ? data.students : [];
      studentsTableBody.innerHTML = students.map((student) => `
        <tr>
          <td>${escapeHtml(student.full_name)}</td>
          <td>${escapeHtml(student.email)}</td>
          <td>${escapeHtml(student.matric_no || 'N/A')}</td>
          <td>${escapeHtml(student.faculty_name || 'N/A')}</td>
          <td>${escapeHtml(student.dept_name || 'N/A')}</td>
          <td>${escapeHtml(formatCurrency(student.price))}</td>
          <td>${escapeHtml(formatDate(student.created_at))}</td>
          <td><span class="badge bg-label-success">${student.is_granted ? 'Granted' : 'Pending'}</span></td>
        </tr>
      `).join('');

      initializeStudentsDataTable();
    }

    dateRangeSelect.addEventListener('change', toggleCustomDateRange);

    schoolSelect.addEventListener('change', async () => {
      await loadFaculties();
      await loadDepartments();
    });

    facultySelect.addEventListener('change', async () => {
      await loadDepartments();
    });

    filterForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      await fetchExports();
    });

    exportsTableBody.addEventListener('click', async (event) => {
      const button = event.target.closest('.show-list-btn');
      if (!button) {
        return;
      }

      await fetchStudents(button.dataset.exportId, button.dataset.exportCode);
    });

    (async function init() {
      toggleCustomDateRange();
      await fetchExports();
    })();
  </script>
</body>
</html>
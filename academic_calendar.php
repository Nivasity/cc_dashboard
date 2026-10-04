<?php
session_start();
include('model/config.php');
include('model/page_config.php');

// Academic calendar: each school's current session + semester. Students only see materials of the
// current period, and class reps only export purchases of the current session.
if (!$resource_mgt_menu) {
  header('Location: index.php');
  exit();
}

$admin_role = isset($_SESSION['nivas_adminRole']) ? (int) $_SESSION['nivas_adminRole'] : 0;
$admin_id = isset($_SESSION['nivas_adminId']) ? (int) $_SESSION['nivas_adminId'] : 0;
$school_filter = '';
if ($admin_role === 5 && $admin_id > 0) {
  $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school FROM admins WHERE id = $admin_id LIMIT 1"));
  $school_filter = ' AND id = ' . intval($row['school'] ?? 0);
}
$schools_query = mysqli_query($conn, "SELECT id, name FROM schools WHERE status = 'active'$school_filter ORDER BY name");
$selected_school = intval($_GET['school'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Academic Calendar | Nivasity Command Center</title>
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
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Resources /</span> Academic Calendar</h4>

            <div class="card mb-4">
              <div class="card-body">
                <div class="row g-3 align-items-end">
                  <div class="col-md-6">
                    <label for="calSchool" class="form-label">School</label>
                    <select id="calSchool" class="form-select">
                      <option value="0">Select a school</option>
                      <?php while ($sch = mysqli_fetch_assoc($schools_query)): ?>
                        <option value="<?php echo (int) $sch['id']; ?>" <?php echo $selected_school === (int) $sch['id'] ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($sch['name']); ?>
                        </option>
                      <?php endwhile; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <p class="small text-muted mb-0">
                      Students only see materials tagged with the school's <strong>current session and semester</strong>.
                      Class reps only see and export this session's materials and purchases.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div id="calAlert" class="alert d-none" role="alert"></div>

            <div class="row g-4 d-none" id="calBody">
              <div class="col-lg-6">
                <div class="card h-100">
                  <div class="card-body">
                    <span class="text-muted small text-uppercase fw-semibold">Current period</span>
                    <h3 class="fw-bold mt-1 mb-1" id="calCurrent">-</h3>
                    <p class="text-muted small mb-4" id="calStarted"></p>

                    <span class="text-muted small text-uppercase fw-semibold">Next period</span>
                    <h5 class="fw-semibold mt-1 mb-3" id="calNext">-</h5>
                    <ul class="small mb-4" id="calSummary"></ul>
                    <button type="button" class="btn btn-primary" id="calSwitchBtn">Move to next period</button>
                    <p class="small text-muted mt-2 mb-0 d-none" id="calNoSwitch">Only school-wide admins can move the school to the next period.</p>
                  </div>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="card h-100">
                  <div class="card-body">
                    <span class="text-muted small text-uppercase fw-semibold">History</span>
                    <div class="table-responsive mt-2">
                      <table class="table table-sm mb-0">
                        <thead><tr><th>Period</th><th>Started</th><th>By</th></tr></thead>
                        <tbody id="calHistory"><tr><td colspan="3" class="text-muted">No history yet</td></tr></tbody>
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

  <!-- Confirm move -->
  <div class="modal fade" id="calSwitchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="calSwitchTitle">Move to next period</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2" id="calSwitchText"></p>
          <p class="small text-muted mb-0">
            Open materials of other periods move to <strong>Awaiting Confirmation</strong> and are hidden from students.
            To sell one again, open it in Course Materials and use <strong>Confirm &amp; Open</strong> with this period.
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="calSwitchSubmit">Move now</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Core JS -->
  <script src="assets/vendor/libs/jquery/jquery.min.js"></script>
  <script src="assets/vendor/libs/popper/popper.min.js"></script>
  <script src="assets/vendor/js/bootstrap.min.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
  <script src="assets/vendor/js/menu.min.js"></script>
  <script src="assets/js/main.js"></script>
  <script>
    (function () {
      var info = null;
      var $school = $('#calSchool');
      var fmt = function (dt) {
        if (!dt) return '-';
        var d = new Date(String(dt).replace(' ', 'T'));
        return isNaN(d) ? dt : d.toLocaleString('en-NG', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' });
      };
      var alertBox = function (cls, text) {
        $('#calAlert').removeClass('d-none alert-success alert-danger alert-warning').addClass(cls).text(text);
      };

      function load() {
        var id = Number($school.val());
        $('#calAlert').addClass('d-none');
        if (!id) { $('#calBody').addClass('d-none'); return; }
        $.getJSON('model/materials.php', { fetch: 'school_semester', school: id }, function (res) {
          if (res.status !== 'success' || !res.semester_ready || !res.session_ready) {
            $('#calBody').addClass('d-none');
            alertBox('alert-warning', res.message || 'Run nivasity/sql/add_academic_session.sql on the database first.');
            return;
          }
          info = res;
          var c = res.counts || {};
          $('#calCurrent').text(res.current_label);
          $('#calStarted').text(res.history && res.history.length ? 'Started ' + fmt(res.history[0].started_at) : '');
          $('#calNext').text(res.next_label);
          $('#calSummary').html(
            '<li><strong>' + (c.going_live || 0) + '</strong> open material(s) already tagged ' + res.next_label + ' will go live</li>' +
            '<li><strong>' + (c.to_awaiting || 0) + '</strong> open material(s) of other periods will move to Awaiting Confirmation</li>' +
            (c.untagged ? '<li><strong>' + c.untagged + '</strong> material(s) have no session/semester and stay hidden</li>' : '')
          );
          $('#calSwitchBtn').toggleClass('d-none', !res.can_switch).text('Move to ' + res.next_label);
          $('#calNoSwitch').toggleClass('d-none', !!res.can_switch);
          var rows = (res.history || []).map(function (h) {
            return '<tr><td class="fw-semibold">' + $('<div>').text(h.label).html() + '</td><td>' + fmt(h.started_at) + '</td><td>' + $('<div>').text(h.started_by).html() + '</td></tr>';
          });
          $('#calHistory').html(rows.length ? rows.join('') : '<tr><td colspan="3" class="text-muted">No history yet</td></tr>');
          $('#calBody').removeClass('d-none');
        }).fail(function () { alertBox('alert-danger', 'Could not load the calendar. Try again.'); });
      }

      $school.on('change', function () {
        var id = Number($school.val());
        history.replaceState(null, '', 'academic_calendar.php' + (id ? '?school=' + id : ''));
        load();
      });

      $('#calSwitchBtn').on('click', function () {
        if (!info) return;
        $('#calSwitchTitle').text('Move to ' + info.next_label + '?');
        $('#calSwitchText').text($school.find('option:selected').text().trim() + ' moves from ' + info.current_label + ' to ' + info.next_label + '.');
        $('#calSwitchModal').modal('show');
      });

      $('#calSwitchSubmit').on('click', function () {
        if (!info) return;
        var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Moving...');
        $.post('model/materials.php', { switch_semester: 1, school: info.school_id, target_semester: info.next_semester }, null, 'json')
          .done(function (res) {
            $('#calSwitchModal').modal('hide');
            alertBox(res.status === 'success' ? 'alert-success' : 'alert-danger', res.message || 'Done');
            load();
          })
          .fail(function () { alertBox('alert-danger', 'Network error. Nothing was changed.'); })
          .always(function () { $btn.prop('disabled', false).text('Move now'); });
      });

      load();
    })();
  </script>
</body>
</html>

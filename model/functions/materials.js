$(document).ready(function () {
  // Configuration constants
  var SUCCESS_MESSAGE_DISPLAY_DURATION = 1500; // milliseconds - time to show success message before closing modal
  var UNSELECTED_VALUE = 0; // Value indicating no selection in dropdowns (matches backend constant)
  var DEFAULT_DATE_RANGE = '30'; // Default date range when filtering user-created materials
  var DEFAULT_CREATOR_TYPE = 'admins';

  var adminRole = window.adminRole || 0;
  var adminSchool = window.adminSchool || 0;
  var adminFaculty = window.adminFaculty || 0;
  var DEPT_ALL_SCHOOL = '__all_school__';
  var DEPT_ALL_FACULTY = '__all_faculty__';
  var STATUS_AWAITING = 'awaiting_confirmation';

  // Semester tagging: hidden until the backend reports the migration has run.
  var semesterReady = false;
  var schoolSemesterInfo = null;

  function semesterLabel(semester) {
    semester = Number(semester);
    if (semester === 1) return 'First Semester';
    if (semester === 2) return 'Second Semester';
    return 'No semester set';
  }

  function getSelectedSchoolId() {
    return adminRole == 5 ? adminSchool : $('#school').val();
  }

  function applySemesterReady(ready) {
    semesterReady = !!ready;
    $('#semesterFilterGroup, #materialSemesterGroup, #semesterPanel').toggleClass('d-none', !semesterReady);
    $('#materialSemester').prop('required', semesterReady);
  }

  function renderSemesterPanel(info) {
    schoolSemesterInfo = info;
    var $awaitingBtn = $('#semesterAwaitingBtn');
    var $untaggedBtn = $('#semesterUntaggedBtn');
    var $switchBtn = $('#switchSemesterBtn');

    if (!info) {
      $('#semesterCurrentLabel').text('Select a school');
      $('#semesterPanelHint').text('Pick one school in the filter to see and switch its current semester.');
      $awaitingBtn.add($untaggedBtn).add($switchBtn).addClass('d-none');
      return;
    }

    $('#semesterCurrentLabel').text(info.current_label);
    $('#semesterPanelHint').text('Students only see open materials tagged for the current semester.');

    var counts = info.counts || {};
    $awaitingBtn.toggleClass('d-none', !counts.awaiting).text((counts.awaiting || 0) + ' awaiting confirmation');
    $untaggedBtn.toggleClass('d-none', !counts.untagged).text((counts.untagged || 0) + ' with no semester set');
    $switchBtn.toggleClass('d-none', !info.can_switch).text('Switch to ' + info.next_label);
  }

  function loadSchoolSemester() {
    var schoolId = getSelectedSchoolId();
    if (!semesterReady) {
      return;
    }
    if (!schoolId || Number(schoolId) <= 0) {
      renderSemesterPanel(null);
      return;
    }
    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { fetch: 'school_semester', school: schoolId },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success' && res.semester_ready) {
          renderSemesterPanel(res);
        } else {
          renderSemesterPanel(null);
        }
      },
      error: function () {
        renderSemesterPanel(null);
      }
    });
  }

  function normalizeDepartmentSelection(selectedValues) {
    var values = Array.isArray(selectedValues) ? selectedValues.slice() : [];
    values = values.map(function (v) { return String(v); });

    if (values.indexOf(DEPT_ALL_SCHOOL) !== -1) {
      return [DEPT_ALL_SCHOOL];
    }
    if (values.indexOf(DEPT_ALL_FACULTY) !== -1) {
      return [DEPT_ALL_FACULTY];
    }

    var numericValues = [];
    var seen = {};
    values.forEach(function (v) {
      if (/^\d+$/.test(v) && Number(v) > 0 && !seen[v]) {
        seen[v] = true;
        numericValues.push(v);
      }
    });
    return numericValues;
  }

  InitiateDatatable('.table');
  $('#school, #faculty, #dept, #creatorType, #dateRange, #deptFilterType').select2({ theme: 'bootstrap-5', width: '100%' });

  function getCreatorType() {
    return $('#creatorType').val() || DEFAULT_CREATOR_TYPE;
  }

  function isUserMaterialsFilterActive() {
    return getCreatorType() === 'users';
  }

  function syncDateRangeVisibility() {
    var showDateFilter = isUserMaterialsFilterActive();
    $('#dateRangeFilterGroup').toggleClass('d-none', !showDateFilter);
    $('#customDateRange').toggleClass('d-none', !showDateFilter || $('#dateRange').val() !== 'custom');
  }

  function syncDeptFilterVisibility() {
    var deptVal = $('#dept').val();
    var showDeptFilter = deptVal && deptVal !== '0';
    $('#deptFilterGroup').toggleClass('d-none', !showDeptFilter);
    if (!showDeptFilter) {
      $('#deptFilterType').val('sold_to_dept').trigger('change.select2');
    }
  }

  syncDateRangeVisibility();
  syncDeptFilterVisibility();

  // Fetch materials on page load
  fetchMaterials();

  function fetchFaculties(schoolId) {
    if (adminRole == 5) {
      schoolId = adminSchool;
    }
    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { fetch: 'faculties', school: schoolId },
      dataType: 'json',
      success: function (res) {
        var $fac = $('#faculty');
        $fac.empty();
        if (!res.restrict_faculty) {
          $fac.append('<option value="0">All Faculties</option>');
        }
        if (res.status === 'success' && res.faculties) {
          $.each(res.faculties, function (i, fac) {
            $fac.append('<option value="' + fac.id + '">' + fac.name + '</option>');
          });
        }
        $fac.prop('disabled', res.restrict_faculty);
        var selected = res.restrict_faculty && res.faculties.length > 0 ? res.faculties[0].id : '0';
        $fac.val(selected).trigger('change.select2');
      }
    });
  }

  function fetchDepts(schoolId, facultyId) {
    if (adminRole == 5) {
      schoolId = adminSchool;
      if (adminFaculty !== 0) {
        facultyId = adminFaculty;
      }
    }
    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { fetch: 'departments', school: schoolId, faculty: facultyId },
      dataType: 'json',
      success: function (res) {
        var $dept = $('#dept');
        $dept.empty();
        $dept.append('<option value="0">All Departments</option>');
        if (res.status === 'success' && res.departments) {
          $.each(res.departments, function (i, dept) {
            $dept.append('<option value="' + dept.id + '">' + dept.name + '</option>');
          });
        }
        $dept.val('0').trigger('change.select2');
      }
    });
  }

  function fetchMaterials() {
    var schoolId = adminRole == 5 ? adminSchool : $('#school').val();
    var facultyId = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $('#faculty').val();
    var deptId = $('#dept').val();
    var deptFilterType = $('#deptFilterType').val() || 'sold_to_dept';
    var creatorType = getCreatorType();
    var isUserMaterials = creatorType === 'users';
    var dateRange = isUserMaterials ? ($('#dateRange').val() || DEFAULT_DATE_RANGE) : 'all';
    var startDate = isUserMaterials ? $('#startDate').val() : '';
    var endDate = isUserMaterials ? $('#endDate').val() : '';

    // Show loading state
    showStatsLoading();
    showTableLoading();

    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: {
        fetch: 'materials',
        school: schoolId,
        faculty: facultyId,
        dept: deptId,
        dept_filter_type: deptFilterType,
        creator_type: creatorType,
        date_range: dateRange,
        start_date: startDate,
        end_date: endDate,
        semester_state: $('#semesterState').val() || ''
      },
      dataType: 'json',
      success: function (res) {
        var wasSemesterReady = semesterReady;
        applySemesterReady(res.semester_ready);
        if (semesterReady && !wasSemesterReady) {
          loadSchoolSemester();
        }
        if ($.fn.dataTable.isDataTable('.table')) {
          var table = $('.table').DataTable();
          table.clear().draw().destroy();
        }
        var tbody = $('.table tbody');
        tbody.empty();

        // Check for error response
        if (res.status === 'error') {
          console.error('Backend error:', res.message);
          showToast('danger', 'Error Loading Materials', res.message || 'Failed to load materials. Please check console for details.');
          hideStatsLoading();
          hideTableLoading();
          return;
        }

        if (res.status === 'success' && res.stats) {
          $('#totalAmountPaid').text('₦ ' + Number(res.stats.total_amount_paid || 0).toLocaleString());
          $('#totalQtySold').text(Number(res.stats.total_qty_sold || 0).toLocaleString() + ' sold');
          $('#totalCount').text(Number(res.stats.total_count || 0).toLocaleString());
          $('#bestSellingCourseCode').text(res.stats.best_selling_code || 'N/A');
          $('#bestSellingSales').text(Number(res.stats.best_selling_qty || 0).toLocaleString() + ' sales');
        }

        if (res.status === 'success' && res.materials) {
          $.each(res.materials, function (i, mat) {
            var actionHtml = '<div class="dropstart">' +
              '<button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="true">' +
              '<i class="bx bx-dots-vertical-rounded"></i></button>' +
              '<div class="dropdown-menu">';

            // Add Edit option for admin-created materials
            if (mat.is_admin) {
              actionHtml += '<a href="javascript:void(0);" class="dropdown-item editMaterial" data-material=\'' + JSON.stringify(mat) + '\'><i class="bx bx-edit me-1"></i> Edit</a>';
            }

            // Add Delete option for closed admin-created materials with no purchases
            if (mat.is_admin && mat.db_status === 'closed' && mat.purchase_count == 0) {
              actionHtml += '<a href="javascript:void(0);" class="dropdown-item text-danger deleteMaterial" data-id="' + mat.id + '" data-title="' + mat.title + '"><i class="bx bx-trash me-1"></i> Delete</a>';
            }

            // Open -> close. Reopening (closed or awaiting confirmation) goes through Confirm & Open
            // once semester tagging is set up, so the price and semester get checked.
            if (mat.db_status === 'open') {
              actionHtml += '<a href="javascript:void(0);" class="dropdown-item toggleMaterial" data-id="' + mat.id + '" data-status="' + mat.db_status + '"><i class="bx bx-lock me-1"></i> Close Material</a>';
            } else if (semesterReady && (mat.db_status === STATUS_AWAITING || mat.db_status === 'closed')) {
              actionHtml += '<a href="javascript:void(0);" class="dropdown-item text-success confirmMaterial" data-material=\'' + JSON.stringify(mat).replace(/'/g, '&#39;') + '\'><i class="bx bx-check-circle me-1"></i> Confirm &amp; Open</a>';
              if (mat.db_status === STATUS_AWAITING) {
                actionHtml += '<a href="javascript:void(0);" class="dropdown-item toggleMaterial" data-id="' + mat.id + '" data-status="' + mat.db_status + '"><i class="bx bx-archive me-1"></i> Retire (Close)</a>';
              }
            } else if (mat.db_status === 'closed') {
              actionHtml += '<a href="javascript:void(0);" class="dropdown-item toggleMaterial" data-id="' + mat.id + '" data-status="' + mat.db_status + '"><i class="bx bx-lock-open me-1"></i> Open Material</a>';
            }

            // One semester per material: offer a copy for the other semester
            if (semesterReady && mat.is_admin && (Number(mat.semester) === 1 || Number(mat.semester) === 2)) {
              actionHtml += '<a href="javascript:void(0);" class="dropdown-item duplicateMaterial" data-material=\'' + JSON.stringify(mat).replace(/'/g, '&#39;') + '\'><i class="bx bx-copy me-1"></i> Duplicate for ' + semesterLabel(Number(mat.semester) === 1 ? 2 : 1) + '</a>';
            }

            actionHtml += '<a href="javascript:void(0);" class="dropdown-item downloadMaterialTransactions" data-id="' + mat.id + '" data-code="' + (mat.code || '') + '"><i class="bx bx-download me-1"></i> Download transactions list</a>' +
              '</div></div>';

            // Posted By column - show admin with role badge OR user with matric number
            var postedHtml = '<span class="text-uppercase text-primary">' + (mat.posted_by || 'Unknown') + '</span>';
            if (mat.is_admin) {
              // Show admin role in badge if it's an admin
              if (mat.role_or_matric && String(mat.role_or_matric).trim()) {
                postedHtml += '<br><span class="badge bg-label-secondary">' + mat.role_or_matric + '</span>';
              }
            } else {
              // Show matric number for users
              if (mat.role_or_matric && String(mat.role_or_matric).trim()) {
                postedHtml += '<br>Matric no: ' + mat.role_or_matric;
              }
            }

            // Title column with faculty, dept, and level info
            var titleHtml = '<strong>' + mat.title + ' (' + mat.course_code + ')</strong>';
            var metaInfo = [];
            if (mat.faculty_name && String(mat.faculty_name).trim()) {
              metaInfo.push('<small class="text-muted">Faculty: ' + mat.faculty_name + '</small>');
            }
            if (mat.level) {
              metaInfo.push('<small><span class="badge bg-label-info">Level: ' + mat.level + '</span></small>');
            }
            if (semesterReady) {
              var semesterBadgeClass = Number(mat.semester) > 0 ? 'bg-label-dark' : 'bg-label-secondary';
              metaInfo.push('<small><span class="badge ' + semesterBadgeClass + '">' + semesterLabel(mat.semester) + '</span></small>');
            }
            if (metaInfo.length > 0) {
              titleHtml += '<br>' + metaInfo.join(' | ');
            }

            var coverageHtml = '';
            if (mat.coverage === 'Custom') {
              coverageHtml = '<span class="badge bg-label-primary">' + (mat.coverage_label || ((mat.dept_count || 0) + ' Departments')) + '</span>';
            } else if (mat.coverage === 'Faculty') {
              coverageHtml = '<span class="badge bg-label-warning">Faculty</span>';
            } else if (mat.coverage === 'School') {
              coverageHtml = '<span class="badge bg-label-success">School</span>';
            } else {
              coverageHtml = '<span class="badge bg-label-primary">' + (mat.coverage_label || 'Custom') + '</span>';
            }

            var statusBadgeClass = mat.status === 'open' ? 'success' : (mat.status === STATUS_AWAITING ? 'warning' : 'danger');
            var statusText = mat.status === STATUS_AWAITING
              ? 'Awaiting confirmation'
              : mat.status.charAt(0).toUpperCase() + mat.status.slice(1);
            if (semesterReady && mat.status === 'open' && Number(mat.semester) > 0 && Number(mat.school_current_semester) > 0 && Number(mat.semester) !== Number(mat.school_current_semester)) {
              statusText = 'Open, live in ' + semesterLabel(mat.semester);
              statusBadgeClass = 'info';
            }

            var row = '<tr>' +
              '<td class="text-uppercase">' + (mat.code || '') + '</td>' +
              '<td class="text-uppercase">' + titleHtml + '</td>' +
              '<td>' + coverageHtml + '</td>' +
              '<td>' + postedHtml + '</td>' +
              '<td>₦ ' + Number(mat.price).toLocaleString() + '</td>' +
              '<td>₦ ' + Number(mat.revenue).toLocaleString() + '</td>' +
              '<td>' + mat.qty_sold + '</td>' +
              '<td><span class="fw-bold badge bg-label-' + statusBadgeClass + '">' + statusText + '</span></td>' +
              '<td>' + actionHtml + '</td>' +
              '</tr>';
            tbody.append(row);
          });
        }
        InitiateDatatable('.table');

        // Hide loading state after table is loaded
        hideStatsLoading();
        hideTableLoading();
      },
      error: function (xhr, status, error) {
        console.error('Error fetching materials:', error);
        console.error('Response:', xhr.responseText);

        // Show error message
        if (xhr.responseJSON && xhr.responseJSON.message) {
          Swal.fire({
            icon: 'error',
            title: 'Error Loading Materials',
            text: xhr.responseJSON.message
          });
        }

        // Hide loading state on error
        hideStatsLoading();
        hideTableLoading();
      }
    });
  }

  // Helper functions for loading state
  function showStatsLoading() {
    $('#amountCard, #countCard, #bestSellingCard').addClass('stats-card-loading');
  }

  function hideStatsLoading() {
    $('#amountCard, #countCard, #bestSellingCard').removeClass('stats-card-loading');
  }

  function showTableLoading() {
    $('#materialsCard').addClass('stats-card-loading');
  }

  function hideTableLoading() {
    $('#materialsCard').removeClass('stats-card-loading');
  }

  $('#school').on('change', function () {
    var schoolId = adminRole == 5 ? adminSchool : $(this).val();
    $('#dept').val('0').trigger('change.select2');
    fetchFaculties(schoolId);
    fetchDepts(schoolId, 0);
    fetchMaterials();
    loadSchoolSemester();
  });

  $('#semesterState').on('change', function () {
    fetchMaterials();
  });

  $('#semesterAwaitingBtn, #semesterUntaggedBtn').on('click', function () {
    $('#semesterState').val($(this).data('state'));
    fetchMaterials();
  });

  $('#faculty').on('change', function () {
    var schoolId = adminRole == 5 ? adminSchool : $('#school').val();
    var facultyId = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $(this).val();
    $('#dept').val('0').trigger('change.select2');
    fetchDepts(schoolId, facultyId);
    fetchMaterials();
  });

  $('#dept').on('change', function () {
    syncDeptFilterVisibility();
    fetchMaterials();
  });

  $('#deptFilterType').on('change', function () {
    fetchMaterials();
  });

  $('#creatorType').on('change', function () {
    if (!isUserMaterialsFilterActive()) {
      $('#dateRange').val(DEFAULT_DATE_RANGE).trigger('change.select2');
      $('#startDate').val('');
      $('#endDate').val('');
    }
    syncDateRangeVisibility();
    fetchMaterials();
  });

  $('#dateRange').on('change', function () {
    if (!isUserMaterialsFilterActive()) {
      $('#customDateRange').addClass('d-none');
      return;
    }
    var dateRange = $(this).val();
    if (dateRange === 'custom') {
      $('#customDateRange').removeClass('d-none');
    } else {
      $('#customDateRange').addClass('d-none');
      fetchMaterials();
    }
  });

  $('#startDate, #endDate').on('change', function () {
    if (!isUserMaterialsFilterActive()) {
      return;
    }
    var startDate = $('#startDate').val();
    var endDate = $('#endDate').val();
    if (startDate && endDate) {
      // Validate date range
      if (new Date(startDate) > new Date(endDate)) {
        if (typeof showToast === 'function') showToast('bg-warning', 'Start date must be before end date.');
        return;
      }
      fetchMaterials();
    }
  });

  $('#filterForm').on('submit', function (e) {
    e.preventDefault();
    fetchMaterials();
  });

  // Download transactions for a specific material using the robust CSV blob logic
  $(document).on('click', '.downloadMaterialTransactions', function (e) {
    e.preventDefault();
    var $link = $(this);
    var id = Number($link.data('id')) || 0;
    if (!id) {
      if (typeof showToast === 'function') showToast('bg-danger', 'Invalid material selected.');
      return;
    }

    // Optional: show a temporary state on the triggering control
    var originalHtml = $link.html();
    $link.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Downloading...');

    $.ajax({
      url: 'model/transactions_download.php',
      method: 'GET',
      data: { material_id: id },
      xhr: function () {
        var xhr = new window.XMLHttpRequest();
        xhr.responseType = 'blob';
        return xhr;
      },
      xhrFields: { responseType: 'blob' },
      success: function (data, status, xhr) {
        var blob = (xhr && xhr.response) ? xhr.response : data;
        if (!(blob instanceof Blob)) {
          try {
            blob = new Blob([blob], { type: 'text/csv;charset=utf-8' });
          } catch (e) {
            blob = new Blob([String(blob || '')], { type: 'text/csv;charset=utf-8' });
          }
        }

        var disposition = xhr.getResponseHeader('Content-Disposition') || '';
        var filename = 'material_transactions_' + new Date().toISOString().replace(/[-:T]/g, '').slice(0, 15) + '.csv';
        var match = /filename="?([^";]+)"?/i.exec(disposition);
        if (match && match[1]) filename = match[1];

        var link = document.createElement('a');
        var url = window.URL.createObjectURL(blob);
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        setTimeout(function () {
          window.URL.revokeObjectURL(url);
          document.body.removeChild(link);
        }, 100);
        if (typeof showToast === 'function') {
          showToast('bg-success', 'CSV generated. Download starting...');
        }
      },
      error: function () {
        if (typeof showToast === 'function') {
          showToast('bg-danger', 'Failed to generate CSV. Please try again.');
        }
      },
      complete: function () {
        $link.prop('disabled', false).html(originalHtml);
      }
    });
  });

  // Download CSV based on current filters
  $(document).on('click', '#downloadMaterials', function () {
    var $btn = $(this);
    var originalHtml = $btn.html();
    var schoolId = adminRole == 5 ? adminSchool : $('#school').val();
    var facultyId = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $('#faculty').val();
    var deptId = $('#dept').val();
    var deptFilterType = $('#deptFilterType').val() || 'sold_to_dept';
    var creatorType = getCreatorType();
    var isUserMaterials = creatorType === 'users';
    var dateRange = isUserMaterials ? ($('#dateRange').val() || DEFAULT_DATE_RANGE) : 'all';
    var startDate = isUserMaterials ? $('#startDate').val() : '';
    var endDate = isUserMaterials ? $('#endDate').val() : '';

    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: {
        download: 'csv',
        school: schoolId,
        faculty: facultyId,
        dept: deptId,
        dept_filter_type: deptFilterType,
        creator_type: creatorType,
        date_range: dateRange,
        start_date: startDate,
        end_date: endDate,
        semester_state: $('#semesterState').val() || ''
      },
      xhrFields: { responseType: 'blob' },
      beforeSend: function () {
        $btn.prop('disabled', true)
          .html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Downloading...');
      },
      success: function (data, status, xhr) {
        var blob = data;
        var disposition = xhr.getResponseHeader('Content-Disposition') || '';
        var filename = 'materials_' + new Date().toISOString().replace(/[-:T]/g, '').slice(0, 15) + '.csv';
        var match = /filename="?([^";]+)"?/i.exec(disposition);
        if (match && match[1]) filename = match[1];

        var link = document.createElement('a');
        var url = window.URL.createObjectURL(blob);
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        setTimeout(function () {
          window.URL.revokeObjectURL(url);
          document.body.removeChild(link);
        }, 100);
        if (typeof showToast === 'function') {
          showToast('bg-success', 'CSV generated. Download starting...');
        }
      },
      error: function () {
        if (typeof showToast === 'function') {
          showToast('bg-danger', 'Failed to generate CSV. Please try again.');
        }
      },
      complete: function () {
        $btn.prop('disabled', false).html(originalHtml);
      }
    });
  });

  $(document).on('click', '.toggleMaterial', function (e) {
    e.preventDefault();
    var id = $(this).data('id');
    $.ajax({
      url: 'model/materials.php',
      method: 'POST',
      data: { toggle_id: id },
      dataType: 'json',
      success: function (res) {
        showToast(res.status === 'success' ? 'bg-success' : 'bg-danger', res.message);
        fetchMaterials();
        loadSchoolSemester();
      },
      error: function () {
        showToast('bg-danger', 'Network error');
      }
    });
  });

  // Confirm & Open: check price and semester before a material goes back on sale
  function updateConfirmSemesterHint() {
    var chosen = Number($('#confirmMaterialSemester').val());
    var current = schoolSemesterInfo ? Number(schoolSemesterInfo.current_semester) : 0;
    var material = $('#confirmMaterialModal').data('material') || {};
    if (!current) {
      current = Number(material.school_current_semester) || 0;
    }
    var hint = '';
    if (chosen && current && chosen !== current) {
      hint = 'It will stay hidden until ' + semesterLabel(chosen) + ' starts.';
    } else if (chosen && current) {
      hint = 'Students will see it immediately.';
    }
    $('#confirmMaterialSemesterHint').text(hint);
  }

  $(document).on('click', '.confirmMaterial', function (e) {
    e.preventDefault();
    var material = $(this).data('material');
    if (!material) {
      return;
    }
    $('#confirmMaterialModal').data('material', material);
    $('#confirmMaterialId').val(material.id);
    $('#confirmMaterialTitle').text(material.title + ' (' + material.course_code + ')');
    var meta = ['Code: ' + (material.code || '')];
    meta.push('Current price: ₦ ' + Number(material.price).toLocaleString());
    meta.push('Sold so far: ' + (material.qty_sold || 0));
    if (material.confirmed_at) {
      meta.push('Last confirmed: ' + material.confirmed_at);
    }
    $('#confirmMaterialMeta').text(meta.join(' · '));
    $('#confirmMaterialPrice').val(material.price);
    $('#confirmMaterialSemester').val(Number(material.semester) > 0 ? String(material.semester) : '');
    $('#confirmMaterialAlert').addClass('d-none').text('');
    updateConfirmSemesterHint();
    $('#confirmMaterialModal').modal('show');
  });

  $('#confirmMaterialSemester').on('change', updateConfirmSemesterHint);

  $('#confirmMaterialForm').on('submit', function (e) {
    e.preventDefault();
    var $alert = $('#confirmMaterialAlert');
    var $btn = $('#confirmMaterialSubmit');
    var price = String($('#confirmMaterialPrice').val() || '').trim();
    var semester = $('#confirmMaterialSemester').val();

    if (!/^\d+$/.test(price)) {
      $alert.removeClass('d-none alert-success').addClass('alert-danger').text('Enter a valid price (whole naira, 0 or more).');
      return;
    }
    if (!semester) {
      $alert.removeClass('d-none alert-success').addClass('alert-danger').text('Select the semester this material is sold in.');
      return;
    }

    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Confirming...');
    $.ajax({
      url: 'model/materials.php',
      method: 'POST',
      data: {
        confirm_material: 1,
        material_id: $('#confirmMaterialId').val(),
        price: price,
        semester: semester
      },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success') {
          showToast('bg-success', res.message);
          $('#confirmMaterialModal').modal('hide');
          fetchMaterials();
          loadSchoolSemester();
        } else {
          $alert.removeClass('d-none alert-success').addClass('alert-danger').text(res.message || 'Failed to confirm material');
        }
      },
      error: function () {
        $alert.removeClass('d-none alert-success').addClass('alert-danger').text('Network error. Please try again.');
      },
      complete: function () {
        $btn.prop('disabled', false).html('Confirm &amp; Open');
      }
    });
  });

  // Duplicate a material for the other semester (opens the create form pre-filled)
  $(document).on('click', '.duplicateMaterial', function (e) {
    e.preventDefault();
    var material = $(this).data('material');
    if (material) {
      openEditModal(material, 'duplicate');
    }
  });

  // Semester switch for the selected school
  $('#switchSemesterBtn').on('click', function () {
    var info = schoolSemesterInfo;
    if (!info) {
      return;
    }
    var counts = info.counts || {};
    $('#switchSemesterTitle').text('Switch to ' + info.next_label);
    var items = [
      '<li><strong>' + (counts.to_awaiting || 0) + '</strong> open material(s) from ' + info.current_label + ' or with no semester set will move to <strong>Awaiting Confirmation</strong> and be hidden.</li>',
      '<li><strong>' + (counts.going_live || 0) + '</strong> open material(s) tagged ' + info.next_label + ' will go live for students.</li>'
    ];
    if (counts.awaiting_next) {
      items.push('<li><strong>' + counts.awaiting_next + '</strong> ' + info.next_label + ' material(s) from last session are still awaiting confirmation. Confirm them after switching.</li>');
    }
    $('#switchSemesterSummary').html(items.join(''));
    $('#switchSemesterAlert').addClass('d-none').text('');
    $('#switchSemesterModal').modal('show');
  });

  $('#switchSemesterSubmit').on('click', function () {
    var info = schoolSemesterInfo;
    var $btn = $(this);
    if (!info) {
      return;
    }
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Switching...');
    $.ajax({
      url: 'model/materials.php',
      method: 'POST',
      data: { switch_semester: 1, school: info.school_id, target_semester: info.next_semester },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success') {
          showToast('bg-success', res.message);
          $('#switchSemesterModal').modal('hide');
          fetchMaterials();
          loadSchoolSemester();
        } else {
          $('#switchSemesterAlert').removeClass('d-none').addClass('alert-danger').text(res.message || 'Failed to switch semester');
        }
      },
      error: function () {
        $('#switchSemesterAlert').removeClass('d-none').addClass('alert-danger').text('Network error. Please try again.');
      },
      complete: function () {
        $btn.prop('disabled', false).text('Switch Semester');
      }
    });
  });

  // Handle Edit Material click
  $(document).on('click', '.editMaterial', function (e) {
    e.preventDefault();
    var materialData = $(this).data('material');
    if (materialData) {
      openEditModal(materialData);
    }
  });

  // Handle Delete Material click with double confirmation
  $(document).on('click', '.deleteMaterial', function (e) {
    e.preventDefault();
    var materialId = $(this).data('id');
    var materialTitle = $(this).data('title');

    if (!materialId) {
      if (typeof showToast === 'function') {
        showToast('bg-danger', 'Invalid material selected.');
      }
      return;
    }

    // First confirmation using SweetAlert if available
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Are you sure?',
        html: 'You are about to delete this course material:<br><strong>' + materialTitle + '</strong><br><br>This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          // Second confirmation using native browser confirm
          if (confirm('Are you ABSOLUTELY sure you want to delete "' + materialTitle + '"? This cannot be undone.')) {
            deleteMaterial(materialId);
          }
        }
      });
    } else {
      // Fallback to native confirm dialogs if SweetAlert not available
      if (confirm('Are you sure you want to delete "' + materialTitle + '"? This action cannot be undone!')) {
        if (confirm('Are you ABSOLUTELY sure? This is your final confirmation.')) {
          deleteMaterial(materialId);
        }
      }
    }
  });

  // Function to delete material after confirmations
  function deleteMaterial(materialId) {
    $.ajax({
      url: 'model/materials.php',
      method: 'POST',
      data: { delete_material: 1, material_id: materialId },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success') {
          if (typeof showToast === 'function') {
            showToast('bg-success', res.message);
          }
          // Show success message with SweetAlert if available
          if (typeof Swal !== 'undefined') {
            Swal.fire('Deleted!', res.message, 'success');
          }
          fetchMaterials();
        } else {
          if (typeof showToast === 'function') {
            showToast('bg-danger', res.message || 'Failed to delete material');
          }
          if (typeof Swal !== 'undefined') {
            Swal.fire('Error', res.message || 'Failed to delete material', 'error');
          } else {
            alert('Error: ' + (res.message || 'Failed to delete material'));
          }
        }
      },
      error: function () {
        if (typeof showToast === 'function') {
          showToast('bg-danger', 'Network error. Please try again.');
        }
        if (typeof Swal !== 'undefined') {
          Swal.fire('Error', 'Network error. Please try again.', 'error');
        } else {
          alert('Network error. Please try again.');
        }
      }
    });
  }

  // Function to open modal in edit mode
  // mode 'duplicate' pre-fills the form from an existing material but creates a new one
  // (new code) for the other semester.
  function openEditModal(material, mode) {
    var isDuplicate = mode === 'duplicate';
    var otherSemester = Number(material.semester) === 1 ? 2 : 1;

    // Change modal title and button text
    $('#materialModalTitle').text(isDuplicate ? 'Duplicate for ' + semesterLabel(otherSemester) : 'Edit Course Material');
    $('#newMaterialSubmit').text(isDuplicate ? 'Create Copy' : 'Update Material');

    // Set material ID in hidden field (empty = create)
    $('#materialId').val(isDuplicate ? '' : material.id);
    $('#materialSemester').val(isDuplicate ? String(otherSemester) : (Number(material.semester) > 0 ? String(material.semester) : ''));

    // Set a flag to prevent the shown.bs.modal event from overwriting our values
    $('#newMaterialModal').data('isEditMode', true);
    $('#newMaterialModal').data('editMaterial', material);

    // Populate editable fields
    $('#materialTitle').val(material.title);
    $('#materialCourseCode').val(material.course_code);
    $('#materialPrice').val(material.price);

    // Load dropdown data and set selections BEFORE showing modal
    // This prevents select2 from reverting to defaults
    var schoolId = material.school_id;
    var hostFacultyId = material.host_faculty;
    var facultyId = material.faculty_id;
    var deptSelections = [];
    if (material.coverage === 'School') {
      deptSelections = [DEPT_ALL_SCHOOL];
    } else if (material.coverage === 'Faculty') {
      deptSelections = [DEPT_ALL_FACULTY];
    } else if (Array.isArray(material.dept_ids) && material.dept_ids.length > 0) {
      deptSelections = material.dept_ids.map(function (id) { return String(id); });
    } else if (material.dept_id && Number(material.dept_id) > 0) {
      deptSelections = [String(material.dept_id)];
    }
    var level = material.level || '';

    // Step 1: Fetch school name and make it read-only
    // School cannot be changed during edit as it would invalidate faculty/dept relationships
    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: {
        fetch: 'material_names',
        school_id: schoolId,
        host_faculty_id: 0,
        faculty_id: 0,
        dept_id: 0,
        level: 0
      },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success') {
          // Destroy select2 on school field
          $('#materialSchool').select2('destroy');

          // Replace school select with disabled text input showing the school name
          $('#materialSchool').replaceWith('<input type="text" class="form-control" id="materialSchool" value="' + res.school_name + '" disabled>');

          // Add hidden input with school ID for form submission
          $('#newMaterialForm').append('<input type="hidden" class="dynamic-hidden" name="school" value="' + schoolId + '">');
        }

        // Step 2: Fetch and populate faculties for this school
        $.ajax({
          url: 'model/materials.php',
          method: 'GET',
          data: { fetch: 'faculties', school: schoolId },
          dataType: 'json',
          success: function (res) {
            var $hostFac = $('#materialHostFaculty');
            var $fac = $('#materialFaculty');

            // Populate both host faculty and faculty dropdowns
            $hostFac.empty();
            $fac.empty();

            if (!res.restrict_faculty) {
              $hostFac.append('<option value="">Select Faculty Host</option>');
              $fac.append('<option value="">Select Faculty</option>');
            }
            if (res.status === 'success' && res.faculties) {
              $.each(res.faculties, function (i, fac) {
                $hostFac.append('<option value="' + fac.id + '">' + fac.name + '</option>');
                $fac.append('<option value="' + fac.id + '">' + fac.name + '</option>');
              });
            }

            // Set the selected values and trigger select2 to update display
            $hostFac.val(hostFacultyId).trigger('change.select2');
            $fac.val(facultyId).trigger('change.select2');

            // Step 3: Fetch and populate departments
            $.ajax({
              url: 'model/materials.php',
              method: 'GET',
              data: { fetch: 'departments', school: schoolId, faculty: facultyId, for_material: 1 },
              dataType: 'json',
              success: function (res) {
                var $dept = $('#materialDept');
                var deptList = (res.status === 'success' && res.departments) ? res.departments : [];
                populateModalDeptOptions($dept, deptList, facultyId);
                $dept.val(normalizeDepartmentSelection(deptSelections)).trigger('change.select2');

                // Step 4: Restore level options and select the current value
                restoreMaterialLevelSelect(level);

                // Step 5: NOW show the modal - all data is loaded and selected
                $('#newMaterialModal').modal('show');
              },
              error: function () {
                // On error, still show the modal with whatever data we have
                restoreMaterialLevelSelect(level);
                $('#newMaterialModal').modal('show');
              }
            });
          },
          error: function () {
            // On error, still show the modal with basic data
            restoreMaterialLevelSelect(level);
            $('#newMaterialModal').modal('show');
          }
        });
      },
      error: function () {
        // On error, still show the modal without school name replacement
        // Step 2: Fetch and populate faculties for this school
        $.ajax({
          url: 'model/materials.php',
          method: 'GET',
          data: { fetch: 'faculties', school: schoolId },
          dataType: 'json',
          success: function (res) {
            var $hostFac = $('#materialHostFaculty');
            var $fac = $('#materialFaculty');

            // Populate both host faculty and faculty dropdowns
            $hostFac.empty();
            $fac.empty();

            if (!res.restrict_faculty) {
              $hostFac.append('<option value="">Select Faculty Host</option>');
              $fac.append('<option value="">Select Faculty</option>');
            }
            if (res.status === 'success' && res.faculties) {
              $.each(res.faculties, function (i, fac) {
                $hostFac.append('<option value="' + fac.id + '">' + fac.name + '</option>');
                $fac.append('<option value="' + fac.id + '">' + fac.name + '</option>');
              });
            }

            // Set the selected values and trigger select2 to update display
            $hostFac.val(hostFacultyId).trigger('change.select2');
            $fac.val(facultyId).trigger('change.select2');

            // Step 3: Fetch and populate departments
            $.ajax({
              url: 'model/materials.php',
              method: 'GET',
              data: { fetch: 'departments', school: schoolId, faculty: facultyId, for_material: 1 },
              dataType: 'json',
              success: function (res) {
                var $dept = $('#materialDept');
                var deptList = (res.status === 'success' && res.departments) ? res.departments : [];
                populateModalDeptOptions($dept, deptList, facultyId);
                $dept.val(normalizeDepartmentSelection(deptSelections)).trigger('change.select2');

                // Step 4: Restore level options and select the current value
                restoreMaterialLevelSelect(level);

                // Step 5: NOW show the modal - all data is loaded and selected
                $('#newMaterialModal').modal('show');
              },
              error: function () {
                // On error, still show the modal with whatever data we have
                restoreMaterialLevelSelect(level);
                $('#newMaterialModal').modal('show');
              }
            });
          },
          error: function () {
            // On error, still show the modal with basic data
            restoreMaterialLevelSelect(level);
            $('#newMaterialModal').modal('show');
          }
        });
      }
    });
  }

  // Initialize dropdowns to match default selections
  fetchFaculties(adminRole == 5 ? adminSchool : $('#school').val());
  fetchDepts(adminRole == 5 ? adminSchool : $('#school').val(), (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $('#faculty').val());

  // New Material Modal functionality
  $('#materialSchool, #materialHostFaculty, #materialFaculty, #materialLevel').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#newMaterialModal') });
  $('#materialDept').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#newMaterialModal'), closeOnSelect: false, placeholder: 'Select departments coverage' });

  function getMaterialLevelOptions(selectedValue) {
    var currentValue = selectedValue === null || selectedValue === undefined ? '' : String(selectedValue);
    var levels = ['', '100', '200', '300', '400', '500', '600', '700'];

    return levels.map(function (levelValue) {
      var label = levelValue === '' ? 'All Levels' : levelValue + ' Level';
      var selected = currentValue === levelValue ? ' selected' : '';
      return '<option value="' + levelValue + '"' + selected + '>' + label + '</option>';
    }).join('');
  }

  function restoreMaterialLevelSelect(selectedValue) {
    var $level = $('#materialLevel');
    if ($level.length === 0) {
      return;
    }

    if ($level.hasClass('select2-hidden-accessible')) {
      $level.select2('destroy');
    }

    $level.html(getMaterialLevelOptions(selectedValue));
    $level.select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#newMaterialModal') });
    $level.val(selectedValue === null || selectedValue === undefined ? '' : String(selectedValue)).trigger('change.select2');
  }

  function fetchModalFaculties(schoolId) {
    if (adminRole == 5) {
      schoolId = adminSchool;
    }
    // Don't fetch if schoolId is not valid
    if (!schoolId || schoolId == 0) {
      return;
    }
    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { fetch: 'faculties', school: schoolId },
      dataType: 'json',
      success: function (res) {
        var $hostFac = $('#materialHostFaculty');
        var $fac = $('#materialFaculty');

        // Update both host faculty and faculty dropdowns
        $hostFac.empty();
        $fac.empty();

        if (!res.restrict_faculty) {
          $hostFac.append('<option value="">Select Faculty Host</option>');
          $fac.append('<option value="">Select Faculty</option>');
        }
        if (res.status === 'success' && res.faculties) {
          $.each(res.faculties, function (i, fac) {
            $hostFac.append('<option value="' + fac.id + '">' + fac.name + '</option>');
            $fac.append('<option value="' + fac.id + '">' + fac.name + '</option>');
          });
        }
        $hostFac.prop('disabled', res.restrict_faculty);
        $fac.prop('disabled', res.restrict_faculty);

        // For restricted admin role 5, use their assigned faculty
        var selected = '';
        if (res.restrict_faculty && adminRole == 5 && adminFaculty !== 0) {
          selected = adminFaculty;
        } else if (res.restrict_faculty && res.faculties.length > 0) {
          selected = res.faculties[0].id;
        }
        $hostFac.val(selected).trigger('change.select2');
        $fac.val(selected).trigger('change.select2');
      }
    });
  }

  function populateModalDeptOptions($dept, departments, facultyId) {
    $dept.empty();
    if (!(adminRole == 5 && adminFaculty !== 0)) {
      $dept.append('<option value="' + DEPT_ALL_SCHOOL + '">All Departments in School</option>');
    }
    if (facultyId && Number(facultyId) > 0) {
      $dept.append('<option value="' + DEPT_ALL_FACULTY + '">All Departments in Faculty</option>');
    }
    $.each(departments || [], function (i, dept) {
      $dept.append('<option value="' + dept.id + '">' + dept.name + '</option>');
    });
  }

  function fetchModalDepts(schoolId, facultyId, selectedValues) {
    if (adminRole == 5) {
      schoolId = adminSchool;
      if (adminFaculty !== 0) {
        facultyId = adminFaculty;
      }
    }
    // Don't fetch if schoolId is not valid
    if (!schoolId || schoolId == 0) {
      return;
    }
    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { fetch: 'departments', school: schoolId, faculty: facultyId, for_material: 1 },
      dataType: 'json',
      success: function (res) {
        var $dept = $('#materialDept');
        var deptList = (res.status === 'success' && res.departments) ? res.departments : [];
        populateModalDeptOptions($dept, deptList, facultyId);
        var finalSelection = normalizeDepartmentSelection(Array.isArray(selectedValues) ? selectedValues : []);
        $dept.val(finalSelection).trigger('change.select2');
      }
    });
  }

  $('#materialSchool').on('change', function () {
    // Skip fetching if we're in edit mode - data is already being loaded by openEditModal
    if ($('#newMaterialModal').data('isEditMode')) {
      return;
    }
    var schoolId = adminRole == 5 ? adminSchool : $(this).val();
    fetchModalFaculties(schoolId);
    fetchModalDepts(schoolId, 0, []);
    $('#materialSemester').val('');
    defaultMaterialSemester(schoolId);
  });

  $('#materialFaculty').on('change', function () {
    // Skip fetching if we're in edit mode - data is already being loaded by openEditModal
    if ($('#newMaterialModal').data('isEditMode')) {
      return;
    }
    var schoolId = adminRole == 5 ? adminSchool : $('#materialSchool').val();
    var facultyId = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $(this).val();
    fetchModalDepts(schoolId, facultyId || 0, []);
  });

  $('#materialDept').on('change', function () {
    var normalized = normalizeDepartmentSelection($(this).val() || []);
    var current = $(this).val() || [];
    if (JSON.stringify(normalized) !== JSON.stringify(current)) {
      $(this).val(normalized).trigger('change.select2');
    }
  });

  // Handle new material form submission
  $('#newMaterialForm').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $alert = $('#newMaterialAlert');
    var $submitBtn = $('#newMaterialSubmit');
    var materialId = $('#materialId').val();
    var isEdit = materialId && materialId !== '';

    // Client-side validation
    if (!$form[0].checkValidity()) {
      $form[0].reportValidity();
      return;
    }

    // Additional validation for dropdown values (checkValidity doesn't catch empty string for required dropdowns)
    var schoolVal = $('#materialSchool').val();
    var hostFacultyVal = $('#materialHostFaculty').val();
    var facultyVal = $('#materialFaculty').val();
    var deptVals = normalizeDepartmentSelection($('#materialDept').val() || []);

    if (!schoolVal || schoolVal == UNSELECTED_VALUE) {
      $alert.removeClass('d-none alert-success').addClass('alert-danger').text('Please select a school');
      return;
    }

    if (!hostFacultyVal || hostFacultyVal == UNSELECTED_VALUE) {
      $alert.removeClass('d-none alert-success').addClass('alert-danger').text('Please select a faculty host');
      return;
    }

    if (!facultyVal || facultyVal == UNSELECTED_VALUE) {
      $alert.removeClass('d-none alert-success').addClass('alert-danger').text('Please select a faculty (who can buy)');
      return;
    }

    if (!Array.isArray(deptVals) || deptVals.length === 0) {
      $alert.removeClass('d-none alert-success').addClass('alert-danger').text('Please select departments coverage');
      return;
    }

    $('#materialDept').val(deptVals).trigger('change.select2');

    var formData = $form.serialize();
    var actionParam = isEdit ? 'update_material=1' : 'create_material=1';
    var buttonText = isEdit ? 'Updating...' : 'Creating...';
    var originalButtonText = isEdit ? 'Update Material' : 'Create Material';

    $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>' + buttonText);
    $alert.addClass('d-none');

    $.ajax({
      url: 'model/materials.php',
      method: 'POST',
      data: formData + '&' + actionParam,
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success') {
          $alert.removeClass('d-none alert-danger').addClass('alert-success').text(res.message);
          if (typeof showToast === 'function') {
            showToast('bg-success', res.message);
          }
          setTimeout(function () {
            $('#newMaterialModal').modal('hide');
            $form[0].reset();
            $alert.addClass('d-none');
            fetchMaterials();
          }, SUCCESS_MESSAGE_DISPLAY_DURATION);
        } else {
          $alert.removeClass('d-none alert-success').addClass('alert-danger').text(res.message || 'Failed to ' + (isEdit ? 'update' : 'create') + ' material');
          if (typeof showToast === 'function') {
            showToast('bg-danger', res.message || 'Failed to ' + (isEdit ? 'update' : 'create') + ' material');
          }
        }
      },
      error: function () {
        $alert.removeClass('d-none alert-success').addClass('alert-danger').text('Network error. Please try again.');
        if (typeof showToast === 'function') {
          showToast('bg-danger', 'Network error. Please try again.');
        }
      },
      complete: function () {
        $submitBtn.prop('disabled', false).html(originalButtonText);
      }
    });
  });

  // Reset modal when closed
  $('#newMaterialModal').on('hidden.bs.modal', function () {
    $('#newMaterialForm')[0].reset();
    $('#newMaterialAlert').addClass('d-none');

    // Reset to create mode
    $('#materialModalTitle').text('Add New Course Material');
    $('#newMaterialSubmit').text('Create Material');
    $('#materialId').val('');
    $('#materialSemester').val('');

    // Clear edit mode flag
    $(this).removeData('isEditMode');
    $(this).removeData('editMaterial');

    // Remove any hidden fields added during edit mode
    $('#newMaterialForm').find('input.dynamic-hidden').remove();

    // If fields were replaced with disabled inputs during edit, restore them to select2 dropdowns
    // Check if materialSchool is currently a text input (disabled)
    if ($('#materialSchool').is('input[type="text"][disabled]')) {
      // Restore original select dropdowns for school, faculty, dept, level
      $('#materialSchool').replaceWith('<select class="form-select" id="materialSchool" name="school" required></select>');
      $('#materialHostFaculty').replaceWith('<select class="form-select" id="materialHostFaculty" name="host_faculty" required></select>');
      $('#materialFaculty').replaceWith('<select class="form-select" id="materialFaculty" name="faculty" required></select>');
      $('#materialDept').replaceWith('<select class="form-select" id="materialDept" name="depts[]" multiple required></select>');
      $('#materialLevel').replaceWith('<select class="form-select" id="materialLevel" name="level">' + getMaterialLevelOptions('') + '</select>');

      // Re-initialize select2 on restored dropdowns
      $('#materialSchool, #materialHostFaculty, #materialFaculty, #materialLevel').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#newMaterialModal') });
      $('#materialDept').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#newMaterialModal'), closeOnSelect: false, placeholder: 'Select departments coverage' });
    }

    restoreMaterialLevelSelect('');

    // For restricted admins (role 5), restore their default values
    if (adminRole == 5) {
      if (adminSchool) {
        $('#materialSchool').val(adminSchool).trigger('change.select2');
      }
      if (adminFaculty !== 0) {
        $('#materialHostFaculty').val(adminFaculty).trigger('change.select2');
        $('#materialFaculty').val(adminFaculty).trigger('change.select2');
      } else {
        $('#materialHostFaculty').val('').trigger('change.select2');
        $('#materialFaculty').val('').trigger('change.select2');
      }
    } else {
      // For non-restricted admins, only clear if there's a valid initial state
      var $school = $('#materialSchool');
      var $hostFaculty = $('#materialHostFaculty');
      var $faculty = $('#materialFaculty');

      if ($school.find('option').length > 1) {
        $school.val($school.find('option:first').val()).trigger('change.select2');
      }
      if ($hostFaculty.find('option').length > 1) {
        $hostFaculty.val($hostFaculty.find('option:first').val()).trigger('change.select2');
      }
      if ($faculty.find('option').length > 1) {
        $faculty.val($faculty.find('option:first').val()).trigger('change.select2');
      }
    }
    $('#materialDept').val([]).trigger('change.select2');
  });

  // Initialize modal dropdowns on modal show
  $('#newMaterialModal').on('shown.bs.modal', function () {
    // Skip fetching if we're in edit mode - data is already loaded
    if ($(this).data('isEditMode')) {
      // Clear the flag so user can change dropdowns normally after modal is shown
      $(this).removeData('isEditMode');
      return;
    }

    var schoolId = adminRole == 5 ? adminSchool : $('#materialSchool').val();
    if (schoolId) {
      fetchModalFaculties(schoolId);
      fetchModalDepts(schoolId, (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : 0, []);
    }
    defaultMaterialSemester(schoolId);
  });

  // New materials default to the school's current semester
  function defaultMaterialSemester(schoolId) {
    if (!semesterReady || $('#materialSemester').val()) {
      return;
    }
    if (schoolSemesterInfo && Number(schoolSemesterInfo.school_id) === Number(schoolId)) {
      $('#materialSemester').val(String(schoolSemesterInfo.current_semester));
      return;
    }
    if (!schoolId || Number(schoolId) <= 0) {
      return;
    }
    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { fetch: 'school_semester', school: schoolId },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success' && res.semester_ready && !$('#materialSemester').val()) {
          $('#materialSemester').val(String(res.current_semester));
        }
      }
    });
  }
});

$(document).ready(function () {
  var adminRole = window.adminRole || 0;
  var adminSchool = window.adminSchool || 0;
  var adminFaculty = window.adminFaculty || 0;

  InitiateDatatable('.table');
  $('#school, #faculty, #dept').select2({ theme: 'bootstrap-5', width: '100%' });

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

    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { fetch: 'materials', school: schoolId, faculty: facultyId, dept: deptId },
      dataType: 'json',
      success: function (res) {
        if ($.fn.dataTable.isDataTable('.table')) {
          var table = $('.table').DataTable();
          table.clear().draw().destroy();
        }
        var tbody = $('.table tbody');
        tbody.empty();
        if (res.status === 'success' && res.materials) {
          $.each(res.materials, function (i, mat) {
            var actionHtml = '';
            if (!mat.due_passed) {
              actionHtml = '<div class="dropstart">' +
                '<button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="true">' +
                '<i class="bx bx-dots-vertical-rounded"></i></button>' +
                '<div class="dropdown-menu">' +
                '<a href="javascript:void(0);" class="dropdown-item toggleMaterial" data-id="' + mat.id + '" data-status="' + mat.db_status + '">' +
                (mat.db_status === 'open' ? '<i class="bx bx-lock me-1"></i> Close Material' : '<i class="bx bx-lock-open me-1"></i> Open Material') + '</a>' +
                '</div></div>';
            }
            var row = '<tr>' +
              '<td class="text-uppercase"><strong>' + mat.title + ' (' + mat.course_code + ')</strong></td>' +
              '<td>₦ ' + Number(mat.price).toLocaleString() + '</td>' +
              '<td>₦ ' + Number(mat.revenue).toLocaleString() + '</td>' +
              '<td>' + mat.qty_sold + '</td>' +
              '<td><span class="fw-bold badge bg-label-' + (mat.status === 'open' ? 'success' : 'danger') + '">' + mat.status.charAt(0).toUpperCase() + mat.status.slice(1) + '</span></td>' +
              '<td>' + mat.due_date + '</td>' +
              '<td>' + actionHtml + '</td>' +
              '</tr>';
            tbody.append(row);
          });
        }
        InitiateDatatable('.table');
      }
    });
  }

  $('#school').on('change', function () {
    var schoolId = adminRole == 5 ? adminSchool : $(this).val();
    fetchFaculties(schoolId);
    fetchDepts(schoolId, 0);
    fetchMaterials();
  });

  $('#faculty').on('change', function () {
    var schoolId = adminRole == 5 ? adminSchool : $('#school').val();
    var facultyId = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $(this).val();
    fetchDepts(schoolId, facultyId);
    fetchMaterials();
  });

  $('#dept').on('change', function () {
    fetchMaterials();
  });

  $('#filterForm').on('submit', function (e) {
    e.preventDefault();
    fetchMaterials();
  });

  // Download CSV based on current filters
  $(document).on('click', '#downloadMaterials', function () {
    var $btn = $(this);
    var originalHtml = $btn.html();
    var schoolId = adminRole == 5 ? adminSchool : $('#school').val();
    var facultyId = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $('#faculty').val();
    var deptId = $('#dept').val();

    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { download: 'csv', school: schoolId, faculty: facultyId, dept: deptId },
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
        setTimeout(function(){
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
      },
      error: function () {
        showToast('bg-danger', 'Network error');
      }
    });
  });

  // Initialize dropdowns to match default selections
  fetchFaculties(adminRole == 5 ? adminSchool : $('#school').val());
  fetchDepts(adminRole == 5 ? adminSchool : $('#school').val(), (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $('#faculty').val());
});

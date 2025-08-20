$(document).ready(function () {
  InitiateDatatable('.table');
  $('#school, #faculty, #dept').select2({ theme: 'bootstrap-5', width: '100%' });

  function fetchFaculties(schoolId) {
    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { fetch: 'faculties', school: schoolId },
      dataType: 'json',
      success: function (res) {
        var $fac = $('#faculty');
        $fac.empty().append('<option value="0">All Faculties</option>');
        if (res.status === 'success' && res.faculties) {
          $.each(res.faculties, function (i, fac) {
            $fac.append('<option value="' + fac.id + '">' + fac.name + '</option>');
          });
        }
        $fac.val('0').trigger('change.select2');
      }
    });
  }

  function fetchDepts(schoolId, facultyId) {
    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { fetch: 'departments', school: schoolId, faculty: facultyId },
      dataType: 'json',
      success: function (res) {
        var $dept = $('#dept');
        $dept.empty().append('<option value="0">All Departments</option>');
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
    var schoolId = $('#school').val();
    var facultyId = $('#faculty').val();
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
            var row = '<tr>' +
              '<td class="text-uppercase"><strong>' + mat.title + ' (' + mat.course_code + ')</strong></td>' +
              '<td>₦ ' + Number(mat.price).toLocaleString() + '</td>' +
              '<td>₦ ' + Number(mat.revenue).toLocaleString() + '</td>' +
              '<td>' + mat.qty_sold + '</td>' +
              '<td><span class="fw-bold badge bg-label-' + (mat.status === 'open' ? 'success' : 'danger') + '">' + mat.status.charAt(0).toUpperCase() + mat.status.slice(1) + '</span></td>' +
              '<td>' + mat.due_date + '</td>' +
              '<td><div class="dropstart">' +
              '<button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="true">' +
              '<i class="bx bx-dots-vertical-rounded"></i></button>' +
              '<div class="dropdown-menu">' +
              '<a href="javascript:void(0);" class="dropdown-item toggleMaterial" data-id="' + mat.id + '" data-status="' + mat.status + '">' +
              (mat.status === 'open' ? 'Close Material' : 'Open Material') + '</a>' +
              '</div></div></td>' +
              '</tr>';
            tbody.append(row);
          });
        }
        InitiateDatatable('.table');
      }
    });
  }

  $('#school').on('change', function () {
    var schoolId = $(this).val();
    fetchFaculties(schoolId);
    fetchDepts(schoolId, 0);
    fetchMaterials();
  });

  $('#faculty').on('change', function () {
    var schoolId = $('#school').val();
    var facultyId = $(this).val();
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
});

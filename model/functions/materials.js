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
            var actionHtml = '<div class="dropstart">' +
              '<button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="true">' +
              '<i class="bx bx-dots-vertical-rounded"></i></button>' +
              '<div class="dropdown-menu">';

            // Only include toggle when material is open and not due-passed
            if (!mat.due_passed && mat.db_status === 'open') {
              actionHtml += '<a href="javascript:void(0);" class="dropdown-item toggleMaterial" data-id="' + mat.id + '" data-status="' + mat.db_status + '"><i class="bx bx-lock me-1"></i> Close Material</a>';
            }

            actionHtml += '<a href="javascript:void(0);" class="dropdown-item downloadMaterialTransactions" data-id="' + mat.id + '" data-code="' + (mat.code || '') + '"><i class="bx bx-download me-1"></i> Download transactions list</a>' +
              '</div></div>';
            var postedHtml = '<span class="text-uppercase text-primary">' + (mat.posted_by || '') + '</span>';
            if (mat.matric && String(mat.matric).trim()) { postedHtml += '<br>Matric no: ' + mat.matric; }
            var row = '<tr>' +
              '<td class="text-uppercase">' + (mat.code || '') + '</td>' +
              '<td class="text-uppercase"><strong>' + mat.title + ' (' + mat.course_code + ')</strong></td>' +
              '<td>' + postedHtml + '</td>' +
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

  // New Material Modal functionality
  $('#materialSchool, #materialFaculty, #materialDept').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#newMaterialModal') });

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
        var $fac = $('#materialFaculty');
        $fac.empty();
        if (!res.restrict_faculty) {
          $fac.append('<option value="">Select Faculty</option>');
        }
        if (res.status === 'success' && res.faculties) {
          $.each(res.faculties, function (i, fac) {
            $fac.append('<option value="' + fac.id + '">' + fac.name + '</option>');
          });
        }
        $fac.prop('disabled', res.restrict_faculty);
        // For restricted admin role 5, use their assigned faculty
        var selected = '';
        if (res.restrict_faculty && adminRole == 5 && adminFaculty !== 0) {
          selected = adminFaculty;
        } else if (res.restrict_faculty && res.faculties.length > 0) {
          selected = res.faculties[0].id;
        }
        $fac.val(selected).trigger('change.select2');
      }
    });
  }

  function fetchModalDepts(schoolId, facultyId) {
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
      data: { fetch: 'departments', school: schoolId, faculty: facultyId },
      dataType: 'json',
      success: function (res) {
        var $dept = $('#materialDept');
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

  $('#materialSchool').on('change', function () {
    var schoolId = adminRole == 5 ? adminSchool : $(this).val();
    fetchModalFaculties(schoolId);
    fetchModalDepts(schoolId, 0);
  });

  $('#materialFaculty').on('change', function () {
    var schoolId = adminRole == 5 ? adminSchool : $('#materialSchool').val();
    var facultyId = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $(this).val();
    if (facultyId) {
      fetchModalDepts(schoolId, facultyId);
    }
  });

  // Handle new material form submission
  $('#newMaterialForm').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $alert = $('#newMaterialAlert');
    var $submitBtn = $('#newMaterialSubmit');
    
    // Client-side validation
    if (!$form[0].checkValidity()) {
      $form[0].reportValidity();
      return;
    }

    var formData = $form.serialize();
    
    $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Creating...');
    $alert.addClass('d-none');

    $.ajax({
      url: 'model/materials.php',
      method: 'POST',
      data: formData + '&create_material=1',
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success') {
          $alert.removeClass('d-none alert-danger').addClass('alert-success').text(res.message);
          if (typeof showToast === 'function') {
            showToast('bg-success', res.message);
          }
          // Success message display duration before closing modal and refreshing
          var successMessageDelay = 1500; // milliseconds
          setTimeout(function () {
            $('#newMaterialModal').modal('hide');
            $form[0].reset();
            $alert.addClass('d-none');
            fetchMaterials();
          }, successMessageDelay);
        } else {
          $alert.removeClass('d-none alert-success').addClass('alert-danger').text(res.message || 'Failed to create material');
          if (typeof showToast === 'function') {
            showToast('bg-danger', res.message || 'Failed to create material');
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
        $submitBtn.prop('disabled', false).html('Create Material');
      }
    });
  });

  // Reset modal when closed
  $('#newMaterialModal').on('hidden.bs.modal', function () {
    $('#newMaterialForm')[0].reset();
    $('#newMaterialAlert').addClass('d-none');
    
    // For restricted admins (role 5), restore their default values
    if (adminRole == 5) {
      if (adminSchool) {
        $('#materialSchool').val(adminSchool).trigger('change.select2');
      }
      if (adminFaculty !== 0) {
        $('#materialFaculty').val(adminFaculty).trigger('change.select2');
      } else {
        $('#materialFaculty').val('').trigger('change.select2');
      }
    } else {
      // For non-restricted admins, only clear if there's a valid initial state
      var $school = $('#materialSchool');
      var $faculty = $('#materialFaculty');
      
      if ($school.find('option').length > 1) {
        $school.val($school.find('option:first').val()).trigger('change.select2');
      }
      if ($faculty.find('option').length > 1) {
        $faculty.val($faculty.find('option:first').val()).trigger('change.select2');
      }
    }
    $('#materialDept').val('0').trigger('change.select2');
  });

  // Initialize modal dropdowns on modal show
  $('#newMaterialModal').on('shown.bs.modal', function () {
    var schoolId = adminRole == 5 ? adminSchool : $('#materialSchool').val();
    if (schoolId) {
      fetchModalFaculties(schoolId);
      fetchModalDepts(schoolId, 0);
    }
  });
});

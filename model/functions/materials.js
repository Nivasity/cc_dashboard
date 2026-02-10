$(document).ready(function () {
  // Configuration constants
  var SUCCESS_MESSAGE_DISPLAY_DURATION = 1500; // milliseconds - time to show success message before closing modal
  var UNSELECTED_VALUE = 0; // Value indicating no selection in dropdowns (matches backend constant)
  var DEFAULT_DATE_RANGE = 'all'; // Default date range for filtering (show all materials)
  
  var adminRole = window.adminRole || 0;
  var adminSchool = window.adminSchool || 0;
  var adminFaculty = window.adminFaculty || 0;

  InitiateDatatable('.table');
  $('#school, #faculty, #dept, #dateRange').select2({ theme: 'bootstrap-5', width: '100%' });
  
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
    var dateRange = $('#dateRange').val() || DEFAULT_DATE_RANGE;
    var startDate = $('#startDate').val();
    var endDate = $('#endDate').val();

    // Show loading state
    showTableLoading();

    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { 
        fetch: 'materials', 
        school: schoolId, 
        faculty: facultyId, 
        dept: deptId,
        date_range: dateRange,
        start_date: startDate,
        end_date: endDate
      },
      dataType: 'json',
      success: function (res) {
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
          hideTableLoading();
          return;
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

            // Only include toggle when material is open and not due-passed
            if (!mat.due_passed && mat.db_status === 'open') {
              actionHtml += '<a href="javascript:void(0);" class="dropdown-item toggleMaterial" data-id="' + mat.id + '" data-status="' + mat.db_status + '"><i class="bx bx-lock me-1"></i> Close Material</a>';
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
            if (mat.dept_name && String(mat.dept_name).trim()) {
              metaInfo.push('<small class="text-muted">Dept: ' + mat.dept_name + '</small>');
            }
            if (mat.level) {
              metaInfo.push('<small class="text-muted">Level: <span class="badge bg-label-info">' + mat.level + '</span></small>');
            }
            if (metaInfo.length > 0) {
              titleHtml += '<br>' + metaInfo.join(' | ');
            }
            
            var row = '<tr>' +
              '<td class="text-uppercase">' + (mat.code || '') + '</td>' +
              '<td class="text-uppercase">' + titleHtml + '</td>' +
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
        
        // Hide loading state after table is loaded
        hideTableLoading();
      },
      error: function(xhr, status, error) {
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
        hideTableLoading();
      }
    });
  }

  // Helper functions for loading state
  function showTableLoading() {
    $('#materialsCard').addClass('stats-card-loading');
  }

  function hideTableLoading() {
    $('#materialsCard').removeClass('stats-card-loading');
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

  $('#dateRange').on('change', function () {
    var dateRange = $(this).val();
    if (dateRange === 'custom') {
      $('#customDateRange').removeClass('d-none');
    } else {
      $('#customDateRange').addClass('d-none');
      fetchMaterials();
    }
  });

  $('#startDate, #endDate').on('change', function () {
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
    var dateRange = $('#dateRange').val() || DEFAULT_DATE_RANGE;
    var startDate = $('#startDate').val();
    var endDate = $('#endDate').val();

    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { 
        download: 'csv', 
        school: schoolId, 
        faculty: facultyId, 
        dept: deptId,
        date_range: dateRange,
        start_date: startDate,
        end_date: endDate
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
  function openEditModal(material) {
    // Change modal title and button text
    $('#materialModalTitle').text('Edit Course Material');
    $('#newMaterialSubmit').text('Update Material');
    
    // Set material ID in hidden field
    $('#materialId').val(material.id);
    
    // Populate EDITABLE fields (these remain interactive)
    $('#materialTitle').val(material.title);
    $('#materialCourseCode').val(material.course_code);
    $('#materialPrice').val(material.price);
    
    // Set due date (material.due_date_raw is in Y-m-d\TH:i format for datetime-local input)
    if (material.due_date_raw) {
      $('#materialDueDate').val(material.due_date_raw);
    }
    
    // For NON-EDITABLE fields, we need to:
    // 1. Fetch human-readable names (school, faculty, dept names)
    // 2. Replace select2 dropdowns with disabled text inputs showing the values
    // 3. Store the IDs in hidden attributes or hidden fields for backend submission
    
    // Fetch names for non-editable fields and populate them as disabled inputs
    $.ajax({
      url: 'model/materials.php',
      method: 'GET',
      data: { 
        fetch: 'material_names', 
        school_id: material.school_id,
        host_faculty_id: material.host_faculty,
        faculty_id: material.faculty_id,
        dept_id: material.dept_id || 0,
        level: material.level || ''
      },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success') {
          // Destroy select2 instances for fields we're converting to disabled inputs
          $('#materialSchool').select2('destroy');
          $('#materialHostFaculty').select2('destroy');
          $('#materialFaculty').select2('destroy');
          $('#materialDept').select2('destroy');
          $('#materialLevel').select2('destroy');
          
          // Replace with disabled text inputs showing names
          $('#materialSchool').replaceWith('<input type="text" class="form-control" id="materialSchool" value="' + res.school_name + '" disabled>');
          $('#materialHostFaculty').replaceWith('<input type="text" class="form-control" id="materialHostFaculty" value="' + res.host_faculty_name + '" disabled>');
          $('#materialFaculty').replaceWith('<input type="text" class="form-control" id="materialFaculty" value="' + res.faculty_name + '" disabled>');
          $('#materialDept').replaceWith('<input type="text" class="form-control" id="materialDept" value="' + res.dept_name + '" disabled>');
          $('#materialLevel').replaceWith('<input type="text" class="form-control" id="materialLevel" value="' + res.level_text + '" disabled>');
          
          // Store IDs in hidden fields for backend submission
          $('#newMaterialForm').append('<input type="hidden" name="school" value="' + material.school_id + '">');
          $('#newMaterialForm').append('<input type="hidden" name="host_faculty" value="' + material.host_faculty + '">');
          $('#newMaterialForm').append('<input type="hidden" name="faculty" value="' + material.faculty_id + '">');
          $('#newMaterialForm').append('<input type="hidden" name="dept" value="' + (material.dept_id || 0) + '">');
          $('#newMaterialForm').append('<input type="hidden" name="level" value="' + (material.level || '') + '">');
        }
      }
    });
    
    // Show the modal
    $('#newMaterialModal').modal('show');
  }

  // Initialize dropdowns to match default selections
  fetchFaculties(adminRole == 5 ? adminSchool : $('#school').val());
  fetchDepts(adminRole == 5 ? adminSchool : $('#school').val(), (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $('#faculty').val());

  // New Material Modal functionality
  $('#materialSchool, #materialHostFaculty, #materialFaculty, #materialDept, #materialLevel').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#newMaterialModal') });

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
    
    // Remove any hidden fields added during edit mode
    $('#newMaterialForm').find('input[type="hidden"][name="school"], input[type="hidden"][name="host_faculty"], input[type="hidden"][name="faculty"], input[type="hidden"][name="dept"], input[type="hidden"][name="level"]').remove();
    
    // If fields were replaced with disabled inputs during edit, restore them to select2 dropdowns
    // Check if materialSchool is currently a text input (disabled)
    if ($('#materialSchool').is('input[type="text"][disabled]')) {
      // Restore original select dropdowns for school, faculty, dept, level
      $('#materialSchool').replaceWith('<select class="form-select" id="materialSchool" name="school" required></select>');
      $('#materialHostFaculty').replaceWith('<select class="form-select" id="materialHostFaculty" name="host_faculty" required></select>');
      $('#materialFaculty').replaceWith('<select class="form-select" id="materialFaculty" name="faculty" required></select>');
      $('#materialDept').replaceWith('<select class="form-select" id="materialDept" name="dept" required></select>');
      $('#materialLevel').replaceWith('<select class="form-select" id="materialLevel" name="level"></select>');
      
      // Re-initialize select2 on restored dropdowns
      $('#materialSchool, #materialHostFaculty, #materialFaculty, #materialDept, #materialLevel').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#newMaterialModal') });
    }
    
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
    $('#materialDept').val('0').trigger('change.select2');
  });

  // Initialize modal dropdowns on modal show
  $('#newMaterialModal').on('shown.bs.modal', function () {
    var schoolId = adminRole == 5 ? adminSchool : $('#materialSchool').val();
    if (schoolId) {
      fetchModalFaculties(schoolId);
      fetchModalDepts(schoolId, 0);
    }
    
    // Set minimum date to current datetime for better UX (backend also validates)
    var now = new Date();
    var year = now.getFullYear();
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var day = String(now.getDate()).padStart(2, '0');
    var hours = String(now.getHours()).padStart(2, '0');
    var minutes = String(now.getMinutes()).padStart(2, '0');
    var minDateTime = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
    $('#materialDueDate').attr('min', minDateTime);
  });
});

$(document).ready(function () {
  InitiateDatatable('.table');

  let editing = false;
  const adminModalEl = document.getElementById('newAdminModal');
  const adminModal = bootstrap.Modal.getOrCreateInstance(adminModalEl);

  const $role = $('#role');
  const $school = $('#school');
  const $faculty = $('#faculty');
  const $departments = $('#departments');
  const $schoolWrap = $('#school_wrapper');
  const $facultyWrap = $('#faculty_wrapper');
  const $departmentsWrap = $('#departments_wrapper');

  $('#role, #school, #faculty').select2({ theme: 'bootstrap-5', dropdownParent: $('#newAdminModal') });
  $('#departments').select2({
    theme: 'bootstrap-5',
    dropdownParent: $('#newAdminModal'),
    closeOnSelect: false,
    width: '100%'
  });

  function isScopedRole(roleVal) {
    const role = parseInt(roleVal, 10);
    return role === 5 || role === 6;
  }

  function isGrantManagerRole(roleVal) {
    return parseInt(roleVal, 10) === 6;
  }

  function parseDepartmentData(raw) {
    if (!raw) {
      return [];
    }

    try {
      const decoded = JSON.parse(raw);
      if (Array.isArray(decoded)) {
        return decoded
          .map(function (v) { return parseInt(v, 10); })
          .filter(function (v) { return Number.isInteger(v) && v > 0; })
          .map(function (v) { return String(v); });
      }
    } catch (e) {
      // Fallback parsing below.
    }

    return String(raw)
      .split(',')
      .map(function (v) { return parseInt(v.trim(), 10); })
      .filter(function (v) { return Number.isInteger(v) && v > 0; })
      .map(function (v) { return String(v); });
  }

  function setSchoolOptionLabel(roleVal) {
    const label = isGrantManagerRole(roleVal) ? 'Select School' : 'All Schools';
    let $firstOption = $school.find('option[value="0"]');
    if ($firstOption.length === 0) {
      $firstOption = $('<option value="0"></option>');
      $school.prepend($firstOption);
    }
    $firstOption.text(label);
  }

  function setFacultyOptionLabel(roleVal) {
    const label = isGrantManagerRole(roleVal) ? 'Select Faculty' : 'All Faculties';
    let $firstOption = $faculty.find('option[value="0"]');
    if ($firstOption.length === 0) {
      $firstOption = $('<option value="0"></option>');
      $faculty.prepend($firstOption);
    }
    $firstOption.text(label);
  }

  function setDepartmentsAllSelected() {
    $departments.val(['__all__']).trigger('change');
  }

  function clearDepartmentsOptions() {
    $departments.empty();
    $departments.append('<option value="__all__">All Departments</option>');
    setDepartmentsAllSelected();
  }

  function toggleRoleFields(roleVal) {
    if (isScopedRole(roleVal)) {
      $schoolWrap.show();
      $facultyWrap.show();
      setSchoolOptionLabel(roleVal);
      setFacultyOptionLabel(roleVal);
      $school.trigger('change');
    } else {
      $schoolWrap.hide();
      $facultyWrap.hide();
      $departmentsWrap.hide();
      $school.val('0').trigger('change');
      $faculty.val('0').trigger('change');
      clearDepartmentsOptions();
    }

    if (isGrantManagerRole(roleVal)) {
      $departmentsWrap.show();
    } else {
      $departmentsWrap.hide();
      clearDepartmentsOptions();
    }
  }

  function loadDepartments(schoolId, facultyId, selectedDepartments, roleVal) {
    clearDepartmentsOptions();

    if (!isGrantManagerRole(roleVal)) {
      return;
    }

    const finalSelected = (Array.isArray(selectedDepartments) ? selectedDepartments : []).map(String);

    if (parseInt(schoolId, 10) <= 0 || parseInt(facultyId, 10) <= 0) {
      setDepartmentsAllSelected();
      return;
    }

    $.ajax({
      method: 'POST',
      url: 'model/getInfo.php',
      data: { get_data: 'depts', school: schoolId, faculty: facultyId },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success' && Array.isArray(res.departments)) {
          $.each(res.departments, function (_, dept) {
            $departments.append('<option value="' + dept.id + '">' + dept.name + '</option>');
          });
        }

        if (finalSelected.length > 0) {
          const validValues = finalSelected.filter(function (val) {
            return $departments.find('option[value="' + val + '"]').length > 0;
          });

          if (validValues.length > 0) {
            $departments.val(validValues).trigger('change');
            return;
          }
        }

        setDepartmentsAllSelected();
      },
      error: function () {
        setDepartmentsAllSelected();
      }
    });
  }

  function loadFaculties(schoolId, selectedFaculty, roleVal, selectedDepartments) {
    $faculty.empty();
    setFacultyOptionLabel(roleVal);

    if (parseInt(schoolId, 10) <= 0) {
      $faculty.val('0').trigger('change');
      loadDepartments(0, 0, selectedDepartments || [], roleVal);
      return;
    }

    $.ajax({
      method: 'POST',
      url: 'model/getInfo.php',
      data: { get_data: 'faculties', school: schoolId },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success' && Array.isArray(res.faculties)) {
          $.each(res.faculties, function (_, fac) {
            $faculty.append('<option value="' + fac.id + '">' + fac.name + '</option>');
          });
        }

        const facultyValue = String(selectedFaculty || 0);
        if ($faculty.find('option[value="' + facultyValue + '"]').length > 0) {
          $faculty.val(facultyValue).trigger('change');
        } else {
          $faculty.val('0').trigger('change');
        }

        loadDepartments(schoolId, $faculty.val(), selectedDepartments || [], roleVal);
      },
      error: function () {
        $faculty.val('0').trigger('change');
        loadDepartments(schoolId, 0, selectedDepartments || [], roleVal);
      }
    });
  }

  $('.new_formBtn').on('click', function () {
    editing = false;
    $('#adminForm')[0].reset();
    $('#adminForm [name="admin_id"]').val(0);
    $('#newAdminModalLabel').text('Add New Admin');
    $role.val($role.find('option:first').val()).trigger('change');
    $school.val('0').trigger('change');
    $faculty.val('0').trigger('change');
    clearDepartmentsOptions();
    $('#password_field').show();
    adminModal.show();
  });

  $('.editAdmin').on('click', function () {
    const dropdownToggle = $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]')[0];
    if (dropdownToggle) {
      bootstrap.Dropdown.getOrCreateInstance(dropdownToggle).hide();
    }

    editing = true;
    $('#newAdminModalLabel').text('Edit Admin');
    $('#adminForm [name="admin_id"]').val($(this).data('id'));
    $('#adminForm [name="first_name"]').val($(this).data('first'));
    $('#adminForm [name="last_name"]').val($(this).data('last'));
    $('#adminForm [name="email"]').val($(this).data('email'));
    $('#adminForm [name="phone"]').val($(this).data('phone'));
    $('#adminForm [name="gender"]').val($(this).data('gender'));

    const role = parseInt($(this).data('role'), 10) || 0;
    const school = parseInt($(this).data('school'), 10) || 0;
    const faculty = parseInt($(this).data('faculty'), 10) || 0;
    const selectedDepartments = parseDepartmentData($(this).attr('data-departments'));

    $role.val(String(role)).trigger('change');

    if (isScopedRole(role)) {
      $school.val(String(school)).trigger('change');
      loadFaculties(school, faculty, role, selectedDepartments);
    }

    $('#adminForm [name="password"]').val('');
    $('#password_field').hide();

    adminModal.show();
    editing = false;
  });

  adminModalEl.addEventListener('hide.bs.modal', function () {
    $('#role, #school, #faculty, #departments').select2('close');
    if (document.activeElement && adminModalEl.contains(document.activeElement)) {
      document.activeElement.blur();
    }
  });

  $role.on('change', function () {
    toggleRoleFields($(this).val());
  });

  $school.on('change', function () {
    if (editing) return;
    loadFaculties($(this).val(), 0, $role.val(), []);
  });

  $faculty.on('change', function () {
    if (editing) return;
    if (!isGrantManagerRole($role.val())) return;
    loadDepartments($school.val(), $faculty.val(), [], $role.val());
  });

  $departments.on('change', function () {
    if (!isGrantManagerRole($role.val())) return;

    const selected = $departments.val() || [];
    if (selected.includes('__all__') && selected.length > 1) {
      $departments.val(['__all__']).trigger('change.select2');
    }
  });

  $('#adminForm').on('submit', function (e) {
    e.preventDefault();

    const role = parseInt($role.val(), 10);
    if (role === 6) {
      if (parseInt($school.val(), 10) <= 0) {
        showToast('bg-danger', 'Select a school for Grant Manager (Role 6).');
        return;
      }
      if (parseInt($faculty.val(), 10) <= 0) {
        showToast('bg-danger', 'Select a faculty for Grant Manager (Role 6).');
        return;
      }

      const selectedDepartments = $departments.val() || [];
      if (!selectedDepartments.includes('__all__') && selectedDepartments.length === 0) {
        showToast('bg-danger', 'Select departments or choose All Departments.');
        return;
      }
    }

    const formData = $(this).serialize() + '&admin_manage=1';
    $.ajax({
      url: 'model/admin.php',
      method: 'POST',
      data: formData,
      dataType: 'json',
      beforeSend: function () {
        $('#submitBtn').prop('disabled', true);
      },
      success: function (data) {
        $('#submitBtn').prop('disabled', false);
        showToast(data.status == 'success' ? 'bg-success' : 'bg-danger', data.message);
        if (data.status == 'success') {
          setTimeout(() => location.reload(), 1000);
        }
      },
      error: function () {
        $('#submitBtn').prop('disabled', false);
        showToast('bg-danger', 'Network error');
      }
    });
  });

  $('.toggleAdminStatus').on('click', function () {
    const id = $(this).data('id');
    const currentStatus = ($(this).data('status') || '').toString().toLowerCase();
    const activating = currentStatus !== 'active';
    const action = activating ? 'activate' : 'deactivate';
    const message = activating
      ? 'Are you sure you want to activate this admin?'
      : 'Are you sure you want to deactivate this admin?';

    if (!confirm(message)) {
      return;
    }

    $.ajax({
      url: 'model/admin.php',
      method: 'POST',
      data: { admin_toggle: 1, admin_id: id, action: action },
      dataType: 'json',
      success: function (data) {
        showToast(data.status == 'success' ? 'bg-success' : 'bg-danger', data.message);
        if (data.status == 'success') {
          setTimeout(() => location.reload(), 1000);
        }
      },
      error: function () {
        showToast('bg-danger', 'Network error');
      }
    });
  });
});

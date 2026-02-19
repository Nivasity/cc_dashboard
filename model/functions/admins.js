$(document).ready(function () {
  InitiateDatatable('.table');

  let editing = false;
  const adminModalEl = document.getElementById('newAdminModal');
  const adminModal = bootstrap.Modal.getOrCreateInstance(adminModalEl);

  $('#role, #school, #faculty').select2({ theme: 'bootstrap-5', dropdownParent: $('#newAdminModal') });

  const $schoolWrap = $('#school_wrapper');
  const $facultyWrap = $('#faculty_wrapper');

  function isScopedRole(roleVal) {
    const role = parseInt(roleVal, 10);
    return role === 5 || role === 6;
  }

  function isGrantManagerRole(roleVal) {
    return parseInt(roleVal, 10) === 6;
  }

  function setSchoolOptionLabel(roleVal) {
    const label = isGrantManagerRole(roleVal) ? 'Select School' : 'All Schools';
    const $school = $('#school');
    let $firstOption = $school.find('option[value="0"]');
    if ($firstOption.length === 0) {
      $firstOption = $('<option value="0"></option>');
      $school.prepend($firstOption);
    }
    $firstOption.text(label);
  }

  function toggleRoleFields(roleVal) {
    if (isScopedRole(roleVal)) {
      $schoolWrap.show();
      $facultyWrap.show();
      setSchoolOptionLabel(roleVal);
      $('#school').trigger('change');
    } else {
      $schoolWrap.hide();
      $facultyWrap.hide();
      $('#school').val('0').trigger('change');
      $('#faculty').val('0').trigger('change');
    }
  }

  function loadFaculties(schoolId, selected = 0, roleVal = $('#role').val()) {
    const $fac = $('#faculty');
    $fac.empty();
    const defaultFacultyLabel = isGrantManagerRole(roleVal) ? 'Select Faculty' : 'All Faculties';
    $fac.append('<option value="0">' + defaultFacultyLabel + '</option>');
    if (schoolId == 0) {
      $fac.val(selected).trigger('change');
      return;
    }
    $.ajax({
      method: 'POST',
      url: 'model/getInfo.php',
      data: { get_data: 'faculties', school: schoolId },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success' && res.faculties) {
          $.each(res.faculties, function (i, fac) {
            $fac.append('<option value="' + fac.id + '">' + fac.name + '</option>');
          });
        }
        $fac.val(selected).trigger('change');
      }
    });
  }

  $('.new_formBtn').on('click', function () {
    editing = false;
    $('#adminForm')[0].reset();
    $('#adminForm [name="admin_id"]').val(0);
    $('#newAdminModalLabel').text('Add New Admin');
    $('#role').val($('#role option:first').val()).trigger('change');
    $('#school').val('0').trigger('change');
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
    const role = $(this).data('role');
    $('#role').val(role).trigger('change');
    if (isScopedRole(role)) {
      const school = $(this).data('school') || 0;
      const faculty = $(this).data('faculty') || 0;
      $('#school').val(school).trigger('change');
      loadFaculties(school, faculty, role);
    }
    $('#adminForm [name="password"]').val('');
    $('#password_field').hide();

    adminModal.show();
    editing = false;
  });

  adminModalEl.addEventListener('hide.bs.modal', function () {
    $('#role, #school, #faculty').select2('close');
    if (document.activeElement && adminModalEl.contains(document.activeElement)) {
      document.activeElement.blur();
    }
  });

  $('#role').on('change', function () {
    toggleRoleFields($(this).val());
  });

  $('#school').on('change', function () {
    if (editing) return;
    loadFaculties($(this).val(), 0, $('#role').val());
  });

  $('#adminForm').on('submit', function (e) {
    e.preventDefault();
    const role = parseInt($('#role').val(), 10);
    if (role === 6) {
      if (parseInt($('#school').val(), 10) <= 0) {
        showToast('bg-danger', 'Select a school for Grant Manager (Role 6).');
        return;
      }
      if (parseInt($('#faculty').val(), 10) <= 0) {
        showToast('bg-danger', 'Select a faculty for Grant Manager (Role 6).');
        return;
      }
    }

    var formData = $(this).serialize() + '&admin_manage=1';
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

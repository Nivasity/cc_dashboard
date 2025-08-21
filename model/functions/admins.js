$(document).ready(function () {
  InitiateDatatable('.table');

  let editing = false;

  $('#role, #school, #faculty').select2({ theme: 'bootstrap-5', dropdownParent: $('#newAdminModal') });

  function loadFaculties(schoolId, selected = 0) {
    const $fac = $('#faculty');
    $fac.empty();
    $fac.append('<option value="0">All Faculties</option>');
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
  });

  $('.editAdmin').on('click', function () {
    editing = true;
    $('#newAdminModalLabel').text('Edit Admin');
    $('#adminForm [name="admin_id"]').val($(this).data('id'));
    $('#adminForm [name="first_name"]').val($(this).data('first'));
    $('#adminForm [name="last_name"]').val($(this).data('last'));
    $('#adminForm [name="email"]').val($(this).data('email'));
    $('#adminForm [name="phone"]').val($(this).data('phone'));
    $('#adminForm [name="gender"]').val($(this).data('gender'));
    $('#role').val($(this).data('role')).trigger('change');
    const school = $(this).data('school') || 0;
    const faculty = $(this).data('faculty') || 0;
    $('#school').val(school).trigger('change');
    loadFaculties(school, faculty);
    $('#adminForm [name="password"]').val('');
    $('#password_field').hide();

    const modal = new bootstrap.Modal(document.getElementById('newAdminModal'));
    modal.show();
    editing = false;
  });

  $('#school').on('change', function () {
    if (editing) return;
    loadFaculties($(this).val(), 0);
  });

  $('#adminForm').on('submit', function (e) {
    e.preventDefault();
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

  $('.deleteAdmin').on('click', function () {
    if (confirm('Are you sure you want to delete this admin?')) {
      const id = $(this).data('id');
      $.ajax({
        url: 'model/admin.php',
        method: 'POST',
        data: { admin_delete: 1, admin_id: id },
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
    }
  });
});

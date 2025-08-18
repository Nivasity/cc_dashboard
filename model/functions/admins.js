$(document).ready(function () {
  InitiateDatatable('.table');

  $('#role, #school').select2({ theme: 'bootstrap-5', dropdownParent: $('#newAdminModal') });

  $('.new_formBtn').on('click', function () {
    $('#adminForm')[0].reset();
    $('#adminForm [name="admin_id"]').val(0);
    $('#newAdminModalLabel').text('Add New Admin');
    $('#role').val($('#role option:first').val()).trigger('change');
    $('#school').val('0').trigger('change');
  });

  $('.editAdmin').on('click', function () {
    $('#newAdminModalLabel').text('Edit Admin');
    $('#adminForm [name="admin_id"]').val($(this).data('id'));
    $('#adminForm [name="first_name"]').val($(this).data('first'));
    $('#adminForm [name="last_name"]').val($(this).data('last'));
    $('#adminForm [name="email"]').val($(this).data('email'));
    $('#adminForm [name="phone"]').val($(this).data('phone'));
    $('#adminForm [name="gender"]').val($(this).data('gender'));
    $('#role').val($(this).data('role')).trigger('change');
    $('#school').val($(this).data('school')).trigger('change');
    $('#adminForm [name="password"]').val('');

    const modal = new bootstrap.Modal(document.getElementById('newAdminModal'));
    modal.show();
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

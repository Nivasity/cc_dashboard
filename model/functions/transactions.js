$(document).ready(function () {
  var adminRole = window.adminRole || 0;
  var adminSchool = window.adminSchool || 0;
  var adminFaculty = window.adminFaculty || 0;
  var $manualModal = $('#manualTransactionModal');
  var $manualForm = $('#manualTransactionForm');
  var $manualAlert = $('#manualTransactionAlert');
  var $manualEmail = $('#manualUserEmail');
  var $manualDetails = $('#manualUserDetails');
  var $manualSelect = $('#manualMaterialSelect');
  var $manualSummary = $('#manualMaterialSummary');
  var $manualRef = $('#manualTransactionRef');
  var $manualSubmit = $('#manualTransactionSubmit');
  var manualSubmitText = $manualSubmit.length ? $manualSubmit.html() : '';
  var selectedUser = null;
  var emailLookupTimer = null;
  var emailLookupRequest = null;
  var materialsRequest = null;

  InitiateDatatable('.table');
  $('#school, #faculty, #dept').select2({ theme: 'bootstrap-5', width: '100%' });
  if ($manualSelect.length) {
    $manualSelect.prop('disabled', true);
  }

  function showManualAlert(color, message) {
    if (!$manualAlert.length) return;
    var classes = 'alert-success alert-danger alert-warning alert-info alert-secondary';
    if (!message) {
      $manualAlert.addClass('d-none').removeClass(classes);
      return;
    }
    $manualAlert.removeClass('d-none').removeClass(classes).addClass('alert-' + color).text(message);
  }

  function renderUserDetails(user) {
    if (!$manualDetails.length) return;
    if (!user) {
      $manualDetails.empty();
      return;
    }

    var fullName = (user.first_name || '') + ' ' + (user.last_name || '');
    var matric = user.matric_no ? '<span class="fw-semibold">Matric:</span> ' + user.matric_no : '';
    var phone = user.phone ? '<span class="fw-semibold">Phone:</span> ' + user.phone : '';
    var dept = user.dept_name ? '<span class="fw-semibold">Department:</span> ' + user.dept_name : '';
    var faculty = user.faculty_name ? '<span class="fw-semibold">Faculty:</span> ' + user.faculty_name : '';
    var school = user.school_name ? '<span class="fw-semibold">School:</span> ' + user.school_name : '';
    var status = user.status ? '<span class="badge bg-label-' + (user.status === 'active' ? 'success' : 'secondary') + '">' + user.status.charAt(0).toUpperCase() + user.status.slice(1) + '</span>' : '';

    var detailsHtml = '<div class="card border">' +
      '<div class="card-body py-3">' +
      '<h6 class="mb-1">' + (fullName.trim() || user.email) + ' ' + status + '</h6>' +
      '<p class="mb-1"><span class="fw-semibold">Email:</span> ' + user.email + '</p>' +
      '<div class="small text-muted d-flex flex-column gap-1">' +
      (matric ? '<span>' + matric + '</span>' : '') +
      (phone ? '<span>' + phone + '</span>' : '') +
      (school ? '<span>' + school + '</span>' : '') +
      (faculty ? '<span>' + faculty + '</span>' : '') +
      (dept ? '<span>' + dept + '</span>' : '') +
      '</div>' +
      '</div>' +
      '</div>';

    $manualDetails.html(detailsHtml);
  }

  function renderUserMessage(color, message) {
    if (!$manualDetails.length) return;
    if (!message) {
      $manualDetails.empty();
      return;
    }
    var classes = {
      info: 'info',
      danger: 'danger',
      warning: 'warning',
      success: 'success'
    };
    var tone = classes[color] || 'secondary';
    var alertHtml = '<div class="alert alert-' + tone + ' mb-0">' + message + '</div>';
    $manualDetails.html(alertHtml);
  }

  function updateManualSummary() {
    if (!$manualSummary.length) return;
    var selectedOptions = $manualSelect.find('option:selected');
    if (!selectedOptions.length) {
      $manualSummary.text('');
      return;
    }
    var total = 0;
    selectedOptions.each(function () {
      total += Number($(this).data('price') || 0);
    });
    var summaryText = 'Selected ' + selectedOptions.length + ' material' + (selectedOptions.length > 1 ? 's' : '') +
      ' • Total amount: ₦ ' + total.toLocaleString();
    $manualSummary.text(summaryText);
  }

  function updateManualSubmitState() {
    if (!$manualSubmit.length) return;
    var hasUser = selectedUser && selectedUser.id;
    var hasMaterials = ($manualSelect.val() || []).length > 0;
    var hasRef = $manualRef.val().trim().length > 0;
    $manualSubmit.prop('disabled', !(hasUser && hasMaterials && hasRef));
  }

  function loadManualOptions(userSchoolId) {
    if (!$manualSelect.length) return;
    if (materialsRequest && materialsRequest.readyState !== 4) {
      materialsRequest.abort();
    }

    $manualSelect.empty();
    updateManualSummary();

    var schoolId = Number(userSchoolId || 0);
    if (!schoolId) {
      $manualSelect.prop('disabled', true);
      showManualAlert('info', 'Enter a user email to load course materials.');
      updateManualSubmitState();
      return;
    }

    showManualAlert('info', 'Loading course materials...');
    $manualSelect.prop('disabled', true);

    materialsRequest = $.ajax({
      url: 'model/transactions.php',
      method: 'GET',
      data: {
        fetch: 'materials',
        user_school: schoolId
      },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success' && Array.isArray(res.materials)) {
          res.materials.forEach(function (material) {
            var label = material.title + ' - ' + material.course_code + ' | Code: ' + material.code + ' | ID: ' + material.id;
            var option = new Option(label, material.id, false, false);
            $(option).data('price', Number(material.price) || 0);
            $manualSelect.append(option);
          });
          $manualSelect.prop('disabled', false);
          $manualSelect.trigger('change');
          if (res.materials.length === 0) {
            showManualAlert('warning', res.message || 'No course materials were found for this user\'s school.');
          } else {
            showManualAlert(null, null);
          }
        } else {
          $manualSelect.prop('disabled', true);
          showManualAlert('danger', res.message || 'Unable to load materials.');
        }
        updateManualSummary();
        updateManualSubmitState();
      },
      error: function () {
        $manualSelect.prop('disabled', true);
        showManualAlert('danger', 'Failed to load materials. Please try again.');
        updateManualSummary();
        updateManualSubmitState();
      }
    });
  }

  function fetchUserDetails(email) {
    if (!$manualEmail.length) return;
    if (emailLookupRequest && emailLookupRequest.readyState !== 4) {
      emailLookupRequest.abort();
    }
    if (!email) {
      selectedUser = null;
      renderUserDetails(null);
      renderUserMessage(null, null);
      showManualAlert(null, null);
      loadManualOptions(null);
      updateManualSubmitState();
      return;
    }
    renderUserDetails(null);
    renderUserMessage('info', 'Looking up user details...');
    selectedUser = null;
    updateManualSubmitState();
    emailLookupRequest = $.ajax({
      url: 'model/transactions.php',
      method: 'GET',
      data: { fetch: 'user_details', email: email },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success' && res.user) {
          selectedUser = res.user;
          renderUserDetails(res.user);
          showManualAlert(null, null);
          loadManualOptions(res.user.school);
        } else {
          selectedUser = null;
          renderUserDetails(null);
          var errorMessage = res.message || 'User not found for the supplied email.';
          renderUserMessage('danger', errorMessage);
          if (typeof showToast === 'function') {
            showToast('bg-danger', errorMessage);
          }
          loadManualOptions(null);
        }
        updateManualSubmitState();
      },
      error: function () {
        selectedUser = null;
        renderUserDetails(null);
        var errorMessage = 'Unable to fetch user details. Please try again.';
        renderUserMessage('danger', errorMessage);
        if (typeof showToast === 'function') {
          showToast('bg-danger', errorMessage);
        }
        loadManualOptions(null);
        updateManualSubmitState();
      }
    });
  }

  function resetManualForm() {
    if (!$manualForm.length) return;
    $manualForm[0].reset();
    if ($manualSelect.length) {
      $manualSelect.val([]).trigger('change');
    }
    renderUserDetails(null);
    renderUserMessage(null, null);
    selectedUser = null;
    showManualAlert(null, null);
    $manualSummary.text('');
    updateManualSubmitState();
  }

  $manualSelect.on('change', function () {
    updateManualSummary();
    updateManualSubmitState();
  });

  $manualRef.on('input', function () {
    updateManualSubmitState();
  });

  $manualEmail.on('input', function () {
    var email = $(this).val().trim();
    if (emailLookupTimer) {
      clearTimeout(emailLookupTimer);
    }
    emailLookupTimer = setTimeout(function () {
      fetchUserDetails(email);
    }, 400);
  });

  $manualModal.on('show.bs.modal', function () {
    resetManualForm();
    loadManualOptions(null);
  });

  $manualModal.on('hidden.bs.modal', function () {
    resetManualForm();
  });

  $manualForm.on('submit', function (e) {
    e.preventDefault();
    if (!$manualSubmit.length) return;
    if ($manualSubmit.prop('disabled')) return;
    var payload = {
      action: 'create_manual_transaction',
      email: $manualEmail.val().trim(),
      transaction_ref: $manualRef.val().trim(),
      manuals: $manualSelect.val() || []
    };

    $.ajax({
      url: 'model/transactions.php',
      method: 'POST',
      data: payload,
      dataType: 'json',
      traditional: true,
      beforeSend: function () {
        $manualSubmit.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving...');
      },
      success: function (res) {
        if (res.status === 'success') {
          showManualAlert('success', res.message || 'Transaction recorded successfully.');
          if (typeof showToast === 'function') {
            showToast('bg-success', res.message || 'Manual transaction recorded successfully.');
          }
          fetchTransactions();
          setTimeout(function () {
            $manualModal.modal('hide');
          }, 600);
        } else {
          var message = res.message || 'Failed to record transaction.';
          showManualAlert('danger', message);
          if (typeof showToast === 'function') {
            showToast('bg-danger', message);
          }
        }
      },
      error: function () {
        var message = 'Network error. Please try again.';
        showManualAlert('danger', message);
      },
      complete: function () {
        $manualSubmit.prop('disabled', false).html(manualSubmitText);
        updateManualSubmitState();
      }
    });
  });

  function fetchFaculties(schoolId) {
    if (adminRole == 5) {
      schoolId = adminSchool;
    }
    $.ajax({
      url: 'model/transactions.php',
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
      url: 'model/transactions.php',
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

  function fetchTransactions() {
    var schoolId = adminRole == 5 ? adminSchool : $('#school').val();
    var facultyId = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $('#faculty').val();
    var deptId = $('#dept').val();

    $.ajax({
      url: 'model/transactions.php',
      method: 'GET',
      data: { fetch: 'transactions', school: schoolId, faculty: facultyId, dept: deptId },
      dataType: 'json',
      success: function (res) {
        if ($.fn.dataTable.isDataTable('.table')) {
          var table = $('.table').DataTable();
          table.clear().draw().destroy();
        }
        var tbody = $('.table tbody');
        tbody.empty();
        if (res.status === 'success' && res.transactions) {
          $.each(res.transactions, function (i, trn) {
            var badgeClass = 'secondary';
            if (trn.status === 'successful') badgeClass = 'success';
            else if (trn.status === 'pending') badgeClass = 'warning';
            else badgeClass = 'danger';
            var row = '<tr>' +
              '<td class="fw-bold">#' + trn.ref_id + '</td>' +
              '<td><span class="text-uppercase text-primary">' + trn.student + '</span><br>Matric no: ' + trn.matric + '</td>' +
              '<td>' + trn.materials + '</td>' +
              '<td class="fw-bold">₦ ' + Number(trn.amount).toLocaleString() + '</td>' +
              '<td>' + trn.date + '<br>' + trn.time + '</td>' +
              '<td><span class="fw-bold badge bg-label-' + badgeClass + '">' + trn.status.charAt(0).toUpperCase() + trn.status.slice(1) + '</span></td>' +
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
    fetchTransactions();
  });

  $('#faculty').on('change', function () {
    var schoolId = adminRole == 5 ? adminSchool : $('#school').val();
    var facultyId = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $(this).val();
    fetchDepts(schoolId, facultyId);
    fetchTransactions();
  });

  $('#dept').on('change', function () {
    fetchTransactions();
  });

  $('#filterForm').on('submit', function (e) {
    e.preventDefault();
    fetchTransactions();
  });

  // Download CSV based on current filters using jQuery AJAX
  $('#downloadCsv').on('click', function () {
    var $btn = $(this);
    var originalHtml = $btn.html();
    var schoolId = adminRole == 5 ? adminSchool : $('#school').val();
    var facultyId = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $('#faculty').val();
    var deptId = $('#dept').val();

    $.ajax({
      url: 'model/transactions.php',
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
        var filename = 'transactions_' + new Date().toISOString().replace(/[-:T]/g, '').slice(0, 15) + '.csv';
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

  fetchFaculties(adminRole == 5 ? adminSchool : $('#school').val());
  fetchDepts(adminRole == 5 ? adminSchool : $('#school').val(), (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $('#faculty').val());
  fetchTransactions();
});

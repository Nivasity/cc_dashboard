$(document).ready(function () {
  var adminRole = window.adminRole || 0;
  var adminSchool = window.adminSchool || 0;
  var adminFaculty = window.adminFaculty || 0;
  var $manualModal = $('#manualTransactionModal');
  var $manualForm = $('#manualTransactionForm');
  var $manualAlert = $('#manualTransactionAlert');
  var $manualEmail = $('#manualUserEmail');
  var $manualFeedback = $('#manualUserFeedback');
  var $manualDetails = $('#manualUserDetails');
  var $manualSelect = $('#manualMaterialSelect');
  var $manualSummary = $('#manualMaterialSummary');
  var $manualRef = $('#manualTransactionRef');
  var $manualSubmit = $('#manualTransactionSubmit');
  var $manualStatus = $('#manualTransactionStatus');
  var $refundGroup = $('#manualRefundAmountGroup');
  var $refundAmount = $('#manualRefundAmount');
  var $deleteModal = $('#deleteTransactionModal');
  var $deleteMessage = $('#deleteTransactionMessage');
  var $confirmDelete = $('#confirmDeleteTransaction');
  var manualSubmitText = $manualSubmit.length ? $manualSubmit.html() : '';
  var selectedUser = null;
  var emailLookupTimer = null;
  var emailLookupRequest = null;
  var materialsRequest = null;
  var feedbackTimer = null;
  var lastLookupEmail = '';
  var deleteRefId = null;

  InitiateDatatable('.table');
  $('#school, #faculty, #dept, #dateRange').select2({ theme: 'bootstrap-5', width: '100%' });
  if ($manualSelect.length) {
    $manualSelect.select2({
      theme: 'bootstrap-5',
      width: '100%',
      placeholder: 'Select course materials',
      allowClear: true,
      closeOnSelect: false,
      dropdownParent: $manualModal
    });
    $manualSelect.prop('disabled', true).trigger('change.select2');
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

  function renderUserFeedback(color, message) {
    if (!$manualFeedback.length) return;
    if (feedbackTimer) {
      clearTimeout(feedbackTimer);
      feedbackTimer = null;
    }
    if (!message) {
      $manualFeedback.addClass('d-none').empty();
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
    $manualFeedback.removeClass('d-none').html(alertHtml);
  }

  function updateManualSummary() {
    if (!$manualSummary.length) return;
    var status = ($manualStatus.val() || 'successful');
    if (status === 'refunded') {
      $manualSummary.text('');
      return;
    }
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

  function requiredRefPrefix() {
    return selectedUser && selectedUser.id ? ('nivas_' + selectedUser.id) : '';
  }

  function isValidRefFormat() {
    var ref = ($manualRef.val() || '').trim().toLowerCase();
    var prefix = requiredRefPrefix().toLowerCase();
    if (!ref || !prefix) return false;
    return ref.startsWith(prefix);
  }

  function updateManualSubmitState() {
    if (!$manualSubmit.length) return;
    var hasUser = selectedUser && selectedUser.id;
    var hasRef = $manualRef.val().trim().length > 0;
    var refOk = isValidRefFormat();
    var status = ($manualStatus.val() || 'successful');
    var ok = false;
    if (status === 'refunded') {
      var amt = Number($refundAmount.val() || 0);
      ok = hasUser && hasRef && refOk && amt > 0;
    } else {
      var hasMaterials = ($manualSelect.val() || []).length > 0;
      ok = hasUser && hasMaterials && hasRef && refOk;
    }
    $manualSubmit.prop('disabled', !ok);
  }

  function loadManualOptions(userSchoolId) {
    if (!$manualSelect.length) return;
    if ($manualStatus.length && ($manualStatus.val() || 'successful') === 'refunded') {
      $manualSelect.empty().prop('disabled', true).trigger('change.select2');
      updateManualSummary();
      updateManualSubmitState();
      return;
    }
    if (materialsRequest && materialsRequest.readyState !== 4) {
      materialsRequest.abort();
    }

    $manualSelect.empty().trigger('change.select2');
    updateManualSummary();

    var schoolId = Number(userSchoolId || 0);
    if (!schoolId) {
      $manualSelect.prop('disabled', true).trigger('change.select2');
      showManualAlert('info', 'Enter a user email to load course materials.');
      updateManualSubmitState();
      return;
    }

    showManualAlert('info', 'Loading course materials...');
    $manualSelect.prop('disabled', true).trigger('change.select2');

    materialsRequest = $.ajax({
      url: 'model/transactions_materials.php',
      method: 'GET',
      data: {
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
          $manualSelect.prop('disabled', false).trigger('change.select2');
          if (res.materials.length === 0) {
            showManualAlert('warning', res.message || 'No course materials were found for this user\'s school.');
          } else {
            showManualAlert(null, null);
          }
        } else {
          $manualSelect.prop('disabled', true).trigger('change.select2');
          showManualAlert('danger', res.message || 'Unable to load materials.');
        }
        updateManualSummary();
        updateManualSubmitState();
      },
      error: function () {
        $manualSelect.prop('disabled', true).trigger('change.select2');
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
      renderUserFeedback(null, null);
      showManualAlert(null, null);
      loadManualOptions(null);
      updateManualSubmitState();
      return;
    }
    renderUserDetails(null);
    renderUserFeedback('info', '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Looking up user details...');
    selectedUser = null;
    lastLookupEmail = (email || '').toLowerCase();
    updateManualSubmitState();
    emailLookupRequest = $.ajax({
      url: 'model/transactions_user_details.php',
      method: 'GET',
      data: { email: email },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success' && res.user) {
          selectedUser = res.user;
          renderUserDetails(res.user);
          renderUserFeedback('success', 'User found. Materials list updated for their school.');
          if (res.user.email) {
            feedbackTimer = setTimeout(function () {
              if ((res.user.email || '').toLowerCase() === lastLookupEmail) {
                renderUserFeedback(null, null);
              }
            }, 3000);
          }
          // Re-validate reference prefix using the found user id
          if ($manualRef.val().trim().length && !isValidRefFormat()) {
            showManualAlert('warning', 'Reference must start with "' + requiredRefPrefix() + '"');
          } else {
            showManualAlert(null, null);
          }
          loadManualOptions(res.user.school);
        } else {
          selectedUser = null;
          var errorMessage = res.message || 'User not found for the supplied email.';
          renderUserDetails(null);
          renderUserFeedback('danger', errorMessage);
          if (typeof showToast === 'function') {
            showToast('bg-danger', errorMessage);
          }
          loadManualOptions(null);
        }
        updateManualSubmitState();
      },
      error: function () {
        selectedUser = null;
        var errorMessage = 'Unable to fetch user details. Please try again.';
        renderUserDetails(null);
        renderUserFeedback('danger', errorMessage);
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
      $manualSelect.val([]).trigger('change.select2');
    }
    if ($manualStatus.length) {
      $manualStatus.val('successful').trigger('change');
    }
    if ($refundAmount.length) {
      $refundAmount.val('');
    }
    renderUserDetails(null);
    renderUserFeedback(null, null);
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
    var hasUser = selectedUser && selectedUser.id;
    if (hasUser) {
      if (!isValidRefFormat()) {
        showManualAlert('warning', 'Reference must start with "' + requiredRefPrefix() + '"');
      } else {
        showManualAlert(null, null);
      }
    }
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
    if (!isValidRefFormat()) {
      showManualAlert('danger', 'Reference must start with "' + requiredRefPrefix() + '"');
      return;
    }
    var status = ($manualStatus.val() || 'successful');
    var payload = {
      action: 'create_manual_transaction',
      status: status,
      email: $manualEmail.val().trim(),
      transaction_ref: $manualRef.val().trim(),
      manuals: status === 'successful' ? ($manualSelect.val() || []) : [],
      amount: status === 'refunded' ? Number($refundAmount.val() || 0) : undefined,
      user_id: selectedUser ? selectedUser.id : null
    };

    $.ajax({
      url: 'model/transactions.php',
      method: 'POST',
      data: payload,
      dataType: 'json',
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

  // Toggle UI on status change
  $(document).on('change', '#manualTransactionStatus', function () {
    var status = ($manualStatus.val() || 'successful');
    if (status === 'refunded') {
      $refundGroup.removeClass('d-none');
      $manualSelect.closest('.mb-4').addClass('d-none');
      $manualSelect.val([]).trigger('change.select2');
    } else {
      $refundGroup.addClass('d-none');
      $manualSelect.closest('.mb-4').removeClass('d-none');
      if (selectedUser && selectedUser.school) {
        loadManualOptions(selectedUser.school);
      }
    }
    updateManualSummary();
    updateManualSubmitState();
  });

  // Amount change should re-validate
  $(document).on('input', '#manualRefundAmount', function () { updateManualSubmitState(); });

  function fetchFaculties(schoolId) {
    if (adminRole == 5) {
      schoolId = adminSchool;
    }
    $.ajax({
      url: 'model/transactions_faculties.php',
      method: 'GET',
      data: { school: schoolId },
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
      url: 'model/transactions_departments.php',
      method: 'GET',
      data: { school: schoolId, faculty: facultyId },
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
    var dateRange = $('#dateRange').val() || '7';
    var startDate = $('#startDate').val();
    var endDate = $('#endDate').val();

    // Fetch statistics
    $.ajax({
      url: 'model/transactions_stats.php',
      method: 'GET',
      data: { 
        school: schoolId, 
        faculty: facultyId, 
        dept: deptId,
        date_range: dateRange,
        start_date: startDate,
        end_date: endDate
      },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success' && res.stats) {
          $('#totalCount').text(res.stats.count.toLocaleString());
          $('#totalSum').text('₦ ' + res.stats.sum.toLocaleString());
          $('#averagePaid').text('₦ ' + Math.round(res.stats.average).toLocaleString());
        }
      }
    });

    $.ajax({
      url: 'model/transactions_list.php',
      method: 'GET',
      data: { 
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
        if (res.status === 'success' && res.transactions) {
          var canDelete = (adminRole === 1 || adminRole === 2 || adminRole === 4);

          $.each(res.transactions, function (i, trn) {
            var badgeClass = 'secondary';
            if (trn.status === 'successful') badgeClass = 'success';
            else if (trn.status === 'pending') badgeClass = 'warning';
            else if (trn.status === 'refunded') badgeClass = 'secondary';
            else badgeClass = 'danger';

            var row = '<tr>' +
              '<td class="fw-bold">#' + trn.ref_id + '</td>' +
              '<td><span class="text-uppercase text-primary">' + trn.student + '</span><br>Matric no: ' + trn.matric + '</td>' +
              '<td>' + trn.materials + '</td>' +
              '<td class="fw-bold">₦ ' + Number(trn.amount).toLocaleString() + '</td>' +
              '<td>' + trn.date + '<br>' + trn.time + '</td>' +
              '<td><span class="fw-bold badge bg-label-' + badgeClass + '">' +
              trn.status.charAt(0).toUpperCase() + trn.status.slice(1) + '</span></td>';

            console.log(canDelete, trn, adminRole);
            if (canDelete) {
              row += '<td><button type="button" class="btn btn-sm btn-outline-danger delete-transaction" data-ref="' +
                trn.ref_id + '"><i class="bx bx-trash"></i></button></td>';
            }

            row += '</tr>';
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

  // Handle date range change
  $('#dateRange').on('change', function () {
    var dateRange = $(this).val();
    if (dateRange === 'custom') {
      $('#customDateRange').removeClass('d-none');
    } else {
      $('#customDateRange').addClass('d-none');
      fetchTransactions();
    }
  });

  // Handle custom date change
  $('#startDate, #endDate').on('change', function () {
    var startDate = $('#startDate').val();
    var endDate = $('#endDate').val();
    if (startDate && endDate) {
      fetchTransactions();
    }
  });

  // Handle delete transaction (roles 1, 2, 4 only)
  $(document).on('click', '.delete-transaction', function () {
    if (!(adminRole === 1 || adminRole === 2 || adminRole === 4)) {
      if (typeof showToast === 'function') {
        showToast('bg-danger', 'You are not allowed to delete transactions.');
      }
      return;
    }
    var ref = $(this).data('ref');
    deleteRefId = ref || null;
    if ($deleteMessage.length && deleteRefId) {
      $deleteMessage.text(
        'Are you sure you want to delete transaction \"' + deleteRefId +
        '\"? This will also remove all related course material purchase records.'
      );
    }
    if ($deleteModal.length) {
      var modalEl = $deleteModal.get(0);
      var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
    }
  });

  $confirmDelete.on('click', function () {
    if (!deleteRefId) {
      if (typeof showToast === 'function') {
        showToast('bg-danger', 'No transaction selected.');
      }
      return;
    }
    var ref = deleteRefId;
    deleteRefId = null;
    var modalEl = $deleteModal.get(0);
    var modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;

    $.ajax({
      url: 'model/transactions_delete.php',
      method: 'POST',
      dataType: 'json',
      data: { ref_id: ref },
      success: function (res) {
        if (typeof showToast === 'function') {
          showToast(res.status === 'success' ? 'bg-success' : 'bg-danger', res.message || 'Done');
        }
        if (res.status === 'success') {
          fetchTransactions();
          if (modal) modal.hide();
        }
      },
      error: function () {
        if (typeof showToast === 'function') {
          showToast('bg-danger', 'Failed to delete transaction. Please try again.');
        }
      }
    });
  });

  // Download CSV based on current filters using jQuery AJAX
  $('#downloadCsv').on('click', function () {
    var $btn = $(this);
    var originalHtml = $btn.html();
    var schoolId = adminRole == 5 ? adminSchool : $('#school').val();
    var facultyId = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : $('#faculty').val();
    var deptId = $('#dept').val();
    var dateRange = $('#dateRange').val() || '7';
    var startDate = $('#startDate').val();
    var endDate = $('#endDate').val();

    $.ajax({
      url: 'model/transactions_download.php',
      method: 'GET',
      data: { 
        school: schoolId, 
        faculty: facultyId, 
        dept: deptId,
        date_range: dateRange,
        start_date: startDate,
        end_date: endDate
      },
      xhr: function () {
        var xhr = new window.XMLHttpRequest();
        xhr.responseType = 'blob';
        return xhr;
      },
      xhrFields: { responseType: 'blob' },
      beforeSend: function () {
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Downloading...');
      },
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

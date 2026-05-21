$(document).ready(function () {
  var $school = $('#bp_school');
  var $faculty = $('#bp_faculty');
  var $dept = $('#bp_dept');
  var $manual = $('#bp_manual');
  var $alert = $('#bp_alert');
  var $studentsFile = $('#bp_students_file');
  var $price = $('#bp_price');
  var $students = $('#bp_students');
  var $matched = $('#bp_matched');
  var $unmatched = $('#bp_unmatched');
  var $total = $('#bp_total');
  var $txref = $('#bp_txref');
  var $submit = $('#bp_submit');

  var $manualSchool = $('#mb_school');
  var $manualFaculty = $('#mb_faculty');
  var $manualDept = $('#mb_dept');
  var $manualMaterial = $('#mb_manual');
  var $manualAlert = $('#mb_alert');
  var $manualStudentsFile = $('#mb_students_file');
  var $manualReceiptFile = $('#mb_receipt_file');
  var $manualPaidByName = $('#mb_paid_by_name');
  var $manualPaidByPhone = $('#mb_paid_by_phone');
  var $manualReason = $('#mb_payment_reason');
  var $manualPrice = $('#mb_price');
  var $manualStudents = $('#mb_students');
  var $manualMatched = $('#mb_matched');
  var $manualUnmatched = $('#mb_unmatched');
  var $manualTotal = $('#mb_total');
  var $manualSubmit = $('#mb_submit');

  var $filterSchool = $('#filter_school');
  var $filterFaculty = $('#filter_faculty');
  var $filterDept = $('#filter_dept');

  var createBatchModalEl = document.getElementById('createBatchModal');
  var createBatchModal = createBatchModalEl ? bootstrap.Modal.getOrCreateInstance(createBatchModalEl) : null;
  var manualBatchModalEl = document.getElementById('manualBatchModal');
  var manualBatchModal = manualBatchModalEl ? bootstrap.Modal.getOrCreateInstance(manualBatchModalEl) : null;

  function num(value) {
    return Number(value || 0);
  }

  function formatNumber(value) {
    return Number(value || 0).toLocaleString();
  }

  function showAlert($el, color, message) {
    var classes = 'alert-success alert-danger alert-warning alert-info alert-secondary';
    if (!$el || $el.length === 0) {
      return;
    }

    if (!message) {
      $el.addClass('d-none').removeClass(classes).text('');
      return;
    }

    $el.removeClass('d-none').removeClass(classes).addClass('alert-' + color).text(message);

    if (typeof showToast === 'function') {
      var toastClass = 'bg-secondary';
      if (color === 'success') {
        toastClass = 'bg-success';
      } else if (color === 'danger') {
        toastClass = 'bg-danger';
      } else if (color === 'warning') {
        toastClass = 'bg-warning';
      } else if (color === 'info') {
        toastClass = 'bg-info';
      }
      showToast(toastClass, message);
    }
  }

  function getScopedSchoolValue($schoolEl) {
    return adminRole === 5 ? adminSchool : num($schoolEl.val());
  }

  function getScopedFacultyValue($facultyEl) {
    return (adminRole === 5 && adminFaculty !== 0) ? adminFaculty : num($facultyEl.val());
  }

  function setPreviewFields(fields, data) {
    fields.price.val(formatNumber(data.price_per_student || 0));
    fields.students.val(formatNumber(data.student_count || 0));
    fields.matched.val(formatNumber(data.matched_count || 0));
    fields.unmatched.val(formatNumber(data.unmatched_count || 0));
    fields.total.val(data.total_amount || 0);
    if (fields.txref) {
      fields.txref.val(data.tx_ref || '');
    }
  }

  function resetPreview(fields) {
    fields.price.val('0');
    fields.students.val('0');
    fields.matched.val('0');
    fields.unmatched.val('0');
    fields.total.val('0');
    if (fields.txref) {
      fields.txref.val('');
    }
    fields.submit.prop('disabled', true);
  }

  function resetBatchSummary() {
    $('#batchItemsSummary').addClass('d-none');
    $('#bis_txref, #bis_source, #bis_status, #bis_manual, #bis_students, #bis_total, #bis_paid_by, #bis_created, #bis_reason').text('-');
    $('#bis_receipt_wrap').addClass('d-none');
    $('#bis_receipt_link').attr('href', '#');
  }

  function renderBatchSummary(batch) {
    resetBatchSummary();
    if (!batch) {
      return;
    }

    $('#batchItemsSummary').removeClass('d-none');
    $('#bis_txref').text(batch.tx_ref || '-');
    $('#bis_source').text(batch.gateway || '-');
    $('#bis_status').text(batch.status || '-');
    $('#bis_manual').text(batch.manual || '-');
    $('#bis_students').text(formatNumber(batch.total_students || 0));
    $('#bis_total').text(formatNumber(batch.total_amount || 0));
    $('#bis_created').text(batch.created_at || '-');
    $('#bis_reason').text(batch.payment_reason || '-');

    var paidByParts = [];
    if (batch.paid_by_name) {
      paidByParts.push(batch.paid_by_name);
    }
    if (batch.paid_by_phone) {
      paidByParts.push(batch.paid_by_phone);
    }
    $('#bis_paid_by').text(paidByParts.length ? paidByParts.join(' | ') : '-');

    if (batch.receipt_url) {
      $('#bis_receipt_wrap').removeClass('d-none');
      $('#bis_receipt_link').attr('href', batch.receipt_url);
    }
  }

  function resetCreateForm() {
    var defaultSchool = adminRole === 5 ? String(adminSchool) : (($school.find('option:first').val() || '0'));
    $school.val(defaultSchool).trigger('change.select2');
    if (!(adminRole === 5 && adminFaculty !== 0)) {
      $faculty.val('0').trigger('change.select2');
    }
    $dept.val('0').trigger('change.select2');
    $manual.val('0').trigger('change.select2');
    $studentsFile.val('');
    showAlert($alert, null, null);
    resetPreview({
      price: $price,
      students: $students,
      matched: $matched,
      unmatched: $unmatched,
      total: $total,
      txref: $txref,
      submit: $submit
    });
  }

  function resetManualForm() {
    var defaultSchool = adminRole === 5 ? String(adminSchool) : (($manualSchool.find('option:first').val() || '0'));
    $manualSchool.val(defaultSchool).trigger('change.select2');
    if (!(adminRole === 5 && adminFaculty !== 0)) {
      $manualFaculty.val('0').trigger('change.select2');
    }
    $manualDept.val('0').trigger('change.select2');
    $manualMaterial.val('0').trigger('change.select2');
    $manualStudentsFile.val('');
    $manualReceiptFile.val('');
    $manualPaidByName.val('');
    $manualPaidByPhone.val('');
    $manualReason.val('');
    showAlert($manualAlert, null, null);
    resetPreview({
      price: $manualPrice,
      students: $manualStudents,
      matched: $manualMatched,
      unmatched: $manualUnmatched,
      total: $manualTotal,
      submit: $manualSubmit
    });
  }

  function initSelect2() {
    $('#filter_school, #filter_faculty, #filter_dept').select2({ theme: 'bootstrap-5', width: '100%' });

    if (createBatchModalEl) {
      $('#bp_school, #bp_faculty, #bp_dept, #bp_manual').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#createBatchModal')
      });
    }

    if (manualBatchModalEl) {
      $('#mb_school, #mb_faculty, #mb_dept, #mb_manual').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#manualBatchModal')
      });
    }
  }

  function loadFaculties($el, schoolId, selected) {
    $.get('model/transactions_faculties.php', { school: schoolId }).done(function (res) {
      $el.empty();
      if (!(adminRole === 5 && adminFaculty !== 0)) {
        $el.append('<option value="0">All Faculties</option>');
      }
      if (res.status === 'success' && Array.isArray(res.faculties)) {
        res.faculties.forEach(function (faculty) {
          var sel = (selected && Number(selected) === Number(faculty.id)) ? ' selected' : '';
          $el.append('<option value="' + faculty.id + '"' + sel + '>' + faculty.name + '</option>');
        });
      }
      $el.trigger('change.select2');
    });
  }

  function loadDepts($el, schoolId, facultyId, allowAll) {
    $.get('model/transactions_departments.php', { school: schoolId, faculty: facultyId }).done(function (res) {
      $el.empty();
      if (allowAll) {
        $el.append('<option value="0">All Departments</option>');
      } else {
        $el.append('<option value="0">Select Department</option>');
      }
      if (res.status === 'success' && Array.isArray(res.departments)) {
        res.departments.forEach(function (dept) {
          $el.append('<option value="' + dept.id + '">' + dept.name + '</option>');
        });
      }
      $el.trigger('change.select2');
    });
  }

  function loadMaterials($schoolEl, $facultyEl, $deptEl, $manualEl) {
    var schoolId = getScopedSchoolValue($schoolEl);
    var facultyId = getScopedFacultyValue($facultyEl);
    var deptId = num($deptEl.val());
    $manualEl.empty().append('<option value="0">Select Material</option>');
    if (!schoolId || !deptId) {
      $manualEl.trigger('change.select2');
      return;
    }

    $.get('model/transactions_materials.php', { school: schoolId, faculty: facultyId, dept: deptId }).done(function (res) {
      if (res.status === 'success' && Array.isArray(res.materials)) {
        res.materials.forEach(function (material) {
          var label = material.title + ' - ' + material.course_code + ' | Code: ' + material.code + ' | ID: ' + material.id;
          $manualEl.append($('<option>').val(material.id).text(label).data('price', Number(material.price) || 0));
        });
      }
      $manualEl.trigger('change.select2');
    });
  }

  function updateGatewayPreview() {
    showAlert($alert, null, null);
    resetPreview({
      price: $price,
      students: $students,
      matched: $matched,
      unmatched: $unmatched,
      total: $total,
      txref: $txref,
      submit: $submit
    });

    var manualId = num($manual.val());
    var schoolId = getScopedSchoolValue($school);
    var deptId = num($dept.val());
    var file = $studentsFile[0] && $studentsFile[0].files ? $studentsFile[0].files[0] : null;
    if (!manualId || !schoolId || !deptId || !file) {
      return;
    }

    var formData = new FormData();
    formData.append('action', 'preview_batch_csv');
    formData.append('manual_id', manualId);
    formData.append('school', schoolId);
    formData.append('dept', deptId);
    formData.append('students_csv', file);

    $.ajax({
      url: 'model/batch_payments.php',
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false
    }).done(function (res) {
      if (res.status === 'success' && res.data) {
        setPreviewFields({
          price: $price,
          students: $students,
          matched: $matched,
          unmatched: $unmatched,
          total: $total,
          txref: $txref
        }, res.data);
        if (Number(res.data.student_count || 0) > 0) {
          $submit.prop('disabled', false);
        }
        if (Number(res.data.unmatched_count || 0) > 0) {
          showAlert($alert, 'warning', Number(res.data.unmatched_count || 0) + ' matric number(s) were not matched in users.');
        } else if (Number(res.data.duplicates_removed || 0) > 0) {
          showAlert($alert, 'info', Number(res.data.duplicates_removed || 0) + ' duplicate matric number(s) were ignored from the CSV.');
        }
      } else {
        showAlert($alert, 'danger', res.message || 'Unable to prepare preview.');
      }
    }).fail(function () {
      showAlert($alert, 'danger', 'Network error.');
    });
  }

  function updateManualPreview() {
    showAlert($manualAlert, null, null);
    resetPreview({
      price: $manualPrice,
      students: $manualStudents,
      matched: $manualMatched,
      unmatched: $manualUnmatched,
      total: $manualTotal,
      submit: $manualSubmit
    });

    var materialId = num($manualMaterial.val());
    var schoolId = getScopedSchoolValue($manualSchool);
    var deptId = num($manualDept.val());
    var file = $manualStudentsFile[0] && $manualStudentsFile[0].files ? $manualStudentsFile[0].files[0] : null;
    if (!materialId || !schoolId || !deptId || !file) {
      return;
    }

    var formData = new FormData();
    formData.append('action', 'preview_manual_batch_csv');
    formData.append('manual_id', materialId);
    formData.append('school', schoolId);
    formData.append('dept', deptId);
    formData.append('students_csv', file);

    $.ajax({
      url: 'model/batch_payments.php',
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false
    }).done(function (res) {
      if (res.status === 'success' && res.data) {
        setPreviewFields({
          price: $manualPrice,
          students: $manualStudents,
          matched: $manualMatched,
          unmatched: $manualUnmatched,
          total: $manualTotal
        }, res.data);
        if (Number(res.data.student_count || 0) > 0) {
          $manualSubmit.prop('disabled', false);
        }
        if (Number(res.data.unmatched_count || 0) > 0) {
          showAlert($manualAlert, 'warning', Number(res.data.unmatched_count || 0) + ' student row(s) were not matched in users. Matched students will receive access immediately, unmatched rows remain on file only.');
        } else if (Number(res.data.duplicates_removed || 0) > 0) {
          showAlert($manualAlert, 'info', Number(res.data.duplicates_removed || 0) + ' duplicate matric number(s) were ignored from the CSV.');
        }
      } else {
        showAlert($manualAlert, 'danger', res.message || 'Unable to prepare manual preview.');
      }
    }).fail(function () {
      showAlert($manualAlert, 'danger', 'Network error.');
    });
  }

  function renderBatchesTable(items) {
    var $tbody = $('#batchesTable tbody');
    if ($.fn.dataTable.isDataTable('#batchesTable')) {
      $('#batchesTable').DataTable().clear().draw().destroy();
    }
    $tbody.empty();

    (items || []).forEach(function (batch) {
      var badge = 'secondary';
      if (batch.status === 'paid' || batch.status === 'successful') {
        badge = 'success';
      } else if (batch.status === 'pending') {
        badge = 'warning';
      } else if (batch.status === 'failed') {
        badge = 'danger';
      }

      var sourceBadge = (batch.gateway || '').toUpperCase() === 'MANUAL' ? 'secondary' : 'info';
      var actionHtml = '<button type="button" class="btn btn-sm btn-outline-primary view-items" data-id="' + batch.id + '">View</button>';
      if (batch.status === 'pending') {
        actionHtml += ' <button type="button" class="btn btn-sm btn-warning batch-create-link ms-1" data-id="' + batch.id + '" data-txref="' + batch.tx_ref + '" data-amount="' + Number(batch.total_amount || 0) + '">Create Payment Link</button>';
        actionHtml += ' <button type="button" class="btn btn-sm btn-info batch-verify-payment ms-1" data-id="' + batch.id + '" data-txref="' + batch.tx_ref + '">Verify Payment</button>';
      }

      var row = '<tr>' +
        '<td class="fw-semibold">' + (batch.tx_ref || '') + '</td>' +
        '<td><span class="badge bg-label-' + sourceBadge + '">' + (batch.gateway || 'PAYSTACK') + '</span></td>' +
        '<td>' + (batch.manual || '') + '</td>' +
        '<td>' + (batch.dept || '') + '</td>' +
        '<td>' + (batch.school || '') + '</td>' +
        '<td>' + formatNumber(batch.total_students || 0) + '</td>' +
        '<td>' + formatNumber(batch.total_amount || 0) + '</td>' +
        '<td><span class="badge bg-label-' + badge + '">' + (batch.status || '') + '</span></td>' +
        '<td>' + (batch.created_at || '') + '</td>' +
        '<td>' + actionHtml + '</td>' +
        '</tr>';
      $tbody.append(row);
    });

    $('#batchesTable').DataTable();
  }

  function fallbackCopy(text, $btn) {
    var $tmp = $('<textarea>').val(text).css({ position: 'absolute', left: '-9999px' });
    $('body').append($tmp);
    $tmp.select();
    try {
      var ok = document.execCommand('copy');
      if (ok) {
        $btn.text('Copied');
        setTimeout(function () { $btn.text('Copy link'); }, 2000);
      } else {
        alert('Copy failed. Select and copy the link manually.');
      }
    } catch (error) {
      alert('Copy not supported.');
    }
    $tmp.remove();
  }

  function loadBatches() {
    var schoolId = adminRole === 5 ? adminSchool : num($filterSchool.val());
    var facultyId = (adminRole === 5 && adminFaculty !== 0) ? adminFaculty : num($filterFaculty.val());
    var deptId = num($filterDept.val());
    $.get('model/batch_payments_list.php', { school: schoolId, faculty: facultyId, dept: deptId }).done(function (res) {
      if (res.status === 'success') {
        renderBatchesTable(res.batches || []);
      }
    });
  }

  $('#batchCreateForm').on('submit', function (event) {
    event.preventDefault();
    if ($submit.prop('disabled')) {
      return;
    }

    var totalAmount = num($total.val());
    if (totalAmount <= 0) {
      showAlert($alert, 'danger', 'Total amount must be greater than 0.');
      return;
    }

    var file = $studentsFile[0] && $studentsFile[0].files ? $studentsFile[0].files[0] : null;
    if (!file) {
      showAlert($alert, 'danger', 'Upload the CSV file containing student matric numbers.');
      return;
    }

    var payload = new FormData();
    payload.append('action', 'create_batch');
    payload.append('manual_id', num($manual.val()));
    payload.append('school', getScopedSchoolValue($school));
    payload.append('dept', num($dept.val()));
    payload.append('tx_ref', ($txref.val() || '').trim());
    payload.append('total_amount', totalAmount);
    payload.append('students_csv', file);

    $submit.prop('disabled', true).text('Creating...');
    $.ajax({
      url: 'model/batch_payments.php',
      method: 'POST',
      data: payload,
      processData: false,
      contentType: false
    }).done(function (res) {
      if (res.status === 'success') {
        var successMessage = 'Batch created. tx_ref: ' + ((res.data && res.data.tx_ref) || '');
        if (res.data && Number(res.data.unmatched_count || 0) > 0) {
          successMessage += ' Unmatched matric-only items: ' + Number(res.data.unmatched_count || 0) + '.';
        }
        showAlert($alert, 'success', successMessage);
        if (createBatchModal) {
          createBatchModal.hide();
        }
        resetCreateForm();
        loadBatches();
      } else {
        showAlert($alert, 'danger', res.message || 'Failed to create batch.');
      }
    }).fail(function () {
      showAlert($alert, 'danger', 'Network error.');
    }).always(function () {
      $submit.prop('disabled', false).text('Create Batch');
    });
  });

  $('#manualBatchForm').on('submit', function (event) {
    event.preventDefault();
    if ($manualSubmit.prop('disabled')) {
      return;
    }

    var studentsFile = $manualStudentsFile[0] && $manualStudentsFile[0].files ? $manualStudentsFile[0].files[0] : null;
    var receiptFile = $manualReceiptFile[0] && $manualReceiptFile[0].files ? $manualReceiptFile[0].files[0] : null;
    var paidByName = ($manualPaidByName.val() || '').trim();
    var paidByPhone = ($manualPaidByPhone.val() || '').trim();
    var reason = ($manualReason.val() || '').trim();
    var totalAmount = num($manualTotal.val());

    if (!studentsFile) {
      showAlert($manualAlert, 'danger', 'Upload the CSV containing first name, last name, and matric number.');
      return;
    }
    if (!receiptFile) {
      showAlert($manualAlert, 'danger', 'Upload the payment receipt as an image or PDF.');
      return;
    }
    if (!paidByName) {
      showAlert($manualAlert, 'danger', 'Enter the name of the person who paid manually.');
      return;
    }
    if (!paidByPhone) {
      showAlert($manualAlert, 'danger', 'Enter the phone number of the person who paid manually.');
      return;
    }
    if (!reason) {
      showAlert($manualAlert, 'danger', 'Enter the reason for this manual purchase record.');
      return;
    }
    if (totalAmount <= 0) {
      showAlert($manualAlert, 'danger', 'The recorded subtotal must be greater than 0.');
      return;
    }

    var payload = new FormData();
    payload.append('action', 'create_manual_batch');
    payload.append('manual_id', num($manualMaterial.val()));
    payload.append('school', getScopedSchoolValue($manualSchool));
    payload.append('dept', num($manualDept.val()));
    payload.append('paid_by_name', paidByName);
    payload.append('paid_by_phone', paidByPhone);
    payload.append('payment_reason', reason);
    payload.append('students_csv', studentsFile);
    payload.append('payment_receipt', receiptFile);

    $manualSubmit.prop('disabled', true).text('Recording...');
    $.ajax({
      url: 'model/batch_payments.php',
      method: 'POST',
      data: payload,
      processData: false,
      contentType: false
    }).done(function (res) {
      if (res.status === 'success') {
        var successMessage = 'Manual purchase recorded. tx_ref: ' + ((res.data && res.data.tx_ref) || '');
        if (res.data && Number(res.data.unmatched_count || 0) > 0) {
          successMessage += ' Unmatched student rows: ' + Number(res.data.unmatched_count || 0) + '.';
        }
        showAlert($manualAlert, 'success', successMessage);
        if (manualBatchModal) {
          manualBatchModal.hide();
        }
        resetManualForm();
        loadBatches();
      } else {
        showAlert($manualAlert, 'danger', res.message || 'Failed to record manual purchase.');
      }
    }).fail(function () {
      showAlert($manualAlert, 'danger', 'Network error.');
    }).always(function () {
      $manualSubmit.prop('disabled', false).text('Record Manual Purchase');
    });
  });

  $(document).on('click', '.batch-create-link', function () {
    var $btn = $(this);
    var batchId = Number($btn.data('id')) || 0;
    var txref = $btn.data('txref');
    var amount = Number($btn.data('amount') || 0);
    if (!batchId || !txref || amount <= 0) {
      showAlert($alert, 'danger', 'Invalid payment details.');
      return;
    }

    $btn.prop('disabled', true).text('Creating...');
    $.post('model/create_payment_link.php', { batch_id: batchId, tx_ref: txref, amount: amount }).done(function (res) {
      if (res.status === 'success' && res.data && res.data.link) {
        var link = res.data.link;
        showAlert($alert, 'success', 'Payment link created. Checkout will add a 2% processing fee.');
        $('#paymentLinkHref').attr('href', link).text(link);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentLinkModal')).show();
        $('#copyPaymentLinkBtn').off('click').on('click', function () {
          var $copyBtn = $(this);
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link).then(function () {
              $copyBtn.text('Copied');
              setTimeout(function () { $copyBtn.text('Copy link'); }, 2000);
            }).catch(function () {
              fallbackCopy(link, $copyBtn);
            });
          } else {
            fallbackCopy(link, $copyBtn);
          }
        });
        loadBatches();
      } else {
        showAlert($alert, 'danger', res.message || 'Failed to create payment link.');
      }
    }).fail(function () {
      showAlert($alert, 'danger', 'Network error.');
    }).always(function () {
      $btn.prop('disabled', false).text('Create Payment Link');
    });
  });

  $(document).on('click', '.batch-verify-payment', function () {
    var $btn = $(this);
    var batchId = Number($btn.data('id')) || 0;
    var txref = $btn.data('txref');
    if (!batchId || !txref) {
      showAlert($alert, 'danger', 'Invalid payment details.');
      return;
    }

    $btn.prop('disabled', true).text('Verifying...');
    $.post('model/verify_payment_batch.php', { batch_id: batchId, tx_ref: txref }).done(function (res) {
      if (res.status === 'success') {
        showAlert($alert, 'success', 'Payment verified and processed successfully.');
        loadBatches();
      } else {
        showAlert($alert, 'danger', res.message || 'Failed to verify payment.');
      }
    }).fail(function () {
      showAlert($alert, 'danger', 'Network error.');
    }).always(function () {
      $btn.prop('disabled', false).text('Verify Payment');
    });
  });

  $(document).on('click', '.view-items', function () {
    var id = Number($(this).data('id')) || 0;
    var $tbody = $('#batchItemsTable tbody');
    var $notice = $('#batchItemsNotice');
    $tbody.empty();
    $notice.addClass('d-none').text('');
    resetBatchSummary();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('batchItemsModal')).show();

    $.get('model/batch_payment_items.php', { batch_id: id }).done(function (res) {
      renderBatchSummary(res.batch || null);
      if (res.status === 'success' && Array.isArray(res.items)) {
        if (Number(res.unmatched_count || 0) > 0) {
          $notice.removeClass('d-none').text(Number(res.unmatched_count || 0) + ' item(s) were not matched in users.');
        }
        res.items.forEach(function (item) {
          var badge = 'secondary';
          if (item.status === 'paid' || item.status === 'successful') {
            badge = 'success';
          } else if (item.status === 'pending') {
            badge = 'warning';
          } else if (item.status === 'failed') {
            badge = 'danger';
          }

          var row = '<tr>' +
            '<td>' + (item.student || '') + '</td>' +
            '<td>' + (item.matric || '') + '</td>' +
            '<td>' + (item.email || '') + '</td>' +
            '<td>' + formatNumber(item.price || 0) + '</td>' +
            '<td class="text-monospace">' + (item.ref_id || '') + '</td>' +
            '<td><span class="badge bg-label-' + badge + '">' + (item.status || '') + '</span></td>' +
            '</tr>';
          $tbody.append(row);
        });
      }
    }).fail(function () {
      $notice.removeClass('d-none').text('Failed to load batch items.');
    });
  });

  $school.on('change', function () {
    var schoolId = getScopedSchoolValue($school);
    var facultyId = (adminRole === 5 && adminFaculty !== 0) ? adminFaculty : 0;
    loadFaculties($faculty, schoolId, facultyId);
    loadDepts($dept, schoolId, facultyId, false);
    setTimeout(function () {
      loadMaterials($school, $faculty, $dept, $manual);
    }, 200);
  });

  $faculty.on('change', function () {
    var schoolId = getScopedSchoolValue($school);
    var facultyId = getScopedFacultyValue($faculty);
    loadDepts($dept, schoolId, facultyId, false);
    setTimeout(function () {
      loadMaterials($school, $faculty, $dept, $manual);
    }, 200);
  });

  $dept.on('change', function () {
    loadMaterials($school, $faculty, $dept, $manual);
    setTimeout(updateGatewayPreview, 150);
  });
  $manual.on('change', updateGatewayPreview);
  $studentsFile.on('change', updateGatewayPreview);

  $manualSchool.on('change', function () {
    var schoolId = getScopedSchoolValue($manualSchool);
    var facultyId = (adminRole === 5 && adminFaculty !== 0) ? adminFaculty : 0;
    loadFaculties($manualFaculty, schoolId, facultyId);
    loadDepts($manualDept, schoolId, facultyId, false);
    setTimeout(function () {
      loadMaterials($manualSchool, $manualFaculty, $manualDept, $manualMaterial);
    }, 200);
  });

  $manualFaculty.on('change', function () {
    var schoolId = getScopedSchoolValue($manualSchool);
    var facultyId = getScopedFacultyValue($manualFaculty);
    loadDepts($manualDept, schoolId, facultyId, false);
    setTimeout(function () {
      loadMaterials($manualSchool, $manualFaculty, $manualDept, $manualMaterial);
    }, 200);
  });

  $manualDept.on('change', function () {
    loadMaterials($manualSchool, $manualFaculty, $manualDept, $manualMaterial);
    setTimeout(updateManualPreview, 150);
  });
  $manualMaterial.on('change', updateManualPreview);
  $manualStudentsFile.on('change', updateManualPreview);

  if (createBatchModalEl) {
    createBatchModalEl.addEventListener('hidden.bs.modal', function () {
      resetCreateForm();
    });
  }
  if (manualBatchModalEl) {
    manualBatchModalEl.addEventListener('hidden.bs.modal', function () {
      resetManualForm();
    });
  }

  $filterSchool.on('change', function () {
    var schoolId = adminRole === 5 ? adminSchool : num($filterSchool.val());
    var facultyId = (adminRole === 5 && adminFaculty !== 0) ? adminFaculty : 0;
    loadFaculties($filterFaculty, schoolId, facultyId);
    loadDepts($filterDept, schoolId, facultyId, true);
    setTimeout(loadBatches, 300);
  });

  $filterFaculty.on('change', function () {
    var schoolId = adminRole === 5 ? adminSchool : num($filterSchool.val());
    var facultyId = (adminRole === 5 && adminFaculty !== 0) ? adminFaculty : num($filterFaculty.val());
    loadDepts($filterDept, schoolId, facultyId, true);
    setTimeout(loadBatches, 300);
  });
  $filterDept.on('change', loadBatches);

  initSelect2();

  var createSchoolId = adminRole === 5 ? adminSchool : num($school.val());
  loadFaculties($faculty, createSchoolId, (adminRole === 5 && adminFaculty !== 0) ? adminFaculty : 0);
  loadDepts($dept, createSchoolId, (adminRole === 5 && adminFaculty !== 0) ? adminFaculty : 0, false);
  loadMaterials($school, $faculty, $dept, $manual);

  var manualSchoolId = adminRole === 5 ? adminSchool : num($manualSchool.val());
  loadFaculties($manualFaculty, manualSchoolId, (adminRole === 5 && adminFaculty !== 0) ? adminFaculty : 0);
  loadDepts($manualDept, manualSchoolId, (adminRole === 5 && adminFaculty !== 0) ? adminFaculty : 0, false);
  loadMaterials($manualSchool, $manualFaculty, $manualDept, $manualMaterial);

  var filterSchoolId = adminRole === 5 ? adminSchool : num($filterSchool.val());
  loadFaculties($filterFaculty, filterSchoolId, (adminRole === 5 && adminFaculty !== 0) ? adminFaculty : 0);
  loadDepts($filterDept, filterSchoolId, (adminRole === 5 && adminFaculty !== 0) ? adminFaculty : 0, true);
  loadBatches();
});


$(document).ready(function () {
  var $school = $('#bp_school');
  var $faculty = $('#bp_faculty');
  var $dept = $('#bp_dept');
  var $manual = $('#bp_manual');
  var $alert = $('#bp_alert');
  var $price = $('#bp_price');
  var $students = $('#bp_students');
  var $total = $('#bp_total');
  var $txref = $('#bp_txref');
  var $submit = $('#bp_submit');

  var $filterSchool = $('#filter_school');
  var $filterFaculty = $('#filter_faculty');
  var $filterDept = $('#filter_dept');

  function showAlert(color, message) {
    var classes = 'alert-success alert-danger alert-warning alert-info alert-secondary';
    if (!message) { $alert.addClass('d-none').removeClass(classes); return; }
    $alert.removeClass('d-none').removeClass(classes).addClass('alert-' + color).text(message);
  }

  function num(n) { return Number(n || 0); }

  function initSelect2() {
    $('#bp_school, #bp_faculty, #bp_dept, #bp_manual, #filter_school, #filter_faculty, #filter_dept').select2({ theme: 'bootstrap-5', width: '100%' });
  }

  function loadFaculties($el, schoolId, selected) {
    $.get('model/transactions_faculties.php', { school: schoolId }).done(function (res) {
      $el.empty();
      if (!(adminRole == 5 && adminFaculty !== 0)) {
        $el.append('<option value="0">All Faculties</option>');
      }
      if (res.status === 'success' && Array.isArray(res.faculties)) {
        res.faculties.forEach(function (f) {
          var sel = (selected && Number(selected) === Number(f.id)) ? ' selected' : '';
          $el.append('<option value="' + f.id + '"' + sel + '>' + f.name + '</option>');
        });
      }
      $el.trigger('change.select2');
    });
  }

  function loadDepts($el, schoolId, facultyId, allowAll) {
    $.get('model/transactions_departments.php', { school: schoolId, faculty: facultyId }).done(function (res) {
      $el.empty();
      if (allowAll) { $el.append('<option value="0">All Departments</option>'); }
      if (res.status === 'success' && Array.isArray(res.departments)) {
        res.departments.forEach(function (d) {
          $el.append('<option value="' + d.id + '">' + d.name + '</option>');
        });
      }
      $el.trigger('change.select2');
    });
  }

  function loadManuals() {
    var sid = adminRole == 5 ? adminSchool : num($school.val());
    var fid = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : num($faculty.val());
    var did = num($dept.val());
    $manual.empty().append('<option value="0">Select Material</option>');
    if (!sid || !did) { $manual.trigger('change.select2'); return; }
    $.get('model/transactions_materials.php', { school: sid, faculty: fid, dept: did }).done(function (res) {
      if (res.status === 'success' && Array.isArray(res.materials)) {
        res.materials.forEach(function (m) {
          var label = m.title + ' - ' + m.course_code + ' | Code: ' + m.code + ' | ID: ' + m.id;
          var opt = $('<option>').val(m.id).text(label).data('price', Number(m.price) || 0);
          $manual.append(opt);
        });
      }
      $manual.trigger('change.select2');
    });
  }

  function updatePreview() {
    showAlert(null, null);
    $price.val('0');
    $students.val('0');
    $total.val('0');
    $txref.val('');
    $submit.prop('disabled', true);
    var mid = num($manual.val());
    var sid = adminRole == 5 ? adminSchool : num($school.val());
    var did = num($dept.val());
    if (!mid || !sid || !did) return;
    $.get('model/batch_payments.php', { fetch: 'preview', manual_id: mid, school: sid, dept: did }).done(function (res) {
      if (res.status === 'success' && res.data) {
        $price.val(Number(res.data.price_per_student).toLocaleString());
        $students.val(Number(res.data.student_count).toLocaleString());
        $total.val(Number(res.data.total_amount).toLocaleString());
        $txref.val(res.data.tx_ref || '');
        if (Number(res.data.student_count) > 0) { $submit.prop('disabled', false); }
        if (Number(res.data.student_count) === 0) {
          showAlert('warning', 'No active students found for the selected department.');
        }
      } else {
        showAlert('danger', res.message || 'Unable to prepare preview.');
      }
    }).fail(function () { showAlert('danger', 'Network error.'); });
  }

  $('#batchCreateForm').on('submit', function (e) {
    e.preventDefault();
    if ($submit.prop('disabled')) return;
    var totalAmount = num($total.val());
    if (totalAmount <= 0) {
      showAlert('danger', 'Total amount must be greater than 0.');
      return;
    }
    var payload = {
      action: 'create_batch',
      manual_id: num($manual.val()),
      school: adminRole == 5 ? adminSchool : num($school.val()),
      dept: num($dept.val()),
      tx_ref: ($txref.val() || '').trim(),
      total_amount: totalAmount
    };
    $submit.prop('disabled', true).text('Creating...');
    $.post('model/batch_payments.php', payload).done(function (res) {
      if (res.status === 'success') {
        showAlert('success', 'Batch created. tx_ref: ' + (res.data && res.data.tx_ref ? res.data.tx_ref : ''));
        loadBatches();
      } else {
        showAlert('danger', res.message || 'Failed to create batch.');
      }
    }).fail(function () {
      showAlert('danger', 'Network error.');
    }).always(function () {
      $submit.prop('disabled', false).text('Create Batch');
    });
  });

  function renderBatchesTable(items) {
    var $tbody = $('#batchesTable tbody');
    if ($.fn.dataTable.isDataTable('#batchesTable')) {
      var t = $('#batchesTable').DataTable();
      t.clear().draw().destroy();
    }
    $tbody.empty();
    (items || []).forEach(function (b) {
      var badge = 'secondary';
      if (b.status === 'paid') badge = 'success';
      else if (b.status === 'pending') badge = 'warning';
      else if (b.status === 'failed') badge = 'danger';
      var payBtn = '';
      if (b.status === 'pending') {
        payBtn = '<button type="button" class="btn btn-sm btn-warning batch-create-link ms-1" data-id="' + b.id + '" data-txref="' + b.tx_ref + '" data-amount="' + Number(b.total_amount) + '">Create Payment Link</button>';
        payBtn += ' <button type="button" class="btn btn-sm btn-info batch-verify-payment ms-1" data-id="' + b.id + '" data-txref="' + b.tx_ref + '">Verify Payment</button>';
      }

      var row = '<tr>' +
        '<td class="fw-semibold">' + b.tx_ref + '</td>' +
        '<td>' + b.manual + '</td>' +
        '<td>' + b.dept + '</td>' +
        '<td>' + b.school + '</td>' +
        '<td>' + Number(b.total_students).toLocaleString() + '</td>' +
        '<td>' + Number(b.total_amount).toLocaleString() + '</td>' +
        '<td><span class="badge bg-label-' + badge + '">' + b.status + '</span></td>' +
        '<td>' + b.created_at + '</td>' +
        '<td><button type="button" class="btn btn-sm btn-outline-primary view-items" data-id="' + b.id + '">View</button>' + payBtn + '</td>' +
        '</tr>';
      $tbody.append(row);
    });
    $('#batchesTable').DataTable();
  }

  // Create Payment Link for a batch (server-side call)
  $(document).on('click', '.batch-create-link', function () {
    var $btn = $(this);
    var batchId = Number($btn.data('id')) || 0;
    var txref = $btn.data('txref');
    var amount = Number($btn.data('amount') || 0);
    if (!batchId || !txref || amount <= 0) { showAlert('danger', 'Invalid payment details.'); return; }
    $btn.prop('disabled', true).text('Creating...');
    $.post('model/create_payment_link.php', { batch_id: batchId, tx_ref: txref, amount: amount }).done(function (res) {
      if (res.status === 'success' && res.data && res.data.link) {
        var link = res.data.link;
        showAlert('success', 'Payment link created.');
        // Show link in modal for admin to copy/share
        $('#paymentLinkHref').attr('href', link).text(link);
        var plModal = new bootstrap.Modal(document.getElementById('paymentLinkModal'));
        plModal.show();
        // Setup copy handler (one-time)
        $('#copyPaymentLinkBtn').off('click').on('click', function () {
          var $btn = $(this);
          var txt = link;
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(txt).then(function () {
              $btn.text('Copied');
              setTimeout(function () { $btn.text('Copy link'); }, 2000);
            }).catch(function () { fallbackCopy(txt, $btn); });
          } else {
            fallbackCopy(txt, $btn);
          }
        });
        // also refresh list so admin sees status
        loadBatches();
      } else {
        showAlert('danger', res.message || 'Failed to create payment link.');
      }
    }).fail(function () {
      showAlert('danger', 'Network error.');
    }).always(function () {
      $btn.prop('disabled', false).text('Create Payment Link');
    });
  });

  function fallbackCopy(text, $btn) {
    // Create temporary textarea
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
    } catch (e) {
      alert('Copy not supported.');
    }
    $tmp.remove();
  }

  function loadBatches() {
    var sid = adminRole == 5 ? adminSchool : num($filterSchool.val());
    var fid = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : num($filterFaculty.val());
    var did = num($filterDept.val());
    $.get('model/batch_payments_list.php', { school: sid, faculty: fid, dept: did }).done(function (res) {
      if (res.status === 'success') {
        renderBatchesTable(res.batches || []);
      }
    });
  }

  $(document).on('click', '.view-items', function () {
    var id = Number($(this).data('id'));
    var $tbody = $('#batchItemsTable tbody');
    $tbody.empty();
    $('#batchItemsModal').modal('show');
    $.get('model/batch_payment_items.php', { batch_id: id }).done(function (res) {
      if (res.status === 'success' && Array.isArray(res.items)) {
        res.items.forEach(function (it) {
          var badge = 'secondary';
          if (it.status === 'paid') badge = 'success';
          else if (it.status === 'pending') badge = 'warning';
          else if (it.status === 'failed') badge = 'danger';
          var row = '<tr>' +
            '<td>' + (it.student || '') + '</td>' +
            '<td>' + (it.matric || '') + '</td>' +
            '<td>' + (it.email || '') + '</td>' +
            '<td>' + Number(it.price).toLocaleString() + '</td>' +
            '<td class="text-monospace">' + it.ref_id + '</td>' +
            '<td><span class="badge bg-label-' + badge + '">' + it.status + '</span></td>' +
            '</tr>';
          $tbody.append(row);
        });
      }
    });
  });

  // Bind selection changes
  $school.on('change', function () {
    var sid = adminRole == 5 ? adminSchool : num($school.val());
    var fid = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : 0;
    loadFaculties($faculty, sid, fid);
    loadDepts($dept, sid, fid, false);
    setTimeout(loadManuals, 200);
  });
  $faculty.on('change', function () {
    var sid = adminRole == 5 ? adminSchool : num($school.val());
    var fid = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : num($faculty.val());
    loadDepts($dept, sid, fid, false);
    setTimeout(loadManuals, 200);
  });
  $dept.on('change', function () { loadManuals(); setTimeout(updatePreview, 150); });
  $manual.on('change', updatePreview);

  $filterSchool.on('change', function () {
    var sid = adminRole == 5 ? adminSchool : num($filterSchool.val());
    var fid = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : 0;
    loadFaculties($filterFaculty, sid, fid);
    loadDepts($filterDept, sid, fid, true);
    setTimeout(loadBatches, 300);
  });
  $filterFaculty.on('change', function () {
    var sid = adminRole == 5 ? adminSchool : num($filterSchool.val());
    var fid = (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : num($filterFaculty.val());
    loadDepts($filterDept, sid, fid, true);
    setTimeout(loadBatches, 300);
  });
  $filterDept.on('change', loadBatches);

  // Verify Payment handler
  $(document).on('click', '.batch-verify-payment', function () {
    var $btn = $(this);
    var batchId = Number($btn.data('id')) || 0;
    var txref = $btn.data('txref');
    if (!batchId || !txref) { showAlert('danger', 'Invalid payment details.'); return; }
    $btn.prop('disabled', true).text('Verifying...');
    $.post('model/verify_payment_batch.php', { batch_id: batchId, tx_ref: txref }).done(function (res) {
      if (res.status === 'success') {
        showAlert('success', 'Payment verified and processed successfully!');
        loadBatches();
      } else {
        showAlert('danger', res.message || 'Failed to verify payment.');
      }
    }).fail(function () {
      showAlert('danger', 'Network error.');
    }).always(function () {
      $btn.prop('disabled', false).text('Verify Payment');
    });
  });

  // Init
  initSelect2();
  // Create section
  var sid0 = adminRole == 5 ? adminSchool : num($school.val());
  loadFaculties($faculty, sid0, (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : 0);
  loadDepts($dept, sid0, (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : 0, false);
  loadManuals();
  // List section
  var fs0 = adminRole == 5 ? adminSchool : num($filterSchool.val());
  loadFaculties($filterFaculty, fs0, (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : 0);
  loadDepts($filterDept, fs0, (adminRole == 5 && adminFaculty !== 0) ? adminFaculty : 0, true);
  loadBatches();
});


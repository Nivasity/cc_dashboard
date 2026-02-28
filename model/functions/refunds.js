$(document).ready(function () {
  var endpoint = 'model/refunds.php';
  var queueTable = null;
  var cancelRefundId = null;

  var $createModal = $('#newRefundModal');
  var $createForm = $('#refundCreateForm');
  var $createBtn = $('#createRefundBtn');
  var $queueFilterForm = $('#refundQueueFilterForm');
  var $monitoringFilterForm = $('#monitoringFilterForm');
  var $cancelModal = $('#cancelRefundModal');
  var $cancelForm = $('#cancelRefundForm');
  var $cancelBtn = $('#cancelRefundBtn');

  $('#monitoringSchoolId, #queueSchoolId, #queueStatus').select2({
    theme: 'bootstrap-5',
    width: '100%'
  });

  $('#createSchoolId').select2({
    theme: 'bootstrap-5',
    width: '100%',
    dropdownParent: $createModal
  });

  function toNumber(value) {
    var n = Number(value);
    return Number.isFinite(n) ? n : 0;
  }

  function formatCurrency(value) {
    return 'NGN ' + toNumber(value).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getBadgeClass(status) {
    switch (status) {
      case 'pending':
        return 'warning';
      case 'partially_applied':
        return 'info';
      case 'applied':
        return 'success';
      case 'cancelled':
        return 'secondary';
      default:
        return 'secondary';
    }
  }

  function renderQueue(refunds) {
    var $tbody = $('#refundQueueTable tbody');
    $tbody.empty();

    if (!Array.isArray(refunds) || refunds.length === 0) {
      $tbody.append('<tr><td colspan="11" class="text-center text-muted">No refunds found.</td></tr>');
      if (queueTable) {
        queueTable.clear().destroy();
        queueTable = null;
      }
      return;
    }

    refunds.forEach(function (refund) {
      var reason = escapeHtml(refund.reason || '');
      var studentText = refund.student_name ? escapeHtml(refund.student_name) : '-';
      if (refund.student_id) {
        studentText += '<br><small class="text-muted">ID: ' + escapeHtml(refund.student_id) + '</small>';
      }

      var actions = '<a href="refund_detail.php?id=' + encodeURIComponent(refund.id) + '" class="btn btn-sm btn-outline-primary me-1">Detail</a>';
      if (refund.status !== 'cancelled') {
        actions += '<button type="button" class="btn btn-sm btn-outline-danger cancel-refund" data-id="' +
          escapeHtml(refund.id) + '" data-ref="' + escapeHtml(refund.ref_id) + '">Cancel</button>';
      }

      $tbody.append(
        '<tr>' +
          '<td>' + escapeHtml(refund.id) + '</td>' +
          '<td>' + escapeHtml(refund.school_name || ('School #' + refund.school_id)) + '</td>' +
          '<td class="fw-semibold">' + escapeHtml(refund.ref_id) + '</td>' +
          '<td>' + studentText + '</td>' +
          '<td>' + formatCurrency(refund.amount) + '</td>' +
          '<td>' + formatCurrency(refund.remaining_amount) + '</td>' +
          '<td>' + formatCurrency(refund.consumed_amount) + '</td>' +
          '<td><span class="badge bg-label-' + getBadgeClass(refund.status) + '">' +
            escapeHtml((refund.status || '').replace('_', ' ')) + '</span></td>' +
          '<td style="max-width: 240px;">' + reason + '</td>' +
          '<td>' + escapeHtml(refund.created_at || '-') + '</td>' +
          '<td>' + actions + '</td>' +
        '</tr>'
      );
    });

    if (queueTable) {
      queueTable.clear().destroy();
      queueTable = null;
    }

    queueTable = new DataTable('#refundQueueTable', {
      pageLength: 25,
      order: [[0, 'desc']]
    });
  }

  function renderOutstanding(rows) {
    var $tbody = $('#outstandingTable tbody');
    $tbody.empty();

    if (!Array.isArray(rows) || rows.length === 0) {
      $tbody.append('<tr><td colspan="3" class="text-center text-muted">No outstanding liability.</td></tr>');
      return;
    }

    rows.forEach(function (row) {
      $tbody.append(
        '<tr>' +
          '<td>' + escapeHtml(row.school_name || ('School #' + row.school_id)) + '</td>' +
          '<td>' + formatCurrency(row.outstanding_amount) + '</td>' +
          '<td>' + escapeHtml(row.refunds_count || 0) + '</td>' +
        '</tr>'
      );
    });
  }

  function renderDaily(rows) {
    var $tbody = $('#dailyConsumptionTable tbody');
    $tbody.empty();

    if (!Array.isArray(rows) || rows.length === 0) {
      $tbody.append('<tr><td colspan="4" class="text-center text-muted">No consumed rows in selected range.</td></tr>');
      return;
    }

    rows.forEach(function (row) {
      $tbody.append(
        '<tr>' +
          '<td>' + escapeHtml(row.report_date || '-') + '</td>' +
          '<td>' + escapeHtml(row.school_name || ('School #' + row.school_id)) + '</td>' +
          '<td>' + formatCurrency(row.total_consumed) + '</td>' +
          '<td>' + escapeHtml(row.consumed_rows || 0) + '</td>' +
        '</tr>'
      );
    });
  }

  function getSchoolFilter(selector) {
    var raw = $(selector).val();
    return raw === '' || raw === null ? null : raw;
  }

  function fetchQueue() {
    var params = {
      action: 'queue',
      school_id: getSchoolFilter('#queueSchoolId'),
      status: $('#queueStatus').val() || '',
      created_from: $('#queueCreatedFrom').val() || '',
      created_to: $('#queueCreatedTo').val() || '',
      source_ref_id: $('#queueSourceRef').val().trim(),
      limit: 500,
      offset: 0
    };

    $.ajax({
      url: endpoint,
      method: 'GET',
      dataType: 'json',
      data: params,
      success: function (res) {
        if (res.status === 'success') {
          renderQueue(res.refunds || []);
        } else {
          renderQueue([]);
          showToast('bg-danger', res.message || 'Failed to load refund queue.');
        }
      },
      error: function () {
        renderQueue([]);
        showToast('bg-danger', 'Failed to load refund queue.');
      }
    });
  }

  function fetchOutstanding() {
    $.ajax({
      url: endpoint,
      method: 'GET',
      dataType: 'json',
      data: {
        action: 'monitoring_outstanding',
        school_id: getSchoolFilter('#monitoringSchoolId')
      },
      success: function (res) {
        if (res.status === 'success') {
          $('#outstandingTotal').text(formatCurrency(res.total_outstanding || 0));
          renderOutstanding(res.rows || []);
        } else {
          $('#outstandingTotal').text(formatCurrency(0));
          renderOutstanding([]);
          showToast('bg-danger', res.message || 'Failed to load outstanding liability.');
        }
      },
      error: function () {
        $('#outstandingTotal').text(formatCurrency(0));
        renderOutstanding([]);
        showToast('bg-danger', 'Failed to load outstanding liability.');
      }
    });
  }

  function fetchDaily() {
    $.ajax({
      url: endpoint,
      method: 'GET',
      dataType: 'json',
      data: {
        action: 'monitoring_daily',
        school_id: getSchoolFilter('#monitoringSchoolId'),
        from_date: $('#monitoringFromDate').val() || '',
        to_date: $('#monitoringToDate').val() || ''
      },
      success: function (res) {
        if (res.status === 'success') {
          renderDaily(res.rows || []);
        } else {
          renderDaily([]);
          showToast('bg-danger', res.message || 'Failed to load daily consumption report.');
        }
      },
      error: function () {
        renderDaily([]);
        showToast('bg-danger', 'Failed to load daily consumption report.');
      }
    });
  }

  function reloadMonitoring() {
    fetchOutstanding();
    fetchDaily();
  }

  function resetCreateForm() {
    if (!$createForm.length) {
      return;
    }

    $createForm.trigger('reset');
    $('#createSchoolId').val('').trigger('change.select2');
  }

  $createForm.on('submit', function (e) {
    e.preventDefault();

    var payload = {
      action: 'create',
      school_id: $('#createSchoolId').val(),
      source_ref_id: $('#createSourceRefId').val().trim(),
      student_id: $('#createStudentId').val().trim(),
      amount: $('#createAmount').val(),
      reason: $('#createReason').val().trim()
    };

    if (!payload.school_id || !payload.source_ref_id || !payload.amount || !payload.reason) {
      showToast('bg-danger', 'School, source ref, amount and reason are required.');
      return;
    }

    $createBtn.prop('disabled', true).text('Creating...');

    $.ajax({
      url: endpoint,
      method: 'POST',
      dataType: 'json',
      data: payload,
      success: function (res) {
        if (res.status === 'success') {
          showToast('bg-success', res.message || 'Refund created successfully.');
          resetCreateForm();
          var createModal = bootstrap.Modal.getInstance($createModal.get(0));
          if (createModal) {
            createModal.hide();
          }
          fetchQueue();
          reloadMonitoring();
        } else {
          showToast('bg-danger', res.message || 'Failed to create refund.');
        }
      },
      error: function (xhr) {
        var message = 'Failed to create refund.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          message = xhr.responseJSON.message;
        }
        showToast('bg-danger', message);
      },
      complete: function () {
        $createBtn.prop('disabled', false).text('Create Refund');
      }
    });
  });

  $createModal.on('hidden.bs.modal', function () {
    resetCreateForm();
  });

  $queueFilterForm.on('submit', function (e) {
    e.preventDefault();
    fetchQueue();
  });

  $monitoringFilterForm.on('submit', function (e) {
    e.preventDefault();
    var fromDate = $('#monitoringFromDate').val() || '';
    var toDate = $('#monitoringToDate').val() || '';
    if (fromDate && toDate && fromDate > toDate) {
      showToast('bg-danger', 'From date cannot be after to date.');
      return;
    }
    reloadMonitoring();
  });

  $(document).on('click', '.cancel-refund', function () {
    cancelRefundId = $(this).data('id');
    var ref = $(this).data('ref');
    $('#cancelRefundTarget').text('Refund ID ' + cancelRefundId + ' | Source Ref ' + ref);
    $('#cancelRefundReason').val('');
    var modal = bootstrap.Modal.getOrCreateInstance($cancelModal.get(0));
    modal.show();
  });

  $cancelForm.on('submit', function (e) {
    e.preventDefault();
    if (!cancelRefundId) {
      showToast('bg-danger', 'No refund selected.');
      return;
    }

    var reason = $('#cancelRefundReason').val().trim();
    if (!reason) {
      showToast('bg-danger', 'Cancellation reason is required.');
      return;
    }

    $cancelBtn.prop('disabled', true).text('Cancelling...');

    $.ajax({
      url: endpoint,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'cancel',
        refund_id: cancelRefundId,
        cancel_reason: reason
      },
      success: function (res) {
        if (res.status === 'success') {
          showToast('bg-success', res.message || 'Refund cancelled successfully.');
          var modal = bootstrap.Modal.getInstance($cancelModal.get(0));
          if (modal) {
            modal.hide();
          }
          fetchQueue();
          reloadMonitoring();
        } else {
          showToast('bg-danger', res.message || 'Failed to cancel refund.');
        }
      },
      error: function (xhr) {
        var message = 'Failed to cancel refund.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          message = xhr.responseJSON.message;
        }
        showToast('bg-danger', message);
      },
      complete: function () {
        $cancelBtn.prop('disabled', false).text('Cancel Refund');
      }
    });
  });

  fetchQueue();
  reloadMonitoring();
});

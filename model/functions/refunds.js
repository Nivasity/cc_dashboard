$(document).ready(function () {
  var endpoint = 'model/refunds.php';
  var queueTable = null;
  var studentLookupTimer = null;
  var sourceLookupTimer = null;

  var $createModal = $('#newRefundModal');
  var $createForm = $('#refundCreateForm');
  var $createBtn = $('#createRefundBtn');
  var $createStudentEmail = $('#createStudentEmail');
  var $createSourceRef = $('#createSourceRefId');
  var $createMaterials = $('#createMaterialIds');
  var $createReason = $('#createReason');
  var $studentLookupFeedback = $('#studentLookupFeedback');
  var $sourceLookupFeedback = $('#sourceLookupFeedback');
  var $selectedMaterialsTotal = $('#selectedMaterialsTotal');

  var $queueFilterForm = $('#refundQueueFilterForm');
  var $monitoringFilterForm = $('#monitoringFilterForm');

  var createState = {
    student: null,
    sourceValidated: false,
    sourceMaterials: []
  };

  $('#monitoringSchoolId, #queueSchoolId, #queueStatus').select2({
    theme: 'bootstrap-5',
    width: '100%'
  });

  $createMaterials.select2({
    theme: 'bootstrap-5',
    width: '100%',
    dropdownParent: $createModal,
    placeholder: 'Select refund materials',
    allowClear: true,
    closeOnSelect: false
  });
  $createMaterials.prop('disabled', true).trigger('change.select2');

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

  function isValidEmail(value) {
    var email = String(value || '').trim();
    if (!email) {
      return false;
    }

    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
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

  function setFeedback($node, tone, message) {
    if (!$node.length) {
      return;
    }

    var toneClass = 'text-muted';
    if (tone === 'success') {
      toneClass = 'text-success';
    } else if (tone === 'error') {
      toneClass = 'text-danger';
    } else if (tone === 'info') {
      toneClass = 'text-info';
    }

    $node.removeClass('text-muted text-success text-danger text-info').addClass(toneClass).text(message || '');
  }

  function getSelectedMaterialIds() {
    var value = $createMaterials.val();
    return Array.isArray(value) ? value.filter(Boolean) : [];
  }

  function updateSelectedMaterialsTotal() {
    var selectedIds = getSelectedMaterialIds();
    var total = 0;

    selectedIds.forEach(function (id) {
      var option = $createMaterials.find('option[value="' + id + '"]');
      total += toNumber(option.data('price'));
    });

    $selectedMaterialsTotal.text('Selected Total: ' + formatCurrency(total));
  }

  function updateCreateSubmitState() {
    var hasStudent = createState.student && createState.student.id;
    var hasValidSource = createState.sourceValidated === true;
    var hasMaterials = getSelectedMaterialIds().length > 0;
    var hasReason = String($createReason.val() || '').trim() !== '';

    $createBtn.prop('disabled', !(hasStudent && hasValidSource && hasMaterials && hasReason));
  }

  function clearMaterials() {
    createState.sourceMaterials = [];
    $createMaterials.empty().val([]).trigger('change.select2');
    $createMaterials.prop('disabled', true).trigger('change.select2');
    $selectedMaterialsTotal.text('Selected Total: ' + formatCurrency(0));
  }

  function invalidateSource(message) {
    createState.sourceValidated = false;
    clearMaterials();
    setFeedback($sourceLookupFeedback, message ? 'error' : 'muted', message || '');
    updateCreateSubmitState();
  }

  function lookupSource() {
    var sourceRefId = String($createSourceRef.val() || '').trim();
    var studentId = createState.student ? createState.student.id : null;

    if (!sourceRefId || !studentId) {
      invalidateSource('Provide student email and source ref to validate transaction.');
      return;
    }

    setFeedback($sourceLookupFeedback, 'info', 'Validating source transaction...');

    $.ajax({
      url: endpoint,
      method: 'GET',
      dataType: 'json',
      data: {
        action: 'lookup_source',
        source_ref_id: sourceRefId,
        student_id: studentId
      },
      success: function (res) {
        if (res.status !== 'success') {
          invalidateSource(res.message || 'Unable to validate source ref.');
          return;
        }

        var materials = Array.isArray(res.materials) ? res.materials : [];
        if (materials.length === 0) {
          invalidateSource('No refundable materials found for this transaction.');
          return;
        }

        createState.sourceValidated = true;
        createState.sourceMaterials = materials;

        $createMaterials.empty();
        materials.forEach(function (material) {
          var boughtId = material.bought_id;
          var label = (material.title || 'Material #' + material.manual_id) +
            ' (' + (material.course_code || '-') + ') - ' + formatCurrency(material.price);
          var option = new Option(label, boughtId, false, false);
          $(option).data('price', toNumber(material.price));
          $createMaterials.append(option);
        });

        $createMaterials.prop('disabled', false).val([]).trigger('change.select2');
        updateSelectedMaterialsTotal();
        setFeedback($sourceLookupFeedback, 'success', 'Source ref validated. Select materials to refund.');
        updateCreateSubmitState();
      },
      error: function (xhr) {
        var message = 'Unable to validate source transaction.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          message = xhr.responseJSON.message;
        }
        invalidateSource(message);
      }
    });
  }

  function lookupStudent() {
    var email = String($createStudentEmail.val() || '').trim();
    if (!isValidEmail(email)) {
      createState.student = null;
      setFeedback($studentLookupFeedback, email ? 'error' : 'muted', email ? 'Enter a valid student email.' : '');
      $createSourceRef.prop('disabled', true);
      invalidateSource('Provide student email and source ref to validate transaction.');
      updateCreateSubmitState();
      return;
    }

    setFeedback($studentLookupFeedback, 'info', 'Looking up student...');

    $.ajax({
      url: endpoint,
      method: 'GET',
      dataType: 'json',
      data: {
        action: 'lookup_student',
        student_email: email
      },
      success: function (res) {
        if (res.status !== 'success' || !res.student) {
          createState.student = null;
          setFeedback($studentLookupFeedback, 'error', res.message || 'Student lookup failed.');
          $createSourceRef.prop('disabled', true);
          invalidateSource('Provide student email and source ref to validate transaction.');
          updateCreateSubmitState();
          return;
        }

        createState.student = res.student;
        $createSourceRef.prop('disabled', false);
        setFeedback($studentLookupFeedback, 'success', 'Student found: ' + (res.student.first_name || '') + ' ' + (res.student.last_name || ''));
        if (String($createSourceRef.val() || '').trim() !== '') {
          lookupSource();
        } else {
          invalidateSource('Enter source ref ID to continue.');
        }
        updateCreateSubmitState();
      },
      error: function (xhr) {
        createState.student = null;
        var message = 'Student lookup failed.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          message = xhr.responseJSON.message;
        }
        setFeedback($studentLookupFeedback, 'error', message);
        $createSourceRef.prop('disabled', true);
        invalidateSource('Provide student email and source ref to validate transaction.');
        updateCreateSubmitState();
      }
    });
  }

  function resetCreateForm() {
    if (!$createForm.length) {
      return;
    }

    createState.student = null;
    createState.sourceValidated = false;
    createState.sourceMaterials = [];

    $createForm.trigger('reset');
    $createSourceRef.prop('disabled', true);
    $createMaterials.empty().val([]).trigger('change.select2');
    $createMaterials.prop('disabled', true).trigger('change.select2');
    setFeedback($studentLookupFeedback, 'muted', '');
    setFeedback($sourceLookupFeedback, 'muted', '');
    $selectedMaterialsTotal.text('Selected Total: ' + formatCurrency(0));

    if (studentLookupTimer) {
      clearTimeout(studentLookupTimer);
      studentLookupTimer = null;
    }

    if (sourceLookupTimer) {
      clearTimeout(sourceLookupTimer);
      sourceLookupTimer = null;
    }

    updateCreateSubmitState();
  }

  function renderQueue(refunds) {
    var $tbody = $('#refundQueueTable tbody');
    $tbody.empty();

    if (!Array.isArray(refunds) || refunds.length === 0) {
      $tbody.append('<tr><td colspan="9" class="text-center text-muted">No refunds found.</td></tr>');
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

      var actions = '<a href="refund_detail.php?id=' + encodeURIComponent(refund.id) + '" class="btn btn-sm btn-outline-primary">Detail</a>';

      $tbody.append(
        '<tr>' +
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
      order: [[7, 'desc']]
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
          $('#refundedTotal').text(formatCurrency(res.total_refunded || 0));
          renderOutstanding(res.rows || []);
        } else {
          $('#outstandingTotal').text(formatCurrency(0));
          $('#refundedTotal').text(formatCurrency(0));
          renderOutstanding([]);
          showToast('bg-danger', res.message || 'Failed to load outstanding liability.');
        }
      },
      error: function () {
        $('#outstandingTotal').text(formatCurrency(0));
        $('#refundedTotal').text(formatCurrency(0));
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

  $createStudentEmail.on('input', function () {
    createState.student = null;
    createState.sourceValidated = false;
    $createSourceRef.prop('disabled', true);
    clearMaterials();
    setFeedback($studentLookupFeedback, 'muted', '');
    setFeedback($sourceLookupFeedback, 'muted', '');
    updateCreateSubmitState();

    if (studentLookupTimer) {
      clearTimeout(studentLookupTimer);
    }

    studentLookupTimer = setTimeout(function () {
      lookupStudent();
    }, 350);
  });

  $createSourceRef.on('input', function () {
    createState.sourceValidated = false;
    clearMaterials();
    setFeedback($sourceLookupFeedback, 'info', 'Validating source transaction...');
    updateCreateSubmitState();

    if (sourceLookupTimer) {
      clearTimeout(sourceLookupTimer);
    }

    sourceLookupTimer = setTimeout(function () {
      lookupSource();
    }, 350);
  });

  $createReason.on('input', function () {
    updateCreateSubmitState();
  });

  $createMaterials.on('change', function () {
    updateSelectedMaterialsTotal();
    updateCreateSubmitState();
  });

  $createForm.on('submit', function (e) {
    e.preventDefault();

    var payload = {
      action: 'create',
      source_ref_id: String($createSourceRef.val() || '').trim(),
      student_email: String($createStudentEmail.val() || '').trim(),
      material_ids: getSelectedMaterialIds(),
      reason: String($createReason.val() || '').trim()
    };

    if (!payload.source_ref_id || !payload.student_email || payload.material_ids.length === 0 || !payload.reason) {
      showToast('bg-danger', 'Student email, source ref, materials and reason are required.');
      updateCreateSubmitState();
      return;
    }

    $createBtn.prop('disabled', true).text('Creating...');

    $.ajax({
      url: endpoint,
      method: 'POST',
      dataType: 'json',
      data: payload,
      traditional: true,
      success: function (res) {
        if (res.status === 'success') {
          showToast('bg-success', res.message || 'Refund created successfully.');
          var createModal = bootstrap.Modal.getInstance($createModal.get(0));
          if (createModal) {
            createModal.hide();
          }
          fetchQueue();
          reloadMonitoring();
        } else {
          showToast('bg-danger', res.message || 'Failed to create refund.');
          updateCreateSubmitState();
        }
      },
      error: function (xhr) {
        var message = 'Failed to create refund.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          message = xhr.responseJSON.message;
        }
        showToast('bg-danger', message);
        updateCreateSubmitState();
      },
      complete: function () {
        if ($createBtn.text() === 'Creating...') {
          $createBtn.text('Create Refund');
        }
      }
    });
  });

  $createModal.on('shown.bs.modal', function () {
    updateCreateSubmitState();
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

  resetCreateForm();
  fetchQueue();
  reloadMonitoring();
});

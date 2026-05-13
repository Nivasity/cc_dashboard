$(function () {
  var endpointUrl = 'model/wallet_pre_credits.php';
  var pageConfig = window.ccWalletPreCredits || {};
  var tablesReady = !!pageConfig.tablesReady;
  var missingTables = Array.isArray(pageConfig.missingTables) ? pageConfig.missingTables : [];
  var currentWallet = null;
  var walletPreCreditTable = null;
  var activeDetailRecord = null;
  var $pageAlert = $('#walletPreCreditPageAlert');
  var $tableNotice = $('#walletPreCreditTableNotice');
  var $tableBody = $('#walletPreCreditTableBody');
  var $filtersForm = $('#walletPreCreditFilters');
  var $statusFilter = $('#walletPreCreditStatusFilter');
  var $dateRange = $('#walletPreCreditDateRange');
  var $customDateRange = $('#walletPreCreditCustomDateRange');
  var $startDate = $('#walletPreCreditStartDate');
  var $endDate = $('#walletPreCreditEndDate');
  var $createForm = $('#walletPreCreditCreateForm');
  var $lookupButton = $('#walletPreCreditLookupBtn');
  var $submitButton = $('#walletPreCreditSubmitBtn');
  var $lookupInput = $('#walletPreCreditAccountNumber');
  var $modalAlert = $('#walletPreCreditModalAlert');
  var $walletPreview = $('#walletPreCreditWalletPreview');
  var $detailFields = $('#walletPreCreditDetailFields');
  var $detailModalAlert = $('#walletPreCreditDetailAlert');
  var $updateForm = $('#walletPreCreditUpdateForm');
  var $updateButton = $('#walletPreCreditUpdateBtn');
  var tableState = {
    loading: false
  };

  function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatCurrency(amount) {
    var numericAmount = Number(amount || 0);
    return '₦' + numericAmount.toLocaleString('en-NG', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function statusBadge(status, label) {
    var tone = 'secondary';
    if (status === 'confirmed') {
      tone = 'success';
    } else if (status === 'amount_disputed') {
      tone = 'danger';
    } else if (status === 'pending_confirmation') {
      tone = 'warning';
    }

    return '<span class="badge bg-label-' + tone + '">' + escapeHtml(label || status) + '</span>';
  }

  function toggleCustomDateRange() {
    $customDateRange.toggleClass('d-none', $dateRange.val() !== 'custom');
  }

  function showAlert($element, tone, message) {
    var classes = 'alert-success alert-danger alert-warning alert-info alert-secondary';
    if (!$element.length) {
      return;
    }

    if (!message) {
      $element.addClass('d-none').removeClass(classes).empty();
      return;
    }

    $element.removeClass('d-none').removeClass(classes).addClass('alert-' + (tone || 'info')).html(message);
  }

  function setCreateFormState(lookupComplete) {
    $detailFields.toggleClass('d-none', !lookupComplete);
    $submitButton.prop('disabled', !lookupComplete);
  }

  function resetCreateForm(keepAccountNumber) {
    currentWallet = null;
    $walletPreview.addClass('d-none').empty();
    setCreateFormState(false);
    showAlert($modalAlert, null, '');
    $createForm.find('#walletPreCreditReference').val('');
    $createForm.find('#walletPreCreditAmount').val('');
    $createForm.find('#walletPreCreditReceipt').val('');
    $createForm.find('#walletPreCreditAdminNote').val('');
    if (!keepAccountNumber) {
      $lookupInput.val('');
    }
  }

  function renderWalletPreview(wallet) {
    var lines = [];
    var meta = [];
    var studentName = wallet.student_name || 'Unknown User';
    var matricOrEmail = wallet.matric_no || wallet.email || '-';

    lines.push('<div class="fw-semibold mb-1">' + escapeHtml(studentName) + '</div>');
    lines.push('<div class="small text-muted mb-2">' + escapeHtml(matricOrEmail) + '</div>');
    meta.push('<div><span class="fw-semibold">Account:</span> ' + escapeHtml(wallet.account_number || '-') + '</div>');
    meta.push('<div><span class="fw-semibold">Account Name:</span> ' + escapeHtml(wallet.account_name || '-') + '</div>');
    meta.push('<div><span class="fw-semibold">Bank:</span> ' + escapeHtml(wallet.bank_name || '-') + '</div>');
    meta.push('<div><span class="fw-semibold">Wallet Balance:</span> ' + formatCurrency(wallet.wallet_balance || 0) + '</div>');
    meta.push('<div><span class="fw-semibold">School:</span> ' + escapeHtml(wallet.school_name || '-') + '</div>');
    meta.push('<div><span class="fw-semibold">Faculty / Dept:</span> ' + escapeHtml((wallet.faculty_name || '-') + ' / ' + (wallet.dept_name || '-')) + '</div>');

    $walletPreview.html(lines.join('') + '<div class="small text-body d-grid gap-1">' + meta.join('') + '</div>').removeClass('d-none');
  }

  function updateSummary(summary) {
    $('#walletPreCreditTotalCount').text(Number(summary.total_count || 0).toLocaleString('en-NG'));
    $('#walletPreCreditPendingCount').text(Number(summary.pending_count || 0).toLocaleString('en-NG'));
    $('#walletPreCreditConfirmedCount').text(Number(summary.confirmed_count || 0).toLocaleString('en-NG'));
    $('#walletPreCreditOverdueCount').text(Number(summary.overdue_count || 0).toLocaleString('en-NG'));
  }

  function renderTableRows(rows) {
    if ($.fn.dataTable.isDataTable('#walletPreCreditTable')) {
      $('#walletPreCreditTable').DataTable().clear().destroy();
    }

    $tableBody.empty();
    if (!Array.isArray(rows) || !rows.length) {
      $tableBody.html('<tr><td colspan="9" class="text-center text-muted py-4">No wallet pre-credit records matched the current filters.</td></tr>');
      return;
    }

    rows.forEach(function (row) {
      var studentMeta = [];
      if (row.matric_no) {
        studentMeta.push(escapeHtml(row.matric_no));
      }
      if (!row.matric_no && row.email) {
        studentMeta.push(escapeHtml(row.email));
      }
      if (row.school_name) {
        studentMeta.push(escapeHtml(row.school_name));
      }

      var receiptHtml = row.receipt_url
        ? '<a href="' + escapeHtml(row.receipt_url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(row.receipt_name || 'View receipt') + '</a>'
        : '<span class="text-muted">No receipt</span>';
      var flagHtml = row.overdue
        ? '<span class="badge bg-label-danger wallet-pre-credit-flag">48h+ pending</span>'
        : '<span class="text-muted">-</span>';
      var trClass = row.overdue ? ' class="table-warning"' : '';

      $tableBody.append(
        '<tr' + trClass + '>' +
          '<td data-order="' + escapeHtml(row.created_at || '') + '">' + escapeHtml(row.created_at_display || '-') + '</td>' +
          '<td>' +
            '<div class="fw-semibold">' + escapeHtml(row.student_name || 'Unknown User') + '</div>' +
            '<small class="text-muted d-block">' + (studentMeta.length ? studentMeta.join(' • ') : '-') + '</small>' +
          '</td>' +
          '<td>' + escapeHtml(row.account_number || '-') + '</td>' +
          '<td>' + escapeHtml(row.provider_reference || '-') + '</td>' +
          '<td data-order="' + escapeHtml(String(row.amount || 0)) + '" class="fw-semibold">' + formatCurrency(row.amount || 0) + '</td>' +
          '<td>' + statusBadge(row.status, row.status_label) + '</td>' +
          '<td>' + receiptHtml + '</td>' +
          '<td>' + flagHtml + '</td>' +
          '<td><button type="button" class="btn btn-sm btn-outline-primary js-view-wallet-pre-credit" data-id="' + Number(row.id || 0) + '">View</button></td>' +
        '</tr>'
      );
    });

    walletPreCreditTable = $('#walletPreCreditTable').DataTable({
      order: [[0, 'desc']],
      responsive: true,
      pageLength: 25
    });
  }

  function fetchRows() {
    if (!tablesReady || tableState.loading) {
      return;
    }

    tableState.loading = true;
    showAlert($pageAlert, null, '');
    $tableBody.html('<tr><td colspan="9" class="text-center text-muted py-4">Loading wallet pre-credit records...</td></tr>');

    $.ajax({
      url: endpointUrl,
      method: 'GET',
      data: $filtersForm.serialize(),
      dataType: 'json'
    }).done(function (response) {
      if (!response.success) {
        showAlert($pageAlert, 'danger', response.message || 'Failed to load wallet pre-credit records.');
        renderTableRows([]);
        return;
      }

      updateSummary(response.summary || {});
      renderTableRows(response.rows || []);

      if (response.table_notice) {
        $tableNotice.removeClass('d-none').text(response.table_notice);
      } else {
        $tableNotice.addClass('d-none').text('');
      }
    }).fail(function (xhr) {
      var response = xhr.responseJSON || {};
      var message = response.message || 'Failed to load wallet pre-credit records.';
      showAlert($pageAlert, 'danger', message);
      renderTableRows([]);
    }).always(function () {
      tableState.loading = false;
    });
  }

  function loadDetails(id) {
    showAlert($detailModalAlert, null, '');
    $.ajax({
      url: endpointUrl,
      method: 'GET',
      data: { fetch: 'details', id: id },
      dataType: 'json'
    }).done(function (response) {
      if (!response.success || !response.record) {
        showAlert($detailModalAlert, 'danger', response.message || 'Could not load the selected pre-credit record.');
        return;
      }

      activeDetailRecord = response.record;
      $('#walletPreCreditDetailId').val(response.record.id || '');
      $('#walletPreCreditDetailStudent').text(response.record.student_name || '-');
      $('#walletPreCreditDetailStatusLabel').html(statusBadge(response.record.status, response.record.status_label));
      $('#walletPreCreditDetailReference').text(response.record.provider_reference || '-');
      $('#walletPreCreditDetailAmount').text(formatCurrency(response.record.amount || 0));
      $('#walletPreCreditDetailAccountNumber').text(response.record.account_number || '-');
      $('#walletPreCreditDetailConfirmedAmount').text(response.record.confirmed_amount !== null ? formatCurrency(response.record.confirmed_amount) : '-');
      $('#walletPreCreditDetailCreatedAt').text(response.record.created_at_display || '-');
      $('#walletPreCreditDetailConfirmedAt').text(response.record.confirmed_at_display || '-');
      $('#walletPreCreditDetailAdminNote').val(response.record.admin_note || '');
      $('#walletPreCreditDetailReconciliationNote').val(response.record.reconciliation_note || '');
      $('#walletPreCreditDetailStatus').val(response.record.status || 'pending_confirmation');
      $('#walletPreCreditDetailFlagText').text(response.record.overdue ? 'This record is older than 48 hours and still pending confirmation.' : 'No pending flag.');

      if (response.record.receipt_url) {
        $('#walletPreCreditDetailReceipt').html('<a href="' + escapeHtml(response.record.receipt_url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(response.record.receipt_name || 'View receipt') + '</a>');
      } else {
        $('#walletPreCreditDetailReceipt').text('-');
      }

      if (response.record.status === 'confirmed') {
        $('#walletPreCreditDetailStatus').prop('disabled', true);
      } else {
        $('#walletPreCreditDetailStatus').prop('disabled', false);
      }
    }).fail(function (xhr) {
      var response = xhr.responseJSON || {};
      showAlert($detailModalAlert, 'danger', response.message || 'Could not load the selected pre-credit record.');
    });
  }

  if (!tablesReady) {
    showAlert($pageAlert, 'warning', 'Wallet pre-credit tables are not available in this environment yet. Missing table(s): <strong>' + escapeHtml(missingTables.join(', ')) + '</strong>.');
    return;
  }

  toggleCustomDateRange();
  fetchRows();

  $dateRange.on('change', function () {
    toggleCustomDateRange();
    fetchRows();
  });

  $statusFilter.on('change', fetchRows);
  $startDate.on('change', fetchRows);
  $endDate.on('change', fetchRows);
  $filtersForm.on('submit', function (event) {
    event.preventDefault();
    fetchRows();
  });
  $('#walletPreCreditResetBtn').on('click', function () {
    $statusFilter.val('all');
    $dateRange.val('30');
    $startDate.val('');
    $endDate.val('');
    toggleCustomDateRange();
    fetchRows();
  });

  $lookupInput.on('input', function () {
    resetCreateForm(true);
  });

  $('#walletPreCreditModal').on('hidden.bs.modal', function () {
    $createForm[0].reset();
    resetCreateForm(false);
  });

  $lookupButton.on('click', function () {
    var accountNumber = ($lookupInput.val() || '').trim();
    if (!accountNumber) {
      showAlert($modalAlert, 'warning', 'Enter the wallet account number to continue.');
      return;
    }

    showAlert($modalAlert, 'info', '<span class="spinner-border spinner-border-sm me-2"></span>Looking up wallet...');
    $lookupButton.prop('disabled', true);

    $.ajax({
      url: endpointUrl,
      method: 'POST',
      data: {
        lookup_wallet: 1,
        account_number: accountNumber
      },
      dataType: 'json'
    }).done(function (response) {
      if (!response.success || !response.wallet) {
        currentWallet = null;
        $walletPreview.addClass('d-none').empty();
        setCreateFormState(false);
        showAlert($modalAlert, 'danger', response.message || 'No matching wallet was found.');
        return;
      }

      currentWallet = response.wallet;
      renderWalletPreview(response.wallet);
      setCreateFormState(true);
      showAlert($modalAlert, 'success', response.message || 'Wallet found. Complete the pre-credit details.');
    }).fail(function (xhr) {
      var response = xhr.responseJSON || {};
      currentWallet = null;
      $walletPreview.addClass('d-none').empty();
      setCreateFormState(false);
      showAlert($modalAlert, 'danger', response.message || 'No matching wallet was found.');
    }).always(function () {
      $lookupButton.prop('disabled', false);
    });
  });

  $createForm.on('submit', function (event) {
    event.preventDefault();
    if (!currentWallet || !currentWallet.wallet_id) {
      showAlert($modalAlert, 'warning', 'Look up the wallet account number before creating a pre-credit.');
      return;
    }

    var formData = new FormData(this);
    formData.append('create_pre_credit', '1');
    showAlert($modalAlert, 'info', '<span class="spinner-border spinner-border-sm me-2"></span>Crediting wallet...');
    $submitButton.prop('disabled', true);

    $.ajax({
      url: endpointUrl,
      method: 'POST',
      data: formData,
      dataType: 'json',
      processData: false,
      contentType: false
    }).done(function (response) {
      if (!response.success) {
        showAlert($modalAlert, 'danger', response.message || 'Failed to create the wallet pre-credit.');
        return;
      }

      showAlert($modalAlert, 'success', response.message || 'Wallet pre-credit created successfully.');
      fetchRows();
      if (typeof showToast === 'function') {
        showToast('bg-success', response.message || 'Wallet pre-credit created successfully.');
      }

      setTimeout(function () {
        var modalEl = document.getElementById('walletPreCreditModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) {
          modal.hide();
        }
      }, 700);
    }).fail(function (xhr) {
      var response = xhr.responseJSON || {};
      showAlert($modalAlert, 'danger', response.message || 'Failed to create the wallet pre-credit.');
    }).always(function () {
      $submitButton.prop('disabled', !currentWallet);
    });
  });

  $(document).on('click', '.js-view-wallet-pre-credit', function () {
    var id = Number($(this).data('id') || 0);
    if (!id) {
      return;
    }

    var modalEl = document.getElementById('walletPreCreditDetailModal');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
    loadDetails(id);
  });

  $updateForm.on('submit', function (event) {
    event.preventDefault();
    var preCreditId = Number($('#walletPreCreditDetailId').val() || 0);
    if (!preCreditId) {
      showAlert($detailModalAlert, 'danger', 'Select a pre-credit record before saving updates.');
      return;
    }

    $updateButton.prop('disabled', true);
    showAlert($detailModalAlert, 'info', '<span class="spinner-border spinner-border-sm me-2"></span>Saving update...');

    $.ajax({
      url: endpointUrl,
      method: 'POST',
      data: $(this).serialize() + '&update_pre_credit=1',
      dataType: 'json'
    }).done(function (response) {
      if (!response.success || !response.record) {
        showAlert($detailModalAlert, 'danger', response.message || 'Failed to update the wallet pre-credit record.');
        return;
      }

      showAlert($detailModalAlert, 'success', response.message || 'Wallet pre-credit updated successfully.');
      if (typeof showToast === 'function') {
        showToast('bg-success', response.message || 'Wallet pre-credit updated successfully.');
      }
      fetchRows();
      loadDetails(response.record.id);
    }).fail(function (xhr) {
      var response = xhr.responseJSON || {};
      showAlert($detailModalAlert, 'danger', response.message || 'Failed to update the wallet pre-credit record.');
    }).always(function () {
      $updateButton.prop('disabled', false);
    });
  });
});
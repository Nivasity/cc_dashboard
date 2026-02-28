$(document).ready(function () {
  var refundId = Number(window.refundId || 0);
  var endpoint = 'model/refunds.php';
  var ledgerTable = null;

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

  function badgeClass(status) {
    switch (status) {
      case 'reserved':
      case 'pending':
        return 'warning';
      case 'consumed':
      case 'applied':
        return 'success';
      case 'released':
        return 'info';
      case 'partially_applied':
        return 'primary';
      case 'cancelled':
        return 'secondary';
      default:
        return 'secondary';
    }
  }

  function renderSummary(refund, totals) {
    $('#summaryId').text(refund.id || '-');
    $('#summarySchool').text(refund.school_name || ('School #' + (refund.school_id || '-')));
    $('#summaryStudent').text(refund.student_name || (refund.student_id ? ('ID ' + refund.student_id) : '-'));
    $('#summaryRefId').text(refund.ref_id || '-');
    $('#summaryStatus').html('<span class="badge bg-label-' + badgeClass(refund.status) + '">' +
      escapeHtml((refund.status || '').replace('_', ' ')) + '</span>');
    $('#summaryAmount').text(formatCurrency(refund.amount));
    $('#summaryRemaining').text(formatCurrency(refund.remaining_amount));
    $('#summaryConsumed').text(formatCurrency(refund.consumed_amount));
    $('#summaryReason').text(refund.reason || '-');

    $('#totalReserved').text(formatCurrency(totals.total_reserved));
    $('#totalConsumed').text(formatCurrency(totals.total_consumed));
    $('#totalReleased').text(formatCurrency(totals.total_released));
    $('#totalOutstanding').text(formatCurrency(totals.outstanding));
  }

  function renderLedgerRows(rows) {
    var $tbody = $('#refundLedgerTable tbody');
    $tbody.empty();

    if (!Array.isArray(rows) || rows.length === 0) {
      $tbody.append('<tr><td colspan="8" class="text-center text-muted">No split ledger rows found.</td></tr>');
      if (ledgerTable) {
        ledgerTable.clear().destroy();
        ledgerTable = null;
      }
      return;
    }

    rows.forEach(function (row) {
      $tbody.append(
        '<tr>' +
          '<td>' + escapeHtml(row.split_sequence || '-') + '</td>' +
          '<td>' + escapeHtml(row.ref_id || '-') + '</td>' +
          '<td>' + formatCurrency(row.amount) + '</td>' +
          '<td><span class="badge bg-label-' + badgeClass(row.status) + '">' + escapeHtml(row.status || '-') + '</span></td>' +
          '<td>' + escapeHtml(row.reserved_at || '-') + '</td>' +
          '<td>' + escapeHtml(row.consumed_at || '-') + '</td>' +
          '<td>' + escapeHtml(row.released_at || '-') + '</td>' +
          '<td>' + escapeHtml(row.release_reason || '-') + '</td>' +
        '</tr>'
      );
    });

    if (ledgerTable) {
      ledgerTable.clear().destroy();
      ledgerTable = null;
    }

    ledgerTable = new DataTable('#refundLedgerTable', {
      pageLength: 25,
      order: [[0, 'asc']]
    });
  }

  function loadDetail() {
    if (!refundId) {
      showToast('bg-danger', 'Missing refund id.');
      return;
    }

    $.ajax({
      url: endpoint,
      method: 'GET',
      dataType: 'json',
      data: {
        action: 'detail',
        refund_id: refundId
      },
      success: function (res) {
        if (res.status === 'success') {
          renderSummary(res.refund || {}, res.totals || {});
          renderLedgerRows(res.reservations || []);
        } else {
          showToast('bg-danger', res.message || 'Failed to load refund detail.');
        }
      },
      error: function (xhr) {
        var message = 'Failed to load refund detail.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          message = xhr.responseJSON.message;
        }
        showToast('bg-danger', message);
      }
    });
  }

  loadDetail();
});

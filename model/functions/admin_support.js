$(document).ready(function () {
  var currentStatus = 'open';
  var currentAssignment = 'mine';

  function badgeForStatus(status) {
    var cls = 'secondary';
    if (status === 'open') cls = 'warning';
    else if (status === 'pending') cls = 'info';
    else if (status === 'resolved') cls = 'success';
    else if (status === 'closed') cls = 'dark';
    return '<span class="badge bg-label-' + cls + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
  }

  function badgeForPriority(priority) {
    var cls = 'secondary';
    if (priority === 'low') cls = 'secondary';
    else if (priority === 'medium') cls = 'primary';
    else if (priority === 'high') cls = 'warning';
    else if (priority === 'urgent') cls = 'danger';
    return '<span class="badge bg-label-' + cls + '">' + priority.charAt(0).toUpperCase() + priority.slice(1) + '</span>';
  }

  function renderConversation(messages) {
    var html = '';
    if (!Array.isArray(messages) || !messages.length) {
      return '<div class="text-muted small">No messages yet.</div>';
    }

    messages.forEach(function (m) {
      var senderLabel = 'System';
      if (m.sender_type === 'admin') {
        senderLabel = m.admin_name ? ('Support: ' + m.admin_name) : 'Admin';
      }

      var isMine = (m.sender_type === 'admin' && typeof ccAdminId !== 'undefined' && m.admin_id && Number(m.admin_id) === Number(ccAdminId));

      var labelClass = 'fw-bold';
      var msgClass = 'ticket-message border rounded p-2 w-75';
      if (isMine) {
        labelClass += ' text-secondary';
        msgClass += ' text-secondary ms-auto';
      } else {
        msgClass += ' me-auto';
      }

      var wrapperClass = 'mb-3';
      if (isMine) {
        wrapperClass += ' text-end';
      }

      var headerClass = 'd-flex justify-content-between align-items-center';
      if (isMine) {
        headerClass = 'd-flex justify-content-end align-items-center';
      }

      var ts = m.created_at_formatted || m.created_at || '';
      html += '<div class="' + wrapperClass + '">';
      html += '<div class="' + headerClass + '">';
      html += '<small class="' + labelClass + '">' + senderLabel + '</small>';
      html += '</div>';
      if (m.is_internal && m.is_internal !== '0') {
        html += '<span class="badge bg-label-info mb-1">Internal note</span><br>';
      }
      html += '<div class="' + msgClass + '">' + (m.body || '') + '</div>';
      if (ts) {
        html += '<div class="mt-1"><small class="text-muted">' + ts + '</small></div>';
      }

      if (Array.isArray(m.attachments) && m.attachments.length) {
        html += '<div class="mt-1 small">Attachments: ';
        m.attachments.forEach(function (a, idx) {
          if (!a.file_path || !a.file_name) return;
          var href = a.file_path;
          if (!/^https?:\/\//i.test(href) && href.charAt(0) !== '/') {
            href = '/' + href;
          }
          if (idx > 0) html += ', ';
          html += '<a href="' + href + '" target="_blank" rel="noopener noreferrer">' + a.file_name + '</a>';
        });
        html += '</div>';
      }

      html += '</div>';
    });

    return html;
  }

  function fetchAdminTickets() {
    $.ajax({
      url: 'model/admin_support.php',
      method: 'GET',
      data: { fetch: 'tickets', status: currentStatus, assignment: currentAssignment },
      dataType: 'json',
      success: function (res) {
        if ($.fn.dataTable.isDataTable('#adminTicketsTable')) {
          var table = $('#adminTicketsTable').DataTable();
          table.clear().draw().destroy();
        }
        var tbody = $('#adminTicketsTable tbody');
        tbody.empty();
        if (res.status === 'success' && res.tickets) {
          $.each(res.tickets, function (i, t) {
            var row = '<tr>' +
              '<td class="fw-bold">#' + t.code + '</td>' +
              '<td>' + t.subject + '</td>' +
              '<td>' + badgeForPriority((t.priority || '').toLowerCase()) + '</td>' +
              '<td>' + badgeForStatus((t.status || '').toLowerCase()) + '</td>' +
              '<td>' + (t.created_by || '-') + '</td>' +
              '<td>' + (t.assigned_to || '-') + '</td>' +
              '<td>' + (t.date || '') + '<br>' + (t.time || '') + '</td>' +
              '<td><button class="btn btn-sm btn-outline-primary viewAdminTicket" data-code="' + t.code + '">View</button></td>' +
              '</tr>';
            tbody.append(row);
          });
        }
        InitiateDatatable('#adminTicketsTable');
      },
      error: function () {
        showToast && showToast('bg-danger', 'Failed to load internal tickets');
      }
    });
  }

  function loadAdminTicket(code) {
    $.ajax({
      url: 'model/admin_support.php',
      method: 'GET',
      data: { fetch: 'ticket', code: code },
      dataType: 'json',
      success: function (res) {
        if (res.status !== 'success' || !res.ticket) {
          showToast && showToast('bg-danger', res.message || 'Ticket not found');
          return;
        }
        var t = res.ticket;
        $('#adt_view_code').text('#' + t.code);
        $('#adt_view_status').html(badgeForStatus((t.status || '').toLowerCase()));
        $('#adt_view_created_by').text(t.created_by || '-');
        $('#adt_view_assigned_to').text(t.assigned_to || '-');
        $('#adt_view_subject').text(t.subject || '-');
        $('#adt_view_code_input').val(t.code);

        var convHtml = renderConversation(res.messages || []);
        $('#adt_view_conversation').html(convHtml);

        $('#adt_view_close').prop('checked', false);
        $('#adt_view_response').val('');
        $('#adt_view_attachments').val('');

        var modal = new bootstrap.Modal(document.getElementById('adminTicketModal'));
        modal.show();
      },
      error: function () {
        showToast && showToast('bg-danger', 'Failed to load internal ticket');
      }
    });
  }

  // Filters
  $('#adminTicketStatusFilter').on('change', function () {
    currentStatus = $(this).val();
    fetchAdminTickets();
  });
  $('#adminTicketAssignmentFilter').on('change', function () {
    currentAssignment = $(this).val();
    fetchAdminTickets();
  });

  // View ticket
  $(document).on('click', '.viewAdminTicket', function () {
    var code = $(this).data('code');
    loadAdminTicket(code);
  });

  // Create new internal ticket
  $('#adminNewTicketForm').on('submit', function (e) {
    e.preventDefault();
    var form = this;
    var formData = new FormData(form);
    formData.append('create_ticket', 1);
    var $btn = $(form).find('button[type=submit]');
    var original = $btn.html();
    $.ajax({
      url: 'model/admin_support.php',
      method: 'POST',
      data: formData,
      dataType: 'json',
      processData: false,
      contentType: false,
      beforeSend: function () {
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Creating...');
      },
      success: function (res) {
        showToast && showToast(res.status === 'success' ? 'bg-success' : 'bg-danger', res.message || 'Done');
        if (res.status === 'success') {
          fetchAdminTickets();
          var modalEl = document.getElementById('newAdminTicketModal');
          var modal = bootstrap.Modal.getInstance(modalEl);
          modal && modal.hide();
          form.reset();
        }
      },
      complete: function () {
        $btn.prop('disabled', false).html(original);
      },
      error: function () {
        showToast && showToast('bg-danger', 'Network error');
      }
    });
  });

  // Respond on internal ticket
  $('#adminRespondForm').on('submit', function (e) {
    e.preventDefault();
    var form = this;
    var formData = new FormData(form);
    formData.append('respond_ticket', 1);
    var $btn = $(form).find('button[type=submit]');
    var original = $btn.html();
    $.ajax({
      url: 'model/admin_support.php',
      method: 'POST',
      data: formData,
      dataType: 'json',
      processData: false,
      contentType: false,
      beforeSend: function () {
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Sending...');
      },
      success: function (res) {
        showToast && showToast(res.status === 'success' ? 'bg-success' : 'bg-danger', res.message || 'Done');
        if (res.status === 'success') {
          fetchAdminTickets();
          var code = $('#adt_view_code_input').val();
          if (code) {
            loadAdminTicket(code);
          }
        }
      },
      complete: function () {
        $btn.prop('disabled', false).html(original);
      },
      error: function () {
        showToast && showToast('bg-danger', 'Network error');
      }
    });
  });

  // Initial load
  fetchAdminTickets();
});

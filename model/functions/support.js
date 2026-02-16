$(document).ready(function () {
  var currentStatus = (new URLSearchParams(window.location.search).get('status') || 'open').toLowerCase();

  function setActiveTab(status) {
    $('#ticketTabs .nav-link').removeClass('active');
    if (status === 'closed') $('#closed-tab').addClass('active');
    else $('#open-tab').addClass('active');
  }

  function badgeForStatus(status) {
    var cls = 'secondary';
    if (status === 'open') cls = 'warning';
    else if (status === 'closed') cls = 'success';
    return '<span class="badge bg-label-' + cls + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
  }

  function renderConversation(messages) {
    var html = '';
    if (!Array.isArray(messages) || !messages.length) {
      return '<div class="text-muted small">No messages yet.</div>';
    }

    // Helper function to escape HTML entities
    function escapeHtml(text) {
      if (!text) return '';
      var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    messages.forEach(function (m) {
      var senderLabel = 'System';
      if (m.sender_type === 'user') {
        senderLabel = m.user_name ? m.user_name : 'User';
      } else if (m.sender_type === 'admin') {
        senderLabel = m.admin_name ? ('Support: ' + m.admin_name) : 'Admin';
      }

      var labelClass = '';
      var msgClass = 'ticket-message border rounded p-2 w-75';
      if (m.sender_type === 'admin') {
        labelClass += ' ';
        msgClass += ' ms-auto';
      } else {
        msgClass += ' me-auto';
      }

      var wrapperClass = 'mb-3';
      if (m.sender_type === 'admin') {
        wrapperClass += ' text-end';
      }

      var headerClass = 'd-flex justify-content-between align-items-center';
      if (m.sender_type === 'admin') {
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
        html += '<div class="mt-1"><small class="text-muted text-xs">' + ts + '</small></div>';
      }

      if (Array.isArray(m.attachments) && m.attachments.length) {
        // Helper function to check if attachment is an image
        var isImageAttachment = function(attachment) {
          if (!attachment || !attachment.file_name) return false;
          // Note: SVG excluded due to potential XSS risks (can contain embedded scripts)
          var isImage = /\.(jpg|jpeg|png|gif|bmp|webp)$/i.test(attachment.file_name);
          var mimeIsImage = attachment.mime_type && /^image\/(jpeg|png|gif|bmp|webp)$/i.test(attachment.mime_type);
          return isImage || mimeIsImage;
        };
        
        // Helper function to validate and sanitize URLs
        var sanitizeUrl = function(url) {
          if (!url) return '#';
          // Only allow http and https protocols
          if (/^https?:\/\//i.test(url)) {
            return url;
          }
          // For relative paths, ensure they start with /
          if (url.charAt(0) === '/') {
            return url;
          }
          return '/' + url;
        };
        
        // Helper function to get alignment class based on sender type
        var getAlignmentClass = function(senderType) {
          return senderType === 'admin' ? ' ms-auto' : ' me-auto';
        };
        
        var hasNonImageAttachment = false;
        var messageWidth = 'w-75'; // Same width as message body
        
        m.attachments.forEach(function (a, idx) {
          if (!a.file_path || !a.file_name) return;
          var href = sanitizeUrl(a.file_path);
          var escapedHref = escapeHtml(href);
          var escapedFileName = escapeHtml(a.file_name);
          
          if (isImageAttachment(a)) {
            // Display image preview with same max-width as message
            html += '<div class="mt-2 ' + messageWidth + getAlignmentClass(m.sender_type) + '">';
            html += '<a href="' + escapedHref + '" target="_blank" rel="noopener noreferrer">';
            html += '<img src="' + escapedHref + '" class="img-fluid rounded border" alt="' + escapedFileName + '">';
            html += '</a>';
            html += '<div class="small text-muted mt-1">' + escapedFileName + '</div>';
            html += '</div>';
          } else {
            // Display as link for non-image files
            if (!hasNonImageAttachment) {
              html += '<div class="mt-1 small">Attachments: ';
              hasNonImageAttachment = true;
            } else {
              html += ', ';
            }
            html += '<a href="' + escapedHref + '" target="_blank" rel="noopener noreferrer">' + escapedFileName + '</a>';
          }
        });
        
        // Close the attachments div if we had any non-image attachments
        if (hasNonImageAttachment) {
          html += '</div>';
        }
      }

      html += '</div>';
    });

    return html;
  }

  function fetchTickets(status) {
    $.ajax({
      url: 'model/support.php',
      method: 'GET',
      data: { fetch: 'tickets', status: status },
      dataType: 'json',
      success: function (res) {
        if ($.fn.dataTable.isDataTable('.table')) {
          var table = $('.table').DataTable();
          table.clear().draw().destroy();
        }
        var tbody = $('.table tbody');
        tbody.empty();
        if (res.status === 'success' && res.tickets) {
          $.each(res.tickets, function (i, t) {
            var row = '<tr>' +
              '<td class="fw-bold">#' + t.code + '</td>' +
              '<td><span class="text-uppercase text-primary">' + t.student + '</span><br><small>' + t.email + '</small></td>' +
              '<td>' + t.subject + '</td>' +
              '<td>' + (t.category || '-') + '</td>' +
              '<td>' + t.date + '<br>' + t.time + '</td>' +
              '<td>' + badgeForStatus(t.status) + '</td>' +
              '<td><button class="btn btn-sm btn-outline-primary viewTicket" data-code="' + t.code + '">View</button></td>' +
              '</tr>';
            tbody.append(row);
          });
        }
        InitiateDatatable('.table');
      },
      error: function () {
        showToast && showToast('bg-danger', 'Failed to load tickets');
      }
    });
  }

  function loadTicket(code) {
    $.ajax({
      url: 'model/support.php',
      method: 'GET',
      data: { fetch: 'ticket', code: code },
      dataType: 'json',
      success: function (res) {
        if (res.status !== 'success' || !res.ticket) {
          showToast && showToast('bg-danger', res.message || 'Ticket not found');
          return;
        }
        var t = res.ticket;
        $('#t_code').text('#' + t.code);
        $('#t_status').html(badgeForStatus(t.status));
        $('#t_student').text(t.student);
        $('#t_email').text(t.email);
        $('#t_subject').text(t.subject);
        $('#r_code').val(t.code);

        var convHtml = renderConversation(res.messages || []);
        $('#t_conversation').html(convHtml);

        if (t.status === 'open') {
          $('#respondForm').show();
          $('#reopenBtn').hide();
          $('#r_message').val('');
        } else {
          $('#respondForm').hide();
          $('#reopenBtn').show();
        }

        var modal = new bootstrap.Modal(document.getElementById('ticketModal'));
        modal.show();
      },
      error: function () {
        showToast && showToast('bg-danger', 'Failed to load ticket');
      }
    });
  }

  $('#ticketTabs').on('click', '.nav-link', function () {
    var status = $(this).data('status');
    if (!status) return;
    currentStatus = status;
    setActiveTab(currentStatus);
    fetchTickets(currentStatus);
    const url = new URL(window.location);
    url.searchParams.set('status', currentStatus);
    window.history.replaceState({}, '', url);
  });

  $(document).on('click', '.viewTicket', function () {
    var code = $(this).data('code');
    $('#respondForm')[0].reset();
    loadTicket(code);
  });

  $('#respondForm').on('submit', function (e) {
    e.preventDefault();
    var form = this;
    var formData = new FormData(form);
    formData.append('respond_ticket', 1);
    var $btn = $(form).find('button[type=submit]');
    var original = $btn.html();
    $.ajax({
      url: 'model/support.php',
      method: 'POST',
      data: formData,
      dataType: 'json',
      processData: false,
      contentType: false,
      beforeSend: function(){
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Sending...');
      },
      success: function (res) {
        showToast && showToast(res.status === 'success' ? 'bg-success' : 'bg-danger', res.message || 'Done');
        if (res.status === 'success') {
          fetchTickets(currentStatus);
          var modalEl = document.getElementById('ticketModal');
          var modal = bootstrap.Modal.getInstance(modalEl);
          modal && modal.hide();
        }
      },
      complete: function(){
        $btn.prop('disabled', false).html(original);
      },
      error: function(){
        showToast && showToast('bg-danger', 'Network error');
      }
    });
  });

  $('#reopenBtn').on('click', function () {
    var code = $('#r_code').val();
    $.ajax({
      url: 'model/support.php',
      method: 'POST',
      data: { reopen_ticket: 1, code: code },
      dataType: 'json',
      success: function (res) {
        showToast && showToast(res.status === 'success' ? 'bg-success' : 'bg-danger', res.message || 'Done');
        if (res.status === 'success') {
          fetchTickets(currentStatus);
          var modalEl = document.getElementById('ticketModal');
          var modal = bootstrap.Modal.getInstance(modalEl);
          modal && modal.hide();
        }
      },
      error: function(){
        showToast && showToast('bg-danger', 'Network error');
      }
    });
  });

  setActiveTab(currentStatus);
  fetchTickets(currentStatus);
});

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
        $('#t_message').text(t.message);
        $('#r_code').val(t.code);

        if (t.status === 'open') {
          $('#respondForm').show();
          $('#reopenBtn').hide();
          $('#responseBlock').hide();
          $('#r_message').val('');
        } else {
          $('#respondForm').hide();
          $('#reopenBtn').show();
          $('#responseBlock').show();
          $('#t_response').text(t.response || '-');
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
    loadTicket(code);
  });

  $('#respondForm').on('submit', function (e) {
    e.preventDefault();
    var data = $(this).serializeArray();
    data.push({ name: 'respond_ticket', value: 1 });
    var $btn = $(this).find('button[type=submit]');
    var original = $btn.html();
    $.ajax({
      url: 'model/support.php',
      method: 'POST',
      data: $.param(data),
      dataType: 'json',
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


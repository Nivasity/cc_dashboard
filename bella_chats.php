<?php
session_start();
include('model/config.php');
include('model/page_config.php');

// Bella conversations: monitor Nivasity's support assistant, answer chats she handed over
// (Waiting), and hand them back or resolve them. Data lives in the Bella Worker (D1), read
// through model/bella.php.
if (!$support_mgt_menu) {
  header('Location: index.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Bella Chats | Nivasity Command Center</title>
  <meta name="description" content="" />
  <?php include('partials/_head.php') ?>
  <style>
    .bella-list { max-height: 68vh; overflow-y: auto; }
    .bella-list .list-group-item { cursor: pointer; }
    .bella-list .list-group-item.active-conv { background: rgba(105, 108, 255, .08); }
    .bella-thread { height: 52vh; overflow-y: auto; background: #f5f5f9; }
    .bella-msg { max-width: 80%; white-space: pre-wrap; word-break: break-word; }
    .bella-msg.student { background: #696cff; color: #fff; margin-left: auto; }
    .bella-msg.bella, .bella-msg.agent { background: #fff; border: 1px solid #e4e6e8; }
    .bella-msg.agent { border-color: #71dd37; }
    .bella-meta { font-size: 11px; color: #8592a3; }
  </style>
</head>
<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php include('partials/_sidebar.php') ?>
      <div class="layout-page">
        <?php include('partials/_navbar.php') ?>
        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">
            <h4 class="fw-bold py-3 mb-3"><span class="text-muted fw-light">Support /</span> Bella Chats</h4>
            <div id="bellaAlert" class="alert d-none" role="alert"></div>

            <div class="row g-3 mb-3" id="bellaStats">
              <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3"><small class="text-muted">Waiting for team</small><h4 class="mb-0" data-stat="waiting">–</h4></div></div></div>
              <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3"><small class="text-muted">Chats today</small><h4 class="mb-0" data-stat="today">–</h4></div></div></div>
              <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3"><small class="text-muted">Handled by Bella (7 days)</small><h4 class="mb-0" data-stat="resolved_rate">–</h4></div></div></div>
              <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3"><small class="text-muted">AI tokens today</small><h4 class="mb-0" data-stat="tokens">–</h4><small class="text-muted" data-stat="provider"></small></div></div></div>
            </div>

            <div class="row g-3">
              <div class="col-lg-4">
                <div class="card h-100">
                  <div class="card-header pb-2">
                    <div class="btn-group btn-group-sm w-100 mb-2" role="group" id="bellaFilter">
                      <button type="button" class="btn btn-outline-primary active" data-status="">All</button>
                      <button type="button" class="btn btn-outline-primary" data-status="waiting">Waiting</button>
                      <button type="button" class="btn btn-outline-primary" data-status="human">With team</button>
                      <button type="button" class="btn btn-outline-primary" data-status="bella">Bella</button>
                      <button type="button" class="btn btn-outline-primary" data-status="resolved">Resolved</button>
                    </div>
                    <input type="search" class="form-control form-control-sm" id="bellaSearch" placeholder="Search name, email or user id" />
                  </div>
                  <div class="list-group list-group-flush bella-list" id="bellaList"></div>
                  <div class="card-footer py-2 d-flex justify-content-between align-items-center">
                    <button class="btn btn-sm btn-outline-secondary" id="bellaPrev">Prev</button>
                    <small class="text-muted" id="bellaPageInfo"></small>
                    <button class="btn btn-sm btn-outline-secondary" id="bellaNext">Next</button>
                  </div>
                </div>
              </div>

              <div class="col-lg-8">
                <div class="card h-100" id="bellaConv">
                  <div class="card-body text-muted" id="bellaEmpty">Select a conversation.</div>
                  <div class="d-none" id="bellaConvBody">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
                      <div>
                        <h5 class="mb-0" id="convName"></h5>
                        <small class="text-muted" id="convSub"></small>
                      </div>
                      <div class="d-flex gap-2 flex-wrap">
                        <span class="badge align-self-center" id="convStatus"></span>
                        <button class="btn btn-sm btn-outline-primary conv-status" data-status="bella">Hand back to Bella</button>
                        <button class="btn btn-sm btn-success conv-status" data-status="resolved">Resolve</button>
                      </div>
                    </div>
                    <div class="px-4 pb-2">
                      <div class="alert alert-warning py-2 mb-2 d-none" id="convReason"></div>
                      <details class="small mb-2 d-none" id="convSummaryWrap"><summary class="text-muted">Earlier conversations (summary)</summary><div id="convSummary" class="mt-1"></div></details>
                    </div>
                    <div class="bella-thread p-3" id="convThread"></div>
                    <form class="card-body border-top" id="convReply">
                      <textarea class="form-control mb-2" id="convText" rows="2" placeholder="Reply to the student. Bella stays quiet until you hand the chat back."></textarea>
                      <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Bella's tools and token use show under her messages.</small>
                        <button class="btn btn-primary" type="submit" id="convSend">Send reply</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php include('partials/_footer.php') ?>
          <div class="content-backdrop fade"></div>
        </div>
      </div>
    </div>
    <div class="layout-overlay layout-menu-toggle"></div>
  </div>

  <script src="assets/vendor/libs/jquery/jquery.min.js"></script>
  <script src="assets/vendor/libs/popper/popper.min.js"></script>
  <script src="assets/vendor/js/bootstrap.min.js"></script>
  <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
  <script src="assets/vendor/js/menu.min.js"></script>
  <script src="assets/js/main.js"></script>
  <script>
    $(function () {
      var STATUS = {
        waiting: ['Waiting for team', 'danger'],
        human: ['With team', 'warning'],
        bella: ['Bella', 'primary'],
        resolved: ['Resolved', 'success']
      };
      var filter = '', page = 1, total = 0, current = 0, searchTimer = null;

      function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
      function when(s) { return s ? new Date(s.replace(' ', 'T') + 'Z').toLocaleString() : ''; }
      function call(data) {
        return $.post('model/bella.php', data, null, 'json').fail(function (xhr) {
          showAlert((xhr.responseJSON && xhr.responseJSON.error) || 'Request failed');
        });
      }
      function showAlert(msg, type) {
        $('#bellaAlert').removeClass('d-none alert-danger alert-success').addClass('alert-' + (type || 'danger')).text(msg);
        setTimeout(function () { $('#bellaAlert').addClass('d-none'); }, 5000);
      }

      function loadStats() {
        call({ action: 'stats' }).done(function (r) {
          if (r.error) { showAlert(r.error); return; }
          var by = r.by_status || {}, d = r.last_24h || {}, w = r.last_7d || {};
          $('[data-stat=waiting]').text(by.waiting || 0);
          $('[data-stat=today]').text(d.conversations || 0);
          var conv = Number(w.conversations || 0), esc7 = Number(w.escalated || 0);
          $('[data-stat=resolved_rate]').text(conv ? Math.round((conv - esc7) / conv * 100) + '%' : '–');
          $('[data-stat=tokens]').text((Number(d.tokens_in || 0) + Number(d.tokens_out || 0)).toLocaleString());
          $('[data-stat=provider]').text('Model: ' + (r.provider || ''));
        });
      }

      function loadList() {
        call({ action: 'list', status: filter, q: $('#bellaSearch').val(), page: page }).done(function (r) {
          if (r.error) { $('#bellaList').html('<div class="p-3 text-muted">' + esc(r.error) + '</div>'); return; }
          total = r.total || 0;
          var rows = (r.conversations || []).map(function (c) {
            var st = STATUS[c.status] || [c.status, 'secondary'];
            return '<a class="list-group-item list-group-item-action' + (c.id == current ? ' active-conv' : '') + '" data-id="' + c.id + '">'
              + '<div class="d-flex justify-content-between"><strong class="text-truncate">' + esc(c.user_name || ('User ' + c.user_id)) + '</strong>'
              + (c.unread_admin > 0 ? '<span class="badge bg-danger rounded-pill">' + c.unread_admin + '</span>' : '') + '</div>'
              + '<div class="small text-muted text-truncate">' + esc(c.last_message || '') + '</div>'
              + '<div class="d-flex justify-content-between"><span class="badge bg-label-' + st[1] + '">' + st[0] + '</span><small class="text-muted">' + when(c.last_message_at) + '</small></div></a>';
          });
          $('#bellaList').html(rows.length ? rows.join('') : '<div class="p-3 text-muted">No conversations.</div>');
          var pages = Math.max(1, Math.ceil(total / 25));
          $('#bellaPageInfo').text('Page ' + page + ' of ' + pages + ' · ' + total);
          $('#bellaPrev').prop('disabled', page <= 1);
          $('#bellaNext').prop('disabled', page >= pages);
        });
      }

      function cardText(c) {
        if (c.type === 'checkout') return 'Go to checkout (N' + Number(c.total).toLocaleString() + ')';
        if (c.type === 'fund_wallet') return 'Fund wallet (N' + Number(c.shortfall).toLocaleString() + ' short)';
        if (c.type === 'material') return c.course_code + ' · ' + c.title;
        return c.label + ' → ' + c.path;
      }

      function loadConv(id, keepScroll) {
        current = id;
        call({ action: 'get', id: id }).done(function (r) {
          if (r.error) { showAlert(r.error); return; }
          var c = r.conversation, st = STATUS[c.status] || [c.status, 'secondary'];
          $('#bellaEmpty').addClass('d-none');
          $('#bellaConvBody').removeClass('d-none');
          $('#convName').text(c.user_name || ('User ' + c.user_id));
          $('#convSub').text((c.user_email || '') + ' · user #' + c.user_id + ' · since ' + when(c.created_at));
          $('#convStatus').attr('class', 'badge align-self-center bg-label-' + st[1]).text(st[0]);
          $('#convReason').toggleClass('d-none', !(c.escalation_reason && (c.status === 'waiting' || c.status === 'human'))).text('Bella handed over: ' + (c.escalation_reason || ''));
          $('#convSummaryWrap').toggleClass('d-none', !c.summary);
          $('#convSummary').text(c.summary || '');
          $('.conv-status[data-status=bella]').toggleClass('d-none', c.status === 'bella');
          $('.conv-status[data-status=resolved]').toggleClass('d-none', c.status === 'resolved');
          var html = (r.messages || []).map(function (m) {
            if (m.role === 'system') return '<p class="text-center bella-meta my-2">' + esc(m.content) + ' · ' + when(m.created_at) + '</p>';
            var who = m.role === 'student' ? 'Student' : m.role === 'agent' ? (m.agent_name || 'Team') : 'Bella';
            var cards = (m.cards || []).map(function (cd) { return '<span class="badge bg-label-secondary me-1 mt-1">' + esc(cardText(cd)) + '</span>'; }).join('');
            var meta = m.role === 'bella' && (m.tools || []).length ? ' · tools: ' + esc(m.tools.join(', ')) : '';
            if (m.role === 'bella' && m.tokens_in) meta += ' · ' + (Number(m.tokens_in) + Number(m.tokens_out)).toLocaleString() + ' tokens';
            var file = '';
            if (m.attachment) {
              file = m.attachment.type.indexOf('image/') === 0
                ? '<a href="' + esc(m.attachment.url) + '" target="_blank" rel="noopener"><img src="' + esc(m.attachment.url) + '" alt="" class="d-block rounded mt-1" style="max-width:220px;max-height:220px"></a>'
                : '<a href="' + esc(m.attachment.url) + '" target="_blank" rel="noopener" class="d-block mt-1 fw-semibold' + (m.role === 'student' ? ' text-white' : '') + '"><i class="bx bx-file"></i> ' + esc(m.attachment.name) + '</a>';
            }
            return '<div class="mb-3 d-flex flex-column' + (m.role === 'student' ? ' align-items-end' : '') + '">'
              + '<div class="bella-msg ' + m.role + ' rounded-3 px-3 py-2">' + esc(m.content) + file + (cards ? '<div>' + cards + '</div>' : '') + '</div>'
              + '<span class="bella-meta mt-1">' + esc(who) + ' · ' + when(m.created_at) + meta + '</span></div>';
          }).join('');
          var $t = $('#convThread');
          var atBottom = $t[0].scrollHeight - $t.scrollTop() - $t.outerHeight() < 40;
          $t.html(html || '<p class="text-muted">No messages in the last 7 days.</p>');
          if (!keepScroll || atBottom) $t.scrollTop($t[0].scrollHeight);
          $('#bellaList .list-group-item').removeClass('active-conv').filter('[data-id=' + id + ']').addClass('active-conv').find('.badge.bg-danger').remove();
        });
      }

      $('#bellaFilter').on('click', 'button', function () {
        $('#bellaFilter button').removeClass('active');
        filter = $(this).addClass('active').data('status') || '';
        page = 1; loadList();
      });
      $('#bellaSearch').on('input', function () { clearTimeout(searchTimer); searchTimer = setTimeout(function () { page = 1; loadList(); }, 350); });
      $('#bellaPrev').on('click', function () { if (page > 1) { page--; loadList(); } });
      $('#bellaNext').on('click', function () { page++; loadList(); });
      $('#bellaList').on('click', '.list-group-item', function () { loadConv($(this).data('id')); });

      $('#convReply').on('submit', function (e) {
        e.preventDefault();
        var text = $.trim($('#convText').val());
        if (!text || !current) return;
        var $b = $('#convSend').prop('disabled', true);
        call({ action: 'reply', id: current, text: text }).done(function (r) {
          if (r.error) { showAlert(r.error); return; }
          $('#convText').val('');
          loadConv(current); loadList(); loadStats();
        }).always(function () { $b.prop('disabled', false); });
      });

      $('.conv-status').on('click', function () {
        if (!current) return;
        call({ action: 'status', id: current, status: $(this).data('status') }).done(function (r) {
          if (r.error) { showAlert(r.error); return; }
          showAlert(r.status === 'bella' ? 'Bella is back in the chat.' : 'Conversation resolved.', 'success');
          loadConv(current); loadList(); loadStats();
        });
      });

      loadStats(); loadList();
      var linked = Number(new URLSearchParams(location.search).get('id') || 0);
      if (linked) loadConv(linked);
      setInterval(function () {
        if (document.hidden) return;
        loadList(); loadStats();
        if (current) loadConv(current, true);
      }, 15000);
    });
  </script>
</body>
</html>

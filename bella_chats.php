<?php
session_start();
include('model/config.php');
include('model/page_config.php');
// Material change (swap) override: super admin, admin, support
$can_change_material = in_array((int) ($_SESSION['nivas_adminRole'] ?? 0), [1, 2, 3], true);

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
    .bella-thread { height: 52vh; overflow-y: auto; background: rgba(133, 146, 163, .08); }
    .bella-msg { max-width: 80%; white-space: pre-wrap; word-break: break-word; }
    .bella-msg .badge { text-transform: none; font-weight: 500; white-space: normal; text-align: left; }
    #bellaFilter .btn { white-space: nowrap; padding-left: .5rem; padding-right: .5rem; }
    .swap-item { display: flex; gap: .75rem; align-items: flex-start; padding: .75rem 1rem; border-bottom: 1px solid rgba(133, 146, 163, .2); cursor: pointer; margin: 0; }
    .swap-item:last-child { border-bottom: 0; }
    .swap-item.is-disabled { cursor: default; opacity: .6; }
    .swap-item:not(.is-disabled):hover { background: rgba(133, 146, 163, .08); }
    .swap-item input { margin-top: .3rem; }
    .swap-list { max-height: 300px; overflow-y: auto; }
    @media (max-width: 575.98px) { .bella-msg { max-width: 92%; } .bella-thread { height: 60vh; } }
    /* Staff view: the student on the left, Bella and the team on the right */
    /* Desktop: the page fits the window; the list and the thread scroll inside it */
    @media (min-width: 992px) {
      .bella-wrap { height: calc(100vh - 7.5rem); min-height: 480px; }
      .bella-wrap > [class*=col-] { height: 100%; }
      .bella-wrap .card { height: 100%; display: flex; flex-direction: column; }
      .bella-list { max-height: none; flex: 1 1 auto; min-height: 0; }
      #bellaConvBody:not(.d-none) { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
      .bella-thread { height: auto; flex: 1 1 auto; min-height: 0; }
    }
    .conv-head { padding: .75rem 1rem; border-bottom: 1px solid rgba(133, 146, 163, .2); }
    .conv-head h5 { font-size: 1rem; }
    .conv-head .conv-sub { font-size: .75rem; color: #8592a3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .conv-head .btn { padding: .25rem .6rem; font-size: .8rem; }
    .conv-note { font-size: .8rem; padding: .35rem 1rem; }
    .bella-msg.student { background: rgba(133, 146, 163, .14); border: 1px solid rgba(133, 146, 163, .3); color: inherit; }
    .bella-msg.bella { background: #696cff; color: #fff; }
    .bella-msg.agent { background: rgba(113, 221, 55, .16); border: 1px solid #71dd37; color: inherit; }
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
            <div id="bellaAlert" class="alert d-none" role="alert"></div>

            <div class="row g-3 bella-wrap">
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
                    <div class="conv-head d-flex align-items-center gap-2">
                      <button type="button" class="btn btn-sm btn-icon btn-text-secondary d-lg-none" id="convBack" aria-label="Back to list"><i class="bx bx-arrow-back"></i></button>
                      <div class="flex-grow-1" style="min-width:0">
                        <div class="d-flex align-items-center gap-2">
                          <h5 class="mb-0 text-truncate" id="convName"></h5>
                          <span class="badge" id="convStatus"></span>
                        </div>
                        <div class="conv-sub" id="convSub"></div>
                      </div>
                      <div class="d-flex gap-1 flex-shrink-0">
                        <?php if ($can_change_material) { ?>
                          <button class="btn btn-sm btn-outline-warning" id="swapOpen" title="Change material"><i class="bx bx-transfer"></i><span class="d-none d-xl-inline ms-1">Change material</span></button>
                        <?php } ?>
                        <button class="btn btn-sm btn-outline-primary conv-status" data-status="bella" title="Hand back to Bella"><i class="bx bx-bot"></i><span class="d-none d-md-inline ms-1">Hand back</span></button>
                        <button class="btn btn-sm btn-success conv-status" data-status="resolved" title="Resolve"><i class="bx bx-check"></i><span class="d-none d-md-inline ms-1">Resolve</span></button>
                      </div>
                    </div>
                    <div class="alert alert-warning conv-note rounded-0 mb-0 d-none text-truncate" id="convReason"></div>
                    <details class="small px-3 py-1 mb-0 d-none" id="convSummaryWrap"><summary class="text-muted">Earlier conversations (summary)</summary><div id="convSummary" class="mt-1"></div></details>
                    <div class="bella-thread p-3" id="convThread"></div>
                    <form class="card-body border-top py-2 px-3" id="convReply">
                      <textarea class="form-control mb-2" id="convText" rows="2" placeholder="Reply to the student. Bella stays quiet until you hand the chat back."></textarea>
                      <div class="mb-2 d-none" id="convFileChip">
                        <span class="badge bg-label-secondary fw-normal text-wrap py-2 px-3"><i class="bx bx-paperclip"></i> <span id="convFileName"></span>
                          <a href="javascript:void(0)" class="ms-2" id="convFileClear" aria-label="Remove attachment"><i class="bx bx-x"></i></a></span>
                      </div>
                      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="d-flex gap-2">
                          <label class="btn btn-outline-secondary btn-sm mb-0" title="Attach a photo or PDF (max 5 MB)">
                            <i class="bx bx-paperclip"></i> Attach
                            <input type="file" id="convFile" accept="image/jpeg,image/png,image/webp,application/pdf" class="d-none" />
                          </label>
                          <button class="btn btn-outline-secondary btn-sm" type="button" id="convSuggest"><i class="bx bx-bulb"></i> Suggest reply</button>
                        </div>
                        <button class="btn btn-primary" type="submit" id="convSend">Send reply</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Change material (admin override of the 72-hour / once-per-purchase limits) -->
          <div class="modal fade" id="swapModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
              <form class="modal-content" id="swapForm">
                <div class="modal-header">
                  <h5 class="modal-title">Change material</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div id="swapAlert" class="alert d-none" role="alert"></div>
                  <p class="small text-muted mb-3">
                    Within 72 hours of purchase, students swap materials themselves (or ask Bella). After that, you can change a purchase here,
                    once. A purchase that was already changed, a lost copy or a collected copy can't be changed, and the new material must cost the same.
                  </p>
                  <div class="alert alert-info py-2 small d-none" id="swapRequest"></div>
                  <label class="form-label">Purchase</label>
                  <div class="border rounded mb-3 swap-list" id="swapPurchases"><div class="p-3 text-muted small">Loading…</div></div>
                  <div class="mb-3">
                    <label class="form-label" for="swapTarget">Change to</label>
                    <select class="form-select" id="swapTarget" disabled><option value="">Choose a purchase first</option></select>
                  </div>
                  <div class="mb-0">
                    <label class="form-label" for="swapReason">Reason</label>
                    <input class="form-control" id="swapReason" maxlength="250" required placeholder="e.g. Bought the wrong course; asked within the week" />
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-warning" id="swapSubmit">Change material</button>
                </div>
              </form>
            </div>
          </div>

          <?php /* No footer: the chat fills the window */ ?>
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
      var filter = '', page = 1, total = 0, current = 0, searchTimer = null, currentConv = null, currentSwap = null;

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

      // Bella's numbers live on Bella Analytics; here only the Waiting count, on its filter button
      // (it comes with the list, so polling is one call)
      function showWaiting(n) {
        $('#bellaFilter [data-status=waiting]').html('Waiting' + (n ? ' <span class="badge bg-danger rounded-pill ms-1">' + n + '</span>' : ''));
      }

      function loadList() {
        call({ action: 'list', status: filter, q: $('#bellaSearch').val(), page: page }).done(function (r) {
          if (r.error) { $('#bellaList').html('<div class="p-3 text-muted">' + esc(r.error) + '</div>'); return; }
          total = r.total || 0;
          showWaiting(r.waiting || 0);
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
        if (c.type === 'pay_wallet') return 'Pay now with PIN (N' + Number(c.total).toLocaleString() + ')';
        if (c.type === 'wallet_account') return 'Wallet details card';
        if (c.type === 'end_chat') return c.status === 'ended' ? 'End chat (ended)' : 'End chat button';
        if (c.type === 'rate_chat') return c.rating ? 'Rated ' + c.rating + '/5' : 'Rate this chat';
        if (c.type === 'confirm_change') return 'Confirm swap' + (c.status ? ' (' + c.status + ')' : '');
        if (c.label && c.path) return c.label + ' → ' + c.path;
        return c.label || c.type;
      }

      function loadConv(id, keepScroll) {
        current = id;
        call({ action: 'get', id: id }).done(function (r) {
          if (r.error) { showAlert(r.error); return; }
          var c = r.conversation, st = STATUS[c.status] || [c.status, 'secondary'];
          currentConv = c;
          currentSwap = r.escalation && r.escalation.swap_request ? r.escalation.swap_request : null;
          $('#bellaEmpty').addClass('d-none');
          $('#bellaConvBody').removeClass('d-none');
          $('#convName').text(c.user_name || ('User ' + c.user_id));
          $('#convSub').text((c.user_email || '') + ' · #' + c.user_id + ' · since ' + (c.created_at ? new Date(c.created_at.replace(' ', 'T') + 'Z').toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '')).attr('title', (c.user_email || '') + ' · user #' + c.user_id + ' · since ' + when(c.created_at));
          $('#convStatus').attr('class', 'badge align-self-center bg-label-' + st[1]).text(st[0]);
          $('#convReason').toggleClass('d-none', !(c.escalation_reason && (c.status === 'waiting' || c.status === 'human'))).text('Bella handed over: ' + (c.escalation_reason || '')).attr('title', c.escalation_reason || '');
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
            if (m.role === 'bella' && m.latency_ms) meta += ' · ' + (m.latency_ms / 1000).toFixed(1) + 's';
            var file = '';
            if (m.attachment) {
              file = m.attachment.type.indexOf('audio/') === 0
                ? '<audio controls preload="none" src="' + esc(m.attachment.url) + '" class="d-block mt-1" style="max-width:240px;height:40px"></audio>'
                : m.attachment.type.indexOf('image/') === 0
                ? '<a href="' + esc(m.attachment.url) + '" target="_blank" rel="noopener"><img src="' + esc(m.attachment.url) + '" alt="" class="d-block rounded mt-1" style="max-width:220px;max-height:220px"></a>'
                : '<a href="' + esc(m.attachment.url) + '" target="_blank" rel="noopener" class="d-block mt-1 fw-semibold' + (m.role === 'bella' ? ' text-white' : '') + '"><i class="bx bx-file"></i> ' + esc(m.attachment.name) + '</a>';
            }
            return '<div class="mb-3 d-flex flex-column' + (m.role === 'student' ? '' : ' align-items-end') + '">'
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
      $('#bellaList').on('click', '.list-group-item', function () {
        loadConv($(this).data('id'));
        // Phones: the chat sits under the list, so jump to it
        if (window.innerWidth < 992) document.getElementById('bellaConv').scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
      $('#convBack').on('click', function () { document.getElementById('bellaList').scrollIntoView({ behavior: 'smooth', block: 'start' }); });

      // Optional photo or PDF with the reply
      function clearReplyFile() { $('#convFile').val(''); $('#convFileChip').addClass('d-none'); }
      $('#convFile').on('change', function () {
        var f = this.files && this.files[0];
        if (!f) { clearReplyFile(); return; }
        if (f.size > 5 * 1024 * 1024) { showAlert('That file is too big. The limit is 5 MB.'); clearReplyFile(); return; }
        $('#convFileName').text(f.name);
        $('#convFileChip').removeClass('d-none');
      });
      $('#convFileClear').on('click', clearReplyFile);

      $('#convReply').on('submit', function (e) {
        e.preventDefault();
        var text = $.trim($('#convText').val());
        var file = $('#convFile')[0].files[0];
        if ((!text && !file) || !current) return;
        var fd = new FormData();
        fd.append('action', 'reply');
        fd.append('id', current);
        fd.append('text', text);
        if (file) fd.append('file', file);
        var $b = $('#convSend').prop('disabled', true).text(file ? 'Sending file…' : 'Sending…');
        $.ajax({ url: 'model/bella.php', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
          .done(function (r) {
            if (r.error) { showAlert(r.error); return; }
            $('#convText').val('');
            clearReplyFile();
            loadConv(current); loadList();
          })
          .fail(function (xhr) { showAlert((xhr.responseJSON && xhr.responseJSON.error) || 'Could not send the reply'); })
          .always(function () { $b.prop('disabled', false).text('Send reply'); });
      });

      $('#convSuggest').on('click', function () {
        if (!current) return;
        var $b = $(this).prop('disabled', true).text('Drafting…');
        call({ action: 'suggest', id: current }).done(function (r) {
          if (r.error) { showAlert(r.error); return; }
          $('#convText').val(r.text || '').focus();
        }).always(function () { $b.prop('disabled', false).html('<i class="bx bx-bulb"></i> Suggest reply'); });
      });

      $('.conv-status').on('click', function () {
        if (!current) return;
        call({ action: 'status', id: current, status: $(this).data('status') }).done(function (r) {
          if (r.error) { showAlert(r.error); return; }
          showAlert(r.status === 'bella' ? 'Bella is back in the chat.' : 'Conversation resolved. If it teaches something general, Bella will suggest a help article in Bella Knowledge.', 'success');
          loadConv(current); loadList();
        });
      });

      // ── Change material (override) ──
      var swapSel = null;
      function shortDate(v) {
        var d = new Date(String(v).replace(' ', 'T'));
        return isNaN(d) ? v : d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
      }
      function swapAlert(msg, type) {
        $('#swapAlert').removeClass('d-none alert-danger alert-success').addClass('alert-' + (type || 'danger')).text(msg);
      }
      function swapCall(data) {
        return $.post('model/material_change_admin.php', $.extend({ user_id: currentConv.user_id }, data), null, 'json').fail(function (xhr) {
          swapAlert((xhr.responseJSON && xhr.responseJSON.error) || 'Request failed');
        });
      }
      function loadSwapTargets(manualId, refId, preferId) {
        swapSel = { manual_id: manualId, ref_id: refId };
        $('#swapTarget').prop('disabled', true).html('<option>Loading…</option>');
        swapCall({ action: 'candidates', manual_id: manualId, ref_id: refId }).done(function (r) {
          var opts = (r.candidates || []).map(function (m) {
            return '<option value="' + m.id + '"' + (Number(preferId) === m.id ? ' selected' : '') + '>' + esc(m.course_code + ' · ' + m.title + ' (' + m.dept_name + ')') + '</option>';
          });
          $('#swapTarget').prop('disabled', !opts.length).html(opts.length ? '<option value="">Choose a material</option>' + opts.join('') : '<option value="">No material at the same price is available</option>');
          if (preferId) $('#swapTarget').val(String(preferId));
        });
      }
      function loadSwapPurchases() {
        $('#swapPurchases').html('<div class="p-3 text-muted small">Loading…</div>');
        swapCall({ action: 'purchases' }).done(function (r) {
          var rows = (r.purchases || []).map(function (p, i) {
            var status = p.admin_can_change
              ? '<span class="badge bg-label-success">Can change</span>'
              : p.student_can_change
                ? '<span class="badge bg-label-info">Student can still swap it</span>'
                : /already been changed/i.test(p.note)
                  ? '<span class="badge bg-label-secondary">Already changed</span>'
                  : '<span class="badge bg-label-secondary">Can\'t change</span>';
            var why = p.admin_can_change ? '' : '<div class="small text-muted mt-1">' + esc(p.note) + '</div>';
            var pick = currentSwap && currentSwap.ref_id === p.ref_id && Number(currentSwap.material_id) === p.manual_id;
            return '<label class="swap-item' + (p.admin_can_change ? '' : ' is-disabled') + '">'
              + '<input class="form-check-input" type="radio" name="swapPick" value="' + i + '"' + (p.admin_can_change ? '' : ' disabled') + (pick && p.admin_can_change ? ' checked' : '') + '>'
              + '<span class="flex-grow-1" style="min-width:0"><span class="d-flex flex-wrap justify-content-between gap-2">'
              + '<strong class="text-truncate">' + esc(p.course_code + ' · ' + p.title) + '</strong>' + status + '</span>'
              + '<span class="d-block small text-muted">Bought ' + esc(shortDate(p.bought_at)) + ' · N' + Number(p.price).toLocaleString() + ' · ' + esc(p.ref_id) + '</span>'
              + why + '</span></label>';
          });
          $('#swapPurchases').html(rows.length ? rows.join('') : '<div class="p-3 text-muted small">No purchases.</div>').data('rows', r.purchases || []);
          var checked = $('input[name=swapPick]:checked');
          if (checked.length) {
            var p = r.purchases[Number(checked.val())];
            loadSwapTargets(p.manual_id, p.ref_id, currentSwap && currentSwap.new_material_id);
          }
        });
      }
      $('#swapOpen').on('click', function () {
        if (!currentConv) return;
        swapSel = null;
        $('#swapAlert').addClass('d-none');
        $('#swapReason').val('');
        $('#swapTarget').prop('disabled', true).html('<option value="">Choose a purchase first</option>');
        $('#swapRequest').toggleClass('d-none', !currentSwap).text(currentSwap ? 'Bella passed on a swap request for purchase ' + currentSwap.ref_id + '. It is pre-selected below.' : '');
        $('#swapModal').modal('show');
        loadSwapPurchases();
      });
      $(document).on('change', 'input[name=swapPick]', function () {
        var p = $('#swapPurchases').data('rows')[Number(this.value)];
        loadSwapTargets(p.manual_id, p.ref_id);
      });
      $('#swapForm').on('submit', function (e) {
        e.preventDefault();
        if (!swapSel || !$('#swapTarget').val()) { swapAlert('Choose a purchase and the material to change to.'); return; }
        if (!confirm('Change this purchase to ' + $('#swapTarget option:selected').text() + '?')) return;
        var $b = $('#swapSubmit').prop('disabled', true);
        swapCall({ action: 'execute', manual_id: swapSel.manual_id, ref_id: swapSel.ref_id, new_manual_id: $('#swapTarget').val(), reason: $('#swapReason').val(), conversation_id: current })
          .done(function (r) {
            if (r.error) { swapAlert(r.error); return; }
            $('#swapModal').modal('hide');
            showAlert('Changed to ' + (r.new_material || 'the new material') + '. Let the student know in the chat.', 'success');
            $('#convText').val('Hi, we have changed your purchase to ' + (r.new_material || 'the material you asked for') + '. It now shows under your orders with the same receipt.').focus();
          })
          .always(function () { $b.prop('disabled', false); });
      });

      loadList();
      var linked = Number(new URLSearchParams(location.search).get('id') || 0);
      if (linked) loadConv(linked);
      // Every 30 s while the tab is visible: the list (with the waiting count) and the open chat
      setInterval(function () {
        if (document.hidden) return;
        loadList();
        if (current) loadConv(current, true);
      }, 30000);
    });
  </script>
</body>
</html>

<?php
session_start();
include('model/config.php');
include('model/page_config.php');

// Bella analytics: how much Bella handles, how fast, what students think, what it costs.
// Data comes from the Bella Worker (/admin/analytics) through model/bella.php.
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
  <title>Bella Analytics | Nivasity Command Center</title>
  <meta name="description" content="" />
  <?php include('partials/_head.php') ?>
  <style>
    .stat-card small.label { color: #8592a3; }
    .stat-card h3 { margin: .25rem 0 0; }
    .bar-row { display: flex; align-items: center; gap: .5rem; margin-bottom: .45rem; font-size: .875rem; }
    .bar-row .name { width: 42%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bar-row .bar { flex: 1; height: 8px; border-radius: 4px; background: rgba(133, 146, 163, .16); overflow: hidden; }
    .bar-row .bar span { display: block; height: 100%; background: #696cff; border-radius: 4px; }
    .bar-row .n { width: 3rem; text-align: right; font-variant-numeric: tabular-nums; }
    .stars { color: #ffab00; letter-spacing: 1px; }
    .empty { color: #8592a3; font-size: .875rem; }
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
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-3 mb-2">
              <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Bella /</span> Analytics</h4>
              <div class="btn-group btn-group-sm" role="group" id="range">
                <button type="button" class="btn btn-outline-primary" data-days="7">7 days</button>
                <button type="button" class="btn btn-outline-primary active" data-days="30">30 days</button>
                <button type="button" class="btn btn-outline-primary" data-days="90">90 days</button>
              </div>
            </div>
            <div id="alertBox" class="alert alert-danger d-none" role="alert"></div>

            <div class="row g-3 mb-3">
              <div class="col-6 col-xl-3"><div class="card h-100 stat-card"><div class="card-body"><small class="label">Chats</small><h3 data-k="conversations">–</h3><small class="text-muted" data-k="conversations_sub"></small></div></div></div>
              <div class="col-6 col-xl-3"><div class="card h-100 stat-card"><div class="card-body"><small class="label">Handled by Bella</small><h3 data-k="handled">–</h3><small class="text-muted" data-k="handled_sub"></small></div></div></div>
              <div class="col-6 col-xl-3"><div class="card h-100 stat-card"><div class="card-body"><small class="label">Student rating</small><h3 data-k="rating">–</h3><small class="text-muted" data-k="rating_sub"></small></div></div></div>
              <div class="col-6 col-xl-3"><div class="card h-100 stat-card"><div class="card-body"><small class="label">Typical reply time</small><h3 data-k="speed">–</h3><small class="text-muted" data-k="speed_sub"></small></div></div></div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-lg-8">
                <div class="card h-100">
                  <div class="card-header pb-0"><h5 class="mb-0">Chats and handovers per day</h5></div>
                  <div class="card-body"><div id="chartChats"></div></div>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="card h-100">
                  <div class="card-header pb-0"><h5 class="mb-0">AI usage</h5></div>
                  <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Model</span><strong data-k="provider">–</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Tokens in</span><strong data-k="tokens_in">–</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Tokens out</span><strong data-k="tokens_out">–</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Per Bella reply</span><strong data-k="tokens_per_reply">–</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Replies with no tool</span><strong data-k="no_tools">–</strong></div>
                    <hr />
                    <div class="d-flex justify-content-between align-items-baseline"><span>Estimated cost</span><h4 class="mb-0" data-k="cost">–</h4></div>
                    <small class="text-muted">At Gemini 2.5 Flash list prices. Check the Google bill for the real figure.</small>
                  </div>
                </div>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-lg-8">
                <div class="card h-100">
                  <div class="card-header pb-0"><h5 class="mb-0">Bella reply time (seconds)</h5></div>
                  <div class="card-body"><div id="chartSpeed"></div></div>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="card h-100">
                  <div class="card-header pb-0"><h5 class="mb-0">Tools Bella used</h5></div>
                  <div class="card-body" id="tools"></div>
                </div>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-lg-4">
                <div class="card h-100">
                  <div class="card-header pb-0"><h5 class="mb-0">Ratings</h5></div>
                  <div class="card-body">
                    <div id="ratingBars"></div>
                    <h6 class="mt-3 mb-2">Latest feedback</h6>
                    <div id="comments"></div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="card h-100">
                  <div class="card-header pb-0"><h5 class="mb-0">Handovers to the team</h5></div>
                  <div class="card-body">
                    <div class="row text-center mb-3">
                      <div class="col"><h4 class="mb-0 text-danger" data-k="esc_open">–</h4><small class="text-muted">Open</small></div>
                      <div class="col"><h4 class="mb-0 text-warning" data-k="esc_answered">–</h4><small class="text-muted">Answered</small></div>
                      <div class="col"><h4 class="mb-0 text-success" data-k="esc_resolved">–</h4><small class="text-muted">Resolved</small></div>
                    </div>
                    <div class="d-flex justify-content-between mb-3"><span>Average first team reply</span><strong data-k="esc_answer">–</strong></div>
                    <h6 class="mb-2">Latest reasons</h6>
                    <div id="reasons"></div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="card mb-3">
                  <div class="card-header pb-0"><h5 class="mb-0">Voice notes</h5></div>
                  <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Voice notes</span><strong data-k="voice_notes">–</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Minutes of audio</span><strong data-k="voice_minutes">–</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Average transcription</span><strong data-k="voice_ms">–</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Deepgram / Gemini</span><strong data-k="voice_split">–</strong></div>
                    <div class="d-flex justify-content-between"><span>Failed</span><strong data-k="voice_failed">–</strong></div>
                  </div>
                </div>
                <div class="card">
                  <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Most used help articles</h5>
                    <a href="bella_knowledge.php" class="small" data-k="kb_suggested"></a>
                  </div>
                  <div class="card-body" id="kbTop"></div>
                </div>
              </div>
            </div>
            <p class="text-muted small mb-0">Days are in UTC. Chat numbers are saved daily, so they stay after chat messages are deleted.</p>
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
  <script src="assets/vendor/libs/apex-charts/apexcharts.min.js"></script>
  <script src="assets/js/main.js"></script>
  <script>
    $(function () {
      var days = 30, charts = {};
      var TOOL_NAMES = {
        search_materials: 'Find materials', search_knowledge: 'Help articles', add_to_cart: 'Add to cart', remove_from_cart: 'Remove from cart',
        view_cart: 'View cart', show_wallet_details: 'Wallet details', wallet_summary: 'Wallet balance', recent_wallet_transactions: 'Wallet transactions',
        purchased_materials: 'Purchases', show_link: 'Page buttons', check_material_change: 'Check swap', propose_material_change: 'Propose swap',
        end_chat: 'End chat', escalate_to_human: 'Hand to team'
      };

      function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
      function num(n) { return Number(n || 0).toLocaleString(); }
      function secs(ms) { return ms == null ? '–' : (ms / 1000).toFixed(1) + 's'; }
      function day(d) { return new Date(d + 'T00:00:00Z').toLocaleDateString(undefined, { day: 'numeric', month: 'short', timeZone: 'UTC' }); }
      function set(k, v) { $('[data-k="' + k + '"]').text(v); }
      function dark() { return document.documentElement.classList.contains('dark-style'); }
      function bars(rows, max) {
        if (!rows.length) return '<div class="empty">Nothing yet.</div>';
        return rows.map(function (r) {
          return '<div class="bar-row"><span class="name" title="' + esc(r.name) + '">' + esc(r.name) + '</span><span class="bar"><span style="width:' + (max ? Math.round(r.n / max * 100) : 0) + '%"></span></span><span class="n">' + num(r.n) + '</span></div>';
        }).join('');
      }

      // Days with no chats are shown as 0 so the line doesn't skip them
      function fill(series) {
        var map = {}, out = [];
        series.forEach(function (r) { map[r.day] = r; });
        for (var i = days - 1; i >= 0; i--) {
          var d = new Date(Date.now() - i * 864e5).toISOString().slice(0, 10);
          out.push(map[d] || { day: d, conversations: 0, escalations: 0, reply_median_ms: null, reply_p90_ms: null });
        }
        return out;
      }

      function chart(id, options) {
        if (charts[id]) charts[id].destroy();
        var base = { chart: { height: 280, toolbar: { show: false }, foreColor: dark() ? '#a3a4cc' : '#697a8d', fontFamily: 'inherit' }, grid: { borderColor: dark() ? '#444564' : '#eceef1' }, dataLabels: { enabled: false }, legend: { position: 'top', horizontalAlign: 'left' }, tooltip: { theme: dark() ? 'dark' : 'light' } };
        charts[id] = new ApexCharts(document.getElementById(id), $.extend(true, base, options));
        charts[id].render();
      }

      function render(r) {
        var t = r.totals || {}, rt = r.ratings || {}, e = r.escalations || {}, v = r.voice || {};
        set('conversations', num(t.conversations));
        set('conversations_sub', num(t.student_messages) + ' student messages, ' + num(t.sessions) + ' sessions');
        set('handled', t.handled_by_bella == null ? '–' : t.handled_by_bella + '%');
        set('handled_sub', num(t.escalations) + ' handed to the team');
        set('rating', rt.average == null ? '–' : rt.average + ' / 5');
        set('rating_sub', rt.count ? 'from ' + num(rt.count) + ' rated chat' + (rt.count == 1 ? '' : 's') : 'No ratings yet');
        set('speed', secs(t.reply_median_ms));
        set('speed_sub', 'Bella replies, average of daily medians');
        set('provider', r.provider);
        set('tokens_in', num(t.tokens_in));
        set('tokens_out', num(t.tokens_out));
        set('tokens_per_reply', t.tokens_per_reply == null ? '–' : num(t.tokens_per_reply));
        set('no_tools', num(t.replies_without_tools) + ' of ' + num(t.bella_messages));
        set('cost', '$' + Number(t.cost_usd || 0).toFixed(2));

        var s = fill(r.series || []);
        var labels = s.map(function (x) { return day(x.day); });
        chart('chartChats', {
          chart: { type: 'area' }, colors: ['#696cff', '#ff3e1d'], stroke: { curve: 'smooth', width: 2 },
          fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .05 } },
          series: [{ name: 'Chats', data: s.map(function (x) { return x.conversations; }) }, { name: 'Handovers', data: s.map(function (x) { return x.escalations; }) }],
          xaxis: { categories: labels, tickAmount: Math.min(10, labels.length) }, yaxis: { min: 0, forceNiceScale: true, labels: { formatter: function (v) { return Math.round(v); } } }
        });
        chart('chartSpeed', {
          chart: { type: 'line' }, colors: ['#71dd37', '#ffab00'], stroke: { curve: 'smooth', width: 2 },
          series: [
            { name: 'Typical', data: s.map(function (x) { return x.reply_median_ms == null ? null : +(x.reply_median_ms / 1000).toFixed(1); }) },
            { name: 'Slowest 10%', data: s.map(function (x) { return x.reply_p90_ms == null ? null : +(x.reply_p90_ms / 1000).toFixed(1); }) }
          ],
          xaxis: { categories: labels, tickAmount: Math.min(10, labels.length) }, yaxis: { min: 0, labels: { formatter: function (v) { return v == null ? '' : v + 's'; } } }
        });

        var tools = (r.tools || []).map(function (x) { return { name: TOOL_NAMES[x.name] || x.name, n: x.count }; });
        $('#tools').html(bars(tools, tools.length ? tools[0].n : 0));

        var byStar = rt.by_star || [0, 0, 0, 0, 0], maxStar = Math.max.apply(null, byStar);
        $('#ratingBars').html(rt.count ? bars([5, 4, 3, 2, 1].map(function (st) { return { name: st + ' star' + (st == 1 ? '' : 's'), n: byStar[st - 1] }; }), maxStar) : '<div class="empty">No ratings yet.</div>');
        $('#comments').html((rt.comments || []).length ? rt.comments.map(function (c) {
          return '<div class="mb-2"><span class="stars">' + '★'.repeat(c.rating) + '</span> <small class="text-muted">' + esc(new Date(c.created_at.replace(' ', 'T') + 'Z').toLocaleDateString()) + '</small><div class="small">' + esc(c.comment) + '</div></div>';
        }).join('') : '<div class="empty">No written feedback yet.</div>');

        set('esc_open', num(e.open));
        set('esc_answered', num(e.answered));
        set('esc_resolved', num(e.resolved));
        var mins = e.avg_answer_minutes;
        set('esc_answer', mins == null ? '–' : mins < 60 ? Math.round(mins) + ' min' : (mins / 60).toFixed(1) + ' h');
        $('#reasons').html((e.recent || []).length ? e.recent.map(function (x) {
          return '<div class="small mb-2 text-truncate" title="' + esc(x.reason) + '"><span class="badge bg-label-' + ({ open: 'danger', answered: 'warning', resolved: 'success' }[x.status] || 'secondary') + ' me-1">' + esc(x.status) + '</span>' + esc(x.reason || 'No reason') + '</div>';
        }).join('') : '<div class="empty">No handovers in this period.</div>');

        set('voice_notes', num(v.notes));
        set('voice_minutes', (Number(v.seconds || 0) / 60).toFixed(1));
        set('voice_ms', v.avg_ms == null ? '–' : secs(v.avg_ms));
        set('voice_split', num(v.deepgram) + ' / ' + num(v.gemini));
        set('voice_failed', num(v.failed));

        var kb = (r.kb && r.kb.top) || [];
        $('#kbTop').html(bars(kb.map(function (a) { return { name: a.title, n: a.uses }; }), kb.length ? kb[0].uses : 0));
        set('kb_suggested', r.kb && r.kb.suggested ? r.kb.suggested + ' suggested to review' : '');
      }

      function load() {
        $.post('model/bella.php', { action: 'analytics', days: days }, null, 'json')
          .done(function (r) {
            if (r.error) { $('#alertBox').removeClass('d-none').text(r.error); return; }
            $('#alertBox').addClass('d-none');
            render(r);
          })
          .fail(function (xhr) { $('#alertBox').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.error) || 'Could not load Bella analytics'); });
      }

      $('#range').on('click', 'button', function () {
        $('#range button').removeClass('active');
        $(this).addClass('active');
        days = Number($(this).data('days'));
        load();
      });
      load();
    });
  </script>
</body>
</html>

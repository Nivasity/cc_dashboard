<?php
session_start();
include('model/config.php');
include('model/page_config.php');

// Bella Knowledge: help articles Bella answers from (policies, fees, refunds, collection, how-tos).
// Stored in the Bella Worker (D1) and managed through model/bella.php.
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
  <title>Bella Knowledge | Nivasity Command Center</title>
  <meta name="description" content="" />
  <?php include('partials/_head.php') ?>
</head>
<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php include('partials/_sidebar.php') ?>
      <div class="layout-page">
        <?php include('partials/_navbar.php') ?>
        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">
            <div class="d-flex flex-wrap justify-content-between align-items-center py-3 mb-3 gap-2">
              <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Support /</span> Bella Knowledge</h4>
              <button class="btn btn-primary" id="kbNew"><i class="bx bx-plus"></i> New article</button>
            </div>
            <div id="kbAlert" class="alert d-none" role="alert"></div>
            <p class="text-muted small">
              Bella searches these articles when students ask how Nivasity works and answers only from them.
              Write each one as the answer you want students to get. Keep it short and specific, and add keywords students might use.
              Turned-off articles are kept but not used.
            </p>
            <ul class="nav nav-pills mb-3" id="kbTabs">
              <li class="nav-item"><button class="nav-link active" data-tab="published">Articles</button></li>
              <li class="nav-item"><button class="nav-link" data-tab="suggested">Suggested <span class="badge bg-danger rounded-pill ms-1 d-none" id="kbSuggestedCount"></span></button></li>
            </ul>
            <p class="small text-muted d-none" id="kbSuggestedHelp">
              When a chat the team handled is resolved, Bella drafts an article if it teaches something other students need.
              Nothing is used until you approve it. Check the source chat, edit if needed, then approve.
            </p>
            <div class="card">
              <div class="card-header pb-0">
                <input type="search" class="form-control" id="kbSearch" placeholder="Filter articles" />
              </div>
              <div class="table-responsive">
                <table class="table mb-0">
                  <thead><tr><th>Title</th><th>Keywords</th><th>Status</th><th>Used by Bella</th><th class="text-end">Actions</th></tr></thead>
                  <tbody id="kbBody"><tr><td colspan="5" class="text-muted py-3">Loading…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="modal fade" id="kbModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
              <form class="modal-content" id="kbForm">
                <div class="modal-header">
                  <h5 class="modal-title" id="kbModalTitle">New article</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" id="kbId" />
                  <div class="mb-3">
                    <label class="form-label" for="kbTitle">Title (the question)</label>
                    <input class="form-control" id="kbTitle" maxlength="200" required placeholder="e.g. Can I get a refund for a material I paid for?" />
                  </div>
                  <div class="mb-3">
                    <label class="form-label" for="kbText">Answer</label>
                    <textarea class="form-control" id="kbText" rows="8" maxlength="8000" required></textarea>
                  </div>
                  <div class="mb-3">
                    <label class="form-label" for="kbKeywords">Keywords</label>
                    <input class="form-control" id="kbKeywords" maxlength="500" placeholder="refund, money back, wrong material" />
                  </div>
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="kbActive" checked />
                    <label class="form-check-label" for="kbActive">Bella can use this article</label>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary" id="kbSave">Save</button>
                </div>
              </form>
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
      var articles = [], tab = 'published', approving = 0;
      function daysSince(s) { return s ? (Date.now() - new Date(s.replace(' ', 'T') + 'Z').getTime()) / 86400000 : Infinity; }
      function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
      function alertMsg(msg, type) {
        $('#kbAlert').removeClass('d-none alert-danger alert-success').addClass('alert-' + (type || 'danger')).text(msg);
        setTimeout(function () { $('#kbAlert').addClass('d-none'); }, 5000);
      }
      function call(data) {
        return $.post('model/bella.php', data, null, 'json').fail(function (xhr) {
          alertMsg((xhr.responseJSON && xhr.responseJSON.error) || 'Request failed');
        });
      }
      function render() {
        var q = $.trim($('#kbSearch').val()).toLowerCase();
        var suggested = articles.filter(function (a) { return a.state === 'suggested'; }).length;
        $('#kbSuggestedCount').toggleClass('d-none', !suggested).text(suggested);
        $('#kbSuggestedHelp').toggleClass('d-none', tab !== 'suggested');
        var rows = articles.filter(function (a) {
          return a.state === tab && (!q || (a.title + ' ' + (a.keywords || '') + ' ' + a.body).toLowerCase().indexOf(q) !== -1);
        }).map(function (a) {
          var status, used, actions;
          if (a.state === 'suggested') {
            status = a.suggested_edit_of
              ? '<span class="badge bg-label-warning">Update to: ' + esc(a.edit_of_title || ('#' + a.suggested_edit_of)) + '</span>'
              : '<span class="badge bg-label-info">New article</span>';
            used = a.source_conversation_id ? '<a href="bella_chats.php?id=' + a.source_conversation_id + '" target="_blank" class="small">Source chat</a>' : '';
            actions = '<button class="btn btn-sm btn-success kb-approve" data-id="' + a.id + '">Approve</button> '
              + '<button class="btn btn-sm btn-outline-primary kb-edit" data-id="' + a.id + '">Edit &amp; approve</button> '
              + '<button class="btn btn-sm btn-outline-secondary kb-dismiss" data-id="' + a.id + '">Dismiss</button>';
          } else {
            var stale = Number(a.active) && daysSince(a.last_used_at || a.created_at) > 60;
            status = (Number(a.active) ? '<span class="badge bg-label-success">On</span>' : '<span class="badge bg-label-secondary">Off</span>')
              + (stale ? ' <span class="badge bg-label-warning" title="Not used by Bella for 60 days">Review</span>' : '');
            used = '<span class="small">' + Number(a.uses || 0).toLocaleString() + ' times' + (a.last_used_at ? '<br><span class="text-muted">last ' + esc(a.last_used_at.slice(0, 10)) + '</span>' : '') + '</span>';
            actions = '<button class="btn btn-sm btn-outline-primary kb-edit" data-id="' + a.id + '">Edit</button> '
              + '<button class="btn btn-sm btn-outline-danger kb-del" data-id="' + a.id + '">Delete</button>';
          }
          return '<tr><td><strong>' + esc(a.title) + '</strong><div class="small text-muted text-truncate" style="max-width:420px">' + esc(a.body) + '</div></td>'
            + '<td class="small">' + esc(a.keywords || '') + '</td><td>' + status + '</td><td>' + used + '</td>'
            + '<td class="text-end text-nowrap">' + actions + '</td></tr>';
        });
        $('#kbBody').html(rows.length ? rows.join('') : '<tr><td colspan="5" class="text-muted py-3">'
          + (tab === 'suggested' ? 'No suggestions right now.' : 'No articles yet. Add your refund policy, fees and how students collect materials first.') + '</td></tr>');
      }
      function load() {
        call({ action: 'kb_list' }).done(function (r) {
          if (r.error) { $('#kbBody').html('<tr><td colspan="5" class="text-danger py-3">' + esc(r.error) + '</td></tr>'); return; }
          articles = r.articles || [];
          render();
        });
      }
      function open(a) {
        approving = a && a.state === 'suggested' ? Number(a.id) : 0;
        $('#kbModalTitle').text(approving ? 'Edit & approve suggestion' : a ? 'Edit article' : 'New article');
        $('#kbSave').text(approving ? 'Approve' : 'Save');
        $('#kbActive').closest('.form-check').toggleClass('d-none', !!approving);
        $('#kbId').val(a ? a.id : '');
        $('#kbTitle').val(a ? a.title : '');
        $('#kbText').val(a ? a.body : '');
        $('#kbKeywords').val(a ? a.keywords || '' : '');
        $('#kbActive').prop('checked', a ? !!Number(a.active) : true);
        $('#kbModal').modal('show');
      }
      $('#kbNew').on('click', function () { open(null); });
      $('#kbTabs').on('click', 'button', function () {
        $('#kbTabs button').removeClass('active');
        tab = $(this).addClass('active').data('tab');
        render();
      });
      $(document).on('click', '.kb-approve', function () {
        call({ action: 'kb_approve', id: $(this).data('id') }).done(function (r) {
          if (r.error) { alertMsg(r.error); return; }
          alertMsg('Approved. Bella will use it now.', 'success'); load();
        });
      });
      $(document).on('click', '.kb-dismiss', function () {
        call({ action: 'kb_dismiss', id: $(this).data('id') }).done(function (r) {
          if (r.error) { alertMsg(r.error); return; }
          alertMsg('Suggestion dismissed.', 'success'); load();
        });
      });
      $('#kbSearch').on('input', render);
      $(document).on('click', '.kb-edit', function () {
        var id = Number($(this).data('id'));
        open(articles.find(function (a) { return Number(a.id) === id; }));
      });
      $(document).on('click', '.kb-del', function () {
        if (!confirm('Delete this article? Bella will stop using it.')) return;
        call({ action: 'kb_delete', id: $(this).data('id') }).done(function (r) {
          if (r.error) { alertMsg(r.error); return; }
          alertMsg('Article deleted.', 'success'); load();
        });
      });
      $('#kbForm').on('submit', function (e) {
        e.preventDefault();
        var $b = $('#kbSave').prop('disabled', true);
        var data = approving
          ? { action: 'kb_approve', id: approving, title: $('#kbTitle').val(), body: $('#kbText').val(), keywords: $('#kbKeywords').val() }
          : { action: 'kb_save', id: $('#kbId').val(), title: $('#kbTitle').val(), body: $('#kbText').val(),
              keywords: $('#kbKeywords').val(), active: $('#kbActive').is(':checked') ? '1' : '0' };
        call(data).done(function (r) {
          if (r.error) { alertMsg(r.error); return; }
          $('#kbModal').modal('hide'); alertMsg('Article saved.', 'success'); load();
        }).always(function () { $b.prop('disabled', false); });
      });
      load();
    });
  </script>
</body>
</html>

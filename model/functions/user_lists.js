$(document).ready(function () {
  const config = window.userListConfig || null;
  if (!config || !config.type || !config.tableSelector) {
    return;
  }

  const $table = $(config.tableSelector);
  if ($table.length === 0) {
    return;
  }

  const escapeHtml = (value) => $('<div>').text(value ?? '').html();
  const fallback = (value, placeholder = 'N/A') => value && value.length ? value : placeholder;

  const statusBadge = (user) => {
    const rawStatus = (user.raw_status || '').toString().toLowerCase();
    const badgeClassMap = {
      verified: 'success',
      inreview: 'warning',
      unverified: 'secondary',
      deactivated: 'danger',
      denied: 'danger',
      deactivate: 'danger',
    };
    const badgeClass = badgeClassMap[rawStatus] || (user.is_active ? 'success' : 'secondary');
    const label = user.status || (rawStatus ? rawStatus.charAt(0).toUpperCase() + rawStatus.slice(1) : 'N/A');
    return `<span class="badge bg-label-${badgeClass}">${escapeHtml(label)}</span>`;
  };

  const metaColumn = (user) => {
    if (config.type === 'hoc') {
      const meta = user.meta || {};
      const school = escapeHtml(fallback(meta.school || '-', 'N/A'));
      const dept = escapeHtml(fallback(meta.dept || '-', 'N/A'));
      return `<span class="fw-semibold d-block">${school}</span><small class="text-muted">${dept}</small>`;
    }
    const meta = user.meta || {};
    const businessLines = [];
    if (meta.business_name) {
      businessLines.push(`<span class="fw-semibold">${escapeHtml(meta.business_name)}</span>`);
    }
    if (meta.business_address) {
      businessLines.push(`<small class="text-muted d-block">${escapeHtml(meta.business_address)}</small>`);
    }
    if (meta.work_email) {
      businessLines.push(`<small class="text-muted d-block">${escapeHtml(meta.work_email)}</small>`);
    }
    if (meta.web_url) {
      businessLines.push(`<small class="text-muted d-block">${escapeHtml(meta.web_url)}</small>`);
    }
    return businessLines.join('') || '<span class="text-muted">N/A</span>';
  };

  const destroyDataTable = () => {
    if ($.fn.dataTable && $.fn.dataTable.isDataTable(config.tableSelector)) {
      const dt = $table.DataTable();
      dt.clear().draw().destroy();
    }
  };

  const renderTable = (users) => {
    const $tbody = $table.find('tbody');
    $tbody.empty();

    if (!users || !users.length) {
      $tbody.append(`<tr><td colspan="6" class="text-center text-muted">No records found.</td></tr>`);
      return;
    }

    users.forEach((user) => {
      const name = escapeHtml(user.name || '-');
      const email = escapeHtml(user.email || '-');
      const phone = escapeHtml(user.phone || '-');
      const status = statusBadge(user);
      const lastLogin = escapeHtml(fallback(user.last_login, 'N/A'));
      const row = `<tr>
        <td class="text-uppercase">${name}</td>
        <td>${email}</td>
        <td>${phone}</td>
        <td>${metaColumn(user)}</td>
        <td>${status}</td>
        <td>${lastLogin}</td>
      </tr>`;

      $tbody.append(row);
    });
  };

  const loadUsers = () => {
    $.ajax({
      url: 'model/user_list.php',
      method: 'GET',
      data: { type: config.type },
      dataType: 'json',
      success: function (res) {
        destroyDataTable();
        if (res.status === 'success') {
          renderTable(res.users || []);
        } else {
          renderTable([]);
          if (typeof showToast === 'function') {
            showToast('bg-danger', res.message || 'Failed to load users');
          }
        }
        InitiateDatatable(config.tableSelector);
      },
      error: function () {
        destroyDataTable();
        renderTable([]);
        if (typeof showToast === 'function') {
          showToast('bg-danger', 'Failed to load users');
        }
      },
    });
  };

  loadUsers();
});


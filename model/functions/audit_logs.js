$(document).ready(function () {
        const table = $('.audit-table').DataTable({
                columns: [
                        { data: 'created_at' },
                        { data: 'admin_name' },
                        { data: 'action' },
                        { data: 'entity_type' },
                        { data: 'entity_id' },
                        { data: 'details_formatted' },
                        { data: 'ip_address' }
                ],
                order: [[0, 'desc']],
                pageLength: 25,
                lengthMenu: [25, 50, 100],
                searching: true,
                autoWidth: false,
                columnDefs: [
                        {
                                targets: 5,
                                render: function (data) {
                                        if (!data) {
                                                return '';
                                        }
                                        return `<pre class="mb-0 text-wrap text-break">${$('<div>').text(data).html()}</pre>`;
                                }
                        }
                ]
        });

        const $form = $('#auditFilterForm');
        const $refreshBtn = $('#refreshAuditLogs');

        function fetchAuditLogs() {
                const params = $form.serializeArray().reduce((acc, item) => {
                        if (item.value) {
                                acc[item.name] = item.value;
                        }
                        return acc;
                }, {});
                const limitValue = parseInt($('#limit').val(), 10);
                params['get_data'] = 'audit_logs';
                params['limit'] = Number.isNaN(limitValue) ? 200 : limitValue;

                $refreshBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Loading...');

                $.ajax({
                        url: 'model/audit.php',
                        method: 'GET',
                        data: params,
                        success: function (response) {
                                if (response.status === 'success') {
                                        table.clear();
                                        table.rows.add(response.logs).draw();
                                } else {
                                        showToast('bg-danger', response.message || 'Unable to load audit logs.');
                                }
                        },
                        error: function () {
                                showToast('bg-danger', 'Unable to load audit logs.');
                        },
                        complete: function () {
                                $refreshBtn.prop('disabled', false).text('Search');
                        }
                });
        }

        $form.on('submit', function (e) {
                e.preventDefault();
                fetchAuditLogs();
        });

        $('#resetAuditFilters').on('click', function () {
                $form[0].reset();
                fetchAuditLogs();
        });

        fetchAuditLogs();
});

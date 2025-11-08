function InitiateDatatable(table) {
    // Initialize DataTable and preserve DOM order (latest from backend)
    $(table).DataTable({
        searching: true,
        ordering: true,
        paging: true,
        order: []
    });
}

function InitiateDatatable(table) {
    // Initialize DataTable with stateSave to preserve interactions
    $(table).DataTable({
        searching: true,
        ordering: true,
        paging: true
    });
}
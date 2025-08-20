$(document).ready(function () {
  InitiateDatatable('.table');
  $('#school, #faculty, #dept').select2({ theme: 'bootstrap-5', width: '100%' });

  $('#school').on('change', function () {
    $('#faculty').val('0');
    $('#dept').val('0');
    $('#filterForm').submit();
  });

  $('#faculty').on('change', function () {
    $('#dept').val('0');
    $('#filterForm').submit();
  });

  $('#dept').on('change', function () {
    $('#filterForm').submit();
  });
});

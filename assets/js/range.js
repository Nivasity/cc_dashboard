$(function() {
  $('.range-option').on('click', function(e) {
    e.preventDefault();
    var range = $(this).data('range');
    $.get('model/range_data.php', { range: range }, function(data) {
      $('#total-revenue-amount').text('₦' + Number(data.total_revenue).toLocaleString());
      $('#total-revenue-growth').removeClass('text-success text-danger').addClass(data.revenue_class)
        .html('<i class="bx ' + data.revenue_icon + '"></i> ' + (data.growth_percent >= 0 ? '+' : '') + data.growth_percent + '%');
      $('#total-sales-amount').text('₦' + Number(data.total_sales).toLocaleString());
      $('#total-sales-growth').removeClass('text-success text-danger').addClass(data.sales_class)
        .html('<i class="bx ' + data.sales_icon + '"></i> ' + (data.sales_growth_percent >= 0 ? '+' : '') + data.sales_growth_percent + '%');
    }, 'json');
  });
});

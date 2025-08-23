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

  $('.year-option').on('click', function(e) {
    e.preventDefault();
    var year = $(this).data('year');
    $('#growthReportId').text(year);
    $.get('model/growth_data.php', { year: year }, function(data) {
      $('#growthChart').data('growth', data.growth_percent);
      ApexCharts.exec('growthChart', 'updateSeries', [data.growth_percent]);
      $('#growth-chart-text').text(Math.round(data.growth_percent) + '% Company Growth');
      $('#current-year-label').text(data.curr_year);
      $('#current-year-amount').text('₦' + Number(data.total_revenue).toLocaleString());
      $('#prev-year-label').text(data.prev_year);
      $('#prev-year-amount').text('₦' + Number(data.prev_revenue).toLocaleString());
      $('#totalRevenueChart')
        .attr('data-curr-year', data.curr_year)
        .attr('data-prev-year', data.prev_year)
        .attr('data-current', JSON.stringify(data.monthly_current))
        .attr('data-prev', JSON.stringify(data.monthly_previous));
      ApexCharts.exec('totalRevenueChart', 'updateOptions', {
        series: [
          { name: data.curr_year, data: data.monthly_current },
          { name: data.prev_year, data: data.monthly_previous.map(function(n){ return -n; }) }
        ]
      });
    }, 'json');
  });
});

$(function () {
  $('.range-option').on('click', function (e) {
    e.preventDefault();
    var range = $(this).data('range');
    var label = $(this).text();
    $('.range-display').text(label);

    $.get('model/range_data.php', { range: range }, function (data) {
      $('#total-revenue-amount').text('\u20A6' + Number(data.total_revenue).toLocaleString());
      $('#total-revenue-growth')
        .removeClass('text-success text-danger')
        .addClass(data.revenue_class)
        .html('<i class="bx ' + data.revenue_icon + '"></i> ' + data.growth_sign + data.growth_percent + '%');

      $('#total-sales-amount').text('\u20A6' + Number(data.total_sales).toLocaleString());
      $('#total-sales-growth')
        .removeClass('text-success text-danger')
        .addClass(data.sales_class)
        .html('<i class="bx ' + data.sales_icon + '"></i> ' + data.sales_growth_sign + data.sales_growth_percent + '%');

      ApexCharts.exec('totalRevenueChart', 'updateOptions', {
        xaxis: {
          categories: data.chart_categories || ['Revenue']
        },
        series: [
          { name: data.chart_current_label || 'Current Period', data: data.chart_current || [0] },
          { name: data.chart_previous_label || 'Previous Period', data: data.chart_previous || [0] }
        ]
      });
    }, 'json');
  });

  $('.year-option').on('click', function (e) {
    e.preventDefault();
    var year = $(this).data('year');
    $('#growthReportId').text(year);
    $.get('model/growth_data.php', { year: year }, function (data) {
      $('#growthChart').attr('data-growth', data.growth_percent);
      ApexCharts.exec('growthChart', 'updateSeries', [data.growth_percent]);
      $('#growth-chart-text').text(Math.round(data.growth_percent) + '% Company Growth');
      $('#current-year-label').text(data.curr_year);
      $('#current-year-amount').text('\u20A6' + Number(data.total_revenue).toLocaleString());
      $('#prev-year-label').text(data.prev_year);
      $('#prev-year-amount').text('\u20A6' + Number(data.prev_revenue).toLocaleString());
    }, 'json');
  });
});

<?php

if (!function_exists('get_dashboard_revenue_range_payload')) {
  function get_dashboard_revenue_range_payload($conn, $admin_role, $admin_school, $admin_faculty, $range_raw)
  {
    $config = dashboard_resolve_range_config($range_raw);
    $apply_commission = ((int)$admin_role === 5);

    $revenue_base = dashboard_build_amount_base_sql('t.profit', $admin_role, $admin_school, $admin_faculty);
    $sales_base = dashboard_build_amount_base_sql('t.amount', $admin_role, $admin_school, $admin_faculty);

    $total_revenue = dashboard_sum_for_window($conn, $revenue_base, $config['current_start'], $config['current_end'], $apply_commission);
    $prev_revenue = dashboard_sum_for_window($conn, $revenue_base, $config['prev_start'], $config['prev_end'], $apply_commission);

    $total_sales = dashboard_sum_for_window($conn, $sales_base, $config['current_start'], $config['current_end'], false);
    $prev_sales = dashboard_sum_for_window($conn, $sales_base, $config['prev_start'], $config['prev_end'], false);

    $growth_diff = $total_revenue - $prev_revenue;
    $growth_percent = $prev_revenue > 0
      ? (abs($growth_diff) / $prev_revenue) * 100
      : ($total_revenue > 0 ? 100 : 0);

    $sales_diff = $total_sales - $prev_sales;
    $sales_growth_percent = $prev_sales > 0
      ? (abs($sales_diff) / $prev_sales) * 100
      : ($total_sales > 0 ? 100 : 0);

    $series = dashboard_build_chart_series(
      $conn,
      $revenue_base,
      $config,
      $apply_commission
    );

    return [
      'range' => $config['range'],
      'range_label' => $config['range_label'],
      'total_revenue' => (int) round($total_revenue),
      'prev_revenue' => (int) round($prev_revenue),
      'growth_percent' => round($growth_percent, 2),
      'growth_sign' => $growth_diff >= 0 ? '+' : '-',
      'revenue_class' => $growth_diff >= 0 ? 'text-success' : 'text-danger',
      'revenue_icon' => $growth_diff >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt',
      'total_sales' => (int) round($total_sales),
      'prev_sales' => (int) round($prev_sales),
      'sales_growth_percent' => round($sales_growth_percent, 2),
      'sales_growth_sign' => $sales_diff >= 0 ? '+' : '-',
      'sales_class' => $sales_diff >= 0 ? 'text-success' : 'text-danger',
      'sales_icon' => $sales_diff >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt',
      'chart_categories' => $series['categories'],
      'chart_current' => $series['current'],
      'chart_previous' => $series['previous'],
      'chart_current_label' => $config['comparison_current_label'],
      'chart_previous_label' => $config['comparison_prev_label'],
    ];
  }
}

if (!function_exists('dashboard_build_amount_base_sql')) {
  function dashboard_build_amount_base_sql($amount_field, $admin_role, $admin_school, $admin_faculty)
  {
    $admin_role = (int) $admin_role;
    $admin_school = (int) $admin_school;
    $admin_faculty = (int) $admin_faculty;

    $base = "SELECT COALESCE(SUM({$amount_field}),0) AS total FROM transactions t WHERE t.status = 'successful'";
    if ($admin_role === 5 && $admin_school > 0) {
      $base .= " AND EXISTS (SELECT 1 FROM manuals_bought b JOIN manuals m ON b.manual_id = m.id LEFT JOIN depts d ON m.dept = d.id WHERE b.ref_id = t.ref_id AND b.status='successful' AND b.school_id = {$admin_school}";
      if ($admin_faculty !== 0) {
        $base .= " AND (m.faculty = {$admin_faculty} OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = {$admin_faculty}))";
      }
      $base .= ")";
    }

    return $base;
  }
}

if (!function_exists('dashboard_sum_for_window')) {
  function dashboard_sum_for_window($conn, $base_sql, $start_dt, $end_dt, $apply_commission)
  {
    $start = mysqli_real_escape_string($conn, $start_dt->format('Y-m-d H:i:s'));
    $end = mysqli_real_escape_string($conn, $end_dt->format('Y-m-d H:i:s'));
    $sql = $base_sql . " AND t.created_at >= '{$start}' AND t.created_at < '{$end}'";
    $row = mysqli_fetch_assoc(mysqli_query($conn, $sql));
    $total = (float) ($row['total'] ?? 0);

    if ($apply_commission) {
      $total *= 0.1;
    }

    return $total;
  }
}

if (!function_exists('dashboard_resolve_range_config')) {
  function dashboard_resolve_range_config($range_raw)
  {
    $range_raw = strtolower(trim((string) $range_raw));
    $now = new DateTimeImmutable('now');
    $today_start = new DateTimeImmutable($now->format('Y-m-d 00:00:00'));

    $range = '24h';
    if ($range_raw === '7d' || $range_raw === 'weekly') {
      $range = '7d';
    } elseif ($range_raw === '30d' || $range_raw === 'monthly') {
      $range = '30d';
    } elseif ($range_raw === '90d' || $range_raw === 'quarter') {
      $range = '90d';
    } elseif ($range_raw === 'this_year' || $range_raw === 'yearly') {
      $range = 'this_year';
    } elseif ($range_raw === '24h') {
      $range = '24h';
    }

    if ($range === '7d') {
      $current_start = $today_start->sub(new DateInterval('P6D'));
      $current_end = $now;
      $prev_end = $current_start;
      $prev_start = $current_start->sub(new DateInterval('P7D'));
      return [
        'range' => $range,
        'range_label' => '7 Days',
        'comparison_current_label' => 'Last 7 Days',
        'comparison_prev_label' => 'Previous 7 Days',
        'current_start' => $current_start,
        'current_end' => $current_end,
        'prev_start' => $prev_start,
        'prev_end' => $prev_end,
        'chart_mode' => 'daily',
        'bucket_count' => 7,
        'bucket_interval' => 'P1D',
      ];
    }

    if ($range === '30d') {
      $current_start = $today_start->sub(new DateInterval('P29D'));
      $current_end = $now;
      $prev_end = $current_start;
      $prev_start = $current_start->sub(new DateInterval('P30D'));
      return [
        'range' => $range,
        'range_label' => '30 Days',
        'comparison_current_label' => 'Last 30 Days',
        'comparison_prev_label' => 'Previous 30 Days',
        'current_start' => $current_start,
        'current_end' => $current_end,
        'prev_start' => $prev_start,
        'prev_end' => $prev_end,
        'chart_mode' => 'day_block',
        'bucket_count' => 6,
        'bucket_interval' => 'P5D',
      ];
    }

    if ($range === '90d') {
      $current_start = $today_start->sub(new DateInterval('P89D'));
      $current_end = $now;
      $prev_end = $current_start;
      $prev_start = $current_start->sub(new DateInterval('P90D'));
      return [
        'range' => $range,
        'range_label' => '90 Days',
        'comparison_current_label' => 'Last 90 Days',
        'comparison_prev_label' => 'Previous 90 Days',
        'current_start' => $current_start,
        'current_end' => $current_end,
        'prev_start' => $prev_start,
        'prev_end' => $prev_end,
        'chart_mode' => 'day_block',
        'bucket_count' => 6,
        'bucket_interval' => 'P15D',
      ];
    }

    if ($range === 'this_year') {
      $current_start = new DateTimeImmutable($now->format('Y-01-01 00:00:00'));
      $current_end = $now;
      $prev_start = $current_start->sub(new DateInterval('P1Y'));
      $prev_end = $current_start;
      return [
        'range' => $range,
        'range_label' => 'This Year',
        'comparison_current_label' => 'This Year',
        'comparison_prev_label' => 'Last Year',
        'current_start' => $current_start,
        'current_end' => $current_end,
        'prev_start' => $prev_start,
        'prev_end' => $prev_end,
        'chart_mode' => 'month',
      ];
    }

    $current_start = $now->sub(new DateInterval('PT24H'));
    $current_end = $now;
    $prev_end = $current_start;
    $prev_start = $current_start->sub(new DateInterval('PT24H'));
    return [
      'range' => '24h',
      'range_label' => '24 Hours',
      'comparison_current_label' => 'Last 24 Hours',
      'comparison_prev_label' => 'Previous 24 Hours',
      'current_start' => $current_start,
      'current_end' => $current_end,
      'prev_start' => $prev_start,
      'prev_end' => $prev_end,
      'chart_mode' => 'hourly',
      'bucket_count' => 12,
      'bucket_interval' => 'PT2H',
    ];
  }
}

if (!function_exists('dashboard_build_chart_series')) {
  function dashboard_build_chart_series($conn, $base_sql, $config, $apply_commission)
  {
    $categories = [];
    $current = [];
    $previous = [];

    if ($config['chart_mode'] === 'month') {
      $curr_year = (int) $config['current_start']->format('Y');
      $prev_year = (int) $config['prev_start']->format('Y');

      for ($m = 1; $m <= 12; $m++) {
        $curr_start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $curr_year, $m));
        $curr_end = ($m === 12)
          ? new DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $curr_year + 1))
          : new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $curr_year, $m + 1));

        $prev_start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $prev_year, $m));
        $prev_end = ($m === 12)
          ? new DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $prev_year + 1))
          : new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $prev_year, $m + 1));

        $categories[] = $curr_start->format('M');
        $current[] = (int) round(dashboard_sum_for_window($conn, $base_sql, $curr_start, $curr_end, $apply_commission));
        $previous[] = (int) round(dashboard_sum_for_window($conn, $base_sql, $prev_start, $prev_end, $apply_commission));
      }

      return [
        'categories' => $categories,
        'current' => $current,
        'previous' => $previous,
      ];
    }

    $bucket_count = (int) $config['bucket_count'];
    $interval_spec = (string) $config['bucket_interval'];
    $interval = new DateInterval($interval_spec);

    for ($i = 0; $i < $bucket_count; $i++) {
      $curr_start = $config['current_start']->add(new DateInterval(dashboard_interval_multiply($interval_spec, $i)));
      $curr_end = $curr_start->add($interval);
      $prev_start = $config['prev_start']->add(new DateInterval(dashboard_interval_multiply($interval_spec, $i)));
      $prev_end = $prev_start->add($interval);

      if ($curr_end > $config['current_end']) {
        $curr_end = $config['current_end'];
      }
      if ($prev_end > $config['prev_end']) {
        $prev_end = $config['prev_end'];
      }

      if ($config['chart_mode'] === 'hourly') {
        $categories[] = $curr_start->format('H:i');
      } elseif ($config['chart_mode'] === 'daily') {
        $categories[] = $curr_start->format('d M');
      } else {
        $label_end = $curr_end->sub(new DateInterval('PT1S'));
        $categories[] = $curr_start->format('d M') . ' - ' . $label_end->format('d M');
      }

      $current[] = (int) round(dashboard_sum_for_window($conn, $base_sql, $curr_start, $curr_end, $apply_commission));
      $previous[] = (int) round(dashboard_sum_for_window($conn, $base_sql, $prev_start, $prev_end, $apply_commission));
    }

    return [
      'categories' => $categories,
      'current' => $current,
      'previous' => $previous,
    ];
  }
}

if (!function_exists('dashboard_interval_multiply')) {
  function dashboard_interval_multiply($spec, $multiplier)
  {
    $multiplier = (int) $multiplier;
    if ($multiplier <= 0) {
      return 'PT0S';
    }

    if ($spec === 'PT2H') {
      return 'PT' . (2 * $multiplier) . 'H';
    }
    if ($spec === 'P1D') {
      return 'P' . $multiplier . 'D';
    }
    if ($spec === 'P5D') {
      return 'P' . (5 * $multiplier) . 'D';
    }
    if ($spec === 'P15D') {
      return 'P' . (15 * $multiplier) . 'D';
    }

    return 'PT0S';
  }
}

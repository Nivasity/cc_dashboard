<?php
/**
 * Helper functions for transaction filtering
 */

/**
 * Build date filter SQL clause based on date range parameters
 * 
 * @param mysqli $conn Database connection for escaping strings
 * @param string $date_range Date range type ('7', '30', '90', 'all', 'custom')
 * @param string $start_date Start date for custom range (Y-m-d format)
 * @param string $end_date End date for custom range (Y-m-d format)
 * @return string SQL WHERE clause fragment (with leading AND)
 */
function buildDateFilter($conn, $date_range, $start_date, $end_date) {
  $date_filter = "";
  
  if ($date_range === 'custom' && $start_date && $end_date) {
    // Validate date format
    $start_dt = DateTime::createFromFormat('Y-m-d', $start_date);
    $end_dt = DateTime::createFromFormat('Y-m-d', $end_date);
    
    if ($start_dt && $end_dt && $start_dt->format('Y-m-d') === $start_date && $end_dt->format('Y-m-d') === $end_date) {
      $start_date = mysqli_real_escape_string($conn, $start_date);
      $end_date = mysqli_real_escape_string($conn, $end_date);
      $date_filter = " AND t.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
    }
  } elseif ($date_range !== 'all') {
    $days = intval($date_range);
    if ($days > 0) {
      $date_filter = " AND t.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    }
  }
  
  return $date_filter;
}
?>

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
 * @param string $table_alias Table alias that owns the created_at column
 * @return string SQL WHERE clause fragment (with leading AND)
 */
function buildDateFilter($conn, $date_range, $start_date, $end_date, $table_alias = 't') {
  $date_filter = "";
  $table_alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table_alias);
  if ($table_alias === '') {
    $table_alias = 't';
  }
  $createdAtColumn = $table_alias . '.created_at';
  
  if ($date_range === 'custom' && $start_date && $end_date) {
    // Validate date format
    $start_dt = DateTime::createFromFormat('Y-m-d', $start_date);
    $end_dt = DateTime::createFromFormat('Y-m-d', $end_date);
    
    if ($start_dt && $end_dt && $start_dt->format('Y-m-d') === $start_date && $end_dt->format('Y-m-d') === $end_date) {
      $start_date = mysqli_real_escape_string($conn, $start_date);
      $end_date = mysqli_real_escape_string($conn, $end_date);
      $date_filter = " AND {$createdAtColumn} BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
    }
  } elseif ($date_range !== 'all') {
    $days = intval($date_range);
    if ($days > 0) {
      $date_filter = " AND {$createdAtColumn} >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    }
  }
  
  return $date_filter;
}

/**
 * Build SQL clause for filtering materials by host faculty, falling back to faculty.
 *
 * @param string $manual_alias
 * @param int $faculty_id
 * @return string
 */
function buildHostedMaterialFacultyFilter($manual_alias, $faculty_id) {
  $faculty_id = intval($faculty_id);
  if ($faculty_id <= 0) {
    return '';
  }

  $manual_alias = trim((string)$manual_alias);
  if ($manual_alias === '') {
    $manual_alias = 'm';
  }

  return " AND (
    CASE
      WHEN {$manual_alias}.host_faculty IS NOT NULL AND {$manual_alias}.host_faculty <> 0 THEN {$manual_alias}.host_faculty
      ELSE IFNULL({$manual_alias}.faculty, 0)
    END = {$faculty_id}
  )";
}

/**
 * Build SQL clause for filtering materials by department.
 * Uses legacy dept when set, otherwise checks membership inside depts.
 *
 * @param string $manual_alias
 * @param int $dept_id
 * @return string
 */
function buildHostedMaterialDeptFilter($manual_alias, $dept_id) {
  $dept_id = intval($dept_id);
  if ($dept_id <= 0) {
    return '';
  }

  $manual_alias = trim((string)$manual_alias);
  if ($manual_alias === '') {
    $manual_alias = 'm';
  }

  return " AND (
    (IFNULL({$manual_alias}.dept, 0) <> 0 AND {$manual_alias}.dept = {$dept_id})
    OR
    (IFNULL({$manual_alias}.dept, 0) = 0 AND {$manual_alias}.depts IS NOT NULL AND {$manual_alias}.depts <> '' AND FIND_IN_SET({$dept_id}, {$manual_alias}.depts))
  )";
}
?>

<?php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/functions.php');

$status = 'failed';
$message = '';
$data = [];

$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_id = $_SESSION['nivas_adminId'] ?? null;
$admin_school = $admin_faculty = 0;
if ($admin_role == 5 && $admin_id) {
  $info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $admin_id"));
  if ($info) {
    $admin_school = (int)$info['school'];
    $admin_faculty = (int)$info['faculty'];
  }
}

function h_int($v) { return intval($v ?? 0); }
function make_random_txref($prefix = 'batch_nivas_') {
  $digits = '';
  for ($i = 0; $i < 10; $i++) { $digits .= (string)random_int(0, 9); }

  return $prefix . $digits;
}

function make_batch_item_ref($student_id, $student_matric, $prefix = 'batch_nivas_') {
  $digits = '';
  for ($i = 0; $i < 10; $i++) { $digits .= (string)random_int(0, 9); }

  if ((int)$student_id > 0) {
    return $prefix . (int)$student_id . '_' . $digits;
  }

  $matric_part = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$student_matric));
  if ($matric_part === '') {
    $matric_part = 'UNMATCHED';
  }

  return $prefix . substr($matric_part, 0, 12) . '_' . $digits;
}

function normalize_matric($value) {
  $value = strtoupper(trim((string)$value));
  $value = preg_replace('/\s+/', '', $value);
  return $value;
}

function normalize_person_name($value) {
  $value = trim((string)$value);
  $value = preg_replace('/\s+/', ' ', $value);
  return $value;
}

function normalize_phone_number($value) {
  $value = trim((string)$value);
  $digits = preg_replace('/\D+/', '', $value);
  if ($digits === '') {
    return '';
  }

  return strpos($value, '+') === 0 ? '+' . $digits : $digits;
}

function normalize_csv_header_value($value) {
  return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string)$value)));
}

function is_csv_header_value($value) {
  $normalized = normalize_csv_header_value($value);
  return in_array($normalized, ['MATRIC', 'MATRICNO', 'MATRICNUMBER', 'STUDENTMATRIC', 'STUDENTMATRICNO'], true);
}

function student_csv_header_aliases() {
  return [
    'matric' => ['MATRIC', 'MATRICNO', 'MATRICNUMBER', 'STUDENTMATRIC', 'STUDENTMATRICNO'],
    'first_name' => ['FIRSTNAME', 'FIRST', 'GIVENNAME', 'STUDENTFIRSTNAME'],
    'last_name' => ['LASTNAME', 'LAST', 'SURNAME', 'FAMILYNAME', 'STUDENTLASTNAME'],
  ];
}

function find_student_csv_header_indices($row) {
  $aliases = student_csv_header_aliases();
  $indices = [
    'matric' => null,
    'first_name' => null,
    'last_name' => null,
  ];

  foreach ((array)$row as $index => $cell) {
    $normalized = normalize_csv_header_value($cell);
    if ($normalized === '') {
      continue;
    }

    foreach ($aliases as $field => $field_aliases) {
      if ($indices[$field] === null && in_array($normalized, $field_aliases, true)) {
        $indices[$field] = (int)$index;
      }
    }
  }

  $matched_headers = 0;
  foreach ($indices as $field_index) {
    if ($field_index !== null) {
      $matched_headers++;
    }
  }

  return [
    'has_header' => $matched_headers > 0,
    'indices' => $indices,
  ];
}

function parse_uploaded_student_csv($field_name, $options = []) {
  $require_names = !empty($options['require_names']);

  if (!isset($_FILES[$field_name])) {
    throw new Exception('Upload the CSV file containing student matric numbers.');
  }

  $file = $_FILES[$field_name];
  $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($error !== UPLOAD_ERR_OK) {
    throw new Exception(batch_csv_upload_error_message($error));
  }

  $tmp_name = $file['tmp_name'] ?? '';
  if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
    throw new Exception('The uploaded CSV file could not be read.');
  }

  $handle = fopen($tmp_name, 'r');
  if ($handle === false) {
    throw new Exception('Failed to open the uploaded CSV file.');
  }

  $student_rows = [];
  $matrics = [];
  $seen = [];
  $duplicates_removed = 0;
  $row_number = 0;
  $header_indices = [
    'matric' => null,
    'first_name' => null,
    'last_name' => null,
  ];
  $header_mode = false;

  while (($row = fgetcsv($handle)) !== false) {
    $row_number++;

    if ($row_number === 1) {
      $header_info = find_student_csv_header_indices($row);
      $header_mode = !empty($header_info['has_header']);
      $header_indices = $header_info['indices'];

      if ($require_names && (
        $header_indices['matric'] === null
        || $header_indices['first_name'] === null
        || $header_indices['last_name'] === null
      )) {
        fclose($handle);
        throw new Exception('Students CSV must contain first_name, last_name, and matric_no columns.');
      }

      if ($header_mode) {
        continue;
      }
    }

    if ($header_mode) {
      $has_any_content = false;
      foreach ((array)$row as $cell) {
        if (trim((string)$cell) !== '') {
          $has_any_content = true;
          break;
        }
      }

      if (!$has_any_content) {
        continue;
      }

      $matric = normalize_matric($row[$header_indices['matric']] ?? '');
      $first_name = normalize_person_name($row[$header_indices['first_name']] ?? '');
      $last_name = normalize_person_name($row[$header_indices['last_name']] ?? '');

      if ($matric === '') {
        fclose($handle);
        throw new Exception('Row ' . $row_number . ' is missing a matric number.');
      }

      if ($require_names && ($first_name === '' || $last_name === '')) {
        fclose($handle);
        throw new Exception('Row ' . $row_number . ' must include first name and last name.');
      }

      if (isset($seen[$matric])) {
        $duplicates_removed++;
        continue;
      }

      $seen[$matric] = true;
      $student_rows[] = [
        'matric' => $matric,
        'first_name' => $first_name,
        'last_name' => $last_name,
      ];
      $matrics[] = $matric;
      continue;
    }

    $row_values = [];
    foreach ((array)$row as $cell) {
      $candidate = normalize_matric($cell);
      if ($candidate !== '') {
        $row_values[] = $candidate;
      }
    }

    if (count($row_values) === 0) {
      continue;
    }

    if ($row_number === 1 && count($row_values) === 1 && is_csv_header_value($row_values[0])) {
      continue;
    }

    foreach ($row_values as $matric) {
      if ($row_number === 1 && is_csv_header_value($matric)) {
        continue;
      }

      if (isset($seen[$matric])) {
        $duplicates_removed++;
        continue;
      }

      $seen[$matric] = true;
      $student_rows[] = [
        'matric' => $matric,
        'first_name' => '',
        'last_name' => '',
      ];
      $matrics[] = $matric;
    }
  }

  fclose($handle);

  return [
    'rows' => $student_rows,
    'matrics' => $matrics,
    'duplicates_removed' => $duplicates_removed,
    'has_header' => $header_mode,
  ];
}

function batch_csv_upload_error_message($error_code) {
  switch ((int)$error_code) {
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
      return 'The uploaded CSV file is too large.';
    case UPLOAD_ERR_PARTIAL:
      return 'The CSV file upload was interrupted. Please retry.';
    case UPLOAD_ERR_NO_FILE:
      return 'Upload the CSV file containing student matric numbers.';
    default:
      return 'Failed to upload the CSV file.';
  }
}

function map_batch_students_from_rows($conn, $school_id, $dept_id, $student_rows) {
  $school_id = (int)$school_id;
  $dept_id = (int)$dept_id;
  $normalized_rows = [];
  foreach ((array)$student_rows as $row) {
    $matric = normalize_matric($row['matric'] ?? '');
    if ($matric === '') {
      continue;
    }

    $normalized_rows[] = [
      'matric' => $matric,
      'first_name' => normalize_person_name($row['first_name'] ?? ''),
      'last_name' => normalize_person_name($row['last_name'] ?? ''),
    ];
  }

  $matrics = array_map(function ($row) {
    return $row['matric'];
  }, $normalized_rows);

  if ($school_id <= 0 || $dept_id <= 0 || count($matrics) === 0) {
    return [
      'entries' => [],
      'matched_count' => 0,
      'unmatched_count' => 0,
    ];
  }

  $escaped = [];
  foreach ($matrics as $matric) {
    $escaped[] = "'" . mysqli_real_escape_string($conn, $matric) . "'";
  }

  $matched_lookup = [];
  $sql = "SELECT id, matric_no FROM users WHERE school = $school_id AND dept = $dept_id AND UPPER(TRIM(matric_no)) IN (" . implode(',', $escaped) . ") ORDER BY id ASC";
  $res = mysqli_query($conn, $sql);
  if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
      $key = normalize_matric($row['matric_no'] ?? '');
      if ($key !== '' && !isset($matched_lookup[$key])) {
        $matched_lookup[$key] = (int)($row['id'] ?? 0);
      }
    }
  }

  $entries = [];
  $matched_count = 0;
  $unmatched_count = 0;

  foreach ($normalized_rows as $row) {
    $matric = $row['matric'];
    $student_id = isset($matched_lookup[$matric]) ? (int)$matched_lookup[$matric] : 0;
    if ($student_id > 0) {
      $matched_count++;
    } else {
      $unmatched_count++;
    }

    $entries[] = [
      'student_id' => $student_id,
      'student_matric' => $matric,
      'student_first_name' => $row['first_name'],
      'student_last_name' => $row['last_name'],
    ];
  }

  return [
    'entries' => $entries,
    'matched_count' => $matched_count,
    'unmatched_count' => $unmatched_count,
  ];
}

function map_batch_students($conn, $school_id, $dept_id, $matrics) {
  $student_rows = [];
  foreach ((array)$matrics as $matric) {
    $normalized = normalize_matric($matric);
    if ($normalized === '') {
      continue;
    }

    $student_rows[] = [
      'matric' => $normalized,
      'first_name' => '',
      'last_name' => '',
    ];
  }

  return map_batch_students_from_rows($conn, $school_id, $dept_id, $student_rows);
}

function respond_batch_json($status, $message, $data = []) {
  header('Content-Type: application/json');
  echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $data,
  ]);
  exit;
}

function manual_batch_allowed_receipt_mime_types() {
  return [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'application/pdf' => 'pdf',
  ];
}

function manual_batch_resolve_receipt_mime_type($tmp_name, $client_type = '') {
  $client_type = trim((string)$client_type);
  $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
  $mime_type = $finfo ? (string)finfo_file($finfo, $tmp_name) : '';
  if ($finfo) {
    finfo_close($finfo);
  }

  if ($mime_type === '' && function_exists('mime_content_type')) {
    $mime_type = (string)mime_content_type($tmp_name);
  }

  if ($mime_type === '') {
    $mime_type = $client_type;
  }

  return strtolower(trim($mime_type));
}

function manual_batch_store_receipt($file, $reference) {
  if (!is_array($file) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    throw new Exception('Upload the payment receipt as an image or PDF.');
  }

  $error_code = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_OK;
  if ($error_code !== UPLOAD_ERR_OK) {
    throw new Exception('Receipt upload failed. Please try again.');
  }

  $file_size = isset($file['size']) ? (int)$file['size'] : 0;
  if ($file_size <= 0) {
    throw new Exception('Receipt file is empty.');
  }

  if ($file_size > (8 * 1024 * 1024)) {
    throw new Exception('Receipt file must not exceed 8MB.');
  }

  $mime_type = manual_batch_resolve_receipt_mime_type($file['tmp_name'], $file['type'] ?? '');
  $allowed_mime_types = manual_batch_allowed_receipt_mime_types();
  if (!isset($allowed_mime_types[$mime_type])) {
    throw new Exception('Receipt must be a JPG, PNG, WEBP, GIF image, or PDF.');
  }

  $safe_reference = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim((string)$reference));
  if ($safe_reference === '') {
    $safe_reference = 'manual_payment_receipt';
  }

  $upload_dir = __DIR__ . '/../assets/images/manual_payment_receipts/';
  if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
    throw new Exception('Could not prepare the receipt upload directory.');
  }

  $stored_name = $safe_reference . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed_mime_types[$mime_type];
  $destination = $upload_dir . $stored_name;
  if (!move_uploaded_file($file['tmp_name'], $destination)) {
    throw new Exception('Could not save the uploaded receipt.');
  }

  return [
    'receipt_path' => 'assets/images/manual_payment_receipts/' . $stored_name,
    'receipt_name' => (string)($file['name'] ?? $stored_name),
    'receipt_mime_type' => $mime_type,
    'receipt_size' => $file_size,
  ];
}

function manual_batch_delete_stored_file($relative_path) {
  $relative_path = trim((string)$relative_path);
  if ($relative_path === '') {
    return;
  }

  $full_path = realpath(__DIR__ . '/..');
  if ($full_path === false) {
    return;
  }

  $candidate = $full_path . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relative_path, '/\\'));
  if (is_file($candidate)) {
    @unlink($candidate);
  }
}

function generate_unique_manual_payment_batch_ref(mysqli $conn, $prefix = 'batch_nivas_') {
  $attempts = 0;
  do {
    $tx_ref = make_random_txref($prefix);
    $check = mysqli_query($conn, "SELECT id FROM manual_payment_batches WHERE tx_ref = '" . mysqli_real_escape_string($conn, $tx_ref) . "' LIMIT 1");
    $exists = $check ? mysqli_num_rows($check) : 0;
    $attempts++;
  } while ($exists && $attempts < 5);

  if ($exists) {
    throw new Exception('Failed to generate unique tx_ref after 5 attempts.');
  }

  return $tx_ref;
}

function manual_batch_schema_column_exists(mysqli $conn, $table, $column) {
  $table_safe = mysqli_real_escape_string($conn, (string)$table);
  $column_safe = mysqli_real_escape_string($conn, (string)$column);
  $sql = "SELECT 1
          FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '$table_safe'
            AND COLUMN_NAME = '$column_safe'
          LIMIT 1";
  $res = mysqli_query($conn, $sql);
  return $res instanceof mysqli_result && mysqli_num_rows($res) > 0;
}

function manual_batch_schema_ready(mysqli $conn) {
  static $ready = null;
  if ($ready !== null) {
    return $ready;
  }

  $required_columns = [
    'manual_payment_batches' => ['paid_by_name', 'paid_by_phone', 'payment_reason', 'receipt_path', 'receipt_name', 'receipt_mime_type', 'receipt_size'],
    'manual_payment_batch_items' => ['student_first_name', 'student_last_name'],
  ];

  foreach ($required_columns as $table => $columns) {
    foreach ($columns as $column) {
      if (!manual_batch_schema_column_exists($conn, $table, $column)) {
        $ready = false;
        return $ready;
      }
    }
  }

  $ready = true;
  return $ready;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $fetch = $_GET['fetch'] ?? '';
  if ($fetch === 'preview') {
    $manual_id = h_int($_GET['manual_id'] ?? 0);
    $school_id = h_int($_GET['school'] ?? 0);
    $dept_id = h_int($_GET['dept'] ?? 0);

    if ($admin_role == 5) {
      $school_id = $admin_school;
    }

    if ($manual_id <= 0 || $school_id <= 0 || $dept_id <= 0) {
      $message = 'Provide manual, school and department.';
    } else {
      $mres = mysqli_query($conn, "SELECT id, price, school_id FROM manuals WHERE id = $manual_id");
      $manual = $mres ? mysqli_fetch_assoc($mres) : null;
      if (!$manual) {
        $message = 'Selected material not found.';
      } elseif ($admin_role == 5 && (int)$manual['school_id'] !== $admin_school) {
        $message = 'You cannot access this material.';
      } else {
        $price_per_student = (int)$manual['price'];
        $count_sql = "SELECT COUNT(*) AS cnt FROM users WHERE dept = $dept_id AND school = $school_id AND status = 'verified'";
        $cres = mysqli_query($conn, $count_sql);
        $crow = $cres ? mysqli_fetch_assoc($cres) : ['cnt' => 0];
        $student_count = (int)($crow['cnt'] ?? 0);
        $total_amount = $student_count * $price_per_student;
  $tx_ref = 'batch_nivas_' . $manual_id . '_' . time();
  $tx_ref = make_random_txref();
        $status = 'success';
        $data = [
          'student_count' => $student_count,
          'price_per_student' => $price_per_student,
          'total_amount' => $total_amount,
          'tx_ref' => $tx_ref
        ];
      }
    }
  }

  header('Content-Type: application/json');
  echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $data
  ]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'preview_batch_csv') {
    $manual_id = h_int($_POST['manual_id'] ?? 0);
    $school_id = h_int($_POST['school'] ?? 0);
    $dept_id = h_int($_POST['dept'] ?? 0);

    if ($admin_role == 5) {
      $school_id = $admin_school;
    }

    if ($manual_id <= 0 || $school_id <= 0 || $dept_id <= 0) {
      respond_batch_json('failed', 'Provide manual, school and department.');
    }

    $mres = mysqli_query($conn, "SELECT id, price, school_id FROM manuals WHERE id = $manual_id");
    $manual = $mres ? mysqli_fetch_assoc($mres) : null;
    if (!$manual) {
      respond_batch_json('failed', 'Selected material not found.');
    }
    if ($admin_role == 5 && (int)$manual['school_id'] !== $admin_school) {
      respond_batch_json('failed', 'You cannot access this material.');
    }

    try {
      $csv = parse_uploaded_student_csv('students_csv');
    } catch (Exception $e) {
      respond_batch_json('failed', $e->getMessage());
    }

    $student_map = map_batch_students($conn, $school_id, $dept_id, $csv['matrics']);
    $student_count = count($student_map['entries']);
    if ($student_count === 0) {
      respond_batch_json('failed', 'No matric numbers were found in the uploaded CSV file.');
    }

    $price_per_student = (int)$manual['price'];
    $total_amount = $student_count * $price_per_student;
    respond_batch_json('success', 'Batch preview prepared successfully.', [
      'student_count' => $student_count,
      'matched_count' => (int)$student_map['matched_count'],
      'unmatched_count' => (int)$student_map['unmatched_count'],
      'duplicates_removed' => (int)$csv['duplicates_removed'],
      'price_per_student' => $price_per_student,
      'total_amount' => $total_amount,
      'tx_ref' => make_random_txref(),
    ]);
  }

  if ($action === 'preview_manual_batch_csv') {
    $manual_id = h_int($_POST['manual_id'] ?? 0);
    $school_id = h_int($_POST['school'] ?? 0);
    $dept_id = h_int($_POST['dept'] ?? 0);

    if ($admin_role == 5) {
      $school_id = $admin_school;
    }

    if ($manual_id <= 0 || $school_id <= 0 || $dept_id <= 0) {
      respond_batch_json('failed', 'Provide material, school and department.');
    }

    $mres = mysqli_query($conn, "SELECT id, price, school_id FROM manuals WHERE id = $manual_id");
    $manual = $mres ? mysqli_fetch_assoc($mres) : null;
    if (!$manual) {
      respond_batch_json('failed', 'Selected material not found.');
    }
    if ((int)$manual['school_id'] !== $school_id) {
      respond_batch_json('failed', 'Selected material does not belong to the chosen school.');
    }
    if ($admin_role == 5 && (int)$manual['school_id'] !== $admin_school) {
      respond_batch_json('failed', 'You cannot access this material.');
    }

    try {
      $csv = parse_uploaded_student_csv('students_csv', ['require_names' => true]);
    } catch (Exception $e) {
      respond_batch_json('failed', $e->getMessage());
    }

    $student_map = map_batch_students_from_rows($conn, $school_id, $dept_id, $csv['rows']);
    $student_count = count($student_map['entries']);
    if ($student_count === 0) {
      respond_batch_json('failed', 'No student rows were found in the uploaded CSV file.');
    }

    $price_per_student = (int)$manual['price'];
    $total_amount = $student_count * $price_per_student;
    respond_batch_json('success', 'Manual payment preview prepared successfully.', [
      'student_count' => $student_count,
      'matched_count' => (int)$student_map['matched_count'],
      'unmatched_count' => (int)$student_map['unmatched_count'],
      'duplicates_removed' => (int)$csv['duplicates_removed'],
      'price_per_student' => $price_per_student,
      'total_amount' => $total_amount,
    ]);
  }

  if ($action === 'create_manual_batch') {
    if (!$admin_id) {
      $message = 'You must be signed in to record a manual material purchase.';
    } elseif (!manual_batch_schema_ready($conn)) {
      $message = 'Manual material purchase setup is incomplete. Run sql/alter_manual_payment_batches_add_manual_purchase_fields.sql first.';
    } elseif (in_array((int)$admin_role, [3, 5], true)) {
      $message = 'You are not allowed to record manual material purchases.';
    } else {
      $manual_id = h_int($_POST['manual_id'] ?? 0);
      $school_id = h_int($_POST['school'] ?? 0);
      $dept_id = h_int($_POST['dept'] ?? 0);
      $paid_by_name = normalize_person_name($_POST['paid_by_name'] ?? '');
      $paid_by_phone = normalize_phone_number($_POST['paid_by_phone'] ?? '');
      $payment_reason = trim((string)($_POST['payment_reason'] ?? ''));
      $phone_digits = preg_replace('/\D+/', '', $paid_by_phone);

      if ($admin_role == 5) {
        $school_id = $admin_school;
      }

      if ($manual_id <= 0 || $school_id <= 0 || $dept_id <= 0) {
        $message = 'Provide material, school and department.';
      } elseif ($paid_by_name === '') {
        $message = 'Enter the name of the person who paid manually.';
      } elseif ($paid_by_phone === '' || strlen($phone_digits) < 7 || strlen($phone_digits) > 15) {
        $message = 'Enter a valid phone number for the person who paid manually.';
      } elseif ($payment_reason === '') {
        $message = 'Enter the reason for recording this manual payment.';
      } else {
        $mres = mysqli_query($conn, "SELECT id, price, user_id AS seller, school_id FROM manuals WHERE id = $manual_id");
        $manual = $mres ? mysqli_fetch_assoc($mres) : null;
        if (!$manual) {
          $message = 'Selected material not found.';
        } elseif ((int)$manual['school_id'] !== $school_id) {
          $message = 'Selected material does not belong to the chosen school.';
        } elseif ($admin_role == 5 && (int)$manual['school_id'] !== $admin_school) {
          $message = 'You cannot access this material.';
        } else {
          try {
            $csv = parse_uploaded_student_csv('students_csv', ['require_names' => true]);
          } catch (Exception $e) {
            $message = $e->getMessage();
            $csv = ['rows' => [], 'duplicates_removed' => 0];
          }

          $student_map = map_batch_students_from_rows($conn, $school_id, $dept_id, $csv['rows'] ?? []);
          $student_entries = $student_map['entries'];

          if ($message === '' && count($student_entries) === 0) {
            $message = 'No student rows were found in the uploaded CSV file.';
          }

          if ($message === '') {
            $price_per_student = (int)$manual['price'];
            $total_students = count($student_entries);
            $total_amount = $total_students * $price_per_student;
            $seller = (int)($manual['seller'] ?? 0);
            $receipt = null;

            try {
              $tx_ref = generate_unique_manual_payment_batch_ref($conn, 'manual_ext_');
              $receipt = manual_batch_store_receipt($_FILES['payment_receipt'] ?? null, $tx_ref);
            } catch (Exception $e) {
              $message = $e->getMessage();
            }

            if ($message === '') {
              $batch_stmt = null;
              $item_stmt = null;
              $manual_stmt = null;
              $inserted_manual_refs = [];

              mysqli_begin_transaction($conn);
              try {
                $hoc_id = 0;
                $gateway = 'MANUAL';
                $receipt_path = (string)$receipt['receipt_path'];
                $receipt_name = (string)$receipt['receipt_name'];
                $receipt_mime_type = (string)$receipt['receipt_mime_type'];
                $receipt_size = (int)$receipt['receipt_size'];

                $batch_stmt = $conn->prepare('INSERT INTO manual_payment_batches (manual_id, hoc_id, dept_id, school_id, total_students, total_amount, tx_ref, gateway, status, paid_by_name, paid_by_phone, payment_reason, receipt_path, receipt_name, receipt_mime_type, receipt_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "paid", ?, ?, ?, ?, ?, ?, ?)');
                if (!$batch_stmt) {
                  throw new Exception('Failed to prepare the manual batch record.');
                }

                $batch_stmt->bind_param('iiiiiissssssssi', $manual_id, $hoc_id, $dept_id, $school_id, $total_students, $total_amount, $tx_ref, $gateway, $paid_by_name, $paid_by_phone, $payment_reason, $receipt_path, $receipt_name, $receipt_mime_type, $receipt_size);
                if (!$batch_stmt->execute()) {
                  throw new Exception('Failed to save the manual batch record.');
                }
                $batch_id = (int)$batch_stmt->insert_id;
                $batch_stmt->close();
                $batch_stmt = null;

                $item_stmt = $conn->prepare('INSERT INTO manual_payment_batch_items (batch_id, manual_id, student_id, student_matric, student_first_name, student_last_name, price, ref_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "paid")');
                if (!$item_stmt) {
                  throw new Exception('Failed to prepare manual batch items.');
                }

                $manual_stmt = $conn->prepare('INSERT INTO manuals_bought (manual_id, price, seller, buyer, school_id, ref_id, status) VALUES (?, ?, ?, ?, ?, ?, "successful")');
                if (!$manual_stmt) {
                  throw new Exception('Failed to prepare material purchase inserts.');
                }

                foreach ($student_entries as $entry) {
                  $student_id = (int)($entry['student_id'] ?? 0);
                  $student_matric = (string)($entry['student_matric'] ?? '');
                  $student_first_name = (string)($entry['student_first_name'] ?? '');
                  $student_last_name = (string)($entry['student_last_name'] ?? '');
                  $item_ref = make_batch_item_ref($student_id, $student_matric, 'manual_ext_');

                  $item_stmt->bind_param('iiisssis', $batch_id, $manual_id, $student_id, $student_matric, $student_first_name, $student_last_name, $price_per_student, $item_ref);
                  if (!$item_stmt->execute()) {
                    throw new Exception('Failed to save a manual batch item.');
                  }

                  if ($student_id <= 0) {
                    continue;
                  }

                  $manual_stmt->bind_param('iiiiis', $manual_id, $price_per_student, $seller, $student_id, $school_id, $item_ref);
                  if (!$manual_stmt->execute()) {
                    throw new Exception('Failed to grant material access for a matched student.');
                  }

                  $inserted_manual_refs[] = $item_ref;
                }

                $item_stmt->close();
                $item_stmt = null;
                $manual_stmt->close();
                $manual_stmt = null;

                mysqli_commit($conn);
                log_audit_event($conn, $admin_id, 'create', 'manual_payment_batch', $batch_id, [
                  'entry_mode' => 'manual',
                  'manual_id' => $manual_id,
                  'dept_id' => $dept_id,
                  'school_id' => $school_id,
                  'total_students' => $total_students,
                  'total_amount' => $total_amount,
                  'tx_ref' => $tx_ref,
                  'gateway' => $gateway,
                  'paid_by_name' => $paid_by_name,
                  'paid_by_phone' => $paid_by_phone,
                  'payment_reason' => $payment_reason,
                  'matched_count' => (int)$student_map['matched_count'],
                  'unmatched_count' => (int)$student_map['unmatched_count'],
                  'duplicates_removed' => (int)$csv['duplicates_removed'],
                  'receipt_path' => $receipt_path,
                ]);

                $status = 'success';
                $message = 'Manual material purchases recorded successfully.';
                if ((int)$student_map['unmatched_count'] > 0) {
                  $message .= ' ' . (int)$student_map['unmatched_count'] . ' student row(s) were stored without a linked user account.';
                }
                $data = [
                  'batch_id' => $batch_id,
                  'tx_ref' => $tx_ref,
                  'total_students' => $total_students,
                  'matched_count' => (int)$student_map['matched_count'],
                  'unmatched_count' => (int)$student_map['unmatched_count'],
                  'duplicates_removed' => (int)$csv['duplicates_removed'],
                  'total_amount' => $total_amount,
                  'gateway' => $gateway,
                  'receipt_name' => $receipt_name,
                ];
              } catch (Exception $e) {
                if ($batch_stmt instanceof mysqli_stmt) {
                  $batch_stmt->close();
                }
                if ($item_stmt instanceof mysqli_stmt) {
                  $item_stmt->close();
                }
                if ($manual_stmt instanceof mysqli_stmt) {
                  $manual_stmt->close();
                }

                if (count($inserted_manual_refs) > 0) {
                  $escaped_refs = [];
                  foreach (array_unique($inserted_manual_refs) as $ref_id) {
                    $escaped_refs[] = "'" . mysqli_real_escape_string($conn, $ref_id) . "'";
                  }
                  mysqli_query($conn, 'DELETE FROM manuals_bought WHERE ref_id IN (' . implode(',', $escaped_refs) . ')');
                }

                mysqli_rollback($conn);
                if (is_array($receipt)) {
                  manual_batch_delete_stored_file($receipt['receipt_path'] ?? '');
                }
                $message = $e->getMessage();
              }
            }
          }
        }
      }
    }

    header('Content-Type: application/json');
    echo json_encode([
      'status' => $status,
      'message' => $message,
      'data' => $data
    ]);
    exit;
  }

  if ($action === 'create_batch') {
    if (!$admin_id) {
      $message = 'You must be signed in to create a batch.';
    } elseif (in_array((int)$admin_role, [3, 5], true)) {
      $message = 'You are not allowed to create batch payments.';
    } else {
      $manual_id = h_int($_POST['manual_id'] ?? 0);
      $school_id = h_int($_POST['school'] ?? 0);
      $dept_id = h_int($_POST['dept'] ?? 0);
      $tx_ref = trim($_POST['tx_ref'] ?? '');
      $total_amount = h_int($_POST['total_amount'] ?? 0);
      if ($admin_role == 5) { $school_id = $admin_school; }

      if ($manual_id <= 0 || $school_id <= 0 || $dept_id <= 0 || $total_amount <= 0) {
        $message = 'Provide manual, school, department, and total amount.';
      } else {
        $mres = mysqli_query($conn, "SELECT id, price, user_id AS seller, school_id FROM manuals WHERE id = $manual_id");
        $manual = $mres ? mysqli_fetch_assoc($mres) : null;
        if (!$manual) {
          $message = 'Selected material not found.';
        } elseif ($admin_role == 5 && (int)$manual['school_id'] !== $admin_school) {
          $message = 'You cannot access this material.';
        } else {
          try {
            $csv = parse_uploaded_student_csv('students_csv');
          } catch (Exception $e) {
            $message = $e->getMessage();
            $csv = ['matrics' => [], 'duplicates_removed' => 0];
          }

          $price_per_student = (int)$manual['price'];
          $student_map = map_batch_students($conn, $school_id, $dept_id, $csv['matrics']);
          $student_entries = $student_map['entries'];

          if (count($student_entries) === 0) {
            $message = $message !== '' ? $message : 'No matric numbers were found in the uploaded CSV file.';
          } else {
            $total_students = count($student_entries);
            if ($tx_ref === '') {
              $tx_ref = generate_unique_manual_payment_batch_ref($conn, 'batch_nivas_');
            }

            $gateway = 'PAYSTACK';

            // Lookup HOC for the selected school and dept (use selected school/dept, not current session admin)
            $hoc_id = 0;
            $hres = mysqli_query($conn, "SELECT id FROM users WHERE role = 'hoc' AND status = 'verified' AND school = $school_id AND dept = $dept_id LIMIT 1");
            if ($hres && mysqli_num_rows($hres) > 0) {
              $hrow = mysqli_fetch_assoc($hres);
              $hoc_id = (int)$hrow['id'];
            } else {
              $message = 'No verified HOC (Head of Class) found for the selected school and department. Please assign an HOC to this department first.';
            }

            if ($hoc_id <= 0) {
              header('Content-Type: application/json');
              echo json_encode([
                'status' => 'failed',
                'message' => $message,
                'data' => []
              ]);
              exit;
            }

            mysqli_begin_transaction($conn);
            try {
                $tx_ref = generate_unique_manual_payment_batch_ref($conn, 'batch_nivas_');

                // Insert batch with the generated tx_ref
                $stmt = $conn->prepare('INSERT INTO manual_payment_batches (manual_id, hoc_id, dept_id, school_id, total_students, total_amount, tx_ref, gateway, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending")');
                $stmt->bind_param('iiiiiiss', $manual_id, $hoc_id, $dept_id, $school_id, $total_students, $total_amount, $tx_ref, $gateway);
                if (!$stmt->execute()) { throw new Exception('Failed to create batch.'); }
                $batch_id = $stmt->insert_id;
                $stmt->close();

              $item_stmt = $conn->prepare('INSERT INTO manual_payment_batch_items (batch_id, manual_id, student_id, student_matric, price, ref_id, status) VALUES (?, ?, ?, ?, ?, ?, "pending")');

              foreach ($student_entries as $entry) {
                $student_id = (int)($entry['student_id'] ?? 0);
                $student_matric = (string)($entry['student_matric'] ?? '');
                $ref_id = make_batch_item_ref($student_id, $student_matric);

                $item_stmt->bind_param('iiisis', $batch_id, $manual_id, $student_id, $student_matric, $price_per_student, $ref_id);
                if (!$item_stmt->execute()) { throw new Exception('Failed to add batch item.'); }
              }
              $item_stmt->close();

              mysqli_commit($conn);
              log_audit_event($conn, $admin_id, 'create', 'manual_payment_batch', $batch_id, [
                'manual_id' => $manual_id,
                'dept_id' => $dept_id,
                'school_id' => $school_id,
                'total_students' => $total_students,
                'total_amount' => $total_amount,
                'tx_ref' => $tx_ref,
                'gateway' => $gateway,
                'matched_count' => (int)$student_map['matched_count'],
                'unmatched_count' => (int)$student_map['unmatched_count'],
                'duplicates_removed' => (int)$csv['duplicates_removed']
              ]);

              $status = 'success';
              $message = 'Batch created successfully.';
              if ((int)$student_map['unmatched_count'] > 0) {
                $message .= ' ' . (int)$student_map['unmatched_count'] . ' matric number(s) were saved without a linked student record.';
              }
              $data = [
                'batch_id' => $batch_id,
                'tx_ref' => $tx_ref,
                'total_students' => $total_students,
                'matched_count' => (int)$student_map['matched_count'],
                'unmatched_count' => (int)$student_map['unmatched_count'],
                'duplicates_removed' => (int)$csv['duplicates_removed'],
                'total_amount' => $total_amount,
                'gateway' => $gateway
              ];
            } catch (Exception $e) {
              mysqli_rollback($conn);
              $message = $e->getMessage();
            }
          }
        }
      }
    }

    header('Content-Type: application/json');
    echo json_encode([
      'status' => $status,
      'message' => $message,
      'data' => $data
    ]);
    exit;
  }
}

header('Content-Type: application/json');
echo json_encode([
  'status' => $status,
  'message' => $message,
  'data' => $data
]);
?>

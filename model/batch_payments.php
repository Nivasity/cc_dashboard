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
function make_random_txref() {
  // Generate 10 random digits
  $digits = '';
  for ($i = 0; $i < 10; $i++) { $digits .= (string)random_int(0,9); }
  return 'batch_nivas_' . $digits;
}

function make_batch_item_ref($student_id, $student_matric) {
  $digits = '';
  for ($i = 0; $i < 10; $i++) { $digits .= (string)random_int(0,9); }

  if ((int)$student_id > 0) {
    return 'batch_nivas_' . (int)$student_id . '_' . $digits;
  }

  $matric_part = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$student_matric));
  if ($matric_part === '') {
    $matric_part = 'UNMATCHED';
  }

  return 'batch_nivas_' . substr($matric_part, 0, 12) . '_' . $digits;
}

function normalize_matric($value) {
  $value = strtoupper(trim((string)$value));
  $value = preg_replace('/\s+/', '', $value);
  return $value;
}

function is_csv_header_value($value) {
  $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string)$value)));
  return in_array($normalized, ['MATRIC', 'MATRICNO', 'MATRICNUMBER', 'STUDENTMATRIC', 'STUDENTMATRICNO'], true);
}

function parse_uploaded_student_csv($field_name) {
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

  $matrics = [];
  $seen = [];
  $duplicates_removed = 0;
  $row_number = 0;

  while (($row = fgetcsv($handle)) !== false) {
    $row_number++;
    $matric = '';
    foreach ((array)$row as $cell) {
      $candidate = normalize_matric($cell);
      if ($candidate !== '') {
        $matric = $candidate;
        break;
      }
    }

    if ($matric === '') {
      continue;
    }

    if ($row_number === 1 && is_csv_header_value($matric)) {
      continue;
    }

    if (isset($seen[$matric])) {
      $duplicates_removed++;
      continue;
    }

    $seen[$matric] = true;
    $matrics[] = $matric;
  }

  fclose($handle);

  return [
    'matrics' => $matrics,
    'duplicates_removed' => $duplicates_removed,
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

function map_batch_students($conn, $school_id, $dept_id, $matrics) {
  $school_id = (int)$school_id;
  $dept_id = (int)$dept_id;
  $matrics = array_values(array_filter(array_map('normalize_matric', (array)$matrics)));

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

  foreach ($matrics as $matric) {
    $student_id = isset($matched_lookup[$matric]) ? (int)$matched_lookup[$matric] : 0;
    if ($student_id > 0) {
      $matched_count++;
    } else {
      $unmatched_count++;
    }

    $entries[] = [
      'student_id' => $student_id,
      'student_matric' => $matric,
    ];
  }

  return [
    'entries' => $entries,
    'matched_count' => $matched_count,
    'unmatched_count' => $unmatched_count,
  ];
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

  if ($action === 'create_batch') {
    if (!$admin_id) {
      $message = 'You must be signed in to create a batch.';
    } elseif (in_array((int)$admin_role, [4, 5], true)) {
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
            // ensure generated tx_ref is unique
            $attempts = 0;
            do {
             $tx_ref = make_random_txref();
             $check = mysqli_query($conn, "SELECT id FROM manual_payment_batches WHERE tx_ref = '" . mysqli_real_escape_string($conn, $tx_ref) . "' LIMIT 1");
             $exists = $check ? mysqli_num_rows($check) : 0;
             $attempts++;
            } while ($exists && $attempts < 5);
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
                // Generate unique tx_ref: batch_nivas_{10 random digits} with DB uniqueness check
                $attempts = 0;
                do {
                  $rand_digits = '';
                  for ($i = 0; $i < 10; $i++) { $rand_digits .= (string)random_int(0,9); }
                  $tx_ref = 'batch_nivas_' . $rand_digits;
                  $check = mysqli_query($conn, "SELECT id FROM manual_payment_batches WHERE tx_ref = '" . mysqli_real_escape_string($conn, $tx_ref) . "' LIMIT 1");
                  $exists = $check ? mysqli_num_rows($check) : 0;
                  $attempts++;
                } while ($exists && $attempts < 5);

                if ($exists) { throw new Exception('Failed to generate unique tx_ref after 5 attempts.'); }

                // Insert batch with the generated tx_ref
                $stmt = $conn->prepare('INSERT INTO manual_payment_batches (manual_id, hoc_id, dept_id, school_id, total_students, total_amount, tx_ref, gateway, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending")');
                $stmt->bind_param('iiiiiiss', $manual_id, $hoc_id, $dept_id, $school_id, $total_students, $total_amount, $tx_ref, $gateway);
                if (!$stmt->execute()) { throw new Exception('Failed to create batch.'); }
                $batch_id = $stmt->insert_id;
                $stmt->close();

              $item_stmt = $conn->prepare('INSERT INTO manual_payment_batch_items (batch_id, manual_id, student_id, student_matric, price, ref_id, status) VALUES (?, ?, ?, ?, ?, ?, "pending")');
              $txn_stmt = $conn->prepare('INSERT INTO transactions (ref_id, user_id, batch_id, amount, status, medium) VALUES (?, ?, ?, ?, "pending", "PAYSTACK")');

              foreach ($student_entries as $entry) {
                $student_id = (int)($entry['student_id'] ?? 0);
                $student_matric = (string)($entry['student_matric'] ?? '');
                $ref_id = make_batch_item_ref($student_id, $student_matric);

                $item_stmt->bind_param('iiisis', $batch_id, $manual_id, $student_id, $student_matric, $price_per_student, $ref_id);
                if (!$item_stmt->execute()) { throw new Exception('Failed to add batch item.'); }

                $txn_stmt->bind_param('siii', $ref_id, $student_id, $batch_id, $price_per_student);
                if (!$txn_stmt->execute()) { throw new Exception('Failed to create pending transactions.'); }
              }
              $item_stmt->close();
              $txn_stmt->close();

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

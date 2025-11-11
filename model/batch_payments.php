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
  if ($action === 'create_batch') {
    if (!$admin_id) {
      $message = 'You must be signed in to create a batch.';
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
          $price_per_student = (int)$manual['price'];
          $students = [];
          $sres = mysqli_query($conn, "SELECT id FROM users WHERE dept = $dept_id AND school = $school_id AND status = 'verified'");
          if ($sres) {
            while ($row = mysqli_fetch_assoc($sres)) { $students[] = (int)$row['id']; }
          }

          if (count($students) === 0) {
            $message = 'No verified students found in the selected department.';
          } else {
            $total_students = count($students);
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
                $stmt = $conn->prepare('INSERT INTO manual_payment_batches (manual_id, hoc_id, dept_id, school_id, total_students, total_amount, tx_ref, status) VALUES (?, ?, ?, ?, ?, ?, ?, "pending")');
                $stmt->bind_param('iiiiiss', $manual_id, $hoc_id, $dept_id, $school_id, $total_students, $total_amount, $tx_ref);
                if (!$stmt->execute()) { throw new Exception('Failed to create batch.'); }
                $batch_id = $stmt->insert_id;
                $stmt->close();

              $item_stmt = $conn->prepare('INSERT INTO manual_payment_batch_items (batch_id, manual_id, student_id, price, ref_id, status) VALUES (?, ?, ?, ?, ?, "pending")');
              $txn_stmt = $conn->prepare('INSERT INTO transactions (ref_id, user_id, batch_id, amount, status, medium) VALUES (?, ?, ?, ?, "pending", "FLUTTERWAVE")');

              $now = time();
              foreach ($students as $student_id) {
                // Build item ref_id as: batch_nivas_{user_id}_{10 random digits}
                $rand = '';
                for ($ri = 0; $ri < 10; $ri++) { $rand .= (string)random_int(0,9); }
                $ref_id = 'batch_nivas_' . $student_id . '_' . $rand;

                $item_stmt->bind_param('iiiis', $batch_id, $manual_id, $student_id, $price_per_student, $ref_id);
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
                'tx_ref' => $tx_ref
              ]);

              $status = 'success';
              $message = 'Batch created successfully.';
              $data = [ 'batch_id' => $batch_id, 'tx_ref' => $tx_ref, 'total_students' => $total_students, 'total_amount' => $total_amount ];
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

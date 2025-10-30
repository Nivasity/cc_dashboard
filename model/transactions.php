<?php
session_start();
include('config.php');
include('functions.php');

$statusRes = 'failed';
$messageRes = '';
$faculties = $departments = $transactions = $materials = null;
$user = null;
$restrict_faculty = false;

$admin_role = $_SESSION['nivas_adminRole'] ?? null;
$admin_id = $_SESSION['nivas_adminId'] ?? null;
$admin_school = $admin_faculty = 0;
if ($admin_role == 5 && $admin_id) {
  $info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school, faculty FROM admins WHERE id = $admin_id"));
  $admin_school = $info['school'];
  $admin_faculty = $info['faculty'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'create_manual_transaction') {
    if (!$admin_id) {
      $messageRes = 'You must be signed in to record a manual transaction.';
    } else {
      $email = trim($_POST['email'] ?? '');
      $transaction_ref = trim($_POST['transaction_ref'] ?? '');
      $manual_ids = $_POST['manuals'] ?? [];
      if (!is_array($manual_ids)) {
        $manual_ids = $manual_ids !== '' ? [$manual_ids] : [];
      }
      $manual_ids = array_values(array_unique(array_filter(array_map(function ($id) {
        return (int)$id;
      }, $manual_ids), function ($id) {
        return $id > 0;
      })));

      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messageRes = 'Please provide a valid email address.';
      } elseif (empty($manual_ids)) {
        $messageRes = 'Select at least one course material.';
      } elseif ($transaction_ref === '') {
        $messageRes = 'Transaction reference is required.';
      } else {
        $user_stmt = $conn->prepare('SELECT id, email, first_name, last_name, school, dept, status FROM users WHERE email = ? LIMIT 1');
        $user_stmt->bind_param('s', $email);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result ? $user_result->fetch_assoc() : null;
        $user_stmt->close();

        if (!$user_data) {
          $messageRes = 'No user found with the provided email address.';
        } else {
          $ref_stmt = $conn->prepare('SELECT id FROM transactions WHERE ref_id = ? LIMIT 1');
          $ref_stmt->bind_param('s', $transaction_ref);
          $ref_stmt->execute();
          $ref_stmt->store_result();
          $ref_exists = $ref_stmt->num_rows > 0;
          $ref_stmt->close();

          if ($ref_exists) {
            $messageRes = 'The supplied transaction reference already exists.';
          } else {
            $manual_id_list = implode(',', $manual_ids);
            if ($manual_id_list === '') {
              $messageRes = 'No valid materials were provided.';
            } else {
              $manual_sql = "SELECT id, title, course_code, code, price, user_id AS seller, school_id, dept, faculty FROM manuals WHERE id IN ($manual_id_list)";
              if ($admin_role == 5) {
                $manual_sql .= " AND school_id = $admin_school";
              }
              $manual_query = mysqli_query($conn, $manual_sql);
              $manual_records = array();
              while ($manual_row = mysqli_fetch_assoc($manual_query)) {
                $manual_records[] = $manual_row;
              }

              if (count($manual_records) !== count($manual_ids)) {
                $messageRes = 'Some selected materials could not be found or you do not have permission to access them.';
              } else {
                $user_school = (int)($user_data['school'] ?? 0);
                $all_school_match = true;
                foreach ($manual_records as $record) {
                  $manual_school = (int)($record['school_id'] ?? 0);
                  if ($manual_school > 0 && $user_school > 0 && $manual_school !== $user_school) {
                    $all_school_match = false;
                    break;
                  }
                }

                if (!$all_school_match) {
                  $messageRes = 'Selected materials must belong to the same school as the user.';
                } else {
                  $total_amount = 0;
                  foreach ($manual_records as $record) {
                    $total_amount += (int)($record['price'] ?? 0);
                  }

                  mysqli_begin_transaction($conn);
                  try {
                    $charge = 0;
                    $profit = 0;
                    $status = 'successful';
                    $medium = 'MANUAL';
                    $user_id = (int)$user_data['id'];

                    $txn_stmt = $conn->prepare('INSERT INTO transactions (ref_id, user_id, amount, charge, profit, status, medium) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $txn_stmt->bind_param('siiiiss', $transaction_ref, $user_id, $total_amount, $charge, $profit, $status, $medium);
                    if (!$txn_stmt->execute()) {
                      throw new Exception('Unable to save transaction record.');
                    }
                    $txn_stmt->close();

                    $manual_stmt = $conn->prepare('INSERT INTO manuals_bought (manual_id, price, seller, buyer, school_id, ref_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $manual_status = 'successful';
                    foreach ($manual_records as $record) {
                      $manual_id = (int)$record['id'];
                      $price = (int)($record['price'] ?? 0);
                      $seller = (int)($record['seller'] ?? 0);
                      $school_id = (int)($record['school_id'] ?? 0);
                      $manual_stmt->bind_param('iiiiiss', $manual_id, $price, $seller, $user_id, $school_id, $transaction_ref, $manual_status);
                      if (!$manual_stmt->execute()) {
                        throw new Exception('Failed to attach material #' . $manual_id . ' to the transaction.');
                      }
                    }
                    $manual_stmt->close();

                    mysqli_commit($conn);
                    $statusRes = 'success';
                    $messageRes = 'Manual transaction recorded successfully.';
                    log_audit_event($conn, $admin_id, 'create', 'manual_transaction', null, [
                      'ref_id' => $transaction_ref,
                      'user_id' => $user_id,
                      'manual_ids' => $manual_ids,
                      'amount' => $total_amount
                    ]);
                  } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $statusRes = 'failed';
                    $messageRes = $e->getMessage();
                  }
                }
              }
            }
          }
        }
      }
    }
  } else {
    $messageRes = 'Invalid action supplied.';
  }

  $responseData = array(
    'status' => $statusRes,
    'message' => $messageRes
  );

  header('Content-Type: application/json');
  echo json_encode($responseData);
  exit;
}

if (isset($_GET['download']) && $_GET['download'] === 'csv') {
  $school = intval($_GET['school'] ?? 0);
  $faculty = intval($_GET['faculty'] ?? 0);
  $dept = intval($_GET['dept'] ?? 0);
  if ($admin_role == 5) {
    $school = $admin_school;
    if ($admin_faculty != 0) {
      $faculty = $admin_faculty;
    }
  }

  $tran_sql = "SELECT t.ref_id, u.first_name, u.last_name, u.matric_no, " .
    "COALESCE(s.name, '') AS school_name, COALESCE(f.name, '') AS faculty_name, COALESCE(d.name, '') AS dept_name, " .
    "GROUP_CONCAT(CONCAT(m.title, ' - ', m.course_code, ' (', b.price, ')') SEPARATOR ' | ') AS materials, " .
    "t.amount, t.status, t.created_at " .
    "FROM transactions t " .
    "JOIN users u ON t.user_id = u.id " .
    "JOIN manuals_bought b ON b.ref_id = t.ref_id AND b.status='successful' " .
    "JOIN manuals m ON b.manual_id = m.id " .
    "LEFT JOIN depts d ON m.dept = d.id " .
    "LEFT JOIN faculties f ON m.faculty = f.id " .
    "LEFT JOIN schools s ON b.school_id = s.id " .
    "WHERE 1=1";
  if ($school > 0) {
    $tran_sql .= " AND b.school_id = $school";
  }
  if ($faculty != 0) {
    $tran_sql .= " AND (m.faculty = $faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty))";
  }
  if ($dept > 0) {
    $tran_sql .= " AND m.dept = $dept";
  }
  $tran_sql .= " GROUP BY t.id ORDER BY t.created_at DESC";
  $tran_query = mysqli_query($conn, $tran_sql);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="transactions_' . date('Ymd_His') . '.csv"');

  $out = fopen('php://output', 'w');
  fputcsv($out, ['Ref Id', 'Student Name', 'Matric No', 'School', 'Faculty/College', 'Department', 'Materials', 'Total Paid', 'Date', 'Time', 'Status']);
  while ($row = mysqli_fetch_assoc($tran_query)) {
    $dateStr = date('M j, Y', strtotime($row['created_at']));
    $timeStr = date('h:i a', strtotime($row['created_at']));
    $statusStr = $row['status'];
    fputcsv($out, [
      $row['ref_id'],
      trim($row['first_name'] . ' ' . $row['last_name']),
      $row['matric_no'],
      $row['school_name'],
      $row['faculty_name'],
      $row['dept_name'],
      $row['materials'],
      $row['amount'],
      $dateStr,
      $timeStr,
      $statusStr
    ]);
  }
  fclose($out);
  exit;
}

if (isset($_GET['fetch'])) {
  $fetch = $_GET['fetch'];
  $school = intval($_GET['school'] ?? 0);
  $faculty = intval($_GET['faculty'] ?? 0);
  $dept = intval($_GET['dept'] ?? 0);
  if ($admin_role == 5) {
    $school = $admin_school;
    if ($admin_faculty != 0) {
      $faculty = $admin_faculty;
    }
  }

  if ($fetch == 'faculties') {
    if ($admin_role == 5) {
      if ($admin_faculty != 0) {
        $fac_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND id = $admin_faculty");
        $restrict_faculty = true;
      } else {
        $fac_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND school_id = $admin_school ORDER BY name");
      }
    } else {
      $fac_query = ($school > 0) ?
        mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' AND school_id = $school ORDER BY name") :
        mysqli_query($conn, "SELECT id, name FROM faculties WHERE status = 'active' ORDER BY name");
    }
    $faculties = array();
    while ($row = mysqli_fetch_assoc($fac_query)) {
      $faculties[] = $row;
    }
    $statusRes = 'success';
  }

  if ($fetch == 'departments') {
    if ($admin_role == 5) {
      if ($admin_faculty != 0) {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND faculty_id = $admin_faculty ORDER BY name");
      } elseif ($faculty != 0) {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND faculty_id = $faculty AND school_id = $admin_school ORDER BY name");
      } else {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND school_id = $admin_school ORDER BY name");
      }
    } else {
      if ($faculty != 0) {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND faculty_id = $faculty ORDER BY name");
      } elseif ($school > 0) {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND school_id = $school ORDER BY name");
      } else {
        $dept_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' ORDER BY name");
      }
    }
    $departments = array();
    while ($row = mysqli_fetch_assoc($dept_query)) {
      $departments[] = $row;
    }
    $statusRes = 'success';
  }

  if ($fetch == 'transactions') {
    $tran_sql = "SELECT t.ref_id, t.amount, t.status, t.created_at, u.first_name, u.last_name, u.matric_no, " .
      "GROUP_CONCAT(CONCAT(m.title, ' - ', m.course_code, ' (₦ ', b.price, ')') SEPARATOR '<br>') AS materials " .
      "FROM transactions t " .
      "JOIN users u ON t.user_id = u.id " .
      "JOIN manuals_bought b ON b.ref_id = t.ref_id AND b.status='successful' " .
      "JOIN manuals m ON b.manual_id = m.id " .
      "LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";
    if ($school > 0) {
      $tran_sql .= " AND b.school_id = $school";
    }
    if ($faculty != 0) {
      $tran_sql .= " AND (m.faculty = $faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty))";
    }
    if ($dept > 0) {
      $tran_sql .= " AND m.dept = $dept";
    }
    $tran_sql .= " GROUP BY t.id ORDER BY t.created_at DESC";
    $tran_query = mysqli_query($conn, $tran_sql);
    $transactions = array();
    while ($row = mysqli_fetch_assoc($tran_query)) {
      $transactions[] = array(
        'ref_id' => $row['ref_id'],
        'student' => $row['first_name'] . ' ' . $row['last_name'],
        'matric' => $row['matric_no'],
        'materials' => $row['materials'] ?? '',
        'amount' => $row['amount'],
        'date' => date('M j, Y', strtotime($row['created_at'])),
        'time' => date('h:i a', strtotime($row['created_at'])),
        'status' => $row['status']
      );
    }
    $statusRes = 'success';
  }

  if ($fetch == 'materials') {
    $material_sql = "SELECT id, title, course_code, code, price FROM manuals WHERE 1=1";
    if ($admin_role == 5) {
      $material_sql .= " AND school_id = $admin_school";
    }
    $material_sql .= " ORDER BY title ASC";
    $material_query = mysqli_query($conn, $material_sql);
    $materials = array();
    while ($row = mysqli_fetch_assoc($material_query)) {
      $materials[] = array(
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'course_code' => $row['course_code'],
        'code' => $row['code'],
        'price' => (int)$row['price']
      );
    }
    $statusRes = 'success';
    if (count($materials) === 0) {
      $messageRes = 'No course materials found.';
    }
  }

  if ($fetch == 'user_details') {
    $email = trim($_GET['email'] ?? '');
    if ($email === '') {
      $messageRes = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $messageRes = 'Please provide a valid email address.';
    } else {
      $user_stmt = $conn->prepare('SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.matric_no, u.status, u.school, u.dept, s.name AS school_name, d.name AS dept_name, f.name AS faculty_name FROM users u LEFT JOIN schools s ON u.school = s.id LEFT JOIN depts d ON u.dept = d.id LEFT JOIN faculties f ON d.faculty_id = f.id WHERE u.email = ? LIMIT 1');
      $user_stmt->bind_param('s', $email);
      $user_stmt->execute();
      $result = $user_stmt->get_result();
      if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $statusRes = 'success';
      } else {
        $messageRes = 'No user found with the provided email address.';
      }
      $user_stmt->close();
    }
  }
}

$responseData = array(
  'status' => $statusRes,
  'message' => $messageRes,
  'faculties' => $faculties,
  'departments' => $departments,
  'transactions' => $transactions,
  'materials' => $materials,
  'user' => $user,
  'restrict_faculty' => $restrict_faculty
);

header('Content-Type: application/json');
echo json_encode($responseData);
?>

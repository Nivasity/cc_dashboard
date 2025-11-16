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

function calculate_handling_charge(int $subtotal): int
{
  if ($subtotal <= 0) {
    return 0;
  }

  if ($subtotal < 2500) {
    return 70;
  }

  $percentage_fee = $subtotal * 0.02;
  if ($subtotal < 5000) {
    $addon = 20;
  } elseif ($subtotal < 10000) {
    $addon = 30;
  } else {
    $addon = 50;
  }

  return (int)round($percentage_fee + $addon);
}

function calculate_transaction_profit(int $subtotal, int $charge): int
{
  if ($charge <= 0) {
    return 0;
  }

  $transferAmount = $subtotal + $charge;
  $gateway_fee = round($transferAmount * 0.02, 2);
  $profit = max($charge - $gateway_fee, 0);

  return (int)round($profit);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'create_manual_transaction') {
    if (!$admin_id) {
      $messageRes = 'You must be signed in to record a manual transaction.';
    } elseif ((int)$admin_role === 5) {
      $messageRes = 'You are not allowed to record manual transactions.';
    } else {
      $email = trim($_POST['email'] ?? '');
      $transaction_ref = trim($_POST['transaction_ref'] ?? '');
      $posted_user_id = intval($_POST['user_id'] ?? 0);
      $posted_status = strtolower(trim($_POST['status'] ?? 'successful'));
      $posted_amount = intval($_POST['amount'] ?? 0);
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
      } elseif ($posted_status !== 'refunded' && empty($manual_ids)) {
        $messageRes = 'Select at least one course material.';
      } elseif ($posted_status === 'refunded' && $posted_amount <= 0) {
        $messageRes = 'Enter a valid amount for the refund.';
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
          $user_id = (int)$user_data['id'];
          if ($posted_user_id > 0 && $posted_user_id !== $user_id) {
            $messageRes = 'User mismatch: Please reselect the user.';
          } else {
            // Enforce transaction reference prefix: nivas_{user_id}
            $required_prefix = 'nivas_' . $user_id;
            if (stripos($transaction_ref, $required_prefix) !== 0) {
              $messageRes = 'Transaction reference must start with "' . $required_prefix . '"';
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
            if ($posted_status === 'refunded') {
              // Record a refund without materials
              $user_id = (int)$user_data['id'];
              $status = 'refunded';
              $charge = 0;
              $profit = 0;
              $medium = 'MANUAL';
              $txn_amount = max(0, (int)$posted_amount);

              $txn_stmt = $conn->prepare('INSERT INTO transactions (ref_id, user_id, amount, charge, profit, status, medium) VALUES (?, ?, ?, ?, ?, ?, ?)');
              $txn_stmt->bind_param('siiiiss', $transaction_ref, $user_id, $txn_amount, $charge, $profit, $status, $medium);
              if ($txn_stmt->execute()) {
                $txn_stmt->close();
                $statusRes = 'success';
                $messageRes = 'Refund transaction recorded successfully.';
                log_audit_event($conn, $admin_id, 'create', 'manual_transaction_refund', null, [
                  'ref_id' => $transaction_ref,
                  'user_id' => $user_id,
                  'amount' => $txn_amount
                ]);
              } else {
                $txn_stmt->close();
                $messageRes = 'Unable to save refund transaction record.';
              }
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
                    $subtotal = 0;
                    foreach ($manual_records as $record) {
                      $subtotal += (int)($record['price'] ?? 0);
                    }

                    mysqli_begin_transaction($conn);
                    $manual_stmt = null;
                    try {
                      $charge = calculate_handling_charge($subtotal);
                      $profit = calculate_transaction_profit($subtotal, $charge);
                      $status = 'successful';
                      $medium = 'MANUAL';
                      $user_id = (int)$user_data['id'];
                      $txn_amount = $subtotal + $charge; // total paid = subtotal + charge

                      $txn_stmt = $conn->prepare('INSERT INTO transactions (ref_id, user_id, amount, charge, profit, status, medium) VALUES (?, ?, ?, ?, ?, ?, ?)');
                      $txn_stmt->bind_param('siiiiss', $transaction_ref, $user_id, $txn_amount, $charge, $profit, $status, $medium);
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
                        'amount' => $txn_amount,
                        'subtotal' => $subtotal,
                        'charge' => $charge
                      ]);
                    } catch (Exception $e) {
                      if ($manual_stmt instanceof mysqli_stmt) {
                        $manual_stmt->close();
                      }
                      if ($transaction_ref !== '') {
                        // manuals_bought currently uses MyISAM, so explicitly delete any partial rows
                        // to keep the manual transaction operation atomic.
                        $cleanup_stmt = $conn->prepare('DELETE FROM manuals_bought WHERE ref_id = ?');
                        if ($cleanup_stmt) {
                          $cleanup_stmt->bind_param('s', $transaction_ref);
                          $cleanup_stmt->execute();
                          $cleanup_stmt->close();
                        }
                      }
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
    }
  }
  }

  // If POST with unknown action, set message
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($action ?? '') !== 'create_manual_transaction' && $messageRes === '') {
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
}

// CSV download moved to standalone endpoint: model/transactions_download.php

if (isset($_GET['fetch'])) {
  $fetch = $_GET['fetch'];
  $school = intval($_GET['school'] ?? 0);
  $faculty = intval($_GET['faculty'] ?? 0);
  $dept = intval($_GET['dept'] ?? 0);
  $user_school = intval($_GET['user_school'] ?? 0);
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
      "LEFT JOIN manuals_bought b ON b.ref_id = t.ref_id AND b.status='successful' " .
      "LEFT JOIN manuals m ON b.manual_id = m.id " .
      "LEFT JOIN depts d ON m.dept = d.id WHERE 1=1";
    if ($school > 0) {
      $tran_sql .= " AND (b.school_id = $school OR (b.school_id IS NULL AND u.school = $school))";
    }
    if ($faculty != 0) {
      $tran_sql .= " AND (m.faculty = $faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty))";
    }
    if ($dept > 0) {
      $tran_sql .= " AND m.dept = $dept";
    }
  // Group by all non-aggregated columns to satisfy ONLY_FULL_GROUP_BY
    $tran_sql .= " GROUP BY t.id, t.ref_id, t.amount, t.status, t.created_at, u.first_name, u.last_name, u.matric_no ORDER BY t.created_at DESC";
    $tran_query = mysqli_query($conn, $tran_sql);
    $transactions = array();
    if ($tran_query) {
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
    } else {
      $messageRes = 'Failed to fetch transactions.';
    }
  }

  if ($fetch == 'materials') {
    $material_sql = "SELECT m.id, m.title, m.course_code, m.code, m.price FROM manuals m LEFT JOIN depts d ON m.dept = d.id WHERE m.status = 'open'";
    if ($user_school > 0) {
      $material_sql .= " AND m.school_id = $user_school";
    } elseif ($admin_role == 5) {
      $material_sql .= " AND m.school_id = $admin_school";
      if ($admin_faculty != 0) {
        $material_sql .= " AND (m.faculty = $admin_faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $admin_faculty))";
      }
    } else {
      if ($school > 0) {
        $material_sql .= " AND m.school_id = $school";
      }
      if ($faculty != 0) {
        $material_sql .= " AND (m.faculty = $faculty OR ((m.faculty IS NULL OR m.faculty = 0) AND d.faculty_id = $faculty))";
      }
    }
    if ($dept > 0 && $user_school == 0) {
      $material_sql .= " AND m.dept = $dept";
    }
    $material_sql .= " ORDER BY m.title ASC";
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
      $messageRes = 'No course materials match the selected filters.';
    }
  }

  // User details fetch moved to standalone endpoint: model/transactions_user_details.php
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

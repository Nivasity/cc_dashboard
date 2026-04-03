<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');

function schools_domain_column_exists($conn) {
  static $hasDomainColumn = null;

  if ($hasDomainColumn !== null) {
    return $hasDomainColumn;
  }

  $query = mysqli_query($conn, "SHOW COLUMNS FROM schools LIKE 'domain'");
  $hasDomainColumn = $query && mysqli_num_rows($query) > 0;

  return $hasDomainColumn;
}

function normalize_school_domain($value) {
  $value = trim((string) $value);
  if ($value === '') {
    return '';
  }

  if (strpos($value, '://') === false) {
    $value = 'https://' . $value;
  }

  $host = parse_url($value, PHP_URL_HOST);
  if (!is_string($host) || $host === '') {
    return '';
  }

  $host = strtolower(trim($host));
  $host = preg_replace('/:\d+$/', '', $host);

  return $host;
}

$statusRes = $messageRes = 'failed';

$user_id = $_SESSION['nivas_adminId'];

if (isset($_POST['school_edit'])) {
  $school_id = $_POST['school_id'];
  $status = $_POST['status'];

  if ($status !== 'delete') {
    mysqli_query($conn, "UPDATE schools SET status = '$status' WHERE id = $school_id");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "Status changed successfully!";
      log_audit_event($conn, $user_id, 'status_change', 'school', $school_id, [
        'new_status' => $status
      ]);
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  } else {
    mysqli_query($conn, "DELETE FROM schools WHERE id = $school_id");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "School deleted successfully!";
      log_audit_event($conn, $user_id, 'delete', 'school', $school_id);
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  }

} else {
  $school_id = mysqli_real_escape_string($conn, $_POST['school_id']);
  $name = mysqli_real_escape_string($conn, $_POST['name']);
  $code = mysqli_real_escape_string($conn, $_POST['code']);
  $rawDomain = $_POST['domain'] ?? '';
  $domain = normalize_school_domain($rawDomain);

  if (!schools_domain_column_exists($conn)) {
    $statusRes = "error";
    $messageRes = "The schools.domain column is missing. Run the school domain migration first.";
  } elseif ($domain === '') {
    $statusRes = "failed";
    $messageRes = "Please enter a valid school domain, for example funaab.nivasity.com.";
  } else {
    $domainEscaped = mysqli_real_escape_string($conn, $domain);

    $added = ($school_id == 0) ? '' : " AND id != $school_id";
    $school_query = mysqli_query($conn, "SELECT * FROM schools WHERE code = '$code'$added");
    $domain_query = mysqli_query($conn, "SELECT * FROM schools WHERE domain = '$domainEscaped'$added");

    if (mysqli_num_rows($school_query) >= 1) {
      $messageRes = "A School already exist with this code - $code";
    } elseif ($domain_query && mysqli_num_rows($domain_query) >= 1) {
      $messageRes = "A school already exists with this domain - $domain";
    } else {
      if ($school_id == 0) {
        mysqli_query($conn, "INSERT INTO schools (name, code, domain) VALUES ('$name', '$code', '$domainEscaped')");

        if (mysqli_affected_rows($conn) >= 1) {
          $statusRes = "success";
          $messageRes = "School successfully added!";
          $insert_id = mysqli_insert_id($conn);
          log_audit_event($conn, $user_id, 'create', 'school', $insert_id, [
            'name' => $name,
            'code' => $code,
            'domain' => $domain
          ]);
        } else {
          $statusRes = "error";
          $messageRes = "Internal Server Error. Please try again later!";
        }
      } else {
        mysqli_query($conn, "UPDATE schools SET name = '$name', code = '$code', domain = '$domainEscaped' WHERE id = $school_id");

        if (mysqli_affected_rows($conn) >= 1) {
          $statusRes = "success";
          $messageRes = "School successfully edited!";
          log_audit_event($conn, $user_id, 'update', 'school', $school_id, [
            'name' => $name,
            'code' => $code,
            'domain' => $domain
          ]);
        } else {
          $statusRes = "error";
          $messageRes = "No changes made!";
        }
      }
    }
  }
}


$responseData = array(
  "status" => "$statusRes",
  "message" => "$messageRes",
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);

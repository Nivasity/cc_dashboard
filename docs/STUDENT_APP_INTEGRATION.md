# Integration Notes for Student Application

## Overview
This document describes the integration points between the CC Dashboard (admin) and the Student Application for the grant management feature.

## Current Implementation (CC Dashboard - Admin Side)

### What's Been Implemented:
1. **Database Table**: `manual_export_audits` - Tracks all material exports by HOCs
2. **Admin Role 6**: Grant Manager role with restricted access
3. **Grant Management Page**: `material_grants.php` - View and grant pending exports
4. **API Endpoints**: `model/material_grants.php` - List and grant exports
5. **Audit Logging**: All grant actions are logged in the audit_logs table

## Required Integration (Student Application Side)

### Background
According to the requirements, there is a separate **Student Application** where HOCs (Head of Class) can:
- View lists of students who have bought materials
- Export/download these lists

### What Needs to Be Done in the Student Application:

#### 1. Create Export Audit Records
When an HOC downloads/exports a student list for a material, create a record in `manual_export_audits`:

```php
// Example: After generating the export/download
$export_code = generateUniqueCode(); // Generate a unique code like "GXCZEFPJVY"
$manual_id = /* the material ID */;
$hoc_user_id = /* the HOC's user ID */;
$students_count = /* number of students in the export */;
$total_amount = /* total amount from all students */;

$sql = "INSERT INTO manual_export_audits 
  (code, manual_id, hoc_user_id, students_count, total_amount, grant_status) 
  VALUES 
  ('$export_code', $manual_id, $hoc_user_id, $students_count, $total_amount, 'pending')";
  
mysqli_query($conn, $sql);
```

#### 2. Mark Granted Items in Exports
When generating the export CSV/list, check if the export has been granted:

```php
// Before generating export
$export_id = /* get the export ID */;
$check_sql = "SELECT grant_status, granted_at, granted_by 
  FROM manual_export_audits 
  WHERE id = $export_id";
  
$result = mysqli_query($conn, $check_sql);
$export = mysqli_fetch_assoc($result);

if ($export['grant_status'] === 'granted') {
  // Add a "Granted" marker to the export
  // Add granted date and by whom
  // Example: Add columns to CSV: Granted (Yes/No), Granted Date, Granted By
}
```

#### 3. Display Grant Status to HOCs
In the student application, when HOCs view their exports/downloads:
- Show grant status (Pending/Granted)
- Show granted date if granted
- Optionally show who granted it

Example UI:
```
Export Code: GXCZEFPJVY
Students: 25
Total Amount: ₦15,000
Status: [GRANTED on 2025-11-17 by Admin]
Download: [Download CSV]
```

### Database Schema Reference

```sql
-- Table: manual_export_audits
CREATE TABLE `manual_export_audits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(25) NOT NULL,                -- Unique export code
  `manual_id` int(11) NOT NULL,               -- Material ID
  `hoc_user_id` int(11) NOT NULL,             -- HOC user ID
  `students_count` int(11) NOT NULL,          -- Number of students
  `total_amount` int(11) NOT NULL,            -- Total amount
  `downloaded_at` datetime NOT NULL,          -- When downloaded
  `grant_status` varchar(20) DEFAULT 'pending', -- 'pending' or 'granted'
  `granted_by` int(11) DEFAULT NULL,          -- Admin ID who granted
  `granted_at` datetime DEFAULT NULL,         -- When granted
  `last_student_id` int(11) DEFAULT NULL,     -- Track last student (for pagination)
  PRIMARY KEY (`id`)
);
```

### Example Export Flow

#### Student Application (HOC Side):
1. HOC logs in to student application
2. HOC selects a material to view students who bought it
3. HOC clicks "Export/Download" button
4. System creates record in `manual_export_audits` with status='pending'
5. System generates CSV with student list
6. HOC downloads the file

#### CC Dashboard (Admin Side):
1. Grant Manager (Role 6) logs in to CC Dashboard
2. Views Material Grants page
3. Sees the pending export from HOC
4. Reviews the export details
5. Clicks "Grant" button
6. Export status changes to 'granted'

#### Student Application (HOC Side) - After Grant:
1. HOC logs in again or refreshes
2. Sees export status changed to "Granted"
3. Can re-download with "Granted" marker included

### Security Considerations

1. **Verify HOC Identity**: Ensure only the HOC who created the export can download it
2. **Validate Material Ownership**: Verify HOC has rights to the material
3. **Prevent Duplicate Codes**: Use unique codes for each export
4. **Audit Trail**: Log all export creation and downloads

### Testing Checklist

- [ ] Create export record when HOC downloads student list
- [ ] Verify export appears in CC Dashboard grant management
- [ ] Grant the export from CC Dashboard
- [ ] Verify granted status updates in student application
- [ ] Verify "Granted" marker appears in re-downloaded exports
- [ ] Test with multiple HOCs and materials
- [ ] Test with different grant statuses

### Code Examples

#### Generate Unique Export Code
```php
function generateExportCode($length = 10) {
  $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $code = '';
  for ($i = 0; $i < $length; $i++) {
    $code .= $characters[rand(0, strlen($characters) - 1)];
  }
  return $code;
}

// Ensure uniqueness
do {
  $code = generateExportCode();
  $check = mysqli_query($conn, "SELECT id FROM manual_export_audits WHERE code = '$code'");
} while (mysqli_num_rows($check) > 0);
```

#### Check Grant Status Before Export
```php
// When HOC requests to download an export
$export_id = $_GET['export_id'];
$hoc_user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM manual_export_audits 
  WHERE id = $export_id AND hoc_user_id = $hoc_user_id";
$result = mysqli_query($conn, $sql);
$export = mysqli_fetch_assoc($result);

if ($export) {
  // Generate CSV with grant status
  $csv_header = ['Student Name', 'Matric No', 'Amount', 'Grant Status', 'Granted Date'];
  
  // In the CSV data rows, include:
  if ($export['grant_status'] === 'granted') {
    $grant_info = 'Granted on ' . date('Y-m-d', strtotime($export['granted_at']));
  } else {
    $grant_info = 'Pending';
  }
}
```

## Questions for Student Application Team

1. Where in the student application do HOCs currently export student lists?
2. What format are the exports (CSV, Excel, PDF)?
3. Is there already a download/export history for HOCs?
4. What database connection does the student application use?
5. Can we share the `manual_export_audits` table between applications?

## Support

For questions about the CC Dashboard implementation, refer to `GRANT_MANAGEMENT_README.md`.
For integration issues, please coordinate between admin and student application teams.

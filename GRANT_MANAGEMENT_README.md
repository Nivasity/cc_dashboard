# Admin Role 6 - Grant Management Implementation

## Overview
This implementation adds a new admin role (Role 6 - Grant Manager) with restricted access to only view and grant material downloads made by HOCs (Head of Class).

## Database Changes

### New Table: `manual_export_audits`
Tracks all material downloads by HOCs with grant status tracking.

**Columns:**
- `id` - Primary key
- `code` - Unique export code
- `manual_id` - Reference to the material
- `hoc_user_id` - ID of the HOC who downloaded
- `students_count` - Number of students in the download
- `total_amount` - Total amount for the materials
- `downloaded_at` - When the export was downloaded
- `grant_status` - Status: 'pending' or 'granted'
- `granted_by` - Admin ID who granted (null if pending)
- `granted_at` - Timestamp when granted (null if pending)
- `last_student_id` - Track last student processed (for future pagination)

### New Admin Role
**Role 6 - Grant Manager**
- Can only access the Material Grants page
- Cannot access any other dashboard pages
- **Restricted to specific school and faculty**
- Can view material exports from their assigned school/faculty only
- Can grant pending exports within their scope

## Installation

### For New Installations
The `manual_export_audits` table and role 6 are already included in `niverpay_db.sql`.

### For Existing Installations
Run these SQL scripts in order:

1. **Create the table:**
   ```bash
   mysql -u username -p niverpay_db < sql/add_manual_export_audits_table.sql
   ```

2. **Add the admin role:**
   ```bash
   mysql -u username -p niverpay_db < sql/add_admin_role_6_grant_manager.sql
   ```

3. **Optional - Add sample data for testing:**
   ```bash
   mysql -u username -p niverpay_db < sql/sample_manual_export_audits_data.sql
   ```

## Usage

### Creating a Grant Manager Admin
1. Log in as a super admin (Role 1)
2. Go to Admin Management → Admins → Profiles
3. Create a new admin and assign Role 6 (Grant Manager)
4. **Important:** Assign the admin to a specific school and faculty
   - Set the `school` field to the appropriate school ID
   - Set the `faculty` field to the appropriate faculty ID (or 0 for all faculties in the school)

### Grant Manager Workflow
1. Grant Manager logs in
2. Sees only "Material Grants" in the sidebar
3. Views list of material downloads **from their assigned school/faculty only**
4. Can filter by status (Pending/Granted/All)
5. Clicks "Grant" button for pending items
6. Confirms the grant action
7. Export is marked as granted with timestamp and admin ID

### School/Faculty Filtering
- **School-based:** Grant Manager only sees exports for materials from their assigned school
- **Faculty-based:** If assigned to a specific faculty, only sees exports for materials in that faculty
- **Security:** Cannot grant exports outside their school/faculty scope (403 error)
- Filtering logic matches Role 5 behavior (checks both manual.faculty and dept.faculty_id)

### Features
- **DataTables Integration**: Searchable, sortable table
- **Status Filtering**: Filter by pending, granted, or all
- **School/Faculty Filtering**: Automatic based on admin assignment
- **Real-time Updates**: Table refreshes after grant action
- **Audit Trail**: Tracks who granted and when
- **Responsive Design**: Works on all devices
- **Toast Notifications**: User feedback on actions
- **Security Validation**: Prevents granting exports outside admin's scope

## Files Modified/Created

### Created Files:
- `material_grants.php` - Main grant management page (Role 6 only)
- `model/material_grants.php` - Backend API for listing and granting exports
- `sql/add_manual_export_audits_table.sql` - Migration script for table
- `sql/add_admin_role_6_grant_manager.sql` - Migration script for role
- `sql/sample_manual_export_audits_data.sql` - Sample test data
- `GRANT_MANAGEMENT_README.md` - This documentation

### Modified Files:
- `niverpay_db.sql` - Added manual_export_audits table
- `model/page_config.php` - Added role 6 permissions
- `partials/_sidebar.php` - Added Grant Management menu

## API Endpoints

### List Exports
**URL:** `model/material_grants.php?action=list`
**Method:** GET
**Parameters:**
- `status` (optional): Filter by 'pending', 'granted', or empty for all

**Filtering:**
- Automatically filters by admin's assigned school
- Automatically filters by admin's assigned faculty (if set)
- Only returns exports for materials within admin's scope

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "code": "GXCZEFPJVY",
      "manual_id": 19,
      "manual_title": "Introduction to Computer Science",
      "manual_code": "CSC101",
      "school_id": 1,
      "manual_faculty": 2,
      "hoc_user_id": 1,
      "hoc_first_name": "John",
      "hoc_last_name": "Doe",
      "students_count": 25,
      "total_amount": 15000,
      "downloaded_at": "2025-11-16 13:37:27",
      "grant_status": "pending",
      "granted_by": null,
      "granted_at": null
    }
  ]
}
```

### Grant Export
**URL:** `model/material_grants.php?action=grant`
**Method:** POST
**Parameters:**
- `export_id`: ID of the export to grant

**Security Checks:**
- Validates export belongs to admin's school
- Validates export belongs to admin's faculty (if admin has faculty assigned)
- Returns 403 error if admin tries to grant export outside their scope

**Success Response:**
```json
{
  "success": true,
  "message": "Material export granted successfully"
}
```

**Error Response (Outside Scope):**
```json
{
  "success": false,
  "message": "You do not have permission to grant this export"
}
```

## Security Features
- Role-based access control (only Role 6 can access)
- **School/Faculty-based filtering**: Admins can only see/grant exports within their scope
- Session validation
- SQL injection prevention
- XSS protection via JSON encoding
- **Permission validation**: Prevents granting exports outside admin's school/faculty
- CSRF protection (recommended to add tokens)

## Future Enhancements
1. **Student App Integration**: When HOC exports the list in the student application, mark granted items
2. **Email Notifications**: Notify HOC when their download is granted
3. **Bulk Grant**: Allow granting multiple exports at once
4. **Export Reports**: Generate reports of granted materials
5. **Last Student ID Tracking**: Implement pagination for large student lists

## Troubleshooting

### Grant Manager cannot see the page
- Verify the admin has role = 6 in the `admins` table
- Check if `grant_mgt_menu` is set correctly in `page_config.php`
- Ensure admin has school and faculty assigned in `admins` table

### No data showing in the table
- Verify `manual_export_audits` table exists
- Check if there are records in the table
- **Verify admin's school/faculty assignment matches available exports**
- Check database connection in `model/config.php`
- Verify exports exist for materials in admin's school/faculty

### Grant action fails
- Check admin session is valid
- Verify export exists and is in 'pending' status
- **Verify export belongs to admin's school/faculty**
- Check database permissions for UPDATE operations

## Support
For issues or questions, please contact the development team or create an issue in the repository.

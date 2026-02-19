# Material Grants Feature - Quick Start Guide

## What This Feature Does

This feature adds a new admin role (Role 6 - Material Grant Manager) that can:
- View all materials purchased by students
- Approve/Grant materials with a single click
- Track which materials are pending vs. granted
- See statistics on total, pending, and granted materials

HOCs can export student lists and see grant status for each student.

## Installation

### 1. Run Database Migrations

```bash
# Login to MySQL
mysql -u your_username -p niverpay_db

# Run migration scripts
source /path/to/sql/material_grants.sql;
source /path/to/sql/add_role_6.sql;
```

### 2. Create a Role 6 Admin

**Option A: Via Admin Panel (Recommended)**
1. Login as Super Admin (Role 1)
2. Go to Admin Management → Admins
3. Click "Add New Admin"
4. Fill in details and select Role = "Material Grant Manager" (6)

**Option B: Via SQL**
Edit `sql/add_role_6.sql` and uncomment the INSERT statement at the bottom:
```sql
INSERT INTO `admins` (`first_name`, `last_name`, `email`, `phone`, `role`, `password`, `status`)
VALUES (
  'Grant',
  'Manager',
  'grant.manager@example.com',
  '1234567890',
  6,
  MD5('YourSecurePassword'),  -- Change this!
  'active'
);
```

### 3. Login as Role 6 Admin

1. Navigate to `/signin.html`
2. Login with role 6 credentials
3. You will only see "Material Grants" → "Grant Management" in the sidebar
4. All other pages will redirect to the grants page

## Using the Material Grants Feature

### For Role 6 Admins

**Dashboard View:**
- **Total Materials**: Shows total number of purchased materials
- **Pending Grants**: Materials awaiting approval
- **Granted**: Already approved materials

**Granting Materials:**
1. View the list of pending materials
2. Review student and material details
3. Click "Grant" button to approve
4. Material status changes from "Pending" to "Granted"

**Filtering:**
- Use the status dropdown to filter:
  - "All Status" - Shows everything
  - "Pending" - Only materials awaiting approval
  - "Granted" - Only approved materials

**Search & Sort:**
- Use DataTables search bar to find specific students/materials
- Click column headers to sort

### For HOCs (Exporting Student Lists)

1. Go to "Course Materials" page
2. Select the material to export
3. Click "Download CSV"
4. CSV will include a new "Grant Status" column showing:
   - **pending** - Awaiting grant approval
   - **granted** - Already approved
   - **N/A** - Old transactions (before feature was implemented)

## File Structure

```
/material_grants.php              - Main grant management page (UI)
/model/material_grants.php        - Backend API for grants
/partials/_sidebar.php            - Updated with role 6 menu
/model/page_config.php            - Updated with role 6 permissions
/model/transactions_download.php  - Updated to include grant status

/sql/material_grants.sql          - Database migration
/sql/add_role_6.sql              - Role 6 setup script

/niverpay_db.sql                 - Updated schema (includes material_grants table)

/MATERIAL_GRANTS_DOCUMENTATION.md - Comprehensive documentation
/MATERIAL_GRANTS_SUMMARY.md       - Implementation summary
/MATERIAL_GRANTS_QUICK_START.md   - This file
```

## Troubleshooting

**Issue: Role 6 admin sees "Unauthorized access"**
- Solution: Ensure the admin's role field is set to `6` in the database
- Check: `SELECT role FROM admins WHERE email = 'admin@example.com'`

**Issue: Grant button doesn't work**
- Solution: Check browser console for JavaScript errors
- Verify: jQuery and DataTables are loaded properly

**Issue: No grants showing in the table**
- Solution: Check if there are any successful material purchases
- Run: `SELECT COUNT(*) FROM material_grants`
- If zero, make a test purchase to populate the table

**Issue: Export doesn't show grant status**
- Solution: Verify you're using the updated `transactions_download.php`
- Check: The SQL query includes the LEFT JOIN to material_grants

## Security Notes

- ✅ Role 6 admins cannot access any other pages
- ✅ API checks role before allowing grant operations
- ✅ SQL injection protection via prepared statements
- ✅ All grant actions are logged in audit trail
- ✅ Session-based authentication required

## Testing Checklist

After installation, verify:
- [ ] Role 6 admin can login
- [ ] Only sees "Material Grants" menu
- [ ] Statistics load correctly (Total, Pending, Granted)
- [ ] Can filter by status (All/Pending/Granted)
- [ ] Can click "Grant" to approve a material
- [ ] Status changes from "Pending" to "Granted"
- [ ] Export includes "Grant Status" column
- [ ] Cannot access other admin pages (redirects to grants)

## Support

For questions or issues:
1. Check `MATERIAL_GRANTS_DOCUMENTATION.md` for detailed information
2. Review `MATERIAL_GRANTS_SUMMARY.md` for implementation details
3. Check audit logs for grant actions: `SELECT * FROM audit_logs WHERE action = 'grant'`

---
**Version**: 1.0  
**Last Updated**: February 19, 2026

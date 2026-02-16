# Course Materials - Close All Open Materials Script

## Overview
This SQL script is designed to close all currently open course materials in the system. It updates the `status` field of all materials from `'open'` to `'closed'` in the `manuals` table.

## Background
As part of the update to remove automatic due date-based closure of course materials, this script provides a way for administrators to manually close all materials that are currently open.

**Previous Behavior:**
- Materials were automatically marked as "closed" when their due date passed (even if the database status was still "open")
- The application used `CASE WHEN m.due_date < NOW() THEN 'closed' ELSE m.status END` logic

**New Behavior:**
- Materials are only considered closed when their database status is explicitly set to 'closed'
- Due dates no longer automatically close materials
- Administrators must manually toggle material status between open and closed

## Usage

### Option 1: Using MySQL Command Line
```bash
mysql -u your_username -p your_database < sql/close_all_open_materials.sql
```

### Option 2: Using phpMyAdmin
1. Open phpMyAdmin
2. Select your database (usually `niverpay_db`)
3. Click on the "SQL" tab
4. Copy and paste the contents of `sql/close_all_open_materials.sql`
5. Click "Go" to execute

### Option 3: Direct SQL Execution
If you prefer to run the query directly without the script file:

```sql
UPDATE manuals 
SET status = 'closed' 
WHERE status = 'open';
```

## What This Script Does

1. **Updates all open materials**: Sets the `status` column to `'closed'` for all records in the `manuals` table where `status = 'open'`
2. **Preserves already closed materials**: Materials that are already closed remain unchanged
3. **Returns count**: Shows how many materials were updated

## Important Notes

⚠️ **Warning**: This operation will close ALL open course materials in your database. Make sure this is what you want before running this script.

### Before Running
- **Backup your database**: Always create a backup before running UPDATE queries
- **Review open materials**: You may want to check which materials will be affected first:
  ```sql
  SELECT id, title, course_code, status, due_date 
  FROM manuals 
  WHERE status = 'open';
  ```

### After Running
- Materials can be re-opened individually through the admin dashboard if needed
- The toggle feature in the UI now allows switching between 'open' and 'closed' status
- Notifications will be sent when materials are closed (if notification system is configured)

## Verification

After running the script, you can verify the results:

```sql
-- Check if any materials are still open
SELECT COUNT(*) AS open_materials FROM manuals WHERE status = 'open';

-- Check total closed materials
SELECT COUNT(*) AS closed_materials FROM manuals WHERE status = 'closed';

-- View all materials and their current status
SELECT id, title, course_code, status, due_date 
FROM manuals 
ORDER BY created_at DESC;
```

## Related Files
- `model/materials.php` - Backend logic for materials (updated to use db_status only)
- `model/functions/materials.js` - Frontend JavaScript (updated to allow toggling closed materials)
- `course_materials.php` - Admin interface for managing materials

## Support
If you encounter any issues or have questions about this script, please contact your system administrator or refer to the main README.md file.

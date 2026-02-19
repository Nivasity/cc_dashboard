# ALTER SQL Scripts for manual_export_audits Table

## Overview
These SQL scripts provide different ways to update an existing `manual_export_audits` table to add grant management functionality.

## Files

### 1. `alter_manual_export_audits_quick.sql` ⚡ RECOMMENDED
**Use this if:** You just want to quickly add the grant columns to your existing table.

**What it does:**
- Adds 4 grant management columns in a single ALTER statement
- Adds index on `grant_status`
- Fast and simple

**Command:**
```bash
mysql -u username -p niverpay_db < sql/alter_manual_export_audits_quick.sql
```

---

### 2. `alter_manual_export_audits_add_grant_columns.sql` 📝 DETAILED
**Use this if:** You want detailed, step-by-step ALTER statements with comments.

**What it does:**
- Adds 4 grant management columns (one ALTER per column)
- Adds index on `grant_status`
- Includes commented optional sections for PRIMARY KEY, UNIQUE constraints, etc.
- Better for understanding what each change does

**Command:**
```bash
mysql -u username -p niverpay_db < sql/alter_manual_export_audits_add_grant_columns.sql
```

---

### 3. `alter_manual_export_audits_complete.sql` 🔧 COMPREHENSIVE
**Use this if:** Your table is missing PRIMARY KEY, UNIQUE constraints, or indexes.

**What it does:**
- Adds all 4 grant management columns (REQUIRED)
- Includes optional sections for:
  - PRIMARY KEY on `id`
  - AUTO_INCREMENT on `id`
  - UNIQUE constraint on `code`
  - Performance indexes on `manual_id` and `hoc_user_id`
- Includes verification queries

**Command:**
```bash
# Review and uncomment sections as needed, then run:
mysql -u username -p niverpay_db < sql/alter_manual_export_audits_complete.sql
```

---

## Columns Added

All scripts add these 4 columns:

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `grant_status` | varchar(20) | 'pending' | Status: 'pending' or 'granted' |
| `granted_by` | int(11) | NULL | Admin ID who granted |
| `granted_at` | datetime | NULL | When it was granted |
| `last_student_id` | int(11) | NULL | For pagination tracking |

Plus an index:
- `idx_manual_export_status` on `grant_status` column

---

## Before Running

### 1. Backup Your Database
```bash
mysqldump -u username -p niverpay_db > backup_$(date +%Y%m%d).sql
```

### 2. Check Current Table Structure
```sql
DESCRIBE manual_export_audits;
SHOW INDEXES FROM manual_export_audits;
```

### 3. Check if Columns Already Exist
```sql
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'niverpay_db' 
  AND TABLE_NAME = 'manual_export_audits'
  AND COLUMN_NAME IN ('grant_status', 'granted_by', 'granted_at', 'last_student_id');
```

If this returns any rows, those columns already exist!

---

## After Running

### Verify Changes
```sql
-- Check table structure
DESCRIBE manual_export_audits;

-- Check indexes
SHOW INDEXES FROM manual_export_audits;

-- Check existing data (should show new columns with NULL/default values)
SELECT * FROM manual_export_audits LIMIT 5;
```

### Expected Result
Your table should now have these columns:
- id
- code
- manual_id
- hoc_user_id
- students_count
- total_amount
- downloaded_at
- **grant_status** ← NEW
- **granted_by** ← NEW
- **granted_at** ← NEW
- **last_student_id** ← NEW

---

## Troubleshooting

### Error: Column already exists
**Solution:** The column was already added. Skip that ALTER statement or use the complete script which has optional sections.

### Error: Duplicate key name
**Solution:** The index already exists. Skip the index creation.

### Error: Table doesn't exist
**Solution:** The table hasn't been created yet. Use `add_manual_export_audits_table.sql` instead.

---

## Which Script Should I Use?

**Quick Decision Tree:**

```
Do you have an existing manual_export_audits table?
├─ NO  → Use: add_manual_export_audits_table.sql (creates fresh table)
└─ YES → Continue...
    
    Does your table have grant_status, granted_by, etc. columns?
    ├─ YES → Already done! No ALTER needed.
    └─ NO  → Continue...
        
        Do you want the quickest solution?
        ├─ YES → Use: alter_manual_export_audits_quick.sql ⚡
        └─ NO  → Continue...
            
            Does your table have PRIMARY KEY and proper indexes?
            ├─ YES → Use: alter_manual_export_audits_add_grant_columns.sql 📝
            └─ NO  → Use: alter_manual_export_audits_complete.sql 🔧
```

---

## Example Usage

### Scenario 1: Fresh Installation
You already have the basic table and just want to add grant columns:

```bash
# Backup first
mysqldump -u root -p niverpay_db > backup.sql

# Run quick ALTER
mysql -u root -p niverpay_db < sql/alter_manual_export_audits_quick.sql

# Verify
mysql -u root -p niverpay_db -e "DESCRIBE manual_export_audits"
```

### Scenario 2: Need to Review Each Change
```bash
# Backup first
mysqldump -u root -p niverpay_db > backup.sql

# Run detailed ALTER
mysql -u root -p niverpay_db < sql/alter_manual_export_audits_add_grant_columns.sql

# Verify
mysql -u root -p niverpay_db -e "SHOW CREATE TABLE manual_export_audits\G"
```

### Scenario 3: Table Needs Full Upgrade
```bash
# Backup first
mysqldump -u root -p niverpay_db > backup.sql

# Edit the complete script and uncomment needed sections
nano sql/alter_manual_export_audits_complete.sql

# Run complete ALTER
mysql -u root -p niverpay_db < sql/alter_manual_export_audits_complete.sql

# Verify
mysql -u root -p niverpay_db -e "SHOW CREATE TABLE manual_export_audits\G"
```

---

## Related Files

- `add_manual_export_audits_table.sql` - Creates table from scratch (for new installations)
- `sample_manual_export_audits_data.sql` - Sample data for testing
- `add_admin_role_6_grant_manager.sql` - Adds the Grant Manager role

---

## Support

For issues or questions:
1. Check the main documentation: `GRANT_MANAGEMENT_README.md`
2. Review security guidelines: `SECURITY_REVIEW.md`
3. Check integration guide: `STUDENT_APP_INTEGRATION.md`

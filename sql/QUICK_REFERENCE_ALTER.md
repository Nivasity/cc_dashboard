# Quick Reference: ALTER Scripts for manual_export_audits

## 📌 Quick Commands

### Recommended (Quick ALTER)
```bash
mysql -u username -p niverpay_db < sql/alter_manual_export_audits_quick.sql
```

### Detailed (Step-by-step)
```bash
mysql -u username -p niverpay_db < sql/alter_manual_export_audits_add_grant_columns.sql
```

### Complete (Full upgrade)
```bash
mysql -u username -p niverpay_db < sql/alter_manual_export_audits_complete.sql
```

---

## 📋 Pre-Flight Checklist

- [ ] Backup database: `mysqldump -u username -p niverpay_db > backup.sql`
- [ ] Verify table exists: `SHOW TABLES LIKE 'manual_export_audits';`
- [ ] Check current columns: `DESCRIBE manual_export_audits;`
- [ ] Check if columns already exist: See below ⬇️

### Check if ALTER is needed
```sql
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'niverpay_db' 
  AND TABLE_NAME = 'manual_export_audits'
  AND COLUMN_NAME IN ('grant_status', 'granted_by', 'granted_at', 'last_student_id');
```
If this returns rows, the columns already exist!

---

## ✅ Post-ALTER Verification

```sql
-- Check table structure
DESCRIBE manual_export_audits;

-- Check indexes
SHOW INDEXES FROM manual_export_audits;

-- View data
SELECT * FROM manual_export_audits LIMIT 5;
```

Expected columns after ALTER:
- ✅ grant_status
- ✅ granted_by
- ✅ granted_at
- ✅ last_student_id

---

## 🎯 Which Script to Use?

| Scenario | Script |
|----------|--------|
| Just want to add grant columns quickly | ⚡ `alter_manual_export_audits_quick.sql` |
| Want to understand each change | 📝 `alter_manual_export_audits_add_grant_columns.sql` |
| Need to add keys/indexes too | 🔧 `alter_manual_export_audits_complete.sql` |
| Table doesn't exist yet | Use `add_manual_export_audits_table.sql` instead |

---

## 🆘 Troubleshooting

| Error | Solution |
|-------|----------|
| Column already exists | Skip that column or use complete script |
| Duplicate key name | Skip the index creation |
| Table doesn't exist | Use CREATE script, not ALTER |
| Access denied | Check MySQL user permissions |

---

## 📚 Full Documentation

See `sql/README_ALTER_SCRIPTS.md` for complete details.

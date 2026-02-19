# Material Grants Feature Documentation

## Overview
The Material Grants feature enables a dedicated admin role (Role 6) to manage and approve material grants for students who have purchased course materials. This feature tracks all bought materials and provides an interface for grant approval.

## Database Changes

### New Table: `material_grants`
Tracks the grant status of all purchased materials.

**Columns:**
- `id` (INT) - Primary key
- `manual_bought_ref_id` (VARCHAR 50) - Reference to the transaction
- `manual_id` (INT) - Reference to the material
- `buyer_id` (INT) - Student who purchased the material
- `seller_id` (INT) - HOC/seller who posted the material
- `school_id` (INT) - School ID
- `admin_id` (INT) - Admin who granted (NULL if pending)
- `status` (ENUM) - 'pending' or 'granted'
- `last_student_id` (INT) - Tracks last student granted for batch tracking
- `granted_at` (DATETIME) - Timestamp when granted
- `created_at` (DATETIME) - Record creation timestamp

**Migration:**
Run the SQL file: `/sql/material_grants.sql` to create the table and populate existing data.

## Admin Role 6 - Material Grant Manager

### Permissions
Admin Role 6 has **restricted access** - they can only view and manage the Material Grants page.

**Access:**
- ✅ Material Grants Management page (`/material_grants.php`)
- ❌ All other admin pages (Dashboard, Students, Transactions, etc.)

### Features Available

#### 1. Grant Management Dashboard
- View statistics: Total materials, Pending grants, Granted materials
- Filter by status: All, Pending, Granted
- View detailed list of all material purchases

#### 2. Material Information Displayed
- Reference ID
- Material title and course code
- Student name and matric number
- School and department
- Price
- Status (Pending/Granted)
- Purchase date
- Action button (Grant)

#### 3. Grant Action
- Click "Grant" button to approve a material
- Updates status from "pending" to "granted"
- Records the admin who granted
- Records the grant timestamp
- Tracks last student ID for batch tracking

## Auto-Population

The `material_grants` table is automatically populated when students purchase materials through:

1. **Direct Transactions** (`model/transactions.php`)
2. **Webhook Batch Payments** (`model/flw_webhook_batch.php`)
3. **Manual Batch Payment Verification** (`model/verify_payment_batch.php`)

Each successful material purchase creates a grant record with status "pending".

## Export Integration

### Enhanced Transaction Export
The transaction export (`model/transactions_download.php`) now includes a "Grant Status" column.

**Export Columns:**
- Ref Id
- Student Name
- Matric No
- Admission Year
- School
- Faculty/College
- Department
- Materials
- Total Paid
- Date
- Time
- Status
- **Grant Status** (NEW) - Shows: pending, granted, or N/A

This allows HOCs to see which students have been granted materials when exporting transaction data.

## Usage Workflow

### For Role 6 Admins:
1. Log in with Role 6 credentials
2. Navigate to "Material Grants" → "Grant Management"
3. View pending material purchases
4. Review student and material details
5. Click "Grant" to approve materials
6. Use filters to view granted vs pending materials

### For HOCs (Exporting Data):
1. Navigate to Course Materials page
2. Select material to export
3. Click "Download CSV"
4. CSV will include "Grant Status" column showing which students have been granted

## Security & Access Control

- Role 6 admins cannot access any other pages
- Grant actions are logged in the audit trail
- Only "pending" grants can be approved
- Grant records cannot be deleted (only status changes)

## Technical Implementation

### Files Modified/Created:

**Database:**
- `sql/material_grants.sql` - Migration script
- `niverpay_db.sql` - Updated schema

**Backend:**
- `model/material_grants.php` - Grant operations API
- `model/page_config.php` - Added Role 6 permissions
- `model/transactions.php` - Auto-populate grants
- `model/flw_webhook_batch.php` - Auto-populate grants
- `model/verify_payment_batch.php` - Auto-populate grants
- `model/transactions_download.php` - Include grant status in exports

**Frontend:**
- `material_grants.php` - Grant management interface
- `partials/_sidebar.php` - Added menu for Role 6

## Future Enhancements

Potential improvements:
- Bulk grant action (grant multiple materials at once)
- Grant filtering by school/department/date range
- Grant notifications to students
- Grant statistics and reporting
- Revoke grant functionality

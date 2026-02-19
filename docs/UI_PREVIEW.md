# Material Grants Page - UI Preview

## Page Layout

```
┌─────────────────────────────────────────────────────────────────────┐
│ Grant Management / Material Grants                                  │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│ Downloaded Materials                            [Filter: Pending ▼] │
│ Manage and grant downloaded material exports                        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Export Code │ Material      │ HOC Name │ Students │ Status  │  │
│  ├─────────────┼───────────────┼──────────┼──────────┼─────────┤  │
│  │ GXCZEFPJVY  │ CSC101        │ John Doe │ 1        │ Pending │  │
│  │             │ (Intro CS)    │          │ ₦600     │ [Grant] │  │
│  ├─────────────┼───────────────┼──────────┼──────────┼─────────┤  │
│  │ ABCDEFGHIJ  │ CSC101        │ John Doe │ 25       │ Pending │  │
│  │             │ (Intro CS)    │          │ ₦15,000  │ [Grant] │  │
│  ├─────────────┼───────────────┼──────────┼──────────┼─────────┤  │
│  │ KLMNOPQRST  │ CSC101        │ John Doe │ 50       │ Granted │  │
│  │             │ (Intro CS)    │          │ ₦30,000  │ 11/15   │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

## Sidebar Menu (Role 6 Only)

```
┌────────────────────────┐
│  GRANT MANAGEMENT      │
├────────────────────────┤
│  ✓ Material Grants     │ <- Active/Only visible page
├────────────────────────┤
│                        │
│  Sign Out              │
└────────────────────────┘
```

## Grant Confirmation Modal

```
┌──────────────────────────────────┐
│ Confirm Grant               [×]  │
├──────────────────────────────────┤
│                                  │
│ Are you sure you want to grant   │
│ this material export?            │
│                                  │
│ ┌──────────────────────────────┐ │
│ │ Export Code: GXCZEFPJVY      │ │
│ └──────────────────────────────┘ │
│                                  │
│         [Cancel]  [Grant]        │
└──────────────────────────────────┘
```

## Key Features

### 1. Status Filter Dropdown
- All Status
- Pending (default)
- Granted

### 2. Table Columns
- Export Code (unique identifier)
- Material (Title and Course Code)
- HOC Name (who downloaded)
- Students Count
- Total Amount (₦)
- Downloaded At (date/time)
- Status (badge: Pending/Granted)
- Actions (Grant button or "Granted" text)

### 3. Grant Button
- Only visible for "Pending" items
- Opens confirmation modal
- Updates status to "Granted"
- Records admin ID and timestamp

### 4. DataTables Features
- Search across all columns
- Sort by any column
- Pagination for large datasets
- Responsive design

## User Workflow

1. Grant Manager (Role 6) logs in
2. Automatically redirected to Material Grants page
3. Sees list of all material downloads by HOCs
4. Can filter by status (Pending/Granted/All)
5. Clicks "Grant" button for pending item
6. Confirms in modal
7. Item status changes to "Granted"
8. Toast notification shows success
9. Table refreshes to show updated status

## Color Scheme

- Pending Badge: Yellow/Warning (⚠️)
- Granted Badge: Green/Success (✅)
- Grant Button: Blue/Primary
- Table Headers: Light Gray/Secondary
- Active Menu: Purple highlight

## Responsive Behavior

- Desktop: Full table with all columns
- Tablet: Scrollable table
- Mobile: Stacked cards (DataTables responsive)

## Security Features

✅ Role-based access (only Role 6)
✅ Session validation
✅ SQL injection prevention
✅ XSS protection
✅ Audit logging

## Integration Points

### Admin Dashboard (Implemented)
- ✅ Database table created
- ✅ Role 6 permissions configured
- ✅ Grant management page
- ✅ API endpoints

### Student Application (To Be Implemented)
- ⏳ Create export records when HOC downloads
- ⏳ Display grant status to HOCs
- ⏳ Mark granted items in exports

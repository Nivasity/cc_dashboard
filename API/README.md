# API Endpoints

## Auth

Set `API_BEARER_TOKEN` inside `config/db.php` (copy from `config/db.example.php` if needed).
Set `API_ADMIN_ID` (an existing `admins.id`) inside `config/db.php` for API-triggered support/user notifications.

Send the token in the `Authorization` header:

```text
Authorization: Bearer <your_token>
```

## Reference Endpoint

```text
GET /API/reference
GET /API/reference?table=<table_name>
```

If `table` is omitted, the endpoint returns all allowed table names from `API/niverpay_db.sql`.

### Query Parameters

- `table`: table name to query (required to fetch rows)
- `columns`: comma-separated list of columns (default `*`)
- `filter[field]=value`: equals
- `filter_ne[field]=value`: not equals
- `filter_gt[field]=value`: greater than
- `filter_gte[field]=value`: greater than or equal
- `filter_lt[field]=value`: less than
- `filter_lte[field]=value`: less than or equal
- `filter_like[field]=value`: contains search
- `filter_in[field]=a,b,c`: IN filter
- `sort`: comma list, prefix column with `-` for descending (example: `sort=-id,created_at`)
- `limit`: rows per page, min `1`, max `500` (default `100`)
- `offset`: start offset (default `0`)

### Reference Response Format

All `GET /API/reference?table=<table_name>` responses follow:

```json
{
  "success": true,
  "table": "users",
  "meta": {
    "total": 120,
    "count": 25,
    "limit": 25,
    "offset": 0
  },
  "data": []
}
```

### Accessible Reference Tables

Source of truth: `API/niverpay_db.sql` (`CREATE TABLE` list used by the endpoint).

- `audit_logs`: `id`, `admin_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `user_agent`, `created_at`
- `cart`: `id`, `ref_id`, `user_id`, `item_id`, `type`, `status`, `gateway`, `created_at`
- `depts`: `id`, `name`, `faculty_id`, `school_id`, `status`, `created_at`
- `event_tickets`: `id`, `event_id`, `price`, `seller`, `buyer`, `ref_id`, `status`, `created_at`
- `events`: `id`, `title`, `description`, `event_type`, `school`, `event_link`, `location`, `event_banner`, `price`, `code`, `event_date`, `event_time`, `quantity`, `user_id`, `currency`, `status`, `created_at`
- `faculties`: `id`, `name`, `school_id`, `status`, `created_at`
- `fund_requests`: `id`, `full_name`, `email`, `phone`, `organization`, `amount_requested`, `purpose`, `additional_details`, `status`, `created_at`
- `manual_export_audits`: `id`, `code`, `manual_id`, `hoc_user_id`, `students_count`, `total_amount`, `from_bought_id`, `to_bought_id`, `downloaded_at`, `grant_status`, `granted_by`, `granted_at`, `last_student_id`, `bought_ids_json`
- `manuals`: `id`, `title`, `course_code`, `price`, `code`, `due_date`, `quantity`, `dept`, `depts`, `coverage`, `faculty`, `host_faculty`, `level`, `user_id`, `admin_id`, `currency`, `school_id`, `status`, `created_at`
- `manuals_bought`: `id`, `manual_id`, `price`, `seller`, `buyer`, `school_id`, `ref_id`, `status`, `created_at`, `grant_status`, `export_id`
- `mobile_experience_feedback`: `id`, `user_id`, `device_choice`, `comfort_level`, `comfort_label`, `source_page`, `user_agent`, `created_at`
- `notifications`: `id`, `user_id`, `title`, `body`, `type`, `data`, `created_at`, `read_at`
- `organisation`: `id`, `business_name`, `business_address`, `web_url`, `work_email`, `socials`, `user_id`, `created_at`
- `quick_login_codes`: `id`, `student_id`, `code`, `expiry_datetime`, `status`, `created_by`, `created_at`
- `schools`: `id`, `name`, `code`, `status`, `created_at`
- `settlement_accounts`: `id`, `school_id`, `user_id`, `acct_name`, `acct_number`, `bank`, `flw_id`, `subaccount_code`, `gateway`, `type`, `currency`, `status`, `created_at`
- `support_contacts`: `id`, `whatsapp`, `email`, `phone`, `status`, `created_at`, `updated_at`
- `support_ticket_attachments`: `id`, `message_id`, `file_path`, `file_name`, `mime_type`, `file_size`, `created_at`
- `support_ticket_messages`: `id`, `ticket_id`, `sender_type`, `user_id`, `admin_id`, `body`, `is_internal`, `created_at`
- `support_tickets_legacy`: `id`, `code`, `subject`, `user_id`, `message`, `status`, `response`, `response_time`, `created_at`
- `support_tickets_v2`: `id`, `code`, `subject`, `user_id`, `status`, `priority`, `category`, `assigned_admin_id`, `last_message_at`, `closed_at`, `created_at`, `updated_at`
- `system_alerts`: `id`, `title`, `message`, `expiry_date`, `active`, `created_at`
- `transactions`: `id`, `ref_id`, `user_id`, `batch_id`, `amount`, `charge`, `profit`, `status`, `medium`, `created_at`
- `users`: `id`, `first_name`, `last_name`, `email`, `phone`, `gender`, `school`, `dept`, `matric_no`, `role`, `password`, `status`, `adm_year`, `profile_pic`, `last_login`
- `verification_code`: `user_id`, `code`, `exp_date`

### Table Query Format (Any Accessible Table)

```text
GET /API/reference?table=<table_name>&columns=<col1,col2>&filter[field]=value&sort=-id&limit=25&offset=0
```

### Example Requests

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" "http://localhost/cc_dashboard/API/reference"
```

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" "http://localhost/cc_dashboard/API/reference?table=users&filter[status]=active&sort=-id&limit=25&offset=0"
```

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" "http://localhost/cc_dashboard/API/reference?table=transactions&filter_gte[amount]=1000&filter_like[status]=success"
```

## Users Endpoint

Manage records from the `users` table in `API/niverpay_db.sql`.

```text
GET    /API/users
GET    /API/users?id=1
PATCH  /API/users?id=1
```

### Users List Query Parameters

- `columns`: comma-separated list of columns to return (`password` is blocked)
- `filter[field]=value`: equals
- `filter_ne[field]=value`: not equals
- `filter_gt[field]=value`: greater than
- `filter_gte[field]=value`: greater than or equal
- `filter_lt[field]=value`: less than
- `filter_lte[field]=value`: less than or equal
- `filter_like[field]=value`: contains search
- `filter_in[field]=a,b,c`: IN filter
- `sort`: comma list, prefix with `-` for descending
- `limit`: rows per page, min `1`, max `500` (default `100`)
- `offset`: start offset (default `0`)

### Users Update Payload

`PATCH /API/users?id=1` accepts any subset of:
`first_name`, `last_name`, `email`, `phone`, `gender`, `school`, `dept`, `matric_no`, `role`, `status`, `adm_year`, `profile_pic`.

`password` updates are not allowed on this endpoint.
On successful update, the API also triggers:
- `sendMail(...)` from `model/mail.php`
- `notifyStudentProfileUpdate(...)` from `model/notification_helpers.php`
- `log_audit_event(...)` from `model/functions.php` with:
  `{"after": {...}, "former": {...}}`

### Users Examples

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" "http://localhost/cc_dashboard/API/users?filter[status]=verified&sort=-id&limit=20"
```

```bash
curl -X PATCH -H "Authorization: Bearer YOUR_TOKEN" -H "Content-Type: application/json" ^
  -d "{\"status\":\"verified\"}" ^
  "http://localhost/cc_dashboard/API/users?id=1"
```

## Verification Code Endpoint

Send an email verification code to a user and persist it in `verification_code` table from `API/niverpay_db.sql`.

```text
POST /API/verification_code
```

### Verification Request Payload

`POST /API/verification_code` body (`application/json` or form-data):

```json
{
  "email": "student@example.com"
}
```

### Verification Behavior

- Validates the user exists in `users` by email.
- Generates a unique code using `generateVerificationCode()` + `isCodeUnique()` from `model/functions.php`.
- Inserts the code into `verification_code` with a 24-hour `exp_date`.
- Sends verification email via `sendMail()` from `model/mail.php`.

### Verification Example

```bash
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" -H "Content-Type: application/json" ^
  -d "{\"email\":\"student@example.com\"}" ^
  "http://localhost/cc_dashboard/API/verification_code"
```

## Support Tickets Endpoint

Manage `support_tickets_v2` / `support_ticket_messages` using `API/niverpay_db.sql` schema.

```text
GET    /API/support_tickets
GET    /API/support_tickets?id=1
GET    /API/support_tickets?code=ABC12345
POST   /API/support_tickets
PATCH  /API/support_tickets
```

### Support List Query Parameters

- `status`: ticket status filter
- `user_id`: filter by ticket owner
- `sort`: one of `id`, `created_at`, `last_message_at`, `status`, `priority` (prefix with `-` for descending)
- `limit`: rows per page (default `50`, max `500`)
- `offset`: start offset

### Create Ticket

`POST /API/support_tickets` body (`application/json`):

```json
{
  "user_id": 10,
  "subject": "Unable to access material",
  "message": "I paid but cannot open the material.",
  "category": "payment",
  "priority": "medium"
}
```

### Respond / Close Ticket

`PATCH /API/support_tickets` supports two actions:

- Respond as admin:

```json
{
  "action": "respond",
  "code": "ABC12345",
  "response": "We have fixed this issue. Please retry.",
  "close_ticket": false
}
```

- Close ticket (with optional final message):

```json
{
  "action": "close",
  "id": 21,
  "response": "Resolved and closed."
}
```

For support updates (respond/close), the API also triggers:
- `sendMail(...)` from `model/mail.php`
- `notifySupportTicketResponse(...)` / `notifySupportTicketClosed(...)` from `model/notification_helpers.php`
- `log_audit_event(...)` from `model/functions.php` with:
  `{"after": {...}, "former": {...}}`

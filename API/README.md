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

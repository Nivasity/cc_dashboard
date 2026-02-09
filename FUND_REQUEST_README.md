# Nivasity Management Fund Request Form

## Overview
A publicly accessible web form for submitting fund requests to the Nivasity Management Fund. When submitted, the form saves the request to the database and sends email notifications to the finance team.

## Access
The form is accessible at: `/fund_request.html`

No authentication is required - the form is publicly accessible.

## Features
- **User-friendly interface** - Clean, responsive form design matching Nivasity branding
- **Real-time validation** - Client-side and server-side validation for all inputs
- **Email notifications** - Automatic email sent to finance team upon submission
- **Database storage** - All requests are saved for tracking and follow-up
- **Security** - Protected against SQL injection and XSS attacks

## Email Recipients
When a fund request is submitted:
- **Primary recipient**: finance@nivasity.com
- **CC recipients**: 
  - samuel@nivasity.com
  - samuel.cf@nivasity.com
  - blessing.cf@nivasity.com
- **Reply-to**: Set to the applicant's email for easy follow-up

## Form Fields

### Required Fields
1. **Full Name** - Applicant's complete name
2. **Email** - Valid email address for correspondence
3. **Phone Number** - Contact number
4. **Amount Requested** - Funding amount in Nigerian Naira (NGN)
5. **Purpose** - Detailed description of the fund request purpose

### Optional Fields
1. **Organization/Institution** - Applicant's organization (if applicable)
2. **Additional Details** - Any supplementary information

## Database Schema
The form data is stored in the `fund_requests` table with the following structure:

```sql
CREATE TABLE `fund_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `amount_requested` decimal(15,2) NOT NULL,
  `purpose` text NOT NULL,
  `additional_details` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
);
```

## Setup Instructions

### 1. Database Setup
Run the SQL migration to create the required table:
```bash
mysql -u username -p niverpay_db < sql/fund_requests.sql
```

### 2. Email Configuration
Ensure BREVO email service is configured:
- Create `config/brevo.php` from `config/brevo.example.php`
- Add your BREVO API key
- See `config/README.md` for detailed instructions

### 3. Web Server Configuration
Ensure the web server can serve static HTML files and has PHP enabled for the handler script.

## Technical Details

### Files
- **fund_request.html** - Public-facing form page
- **model/fund_request_handler.php** - Backend processing script
- **sql/fund_requests.sql** - Database schema migration

### Security Measures
1. **Input validation** - All inputs validated both client-side and server-side
2. **SQL injection protection** - Uses prepared statements with parameterized queries
3. **XSS prevention** - All user input is HTML-escaped before display
4. **Email validation** - Ensures valid email format
5. **Amount validation** - Enforces positive numbers only
6. **POST-only endpoint** - Handler only accepts POST requests
7. **Error message sanitization** - Generic error messages to clients, detailed logs server-side

### Email Template
Emails are sent using the existing BREVO integration with Nivasity's branded email template. The email includes:
- Request ID for tracking
- Complete form data in a formatted table
- Applicant contact information
- Submission timestamp
- Reply-to header set to applicant's email

## Usage Flow
1. User accesses `/fund_request.html`
2. User fills out the form with required information
3. Form validates inputs client-side
4. User submits the form
5. AJAX request sent to `model/fund_request_handler.php`
6. Server validates and sanitizes all inputs
7. Request saved to database with "pending" status
8. Email notification sent to finance team
9. Success message displayed to user

## Troubleshooting

### Form doesn't submit
- Check browser console for JavaScript errors
- Verify all required fields are filled
- Ensure email format is valid

### Email not received
- Verify BREVO API key is configured in `config/brevo.php`
- Check server error logs for email sending issues
- Confirm BREVO account has sufficient credits

### Database errors
- Ensure `fund_requests` table exists
- Verify database connection in `config/db.php`
- Check file permissions for `model/fund_request_handler.php`

## Maintenance

### Viewing Requests
Fund requests are stored in the `fund_requests` table and can be accessed via SQL queries:
```sql
SELECT * FROM fund_requests ORDER BY created_at DESC;
```

### Updating Request Status
```sql
UPDATE fund_requests SET status = 'approved' WHERE id = ?;
UPDATE fund_requests SET status = 'rejected' WHERE id = ?;
```

## Support
For technical issues or questions, contact the development team or email support@nivasity.com.

# Email

Dorguzen sends all email through `DGZ_Messenger` — a wrapper around PHPMailer that handles SMTP configuration, template rendering, and delivery.

---

## SMTP Configuration

```ini
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourapp.com
MAIL_FROM_NAME="Your App Name"
```

### MailTrap for local development

Sign in to your [Mailtrap](https://mailtrap.io) account, go to **Email Testing → Inboxes → SMTP Settings**, and copy your credentials.

```ini
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourapp.com
MAIL_FROM_NAME="Your App Name"
```

> **Also set `APP_EMAIL`** — this is the admin recipient address that `DGZ_Messenger` sends notifications *to*. If left empty, PHPMailer has no To: address and all admin emails fail silently.
>
> ```ini
> APP_EMAIL=admin@yourapp.com
> ```
>
> Mailtrap routes mail by SMTP credentials alone — any value in `APP_EMAIL`, `MAIL_FROM_ADDRESS`, or `MAIL_FROM_NAME` is accepted. Make sure all three are set to real (non-empty) values or you will see failures logged as *"Email failed to send from: …"* with no further error.

---

## Sending Email

```php
$messenger = new DGZ_Messenger();
```

### Generic method — `sendEmail()`

For any email your app needs to send that isn't covered by the built-in methods, use `sendEmail()`. You never need to add methods to `DGZ_Messenger` or touch core code — create a template in `views/emails/` and call `sendEmail()` from your service or controller.

```php
// Templated HTML email (most common)
$messenger->sendEmail(
    toEmail:     config('appEmail'),          // recipient
    toName:      'Admin',
    subject:     'New booking from ' . $name,
    replyTo:     $visitorEmail,               // optional reply-to
    replyToName: $visitorName,
    data:        [
        'heading'  => 'Booking Details',
        'name'     => $name,
        'date'     => $startDate,
    ],
    template: 'booking-confirmation',         // views/emails/booking-confirmation.php
);

// Plain-text email (no template)
$messenger->sendEmail(
    toEmail:  'user@example.com',
    toName:   'Jane',
    subject:  'Quick note',
    body:     'Your request has been received.',
);
```

When `$template` is provided the email is rendered via `renderEmail()` and sent as HTML. When omitted, `$body` is sent as plain text.

### Built-in methods

These cover standard framework flows — contact forms, auth emails, newsletters, error alerts. You do not need to call these for custom app emails; use `sendEmail()` instead.

| Method | What it sends |
|---|---|
| `sendContactFormMsgToAdmin($name, $email, $phone, $message)` | Contact form to admin |
| `sendEmailActivationEmail($name, $email, $subject, $message)` | Account activation link |
| `sendWelcomeEmail($name, $email, $subject, $message)` | Welcome email after registration |
| `sendPasswordResetEmail($email, $firstname, $resetCode)` | Password reset link |
| `sendErrorLogMsgToAdmin($message)` | Error alert to admin |
| `sendNewsletterWelcomeMsg(...)` | First newsletter to new subscriber |
| `sendNewsletterMsg(...)` | Regular newsletter to existing subscriber |
| `sendHtml($toEmail, $toName, $subject, $htmlBody)` | Pre-rendered HTML body (no template rendering) |

All methods return `true` on success, `false` on failure. Failures are logged automatically.

---

## Email Templates

`DGZ_Messenger` uses a two-level template system: a **content view** wrapped in a **layout**.

### View resolution (first match wins)

1. `views/emails/{view}.php` — your application override
2. `core/email-views/{view}.php` — framework default

### Framework default views

| View | Used by |
|---|---|
| `contact-form` | `sendContactFormMsgToAdmin()` |
| `member-email` | `sendEmailActivationEmail()`, `sendWelcomeEmail()` |
| `password-reset` | `sendPasswordResetEmail()` |
| `error-log` | `sendErrorLogMsgToAdmin()` |
| `newsletter-welcome` | `sendNewsletterWelcomeMsg()` |
| `newsletter` | `sendNewsletterMsg()` |

### Overriding a template

Create a file in `views/emails/` with the same name:

```
views/emails/member-email.php     ← overrides core/email-views/member-email.php
views/emails/password-reset.php   ← overrides core/email-views/password-reset.php
```

### Variables available in all templates

| Variable | Source |
|---|---|
| `$appName` | `configs/app.php` |
| `$appBusinessName` | `configs/app.php` |
| `$appURL` | `configs/app.php` |
| `$appYear` | `date('Y')` |
| `$accentColour` | App colour theme from config |

Caller-supplied keys (e.g. `$name`, `$resetUrl`) are also available and take precedence.

---

## Bulk Newsletter Email

Newsletter emails are not sent directly from web requests. They are queued in `pending_emails` and processed in batches by `SendPendingEmailsJob`, which runs every minute via the scheduler:

```php
// src/CLI/console/Schedule.php
$schedule->job(\Dorguzen\Jobs\SendPendingEmailsJob::class)
         ->everyMinute()
         ->withoutOverlapping();
```

The job processes up to 50 pending rows per run and marks each `'sent'` or `'failed'`.

---

## Error Handling

All send methods catch `PHPMailer\Exception` internally, log the failure, and return `false`. They never throw to the calling code:

```php
$sent = $messenger->sendPasswordResetEmail($email, $name, $token);
if (!$sent) {
    // failure is already logged — handle gracefully in the UI
}
```

Failures are written to the `logs` DB table with the method name and PHPMailer error info. Check there first when debugging delivery issues.

---

## Testing & Debugging SMTP

If emails are not arriving, use this standalone script to verify that your SMTP credentials and connection are working independently of the framework. Save it temporarily outside the web root, run it once, then delete it.

```php
<?php
// Temporary debug script — delete after use, never commit
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->SMTPDebug  = 2;                          // print full SMTP conversation
    $mail->Host       = 'sandbox.smtp.mailtrap.io'; // from .env MAIL_HOST
    $mail->SMTPAuth   = true;
    $mail->Username   = 'your-mailtrap-username';   // from .env MAIL_USERNAME
    $mail->Password   = 'your-mailtrap-password';   // from .env MAIL_PASSWORD
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('noreply@yourapp.com', 'Your App');
    $mail->addAddress('admin@yourapp.com', 'Admin');
    $mail->isHTML(false);
    $mail->Subject = 'SMTP test';
    $mail->Body    = 'If you see this, SMTP is working.';
    $mail->send();
    echo "Email sent successfully.\n";
} catch (Exception $e) {
    echo "Failed: {$mail->ErrorInfo}\n";
}
```

`SMTPDebug = 2` prints the full SMTP handshake so you can see exactly where the connection fails. A response ending with `250 2.0.0 Ok: queued` confirms the mail server accepted the message.

**Common causes of silent failures:**

| Symptom | Likely cause |
|---|---|
| `logs` table shows *"Email failed to send"* with no detail | `APP_EMAIL` is empty — PHPMailer has no To: address |
| Debug script works but app emails don't arrive | `APP_EMAIL` or `MAIL_USERNAME`/`MAIL_PASSWORD` not set in `.env` |
| `Connection refused` or timeout | Wrong `MAIL_HOST` / `MAIL_PORT` |
| `SMTP Error: Could not authenticate` | Wrong `MAIL_USERNAME` / `MAIL_PASSWORD` |

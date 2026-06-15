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

```ini
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=<mailtrap-user>
MAIL_PASSWORD=<mailtrap-pass>
MAIL_ENCRYPTION=tls
```

---

## Sending Email

```php
$messenger = new DGZ_Messenger();
```

### Built-in Send Methods

| Method | What it sends |
|---|---|
| `sendContactFormMsgToAdmin($name, $email, $phone, $message)` | Contact form to admin |
| `sendEmailActivationEmail($name, $email, $subject, $message)` | Account activation link |
| `sendWelcomeEmail($name, $email, $subject, $message)` | Welcome email after registration |
| `sendPasswordResetEmail($email, $firstname, $resetCode)` | Password reset link |
| `sendErrorLogMsgToAdmin($message)` | Error alert to admin |
| `sendNewsletterWelcomeMsg(...)` | First newsletter to new subscriber |
| `sendNewsletterMsg(...)` | Regular newsletter to existing subscriber |
| `sendHtml($toEmail, $toName, $subject, $htmlBody)` | Pre-rendered HTML body |

All methods return `true` on success, `false` on failure. Failures are logged automatically.

### Examples

```php
// Welcome email
$messenger->sendWelcomeEmail(
    $user->users_firstname,
    $user->users_email,
    'Welcome to ' . $appName,
    'Click the link below to get started...',
);

// Pre-rendered HTML
$messenger->sendHtml(
    toEmail:  $recipient,
    toName:   $recipientName,
    subject:  'Your custom subject',
    htmlBody: $renderedHtml,
);
```

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

# Email & Newsletter

## Sending Emails in Dorguzen (DGZ_Messenger)

- Overview
- Testing Emails locally with Mail Trap
- 1. SMTP Configuration (.env)
  - For local testing with MailTrap
  - ⚠️ Before deploying to production — switching to your live mail provider
- 2. Public Send Methods
- 3. Rendering Emails (renderEmail)
- 4. File Locations
- 5. Customising Email Templates

---

### Overview

DGZ ships with a built-in email class, DGZ_Messenger (`core/DGZ_Messenger.php`), that wraps PHPMailer. It provides ready-made methods for every transactional email a typical web app needs — contact forms, account activation, welcome messages, password reset, newsletter sends, and admin error alerts.

All SMTP credentials live in `.env`, so you can switch between your production mail provider and a local mail-catcher (e.g. MailTrap) without touching any code.

---

### Testing Emails locally with Mail Trap

Email testing locally is usually a challenge because email service providers often block out emails from untrusted servers, which applies to any web applications you may have running on a local server on your machine (localhost). To bridge this problem and make developers focus on the email design and the working of your application, Dorguzen makes it easy for you to test emails locally with a mail-catcher service like Mail Trap. Here are the steps to test your application email sending locally:

1. Go to https://mailtrap.io and create a free account
2. In the dashboard go to Email Testing → Inboxes → click your inbox → SMTP Settings
3. Select PHPMailer from the integration dropdown — it shows the exact values you need
4. You'll get something like:

```
Host:       sandbox.smtp.mailtrap.io
Port:       587
Username:   <random string>
Password:   <random string>
```

It might not be straight forward once you log in and visit the dashboard. This is because they might change the look of the dashboard or change how any of those links might be accessed. Here is the approach in a nutshell. Basically, when it comes to testing local emails on Mail Trap, you need to do that from what they refer to as a sandbox. So, once logged in, the first thing you need to do is to look on the sidebar for a link to Sandboxes. Click on it, and if you already had a sandbox, you will see in the middle section your sandboxes listed under a heading "My Inboxes". You will see nothing if you did not have one already, so click on the button on the top-right that says "Add Sandbox". The instructions are self-explanatory; they will guide you to create a Sandbox. Once that is done, that sandbox will appear in this middle pane whenever you have selected "Sandboxes" on the left sidebar.

Mail Trap only allows one sandbox for each free user, but that is all you need.

You will see that in this middle pane, the Sandbox name is a link, so click on it to go into that Sandbox. Once in it, you will see the middle pane split into two sections, the one on the left will list any emails you have received into your Mail Trap account-this is literally your inbox. This is where you will come to check for incoming emails after you have submitted one from your application. The emails will be listed here (latest on top). Clicking on any email will display its body and contents on the right.

The URL in your browser should look something like this:

```
https://mailtrap.io/inboxes/2257617/messages
```

On the right, you will see these tabs, which are configurations for your Sandbox:

```
SMTP, Email, API, POP3
```

The next thing you need to send emails locally is to get your Sandbox's credentials which Dorguzen will use to send emails to your Mail Trap Sandbox's inbox. These credentials are four:

```
Host, Port, Username and Password.
```

To get these credentials, whilst in this Sandbox's inbox, look on the right pane and click on the SMTP tab. This will reveal all those credentials you need, which should look something like this:

```
Credentials

  Host             sandbox.smtp.mailtrap.io
  Port             25, 465, 587 or 2525
  Username         2c63b9f9d3k6c7
  Password         ****380h
  ...
```

Copy them over to your `.env` file, comment out the MAIL HOST group of directives meant for email sending in the production environment, and create a replication to override that for Mail Trap local testing. Here is what your `.env` file should look like:

```ini
# SMTP / Mail
#---------------------------------
# MAILGUN CREDENTIALS USING SMTP / Mail (PHPMailer)
# Switch MAIL_HOST/PORT/USERNAME/PASSWORD to MailTrap for local testing.
#---------------------------------
# MAIL_HOST=smtp.mailgun.org
# MAIL_PORT=587
# MAIL_USERNAME='postmaster@admin.camerooncom.com'
# MAIL_PASSWORD='postmaster_camcom@'
# MAIL_ENCRYPTION=tls
# MAIL_FROM_ADDRESS='noreply@admin.camerooncom.com'
# MAIL_FROM_NAME='Camerooncom'

#---------------------------------
# MAILTRAP TESTING
#---------------------------------
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME='yourUsernameFromMailTrapIo'
MAIL_PASSWORD=yourPasswordhere
```

Next, you just have to trigger email sending from your application to confirm that the email sending works. Do something to trigger email, for some, it is the submitting of a contact form your web app's contact page (if you know it sends emails). After doing so, go again to mailtrap.io and check the inbox of your Sandbox for any in-coming emails, and you should see one.

---

### 1. SMTP Configuration (.env)

Add the following block to your `.env` (and `.env.example`):

```ini
MAIL_HOST=smtp.mailgun.org       # your SMTP server
MAIL_PORT=587                    # 587 (TLS) or 465 (SSL)
MAIL_USERNAME=postmaster@...     # SMTP username
MAIL_PASSWORD=your-password      # SMTP password
MAIL_ENCRYPTION=tls              # tls | ssl
MAIL_FROM_ADDRESS=noreply@...    # envelope From address
MAIL_FROM_NAME='Your App'        # envelope From display name
```

For local testing with MailTrap (https://mailtrap.io):
Test locally, after commenting out the code above which is for the production environment, the four lines below are the only lines you need for local MailTrap testing.

```ini
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=<mailtrap-user>
MAIL_PASSWORD=<mailtrap-password>
MAIL_ENCRYPTION=tls
```

Just swap these four values in `.env` — no code changes needed. All emails will be caught by MailTrap's sandbox inbox instead of being delivered to real addresses.

Note: the `MAIL_FROM_*` values are always used as the envelope sender, regardless of which SMTP provider is active.

#### Also set `APP_EMAIL` — the admin recipient address

`APP_EMAIL` (read from config as `appEmail` in `configs/app.php`) is the admin inbox that `DGZ_Messenger` sends notifications *to*. The built-in `sendContactFormMsgToAdmin()` and `sendErrorLogMsgToAdmin()` methods use this value as their `To:` address (`$this->_appEmail`).

```ini
APP_EMAIL=admin@yourapp.com
APP_EMAIL_OTHER=second-admin@yourapp.com   # optional secondary admin address (appEmailOther)
```

> ⚠️ If `APP_EMAIL` is left empty, PHPMailer has no `To:` address and admin emails fail — the failure is logged as *"Email failed to send from: …"* with no further detail. Always set it to a real, non-empty address.

#### ⚠️ Before deploying to production — switch back to your live mail provider

This is a very common deployment mistake. The `.env` on your live server must NOT use Mailtrap credentials — Mailtrap is a sandbox that catches emails and prevents them from reaching real users. If you deploy with Mailtrap active, your registration activation emails, password reset emails, and all other transactional emails will silently disappear into a Mailtrap inbox instead of reaching your users.

The convention is to keep both blocks in `.env` and simply comment/uncomment the right one depending on the environment:

```ini
# SMTP / Mail
#---------------------------------
# MAILGUN (production) — uncomment when deploying to live
#---------------------------------
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@yourdomain.com
MAIL_PASSWORD=your-mailgun-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME='Your App Name'

#---------------------------------
# MAILTRAP (local testing only) — comment out before deploying
#---------------------------------
# MAIL_HOST=sandbox.smtp.mailtrap.io
# MAIL_PORT=587
# MAIL_USERNAME=<mailtrap-user>
# MAIL_PASSWORD=<mailtrap-password>
```

On your local machine, the Mailgun block is commented out and Mailtrap is active. On the live server, the Mailtrap block is commented out and Mailgun (or your chosen provider) is active. One file, both environments, no code changes ever needed.

Production mail provider checklist:

```
✔  MAIL_HOST — your live SMTP server (e.g. smtp.mailgun.org)
✔  MAIL_PORT — 587 (TLS) or 465 (SSL); 25 is often blocked by hosting providers
✔  MAIL_USERNAME / MAIL_PASSWORD — credentials from your mail provider dashboard
✔  MAIL_ENCRYPTION — tls (recommended) or ssl
✔  MAIL_FROM_ADDRESS — a verified sender address on your domain
✔  MAIL_TIMEOUT — defaults to 15 seconds; increase if your provider is slow
                  to respond, but do not set it too high or slow SMTP will
                  block your web requests (see ShouldQueue below)
```

If your activation or reset emails are still not arriving after switching to the live provider, check the logs table — DGZ_Messenger catches all PHPMailer failures and logs the exact SMTP error message so you can diagnose quickly.

---

### 2. Public Send Methods

We are looking here at methods that send standard emails that Dorguzen ships with. Some of them are in the `core/DGZ_Messenger` class.
All public methods return true on success and false on failure (PHPMailer exceptions are caught internally and logged via DGZ_Logger as *"Email failed to send from: <method>()"*).

#### Generic method — `sendEmail()`

For any email your app needs to send that isn't covered by the built-in transactional methods below, use `sendEmail()`. You never need to add methods to `DGZ_Messenger` or touch core code — create a template in `views/emails/` and call `sendEmail()` from your service or controller. Use PHP 8 named arguments since most parameters are optional.

```php
public function sendEmail(
    string $toEmail,
    string $toName      = '',
    string $subject     = '',
    string $body        = '',
    string $replyTo     = '',
    string $replyToName = '',
    array  $data        = [],
    string $template    = '',
): bool
```

```php
$messenger = new DGZ_Messenger();

// Templated HTML email — when $template is set, the body is rendered via
// renderEmail($template, $data) and sent as HTML.
$messenger->sendEmail(
    toEmail:     config('appEmail'),
    toName:      'Admin',
    subject:     'New booking from ' . $name,
    replyTo:     $visitorEmail,
    replyToName: $visitorName,
    data:        ['heading' => 'Booking Details', 'name' => $name],
    template:    'booking-confirmation',   // views/emails/booking-confirmation.php
);

// Plain-text email — when $template is omitted, $body is sent as plain text.
$messenger->sendEmail(
    toEmail: 'user@example.com',
    toName:  'Jane',
    subject: 'Quick note',
    body:    'Your request has been received.',
);
```

#### Built-in transactional methods

```php
sendContactFormMsgToAdmin($name, $visitorEmail, $phone, $message)
    — Forwards a website contact form submission to the site admin email.
      Also used by sendShopContactMsgToShopOwner() to forward to a shop owner.

sendNewsletterWelcomeMsg($subscriber_name, $email, $heading, $subject, $message, $image, $imageCaption, $template = 'newsletter-welcome')
    — Sends the first newsletter to a new subscriber.
      $image and $imageCaption are optional — pass '' to omit the image block.
      $template selects which email view to render (default: 'newsletter-welcome').

sendNewsletterMsg($subscriber_name, $email, $heading, $subject, $message, $image, $imageCaption, $template = 'newsletter')
    — Sends a regular newsletter to an existing subscriber.
      Same signature as sendNewsletterWelcomeMsg().
      $template selects which email view to render (default: 'newsletter').

sendEmailActivationEmail($name, $email, $subject, $message)
    — Account activation email. $message should contain the activation link HTML.

sendWelcomeEmail($name, $email, $subject, $message)
    — Post-registration welcome email. $message is the body text.

sendPasswordResetEmail($email, $firstname, $resetCode)
    — Password-reset email. The reset URL is constructed automatically from
      the app's homepage URL + 'auth/reset?em=' + $resetCode.

sendErrorLogMsgToAdmin($message)
    — Sends an error-log alert to the app admin email. Includes a direct link
      to the admin logs page.

sendHtml($toEmail, $toName, $subject, $htmlBody)
    — Sends a pre-rendered HTML body directly to one recipient, with no
      template/layout rendering. Use this when you have already built the
      complete HTML and just need it delivered.
```

Usage example:

```php
$messenger = new DGZ_Messenger();
$sent = $messenger->sendContactFormMsgToAdmin($name, $email, $phone, $message);
if (!$sent) {
    // handle failure — the error was already logged by DGZ_Logger
}
```

---

### 3. Template System (renderEmail)

Every public send method calls the private `renderEmail()` method internally. You never call `renderEmail()` directly — but understanding how it works lets you override or extend the email templates.

```php
private renderEmail(string $view, array $data, string $layout = 'default'): string
```

How it works:

1. View resolution — checks two locations in order:
   - a) `views/emails/{view}.php` &nbsp; &larr; developer override (your app)
   - b) `core/email-views/{view}.php` &nbsp; &larr; framework default

   If a file exists at (a) it is used; otherwise (b) is the fallback. This means you can customise any email's content without touching the framework core — just create a matching file under `views/emails/`.

2. Auto-injected variables — the following are always available in both the view file and the layout, without you needing to pass them:

```
$appName          from _appName
$appBusinessName  from _appBusinessName
$appSlogan        from _appSlogan
$appURL           from _appURL
$appYear          current year (date('Y'))
$heading          email-type heading (defaults to '' if not passed)
```

3. Layout wrapping — after the view is rendered into `$content`, it is passed to the layout file at:

```
layouts/email/{layout}EmailLayout.php
```

   The default layout is 'default', which maps to `layouts/email/defaultEmailLayout.php`

4. The complete HTML string is returned and assigned to `PHPMailer->Body`.

---

### 4. File Locations

```
core/DGZ_Messenger.php              The mailer class (public API + renderEmail)
core/email-views/                   Framework-default email content templates:
    contact-form.php                  — Contact form fields table
    member-email.php                  — Account activation + welcome (shared)
    password-reset.php                — Reset link + CTA button
    newsletter-welcome.php            — Newsletter welcome with optional image
    newsletter.php                    — Regular newsletter with optional image
    error-log.php                     — Error alert with highlighted message block
views/emails/                       Developer override directory (empty by default).
                                    Drop a file here with the same name as a
                                    core/email-views/ file to override it globally.
layouts/email/defaultEmailLayout.php  The HTML email wrapper (table-based,
                                    inline + <style> CSS, Outlook-compatible).
```

---

### 5. Customising Email Templates

To change the content of any email, create an override file in `views/emails/`:

```
views/emails/password-reset.php     <- overrides core/email-views/password-reset.php
```

Your override file receives the same variables as the framework default (listed in each file's docblock), plus all the auto-injected variables from section 3 above. You do not need to output a full HTML document — just the inner body content. The layout handles the outer HTML, header, accent bar, and footer.

To create a completely different email layout (e.g. a minimal plain-white layout for transactional receipts):

1. Create `layouts/email/receiptEmailLayout.php`
2. Add a new public send method on DGZ_Messenger that calls:

```php
$this->renderEmail('my-view', $data, 'receipt')
```

   (The third argument 'receipt' maps to `layouts/email/receiptEmailLayout.php`)

---

### 6. The Newsletter and Subscription System

This section documents the full newsletter feature end to end — from a visitor subscribing on the public site through to receiving emails, and how an admin manages it all. It also covers how images attached to newsletters are handled, what the scheduler does, and how to keep it running in production.

#### 6.0 Administrator's Guide — Using the Newsletter Feature

This sub-section is written for the person managing the site from the admin dashboard, not the developer building it. If you are the developer, read this section first so you understand what your admin user will experience; then continue to sections 6.1 onwards for the technical internals.

##### 6.0.1 Understanding Subscriber Statuses

Every person who subscribes via the public form is stored in the subscribers table with one of three effective states, visible as a badge in the Admin → Subscribers view:

- **NEW (green badge)**
  The subscriber signed up but has never received a welcome email. They are active and willing to receive mail — they are just waiting for their first contact from you.

- **ACTIVE (grey badge)**
  The subscriber has been sent at least one welcome email. They are a regular subscriber and will receive future bulk newsletters.

- **UNSUBSCRIBED (red badge)**
  The subscriber clicked the Unsubscribe link in one of the emails. They will NEVER receive another email from the system — not welcome emails, not bulk sends. This is enforced at two levels: the bulk send form only submits active subscriber IDs, and the service layer also skips inactive subscribers even if an ID is submitted manually.

Important: the "Total Subscribers" stat card shows ALL subscribers including unsubscribed ones (for record-keeping). The "Send Bulk Email (N)" button shows only the count of ACTIVE subscribers who will actually receive the email.

##### 6.0.2 The Two Types of Emails You Can Send

The newsletter system distinguishes between two send types:

- **WELCOME EMAIL**
  Sent to new subscribers (status = NEW) who have never been contacted before. This is usually a warm introduction to your brand — "Thanks for subscribing, here is what to expect." Once delivered, the subscriber's status changes from NEW to ACTIVE automatically. The recommended template is "newsletter-welcome".

- **BULK EMAIL**
  Sent to all ACTIVE subscribers. This is your regular newsletter — monthly updates, promotions, announcements. The recommended template is "newsletter". Unsubscribed and NEW (unwelcomed) subscribers are excluded automatically.

Tip: you can technically send a bulk email to NEW subscribers too — they will receive it but their "welcomed" flag will not be set by a bulk send. Always send a welcome email first, then follow up with bulk sends.

##### 6.0.3 Step-by-Step: Sending Your First Welcome Email

This is the sequence you follow when a new subscriber signs up and you want to send them a welcome email.

**Step 1 — Create a Welcome Newsletter record (one-time setup)**

Admin Dashboard → Newsletters → Create Newsletter

Fill in:

```
Subject    — e.g. "Welcome to our newsletter!"
Body       — Your welcome message. HTML is supported.
Template   — Choose "newsletter-welcome" from the dropdown.
Image      — Optional header image.
```

Click "Create Newsletter". This saves the record — nothing is sent yet. You only need to create this record once. Reuse it for every future welcome send unless you want to change the welcome message.

**Step 2 — Queue the welcome email**

Admin Dashboard → Subscribers

Click "Send Welcome Emails (N)" — the number shows how many new subscribers are waiting. A modal appears:

```
Select Newsletter   — Choose the welcome newsletter record you just created.
Click "Send Now"
```

This does NOT send the email immediately. It queues one row per subscriber in the pending_emails table with status = 'pending'. You will see a success message: "N welcome email(s) queued. They will be sent automatically on the next scheduler run."

**Step 3 — Run the scheduler to send**

The scheduler processes the queue and actually sends the emails.

Manual trigger (development / testing):
Open a terminal in your project directory and run:

```bash
php dgz schedule:run
```

Automated (production):
Set up a cron job — see section 6.11 for full instructions.

When the scheduler runs it will:

```
- Pick up all queued rows
- Send each email via SMTP (configured in .env)
- Mark each row as 'sent'
- Set the subscriber's status to ACTIVE (welcomed = 1)
```

##### 6.0.4 Step-by-Step: Sending a Bulk Newsletter

This is the sequence for sending a regular newsletter to all active subscribers.

**Step 1 — Create a Bulk Newsletter record**

Admin Dashboard → Newsletters → Create Newsletter

Fill in:

```
Subject    — e.g. "June Update: What's new this month"
Body       — Your newsletter content. HTML is supported.
Template   — Choose "newsletter" from the dropdown.
Image      — Optional header image.
```

Note: Create a NEW newsletter record for each campaign. Do not reuse the welcome newsletter record for bulk sends — it uses the welcome template, which may have different styling or wording ("Welcome to our newsletter!") that would be out of place in a regular bulk send.

**Step 2 — Queue the bulk email**

Admin Dashboard → Subscribers

Click "Send Bulk Email (N)" — the number shows how many active subscribers will receive the email. A modal appears:

```
Select Newsletter   — The dropdown shows all your newsletter records with
                      their template name in brackets, e.g.:
                        "June Update (newsletter)"
                        "Welcome to our newsletter! (newsletter-welcome)"
                      Choose the correct bulk newsletter record.
Click "Send Now"
```

Emails are queued in pending_emails — not sent immediately.

**Step 3 — Run the scheduler** (same as 6.0.3 Step 3 above).

##### 6.0.5 Understanding the Email Queue (pending_emails)

When you click "Send Now" the system does NOT connect to your SMTP server immediately. Instead it writes one row per email into the pending_emails table with status = 'pending'. This is intentional:

```
- Large batches (hundreds of subscribers) do not block the browser request.
- If one email fails to send, the others are not affected.
- You can see exactly what is queued, sent, or failed in the database.
- The scheduler processes up to 50 emails per run, preventing SMTP timeouts
  on large lists.
```

The pending_emails table columns you will interact with most:

```
status          'pending' — waiting to be sent
                'sent'    — successfully sent and timestamp recorded in sent_at
                'failed'  — SMTP error occurred; tries counter is incremented.
                            Will NOT be retried automatically. To retry, manually
                            reset status = 'pending' in the database.

tries           How many send attempts were made for this row.

last_attempt_at Timestamp of the most recent attempt (including failures).

sent_at         Timestamp of successful delivery.
```

##### 6.0.6 Adding a New Email Template

The template dropdown on the Create Newsletter form is built automatically from PHP files in two directories:

```
core/email-views/     Framework defaults (do not edit these files)
views/emails/         Your app's templates (create new templates here)
```

To add a new template:

1. Create a file in `views/emails/`, e.g. `views/emails/promo.php`
2. Write your HTML email body in that file (inner body only — no `<html>`, `<head>`, or `<body>` tags — the email layout wraps it automatically).
3. Reload the Create Newsletter form — "promo" will appear in the dropdown.

No configuration, no registration — just create the file.

The two templates that ship with Dorguzen:

```
newsletter-welcome.php    — Designed for first-contact welcome emails.
                            Use this when creating a welcome newsletter record.

newsletter.php            — Designed for regular bulk newsletters.
                            Use this when creating any campaign/bulk send record.
```

IMPORTANT: Never type a template name manually into the database that does not correspond to an actual .php file. If the file does not exist the scheduler will crash when it tries to render that row. Always create the file first, then create the newsletter record that references it.

##### 6.0.7 Running the Scheduler in Different Environments

Emails are sent by the scheduler, not by the web request. You must ensure the scheduler runs regularly in every environment.

**LOCAL DEVELOPMENT (MAMP / local PHP)**

Run the scheduler manually in a terminal whenever you want to send queued emails:

```bash
php dgz schedule:run
```

There is no need to set up a cron locally — manual triggering is the simplest approach during development and testing.

**SHARED HOSTING**

Most shared hosts provide a "Cron Jobs" section in cPanel or a similar control panel. Add a cron job that runs every minute:

```bash
* * * * * cd /home/youruser/public_html/yourapp && php dgz schedule:run
```

Check your host's documentation for the correct PHP binary path — it is often something like `/usr/local/bin/php` or `/opt/alt/php82/usr/bin/php`.

**VPS / DEDICATED SERVER**

Edit the server's crontab:

```bash
crontab -e
```

Add:

```bash
* * * * * cd /var/www/yourapp && php dgz schedule:run >> /dev/null 2>&1
```

Optionally log output for debugging:

```bash
* * * * * cd /var/www/yourapp && php dgz schedule:run >> /var/log/dgz-scheduler.log 2>&1
```

**DOCKER / CONTAINERS**

Either add the cron to a Dockerfile, or run the scheduler in a sidecar container with a simple shell loop:

```bash
while true; do php dgz schedule:run; sleep 60; done
```

IMPORTANT — each environment manages its own cron independently. Setting up a cron on your local Mac has NO effect on your production server, and vice versa. Configure the cron on the server where the app is deployed.

##### 6.0.8 Troubleshooting the Scheduler

Symptom: Scheduler runs ("Schedule run process complete") but emails stay as 'pending' and nothing is sent.

**Cause 1 — Stale lock in dgz_scheduled_task_locks**

If a previous scheduler run crashed before it could clean up, a lock row may have been left in the dgz_scheduled_task_locks table. While the lock is present, every subsequent run silently skips the job.

Fix (Dorguzen 1.x and later): The lock system automatically deletes expired locks before trying to acquire a new one. Locks expire after 60 seconds, so simply wait one minute and run the scheduler again.

Manual fix (if you need to unblock immediately):

```sql
DELETE FROM dgz_scheduled_task_locks;
```

**Cause 2 — dgz_scheduled_task_locks table does not exist**

The scheduler silently skips all `->withoutOverlapping()` jobs if the lock table is missing. Create it — see section 6.12 for the CREATE TABLE statement.

**Cause 3 — Wrong SMTP credentials**

Check your `.env` file:

```ini
MAIL_HOST=sandbox.smtp.mailtrap.io   (Mailtrap for dev)
MAIL_PORT=587
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
```

If credentials are wrong the scheduler will mark the row as 'failed' and log the error. Check `bootstrap/logs/php_errors.log` or the server error log.

**Cause 4 — Template file missing**

If `newsletters.newsletter_template` references a file that does not exist (e.g. 'welcome_mail' instead of 'newsletter-welcome'), the scheduler will throw an error. Reset status = 'pending' in pending_emails after creating or correcting the template file, then run the scheduler again.

**Cause 5 — Subscriber marked as inactive**

A subscriber with subscriber_active = 0 (Unsubscribed) will always be skipped. Even if their ID is in the pending_emails queue, the service checks the live subscriber record at send time and skips inactive ones.

#### 6.1 Overview

The newsletter system has four moving parts that work together:

```
┌─────────────────────┐     ┌─────────────────────┐
│   Public visitor    │     │   Admin dashboard    │
│  (subscribe form)   │     │ (create newsletters, │
└────────┬────────────┘     │  manage subscribers) │
         │                  └──────────┬────────────┘
         │ POST /subscribe             │ POST /admin/newsletters/create
         ▼                             ▼
┌─────────────────────────────────────────────────┐
│              subscribers table                  │
│              newsletters table                  │
│              pending_emails table               │
└─────────────────────┬───────────────────────────┘
                      │
                      │  php dgz schedule:run  (or cron)
                      ▼
┌─────────────────────────────────────────────────┐
│         SendPendingEmailsJob (scheduler)        │
│   reads pending rows → sends via DGZ_Messenger  │
│   → marks rows sent/failed                      │
└─────────────────────────────────────────────────┘
```

#### 6.2 The Subscription Flow

A visitor fills in the subscribe form on the public site (typically in the footer or a modal). The form POSTs to:

```
POST /subscribe   →   NewsletterController::subscribe()
```

The controller validates the email, then calls:

```php
NewsletterService::saveSubscriber([
    'subscriber_email'     => $email,
    'subscriber_firstname' => $name,
]);
```

This inserts a row into the `subscribers` table with:

```
subscriber_welcomed = 0   (has not yet received a welcome email)
subscriber_active   = 1   (is an active subscriber)
```

Nothing is emailed immediately. The subscriber is simply stored, ready for an admin to queue a welcome email at their discretion.

#### 6.3 The subscribers Table — active vs inactive

Every subscriber row carries two status flags:

```
subscriber_active    1 = active, receives emails
                     0 = inactive (unsubscribed), never receives emails

subscriber_welcomed  0 = new subscriber, has not been sent a welcome email yet
                     1 = has been welcomed — this is a regular subscriber now
```

These two flags control two different things:

- `subscriber_active` controls whether emails are EVER sent to this person. When someone clicks the Unsubscribe link in any email, the system calls `Subscribers::deactivateByEmail()` which sets subscriber_active = 0. They will never appear in bulk send selections again.

- `subscriber_welcomed` controls which email template is used for the FIRST send. An admin sees "new subscribers" (welcomed=0) separately in the dashboard so they can be given a welcome email. Once a welcome email is sent and delivered, the scheduler sets subscriber_welcomed = 1.

A subscriber can be active=1 and welcomed=0 (new, awaiting welcome email), active=1 and welcomed=1 (regular subscriber), or active=0 (unsubscribed — welcomed value is irrelevant at this point).

#### 6.4 The Admin Newsletter Workflow

Admins manage everything from:

```
Admin Dashboard → Subscribers    (manage subscribers, queue emails)
Admin Dashboard → Newsletters    (create/edit newsletter records)
```

**Step 1 — Create a newsletter**

Navigate to Newsletters → Create Newsletter. Fill in:

```
Subject        — The email subject line
Body           — The email body (HTML is supported)
Template       — Which email template to use for rendering (see 6.6)
Image          — Optional image to attach to the newsletter
```

On submit, a row is inserted into the `newsletters` table:

```
newsletter_subject   "Our spring collection is here"
newsletter_body      "<p>Hello...</p>"
newsletter_template  "newsletter"
newsletter_image     "assets/images/newsletters/nl_abc123.jpg"  (or NULL)
```

**Step 2 — Queue emails**

Navigate to Subscribers. Two send actions are available:

a) Send Welcome Email
   Select one or more new subscribers (welcomed=0) and a newsletter record. This calls `NewsletterService::queueWelcomeEmails()`, which inserts one row per subscriber into the `pending_emails` table with status='pending'.

b) Send Bulk Email
   Select any active subscribers and a newsletter record. This calls `NewsletterService::queueBulkEmail()` — same mechanics, same table. The distinction between "welcome" and "bulk" is tracked by the subscriber_welcomed flag at send time, not at queue time.

Both actions only insert into pending_emails. No email is sent immediately.

**Step 3 — The scheduler sends them (see 6.7)**

#### 6.5 The pending_emails Table

This table is the outbound email queue. Each row represents one email to be sent to one subscriber for one newsletter.

```
id                  Primary key (auto-increment)
subscriber_id       FK to subscribers
subscriber_email    Denormalised email address (for fast access in the job)
subscriber_name     Denormalised first name
newsletter_id       FK to newsletters
newsletter_subject  Denormalised subject
status              'pending' | 'sent' | 'failed'
tries               Number of send attempts (incremented on failure)
last_attempt_at     Timestamp of last attempt
sent_at             Timestamp of successful send
created_at          When the row was inserted
```

Denormalising the email/subject onto this table means the job never has to join tables — it fetches the newsletter and subscriber fresh on each run to get the latest body and image, but has the routing data immediately.

#### 6.6 Email Templates and the newsletter_template Field

When creating a newsletter, the admin picks a template from a dropdown. This list is built dynamically by `NewsletterService::scanEmailTemplates()`, which scans two directories and merges the results:

```
core/email-views/       Framework default templates
views/emails/           App-level overrides (same filename = takes precedence)
```

Any .php file in either directory appears in the dropdown automatically. To add a new newsletter template, simply create the file:

```
views/emails/my-template.php
```

It will appear in the dropdown on the next page load with no further configuration needed.

The chosen template name is stored in `newsletters.newsletter_template`. At send time the scheduler reads this value and passes it to DGZ_Messenger:

```php
$messenger->sendNewsletterMsg(
    $name, $email, $subject, $subject, $body, $image, '', $template
);
```

DGZ_Messenger then calls its internal `renderEmail()` method:

```php
renderEmail($template, [...data...])
```

which resolves to `views/emails/{template}.php` (app override) or `core/email-views/{template}.php` (framework default).

IMPORTANT — What variables are available in a newsletter template:

```
$subscriber_name    Subscriber's first name
$message            The newsletter body HTML (with unsubscribe link appended)
$image              Relative path stored in newsletters.newsletter_image
                    e.g. 'assets/images/newsletters/nl_abc123.jpg'
$imageCaption       Currently always '' (reserved for future use)
$heading            The newsletter subject (used as the email heading)
$appURL             Full base URL of the app (auto-injected)
$appName            App name (auto-injected)
$appBusinessName    Business name (auto-injected)
$appSlogan          Slogan (auto-injected)
$accentColour       App colour theme hex value (auto-injected)
$appYear            Current year (auto-injected)
```

Your template file should output inner body HTML only — no `<html>`, `<head>`, or `<body>` tags. The layout (`layouts/email/defaultEmailLayout.php`) wraps it.

Example minimal newsletter template:

```php
<!-- views/emails/my-template.php -->
<p style="font-family:Helvetica,Arial,sans-serif;font-size:16px;color:#444;">
    Hi <?= htmlspecialchars($subscriber_name) ?>,
</p>

<?php if (!empty($image)): ?>
<img src="<?= htmlspecialchars(rtrim($appURL, '/') . '/' . ltrim($image, '/')) ?>"
     alt="" style="max-width:100%;display:block;margin:0 0 16px;">
<?php endif; ?>

<div style="font-family:Helvetica,Arial,sans-serif;font-size:14px;color:#444;line-height:1.7;">
    <?= $message ?>
</div>
```

Note: use `<?= $message ?>` (raw output), NOT `htmlspecialchars($message)`, because the newsletter body contains HTML. The unsubscribe link is already appended to `$message` by the scheduler job before it reaches the template.

Why the core newsletter templates escape `$message`:
The framework's `core/email-views/newsletter.php` uses `htmlspecialchars($message)` because it was designed for plain-text messages. App-level overrides in `views/emails/` use raw output to support HTML bodies. This is the correct pattern — override, don't modify core.

#### 6.7 How Newsletter Images Are Handled

When an admin uploads an image on the Create Newsletter form:

1. The file is saved to:

```
assets/images/newsletters/nl_<unique_id>.<ext>
```

2. The relative path is stored in `newsletters.newsletter_image`:

```
'assets/images/newsletters/nl_6a2af2ff434b29.jpg'
```

3. At send time the scheduler passes this path as `$image` to the messenger.

4. In the email template the full URL is constructed as:

```php
rtrim($appURL, '/') . '/' . ltrim($image, '/')
→ https://myapp.com/assets/images/newsletters/nl_6a2af2ff434b29.jpg
```

5. This URL is embedded in the `<img>` tag in the sent email.

Important: the image URL is baked into the email at the moment it is sent. If APP_URL changes after sending (e.g. domain migration), old sent emails will have broken image links. This is standard behaviour for all email systems.

Local development note: when testing locally, `$appURL` will be something like `http://localhost/myapp` — a URL that external mail-testing services (Mailtrap, etc.) cannot reach. The image tag IS present in the email HTML (you can verify by viewing the raw source in Mailtrap), but the image will not render because the file is on your local machine. This is expected. Once deployed to a public server the images will appear correctly.

#### 6.8 The Welcome Email vs The Regular Newsletter

The scheduler distinguishes between a subscriber's first email and all subsequent ones using the subscriber_welcomed flag:

```php
$isFirstSend = empty($subscriber['subscriber_welcomed']);

if ($isFirstSend) {
    $messenger->sendNewsletterWelcomeMsg(..., $template ?: 'newsletter-welcome');
} else {
    $messenger->sendNewsletterMsg(..., $template ?: 'newsletter');
}
```

If the newsletter's template field is empty or unset, the system falls back to the appropriate default:

```
First send     → 'newsletter-welcome'  (core/email-views/newsletter-welcome.php)
Subsequent     → 'newsletter'          (core/email-views/newsletter.php)
```

After a successful first send, the scheduler marks the subscriber as welcomed:

```php
$subscribers->markAsWelcomed($subscriberId);
// sets subscriber_welcomed = 1
```

From this point on, all future sends for this subscriber use `sendNewsletterMsg()`.

The practical implication: if you select a specific template on the newsletter record, that same template is used whether it is a first send or a repeat send. The welcome/regular distinction affects the fallback default only.

#### 6.9 The Unsubscribe Flow

Every email sent by the scheduler includes an unsubscribe link in the footer. The link is appended directly to the newsletter body (`$message`) by the job before passing it to the messenger:

```php
$unsubscribeUrl = rtrim($baseUrl, '/') . '/unsubscribe?email=' . urlencode($email);
$body = $newsletter['newsletter_body']
    . '<p style="...">You are receiving this because you subscribed. '
    . '<a href="' . htmlspecialchars($unsubscribeUrl) . '">Unsubscribe</a>'
    . '</p>';
```

When the subscriber clicks the link:

```
GET /unsubscribe?email=user@example.com
→ NewsletterController::unsubscribe()
→ NewsletterService::unsubscribeByEmail($email)
→ Subscribers::deactivateByEmail($email)   sets subscriber_active = 0
```

The subscriber sees a confirmation page. They will never receive emails again (the active=0 check means they will never be selectable for future sends). The system does not reveal whether the email was found — it always shows the same confirmation page to prevent email enumeration.

#### 6.10 The Scheduler — How It Works

The scheduler is the engine that processes the pending_emails queue. It is NOT a background daemon — it is a one-shot CLI command:

```bash
php dgz schedule:run
```

Each time it runs it:

1. Loads `src/CLI/console/Schedule.php` and reads all registered tasks
2. For each task, checks whether its frequency filter is satisfied (e.g. `->everyMinute()` means "run if at least 60 seconds have elapsed since the last run" — it does NOT mean "loop forever every minute")
3. Acquires a DB lock (dgz_scheduled_task_locks table) via `->withoutOverlapping()` to prevent two concurrent runs of the same job
4. Dispatches the job — for QUEUE_DRIVER=sync this calls handle() immediately
5. Releases the lock
6. Exits

The SendPendingEmailsJob is registered in `src/CLI/console/Schedule.php`:

```php
$schedule->job(\Dorguzen\Jobs\SendPendingEmailsJob::class)
         ->everyMinute()
         ->withoutOverlapping();
```

Each run of handle() processes up to 50 pending rows and marks each one as 'sent' or 'failed'. If a send fails, the row stays as 'failed' and the tries counter is incremented — it will NOT be retried automatically unless you reset the status to 'pending' manually.

#### 6.11 Running the Scheduler

**Option A — Manual run (development / testing)**

```bash
cd /path/to/your/app
php dgz schedule:run
```

Run this after queuing emails to process them immediately. Useful during development when you want to trigger sends on demand.

**Option B — Server cron job (recommended for production)**

Set up a cron job on your server that calls the scheduler every minute. This is the standard approach used by most PHP frameworks (Laravel, Symfony, etc.):

```bash
# crontab -e
* * * * * cd /path/to/your/app && php dgz schedule:run >> /dev/null 2>&1
```

Breaking this down:

```
* * * * *       — fire every minute
cd /path/...    — change to the app directory (required — the CLI needs
                  to find bootstrap/app.php relative to the working dir)
php dgz schedule:run  — the one-shot scheduler command
>> /dev/null 2>&1     — discard output (or redirect to a log file
                         e.g. >> /var/log/dgz-scheduler.log 2>&1)
```

The scheduler's `->everyMinute()` filter then controls which registered jobs actually run on any given invocation. Cron provides the heartbeat; the scheduler provides the frequency logic.

To use a log file instead of discarding output:

```bash
* * * * * cd /path/to/app && php dgz schedule:run >> /var/log/dgz-scheduler.log 2>&1
```

**Option C — Supervisor / process manager (alternative to cron)**

If your server has Supervisor installed, you can keep a persistent worker process running instead of cron:

```ini
[program:dgz-scheduler]
command=bash -c "while true; do php dgz schedule:run; sleep 60; done"
directory=/path/to/your/app
autostart=true
autorestart=true
stderr_logfile=/var/log/dgz-scheduler.err.log
stdout_logfile=/var/log/dgz-scheduler.out.log
```

This shell loop runs the scheduler, sleeps 60 seconds, then runs it again — effectively the same behaviour as a per-minute cron job, but managed by Supervisor which handles auto-restart if the process dies.

Which option to use:

```
Development        → Option A (manual, on demand)
Shared hosting     → Option B (cron — most hosts provide crontab access)
VPS / dedicated    → Option B or C (both work; Supervisor gives better
                     visibility and auto-restart)
Docker / containers → Option C or a dedicated sidecar container running
                      the cron
```

#### 6.12 The Infrastructure Table: dgz_scheduled_task_locks

The `->withoutOverlapping()` call on a scheduled job uses a database table to prevent two concurrent runs of the same job:

```
dgz_scheduled_task_locks
    task_key    VARCHAR  — the job class name (unique key)
    locked_at   DATETIME — when the lock was acquired
    expires_at  DATETIME — when the lock should be considered stale
```

This table must exist in your database. It is NOT created by a migration file — it is an infrastructure table that should be created when setting up the database for any Dorguzen application. Create it with:

```sql
CREATE TABLE dgz_scheduled_task_locks (
    task_key   VARCHAR(191) NOT NULL,
    locked_at  DATETIME     NOT NULL,
    expires_at DATETIME     NOT NULL,
    PRIMARY KEY (task_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

If this table is missing, `->withoutOverlapping()` silently treats every job as "already running" and skips it — the scheduler will appear to run but nothing will be processed. This is a known gotcha: always verify this table exists when setting up a new environment.

#### 6.13 End-to-End Summary (Quick Reference)

```
1.  Visitor submits subscribe form
        POST /subscribe → subscribers table (active=1, welcomed=0)

2.  Admin creates a newsletter
        Admin → Newsletters → Create → newsletters table

3.  Admin queues emails
        Admin → Subscribers → select subscribers + newsletter
        → Send Welcome Email or Send Bulk Email
        → pending_emails table (status='pending')

4.  Scheduler runs (cron / manual)
        php dgz schedule:run
        → SendPendingEmailsJob::handle()
        → fetches up to 50 pending rows
        → for each row:
             a. fetches newsletter record (body, image, template)
             b. fetches subscriber record (name, email, welcomed flag)
             c. appends unsubscribe link HTML to body
             d. calls sendNewsletterWelcomeMsg() [first send]
                OR sendNewsletterMsg() [repeat send]
             e. DGZ_Messenger::renderEmail($template, $data)
                  → resolves views/emails/{template}.php
                    OR core/email-views/{template}.php
                  → wraps in layouts/email/defaultEmailLayout.php
                  → PHPMailer sends via SMTP
             f. marks row as 'sent'; if first send, marks subscriber welcomed=1
             g. on exception: marks row as 'failed', logs error

5.  Subscriber clicks Unsubscribe in email
        GET /unsubscribe?email=... → subscriber_active = 0
        → subscriber never receives emails again
```

---

## Newsletter & Email System

### Overview

Dorguzen ships with a complete newsletter and subscriber management system out of the box. Any DGZ application that has run the newsletter migrations gets two database tables (subscribers and newsletters), two models, a service layer, and a full admin UI for creating newsletters, managing subscribers, and dispatching email campaigns.

The system is split into two distinct phases:

- **Phase 1** — Admin creates newsletters and selects recipients. The controller calls `NewsletterService::queueWelcomeEmails()` or `queueBulkEmail()`, which writes one row into pending_emails for every selected subscriber. The HTTP request returns immediately; no email is sent inline.

- **Phase 2** — The Dorguzen scheduler runs SendPendingEmailsJob on a cron-like schedule. The task picks up pending rows in batches of 50, renders each email template, sends via DGZ_Messenger (PHPMailer / SMTP), and marks the row as 'sent' or 'failed'.

This two-phase design keeps HTTP response times fast regardless of how many recipients are in the list and gives you full visibility into the sending state through a single database table.

---

### The pending_emails Queue Table

The pending_emails table acts as a durable, inspectable queue between the admin action and the actual SMTP call. Every outbound newsletter email starts life as a row in this table.

Key columns:

```
id                  Auto-increment primary key.
subscriber_id       FK to subscribers.subscriber_id.
subscriber_email    Denormalised email address — avoids a join per row
                    during bulk sends.
subscriber_name     Denormalised first name used in the template greeting.
newsletter_id       FK to newsletters.newsletter_id.
newsletter_subject  Denormalised subject — cached at queue time so that
                    an admin editing the newsletter mid-send does not
                    break in-flight emails.
status              enum('pending','sent','failed'). Default 'pending'.
tries               Integer counter incremented on each failed attempt.
last_attempt_at     Timestamp of the most recent send attempt, whether
                    it succeeded or failed.
scheduled_at        Optional future date-time; not enforced by the task
                    itself but useful if you want to implement deferred
                    sends at the application layer.
sent_at             Set to NOW() when status transitions to 'sent'.
created_at          Set automatically by the database on INSERT.
updated_at          Updated automatically by the database on every change.
```

Status lifecycle:

```
pending  →  sent      (happy path)
pending  →  failed    (SMTP error, missing subscriber, missing template)
```

Failed rows are NOT automatically retried. Retrying is a deliberate action — you can reset rows to 'pending' with a direct SQL UPDATE and they will be picked up on the next scheduler run.

To inspect the queue directly:

```sql
SELECT status, COUNT(*) AS n FROM pending_emails GROUP BY status;

SELECT * FROM pending_emails WHERE status = 'failed' ORDER BY created_at DESC;
```

The tries column tells you how many attempts were made before the row was abandoned, and last_attempt_at tells you when.

---

### How Email Sending Works

The full flow from admin action to delivered email:

1. Admin visits `/admin/subscribers`, selects one or more subscribers, picks a newsletter, and clicks Send Welcome Emails or Send Bulk.

2. NewsletterController calls `NewsletterService::queueWelcomeEmails()` or `queueBulkEmail()`. Both methods delegate to the same internal `insertPendingRows()` helper, which:
   - a. Loads the newsletter row to get the subject.
   - b. Loads each subscriber row to get the email address and name.
   - c. INSERTs one row into pending_emails per subscriber.

   The controller immediately redirects back with a flash message confirming how many emails were queued.

3. The Dorguzen scheduler runs `php dgz schedule:run` once per minute (or on whatever cadence you configure). ScheduleRunCommand calls `ScheduleLoader::load()`, which requires `src/CLI/console/Schedule.php` and calls the closure inside it with a Schedule object.

4. Schedule.php registers SendPendingEmailsJob as a job with `->everyMinute()->withoutOverlapping()`. When the task is due, `Scheduler::runJob()` instantiates the class and calls dispatch(), which in turn calls handle() via the QueueManager.

5. `SendPendingEmailsJob::handle()` calls `PendingEmails::getPendingEmails(50)` and iterates the rows. For each row:
   - a. Loads the newsletter and subscriber from their respective models.
   - b. Resolves the template file from `views/emails/{template_name}.php`, falling back to `views/emails/welcome_mail.php` if the file is missing.
   - c. Extracts variables into scope and renders the template using output buffering (ob_start / ob_get_clean).
   - d. Calls `DGZ_Messenger::sendNewsletterMsg()` with the rendered HTML.
   - e. On success: calls `PendingEmails::markSent()` and (for first-time recipients) `Subscribers::markAsWelcomed()`.
   - f. On failure: catches the Throwable, logs it, calls `PendingEmails::markFailed()`.

6. DGZ_Messenger uses PHPMailer internally. It reads SMTP credentials from `.env` at construction time and sends via the configured host.

---

### Email Templates

Email templates live in `views/emails/` inside your DGZ application. Each template is a plain PHP file rendered using output buffering. The rendered string is passed directly to DGZ_Messenger as the HTML body.

Variables available inside every newsletter template:

```
$subscriber_firstname    The subscriber's first name (may be empty).
$newsletter_subject      The newsletter's subject line.
$newsletter_body         The newsletter's main body content (HTML string
                         as stored in the newsletters table).
$newsletter_image_url    Fully qualified URL to the newsletter image,
                         or empty string if none was uploaded.
$site_name               The application name from Config (APP_NAME in .env).
$unsubscribe_url         A pre-built unsubscribe link:
                         {base_url}unsubscribe?email={encoded_email}
```

To create a new email template:

1. Add a .php file to `views/emails/`, for example `views/emails/promo.php`.
2. Use the variables listed above anywhere in the file.
3. The template scanner (`NewsletterService::scanEmailTemplates()`) discovers all .php files in that directory automatically, so the new template appears in the admin Create Newsletter dropdown without any further configuration.

The `scanEmailTemplates()` method:

It uses glob() to find all *.php files in `views/emails/`, strips the .php extension, sorts the names alphabetically, and returns the array. This array is passed to the create-newsletter view as `$templates`. Adding a file is the only registration step required.

Note: DGZ_Messenger also has its own `renderEmail()` method, which wraps content views in an email layout from `layouts/email/`. That method is used by the built-in contact-form and password-reset emails, but newsletter emails use the raw template approach (include + ob_start) so that admins get full HTML control over the newsletter layout.

---

### Powering It with the Dorguzen Scheduler

Dorguzen includes an internal task scheduler that removes the need for a cPanel cron job or a shell-level crontab entry for most scheduled work.

Architecture:

```
Schedule           — a fluent registry of tasks (command, job, or event)
                     defined in src/CLI/console/Schedule.php.
ScheduleLoader     — loads that file and returns the populated Schedule
                     object to the runner.
ScheduleRunCommand — the CLI command (schedule:run) that iterates tasks,
                     checks whether each is due, and calls Scheduler::run().
Scheduler          — dispatches the task to the correct subsystem
                     (CLI, queue, or event bus) and enforces overlap locking.
```

Registering SendPendingEmailsJob:

In `src/CLI/console/Schedule.php` return a closure that accepts a Schedule instance and registers the job:

```php
use Dorguzen\Core\Console\Scheduling\Schedule;

return function (Schedule $schedule): void {
    $schedule->job(\Dorguzen\Jobs\SendPendingEmailsJob::class)
             ->everyMinute()
             ->withoutOverlapping();
};
```

The `->withoutOverlapping()` call acquires a database lock when the task starts. If the previous run has not finished (e.g. a large batch is still sending), the new run is skipped silently, preventing duplicate sends.

Starting the scheduler:

```bash
php dgz schedule:run
```

IMPORTANT — this is a one-shot command. Each invocation:

1. Checks which registered tasks are due right now.
2. Runs any that are due.
3. Exits.

It does NOT stay running in the background or loop by itself. The `->everyMinute()` / `->hourly()` / `->dailyAt()` calls are filters — they tell the scheduler "only run this task when the command is invoked AND the required interval has elapsed since the last run." They do not make the command self-perpetuating.

To achieve continuous, automatic execution you need something external to invoke `php dgz schedule:run` repeatedly (see the production recommendation below).

Available frequency helpers on a registered task:

```
->everyMinute()          runs every minute (* * * * *)
->hourly()               runs at the top of every hour
->daily()                runs at midnight every day
->dailyAt('08:00')       runs at 08:00 every day
->weekly()               runs at midnight on Sunday
->monthly()              runs at midnight on the 1st of each month
->cron('*/5 * * * *')    raw cron expression for any other cadence
```

Production recommendation:

Use Supervisor (or an equivalent process manager) to keep a loop alive that fires `php dgz schedule:run` every minute:

```ini
[program:dgz-scheduler]
command=bash -c "while true; do php /path/to/app/dgz schedule:run; sleep 60; done"
autostart=true
autorestart=true
```

On shared hosting without Supervisor, a single crontab entry that runs the command every minute achieves the same result:

```bash
* * * * * php /path/to/app/dgz schedule:run >> /dev/null 2>&1
```

Either way, only one entry point is needed — the Schedule.php file controls everything else from inside the codebase.

---

### Why Not Sync Queue / RabbitMQ / Standard Cron?

**Sync queue (QUEUE_DRIVER=sync):**
Jobs run inline before the HTTP response is returned. For a list of 500 subscribers this means the admin waits 30–90 seconds for the page to load, often hitting PHP's max_execution_time limit. Sync is fine for development but unsuitable for any list with more than a handful of recipients.

**RabbitMQ:**
RabbitMQ is the right tool for very high-throughput messaging at scale. However it requires a separate broker process, additional system dependencies (php-amqplib), and familiarity with exchange/queue topology. For a newsletter feature on a self-hosted PHP application this is significant infrastructure overhead.

**Standard cron (server-level crontab or cPanel scheduler):**
Cron works, but it requires SSH or hosting-panel access to configure, is not part of the codebase, and is easy to lose track of across deployments. New team members have no visibility into what is scheduled unless they check the server.

**Dorguzen Scheduler:**
The scheduler runs inside the application process, is registered in plain PHP (`src/CLI/console/Schedule.php`), is version-controlled alongside the rest of the code, requires no external services, and works identically on a local Mac, a shared hosting account, and a cloud VPS. The pending_emails table provides full observability into what was sent, when, and whether it failed.

---

### SMTP Configuration

DGZ_Messenger reads all mail settings from `.env` at construction time. No code changes are needed to switch between providers.

Required .env keys:

```ini
MAIL_HOST          SMTP hostname, e.g. smtp.mailgun.org or
                   sandbox.smtp.mailtrap.io
MAIL_PORT          SMTP port, typically 587 (STARTTLS) or 465 (SSL)
MAIL_USERNAME      SMTP authentication username
MAIL_PASSWORD      SMTP authentication password
MAIL_ENCRYPTION    tls or ssl
MAIL_FROM_ADDRESS  The From address recipients see, e.g.
                   noreply@yourdomain.com
MAIL_FROM_NAME     The From name recipients see, e.g. "Your App Name"
```

Development:
Point at Mailtrap (https://mailtrap.io) or any local mail catcher. Mailtrap provides sandbox SMTP credentials that capture all outbound email without delivering it to real inboxes. Set MAIL_HOST to `sandbox.smtp.mailtrap.io` and MAIL_PORT to 587.

Production:
Any transactional SMTP provider works: Mailgun, SendGrid, Amazon SES, Postmark, or your web host's own SMTP server. Copy the provider's SMTP credentials into `.env` and set LIVE_ENV=true.

DGZ_Messenger uses PHPMailer under the hood. PHPMailer is included as a Composer dependency — no additional installation is required. The messenger initialises a single PHPMailer instance in its constructor and reuses it across method calls within the same request/task run, calling clearAddresses() between sends to avoid recipient accumulation.

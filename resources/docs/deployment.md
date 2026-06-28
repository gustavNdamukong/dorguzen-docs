# Deploying to Production

This page covers what you need to know when deploying a Dorguzen application to different server environments: how Dorguzen controls PHP upload and memory limits across SAPIs, configuring the scheduler cron on your live server, the common mail deployment mistake to avoid, migration best practice in production, and why the built-in development server must never be used in production.

---

## Requirements

- PHP **8.0** or higher
- One of: MySQL/MariaDB, PostgreSQL, SQLite
- Composer
- PHP extensions: `mysqli` (for MySQL), `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`, `mbstring`, `openssl`, `json`

---

## Environment Setup

Copy the example file and fill in every value:

```bash
cp .env.example .env
```

**Key production values:**

```ini
APP_ENV=prod
APP_DEBUG=false
APP_URL=https://yourapp.com
APP_LIVE_URL=https://yourapp.com

DB_CONNECTION=mysqli
DB_HOST=127.0.0.1
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
DB_DATABASE=your_db_name
DB_KEY=your-random-aes-encryption-key

APP_JWT_SECRET=your-random-jwt-secret-40-chars-minimum
APP_JWT_ENCODING=HS256

APP_API_CSRF_EXCEPTION='/api/'
API_DOCS_ENABLED=false

QUEUE_DRIVER=db

MODULES_SEO_STATUS=on
```

For mail configuration, see *Switch Back to Your Live Mail Provider* below.

**Never commit `.env` to version control.** It contains credentials.

---

## Deployment Steps

```bash
# 1. Install production dependencies (no dev packages, optimized autoloader)
composer install --no-dev --optimize-autoloader

# 2. Verify all required .env variables are set
php dgz env:check

# 3. Run database migrations
php dgz migrate

# 4. Seed initial data (first deploy only)
php dgz db:seed

# 5. Build caches
php dgz cache:config-clear       # clear any stale config cache
php dgz cache:route-cache        # pre-build route cache
php dgz cache:middleware-cache   # pre-build middleware cache

# 6. Set file permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

On subsequent deploys: re-run steps 1, 3 (migrations only), and 5 (clear + rebuild caches).

---

## Server Upload Limits and the PHP SAPI (.htaccess and .user.ini)

This section explains how Dorguzen controls PHP upload and memory limits, why two separate configuration files are used, and what you need to know when deploying to different server environments.

---

### What is a PHP SAPI?

SAPI stands for Server API. It is the layer that connects your web server (Apache, Nginx, etc.) to PHP — in other words, it is the mechanism by which Apache hands an incoming HTTP request to PHP for processing.

There are two SAPIs you will commonly encounter:

**mod_php**
PHP is loaded directly inside Apache as a module. Apache and PHP are tightly coupled. Configuration for PHP can be set inside `.htaccess` using the `php_value` directive. This was the standard approach for many years and is still used on some servers.

**PHP-FPM (FastCGI Process Manager)**
PHP runs as a completely separate process from Apache. Apache passes requests to PHP-FPM over a FastCGI connection. They are decoupled. This is the modern standard and what most up-to-date servers (including newer versions of MAMP) use by default.

Because PHP is a separate process with PHP-FPM, Apache's `php_value` directives in `.htaccess` have no effect — Apache cannot reach across into the PHP process to set configuration values. A different mechanism is needed.

The key question: which SAPI is my server using?

- If you are using a modern version of MAMP, the answer is almost certainly PHP-FPM.
- If you are on a traditional cPanel shared hosting plan, it may be mod_php.
- If you are on a modern VPS or cloud server (DigitalOcean, AWS, etc.) it is almost certainly PHP-FPM.

You do not need to be certain — Dorguzen ships both configuration files so the correct one is always present regardless of the environment.

---

### What .htaccess does (and what it does not do)

The `.htaccess` file in the project root has two jobs:

1. **URL rewriting** — redirect every request to `index.php` (the front controller), unless the request is for a real file on disk (CSS, images, JS, etc.). This is handled by Apache's `mod_rewrite` module and works identically on both mod_php and PHP-FPM. The SAPI does not affect URL rewriting at all.

2. **PHP settings (mod_php only)** — set upload limits and memory using `php_value` directives inside `<IfModule mod_php8.c>` blocks. These are silently ignored when the server uses PHP-FPM.

Dorguzen's `.htaccess` currently contains:

```apache
# ------------------------------- RULE: catch-all (any depth)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]

<IfModule mod_php7.c>
   php_value upload_max_filesize 50M
   php_value post_max_size 50M
   php_value memory_limit 256M
</IfModule>
<IfModule mod_php8.c>
   php_value upload_max_filesize 50M
   php_value post_max_size 50M
   php_value memory_limit 256M
</IfModule>
```

The `<IfModule>` wrapper is important: it tells Apache to only apply those directives if the named module is loaded. Without it, Apache would throw a 500 error on any server that does not have mod_php installed. With it, the block is simply skipped on PHP-FPM servers — safely and silently.

Blocks for both `mod_php7.c` and `mod_php8.c` are included to cover PHP 7.x and 8.x mod_php installations respectively.

---

### What .user.ini does (PHP-FPM only)

PHP-FPM reads a special file called `.user.ini` from the document root of each project. Think of it as the PHP-FPM equivalent of the `php_value` directives in `.htaccess`. It uses standard PHP ini syntax — plain key = value pairs, no Apache directives, no module wrappers.

Dorguzen's `.user.ini` currently contains:

```ini
upload_max_filesize = 50M
post_max_size = 50M
memory_limit = 256M
```

This file is read automatically by PHP-FPM on every request and requires no special setup. It also works on shared hosting running PHP-FPM where you do not have access to the server's main `php.ini` — making it the correct solution for the majority of modern hosting environments.

---

### Why both files exist

Dorguzen ships both `.htaccess` and `.user.ini` so that the same upload limits apply regardless of which SAPI the server uses:

- **Server uses mod_php** → `.htaccess` `php_value` blocks take effect; `.user.ini` is ignored (PHP-FPM is not running)
- **Server uses PHP-FPM** → `.user.ini` takes effect; `.htaccess` `php_value` blocks are silently skipped

You do not need to choose one or delete the other. Keep both files in place and the correct one will be picked up automatically.

---

### What happens when an upload exceeds the limit

PHP has two relevant ini settings that work together:

- `post_max_size` — the maximum size of the entire HTTP POST body (all form fields plus all uploaded files combined).
- `upload_max_filesize` — the maximum size of a single uploaded file.

`post_max_size` must always be equal to or larger than `upload_max_filesize`, because the uploaded file travels inside the POST body.

If a request body exceeds `post_max_size`, PHP silently discards the entire `$_POST` array before your application runs. This has an important side effect: the CSRF token (which travels in `$_POST`) also disappears, causing the CSRF middleware to appear to fail with a misleading "Invalid or missing CSRF token" error.

Dorguzen's `CsrfPsrMiddleware` detects this situation before checking the CSRF token. When it finds that the incoming Content-Length exceeds `post_max_size`, it throws a clear exception:

> "Uploaded file exceeds server max size limit"
> "The data you submitted (X MB) exceeds the server's maximum allowed POST size (50M). Please upload a smaller file."

This means you will always get a meaningful error instead of a confusing CSRF failure when a file is too large.

Note: PHP emits its own low-level warning about this ("POST Content-Length of X bytes exceeds the limit of Y bytes in Unknown on line 0") before your application code runs. This warning appears in the browser on local/development environments because `display_errors` is on. It is suppressed automatically in production because Dorguzen calls `setupErrorHandling($env)` in `index.php`, which turns `display_errors` off for any environment that is not local.

---

## Storing Secrets in Production

- Store secrets only in `.env`
- Load secrets into config via `env()`
- Do not hard-code credentials in config files

In production:

- Use environment-level variables
- Lock down file permissions
- Ensure `bootstrap/cache/` is writable but protected

---

## Before Deploying to Production — Switch Back to Your Live Mail Provider

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

- `MAIL_HOST` — your live SMTP server (e.g. `smtp.mailgun.org`)
- `MAIL_PORT` — 587 (TLS) or 465 (SSL); 25 is often blocked by hosting providers
- `MAIL_USERNAME` / `MAIL_PASSWORD` — credentials from your mail provider dashboard
- `MAIL_ENCRYPTION` — tls (recommended) or ssl
- `MAIL_FROM_ADDRESS` — a verified sender address on your domain
- `MAIL_TIMEOUT` — defaults to 15 seconds; increase if your provider is slow to respond, but do not set it too high or slow SMTP will block your web requests (see ShouldQueue below)

---

## Configuring the Scheduler Cron on Your Server

**Shared hosting**

Most shared hosts provide a "Cron Jobs" section in cPanel or a similar control panel. Add a cron job that runs every minute:

```bash
* * * * * cd /home/youruser/public_html/yourapp && php dgz schedule:run
```

Check your host's documentation for the correct PHP binary path — it is often something like `/usr/local/bin/php` or `/opt/alt/php82/usr/bin/php`.

**VPS / dedicated server**

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

**Docker / containers**

Either add the cron to a Dockerfile, or run the scheduler in a sidecar container with a simple shell loop:

```bash
while true; do php dgz schedule:run; sleep 60; done
```

**IMPORTANT** — each environment manages its own cron independently. Setting up a cron on your local Mac has NO effect on your production server, and vice versa. Configure the cron on the server where the app is deployed.

---

## Migrations — Production Best Practice

In production environments:

- Always run `php dgz migrate` during deployment.
- Never use `migrate:fresh`.
- Always ensure backups exist before rollback.
- Lock system ensures safe single-process execution.

---

## The Built-in Development Server is NOT for Production — and Why

The built-in server must never be used in a live, publicly accessible environment. This is not a Dorguzen limitation — it is a fundamental constraint of PHP's built-in server itself, and PHP's own documentation states this explicitly.

Here is why production web servers like Apache (used by MAMP) and Nginx can handle real traffic, but PHP's built-in server cannot:

1. **Single-threaded, single-process**
   PHP's built-in server handles exactly one request at a time. While it is processing request A, every other incoming request waits in a queue. On a real website with even a handful of simultaneous visitors — or a page that loads a dozen assets (CSS, JS, images) in parallel — requests pile up and the server grinds to a halt.

   Apache and Nginx spawn multiple worker processes or threads and handle hundreds of concurrent connections simultaneously. MAMP uses Apache under the hood, which is why it can serve your application smoothly to a real audience.

2. **No keep-alive or connection pooling**
   Modern browsers open several parallel connections to load a page faster. The built-in server does not support HTTP keep-alive properly, so each asset gets its own slow connection cycle.

3. **No static file optimisation**
   Apache and Nginx serve static files (images, CSS, JS) directly from disk at OS speed, bypassing PHP entirely. The built-in server routes every request — including static assets — through PHP, which is far slower and wastes memory.

4. **No TLS / HTTPS**
   The built-in server speaks plain HTTP only. Production sites require HTTPS. Apache and Nginx handle TLS termination natively (or via a reverse proxy like Certbot/Let's Encrypt).

5. **No process supervision**
   If the built-in server crashes, it stays crashed. Production web servers integrate with systemd, supervisord, or similar process managers that restart them automatically.

In short: MAMP, LAMP, XAMPP, and Nginx are engineered for reliability, concurrency, and security at scale. PHP's built-in server is engineered for a developer to preview their work quickly on their own machine. Use each tool for what it was designed for.

---

## Shared Hosting

### Web root

Point your document root to the project root (the directory containing `index.php`). The `.htaccess` file handles URL rewriting — ensure `mod_rewrite` is enabled.

### Database driver

`DB_CONNECTION=mysqli` is the most widely supported option on shared hosting.

### SQLite (no MySQL available)

```ini
DB_CONNECTION=sqlite
DB_SQLITE_PATH=storage/database.sqlite
```

Create the storage directory and make it writable:

```bash
mkdir -p storage
chmod 755 storage
touch storage/database.sqlite
```

Do **not** prefix `DB_SQLITE_PATH` with a leading `/` — it's a relative path from the project root.

### Queue workers

Shared hosting typically doesn't allow persistent daemon processes. Use `QUEUE_DRIVER=sync` so queued listeners execute inline, or use a cron job to periodically process the queue:

```
* * * * * php /path/to/your/app/dgz queue:work --once
```

For the scheduler cron, see *Configuring the Scheduler Cron on Your Server* above.

---

## VPS / Dedicated Server

### Web server

Configure Apache or Nginx to point to the project root. A minimal Apache `VirtualHost`:

```apache
<VirtualHost *:443>
    ServerName yourapp.com
    DocumentRoot /var/www/yourapp

    <Directory /var/www/yourapp>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### HTTPS

```bash
# Let's Encrypt via Certbot
certbot --apache -d yourapp.com
```

Then call `enforceHttps()` in your application bootstrap to redirect HTTP and set an HSTS header (see below).

### Queue worker as a daemon

When `QUEUE_DRIVER=db`, run the worker as a persistent background process. With Supervisor:

```ini
[program:dgz-worker]
command=php /var/www/yourapp/dgz queue:work
directory=/var/www/yourapp
autostart=true
autorestart=true
stopwaitsecs=60
stdout_logfile=/var/log/dgz-worker.log
```

The worker listens for `SIGTERM`/`SIGINT` and finishes the current job before exiting — safe for graceful restarts.

---

## Enforcing HTTPS in Application Code

```php
// Call this in your application bootstrap or a global middleware
enforceHttps();
```

This redirects all HTTP requests to HTTPS and sends `Strict-Transport-Security`. It respects Cloudflare and reverse-proxy headers (`CF-Visitor`, `X-Forwarded-Proto`) and supports a configurable trusted-proxy IP list. It only runs in production environments, so it is safe to leave in place during local development.

---

## Global CLI Tool

To run `dgz` from any directory on your server:

```bash
php dgz install
```

This creates a symlink at `/usr/local/bin/dgz` (Linux/macOS) or a `dgz.bat` wrapper (Windows).

---

## Post-Deploy Checklist

| Check | How |
|---|---|
| `APP_ENV=prod`, `APP_DEBUG=false` | Verify in `.env` |
| `API_DOCS_ENABLED=false` | Disable Swagger UI |
| All migrations ran | `php dgz migrate:status` |
| Env vars complete | `php dgz env:check` |
| Caches built | `php dgz cache:route-cache && php dgz cache:middleware-cache` |
| HTTPS enforced | `enforceHttps()` called, or web server handles it |
| `storage/` writable | `chmod -R 755 storage/` |
| `bootstrap/cache/` writable | `chmod -R 755 bootstrap/cache/` |
| `.env` not web-accessible | Verify `.htaccess` blocks direct `.env` access |
| Queue worker running | Supervisor or equivalent |
| Cron entry active | `crontab -l` to verify |

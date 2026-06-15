# Deployment

This page covers deploying a Dorguzen application to production — shared hosting and VPS alike.

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

MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=noreply@yourapp.com
MAIL_FROM_NAME="Your App"

MODULES_SEO_STATUS=on
```

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
php dgz cache:config-clear   # clear any stale config cache
php dgz cache:route-cache    # pre-build route cache
php dgz middleware:cache     # pre-build middleware cache

# 6. Set file permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

On subsequent deploys: re-run steps 1, 3 (migrations only), and 5 (clear + rebuild caches).

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

### Cron (for scheduler)

Add one entry for the task scheduler:

```
* * * * * php /path/to/your/app/dgz schedule:run >> /dev/null 2>&1
```

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

Then call `enforceHttps()` in your application bootstrap to redirect HTTP and set an HSTS header.

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

### Cron

```
* * * * * php /var/www/yourapp/dgz schedule:run >> /dev/null 2>&1
```

---

## HTTPS in Application Code

```php
// Call this in bootstrap/app.php or a global middleware
enforceHttps();
```

This redirects all HTTP requests to HTTPS and sends `Strict-Transport-Security`. It respects Cloudflare and reverse-proxy headers (`CF-Visitor`, `X-Forwarded-Proto`) and supports a configurable trusted-proxy IP list.

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
| Caches built | `php dgz cache:route-cache && php dgz middleware:cache` |
| HTTPS enforced | `enforceHttps()` called, or web server handles it |
| `storage/` writable | `chmod -R 755 storage/` |
| `bootstrap/cache/` writable | `chmod -R 755 bootstrap/cache/` |
| `.env` not web-accessible | Verify `.htaccess` blocks direct `.env` access |
| Queue worker running | Supervisor or equivalent |
| Cron entry active | `crontab -l` to verify |

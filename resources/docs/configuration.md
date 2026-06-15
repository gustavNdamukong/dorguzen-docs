# Configuration

Dorguzen uses a two-layer configuration system: a `.env` file for environment-specific values, and PHP config files in `configs/` that read from `.env` via the `env()` helper.

---

## Environment File (`.env`)

Copy `.env.example` to `.env` and fill in your values. This file is never committed to version control.

Key variables:

```ini
APP_NAME=dorguzen
APP_BUSINESS_NAME="Dorguzen Framework"
APP_ENV=local            # local | prod | production
APP_DEBUG=true
APP_LOCAL_URL=http://localhost/dorguzen/
APP_LIVE_URL=https://www.dorguzen.com/
APP_FILE_ROOT_PATH_LOCAL=/dorguzen/
APP_FILE_ROOT_PATH_LIVE=/

APP_EMAIL=your@email.com
APP_CONTACT_TEL=
APP_POST_ADDRESS=

APP_JWT_SECRET=your-secret-key
APP_JWT_ENCODING=HS256

QUEUE_DRIVER=sync        # sync (dev) | db (production)

APP_LOG_DRIVER=file      # file | db | both
APP_LOG_FORMAT=text      # text | json

ALLOW_REGISTRATION=true

# Modules (on | off)
MODULES_SEO_STATUS=on
MODULES_PAYMENTS_STATUS=off
MODULES_SMS_STATUS=off
MODULES_GALLERY_STATUS=off
MODULES_VIDEOS_STATUS=off
MODULES_BLOG_STATUS=off

# API
API_DOCS_ENABLED=false
APP_API_CSRF_EXCEPTION=/api/

# Slack (optional)
SLACK_WEBHOOK_URL=
SLACK_DEFAULT_CHANNEL=#general
```

---

## Config Files (`configs/`)

Config files are plain PHP arrays loaded at boot and accessible via the `config()` helper.

| File | Purpose |
|---|---|
| `configs/app.php` | App identity, URLs, upload dirs, JWT, queue driver, module toggles, role permissions |
| `configs/database.php` | Database credentials and connection settings |
| `configs/events.php` | Event-to-listener mappings |

### Reading config values

```php
// Read from .env directly
$debug = env('APP_DEBUG', false);

// Read from a config file
$appName  = config('app.appName');
$dbConfig = config('database.DBcredentials');
```

The `Config` class (`src/config/Config.php`) is the typed accessor injected into models and services via the DI container. Prefer `$this->config` over calling `config()` inside model constructors.

---

## Important: `.env` Key Names

The env key read by `configs/app.php` for the local file root path is `APP_FILE_ROOT_PATH_LOCAL`, not `FILE_ROOT_PATH_LOCAL`. Using the wrong key name causes the framework to fall back to the default value, which breaks URL routing. Always verify your key names match what the config file reads.

---

## Module Toggles

Modules are enabled or disabled via `.env`. When a module is `off`, its routes still exist in `routes/web.php` but the controller handles the disabled state gracefully.

```php
// configs/app.php
'modules' => [
    'seo'      => env('MODULES_SEO_STATUS', 'on'),
    'gallery'  => env('MODULES_GALLERY_STATUS', 'off'),
    'videos'   => env('MODULES_VIDEOS_STATUS', 'off'),
    'blog'     => env('MODULES_BLOG_STATUS', 'off'),
    'payments' => env('MODULES_PAYMENTS_STATUS', 'off'),
    'sms'      => env('MODULES_SMS_STATUS', 'off'),
],
```

---

## Role Permissions

Fine-grained admin permissions are defined in `configs/app.php` under `permissions`.

```php
'permissions' => [
    'seo'          => ['admin', 'admin_gen', 'super_admin'],
    'manage_users' => ['admin', 'admin_gen', 'super_admin'],
    'settings'     => ['super_admin'],
],
```

---

## Config Cache

Config values are cached to `bootstrap/cache/config.php` for performance. Delete this file after any `.env` change to force a rebuild — the framework regenerates it automatically on the next request.

> Do not edit `bootstrap/cache/config.php` directly. It is auto-generated.

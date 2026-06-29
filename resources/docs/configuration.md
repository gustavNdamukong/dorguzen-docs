# Configuration

## Introduction

This refers to the various ways in which the configuration of your application can be handled in code at runtime. A good example is in the use of environmental variables, typically in an env file. It also involves all the knowledge around setting up your work station and tech stack for optimal performance. Therefore, see Configuration to mean Settings. It also involves information on the best practice for organising your project directory and file structure.

---

> The **directory structure** has its own page — see [Directory Structure](/dorguzen-docs/docs/directory-structure).

---

## Dorguzen Configuration System

The Dorguzen configuration system provides a centralized, cached, extensible, and environment-aware way to manage application settings across the entire framework.

It is designed around three core principles:

Single source of truth - configuration is loaded once and reused everywhere.

Zero re-instantiation - configuration is never reloaded or re-created.

Multiple access styles - global, helper-based, and DI-based access are all supported.

### Files Involved

**src/config/ConfigLoader.php**

Responsible for discovering, loading, merging, and caching all configuration files.

Reads configuration files from:

configs/

configs/modules/

Supports multiple formats:

PHP

XML

YAML / YML

Handles config caching for performance

Returns a merged configuration array

This class does not expose configuration globally. It only loads it.

**src/config/Config.php**

The configuration repository.

This class:

Stores the final merged configuration array

Provides read/write access to config values

Acts as the single source of truth for config data

Common methods include:

```
all()

get(string $key, mixed $default = null)

has(string $key)

set(string $key, mixed $value)

getConfig() (backward compatibility)

getFileRootPath()

getHomePage()

…and other framework-specific helpers
```

Important:
Config does not load files. It only stores and retrieves configuration data.

**src/config/EnvLoader.php**

Responsible for loading .env files and exposing their values globally.

Reads .env key/value pairs

Injects them into $_ENV

Works seamlessly with the env() helper

You do not need to understand its internals to use it — only how to consume .env values.

### Directories Involved

**src/config/**

Contains all core configuration-related classes:

Config

ConfigLoader

EnvLoader

**configs/**

The primary configuration directory for your application.

Example files:

```
configs/
├── app.php
├── database.php
├── mail.yaml
└── cache.xml
```

**configs/modules/**

Optional but recommended directory for module-specific configuration.

Example:

```
configs/modules/
├── blog.php
├── auth.yaml
└── payments.xml
```

All module configs are:

Automatically discovered

Automatically merged

Namespaced by filename

**bootstrap/cache/ ⚠️ Required**

This directory must exist and be writeable.

Used for:

Cached merged configuration

Cache metadata/signatures

```
bootstrap/cache/
├── config.php
└── config.php.meta
```

If this directory is missing or not writeable, configuration caching will fail.

### How the System Works

**Configuration Bootstrap (bootstrap/config.php)**

The configuration system is bootstrapped early in the application lifecycle.

Key steps:

```php
// Safety check
if (!defined('DGZ_BASE_PATH')) {
    throw new DGZ_Exception(
        'config.php must be loaded via index.php',
        DGZ_Exception::INVALID_CONFIG
    );
}

// Paths
$configDir = DGZ_BASE_PATH . '/configs';
$cacheFile = DGZ_BASE_PATH . '/bootstrap/cache/config.php';

// 1) Load .env (optional)
$envLoader = new EnvLoader(DGZ_BASE_PATH);
$envLoader->load('.env');

// 2) Load config files (with caching)
$loader = new ConfigLoader($configDir, $cacheFile, true);
$configArray = $loader->load();

// 3) Create config repository
$GLOBALS['config'] = new Config($configArray);

// 4) Register in DI container
$container = $GLOBALS['container'];
$container->singleton(Config::class, fn () => $GLOBALS['config']);

// 5) Return repository
return $GLOBALS['config'];
```

### Accessing Configuration Data

Rule:
ONLY ONE INSTANCE of Config should ever exist.
Never instantiate it manually.

Dorguzen provides three safe and supported access methods.

#### 1️⃣ Via $GLOBALS

```php
$GLOBALS['config']->getConfig();
$GLOBALS['config']->get('app');
$appName = $GLOBALS['config']->get('app.name');
$debug = $GLOBALS['config']->get('app.debug', false);
$GLOBALS['config']->all();
```

✔ Direct
✔ Fast
✖ Verbose

#### 2️⃣ Via the config() Helper (Recommended)

Defined in bootstrap/helpers.php.

```php
config('app.name');
config('database.connections.mysql.host');
config('app.debug', false);
```

This is functionally equivalent to:

```php
$GLOBALS['config']->get('app.name');
```

✔ Clean
✔ Readable
✔ Preferred in application code

#### 3️⃣ Via the DI Container

```php
use Dorguzen\Config\Config;

$config = container(Config::class);

$config->get('app.name');
$config->getConfig(); // backward compatibility
```

✔ Ideal for services, models, and controllers
✔ Test-friendly
✔ Framework-consistent

### Why bootstrap/config.php Returns the Config Object

At the end of bootstrap/config.php:

```php
$GLOBALS['config'] = new Config($configArray);
return $GLOBALS['config'];
```

This enables two valid usage styles.

**Style 1: Global (Current)**

```php
require_once 'bootstrap/config.php';
config('app.name');
```

**Style 2: Explicit (Future-Proof)**

```php
$config = require 'bootstrap/config.php';
$config->get('app.name');
```

Benefits

CLI compatibility

Testability

Optional DI usage

Zero downside

Matches patterns used by Laravel, Symfony, and Slim

👉 Think of bootstrap/config.php as a factory file, not just a script.

---

## Dorguzen Environment Configuration (.env)

Dorguzen uses environment variables to store sensitive and environment-specific configuration such as database credentials, cache drivers, API keys, and runtime modes (local, staging, production).

This system is powered by:

Dorguzen's EnvLoader

The battle-tested vlucas/phpdotenv package

The global env() helper

As a framework user, you do not need to understand the internals — only how to use .env files correctly.

### 1. Getting Started: .env.example

Dorguzen ships with two files named:

```
.env.example
.env.local.example
```

These files serve as a template showing all environment variables your application expects.

First step after installing Dorguzen

```
cp .env.example .env
```

and

```
cp .env.local.example .env.local
```

You will now work only with .env and .env.local, while keeping .env.example and .env.local.example as references only.

✅ .env.example and .env.local.example are committed to Git
❌ .env and .env.local must never be committed

### 2. The .env Files

The .env files contain key-value pairs, one per line.

**Supported syntax**

Both quoted and unquoted strings, as well as booleans (true/false) are supported:

```ini
APP_NAME="ProjectName"
APP_ENV=local
APP_SLOGAN="The Best Service in Town"
CACHE_DRIVER=file
```

✔ Quotes are optional
✔ Spaces are allowed inside quoted strings
✔ Comments are allowed using #

### 3. Environment Detection (APP_ENV)

The variable APP_ENV tells Dorguzen which environment the application is running in.

Example:

```ini
APP_ENV=local
```

Common values include:

local

staging

production

This value is used by Dorguzen to determine which additional .env files to load.

### 4. Environment-Specific .env Files

Dorguzen supports multiple environment files, loaded in a predictable order.

**Load order (important)**

.env (base defaults)

.env.{APP_ENV} (environment-specific)

.env.local (local machine overrides)

**Example**

If your .env contains:

```ini
APP_ENV=local
CACHE_DRIVER=file
```

And your .env.local contains:

```ini
CACHE_DRIVER=redis
```

Then the effective value will be:

```php
env('CACHE_DRIVER'); // redis
```

**Common patterns**

| File | Purpose |
|---|---|
| .env | Base configuration |
| .env.local | Local developer overrides |
| .env.staging | Staging server config |
| .env.prod | Production server config |

You can introduce as many environments as you like — Dorguzen will automatically load the matching file based on APP_ENV.

### 5. Git & Security Best Practices

These files must be ignored:

```
.env
.env.local
.env.staging
.env.prod
```

This file must be committed:

```
.env.example
```

This ensures:

Secrets never leak to GitHub

New developers know which variables are required

Production credentials remain server-only

In production, .env files are typically created directly on the server or injected via deployment tools, Docker, or CI/CD pipelines.

### 6. Using Environment Variables in Config Files

Environment variables are accessed using the global env() helper.

**Example: configs/app.php**

```php
return [
    'appName' => env('APP_NAME'),
    'environment' => env('APP_ENV'),
    'cache_driver' => env('CACHE_DRIVER', 'file'),
];
```

**Example: Database credentials**

```php
'localDBcredentials' => [
    'username'          => env('DB_LOCAL_USERNAME'),
    'pwd'               => env('DB_LOCAL_PASSWORD'),
    'db'                => env('DB_LOCAL_DATABASE'),
    'host'              => env('DB_LOCAL_HOST'),
    'connectionType'    => env('DB_LOCAL_CONNECTION'),
    'key'               => env('DB_LOCAL_KEY'),
],
```

The second argument to env() is an optional fallback value.

### 7. How env() Works (Conceptual)

The env() helper reads from PHP's environment:

getenv()

$_ENV

Fallback value (if provided)

Once loaded at bootstrap:

Environment variables are available globally

No container access is required

No performance cost at runtime

This makes environment variables ideal for:

Secrets

Credentials

Feature flags

Environment detection

### 8. Important Guidelines

✅ Do

Use env() only inside config files

Store secrets in .env, not in PHP code

Use .env.local for personal overrides

❌ Don't

Call env() deep inside application logic

Commit .env files

Hard-code credentials in configs

Once config files are loaded, your application should rely on config() — not env().

### 9. Summary

Dorguzen uses .env files for environment configuration

.env.example is your starting point

.env.local and .env.{APP_ENV} allow safe overrides

APP_ENV controls environment selection

env() bridges .env values into config files

Sensitive data never touches Git

### 10. URL and Path Variables per Environment

Dorguzen's Config class uses several .env variables to serve the correct URLs and asset paths depending on which environment the app is running in. It is important to set these correctly in every environment, or assets, links, and emails will point to the wrong place.

Key variables and their purpose:

```
Variable               Purpose
---------------------------------------------------------------------------
APP_ENV                Identifies the environment. Recognised values: local,
                       testing, staging, qa, prod, production.

APP_URL                The full base URL of the application in this environment.
                       Used by Config::getHomePage() and anywhere an absolute
                       URL is needed (e.g. unsubscribe links in emails).
                       Set this to the correct URL for every environment.

                         local:    APP_URL=http://localhost/myapp
                         staging:  APP_URL=https://staging.myapp.com
                         prod:     APP_URL=https://myapp.com

APP_FILE_ROOT_PATH_LOCAL   The URL path prefix used when the app runs locally
                           under a web server sub-folder (e.g. MAMP).
                           Example: /myapp/
                           Used by Config::getFileRootPath() when APP_ENV is
                           local or testing.

APP_FILE_ROOT_PATH_LIVE    The URL path prefix used on deployed servers.
                           Typically / (the app lives at the domain root).
                           Used by Config::getFileRootPath() for staging, qa,
                           prod, and production.
```

How Config::getFileRootPath() decides which to use:

```
  APP_ENV=local or testing  → returns APP_FILE_ROOT_PATH_LOCAL  (e.g. /myapp/)
  Any other environment     → returns APP_FILE_ROOT_PATH_LIVE   (e.g. /)
```

How Config::getHomePage() works:

```
  Always returns APP_URL for the current environment, trimmed of trailing slash.
  This means you only need to set APP_URL correctly in each environment's .env
  and all absolute URL generation (links, emails, redirects) will work everywhere.
```

Common mistake to avoid:

```
  Before Dorguzen supported multiple named environments, the live/local switch
  was driven by a boolean LIVE_ENV flag. If you see LIVE_ENV or APP_LIVE in an
  older .env file, those are legacy — APP_ENV is the correct switch now.

  Also ensure your .env variable names match exactly what configs/app.php reads.
  For example, FILE_ROOT_PATH_LOCALL (two L's) is a typo that silently falls
  through to the default — always verify against configs/app.php.
```

### 11. Server Upload Limits and the PHP SAPI (.htaccess and .user.ini)

This section explains how Dorguzen controls PHP upload and memory limits, why two separate configuration files are used, and what you need to know when deploying to different server environments.

**What is a PHP SAPI?**

SAPI stands for Server API. It is the layer that connects your web server (Apache, Nginx, etc.) to PHP — in other words, it is the mechanism by which Apache hands an incoming HTTP request to PHP for processing.

There are two SAPIs you will commonly encounter:

```
  mod_php
    PHP is loaded directly inside Apache as a module. Apache and PHP are tightly
    coupled. Configuration for PHP can be set inside .htaccess using the php_value
    directive. This was the standard approach for many years and is still used on
    some servers.

  PHP-FPM (FastCGI Process Manager)
    PHP runs as a completely separate process from Apache. Apache passes requests
    to PHP-FPM over a FastCGI connection. They are decoupled. This is the modern
    standard and what most up-to-date servers (including newer versions of MAMP)
    use by default.

    Because PHP is a separate process with PHP-FPM, Apache's php_value directives
    in .htaccess have no effect — Apache cannot reach across into the PHP process
    to set configuration values. A different mechanism is needed.
```

**The key question: which SAPI is my server using?**

```
  If you are using a modern version of MAMP, the answer is almost certainly PHP-FPM.
  If you are on a traditional cPanel shared hosting plan, it may be mod_php.
  If you are on a modern VPS or cloud server (DigitalOcean, AWS, etc.) it is
  almost certainly PHP-FPM.

  You do not need to be certain — Dorguzen ships both configuration files so the
  correct one is always present regardless of the environment.
```

**What .htaccess does (and what it does not do)**

The .htaccess file in the project root has two jobs:

```
  1. URL rewriting — redirect every request to index.php (the front controller),
     unless the request is for a real file on disk (CSS, images, JS, etc.).
     This is handled by Apache's mod_rewrite module and works identically on both
     mod_php and PHP-FPM. The SAPI does not affect URL rewriting at all.

  2. PHP settings (mod_php only) — set upload limits and memory using php_value
     directives inside <IfModule mod_php8.c> blocks. These are silently ignored
     when the server uses PHP-FPM.
```

Dorguzen's .htaccess currently contains:

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

Blocks for both mod_php7.c and mod_php8.c are included to cover PHP 7.x and 8.x mod_php installations respectively.

**What .user.ini does (PHP-FPM only)**

PHP-FPM reads a special file called .user.ini from the document root of each project. Think of it as the PHP-FPM equivalent of the php_value directives in .htaccess. It uses standard PHP ini syntax — plain key = value pairs, no Apache directives, no module wrappers.

Dorguzen's .user.ini currently contains:

```ini
  upload_max_filesize = 50M
  post_max_size = 50M
  memory_limit = 256M
```

This file is read automatically by PHP-FPM on every request and requires no special setup. It also works on shared hosting running PHP-FPM where you do not have access to the server's main php.ini — making it the correct solution for the majority of modern hosting environments.

**Why both files exist**

Dorguzen ships both .htaccess and .user.ini so that the same upload limits apply regardless of which SAPI the server uses:

```
  Server uses mod_php  → .htaccess php_value blocks take effect
                          .user.ini is ignored (PHP-FPM is not running)

  Server uses PHP-FPM  → .user.ini takes effect
                          .htaccess php_value blocks are silently skipped
```

You do not need to choose one or delete the other. Keep both files in place and the correct one will be picked up automatically.

**What happens when an upload exceeds the limit**

PHP has two relevant ini settings that work together:

```
  post_max_size       — the maximum size of the entire HTTP POST body (all form
                        fields plus all uploaded files combined).

  upload_max_filesize — the maximum size of a single uploaded file.
```

post_max_size must always be equal to or larger than upload_max_filesize, because the uploaded file travels inside the POST body.

If a request body exceeds post_max_size, PHP silently discards the entire $_POST array before your application runs. This has an important side effect: the CSRF token (which travels in $_POST) also disappears, causing the CSRF middleware to appear to fail with a misleading "Invalid or missing CSRF token" error.

Dorguzen's CsrfPsrMiddleware detects this situation before checking the CSRF token. When it finds that the incoming Content-Length exceeds post_max_size, it throws a clear exception:

```
  "Uploaded file exceeds server max size limit"
  "The data you submitted (X MB) exceeds the server's maximum allowed POST size
  (50M). Please upload a smaller file."
```

This means you will always get a meaningful error instead of a confusing CSRF failure when a file is too large.

Note: PHP emits its own low-level warning about this ("POST Content-Length of X bytes exceeds the limit of Y bytes in Unknown on line 0") before your application code runs. This warning appears in the browser on local/development environments because display_errors is on. It is suppressed automatically in production because Dorguzen calls setupErrorHandling($env) in index.php, which turns display_errors off for any environment that is not local.

### 12. Key Application Variables Reference

The narrative above explains how the `.env` system works. The table below is the concrete reference of the application-level variables Dorguzen reads in `configs/app.php` (and `configs/logging.php`). Each is consumed via `env()` with the fallback shown, so an unset variable degrades to a safe default rather than failing.

```
Variable                  configs key             Purpose / notes
-----------------------------------------------------------------------------
APP_LOCAL_URL             app.localUrl            Base URL when running locally
                                                  (e.g. http://localhost/myapp/).
APP_LIVE_URL              app.liveUrl             Base URL on the deployed server.
APP_LIVE_URL_SECURE       app.liveUrlSecure       HTTPS base URL for the live site.
APP_EMAIL                 app.appEmail            Admin recipient address used by
                                                  DGZ_Messenger. MUST NOT be empty —
                                                  it is also the default From/Reply-To
                                                  (localHeaderFrom, liveHeaderFrom,
                                                  headerReply-To all read APP_EMAIL).
APP_CONTACT_TEL           app.site_contact_tel    Public contact phone number.
APP_POST_ADDRESS          app.site_postal_address Public postal address.
APP_JWT_SECRET            app.jwt-secret-key      Secret key for signing API JWTs.
APP_JWT_ENCODING          app.encoding_algorithm  JWT signing algorithm (e.g. HS256).
APP_API_CSRF_EXCEPTION    app.csrf_except         Path prefix exempt from CSRF
                                                  validation. Set to '/api/' to exempt
                                                  all API routes (array_filter drops it
                                                  when unset, so no blanket exemption).
QUEUE_DRIVER              app.queue_driver        Queue backend: sync (dev) | db |
                                                  rabbitmq (production).
ALLOW_REGISTRATION        app.allow_registration  Gates public self-service signup;
                                                  new registrants default to 'member'.
APP_LOG_DRIVER            logging driver          Where logs go: file | db | both.
APP_LOG_FORMAT            logging format          Log line format: text | json.
API_DOCS_ENABLED          (read directly)         When true, exposes the API docs
                                                  endpoint (DocsController). Leave
                                                  false/unset in production.
SLACK_WEBHOOK_URL         (read by Slack channel) Incoming-webhook URL for Slack
                                                  notifications.
SLACK_DEFAULT_CHANNEL     (read by Slack channel) Optional override of the webhook's
                                                  own default channel (e.g. #general).
```

Module on/off flags follow the same pattern in `app.modules`:

```ini
MODULES_SEO_STATUS=on        # on | off
MODULES_PAYMENTS_STATUS=off
MODULES_SMS_STATUS=off
MODULES_GALLERY_STATUS=off
MODULES_VIDEOS_STATUS=off
MODULES_BLOG_STATUS=off
MODULES_TESTIMONIALS_STATUS=off
```

Beyond the format examples shown earlier, the `configs/` directory ships these real PHP config files: `app.php` (identity, URLs, upload dirs, JWT, queue driver, module toggles, role permissions), `database.php` (DB credentials/connections), `events.php` (event → listener map), and `logging.php` (log driver/format).

---

## Role Permissions

Fine-grained admin permissions are defined in `configs/app.php` under the `permissions` key. Each entry maps a capability to the list of roles allowed to use it:

```php
// configs/app.php
'permissions' => [
    'seo'          => ['admin', 'admin_gen', 'super_admin'],
    'payments'     => ['admin_gen', 'super_admin'],
    'manage_users' => ['admin', 'admin_gen', 'super_admin'],
    'settings'     => ['super_admin'],
],
```

Read these in code via the config repository (e.g. `config('app.permissions.settings')`) to check whether the current user's role is authorised for an action. Note that `settings` is restricted to `super_admin` — the most privileged role — while broader capabilities like `seo` and `manage_users` also admit `admin` and `admin_gen`.

---

## Customising a New Application After Cloning

When you clone or install a fresh Dorguzen application, there are two kinds of customisation you will want to do immediately: rename the application and set its colour theme. These are handled in two different places by design.

### 1. Rename Your Application

Open your .env file and update the following variables to reflect your new project:

```ini
    APP_NAME=my-app-slug          # used internally (e.g. for logging)
    APP_BUSINESS_NAME="My App"    # the human-readable brand name shown in the UI
                                  # and in email headers
    APP_SLOGAN="Your tagline here"
    APP_URL=http://localhost/my-app   # base URL for this environment
```

These values are read by configs/app.php via env() and are available throughout the application via $this->config->get('app.appName') etc., and are injected automatically into email layouts.

Do NOT hardcode the app name in layout files or views — always read it from config so a single .env change renames the whole application consistently.

### 2. Set the Colour Theme

The colour theme is NOT set in .env. It is controlled at runtime through the admin panel and stored in the baseSettings database table.

After logging in as an admin, navigate to:

```
    Admin Dashboard → Settings
```

Find the app_color_theme field and enter a valid CSS colour value — any hex code, rgb(), or named colour works:

```
    Examples:  #e63946   #3a86ff   #2ecc71   rgb(52, 152, 219)
```

How the override mechanism works:

```
  Every layout file (seoMasterLayout.php, adminLayout.php, etc.) reads the
  app_color_theme value from the database via $this->config->getAppColorTheme()
  and injects it as an inline <style> block in the page <head>:

      <style>:root { --site-theme: #3a86ff; }</style>

  This inline declaration overrides the default value defined in assets/css/style.css:

      :root {
          --site-theme: #fd7e14;  /* default orange — overridden by DB value at runtime */
      }

  Because inline <style> has higher specificity than an external stylesheet, the
  DB value always wins on every page load. No CSS file needs to be edited, no
  deployment is required — the change is instant and affects the entire site.

  Email layouts (layouts/email/defaultEmailLayout.php) use the same value via the
  $accentColour variable, which is injected by DGZ_Messenger::renderEmail() the
  same way.
```

What --site-theme controls:

```
  The CSS variable --site-theme is referenced throughout assets/css/style.css for:
  - Primary button background and border colours (.btn-primary, .bg-primary)
  - Outline button colours (.btn-outline-primary)
  - Navigation link and nav-item colours
  - Text highlights (.text-primary, .text-secondary)
  - Hero section button accent colours
  - Team card and testimonial carousel accent colours
  - Breadcrumb separator and back-to-top button icon
  - Email header background and footer accent bar

  Changing app_color_theme in the admin panel therefore recolours the entire
  application at once with no code changes.
```

### 3. Summary: Two Different Customisation Paths

```
  What you're changing         Where to change it
  ---------------------------------------------------------------
  App name, slogan, URL        .env  (APP_NAME, APP_BUSINESS_NAME, APP_URL, etc.)
  Colour theme                 Admin Dashboard → Settings → app_color_theme
  Layout structure / markup    layout PHP files in layouts/
  CSS overrides beyond colour  assets/css/style.css
```

Never hardcode the app name or the colour value in templates — keep both as single points of truth so they stay consistent across views, emails, and admin.

---

## Security Recommendations

✅ Never commit .env to Git

✅ Add .env to .gitignore

✅ Use .env.example for documentation

✅ Store secrets only in .env

✅ Load secrets into config via env()

❌ Do not hard-code credentials in config files

In production:

Use environment-level variables

Lock down file permissions

Ensure bootstrap/cache/ is writable but protected

**Summary**

✔ Centralized configuration
✔ Cached for performance
✔ Module-aware
✔ Environment-aware
✔ Single instance
✔ Multiple access styles

---

## Runtime Database Settings (baseSettings)

### What baseSettings Is

Dorguzen ships with a database table called `baseSettings`. Its purpose is to hold a small number of settings that a site administrator needs to be able to change at runtime through the admin panel — without touching any config files, redeploying, or restarting the server.

These settings are loaded lazily by the Config class when first needed and are kept entirely separate from the file-based configuration loaded at bootstrap. They do not live in `configs/app.php`, they are not in `.env`, and they are not part of the config cache. They are always read live from the database.

### The Two-Tier Settings Design

Dorguzen intentionally splits application settings into two tiers:

```
  Tier 1 — File-based config (bootstrap time)
    Loaded from `configs/` files and `.env` at the very start of every request.
    Cached for performance. Controlled by developers and deployment pipelines.
    Accessed via config('app.key') or $this->config->get('app.key').

  Tier 2 — Database settings (runtime, admin-controlled)
    Stored in the `baseSettings` DB table.
    Loaded lazily on first use during a request.
    Controlled by site administrators through the admin panel.
    Accessed via $this->config->getBaseSettings()['key'].
```

The division is a deliberate design decision: settings that are infrastructure or code decisions belong in files, while settings that a non-developer site admin might legitimately want to adjust belong in the database.

### What Belongs in File Config (Bootstrap Settings)

Use `configs/app.php` and `.env` for settings that:

```
  - Are set once at deployment and rarely change
  - Require a server-level or code-level change to modify safely
  - Contain sensitive data (database credentials, API keys, JWT secrets)
  - Must be available before any DB connection is established
  - Are developer decisions, not admin decisions
```

Examples:

```
  - APP_NAME, APP_URL, APP_ENV
  - Database credentials (DB_HOST, DB_USERNAME, DB_PASSWORD)
  - JWT secret key
  - Mail/SMTP credentials
  - Whether the app is live or local
  - Default locale and fallback locale
  - Module on/off flags (seo, payments, sms)
  - allow_registration (a deployment decision, not a runtime toggle — gates public
    self-service signup; new registrants default to the 'member' role. See
    "Public registration & the default member role" under User Roles.)
```

### What Belongs in baseSettings (Runtime Settings)

Use the `baseSettings` DB table for settings that:

```
  - A site admin should be able to change through the UI without code access
  - Affect the visual appearance or behaviour of the site, not its infrastructure
  - Do not contain sensitive data
  - Are safe for a non-developer to change
  - Take effect immediately on the next page load without any deployment step
```

Examples of settings that belong here:

```
  - brand_slider_source — the path to the directory containing the slider images
  - app_color_theme     — the CSS colour theme applied to the site
  - Any future UI toggle an admin should control (e.g. maintenance mode banner,
    show/hide a promotions section, active site theme name)
```

A good rule of thumb: if changing the setting requires the admin panel, it belongs in baseSettings. If changing it requires editing a file or running a deployment, it belongs in file config.

### Accessing baseSettings in Code

The Config class provides a dedicated method for reading baseSettings. It is lazy-loaded — the database is only queried the first time it is called on a given request.

```php
    // In a controller, layout, or view partial:
    $baseSettings = $this->config->getBaseSettings();

    $colorTheme = $this->config->getAppColorTheme();
    // equivalent to $this->config->getBaseSettings()['app_color_theme']
```

Important: do NOT mix the two tiers. Do not call config('app.app_color_theme') — that key does not exist in the file config. And do not call getBaseSettings() to read database credentials — they are not in the DB table. Each tier has its own access method for a reason.

### The baseSettings DB Table

The table has a simple structure:

```
    settings_id    INT  (primary key, auto-increment)
    settings_name  VARCHAR  (the key, e.g. 'brand_slider_source')
    settings_value VARCHAR  (the value, e.g. 'true')
```

Current entries:

```
    brand_slider_source  e.g. 'assets/images/gallery'
                         The path (relative to the app root) of the directory
                         from which the brand slider pulls its images.
                         See the Brand Slider section below for full details.

    app_color_theme      e.g. 'dark-blue'
                         The name of the CSS colour theme file to load.
                         Available themes are the CSS files in assets/css/color/.
                         The value here must match a filename in that directory
                         (without the .css extension).
```

### Managing baseSettings via the Admin Panel

Log in as an admin and navigate to:

```
    Admin Dashboard → Settings
```

This page reads all rows from the `baseSettings` table and renders a form field for each one. Submitting the form updates each setting's value in the database. Changes take effect on the next page load — no restart or deployment required.

To add a new runtime setting:

```sql
  1. Insert a row directly into the `baseSettings` table:
         INSERT INTO baseSettings (settings_name, settings_value)
         VALUES ('my_new_setting', 'default_value');
  2. Add a corresponding form field for it in views/admin/manageSettings.php.
  3. Read it in your code via $this->config->getBaseSettings()['my_new_setting'].
```

---

## The Brand Slider

### What It Is

The brand slider is a horizontal image carousel that sits between the main page content and the footer. It is designed to showcase a set of images — typically brand logos, product photos, or portfolio images — in a continuously scrolling strip. It is powered by Owl Carousel.

It is a per-page opt-in feature: it is off by default on every page. Each controller method that wants the slider must explicitly enable it. The two supported layouts are:

```
  - layouts/seoMaster/seoMasterLayout.php   (the recommended default layout)
  - layouts/dorguzApp/dorguzAppLayout.php   (the older full-featured layout)
```

### How to Enable It (Per Page)

The brand slider is controlled from the controller via the inherited `setImageSlider()` method. Call it before rendering the view:

```php
    public function home(): void
    {
        $this->setImageSlider(true);   // show the slider on this page only

        $view = DGZ_View::getView('home', $this, 'html');
        $view->show($this->homeService->homePayload());
    }
```

Pages that do not call `$this->setImageSlider(true)` will never show the slider. The layout checks the `$this->showImageSlider` property (which defaults to false) and skips the carousel HTML entirely — no empty space, no placeholder.

This is preferable to a global DB toggle because:

```
  - Different pages can make independent decisions
  - A developer can clearly see from the controller whether a page has a slider
  - No database round-trip is needed just to decide whether to render a section
```

### How to Configure the Image Source (brand_slider_source)

The `brand_slider_source` setting tells the slider where to find its images. Its value is a path relative to the application root — not a URL, not an absolute server path. For example:

```
    assets/images/gallery
```

This means the slider will look for images inside:

```
    /your-app-root/assets/images/gallery/
```

To change it, update the DB value:

```sql
    UPDATE baseSettings SET settings_value = 'assets/images/myimages'
    WHERE settings_name = 'brand_slider_source';
```

Or change it through the admin panel Settings page.

The path must be relative to the application root (the directory containing index.php). Do not include a leading or trailing slash. Do not use a URL — the framework resolves the file system path and the browser URL separately from this single relative value.

### Adding Your Images

Simply drop image files into the directory pointed to by `brand_slider_source`. The slider automatically picks up every .jpg, .jpeg, .png, .gif, and .webp file in that directory. There are no filenames to configure and no array to maintain.

Steps:

```
  1. Decide on your image directory (e.g. assets/images/gallery/).
  2. Update brand_slider_source in the DB to match (e.g. 'assets/images/gallery').
  3. Drop your images into that directory.
  4. Call $this->setImageSlider(true) in any controller method that should show it.
  5. Load that page — the slider appears between the content and the footer.
```

To remove an image from the slider, delete the file from the directory.
To add more images, drop them into the directory. No code changes needed.

Dorguzen ships with a default gallery directory at assets/images/gallery/ seeded with six demo images. These are placeholder images and should be replaced with your own before going live.

For best visual results use images that are the same dimensions as each other. Wide landscape images (e.g. 300×200px) work best for brand/logo sliders.

### How It Works Internally

In both seoMasterLayout and dorguzAppLayout, the brand slider block:

```
  1. Checks $this->showImageSlider (a boolean property on DGZ_Layout, defaulting
     to false). This value is set by the framework automatically from the
     controller's own $showImageSlider property, which the developer sets via
     $this->setImageSlider(true) before calling $view->show().

  2. If $this->showImageSlider is false, nothing is rendered.

  3. Calls $this->config->getBaseSettings() to retrieve brand_slider_source,
     then builds the absolute file system path from DGZ_BASE_PATH and that value,
     using PHP's glob() to discover all image files in that directory.

  4. Renders an <img> tag for each file found, building the browser URL from
     getFileRootPath() (which resolves correctly for both local and live
     environments) and the relative source path.

  5. The #brands-carousel div is initialised by Owl Carousel, which is already
     loaded by both layouts' html_dependencies files.
```

The brand slider shares the same Owl Carousel library used by other carousels in the layouts — no additional JavaScript or CSS dependencies are needed.

---

> The **SEO module** has its own page — see [SEO Module](/dorguzen-docs/docs/seo).

---

## The assetVer() Helper — Cache-Busting for CSS & JavaScript

When a browser loads your site, it saves ("caches") your CSS and JavaScript files on the visitor's device so the next visit loads faster. The catch: after you edit a file like style.css and upload the new version, many browsers keep serving the OLD cached copy for a while — so your visitors don't see your change. This is the classic "I changed the CSS but the old style still shows" problem, and it's especially stubborn on mobile (iPhone Safari).

- **Cache:** a local copy a browser keeps of a file so it doesn't re-download it every visit.
- **Cache-busting:** a trick that makes the browser treat an updated file as a brand-new file, so it downloads the fresh copy straight away.

Dorguzen solves this with the global helper `assetVer()` (defined in `bootstrap/helpers.php`). It returns the asset's URL with a version "stamp" appended — the file's last-modified time:

```
/assets/css/style.css?v=1719357141
```

Because the number after `?v=` changes the moment you edit the file, the URL changes too, so every browser (phones included) treats it as a new file and fetches the latest version. No visitor ever has to clear their cache.

Use it in your layouts and views for any LOCAL css or js file, in place of the raw path:

```php
Before:
<link href="<?= $this->config->getFileRootPath() ?>assets/css/style.css" rel="stylesheet">
<script src="<?= $this->config->getFileRootPath() ?>js/main.js"></script>

After:
<link href="<?= assetVer('assets/css/style.css') ?>" rel="stylesheet">
<script src="<?= assetVer('js/main.js') ?>"></script>
```

The path you pass is relative to your project root (e.g. `'assets/css/style.css'`). The helper safely checks the file exists before stamping it, so a wrong path never throws an error. Use it only for your OWN files — external CDN links (e.g. Bootstrap loaded from a CDN) already carry a version in their URL, so they don't need it.

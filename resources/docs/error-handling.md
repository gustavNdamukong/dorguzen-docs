# Error Handling

This discusses the tools made available by your programming language to find bugs in your code. It also talks about conventions and best practices for writing reliable, performant and fault-tolerant applications.

- Performance pitfalls are identified here and work-arounds given.
- Testing talks about all the tools your programming language offers you for testing code, including examples of how it is done.

This section covers:

- How Dorguzen catches errors and exceptions (development vs production)
- The `DGZ_Exception` class and its type constants
- Production error alerts
- Validation errors and custom exception handling
- The awesome `dgzie()` helper function
- Writing custom logs for your application
- Creating custom logs with `DGZ_Logger`
  - Dorguzen Logging System — Full Documentation
- The difference between PHP's `error_log()` and `DGZ_Logger`
- How to write values to console in PHPUnit tests

---

## Development vs Production

Dorguzen catches PHP errors and unhandled exceptions, logs them, and renders an appropriate response — detailed in development, generic in production. The behaviour is controlled by `APP_ENV` in `.env`, which the framework reduces to the `config('live')` flag (`configs/app.php`).

| `APP_ENV` | `config('live')` | Error display |
|---|---|---|
| `local` | `'false'` | Full exception details (dump with file, line, hint and trace) |
| `prod` / `production` | `'true'` | Generic message; details logged but never shown |

Set in `.env`:

```ini
APP_ENV=local   # development
APP_ENV=prod    # production
```

In `local`, `bootstrap/helpers.php` turns on `display_errors` and `error_reporting(E_ALL)`. In production it disables display, enables `log_errors`, and writes to a PHP error log file. In production, full exception details are logged but never shown to the user.

---

## How Exceptions Flow

```
PHP error / thrown exception
    ↓
set_error_handler()  (bootstrap/helpers.php)
    → Converts PHP errors to DGZ_Exception (typed error constant + hint)
    → On production (config('live') === 'true'):
          emails the admin via DGZ_Messenger::sendErrorLogMsgToAdmin()
    → new ExceptionController()->redirect('exception', 'error')
        ↓
ExceptionView
    → on local  (config('live') === 'false'): dump() the error details
    → on production: generic message
```

`DGZ_Controller::display()` additionally wraps controller actions in its own try/catch: an uncaught `DGZ_Exception` (or any `\Throwable`) is rendered through `ExceptionView`, with a last-resort fallback if the view itself fails.

---

## DGZ_Exception

All framework errors are thrown as `DGZ_Exception` (`core/DGZ_Exception.php`). It extends `\Exception` and adds a typed error constant and an optional developer hint.

```php
throw new DGZ_Exception(
    'User not found',
    DGZ_Exception::NO_USER_RECORD,
    'Check that the user ID exists before calling this method.'
);
```

The constructor signature is:

```php
public function __construct($message, $errorType = self::EXCEPTION, $hint = 'No hint available')
```

### Exception type constants

| Constant | Value |
|---|---|
| `EXCEPTION` | `exception` |
| `NO_MENU_DEFINED` | `noMenuDefined` |
| `NO_LAYOUT_FOUND` | `noLayoutFound` |
| `NO_VIEW_FOUND` | `noViewFound` |
| `FIELD_NOT_FOUND_ON_TABLE` | `fieldNotFoundOnTable` |
| `FILE_NOT_FOUND` | `fileNotFound` |
| `CLASS_NOT_FOUND` | `classNotFound` |
| `NO_VALIDATOR_FOUND` | `noValidatorFound` |
| `QUERY_ERROR` | `queryError` |
| `PERMISSION_DENIED` | `permissionError` |
| `MISSING_PARAMETERS` | `missingParametersError` |
| `IDENTIFIER_NOT_FOUND` | `identifierNotFound` |
| `WRONG_PARAMETER_TYPE` | `wrongParameterType` |
| `INCORRECT_USERNAME_PASSWORD` | `incorrectUsernameOrPassword` |
| `NO_USER_RECORD` | `noUserRecord` |
| `WRONG_ADAPTER_FOR_MODEL` | `wrongAdapterForModel` |
| `MISSING_HANDLER_FOR_ACTION` | `missingHandlerForAction` |
| `CONTROLLER_CLASS_NOT_FOUND` | `controllerClassNotFound` |
| `INVALID_INPUT` | `invalidInput` |
| `INVALID_CONFIG` | `invalidConfig` |
| `INVALID_PARAMETER_VALUE` | `invalidParameterValue` |
| `PHP_FATAL_ERROR` / `PHP_ERROR` / `PHP_WARNING` / `PHP_NOTICE` / `PHP_OTHER_ERROR` | PHP native errors converted by the error handler |
| `DATABASE_QUERY_ERROR` | `databaseQueryError` |
| `DATABASE_EXPECTED_RECORD_NOT_RETURNED` | `databaseExpectedRecordNotReturned` |
| `NOT_IMPLEMENTED_EXCEPTION` | `notImplementedException` |
| `NO_CONTEXT_PROVIDED` | `noContextProvided` |
| `GALLERY_ALBUM_NOT_FOUND` | `galleryAlbumNotFoundException` |

Access the type, hint and message on a caught exception:

```php
try {
    // ...
} catch (DGZ_Exception $e) {
    $type = $e->getType();    // e.g. 'noUserRecord'
    $hint = $e->getHint();    // developer hint string
    $msg  = $e->getMessage();
}
```

---

## Production Error Alerts

When the app is live (`config('live') === 'true'`), the error handler in `bootstrap/helpers.php` automatically emails the site admin on every caught PHP error:

```php
$messenger = new DGZ_Messenger();
$messenger->sendErrorLogMsgToAdmin($message);
```

Ensure SMTP is correctly configured in production so these alerts are delivered. See [Email](/docs/email).

---

## Validation Errors

Form and API validation failures use a separate exception class, `Dorguzen\Core\Exceptions\ValidationException`:

```php
use Dorguzen\Core\Exceptions\ValidationException;

throw new ValidationException(
    errors:                  $validatorErrors,
    input:                   $_POST,
    validationErrorMessages: $messages,
    redirectTo:              '/contact'
);
```

The constructor accepts `$errors`, `$input`, `$validationErrorMessages`, `$message`, `$errorCode` and `$redirectTo` (all optional). `DGZ_Router` catches `ValidationException`, builds a combined error message from `getValidationErrorMessages()`, flashes it via `addErrors()`, and redirects. You generally never throw this manually — the validation layer does it for you.

See [Validation](/docs/validation) and [Forms](/docs/forms) for the full flow.

---

## Custom Exception Handling

To handle a specific exception type in a controller, catch it directly and inspect its type:

```php
public function show($id): void
{
    try {
        $post = $this->blogService->getPost($id);
    } catch (DGZ_Exception $e) {
        if ($e->getType() === DGZ_Exception::DATABASE_EXPECTED_RECORD_NOT_RETURNED) {
            $this->addErrors('Post not found.');
            $this->redirect('blog');
            return;
        }
        throw $e; // re-throw anything you don't handle
    }

    $this->display('blog/show', compact('post'));
}
```

Uncaught exceptions bubble up to `DGZ_Controller::display()`, which logs them and renders `ExceptionView`.

---

## The awesome dgzie() helper function

The DGZ framework has a great debugging helper function `dgzie()`, which is a wrapper to Symfony's `VarDumper()`. Here are some notes about it:

- It is a wrapper around Symfony's VarDumper
- It's defined in `bootstrap/helpers.php`
- To use it, just pass it a comma-separated list of things you want to display and halt execution of PHP. This list can be made of variables, literal items, objects, arrays etc. For example:

```php
dgzie($sql, $params);
```

- The output spots very useful information about the code being viewed, like:

    - the line the `dgzie()` function is called on
    - the current request route
    - the state of the current request object
    - and of course, the contents of the items you passed in to be dumped to the screen.
    - As a bonus, it also has good color-coding, and drop-down arrows to reveal nested object and array elements so as to fit nicely on your screen.

---

## Writing custom logs for your application

Beside PHP's built-in error logs which Dorguzen does well to catch and handle elegantly, there are always times when you need to debug a certain feature or piece of code and need to be able to log data to efficiently reverse-engineer and pin-point what is going on. For that you can always use the handy PHP `error_log()` global function anywhere in your code. This will write logs to the `php_error.log` file of your PHP installation. This file on a MAMP installation on a Mac is located here:

```
/Applications/MAMP/logs/php_error.log
```

But you may not always have access to this log file, especially if you are working on a shared remote hosting server. For that you have two options:

- Tell the built-in `error_log()` function to write to a specific log file of your choice. Just pass it the target file location in the third argument (see syntax below).
- or DGZ has its own logging system that you can use. It's provided by the class `Dorguzen\Core\DGZ_Logger.php`.

To use PHP's `error_log()` to write logs to a custom file, you need to use the 3rd argument. Yes, it has 4 arguments, and the following is an explanation of the 4 arguments:

```php
error_log(
  string message,
  int message_type = 0,
  ?string destination = null,
  ?string extra_headers = null
): bool
```

`message_type` can be either 0, 1, 2, 3, or 4:

| Value | Meaning |
|---|---|
| 0 | Send to PHP system logger (default error log) |
| 1 | Send email (requires extra_headers) |
| 2 | Send to debug connection (not commonly used) |
| 3 | ✅ Write message to a file specified in the third argument - the one you need ... |
| 4 | Send directly to SAPI logging handler |

So, when you write:

```php
error_log("DGZ_Request object requested", 3, __DIR__ . '/../storage/logs/dgz_errors.log');
```

You are saying:

```
3 → write to a custom file
third argument → the file path to write to
```

This is the correct way to log to a specific file using `error_log()`.

---

## Creating custom logs with DGZ_Logger

Here is how custom logs in Dorguzen work, in a nutshell. There are three parts:

- configuration
- `Dorguzen\Core\DGZ_Logger`
- `models\Logs`

First, you have to define the type of logging you want in the `Dorguzen\Configs\Config.php` file. It basically determines where you prefer logs to be written to. The setting is defined in the

```
'log_driver'
```

key. The value can be one of three options:

```
'file'
'db'
'both'
```

But the default value is `'db'`. You can always find logs for your application in the logs table, unless you chose `'file'` as the driver.

You can also specify the format using the

```
'log_format'
```

key. The value can be one of two options:

```
'text'
'json'
```

But the default is `'text'`.

Essentially, you would call the static method `log()` of `DGZ_Logger` and pass it the actual string to be logged. This `log()` method uses the Logs model to save the logs data to the database if your configuration specifies that.

Here is the full documentation on how to write custom logs in Dorguzen.

---

## ✅ Dorguzen Logging System — Full Documentation

The Dorguzen Logging System provides a powerful, configurable, and developer-friendly way to record runtime information from your application. It is designed to work both in local development and in restricted shared hosting environments where you cannot access PHP error logs (e.g., GoDaddy Shared Hosting).

The logger is implemented through three main classes:

### ✅ 1. Core Components (Classes Involved)

**a) `Dorguzen\Configs\Config`**

This class provides global framework configuration, including the logging settings:

- Logging driver (file, db, or both)
- Logging format (text or json)
- Log directory path (defaults to `storage/logs`)

**b) `Dorguzen\Core\DGZ_Logger`**

This is the core logging engine. It:

- Accepts log messages at multiple severity levels (debug, info, warning, error, critical)
- Handles file creation, rotation, and concurrency-safe writes
- Forwards logs to the database via the Logs model
- Supports text and JSON structured logging
- Automatically creates missing directories
- Uses PSR-3-style method signatures (but simplified)

**c) `src\models\Logs`**

This is the ORM model responsible for database logging. It now also stores context data in a `context_json` field (JSON encoded).

This allows you to build structured, filterable logs directly inside your application.

### ✅ 2. Logging Drivers

The behavior of the logger is controlled by:

```php
'log_driver' => 'db',   // file | db | both
'log_format' => 'text', // text | json
```

These values live inside `configs/Config.php`.

**Driver Options**

| Driver | Description |
|---|---|
| file | Writes logs to rotating files under `/storage/logs/`. |
| db | Logs messages into your logs database table. |
| both | Writes to file AND database simultaneously. |

### ✅ 3. Logging Formats

You may choose how each line is written when using the file driver.

```php
'log_format' => 'text', // default
// or:
'log_format' => 'json'
```

**The text Output Example**

```
[2025-11-10 15:04:21] ERROR: Payment failed
```

**The json Output Example**

```json
{"timestamp":"2025-11-10 15:04:21","level":"ERROR","message":"Payment failed","context":{"txn":5533}}
```

JSON logs are machine-readable and excellent for future analytics.

### ✅ 4. How Logging Works (Chronological Explanation)

Below is the full flow of how a log message is processed from start to finish.

**✅ Step 1 — Logger Initialization**

In `bootstrap/app.php`, your framework initializes the logger:

```php
DGZ_Logger::init(__DIR__ . '/../storage/logs/dgz_errors.log');
```

During initialization:

- The logger builds its internal settings (driver, format, etc.)
- Ensures the directory `/storage/logs` exists
- Ensures the directory is writable
- Creates a Logs model instance for DB logging
- Prepares to create today's log file

**✅ Step 2 — Daily Log File Determination**

Each day creates its own file:

```
/storage/logs/dgz-2025-11-10.log
```

This is called daily log rotation, and it prevents massive single log files from forming.

**✅ Step 3 — Writing Logs**

When you call:

```php
DGZ_Logger::error("User login failed", ['email' => 'test@example.com']);
```

The logger:

- Normalizes the severity level
- Formats the message depending on text/json mode
- Writes to file, using `flock()` for concurrency-safe writes
- Writes to DB via

```php
$logsModel->log($level, $message, $context);
```

Thanks to the new `context_json` column, the DB now stores structured context data.

### ✅ 5. Cool Features of the Logging system

The Dorguzen logging system is equivalent in spirit to a lightweight version of Laravel's Monolog integration, but written natively with zero external dependencies.

Here are its strongest features:

**a) Safe Concurrent Writes (flock)**

Multiple requests writing at once will not corrupt the log file.

**b) Daily Log Rotation**

Automatically creates a new file per day: `dgz-YYYY-MM-DD.log`

**c) Dual Drivers (file, db, both)**

You can:

- log only to file
- log only to DB

or both at the same time

Right from your configuration.

**d) Optional JSON Structured Logging**

This is perfect for servers, analytics tools, and future machine learning integration.

**e) Log Levels**

debug, info, warning, error, critical

Same naming conventions used by PSR-3 and Laravel.

**f) PSR-3-Style Method Signatures**

The API feels familiar for anyone used to modern logging libraries:

```php
DGZ_Logger::error($msg, $context);
```

**g) Integrates Cleanly With Your Logs Model**

All context is stored into `context_json`, providing:

- filterable logs
- human readable output
- selectable context keys
- advanced debugging

**h) Fully Extensible**

You can easily attach:

- Email notifications
- Slack / Discord alerts
- Webhooks
- Custom rotating strategies

Because the logger architecture is very clean.

### ✅ 6. Example Usage

**Basic Log**

```php
DGZ_Logger::info("User logged in");
```

**✅ Log with Context**

```php
DGZ_Logger::warning("Slow DB query", [
    'duration_ms' => 240,
    'sql' => 'SELECT * FROM users'
]);
```

**✅ Critical Error**

```php
DGZ_Logger::critical("Payment gateway unavailable", [
    'gateway' => 'Stripe'
]);
```

### ✅ 7. Example Output

**File (text mode)**

```
[2025-11-10 15:32:10] INFO: User logged in
[2025-11-10 15:32:12] WARNING: Slow DB query | {"duration_ms":240,"sql":"SELECT * FROM users"}
[2025-11-10 15:32:20] CRITICAL: Payment gateway unavailable | {"gateway":"Stripe"}
```

**File (json mode)**

```json
{"timestamp":"2025-11-10 15:32:20","level":"CRITICAL","message":"Payment gateway unavailable","context":{"gateway":"Stripe"}}
```

**Database Row Example**

| id | title | message | context_json | logs_created |
|---|---|---|---|---|
| 1 | CRITICAL | Payment gateway unavailable | {"gateway":"Stripe"} | 2025-11-10 |

### ✅ 8. What This Unlocks (The Future)

Because this system is structured, stable, and extensible, you can now build:

- Searchable, filterable admin UI
- Filter logs by:
  - level
  - date
  - keywords
  - context keys
- Trend analytics & monitoring dashboards
- Plot errors by day, by controller, by model, etc.
- Machine-learning / anomaly detection

JSON logs make it possible.

- Rich developer experience

Even on shared hosting with no access to `error_log`.

- Automated alert pipelines

Plug-in logic can email you on CRITICAL failures.

### Final Thoughts

The new Dorguzen Logging System is:

- ✅ Console-framework-grade
- ✅ Concurrency safe
- ✅ Extremely configurable
- ✅ PSR-3-aligned
- ✅ Extends neatly without rewriting anything
- ✅ Equivalent in spirit to a small Monolog engine
- ✅ Designed for real-world hosting constraints

You may want to clear your logs, sometimes, otherwise it will keep logging for ever. There you have it; one of the strongest and most flexible subsystems in Dorguzen — a very useful tool for debugging. Happy logging.

---

## Log Channels

So far the examples above all use `DGZ_Logger::error()` / `info()` / etc., which write to a single, shared destination controlled by `APP_LOG_DRIVER` and `APP_LOG_FORMAT` in your `.env` file.

A log channel is a named, independently-configured logging destination. Instead of one global stream you can have several — one per concern:

```
payments  →  file-only, JSON, warnings and above
security  →  file + DB, JSON, errors and above
default   →  whatever APP_LOG_DRIVER says (backwards-compatible with all existing DGZ_Logger calls)
```

Every channel has its own:

```
driver     — where to write: 'file' | 'db' | 'both'
format     — line format:    'text' | 'json'
path       — directory for log files, relative to the project root
min_level  — minimum severity to record: debug | info | notice | warning | error | critical
             Messages below the threshold are silently discarded.
```

### Configuring channels

Open (or create) `configs/logging.php`. The ConfigLoader picks it up automatically — no registration needed.

```php
<?php
return [
    'channels' => [

        // 'default' mirrors your .env APP_LOG_DRIVER / APP_LOG_FORMAT settings
        // so all existing DGZ_Logger::error() calls are completely unaffected.
        'default' => [
            'driver'          => env('APP_LOG_DRIVER', 'db'),
            'format'          => env('APP_LOG_FORMAT', 'text'),
            'path'            => 'storage/logs',
            'min_level'       => 'debug',
            'filename_prefix' => 'dgz',   // produces dgz-YYYY-MM-DD.log
        ],

        'payments' => [
            'driver'    => 'file',   // file only — never touches the DB
            'format'    => 'json',
            'path'      => 'storage/logs',
            'min_level' => 'warning',
        ],

        'security' => [
            'driver'    => 'both',   // file AND DB
            'format'    => 'json',
            'path'      => 'storage/logs',
            'min_level' => 'error',
        ],

    ],
];
```

### Using channels in your application code

Anywhere you have access to `DGZ_Logger` you can call `::channel()`:

```php
DGZ_Logger::channel('payments')->warning('Charge failed', ['amount' => 5000, 'user' => $userId]);
DGZ_Logger::channel('security')->error('Login brute force', ['ip' => $ip, 'attempts' => 12]);
DGZ_Logger::channel('security')->critical('Privilege escalation attempt', ['user' => $user]);
```

The API is identical to the top-level `DGZ_Logger` convenience methods (debug/info/notice/warning/error/critical), so switching between global and channel-based logging is a one-word change.

Channel instances are cached per request — calling `::channel('payments')` ten times only creates one object.

### File naming

By default a channel writes to:

```
{path}/{channelName}-YYYY-MM-DD.log     e.g.  payments-2025-11-10.log
```

You can override the filename prefix with the optional `filename_prefix` key in the channel config:

```
'filename_prefix' => 'dgz'    →    dgz-2025-11-10.log
```

The 'default' channel uses `filename_prefix: 'dgz'` so its files match the legacy format produced before channels were introduced.

### Filtering DB logs by channel

When driver is 'db' or 'both', the channel name is injected into the `context_json` column:

```json
{"_channel": "security", "ip": "1.2.3.4", "attempts": 12}
```

This lets you query or filter by channel in phpMyAdmin, your admin panel, or a future log viewer:

```sql
SELECT * FROM logs WHERE context_json LIKE '%"_channel":"security"%';
```

### Viewing logs via the CLI (php dgz log)

The `php dgz log` command displays recent log entries directly in your terminal:

```bash
php dgz log
```

Important: this command reads exclusively from the DB (the logs table). It does not read any log files. This means:

- Channels with driver 'db' or 'both' — their entries appear in the output.
- Channels with driver 'file' — their entries do NOT appear; they only exist on disk.

To make the blind spot visible, the command automatically prints a notice at the bottom of its output whenever it detects one or more file-only channels in your config:

```
Note: channel(s) [payments] use driver 'file' — their entries are not stored in the DB
      and do not appear above.
      To inspect: php dgz log:tail --channel=payments
```

This notice is purely informational — it does not affect the exit code or the log output above it.

If you want a channel's entries to appear in `php dgz log`, change its driver to 'db' or 'both' in `configs/logging.php`. The trade-off is that 'both' writes every entry twice (file + DB row), which adds a small write overhead but gives you both the raw file archive and the structured DB view simultaneously.

---

## Log Tailing

Log tailing lets you watch a log file update in real time in your terminal — identical to running `tail -f` manually, but integrated into the Dorguzen CLI so you never have to remember the file path.

### The command

Tail the default channel (driver must be 'file' or 'both'):

```bash
php dgz log:tail
```

Tail a named channel:

```bash
php dgz log:tail --channel=payments
php dgz log:tail -c payments
```

Show 50 lines of history before following new output (default is 20):

```bash
php dgz log:tail --channel=security --lines=50
php dgz log:tail -c security -l 50
```

The command streams output to your terminal and keeps running until you press Ctrl+C. It tells you which file it is watching so there is never any ambiguity.

Notes:

- Requires the channel's driver to be 'file' or 'both'. A db-only channel has no file.
- The log file is created on the first write. If none exists yet, the command tells you what to do.
- `tail` must be available on the host OS. It is standard on macOS and every Linux distribution.

### How to test it — a complete working example

This is the fastest way to see channels and tailing working end-to-end. Open two terminal tabs side-by-side.

**Step 1** — make sure the payments channel writes to a file. In `configs/logging.php` confirm (or set):

```php
'payments' => [
    'driver'    => 'file',
    'format'    => 'json',
    'path'      => 'storage/logs',
    'min_level' => 'warning',
],
```

**Step 2** — in Terminal A, start tailing:

```bash
php dgz log:tail --channel=payments
```

You will see:

```
Tailing [payments]: /path/to/project/storage/logs/payments-2025-11-10.log
Press Ctrl+C to stop.
```

If the file does not exist yet the command will print a hint — just continue to Step 3.

**Step 3** — in Terminal B, write a log entry. The quickest way is a tiny PHP one-liner from the project root:

```bash
php -r "
    require_once __DIR__ . '/tests/manual/cliTestHeader.php';
   \Dorguzen\Core\DGZ_Logger::channel('payments')->warning('Test charge failed', ['amount' => 100]);
"
```

Or, add this temporarily to any controller action and visit that page in your browser:

```php
DGZ_Logger::channel('payments')->warning('Test charge failed', ['amount' => 100]);
DGZ_Logger::channel('payments')->error('Payment gateway timeout', ['gateway' => 'Stripe']);
```

**Step 4** — watch Terminal A. Each log line appears in Terminal A the moment it is written:

```json
{"time":"2025-11-10 14:32:01","channel":"payments","level":"WARNING","message":"Test charge failed","context":{"amount":100}}
{"time":"2025-11-10 14:32:01","channel":"payments","level":"ERROR","message":"Payment gateway timeout","context":{"gateway":"Stripe"}}
```

Press Ctrl+C in Terminal A to stop tailing.

That is it. From here you can add more channels to `configs/logging.php` for any concern in your application — audit trails, slow query logs, third-party API calls — each independently configured and independently tailored.

### Lifespan of log files

You may be wondering, if we only ever see the file of the current date, what's the use of log files of other dates. What are its use cases?

Here is the thing. The daily log files are primarily for retrospective investigation, not live monitoring. Here are the real-world use cases:

**1. Post-mortem debugging**

Something broke at 2am on Tuesday. You wake up Wednesday, see reports, and need to know exactly what happened. You open `payments-2026-02-24.log` and trace the exact sequence of events — timestamps, context data, everything.

**2. Incident timeline reconstruction**

A user reports their payment failed 'sometime last week.' Without the old log files you'd have no evidence. With them you can pull up the exact date range and search:

```bash
grep 'user_id.*4521' storage/logs/payments-2026-02-18.log
```

**3. Spotting patterns across time**

Errors that happen every Monday at 9am (scheduled job clash), or every month-end (heavy load), or only on specific days. You'd never notice this from today's file alone.

**4. Auditing and compliance**

Financial transactions, auth events, and security logs often need to be retained for legal/compliance reasons (30 days, 90 days, 1 year depending on your industry). Camerooncom handles payments, so this is relevant.

**5. Comparing before/after a deployment**

You shipped code on Friday. Something seems off now. You compare Saturday's log against Thursday's to see what changed in behaviour.

---

So the question becomes: how long should you keep them?

The common approach is log rotation with a retention policy — keep N days, then auto-delete. Tools like `logrotate` (Linux) handle this, or you could add a `log:prune --days=30` command to the CLI. Most production apps keep 30-90 days of file logs and rely on the DB for longer-term structured querying.

Since Dorguzen provides you with a DB driver for logs as well, old file logs are mostly a safety net and a fast grep-able archive. The DB gives you the structured, queryable view; the files give you the raw, never-filtered record.

---

## Log Pruning

Log pruning lets you enforce a file retention policy from the CLI — deleting log files older than a chosen number of days — without touching anything by hand. A DB audit record is always written after every run (including dry-runs) so there is a permanent, queryable history of what was pruned and when.

### The command

Prune all channels, delete files older than 30 days (the default):

```bash
php dgz log:prune
```

Use a custom cutoff:

```bash
php dgz log:prune --days=7
php dgz log:prune --days=90
```

Limit to one channel:

```bash
php dgz log:prune --channel=payments
php dgz log:prune -c payments
```

Combine options:

```bash
php dgz log:prune --channel=security --days=90
```

Preview what would be deleted without touching anything (`--dry-run`):

```bash
php dgz log:prune --dry-run
php dgz log:prune --channel=payments --days=7 --dry-run
```

List every log file regardless of age (days=0 means "everything is old"):

```bash
php dgz log:prune --days=0 --dry-run
```

### Options summary

`--days, -d`      (default: 30)

Files whose last-modified time is more than this many days ago are candidates for deletion. Use 0 to target every log file.

`--channel, -c`   (default: all)

Restrict pruning to a single named channel. The name must match a key in `configs/logging.php`. Channels with driver 'db' are automatically skipped (they have no files). Pass 'all' (or omit the option) to process every file-backed channel.

`--dry-run`       (flag, no value)

Report which files would be deleted without actually deleting them. The DB audit entry is still written so you have a record of the intent.

### How channel ownership is determined

The command identifies which files belong to a channel by filename prefix, not by subdirectory. Multiple channels can share the same directory (e.g. `storage/logs/`) and the command will still only touch the files that belong to each channel.

The prefix follows the same rule used by the logger when creating files:

- If the channel config has a `filename_prefix` key, that value is used.
- Otherwise the channel name itself is the prefix.

So for the default `configs/logging.php` setup:

```
Channel 'default'  → prefix 'dgz'      → matches dgz-YYYY-MM-DD.log
Channel 'payments' → prefix 'payments'  → matches payments-YYYY-MM-DD.log
Channel 'security' → prefix 'security'  → matches security-YYYY-MM-DD.log
```

Each channel only sees its own files. A file like `payments-2026-02-25.log` will never be counted or deleted when pruning the 'default' or 'security' channel.

If you ever configure a channel to use a dedicated subdirectory (e.g. `path: storage/logs/payments`), both the prefix filter and the directory are scoped per channel, so isolation is maintained either way.

### The --dry-run safety net

Always run with `--dry-run` first, especially when setting a new retention window. The output shows exactly which files are in scope, their age in days, and the final count:

```
[dry-run] No files will be deleted.

Channel 'default' — scanning /path/to/project/storage/logs
  [would delete] dgz-2025-11-10.log (age: 106d)
  [would delete] dgz-2025-11-11.log (age: 105d)

Dry run complete. 2 file(s) would be deleted, 3 kept.
```

Once you are happy with what you see, run the same command without `--dry-run` to commit the deletion.

### DB audit record

After every run (real or dry), the command writes one row to the logs table via `Logs::log()`. This bypasses the channel driver setting so the audit ALWAYS reaches the DB, even if you disabled DB logging for a channel.

The row looks like:

```
logs_title:   INFO
logs_message: log:prune — 2 file(s) deleted
context_json: {"days":30,"channel":"all","deleted":2,"skipped":3,"dry_run":false}
```

To query recent prune history:

```sql
SELECT * FROM logs WHERE logs_title = 'INFO'
  AND logs_message LIKE 'log:prune%'
  ORDER BY logs_created DESC;
```

Or via the CLI:

```bash
php dgz log
```

### Recommended retention strategy

File logs — keep for 30 days by default.

```bash
php dgz log:prune --days=30
```

High-sensitivity channels (security, payments) — keep longer for compliance.

```bash
php dgz log:prune --channel=security --days=90
php dgz log:prune --channel=payments --days=90
```

DB logs — keep indefinitely. They are compact (no raw text, just structured rows) and are the primary source for dashboards and audit queries.

You can automate pruning with a cron job (Linux/macOS):

```bash
# Run every day at 2am, prune files older than 30 days
0 2 * * * cd /path/to/project && php dgz log:prune --days=30 >> /dev/null 2>&1
```

### Graceful edge cases

- Unknown channel — the command prints an error and exits with a non-zero code.

```
php dgz log:prune --channel=unknown
→ [error] Unknown channel 'unknown'. Check configs/logging.php for valid channel names.
```

- Channel with driver 'db' — skipped silently with an informational note.
- Log directory does not exist — skipped with a warning; the command continues with other channels.
- File permission error — reported per-file; the run continues and the DB audit counts it as skipped.
- No DB connection — the audit write failure is caught and reported as a warning; the exit code is still SUCCESS since the file operations succeeded.

---

## The difference between PHP's error_log() and DGZ_Logger

You can use either, PHP's `error_log()` or Dorguzen's `DGZ_Logger`'s `log()` function to write custom logs, but here's a comparison:

### error_log() advantages

Here are the advantages of using `error_log()` for logging:

- Handles file locking internally
- OS-level optimized
- Good for append-only logs
- Less likely to cause race conditions

### file_put_contents() advantages

- Full control over format
- Can create directories first
- Can rotate logs more easily

For writing logs to a custom file, either is fine — but DGZ's `DGZ_Logger::log()` works with your config settings to manage how you log data — via DB, file, or both. Having the logs in your DB can mean that you can display them in log report screens for other members of your team to analyse.

---

## How to write values to console during PHPUnit tests

Do a `try {}` and catch block, and in the catch block, throw a `RuntimeException` exception like this:

```php
throw new \RuntimeException(
    'Config WAS loaded successfully in bootstrap/testing.php Config dump: ' .
    json_encode($this->config->getConfig())
)
```

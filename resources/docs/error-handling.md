# Error Handling

Dorguzen catches all PHP errors and unhandled exceptions, logs them, and renders an appropriate response — detailed in development, generic in production. All error behaviour is controlled by `APP_ENV` in `.env`.

---

## Development vs Production

The `APP_ENV` value determines what error information is shown:

| `APP_ENV` | `config('live')` | Error display |
|---|---|---|
| `local` | `'false'` | Full exception dump with stack trace |
| `prod` / `production` | `'true'` | Generic "Something went wrong" message |

Set in `.env`:

```ini
APP_ENV=local   # development
APP_ENV=prod    # production
```

In production, full exception details are logged but never shown to the user. Admins (super_admin, admin) see a "View Logs" button after an error.

---

## How Exceptions Flow

```
PHP error / thrown exception
    ↓
set_error_handler() (bootstrap/helpers.php)
    → Converts PHP errors to DGZ_Exception
    → Logs via DGZ_Logger
    → On production: emails admin via DGZ_Messenger
    → Redirects to exception/error route
        ↓
DGZ_Controller::display()
    → Inner try/catch: logs exception, passes to ExceptionView
    → ExceptionView: on local → dump(); on production → generic message
```

---

## DGZ_Exception

All framework errors are thrown as `DGZ_Exception`. It extends `\Exception` and adds a typed error constant and an optional hint.

```php
throw new DGZ_Exception(
    'User not found',
    DGZ_Exception::NO_USER_RECORD,
    'Check that the user ID exists before calling this method.'
);
```

### Exception type constants

| Constant | Meaning |
|---|---|
| `EXCEPTION` | Generic exception |
| `PERMISSION_DENIED` | Access control failure (also thrown by CSRF middleware) |
| `NO_USER_RECORD` | Expected user not found in DB |
| `INCORRECT_USERNAME_PASSWORD` | Auth failure |
| `QUERY_ERROR` / `DATABASE_QUERY_ERROR` | DB driver error |
| `FILE_NOT_FOUND` | Missing file |
| `CLASS_NOT_FOUND` / `CONTROLLER_CLASS_NOT_FOUND` | Class resolution failure |
| `NO_VIEW_FOUND` / `NO_LAYOUT_FOUND` | View/layout resolution failure |
| `MISSING_PARAMETERS` | Required parameter absent |
| `WRONG_PARAMETER_TYPE` / `INVALID_PARAMETER_VALUE` | Type/value constraint |
| `MISSING_HANDLER_FOR_ACTION` | No controller method for route |
| `INVALID_INPUT` / `INVALID_CONFIG` | Bad input or config |
| `PHP_FATAL_ERROR` / `PHP_ERROR` / `PHP_WARNING` / `PHP_NOTICE` | PHP native errors converted by error handler |
| `NOT_IMPLEMENTED_EXCEPTION` | Stub/WIP method |
| `GALLERY_ALBUM_NOT_FOUND` | Domain-specific |

Access the type and hint on a caught exception:

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

## Logging

Use `DGZ_Logger` to write application log entries. It supports two drivers — database (`dgz_logs` table) and daily rotating file — controlled per named channel.

### Default (global) logger

```php
use Dorguzen\Core\DGZ_Logger;

DGZ_Logger::debug('Processing payment', ['amount' => 99.99]);
DGZ_Logger::info('User registered', ['user_id' => $userId]);
DGZ_Logger::warning('Slow query detected', ['ms' => 1200]);
DGZ_Logger::error('Payment gateway timeout', ['gateway' => 'stripe']);
DGZ_Logger::critical('Database connection lost');
```

All levels: `debug`, `info`, `notice`, `warning`, `error`, `critical`.

### Named channels

Named channels allow separate files, formats, and minimum log levels per concern:

```php
DGZ_Logger::channel('payments')->warning('Charge failed', ['amount' => 100]);
DGZ_Logger::channel('security')->critical('Brute force attempt', ['ip' => $ip]);
```

Channels are defined in `configs/logging.php`:

```php
return [
    'channels' => [
        'default' => [
            'driver'    => env('APP_LOG_DRIVER', 'db'), // 'db', 'file', or 'both'
            'format'    => env('APP_LOG_FORMAT', 'text'), // 'text' or 'json'
            'path'      => 'storage/logs',
            'min_level' => 'debug',
        ],
        'payments' => [
            'driver'    => 'file',
            'format'    => 'json',
            'path'      => 'storage/logs',
            'min_level' => 'warning',
        ],
        'security' => [
            'driver'    => 'both',
            'format'    => 'json',
            'path'      => 'storage/logs',
            'min_level' => 'error',
        ],
    ],
];
```

### Log file location and naming

```
storage/logs/dgz-2025-11-10.log          ← default channel (text or JSON)
storage/logs/payments-2025-11-10.log     ← 'payments' channel
storage/logs/security-2025-11-10.log     ← 'security' channel
```

Log files rotate daily. Old files are never deleted automatically — use `log:prune` for that.

### Text format

```
[2025-11-10 14:32:07] ERROR: Payment gateway timeout {"gateway":"stripe"}
```

### JSON format

```json
{"time":"2025-11-10 14:32:07","level":"ERROR","message":"Payment gateway timeout","context":{"gateway":"stripe"}}
```

---

## Log CLI Commands

```bash
php dgz logs              # view DB log entries (most recent first)
php dgz log:tail          # tail the daily log file in real time
php dgz log:prune         # delete log files older than 30 days
php dgz log:prune --days=7              # custom retention
php dgz log:prune --channel=payments   # prune a specific channel
php dgz log:prune --dry-run            # preview without deleting
```

`log:tail` only works for channels with `driver: file` or `driver: both` — file-less `db` channels have nothing to tail.

---

## Debug Helpers

These helpers are available globally in all environments.

### `dump()` — inspect a value (non-terminating)

Outputs a Symfony VarDumper formatted dump and continues execution:

```php
dump($user);
dump($request->all(), $config);
```

Use this when you want to inspect a value mid-execution without stopping the request.

### `dgzie()` — DGZ debug dump (terminates)

The framework's equivalent of `dd()`. Dumps values and exits. Also auto-dumps the current `DGZ_Request` object and shows the calling file and line:

```php
dgzie($user, $posts);
// Shows: called from src/controllers/BlogController.php on line 42
// Dumps: DGZ_Request object
// Dumps: $user, $posts
// Exits
```

> **Note:** Symfony's `dd()` is also available from the `symfony/var-dumper` package in vendor — it works the same way. `dgzie()` is the DGZ-native version with added request context.

---

## Production Error Alerts

When `APP_ENV=prod`, the error handler automatically emails the admin on every caught PHP error:

```php
$messenger = new DGZ_Messenger();
$messenger->sendErrorLogMsgToAdmin($errorMessage);
```

The recipient is the `MAIL_FROM_ADDRESS` configured in `.env`. Ensure SMTP is correctly configured in production so these alerts are delivered. See [Email](/docs/email).

---

## Validation Errors

Form and API validation failures use a separate exception class:

```php
use Dorguzen\Core\exceptions\ValidationException;

throw new ValidationException(
    errors:                  $validatorErrors,
    input:                   $_POST,
    validationErrorMessages: $messages,
    redirectTo:              '/contact'
);
```

The router catches `ValidationException`, flashes the errors via `addErrors()`, and redirects to `$redirectTo`. You never need to throw this manually — the JetForms middleware and `DGZ_Validator` handle it.

See [Validation](/docs/validation) and [Forms](/docs/forms) for the full flow.

---

## Custom Exception Handling

To handle a specific exception type in a controller, catch it directly:

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

Uncaught exceptions bubble up to `DGZ_Controller::display()`, which logs them and renders the appropriate error view.

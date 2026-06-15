# Security

Dorguzen applies several security measures by default: CSRF protection on all mutating requests, parameterized SQL queries, session hardening, and password encryption. This page covers how each mechanism works and what you need to do (or avoid) as an application developer.

---

## CSRF Protection

All `POST`, `PATCH`, `PUT`, and `DELETE` requests are CSRF-protected by `CsrfPsrMiddleware`, which runs globally on every request.

### How it works

The middleware:
1. Checks `Content-Length` against PHP's `post_max_size` first — surfaces a clear error if uploads exceed the limit, rather than a misleading CSRF failure.
2. Reads the CSRF token from `$_POST['_csrf_token']` or the `X-CSRF-TOKEN` request header.
3. Validates it against the session-stored token via `$request->validateCsrfToken()`.
4. Throws `DGZ_Exception::PERMISSION_DENIED` on failure.

### Including the token in HTML forms

```php
<form method="POST" action="/contact">
    <input type="hidden" name="_csrf_token" value="<?= getCsrfToken() ?>">
    <!-- your fields -->
</form>
```

### AJAX requests

Send the token as a header:

```js
fetch('/api/some-route', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    body: JSON.stringify(data),
});
```

Output the token in the layout `<head>`:

```html
<meta name="csrf-token" content="<?= getCsrfToken() ?>">
```

### Exempting paths (API routes)

Set a path prefix exception in `.env`:

```ini
APP_API_CSRF_EXCEPTION='/api/'
```

This populates `configs/app.php`'s `csrf_except` array. Any request whose URI starts with `/api/` skips CSRF validation — required for JWT-authenticated API clients.

---

## SQL Injection Prevention

All database queries go through parameterized prepared statements. The ORM layer (`DGZ_Model`) and all drivers use `?` placeholders — user input is never interpolated directly into SQL strings.

```php
// Safe — value is a bound parameter, not string concatenation
$user = $users->findByWhere(['users_email' => $email]);

// Safe — values are bound separately
$users->updateWhere(
    ['users_firstname' => $newName],
    ['users_id'        => $id]
);
```

Never construct raw SQL from user input. If you must write raw SQL, use the driver's `prepare()` and `execute()` methods with bound parameters.

---

## Password Storage

How passwords are stored depends on the active DB driver:

| Driver | Method |
|---|---|
| `mysqli` | `AES_ENCRYPT(password, DB_KEY)` — MySQL-level encryption using your `DB_KEY` env var |
| `pdo` | `AES_ENCRYPT(password, DB_KEY)` |
| `pgsql` | `password_hash($password, PASSWORD_DEFAULT)` |
| `sqlite` | `password_hash($password, PASSWORD_DEFAULT)` |

For the MySQL drivers, set a strong, random `DB_KEY` in `.env`:

```ini
DB_KEY='your-random-32-char-secret-here'
```

**Never commit `DB_KEY` to version control.**

### Password strength validation

`DGZ_CheckPassword` validates password strength before storing. Configurable requirements: minimum length (default 6), mixed case, minimum digit count, minimum symbol count. Call it before calling the auth service:

```php
$checker = new DGZ_CheckPassword();
$checker->setMinLength(8)->requireMixedCase()->setMinNumbers(1);
$result = $checker->check($password);
```

---

## Session Security

- **Session regeneration**: `session_regenerate_id()` is called on every successful login to prevent session fixation attacks.
- **Session timeout**: Sessions expire after 2 hours of inactivity (configured in `bootstrap/app.php`).
- **Remember-me cookie**: Set with `httponly: true`, `samesite: Lax`, and `secure: true` when HTTPS is detected. Expires after 96 hours.
- **Session token**: The authenticated session is identified by `$_SESSION['authenticated'] = 'Let Go-' . $appName` — this ties the session to a specific application, preventing cross-app session reuse on shared hosts.

---

## XSS Prevention

Dorguzen does not auto-escape template output — you are responsible for escaping values in views.

The primary sanitisation helper is `DGZ_Validate::fix_string()`:

```php
$clean = DGZ_Validate::fix_string($userInput);
// Applies: stripslashes → trim → htmlentities
```

Use this on any user-supplied string before storing it. When outputting stored values in HTML, wrap them with `htmlspecialchars()`:

```php
<p><?= htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') ?></p>
```

The framework applies `htmlspecialchars()` to asset file paths, theme colour values, and debug output — but not to application data rendered in views.

---

## HTTPS Enforcement

Call `enforceHttps()` in your bootstrap or a middleware to redirect all HTTP traffic to HTTPS and set an HSTS header:

```php
// bootstrap/app.php or in a global middleware
enforceHttps();
```

The helper respects Cloudflare and reverse-proxy headers (`CF-Visitor`, `X-Forwarded-Proto`, `X-Forwarded-Ssl`) and a configurable trusted-proxy IP list.

---

## API Authentication

API endpoints are protected by JWT. See the [REST API](/docs/rest-api) page for the full authentication flow. Key points:

- Access tokens are signed with `APP_JWT_SECRET` using `APP_JWT_ENCODING` (default `HS256`).
- Tokens are validated inside each controller with `$this->validateToken()`.
- Refresh tokens are stored in the database and rotated on each refresh.

---

## Security Checklist for Production

| Item | Action |
|---|---|
| `APP_DEBUG=false` | Never expose stack traces in production |
| `APP_ENV=prod` | Disables debug tooling |
| Strong `DB_KEY` | Random 32+ character string, never committed |
| Strong `APP_JWT_SECRET` | Random 40+ character string, never committed |
| `API_DOCS_ENABLED=false` | Disable Swagger UI in production |
| HTTPS | Call `enforceHttps()` |
| File permissions | `storage/` and `bootstrap/cache/` writable by web server only |
| `.env` not web-accessible | Verify `.htaccess` blocks direct access to `.env` |

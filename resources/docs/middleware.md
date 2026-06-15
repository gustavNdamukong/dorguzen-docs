# Middleware

Dorguzen has two categories of middleware: **global middleware** (runs on every request) and **route middleware** (applied to specific routes or groups).

```
middleware/
├── globalMiddleware/
│   ├── BaseMiddleware.php           ← controller-scope routing guard
│   ├── CsrfPsrMiddleware.php        ← CSRF token validation
│   └── FormValidationMiddleware.php ← JetForms auto-validation
└── routeMiddleware/
    └── AuthMiddleware.php           ← PSR-style auth guard for route groups
```

---

## Global Middleware

### BaseMiddleware

`BaseMiddleware` is the primary routing guard. Its `boot()` method returns a map of controller short-names to the action to take when that controller is targeted:

```php
public function boot(): array
{
    return [
        'admin'    => 'authenticated',   // require valid session
        'seo'      => 'isActiveModule',  // require module to be on
        'gallery'  => 'isActiveModule',
        'blog'     => 'isActiveModule',
    ];
}
```

| Value | Behaviour |
|---|---|
| `'authenticated'` | Allow only if `$_SESSION['authenticated']` is valid. Redirects to `admin/login` on failure. |
| `'authorized'` | Same session check plus `isAdmin()`. Throws `PERMISSION_DENIED` on failure. |
| `'isActiveModule'` | Checks the module is `on` in `configs/app.php`. Throws `PERMISSION_DENIED` if off. |
| `'divert'` | Calls a method matching the controller short-name that returns `[$controller, $method, $args]` for re-dispatch. |

**Built-in guard methods:**

```php
public function authenticated(): bool   // true if user session is valid
public function authorised(): bool      // true if authenticated and isAdmin()
public function isActiveModule(string $module): bool  // true if module is 'on'
```

**Adding a new restriction:**

```php
// In boot():
'mycontroller' => 'authenticated',
```

No further code needed — `authenticated()` already exists. For a custom check, add the value and the matching method.

---

### CsrfPsrMiddleware

Validates CSRF tokens on all `POST`, `PUT`, `PATCH`, `DELETE` requests.

- Reads the token from the `_csrf_token` POST field **or** the `X-CSRF-TOKEN` HTTP header (for AJAX).
- Validates using `DGZ_Request::validateCsrfToken()`.
- Skips validation for paths matching `APP_API_CSRF_EXCEPTION` in `.env`.

**In every HTML form:**

```html
<input type="hidden" name="_csrf_token" value="<?= getCsrfToken() ?>">
```

**For AJAX requests:**

```js
headers: { 'X-CSRF-TOKEN': csrfToken }
```

**Exempting API routes:**

```ini
APP_API_CSRF_EXCEPTION=/api/
```

---

### FormValidationMiddleware

Automatically validates forms that use the JetForms system. Checks for a `_form_name` POST field — if absent, it passes through immediately (non-JetForms requests are unaffected).

On validation failure: stores old input and errors in `$_SESSION`, throws `ValidationException`, and the router redirects back to the referrer.

---

## Route Middleware

Route middleware is applied to specific routes in `routes/web.php` using `->middleware()` and `->group()`:

```php
$router->middleware(['auth'])->group(function() use ($router) {
    $router->get('/user/dashboard', 'UserController@dashboard');
    $router->get('/user/profile',   'UserController@profile');
});
```

### AuthMiddleware

Checks that `$_SESSION['authenticated']` is the expected token. Throws `PERMISSION_DENIED` if not, which the framework converts to a redirect to the login page.

---

## Middleware Priority

Global middleware runs in priority order (lower = earlier):

| Middleware | Priority |
|---|---|
| `CsrfPsrMiddleware` | 1 |
| `BaseMiddleware` | 2 |
| `FormValidationMiddleware` | 5 |

---

## Creating Custom Global Middleware

Add an entry to `BaseMiddleware::boot()` and implement the matching method:

```php
// boot():
'restricted' => 'myCheck',

// Method:
public function myCheck(): bool
{
    return someCondition();  // return false or throw to deny
}
```

For a PSR-style pipeline middleware, create a class in `middleware/globalMiddleware/` with a `process()` method and a `$priority` property:

```php
class MyMiddleware
{
    public int $priority = 3;

    public function process(PsrRequestAdapter $request, SimpleRequestHandler $next)
    {
        // logic here
        return $next->handle($request);
    }
}
```

The bootstrapper loads all classes from `middleware/globalMiddleware/` automatically.

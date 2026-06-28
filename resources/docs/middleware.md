# Middleware

- How the DGZ Middleware System Works
- Common Uses for Middleware
- Creating a Middleware
- Example: Constructor Setup
- The boot() and handle() Relationship
- Controlling Request Flow
- Route Middleware & Route Groups
- Middleware Priority System
- PSR Support and Jet forms validation

---

## How the DGZ Middleware System Works

The DGZ middleware system provides a powerful, modular way to run code before your application launches or before a request is dispatched to a controller.

It works seamlessly whether you are:

- building an app as a module (using DGZ's package system to plug into a DGZ web app), or
- working directly within the main web application.

Middleware is where you define logic that runs in between a request being received and your controller being executed.

Dorguzen makes working with middleware modular, as in, you can have one for each module in your application. Here's why it works.

- you add user middleware to `/middleware/moduleNameMiddleware.php` starting with the DGZ's own middleware calls as seen below. The current `MiddleWare.php` will be DGZ's middleware, and the code to process the checks (conditionals below) will be placed in a `handler()` method of that Middleware class. All user-defined (module) middleware should run in the same fashion.

- Dorguzen will loop thru all these middleware and call their `boot()` methods
- then call their `handle()` methods, passing it the requested controller input and method so they can be granular about handling the request to the method level

- each of the handlers should have code in it that handles the desired logic
- this should suffice seeing that each time any if statements or whatever conditionals the user placed in there throws an exception, this script will abort, and the exception will be caught and handled nicely below.

- So here are the steps
    - You load every `*Middleware.php` file in `/middleware`.
    - You instantiate each middleware.
    - You sort them safely by `$priority` (default 10 if not set).
    - You run them in proper order.

---

## Common Uses for Middleware

Middleware is perfect for scenarios such as:

- Checking if a user is authenticated before accessing a page (DGZ already provides a default middleware for this).
- Running some code before the app launches, e.g., loading a custom config or setting headers.
- Inspecting or redirecting a request before it reaches its intended controller.
- Pre-loading configuration data or perform setup routines unrelated to routing intents.

---

## Creating a Middleware

To create a middleware:

1) Create a class in a file whose name ends with `Middleware.php`, and place it in the `/middleware/` directory.
   Example:

   ```
   /middleware/routeMiddleware/AuthMiddleware.php
   ```

2) Your middleware class must implement the `DGZ_MiddlewareInterface` interface located in

   ```
   Dorguzen\Core\DGZ_MiddlewareInterface.php
   ```

3) It must define the following two methods as required by the interface:

   ```php
   public function boot(): array;
   public function handle($controller, $controllerShortName, $method): bool;
   ```

   `boot()` must return an array (it can be empty if unused). This is particularly handy to use to pre-load configuration data or perform setup routines unrelated to routing intents. This means, the idea of it returning an array is if you choose to let `handle()` call it and perform some logic, but you can also place about any kind of code in there to carry out some pre-request tasks before returning an empty array.

   `handle()` must accept three arguments:
   - `$controller` → the fully qualified target controller for the request
   - `$controllerShortName` → the simplified controller name DGZ uses in auto-discovery routing
   - `$method` → the method that should handle the current request

---

## Example: Constructor Setup

You can include a constructor for dependency setup (but no required parameters since DGZ instantiates middleware automatically). That means when you do, do not give it any required parameters. Here's an example of a middleware constructor added if needed:

```php
public function __construct()
{
    $this->config = container(Config::class);
    $this->users = container(Users::class);
    $this->request = container(DGZ_Request::class);
}
```

---

## The boot() and handle() Relationship

DGZ gives you flexibility in how you use these methods. Both methods can be used in combination.

A common and powerful pattern — as seen in `BaseMiddleware.php` — is to make the `boot()` method return an associative array of "rules" where the array keys represent controller short names, and their values what kind of check or action (intent/pre-check) should be performed on each.

Let's look at this example from `BaseMiddleware.php`:

```php
public function boot(): array
{
    return [
      'account' => 'authenticated',
      'admin'   => 'authorized',
      'shop'    => 'isActiveModule',
    ];
}
```

Here:

- The key (`'account'`) corresponds to the short name of the controller handling the request.
- The value (`'authenticated'`) represents the intent or type of check that should apply to that controller.

The DGZ framework will then call your middleware's `handle()` method, passing the `$controller`, `$controllerShortName`, and `$method`. The `handle()` method will inspect the `$boot` array and determine what logic to run for each controller dynamically.

Basically, the `handle()` method, can use this `boot()` mapping to dynamically call helper methods that perform the correct checks:

```php
public function handle(string $controller, string $controllerShortName, string $method): bool
{
    $boot = $this->boot();

    if (!array_key_exists(strtolower($controllerShortName), $boot)) {
      return true; // Not handled by this middleware
    }

    $intent = $boot[$controllerShortName];

    switch ($intent) {
        case 'authenticated':
          if (!$this->authenticated()) {
            throw new DGZ_Exception('Not authorized', DGZ_Exception::PERMISSION_DENIED, 'You must be logged in.');
          }
          break;

        case 'authorized':
          if (!$this->authorised()) {
            throw new DGZ_Exception('Not authorized', DGZ_Exception::PERMISSION_DENIED, 'Restricted area.');
          }
          break;

        case 'isActiveModule':
          if (!$this->isActiveModule($controllerShortName)) {
            throw new DGZ_Exception('Not authorized', DGZ_Exception::PERMISSION_DENIED, 'Module not active.');
          }
          break;
    }

    return true;
}
```

In this example:
- If the controller short name is `account`, the middleware automatically checks if the user is logged in.
- If it's `admin`, it verifies that the user has proper authorization.
- If it's `shop`, it ensures that the target module is currently active.

This pattern gives your middleware the power to behave differently depending on which controller is being routed, without needing multiple separate middleware classes. It lets you define different types of validations per controller in one place (`boot()`), while keeping your logic clean and centralized in `handle()`.

However — and this is important — you don't have to use `boot()` this way. It's just one creative pattern to show you what is possible. You can certainly design their own flow depending on the needs of your application.

It allows you to centralize several related pre-checks in one middleware class. It's especially useful for global middleware such as `BaseMiddleware`, which may need to handle multiple controller types or rule sets at once.

For example:
- You might want to apply different authentication levels depending on the section of your app.
- You can use the `$intent` values in the `boot()` array to trigger different logic paths dynamically.

### The `divert` intent (re-dispatching a request)

Besides the guard intents above, `BaseMiddleware` also recognises a `'divert'` intent. When a controller short name maps to `'divert'`, the middleware calls a method named after that controller, and that method returns a fresh target for the request — letting you transparently re-route to a different controller/method (the shipped example sends `'api'` requests through an `ApiController`):

```php
public function boot(): array
{
    return [
        'api' => 'divert',
    ];
}
```

Note also that a guard does not have to throw on failure. In `BaseMiddleware`, an `'authenticated'` failure redirects to the login page (`header('Location: ' . $this->config->getFileRootPath() . 'admin/login')`), whereas `'authorised'` and `'isActiveModule'` failures throw `DGZ_Exception::PERMISSION_DENIED`. The `authorised()` check builds on `authenticated()` by additionally calling `$this->users->isAdmin(...)`, and `isActiveModule()` passes only when the module is set to `'on'` in `configs/app.php`.

---

## Controlling Request Flow

Your `handle()` method decides whether DGZ proceeds with or aborts the request. So, if `handle()`:

- Returns `true` → DGZ continues with the request as normal.
- Returns `false` → DGZ halts the request.
- Throws an exception → DGZ's Router catches it and displays an error view or does whatever you program it to do.

Example:

```php
if (!$this->authenticated()) {
  throw new DGZ_Exception(
    'Not authorized',
    DGZ_Exception::PERMISSION_DENIED,
    'You must be logged in to access this section.'
  );
}
```

---

## Route Middleware & Route Groups

The middleware covered so far is **global** — DGZ auto-loads everything under `middleware/globalMiddleware/` (e.g. `BaseMiddleware`, `CsrfPsrMiddleware`, `FormValidationMiddleware`) and runs it on every request. Dorguzen also supports **route middleware**, which is attached to specific routes or route groups and lives in `middleware/routeMiddleware/`.

Route middleware is applied in `routes/web.php` (or `routes/api.php`) using the router's `middleware()` and `group()` methods:

```php
$router->middleware(['AuthMiddleware'])->group(function () use ($router) {
    $router->get('/user/dashboard', 'UserController@dashboard');
    $router->get('/user/profile',   'UserController@profile');
});
```

`middleware()` pushes a middleware layer onto the router's stack and returns `$this`; `group()` runs the closure so every route declared inside inherits that stack, then pops the layer afterwards so it does not leak to routes added later. Each route stores its resolved `middleware` list, which the HTTP kernel runs (via `runRouteMiddleware()` / `executeRouteMiddlewarePipeline()`) before dispatching to the controller.

### AuthMiddleware

`middleware/routeMiddleware/AuthMiddleware.php` is the route guard shipped with DGZ. It is a PSR-style middleware whose `process()` lets the request continue (`return $next->handle($request)`) only when `$_SESSION['authenticated']` matches the expected token (`'Let Go-' . appName`); otherwise it throws `DGZ_Exception::PERMISSION_DENIED`, which the router converts into a redirect to the login page.

---

## Middleware Priority System

DGZ runs all middlewares in the `/middleware/` directory automatically before dispatching the request.

Each middleware can define a property named `priority`:

```php
public int $priority = 5;
```

Lower numbers mean higher priority (i.e., they run earlier).
If a middleware does not define a priority, DGZ assigns a default value of 10.

Example execution order:

| Middleware | Priority | Order |
|---|---|---|
| CsrfMiddleware | 1 | 🥇 Runs first |
| AuthMiddleware | 5 | 🥈 Runs second |
| PaymentsMiddleware | 8 | 🥉 Runs third |
| LoggerMiddleware | 10 (default) | Last |

Even if you have more than 10 middlewares, the system continues to sort and execute them correctly — it's not limited to the number 10.

---

## PSR Support and Jet forms validation

**Hybrid PSR-15 Compatible Middleware Pipeline**

Dorguzen now ships with a brand-new middleware engine that finally brings:

- ✔ Full support for classic DGZ middleware
- ✔ Support for modern PSR-15 style middleware
- ✔ Ordered middleware execution via priority
- ✔ Seamless CSRF protection
- ✔ Integrated Jet Form validation (more on Jet Forms later)

This upgrade means Dorguzen middleware is now flexible, future-proof, and compatible with middleware written for any framework that follows the PSR-15 contract.

### 1. What Middleware Is in Dorguzen

Middleware is code that executes before your controllers, usually to:

- Validate CSRF tokens
- Authenticate users
- Validate reusable Jet Forms
- Modify requests
- Perform logging
- Run access control
- Run pre-controller logic

Every middleware lives in:

```
/middleware/
```

and must end with:

```
*Middleware.php
```

Example:

```
CsrfPsrMiddleware.php
FormValidationMiddleware.php
AuthMiddleware.php
```

Dorguzen automatically loads every file ending in `Middleware.php`, instantiates its class, sorts them by priority, and runs them in order.

### 2. Two Middleware Styles Supported

Dorguzen accepts two different middleware types, and can chain them together:

#### A. Legacy DGZ Middleware

These middleware classes extend `Dorguzen\Core\DGZ_MiddlewareInterface`.

They must implement:

```php
public function boot();
public function handle($controller, $controllerShortName, $method);
```

Return behaviour:

- Return `true` → continue middleware chain
- Return anything else → stop and return that value (e.g. redirect, error)

#### B. PSR-15 Style Middleware

These use the method:

```php
public function process(
    PsrRequestAdapter $request,
    SimpleRequestHandler $handler
)
```

PSR-15 style middleware must either:

- return `$handler->handle($request)` to continue, OR
- return some response-like object / throw exception to stop

Dorguzen exposes two internal helper classes to make this possible:

```
Dorguzen\Core\Psr\PsrRequestAdapter
Dorguzen\Core\Psr\SimpleRequestHandler
```

These mimic PSR-7 / PSR-15 interfaces just enough to make PSR-style middleware functional without requiring external libraries.

### 3. How Dorguzen Executes Middleware

The heart of this system is the method:

```php
DGZ_Router::runMiddleware()
```

**Step-by-step:**

**Step 1 — Auto-load middleware classes**

Dorguzen scans:

```
middleware/*.php
```

Loads each class and stores instances in an array.

**Step 2 — Sort middleware by priority**

Each middleware may optionally define:

```php
public int $priority = 5;
```

Lower = runs earlier (e.g., CSRF has priority 1).

Default priority = 10.

**Step 3 — Build a middleware pipeline**

Dorguzen builds a chain of closures—a "pipeline"—from last to first.

Each middleware becomes a callable:

```php
fn(PsrRequestAdapter $req): mixed
```

This means all middleware (DGZ or PSR) must look identical to the pipeline.

**Step 4 — Adapt Legacy DGZ Middleware**

If middleware implements `DGZ_MiddlewareInterface`, Dorguzen wraps it:

```php
$mw->boot();
$result = $mw->handle($controller, $controllerShortName, $method);
```

If it returns `true`, the pipeline continues.

If it returns a value (redirect/response), pipeline stops.

**Step 5 — Adapt PSR-15 Middleware**

If middleware contains `process()`:

```php
$handler = new SimpleRequestHandler($next);
return $mw->process($psrRequest, $handler);
```

This mimics official PSR-15 behaviour.

**Step 6 — Fallback for weird shapes**

If nothing matches, Dorguzen simply skips the middleware—but logs nothing.

**Step 7 — Kick the pipeline**

The router creates a new:

```php
PsrRequestAdapter(DGZ_Request)
```

and runs:

```php
$next($psrRequest)
```

If final middleware returns:

- `true` or `null` → routing proceeds
- anything else → routing stops and that return is used

### 4. CSRF Middleware (example)

Dorguzen includes CSRF protection implemented as:

PSR-style middleware using `process()`.

It receives the request from `PsrRequestAdapter`, reads headers/body, validates the token, then either:

- returns `$handler->handle($request)` → allow request
- throws/returns response → stop pipeline

Dorguzen's `DGZ_Form` (used by Jet Forms) integrates automatically with this, so developers never need to manually add CSRF fields.

**Concrete contract:**

- The token is read from the `_csrf_token` POST field (also accepted on the query string or JSON body) **or**, for AJAX, from the `X-CSRF-TOKEN` HTTP header — see `DGZ_Request::getCsrfTokenFromRequest()`.
- Validation runs through `DGZ_Request::validateCsrfToken()`.
- The global `getCsrfToken()` helper generates/returns the token. In a hand-written form, add it as a hidden field:

  ```html
  <input type="hidden" name="_csrf_token" value="<?= getCsrfToken() ?>">
  ```

  (`DGZ_Form`/Jet Forms inject this for you automatically.)
- Paths can be exempted from CSRF validation via the `csrf_except` list in `configs/app.php`, which is seeded from `APP_API_CSRF_EXCEPTION` in `.env`. For example, `APP_API_CSRF_EXCEPTION='/api/'` exempts all API routes (prefix-matched).

### 5. Form Validation Middleware (What Powers Jet Forms)

This is a DGZ-style middleware using `handle()`.

It:

- Detects submitted reusable Jet Forms via hidden `_form_name`
- Resolves the form using `JetFormsRegistry`
- Fills the form with input
- Runs the form's validation rules
- Throws `ValidationException` on failure
  Router catches this → sets `$_SESSION['old_input']` and `validation_errors`
- On success → stores validated data and continues

Jet Forms "just work" because of this middleware.
(Documentation for Jet Forms comes next.)

### 6. Writing Custom Middleware

Here are examples in both supported styles.

#### A. Legacy DGZ Middleware Example

```php
class AuthMiddleware implements DGZ_MiddlewareInterface
{
    public int $priority = 3;

    public function boot() {}

    public function handle($controller, $controllerShortName, $method): bool
    {
        if (!isset($_SESSION['user_id'])) {
            redirect('/login');
            return false;
        }

        return true;
    }
}
```

#### B. PSR-15 Style Middleware Example

```php
class ExamplePsrMiddleware
{
    public int $priority = 8;

    public function process($request, $handler)
    {
        if ($request->getHeader('X-Block-Me') === '1') {
            return 'blocked'; // stop pipeline
        }

        return $handler->handle($request);
    }
}
```

### 7. Middleware Execution Order

Example:

```
priority 1 → CsrfPsrMiddleware
priority 3 → AuthMiddleware
priority 5 → FormValidationMiddleware
priority 10 → Any other middleware
```

Earlier = more critical.

### 8. Why This Hybrid Approach Is Powerful

Dorguzen is now able to:

- Run middleware from old DGZ projects
- Run full PSR-15 middleware from external tutorials/libraries
- Support modern middleware chains without requiring PSR-7/PSR-15 libraries
- Allow deep customization of request pipelines

This means your framework gains modern architecture without losing its identity.

### 9. Jet Form Support (Preview)

Reusable forms rely entirely on middleware:

- `FormValidationMiddleware` handles validation
- CSRF middleware handles CSRF tokens
- Jet Forms remain ultra-lightweight
- Developers write only the form rules, not the plumbing

Consult the Forms and Email section for the full understanding of Jet Forms chapter.

---

## Summary

Here are some take-away points to master DGZ's middleware system:

- All middlewares live in `/middleware/`
- They must implement `DGZ_MiddlewareInterface`
- They can define `boot()` and `handle()`
- They may use a constructor for dependency setup
- `handle()` determines if DGZ continues or aborts
- Priority numbers control execution order
- Exceptions thrown from middleware are caught and handled by the router.

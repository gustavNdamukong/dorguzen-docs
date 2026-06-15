# Request Lifecycle

This page traces the full path of an HTTP request through Dorguzen, from the browser to the response.

---

## High-Level Flow

```
Browser / API client
    ↓
Apache mod_rewrite (.htaccess)
    → real files/directories served directly
    → everything else forwarded to index.php
    ↓
index.php
    → ob_start()
    → define DGZ_BASE_PATH
    → Composer autoloader
    → bootstrap/app.php       (DI container, session, auth gate)
    → bootstrap/config.php    (config files merged, .env loaded)
    → bootstrap/helpers.php   (global helper functions)
    → DGZ_Router instantiated
    → routes/web.php + routes/api.php loaded (or route cache served)
    → DGZ_Router::route()
    ↓
Route matching
    → defined routes checked first (matchDefinedRoute)
    → auto-discovery fallback (controller/method/id URL segments)
    ↓
Global middleware stack
    → CsrfPsrMiddleware     (validates CSRF on mutating requests)
    → AuthMiddleware        (sets guest/authenticated session state)
    ↓
Route middleware (if declared on route)
    ↓
Controller method called
    ↓
Service layer (business logic + DB queries)
    ↓
View rendered → wrapped in layout
    ↓
Response output
```

---

## Bootstrap (`bootstrap/app.php`)

The bootstrap file:

1. **Creates the DI container** (`DGZ_Container`) and stores it globally.
2. **Registers singletons** — all core models, services, queue drivers, and the event system are wired together once per request.
3. **Starts the session** — calls `session_start()` if no session is active.
4. **Runs the auth gate** — sets `$_SESSION['_Guest'] = 'visitor'` for unauthenticated users and enforces a 2-hour session timeout for authenticated ones (unless a `rem_me` cookie is present, which refreshes both the session and cookie TTL).

---

## Route Loading

`index.php` loads routes after bootstrap:

```php
$router = new DGZ_Router($container->get(DGZ_Request::class));
DGZ_Router::setInstance($router);

$cachedRouteFile = DGZ_BASE_PATH . '/storage/cache/routes.php';

if (file_exists($cachedRouteFile)) {
    $router->setRoutes(require $cachedRouteFile);
} else {
    include_once 'routes/web.php';
    include_once 'routes/api.php';
    $router->finalizeRoutes();
}

DGZ_Router::route();
```

The route cache (`storage/cache/routes.php`) bypasses route file parsing entirely. It is written automatically on the first uncached request and must be deleted after any route change.

---

## Route Matching

`DGZ_Router::route()` calls `getControllerAndMethod()`, which:

1. **Writes the route cache** if it does not yet exist.
2. **Calls `matchDefinedRoute()`** — iterates registered routes, strips the base path from `REQUEST_URI`, and matches against each route's pattern (with `{param}` converted to `([^/]+)` regex). Returns the first match.
3. **Falls back to auto-discovery** if no defined route matches — splits `REQUEST_URI` into segments and maps them to `controller/method/id`.

Defined routes always win over auto-discovery.

---

## Controller Execution

Controllers are resolved through the DI container. A typical controller method:

```php
public function index(): void
{
    $payload = container(BlogService::class)->buildIndexPayload();
    $view    = DGZ_View::getView('blog', $this, 'html');
    $this->setPageTitle('Blog');
    $view->show($payload);
}
```

The controller:
1. Calls a service to get a flat `$payload` array (no DB calls in the controller).
2. Gets a view object via `DGZ_View::getView()`.
3. Calls `$view->show($payload)` to render and output.

---

## View and Layout Rendering

`$view->show($payload)` runs the view class's `show()` method, which:
1. Unpacks `$payload` with `extract($viewModel)` or direct array access.
2. Outputs HTML using inline PHP.

The layout (the HTML shell with `<head>`, navigation, footer) is applied by the controller before the view renders. You select it with:

```php
$this->setLayoutDirectory('seoMaster');
$this->setLayoutView('seoMasterLayout');
```

The layout's `display()` method wraps the view's output in the full page HTML.

---

## API Requests

API requests follow the same bootstrap path but diverge at the controller:

```
DGZ_Router::route()
    ↓
API Controller (uses DGZ_APITrait)
    ↓
$this->validateToken()     — verifies JWT; sends 401 and halts if invalid
    ↓
Service layer              — same services as web controllers
    ↓
$this->respond(200, $data) — sends JSON response with correct headers
```

---

## Session State

| State | Key | Value |
|---|---|---|
| Unauthenticated | `$_SESSION['_Guest']` | `'visitor'` |
| Authenticated | `$_SESSION['authenticated']` | set (truthy) |
| Session start | `$_SESSION['start']` | Unix timestamp |
| Remember me | `$_COOKIE['rem_me']` | username |

Session timeout is 2 hours (7200 seconds). The `rem_me` cookie extends both the session and its own TTL to 4 days on each request.

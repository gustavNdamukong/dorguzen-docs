# Routing

## REQUEST ROUTING IN DORGUZEN (DGZ)

Dorguzen (DGZ) is a true MVC framework, and like most MVC systems, routing plays a key role in directing HTTP requests to the appropriate controllers and actions. DGZ provides two routing mechanisms that can be used independently or together:

1. Automatic Route Discovery
2. Defined Routes

Both approaches support regular controllers, module controllers, and API controllers.

---

## 1. Automatic Route Discovery

Automatic route discovery is DGZ's default routing mechanism. It maps incoming URLs to controllers and methods by analysing the URI string — no manual route definitions needed.

### 1.1 How It Works

A typical DGZ request URI looks like this:

```
Local:       http://localhost/yourapp/text1/text2/text3
Production:  http://yourapp/text1/text2/text3
```

DGZ splits the URI into segments separated by slashes. It first determines the environment (local or production), then skips the application-name segment and interprets the rest.

```
Environment   Example URI                              Segments
-----------   --------                                 --------
Local         http://localhost/yourapp/contact/show/10  0:localhost 1:yourapp 2:contact 3:show 4:10
Production    http://yourapp/contact/show/10            0:yourapp   1:contact 2:show    3:10
```

### 1.2 Segment-to-Controller Mapping

The first meaningful segment after the app name is the controller name.

```
URI       → Controller looked up
/contact  → ContactController   (found in src/controllers/)
/about    → AboutController     (found in src/controllers/)
/seo      → SeoController       (found in modules/seo/controllers/ — module route)
```

DGZ checks controller locations in this order:

1. `src/controllers/`            — regular application controllers
2. `modules/{name}/controllers/` — module entry controllers (if the segment matches an active module)

### 1.3 Default Action

If the URI contains only the controller segment (e.g. `/contact`), DGZ calls the controller's `getDefaultAction()` method automatically. All controllers must implement `getDefaultAction()`.

### 1.4 Methods and Parameters

Additional URI segments are interpreted as either a method name or a parameter value.

```
URI                       Action
/about/show               Calls show() on AboutController
/about/show/10            Calls show() with Request->targetId = 10
/about/show/clothes       Calls clothes() on AboutController
/about/show/clothes/4     Calls clothes() with Request->targetId = 4
```

Tip: intermediary segments can be omitted for cleaner URLs.
`/about/clothes/4` works the same as `/about/show/clothes/4`.

This makes for readable, SEO-friendly URLs like:

```
/employee/team-members/job-roles
/employee/team-members/code-of-conduct
```

### 1.5 Module Sub-Controllers and Automatic Route Discovery

A module can have more than one controller. When automatic route discovery encounters a module segment in the URL, it needs to know whether the next segment is a method on the module's default (entry) controller, or the name of a different controller inside that module.

DGZ resolves this by calling `getControllers()` on the module's entry controller. This method returns an array of all controller class names registered in that module. DGZ checks whether the next URL segment matches one of those names and routes accordingly.

```
URL: /seo/analytics/report
→ DGZ sees 'seo' is an active module
→ calls SeoController::getControllers()
→ finds 'AnalyticsController' in the list
→ routes to AnalyticsController::report()

URL: /seo/refresh
→ DGZ sees 'seo' is an active module
→ calls SeoController::getControllers()
→ 'refresh' is not in the controller list
→ treats it as a method: calls SeoController::refresh()
```

To support this, a module's entry controller must implement `DGZ_ModuleControllerInterface`, which requires one method:

```php
public function getControllers(): array;
```

There are two ways to fulfil this requirement:

Option A — use `DGZ_ModuleControllerTrait` (recommended, least boilerplate):

```php
use Dorguzen\Core\DGZ_ModuleControllerInterface;
use Dorguzen\Core\DGZ_ModuleControllerTrait;

class SeoController extends DGZ_Controller implements DGZ_ModuleControllerInterface
{
    use DGZ_ModuleControllerTrait;

    protected array $controllers = [
        'AnalyticsController',
        'ReportsController',
    ];
}
```

The trait provides `getControllers()` automatically, returning `$this->controllers`.

Option B — implement the interface directly (more explicit):

```php
class SeoController extends DGZ_Controller implements DGZ_ModuleControllerInterface
{
    protected array $controllers = ['AnalyticsController', 'ReportsController'];

    public function getControllers(): array
    {
        return $this->controllers;
    }
}
```

IMPORTANT: A module with only one controller (its default entry controller) does NOT need `DGZ_ModuleControllerInterface` or `getControllers()` at all. The router only calls `getControllers()` when it needs to resolve a second URL segment that could be either a sub-controller or a method. If you add sub-controllers to a module later, implementing the interface becomes mandatory — without it, the router will throw a fatal 'method not found' error.

---

## 2. Defined Routes

Defined routes give you explicit, full control over how requests are handled. They are declared in route files and matched before automatic discovery is attempted — defined routes always take priority.

All route files live in the `routes/` directory:

```
routes/web.php   — web (HTML) routes
routes/api.php   — API routes
```

### 2.1 Web Routes

Web routes are defined in `routes/web.php`. Each route needs only two arguments:
  - the URI string
  - the action (`ControllerName@methodName`)

```php
<?php
/** @var Dorguzen\Core\DGZ_Router $router */

$router->get('/contact',  'FeedbackController@defaultAction');
$router->get('/home',     'HomeController@home');
$router->post('/login',   'AuthController@login');
```

The URI is what a visitor types in the browser. The action names the controller and method that will handle the request.

Supported HTTP verbs: `get`, `post`, `put`, `patch`, `delete`.

Route parameters:

```php
$router->get('/shop/{id}', 'ShopController@show');
```

The `{id}` value is captured and available via `request()->getTargetId()` or `$_REQUEST['targetId']`.

### 2.2 Module Routes (Defined)

Module controllers — including non-default sub-controllers — work perfectly with defined routes. Specify the module name as the third argument:

```php
$router->get('/seo/refresh',             'Seo@defaultAction',    '', 'seo');
$router->get('/seo/analytics',           'Analytics@index',      '', 'seo');
$router->get('/seo/analytics/report',    'Analytics@report',     '', 'seo');
```

DGZ resolves 'Analytics@index' to `modules/seo/controllers/AnalyticsController.php` with namespace `Dorguzen\Modules\Seo\Controllers`. The module name ('seo') provides the namespace root; the controller name ('Analytics') identifies the class within it.

Note: when using defined routes for module sub-controllers, `DGZ_ModuleControllerInterface` is NOT required. The controller is specified explicitly in the route definition, so there is nothing to discover dynamically. The interface is only needed for automatic route discovery.

### 2.3 API Routes

API routes are defined in `routes/api.php`. They require a version number as the third argument, and controllers live in `src/api/{version}/controllers/`:

```php
<?php
/** @var Dorguzen\Core\DGZ_Router $router */

// POST /api/v1/auth/register  →  src/api/v1/controllers/AuthApiController.php
$router->apiPost('/api/v1/auth/register', 'AuthApi@register', 'v1');
$router->apiPost('/api/v1/auth/login',    'AuthApi@login',    'v1');
```

Supported API verbs: `apiGet`, `apiPost`, `apiPut`, `apiPatch`, `apiDelete`.

Arguments:
  1. URI string         (required) — always prefix with `/api/v{n}/`
  2. Action             (required) — `ControllerName@method`
  3. API version        (required) — e.g. 'v1'

It is strongly recommended to prefix all API routes with `/api/` and the version number. This makes the version explicit to API consumers and allows future versions to coexist cleanly.

Ensure the corresponding version directory (e.g. `src/api/v1/controllers/`) exists for each supported version.

⚠️  .htaccess and URL depth

DGZ uses a single catch-all rewrite rule in `.htaccess`:

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

This rule passes any request to `index.php` — unless the path maps to a real file or directory on disk, in which case Apache serves it directly (static assets, uploads, etc.).

Because all routing logic lives inside `DGZ_Router` (which reads the full `REQUEST_URI`), `.htaccess` does not need to know anything about URL depth. Routes of any length — 1 segment or 10 — are handled identically. This means:

  - Auto-discovery routes (`/shop/products`, `/seo/analytics/report`) work at any depth.
  - Defined routes (`/api/v1/auth/login`, `/api/v1/docs/spec`) work at any depth.
  - There is no "numbered rules" limit to hit.

If an API route (or any route) returns a bare Apache 404 — a plain HTML "Not Found" page rather than a DGZ error view — it means the request never reached PHP. Check that the catch-all rule is present and correct in `.htaccess`.

### 2.4 Route Caching

DGZ caches all registered routes to a flat PHP file on the first request after boot:

```
storage/cache/routes.php
```

On subsequent requests the router loads that cached file directly, bypassing the route files entirely for performance. This is transparent during normal development, but it becomes important to know about in one specific situation:

⚠️  Whenever you add, remove, or change a route in `routes/web.php` or `routes/api.php`, delete the route cache file so the router picks up your changes:

```bash
rm storage/cache/routes.php
```

The cache is rebuilt automatically on the next request.

Symptoms of a stale route cache:
  - A new route returns a 404 or "No controller was found" error even though the controller file exists and the route is correctly defined.
  - Removing a route has no effect — requests still hit the old handler.

If you see either of those after editing a route file, clearing the cache is the first thing to try.

### 2.5 Enforcing JWT Authentication on API Routes

Add `DGZ_APITrait` to any API controller that needs JWT validation:

```php
use Dorguzen\Core\DGZ_APITrait;

class MyApiController extends DGZ_Controller
{
    use DGZ_APITrait;

    public function protectedEndpoint(): void
    {
        $this->setHeaders();
        $tokenResponse = $this->validateToken();
        if (!$this->validatedToken) {
            $tokenResponse->send();
            exit();
        }
        // $this->validatedUser['user_id'] is now available
        ...
    }
}
```

`DGZ_APITrait` provides: `setHeaders()`, `validateToken()`, `refreshToken()`, `generateTokens()`, and refresh-token persistence helpers (`saveRefreshToken`, `getRefreshToken`, `updateRefreshToken`).

### 2.6 The JWT Secret Key

The JWT secret is set in your `.env` file:

```ini
APP_JWT_SECRET=your-secret-here
APP_JWT_ENCODING=HS256
```

This value is the private signing key used by the Firebase JWT PHP library to sign and verify tokens. It does NOT come from the Firebase platform — you generate it yourself. Any non-empty string will work technically, but for production you should use a strong random value of at least 32 characters.

The easiest way to generate one is with openssl in your terminal:

```bash
openssl rand -base64 48
```

Copy the output and paste it as your `APP_JWT_SECRET` value.

Important notes:

  - Never commit your real secret to Git. Keep it only in `.env` (which is in `.gitignore`). Use `.env.example` to document the key name with an empty or placeholder value.

  - If you rotate the secret (change it), all existing tokens are immediately invalidated — users will need to log in again. This is expected behaviour and is the correct way to revoke all active sessions at once if needed.

  - The placeholder value in the repo (xxxxxxxxxx...) is functional but weak. Replace it before going to production.

### 2.7 Named Routes

Any defined route can be given a name by chaining `->name()` onto its declaration. A name is just a stable, human-friendly handle for a route — useful when you want to refer to a route from elsewhere without hard-coding its URI.

```php
$router->get('/',           'HomeController@defaultAction')->name('home');
$router->get('/gallery',    'GalleryController@index', 'gallery')->name('gallery');
$router->get('/auth/login', 'AuthController@login')->name('login');
```

`->name()` works on every verb (`get`, `post`, `put`, `patch`, `delete`) and on module routes, because each route-registration method returns a `DGZ_RouteDefinition` object whose `name()` method records the name back onto the stored route.

The router keeps a fast name→route lookup (built like a database index) so a named route can be retrieved directly:

```php
$router->getRouteByName('home');   // returns that route's definition array, or null
$router->getNamedRoutes();         // returns the full map of name => route
```

Names are preserved through the route cache (see 2.4) — they are reconstructed automatically when cached routes are loaded, so naming costs nothing at runtime.

### 2.8 Middleware on Routes

DGZ runs two kinds of middleware around the dispatch of a request.

Global middleware runs on **every** request automatically. It lives in `middleware/globalMiddleware/` and is discovered (or loaded from cache) on each request. The framework ships, among others:

| Middleware | Purpose |
|---|---|
| `CsrfPsrMiddleware` | Validates CSRF tokens on state-changing requests |
| `FormValidationMiddleware` | Runs declared form-validation rules |

To exempt all API routes from CSRF validation (APIs use JWT, not session CSRF tokens), set in `.env`:

```ini
APP_API_CSRF_EXCEPTION='/api/'
```

Route middleware is applied to specific routes only, by alias. Declare it with `->middleware([...])` and wrap the affected routes in a `->group()` closure; every route registered inside the group inherits that middleware, and it is popped again once the group closes (so it never leaks to later routes):

```php
$router->middleware(['auth'])->group(function () use ($router) {
    $router->get('/admin',              'AdminController@dashboard');
    $router->get('/admin/manageUsers',  'AdminController@manageUsers');
});
```

Aliases (e.g. `'auth'`) are resolved to classes in `middleware/routeMiddleware/` by the HTTP kernel — `'auth'` maps to `AuthMiddleware`. When a matched route carries middleware, the router builds and runs that route-middleware pipeline before the controller is dispatched; routes with no middleware skip the step entirely. Like global middleware, route middleware is cacheable.

---

## 3. Hybrid Routing

DGZ allows you to mix both approaches freely. Defined routes are checked first on every request. If no defined route matches, automatic discovery takes over. This lets you use auto-discovery for rapid development while adding explicit routes where you need fine-grained control, middleware, or named routes.

### Conclusion

Automatic route discovery is ideal for fast development — zero configuration, just create a controller and visit its URL. Defined routes give you full control for production-quality apps, APIs, and complex routing logic. Both approaches support regular controllers, module controllers, and API controllers equally well.

Happy routing — the DGZ way.

---

## FRONTEND/UI AND THE URL

This is about the ability of your application to pass data to and from the frontend, via URL parameters, template engines, redirecting to different views, and the generation of HTML assets for view file layouts. These also involves the ability of the programming language to manipulate these elements on the fly. The Javascript programming language really shines in this domain. This also covers everything the users sees on the screen (UI), and the manipulation thereof, programmatically whether it be through printing data to the screen, how to create comments, passing data to the view in the case of development frameworks, passing to or retrieving data from the URL etc.

---

## Redirecting the Visitor — redirect() and redirectTo()

A "redirect" is when your application, instead of drawing a page, tells the visitor's browser "what you asked for actually lives at a different address — go there instead." The browser then makes a fresh request to that other address. This is an everyday need: after a successful login you send the user to their dashboard, after a form is saved you send them back to a list page, and so on. Dorguzen gives every controller TWO methods for this (both inherited from DGZ_Controller): redirect() and redirectTo(). They look alike but solve different problems, so it pays to know both.

### A word first on "HTTP status codes"

Every redirect carries a small number — the HTTP status code — that tells the browser, and any search engine, WHY the redirect is happening. Two of them matter here:

  - 302 "Found" (a *temporary* redirect): "use this other address for now, but the original address is still the real one — keep it." This is the safe, normal default for app navigation such as post-login or post-save redirects.
  - 301 "Moved Permanently": "the original address is gone for good; the real address is now this new one." A search engine reacts to a 301 by moving the old page's ranking onto the new address and dropping the old one from its index. Use a 301 only when an address should never be used again — for example when two URLs show the exact same page and you want to collapse them into one (see the canonical-URL example at the end of this topic).

### redirect() — jump to another controller/action inside THIS app

This is the one you will use most of the time. You give it a controller name (and optionally a method name), and it sends the visitor to that route inside your own application. It builds the full web address for you from your app's base URL (via Config::getFileRootPath()), so you never hard-code something like "http://localhost/myapp/..." — the same call works on your local machine and on the live server.

```php
// inside any controller method:
$this->redirect('auth', 'login');                       // -> /auth/login
$this->redirect('auth/login');                          // -> /auth/login  (slash form, same thing)
$this->redirect('news');                                // -> /news        (NewsController's default action)
$this->redirect('');                                    // -> /            (the home page)
$this->redirect('shop', 'manage', ['userId' => 42]);    // -> /shop/manage?userId=42
```

redirect() ALWAYS sends a 302 (temporary), and it ALWAYS calls exit() for you afterwards. That second point matters: a redirect is just an HTTP header, so without an exit() the rest of your method would keep running. redirect() takes care of that for you. (If you happen to call it more than once in one request, the last call wins — Dorguzen clears the earlier Location header first.)

### redirectTo() — send the browser to ANY URL, with a status code you choose

Sometimes "another controller/action in this app" is not what you need. You might want to send the visitor to a completely different website, or — far more commonly — you need to control the status code (a 301 instead of the default 302). That is exactly what redirectTo() adds:

```php
public function redirectTo(string $url, int $statusCode = 302): void
```

You hand it a finished URL — absolute like "https://example.com/" or root-relative like "/path" — and, optionally, the status code. Like redirect(), it calls exit() for you and obeys the same last-call-wins rule.

```php
// permanent (301) redirect to your site's canonical home page:
$this->redirectTo($this->config->getHomePage(), 301);

// temporary (302 — the default) redirect to an external site:
$this->redirectTo('https://status.example.com');
```

### Which one should I use?

| Question                      | redirect()                        | redirectTo()                       |
| ----------------------------- | --------------------------------- | ---------------------------------- |
| Where can it send the user?   | a controller/action in THIS app   | any URL (your app, or external)    |
| How do you name the target?   | controller + method names         | a finished URL string              |
| Builds the URL for you?       | yes, from getFileRootPath()       | no — you pass the full URL         |
| Status code                   | always 302 (temporary)            | your choice (302 default, or 301)  |
| Calls exit() for you?         | yes                               | yes                                |

Rule of thumb: use redirect() for ordinary in-app navigation (after login, after saving a form). Reach for redirectTo() only when you need a specific status code (almost always a 301) or a target outside your own routes.

You may wonder why we did not simply add a status-code option to redirect() itself. redirect()'s whole job is "go to a named route in this app", and plenty of existing code relies on that exact, simple behaviour. Bolting URL-and-status handling onto it would blur that contract and risk those callers. Keeping redirectTo() as a separate, small primitive leaves redirect() untouched while giving you the extra power for the rare cases that need it.

### A real example — the homepage "canonical URL" fix

A "canonical URL" is the single, official address for a page. A classic SEO (Search Engine Optimisation) problem is the SAME page answering at more than one address — for instance a home page reachable at "/", "/home", "/index" AND "/index.php". A search engine then sees four addresses showing identical content ("duplicate content") and splits that page's ranking across them instead of crediting one strong page.

Dorguzen handles this for you. When an incoming request resolves to one of those homepage aliases (whether through an explicit /home route or through Dorguzen's route auto-discovery), the framework issues a 301 to the real root using exactly the call shown above:

```php
$this->redirectTo($this->config->getHomePage(), 301);
```

getHomePage() returns the correct absolute base URL for the current environment (local vs live), so this one line does the right thing everywhere. The outcome: any visitor or search-engine crawler that lands on "/home", "/index" or "/index.php" is permanently sent to "/", and the home page's ranking is consolidated onto a single canonical address.

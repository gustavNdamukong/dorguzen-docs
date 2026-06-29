# Modules Overview

## What is a Module?

A module in Dorguzen is a self-contained mini-application that lives inside your main application. Think of it as a fully separated feature area — with its own controllers, models, views, and services — that plugs cleanly into Dorguzen and works alongside the rest of your app without tangling up your main codebase.

Modules are the Dorguzen equivalent of packages or plugins. They are ideal for discrete feature sets that could, in principle, be extracted and reused across projects: an SEO manager, a payment gateway, an SMS notification system, a blog engine, a reports dashboard, and so on.

The key properties of a module:

- Fully separated — its files live under `modules/{name}/` and are namespaced independently.
- Mirrors main-app structure — controllers, models, views, services mirror the layout of the main application, so there is no new mental model to learn.
- First-class citizen — module views, models, and controllers have the same capabilities as anything in the main application. A module controller extends the same `DGZ_Controller` base class, a module model extends `DGZ_Model`, and so on.
- Hooks into app resources easily — a module can read from the shared Config, use any service registered in the DI container, call any main-app model, send emails via `DGZ_Messenger`, use helpers, etc. It is separated by convention, not by hard isolation.
- Toggle on or off — each module can be enabled or disabled with a single flag in `.env`, with no code changes needed anywhere else.

---

## Folder Structure

To create a module, add a subdirectory under `/modules/` and give it the name of the module (all lowercase, no spaces). Mirror the main-app folder structure inside it:

```
myApplication/
└── modules/
    └── blog/
        ├── controllers/
        ├── models/
        ├── services/
        └── views/
```

You can add as many sub-folders as you need (`events/`, `listeners/`, `helpers/`, etc.). The structure is a convention, not a hard requirement — Dorguzen only cares about the `controllers/` directory when routing. Everything else is up to you.

All module controllers must extend the same base controller as the main application:

```php
Dorguzen\Core\DGZ_Controller
```

Module view files are complete HTML templates, identical to those in the main `/views` directory. To render a module view, use the dedicated factory method:

```php
DGZ_View::getModuleView($moduleName, $templateName, $controller, 'html');
```

This means module views integrate with the layout system, flash messages, CSRF helpers, and everything else the main application provides.

---

## Toggling a Module On or Off

Every module is registered in `configs/Config.php` under the `'modules'` key:

```php
'modules' => [
    'seo'      => 'on',
    'payments' => 'off',
    'sms'      => 'on',
],
```

The value is driven by a corresponding `.env` flag. For example, for an SMS module:

```php
# .env
MODULES_SMS_STATUS=on
```

And in `configs/Config.php`:

```php
'modules' => [
    'sms' => env('MODULES_SMS_STATUS', 'off'),
],
```

When a module is set to `'off'`, the router will not resolve any URLs to it and it is completely inactive. Set it to `'on'` and it is available immediately — no code changes, no cache warm-up beyond clearing the route cache.

This means you can ship a module in your codebase but keep it dormant until it is needed, or disable it per-environment (e.g. off in staging, on in production).

---

## Adding Module Configuration

If your module needs its own configuration values, you have two options.

Option A — add keys directly into `configs/Config.php`. Prefix the keys with the module name to keep things readable:

```php
// configs/Config.php
'blog_posts_per_page' => 10,
'blog_allow_comments' => true,
```

Option B (recommended for larger modules) — create a dedicated config file under the `configs/modules/` directory. Dorguzen automatically discovers and merges everything in that directory into the unified config:

```
configs/
└── modules/
    └── blog.php    ← your module config
```

The file should expose a `getConfig()` method that returns an array:

```php
// configs/modules/blog.php
function getConfig(): array {
    return [
        'posts_per_page' => 10,
        'allow_comments' => true,
    ];
}
```

Dorguzen reads and merges this automatically. The values are then globally available anywhere you call `$this->config->getConfig()` or the `config()` helper, just like any other config value.

---

## Adding Module Middleware

If your module needs middleware, place it in the main application's middleware directory — not inside the module folder. The file name must end with `Middleware.php`:

```
middleware/
└── globalMiddleware/
    └── BlogMiddleware.php
```

DGZ automatically identifies middleware files by this naming convention. Once the file is there, you can attach it to any route or route group exactly as you would any other middleware.

---

## Routing Requests to Modules

There are two ways to route requests to a module: auto-discovery and defined routes. Both work equally well. Choose whichever suits the complexity of your module.

---

### 1. Auto-Discovery Routing

With auto-discovery, Dorguzen resolves URLs to module controllers purely by inspecting the URL segments — no routes need to be registered. The URL format for modules is:

```
/{moduleName}/{method}
/{moduleName}/{subController}/{method}
```

Example:

```
/blog                 → BlogController::defaultAction()
/blog/latestPosts     → BlogController::latestPosts()
/blog/admin/dashboard → AdminController::dashboard()   (sub-controller — see below)
```

For auto-discovery to work, the module must be registered as `'on'` in `configs/Config.php` (see Toggling above), and must have a default entry controller named after the module:

```
modules/blog/controllers/BlogController.php
```

This entry controller is the router's gateway into the module. If your module only has one controller, that is all you need.

---

### Registering Sub-Controllers (auto-discovery)

When a module has more than one controller, the router needs to know which URL segments refer to sub-controllers rather than methods. You teach it this by implementing `DGZ_ModuleControllerInterface` on the entry controller and declaring the `$controllers` array:

```php
use Dorguzen\Core\DGZ_ModuleControllerInterface;
use Dorguzen\Core\DGZ_ModuleControllerTrait;

class BlogController extends DGZ_Controller implements DGZ_ModuleControllerInterface
{
    use DGZ_ModuleControllerTrait;

    protected array $controllers = [
        'AdminController',
        'ApiController',
    ];
}
```

With this in place, the URL `/blog/admin/dashboard` resolves to `AdminController::dashboard()` inside the blog module. Without it, the router would treat `'admin'` as a method name on `BlogController` and fail.

URL resolution rules with sub-controllers:

```
/blog/admin/dashboard
  └─ module:      blog
  └─ controller:  AdminController   (found in $controllers)
  └─ method:      dashboard()

/blog/latestPosts
  └─ module:      blog
  └─ controller:  BlogController    ('latestPosts' not in $controllers → it's a method)
  └─ method:      latestPosts()
```

Important: a module with only one controller does NOT need `DGZ_ModuleControllerInterface` at all. Only add it when you introduce a second controller into the module.

---

### 2. Defined Routes

Defined routes give you full, explicit control. They work for any controller in any module, including sub-controllers, with no need for `DGZ_ModuleControllerInterface`.

Pass the controller name as the action and the module name as the third argument:

```php
// routes/web.php
$router->get('/blog/latest',          'BlogController@latestPosts',  'blog');
$router->get('/blog/admin/dashboard', 'AdminController@dashboard',   'blog');
$router->post('/blog/admin/savePost', 'AdminController@savePost',    'blog');
```

DGZ resolves the controller class as:

```php
Dorguzen\Modules\Blog\Controllers\BlogController
Dorguzen\Modules\Blog\Controllers\AdminController
```

The module name (third argument) provides the namespace root; the controller name in the action string identifies the class within it.

With defined routes you do not register the module in `configs/Config.php` — the route definition itself is the authoritative source of where to find the controller. The `'on'`/`'off'` toggle still works as a coarse on/off switch for auto-discovery, but defined routes bypass it.

---

## Which approach should I use?

Use auto-discovery when:

- You are prototyping or building quickly.
- Your module URLs follow a simple, predictable pattern.
- You want zero route-file maintenance as the module grows.

Use defined routes when:

- You need precise control over URL shapes.
- The module exposes a REST-style API.
- You need middleware on specific module routes.
- You want the routing to be self-documenting in `routes/web.php`.

Both approaches can coexist: some module routes defined explicitly, others auto-discovered.

---

Return to [Introduction]({{base}}docs/introduction) or use the sidebar to navigate.

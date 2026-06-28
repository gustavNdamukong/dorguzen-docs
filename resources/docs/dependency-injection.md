# Dependency Injection

## Dependency Injection and the DI Container

Tips and best-practices on how to use the DI container:

- Register models as singletons at bootstrap

---

## Dependency Injection and the DI Container

- NEVER resolve classes from a view.
- Use only one instance of a class in a controller. Do so by having the dependency class injected via the constructor at initialisation.

You can resolve classes from the DI container from anywhere in your project in any of the following ways:

```php
use Dorguzen\Models\Logs;

// write something to the logs table
$container = container();
$container->get(Logs::class)->log(...);
```

or

```php
$container = container();
$logs = $container->get(Logs::class);
$logs->log(...);
```

or

```php
$logs = container(Logs::class);
$logs->log(...);
```

or

```php
$data = container(MyObject::class)->methodOnMyObject();
```

Hereby chaining a call on `container()` by directly calling a method on the resolved class. This works because `container()` returns the resolved object, hence you can directly chain calls on it.

---

## Tips and best-practices on how to use the DI container

### ✅ Register models as singletons at bootstrap

Before anything else, in `bootstrap\app.php`, register an object with the container as a singleton like so:

```php
//------------------------------------------------------------------------
//  REGISTER ESSENTIAL CLASSES WITH THE CONTAINER BEFORE ANYTHING ELSE
//------------------------------------------------------------------------
$container->singleton(Users::class, fn($c) => new Users($c->get(Config::class)));
$container->singleton(Logs::class, fn($c) => new Logs($c->get(Config::class)));
$container->singleton(News::class, fn($c) => new News($c->get(Config::class)));
$container->singleton(Subscribers::class, fn($c) => new Subscribers($c->get(Config::class)));
//------------------------------------------------------------------------
```

Alternatively, you can automate this by scanning the models folder.

After this, every time you resolve a class from the container, like so:

```php
container(Users::class)
```

The returned object will ALWAYS be one and the same instance. The result is:

- ✅ No repeated reflection.
- ✅ No repeated constructor work.
- ✅ No repeated config loads.
- ✅ No repeated error_log spam.

This alone will drop page load times from over 30 seconds to < 1 second.

---

## The Container Class

The container is `DGZ_Container` (`core/DGZ_Container.php`). A global `container()` helper (defined in `bootstrap/app.php`) provides access everywhere:

```php
// Resolve a class
$logs = container(Logs::class);

// Get the container itself
$c = container();

// Check if a class is resolvable before pulling it
if (container()->has(MyService::class)) {
    $myService = container(MyService::class);
}
```

`has()` returns true if the class is already resolved, has a registered binding, or is an autowirable (existing) class.

---

## Autowiring

If a class is not explicitly registered, the container uses PHP reflection to resolve it — inspecting the deepest constructor up the inheritance chain (`getConstructor()`), resolving each type-hinted parameter recursively (`resolveDependencies()`), then caching the result. Reflection objects themselves are cached in `$reflectionCache` so each class is reflected only once.

```php
class NewsController extends DGZ_Controller
{
    // Container sees the type hint and injects NewsService automatically
    public function __construct(private NewsService $newsService)
    {
        parent::__construct();
    }
}
```

Requirements for autowiring:

- Every dependency must be a type-hinted class (a non-builtin type). Untyped parameters cannot be resolved.
- Each dependency class must itself be resolvable.
- Scalar parameters (`string`, `int`, etc.) must have default values; otherwise the container cannot supply them and resolves them to `null` (or, via `resolveParameter()`, throws `Cannot resolve parameter $...`).

---

## Registering Bindings: `singleton()`, `set()`, `bind()`

`singleton()` registers a factory whose instance is created once and reused for the entire request:

```php
$container->singleton(NewsService::class, fn($c) => new NewsService(
    $c->get(News::class),
));
```

`set()` and `bind()` are equivalent ways to register a factory closure. The first `get()` call runs the closure and caches the resulting instance in `$instances`, so by default every binding behaves like a singleton for the rest of the request:

```php
// set() — commonly used with a static guard for framework objects
$container->set(DGZ_Request::class, function() {
    static $request;
    if (!$request) {
        $request = new DGZ_Request();
    }
    return $request;
});

// bind() — ergonomic alias of set()
$container->bind(SomeClass::class, fn($c) => new SomeClass($c->get(Config::class)));
```

---

## Resolution Priority

When you call `container(Foo::class)` (i.e. `$container->get()`), the order is:

1. **Cached instance** — returned immediately if already resolved this request (`$instances`).
2. **Registered binding** — the factory closure is called and the result cached.
3. **Reflection autowiring** — the class must exist (`class_exists()`), then its constructor is inspected and dependencies resolved recursively.
4. **Exception** — thrown if the class cannot be resolved or instantiated (`Cannot resolve: ...`, `Cannot instantiate abstract type: ...`).

---

## What Gets Registered at Bootstrap

`bootstrap/app.php` pre-registers the framework and application classes. Representative groups:

| Category | Examples |
|---|---|
| Core framework | `DGZ_Request`, `DGZ_Response`, `DGZ_Application`, `DGZ_Validator`, `DGZ_DBAdapter` |
| Queue system | `SyncQueue`, `DatabaseQueue`, `QueueManager` |
| Event system | `EventService` (wires its own `EventDispatcher` + `ListenerResolver` internally) |
| Core models | `Users`, `Logs`, `Password_reset`, `ContactFormMessage`, `BaseSettings`, `Refresh_tokens`, `News` |
| Core services | `AuthService`, `AdminService`, `FeedbackService`, `NewsService` |
| Module models | `GalleryAlbum`, `GalleryImage`, `BlogPost`, `BlogCategory`, `BlogComment`, `VideoAlbum`, `Video`, `Testimonials` |
| Module services | `GalleryService`, `GalleryAdminService`, `BlogService`, `BlogAdminService`, `VideoService`, `VideoAdminService`, `TestimonialsService` |

---

## Adding a New Model and Service

```php
// 1. Add use statements at top of bootstrap/app.php
use Dorguzen\Models\Portfolio;
use Dorguzen\Services\PortfolioService;

// 2. Register the model
$container->singleton(Portfolio::class, fn($c) => new Portfolio($c->get(Config::class)));

// 3. Register the service, injecting the model
$container->singleton(PortfolioService::class, fn($c) => new PortfolioService(
    $c->get(Portfolio::class),
));
```

The service can then be injected into any controller via constructor injection or pulled with `container(PortfolioService::class)`.

---

## Circular Dependency Detection

The container tracks classes currently being resolved in `$resolving`. If a class is requested while already being resolved, it logs and throws rather than recursing infinitely:

```
⚠️ [Container] Circular dependency detected while resolving: App\Services\Foo
```

Design your dependency graph one-way: lower-level services should not depend on higher-level ones.

---

## Config and Environment Helpers

Two global helpers (in `bootstrap/helpers.php`) work alongside the container:

```php
env('APP_ENV', 'production')   // reads system env vars via getenv()/$_ENV/$_SERVER
config('app.defaultLayout')    // dot-notation reads from the config repository
```

`env()` is meant for config files only and casts well-known literals (`true`/`false`/`null`/`empty`). The `Config` class (`src/config/Config.php`) is the typed accessor injected into models and available on controllers as `$this->config`.

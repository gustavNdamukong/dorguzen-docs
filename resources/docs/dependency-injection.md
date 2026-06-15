# Dependency Injection

Dorguzen ships with a built-in DI container (`DGZ_Container`, `core/DGZ_Container.php`). It supports reflection-based autowiring, singleton registration, and recursive dependency resolution.

---

## The Global Container

The container is bootstrapped in `bootstrap/app.php`. A global helper provides access everywhere:

```php
// Resolve a class
$newsService = container(NewsService::class);

// Get the container itself
$c = container();

// Check if resolvable
if (container()->has(MyService::class)) { ... }
```

---

## Autowiring

If a class is not explicitly registered, `container()` uses PHP reflection to resolve it — inspecting the constructor, resolving each type-hinted parameter recursively, and caching the result.

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
- Every dependency must be a class (not `string`, `int`, etc.).
- Each class must itself be resolvable.
- Scalar parameters must have default values, or the class must be manually registered.

---

## Registering a Singleton

Use `singleton()` to register a class whose instance is created once and reused for the entire request:

```php
$container->singleton(NewsService::class, fn($c) => new NewsService(
    $c->get(News::class),
));
```

Equivalent alternatives:

```php
// set() with an explicit singleton guard
$container->set(DGZ_Request::class, function() {
    static $request;
    return $request ??= new DGZ_Request();
});

// bind() — factory is called each time, but container caches the result
$container->bind(SomeClass::class, fn($c) => new SomeClass($c->get(Config::class)));
```

---

## Resolution Priority

When you call `container(Foo::class)`, the order is:

1. **Cached instance** — returned immediately if already resolved this request.
2. **Registered binding** — factory closure is called and result is cached.
3. **Reflection autowiring** — constructor inspected, dependencies resolved recursively.
4. **Exception** — thrown if the class cannot be instantiated.

---

## What Gets Registered at Bootstrap

`bootstrap/app.php` pre-registers:

| Category | Examples |
|---|---|
| Core framework | `DGZ_Request`, `DGZ_Response`, `DGZ_Validator`, `DGZ_DBAdapter` |
| Queue system | `SyncQueue`, `DatabaseQueue`, `QueueManager` |
| Event system | `EventService`, `EventDispatcher`, `ListenerResolver` |
| Core models | `Users`, `Logs`, `News`, `Password_reset`, `ContactFormMessage`, `BaseSettings` |
| Core services | `AuthService`, `AdminService`, `FeedbackService`, `NewsService` |
| Module models | `GalleryAlbum`, `GalleryImage`, `BlogPost`, `BlogCategory`, `BlogComment`, `VideoAlbum`, `Video` |
| Module services | `GalleryService`, `GalleryAdminService`, `BlogService`, `BlogAdminService`, `VideoService`, `VideoAdminService` |

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

The container tracks classes currently being resolved. If the same class is requested while already resolving, it throws an exception rather than entering infinite recursion:

```
Circular dependency detected while resolving: App\Services\Foo
```

Design your dependency graph one-way. Lower-level services should not depend on higher-level ones.

---

## Config and Environment Helpers

Two global helpers work outside the container:

```php
env('APP_NAME', 'Dorguzen')       // reads from .env
config('app.defaultLayout')        // reads from configs/app.php
config('database.DBcredentials')   // reads from configs/database.php
```

The `Config` class (`src/config/Config.php`) is the typed accessor injected into models and available on controllers as `$this->config`.

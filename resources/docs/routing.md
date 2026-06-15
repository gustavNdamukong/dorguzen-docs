# Routing

Routes map incoming HTTP requests to controller methods. Web routes are defined in `routes/web.php` and API routes in `routes/api.php`.

---

## Defining Routes

```php
/** @var Dorguzen\Core\DGZ_Router $router */

$router->get('/path',    'ControllerName@method');
$router->post('/path',   'ControllerName@method');
$router->put('/path',    'ControllerName@method');
$router->patch('/path',  'ControllerName@method');
$router->delete('/path', 'ControllerName@method');
```

### Named Routes

```php
$router->get('/home', 'HomeController@defaultAction')->name('home');
$router->get('/blog', 'BlogController@index')->name('blog');
```

### Route Parameters

```php
$router->get('/docs/{slug}', 'DocsController@show');
```

The `{slug}` segment is captured and injected into the controller method as the first untyped parameter:

```php
public function show($slug): void  // no type hint — required for parameter injection
{
    // $slug = 'introduction'
}
```

### Module-Scoped Routes

A third argument marks a route as belonging to a module. The route is only dispatched when that module is enabled:

```php
$router->get('/gallery',       'GalleryController@index', 'gallery');
$router->get('/gallery/album', 'GalleryController@album', 'gallery');
```

If `MODULES_GALLERY_STATUS=off` in `.env`, these routes are not dispatched.

---

## Middleware

### Global Middleware

Global middleware runs on every request automatically. It lives in `middleware/globalMiddleware/`:

| Middleware | Purpose |
|---|---|
| `CsrfPsrMiddleware` | Validates CSRF tokens on POST/PUT/DELETE requests |
| `AuthMiddleware` | Sets guest/authenticated session state |
| `FormValidationMiddleware` | Runs validation rules when declared |

To exempt all API routes from CSRF validation, set in `.env`:

```ini
APP_API_CSRF_EXCEPTION=/api/
```

### Route Middleware

Apply middleware to specific routes or groups using `->middleware()` and `->group()`:

```php
$router->middleware(['auth'])->group(function() use ($router) {
    $router->get('/admin',              'AdminController@dashboard');
    $router->get('/admin/manageUsers',  'AdminController@manageUsers');
});
```

Route middleware classes live in `middleware/routeMiddleware/`.

---

## API Routes

API routes use dedicated methods and include a version string:

```php
$router->apiGet( '/api/v1/users',     'UserApiController@index',  'v1');
$router->apiPost('/api/v1/auth/login','AuthApiController@login',  'v1');
```

API controllers extend `DGZ_Controller` and use the `DGZ_APITrait` for JWT authentication.

---

## Auto-Discovery Routing

If no defined route matches, Dorguzen falls back to auto-discovery: it splits the URL path into segments and maps them to `controller/method/id`:

```
/contact           → ContactController@defaultAction
/contact/send      → ContactController@send
/news/article/42   → NewsController@article  ($straightUrlId = '42')
```

Auto-discovery checks these controller locations in order:
1. `src/controllers/{Name}Controller.php`
2. Module controllers (when module is enabled)

`getDefaultAction()` on the controller determines which method is called when only the controller segment is present in the URL.

---

## Route Cache

Routes are cached to `storage/cache/routes.php` after the first request. When the cache file exists, `routes/web.php` and `routes/api.php` are never loaded.

**Always delete the cache after changing any route:**

```bash
rm storage/cache/routes.php
# or
php dgz route:cache   # regenerates immediately
```

---

## Route Map

### Public Routes

| Method | URI | Handler |
|---|---|---|
| GET | `/` | `HomeController@defaultAction` |
| GET | `/portfolio` | `PortfolioController@portfolio` |
| GET | `/gallery` | `GalleryController@index` |
| GET | `/gallery/album` | `GalleryController@album` |
| GET | `/videos` | `VideosController@index` |
| GET | `/videos/album` | `VideosController@album` |
| GET | `/news` | `NewsController@news` |
| GET | `/news/article` | `NewsController@article` |
| GET | `/blog` | `BlogController@index` |
| GET | `/blog/post` | `BlogController@post` |
| POST | `/blog/comment` | `BlogController@comment` |
| GET | `/feedback` | `FeedbackController@contact` |
| POST | `/feedback/processContact` | `FeedbackController@processContact` |
| POST | `/subscribe` | `NewsletterController@subscribe` |
| GET | `/unsubscribe` | `NewsletterController@unsubscribe` |
| GET | `/search` | `SearchController@search` |

### Auth Routes

| Method | URI | Handler |
|---|---|---|
| GET | `/auth/login` | `AuthController@login` |
| POST | `/auth/doLogin` | `AuthController@doLogin` |
| GET | `/auth/logout` | `AuthController@logout` |
| GET | `/auth/signup` | `AuthController@signup` |
| POST | `/auth/register` | `AuthController@register` |
| GET | `/auth/verifyEmail` | `AuthController@verifyEmail` |
| GET | `/auth/reset` | `AuthController@reset` |
| POST | `/auth/resetPw` | `AuthController@resetPw` |

### User Routes

| Method | URI | Handler |
|---|---|---|
| GET | `/user/dashboard` | `UserController@dashboard` |
| GET/POST | `/user/changePw` | `UserController@changePw` |

### Admin Routes

| Method | URI | Handler |
|---|---|---|
| GET | `/admin` | `AdminController@dashboard` |
| GET | `/admin/manageUsers` | `AdminController@manageUsers` |
| GET/POST | `/admin/baseSettings` | `AdminController@baseSettings` |
| GET | `/admin/contactMessages` | `AdminController@contactMessages` |
| GET | `/admin/log` | `AdminController@log` |
| GET/POST | `/admin/blog/create` | `BlogController@createPost` |
| GET/POST | `/admin/gallery/create` | `GalleryController@createAlbum` |
| POST | `/admin/gallery/upload` | `GalleryController@uploadImages` |
| POST | `/admin/gallery/setCover` | `GalleryController@setCover` |
| GET/POST | `/admin/news/create` | `NewsController@createNews` |
| GET/POST | `/admin/portfolio/create` | `PortfolioController@createPortfolio` |
| GET/POST | `/admin/videos/create` | `VideosController@createAlbum` |
| POST | `/admin/videos/addVideo` | `VideosController@addVideo` |

### API Routes

| Method | URI | Handler |
|---|---|---|
| POST | `/api/v1/auth/login` | `AuthApiController@login` |
| POST | `/api/v1/auth/register` | `AuthApiController@register` |
| POST | `/api/v1/auth/refresh` | `AuthApiController@refresh` |
| GET | `/api/v1/docs` | `DocsApiController@index` |
| GET | `/api/v1/docs/spec` | `DocsApiController@spec` |

# Controllers

Controllers are the entry points for HTTP requests in Dorguzen. They are intentionally thin — their only job is to call a service, receive a payload, and hand it to a view.

---

## Base Class

All web controllers extend `DGZ_Controller` (`core/DGZ_Controller.php`):

```php
namespace Dorguzen\Controllers;

use Dorguzen\Core\DGZ_Controller;
use Dorguzen\Core\DGZ_View;
use Dorguzen\Services\NewsService;

class NewsController extends DGZ_Controller
{
    public function __construct(private NewsService $newsService)
    {
        parent::__construct();
    }

    public function getDefaultAction(): string
    {
        return 'news';
    }
}
```

`getDefaultAction()` tells the router which method to call when no method is specified in the URL. Always override it.

Controllers live in `src/controllers/` — one file, one class.

---

## A Controller Method

A controller method has exactly three responsibilities:

1. Call a service method to get a `$payload` array.
2. Get a view object.
3. Call `$view->show($payload)`.

```php
public function news(): void
{
    $view = DGZ_View::getView('news', $this, 'html');
    $this->setPageTitle('Latest News');
    $view->show($this->newsService->newsListingPayload());
}
```

For admin views:

```php
public function manageNews(): void
{
    $view = DGZ_View::getAdminView('manageNews', $this, 'html');
    $this->setLayoutDirectory('admin');
    $this->setLayoutView('adminLayout');
    $view->show($this->newsService->manageNewsPayload());
}
```

---

## Resolving Services

Constructor injection (preferred — the container autowires it):

```php
public function __construct(private NewsService $newsService)
{
    parent::__construct();
}
```

On-demand via the global helper:

```php
$payload = container(NewsService::class)->newsListingPayload();
```

---

## Handling POST Requests

Controllers handle form submissions directly. Validate, delegate to service, then redirect:

```php
public function createNews(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['news_title'] ?? '');

        if ($title === '') {
            $this->addErrors('<p>Title is required.</p>', 'Error');
            $this->redirect('admin/news', 'create');
            return;
        }

        $newId = $this->newsService->saveNews(['news_title' => $title]);

        if ($newId) {
            $this->addSuccess('News item created.', 'Done!');
        } else {
            $this->addErrors('Could not save the news item.', 'Error');
        }

        $this->redirect('admin/news', '');
        return;
    }

    // GET: show the form
    $view = DGZ_View::getAdminView('createNews', $this, 'html');
    $this->setLayoutDirectory('admin');
    $this->setLayoutView('adminLayout');
    $view->show($this->newsService->createNewsPayload(null));
}
```

Always `return` after a redirect to stop further execution.

---

## Flash Messages

Flash messages survive a redirect and are cleared once displayed.

| Method | Renders as |
|---|---|
| `$this->addSuccess('msg', 'Title')` | Green success banner |
| `$this->addErrors('msg', 'Title')` | Red error banner |
| `$this->addWarning('msg', 'Title')` | Yellow warning banner |
| `$this->addNotice('msg', 'Title')` | Info banner |

---

## Redirecting

```php
$this->redirect('auth/login');       // redirects to /auth/login
$this->redirect('auth', 'login');    // same — controller/method form
$this->redirect('news', '');         // redirects to /news (default action)
```

---

## Sticky Forms (postBack)

To re-populate a form after a failed submission:

```php
$this->postBack($_POST);
$this->redirect('auth', 'register');
```

Read it back in the view from `$_SESSION['postBack']`.

---

## Page Title and Layout

```php
$this->setPageTitle('Manage News');

// Switch layout directory and file
$this->setLayoutDirectory('admin');
$this->setLayoutView('adminLayout');

// No layout (for AJAX partials)
$this->setNoLayout();
```

---

## URL Helper

```php
$this->config->getFileRootPath()   // e.g. '/dorguzen/' — the base URL path
```

---

## Rules for Controllers

- Never query the database directly — all DB work goes through a service.
- Never call other controllers.
- Never build HTML strings or notification markup in a service — that belongs in the controller.
- Never pass raw model objects to views — always return a flat `$payload` array from the service.

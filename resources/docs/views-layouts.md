# Views and Layouts

Dorguzen separates page rendering into two layers:

- **Views** — PHP classes that render the content area of a page.
- **Layouts** — PHP files that supply the surrounding HTML shell (head, nav, footer).

---

## View Classes

Every view is a PHP class extending `DGZ_HtmlView`:

```php
namespace Dorguzen\Views;

use Dorguzen\Core\DGZ_HtmlView;

class news extends DGZ_HtmlView
{
    public function show(array $viewModel = []): void
    {
        $newsList = $viewModel['newsList'] ?? [];
        $rootPath = $this->controller->config->getFileRootPath();
        ?>
        <h1>Latest News</h1>
        <?php foreach ($newsList as $item): ?>
            <article>
                <h2><?= htmlspecialchars($item['news_title']) ?></h2>
                <p><?= htmlspecialchars($item['news_description']) ?></p>
            </article>
        <?php endforeach; ?>
        <?php
    }
}
```

### File Locations and Namespaces

| Type | Directory | Namespace | Factory method |
|---|---|---|---|
| Public views | `views/` | `Dorguzen\Views` | `DGZ_View::getView()` |
| Admin views | `views/admin/` | `Dorguzen\Views\Admin` | `DGZ_View::getAdminView()` |
| Partials / widgets | `views/` | `Dorguzen\Views` | `DGZ_View::getInsideView()` |
| Module views | `modules/ModuleName/Views/` | `Dorguzen\Modules\ModuleName\Views` | `DGZ_View::getModuleView()` |

The file name and class name must match exactly (case-sensitive). View names passed to `getView()` must be flat (no subdirectory slashes) — use distinct names like `docsShow` rather than `docs/show`.

---

## Getting a View From a Controller

```php
// Public view
$view = DGZ_View::getView('news', $this, 'html');

// Admin view
$view = DGZ_View::getAdminView('manageNews', $this, 'html');

// Module view
$view = DGZ_View::getModuleView('Blog', 'blogIndex', $this, 'html');

// Partial embedded inside another view's show() method
$partial = DGZ_View::getInsideView('sidebarPartial', $this->controller);
$partial->show($data);
```

---

## The Payload Pattern

The service returns an associative array. The controller passes it to `show()` unchanged. The view unpacks it at the top.

**Service:**
```php
public function newsListingPayload(): array
{
    return [
        'newsList' => $this->news->getAll('news_created DESC'),
        'total'    => $this->news->getCount(),
    ];
}
```

**Controller:**
```php
$view->show($this->newsService->newsListingPayload());
```

**View:**
```php
public function show(array $viewModel = []): void
{
    $newsList = $viewModel['newsList'] ?? [];
    $total    = $viewModel['total']    ?? 0;
    // ... HTML
}
```

Always use `?? $default` when unpacking to prevent undefined-index notices.

---

## Accessing the Controller From a View

`DGZ_HtmlView` stores a reference to the controller as `$this->controller`:

```php
$rootPath  = $this->controller->config->getFileRootPath();
$pageTitle = $this->controller->getPageTitle();
```

---

## Adding Page-Specific CSS and JS

Call these inside `show()` before any HTML output:

```php
public function show(array $viewModel = []): void
{
    $this->addStyle('news.css');
    $this->addScript('news-gallery.js');
    // HTML follows...
}
```

The layout injects these into `<head>` / end of `<body>` automatically.

---

## Adding Meta Tags

```php
$this->addMetadata([
    '<meta name="description" content="The latest news.">',
    '<title>News | My Site</title>',
]);
```

When the SEO module is active, meta tags are populated from the database automatically via `loadSeoData()` — you only need `addMetadata()` for manual overrides.

---

## Layouts

Layouts wrap views with the page shell. They live in `layouts/`:

```
layouts/
  seoMaster/
    seoMasterLayout.php     ← default public layout
    header.inc.php
    footer.inc.php
  admin/
    adminLayout.php
  docs/
    docsLayout.php
```

### Selecting a Layout in a Controller

```php
// Read from app config (uses APP_DEFAULT_LAYOUT_DIR and APP_DEFAULT_LAYOUT)
$this->setLayoutDirectory($this->config->getConfig()['layoutDirectory']);
$this->setLayoutView($this->config->getConfig()['defaultLayout']);

// Admin layout
$this->setLayoutDirectory('admin');
$this->setLayoutView('adminLayout');

// No layout (AJAX partials)
$this->setNoLayout();
```

You can also set defaults for the entire controller in `__construct()`:

```php
public function __construct()
{
    parent::__construct();
    $this->setLayoutDirectory('docs');
    $this->setLayoutView('docsLayout');
}
```

---

## Foreach Variable Collision — Important Gotcha

If a payload key and a foreach loop variable share the same name, the loop variable overwrites the payload key:

```php
// BUG: $items from payload gets clobbered on every iteration
foreach ($allNews as $items) { ... }
doSomething($items); // only the last row, not the full array

// FIX: use a distinct loop variable name
foreach ($allNews as $newsItem) { ... }
doSomething($items); // original array intact
```

Never use the same name for a payload key and a foreach variable.

---

## Anti-Patterns to Avoid

| Anti-pattern | Problem | Fix |
|---|---|---|
| `container(News::class)->getAll()` inside a view | Hidden DB call in the template | Return data in the service payload |
| Business logic in `show()` | Views must be pure rendering | Move logic to the service |
| Passing raw model objects to views | Couples view to model internals | Pass a flat array from the service |

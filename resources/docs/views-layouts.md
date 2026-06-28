# Views & Layouts

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

## Partial Views — Reusable View Fragments with getInsideView()

A partial view is a regular Dorguzen view class that is embedded inside another view rather than being rendered as a full page. Partials are how you reuse a chunk of HTML — a slide-in navigation menu, a pagination widget, a social-share bar, a comment form, a related-articles list — across many views without duplicating the markup.

Any class that extends `DGZ_HtmlView` (or `DGZ_AdminHtmlView`) and exposes a `show()` method can be used as a partial. There is nothing special about the class — what makes it a partial is simply how it is consumed: via `DGZ_View::getInsideView()` rather than via the normal controller/layout pipeline.

### Creating a partial

Create the view class exactly as you would any view. By convention, partial files are named with a "Partial" suffix so they are easy to recognise at a glance:

```php
// views/relatedArticlesPartial.php
namespace Dorguzen\Views;

class relatedArticlesPartial extends \Dorguzen\Core\DGZ_HtmlView
{
    public function show(array $viewModel = []): void
    {
        extract($viewModel); // $articles
        ?>
        <aside class="related-articles">
            <h4>Related Articles</h4>
            <ul>
                <?php foreach ($articles as $article): ?>
                    <li><a href="<?= $this->rootPath() ?>news/<?= $article['slug'] ?>">
                        <?= htmlspecialchars($article['title']) ?>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <?php
    }
}
```

### Embedding a partial inside another view

Call `DGZ_View::getInsideView()` from within any view's `show()` method, passing the partial's class name (without the namespace) and the current controller:

```php
// Inside another view's show() method:
$related = \Dorguzen\Core\DGZ_View::getInsideView('relatedArticlesPartial', $this->controller);
$related->show(['articles' => $articles]);
```

`getInsideView()` resolves the partial's class, injects the controller reference, and returns the partial instance. Calling `show()` on the result renders the partial's HTML inline at that point in the page. You can pass a view-model array to `show()` just as you would any view.

`getInsideView()` is a general mechanism — it works for any partial, anywhere a controller reference is available. Admin views, frontend views, module views: all can use it.

### Where NOT to use getInsideView() for the slide-in menu

The slide-in navigation menu (`sideSlideInMenuPartial`) is a special case. It is automatically included in all frontend views via the layout header (`layouts/seoMaster/header.inc.php`). Calling `getInsideView('sideSlideInMenuPartial')` from a frontend view on top of that would produce two `#side-menu` divs on the page. The JavaScript toggle functions target `document.getElementById('side-menu')`, which finds only the first one, leaving the second as unreachable dead HTML.

The correct rule:

**Frontend views (rendered through seoMasterLayout):**
Do NOT include `sideSlideInMenuPartial` manually. The layout handles it automatically.

**Admin views (rendered through adminLayout):**
DO include `sideSlideInMenuPartial` manually via `getInsideView()`. The admin layout does not auto-include it, so each admin view that needs the mobile slide-in menu must include it explicitly:

```php
$slideInMenu = \Dorguzen\Core\DGZ_View::getInsideView('sideSlideInMenuPartial', $this->controller);
$slideInMenu->show();
```

### How the slide-in menu works

The slide-in menu is a hidden off-canvas panel (`display:none`, `id="side-menu"`) that slides into view on mobile when the hamburger button (`navbar-toggler`) is tapped. The panel is controlled by two JavaScript functions defined in the layout header:

- `toggleSlideMenu(e)` — opens the panel if closed, closes it if open
- `closeSlideMenu(e)` — always closes the panel

The hamburger button in the navbar calls `toggleSlideMenu(event)` via `onclick`:

```html
<button onclick="toggleSlideMenu(event)" type="button" class="navbar-toggler">
    <span class="fa fa-bars"></span>
</button>
```

The close button inside the panel calls `closeSlideMenu(event)`.

On desktop the navbar collapses and Bootstrap's own collapse mechanism handles the nav links. The slide-in panel is hidden on desktop and is only relevant on mobile-sized viewports.

### Customising the menu for a specific view

Because the menu is now in the layout header and shared by all frontend views, customising it per-view is intentionally limited. Two practical options:

**Option 1 — Use JavaScript to show/hide specific links.**
Give conditional links a class (e.g. `"nav-link-members-only"`) and toggle their visibility with a short inline `<script>` block in the view that runs after the layout renders:

```php
<script>
    document.querySelectorAll('.nav-link-members-only').forEach(function (el) {
        el.style.display = '<?= isset($_SESSION['authenticated']) ? 'block' : 'none' ?>';
    });
</script>
```

**Option 2 — PHP conditionals in the layout header.**
For cases where a link should always/never appear based on module status or session role, add the conditional directly in `header.inc.php`. The file has access to `$_SESSION` and the `config()` helper, making it straightforward to gate links on module flags or user type:

```php
<?php if (config('app.modules.gallery') === 'on'): ?>
<a href="...">Gallery</a>
<?php endif; ?>
```

**Option 3 — A completely custom slide-in menu for one view.**
If a view's menu is genuinely unique (different links, different structure), override the auto-include by giving its slide-in div a different id (e.g. `id="side-menu-custom"`) and re-point the navbar toggler button for that view with a data attribute or custom JS. This is an advanced pattern and should be used sparingly.

For most applications the single shared menu with PHP conditionals (Option 2) covers all real-world cases cleanly.

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

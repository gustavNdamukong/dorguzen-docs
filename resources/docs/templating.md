# Templating

This section is about the available templating engine(s) available for your programming language, and how Dorguzen works with them.

---

## Templating Engines — What They Are, Why Dorguzen Does Not Need One, and How to Add One If You Want

### What templating engines solve

In traditional PHP frameworks, view files are loose .php files that mix HTML markup with PHP logic directly inline:

```php
<!-- Laravel Blade example -->
<h1>{{ $user->name }}</h1>
@if ($user->isAdmin())
    <a href="/admin">Dashboard</a>
@endif
```

This works, but the separation between logic and presentation depends entirely on developer discipline. Nothing stops a developer from querying the database, running business logic, or calling framework internals right inside a view file. Over time these files tend to accumulate logic they were never meant to contain.

Templating engines like Twig, Blade, and Latte were created to solve this:

- They restrict what code can run inside a template (no arbitrary PHP, only expressions and control structures the engine explicitly allows).
- They provide auto-escaping to prevent XSS — template variables are HTML-encoded by default.
- They offer template inheritance via block/extend systems, so a base layout template can define regions that child templates fill in.
- They separate the concerns of "prepare data" (controller) from "display data" (template), making views easier to hand to designers who do not write PHP.

---

### Why Dorguzen does not need a templating engine

Dorguzen's view system already provides the same guarantees through a different mechanism: class-based views.

In Dorguzen, every view is a PHP class with a show() method:

```php
class ProductsView extends DGZ_HtmlView
{
    public function show(): void
    {
        ?>
        <h1><?= htmlspecialchars($this->controller->pageTitle) ?></h1>
        <?php
    }
}
```

This enforces the separation that templating engines achieve through syntax restrictions, but through PHP's own class structure instead. Specifically:

- Logic belongs in the controller. The view class receives a reference to the controller and accesses prepared data from it — it does not run queries, call services, or make decisions. If it tried to, the MVC contract would be violated in an obvious, reviewable way.

- Template inheritance is handled by DGZ_Layout. The layout class (seoMasterLayout, etc.) defines the outer shell — head, header, footer, nav. Views fill in only the content region. This is structurally equivalent to a Twig `{% extends %}` / `{% block %}` relationship, but implemented with PHP classes and the setContentHtml() / display() pipeline.

- Auto-escaping is the developer's responsibility in Dorguzen views, just as it is in any raw PHP code. The standard PHP function htmlspecialchars() is used where needed. This is not a shortcoming unique to Dorguzen — it applies equally to any PHP code outside a templating engine.

- Designer-friendly syntax is a non-goal for Dorguzen. Dorguzen targets PHP developers. Views are PHP classes, and PHP developers are already comfortable reading and writing them.

The bottom line: templating engines exist to bring discipline to loose PHP view files. Dorguzen view files are not loose — they are typed, namespaced PHP classes inside a strict MVC pipeline. The discipline is already built into the structure.

---

### How to add Twig if you want it anyway

Dorguzen's rendering pipeline makes Twig integration straightforward if a developer wants it. The critical mechanism is in DGZ_Controller::display(), which captures all view output using PHP's output buffering:

```php
ob_start();
call_user_func_array([$this, $method], $inputParameters);
$contentHtml = trim(ob_get_clean());
```

The pipeline does not care how that HTML was produced. Whether a view's show() method uses inline PHP or calls `echo $twig->render('template.twig', $data)`, the result is identical — a string of HTML — and the rest of the pipeline (layout, SEO, flash messages, asset injection) continues unchanged.

Steps to add Twig:

1. Install the package:

```bash
composer require twig/twig
```

2. Bind a Twig\Environment singleton in bootstrap/app.php (or bootstrap/custom_helpers.php):

```php
$loader = new \Twig\Loader\FilesystemLoader(base_path('templates'));
$twig   = new \Twig\Environment($loader, ['autoescape' => 'html']);
$container->singleton(\Twig\Environment::class, fn () => $twig);
```

3. Create a TwigView base class:

```php
namespace Dorguzen\Core;

class TwigView extends DGZ_HtmlView
{
    protected \Twig\Environment $twig;

    public function __construct(DGZ_Controller $controller)
    {
        parent::__construct($controller);
        $this->twig = container(\Twig\Environment::class);
    }

    protected function render(string $template, array $data = []): void
    {
        echo $this->twig->render($template, $data);
    }
}
```

4. Create your Twig templates in the templates/ directory:

```
templates/
└── products/
    └── index.twig
```

5. In any view's show() method, replace inline PHP with a render() call:

```php
class ProductsView extends TwigView
{
    public function show(): void
    {
        $this->render('products/index.twig', [
            'products' => $this->controller->getProducts(),
            'title'    => $this->controller->getPageTitle(),
        ]);
    }
}
```

That is the entire integration for views. The router, controller, layout, SEO pipeline, flash messages, and asset injection all continue to work exactly as before.

To Twigify layouts as well (so you can use Twig's `{% extends %}` across the full page shell), create a TwigLayout extending DGZ_Layout that renders a .twig layout template in its display() method, passing `$this->content`, `$this->notices`, `$this->metadata` etc. as Twig variables. Then point configs/app.php at that layout class instead of the default seoMasterLayout.

Note: Twig's native template inheritance (`{% extends %}` / `{% block %}`) only works within Twig's own template graph. If you Twigify views but keep the PHP layout class, you cannot use `{% extends %}` to inherit from the outer shell — you would use Twig's `{% include %}` and macros within views, while the PHP layout class remains the outer shell. Both hybrid and full-Twig approaches are valid and require no framework modifications.

---

Return to [Introduction]({{base}}docs/introduction) or use the sidebar to navigate.

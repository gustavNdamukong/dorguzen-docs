# Controllers

> Note: Dorguzen's controller material is woven through the routing chapter of the
> canonical docs. The auto-discovery mechanics live in `routing.md`; the passages
> below are the controller-specific concepts (how a controller maps to a URL, the
> default action, module sub-controllers, the API trait, and the redirect helpers).
> Where a passage covers both routing and controllers it is reproduced here in full
> and flagged as overlapping with `routing.md`.

MVC design pattern conventions:

- never write SQL within a controller or a view.
- All SQL should be in models.
- DB table names should be all small letters, & be in plural, while their models are the singular of that — starting in uppercase eg table: `'users'`, model: `'User'`.

---

## Segment-to-Controller Mapping

> Overlaps with `routing.md` (Automatic Route Discovery).

The first meaningful segment after the app name is the controller name.

```
  URI       → Controller looked up
  /contact  → ContactController   (found in src/controllers/)
  /about    → AboutController     (found in src/controllers/)
  /seo      → SeoController       (found in modules/seo/controllers/ — module route)
```

DGZ checks controller locations in this order:

```
  1. src/controllers/            — regular application controllers
  2. modules/{name}/controllers/ — module entry controllers (if the segment matches an active module)
```

---

## Default Action

> Overlaps with `routing.md` (Automatic Route Discovery).

If the URI contains only the controller segment (e.g. `/contact`), DGZ calls the controller's `getDefaultAction()` method automatically. All controllers must implement `getDefaultAction()`.

---

## Methods and Parameters

> Overlaps with `routing.md` (Automatic Route Discovery).

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

---

## Module Sub-Controllers and Automatic Route Discovery

> Overlaps with `routing.md` (Automatic Route Discovery), but the `getControllers()`
> / `DGZ_ModuleControllerInterface` contract is a controller concept and is reproduced
> in full here.

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

**Option A — use `DGZ_ModuleControllerTrait` (recommended, least boilerplate):**

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

**Option B — implement the interface directly (more explicit):**

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

Note: when using defined routes for module sub-controllers, `DGZ_ModuleControllerInterface` is NOT required. The controller is specified explicitly in the route definition, so there is nothing to discover dynamically. The interface is only needed for automatic route discovery.

---

## Enforcing JWT Authentication on API Routes

> Overlaps with `routing.md` (Defined Routes / API Routes). The `DGZ_APITrait` is added
> to an API controller, so it is reproduced here.

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

---

## Full controller example flow

```php
public function register()
{
    $input = $_POST;

    $validator = $this->validator($input, [
        'username' => 'required|min:5|callback:\MyApp\User::usernameAvailable',
        'password' => 'required|min:8',
        'email' => 'required|email',
    ], [
        'username.callback' => 'That username is already taken.'
    ]);

    if ($validator->fails()) {
        return $this->render('auth/register', ['errors' => $validator->errors(), 'old' => $input]);
    }

    // create user...
}
```

---

## Form submission and Controller validation example

### View with form

```php
$form = new DGZ_Form();

$form::open(
    'chooseCategory', 
    $this->controller->config->getFileRootPath().'data/test-process-form', 
    'post'); ?>

<div class="col-md-12">
    <?php
    $form::input(
        'name', 
        'text', 
        [
            'name' => 'name', 
            'placeholder' => 'your name',
            'class' => 'col-md-12 form-control'
        ]);
    ?>
</div>

<div class="col-md-12">
    <?php 
    $form::select(
        'category',
        [
            'Phones' => [
                'iphone' => 'Apple iPhone',
                'samsung' => 'Samsung Galaxy',
            ],
            'Laptops' => [
                'macbook' => 'MacBook Pro',
                'lenovo' => 'Lenovo Thinkpad',
            ],
            'other' => 'Miscellaneous'
        ],
        ['iphone'], // pre-selected
        true,
        [
            'name' => 'category', 
            'class' => 'col-md-12 form-select', 
        ]
    ); ?>
</div>

<div class="form-group col-md-12">
    <?php
    $form::submit('submit', 'Save data', ['class' => 'btn btn-primary btn-sm ml-3']);
    ?>
</div>
<?php

$form::close(); 
```

### Controller validation

As we can see from the `Form::open()` section, this line:
(`$this->controller->config->getFileRootPath().'data/test-process-form'`) tells us that the form submission handler is the `src\controllers\DataController`, in the method named `testProcessForm()`.

The form has only two fields

- name: where the user is expected to enter their name
- category: where the user is expected to make a selection from a list of categories

In the form handler method, which is `DataController->testProcessForm()`, here is how the form submission is processed:

```php
public function testProcessForm()
{
    $input = request()->post(); 

    $rules = [
        'name' => 'required|max:8',
        'category' => 'min:2' // user must choose at least two items from the select field
    ];

    $customMessages = [
        'name.required' => 'name is required my dawg!',
        'name.max:8' => '8 characters max, for name please',
        'category.min:2' => 'common man, pick at least two ok'
    ];

    $validator = $this->validator($input, $rules, $customMessages);

    if ($validator->fails()) {
        $errors = $validator->errors();

        $errorMsg = "";
        
        foreach ($errors as $key => $error)
        {
            $errorMsg .= $error[0].'<br>';
        }

        // send back to view: 
        $this->addErrors($errorMsg, 'Validation failed');

        // redirect back to another controller & method
        $this->redirect('data', 'privacy');

        // or render a view and pass in the error data
    }

  $this->addSuccess('Submission was successful', 'Yay');

    $view = Dorguzen\Core\DGZ_View::getView('privacy', $this, 'html');
    $this->setPageTitle('Data privacy');
    $view->show();
}
```

---

## Base Class and Constructor Injection

All web controllers extend `DGZ_Controller` (`core/DGZ_Controller.php`) and live in `src/controllers/` — one file, one class. The cleanest way to obtain a service is constructor injection; the container autowires the dependency for you:

```php
namespace Dorguzen\Controllers;

use Dorguzen\Core\DGZ_Controller;
use Dorguzen\Services\NewsService;

class NewsController extends DGZ_Controller
{
    public function __construct(private NewsService $newsService)
    {
        parent::__construct();
    }

    public function getDefaultAction()
    {
        return 'news';
    }
}
```

A controller that needs no dependencies still calls `parent::__construct()` from its own constructor (or omits the constructor entirely).

---

## On-demand Service Resolution

When you do not want a constructor dependency, resolve a service on demand through the global `container()` helper:

```php
$payload = container(NewsService::class)->newsListingPayload();
```

---

## Fetching a View

A controller method typically fetches a view, optionally sets a title/layout, then calls `$view->show($payload)`. There are two factory methods:

```php
// regular (front-end) view
$view = DGZ_View::getView('news', $this, 'html');
$view->show($payload);

// admin view (resolved from the admin views location)
$view = DGZ_View::getAdminView('manageNews', $this, 'html');
$view->show($payload);
```

---

## Flash Messages

Flash messages are stored in the session, survive a redirect, and are rendered between the menu and the page content by the default layouts. Each takes a message and an optional title.

| Method | Purpose |
|---|---|
| `$this->addSuccess('msg', 'Title')` | success message |
| `$this->addErrors('msg', 'Title')` | error message |
| `$this->addWarning('msg', 'Title')` | warning message |
| `$this->addNotice('msg', 'Title')` | informational notice |

---

## Sticky Forms (postBack)

To re-populate a form after a failed submission, hand the submitted input to `postBack()` before redirecting back to the form:

```php
$this->postBack($_POST);
$this->redirect('admin', 'editUser');
```

The view reads the retained values back from the session to refill the fields.

---

## Page Title and Layout

```php
$this->setPageTitle('Manage News');

// switch the layout directory and the layout file
$this->setLayoutDirectory('admin');
$this->setLayoutView('adminLayout');

// render with no layout (e.g. for AJAX partials)
$this->setNoLayout();
```

---

## Redirecting the Visitor — redirect() and redirectTo()

A "redirect" is when your application, instead of drawing a page, tells the visitor's browser "what you asked for actually lives at a different address — go there instead." The browser then makes a fresh request to that other address. This is an everyday need: after a successful login you send the user to their dashboard, after a form is saved you send them back to a list page, and so on. Dorguzen gives every controller TWO methods for this (both inherited from `DGZ_Controller`): `redirect()` and `redirectTo()`. They look alike but solve different problems, so it pays to know both.

### A word first on "HTTP status codes"

Every redirect carries a small number — the HTTP status code — that tells the browser, and any search engine, WHY the redirect is happening. Two of them matter here:

- 302 "Found" (a *temporary* redirect): "use this other address for now, but the original address is still the real one — keep it." This is the safe, normal default for app navigation such as post-login or post-save redirects.
- 301 "Moved Permanently": "the original address is gone for good; the real address is now this new one." A search engine reacts to a 301 by moving the old page's ranking onto the new address and dropping the old one from its index. Use a 301 only when an address should never be used again — for example when two URLs show the exact same page and you want to collapse them into one (see the canonical-URL example at the end of this topic).

### redirect() — jump to another controller/action inside THIS app

This is the one you will use most of the time. You give it a controller name (and optionally a method name), and it sends the visitor to that route inside your own application. It builds the full web address for you from your app's base URL (via `Config::getFileRootPath()`), so you never hard-code something like "http://localhost/myapp/..." — the same call works on your local machine and on the live server.

```php
// inside any controller method:
$this->redirect('auth', 'login');                       // -> /auth/login
$this->redirect('auth/login');                          // -> /auth/login  (slash form, same thing)
$this->redirect('news');                                // -> /news        (NewsController's default action)
$this->redirect('');                                    // -> /            (the home page)
$this->redirect('shop', 'manage', ['userId' => 42]);    // -> /shop/manage?userId=42
```

`redirect()` ALWAYS sends a 302 (temporary), and it ALWAYS calls `exit()` for you afterwards. That second point matters: a redirect is just an HTTP header, so without an `exit()` the rest of your method would keep running. `redirect()` takes care of that for you. (If you happen to call it more than once in one request, the last call wins — Dorguzen clears the earlier Location header first.)

### redirectTo() — send the browser to ANY URL, with a status code you choose

Sometimes "another controller/action in this app" is not what you need. You might want to send the visitor to a completely different website, or — far more commonly — you need to control the status code (a 301 instead of the default 302). That is exactly what `redirectTo()` adds:

```php
public function redirectTo(string $url, int $statusCode = 302): void
```

You hand it a finished URL — absolute like "https://example.com/" or root-relative like "/path" — and, optionally, the status code. Like `redirect()`, it calls `exit()` for you and obeys the same last-call-wins rule.

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

Rule of thumb: use `redirect()` for ordinary in-app navigation (after login, after saving a form). Reach for `redirectTo()` only when you need a specific status code (almost always a 301) or a target outside your own routes.

You may wonder why we did not simply add a status-code option to `redirect()` itself. `redirect()`'s whole job is "go to a named route in this app", and plenty of existing code relies on that exact, simple behaviour. Bolting URL-and-status handling onto it would blur that contract and risk those callers. Keeping `redirectTo()` as a separate, small primitive leaves `redirect()` untouched while giving you the extra power for the rare cases that need it.

### A real example — the homepage "canonical URL" fix

A "canonical URL" is the single, official address for a page. A classic SEO (Search Engine Optimisation) problem is the SAME page answering at more than one address — for instance a home page reachable at "/", "/home", "/index" AND "/index.php". A search engine then sees four addresses showing identical content ("duplicate content") and splits that page's ranking across them instead of crediting one strong page.

Dorguzen handles this for you. When an incoming request resolves to one of those homepage aliases (whether through an explicit `/home` route or through Dorguzen's route auto-discovery), the framework issues a 301 to the real root using exactly the call shown above:

```php
$this->redirectTo($this->config->getHomePage(), 301);
```

`getHomePage()` returns the correct absolute base URL for the current environment (local vs live), so this one line does the right thing everywhere. The outcome: any visitor or search-engine crawler that lands on "/home", "/index" or "/index.php" is permanently sent to "/", and the home page's ranking is consolidated onto a single canonical address.

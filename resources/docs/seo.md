# SEO Module

## Contents

- What It Is
- How It Works — the automatic pipeline
- Step 1: Enable the module
- Step 2: Enter global SEO data
- Step 3: Enter per-page SEO data
    - The page name convention
- What gets injected and where
    - Global SEO (site-wide meta tags)
    - Per-page head meta tags
    - Per-page body SEO data
    - Fallback title
- Manual SEO override (bypassing the DB pipeline)
    - Overriding per-page meta tags in a controller or view
    - Setting global SEO data manually
- Wiring up SEO in a layout file
- Creating a custom layout
    - The layout skeleton
    - Parts of a layout
    - Multiple layouts / themes

---

## What It Is

The SEO module is a built-in Dorguzen module (`modules/seo/`) that gives you a database-driven way to manage the SEO meta tags of every page on your site, as well as site-wide Open Graph and social metadata, without touching a single view file or layout after the initial setup.

It ships with two data stores:

- **`seo`** — Per-page SEO records. Each row targets one view by name and holds the page title, meta description, keywords, Open Graph tags, Twitter Card tags, canonical href, noindex flag, and body content fields (h1, h2, page copy) — all in up to three languages (en, fr, es).

- **`seo_global`** — One row per application. Holds the site-wide meta tags that should appear on every page: OG locale, OG site name, article publisher/author, geo coordinates, Facebook app ID, Twitter card type/handle, and hreflang alternate links.

Both are managed through the admin UI at `/seo` (visible only to admin roles).

---

## How It Works — the automatic pipeline

You do not need to call anything from your controllers. When a view is rendered, `DGZ_Controller::display()` calls:

```php
$this->loadSeoData($this->viewName);
$layout->setGlobalSeoData($this->globalSeoData);
$layout->setMetadata($this->getMetadata());
```

`loadSeoData()` does the following automatically:

1. Checks that the seo module is enabled in config (`'seo' => 'on'`).
2. Instantiates `SeoController` and fetches the global SEO row.
3. Builds an array of HTML `<meta>` tag strings from the global row and stores them on the controller via `setGlobalSeoData()`.
4. Looks up a per-page SEO row whose `seo_page_name` matches the current view's class name (lowercased). If found, builds head and body SEO data arrays and stores them.
5. Passes both arrays to the layout via `setGlobalSeoData()` and `setMetadata()` — making them available to `getGlobalSeoData()` and `getMetadata()` inside the layout's `display()` method.

If the seo module is off, or no matching row is found, nothing is output and the layout falls back to a plain `<title>` tag (see Fallback title below).

The only requirement on your part is two lines in the layout file's `<head>` section (see "Wiring up SEO in a layout file" below).

---

## Step 1: Enable the module

In `configs/app.php` (and optionally `.env`), ensure the seo flag is `'on'`:

```php
// configs/app.php
'modules' => [
    'seo' => env('MODULES_SEO_STATUS', 'on'),
    ...
],
```

```ini
# .env
MODULES_SEO_STATUS=on
```

The module is on by default in a fresh Dorguzen installation.

---

## Step 2: Enter global SEO data

Log in as an admin user and navigate to:

```
/seo  →  SEO Manager  →  "Global SEO" tab (or the "Add Global SEO" link)
```

Fill in the site-wide fields:

| Field | Description |
|---|---|
| OG Locale | e.g. `en_GB` — the primary language/region of the site |
| OG Site Name | Your website's display name shown in social cards |
| Article Publisher | Full URL to your Facebook business page |
| Article Author | Full URL to your Facebook personal page |
| Geo Placename | e.g. London — the city/region the site represents |
| Geo Region | e.g. GB — ISO country/region code |
| Geo Position | e.g. `51.5074;-0.1278` — lat;lon coordinates |
| Facebook App ID | Your FB app ID for the `fb:app_id` meta tag |
| Twitter Card Type | e.g. `summary`, `summary_large_image` |
| Twitter Site Handle | Your Twitter/X handle e.g. `@mysite` |
| HREFlang Alternate 1 | e.g. `fr-ca` — alternate language variant URL |
| HREFlang Alternate 2 | e.g. `en-ca` |

You only need to fill in the fields that apply to your site. Any field left blank is silently skipped — no empty `<meta>` tags are output.

These tags will appear in the `<head>` of every page on your site automatically, in the same request cycle as the page render — no cache to clear.

---

## Step 3: Enter per-page SEO data

Navigate to:

```
/seo  →  "Add Page SEO" link
```

Fill in the fields for the page you want to optimise:

| Field | Description |
|---|---|
| Page Name | The view name (see convention below) |
| Title (en/fr/es) | Meta title — max 60 characters |
| Description (en) | Meta description — max 150 characters |
| Keywords | Comma-separated keywords |
| OG Title | Open Graph title for social sharing |
| OG Description | Open Graph description |
| OG Image | Fully-qualified image URL e.g. `https://mysite.com/assets/social/og.png` |
| OG Image Secure URL | HTTPS version of the OG image URL |
| OG Image Width/Height | Dimensions in pixels |
| OG Video | HTTPS URL of a video for rich cards |
| OG Type | e.g. `website`, `article`, `product` |
| OG URL | Canonical URL of the page for OG |
| Twitter Title | Title shown in Twitter/X cards |
| Twitter Description | Description shown in Twitter/X cards |
| Twitter Image | Image URL for Twitter cards |
| Canonical Href | Canonical link rel href (tick to enable) |
| No Index | Tick to add `<meta name="robots" content="noindex">` |
| SEO Dynamic | Tick if this page's title/description come from user-submitted content — see Dynamic SEO below |
| H1 Text | The recommended h1 heading for this page |
| H2 Text | The recommended h2 sub-heading |
| Page Content | Keyword-rich body copy for this page |

### The page name convention

The page name you enter MUST exactly match the lowercased class name of the view file for that page. Dorguzen matches by calling `strtolower($viewName)` and looking up `seo_page_name` in the database.

Examples:

| View file | Page name to enter |
|---|---|
| `views/home.php`  (class home) | home |
| `views/about.php` (class about) | about |
| `views/contact.php` (class contact) | contact |
| `modules/blog/views/post.php` | post |

If the class is named differently from the file, use the class name, not the filename. The match is always against the class name, lowercased.

---

## What gets injected and where

### Global SEO (site-wide meta tags)

Injected into the layout's `<head>` via `getGlobalSeoData()`. These appear on every page and cover OG site identity, geo tags, Twitter card base settings, and hreflang alternates.

The tags output by `getGlobalSeoData()` include (where data exists):

```html
<meta property="og:locale:alternate" content="..." />
<meta property="og:site_name" content="..." />
<meta property="article:publisher" content="..." />
<meta property="article:author" content="..." />
<meta name="geo.placename" content="..." />
<meta name="geo.region" content="..." />
<meta name="geo.position" content="..." />
<meta property="fb:app_id" content="..." />
<meta name="twitter:card" content="..." />
<meta name="twitter:site" content="..." />
<link rel="alternate" href="..." hreflang="..." />
```

### Per-page head meta tags

Injected via `getMetadata()`. These are page-specific and override the fallback `<title>` tag. For the current page's language, the following are output:

```html
<meta name="description" content="...">
<meta name="keywords" content="...">
<meta property="og:title" content="..." />
<meta property="og:description" content="..." />
<meta property="og:image" content="..." />
<meta property="og:image:secure_url" content="..." />
<meta property="og:image:width" content="..." />
<meta property="og:image:height" content="..." />
<meta property="og:video" content="..." />
<meta property="og:type" content="..." />
<meta property="og:url" content="..." />
<meta name="twitter:title" content="..." />
<meta name="twitter:description" content="..." />
<meta name="twitter:image" content="..." />
<link rel="canonical" href="..." />
<meta name="robots" content="noindex">
<title>...</title>
```

### Per-page body SEO data

Three fields from the per-page SEO row are also made available for use inside the view's body content: h1 text, h2 text, and page copy. These are stored on the layout object and can be accessed in views or layout partials via the controller's `getBodySeoData()` method:

```php
$bodySeo = $this->controller->getBodySeoData();
// keys: 'seo_h1_text', 'seo_h2_text', 'seo_page_content'
```

These fields are optional. If they are empty in the database row, they return empty strings. Typical usage is to output them as the primary heading and introductory copy on a page where you want to manage that content from the admin panel rather than hard-coding it in the view.

Example in a view:

```php
<?php $bodySeo = $this->controller->getBodySeoData(); ?>
<h1><?= htmlspecialchars($bodySeo['seo_h1_text']) ?></h1>
<h2><?= htmlspecialchars($bodySeo['seo_h2_text']) ?></h2>
<p><?= htmlspecialchars($bodySeo['seo_page_content']) ?></p>
```

### Fallback title

If no per-page SEO row exists for the current view (or the SEO module is off), `getMetadata()` returns an empty string. The layout handles this with the following pattern (already in `seoMasterLayout.php` line 22):

```php
<?=($this->getMetadata() != null) ? $this->getMetadata() : "<title>".self::$appName."-".$this->pageTitle."</title>" ?>
```

This means every page always has a `<title>` tag — either the SEO-managed one or the auto-generated "appName - pageTitle" fallback. No extra work needed.

---

## Manual SEO override (bypassing the DB pipeline)

The DB-driven pipeline is optional. Any page can set its own meta tags directly in code, which is handy when a page's SEO is too dynamic for a static admin record, or when you simply prefer to keep it in the controller.

### Overriding per-page meta tags in a controller or view

Call `addMetadata()` with an array of fully-formed meta tag strings — either in a controller method before `display()`, or at the top of a view's `show()` method:

```php
public function about(): void
{
    $this->setPageTitle('About Us');

    $this->addMetadata([
        '<title>About Us | My Company</title>',
        '<meta name="description" content="Learn about our team and mission.">',
        '<meta name="keywords" content="about, team, mission">',
        '<meta property="og:title" content="About Us | My Company">',
        '<meta property="og:description" content="Learn about our team.">',
        '<meta property="og:image" content="https://yourapp.com/assets/images/about-og.jpg">',
    ]);

    $this->display('about');
}
```

Manual metadata takes precedence over the DB-driven pipeline. `loadSeoData()` begins with `if ($this->metadata == null)`, so once `addMetadata()` has set `$this->metadata`, the automatic per-page lookup is skipped entirely and your tags are output verbatim by `getMetadata()`.

> Note: `addMetadata()` is available both on the controller (`DGZ_Controller`) and inside views (`DGZ_HtmlView`); the view version runs each tag through `htmlentities()` before passing it to the controller.

### Setting global SEO data manually

The site-wide global tags can likewise be supplied in code instead of (or in addition to) the `seo_global` admin record, via `setGlobalSeoData()`:

```php
$this->setGlobalSeoData([
    '<meta property="og:site_name" content="My Company">',
    '<meta name="twitter:site" content="@mycompany">',
]);
```

Whatever you set is returned by `getGlobalSeoData()` and output by the layout's first SEO line.

---

## Wiring up SEO in a layout file

If you create a custom layout (see next section), you must include exactly two lines inside the `<head>` tag to connect it to the SEO pipeline:

```php
<!-- inside <head> -->
<?= $this->getGlobalSeoData() ?? '' ?>
<?= ($this->getMetadata() != null) ? $this->getMetadata() : "<title>" . self::$appName . "-" . $this->pageTitle . "</title>" ?>
```

Line 1 outputs the site-wide global meta tags (OG identity, geo, Twitter base).
Line 2 outputs the page-specific meta tags including `<title>`, or falls back to an auto-generated title if no SEO row exists for this page.

Place them immediately after the required `<meta charset>` and `<meta viewport>` tags, before any `<link>` or `<script>` tags, to ensure search engines read them in the correct order. That is all the setup required — the framework handles the rest automatically on every request.

---

## Creating a custom layout

A layout in Dorguzen is the outer HTML shell that wraps every page — the `<html>`, `<head>`, header, footer, and the slot where view content is injected. You can have as many layouts as your application needs: a public site layout, an admin panel layout, a minimal API response layout, a campaign landing page layout, and so on. Dorguzen ships with:

```
layouts/seoMaster/       The recommended public-facing layout (default)
layouts/admin/           The admin panel layout
layouts/dorguzApp/       An alternative full-featured public layout
```

To create a new layout, copy the `seoMaster` directory and rename it:

```
layouts/
└── myTheme/
    ├── myThemeLayout.php          ← the main layout class (required)
    ├── header.inc.php             ← navigation / top bar
    ├── footer.inc.php             ← footer links / copyright
    ├── html_dependencies_top.inc.php    ← CSS links, early scripts
    └── html_dependencies_bottom.inc.php ← JS bundles loaded at end of body
```

### The layout skeleton

Your main layout class must extend `DGZ_Layout` and implement `display()`. Use `seoMasterLayout.php` as your starting point. The minimal required structure:

```php
<?php
namespace Dorguzen\layouts\myTheme;

class myThemeLayout extends \Dorguzen\Core\DGZ_Layout
{
    public function display()
    { ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">

            <!-- SEO — these two lines are required for the SEO module to work -->
            <?= $this->getGlobalSeoData() ?? '' ?>
            <?= ($this->getMetadata() != null) ? $this->getMetadata() : "<title>" . self::$appName . "-" . $this->pageTitle . "</title>" ?>

            <?= $this->getCssHtml() ?>
            <?php include('html_dependencies_top.inc.php'); ?>
        </head>
        <body>

            <?php include('header.inc.php'); ?>

            <!-- Flash messages -->
            <?php if (!empty($this->errors)): ?>
                <div class="alert danger"><?= $this->errors ?></div>
            <?php endif; ?>
            <?php if (!empty($this->successes)): ?>
                <div class="alert success"><?= $this->successes ?></div>
            <?php endif; ?>

            <!-- Page content injected here -->
            <?= $this->content ?>

            <?php include('footer.inc.php'); ?>

            <?php include('html_dependencies_bottom.inc.php'); ?>
            <?= $this->getJavascriptHtml() ?>

        </body>
        </html>
    <?php
    }
}
```

Important lines explained:

- **`$this->getCssHtml()`** — Outputs any per-view `<link>` tags added via `$this->addStyle('file.css')` in a view's `show()`
- **`$this->getJavascriptHtml()`** — Outputs any per-view `<script>` tags added via `$this->addScript('file.js')`
- **`$this->content`** — The rendered HTML of the current view
- **`$this->errors / successes`** — Flash message bags — include all five: exceptions, warnings, errors, notices, successes
- **`include('header.inc.php')`** — Pull in the navigation partial
- **`include('footer.inc.php')`** — Pull in the footer partial

### Parts of a layout

Splitting the layout into partials (header, footer, dependencies) keeps each file focused and easy to edit. The split is a convention — you can merge them or add more partials as your design grows. Common patterns:

- **`header.inc.php`** — Site logo, main navigation, mobile menu toggle, any top banner. Read the current route with `$this->config->getCurrentRoute()` to highlight the active nav item.

- **`footer.inc.php`** — Footer links, social icons, copyright notice, cookie consent popup.

- **`html_dependencies_top.inc.php`** — CSS framework links (Bootstrap, Tailwind etc.), icon libraries, Google Fonts, and any scripts that must load in `<head>`.

- **`html_dependencies_bottom.inc.php`** — JavaScript bundles (jQuery, Bootstrap JS, Owl Carousel, app.js). Placing these at the bottom improves perceived page load speed.

### Multiple layouts / themes

To use a specific layout for a controller action, call `setLayout()` before rendering:

```php
// In a controller method
$this->setLayout('myTheme', 'myThemeLayout');
$this->renderView('myView');
```

To make a layout the default for the whole application, set it in `configs/app.php`:

```php
'layoutDirectory' => 'myTheme',
'defaultLayout'   => 'myThemeLayout',
```

The admin panel uses its own layout (`layouts/admin/adminLayout.php`) automatically for all views in `views/admin/` that extend `DGZ_AdminHtmlView`. You do not need to call `setLayout()` for admin views — the base class handles it.

Each layout is fully independent. A marketing landing page layout can be stripped down to a single column with no navigation. A dashboard layout can include a sidebar and data widgets. A campaign page layout can load a completely different CSS framework. None of these choices affect any other layout.

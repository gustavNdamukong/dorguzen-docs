# SEO

Dorguzen has a built-in DB-driven SEO system that automatically injects per-page meta tags, Open Graph, Twitter Card, and geo tags into every page — without any per-controller code. It can also be overridden manually per page.

---

## Enabling SEO

The SEO module is toggled in `.env`:

```ini
MODULES_SEO_STATUS=on
```

This maps to `configs/app.php`:

```php
'modules' => [
    'seo' => env('MODULES_SEO_STATUS', 'on'),
    // ...
],
```

When `on`, the full DB-driven SEO pipeline fires automatically on every page load via `DGZ_Controller::loadSeoData()`.

---

## How It Works

Before each view renders, the framework:

1. Loads **global SEO data** — site-wide Open Graph, social profile links, geo tags, hreflang alternates, Facebook App ID, Twitter site handle.
2. Loads **page SEO data** by matching the current view name — title, description, keywords, OG image/video, Twitter Card, canonical URL, noindex flag.
3. Passes both to the layout, which outputs them into `<head>`.

The layout renders them here (from `layouts/seoMaster/seoMasterLayout.php`):

```html
<head>
    <?= $this->getGlobalSeoData() ?>
    <?= $this->getMetadata() ?? '<title>' . self::$appName . ' - ' . $this->pageTitle . '</title>' ?>
</head>
```

If no page-specific SEO record exists for a view, the layout falls back to `appName - pageTitle`.

---

## Meta Tags Generated

**Global tags (site-wide):**
- `og:locale:alternate`
- `og:site_name`
- `article:publisher`, `article:author`
- `geo.placename`, `geo.region`, `geo.position`
- `fb:app_id`
- `twitter:card`, `twitter:site`
- `link rel="alternate"` (hreflang)

**Per-page tags:**
- `<title>`
- `meta name="description"`
- `meta name="keywords"`
- `og:title`, `og:description`, `og:image`, `og:image:secure_url`, `og:image:width`, `og:image:height`, `og:video`, `og:type`, `og:url`
- `twitter:title`, `twitter:description`, `twitter:image`
- `link rel="canonical"`
- `meta name="robots" content="noindex"` (when flagged)

---

## Manual SEO Override

If you need custom meta tags for a specific page, set them in the controller before calling `display()`:

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

    // ...
    $this->display('about');
}
```

Manual metadata takes precedence over the DB-driven pipeline. When `$this->metadata` is set, the automatic lookup is skipped entirely.

### Setting global SEO manually

```php
$this->setGlobalSeoData([
    '<meta property="og:site_name" content="My Company">',
    '<meta name="twitter:site" content="@mycompany">',
]);
```

---

## Body SEO Data

Some SEO records also store H1, H2, and intro text intended for on-page use. Access these in your view or controller:

```php
$bodySeo = $this->getBodySeoData();

// Keys available:
$h1          = $bodySeo['seo_h1_text']    ?? '';
$h2          = $bodySeo['seo_h2_text']    ?? '';
$pageContent = $bodySeo['seo_page_content'] ?? '';
```

---

## Page Title Fallback

When no SEO record exists for the current page, the layout outputs:

```html
<title>AppName - PageTitle</title>
```

`AppName` comes from `configs/app.php` (`appName`). `PageTitle` is set in the controller:

```php
$this->setPageTitle('Contact');
```

---

## File Locations

| What | Where |
|---|---|
| SEO module toggle | `configs/app.php` → `modules.seo` |
| Auto SEO loader | `core/DGZ_Controller.php` → `loadSeoData()` |
| Layout meta output | `layouts/seoMaster/seoMasterLayout.php` |
| `setMetadata()` / `getMetadata()` | `core/DGZ_Layout.php` |
| SEO DB controller | `src/controllers/SeoController.php` |

# Social Sharing

## Social Media Share Buttons (DGZ_Share)

DGZ_Share renders a row of platform share-icon buttons for any URL. It is a zero-dependency, pure HTML/CSS/JS widget — no SDK, no third-party script tag, no cookies. Styles and the copy-to-clipboard script are injected into the page once via a PHP static flag, so calling `shareButtons()` multiple times on the same page (e.g. a listing page with many ads) is safe and produces only one `<style>`/`<script>` block.

---

## Supported platforms

| Key        | Share mechanism                                              |
|------------|--------------------------------------------------------------|
| facebook   | facebook.com/sharer/sharer.php?u={url}                       |
| whatsapp   | wa.me/?text={title}+{url}                                    |
| twitter    | twitter.com/intent/tweet?url={url}&text={title}              |
| email      | mailto:?subject={title}&body={url}  (opens native mail app)  |
| copy       | Copies URL to clipboard via navigator.clipboard (JS)         |

Note on TikTok: TikTok has no web-based share URL. It is a video creation platform — there is no "share a link to TikTok" endpoint. Use the 'copy' button instead; users tap it, then paste the URL into any TikTok caption, bio, or DM. This covers the TikTok use-case without fabricating a fake URL.

---

## Quick start

In any view, controller, or layout, call the global helper:

```php
<?= shareButtons($url, $title) ?>
```

That's all. The HTML, CSS, and JS are returned as a string — echo it wherever you want the buttons to appear on the page.

---

## The shareButtons() helper

Signature:

```php
shareButtons(string $url, string $title = '', array $options = []): string
```

Parameters:

- `$url` — The canonical URL of the page/item being shared.
- `$title` — A short description sent with the share (pre-filled tweet text, email subject, WhatsApp message prefix, etc.).
- `$options` — Associative array of optional settings — see below.

---

## Options

| Key         | Type   | Default                                              | Description                                 |
|-------------|--------|------------------------------------------------------|---------------------------------------------|
| 'platforms' | array  | ['facebook','whatsapp','twitter','email','copy']     | Which buttons to show, in what order.       |
| 'label'     | string | 'Share:'                                             | Text before the buttons. '' to hide it.     |
| 'size'      | int    | 38                                                   | Button diameter in pixels.                  |
| 'class'     | string | ''                                                   | Extra CSS class(es) on the wrapper div.     |

---

## Usage examples

1. Basic — all platforms, default label:

```php
<?= shareButtons('https://camerooncom.com/ad/123', 'Samsung TV 55"') ?>
```

2. WhatsApp + copy only — good for a mobile-first listing card:

```php
<?= shareButtons($adUrl, $adTitle, ['platforms' => ['whatsapp', 'copy']]) ?>
```

3. Hide the "Share:" label:

```php
<?= shareButtons($adUrl, $adTitle, ['label' => '']) ?>
```

4. Larger buttons with a custom wrapper class for extra spacing:

```php
<?= shareButtons($adUrl, $adTitle, ['size' => 44, 'class' => 'mt-3']) ?>
```

5. Typical use in an ad details view:

```php
<?php
    $shareUrl   = env('APP_LIVE_URL') . 'ad/' . $ad->id;
    $shareTitle = $ad->title . ' — on Camerooncom';
?>
<?= shareButtons($shareUrl, $shareTitle) ?>
```

---

## Using the class directly

The helper is a thin wrapper. You can also call the class method directly from a controller or service if you need to capture the HTML string first:

```php
use Dorguzen\Core\DGZ_Share;

$html = DGZ_Share::buttons($url, $title, $options);
```

---

## Where the files live

| File                          | Role                                         |
|-------------------------------|----------------------------------------------|
| core/DGZ_Share.php            | The class — SVG icons, share URLs, CSS, JS   |
| bootstrap/helpers.php         | shareButtons() global helper function        |

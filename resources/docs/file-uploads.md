# File Uploads

Dorguzen provides two upload classes. Use the right one for your file type:

| Class | Use for | Thumbnail |
|---|---|---|
| `DGZ_Uploader` | Images (JPEG, PNG, GIF, WebP) | Yes — automatic on `'resize'` mode |
| `DGZ_Upload` | Non-image files (video, audio, PDF) | No |

Both classes live in `core/DGZ_Uploader/`.

---

## Basic Image Upload with Thumbnail

```php
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . $this->config->getFileRootPath() . 'assets/images/blog/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$uploader = new DGZ_Uploader($uploadDir);
$uploader->move('resize');

$filenames  = $uploader->getFilenames();
$coverImage = $filenames[0] ?? null;  // e.g. 'abc123.jpg'
```

To reference the thumbnail:

```php
$thumb = DGZ_Upload::thumbName($coverImage);  // 'abc123_thb.jpg'
```

---

## The `move()` Modes

| Mode | Validates | Thumbnail |
|---|---|---|
| `'original'` | Type + size | No |
| `'original-allow'` | None | No |
| `'resize'` | Type + size | Yes |

**`'original-allow'`** — no validation. Use only in admin-only areas for arbitrary file types.

---

## Permitted MIME Types

Default accepted types:
- `image/gif`, `image/jpeg`, `image/pjpeg`, `image/png`, `image/webp`

Add extra types:

```php
$uploader->addPermittedTypes('application/pdf');
$uploader->addPermittedTypes(['text/plain', 'image/tiff']);
```

---

## File Size Limit

Default is set in `configs/app.php` (`'maxFileUploadSize'`). Override per upload:

```php
$uploader->setMaxSize('5MB');    // human-readable: B, KB, MB, GB
$uploader->setMaxSize(5242880);  // raw bytes also accepted
```

---

## Thumbnail Options

### Max dimension

Default thumbnail max dimension is 200px. Override before `move()`:

```php
$uploader->setThumbMaxSize(400);  // neither side exceeds 400px
$uploader->move('resize');
```

Thumbnails are scaled proportionally.

### Redirect thumbnails to a different folder

```php
$uploader->setThumbDestination('/absolute/path/to/thumbs/');
$uploader->move('resize');
```

### Filename convention

The `_thb` suffix is inserted before the extension:

```
sunset.jpg  →  sunset_thb.jpg
```

Derive the thumbnail name from the original:

```php
DGZ_Upload::thumbName('sunset.jpg');        // 'sunset_thb.jpg'
DGZ_Upload::thumbName('sunset.jpg', '_sm'); // 'sunset_sm.jpg'
```

### Quality

JPEG and WebP thumbnails use quality 82. PNG uses compression level 6. Both are optimal defaults for web delivery.

---

## Non-Image File Uploads

Use `DGZ_Upload` directly when no thumbnail is needed:

```php
use Dorguzen\Core\DGZ_Uploader\DGZ_Upload;

$upload = new DGZ_Upload($uploadDir);
$upload->move('original-allow');

$filenames = $upload->getFilenames();
$videoFile = $filenames[0] ?? null;
```

---

## Checking Results

```php
$uploader->move('resize');

$filenames = $uploader->getFilenames();  // array of uploaded filenames
$messages  = $uploader->getMessages();   // status messages (success + errors)

if (empty($filenames)) {
    // nothing uploaded — check $messages
}
```

---

## Static Helpers

```php
DGZ_Upload::extension('photo.jpg')        // 'jpg'
DGZ_Upload::thumbName('photo.jpg')        // 'photo_thb.jpg'
DGZ_Upload::thumbName('photo.jpg', '_sm') // 'photo_sm.jpg'
```

---

## Class Reference

| Class | Role |
|---|---|
| `DGZ_Upload` | Base: validation, move, filename retrieval |
| `DGZ_Uploader` | Extends DGZ_Upload; adds thumbnail generation |
| `DGZ_Thumbnail` | GD-based proportional image resize; outputs `_thb` file |

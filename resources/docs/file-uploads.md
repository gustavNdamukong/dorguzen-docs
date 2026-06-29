# File Uploads

> FILE MANAGEMENT

## Contents

```
1. Image Uploads — DGZ_Uploader
    1.1  Overview and Architecture
    1.2  Choosing the Right Class
    1.3  The $modify Parameter — the Key to Everything
    1.4  DGZ_Upload — Base Class Reference
    1.5  DGZ_Uploader — Child Class Reference
    1.6  DGZ_Thumbnail — Thumbnail Engine Reference
    1.7  Scenarios and Code Examples
         A. Simple image upload (no thumbnail)
         B. Image upload with automatic thumbnail
         C. Upload with thumbnail in a separate folder
         D. Upload images into a per-record sub-folder
         E. Uploading a document (PDF)
         F. Uploading a video or audio file
         G. Multiple file upload
         H. Controlling thumbnail dimensions and quality
         I. Generating a thumbnail from an already-uploaded file
         J. Displaying uploaded images in a view
    1.8  Supported File Types
    1.9  Config Integration (configs/app.php)
    1.10 Error Handling and Messages
    1.11 Backwards Compatibility Notes

PDF Generation — moved to its own page (see "PDF Generation" in the sidebar)
```

---

## 1. Image Uploads — DGZ_Uploader

### 1.1 Overview and Architecture

Dorguzen ships a three-class file upload system in `core/DGZ_Uploader/`:

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         core/DGZ_Uploader/                              │
│                                                                         │
│  DGZ_Upload          ← base class                                       │
│    Validation, move_uploaded_file(), conflict-safe renaming.             │
│    Works for images, documents, videos — any file type.                 │
│    Does NOT generate thumbnails.                                        │
│                                                                         │
│  DGZ_Uploader        ← child class (extends DGZ_Upload)                 │
│    Everything DGZ_Upload does, PLUS optional thumbnail generation.      │
│    This is the class you use for image uploads in web features.         │
│                                                                         │
│  DGZ_Thumbnail       ← thumbnail engine (used by DGZ_Uploader)          │
│    PHP GD-based proportional resize, quality control, WebP support.     │
│    Can also be used standalone on already-uploaded files.               │
└─────────────────────────────────────────────────────────────────────────┘
```

The flow when you call `move('resize')` on a DGZ_Uploader instance:

```
HTTP upload ($_FILES)
    │
    ▼
DGZ_Uploader::move('resize')
    │
    ├── validates MIME type
    ├── validates file size
    ├── move_uploaded_file() → saves original  (e.g. photo.jpg)
    │
    └── DGZ_Thumbnail::create()
            ├── reads image dimensions with getimagesize()
            ├── calculates proportional thumbnail size (max 200px longest side)
            ├── imagecopyresampled()   ← high-quality bilinear resampling
            └── saves thumbnail        (e.g. photo_thb.jpg, quality 82)
```

---

### 1.2 Choosing the Right Class

```
Scenario                                    Use
──────────────────────────────────────────  ─────────────────────────────
Upload an image, no thumbnail needed        DGZ_Upload with 'original'
Upload an image + generate a thumbnail      DGZ_Uploader with 'resize'
Upload an admin image, skip all checks      DGZ_Uploader with 'original-allow'
Upload a PDF or text document               DGZ_Upload + addPermittedTypes()
Upload a video or audio file                DGZ_Upload with 'original-allow'
Re-thumbnail an already-uploaded image      DGZ_Thumbnail standalone
```

---

### 1.3 The $modify Parameter — the Key to Everything

Both classes have a single `move()` method. The `$modify` string argument controls
what happens. Think of it as the upload mode:

```
move('original')
    Validates MIME type and file size. Saves the file as-is. No thumbnail.
    This is the default and the safe choice for image uploads.

move('original-allow')
    Bypasses ALL validation — no type check, no size check. The file is
    saved unconditionally. Use this for admin-only upload forms where you
    trust the user, or for file types outside the built-in whitelist
    (videos, audio, ZIP files, etc.).
    ⚠ Never expose this mode to unauthenticated users.

move('resize')
    Validates MIME type and file size, saves the original, then
    automatically generates a _thb thumbnail. Only meaningful on
    DGZ_Uploader (calling it on DGZ_Upload just does a plain upload).
```

The second argument, `$overwrite` (bool, default false), controls what happens
when a file with the same name already exists at the destination:

```
false (default) — keeps both files; the new one is renamed (photo_1.jpg,
                  photo_2.jpg, etc.)
true            — overwrites the existing file silently
```

---

### 1.4 DGZ_Upload — Base Class Reference

File: `core/DGZ_Uploader/DGZ_Upload.php`

**Constructor:**

```php
new DGZ_Upload(string $destinationPath)
```

`$destinationPath` — absolute filesystem path to the upload directory.
Must already exist and be writable.

**Public methods:**

```
move(string $modify = 'original', bool $overwrite = false) : void
    Triggers the upload pipeline. Reads from $_FILES automatically.
    See §1.3 for the $modify values.

getMessages() : array
    Returns an array of human-readable outcome strings, one per file:
        "photo.jpg uploaded successfully"
        "photo.jpg exceeds maximum size: 50.0kB"
        "video.mp4 is not a permitted type of file."
    Always check this after move() to give feedback to the user.

extension(string $filename) : string  [static]
    Returns the file extension of a filename without the dot.
        DGZ_Upload::extension('sunset.jpg')   // 'jpg'
        $uploader->extension($filenames[0])   // instance call also works

thumbName(string $filename, string $suffix = '_thb') : string  [static]
    Derives the thumbnail filename from an original filename.
    Use this anywhere you need to display the thumbnail — in views,
    services, or API responses — without repeating the pathinfo() logic.
        DGZ_Upload::thumbName('sunset.jpg')          // 'sunset_thb.jpg'
        DGZ_Upload::thumbName('hero.PNG', '_sm')     // 'hero_sm.PNG'
        $uploader->thumbName($filenames[0])          // instance call also works
    The $suffix must match whatever was passed to DGZ_Thumbnail::setSuffix().
    When using the default suffix (_thb), the second argument can be omitted.

getFilenames() : array
    Returns the final saved filename(s) after any conflict renaming.
    Store this in your database — it is the name that was actually written
    to disk, which may differ from what the user uploaded.

    By design, getFilenames() returns ONLY the original file names, never
    the thumbnail names. This is intentional: thumbnail names do not need
    to be stored because they are always mechanically derivable from the
    original. Strip the extension, append _thb, put the extension back —
    that is always the thumbnail name. Since the thumbnail is generated from
    the original in the same operation, conflict renaming applies to both at
    once: if the original is saved as sunset_1.jpg (because sunset.jpg was
    already taken), the thumbnail will be sunset_1_thb.jpg. They are always
    in sync, so there is nothing extra to store or look up.

    The one exception: if you explicitly call DGZ_Thumbnail::setSuffix() to
    use a suffix other than _thb, make sure you use that same suffix
    consistently wherever you derive the thumbnail name in your views.
    Since _thb is the default and there is rarely a reason to change it,
    in practice this is never an issue.

setMaxSize(int|string $size) : void
    Overrides the default 50 KB file size limit.

    Accepts a human-readable string (recommended) or a raw integer in bytes.
    The string is case-insensitive and the space between number and unit is
    optional. Decimal values are supported.

        $uploader->setMaxSize('2MB');       // 2 megabytes
        $uploader->setMaxSize('500KB');     // 500 kilobytes
        $uploader->setMaxSize('1.5 MB');    // 1.5 megabytes
        $uploader->setMaxSize('1GB');       // 1 gigabyte (large video, admin only)
        $uploader->setMaxSize(5242880);     // raw bytes — still works (5 MB)
        $uploader->setMaxSize(5 * 1024 * 1024); // expression — still works

    Supported units: B, KB, MB, GB (case-insensitive).
    If no unit is given the value is treated as bytes.

getMaxSize() : string
    Returns the current limit formatted for display: e.g. "50.0kB".

addPermittedTypes(array $mimes) : void
    Appends extra MIME types to the whitelist. Only types from the
    $alsoValid list inside isValidMime() are accepted:
        'image/tiff', 'image/webp', 'application/pdf',
        'text/plain', 'text/rtf'
    Example: $uploader->addPermittedTypes(['application/pdf'])
```

Default permitted image types:

```
image/gif, image/jpeg, image/pjpeg, image/png, image/webp
```

---

### 1.5 DGZ_Uploader — Child Class Reference

File: `core/DGZ_Uploader/DGZ_Uploader.php`

Inherits everything from DGZ_Upload and adds thumbnail support.

**Constructor:**

```php
new DGZ_Uploader(string $path, string $uniqueSubFolder = '')
```

`$path` — EITHER a key from your `configs/app.php` array that maps to an
absolute directory path, OR an absolute path directly. If the key is found
in config, that value is used as the destination. Otherwise, `$path` itself
is used as an absolute path.

`$uniqueSubFolder` — optional sub-directory under `$path`. Useful when each
record (portfolio item, news article, product) has its own image folder.
Example: passing `'42/'` gives destination `'/absolute/path/portfolioImages/42/'`
The constructor ensures a trailing slash.

The constructor also reads `maxFileUploadSize` from `configs/app.php` and
applies it automatically. You do not need to call `setMaxSize()` manually
unless you want a different limit than the global config.

**Additional methods (beyond DGZ_Upload):**

```
setThumbMaxSize(int $pixels) : self
    Sets the maximum pixel dimension for the generated thumbnail.
    Default: 200. The thumbnail is scaled proportionally so neither
    width nor height exceeds this value. The original file is always
    saved at its full uploaded size — only the thumbnail is capped.
    Call before move('resize').

        $uploader = new DGZ_Uploader('galleryImagesPath');
        $uploader->setThumbMaxSize(150);
        $uploader->move('resize');

    Returns $this so calls can be chained.

setThumbDestination(string $path) : void
    Redirects thumbnail output to a different folder from the original.
    Must be called before move(). If not called, thumbnails land in the
    same directory as the original.
```

---

### 1.6 DGZ_Thumbnail — Thumbnail Engine Reference

File: `core/DGZ_Uploader/DGZ_Thumbnail.php`

Used internally by DGZ_Uploader. Also usable standalone when you need to
re-thumbnail a file that was already uploaded, or batch-process existing images.

**Constructor:**

```php
new DGZ_Thumbnail(string $absoluteImagePath)
```

Reads the image from disk. If unreadable or not a supported format,
errors are added to `getMessages()` and `create()` will do nothing safely.

**Default settings:**

```
$_maxSize = 500     DGZ_Thumbnail's own internal default, longest side in pixels.
                    NOTE: when DGZ_Uploader calls this class via move('resize'),
                    it overrides this to 200px automatically. The 500px default
                    only applies when you use DGZ_Thumbnail standalone (e.g.
                    batch re-thumbnailing). A 1200×800 image becomes 500×333.
                    To change the default when used via DGZ_Uploader, call
                    $uploader->setThumbMaxSize() before move('resize') instead
                    of setMaxSize() on DGZ_Thumbnail directly.
$_suffix  = '_thb'  appended to base filename before the extension:
                    hero.jpg → hero_thb.jpg
$_quality = 82      JPEG and WebP output quality (1–100).
                    82 is the web industry standard: excellent visual quality
                    at roughly 60% the file size of quality 100.
                    PNG always uses compression level 6 (level 0 =
                    uncompressed; level 9 = maximum compression).
```

**Public methods:**

```
setDestination(string $path) : void
    Sets the output directory for the thumbnail. Called automatically by
    DGZ_Uploader. Must be called before create() when using standalone.

setMaxSize(int $pixels) : void
    Change the thumbnail size cap. Default 500. Example: setMaxSize(300)
    caps the longest dimension at 300px.

setSuffix(string $suffix) : void
    Change the thumbnail suffix. Leading underscore is added automatically
    if missing. setSuffix('thumb') produces _thumb. setSuffix('_sm') stays _sm.
    setSuffix('') removes the suffix entirely (thumbnail saves with same
    base name as original — careful, may overwrite!).

setQuality(int $quality) : void
    Sets JPEG and WebP output quality. Accepts 1–100.
    Recommendations:
        90    near-lossless, larger files — use for print-quality originals
        82    default — excellent for web display
        75    good quality, noticeably smaller — use for heavily-trafficked pages
        60    visible compression artefacts — use only for thumbnails of thumbnails

create() : void
    Runs the resize pipeline: calculateSize → getName → createThumbnail.
    Safe to call even if the image was unreadable — it will just add a
    message and do nothing.

getMessages() : array
    Returns outcome strings: "hero_thb.jpg created successfully." or
    "Couldn't create a thumbnail for hero.jpg"
```

**Supported input → output formats:**

```
JPEG (.jpg/.jpeg)  →  JPEG
PNG (.png)         →  PNG  (alpha transparency preserved)
GIF (.gif)         →  GIF
WebP (.webp)       →  WebP (alpha transparency preserved)
```

Images smaller than `$_maxSize` on both sides are saved at their original
dimensions — they are never upscaled, only downscaled.

---

### 1.7 Scenarios and Code Examples

#### A. Simple image upload (no thumbnail)

Upload a user's avatar or a news article hero image when a thumbnail is not
needed. Uses DGZ_Upload directly.

```php
use Dorguzen\Core\DGZ_Uploader\DGZ_Upload;

// HTML form: <input type="file" name="news_image">
$destination = '/Applications/MAMP/htdocs/myapp/assets/images/news/';
$uploader = new DGZ_Upload($destination);
$uploader->move('original');   // validate + upload, no thumbnail

$messages  = $uploader->getMessages();
$filenames = $uploader->getFilenames();

if (!empty($filenames)) {
    $savedAs = $filenames[0];  // e.g. 'hero.jpg' or 'hero_1.jpg' if renamed
    // save $savedAs to your database row
} else {
    // upload failed — show $messages to the user
}
```

#### B. Image upload with automatic thumbnail (the recommended pattern)

This is the pattern to use for news articles, portfolio items, newsletters,
blog posts, gallery images — anywhere you show a grid of thumbnails and open
the full-size image on click.

`move('resize')` uploads the original AND generates the `_thb` thumbnail in one
call. You only ever store the original filename in the database; the thumbnail
name is always reconstructable from it.

```php
use Dorguzen\Core\DGZ_Uploader\DGZ_Uploader;

// 'portfolioImagesPath' is a key in configs/app.php pointing to the
// absolute upload folder. See §1.9 for how to set this up.
$uploader = new DGZ_Uploader('portfolioImagesPath');
$uploader->move('resize');

$messages  = $uploader->getMessages();
$filenames = $uploader->getFilenames();

if (!empty($filenames)) {
    $original  = $filenames[0];  // 'sunset.jpg'  — save this in the DB
    // thumbnail saved automatically as 'sunset_thb.jpg' in the same folder
}

// --- In your view, deriving the thumbnail name ---
// Never store the thumbnail name in the DB. Use the built-in helper:
$thumb = DGZ_Upload::thumbName($original);   // 'sunset_thb.jpg'
```

#### C. Upload with thumbnail in a separate folder

Useful when you want full-resolution originals in one place and
web-displayable thumbnails in another (e.g. originals in /full/, thumbs
in /thumbs/ for CDN delivery).

```php
$uploader = new DGZ_Uploader('portfolioImagesPath');
$uploader->setThumbDestination(
    '/Applications/MAMP/htdocs/myapp/assets/images/portfolio/thumbs/'
);
$uploader->move('resize');

$filenames = $uploader->getFilenames();
// Original: /assets/images/portfolio/sunset.jpg
// Thumbnail: /assets/images/portfolio/thumbs/sunset_thb.jpg
```

#### D. Upload images into a per-record sub-folder

For features like a portfolio or product catalogue where each item has its
own directory. Pass the record's ID (or slug) as the second constructor arg.
The folder must already exist and be writable — create it when you create
the DB record.

```php
// After INSERT, get the new record ID:
$itemId = $this->portfolioModel->getLastInsertId();

// Create the sub-folder (only if it doesn't exist yet):
$basePath = '/Applications/MAMP/htdocs/myapp/assets/images/portfolio/';
$subDir   = $basePath . $itemId . '/';
if (!is_dir($subDir)) {
    mkdir($subDir, 0755, true);
}

// Upload into that sub-folder:
$uploader = new DGZ_Uploader('portfolioImagesPath', $itemId . '/');
$uploader->move('resize');

// Files land at: /assets/images/portfolio/42/hero.jpg
//                /assets/images/portfolio/42/hero_thb.jpg
```

#### E. Uploading a document (PDF or plain text)

DGZ_Upload's default whitelist only allows images. To accept PDFs, you must
add the MIME type with `addPermittedTypes()`. You also need to raise the max
size since the default 50 KB limit is too small for most documents.

```php
use Dorguzen\Core\DGZ_Uploader\DGZ_Upload;

$uploader = new DGZ_Upload('/Applications/MAMP/htdocs/myapp/storage/docs/');
$uploader->addPermittedTypes(['application/pdf']);
$uploader->setMaxSize('10MB');
$uploader->move('original');

$messages  = $uploader->getMessages();
$filenames = $uploader->getFilenames();
```

Note: PHP's own upload size limits (`upload_max_filesize` and `post_max_size` in
`php.ini`) also apply and must be raised server-side for large files. The
`setMaxSize()` call here adds an application-level guard on top of that.

#### F. Uploading a video or audio file

Videos and audio files have MIME types not in the DGZ whitelist at all
(`video/mp4`, `audio/mpeg`, etc.). The cleanest approach is `'original-allow'`
mode, which bypasses all type and size checks. Reserve this for admin forms.

```php
use Dorguzen\Core\DGZ_Uploader\DGZ_Upload;

// Admin-only: upload a video with no type or size restriction.
// 'original-allow' bypasses setMaxSize() too, so no need to set it here.
$uploader = new DGZ_Upload('/Applications/MAMP/htdocs/myapp/storage/videos/');
$uploader->move('original-allow');

$messages  = $uploader->getMessages();
$filenames = $uploader->getFilenames();
```

⚠ Important: PHP still enforces `upload_max_filesize` and `post_max_size` from
`php.ini` regardless of `$modify`. For large video files, raise these in your
`php.ini` or `.htaccess`:

```
upload_max_filesize = 512M
post_max_size       = 512M
max_execution_time  = 300
```

Alternative: for public-facing video upload with type checking, use
`addPermittedTypes()` and `setMaxSize()` — but do NOT use `isValidMime()` for
video MIME types as they are not in its allowed list. You would need to
extend the class and override `isValidMime()` for that use case.

#### G. Multiple file upload

The class handles multi-file inputs automatically. Your HTML form just needs
the multiple attribute and array notation in the name:

```html
<input type="file" name="gallery_images[]" multiple>
```

The PHP side is identical to a single upload:

```php
$uploader = new DGZ_Uploader('galleryImagesPath');
$uploader->move('resize');

$filenames = $uploader->getFilenames();
// $filenames = ['photo1.jpg', 'photo2.jpg', 'photo3_thb.jpg' ... ]
// Wait — getFilenames() returns only the originals, not thumbnails.
// Thumbnails are generated silently alongside each original.

foreach ($filenames as $name) {
    // save each $name to the database
}

// Messages are collected for each file individually:
foreach ($uploader->getMessages() as $msg) {
    echo $msg . "\n";
    // "photo1.jpg uploaded successfully"
    // "photo2.jpg uploaded successfully and renamed photo2_1.jpg"
    // "huge.jpg exceeds maximum size: 50.0kB"
}
```

#### H. Controlling thumbnail dimensions and quality

DGZ_Uploader creates thumbnails with default settings (200px max side,
quality 82). The original file is always saved at full uploaded size.

To override the thumbnail max size, call `setThumbMaxSize()` before `move()`:

```php
$uploader = new DGZ_Uploader('galleryImagesPath');
$uploader->setThumbMaxSize(300);   // cap thumbnail at 300px longest side
$uploader->move('resize');

$filenames = $uploader->getFilenames();
// original: photo.jpg (full size)  |  thumbnail: photo_thb.jpg (≤300px)
```

To also override quality, use DGZ_Thumbnail standalone after the upload:

Step 1 — upload the original only (no thumbnail yet):

```php
$uploader = new DGZ_Uploader('galleryImagesPath');
$uploader->move('original');
$filenames = $uploader->getFilenames();
```

Step 2 — generate a custom thumbnail with full control:

```php
use Dorguzen\Core\DGZ_Uploader\DGZ_Thumbnail;

$destDir  = '/Applications/MAMP/htdocs/myapp/assets/images/gallery/';
$original = $destDir . $filenames[0];

$thumb = new DGZ_Thumbnail($original);
$thumb->setDestination($destDir);
$thumb->setMaxSize(300);   // cap at 300px longest side
$thumb->setQuality(75);    // slightly more compression (default is 82)
$thumb->create();

foreach ($thumb->getMessages() as $msg) {
    echo $msg;  // "photo_thb.jpg created successfully."
}
```

Quality guide:

```
90  — near-lossless, ~40% larger than default; good for high-quality prints
82  — default, excellent web quality
75  — still good, noticeably smaller files; good for high-traffic pages
60  — visible artefacts; only for tiny thumbnails where size is critical
```

#### I. Generating a thumbnail from an already-uploaded file

If you have images on disk that were uploaded without thumbnails (e.g. before
DGZ_Uploader was adopted), you can batch-generate thumbnails using
DGZ_Thumbnail standalone.

```php
use Dorguzen\Core\DGZ_Uploader\DGZ_Thumbnail;

$sourceDir = '/Applications/MAMP/htdocs/myapp/assets/images/portfolio/';
$thumbDir  = $sourceDir . 'thumbs/';  // must already exist

foreach (glob($sourceDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) as $file) {
    // skip files that are already thumbnails
    if (strpos(basename($file), '_thb.') !== false) continue;

    $thumb = new DGZ_Thumbnail($file);
    $thumb->setDestination($thumbDir);
    $thumb->create();

    foreach ($thumb->getMessages() as $msg) {
        echo $msg . "\n";
    }
}
```

Run this as a one-off CLI script or a controller action behind an admin route.

#### J. Displaying uploaded images in a view

Store only the original filename in your database column. In your view, build
the file path using `getFileRootPath()` from Config, and derive the thumbnail
name from the original filename.

In your service/controller, pass the image filename to the view:

```php
$item = $this->portfolioModel->getItem($id);
// $item['portfolio_image'] = 'sunset.jpg'
```

In your view:

```php
<?php
use Dorguzen\Core\DGZ_Uploader\DGZ_Upload;

$imgPath   = $this->controller->config->getFileRootPath() . 'assets/images/portfolio/';
$original  = $item['portfolio_image'];                         // 'sunset.jpg'
$thumbnail = DGZ_Upload::thumbName($original);                 // 'sunset_thb.jpg'
?>

<!-- Thumbnail in grid (fast load): -->
<img src="<?= $imgPath . $thumbnail ?>" alt="Portfolio item">

<!-- Link to full-size on click: -->
<a href="<?= $imgPath . $original ?>">
    <img src="<?= $imgPath . $thumbnail ?>" alt="Portfolio item">
</a>
```

---

### 1.8 Supported File Types

For image upload and thumbnail generation:

```
Format  MIME type        Upload   Thumbnail   Notes
──────  ───────────────  ───────  ──────────  ──────────────────────────────
JPEG    image/jpeg       ✓        ✓           Most common for photos
JPEG    image/pjpeg      ✓        ✓           Progressive JPEG (IE legacy)
PNG     image/png        ✓        ✓           Alpha transparency preserved
GIF     image/gif        ✓        ✓           Animation is not preserved
WebP    image/webp       ✓        ✓           25-35% smaller than JPEG
```

For other file types (via `addPermittedTypes` or `'original-allow'`):

```
Format  MIME type              Upload   Thumbnail   Notes
──────  ─────────────────────  ───────  ──────────  ──────────────────────
PDF     application/pdf        ✓        ✗           Via addPermittedTypes()
TXT     text/plain             ✓        ✗           Via addPermittedTypes()
RTF     text/rtf               ✓        ✗           Via addPermittedTypes()
TIFF    image/tiff             ✓        ✗           Via addPermittedTypes()
Video   video/* (any)          ✓        ✗           Use 'original-allow' mode
Audio   audio/* (any)          ✓        ✗           Use 'original-allow' mode
```

---

### 1.9 Config Integration (configs/app.php)

DGZ_Uploader's constructor looks up path keys in your `configs/app.php` array.
Define your upload destinations there rather than hardcoding absolute paths:

```php
// configs/app.php
return [
    // ... other config ...
    'newsImagesPath'       => '/Applications/MAMP/htdocs/myapp/assets/images/news/',
    'portfolioImagesPath'  => '/Applications/MAMP/htdocs/myapp/assets/images/portfolio/',
    'galleryImagesPath'    => '/Applications/MAMP/htdocs/myapp/assets/images/gallery/',
    'newsletterImagesPath' => '/Applications/MAMP/htdocs/myapp/assets/images/newsletters/',
    'maxFileUploadSize'    => '2MB',
];
```

Then in your controller or service:

```php
$uploader = new DGZ_Uploader('newsImagesPath');   // looks up config key
$uploader->move('resize');
```

`maxFileUploadSize` is automatically applied by DGZ_Uploader's constructor —
you do not need to call `setMaxSize()` separately.

Remember: in production the absolute path changes. Set the correct absolute
paths per environment in their respective `configs/app.php` or use `APP_ENV`
branching inside `configs/app.php` to resolve the right path.

---

### 1.10 Error Handling and Messages

Always check `getMessages()` after `move()`. A successful upload produces a
message like "photo.jpg uploaded successfully". A failed upload produces an
error message — but the method does NOT throw an exception, so you must check:

```php
$uploader->move('resize');

$filenames = $uploader->getFilenames();
$messages  = $uploader->getMessages();

if (empty($filenames)) {
    // Nothing was saved — show messages as user-facing errors
    $errors = $messages;
} else {
    $savedAs = $filenames[0];
    // Check messages anyway — some may be warnings (e.g. renamed)
}
```

Common messages:

```
"photo.jpg uploaded successfully"
"photo.jpg uploaded successfully and renamed photo_1.jpg"
"photo.jpg exceeds maximum size: 50.0kB"
"video.mp4 is not a permitted type of file."
"No file selected."
"Error uploading photo.jpg. Please try again."      ← partial upload
"System error uploading photo.jpg. Contact webmaster."
"photo_thb.jpg created successfully."
"Couldn't create a thumbnail for photo.jpg"
```

---

### 1.11 Backwards Compatibility Notes

The following improvements were made to the class system. All changes are
backwards compatible — no public method signatures were altered.

Behaviour changes in DGZ_Thumbnail:

1. Thumbnails now correctly receive the `_thb` suffix.
   Previously the `$_suffix` property was defined (`'_thb'`) but was never
   actually applied in `createThumbnail()` — a bug meaning thumbnails were
   saved with the SAME name as the original, silently overwriting it.
   This is now fixed: hero.jpg generates hero_thb.jpg.
   If you have thumbnails on disk from before this fix, they will have been
   saved without the suffix (same name as original). New thumbnails will
   use the suffix. Keep this in mind when displaying older uploaded images.

2. JPEG output quality changed from 100 to 82.
   Quality 100 is lossless JPEG — the largest possible file size with no
   visible benefit over 82. Quality 82 is the web industry standard.

3. PNG compression changed from 0 to 6.
   Level 0 means uncompressed — PNG files at full size. Level 6 is the
   standard web default: good compression ratio, fast decode.

4. WebP is now a supported upload and thumbnail format (was silently
   rejected before). Alpha transparency is preserved for WebP and PNG.

5. `setQuality()` method added to DGZ_Thumbnail for per-use quality control.

6. Default thumbnail dimension changed from 500px to 200px (DGZ_Uploader).
   DGZ_Uploader now passes 200px to DGZ_Thumbnail automatically when calling
   `move('resize')`. Previously the thumbnail inherited DGZ_Thumbnail's own
   default of 500px, meaning a 500×400 source image produced an identically-
   sized thumbnail. Thumbnails are now always a fraction of the main image.
   DGZ_Thumbnail's own internal default (500px) is unchanged — this only
   affects thumbnails generated through `DGZ_Uploader::move('resize')`.
   To override: call `$uploader->setThumbMaxSize(n)` before `move('resize')`.
   New method added: `DGZ_Uploader::setThumbMaxSize(int $pixels): self`

---

> **PDF generation** has its own page — see [PDF Generation](/dorguzen-docs/docs/pdf-generation).

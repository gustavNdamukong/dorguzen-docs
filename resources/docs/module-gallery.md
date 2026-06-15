# Gallery Module

The Gallery module lives in `modules/Gallery/` and provides a public image gallery organised into albums, with thumbnail support and multi-file upload.

## Enabling the Module

In `.env`:

```ini
MODULES_GALLERY_STATUS=on
```

## Database Tables

| Table | Primary Key | Description |
|---|---|---|
| `gallery_albums` | `album_id` | Albums: `album_name`, `album_slug`, `album_description`, `album_status`, `album_cover`, `created_at` |
| `gallery_images` | `image_id` | Images: `album_id`, `image_filename`, `image_caption`, `image_sort_order`, `created_at` |

## Routes

### Public

| Method | URI | Action | Description |
|---|---|---|---|
| GET | `/gallery` | `GalleryController@index` | All active albums |
| GET | `/gallery/album?albumId={id}` | `GalleryController@album` | Images in an album |

### Admin

| Method | URI | Action | Description |
|---|---|---|---|
| GET | `/admin/gallery` | `GalleryController@manageAlbums` | Manage albums |
| GET/POST | `/admin/gallery/create` | `GalleryController@createAlbum` | Create or edit album |
| GET | `/admin/gallery/delete?albumId={id}` | `GalleryController@deleteAlbum` | Delete album and images |
| GET | `/admin/gallery/images?albumId={id}` | `GalleryController@manageImages` | Manage images in album |
| POST | `/admin/gallery/upload` | `GalleryController@uploadImages` | Upload images (multiple files) |
| GET | `/admin/gallery/deleteImage?imageId={id}` | `GalleryController@deleteImage` | Delete a single image |
| POST | `/admin/gallery/setCover` | `GalleryController@setCover` | Set album cover image |

## GalleryService (Frontend)

| Method | Returns | Description |
|---|---|---|
| `galleryIndexPayload()` | `array` | Active albums with `image_count` |
| `galleryAlbumPayload(int $albumId)` | `array` | Album row and images with `thumb_filename` |

## GalleryAdminService (Admin)

| Method | Returns | Description |
|---|---|---|
| `uploadImage(int $albumId)` | `string` | Uploads single image via `DGZ_Uploader` to `assets/images/gallery/{albumId}/` |
| `deleteAlbumFiles(int $albumId)` | `void` | Deletes all image files for album, then directory |
| `saveAlbum(array $data)` | `int\|false` | Creates album record |
| `updateAlbum(int $albumId, array $data)` | `bool` | Updates album |
| `setCover(int $albumId, string $filename)` | `bool` | Sets `album_cover` |
| `deleteAlbum(int $albumId)` | `bool` | Deletes all image records, then album |
| `saveImage(int $albumId, string $filename, string $caption)` | `int\|false` | Inserts image record |

## File Storage

Images stored per-album at `assets/images/gallery/{albumId}/`. `DGZ_Uploader` creates originals and `_thb` thumbnails in the same directory.

Use `DGZ_Upload::thumbName($filename)` to derive the thumbnail filename:

```php
$thumb = DGZ_Upload::thumbName($image['image_filename']); // 'photo_thb.jpg'
```

## Multi-File Upload

The `uploadImages` controller action iterates `$_FILES['gallery_images']`, rebuilds `$_FILES['gallery_image']` as a single-file slice per iteration, and calls `GalleryAdminService::uploadImage()` for each. After upload, it auto-sets the album cover if none is set yet.

## Album Status

Albums have `album_status` (`active`/`inactive`). Only active albums appear on the public gallery index.

## Architecture Notes

- Deleting an album cascades: image files deleted, then image DB records, then album record.
- Slug generation is collision-safe and lives entirely in `GalleryAdminService`.

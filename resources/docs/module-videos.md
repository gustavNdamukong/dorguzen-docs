# Videos Module

The Videos module lives in `modules/Videos/` and provides a public video library organised into albums, with support for YouTube and Vimeo embeds.

## Enabling the Module

In `.env`:

```ini
MODULES_VIDEOS_STATUS=on
```

## Database Tables

| Table | Primary Key | Description |
|---|---|---|
| `video_albums` | `album_id` | Albums: `album_name`, `album_slug`, `album_description`, `created_at` |
| `videos` | `video_id` | Videos: `album_id`, `video_title`, `video_description`, `video_source` (youtube/vimeo), `video_ref` (bare ID), `video_sort_order`, `created_at` |

## Routes

### Public

| Method | URI | Action | Description |
|---|---|---|---|
| GET | `/videos` | `VideosController@index` | All video albums |
| GET | `/videos/album?albumId={id}` | `VideosController@album` | Videos in an album |

### Admin

| Method | URI | Action | Description |
|---|---|---|---|
| GET | `/admin/videos` | `VideosController@manageAlbums` | Manage albums |
| GET/POST | `/admin/videos/create` | `VideosController@createAlbum` | Create or edit album |
| GET | `/admin/videos/delete?albumId={id}` | `VideosController@deleteAlbum` | Delete album |
| GET | `/admin/videos/videos?albumId={id}` | `VideosController@manageVideos` | Manage videos in album |
| POST | `/admin/videos/addVideo` | `VideosController@addVideo` | Add a video |
| GET | `/admin/videos/deleteVideo?videoId={id}` | `VideosController@deleteVideo` | Delete a video |

## VideoService (Frontend)

| Method | Returns | Description |
|---|---|---|
| `videosIndexPayload()` | `array` | All albums with `video_count` and `cover_video` |
| `videosAlbumPayload(int $albumId)` | `array` | Album row and videos in sort order |
| `VideoService::embedUrl(array $video)` | `string` | Static — returns iframe `src` URL |
| `VideoService::thumbnailUrl(array $video)` | `string` | Static — YouTube `hqdefault.jpg` URL, or `''` for Vimeo |

## Video Reference Handling

Videos are stored as bare IDs (`video_ref`), never full URLs. The `addVideo` action normalises input:

- **YouTube**: extracts 11-character ID from `youtube.com/watch?v=`, `youtu.be/`, or `youtube.com/embed/`; strips query params
- **Vimeo**: extracts numeric ID from `vimeo.com/{id}` or `player.vimeo.com/video/{id}`
- Bare IDs are accepted directly

`VideoService::embedUrl()` converts the stored `video_ref` back to a full embed URL at render time:

```php
$embedUrl = VideoService::embedUrl($video);
// YouTube: https://www.youtube.com/embed/{ref}?rel=0&modestbranding=1
// Vimeo:   https://player.vimeo.com/video/{ref}
```

## Usage in Views

```php
foreach ($videos as $video) {
    $embedUrl = \Dorguzen\Modules\Videos\Services\VideoService::embedUrl($video);
    // <iframe src="<?= $embedUrl ?>">
}
```

For album covers on the index page, `VideoService::thumbnailUrl($album['cover_video'])` returns a YouTube thumbnail URL. For Vimeo it returns `''` — render a placeholder image in that case.

## Architecture Notes

- Videos have no file upload — all content is embedded from YouTube or Vimeo.
- `deleteAlbum` cascades to video records via the service (not DB constraints).
- All albums are publicly visible — there is no active/inactive status for video albums.
- Slug generation is collision-safe and lives in `VideoAdminService`.

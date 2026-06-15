# News Feature

The News feature provides a public news listing, a single article view with optional embedded video and audio, and a full admin interface. It lives in `src/` as a standard (non-module) feature.

## Database Table

Table: `news` | Primary key: `news_id`

Key columns: `news_title`, `news_description`, `news_image`, `news_status` (`draft`/`published`), `news_video_url`, `news_audio_url`, `created_at`.

## Routes

### Public

| Method | URI | Action | Description |
|---|---|---|---|
| GET | `/news` | `NewsController@news` | News listing |
| GET | `/news/article?newsId={id}` | `NewsController@article` | Single article |

### Admin

| Method | URI | Action | Description |
|---|---|---|---|
| GET | `/admin/news` | `NewsController@manageNews` | Admin listing |
| GET/POST | `/admin/news/create` | `NewsController@createNews` | Create or edit |
| GET | `/admin/news/delete?news_id={id}` | `NewsController@deleteNews` | Delete |

## NewsService

| Method | Returns | Description |
|---|---|---|
| `newsListingPayload()` | `array` | Published news items, 4 latest, total count |
| `singleNewsItemPayload(int $id)` | `array` | Single article row and 4 latest news |
| `handleImageUpload(int $newsId, bool $isEdit)` | `string` | Uploads via `DGZ_Uploader` to `assets/images/news/`; deletes old image on edit |
| `saveNews(array $data)` | `int\|false` | Inserts new news record |
| `updateNews(int $id, array $data)` | `bool` | Updates record |
| `deleteNews(int $id)` | `bool` | Deletes image files then DB record |

## Video Embedding

`NewsController` normalises video URLs before storing them. Accepted formats:
- Full YouTube URL (`youtube.com/watch?v=`, `youtu.be/`, `youtube.com/embed/`)
- Full Vimeo URL (`vimeo.com/`, `player.vimeo.com/video/`)
- Bare YouTube video ID (11 characters)

Stored value is always a clean embed URL ready for an `<iframe>`. Audio is rendered with an HTML5 `<audio>` player.

## File Storage

Images stored in `assets/images/news/`. Use the `_thb` thumbnail in listing cards and the full image in the article view.

## Architecture Notes

- A single `createNews()` controller action handles both create and edit, distinguished by the `?edit=1` query parameter.
- All file I/O (upload, delete on update, delete on remove) lives in `NewsService`.

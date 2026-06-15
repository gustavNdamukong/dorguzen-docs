# Blog Module

The Blog module lives in `modules/Blog/` and provides a full public-facing blog with categories, paginated post listings, individual post pages with a comment system, and a complete admin interface for managing posts, categories, and comments.

## Enabling the Module

In `.env`:

```ini
MODULES_BLOG_STATUS=on
```

When `off`, all frontend blog routes redirect to the home page.

## Database Tables

| Table | Primary Key | Description |
|---|---|---|
| `blog_posts` | `post_id` | Posts: title, slug, body, excerpt, status, author, cover image, category, timestamps |
| `blog_categories` | `category_id` | Categories with name and slug |
| `blog_comments` | `comment_id` | Comments: author, email, body, status (pending/approved), linked to a post |

## Routes

### Public

| Method | URI | Action | Description |
|---|---|---|---|
| GET | `/blog` | `BlogController@index` | Paginated post listing |
| GET | `/blog/post?slug={slug}` | `BlogController@post` | Single post page |
| POST | `/blog/comment` | `BlogController@comment` | Submit a comment |

### Admin

| Method | URI | Action | Description |
|---|---|---|---|
| GET | `/admin/blog` | `BlogController@managePosts` | Manage all posts and categories |
| GET/POST | `/admin/blog/create` | `BlogController@createPost` | Create a post |
| GET/POST | `/admin/blog/edit?postId={id}` | `BlogController@editPost` | Edit a post |
| GET | `/admin/blog/delete?postId={id}` | `BlogController@deletePost` | Delete a post |
| POST | `/admin/blog/saveCategory` | `BlogController@saveCategory` | Add a category |
| GET | `/admin/blog/deleteCategory?categoryId={id}` | `BlogController@deleteCategory` | Delete a category |
| GET | `/admin/blog/comments` | `BlogController@manageComments` | Manage all comments |
| GET | `/admin/blog/approveComment?commentId={id}` | `BlogController@approveComment` | Approve a comment |
| GET | `/admin/blog/deleteComment?commentId={id}` | `BlogController@deleteComment` | Delete a comment |

## BlogService (Frontend)

| Method | Returns | Description |
|---|---|---|
| `blogIndexPayload(int $page, ?int $categoryId, ?string $search)` | `array` | Paginated published posts (6/page), category filter, keyword search |
| `blogPostPayload(string $slug)` | `?array` | Single published post with comments, recent posts, captcha |
| `saveComment(int $postId, array $data)` | `array` | Validates and saves a comment in `pending` status |

## BlogAdminService (Admin)

| Method | Returns | Description |
|---|---|---|
| `managePostsPayload()` | `array` | All posts and categories for admin listing |
| `createPostPayload(?array $data)` | `array` | Categories for create/edit form |
| `editPostPayload(int $postId)` | `?array` | Post row plus categories for edit form |
| `manageCommentsPayload()` | `array` | All comments with post info, plus pending count |
| `handleCoverImageUpload()` | `?string` | Uploads cover image via `DGZ_Uploader` to `assets/images/blog/` |
| `savePost(array $data)` | `int\|false` | Creates post with auto-generated unique slug |
| `updatePost(int $postId, array $data)` | `bool` | Updates post; sets `published_at` on first publish |
| `deletePost(int $postId)` | `?array` | Deletes comments, then post, then cover image files |
| `approveComment(int $id)` | `bool` | Sets comment status to `approved` |

## File Storage

Cover images are stored flat in `assets/images/blog/`. `DGZ_Uploader` creates an `_thb` thumbnail alongside the original.

## Comment Captcha

`blogPostPayload()` generates two random integers and stores their sum in `$_SESSION['_blog_captcha']`. The view renders a "What is N1 + N2?" field, and `saveComment()` validates the answer.

## Architecture Notes

- Deleting a post cascades: comments deleted first, then the DB record, then the image files — in that order.
- Slug generation is collision-safe: loops until a unique slug is found.
- All file I/O lives in `BlogAdminService`. The controller never accesses `$_FILES` directly.

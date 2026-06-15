# Portfolio Feature

The Portfolio feature provides a public portfolio page and admin interface for managing project items with images. It lives in `src/` as a standard (non-module) feature.

## Database Table

Table: `portfolio` | Primary key: `portfolio_id`

Key columns: `portfolio_title`, `portfolio_company_name`, `portfolio_website`, `portfolio_description`, `portfolio_image`, `created_at`.

## Routes

### Public

| Method | URI | Action | Description |
|---|---|---|---|
| GET | `/portfolio` | `PortfolioController@portfolio` | Portfolio listing |

### Admin

| Method | URI | Action | Description |
|---|---|---|---|
| GET | `/admin/portfolio` | `PortfolioController@managePortfolio` | Admin listing |
| GET/POST | `/admin/portfolio/create` | `PortfolioController@createPortfolio` | Create or edit |
| GET | `/admin/portfolio/delete?portfolio_id={id}` | `PortfolioController@deletePortfolio` | Delete |

## PortfolioService

| Method | Returns | Description |
|---|---|---|
| `portfolioPayload()` | `array` | All portfolio items, newest first |
| `managePortfolioPayload()` | `array` | All items for admin table |
| `handleImageUpload(int $id, bool $isEdit)` | `string` | Uploads via `DGZ_Uploader` to `assets/images/portfolio/`; deletes old image on edit |
| `savePortfolioItem(array $data)` | `int\|false` | Inserts new portfolio record |
| `updatePortfolioItem(int $id, array $data)` | `bool` | Updates record |
| `deletePortfolioItem(int $id)` | `bool` | Deletes image and thumbnail files, then DB record |

## File Storage

Images stored in `assets/images/portfolio/`. `DGZ_Uploader` creates an `_thb` thumbnail automatically.

## Architecture Notes

- A single `createPortfolio()` action handles both create and edit, distinguished by the `?edit=1` query parameter.
- All file I/O lives in `PortfolioService`. Admin routes require `admin`, `admin_gen`, or `super_admin` role.

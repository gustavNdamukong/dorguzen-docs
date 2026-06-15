# Directory Structure

```
dorguzen/
├── assets/                     # Public static assets (CSS, JS, images)
│   ├── css/
│   ├── js/
│   └── images/
│
├── bootstrap/                  # Framework boot sequence
│   ├── app.php                 # DI container setup — registers all singletons
│   ├── config.php              # Loads and merges config files
│   ├── helpers.php             # Core helper functions (env, config, event, dispatch)
│   ├── custom_helpers.php      # Project-specific helper functions
│   ├── helpers_runtime.php     # Runtime helpers (path resolution, etc.)
│   └── cache/
│       └── config.php          # Config cache (auto-generated, do not edit)
│
├── configs/                    # Application configuration
│   ├── app.php                 # App settings, modules, permissions
│   ├── database.php            # DB credentials
│   └── events.php              # Event → listener mappings
│
├── core/                       # Dorguzen framework internals (DGZ_* classes)
│   │                           # Do not modify files in this directory
│   ├── DGZ_Controller.php
│   ├── DGZ_Model.php
│   ├── DGZ_Router.php
│   ├── DGZ_View.php
│   ├── DGZ_HtmlView.php
│   ├── DGZ_Layout.php
│   ├── DGZ_Container.php
│   ├── DGZ_Request.php
│   ├── DGZ_Response.php
│   ├── DGZ_Messenger.php       # Email sending
│   ├── DGZ_Logger.php          # Logging (file/db/both)
│   ├── DGZ_Slack.php           # Slack notifications
│   ├── DGZ_Uploader/           # File upload + thumbnail generation
│   │   ├── DGZ_Upload.php
│   │   ├── DGZ_Uploader.php
│   │   └── DGZ_Thumbnail.php
│   └── CLI/                    # `php dgz` command implementations
│
├── database/
│   ├── migrations/             # Migration files (timestamp-prefixed)
│   └── seeders/                # Seeder classes
│
├── layouts/                    # Layout wrappers (HTML shell, head, nav, footer)
│   └── seoMaster/
│       ├── seoMasterLayout.php
│       ├── header.inc.php
│       └── footer.inc.php
│
├── middleware/
│   ├── globalMiddleware/       # Runs on every request
│   │   ├── AuthMiddleware.php
│   │   ├── CsrfPsrMiddleware.php
│   │   └── FormValidationMiddleware.php
│   └── routeMiddleware/        # Applied per-route via ->middleware([])
│       └── AuthMiddleware.php
│
├── modules/                    # Optional feature modules
│   ├── Seo/
│   ├── Blog/
│   ├── Gallery/
│   └── Videos/
│
├── routes/
│   ├── web.php                 # Web (HTML) routes
│   └── api.php                 # API routes
│
├── src/                        # Application source code
│   ├── controllers/            # Thin web controllers (extend DGZ_Controller)
│   ├── services/               # Business logic and all DB access
│   ├── models/                 # DGZ_Model subclasses (one per DB table)
│   ├── config/
│   │   └── Config.php          # Typed config accessor
│   ├── events/                 # Event DTO classes
│   ├── listeners/              # Event listener classes
│   ├── jobs/                   # Queued job classes
│   └── api/
│       └── v1/
│           └── controllers/    # API controllers (use DGZ_APITrait)
│
├── storage/
│   ├── cache/
│   │   └── routes.php          # Route cache (auto-generated)
│   └── logs/                   # Log files (when APP_LOG_DRIVER=file or both)
│
├── tests/                      # PHPUnit test files
├── vendor/                     # Composer dependencies
├── views/                      # HTML view class files
│   └── admin/                  # Admin panel views
├── dgz                         # CLI entry point (`php dgz <command>`)
├── index.php                   # Application entry point
└── .env                        # Environment configuration (never committed)
```

---

## Key Conventions

**`core/`** — Framework internals. Never edit these files. Customise by extending or configuring.

**`src/`** — Your application code. Controllers, services, models, events, and listeners all live here.

**`modules/`** — Self-contained feature modules. Each module mirrors the `src/` structure with its own `Controllers/`, `Models/`, `Services/`, and `Views/` subdirectories.

**`views/`** — PHP classes extending `DGZ_HtmlView`. They receive a `$viewModel` array and render HTML. No business logic here.

**`configs/`** — Plain PHP arrays that return config values. Only `env()` calls are appropriate here.

**`storage/`** — The `cache/` and `logs/` subdirectories must be writable by the web server. They are excluded from version control.

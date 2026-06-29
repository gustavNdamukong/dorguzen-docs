# Directory Structure

## Dorguzen Directory Structure/Skeleton

Here is the Dorguzen directory structure:

```
-assets
-bootstrap
    -cache                   # compiled config/route caches (gitignored)
      -config.php
      -config.php.meta
    -app.php                 # application bootstrap — DI container + service registrations
    -config.php
    -custom_helpers.php      # your own global helper functions
    -helpers.php
    -helpers_runtime.php
    -testing.php
-configs                     # plain-PHP config arrays (env() values only)
    -app.php
    -database.php
    -events.php
    -logging.php
    -modules
-core                        # framework internals — never edit
    -CLI                     # the dgz console + make:* commands
    -DGZ_Uploader
    -DGZ_views
    -Psr
    -config                  # ConfigLoader.php, EnvLoader.php
    -console
    -database                # migration/seeder engine + DB drivers
    -email-views
    -events
    -exceptions              # ValidationException.php, etc.
    -jetForms
    -kernel
    -queues
    -DGZ_Controller.php
    -DGZ_Model.php
    -DGZ_Router.php
    -...                     # the other DGZ_* framework classes
-css
-database
    -factories
    -migrations
    -seeders
-docs
-js
-lang
    -en
    -fre
-layouts                     # one folder per layout (your app's + the shipped ones)
    -admin
    -email
    -seoMaster
-middleware
    -Middleware.php
    -globalMiddleware        # run on every request
      -BaseMiddleware.php
      -CsrfPsrMiddleware.php
      -FormValidationMiddleware.php
    -routeMiddleware         # opt-in, attached per route
      -AuthMiddleware.php
-modules
-public
-routes
    -api.php
    -web.php
-src                         # YOUR application code
    -CLI
    -Testing
    -api                     # versioned API controllers (e.g. api/v1/Controllers/)
    -config                  # Config.php
    -controllers
    -events
    -forms
    -jobs
    -listeners
    -models
    -services
-storage                     # writable; gitignored
    -cache
      -routes.php
    -logs
-tests
    -feature
    -manual
    -support
    -unit
-vendor
-views                       # DGZ_HtmlView classes (no business logic)
    -admin
-.env
-.env.example
-.env.local
-.env.local.example
-.env.testing
-.gitignore
-.htaccess
-.user.ini
-composer.json
-composer.lock
-dgz                         # the CLI entry point
-index.php                   # front controller
-phpunit.xml
-README.md
```

---

## Key Conventions

**`core/`** — Framework internals. Never edit these files. Customise by extending or configuring.

**`src/`** — Your application code. Controllers, services, models, events, and listeners all live here.

**`modules/`** — Self-contained feature modules. Each module mirrors the `src/` structure with its own `Controllers/`, `Models/`, `Services/`, and `Views/` subdirectories.

**`views/`** — PHP classes extending `DGZ_HtmlView`. They receive a `$viewModel` array and render HTML. No business logic here.

**`configs/`** — Plain PHP arrays that return config values. Only `env()` calls are appropriate here.

**`storage/`** — The `cache/` and `logs/` subdirectories must be writable by the web server. They are excluded from version control.

# Directory Structure

## Dorguzen Directory Structure/Skeleton

Here is the Dorguzen directory structure:

```
-assets
-bootstrap
    -cache
      -config.php
      -config.php.meta
    -app.php
    -config.php
    -helpers.php
    -helpers_runtime.php
-configs
    -app.php
    -modules
-core
    -Exceptions
        -ValidationException.php
    -Psr
        -PsrRequestAdapter.php
        -SimpleRequestHandler.php
    -DGZ_Controller.php
    -DGZ_Model.php
    -etc
-css
-docs
-js
-lang
    -en
    -fre
-layouts
    -admin
    -dorguzApp
    -email
-middleware
    -globalMiddleware
      -BaseMiddleware.php
      -CsrfPsrMiddleware.php
      -FormValidationMiddleware.php
    -routeMiddleware
      -AuthMiddleware
-modules
-routes
    -api.php
    -web.php
-src
    -api
      -DocsController.php
    -config
      -Config.php
      -ConfigLoader.php
      -EnvLoader.php
    -controllers
    -events
    -forms
    -jobs
    -listeners
    -forms
    -models
    -services

-storage (file uploads go here)
    -cache
      -routes.php
    -logs
-tests
    -feature
    -manual
    -support
    -unit
-vendor
-views
    -home.php
    -admin
-composer.json
-composer.lock
-dgz
-index.php
-.env
-.env.example
-.env.local
-.env.local.example
-.gitignore
-.htaccess
-phpunit.xml
```

---

## Key Conventions

**`core/`** — Framework internals. Never edit these files. Customise by extending or configuring.

**`src/`** — Your application code. Controllers, services, models, events, and listeners all live here.

**`modules/`** — Self-contained feature modules. Each module mirrors the `src/` structure with its own `Controllers/`, `Models/`, `Services/`, and `Views/` subdirectories.

**`views/`** — PHP classes extending `DGZ_HtmlView`. They receive a `$viewModel` array and render HTML. No business logic here.

**`configs/`** — Plain PHP arrays that return config values. Only `env()` calls are appropriate here.

**`storage/`** — The `cache/` and `logs/` subdirectories must be writable by the web server. They are excluded from version control.

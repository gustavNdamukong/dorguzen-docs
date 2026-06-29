<?php

namespace Dorguzen\Middleware\GlobalMiddleware;

use Dorguzen\Core\DGZ_MiddlewareInterface;
use Dorguzen\Config\Config;

/**
 * DocsOnlyMiddleware
 *
 * dorguzen.com is a strictly public documentation site — no login, no admin,
 * no modules, no database. This GLOBAL middleware allows only the documentation
 * controllers through; ANY other path (auth, admin, user, news, gallery, etc. —
 * however it was routed, INCLUDING Dorguzen's route auto-discovery) is redirected
 * straight to the docs.
 *
 * It is a separate class in middleware/globalMiddleware/ (the Dorguzen convention
 * for app-specific global middleware) so the shipped BaseMiddleware is left
 * untouched. priority = 0 makes it run before every other global middleware, so a
 * non-docs request is turned away before any auth/admin rule can act on it.
 */
class DocsOnlyMiddleware implements DGZ_MiddlewareInterface
{
    /** Lower runs first (framework default is 10); 0 = run before all others. */
    public int $priority = 0;
    public string $name  = 'DocsOnlyMiddleware';

    /** Controllers that serve the public docs (lowercase alias, no "Controller"). */
    private array $allowed = ['home', 'docs', 'pages', 'exception'];

    public function boot(): array
    {
        return [];
    }

    public function handle(string $controller, string $controllerShortName, string $method): bool
    {
        if (in_array(strtolower($controllerShortName), $this->allowed, true)) {
            return true; // documentation request — let it through
        }

        // Anything else → send the visitor to the documentation.
        $base = container(Config::class)->getFileRootPath();
        header('Location: ' . $base . 'docs/introduction', true, 302);
        exit();
    }
}

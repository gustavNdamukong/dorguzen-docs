<?php

namespace Dorguzen\Controllers;

use Dorguzen\Core\DGZ_View;
use Dorguzen\Core\DGZ_Controller;
use Parsedown;

class DocsController extends DGZ_Controller
{
    private Parsedown $parsedown;

    // Ordered list of all doc slugs — drives prev/next navigation
    private array $pages = [
        // Getting Started
        'introduction',
        'installation',
        'configuration',
        'directory-structure',
        // Core Concepts
        'request-lifecycle',
        'routing',
        'controllers',
        'forms',
        'validation',
        'views-layouts',
        'templating',
        'models-orm',
        'dependency-injection',
        // State & Storage
        'sessions',
        'cookies',
        // Going Deeper
        'authentication',
        'middleware',
        'error-handling',
        'migrations',
        'seeding',
        'queues',
        'events',
        'scheduler',
        'data-structures',
        'design-patterns',
        // Features
        'file-uploads',
        'images',
        'input-output',
        'pdf-generation',
        'email',
        'rest-api',
        'social-share',
        'cli',
        'seo',
        'security',
        'testing',
        // Integrations
        'swagger',
        'integrations',
        'slack',
        'networking',
        // Built-in Modules
        'modules',
        'module-blog',
        'module-news',
        'module-portfolio',
        'module-gallery',
        'module-videos',
        // Ecosystem
        'package-management',
        'localisation',
        // Deployment
        'deployment',
        'performance',
        // Contributing
        'collaboration',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->parsedown = new Parsedown();
        $this->parsedown->setSafeMode(false);

        $this->setLayoutDirectory('docs');
        $this->setLayoutView('docsLayout');
    }

    public function getDefaultAction(): string
    {
        return 'introduction';
    }

    // /docs → redirect to /docs/introduction
    public function index(): void
    {
        header('Location: ' . $this->config->getFileRootPath() . 'docs/introduction');
        exit;
    }

    // /docs/{slug}
    public function show($slug): void
    {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

        $mdFile = DGZ_BASE_PATH . '/resources/docs/' . $slug . '.md';

        if (!in_array($slug, $this->pages) || !file_exists($mdFile)) {
            $this->notFound();
            return;
        }

        $markdown = file_get_contents($mdFile);
        $html     = $this->parsedown->text($markdown);

        $labels = $this->pageLabels();
        $title  = ($labels[$slug] ?? ucfirst(str_replace('-', ' ', $slug))) . ' — Dorguzen Docs';
        $desc   = $this->extractDescription($markdown);

        $this->addMetadata([
            '<title>' . htmlspecialchars($title, ENT_QUOTES) . '</title>',
            '<meta name="description" content="' . htmlspecialchars($desc, ENT_QUOTES) . '">',
            '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES) . '">',
            '<meta property="og:description" content="' . htmlspecialchars($desc, ENT_QUOTES) . '">',
            '<meta property="og:type" content="article">',
            '<meta name="twitter:card" content="summary">',
            '<meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES) . '">',
            '<meta name="twitter:description" content="' . htmlspecialchars($desc, ENT_QUOTES) . '">',
        ]);

        [$prev, $next] = $this->adjacentPages($slug);

        $view = DGZ_View::getView('docsShow', $this, 'html');
        $view->show([
            'content'    => $html,
            'slug'       => $slug,
            'prev'       => $prev,
            'next'       => $next,
            'base'       => $this->config->getFileRootPath(),
            'pageLabels' => $this->pageLabels(),
        ]);
    }

    private function extractDescription(string $markdown): string
    {
        // Strip HTML tags, then find the first plain prose line
        $text = preg_replace('/<[^>]+>/', ' ', $markdown);
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === '>' || $line[0] === '-' || $line[0] === '*' || $line[0] === '|' || $line[0] === '!') {
                continue;
            }
            // Strip any remaining markdown syntax (bold, links, code)
            $line = preg_replace('/[`*_\[\]()]/', '', $line);
            $line = preg_replace('/\s+/', ' ', trim($line));
            if (strlen($line) < 20) continue;
            return strlen($line) > 160 ? substr($line, 0, 157) . '…' : $line;
        }
        return 'Dorguzen PHP framework — clean, readable, and honest architecture.';
    }

    // /docs/search
    public function search(): void
    {
        $query   = trim($_GET['q'] ?? '');
        $results = [];

        if ($query !== '') {
            foreach ($this->pages as $slug) {
                $mdFile = DGZ_BASE_PATH . '/resources/docs/' . $slug . '.md';
                if (!file_exists($mdFile)) continue;

                $content = file_get_contents($mdFile);
                if (stripos($content, $query) !== false) {
                    // Pull the first heading as the page title
                    preg_match('/^#\s+(.+)$/m', $content, $m);
                    $title = $m[1] ?? $slug;

                    // Find a short excerpt around the match
                    $pos     = stripos($content, $query);
                    $start   = max(0, $pos - 80);
                    $excerpt = '…' . substr(strip_tags($content), $start, 200) . '…';

                    $results[] = [
                        'slug'    => $slug,
                        'title'   => $title,
                        'excerpt' => $excerpt,
                    ];
                }
            }
        }

        $view = DGZ_View::getView('docsSearch', $this, 'html');
        $view->show([
            'query'   => $query,
            'results' => $results,
            'base'    => $this->config->getFileRootPath(),
        ]);
    }

    private function notFound(): void
    {
        http_response_code(404);
        $view = DGZ_View::getView('docs404', $this, 'html');
        $view->show(['base' => $this->config->getFileRootPath()]);
    }

    private function adjacentPages(string $current): array
    {
        $idx  = array_search($current, $this->pages);
        $prev = ($idx > 0)                          ? $this->pages[$idx - 1] : null;
        $next = ($idx < count($this->pages) - 1)    ? $this->pages[$idx + 1] : null;
        return [$prev, $next];
    }

    private function pageLabels(): array
    {
        return [
            'introduction'        => 'Introduction',
            'installation'        => 'Installation',
            'configuration'       => 'Configuration',
            'directory-structure' => 'Directory Structure',
            'request-lifecycle'   => 'Request Lifecycle',
            'routing'             => 'Routing',
            'controllers'         => 'Controllers',
            'forms'               => 'Forms',
            'validation'          => 'Validation',
            'views-layouts'       => 'Views & Layouts',
            'templating'          => 'Templating',
            'models-orm'          => 'Models & ORM',
            'dependency-injection'=> 'Dependency Injection',
            'sessions'            => 'Sessions',
            'cookies'             => 'Cookies',
            'authentication'      => 'Authentication',
            'middleware'          => 'Middleware',
            'error-handling'      => 'Error Handling',
            'migrations'          => 'Migrations',
            'seeding'             => 'Database Seeding',
            'queues'              => 'Queues & Jobs',
            'events'              => 'Events',
            'scheduler'           => 'Task Scheduler',
            'data-structures'     => 'Data Structures',
            'design-patterns'     => 'Design Patterns',
            'file-uploads'        => 'File Uploads',
            'images'              => 'Image Processing',
            'input-output'        => 'Input & Output',
            'pdf-generation'      => 'PDF Generation',
            'email'               => 'Email & Newsletter',
            'rest-api'            => 'REST API',
            'social-share'        => 'Social Sharing',
            'cli'                 => 'CLI Tool',
            'seo'                 => 'SEO Module',
            'security'            => 'Security',
            'testing'             => 'Testing',
            'swagger'             => 'Swagger / OpenAPI',
            'integrations'        => 'Third-party Services',
            'slack'               => 'Slack Notifications',
            'networking'          => 'Networking & HTTP',
            'modules'             => 'Modules Overview',
            'module-blog'         => 'Blog',
            'module-news'         => 'News',
            'module-portfolio'    => 'Portfolio',
            'module-gallery'      => 'Gallery',
            'module-videos'       => 'Videos',
            'package-management'  => 'Package Management',
            'localisation'        => 'Localisation (i18n)',
            'deployment'          => 'Deploying to Production',
            'performance'         => 'Performance',
            'collaboration'       => 'Contributing & Collab',
        ];
    }
}

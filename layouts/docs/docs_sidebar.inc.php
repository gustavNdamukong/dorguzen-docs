<?php
$base    = $this->config->getFileRootPath();
$current = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

function dgzNavLink(string $base, string $slug, string $label, string $current): string {
    $href   = $base . 'docs/' . $slug;
    $suffix = 'docs/' . $slug;
    $active = (substr($current, -strlen($suffix)) === $suffix) ? 'active' : '';
    return '<a href="' . $href . '" class="docs-nav-link ' . $active . '">' . $label . '</a>';
}
?>

<nav class="docs-nav">

    <div class="docs-nav-group">
        <span class="docs-nav-group-title">Getting Started</span>
        <?= dgzNavLink($base, 'introduction',         'Introduction',          $current) ?>
        <?= dgzNavLink($base, 'installation',         'Installation',          $current) ?>
        <?= dgzNavLink($base, 'configuration',        'Configuration',         $current) ?>
        <?= dgzNavLink($base, 'directory-structure',  'Directory Structure',   $current) ?>
    </div>

    <div class="docs-nav-group">
        <span class="docs-nav-group-title">Core Concepts</span>
        <?= dgzNavLink($base, 'request-lifecycle',    'Request Lifecycle',     $current) ?>
        <?= dgzNavLink($base, 'routing',              'Routing',               $current) ?>
        <?= dgzNavLink($base, 'controllers',          'Controllers',           $current) ?>
        <?= dgzNavLink($base, 'forms',                'Forms',                 $current) ?>
        <?= dgzNavLink($base, 'validation',           'Validation',            $current) ?>
        <?= dgzNavLink($base, 'views-layouts',        'Views & Layouts',       $current) ?>
        <?= dgzNavLink($base, 'templating',           'Templating',            $current) ?>
        <?= dgzNavLink($base, 'models-orm',           'Models & ORM',          $current) ?>
        <?= dgzNavLink($base, 'dependency-injection', 'Dependency Injection',  $current) ?>
    </div>

    <div class="docs-nav-group">
        <span class="docs-nav-group-title">State & Storage</span>
        <?= dgzNavLink($base, 'sessions',             'Sessions',              $current) ?>
        <?= dgzNavLink($base, 'cookies',              'Cookies',               $current) ?>
    </div>

    <div class="docs-nav-group">
        <span class="docs-nav-group-title">Going Deeper</span>
        <?= dgzNavLink($base, 'authentication',       'Authentication',        $current) ?>
        <?= dgzNavLink($base, 'middleware',           'Middleware',            $current) ?>
        <?= dgzNavLink($base, 'error-handling',       'Error Handling',        $current) ?>
        <?= dgzNavLink($base, 'migrations',           'Migrations',            $current) ?>
        <?= dgzNavLink($base, 'seeding',              'Database Seeding',      $current) ?>
        <?= dgzNavLink($base, 'queues',               'Queues & Jobs',         $current) ?>
        <?= dgzNavLink($base, 'events',               'Events',                $current) ?>
        <?= dgzNavLink($base, 'scheduler',            'Task Scheduler',        $current) ?>
        <?= dgzNavLink($base, 'data-structures',      'Data Structures',       $current) ?>
        <?= dgzNavLink($base, 'design-patterns',      'Design Patterns',       $current) ?>
    </div>

    <div class="docs-nav-group">
        <span class="docs-nav-group-title">Features</span>
        <?= dgzNavLink($base, 'file-uploads',         'File Uploads',          $current) ?>
        <?= dgzNavLink($base, 'images',               'Image Processing',      $current) ?>
        <?= dgzNavLink($base, 'input-output',         'Input & Output',        $current) ?>
        <?= dgzNavLink($base, 'pdf-generation',       'PDF Generation',        $current) ?>
        <?= dgzNavLink($base, 'email',                'Email & Newsletter',    $current) ?>
        <?= dgzNavLink($base, 'rest-api',             'REST API',              $current) ?>
        <?= dgzNavLink($base, 'social-share',         'Social Sharing',        $current) ?>
        <?= dgzNavLink($base, 'cli',                  'CLI Tool',              $current) ?>
        <?= dgzNavLink($base, 'seo',                  'SEO Module',            $current) ?>
        <?= dgzNavLink($base, 'security',             'Security',              $current) ?>
        <?= dgzNavLink($base, 'testing',              'Testing',               $current) ?>
    </div>

    <div class="docs-nav-group">
        <span class="docs-nav-group-title">Integrations</span>
        <?= dgzNavLink($base, 'swagger',              'Swagger / OpenAPI',     $current) ?>
        <?= dgzNavLink($base, 'integrations',         'Third-party Services',  $current) ?>
        <?= dgzNavLink($base, 'slack',                'Slack Notifications',   $current) ?>
        <?= dgzNavLink($base, 'networking',           'Networking & HTTP',     $current) ?>
    </div>

    <div class="docs-nav-group">
        <span class="docs-nav-group-title">Built-in Modules</span>
        <?= dgzNavLink($base, 'modules',              'Modules Overview',      $current) ?>
        <?= dgzNavLink($base, 'module-blog',          'Blog',                  $current) ?>
        <?= dgzNavLink($base, 'module-news',          'News',                  $current) ?>
        <?= dgzNavLink($base, 'module-portfolio',     'Portfolio',             $current) ?>
        <?= dgzNavLink($base, 'module-gallery',       'Gallery',               $current) ?>
        <?= dgzNavLink($base, 'module-videos',        'Videos',                $current) ?>
    </div>

    <div class="docs-nav-group">
        <span class="docs-nav-group-title">Ecosystem</span>
        <?= dgzNavLink($base, 'package-management',   'Package Management',    $current) ?>
        <?= dgzNavLink($base, 'localisation',         'Localisation (i18n)',   $current) ?>
    </div>

    <div class="docs-nav-group">
        <span class="docs-nav-group-title">Deployment</span>
        <?= dgzNavLink($base, 'deployment',           'Deploying to Production', $current) ?>
        <?= dgzNavLink($base, 'performance',          'Performance',           $current) ?>
    </div>

    <div class="docs-nav-group">
        <span class="docs-nav-group-title">Contributing</span>
        <?= dgzNavLink($base, 'collaboration',        'Contributing & Collab', $current) ?>
    </div>

</nav>

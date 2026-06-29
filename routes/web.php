<?php
/** @var Dorguzen\Core\DGZ_Router $router */

// Dorguzen Docs — a strictly public, file-based documentation site.
// Only the documentation + a couple of static pages are served. Every other
// path is redirected to the docs by DocsOnlyMiddleware
// (middleware/globalMiddleware/DocsOnlyMiddleware.php), which also covers
// anything reachable through Dorguzen's route auto-discovery.

$router->get('/',          'HomeController@defaultAction')->name('home'); // redirects to the docs
$router->get('/home',      'HomeController@homeRedirect');                // 301 -> canonical "/"

$router->get('/terms',     'PagesController@terms')->name('terms');
$router->get('/privacy',   'PagesController@privacy')->name('privacy');

$router->get('/docs',          'DocsController@index');
$router->get('/docs/search',   'DocsController@search');
$router->get('/docs/{slug}',   'DocsController@show');

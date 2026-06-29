<?php

namespace Dorguzen\Controllers;

use Dorguzen\Core\DGZ_View;
use Dorguzen\Core\DGZ_Controller;

class HomeController extends DGZ_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getDefaultAction()
    {
        return 'defaultAction';
    }

    public function defaultAction()
    {
        // Docs-only site: the home page sends visitors straight into the documentation.
        $this->redirectTo($this->config->getFileRootPath() . 'docs/introduction', 302);
    }


    /**
     * Permanent (301) redirect from "/home" to the canonical site root ("/").
     *
     * The homepage is rendered by defaultAction() at the site root ("/"). A legacy "/home"
     * route also points at the homepage, so without this the SAME page would be reachable at
     * two URLs ("/" and "/home"), which search engines treat as duplicate content and whose
     * ranking signals they split. Sending a 301 to the canonical root consolidates them while
     * keeping any old bookmarks/links to /home working. getHomePage() returns the correct
     * absolute base URL for the current environment (local vs live).
     */
    public function homeRedirect()
    {
        $this->redirectTo($this->config->getHomePage(), 301);
    }
}

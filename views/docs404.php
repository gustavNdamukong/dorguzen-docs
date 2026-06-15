<?php

namespace Dorguzen\Views;

class docs404 extends \Dorguzen\Core\DGZ_HtmlView
{
    public function show(array $viewModel = []): void
    {
        extract($viewModel);
        ?>
<h1>Page Not Found</h1>
<p>That documentation page doesn't exist. Use the sidebar to find what you're looking for,
   or <a href="<?= $base ?>docs/introduction">start from the beginning</a>.</p>
        <?php
    }
}

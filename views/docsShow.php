<?php

namespace Dorguzen\Views;

class docsShow extends \Dorguzen\Core\DGZ_HtmlView
{
    public function show(array $viewModel = []): void
    {
        extract($viewModel);
        ?>
<article class="docs-article">
    <?= $content ?>
</article>

<?php if ($prev || $next): ?>
<nav class="docs-page-nav">
    <?php if ($prev): ?>
        <a href="<?= $base ?>docs/<?= $prev ?>" class="prev">
            <span class="docs-page-nav-label"><i class="fa fa-arrow-left"></i> Previous</span>
            <span class="docs-page-nav-title"><?= htmlspecialchars($pageLabels[$prev] ?? $prev) ?></span>
        </a>
    <?php else: ?>
        <span></span>
    <?php endif; ?>

    <?php if ($next): ?>
        <a href="<?= $base ?>docs/<?= $next ?>" class="next">
            <span class="docs-page-nav-label">Next <i class="fa fa-arrow-right"></i></span>
            <span class="docs-page-nav-title"><?= htmlspecialchars($pageLabels[$next] ?? $next) ?></span>
        </a>
    <?php endif; ?>
</nav>
<?php endif; ?>
        <?php
    }
}

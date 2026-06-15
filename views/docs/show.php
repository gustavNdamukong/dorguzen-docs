<?php
/**
 * @var string $content     Parsed HTML from the .md file
 * @var string $slug        Current page slug
 * @var string|null $prev   Previous page slug
 * @var string|null $next   Next page slug
 * @var string $base        Root path
 * @var array  $pageLabels  slug → human label map
 */
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

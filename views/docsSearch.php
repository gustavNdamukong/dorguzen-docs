<?php

namespace Dorguzen\Views;

class docsSearch extends \Dorguzen\Core\DGZ_HtmlView
{
    public function show(array $viewModel = []): void
    {
        extract($viewModel);
        ?>
<h1>Search Results</h1>

<?php if ($query === ''): ?>
    <p>Enter a term in the search bar above to search the documentation.</p>

<?php elseif (empty($results)): ?>
    <p>No results found for <strong><?= htmlspecialchars($query) ?></strong>. Try a different term.</p>

<?php else: ?>
    <p><?= count($results) ?> result<?= count($results) !== 1 ? 's' : '' ?> for
       <strong><?= htmlspecialchars($query) ?></strong></p>

    <div class="docs-search-results">
        <?php foreach ($results as $result): ?>
            <div class="docs-search-result">
                <a href="<?= $base ?>docs/<?= $result['slug'] ?>" class="docs-search-result-title">
                    <?= htmlspecialchars($result['title']) ?>
                </a>
                <p class="docs-search-result-excerpt">
                    <?= htmlspecialchars($result['excerpt']) ?>
                </p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
        <?php
    }
}

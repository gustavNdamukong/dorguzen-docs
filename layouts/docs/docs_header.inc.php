<header class="docs-header">
    <div class="docs-header-inner">

        <!-- Left: hamburger + logo -->
        <div class="docs-header-left">
            <button class="docs-sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation">
                <i class="fa fa-bars"></i>
            </button>
            <a href="<?= $this->config->getFileRootPath() ?>docs" class="docs-logo">
                <img src="<?= $this->config->getFileRootPath() ?>assets/images/dorguzen-logo.png" alt="Dorguzen" height="44">
                <span class="docs-logo-wordmark">Dorgu<span>zen</span></span>
            </a>
            <span class="docs-version-badge">v1.1.0</span>
        </div>

        <!-- Centre: search -->
        <div class="docs-header-search">
            <form action="<?= $this->config->getFileRootPath() ?>docs/search" method="get" class="docs-search-form">
                <i class="fa fa-search docs-search-icon"></i>
                <input type="text"
                       name="q"
                       class="docs-search-input"
                       placeholder="Search the docs…"
                       value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES) ?>"
                       autocomplete="off">
                <kbd class="docs-search-hint">⌘K</kbd>
            </form>
        </div>

        <!-- Right: links -->
        <div class="docs-header-right">
            <a href="https://github.com/gustavNdamukong/Dorguzen" target="_blank" rel="noopener" class="docs-header-link" title="GitHub">
                <i class="fab fa-github"></i>
                <span>GitHub</span>
            </a>
        </div>

    </div>
</header>

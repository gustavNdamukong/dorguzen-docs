<?php

namespace Dorguzen\layouts\docs;

class docsLayout extends \Dorguzen\Core\DGZ_Layout {

	public function display() {
		$rootPath = $this->config->getFileRootPath();
	?>
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<?= ($this->getMetadata() != null) ? $this->getMetadata() : '<title>Dorguzen Docs</title>' ?>

		<!-- Fonts -->
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

		<!-- Icons -->
		<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

		<!-- Prism.js syntax highlighting -->
		<link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-okaidia.min.css" rel="stylesheet">

		<!-- Bootstrap -->
		<link href="<?= $rootPath ?>assets/css/bootstrap.min.css" rel="stylesheet">

		<!-- Docs stylesheet -->
		<link href="<?= $rootPath ?>assets/css/docs.css" rel="stylesheet">

		<?= $this->getCssHtml() ?>
	</head>
	<body>

	<?php include('docs_header.inc.php'); ?>

	<div class="docs-wrapper">

		<!-- Sidebar -->
		<aside class="docs-sidebar" id="docsSidebar">
			<?php include('docs_sidebar.inc.php'); ?>
		</aside>

		<!-- Overlay for mobile sidebar -->
		<div class="docs-sidebar-overlay" id="sidebarOverlay"></div>

		<!-- Main content -->
		<main class="docs-main">
			<div class="docs-content">

				<?php if (!empty($this->exceptions)): ?>
					<div class="alert alert-danger"><?= $this->exceptions ?></div>
				<?php endif; ?>
				<?php if (!empty($this->errors)): ?>
					<div class="alert alert-danger"><?= $this->errors ?></div>
				<?php endif; ?>
				<?php if (!empty($this->notices)): ?>
					<div class="alert alert-info"><?= $this->notices ?></div>
				<?php endif; ?>

				<?= $this->content ?>

			</div>
		</main>

	</div>

	<!-- Back to top -->
	<button id="dgz-back-to-top" title="Back to top" aria-label="Back to top">
		<i class="fas fa-chevron-up"></i>
	</button>

	<!-- jQuery -->
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<!-- Bootstrap JS -->
	<script src="<?= $rootPath ?>assets/js/bootstrap.bundle.min.js"></script>
	<!-- Prism.js syntax highlighting + language support -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-bash.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-sql.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-ini.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup.min.js"></script>
	<!-- Docs JS -->
	<script src="<?= $rootPath ?>assets/js/docs.js"></script>

	<?= $this->getJavascriptHtml() ?>

	</body>
	</html>
	<?php
	}
}

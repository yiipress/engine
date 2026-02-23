<?php
/**
 * @var string $title
 * @var bool $hasMermaid
 * @var string $rootPath
 */
$hasMermaid ??= false;
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
<?php if ($hasMermaid): ?>
    <script defer src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
    <script>
        // Initialize Mermaid with current theme
        function getMermaidTheme() {
            return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'default';
        }

        function initializeMermaid() {
            mermaid.initialize({
                startOnLoad: false,
                theme: getMermaidTheme(),
                securityLevel: 'loose'
            });
            mermaid.run({
                querySelector: '.mermaid'
            });
        }

        document.addEventListener('DOMContentLoaded', initializeMermaid);
    </script>
<?php endif; ?>
    <link rel="stylesheet" href="<?= $rootPath ?>assets/theme/style.css">
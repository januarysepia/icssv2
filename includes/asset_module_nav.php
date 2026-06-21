<?php
$asset_current_page = basename($_SERVER['PHP_SELF'] ?? '');
$asset_nav_items = [
    'asset_dashboard.php' => 'Asset Dashboard',
    'asset_catalog.php' => 'Asset Catalog',
    'asset_transactions.php' => 'Borrow / Return',
    'asset_history.php' => 'Asset History',
];
?>
<nav class="asset-module-nav" aria-label="Asset module navigation">
    <?php foreach ($asset_nav_items as $asset_page => $asset_label): ?>
        <a href="<?= h($asset_page) ?>"
           class="<?= $asset_current_page === $asset_page ? 'active' : '' ?>">
            <?= h($asset_label) ?>
        </a>
    <?php endforeach; ?>
</nav>


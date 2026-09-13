<?php
require_once 'includes/config.php';
require_once 'includes/sitemap-generator.php';

$sitemap = new SitemapGenerator($pdo);
$sitemap->generate();
echo "Sitemap generated successfully at " . date('Y-m-d H:i:s');
?>
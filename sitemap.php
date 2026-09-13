<?php
require_once 'includes/config.php';
require_once 'includes/sitemap-generator.php';

header('Content-Type: application/xml');

$sitemap = new SitemapGenerator($pdo);
$xml = $sitemap->generateForBrowser();

echo $xml;
<?php
// Set the content type to plain text
header('Content-Type: text/plain');

// Database connection (if needed for dynamic rules)
require_once 'includes/config.php';

// Define the base URL (you might want to get this dynamically)
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

// Start output
echo "User-agent: *\n";

// Allow crawling of important pages
echo "Allow: /\n";
echo "Allow: /index.php\n";
echo "Allow: /category.php\n";
echo "Allow: /app.php\n";
echo "Allow: /search.php\n";
echo "Allow: /page.php\n";

// Disallow admin area
echo "Disallow: /admin/\n";

// Disallow includes directory
echo "Disallow: /includes/\n";

// Allow crawling of uploads directory but block execution
echo "Allow: /uploads/icons/\n";
echo "Allow: /uploads/apks/\n";
echo "Allow: /uploads/hero/\n";
echo "Allow: /uploads/favicons/\n";
echo "Allow: /uploads/logos/\n";
echo "Disallow: /uploads/*.php\n";

// Disallow form submission URLs
echo "Disallow: /submit_comment.php\n";
echo "Disallow: /submit_rating.php\n";
echo "Disallow: /download.php\n";

// Sitemap URL
echo "\nSitemap: $baseUrl/sitemap.xml\n";
?>
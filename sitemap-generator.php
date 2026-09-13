<?php
require_once 'config.php';

class SitemapGenerator {
    private $pdo;
    private $baseUrl;
    private $sitemapPath;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->baseUrl = SITE_URL;
        $this->sitemapPath = $_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml';
    }
    
    public function generateForBrowser() {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
    
    // Same code as your generate() method but return XML string instead of saving
    $this->addUrl($xml, $this->baseUrl . '/', date('Y-m-d'), 'daily', 1.0);
    $this->addStaticPages($xml);
    $this->addCategories($xml);
    $this->addApps($xml);
    
    return $xml->asXML();
}
    
    public function generate() {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
        
        // Add homepage
        $this->addUrl($xml, $this->baseUrl . '/', date('Y-m-d'), 'daily', 1.0);
        
        // Add static pages
        $this->addStaticPages($xml);
        
        // Add categories
        $this->addCategories($xml);
        
        // Add apps
        $this->addApps($xml);
        
        // Save the sitemap
        $xml->asXML($this->sitemapPath);
        
        // Update robots.txt
        $this->updateRobotsTxt();
        
        return true;
    }
    
    private function addUrl($xml, $loc, $lastmod, $changefreq) {
        $url = $xml->addChild('url');
        $url->addChild('loc', htmlspecialchars($loc));
        $url->addChild('lastmod', $lastmod);
        $url->addChild('changefreq', $changefreq);
    }
    
    private function addStaticPages($xml) {
        foreach ($pages as $page => $freq) {
            $this->addUrl($xml, $this->baseUrl . '/' . $page, date('Y-m-d'), $freq, 0.8);
        }
        
        // Add custom pages from database
        $stmt = $this->pdo->query("SELECT * FROM pages");
        while ($page = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $url = $this->baseUrl . '/page/' . $page['slug'];
            $lastmod = date('Y-m-d', strtotime($page['updated_at']));
            $this->addUrl($xml, $url, $lastmod, 'monthly', 0.7);
        }
    }
    
private function addCategories($xml) {
    $categories = $this->pdo->query("SELECT * FROM categories ORDER BY name ASC");
    
    while ($category = $categories->fetch(PDO::FETCH_ASSOC)) {
        // Convert "games-racing" format to "games/racing"
        if (strpos($category['slug'], '-') !== false) {
            $slugParts = explode('-', $category['slug']);
            $cleanUrl = $this->baseUrl . '/category/' . implode('/', $slugParts);
        } else {
            $cleanUrl = $this->baseUrl . '/category/' . $category['slug'];
        }
        
        $this->addUrl($xml, $cleanUrl, date('Y-m-d'), 'weekly', 0.9);
    }
}
    
    private function addApps($xml) {
        $apps = $this->pdo->query("SELECT * FROM apps ORDER BY updated_at DESC");
        
        while ($app = $apps->fetch(PDO::FETCH_ASSOC)) {
            $url = $this->baseUrl . '/' . $app['slug'];
            $lastmod = date('Y-m-d', strtotime($app['updated_at']));
            $this->addUrl($xml, $url, $lastmod, 'weekly', 0.8);
        }
    }
    
    private function updateRobotsTxt() {
        $robotsPath = $_SERVER['DOCUMENT_ROOT'] . '/robots.txt';
        $sitemapLine = "Sitemap: " . $this->baseUrl . "/sitemap.xml\n";
        
        if (file_exists($robotsPath)) {
            $robotsContent = file_get_contents($robotsPath);
            
            // Remove existing sitemap line if it exists
            $robotsContent = preg_replace('/^Sitemap\:.*$/m', '', $robotsContent);
            
            // Add new sitemap line
            file_put_contents($robotsPath, trim($robotsContent) . "\n\n" . $sitemapLine);
        } else {
            // Create new robots.txt with sitemap
            file_put_contents($robotsPath, $sitemapLine);
        }
    }
}
?>
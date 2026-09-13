<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public/footer.css">

<?php
// Get footer data from database
$brandName = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_brand_name'")->fetchColumn();
$brandDescription = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_brand_description'")->fetchColumn();
$brandUrl = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_brand_url'")->fetchColumn();
$brandLogo = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_brand_logo'")->fetchColumn();
$copyrightText = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_copyright'")->fetchColumn();

// Get footer links grouped by section
$footerLinks = $pdo->query("SELECT * FROM footer_links ORDER BY section_name, id")->fetchAll(PDO::FETCH_ASSOC);
$linksBySection = [];
foreach ($footerLinks as $link) {
    $section = $link['section_name'];
    if (!isset($linksBySection[$section])) {
        $linksBySection[$section] = [
            'title' => $link['section_title'],
            'links' => []
        ];
    }
    $linksBySection[$section]['links'][] = [
        'text' => $link['link_text'],
        'url' => $link['link_url']
    ];
}

// Get social links
$socialLinks = $pdo->query("SELECT * FROM footer_social ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Output footer code from database
$footerCode = $pdo->query("SELECT footer_code FROM header_footer WHERE id = 1")->fetchColumn();
if ($footerCode) {
    echo htmlspecialchars_decode($footerCode);
}
?>

<!-- New ad container before footer -->
<div class="footer-ad-container">
<?php echo displayAd('footer', 'index'); ?>
</div>

<footer class="footer-container">
    <div class="footer-content">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="<?= htmlspecialchars($brandUrl) ?>" class="brand-link">
                    <?php if ($brandLogo): ?>
                        <img src="<?= SITE_URL ?>/uploads/logos/<?= htmlspecialchars($brandLogo) ?>" class="brand-logo" alt="<?= htmlspecialchars($brandName) ?> Logo">
                    <?php endif; ?>

                    <span class="brand-name"><?= htmlspecialchars($brandName) ?></span>
                </a>
                <p class="brand-description"><?= htmlspecialchars($brandDescription) ?></p>
            </div>
            
            <?php if (!empty($linksBySection)): ?>
                <div class="footer-links">
                    <?php foreach ($linksBySection as $sectionName => $section): ?>
                        <div class="links-section">
                            <h2 class="links-title"><?= htmlspecialchars($section['title']) ?></h2>
                            <ul class="links-list">
                                <?php foreach ($section['links'] as $link): ?>
                                    <li class="links-item">
                                        <a href="<?= htmlspecialchars($link['url']) ?>" class="link"><?= htmlspecialchars($link['text']) ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <hr class="footer-divider">
        
        <?php if (!empty($socialLinks)): ?>
            <div class="social-container">
                <div class="social-links">
                    <?php foreach ($socialLinks as $social): ?>
                        <a href="<?= htmlspecialchars($social['url']) ?>" target="_blank" class="social-icon">
                            <i class="fab fa-<?= strtolower($social['platform']) ?>"></i>
                            <span class="sr-only"><?= htmlspecialchars($social['platform']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($copyrightText): ?>
            <div class="copyright">
                <span><?= htmlspecialchars($copyrightText) ?></span>
            </div>
        <?php endif; ?>
    </div>
</footer>

<?php
// Output body end code from database
$bodyEndCode = $pdo->query("SELECT body_end_code FROM header_footer WHERE id = 1")->fetchColumn();
if ($bodyEndCode) {
    echo htmlspecialchars_decode($bodyEndCode);
}
?>

</body>
</html>
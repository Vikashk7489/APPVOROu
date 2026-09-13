<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Function to validate different URL types
function isValidUrl($url) {
    // Check for standard URLs
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return true;
    }
    
    // Check for relative paths
    if (preg_match('/^\/[a-zA-Z0-9\-_\/]*$/', $url)) {
        return true;
    }
    
    // Check for anchors
    if (preg_match('/^#[a-zA-Z0-9\-_]+$/', $url)) {
        return true;
    }
    
    // Check for mailto links
    if (preg_match('/^mailto:[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $url)) {
        return true;
    }
    
    // Check for tel links
    if (preg_match('/^tel:\+?[0-9\s\-\(\)]+$/', $url)) {
        return true;
    }
    
    // Check for root path
    if ($url === '/') {
        return true;
    }
    
    return false;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    // Handle brand info update
    if (isset($_POST['update_brand'])) {
        $brandName = trim($_POST['brand_name']);
        $brandDescription = trim($_POST['brand_description']);
        $brandUrl = trim($_POST['brand_url']);
        
        // Validate brand URL
        if (!isValidUrl($brandUrl)) {
            $_SESSION['error_message'] = 'Invalid brand URL format';
            header("Location: manage_footer.php");
            exit();
        }
        
        // Brand name is only required if there's no logo (existing or newly uploaded)
        $existingLogo = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_brand_logo'")->fetchColumn();
        $hasNewLogo = !empty($_FILES['brand_logo']['name']);
        if ($brandName === '' && !$existingLogo && !$hasNewLogo) {
            $_SESSION['error_message'] = 'Please enter a Brand Name or upload a Brand Logo';
            header("Location: manage_footer.php");
            exit();
        }
        
        // Update brand info
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES 
            ('footer_brand_name', ?),
            ('footer_brand_description', ?),
            ('footer_brand_url', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$brandName, $brandDescription, $brandUrl]);
        
        // Handle brand logo upload
        if (!empty($_FILES['brand_logo']['name'])) {
            $upload = uploadFile($_FILES['brand_logo'], '../uploads/logos/', ['image/jpeg', 'image/png', 'image/svg+xml', 'image/gif', 'image/webp']);
            
            if ($upload['success']) {
                // Delete old logo if exists
                $oldLogo = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_brand_logo'")->fetchColumn();
                if ($oldLogo && file_exists('../uploads/logos/' . $oldLogo)) {
                    unlink('../uploads/logos/' . $oldLogo);
                }
                
                // Update new logo
                $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('footer_brand_logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$upload['filename'], $upload['filename']]);
            }
        }
        
        $_SESSION['success_message'] = 'Brand information updated successfully';
    }
    
    // Handle brand logo removal
    if (isset($_POST['remove_logo'])) {
        $oldLogo = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_brand_logo'")->fetchColumn();
        if ($oldLogo && file_exists('../uploads/logos/' . $oldLogo)) {
            unlink('../uploads/logos/' . $oldLogo);
        }
        
        $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = NULL WHERE setting_key = 'footer_brand_logo'");
        $stmt->execute();
        
        $_SESSION['success_message'] = 'Logo removed successfully';
    }
    
    // Handle footer links update
    if (isset($_POST['update_footer_links'])) {
        // First delete all existing footer links
        $pdo->query("DELETE FROM footer_links");
        
        // Only insert new links if they exist in the POST data
        if (isset($_POST['links'])) {
            $stmt = $pdo->prepare("INSERT INTO footer_links (section_title, link_text, link_url, section_name) VALUES (?, ?, ?, ?)");
            
            foreach ($_POST['links'] as $sectionName => $sectionData) {
                $sectionTitle = trim($sectionData['section_title']);
                
                if (!empty($sectionData['links'])) {
                    foreach ($sectionData['links'] as $link) {
                        if (!empty($link['link_text']) && !empty($link['link_url'])) {
                            $linkUrl = trim($link['link_url']);
                            
                            // Validate URL format
                            if (!isValidUrl($linkUrl)) {
                                $_SESSION['error_message'] = 'Invalid URL format: ' . htmlspecialchars($linkUrl);
                                header("Location: manage_footer.php");
                                exit();
                            }
                            
                            $stmt->execute([
                                $sectionTitle,
                                trim($link['link_text']),
                                $linkUrl,
                                $sectionName
                            ]);
                        }
                    }
                }
            }
        }
        
        $_SESSION['success_message'] = 'Footer links updated successfully';
        header("Location: manage_footer.php");
        exit();
    }
    
    // Handle social link addition
    if (isset($_POST['add_social'])) {
        $socialPlatform = trim($_POST['social_platform']);
        $socialUrl = trim($_POST['social_url']);
        
        // Validate social URL
        if (!isValidUrl($socialUrl)) {
            $_SESSION['error_message'] = 'Invalid social URL format';
            header("Location: manage_footer.php");
            exit();
        }
        
        $stmt = $pdo->prepare("INSERT INTO footer_social (platform, url) VALUES (?, ?)");
        $stmt->execute([$socialPlatform, $socialUrl]);
        
        $_SESSION['success_message'] = 'Social link added successfully';
    }
    
    // Handle social link deletion
    if (isset($_POST['delete_social'])) {
        $socialId = (int)$_POST['social_id'];
        
        $stmt = $pdo->prepare("DELETE FROM footer_social WHERE id = ?");
        $stmt->execute([$socialId]);
        
        $_SESSION['success_message'] = 'Social link deleted successfully';
    }
    
    // Handle copyright update
    if (isset($_POST['update_copyright'])) {
        $copyrightText = trim($_POST['copyright_text']);
        
        // Update database
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('footer_copyright', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$copyrightText, $copyrightText]);
        
        // Also update homepage_settings.json
        $settingsFile = '../data/homepage_settings.json';
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            $settings['footer']['content']['copyright'] = $copyrightText;
            file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
        }
        
        $_SESSION['success_message'] = 'Copyright text updated successfully';
    }
    
    header("Location: manage_footer.php");
    exit();
}

// Get current footer settings
$brandName = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_brand_name'")->fetchColumn();
$brandDescription = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_brand_description'")->fetchColumn();
$brandUrl = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_brand_url'")->fetchColumn();
$brandLogo = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_brand_logo'")->fetchColumn();
$copyrightText = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'footer_copyright'")->fetchColumn();

$socialLinks = $pdo->query("SELECT * FROM footer_social ORDER BY platform")->fetchAll(PDO::FETCH_ASSOC);

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

// Social platform options
$socialPlatforms = [
    'Facebook' => 'fa-facebook-f',
    'Twitter' => 'fa-twitter',
    'YouTube' => 'fa-youtube',
    'Instagram' => 'fa-instagram',
    'Telegram' => 'fa-telegram-plane',
    'Pinterest' => 'fa-pinterest-p',
    'LinkedIn' => 'fa-linkedin-in',
    'TikTok' => 'fa-tiktok',
    'Reddit' => 'fa-reddit-alien',
    'Discord' => 'fa-discord'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Footer - AppCenter Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/manage_footer.css">
</head>
<body>
    <div class="admin-container">
        <div class="overlay"></div>
        
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Admin Panel</h2>
                <button class="close-sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <nav class="sidebar-menu">
                <a href="index.php" class="menu-item"><span>🏠</span> Dashboard</a>
                <a href="all_apps.php" class="menu-item"><span>📱</span> All Apps</a>
                <a href="add_app.php" class="menu-item"><span>➕</span> Add New App</a>
                <a href="manage_categories.php" class="menu-item"><span>🗃️</span> Manage Categories</a>
                <a href="control_panel.php" class="menu-item"><span>⚙️</span> Homepage Control</a>
                <a href="ads.php" class="menu-item"><span>📢</span> Manage Ads</a>
                <a href="header-footer.php" class="menu-item"><span>📝</span> Header/Footer Code</a>
                <a href="manage_header.php" class="menu-item"><span>📰</span> Manage Header</a>
                <a href="manage_footer.php" class="menu-item active"><span>🖥️</span> Manage Footer</a>
                <a href="page.php" class="menu-item"><span>📄</span> Manage Pages</a>
                <a href="blog.php" class="menu-item"><span>✍️</span> Manage Blog</a>
                <a href="faq.php" class="menu-item"><span>❓</span> Manage FAQ</a>
                <a href="seo.php" class="menu-item"><span>🔍</span> SEO Settings</a>
                <a href="statistics.php" class="menu-item"><span>📉</span> Statistics</a>
                <a href="privacy.php" class="menu-item"><span>🔒</span> Privacy & Security</a>
                <a href="optimize.php" class="menu-item"><span>⚡</span> Image Optimizer</a>
                <a href="export_import.php" class="menu-item"><span>🔁</span> Export / Import</a>
                <a href="logout.php" class="menu-item logout"><span>🔚</span> Logout</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="header">
                <div style="display: flex; align-items: center;">
                    <button class="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                <h1>Manage Footer</h1>
            </div>
                <div class="web-button">
                    <a href="<?= SITE_URL ?>" target="_blank" class="web-badge">
                    <div class="web-icon">
                        <i class="fa-solid fa-globe"></i>
                    </div>
                    <span class="web-role">Visit Website</span>
                    </a>
                </div>
            </div>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $_SESSION['error_message'] ?>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $_SESSION['success_message'] ?>
                    <?php unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Brand Information</h2>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="brand_name">Brand Name</label>
                        <input type="text" id="brand_name" name="brand_name" class="form-control" value="<?= htmlspecialchars($brandName) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="brand_description">Brand Description</label>
                        <textarea id="brand_description" name="brand_description" class="form-control" required><?= htmlspecialchars($brandDescription) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="brand_url">Brand URL</label>
                        <input type="text" id="brand_url" name="brand_url" class="form-control" value="<?= htmlspecialchars($brandUrl) ?>" required>
                        <small class="text-muted">Can be full URL (https://...), relative path (/category/apps), mailto (mailto:contact@example.com), tel (tel:+123456789), or anchor (#section)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="brand_logo">Brand Logo (optional)</label>
                        <input type="file" id="brand_logo" name="brand_logo" class="form-control" accept="image/*">
                        <?php if ($brandLogo): ?>
                            <img src="../uploads/logos/<?= htmlspecialchars($brandLogo) ?>" alt="Current Logo" class="logo-preview">
                            <p><small>Current logo: <?= htmlspecialchars($brandLogo) ?></small></p>
                            <button type="submit" name="remove_logo" class="btn btn-danger btn-sm" style="margin-top: 10px;"><i class="fa-solid fa-trash"></i> Remove Logo</button>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" name="update_brand" class="btn"><i class="fas fa-save"></i> Update Brand Info</button>
                </form>
            </div>
            
            <div class="card">
                <h2>Footer Sections & Links</h2>
                
                <form method="POST" id="footer-links-form">
                    <?= csrf_field() ?>
                    <div class="footer-links-container" id="footer-links-container">
                        <?php if (!empty($linksBySection)): ?>
                            <?php foreach ($linksBySection as $sectionName => $section): ?>
                                <div class="footer-link-section" data-section="<?= htmlspecialchars($sectionName) ?>">
                                    <div class="form-group">
                                        <label>Section Title</label>
                                        <input type="text" name="links[<?= htmlspecialchars($sectionName) ?>][section_title]" 
                                               value="<?= htmlspecialchars($section['title']) ?>" class="form-control" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Links</label>
                                        <div class="links-list">
                                            <?php foreach ($section['links'] as $index => $link): ?>
                                                <div class="link-item-form">
                                                    <input type="text" name="links[<?= htmlspecialchars($sectionName) ?>][links][<?= $index ?>][link_text]" 
                                                           value="<?= htmlspecialchars($link['text']) ?>" placeholder="Link Text" class="form-control" required>
                                                    <input type="text" name="links[<?= htmlspecialchars($sectionName) ?>][links][<?= $index ?>][link_url]" 
                                                           value="<?= htmlspecialchars($link['url']) ?>" placeholder="URL (https://, /path, mailto:, tel:, or #anchor)" class="form-control" required>
                                                    <button type="button" class="btn btn-sm btn-danger remove-link">×</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="btn btn-sm add-link" data-section="<?= htmlspecialchars($sectionName) ?>">+ Add Link</button>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-danger remove-section"><i class="fa-solid fa-trash"></i> Remove Section</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state" id="empty-state" style="display: none;">
                                <p>No footer sections added yet. Click "Add New Section" to create one.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" id="add-section" class="btn">+ Add New Section</button>
                    <button type="submit" name="update_footer_links" class="btn"><i class="fas fa-save"></i> Save Footer Links</button>
                </form>
            </div>
            
            <div class="card">
                <h2>Social Media Links</h2>
                
                <form method="POST" class="form-group">
                    <?= csrf_field() ?>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <select name="social_platform" class="select-control" style="flex: 1; min-width: 200px;" required>
                            <option value="">Select Platform</option>
                            <?php foreach ($socialPlatforms as $platform => $icon): ?>
                                <option value="<?= $platform ?>"><?= $platform ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="social_url" class="form-control" placeholder="Profile URL" style="flex: 2;" required>
                        <button type="submit" name="add_social" class="btn"><i class="fa-solid fa-plus"></i> Add Social Link</button>
                    </div>
                </form>
                
                <?php if (empty($socialLinks)): ?>
                    <p>No social links added yet.</p>
                <?php else: ?>
                    <div class="social-links-container">
                        <?php foreach ($socialLinks as $social): ?>
                            <div class="social-link-item">
                                <i class="fab <?= $socialPlatforms[$social['platform']] ?? 'fa-link' ?> social-icon"></i>
                                <span class="social-platform"><?= htmlspecialchars($social['platform']) ?></span>
                                <form method="POST" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="social_id" value="<?= $social['id'] ?>">
                                    <button type="submit" name="delete_social" class="btn btn-sm btn-danger" style="padding: 2px 6px;">×</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>Copyright Information</h2>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="copyright_text">Copyright Text</label>
                        <input type="text" id="copyright_text" name="copyright_text" class="form-control" value="<?= htmlspecialchars($copyrightText) ?>" required>
                    </div>
                    <button type="submit" name="update_copyright" class="btn"><i class="fas fa-save"></i> Update Copyright</button>
                </form>
            </div>
        </main>
    </div>
    
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('footer-links-container');
    const emptyState = document.getElementById('empty-state');
    
    // Show empty state if no sections exist
    function checkEmptyState() {
        const sections = container.querySelectorAll('.footer-link-section');
        if (sections.length === 0) {
            if (emptyState) emptyState.style.display = 'block';
        } else {
            if (emptyState) emptyState.style.display = 'none';
        }
    }
    
    // Initialize empty state
    checkEmptyState();
    
    // Use event delegation for remove-link buttons
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-link')) {
            e.target.closest('.link-item-form').remove();
        }
        
        if (e.target && e.target.classList.contains('remove-section')) {
            const sections = container.querySelectorAll('.footer-link-section');
            if (sections.length === 1) {
                if (!confirm('This is the last section. Remove it?')) {
                    return;
                }
            }
            e.target.closest('.footer-link-section').remove();
            checkEmptyState();
        }
    });

    // Add new link to a section
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('add-link')) {
            const section = e.target.getAttribute('data-section');
            const linksList = e.target.previousElementSibling;
            const index = linksList.querySelectorAll('.link-item-form').length;
            
            const div = document.createElement('div');
            div.className = 'link-item-form';
            
            div.innerHTML = `
                <input type="text" name="links[${section}][links][${index}][link_text]" 
                       placeholder="Link Text" class="form-control" required>
                <input type="text" name="links[${section}][links][${index}][link_url]" 
                       placeholder="URL (https://, /path, mailto:, tel:, or #anchor)" class="form-control" required>
                <button type="button" class="btn btn-sm btn-danger remove-link">×</button>
            `;
            
            linksList.appendChild(div);
        }
    });
    
    // Add new section
    document.getElementById('add-section').addEventListener('click', function() {
        const sectionId = 'section' + Date.now();
        
        const section = document.createElement('div');
        section.className = 'footer-link-section';
        section.setAttribute('data-section', sectionId);
        section.innerHTML = `
            <div class="form-group">
                <label>Section Title</label>
                <input type="text" name="links[${sectionId}][section_title]" 
                       value="New Section" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Links</label>
                <div class="links-list">
                    <div class="link-item-form">
                        <input type="text" name="links[${sectionId}][links][0][link_text]" 
                               placeholder="Link Text" class="form-control" required>
                        <input type="text" name="links[${sectionId}][links][0][link_url]" 
                               placeholder="URL (https://, /path, mailto:, tel:, or #anchor)" class="form-control" required>
                        <button type="button" class="btn btn-sm btn-danger remove-link">×</button>
                    </div>
                </div>
                <button type="button" class="btn btn-sm add-link" data-section="${sectionId}">+ Add Link</button>
            </div>
            <button type="button" class="btn btn-sm btn-danger remove-section">Remove Section</button>
        `;
        
        container.appendChild(section);
        checkEmptyState();
    });
    
    // Handle form submission to ensure it works when all sections are removed
    document.getElementById('footer-links-form').addEventListener('submit', function(e) {
        // No need to modify the form data - our PHP code will handle empty sections
    });
});
    </script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.querySelector('.menu-toggle');
        const closeSidebar = document.querySelector('.close-sidebar');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        
        menuToggle.addEventListener('click', function() {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
        
        closeSidebar.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
        

        // Close sidebar when clicking on a menu item (for mobile)
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
    });
    
    setTimeout(()=>document.querySelectorAll('.alert').forEach(e=>e.classList.add('hide')),3000)
</script>
</body>
</html>
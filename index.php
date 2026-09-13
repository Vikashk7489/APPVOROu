<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get stats
$totalApps = $pdo->query("SELECT COUNT(*) as count FROM apps")->fetch()['count'];
$totalDownloads = $pdo->query("SELECT SUM(downloads) as total FROM apps")->fetch()['total'];
$totalCategories = $pdo->query("SELECT COUNT(*) as count FROM categories")->fetch()['count'];

// Get recent apps
$recentApps = $pdo->query("SELECT apps.*, categories.name as category_name FROM apps LEFT JOIN categories ON apps.category_id = categories.id ORDER BY apps.created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AppCenter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/index.css">
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
                <a href="index.php" class="menu-item active"><span>🏠</span> Dashboard</a>
                <a href="all_apps.php" class="menu-item"><span>📱</span> All Apps</a>
                <a href="add_app.php" class="menu-item"><span>➕</span> Add New App</a>
                <a href="manage_categories.php" class="menu-item"><span>🗃️</span> Manage Categories</a>
                <a href="control_panel.php" class="menu-item"><span>⚙️</span> Homepage Control</a>
                <a href="ads.php" class="menu-item"><span>📢</span> Manage Ads</a>
                <a href="header-footer.php" class="menu-item"><span>📝</span> Header/Footer Code</a>
                <a href="manage_header.php" class="menu-item"><span>📰</span> Manage Header</a>
                <a href="manage_footer.php" class="menu-item"><span>🖥️</span> Manage Footer</a>
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
                    <h1>Dashboard</h1>
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
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Apps</h3>
                    <div class="stat-value">
                        <p><?= $totalApps ?></p>
                        <div class="stat-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Total Downloads</h3>
                    <div class="stat-value">
                        <p><?= $totalDownloads ? number_format($totalDownloads) : '0' ?></p>
                        <div class="stat-icon">
                            <i class="fas fa-download"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Categories</h3>
                    <div class="stat-value">
                        <p><?= $totalCategories ?></p>
                        <div class="stat-icon">
                            <i class="fas fa-folder"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="recent-apps-container">
                <div class="section-header">
                    <h2 class="section-title">Recently Added Apps</h2>
                    <a href="add_app.php" class="btn">
                        <i class="fas fa-plus"></i> Add New App
                    </a>
                </div>
                
                <?php if (count($recentApps) > 0): ?>
                    <div class="apps-grid">
                        <?php foreach ($recentApps as $app): ?>
                            <div class="app-card">
                                <div class="app-card-header">
                                    <div class="app-icon-container">
                                        <?php if (!empty($app['icon'])): ?>
                                            <img src="<?= SITE_URL ?>/uploads/icons/<?= htmlspecialchars($app['icon']) ?>" alt="App Icon" class="app-icon">
                                        <?php else: ?>
                                            <i class="fas fa-mobile-alt" style="color: #999;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="app-info">
                                        <div class="app-name"><?= htmlspecialchars($app['name']) ?></div>
                                        <span class="app-category"><?= htmlspecialchars($app['category_name']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="app-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Version</span>
                                        <span class="detail-value"><?= htmlspecialchars($app['version']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Size</span>
                                        <span class="detail-value"><?= !empty($app['size']) ? htmlspecialchars($app['size']) . ' MB' : 'N/A' ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Updated</span>
                                        <span class="detail-value"><?= date('M d, Y', strtotime($app['updated_at'])) ?></span>
                                    </div>
                                </div>
                                
                                <div class="app-footer">
                                    <div class="download-count">
                                        <i class="fas fa-download"></i>
                                        <span><?= $app['downloads'] ?> downloads</span>
                                    </div>
                                    <div class="app-date">
                                        Added <?= date('M d, Y', strtotime($app['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-apps">
                        <p>No apps found. Add your first app!</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

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
    </script>
</body>
</html>
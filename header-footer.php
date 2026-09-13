<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $headStartCode = $_POST['head_start_code'] ?? '';
    $headerCode = $_POST['header_code'] ?? '';
    $footerCode = $_POST['footer_code'] ?? '';
    $bodyStartCode = $_POST['body_start_code'] ?? '';
    $bodyEndCode = $_POST['body_end_code'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE header_footer SET 
        head_start_code = ?,
        header_code = ?, 
        footer_code = ?, 
        body_start_code = ?, 
        body_end_code = ? 
        WHERE id = 1");
    
    $stmt->execute([$headStartCode, $headerCode, $footerCode, $bodyStartCode, $bodyEndCode]);
    
    $_SESSION['success_message'] = "Header/Footer codes updated successfully!";
    header("Location: header-footer.php");
    exit();
}

// Get current codes
$codes = $pdo->query("SELECT * FROM header_footer WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Header/Footer - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/header-footer.css">
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
                <a href="header-footer.php" class="menu-item active"><span>📝</span> Header/Footer Code</a>
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
                    <h1>Header & Footer</h1>
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
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $_SESSION['success_message'] ?>
                    <?php unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
<form method="POST">
    <?= csrf_field() ?>
    <div class="card">
        <h2>After &lt;head&gt; Code</h2>
        <div class="form-group">
            <textarea id="head_start_code" name="head_start_code" class="form-control"><?= htmlspecialchars($codes['head_start_code'] ?? '') ?></textarea>
            <p class="code-description">This code will be inserted immediately after the opening &lt;head&gt; tag.</p>
        </div>
    </div>
    
    <div class="card">
        <h2>Before &lt;/head&gt; Code</h2>
        <div class="form-group">
            <textarea id="header_code" name="header_code" class="form-control"><?= htmlspecialchars($codes['header_code'] ?? '') ?></textarea>
            <p class="code-description">This code will be inserted just before the closing &lt;/head&gt; tag.</p>
        </div>
    </div>
    
    <div class="card">
        <h2>Opening &lt;body&gt; Tag</h2>
        <div class="form-group">
            <textarea id="body_start_code" name="body_start_code" class="form-control"><?= htmlspecialchars($codes['body_start_code'] ?? '') ?></textarea>
            <p class="code-description">This code will be inserted right after the opening &lt;body&gt; tag.</p>
        </div>
    </div>
    
    <div class="card">
        <h2>Closing &lt;/body&gt; Tag</h2>
        <div class="form-group">
            <textarea id="body_end_code" name="body_end_code" class="form-control"><?= htmlspecialchars($codes['body_end_code'] ?? '') ?></textarea>
            <p class="code-description">This code will be inserted just before the closing &lt;/body&gt; tag.</p>
        </div>
    </div>
    
    <div class="card">
        <h2>Footer &lt;/footer&gt; Code</h2>
        <div class="form-group">
            <textarea id="footer_code" name="footer_code" class="form-control"><?= htmlspecialchars($codes['footer_code'] ?? '') ?></textarea>
            <p class="code-description">This code will be inserted just before the closing &lt;/footer&gt; tag.</p>
        </div>
    </div>
    
    <button type="submit" class="btn"><i class="fas fa-save"></i> Save Changes</button>
</form>
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
        
        setTimeout(()=>document.querySelectorAll('.alert').forEach(e=>e.classList.add('hide')),3000)
    </script>
</body>
</html>
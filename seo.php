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
    try {
        $siteTitle = $_POST['site_title'] ?? '';
        $metaDescription = $_POST['meta_description'] ?? '';
        $metaKeywords = $_POST['meta_keywords'] ?? '';
        
        // Handle favicon upload
        $favicon = null;
        if (isset($_FILES['favicon'])) {
            $file = $_FILES['favicon'];
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                // Check file size (max 1MB)
                if ($file['size'] > 1048576) {
                    throw new Exception("Favicon must be less than 1MB.");
                }

                // Routed through uploadFile() for real-content validation,
                // an extension whitelist, and a randomized filename —
                // the original filename/extension is never trusted.
                $faviconUpload = uploadFile($file, '../uploads/favicons/', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon']);

                if (!$faviconUpload['success']) {
                    throw new Exception($faviconUpload['message']);
                }

                $favicon = $faviconUpload['filename'];

                // Delete old favicon if exists
                $oldFavicon = $pdo->query("SELECT favicon FROM seo_settings WHERE id = 1")->fetchColumn();
                if ($oldFavicon && file_exists('../uploads/favicons/' . $oldFavicon)) {
                    unlink('../uploads/favicons/' . $oldFavicon);
                }
            }
        }
        
        // Update database
        if ($favicon) {
            $stmt = $pdo->prepare("UPDATE seo_settings SET site_title = ?, meta_description = ?, meta_keywords = ?, favicon = ? WHERE id = 1");
            $stmt->execute([$siteTitle, $metaDescription, $metaKeywords, $favicon]);
        } else {
            $stmt = $pdo->prepare("UPDATE seo_settings SET site_title = ?, meta_description = ?, meta_keywords = ? WHERE id = 1");
            $stmt->execute([$siteTitle, $metaDescription, $metaKeywords]);
        }
        
        $successMessage = "SEO settings updated successfully!";
    } catch (Exception $e) {
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Get current SEO settings
$seoData = $pdo->query("SELECT * FROM seo_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Settings - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/seo.css">
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
                <a href="manage_footer.php" class="menu-item"><span>🖥️</span> Manage Footer</a>
                <a href="page.php" class="menu-item"><span>📄</span> Manage Pages</a>
                <a href="blog.php" class="menu-item"><span>✍️</span> Manage Blog</a>
                <a href="faq.php" class="menu-item"><span>❓</span> Manage FAQ</a>
                <a href="seo.php" class="menu-item active"><span>🔍</span> SEO Settings</a>
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
                    <h1>SEO</h1>
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
            
            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($successMessage) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>
            
            <div class="form-container">
                <form action="seo.php" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="site_title">Website Title</label>
                        <input type="text" id="site_title" name="site_title" class="form-control" 
                               value="<?= htmlspecialchars($seoData['site_title'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="meta_description">Meta Description</label>
                        <textarea id="meta_description" name="meta_description" class="form-control" 
                                  required><?= htmlspecialchars($seoData['meta_description'] ?? '') ?></textarea>
                        <small>This appears in search results below your page title. Recommended length: 150-160 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="meta_keywords">Meta Keywords (comma separated)</label>
                        <textarea id="meta_keywords" name="meta_keywords" class="form-control"><?= htmlspecialchars($seoData['meta_keywords'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Favicon</label>
                        <div class="file-input-container">
                            <label for="favicon" class="file-input-label">
                                <i class="fas fa-upload"></i> Choose Favicon File
                            </label>
                            <input type="file" id="favicon" name="favicon" class="file-input" accept="image/x-icon,image/png,image/jpeg,image/webp">
                        </div>
                        <small>Recommended size: 32x32 or 64x64 pixels (PNG, JPG, WebP, or ICO format)</small>
                        
                        <?php if (!empty($seoData['favicon'])): ?>
                            <div class="favicon-preview">
                                <img src="<?= SITE_URL ?>/uploads/favicons/<?= htmlspecialchars($seoData['favicon']) ?>" alt="Current Favicon">
                                <span>Current favicon</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-block"><i class="fas fa-save"></i> Save Changes</button>
                </form>
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
            
            // File input display
            const fileInput = document.getElementById('favicon');
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0]?.name || 'No file chosen';
                    const label = document.querySelector('.file-input-label');
                    label.innerHTML = `<i class="fas fa-upload"></i> ${fileName}`;
                    
                    // Show preview if image
                    if (e.target.files[0] && e.target.files[0].type.match('image.*')) {
                        const previewContainer = document.querySelector('.favicon-preview');
                        if (!previewContainer) {
                            const newPreview = document.createElement('div');
                            newPreview.className = 'favicon-preview';
                            newPreview.innerHTML = '<img id="favicon-preview-img" src="#" alt="Preview"><span>New favicon preview</span>';
                            fileInput.parentNode.parentNode.appendChild(newPreview);
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            document.getElementById('favicon-preview-img').src = event.target.result;
                        }
                        reader.readAsDataURL(e.target.files[0]);
                    }
                });
            }
        });
        
        setTimeout(()=>document.querySelectorAll('.alert').forEach(e=>e.classList.add('hide')),3000)
    </script>
</body>
</html>
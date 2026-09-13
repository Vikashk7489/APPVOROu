<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Handle delete action
if (isset($_GET['delete'])) {
    $app_id = (int)$_GET['delete'];
    
    // Get app details before deleting
    $stmt = $pdo->prepare("SELECT icon, apk_file FROM apps WHERE id = ?");
    $stmt->execute([$app_id]);
    $app = $stmt->fetch();
    
    try {
        $pdo->beginTransaction();
        
        // Delete related rows first to satisfy foreign key constraints
        $stmt = $pdo->prepare("DELETE FROM download_stats WHERE app_id = ?");
        $stmt->execute([$app_id]);
        
        $stmt = $pdo->prepare("DELETE FROM comments WHERE app_id = ?");
        $stmt->execute([$app_id]);
        
        $stmt = $pdo->prepare("DELETE FROM ratings WHERE app_id = ?");
        $stmt->execute([$app_id]);
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM apps WHERE id = ?");
        $stmt->execute([$app_id]);
        
        if ($stmt->rowCount() > 0) {
            // Delete icon file if exists
            if (!empty($app['icon'])) {
                $iconPath = '../uploads/icons/' . $app['icon'];
                if (file_exists($iconPath)) {
                    unlink($iconPath);
                }
            }
            
            // Delete APK file if exists
            if (!empty($app['apk_file'])) {
                $apkPath = '../uploads/apks/' . $app['apk_file'];
                if (file_exists($apkPath)) {
                    unlink($apkPath);
                }
            }
            
            $_SESSION['success_message'] = "App deleted successfully!";
            $pdo->commit();
        } else {
            $_SESSION['error_message'] = "App not found or already deleted.";
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting app: " . $e->getMessage();
    }
    
    header("Location: all_apps.php");
    exit();
}

// Display messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Search functionality
$search = $_GET['search'] ?? '';
$whereClause = '';
$params = [];

if (!empty($search)) {
    $whereClause = " WHERE apps.name LIKE :search OR apps.description LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Count total apps
$countQuery = "SELECT COUNT(*) as count FROM apps" . $whereClause;
$stmt = $pdo->prepare($countQuery);
foreach ($params as $key => &$val) {
    $stmt->bindParam($key, $val);
}
$stmt->execute();
$totalApps = $stmt->fetch()['count'];
$totalPages = max(1, ceil($totalApps / $perPage));

// Get apps data
$query = "SELECT apps.*, categories.name as category_name FROM apps 
          LEFT JOIN categories ON apps.category_id = categories.id" 
          . $whereClause . 
          " ORDER BY apps.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($query);
foreach ($params as $key => &$val) {
    $stmt->bindParam($key, $val);
}
$stmt->execute();
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Apps - AppCenter Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/all_apps.css">
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
                <a href="all_apps.php" class="menu-item active"><span>📱</span> All Apps</a>
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
                    <h1>All Apps</h1>
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
            
            <div class="card">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>
                
                <div class="search-container">
                    <form action="all_apps.php" method="get" style="display: flex; gap: 10px;">
                        <input type="text" name="search" placeholder="Search apps by name or description..." 
                               value="<?= htmlspecialchars($search) ?>">
                        <button type="submit">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="all_apps.php" class="clear-search">Clear All</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if (count($apps) > 0): ?>
                <!-- Desktop Table View -->
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Icon</th>
                                <th>Name</th>
                                <th>Version</th>
                                <th>Category</th>
                                <th>Downloads</th>
                                <th>Actions</th>
                                <th>Editor's Choice</th>
                                <th>Recommended</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($apps as $app): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($app['icon'])): ?>
                                            <img src="<?= SITE_URL ?>/uploads/icons/<?= htmlspecialchars($app['icon']) ?>" alt="App Icon" class="app-icon">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($app['name']) ?></td>
                                    <td><?= htmlspecialchars($app['version']) ?></td>
                                    <td><span class="badge badge-primary"><?= htmlspecialchars($app['category_name']) ?></span></td>
                                    <td><?= $app['downloads'] ?></td>
                                    <td>
                                        <a href="edit_app.php?id=<?= $app['id'] ?>" class="action-btn edit"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="all_apps.php?delete=<?= $app['id'] ?>" class="action-btn delete" onclick="return confirm('Are you absolutely sure? This will permanently delete the app and all its files!')"><i class="fas fa-trash-alt"></i> Delete</a>
                                    </td>
                                    <td>
                                        <a href="editor_choice.php?id=<?= $app['id'] ?>&current=<?= $app['is_editor_choice'] ?>" 
                                           class="action-btn favorite" title="<?= $app['is_editor_choice'] ? 'Remove from Editor\'s Choice' : 'Add to Editor\'s Choice' ?>">
                                            <span><?= $app['is_editor_choice'] ? '★' : '☆' ?></span>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="toggle_recommended.php?id=<?= $app['id'] ?>&current=<?= $app['is_recommended'] ?>" 
                                           class="action-btn recommended" title="<?= $app['is_recommended'] ? 'Remove from Recommended' : 'Add to Recommended' ?>">
                                            <span><?= $app['is_recommended'] ? '★' : '☆' ?></span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Mobile Card View -->
                <div class="mobile-cards-container">
                    <?php foreach ($apps as $app): ?>
                        <div class="mobile-app-card">
                            <div class="mobile-card-header">
                                <?php if (!empty($app['icon'])): ?>
                                    <img src="<?= SITE_URL ?>/uploads/icons/<?= htmlspecialchars($app['icon']) ?>" alt="App Icon" class="mobile-app-icon">
                                <?php endif; ?>
                                <div class="mobile-app-title">
                                    <div class="mobile-app-name"><?= htmlspecialchars($app['name']) ?></div>
                                    <div class="mobile-app-category"><?= htmlspecialchars($app['category_name']) ?></div>
                                </div>
                            </div>
                            
                            <div class="mobile-card-body">
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Version:</span>
                                    <span class="mobile-card-value"><?= htmlspecialchars($app['version']) ?></span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Downloads:</span>
                                    <span class="mobile-card-value"><?= $app['downloads'] ?></span>
                                </div>
                            </div>
                            
                            <div class="mobile-card-footer">
                                <div class="mobile-actions">
                                    <a href="edit_app.php?id=<?= $app['id'] ?>" class="action-btn edit"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="all_apps.php?delete=<?= $app['id'] ?>" class="action-btn delete" onclick="return confirm('Are you absolutely sure? This will permanently delete the app and all its files!')"><i class="fas fa-trash-alt"></i> Delete</a>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <a href="editor_choice.php?id=<?= $app['id'] ?>&current=<?= $app['is_editor_choice'] ?>" 
                                       class="mobile-favorite" title="<?= $app['is_editor_choice'] ? 'Remove from Editor\'s Choice' : 'Add to Editor\'s Choice' ?>">
                                        <span><?= $app['is_editor_choice'] ? '★' : '☆' ?></span>
                                    </a>
                                    <a href="toggle_recommended.php?id=<?= $app['id'] ?>&current=<?= $app['is_recommended'] ?>" 
                                       class="mobile-recommended" title="<?= $app['is_recommended'] ? 'Remove from Recommended' : 'Add to Recommended' ?>">
                                        <span><?= $app['is_recommended'] ? '★' : '☆' ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                    
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="all_apps.php?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&laquo;</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="all_apps.php?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           <?= $i == $page ? 'class="active"' : '' ?>><?= $i ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="all_apps.php?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&raquo;</a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <div class="no-apps">
                        <p>No apps found. <?= !empty($search) ? 'Try a different search term.' : 'Add your first app!' ?></p>
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
            
            setTimeout(()=>document.querySelectorAll('.alert').forEach(e=>e.classList.add('hide')),3000)
            
            // Auto-submit search when cleared
            const searchInput = document.querySelector('input[name="search"]');
            const searchForm = document.querySelector('form');
            
            searchInput.addEventListener('input', function() {
                if (this.value === '') {
                    searchForm.submit();
                }
            });
        });
    </script>
</body>
</html>
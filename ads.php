<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Define possible ad positions and pages
$adPositions = [
    'header' => 'Header',
    'footer' => 'Footer',
    'after_popular_section' => 'After Popular Section',
    'before_latest_post' => 'Before Latest Post',
    'before_post' => 'Before Post',
    'after_post' => 'After Post',
    'between_paragraph' => 'Between Paragraph',
    'sidebar' => 'Sidebar',
    'popup' => 'Popup'
];

$adPages = [
    'index' => 'Homepage',
    'app' => 'App Page',
    'download' => 'Download Page',
    'all' => 'All Pages'
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['add_ad']) || isset($_POST['update_ad'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = trim($_POST['title']);
        $ad_code = trim($_POST['ad_code']);
        $position = $_POST['position'];
        $pages = isset($_POST['pages']) ? implode(',', $_POST['pages']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $paragraph_interval = ($position === 'between_paragraph') ? (int)$_POST['paragraph_interval'] : null;

        // For positions that should only appear on homepage
        $homepage_only_positions = ['after_popular_section', 'before_latest_post'];
        if (in_array($position, $homepage_only_positions)) {
            $pages = 'index'; // Force to homepage only
        }

        // If "All Pages" is selected, override other selections
        if (in_array('all', $_POST['pages'] ?? [])) {
            $pages = 'all';
        }

        if (empty($title) || empty($ad_code) || empty($position)) {
            $_SESSION['error'] = "Please fill in all required fields.";
        } else {
            if (isset($_POST['add_ad'])) {
                // Add new ad
                $stmt = $pdo->prepare("INSERT INTO ads (title, ad_code, position, pages, is_active, paragraph_interval) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $ad_code, $position, $pages, $is_active, $paragraph_interval]);
                $_SESSION['success'] = "Advertisement added successfully!";
            } else {
                // Update existing ad
                $stmt = $pdo->prepare("UPDATE ads SET title = ?, ad_code = ?, position = ?, pages = ?, is_active = ?, paragraph_interval = ?
                                      WHERE id = ?");
                $stmt->execute([$title, $ad_code, $position, $pages, $is_active, $paragraph_interval, $id]);
                $_SESSION['success'] = "Advertisement updated successfully!";
            }
        }
    } elseif (isset($_POST['delete_ad'])) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM ads WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Advertisement deleted successfully!";
    } elseif (isset($_POST['toggle_status'])) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE ads SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Advertisement status updated!";
    }
    
    // Redirect to prevent form resubmission
    header("Location: ads.php");
    exit();
}

// Get messages from session
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Get all ads for listing
$ads = $pdo->query("SELECT * FROM ads ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get ad for editing if ID is provided
$editAd = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editAd = $pdo->prepare("SELECT * FROM ads WHERE id = ?");
    $editAd->execute([$_GET['edit']]);
    $editAd = $editAd->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Advertisements - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/ads.css">
</head>
<body>
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
                <a href="ads.php" class="menu-item active"><span>📢</span> Manage Ads</a>
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
                    <h1>Manage Ads</h1>
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
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <div class="content-card" id="current-ads-section">
                <div class="section-header">
                    <h2 class="section-title">Current Advertisements</h2>
                    <div>
                        <button id="add-new-btn" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New </button>
                        <a href="ads.php" class="btn btn-secondary">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </a>
                    </div>
                </div>
                
                <?php if (empty($ads)): ?>
                    <p>No advertisements found.</p>
                <?php else: ?>
                    <table class="table">
    <thead>
        <tr>
            <th>Title</th>
            <th>Position</th>
            <th>Pages</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($ads as $ad): 
            $pages = explode(',', $ad['pages']);
            $pageLabels = array_map(function($page) use ($adPages) {
                return $adPages[$page] ?? $page;
            }, $pages);
        ?>
            <tr>
                <td data-label="Title"><?= htmlspecialchars($ad['title']) ?></td>
                <td data-label="Position"><?= $adPositions[$ad['position']] ?? $ad['position'] ?></td>
                <td data-label="Pages"><?= implode(', ', $pageLabels) ?></td>
                <td data-label="Status">
                    <span class="status-badge <?= $ad['is_active'] ? 'status-active' : 'status-inactive' ?>">
                        <?= $ad['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td data-label="Actions">
                    <a href="ads.php?edit=<?= $ad['id'] ?>" class="btn btn-secondary btn-sm" title="Edit">
                        <i class="fas fa-edit"></i> <span>Edit</span>
                    </a>
                    <form method="post" style="display: inline-block;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $ad['id'] ?>">
                        <button type="submit" name="toggle_status" class="btn btn-sm <?= $ad['is_active'] ? 'btn-warning' : 'btn-primary' ?>" title="<?= $ad['is_active'] ? 'Deactivate' : 'Activate' ?>">
            <?php if ($ad['is_active']): ?>
                <i class="fas fa-power-off"></i> 
            <?php else: ?>
                <i class="fa-solid fa-play"></i> 
            <?php endif; ?>
            <span><?= $ad['is_active'] ? 'Deactivate' : 'Activate' ?></span>
        </button>
                    </form>
                    <form method="post" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this advertisement?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $ad['id'] ?>">
                        <button type="submit" name="delete_ad" class="btn btn-danger btn-sm" title="Delete">
                            <i class="fas fa-trash"></i> <span>Delete</span>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
                <?php endif; ?>
            </div>
            
            <div class="content-card <?= !$editAd ? 'hidden-form' : '' ?>" id="add-ad-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <?= $editAd ? 'Edit Advertisement' : 'Add New Advertisement' ?>
                    </h2>
                    <?php if (!$editAd): ?>
                        <button id="cancel-add-btn" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    <?php endif; ?>
                </div>
                
                <form method="post">
                    <?= csrf_field() ?>
                    <?php if ($editAd): ?>
                        <input type="hidden" name="id" value="<?= $editAd['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="title" class="form-label">Advertisement Title *</label>
                        <input type="text" id="title" name="title" class="form-control" 
                               value="<?= htmlspecialchars($editAd['title'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="ad_code" class="form-label">Advertisement Code (HTML/JavaScript) *</label>
                        <textarea id="ad_code" name="ad_code" class="form-control" required><?= htmlspecialchars($editAd['ad_code'] ?? '') ?></textarea>
                        <small>Paste your ad code from Google AdSense, Media.net, or other ad networks</small>
                        
                        <?php if ($editAd): ?>
                            <div class="preview-container">
                                <h4>Preview:</h4>
                                <?= $editAd['ad_code'] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="position" class="form-label">Position *</label>
                        <select id="position" name="position" class="form-control" required>
                            <option value="">Select Position</option>
                            <?php foreach ($adPositions as $value => $label): ?>
                                <option value="<?= $value ?>" <?= (isset($editAd['position']) && $editAd['position'] === $value) ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Paragraph Interval Field -->
                    <div class="form-group" id="paragraph-interval-container" style="display: none;">
                        <label for="paragraph_interval" class="form-label">Select Paragraph</label>
                        <select id="paragraph_interval" name="paragraph_interval" class="form-control">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>" <?= (isset($editAd['paragraph_interval']) && $editAd['paragraph_interval'] == $i) ? 'selected' : '' ?>>
                                    <?= $i ?> paragraph<?= $i > 1 ? 's' : '' ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Display On Pages *</label>
                        <div class="checkbox-group" id="pages-container">
                            <?php foreach ($adPages as $value => $label): 
                                $isDisabled = false;
                                $isChecked = isset($editAd['pages']) && (in_array($value, explode(',', $editAd['pages'])) || (isset($editAd['pages'])) && $editAd['pages'] === 'all' && $value === 'all');
                                
                                // If editing and position is homepage-only, disable non-homepage options
                                if ($editAd && in_array($editAd['position'], ['after_popular_section', 'before_latest_post'])) {
                                    $isDisabled = ($value !== 'index');
                                    if ($isDisabled) $isChecked = false;
                                }
                            ?>
                                <div class="checkbox-item <?= $isDisabled ? 'disabled' : '' ?>">
                                    <input type="checkbox" id="page_<?= $value ?>" name="pages[]" value="<?= $value ?>"
                                           <?= $isChecked ? 'checked' : '' ?>
                                           <?= $isDisabled ? 'disabled' : '' ?>>
                                    <label for="page_<?= $value ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div class="form-check">
                            <input type="checkbox" id="is_active" name="is_active" class="form-check-input" 
                                   <?= (isset($editAd['is_active']) && $editAd['is_active']) || !isset($editAd) ? 'checked' : '' ?>>
                            <label for="is_active">Active</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <?php if ($editAd): ?>
                            <button type="submit" name="update_ad" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update
                            </button>
                            <a href="ads.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php else: ?>
                            <button type="submit" name="add_ad" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Advertisement
                            </button>
                        <?php endif; ?>
                    </div>
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
            
            // Menu toggle functionality
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
            
            // Preview ad code changes
            const adCodeTextarea = document.getElementById('ad_code');
            const previewContainer = document.querySelector('.preview-container');
            
            if (adCodeTextarea && previewContainer) {
                adCodeTextarea.addEventListener('input', function() {
                    previewContainer.innerHTML = '<h4>Preview:</h4>' + this.value;
                });
            }

            // Handle position change to show/hide paragraph interval field
            const positionSelect = document.getElementById('position');
            const paragraphIntervalContainer = document.getElementById('paragraph-interval-container');
            
            function toggleParagraphIntervalField() {
                if (positionSelect.value === 'between_paragraph') {
                    paragraphIntervalContainer.style.display = 'block';
                } else {
                    paragraphIntervalContainer.style.display = 'none';
                }
            }
            
            // Initial check
            toggleParagraphIntervalField();
            
            // Check on position change
            positionSelect.addEventListener('change', toggleParagraphIntervalField);
            
            // If editing and position is between_paragraph, show the field
            <?php if (isset($editAd['position']) && $editAd['position'] === 'between_paragraph'): ?>
                paragraphIntervalContainer.style.display = 'block';
            <?php endif; ?>

           // Handle position change to disable certain page options
const pagesContainer = document.getElementById('pages-container');

if (positionSelect && pagesContainer) {
    positionSelect.addEventListener('change', function() {
        const homepageOnlyPositions = ['after_popular_section', 'before_latest_post'];
        const isHomepageOnly = homepageOnlyPositions.includes(this.value);
        const isBetweenParagraph = this.value === 'between_paragraph';
        
        // Get all page checkboxes
        const pageCheckboxes = pagesContainer.querySelectorAll('input[type="checkbox"]');
        
        pageCheckboxes.forEach(checkbox => {
            const checkboxItem = checkbox.closest('.checkbox-item');
            
            if (isHomepageOnly && checkbox.value !== 'index') {
                // Disable non-homepage options for homepage-only positions
                checkbox.disabled = true;
                checkbox.checked = false;
                checkboxItem.classList.add('disabled');
            } else if (isBetweenParagraph) {
                // For between_paragraph position, only enable app page
                if (checkbox.value === 'app') {
                    checkbox.disabled = false;
                    checkboxItem.classList.remove('disabled');
                    if (!checkbox.checked) checkbox.checked = true; // Auto-check app page
                } else {
                    checkbox.disabled = true;
                    checkbox.checked = false;
                    checkboxItem.classList.add('disabled');
                }
            } else {
                // Enable all options for other positions
                checkbox.disabled = false;
                checkboxItem.classList.remove('disabled');
            }
        });
        
        // If homepage only, ensure homepage is checked
        if (isHomepageOnly) {
            const homepageCheckbox = document.getElementById('page_index');
            homepageCheckbox.checked = true;
        }
    });
}

            // Toggle add new advertisement form
            const addNewBtn = document.getElementById('add-new-btn');
            const cancelAddBtn = document.getElementById('cancel-add-btn');
            const addAdSection = document.getElementById('add-ad-section');
            const currentAdsSection = document.getElementById('current-ads-section');
            
            if (addNewBtn && addAdSection && currentAdsSection) {
                addNewBtn.addEventListener('click', function() {
                    addAdSection.classList.remove('hidden-form');
                    currentAdsSection.classList.add('hidden-form');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
                
                if (cancelAddBtn) {
                    cancelAddBtn.addEventListener('click', function() {
                        addAdSection.classList.add('hidden-form');
                        currentAdsSection.classList.remove('hidden-form');
                    });
                }
            }

            // Handle "All Pages" checkbox selection
            const allPagesCheckbox = document.getElementById('page_all');
            if (allPagesCheckbox) {
                allPagesCheckbox.addEventListener('change', function() {
                    const pageCheckboxes = document.querySelectorAll('input[name="pages[]"]:not(#page_all)');
                    if (this.checked) {
                        pageCheckboxes.forEach(checkbox => {
                            checkbox.checked = false;
                            checkbox.disabled = true;
                            checkbox.closest('.checkbox-item').classList.add('disabled');
                        });
                    } else {
                        pageCheckboxes.forEach(checkbox => {
                            checkbox.disabled = false;
                            checkbox.closest('.checkbox-item').classList.remove('disabled');
                        });
                    }
                });
            }

            // If editing and "All Pages" was selected, disable other checkboxes
            <?php if ($editAd && $editAd['pages'] === 'all'): ?>
                const pageCheckboxes = document.querySelectorAll('input[name="pages[]"]:not(#page_all)');
                pageCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                    checkbox.disabled = true;
                    checkbox.closest('.checkbox-item').classList.add('disabled');
                });
            <?php endif; ?>

            // Prevent form resubmission warning
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
        
        setTimeout(()=>document.querySelectorAll('.alert').forEach(e=>e.classList.add('hide')),3000)
    </script>
</body>
</html>
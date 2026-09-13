<?php
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Define the settings file path
$settingsFile = '../data/homepage_settings.json';

// Define all homepage sections with default values
$defaultSettings = [
    'hero' => [
        'visible' => true,
        'content' => [
            'title' => 'APKTEMPLATES',
            'description' => 'An Android App Store where you can download your favorite APK.'
        ],
        'image_path' => ''
    ],
    'recommended' => [
        'visible' => true,
        'content' => [
            'title' => 'Recommended',
            'limit' => 4 // Fixed to 4 apps
        ]
    ],
    'popular' => [
        'visible' => true,
        'content' => [
            'title' => 'Popular Apps',
            'limit' => 12
        ]
    ],
    'main_categories' => [
        'visible' => true,
        'content' => [
            'title' => 'Featured Categories',
            'limit' => 12,
            'view_all_text' => 'View All',
            'selected_category' => null
        ]
    ],
    'featured_categories' => [
        'visible' => true,
        'content' => [
            'title' => 'More Featured Categories',
            'limit' => 12,
            'view_all_text' => 'View All',
            'selected_category' => null
        ]
    ],
    'editors_choice' => [
        'visible' => true,
        'content' => [
            'title' => "Editor's Choice",
            'limit' => 6
        ]
    ],
    'latest_post' => [
        'visible' => true,
        'content' => [
            'title' => 'Latest Post',
            'per_page' => 12
        ]
    ],
    'categories' => [
        'visible' => true,
        'content' => [
            'title' => 'Categories'
        ]
    ],
    'blog' => [
        'visible' => true,
        'content' => [
            'title' => 'Articles',
            'limit' => 4,
            'view_all_text' => 'More'
        ]
    ],
    'footer' => [
        'visible' => true,
        'content' => [
            'copyright' => ''
        ]
    ]
];

// Function to load settings from file
function loadSettings($file, $defaultSettings) {
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $settings = json_decode($json, true);
        // Merge with defaults to ensure all sections exist
        return array_replace_recursive($defaultSettings, $settings);
    }
    return $defaultSettings;
}

// Function to save settings to file
function saveSettings($file, $settings) {
    $json = json_encode($settings, JSON_PRETTY_PRINT);
    file_put_contents($file, $json);
}

// Create data directory if it doesn't exist
if (!file_exists('../data')) {
    mkdir('../data', 0755, true);
}

// Initialize settings from file
$settings = loadSettings($settingsFile, $defaultSettings);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($defaultSettings as $key => $default) {
        // Update visibility
        $settings[$key]['visible'] = isset($_POST['visible_' . $key]) ? 1 : 0;
        
        // Update content if provided
        if (isset($_POST['content_' . $key])) {
            // Force recommended apps limit to 4
            if ($key === 'recommended') {
                $_POST['content_recommended']['limit'] = 4;
            }
            
            // Special handling for main categories to ensure selected_category is set
            if ($key === 'main_categories' || $key === 'featured_categories') {
                $settings[$key]['content'] = array_merge(
                    $default['content'],
                    $_POST['content_' . $key],
                    ['selected_category' => $_POST['content_' . $key]['selected_category'] ?? null]
                );
            } else {
                $settings[$key]['content'] = array_merge(
                    $default['content'],
                    $_POST['content_' . $key]
                );
            }
        }
    }
    
    // Handle hero section image upload
    if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/hero/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Routed through uploadFile() so the same real-content validation,
        // extension whitelist, and randomized filename apply here too.
        $heroUpload = uploadFile($_FILES['hero_image'], $uploadDir, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

        if ($heroUpload['success']) {
            // Delete old image if it exists
            if (!empty($settings['hero']['image_path']) && file_exists($uploadDir . $settings['hero']['image_path'])) {
                unlink($uploadDir . $settings['hero']['image_path']);
            }

            $settings['hero']['image_path'] = $heroUpload['filename'];
        }
    }
    
    // Handle hero image removal
    if (isset($_POST['remove_hero_image'])) {
        $uploadDir = '../uploads/hero/';
        if (!empty($settings['hero']['image_path']) && file_exists($uploadDir . $settings['hero']['image_path'])) {
            unlink($uploadDir . $settings['hero']['image_path']);
            $settings['hero']['image_path'] = '';
        }
    }
    
    // Save to file
    saveSettings($settingsFile, $settings);
    
    $_SESSION['success_message'] = 'Homepage settings updated successfully!';
    header("Location: control_panel.php");
    exit();
}

// Get current hero image
$heroImage = isset($settings['hero']['image_path']) && !empty($settings['hero']['image_path']) ? 
    '../uploads/hero/' . $settings['hero']['image_path'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage Control Panel - AppCenter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/control_panel.css">
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
                <a href="control_panel.php" class="menu-item active"><span>⚙️</span> Homepage Control</a>
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
                    <h1>Control Panel</h1>
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
            
            <form action="" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="panel-body">
                    
                    <!-- Hero Section Control -->
                    <div class="section-control">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-image"></i> Hero Section</h3>
                            <label class="toggle-switch"><input type="checkbox" name="visible_hero" <?= $settings['hero']['visible'] ? 'checked' : '' ?>><span class="slider"></span></label>
                        </div>
                        <div class="section-content <?= $settings['hero']['visible'] ? 'active' : '' ?>">
                            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 30px;">
                                <!-- First Column - Title and Description -->
                                <div>
                                    <div class="form-group">
                                        <label for="hero_title">Title</label>
                                        <input type="text" id="hero_title" name="content_hero[title]" class="form-control" value="<?= htmlspecialchars($settings['hero']['content']['title']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="hero_description">Description</label>
                                        <textarea id="hero_description" name="content_hero[description]" class="form-control"><?= htmlspecialchars($settings['hero']['content']['description']) ?></textarea>
                                    </div>
                                </div>
                                <!-- Second Column - Image Upload -->
                                <div>
                                    <div class="form-group">
                                        <label>Current Image Preview:</label>
                                        <?php if ($heroImage && file_exists($heroImage)): ?>
                                            <img src="<?= $heroImage ?>" alt="Current Hero Image" class="hero-image-preview">
                                        <?php else: ?>
                                            <p style="font-size: 0.875rem; color: #64748b;">No image uploaded</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                            <label for="hero_image">Upload New Image</label>
                                            <span style="font-size: 0.75rem; color: #64748b;">Recommended size: 1200×600 pixels</span>
                                        </div>
                                        <div class="upload-remove-container">
                                            <div class="file-upload">
                                                <button type="button" class="file-upload-btn"><i class="fas fa-cloud-upload-alt"></i> Choose Image</button>
                                                <input type="file" id="hero_image" name="hero_image" class="file-upload-input" accept="image/*">
                                            </div>
                                            <button type="submit" id="saveHeroImageBtn" class="btn" style="display: none;">
                                                <i class="fas fa-save"></i> Save
                                            </button>
                                            <?php if ($heroImage && file_exists($heroImage)): ?>
                                                <button type="submit" name="remove_hero_image" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Remove</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recommended Apps Section Control -->
                    <div class="section-control">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-star"></i> Recommended Apps Section</h3>
                            <label class="toggle-switch">
                                <input type="checkbox" name="visible_recommended" <?= $settings['recommended']['visible'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="section-content <?= $settings['recommended']['visible'] ? 'active' : '' ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="recommended_title">Section Title</label>
                                    <input type="text" id="recommended_title" name="content_recommended[title]" class="form-control" 
                                           value="<?= htmlspecialchars($settings['recommended']['content']['title']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Number of apps to display</label>
                                    <input type="number" name="content_recommended[limit]" class="form-control" 
                                           value="4" readonly>
                                    <small style="font-size: 0.75rem; color: #64748b;">Fixed to display 4 apps</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Popular Apps Section Control -->
                    <div class="section-control">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-fire"></i> Popular Apps Section</h3>
                            <label class="toggle-switch">
                                <input type="checkbox" name="visible_popular" <?= $settings['popular']['visible'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="section-content <?= $settings['popular']['visible'] ? 'active' : '' ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="popular_title">Section Title</label>
                                    <input type="text" id="popular_title" name="content_popular[title]" class="form-control" 
                                           value="<?= htmlspecialchars($settings['popular']['content']['title']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Number of apps to display</label>
                                    <input type="number" name="content_popular[limit]" class="form-control" min="1" max="20" 
                                           value="<?= htmlspecialchars($settings['popular']['content']['limit']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Game/App Sections_1 -->
                    <div class="section-control">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-gamepad"></i> Game/App Sections_1</h3>
                            <label class="toggle-switch">
                                <input type="checkbox" name="visible_main_categories" <?= $settings['main_categories']['visible'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="section-content <?= $settings['main_categories']['visible'] ? 'active' : '' ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="main_categories_title">Section Title</label>
                                    <input type="text" id="main_categories_title" name="content_main_categories[title]" class="form-control" 
                                           value="<?= htmlspecialchars($settings['main_categories']['content']['title'] ?? 'Featured Apps') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Number of apps to display</label>
                                    <input type="number" name="content_main_categories[limit]" class="form-control" min="1" max="20" 
                                           value="<?= htmlspecialchars($settings['main_categories']['content']['limit'] ?? 12) ?>">
                                </div>
                                <div class="form-group">
                                    <label>View All Button Text</label>
                                    <input type="text" name="content_main_categories[view_all_text]" class="form-control" 
                                           value="<?= htmlspecialchars($settings['main_categories']['content']['view_all_text'] ?? 'View All') ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Select ONE Category to Show</label>
                                <select name="content_main_categories[selected_category]" class="form-control">
                                    <option value="">-- Select a Category --</option>
                                    <?php 
                                    $categories = getCategories();
                                    $selectedCategory = $settings['main_categories']['content']['selected_category'] ?? null;
                                    
                                    foreach ($categories as $category): 
                                        if (!empty($category['subcategories'])):
                                    ?>
                                        <option value="<?= $category['id'] ?>" <?= ($category['id'] == $selectedCategory) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Game/App Sections_2 -->
                    <div class="section-control">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-mobile-alt"></i> Game/App Sections_2</h3>
                            <label class="toggle-switch">
                                <input type="checkbox" name="visible_featured_categories" <?= $settings['featured_categories']['visible'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="section-content <?= $settings['featured_categories']['visible'] ? 'active' : '' ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="featured_categories_title">Section Title</label>
                                    <input type="text" id="featured_categories_title" name="content_featured_categories[title]" class="form-control" 
                                           value="<?= htmlspecialchars($settings['featured_categories']['content']['title'] ?? 'More Featured Categories') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Number of apps to display</label>
                                    <input type="number" name="content_featured_categories[limit]" class="form-control" min="1" max="20" 
                                           value="<?= htmlspecialchars($settings['featured_categories']['content']['limit'] ?? 12) ?>">
                                </div>
                                <div class="form-group">
                                    <label>View All Button Text</label>
                                    <input type="text" name="content_featured_categories[view_all_text]" class="form-control" 
                                           value="<?= htmlspecialchars($settings['featured_categories']['content']['view_all_text'] ?? 'View All') ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Select ONE Category to Show</label>
                                <select name="content_featured_categories[selected_category]" class="form-control">
                                    <option value="">-- Select a Category --</option>
                                    <?php 
                                    $categories = getCategories();
                                    $selectedCategory = $settings['featured_categories']['content']['selected_category'] ?? null;
                                    
                                    foreach ($categories as $category): 
                                        if (!empty($category['subcategories'])):
                                    ?>
                                        <option value="<?= $category['id'] ?>" <?= ($category['id'] == $selectedCategory) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Editor's Choice Section Control -->
                    <div class="section-control">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-award"></i> Editor's Choice Section</h3>
                            <label class="toggle-switch">
                                <input type="checkbox" name="visible_editors_choice" <?= $settings['editors_choice']['visible'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="section-content <?= $settings['editors_choice']['visible'] ? 'active' : '' ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="editors_choice_title">Section Title</label>
                                    <input type="text" id="editors_choice_title" name="content_editors_choice[title]" class="form-control" 
                                           value="<?= htmlspecialchars($settings['editors_choice']['content']['title']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Number of apps to display</label>
                                    <input type="number" name="content_editors_choice[limit]" class="form-control" min="1" max="20" 
                                           value="<?= htmlspecialchars($settings['editors_choice']['content']['limit']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Latest Post Section Control -->
                    <div class="section-control">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-newspaper"></i> Latest Post Section</h3>
                            <label class="toggle-switch">
                                <input type="checkbox" name="visible_latest_post" <?= $settings['latest_post']['visible'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="section-content <?= $settings['latest_post']['visible'] ? 'active' : '' ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="latest_post_title">Section Title</label>
                                    <input type="text" id="latest_post_title" name="content_latest_post[title]" class="form-control" 
                                           value="<?= htmlspecialchars($settings['latest_post']['content']['title']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Number of posts per page</label>
                                    <input type="number" name="content_latest_post[per_page]" class="form-control" min="1" max="50" 
                                           value="<?= htmlspecialchars($settings['latest_post']['content']['per_page']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Categories Section Control -->
                    <div class="section-control">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-tags"></i> Categories Section</h3>
                            <label class="toggle-switch">
                                <input type="checkbox" name="visible_categories" <?= $settings['categories']['visible'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="section-content <?= $settings['categories']['visible'] ? 'active' : '' ?>">
                            <div class="form-group">
                                <label for="categories_title">Section Title</label>
                                <input type="text" id="categories_title" name="content_categories[title]" class="form-control" 
                                       value="<?= htmlspecialchars($settings['categories']['content']['title']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Articles (Blog) Section Control -->
                    <div class="section-control">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-newspaper"></i> Articles Section</h3>
                            <label class="toggle-switch">
                                <input type="checkbox" name="visible_blog" <?= $settings['blog']['visible'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="section-content <?= $settings['blog']['visible'] ? 'active' : '' ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="blog_title">Section Title</label>
                                    <input type="text" id="blog_title" name="content_blog[title]" class="form-control" 
                                           value="<?= htmlspecialchars($settings['blog']['content']['title']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Number of articles to display</label>
                                    <input type="number" name="content_blog[limit]" class="form-control" min="1" max="20" 
                                           value="<?= htmlspecialchars($settings['blog']['content']['limit']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="blog_view_all_text">"View All" Button Text</label>
                                    <input type="text" id="blog_view_all_text" name="content_blog[view_all_text]" class="form-control" 
                                           value="<?= htmlspecialchars($settings['blog']['content']['view_all_text']) ?>">
                                </div>
                            </div>
                            <small style="font-size: 0.75rem; color: #64748b;">
                                Shows your most recent published posts from <a href="/admin/blog.php" style="color: var(--primary-color); text-decoration: underline;">Manage Blog</a>. Only appears if you have at least one published post.
                            </small>
                        </div>
                    </div>
                    
                    <!-- Footer Section Control -->
                    <div class="section-control">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-shoe-prints"></i> Footer Section</h3>
                            <label class="toggle-switch">
                                <input type="checkbox" name="visible_footer" <?= $settings['footer']['visible'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="section-content <?= $settings['footer']['visible'] ? 'active' : '' ?>">
                            <div class="form-group">
                                <label for="footer_copyright">Copyright Text</label>
                                <input type="text" id="footer_copyright" name="content_footer[copyright]" class="form-control" 
                                       value="<?= htmlspecialchars($settings['footer']['content']['copyright']) ?>" disabled>
                                <small style="font-size: 0.75rem; color: #64748b;">
  <strong>Note:</strong> Copyright text is managed in the <a href="/admin/manage_footer.php" style="color: var(--primary-color); text-decoration: underline;">Manage Footer</a> page.
</small>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Save All Changes
                        </button>
                    </div>
                </div>
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
            
            // Toggle section content when checkbox changes
            document.querySelectorAll('.toggle-switch input').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const sectionContent = this.closest('.section-control').querySelector('.section-content');
                    if (this.checked) {
                        sectionContent.classList.add('active');
                    } else {
                        sectionContent.classList.remove('active');
                    }
                });
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

            // File upload button text update and save button toggle
            const heroImageInput = document.getElementById('hero_image');
            const saveHeroImageBtn = document.getElementById('saveHeroImageBtn');
            
            heroImageInput.addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
                const button = this.closest('.file-upload').querySelector('.file-upload-btn');
                button.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> ${fileName}`;
                
                // Show the save button when a file is selected
                if (this.files.length > 0) {
                    saveHeroImageBtn.style.display = 'inline-flex';
                } else {
                    saveHeroImageBtn.style.display = 'none';
                }
            });
            
            // Hide save button after form submission (page reload will handle this)
            saveHeroImageBtn.addEventListener('click', function() {
                // The button will be hidden after page reload
            });
        });
        
        setTimeout(()=>document.querySelectorAll('.alert').forEach(e=>e.classList.add('hide')),3000)
    </script>
</body>
</html>

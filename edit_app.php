<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$categories = getCategories();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $app_id = $_POST['app_id'];
    $name = trim($_POST['name']);
    $version = trim($_POST['version']);
    $description = trim($_POST['description']);
    $main_category_id = $_POST['main_category_id'];
    $subcategory_id = $_POST['subcategory_id'];
    $category_id = !empty($subcategory_id) ? $subcategory_id : $main_category_id;
    $size = trim($_POST['size']);
    $external_url = trim($_POST['external_url']);
    $slug = slugify($name);
    
    if (empty($name) || empty($version) || empty($description) || empty($main_category_id)) {
        $error = 'Please fill in all required fields';
    } else {
        $stmt = $pdo->prepare("SELECT icon, apk_file FROM apps WHERE id = ?");
        $stmt->execute([$app_id]);
        $currentApp = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $iconFilename = $currentApp['icon'];
        $apkFilename = $currentApp['apk_file'];
        
        if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
            if (!empty($iconFilename) && file_exists(ICON_DIR . $iconFilename)) {
                unlink(ICON_DIR . $iconFilename);
            }
            
            $uploadResult = uploadFile($_FILES['icon'], ICON_DIR);
            if ($uploadResult['success']) {
                $iconFilename = $uploadResult['filename'];
            } else {
                $error = $uploadResult['message'];
            }
        }
        
        if (empty($external_url)) {
            if (isset($_FILES['apk_file']) && $_FILES['apk_file']['error'] === UPLOAD_ERR_OK) {
                if (!empty($apkFilename) && file_exists(APK_DIR . $apkFilename)) {
                    unlink(APK_DIR . $apkFilename);
                }
                
                $uploadResult = uploadFile($_FILES['apk_file'], APK_DIR, ['application/vnd.android.package-archive']);
                if ($uploadResult['success']) {
                    $apkFilename = $uploadResult['filename'];
                } else {
                    $error = $uploadResult['message'];
                }
            } elseif (empty($apkFilename)) {
                $error = 'Either APK file or external URL is required';
            }
        } else {
            $apkFilename = '';
        }
        
        if (empty($error)) {
            try {
                $stmt = $pdo->prepare("UPDATE apps SET name = :name, slug = :slug, version = :version, description = :description, icon = :icon, apk_file = :apk_file, external_url = :external_url, category_id = :category_id, size = :size WHERE id = :id");
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':slug', $slug);
                $stmt->bindParam(':version', $version);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':icon', $iconFilename);
                $stmt->bindParam(':apk_file', $apkFilename);
                $stmt->bindParam(':external_url', $external_url);
                $stmt->bindParam(':category_id', $category_id);
                $stmt->bindParam(':size', $size);
                $stmt->bindParam(':id', $app_id);
                
                if ($stmt->execute()) {
                    $success = 'App updated successfully!';
                } else {
                    $error = 'Failed to update app. Please try again.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$app = null;
if (isset($_GET['id'])) {
    $app_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM apps WHERE id = ?");
    $stmt->execute([$app_id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$app) {
        $error = 'App not found.';
    }
    
    // Determine if current category is a subcategory
    $current_category_id = $app['category_id'] ?? null;
    $is_subcategory = false;
    $parent_category_id = null;
    
    if ($current_category_id) {
        foreach ($categories as $main_category) {
            if ($main_category['id'] == $current_category_id) {
                // It's a main category
                $parent_category_id = $main_category['id'];
                break;
            }
            if (!empty($main_category['subcategories'])) {
                foreach ($main_category['subcategories'] as $subcategory) {
                    if ($subcategory['id'] == $current_category_id) {
                        $is_subcategory = true;
                        $parent_category_id = $main_category['id'];
                        break 2;
                    }
                }
            }
        }
    }
} else {
    header("Location: all_apps.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit App - AppCenter Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">    
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/edit_app.css">
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
                <h1>Edit App</h1>
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
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                    <div class="btn-group">
                        <a href="all_apps.php" class="btn"><i class="fas fa-arrow-left"></i> Back to All Apps</a>
                        <?php if (isset($app)): ?>
                            <a href="edit_app.php?id=<?= $app['id'] ?>" class="btn"><i class="fas fa-edit"></i> Continue Editing</a>
                        <?php endif; ?>
                    </div>
                <?php elseif (isset($app)): ?>
                    <form action="edit_app.php" method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                        
                        <div class="form-group">
                            <label for="name">App Name *</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($app['name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="version">Version *</label>
                            <input type="text" id="version" name="version" class="form-control" value="<?= htmlspecialchars($app['version']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description *</label>
                            <div class="editor-toolbar">
                                <!-- Text Formatting -->
                                <button type="button" onclick="formatText('bold')" title="Bold"><i class="fas fa-bold"></i></button>
                                <button type="button" onclick="formatText('italic')" title="Italic"><i class="fas fa-italic"></i></button>
                                <button type="button" onclick="formatText('underline')" title="Underline"><i class="fas fa-underline"></i></button>
                                <button type="button" onclick="formatText('strikeThrough')" title="Strikethrough"><i class="fas fa-strikethrough"></i></button>
                                
                                <!-- Headings -->
                                <select onchange="formatText('formatBlock', this.value)" title="Heading Style">
                                    <option value="">Paragraph</option>
                                    <option value="h1">Heading 1</option>
                                    <option value="h2">Heading 2</option>
                                    <option value="h3">Heading 3</option>
                                    <option value="h4">Heading 4</option>
                                    <option value="h5">Heading 5</option>
                                    <option value="h6">Heading 6</option>
                                    <option value="p">Paragraph</option>
                                    <option value="pre">Preformatted</option>
                                    <option value="blockquote">Quote</option>
                                </select>
                                
                                <!-- Lists -->
                                <button type="button" onclick="formatText('insertUnorderedList')" title="Bullet List"><i class="fas fa-list-ul"></i></button>
                                <button type="button" onclick="formatText('insertOrderedList')" title="Numbered List"><i class="fas fa-list-ol"></i></button>
                                
                                <!-- Links -->
                                <button type="button" onclick="formatText('createLink')" title="Insert Link"><i class="fas fa-link"></i></button>
                                
                                <!-- Media -->
                                <button type="button" onclick="insertMedia('image')" title="Insert Image"><i class="fas fa-image"></i></button>
                                <button type="button" onclick="insertMedia('video')" title="Insert Video"><i class="fas fa-video"></i></button>
                                
                                <!-- Alignment -->
                                <button type="button" onclick="formatText('justifyLeft')" title="Align Left"><i class="fas fa-align-left"></i></button>
                                <button type="button" onclick="formatText('justifyCenter')" title="Align Center"><i class="fas fa-align-center"></i></button>
                                <button type="button" onclick="formatText('justifyRight')" title="Align Right"><i class="fas fa-align-right"></i></button>
                                
                                <!-- Undo/Redo -->
                                <button type="button" onclick="formatText('undo')" title="Undo"><i class="fas fa-undo"></i></button>
                                <button type="button" onclick="formatText('redo')" title="Redo"><i class="fas fa-redo"></i></button>
                                
                                <!-- Clear Formatting -->
                                <button type="button" onclick="formatText('removeFormat')" title="Clear Formatting"><i class="fas fa-eraser"></i></button>
                            </div>
                            <div id="description" class="editor-content" contenteditable="true"><?= isset($app['description']) ? htmlspecialchars_decode($app['description']) : '' ?></div>
                            <textarea id="description-hidden" name="description" style="display:none;"></textarea>
                            
                            <!-- Hidden file inputs for media upload -->
                            <input type="file" id="image-upload" accept="image/*" style="display:none;">
                            <input type="file" id="video-upload" accept="video/*" style="display:none;">
                        </div>
                        
                        <div class="category-select-container">
                            <div class="category-select-box">
                                <label for="main_category_id">Main Category *</label>
                                <select id="main_category_id" name="main_category_id" class="category-select" required onchange="updateSubcategories()">
                                    <option value="">-- Select Main Category --</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                            <?= ($parent_category_id == $category['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="category-select-box">
                                <label for="subcategory_id">Subcategory</label>
                                <select id="subcategory_id" name="subcategory_id" class="category-select">
                                    <option value="">-- Select Subcategory --</option>
                                    <?php 
                                    if ($is_subcategory || !empty($parent_category_id)) {
                                        $selectedMainCategory = null;
                                        foreach ($categories as $category) {
                                            if ($category['id'] == $parent_category_id) {
                                                $selectedMainCategory = $category;
                                                break;
                                            }
                                        }
                                        
                                        if ($selectedMainCategory && !empty($selectedMainCategory['subcategories'])) {
                                            foreach ($selectedMainCategory['subcategories'] as $subcategory) {
                                                echo '<option value="' . $subcategory['id'] . '"';
                                                if ($current_category_id == $subcategory['id']) {
                                                    echo ' selected';
                                                }
                                                echo '>' . htmlspecialchars($subcategory['name']) . '</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="size">App Size (e.g., 15 MB)</label>
                            <input type="text" id="size" name="size" class="form-control" value="<?= htmlspecialchars($app['size']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>App Icon</label>
                            <div class="file-upload">
                                <div class="file-upload-preview">
                                    <?php if (!empty($app['icon'])): ?>
                                        <img src="<?= SITE_URL ?>/uploads/icons/<?= htmlspecialchars($app['icon']) ?>" alt="Current Icon">
                                    <?php else: ?>
                                        <i class="fas fa-image" style="font-size: 2rem; color: var(--gray);"></i>
                                    <?php endif; ?>
                                </div>
                                <label for="icon" class="btn" style="width: fit-content;">
                                    <i class="fas fa-upload"></i> Upload Icon
                                    <input type="file" id="icon" name="icon" accept="image/*" class="file-input">
                                </label>
                                <?php if (!empty($app['icon'])): ?>
                                    <div class="current-file">Current: <?= htmlspecialchars($app['icon']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>APK File (or provide external URL below)</label>
                            <div class="file-upload">
                                <label for="apk_file" class="btn" style="width: fit-content;">
                                    <i class="fas fa-upload"></i> Upload APK
                                    <input type="file" id="apk_file" name="apk_file" accept=".apk" class="file-input">
                                </label>
                                <?php if (!empty($app['apk_file'])): ?>
                                    <div class="current-file">Current: <?= htmlspecialchars($app['apk_file']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="external_url">External Download URL (if not uploading APK)</label>
                            <input type="url" id="external_url" name="external_url" class="form-control" value="<?= htmlspecialchars($app['external_url']) ?>">
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn"><i class="fas fa-save"></i> Update App</button>
                            <a href="all_apps.php" class="btn" style="background: var(--danger-color);">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        document.getElementById('icon')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.file-upload-preview');
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                }
                reader.readAsDataURL(file);
            }
        });
        
        function updateSubcategories() {
            const mainCategoryId = document.getElementById('main_category_id').value;
            const subcategorySelect = document.getElementById('subcategory_id');
            
            // Clear existing options except the first one
            while (subcategorySelect.options.length > 1) {
                subcategorySelect.remove(1);
            }
            
            if (mainCategoryId) {
                // Use the categories data from PHP
                const categoriesData = <?php echo json_encode($categories); ?>;
                
                const selectedCategory = categoriesData.find(cat => cat.id == mainCategoryId);
                
                if (selectedCategory && selectedCategory.subcategories && selectedCategory.subcategories.length > 0) {
                    selectedCategory.subcategories.forEach(subcat => {
                        const option = document.createElement('option');
                        option.value = subcat.id;
                        option.textContent = subcat.name;
                        subcategorySelect.appendChild(option);
                    });
                    
                    // If the current category is a subcategory of the selected main category,
                    // select it in the subcategory dropdown
                    <?php if ($is_subcategory): ?>
                        if (mainCategoryId == <?= $parent_category_id ?>) {
                            subcategorySelect.value = <?= $current_category_id ?>;
                        }
                    <?php endif; ?>
                }
            }
        }
        
        // Rich text editor functions
        function formatText(command, value = null) {
            const editor = document.getElementById('description');
            
            // Focus the editor if it's not already focused
            editor.focus();
            
            try {
                if (command === 'createLink') {
                    const url = prompt('Enter the URL:');
                    if (url) {
                        document.execCommand(command, false, url);
                    }
                } else if (value) {
                    document.execCommand(command, false, value);
                } else {
                    document.execCommand(command, false, null);
                }
            } catch (e) {
                console.error('Error executing command:', e);
            }
        }
        
        // Media upload handler
        function insertMedia(type) {
            const inputId = `${type}-upload`;
            const input = document.getElementById(inputId);
            
            // Clear previous file selection
            input.value = '';
            
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (!file) return;
                
                const reader = new FileReader();
                reader.onload = function(event) {
                    const editor = document.getElementById('description');
                    editor.focus();
                    
                    if (type === 'image') {
                        document.execCommand('insertImage', false, event.target.result);
                    } else if (type === 'video') {
                        const videoElement = `<video controls src="${event.target.result}" style="max-width:100%;"></video>`;
                        document.execCommand('insertHTML', false, videoElement);
                    }
                };
                
                reader.readAsDataURL(file);
            };
            
            // Trigger file selection dialog
            input.click();
        }
        
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
            
            // Initialize subcategories based on selected main category
            updateSubcategories();
            
            // Also trigger the change event on the main category select to ensure subcategories are loaded
            document.getElementById('main_category_id').dispatchEvent(new Event('change'));
            
            // Sync editor content with hidden textarea before form submission
            const form = document.querySelector('form');
            const editorContent = document.getElementById('description');
            const hiddenTextarea = document.getElementById('description-hidden');
            
            if (form && editorContent && hiddenTextarea) {
                form.addEventListener('submit', function() {
                    hiddenTextarea.value = editorContent.innerHTML;
                });
            }
        });
    </script>
</body>
</html>
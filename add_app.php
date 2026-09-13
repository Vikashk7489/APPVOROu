<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Get all categories with hierarchy
$categories = getCategories();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_app'])) {
    csrf_verify();
    $name = trim($_POST['name']);
    $version = trim($_POST['version']);
    $description = trim($_POST['description']);
    $main_category_id = $_POST['main_category_id'];
    $subcategory_id = $_POST['subcategory_id'];
    $size = trim($_POST['size']);
    $external_url = trim($_POST['external_url']);
    $is_editor_choice = isset($_POST['is_editor_choice']) ? 1 : 0;
    $is_recommended = isset($_POST['is_recommended']) ? 1 : 0;
    
    // Validate required fields
    $required = [
        'name' => 'App Name',
        'version' => 'Version',
        'main_category_id' => 'Main Category',
        'subcategory_id' => 'Subcategory'
    ];
    
    foreach ($required as $field => $label) {
        if (empty($$field)) {
            $error = "$label is required";
            break;
        }
    }
    
    if (empty($error)) {
        $slug = slugify($name);
        
        try {
            // Handle file uploads
            $iconFilename = null;
            $apkFilename = null;
            
            // Icon upload
            if (!empty($_FILES['icon']['name'])) {
                $iconUpload = uploadFile($_FILES['icon'], ICON_DIR);
                if (!$iconUpload['success']) {
                    throw new Exception($iconUpload['message']);
                }
                $iconFilename = $iconUpload['filename'];
            } else {
                throw new Exception('App icon is required');
            }
            
            // APK or external URL
            if (empty($_FILES['apk_file']['name']) && empty($external_url)) {
                throw new Exception('Either APK file or external URL is required');
            }
            
            if (!empty($_FILES['apk_file']['name'])) {
                $apkUpload = uploadFile($_FILES['apk_file'], APK_DIR, ['application/vnd.android.package-archive', 'application/octet-stream']);
                if (!$apkUpload['success']) {
                    throw new Exception($apkUpload['message']);
                }
                $apkFilename = $apkUpload['filename'];
            }
            
            // Insert app into database
            $stmt = $pdo->prepare("
                INSERT INTO apps 
                (name, slug, version, description, icon, apk_file, size, external_url, category_id, is_editor_choice, is_recommended) 
                VALUES 
                (:name, :slug, :version, :description, :icon, :apk_file, :size, :external_url, :category_id, :is_editor_choice, :is_recommended)
            ");
            
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':version' => $version,
                ':description' => $description,
                ':icon' => $iconFilename,
                ':apk_file' => $apkFilename,
                ':size' => $size,
                ':external_url' => $external_url,
                ':category_id' => $subcategory_id,
                ':is_editor_choice' => $is_editor_choice,
                ':is_recommended' => $is_recommended
            ]);
            
            $success = 'App added successfully!';
            $_POST = []; // Clear form
            
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New App - AppCenter Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/add_app.css">
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
                <a href="add_app.php" class="menu-item active"><span>➕</span> Add New App</a>
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
                    <h1>Add New App</h1>
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
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= $success ?></div>
            <?php endif; ?>
            
            <div class="card">
                <form action="add_app.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="name" class="required">App Name</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="version" class="required">Version</label>
                        <input type="text" id="version" name="version" class="form-control" 
                               value="<?= isset($_POST['version']) ? htmlspecialchars($_POST['version']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
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
                        <div id="description" class="editor-content" contenteditable="true"><?= isset($_POST['description']) ? htmlspecialchars_decode($_POST['description']) : '' ?></div>
                        <textarea id="description-hidden" name="description" style="display:none;"></textarea>
                        
                        <!-- Hidden file inputs for media upload -->
                        <input type="file" id="image-upload" accept="image/*" style="display:none;">
                        <input type="file" id="video-upload" accept="video/*" style="display:none;">
                    </div>
                    
                    <div class="category-select-container">
                        <div class="category-select-box">
                            <label for="main_category_id" class="required">Main Category</label>
                            <select id="main_category_id" name="main_category_id" class="category-select" required onchange="updateSubcategories()">
                                <option value="">-- Select Main Category --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" 
                                        <?= (isset($_POST['main_category_id']) && $_POST['main_category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="category-select-box">
                            <label for="subcategory_id" class="required">Subcategory</label>
                            <select id="subcategory_id" name="subcategory_id" class="category-select" required>
                                <option value="">-- Select Subcategory --</option>
                                <?php 
                                if (isset($_POST['main_category_id'])) {
                                    $selectedMainCategory = null;
                                    foreach ($categories as $category) {
                                        if ($category['id'] == $_POST['main_category_id']) {
                                            $selectedMainCategory = $category;
                                            break;
                                        }
                                    }
                                    
                                    if ($selectedMainCategory && !empty($selectedMainCategory['subcategories'])) {
                                        foreach ($selectedMainCategory['subcategories'] as $subcategory) {
                                            echo '<option value="' . $subcategory['id'] . '"';
                                            if (isset($_POST['subcategory_id']) && $_POST['subcategory_id'] == $subcategory['id']) {
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
                        <label for="size">File Size (e.g., 15MB)</label>
                        <input type="text" id="size" name="size" class="form-control" 
                               value="<?= isset($_POST['size']) ? htmlspecialchars($_POST['size']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="required">App Icon</label>
                        <div class="file-upload-wrapper">
                            <img id="icon-preview" class="file-upload-preview" src="#" alt="Icon preview">
                            <button type="button" class="file-upload-button uplod_icon">
                                <i class="fas fa-upload"></i> Upload Icon
                            </button>
                            <input type="file" id="icon" name="icon" class="file-upload-input" accept="image/*" required>
                            <div id="icon-name" class="file-upload-name">No file selected</div>
                        </div>
                        <small class="text-muted">Recommended size: 150x150 or 512x512 pixels</small>
                        
                    </div>
                    
                    <div class="form-group">
                        <label>APK File or External URL</label>
                        <div class="file-upload-wrapper">
                            <button type="button" id="apk_upload_button" class="file-upload-button disabled">
                                <i class="fas fa-upload"></i> Upload APK
                            </button>
                            <input type="file" id="apk_file" name="apk_file" class="file-upload-input" accept=".apk,application/vnd.android.package-archive">
                            <div id="apk-name" class="file-upload-name">No file selected</div>
                        </div>
                        <small class="text-muted">Either upload an APK or provide an external download URL below</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="external_url">External Download URL (optional)</label>
                        <input type="url" id="external_url" name="external_url" class="form-control" 
                               value="<?= isset($_POST['external_url']) ? htmlspecialchars($_POST['external_url']) : '' ?>">
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_editor_choice" name="is_editor_choice" value="1" <?= isset($_POST['is_editor_choice']) ? 'checked' : '' ?>>
                        <label for="is_editor_choice">Editor's Choice</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_recommended" name="is_recommended" value="1" <?= isset($_POST['is_recommended']) ? 'checked' : '' ?>>
                        <label for="is_recommended">Recommended App</label>
                    </div>
                    
                    <button type="submit" name="add_app" id="add_app_btn" class="btn" disabled><i class="fas fa-plus"></i> Add App</button>
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
        
        // Image preview for icon upload
        const iconInput = document.getElementById('icon');
        const iconPreview = document.getElementById('icon-preview');
        const iconName = document.getElementById('icon-name');
        
        iconInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                iconName.textContent = file.name;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    iconPreview.src = e.target.result;
                    iconPreview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                iconName.textContent = 'No file selected';
                iconPreview.style.display = 'none';
            }
            checkFormValidity();
        });
        
        // APK file name display and interaction with external URL
        const apkInput = document.getElementById('apk_file');
        const apkName = document.getElementById('apk-name');
        const apkUploadButton = document.getElementById('apk_upload_button');
        const externalUrlInput = document.getElementById('external_url');
        const addAppBtn = document.getElementById('add_app_btn');
        
        apkInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                apkName.textContent = file.name;
                // If APK is selected, clear external URL
                externalUrlInput.value = '';
            } else {
                apkName.textContent = 'No file selected';
            }
            checkFormValidity();
        });
        
        externalUrlInput.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                // If external URL is entered, disable APK upload
                apkInput.disabled = true;
                apkUploadButton.disabled = true;
                // Clear any selected APK file
                apkInput.value = '';
                apkName.textContent = 'No file selected';
            } else {
                // If external URL is cleared, enable APK upload
                apkInput.disabled = false;
                apkUploadButton.disabled = false;
                apkUploadButton.style.cursor = 'pointer';
                apkUploadButton.style.opacity = '1';
            }
            checkFormValidity();
        });
        
        // Sync editor content with hidden textarea before form submission
        const form = document.querySelector('form');
        const editorContent = document.getElementById('description');
        const hiddenTextarea = document.getElementById('description-hidden');
        
        form.addEventListener('submit', function() {
            hiddenTextarea.value = editorContent.innerHTML;
        });
        
        // Check if either APK file or external URL is provided
        function checkFormValidity() {
            const hasApkFile = apkInput.files && apkInput.files.length > 0;
            const hasExternalUrl = externalUrlInput.value.trim() !== '';
            const hasIcon = iconInput.files && iconInput.files.length > 0;
            
            // Enable button only if either APK file or external URL is provided AND icon is uploaded
            addAppBtn.disabled = !((hasApkFile || hasExternalUrl) && hasIcon);
        }
        
        // Initialize subcategories based on selected main category
        updateSubcategories();
    });
    
    setTimeout(()=>document.querySelectorAll('.alert').forEach(e=>e.classList.add('hide')),3000);
    
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
            } else {
                // If no subcategories exist, show a message
                const option = document.createElement('option');
                option.value = '';
                option.textContent = '-- No subcategories available --';
                option.disabled = true;
                subcategorySelect.appendChild(option);
            }
        }
    }
    
    function validateForm() {
        const mainCategory = document.getElementById('main_category_id').value;
        const subCategory = document.getElementById('subcategory_id').value;
        
        if (!mainCategory || !subCategory) {
            alert('Please select both a main category and a subcategory');
            return false;
        }
        
        // Check if subcategory is valid (not the disabled "no subcategories" option)
        if (subCategory === '' && document.getElementById('subcategory_id').options[1]?.disabled) {
            alert('This category has no subcategories. Please choose a different category.');
            return false;
        }
        
        // Check if icon is uploaded
        const iconInput = document.getElementById('icon');
        if (!iconInput.files || iconInput.files.length === 0) {
            alert('App icon is required');
            return false;
        }
        
        // Check if either APK or external URL is provided
        const apkInput = document.getElementById('apk_file');
        const externalUrl = document.getElementById('external_url').value;
        if ((!apkInput.files || apkInput.files.length === 0) && !externalUrl) {
            alert('Either APK file or external URL is required');
            return false;
        }
        
        return true;
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
    </script>
</body>
</html>
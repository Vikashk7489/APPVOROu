<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$pages = [];
$current_page = null;
$edit_mode = isset($_GET['edit']);
$content_type = 'text'; // Default content type

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['create_page'])) {
            // Create new page
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $meta_title = trim($_POST['meta_title']);
            $meta_description = trim($_POST['meta_description']);
            $content_type = $_POST['content_type'] ?? 'text';
            $slug = isset($_POST['slug']) ? trim($_POST['slug']) : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug))); // Clean it up
            
            $stmt = $pdo->prepare("INSERT INTO pages (title, slug, content, meta_title, meta_description, content_type) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $content, $meta_title, $meta_description, $content_type]);
            
            $_SESSION['success_message'] = "Page created successfully!";
        }
        elseif (isset($_POST['update_page'])) {
            // Update existing page
            $page_id = $_POST['page_id'];
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $meta_title = trim($_POST['meta_title']);
            $meta_description = trim($_POST['meta_description']);
            $content_type = $_POST['content_type'] ?? 'text';
            $slug = isset($_POST['slug']) ? trim($_POST['slug']) : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug))); // Clean it up
            
            $stmt = $pdo->prepare("UPDATE pages SET 
                                  title = ?, 
                                  slug = ?,
                                  content = ?, 
                                  meta_title = ?, 
                                  meta_description = ?,
                                  content_type = ?,
                                  updated_at = NOW() 
                                  WHERE id = ?");
            $stmt->execute([$title, $slug, $content, $meta_title, $meta_description, $content_type, $page_id]);
            
            $_SESSION['success_message'] = "Page updated successfully!";
        }
        
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        error_log("Page operation error: " . $e->getMessage());
    }
    
    header("Location: page.php");
    exit();
}

// Handle page deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $_SESSION['success_message'] = "Page deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting page: " . $e->getMessage();
    }
    
    header("Location: page.php");
    exit();
}

// Get current page for editing
if ($edit_mode && isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $current_page = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_page) {
            $_SESSION['error_message'] = "Page not found!";
            header("Location: page.php");
            exit();
        }
        
        // Set content type for existing page
        $content_type = $current_page['content_type'] ?? 'text';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error loading page: " . $e->getMessage();
        header("Location: page.php");
        exit();
    }
}

// Get all pages
try {
    $pages = $pdo->query("SELECT * FROM pages ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error loading pages: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_mode ? 'Edit Page' : 'Manage Pages' ?> - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/page.css">
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
                <a href="page.php" class="menu-item active"><span>📄</span> Manage Pages</a>
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
                    <h1><?= $edit_mode ? 'Edit Page' : 'Manage Pages' ?></h1>
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
            
            <!-- Display messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success" id="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?= $_SESSION['success_message'] ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $_SESSION['error_message'] ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <?php if ($edit_mode): ?>
                <!-- Edit/Create Page Form -->
                <a href="page.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Pages
                </a>
                
                <div class="page-form">
                    <form action="page.php<?= $current_page ? '?edit=true&id='.$current_page['id'] : '' ?>" method="POST" id="page-form">
                        <?= csrf_field() ?>
                        <?php if ($current_page): ?>
                            <input type="hidden" name="page_id" value="<?= $current_page['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="title" class="form-label">Page Title*</label>
                            <input type="text" id="title" name="title" class="form-control" 
                                   value="<?= htmlspecialchars($current_page['title'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="slug" class="form-label">Page Slug (URL)*</label>
                            <input type="text" id="slug" name="slug" class="form-control" 
                                   value="<?= htmlspecialchars($current_page['slug'] ?? '') ?>" required>
                            <small style="display: block; margin-top: 5px; color: #666;">This will be used in the page URL (e.g., <?= SITE_URL ?>/page/<strong>your-slug</strong>)</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Content Type</label>
                            <div class="content-type-toggle">
                                <button type="button" class="content-type-btn <?= $content_type === 'text' ? 'active' : '' ?>" data-type="text">
                                    <i class="fas fa-align-left"></i> Text
                                </button>
                                <button type="button" class="content-type-btn <?= $content_type === 'html' ? 'active' : '' ?>" data-type="html">
                                    <i class="fas fa-code"></i> HTML
                                </button>
                            </div>
                            <input type="hidden" id="content_type" name="content_type" value="<?= $content_type ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="content" class="form-label">Content*</label>
                            <div id="text-editor-container" style="<?= $content_type === 'html' ? 'display: none;' : '' ?>">
                                <textarea id="content" name="content" class="form-control" required><?= 
                                    htmlspecialchars($current_page['content'] ?? '') ?></textarea>
                            </div>
                            <div id="html-editor-container" style="<?= $content_type === 'text' ? 'display: none;' : '' ?>">
                                <textarea id="html_content" name="html_content" class="form-control" style="font-family: monospace;"><?= 
                                    htmlspecialchars($current_page['content'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="meta_title" class="form-label">Meta Title</label>
                            <input type="text" id="meta_title" name="meta_title" class="form-control" 
                                   value="<?= htmlspecialchars($current_page['meta_title'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="meta_description" class="form-label">Meta Description</label>
                            <textarea id="meta_description" name="meta_description" class="form-control"><?= 
                                htmlspecialchars($current_page['meta_description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-footer">
                            <a href="page.php" class="btn btn-delete">Cancel</a>
                            <button type="submit" name="<?= $current_page ? 'update_page' : 'create_page' ?>" class="btn add-new-btn">
                                <?= $current_page ? 'Update Page' : 'Create Page' ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Page Listing -->
                <div class="pages-container">
                    <div class="section-header">
                        <h2 class="section-title">All Pages</h2>
                        <a href="page.php?edit=true" class="add-new-btn">
                            <i class="fas fa-plus"></i> Add New Page
                        </a>
                    </div>
                    
                    <?php if (count($pages) > 0): ?>
                        <!-- Desktop Table -->
                        <table class="pages-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Slug</th>
                                    <th>Type</th>
                                    <th>Created</th>
                                    <th>Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pages as $page): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($page['title']) ?></td>
                                        <td><?= htmlspecialchars($page['slug']) ?></td>
                                        <td><?= strtoupper($page['content_type']) ?></td>
                                        <td><?= date('M d, Y', strtotime($page['created_at'])) ?></td>
                                        <td><?= date('M d, Y', strtotime($page['updated_at'])) ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="<?= SITE_URL ?>/page/<?= $page['slug'] ?>" target="_blank" class="btn btn-preview">
                                                    <i class="fas fa-eye"></i> Preview
                                                </a>
                                                <button class="btn btn-copy-url" data-url="<?= SITE_URL ?>/page/<?= $page['slug'] ?>">
                                                    <i class="fas fa-copy"></i> Copy URL
                                                </button>
                                                <a href="page.php?edit=true&id=<?= $page['id'] ?>" class="btn btn-edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="page.php?delete=<?= $page['id'] ?>" class="btn btn-delete" 
                                                   onclick="return confirm('Are you sure you want to delete this page?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Mobile Cards -->
                        <div class="mobile-pages-list">
                            <?php foreach ($pages as $page): ?>
                                <div class="page-card">
                                    <div class="page-card-header">
                                        <h3 class="page-card-title" title="<?= htmlspecialchars($page['title']) ?>">
                                            <?= htmlspecialchars($page['title']) ?>
                                        </h3>
                                        <span class="page-card-type"><?= strtoupper($page['content_type']) ?></span>
                                    </div>
                                    <div class="page-card-body">
                                        <div class="page-card-row">
                                            <span class="page-card-label">URL Slug:</span>
                                            <span class="page-card-value"><?= htmlspecialchars($page['slug']) ?></span>
                                        </div>
                                        <div class="page-card-row">
                                            <span class="page-card-label">Created:</span>
                                            <span class="page-card-value"><?= date('M d, Y', strtotime($page['created_at'])) ?></span>
                                        </div>
                                        <div class="page-card-row">
                                            <span class="page-card-label">Updated:</span>
                                            <span class="page-card-value"><?= date('M d, Y', strtotime($page['updated_at'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="page-card-actions">
                                        <a href="<?= SITE_URL ?>/page/<?= $page['slug'] ?>" target="_blank" class="page-card-btn btn-preview">
                                            <i class="fas fa-eye"></i> Preview
                                        </a>
                                        <button class="page-card-btn btn-copy-url" data-url="<?= SITE_URL ?>/page/<?= $page['slug'] ?>">
                                            <i class="fas fa-copy"></i> Copy URL
                                        </button>
                                        <a href="page.php?edit=true&id=<?= $page['id'] ?>" class="page-card-btn btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="page.php?delete=<?= $page['id'] ?>" class="page-card-btn btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this page?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No pages found. Create your first page!</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
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
            
            // Initialize Summernote editor if we're in edit mode
            <?php if ($edit_mode): ?>
                $('#content').summernote({
                    height: 300,
                    placeholder: 'Write your page content here...',
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'clear']],
                        ['fontname', ['fontname']],
                        ['fontsize', ['fontsize']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['height', ['height']],
                        ['table', ['table']],
                        ['insert', ['link', 'picture', 'video', 'hr']],
                        ['view', ['fullscreen', 'codeview', 'help']]
                    ],
                    styleTags: ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
                    prettifyHtml: false,
                    allowedTags: [
                        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                        'p', 'div', 'span', 'br', 'hr',
                        'ul', 'ol', 'li',
                        'strong', 'em', 'b', 'i', 'u', 's',
                        'table', 'thead', 'tbody', 'tr', 'th', 'td',
                        'a', 'img', 'video',
                        'blockquote', 'pre', 'code'
                    ]
                });
                
                // Content type toggle functionality
                const contentTypeBtns = document.querySelectorAll('.content-type-btn');
                const contentTypeInput = document.getElementById('content_type');
                const textEditorContainer = document.getElementById('text-editor-container');
                const htmlEditorContainer = document.getElementById('html-editor-container');
                const textEditor = $('#content');
                const htmlEditor = document.getElementById('html_content');
                
                contentTypeBtns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        const type = this.dataset.type;
                        
                        // Update active button
                        contentTypeBtns.forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        
                        // Update hidden input
                        contentTypeInput.value = type;
                        
                        // Toggle editor visibility and sync content
                        if (type === 'text') {
                            // When switching to text editor, get the HTML content
                            const htmlContent = htmlEditor.value;
                            
                            textEditorContainer.style.display = 'block';
                            htmlEditorContainer.style.display = 'none';
                            
                            // Update Summernote with the HTML content
                            textEditor.summernote('code', htmlContent);
                        } else {
                            // When switching to HTML editor, get the HTML content from Summernote
                            const htmlContent = textEditor.summernote('code');
                            
                            textEditorContainer.style.display = 'none';
                            htmlEditorContainer.style.display = 'block';
                            
                            // Update HTML editor with the raw HTML
                            htmlEditor.value = htmlContent;
                        }
                    });
                });
                
                // Auto-generate slug from title, but allow manual override
                const titleInput = document.getElementById('title');
                const slugInput = document.getElementById('slug');
                
                titleInput.addEventListener('input', function() {
                    if (!slugInput.dataset.manual) {
                        const slug = titleInput.value.toLowerCase()
                            .replace(/[^a-z0-9]+/g, '-')
                            .replace(/^-+|-+$/g, '');
                        slugInput.value = slug;
                    }
                });
                
                slugInput.addEventListener('change', function() {
                    if (slugInput.value) {
                        slugInput.dataset.manual = true;
                    }
                });
                
                // Handle form submission to ensure proper content is submitted
                document.getElementById('page-form').addEventListener('submit', function(e) {
                    const contentType = contentTypeInput.value;
                    
                    if (contentType === 'text') {
                        // Get the HTML content from Summernote
                        const htmlContent = textEditor.summernote('code');
                        // Update the hidden content field that will be submitted
                        document.getElementById('content').value = htmlContent;
                    } else {
                        // For HTML editor, use the content from the HTML editor textarea
                        document.getElementById('content').value = htmlEditor.value;
                    }
                });
            <?php endif; ?>
            
            // Handle copy URL buttons (both desktop and mobile)
            document.querySelectorAll('.btn-copy-url, .page-card-btn.btn-copy-url').forEach(button => {
                button.addEventListener('click', function() {
                    const url = this.getAttribute('data-url');
                    navigator.clipboard.writeText(url).then(() => {
                        // Change button text temporarily to show success
                        const originalHTML = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-check"></i> Copied!';
                        
                        setTimeout(() => {
                            this.innerHTML = originalHTML;
                        }, 2000);
                    }).catch(err => {
                        console.error('Failed to copy URL: ', err);
                        alert('Failed to copy URL to clipboard');
                    });
                });
            });
        });
        
        setTimeout(()=>document.querySelectorAll('.alert').forEach(e=>e.classList.add('hide')),3000)
    </script>
</body>
</html>
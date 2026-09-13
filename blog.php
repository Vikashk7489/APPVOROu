<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

ensureBlogTable();

define('BLOG_IMG_DIR', UPLOAD_DIR . 'blog/');
if (!is_dir(BLOG_IMG_DIR)) {
    @mkdir(BLOG_IMG_DIR, 0755, true);
}

// Initialize variables
$posts = [];
$current_post = null;
$edit_mode = isset($_GET['edit']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $title = trim($_POST['title']);
        $excerpt = trim($_POST['excerpt']);
        $content = trim($_POST['content']);
        $meta_title = trim($_POST['meta_title']);
        $meta_description = trim($_POST['meta_description']);
        $status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
        $slug = isset($_POST['slug']) && trim($_POST['slug']) !== '' ? trim($_POST['slug']) : $title;
        $slug = slugify($slug);

        // Handle featured image upload (optional)
        $featured_image = $_POST['existing_image'] ?? null;
        if (!empty($_FILES['featured_image']['name'])) {
            $imageUpload = uploadFile($_FILES['featured_image'], BLOG_IMG_DIR);
            if ($imageUpload['success']) {
                // Remove old image when replacing on update
                if (!empty($featured_image) && file_exists(BLOG_IMG_DIR . $featured_image)) {
                    unlink(BLOG_IMG_DIR . $featured_image);
                }
                $featured_image = $imageUpload['filename'];
            } else {
                throw new Exception($imageUpload['message']);
            }
        }

        if (isset($_POST['create_post'])) {
            // Create new post
            $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, excerpt, content, featured_image, meta_title, meta_description, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $excerpt, $content, $featured_image, $meta_title, $meta_description, $status]);

            $_SESSION['success_message'] = "Blog post created successfully!";
        } elseif (isset($_POST['update_post'])) {
            // Update existing post
            $post_id = $_POST['post_id'];
            $stmt = $pdo->prepare("UPDATE blog_posts SET 
                                  title = ?, 
                                  slug = ?,
                                  excerpt = ?,
                                  content = ?, 
                                  featured_image = ?,
                                  meta_title = ?, 
                                  meta_description = ?,
                                  status = ?,
                                  updated_at = NOW() 
                                  WHERE id = ?");
            $stmt->execute([$title, $slug, $excerpt, $content, $featured_image, $meta_title, $meta_description, $status, $post_id]);

            $_SESSION['success_message'] = "Blog post updated successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error saving blog post: " . $e->getMessage();
        error_log("Blog post operation error: " . $e->getMessage());
    }
    
    header("Location: blog.php");
    exit();
}

// Handle post deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (deleteBlogPost($_GET['delete'])) {
        $_SESSION['success_message'] = "Blog post deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting blog post.";
    }
    
    header("Location: blog.php");
    exit();
}

// Get current post for editing
if ($edit_mode && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $current_post = getBlogPostById($_GET['id']);

    if (!$current_post) {
        $_SESSION['error_message'] = "Blog post not found!";
        header("Location: blog.php");
        exit();
    }
}

// Get all posts
try {
    $posts = getAllBlogPostsAdmin();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error loading blog posts: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_mode ? ($current_post ? 'Edit Blog Post' : 'New Blog Post') : 'Manage Blog' ?> - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/blog.css">
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
                <a href="blog.php" class="menu-item active"><span>✍️</span> Manage Blog</a>
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
                    <h1><?= $edit_mode ? ($current_post ? 'Edit Blog Post' : 'New Blog Post') : 'Manage Blog' ?></h1>
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
                <!-- Edit/Create Blog Post Form -->
                <a href="blog.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Blog
                </a>
                
                <div class="page-form">
                    <form action="blog.php<?= $current_post ? '?edit=true&id='.$current_post['id'] : '' ?>" method="POST" id="page-form" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <?php if ($current_post): ?>
                            <input type="hidden" name="post_id" value="<?= $current_post['id'] ?>">
                            <input type="hidden" name="existing_image" value="<?= htmlspecialchars($current_post['featured_image'] ?? '') ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="title" class="form-label">Post Title*</label>
                            <input type="text" id="title" name="title" class="form-control" 
                                   value="<?= htmlspecialchars($current_post['title'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="slug" class="form-label">Post Slug (URL)</label>
                            <input type="text" id="slug" name="slug" class="form-control" 
                                   value="<?= htmlspecialchars($current_post['slug'] ?? '') ?>">
                            <small style="display: block; margin-top: 5px; color: #666;">This will be used in the post URL (e.g., <?= SITE_URL ?>/blog/<strong>your-slug</strong>). Leave blank to auto-generate from the title.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Featured Image</label>
                            <div class="featured-image-upload">
                                <img id="featured-image-preview" 
                                     class="featured-image-preview <?= !empty($current_post['featured_image']) ? 'has-image' : '' ?>" 
                                     src="<?= !empty($current_post['featured_image']) ? SITE_URL . '/uploads/blog/' . htmlspecialchars($current_post['featured_image']) : '#' ?>" 
                                     style="<?= empty($current_post['featured_image']) ? 'display:none;' : '' ?>">
                                <div>
                                    <input type="file" id="featured_image" name="featured_image" accept="image/*">
                                    <small style="display: block; margin-top: 5px; color: #666;">Shown on the blog grid and at the top of the post. Recommended: 800x500px or wider.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="excerpt" class="form-label">Excerpt</label>
                            <textarea id="excerpt" name="excerpt" class="form-control" style="min-height: 90px;" 
                                      placeholder="A short summary shown on the blog grid card..."><?= 
                                htmlspecialchars($current_post['excerpt'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="content" class="form-label">Content*</label>
                            <textarea id="content" name="content" class="form-control" required><?= 
                                htmlspecialchars($current_post['content'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <div class="status-toggle">
                                <button type="button" class="status-toggle-btn <?= ($current_post['status'] ?? 'published') === 'published' ? 'active' : '' ?>" data-status="published">
                                    <i class="fas fa-check-circle"></i> Published
                                </button>
                                <button type="button" class="status-toggle-btn <?= ($current_post['status'] ?? 'published') === 'draft' ? 'active' : '' ?>" data-status="draft">
                                    <i class="fas fa-pencil-alt"></i> Draft
                                </button>
                            </div>
                            <input type="hidden" id="status" name="status" value="<?= htmlspecialchars($current_post['status'] ?? 'published') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="meta_title" class="form-label">Meta Title</label>
                            <input type="text" id="meta_title" name="meta_title" class="form-control" 
                                   value="<?= htmlspecialchars($current_post['meta_title'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="meta_description" class="form-label">Meta Description</label>
                            <textarea id="meta_description" name="meta_description" class="form-control"><?= 
                                htmlspecialchars($current_post['meta_description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-footer">
                            <a href="blog.php" class="btn btn-delete">Cancel</a>
                            <button type="submit" name="<?= $current_post ? 'update_post' : 'create_post' ?>" class="btn add-new-btn">
                                <?= $current_post ? 'Update Post' : 'Create Post' ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Blog Post Listing -->
                <div class="pages-container">
                    <div class="section-header">
                        <h2 class="section-title">All Blog Posts</h2>
                        <a href="blog.php?edit=true" class="add-new-btn">
                            <i class="fas fa-plus"></i> Add New Post
                        </a>
                    </div>
                    
                    <?php if (count($posts) > 0): ?>
                        <!-- Desktop Table -->
                        <table class="pages-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Title</th>
                                    <th>Slug</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($posts as $post): ?>
                                    <tr>
                                        <td class="thumb-cell">
                                            <?php if (!empty($post['featured_image'])): ?>
                                                <img src="<?= SITE_URL ?>/uploads/blog/<?= htmlspecialchars($post['featured_image']) ?>" alt="">
                                            <?php else: ?>
                                                <div class="thumb-placeholder"><i class="fas fa-image"></i></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($post['title']) ?></td>
                                        <td><?= htmlspecialchars($post['slug']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $post['status'] ?>"><?= ucfirst($post['status']) ?></span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($post['created_at'])) ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="<?= SITE_URL ?>/blog/<?= $post['slug'] ?>" target="_blank" class="btn btn-preview">
                                                    <i class="fas fa-eye"></i> Preview
                                                </a>
                                                <button class="btn btn-copy-url" data-url="<?= SITE_URL ?>/blog/<?= $post['slug'] ?>">
                                                    <i class="fas fa-copy"></i> Copy URL
                                                </button>
                                                <a href="blog.php?edit=true&id=<?= $post['id'] ?>" class="btn btn-edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="blog.php?delete=<?= $post['id'] ?>" class="btn btn-delete" 
                                                   onclick="return confirm('Are you sure you want to delete this blog post?');">
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
                            <?php foreach ($posts as $post): ?>
                                <div class="page-card">
                                    <?php if (!empty($post['featured_image'])): ?>
                                        <img class="page-card-thumb" src="<?= SITE_URL ?>/uploads/blog/<?= htmlspecialchars($post['featured_image']) ?>" alt="">
                                    <?php endif; ?>
                                    <div class="page-card-header">
                                        <h3 class="page-card-title" title="<?= htmlspecialchars($post['title']) ?>">
                                            <?= htmlspecialchars($post['title']) ?>
                                        </h3>
                                        <span class="status-badge status-<?= $post['status'] ?>"><?= ucfirst($post['status']) ?></span>
                                    </div>
                                    <div class="page-card-body">
                                        <div class="page-card-row">
                                            <span class="page-card-label">URL Slug:</span>
                                            <span class="page-card-value"><?= htmlspecialchars($post['slug']) ?></span>
                                        </div>
                                        <div class="page-card-row">
                                            <span class="page-card-label">Created:</span>
                                            <span class="page-card-value"><?= date('M d, Y', strtotime($post['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="page-card-actions">
                                        <a href="<?= SITE_URL ?>/blog/<?= $post['slug'] ?>" target="_blank" class="page-card-btn btn-preview">
                                            <i class="fas fa-eye"></i> Preview
                                        </a>
                                        <button class="page-card-btn btn-copy-url" data-url="<?= SITE_URL ?>/blog/<?= $post['slug'] ?>">
                                            <i class="fas fa-copy"></i> Copy URL
                                        </button>
                                        <a href="blog.php?edit=true&id=<?= $post['id'] ?>" class="page-card-btn btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="blog.php?delete=<?= $post['id'] ?>" class="page-card-btn btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this blog post?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No blog posts found. Create your first post!</p>
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
                const contentEditor = $('#content');
                contentEditor.summernote({
                    height: 350,
                    placeholder: 'Write your blog post here...',
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

                // Status toggle functionality
                const statusBtns = document.querySelectorAll('.status-toggle-btn');
                const statusInput = document.getElementById('status');

                statusBtns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        statusBtns.forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        statusInput.value = this.dataset.status;
                    });
                });

                // Featured image preview
                const imageInput = document.getElementById('featured_image');
                const imagePreview = document.getElementById('featured-image-preview');

                imageInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imagePreview.src = e.target.result;
                            imagePreview.style.display = 'block';
                            imagePreview.classList.add('has-image');
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
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
                    document.getElementById('content').value = contentEditor.summernote('code');
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
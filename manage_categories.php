<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['success']);

// Function to check if category exists (either as main or sub)
function categoryExists($pdo, $name, $parentId = null) {
    // Check if name exists as a main category
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
    $stmt->execute([$name]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        return true;
    }
    
    // If this is a subcategory, check if the parent + child combination exists
    if ($parentId !== null) {
        $parentName = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $parentName->execute([$parentId]);
        $parentName = $parentName->fetchColumn();
        
        if ($parentName) {
            $combinedName = $parentName . ' > ' . $name;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
            $stmt->execute([$combinedName]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                return true;
            }
        }
    }
    
    return false;
}

// Handle add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    csrf_verify();
    $name = trim($_POST['name']);
    $categoryType = $_POST['category_type'];
    
    if (empty($name)) {
        $error = 'Category name is required';
    } else {
        $parentId = null;
        $combinedName = $name;
        
        if ($categoryType === 'sub') {
            $parentId = $_POST['parent_category'];
            $parentName = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
            $parentName->execute([$parentId]);
            $parentName = $parentName->fetchColumn();
            
            if (!$parentName) {
                $error = 'Invalid parent category selected';
            } else {
                $combinedName = $parentName . ' > ' . $name;
                
                // Check if this exact subcategory already exists
                if (categoryExists($pdo, $name, $parentId)) {
                    $error = 'This subcategory already exists under the selected parent category.';
                }
            }
        } else {
            // Check if this main category already exists
            if (categoryExists($pdo, $name)) {
                $error = 'A category with this name already exists (either as a main category or subcategory).';
            }
        }
        
        if (empty($error)) {
            $slug = slugify($combinedName);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (:name, :slug)");
                $stmt->bindParam(':name', $combinedName);
                $stmt->bindParam(':slug', $slug);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Category added successfully!';
                    header("Location: manage_categories.php");
                    exit();
                } else {
                    $error = 'Failed to add category. Please try again.';
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'Category with this name already exists.';
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Handle edit category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    csrf_verify();
    $category_id = $_POST['category_id'];
    $name = trim($_POST['name']);
    $categoryType = $_POST['category_type'];
    
    if (empty($name)) {
        $error = 'Category name is required';
    } else {
        $parentId = null;
        $combinedName = $name;
        
        // Get the original category data
        $originalCategory = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $originalCategory->execute([$category_id]);
        $originalCategory = $originalCategory->fetch(PDO::FETCH_ASSOC);
        
        if ($categoryType === 'sub') {
            $parentId = $_POST['parent_category'];
            $parentName = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
            $parentName->execute([$parentId]);
            $parentName = $parentName->fetchColumn();
            
            if (!$parentName) {
                $error = 'Invalid parent category selected';
            } else {
                $combinedName = $parentName . ' > ' . $name;
                
                // Check if this exact subcategory already exists (excluding current category)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND id != ?");
                $stmt->execute([$combinedName, $category_id]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $error = 'This subcategory already exists under the selected parent category.';
                }
            }
        } else {
            // Check if this main category already exists (excluding current category)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND id != ?");
            $stmt->execute([$name, $category_id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $error = 'A category with this name already exists (either as a main category or subcategory).';
            }
        }
        
        if (empty($error)) {
            $slug = slugify($combinedName);
            
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // Update the category
                $stmt = $pdo->prepare("UPDATE categories SET name = :name, slug = :slug WHERE id = :id");
                $stmt->bindParam(':name', $combinedName);
                $stmt->bindParam(':slug', $slug);
                $stmt->bindParam(':id', $category_id);
                
                if ($stmt->execute()) {
                    // If this was a main category and its name changed, update all its subcategories
                    if ($categoryType === 'main') {
                        // Check if the name actually changed
                        if ($originalCategory['name'] !== $combinedName) {
                            // Get all subcategories of this main category (name starts with original name + ' > ')
                            $subcategories = $pdo->prepare("SELECT id, name FROM categories WHERE name LIKE ? AND id != ?");
                            $subcategories->execute([$originalCategory['name'] . ' > %', $category_id]);
                            $subcategories = $subcategories->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($subcategories as $subcat) {
                                // Extract the subcategory name part (after ' > ')
                                $subcatName = substr($subcat['name'], strpos($subcat['name'], ' > ') + 3);
                                $newSubcatName = $combinedName . ' > ' . $subcatName;
                                $newSubcatSlug = slugify($newSubcatName);
                                
                                // Update the subcategory
                                $updateSub = $pdo->prepare("UPDATE categories SET name = :name, slug = :slug WHERE id = :id");
                                $updateSub->bindParam(':name', $newSubcatName);
                                $updateSub->bindParam(':slug', $newSubcatSlug);
                                $updateSub->bindParam(':id', $subcat['id']);
                                $updateSub->execute();
                            }
                        }
                    }
                    
                    $pdo->commit();
                    $_SESSION['success'] = 'Category updated successfully!';
                    header("Location: manage_categories.php");
                    exit();
                } else {
                    $pdo->rollBack();
                    $error = 'Failed to update category. Please try again.';
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                if ($e->getCode() == 23000) {
                    $error = 'Category with this name already exists.';
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Handle delete category
if (isset($_GET['delete'])) {
    $category_id = $_GET['delete'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM apps WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        $error = 'Cannot delete category because it is used by one or more apps.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        if ($stmt->execute([$category_id])) {
            $_SESSION['success'] = 'Category deleted successfully!';
            header("Location: manage_categories.php");
            exit();
        } else {
            $error = 'Failed to delete category. Please try again.';
        }
    }
}

// Get category to edit
$editCategory = null;
if (isset($_GET['edit'])) {
    $category_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $editCategory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$editCategory) {
        $error = 'Category not found.';
    } else {
        if (strpos($editCategory['name'], ' > ') !== false) {
            list($parentName, $childName) = explode(' > ', $editCategory['name'], 2);
            $editCategory['parent_name'] = $parentName;
            $editCategory['name'] = $childName;
        }
    }
}

// Get all categories
$categories = getCategories(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/manage_categories.css">
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
                <a href="manage_categories.php" class="menu-item active"><span>🗃️</span> Manage Categories</a> 
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
                    <h1>Manage Categories</h1>
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
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?= isset($editCategory) ? 'Edit Category' : 'Add New Category' ?></h2>
                <form action="manage_categories.php" method="POST">
                    <?= csrf_field() ?>
                    <?php if (isset($editCategory)): ?>
                        <input type="hidden" name="category_id" value="<?= $editCategory['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="category_type">Category Type</label>
                        <select id="category_type" name="category_type" class="form-control" onchange="toggleParentCategory()">
                            <option value="main" <?= (isset($editCategory) && !isset($editCategory['parent_name'])) ? 'selected' : '' ?>>Main Category</option>
                            <option value="sub" <?= (isset($editCategory) && isset($editCategory['parent_name'])) ? 'selected' : '' ?>>Subcategory</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="parent_category_group" style="<?= (isset($editCategory) && isset($editCategory['parent_name'])) ? 'display:block;' : 'display:none;' ?>">
                        <label for="parent_category">Parent Category</label>
                        <select id="parent_category" name="parent_category" class="form-control">
                            <?php 
                            $mainCategories = $pdo->query("
                                SELECT * FROM categories 
                                WHERE name NOT LIKE '% > %'
                                ORDER BY name ASC
                            ")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($mainCategories as $cat): 
                                $selected = (isset($editCategory) && isset($editCategory['parent_name']) && $editCategory['parent_name'] == $cat['name']) ? 'selected' : '';
                            ?>
                                <option value="<?= $cat['id'] ?>" <?= $selected ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Category Name *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?= isset($editCategory) ? htmlspecialchars($editCategory['name']) : '' ?>" required>
                    </div>
                    
                    <?php if (isset($editCategory)): ?>
                        <button type="submit" name="edit_category" class="btn"><i class="fas fa-save"></i> Update Category</button>
                        <a href="manage_categories.php" class="btn btn-danger">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="add_category" class="btn"><i class="fas fa-plus"></i> Add Category</button>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="card">
                <h2>Category List</h2>
                
                <?php if (count($categories) > 0): ?>
                    <!-- Desktop Table View -->
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th>Total Apps</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>
                                            <div class="category-indicator">
                                                <i class="fas fa-folder main-category-icon"></i>
                                                <?= htmlspecialchars($category['name']) ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($category['slug']) ?></td>
                                        <td><?= $category['app_count'] ?></td>
                                        <td>
                                            <a href="manage_categories.php?edit=<?= $category['id'] ?>" class="action-btn edit"><i class="fas fa-edit"></i> Edit</a>
                                            <?php if (empty($category['subcategories'])): ?>
                                                <a href="manage_categories.php?delete=<?= $category['id'] ?>" class="action-btn delete"><i class="fas fa-trash-alt"></i> Delete</a>
                                            <?php else: ?>
                                                <span class="action-btn delete-inactive"><i class="fas fa-trash-alt"></i> Delete</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php foreach ($category['subcategories'] as $subcategory): ?>
                                        <tr class="subcategory-row">
                                            <td>
                                                <div class="category-indicator">
                                                    <i class="fas fa-folder-open sub-category-icon"></i>
                                                    <?= htmlspecialchars($subcategory['name']) ?>
                                                    <span class="subcategory-badge">Subcategory</span>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($subcategory['slug']) ?></td>
                                            <td><?= $subcategory['app_count'] ?></td>
                                            <td>
                                                <a href="manage_categories.php?edit=<?= $subcategory['id'] ?>" class="action-btn edit"><i class="fas fa-edit"></i> Edit</a>
                                                <a href="manage_categories.php?delete=<?= $subcategory['id'] ?>" class="action-btn delete"><i class="fas fa-trash-alt"></i> Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Mobile Card View -->
                    <div class="mobile-cards-container">
                        <?php foreach ($categories as $category): ?>
                            <div class="mobile-category-card main-category">
                                <div class="mobile-card-header">
                                    <div class="mobile-category-name">
                                        <i class="fas fa-folder main-category-icon"></i>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </div>
                                    <span class="app-count"><?= $category['app_count'] ?> apps</span>
                                </div>
                                
                                <div class="mobile-card-body">
                                    <div class="mobile-card-row">
                                        <span class="mobile-card-label">Slug:</span>
                                        <span class="mobile-card-value"><?= htmlspecialchars($category['slug']) ?></span>
                                    </div>
                                    <div class="mobile-card-row">
                                        <span class="mobile-card-label">Type:</span>
                                        <span class="mobile-card-value">Main Category</span>
                                    </div>
                                </div>
                                
                                <div class="mobile-card-footer">
                                    <a href="manage_categories.php?edit=<?= $category['id'] ?>" class="action-btn edit"><i class="fas fa-edit"></i> Edit</a>
                                    <?php if (empty($category['subcategories'])): ?>
                                        <a href="manage_categories.php?delete=<?= $category['id'] ?>" class="action-btn delete"><i class="fas fa-trash-alt"></i> Delete</a>
                                    <?php else: ?>
                                        <span class="action-btn delete-inactive"><i class="fas fa-trash-alt"></i> Delete</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php foreach ($category['subcategories'] as $subcategory): ?>
                                <div class="mobile-category-card sub-category">
                                    <div class="mobile-card-header">
                                        <div class="mobile-category-name">
                                            <i class="fas fa-folder-open sub-category-icon"></i>
                                            <?= htmlspecialchars($subcategory['name']) ?>
                                        </div>
                                        <span class="app-count"><?= $subcategory['app_count'] ?> apps</span>
                                    </div>
                                    
                                    <div class="mobile-card-body">
                                        <div class="mobile-card-row">
                                            <span class="mobile-card-label">Slug:</span>
                                            <span class="mobile-card-value"><?= htmlspecialchars($subcategory['slug']) ?></span>
                                        </div>
                                        <div class="mobile-card-row">
                                            <span class="mobile-card-label">Type:</span>
                                            <span class="mobile-card-value">Subcategory</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mobile-card-footer">
                                        <a href="manage_categories.php?edit=<?= $subcategory['id'] ?>" class="action-btn edit"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="manage_categories.php?delete=<?= $subcategory['id'] ?>" class="action-btn delete"><i class="fas fa-trash-alt"></i> Delete</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-categories">
                        <p>No categories found. Add your first category!</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle functionality
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

    // Function to automatically set parent category when editing a subcategory
    function autoSetParentCategory() {
        const categoryType = document.getElementById('category_type');
        const parentCategoryGroup = document.getElementById('parent_category_group');
        const parentCategorySelect = document.getElementById('parent_category');
        
        // Check if we're editing a subcategory
        if (categoryType.value === 'sub' && parentCategoryGroup.style.display === 'block') {
            // Find the parent category option that matches the current parent name
            const options = parentCategorySelect.options;
            const currentParentName = '<?= isset($editCategory['parent_name']) ? addslashes($editCategory['parent_name']) : '' ?>';
            
            if (currentParentName) {
                for (let i = 0; i < options.length; i++) {
                    if (options[i].text === currentParentName) {
                        parentCategorySelect.selectedIndex = i;
                        break;
                    }
                }
            }
        }
    }
    
    setTimeout(()=>document.querySelectorAll('.alert').forEach(e=>e.classList.add('hide')),3000)

    // Toggle parent category field based on category type
    function toggleParentCategory() {
        const type = document.getElementById('category_type').value;
        const parentCategoryGroup = document.getElementById('parent_category_group');
        
        parentCategoryGroup.style.display = type === 'sub' ? 'block' : 'none';
        
        // If switching to subcategory, try to auto-select the parent
        if (type === 'sub') {
            autoSetParentCategory();
        }
    }

    // Initialize the category type functionality
    const categoryTypeSelect = document.getElementById('category_type');
    if (categoryTypeSelect) {
        categoryTypeSelect.addEventListener('change', toggleParentCategory);
        
        // Initialize the toggle and auto-select parent if needed
        toggleParentCategory();
        autoSetParentCategory();
    }

    // Confirm before deleting a category
    const deleteButtons = document.querySelectorAll('.action-btn.delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this category?')) {
                e.preventDefault();
            }
        });
    });
});
</script>
</body>
</html>
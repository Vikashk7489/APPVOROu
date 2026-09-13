<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Predefined gradient color schemes that match the design
$gradientSchemes = [
    ['from' => '#FFC785', 'to' => '#FF9900', 'shadow' => '#FFC785'], // Orange
    ['from' => '#FF8585', 'to' => '#FF0000', 'shadow' => '#FF8585'], // Red
    ['from' => '#DF85FF', 'to' => '#AD00FF', 'shadow' => '#DF85FF'], // Purple
    ['from' => '#16d300', 'to' => '#14c300', 'shadow' => '#16d300'], // Green
    ['from' => '#FFA985', 'to' => '#FF2E00', 'shadow' => '#FFA985'], // Orange-Red
    ['from' => '#9D85FF', 'to' => '#2400FF', 'shadow' => '#9D85FF'], // Blue
    
    // 5 New additional schemes
    ['from' => '#85FFB6', 'to' => '#00FF88', 'shadow' => '#85FFB6'], // Mint
    ['from' => '#FF85E6', 'to' => '#FF00C3', 'shadow' => '#FF85E6'], // Pink
    ['from' => '#85D1FF', 'to' => '#00A6FF', 'shadow' => '#85D1FF'], // Sky Blue
    ['from' => '#9c27b0', 'to' => '#673ab7', 'shadow' => '#9c27b0'], // Dark Purple
    ['from' => '#FFD185', 'to' => '#FFAA00', 'shadow' => '#FFD185'], // Gold
    ['from' => '#C285FF', 'to' => '#8A00FF', 'shadow' => '#C285FF'], // Violet
    ['from' => '#D4FC79', 'to' => '#96E6A1', 'shadow' => '#D4FC79'], // Fresh Lime
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    // Handle logo update
    if (isset($_POST['update_logo'])) {
        $logoText = trim($_POST['logo_text']);
        
        // Update logo text
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('logo_text', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$logoText, $logoText]);
        
        // Handle logo image upload
        if (!empty($_FILES['logo_image']['name'])) {
            $upload = uploadFile($_FILES['logo_image'], '../uploads/logos/', ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'image/gif']);
            
            if ($upload['success']) {
                // Delete old logo if exists
                $oldLogo = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'logo_image'")->fetchColumn();
                if ($oldLogo && file_exists('../uploads/logos/' . $oldLogo)) {
                    unlink('../uploads/logos/' . $oldLogo);
                }
                
                // Update new logo
                $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('logo_image', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$upload['filename'], $upload['filename']]);
            }
        }
        
        $_SESSION['success_message'] = 'Logo updated successfully';
    }
    
    // Handle logo removal
    if (isset($_POST['remove_logo'])) {
        $oldLogo = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'logo_image'")->fetchColumn();
        if ($oldLogo && file_exists('../uploads/logos/' . $oldLogo)) {
            unlink('../uploads/logos/' . $oldLogo);
        }
        
        $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = NULL WHERE setting_key = 'logo_image'");
        $stmt->execute();
        
        $_SESSION['success_message'] = 'Logo removed successfully';
    }
    
    // Handle menu item addition
    if (isset($_POST['add_menu_item'])) {
        $title = trim($_POST['title']);
        $url = trim($_POST['url']);
        $iconSvg = trim($_POST['icon_svg']);
        $isCategory = isset($_POST['is_category']) ? 1 : 0;
        $isExternal = isset($_POST['is_external']) ? 1 : 0;
        $colorScheme = isset($_POST['color_scheme']) ? (int)$_POST['color_scheme'] : 0;
        
        // Get colors from the selected scheme
        $scheme = $gradientSchemes[$colorScheme] ?? $gradientSchemes[0];
        $gradientFrom = $scheme['from'];
        $gradientTo = $scheme['to'];
        $shadowColor = $scheme['shadow'];
        
        // Get current max position
        $maxPosition = $pdo->query("SELECT MAX(position) FROM menu_items")->fetchColumn();
        $position = $maxPosition !== false ? $maxPosition + 1 : 0;
        
        $stmt = $pdo->prepare("INSERT INTO menu_items (title, url, icon_svg, gradient_from, gradient_to, shadow_color, is_category, is_external, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $url, $iconSvg, $gradientFrom, $gradientTo, $shadowColor, $isCategory, $isExternal, $position]);
        
        $_SESSION['success_message'] = 'Menu item added successfully';
    }
    
    // Handle menu item update
    if (isset($_POST['update_menu_item'])) {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title']);
        $url = trim($_POST['url']);
        $iconSvg = trim($_POST['icon_svg']);
        $isCategory = isset($_POST['is_category']) ? 1 : 0;
        $isExternal = isset($_POST['is_external']) ? 1 : 0;
        $colorScheme = isset($_POST['color_scheme']) ? (int)$_POST['color_scheme'] : 0;
        
        // Get colors from the selected scheme
        $scheme = $gradientSchemes[$colorScheme] ?? $gradientSchemes[0];
        $gradientFrom = $scheme['from'];
        $gradientTo = $scheme['to'];
        $shadowColor = $scheme['shadow'];
        
        $stmt = $pdo->prepare("UPDATE menu_items SET title = ?, url = ?, icon_svg = ?, gradient_from = ?, gradient_to = ?, shadow_color = ?, is_category = ?, is_external = ? WHERE id = ?");
        $stmt->execute([$title, $url, $iconSvg, $gradientFrom, $gradientTo, $shadowColor, $isCategory, $isExternal, $id]);
        
        $_SESSION['success_message'] = 'Menu item updated successfully';
    }
    
    // Handle menu item deletion
    if (isset($_POST['delete_menu_item'])) {
        $id = (int)$_POST['id'];
        
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['success_message'] = 'Menu item deleted successfully';
    }
    
    // Handle menu reordering
    if (isset($_POST['update_order'])) {
        $order = json_decode($_POST['order'], true);
        
        foreach ($order as $position => $id) {
            $stmt = $pdo->prepare("UPDATE menu_items SET position = ? WHERE id = ?");
            $stmt->execute([$position, $id]);
        }
        
        $_SESSION['success_message'] = 'Menu order updated successfully';
    }
    
    header("Location: manage_header.php");
    exit();
}

// Get current logo settings
$logoText = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'logo_text'")->fetchColumn();
$logoImage = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'logo_image'")->fetchColumn();

// Get all menu items
$menuItems = $pdo->query("SELECT * FROM menu_items ORDER BY position ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Header - AppCenter Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/manage_header.css">
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
                <a href="manage_header.php" class="menu-item active"><span>📰</span> Manage Header</a>
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
                <h1>Manage Header</h1>
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
            
            <div class="card">
                <h2>Logo Settings</h2>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="logo_text">Logo Text</label>
                        <input type="text" id="logo_text" name="logo_text" class="form-control" value="<?= htmlspecialchars($logoText) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="logo_image">Logo Image (optional - will replace text)</label>
                        <input type="file" id="logo_image" name="logo_image" class="form-control" accept="image/*">
                        <?php if ($logoImage): ?>
                            <img src="../uploads/logos/<?= htmlspecialchars($logoImage) ?>" alt="Current Logo" class="logo-preview">
                            <p><small>Current logo: <?= htmlspecialchars($logoImage) ?></small></p>
                            <button type="submit" name="remove_logo" class="btn btn-danger btn-sm" style="margin-top: 10px;"><i class="fas fa-trash-alt"></i> Remove Logo</button>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" name="update_logo" class="btn"><i class="fa-solid fa-pencil"></i> Update Logo</button>
                </form>
            </div>
            
            <div class="card">
                <h2>Add New Menu Item</h2>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="url">URL</label>
                        <input type="text" id="url" name="url" class="form-control" placeholder="https://example.com/game_url" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="icon_svg">Icon SVG Code</label>
                        <textarea id="icon_svg" name="icon_svg" class="form-control" rows="4" placeholder='<svg aria-hidden="true" viewBox="0 0 512 512"><path fill="currentColor" d="M..."></path></svg>'></textarea>
                        <small>Paste SVG code here. Use <code>fill="currentColor"</code> to allow color change White. Example: <code>&lt;svg aria-hidden="true" viewBox="0 0 512 512"&gt;&lt;path <span style="color: red;">fill="currentColor"</span> d="M..."&gt;&lt;/path&gt;&lt;/svg&gt;</code> </small>
                    </div>
              
                    <div class="form-group">
                        <label>Color Scheme</label>
                        <div class="color-schemes">
                            <?php foreach ($gradientSchemes as $index => $scheme): ?>
                                <label class="color-scheme-option">
                                    <input type="radio" name="color_scheme" value="<?= $index ?>" <?= $index === 0 ? 'checked' : '' ?>>
                                    <div class="color-preview" style="background: linear-gradient(135deg, <?= $scheme['from'] ?>, <?= $scheme['to'] ?>);"></div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="is_category" name="is_category" value="1">
                            This is a category link (will use category icon if no SVG provided)
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="is_external" name="is_external" value="1">
                            This is an external link (opens in new tab)
                        </label>
                    </div>
                    
                    <button type="submit" name="add_menu_item" class="btn"><i class="fa-solid fa-plus"></i> Add Menu Item</button>
                    <button type="button" id="download-svg-btn" class="btn" style="background: var(--danger-color); margin-left: 10px;" onclick="window.open('https://www.flaticon.com/', '_blank')">
                   <i class="fa-solid fa-arrow-up-right-from-square"></i> SVG Code
                  </button>

                </form>
            </div>
            
            <div class="card">
                <h2>Menu Items</h2>
                <p>Drag and drop to reorder menu items</p>
                
                <?php if (empty($menuItems)): ?>
                    <p>No menu items found. Add your first menu item above.</p>
                <?php else: ?>
                    <ul class="menu-items-list" id="sortable-menu">
                        <?php foreach ($menuItems as $item): ?>
                            <li class="menu-item-card" data-id="<?= $item['id'] ?>" 
                                data-icon-svg="<?= htmlspecialchars($item['icon_svg']) ?>"
                                data-gradient-from="<?= htmlspecialchars($item['gradient_from']) ?>"
                                data-gradient-to="<?= htmlspecialchars($item['gradient_to']) ?>">
                                <div class="menu-item-details">
                                    <i class="fas fa-grip-vertical drag-handle"></i>
                                    <?php if (!empty($item['icon_svg'])): ?>
                                        <div class="svg-preview" style="--gradient-from: <?= $item['gradient_from'] ?>; --gradient-to: <?= $item['gradient_to'] ?>;">
                                            <?= $item['icon_svg'] ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="min-width: 0;">
                                        <div class="menu-item-title">
                                            <?= htmlspecialchars($item['title']) ?>
                                            <?php if ($item['is_category']): ?>
                                                <span class="badge badge-primary"><i class="fas fa-folder"></i> Category Link</span>
                                            <?php endif; ?>
                                            <?php if ($item['is_external']): ?>
                                                <span class="badge badge-success"><i class="fas fa-external-link-alt"></i> External Link</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="menu-item-url">
                                            <?= htmlspecialchars($item['url']) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="menu-item-actions">
                                    <button type="button" class="btn btn-sm edit-menu-item" data-id="<?= $item['id'] ?>"><i class="fas fa-edit"></i> Edit</button>
                                    <form method="POST" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" name="delete_menu_item" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this menu item?')"><i class="fas fa-trash-alt"></i> Delete</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <form id="update-order-form" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="update_order" value="1">
                        <input type="hidden" name="order" id="menu-order">
                        <button type="submit" class="btn" id="save-order-btn" style="display: none;">Save Order</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Edit Menu Item Modal -->
            <div id="edit-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
                <div style="background-color: white; padding: 20px; border-radius: 5px; width: 100%; max-width: 500px;">
                    <h2>Edit Menu Item</h2>
                    <form id="edit-form" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" id="edit-id">
                        <input type="hidden" name="update_menu_item" value="1">
                        
                        <div class="form-group">
                            <label for="edit-title">Title</label>
                            <input type="text" id="edit-title" name="title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit-url">URL</label>
                            <input type="text" id="edit-url" name="url" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit-icon_svg">Icon SVG Code</label>
                            <textarea id="edit-icon_svg" name="icon_svg" class="form-control" rows="4"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Color Scheme</label>
                            <div class="color-schemes" id="edit-color-schemes">
                                <?php foreach ($gradientSchemes as $index => $scheme): ?>
                                    <label class="color-scheme-option">
                                        <input type="radio" name="color_scheme" value="<?= $index ?>">
                                        <div class="color-preview" style="background: linear-gradient(135deg, <?= $scheme['from'] ?>, <?= $scheme['to'] ?>);"></div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit-is_category" name="is_category" value="1">
                                This is a category link
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit-is_external" name="is_external" value="1">
                                This is an external link
                            </label>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <button type="button" id="cancel-edit" class="btn btn-danger">Cancel</button>
                            <button type="submit" class="btn">Update Menu Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
    <script>
        // Initialize sortable
        const sortable = new Sortable(document.getElementById('sortable-menu'), {
            animation: 150,
            ghostClass: 'sortable-ghost',
            handle: '.drag-handle',
            onEnd: function() {
                const order = [];
                document.querySelectorAll('#sortable-menu li').forEach((item, index) => {
                    order.push(item.getAttribute('data-id'));
                });
                document.getElementById('menu-order').value = JSON.stringify(order);
                document.getElementById('save-order-btn').style.display = 'inline-block';
            }
        });
        
        // Edit menu item functionality
        document.querySelectorAll('.edit-menu-item').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const item = document.querySelector(`.menu-item-card[data-id="${id}"]`);
                
                // Fetch item details
                const title = item.querySelector('.menu-item-title').textContent.split('Category Link')[0].split('External Link')[0].trim();
                const url = item.querySelector('.menu-item-url').textContent.trim();
                const iconSvg = item.getAttribute('data-icon-svg') || '';
                const isCategory = item.querySelector('.badge-primary') !== null;
                const isExternal = item.querySelector('.badge-success') !== null;
                const gradientFrom = item.getAttribute('data-gradient-from') || '#FFC785';
                const gradientTo = item.getAttribute('data-gradient-to') || '#FF9900';
                
                // Find which color scheme matches
                let selectedScheme = 0;
                const colorPreviews = document.querySelectorAll('#edit-color-schemes .color-preview');
                colorPreviews.forEach((preview, index) => {
                    const bg = preview.style.background;
                    if (bg.includes(gradientFrom) && bg.includes(gradientTo)) {
                        selectedScheme = index;
                    }
                });
                
                // Set form values
                document.getElementById('edit-id').value = id;
                document.getElementById('edit-title').value = title;
                document.getElementById('edit-url').value = url;
                document.getElementById('edit-icon_svg').value = iconSvg;
                document.getElementById('edit-is_category').checked = isCategory;
                document.getElementById('edit-is_external').checked = isExternal;
                
                // Set the color scheme
                document.querySelectorAll('#edit-color-schemes input').forEach((radio, index) => {
                    radio.checked = (index == selectedScheme);
                });
                
                // Show modal
                document.getElementById('edit-modal').style.display = 'flex';
            });
        });
        
        // Close modal
        document.getElementById('cancel-edit').addEventListener('click', function() {
            document.getElementById('edit-modal').style.display = 'none';
        });
        
        // Close modal when clicking outside
        document.getElementById('edit-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                document.getElementById('edit-modal').style.display = 'none';
            }
        });
    </script>
    
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
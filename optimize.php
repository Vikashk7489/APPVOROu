<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Define image directories
$imageDirectories = [
    'Icons' => '../uploads/icons/',
    'Logos' => '../uploads/logos/',
    'Hero Images' => '../uploads/hero/',
    'Favicons' => '../uploads/favicons/'
];

// Function to update image references in database
function updateImageReferences($oldPath, $newPath) {
    global $pdo;
    
    try {
        // Get just the filenames
        $oldFilename = basename($oldPath);
        $newFilename = basename($newPath);
        
        // Update apps table (icon field)
        $stmt = $pdo->prepare("UPDATE apps SET icon = REPLACE(icon, :old, :new) WHERE icon LIKE :like_old");
        $stmt->execute([
            ':old' => $oldFilename,
            ':new' => $newFilename,
            ':like_old' => '%'.$oldFilename.'%'
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Database update failed: " . $e->getMessage());
        return false;
    }
}

// Function to scan directories for images
function getImageFiles($directories) {
    $images = [];
    
    foreach ($directories as $type => $dir) {
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && is_file($dir . $file)) {
                    $images[] = [
                        'type' => $type,
                        'path' => $dir . $file,
                        'filename' => $file,
                        'size' => filesize($dir . $file),
                        'optimized' => false,
                        'extension' => strtolower(pathinfo($file, PATHINFO_EXTENSION))
                    ];
                }
            }
        }
    }
    
    return $images;
}

function convertToWebP($sourcePath, $quality = 75) {
    global $pdo;
    
    if (!extension_loaded('imagick')) {
        return ['success' => false, 'message' => 'Imagick extension not available'];
    }
    
    try {
        $imagick = new Imagick($sourcePath);
        $originalSize = filesize($sourcePath);
        
        // Set WebP options
        $imagick->setImageFormat('webp');
        $imagick->setImageCompressionQuality($quality);
        $imagick->stripImage();
        
        // Get new filename (replace extension with .webp)
        $pathInfo = pathinfo($sourcePath);
        $webpFilename = $pathInfo['filename'] . '.webp';
        $webpPath = $pathInfo['dirname'] . '/' . $webpFilename;
        
        // Save WebP image (overwrite original)
        $imagick->writeImage($webpPath);
        $newSize = filesize($webpPath);
        
        // Update database references based on image type
        $oldFilename = basename($sourcePath);
        $newFilename = basename($webpPath);
        
        // 1. Update apps table (icon field)
        if (strpos($pathInfo['dirname'], 'icons') !== false) {
            updateImageReferences($oldFilename, $newFilename);
        }
        
        // 2. Update homepage_settings.json for hero image
        if (strpos($sourcePath, 'hero') !== false) {
            $settingsFile = '../data/homepage_settings.json';
            if (file_exists($settingsFile)) {
                $settings = json_decode(file_get_contents($settingsFile), true);
                if (isset($settings['hero']['image_path']) && basename($settings['hero']['image_path']) == $oldFilename) {
                    $settings['hero']['image_path'] = $newFilename;
                    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
                }
            }
        }
        
        // 3. Update logo in site_settings
        if (strpos($sourcePath, 'logos') !== false) {
            // Check if it's the main logo
            $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'logo_image' AND setting_value = ?");
            $stmt->execute([$newFilename, $oldFilename]);
            
            // Check if it's the footer brand logo
            $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'footer_brand_logo' AND setting_value = ?");
            $stmt->execute([$newFilename, $oldFilename]);
        }
        
        // 4. Update favicon in seo_settings
        if (strpos($sourcePath, 'favicons') !== false) {
            $stmt = $pdo->prepare("UPDATE seo_settings SET favicon = ? WHERE favicon = ?");
            $stmt->execute([$newFilename, $oldFilename]);
        }
        
        // Delete original if it's not already WebP
        if (strtolower($pathInfo['extension']) !== 'webp') {
            unlink($sourcePath);
        }
        
        $imagick->clear();
        
        return [
            'success' => true,
            'message' => 'Converted to WebP',
            'original_path' => $sourcePath,
            'new_path' => $webpPath,
            'original_size' => $originalSize,
            'new_size' => $newSize,
            'savings_percent' => round(100 - ($newSize / $originalSize * 100), 2)
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'WebP conversion failed: ' . $e->getMessage()];
    }
}

// Optimize image in its original format
function optimizeOriginalFormat($sourcePath) {
    if (!extension_loaded('imagick')) {
        return ['success' => false, 'message' => 'Imagick extension not available'];
    }
    
    try {
        $imagick = new Imagick($sourcePath);
        $format = strtoupper($imagick->getImageFormat());
        $originalSize = filesize($sourcePath);
        
        // Universal optimizations
        $imagick->stripImage();
        $imagick->setImageInterlaceScheme(Imagick::INTERLACE_PLANE);
        
        // Format-specific optimization
        switch ($format) {
            case 'JPEG':
                $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
                $imagick->setImageCompressionQuality(75);
                $imagick->setSamplingFactors(['2x1', '1x1', '1x1']);
                $imagick->setOption('jpeg:dct-method', 'float');
                break;
                
            case 'PNG':
                $imagick->setImageCompression(Imagick::COMPRESSION_ZIP);
                $imagick->setImageCompressionQuality(90);
                $imagick->setOption('png:compression-level', '9');
                $imagick->setOption('png:compression-strategy', '1');
                $imagick->setOption('png:filter', '5');
                $imagick->setOption('png:exclude-chunk', 'all');
                break;
                
            case 'GIF':
                $imagick->setImageCompression(Imagick::COMPRESSION_LZW);
                $imagick->quantizeImage(128, Imagick::COLORSPACE_SRGB, 0, false, false);
                $imagick->setOption('gif:method', 'optimize-transparency');
                break;
                
            case 'WEBP':
                $imagick->setImageCompressionQuality(80);
                break;
                
            default:
                return ['success' => false, 'message' => 'Unsupported image format'];
        }
        
        // Save the optimized image (overwrite original)
        $imagick->writeImage($sourcePath);
        $newSize = filesize($sourcePath);
        $imagick->clear();
        
        return [
            'success' => true,
            'message' => 'Optimized (' . $format . ')',
            'original_size' => $originalSize,
            'new_size' => $newSize
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Handle image optimization request
if (isset($_POST['optimize'])) {
    $imagePath = $_POST['image_path'] ?? '';
    $convertToWebP = isset($_POST['convert_to_webp']) && $_POST['convert_to_webp'] == '1';
    
    if (file_exists($imagePath)) {
        if ($convertToWebP) {
            $result = convertToWebP($imagePath);
        } else {
            $result = optimizeOriginalFormat($imagePath);
        }
        
        if ($result['success']) {
            $savings = $result['original_size'] - $result['new_size'];
            $percent = round(($savings / $result['original_size']) * 100, 2);
            
            $_SESSION['success_message'] = "Optimized successfully! " . 
                formatBytes($result['original_size']) . " → " . 
                formatBytes($result['new_size']) . " ($percent% reduction)";
            
            if ($convertToWebP) {
                $_SESSION['success_message'] .= " (Converted to WebP)";
            }
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
        
        header("Location: optimize.php");
        exit();
    }
}

// Handle bulk optimization
if (isset($_POST['bulk_optimize'])) {
    $selectedImages = $_POST['selected_images'] ?? [];
    $convertToWebP = isset($_POST['convert_to_webp']) && $_POST['convert_to_webp'] == '1';
    $results = [];
    
    foreach ($selectedImages as $imagePath) {
        if (file_exists($imagePath)) {
            if ($convertToWebP) {
                $result = convertToWebP($imagePath);
            } else {
                $result = optimizeOriginalFormat($imagePath);
            }
            $results[] = array_merge(
                ['path' => $imagePath],
                $result
            );
        }
    }
    
    $_SESSION['bulk_results'] = $results;
    header("Location: optimize.php");
    exit();
}

// Format bytes to human-readable format
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Get all images
$allImages = getImageFiles($imageDirectories);

// Calculate total size and potential savings
$totalImages = count($allImages);
$totalSize = array_reduce($allImages, function($carry, $item) {
    return $carry + $item['size'];
}, 0);

// Estimate potential savings (25% for WebP conversion)
$potentialSavings = $totalSize * 0.25;

// Sort by size (largest first)
usort($allImages, function($a, $b) {
    return $b['size'] <=> $a['size'];
});

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 10;
$offset = ($page - 1) * $perPage;
$totalPages = max(1, ceil($totalImages / $perPage));

// Get images for current page
$images = array_slice($allImages, $offset, $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Optimizer - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/optimize.css">
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
                <a href="seo.php" class="menu-item"><span>🔍</span> SEO Settings</a>
                <a href="statistics.php" class="menu-item"><span>📉</span> Statistics</a>
                <a href="privacy.php" class="menu-item"><span>🔒</span> Privacy & Security</a>
                <a href="optimize.php" class="menu-item active"><span>⚡</span> Image Optimizer</a>
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
                    <h1>Image Optimizer</h1>
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
            
            <div class="webp-recommendation">
                <i class="fas fa-lightbulb"></i>
                <div>
                    <strong>Recommended:</strong> WebP format typically provides 80-90% better compression than JPEG/PNG
                </div>
            </div>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $_SESSION['success_message'] ?>
                    <?php unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $_SESSION['error_message'] ?>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['bulk_results'])): ?>
                <div class="optimizer-container bulk-results-container">
                    <h3>Bulk Optimization Results</h3>
                    <?php foreach ($_SESSION['bulk_results'] as $result): ?>
                        <div class="bulk-result-item">
                            <div>
                                <span class="<?= $result['success'] ? 'result-success' : 'result-error' ?>">
                                    <?= basename($result['path']) ?> - 
                                    <?= $result['success'] ? $result['message'] : 'Failed: ' . $result['message'] ?>
                                </span>
                            </div>
                            <?php if ($result['success']): ?>
                                <span class="result-savings">
                                    <?= formatBytes($result['original_size']) ?> → <?= formatBytes($result['new_size']) ?>
                                    (<?= round(100 - ($result['new_size'] / $result['original_size'] * 100), 2) ?>% saved)
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php unset($_SESSION['bulk_results']); ?>
            <?php endif; ?>
            
            <!-- Stats Section -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                            <i class="fas fa-images" style="color: #fff;"></i>
                        </div>
                        <div class="stat-title">Total Images</div>
                    </div>
                    <div class="stat-value"><?= $totalImages ?></div>
                    <div class="stat-description">Across all directories</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                            <i class="fas fa-database" style="color: #fff;"></i>
                        </div>
                        <div class="stat-title">Total Size</div>
                    </div>
                    <div class="stat-value"><?= formatBytes($totalSize) ?></div>
                    <div class="stat-description">Current storage used</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                            <i class="fas fa-coins" style="color: #fff;"></i>
                        </div>
                        <div class="stat-title">Potential Savings</div>
                    </div>
                    <div class="stat-value"><?= formatBytes($potentialSavings) ?></div>
                    <div class="stat-description">Estimated with WebP conversion</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                            <i class="fas fa-folder-open" style="color: #fff;"></i>
                        </div>
                        <div class="stat-title">Directories</div>
                    </div>
                    <div class="stat-value"><?= count($imageDirectories) ?></div>
                    <div class="stat-description">Being scanned for images</div>
                </div>
            </div>
            
            <div class="optimizer-container">
                <form method="POST" id="bulkForm">
                    <div class="webp-toggle">
                        <label class="switch">
                            <input type="checkbox" name="convert_to_webp" id="convertToWebP" value="1" <?= isset($_POST['convert_to_webp']) && $_POST['convert_to_webp'] == '1' ? 'checked' : 'checked' ?>>
                            <span class="slider"></span>
                        </label>
                        <label for="convertToWebP">Convert to WebP format</label> <span class="conversion-badge">Recommended</span>
                    </div>
                    
                    <div class="select-all-container">
                        <input type="checkbox" id="selectAll"> 
                        <label for="selectAll">Select all images</label>
                        <button type="submit" name="bulk_optimize" class="btn btn-optimize">
                            <i class="fas fa-compress-alt"></i> Optimize Selected
                        </button>
                    </div>
                    
                    <table class="image-table">
                        <thead>
                            <tr>
                                <th width="40px"></th>
                                <th>Image</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($images)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No images found in upload directories</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($images as $image): ?>
                                    <tr>
                                        <td data-label="Select">
                                            <input type="checkbox" name="selected_images[]" value="<?= htmlspecialchars($image['path']) ?>">
                                        </td>
                                        <td data-label="Image">
                                            <div class="file-info">
                                                <img src="<?= htmlspecialchars($image['path']) ?>" class="image-preview" 
                                                     onerror="this.src='data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=\"http://www.w3.org/2000/svg\" width=\"60\" height=\"60\" viewBox=\"0 0 60 60\"%3E%3Crect fill=\"%23ddd\" width=\"60\" height=\"60\" rx=\"4\" ry=\"4\"%3E%3C/rect%3E%3Ctext fill=\"%23666\" font-family=\"sans-serif\" font-size=\"10\" dy=\"3\" text-anchor=\"middle\" x=\"30\" y=\"30\"%3EImage%3C/text%3E%3C/svg%3E'">
                                                <div class="file-details">
                                                    <span class="filename"><?= htmlspecialchars($image['filename']) ?></span>
                                                    <span class="filepath"><?= htmlspecialchars($image['type']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Type">
                                            <?php 
                                            $badgeClass = 'badge-' . strtolower($image['extension']);
                                            if (!in_array($badgeClass, ['badge-jpg', 'badge-png', 'badge-gif', 'badge-webp'])) {
                                                $badgeClass = 'badge-jpg';
                                            }
                                            ?>
                                            <span class="format-badge <?= $badgeClass ?>"><?= strtoupper($image['extension']) ?></span>
                                        </td>
                                        <td data-label="Size" class="filesize"><?= formatBytes($image['size']) ?></td>
                                        <td data-label="Action">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="image_path" value="<?= htmlspecialchars($image['path']) ?>">
                                                <button type="submit" name="optimize" class="btn btn-optimize">
                                                    <i class="fas fa-compress-alt"></i> Optimize
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="optimize.php?page=<?= $page - 1 ?>">&laquo;</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="optimize.php?page=<?= $i ?>" <?= $i == $page ? 'class="active"' : '' ?>><?= $i ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="optimize.php?page=<?= $page + 1 ?>">&raquo;</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select all checkbox functionality
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('input[name="selected_images[]"]');
            
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAll.checked;
                });
            });
            
            // Check if any checkbox is unchecked to update "Select All" status
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (!this.checked) {
                        selectAll.checked = false;
                    } else {
                        // Check if all are now checked
                        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                        selectAll.checked = allChecked;
                    }
                });
            });
            
            // Ensure WebP toggle state is applied to all forms
            const webpToggle = document.getElementById('convertToWebP');
            const bulkForm = document.getElementById('bulkForm');
            
            // Handle bulk form submission
            bulkForm.addEventListener('submit', function() {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'convert_to_webp';
                input.value = webpToggle.checked ? '1' : '0';
                this.appendChild(input);
            });
            
            // Handle individual image optimizations
            document.querySelectorAll('form').forEach(form => {
                if (form !== bulkForm && form.querySelector('button[name="optimize"]')) {
                    form.addEventListener('submit', function(e) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'convert_to_webp';
                        input.value = webpToggle.checked ? '1' : '0';
                        this.appendChild(input);
                    });
                }
            });
            
            // Mobile menu toggle
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
        
        setTimeout(() => document.querySelectorAll('.alert, .bulk-results-container').forEach(e => e.classList.add('hide')), 3000);
    </script>
</body>
</html>
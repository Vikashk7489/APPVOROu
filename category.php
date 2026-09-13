<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isset($_GET['category'])) {
    header("Location: index.php");
    exit();
}

$categorySlug = $_GET['category'];
$categories = getCategories();

// Get current category
$stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
$stmt->execute([$categorySlug]);
$currentCategory = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentCategory) {
    header("Location: index.php");
    exit();
}

require_once 'includes/header.php';

// Check if this is a main category (has subcategories)
$isMainCategory = false;
$subcategoryIds = [];
foreach ($categories as $category) {
    if ($category['slug'] === $categorySlug && !empty($category['subcategories'])) {
        $isMainCategory = true;
        foreach ($category['subcategories'] as $subcat) {
            $subcategoryIds[] = $subcat['id'];
        }
        break;
    }
}

// Get apps for this category
if ($isMainCategory && !empty($subcategoryIds)) {
    $placeholders = implode(',', array_fill(0, count($subcategoryIds), '?'));
    $stmt = $pdo->prepare("SELECT apps.*, categories.name as category_name 
                          FROM apps 
                          LEFT JOIN categories ON apps.category_id = categories.id 
                          WHERE categories.id IN ($placeholders)
                          ORDER BY apps.created_at DESC");
    $stmt->execute($subcategoryIds);
} else {
    $stmt = $pdo->prepare("SELECT apps.*, categories.name as category_name 
                          FROM apps 
                          LEFT JOIN categories ON apps.category_id = categories.id 
                          WHERE categories.slug = ? 
                          ORDER BY apps.created_at DESC");
    $stmt->execute([$categorySlug]);
}
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($currentCategory['name']) ?> - AppCenter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public/breadcrumb-pill.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public/category.css">
</head>
<body>
    <section class="category-header">
        <div class="container">
            <div class="breadcrumb-pill">
                <a href="<?= SITE_URL ?>">Home</a>
                <?php
                $categoryDetails = getCategoryDetails($currentCategory['name']);
                if ($categoryDetails['subcategory']) {
                    echo '<span class="separator">/</span>';
                    echo '<a href="' . SITE_URL . '/category/' . slugify($categoryDetails['main_category']) . '">' . htmlspecialchars($categoryDetails['main_category']) . '</a>';
                    echo '<span class="separator">/</span>';
                    echo '<span class="breadcrumb-current">' . htmlspecialchars($categoryDetails['subcategory']) . '</span>';
                } else {
                    echo '<span class="separator">/</span>';
                    echo '<span class="breadcrumb-current">' . htmlspecialchars($currentCategory['name']) . '</span>';
                }
                ?>
            </div>
        </div>
    </section>
    
    <div class="container">
        <section class="category-apps">
            <div class="section-title">
                <h2>
                    <div class="icon" style="--gradient-from: #9c27b0; --gradient-to: #673ab7; --shadow-color: #9c27b0;">
                        <svg class="svg-inline--fa icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><rect x="64" y="64" width="80" height="80" rx="40" ry="40" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32"></rect><rect x="216" y="64" width="80" height="80" rx="40" ry="40" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32"></rect><rect x="368" y="64" width="80" height="80" rx="40" ry="40" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32"></rect><rect x="64" y="216" width="80" height="80" rx="40" ry="40" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32"></rect><rect x="216" y="216" width="80" height="80" rx="40" ry="40" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32"></rect><rect x="368" y="216" width="80" height="80" rx="40" ry="40" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32"></rect><rect x="64" y="368" width="80" height="80" rx="40" ry="40" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32"></rect><rect x="216" y="368" width="80" height="80" rx="40" ry="40" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32"></rect><rect x="368" y="368" width="80" height="80" rx="40" ry="40" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32"></rect></svg>
                    </div>
                    <?php 
                    $category_details = getCategoryDetails($currentCategory['name']);
                    echo htmlspecialchars($category_details['subcategory'] ?: $currentCategory['name']); 
                    ?>
                </h2>
            </div>
            
            <?php if (count($apps) > 0): ?>
                <div class="apps-grid">
                    <?php foreach ($apps as $app): 
                        $category_details = getCategoryDetails($app['category_name']);
                    ?>
                        <div class="app-card" onclick="window.location.href='<?= SITE_URL ?>/<?= $app['slug'] ?>'">
                            <div class="app-card-img">
                                <img src="<?= SITE_URL ?>/uploads/icons/<?= htmlspecialchars($app['icon']) ?>" alt="<?= htmlspecialchars($app['name']) ?>">
                                <?php if (strpos(strtolower($currentCategory['name']), 'game') !== false): ?>
                                    <span class="game-badge">Game</span>
                                <?php else: ?>
                                    <span class="app-badge">App</span>
                                <?php endif; ?>
                            </div>
                            <div class="app-card-body">
                                <h3 class="app-card-title"><?= htmlspecialchars($app['name']) ?></h3>
                                <div class="app-card-meta">
                                    <span class="app-category"><?= !empty($category_details['subcategory']) ? htmlspecialchars($category_details['subcategory']) : htmlspecialchars($category_details['main_category']) ?></span>
                                </div>
                                <div class="app-card-details">
                                    <div class="app-detail">
                                        <svg class="svg-inline--fa fa-arrow-down-to-bracket" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="arrow-down-to-bracket" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" data-fa-i2svg="">
                                            <path fill="#4ccb70" d="M448 416v-64c0-17.67-14.33-32-32-32s-32 14.33-32 32v64c0 17.67-14.33 32-32 32H96c-17.67 0-32-14.33-32-32v-64c0-17.67-14.33-32-32-32s-32 14.33-32 32v64c0 53.02 42.98 96 96 96h256C405 512 448 469 448 416zM246.6 342.6l128-128c12.51-12.51 12.49-32.76 0-45.25c-12.5-12.5-32.75-12.5-45.25 0L256 242.8V32c0-17.69-14.31-32-32-32S192 14.31 192 32v210.8L118.6 169.4c-12.5-12.5-32.75-12.5-45.25 0s-12.5 32.75 0 45.25l128 128C213.9 355.1 234.1 355.1 246.6 342.6z"></path>
                                        </svg>
                                        <span class="app-size"><?= !empty($app['size']) ? htmlspecialchars($app['size']) : 'N/A' ?></span>
                                    </div>
                                    <div class="app-detail">
                                        <svg class="svg-inline--fa fa-bolt text-lg" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="bolt" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" data-fa-i2svg="">
                                            <path fill="#4ccb70" d="M240.5 224H352C365.3 224 377.3 232.3 381.1 244.7C386.6 257.2 383.1 271.3 373.1 280.1L117.1 504.1C105.8 513.9 89.27 514.7 77.19 505.9C65.1 497.1 60.7 481.1 66.59 467.4L143.5 288H31.1C18.67 288 6.733 279.7 2.044 267.3C-2.645 254.8 .8944 240.7 10.93 231.9L266.9 7.918C278.2-1.92 294.7-2.669 306.8 6.114C318.9 14.9 323.3 30.87 317.4 44.61L240.5 224z"></path>
                                        </svg>
                                        <span class="app-version">v<?= htmlspecialchars($app['version']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-apps">
                    <h3>No apps found in this category</h3>
                    <p>Check back later or browse other categories.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>
    
    <?php 
    require_once 'includes/footer.php';
    ?>
</body>
</html>
<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

if (!isset($_GET['s']) || empty(trim($_GET['s']))) {
    header("Location: index.php");
    exit();
}

$query = trim($_GET['s']);
$apps = searchApps($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Search Results for "<?= htmlspecialchars($query) ?>" - AppCenter</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public/search.css">
</head>
<body>
    <section class="search-header">
        <div class="container">
            <h1>
                <a href="<?= SITE_URL ?>" style="color: white; text-decoration: none;">Home</a> / 
                <span class="search-query"><?= htmlspecialchars($query) ?></span>
            </h1>
        </div>
    </section>
    
    <div class="container">
        <section class="search-results">
            <div class="section-title">
                <h2>
                    <div class="icon" style="--gradient-from: #4CC9F0; --gradient-to: #00A6FF; --shadow-color: #4CC9F0;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"/></svg>
                    </div>
                    Search Results
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
                            </div>
                            <div class="app-card-body">
                                <h3 class="app-card-title"><?= htmlspecialchars($app['name']) ?></h3>
                                <div class="app-card-meta">
                                    <span class="app-category"><?= !empty($category_details['subcategory']) ? htmlspecialchars($category_details['subcategory']) : htmlspecialchars($category_details['main_category']) ?></span>
                                </div>
                                <div class="app-card-details">
                                    <div class="app-detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                            <path fill="#4ccb70" d="M448 416v-64c0-17.67-14.33-32-32-32s-32 14.33-32 32v64c0 17.67-14.33 32-32 32H96c-17.67 0-32-14.33-32-32v-64c0-17.67-14.33-32-32-32s-32 14.33-32 32v64c0 53.02 42.98 96 96 96h256C405 512 448 469 448 416zM246.6 342.6l128-128c12.51-12.51 12.49-32.76 0-45.25c-12.5-12.5-32.75-12.5-45.25 0L256 242.8V32c0-17.69-14.31-32-32-32S192 14.31 192 32v210.8L118.6 169.4c-12.5-12.5-32.75-12.5-45.25 0s-12.5 32.75 0 45.25l128 128C213.9 355.1 234.1 355.1 246.6 342.6z"></path>
                                        </svg>
                                        <span class="app-size"><?= !empty($app['size']) ? htmlspecialchars($app['size']) : 'N/A' ?></span>
                                    </div>
                                    <div class="app-detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
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
                <div class="no-results">
                    <h3>No apps found matching your search</h3>
                    <p>Try different keywords or browse our categories.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>
    
    <?php require_once 'includes/footer.php'; ?>
</body>
</html>
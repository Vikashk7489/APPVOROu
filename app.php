<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Handle download first - before any output (must run before header.php,
// since header.php prints the HTML page and would prevent file headers
// like Content-Disposition from being sent)
if (isset($_GET['download']) && isset($_GET['slug'])) {
    $slug = $_GET['slug'];
    $app = getAppBySlug($slug);
    
    if ($app) {
        incrementDownloadCount($app['id']);
        
        // Track daily download
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            INSERT INTO download_stats (app_id, download_date, download_count) 
            VALUES (:app_id, :download_date, 1)
            ON DUPLICATE KEY UPDATE download_count = download_count + 1
        ");
        $stmt->bindParam(':app_id', $app['id']);
        $stmt->bindParam(':download_date', $today);
        $stmt->execute();
        
        if (!empty($app['external_url'])) {
            header("Location: " . $app['external_url']);
            exit;
        } else {
            $filePath = APK_DIR . $app['apk_file'];
            if (file_exists($filePath)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/vnd.android.package-archive');
                header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filePath));
                readfile($filePath);
                exit;
            }
        }
    }
}

require_once 'includes/header.php';

// Now proceed with normal page rendering
if (!isset($_GET['slug'])) {
    header("Location: index.php");
    exit();
}

$slug = $_GET['slug'];
$app = getAppBySlug($slug);

if (!$app) {
    header("Location: index.php");
    exit();
}

// Get user's rating status
$user_ip = $_SERVER['REMOTE_ADDR'];
$user_has_rated = false;
$user_rating = 0;

if ($app['id']) {
    $stmt = $pdo->prepare("SELECT rating FROM ratings WHERE app_id = ? AND user_ip = ?");
    $stmt->execute([$app['id'], $user_ip]);
    $rating_data = $stmt->fetch();
    
    if ($rating_data) {
        $user_has_rated = true;
        $user_rating = $rating_data['rating'];
    }
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post-comment'])) {
    $name = filter_input(INPUT_POST, 'author', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING);
    $app_id = $app['id'];
    
    // Validate email ends with @gmail.com
    if (!preg_match('/@gmail\.com$/', $email)) {
        $comment_error = "Please use a @gmail.com email address";
    } else {
        // Insert comment into database
        $stmt = $pdo->prepare("INSERT INTO comments (app_id, name, email, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$app_id, $name, $email, $comment]);
        
        $comment_success = "Comment posted successfully!";
    }
}

// Get existing comments for this app
$comments = [];
if ($app['id']) {
    $stmt = $pdo->prepare("SELECT * FROM comments WHERE app_id = ? ORDER BY created_at DESC");
    $stmt->execute([$app['id']]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$categories = getCategories();

// Function to get dominant color from image
function getDominantColor($imagePath) {
    if (!file_exists($imagePath)) {
        return '#36db85'; // Default color if image doesn't exist
    }

    // Reduce size for faster processing
    $size = 150;
    $image = imagecreatefromstring(file_get_contents($imagePath));
    $scaled = imagescale($image, $size, $size);
    imagedestroy($image);
    
    $totalR = $totalG = $totalB = 0;
    $count = 0;
    
    for ($x = 0; $x < imagesx($scaled); $x++) {
        for ($y = 0; $y < imagesy($scaled); $y++) {
            $rgb = imagecolorat($scaled, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            // Skip very light or very dark pixels
            $brightness = ($r + $g + $b) / 3;
            if ($brightness < 20 || $brightness > 240) continue;
            
            $totalR += $r;
            $totalG += $g;
            $totalB += $b;
            $count++;
        }
    }
    
    imagedestroy($scaled);
    
    if ($count === 0) return '#36db85'; // Default if no suitable colors found
    
    $avgR = round($totalR / $count);
    $avgG = round($totalG / $count);
    $avgB = round($totalB / $count);
    
    return sprintf("#%02x%02x%02x", $avgR, $avgG, $avgB);
}

// Get the dominant color from the app icon
$iconPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/icons/' . $app['icon'];
$dominantColor = getDominantColor($iconPath);

// Function to adjust color brightness
function adjustBrightness($hex, $steps) {
    $steps = max(-255, min(255, $steps));
    $hex = str_replace('#', '', $hex);
    
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
    }
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));
    
    $r_hex = str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
    $g_hex = str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
    $b_hex = str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    
    return '#' . $r_hex . $g_hex . $b_hex;
}

// Function to create pale color version
function createPaleColor($hex) {
    // Convert to RGB
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Mix with white (85% white, 15% original color)
    $r = round($r * 0.15 + 255 * 0.85);
    $g = round($g * 0.15 + 255 * 0.85);
    $b = round($b * 0.15 + 255 * 0.85);
    
    // For pinkish dominant colors, use your specific desired color
    if ($r > 200 && $b > 150 && $g < 150) { // If color is pinkish
        return '#ffe0fa'; // Your desired light pink color
    }
    
    // For other colors, return the mixed color
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// Generate color variations
$primaryColor = $dominantColor;
$secondaryColor = adjustBrightness($dominantColor, -30);
$accentColor = adjustBrightness($dominantColor, 40);
$lightColor = createPaleColor($dominantColor); // Using our new function
$darkColor = adjustBrightness($dominantColor, -80);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($app['name']) ?> - AppCenter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public/breadcrumb-pill.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public/app.css">
<style>
/* Per-app color theme, generated from this app's icon (dominant color).
   Overrides the static fallback :root defined in app.css. */
:root {
    --primary-color: <?= $primaryColor ?>;
    --secondary-color: <?= $secondaryColor ?>;
    --accent-color: <?= $accentColor ?>;
    --light-color: <?= $lightColor ?>;
    --dark-color: <?= $darkColor ?>;
    --success-color: <?= adjustBrightness($dominantColor, 60) ?>;
    --danger-color: <?= adjustBrightness($dominantColor, -100) ?>;
    --games-color: <?= adjustBrightness($dominantColor, -70) ?>;
    --apps-color: <?= adjustBrightness($dominantColor, 30) ?>;
    --rating-color: <?= $secondaryColor ?>;
    --button-text-color: #ffffff;
    --icon-color: #ffffff;
}
</style>
</head>
<body>

<!-- App Overview Section -->
<div class="container">
    <div class="app-overview">
        <!-- Breadcrumbs -->
        <div class="breadcrumb-pill">
            <a href="<?= SITE_URL ?>">Home</a>
            <?php
            $categoryDetails = getCategoryDetails($app['category_name']);
            if ($categoryDetails['subcategory']) {
                echo '<span class="separator">/</span>';
                echo '<a href="' . SITE_URL . '/category/' . slugify($categoryDetails['main_category']) . '/">' . htmlspecialchars($categoryDetails['main_category']) . '</a>';
                echo '<span class="separator">/</span>';
                echo '<a href="' . SITE_URL . '/category/' . slugify($categoryDetails['main_category']) . '/' . slugify($categoryDetails['subcategory']) . '/">' . htmlspecialchars($categoryDetails['subcategory']) . '</a>';
            } else {
                echo '<span class="separator">/</span>';
                echo '<a href="' . SITE_URL . '/category/' . slugify($app['category_name']) . '/">' . htmlspecialchars($app['category_name']) . '</a>';
            }
            ?>
            <span class="separator">/</span>
            <span class="breadcrumb-current"><?= htmlspecialchars($app['name']) ?></span>
        </div>

        <!-- Title -->
        <h1 class="app-title">
            <?= htmlspecialchars($app['name']) ?> MOD APK <?= htmlspecialchars($app['version']) ?> (Premium Unlocked)
        </h1>

        <!-- Content -->
        <div class="app-content">
            <!-- App Image -->
            <div class="app-image-container">
                <img src="<?= SITE_URL ?>/uploads/icons/<?= htmlspecialchars($app['icon']) ?>" class="app-image" alt="<?= htmlspecialchars($app['name']) ?>">
            </div>

            <!-- App Details Table -->
            <div class="app-details">
                <div class="details-table">
                    <!-- App Name -->
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-gamepad detail-icon"></i>
                            App Name
                        </div>
                        <div class="detail-value"><?= htmlspecialchars($app['name']) ?></div>
                    </div>
                    
                    <!-- Latest Version -->
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-bolt detail-icon"></i>
                            Latest Version
                        </div>
                        <div class="detail-value"><?= htmlspecialchars($app['version']) ?></div>
                    </div>
                    
                    <!-- Last Updated -->
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-sync-alt detail-icon"></i>
                            Last Updated
                        </div>
                        <div class="detail-value"><?= date('M d, Y', strtotime($app['updated_at'])) ?></div>
                    </div>
                    
                    <?php if (!empty($app['developer'])): ?>
                    <!-- Publisher -->
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-user-tie detail-icon"></i>
                            Publisher
                        </div>
                        <div class="detail-value"><?= htmlspecialchars($app['developer']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Requirements -->
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-microchip detail-icon"></i>
                            Requirements
                        </div>
                        <div class="detail-value">Android 7.0+</div>
                    </div>
                    
                    <!-- Category -->
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-tags detail-icon"></i>
                            Category
                        </div>
                        <div class="detail-value">
                            <?php
                            $categoryDetails = getCategoryDetails($app['category_name']);
                            if ($categoryDetails['subcategory']) {
                                echo '<a href="' . SITE_URL . '/category/' . slugify($categoryDetails['main_category']) . '/' . slugify($categoryDetails['subcategory']) . '/" class="category-link">' . htmlspecialchars($categoryDetails['subcategory']) . '</a>';
                            } else {
                                echo '<a href="' . SITE_URL . '/category/' . slugify($app['category_name']) . '/" class="category-link">' . htmlspecialchars($app['category_name']) . '</a>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Size -->
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-hdd detail-icon"></i>
                            Size
                        </div>
                        <div class="detail-value"><?= !empty($app['size']) ? htmlspecialchars($app['size']) . ' MB' : 'N/A' ?></div>
                    </div>
                    
                    <!-- Mods -->
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-cog detail-icon"></i>
                            Mods
                        </div>
                        <div class="detail-value">
                            <span class="premium-badge">Premium Unlocked</span>
                        </div>
                    </div>
                    
                    <!-- Downloads -->
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-download detail-icon"></i>
                            Downloads
                        </div>
                        <div class="detail-value"><?= number_format($app['downloads']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Rating Widget -->
            <div class="rating-widget">
                <div class="rating-stars" id="ratingStars">
                    <i class="fas fa-star star" data-rating="1"></i>
                    <i class="fas fa-star star" data-rating="2"></i>
                    <i class="fas fa-star star" data-rating="3"></i>
                    <i class="fas fa-star star" data-rating="4"></i>
                    <i class="fas fa-star star" data-rating="5"></i>
                </div>
                <button class="rating-button" id="submitRating" <?= $user_has_rated ? 'disabled' : '' ?>>
                    <?= $user_has_rated ? 'Already Rated' : 'Submit Rating' ?>
                </button>
                <div id="ratingFeedback" class="rating-feedback"></div>
                <div class="rating-results">
                    <p><span class="avg-rating"><?= number_format($app['rating'], 1) ?></span> Rating (<span class="vote-count"><?= number_format($app['total_ratings']) ?></span> votes)</p>
                </div>
            </div>
        </div>
        
        <?php echo displayAd('before_post', 'app'); ?>

        <!-- Download Buttons -->
        <div class="download-buttons">
            <a href="#app-description-end" class="download-button" id="main-download-btn">
                <i class="fas fa-download download-icon"></i>
                Download (<?= !empty($app['size']) ? htmlspecialchars($app['size']) . 'MB' : 'APK' ?>)
            </a>
        </div>
    </div>
</div>
    
<!-- App Description Section -->
<div class="container">
    <div class="app-description-card">
        <div class="app-description-content">
            <h3>Description</h3>
            <div class="app-description-text">
                <?php
                // First, decode the HTML content
                $description = htmlspecialchars_decode($app['description']);
                
                // Split into paragraphs while preserving HTML structure
                $paragraphs = preg_split('/(<\/p>|<br\s*\/?>\s*<\/br>|<\/div>)/i', $description, -1, PREG_SPLIT_DELIM_CAPTURE);
                
                $paragraphCount = 0;
                $output = '';
                
                foreach ($paragraphs as $i => $paragraph) {
                    // Skip empty paragraphs
                    if (trim(strip_tags($paragraph)) === '') continue;
                    
                    $paragraphCount++;
                    $output .= $paragraph;
                    
                    // Add closing tags we split on
                    if (isset($paragraphs[$i+1])) {
                        $closingTag = $paragraphs[$i+1];
                        if (in_array(strtolower($closingTag), ['</p>', '<br>', '</br>', '</div>'])) {
                            $output .= $closingTag;
                        }
                    }
                    
                    // Show ad only when paragraphCount exactly matches the interval
                    echo $output;
                    echo displayAd('between_paragraph', 'app', $paragraphCount);
                    $output = '';
                }
                
                // Output any remaining content
                echo $output;
                ?>
            </div>
            
            <?php if (!empty($app['features'])): ?>
            <h3 style="margin-top: 30px;">Key Features</h3>
            <ul style="padding-left: 20px; line-height: 1.8;">
                <?php foreach (explode("\n", $app['features']) as $feature): ?>
                    <?php if (!empty(trim($feature))): ?>
                        <li><?= htmlspecialchars(trim($feature)) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            
            <?php echo displayAd('after_post', 'app'); ?>
            
            <!-- Anchor for scroll target -->
            <div id="app-description-end"></div>
            
            <!-- Get Link Button Container -->
            <div class="get-link-container" id="get-link-container">
                <a href="/<?= $app['slug'] ?>/download" class="get-link-button" id="get-link-button">
                    <i class="fas fa-link"></i> Get Download Link
                </a>
                <p class="security-note">
                    <i class="fas fa-shield-alt"></i> Secure verification required
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Related Apps Section -->
<div class="container">
    <div class="related-apps-card">
        <div class="section-header">
            <h3>
                <div class="section-icon" style="--gradient-from: #4CC9F0; --gradient-to: #00A6FF; --shadow-color: #4CC9F0;">
                    <svg class="svg-inline--fa fa-shapes" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 10.9c-.61 0-1.1.49-1.1 1.1s.49 1.1 1.1 1.1c.61 0 1.1-.49 1.1-1.1s-.49-1.1-1.1-1.1zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm2.19 12.19L6 18l3.81-8.19L18 6l-3.81 8.19z"></path></svg>
                </div>
                Recommended for you
            </h3>
        </div>
        
        <div class="related-apps-grid">
            <?php
            $related_apps = getRelatedApps($app['id'], $app['category_id']);
            if (!empty($related_apps)): 
                foreach ($related_apps as $related_app): 
                    $category_details = getCategoryDetails($related_app['category_name']);
                    $is_game = strpos(strtolower($related_app['category_name']), 'game') !== false;
            ?>
                <div class="related-app-card" onclick="window.location.href='<?= SITE_URL ?>/<?= $related_app['slug'] ?>'">
                    <div class="related-app-card-img">
                        <img src="<?= SITE_URL ?>/uploads/icons/<?= htmlspecialchars($related_app['icon']) ?>" alt="<?= htmlspecialchars($related_app['name']) ?>">
                        <?php if ($is_game): ?>
                            <span class="game-badge">Game</span>
                        <?php else: ?>
                            <span class="app-badge">App</span>
                        <?php endif; ?>
                    </div>
                    <div class="related-app-card-body">
                        <h3 class="related-app-card-title"><?= htmlspecialchars($related_app['name']) ?></h3>
                        <div class="related-app-card-meta">
                            <span class="related-app-category"><?= !empty($category_details['subcategory']) ? htmlspecialchars($category_details['subcategory']) : htmlspecialchars($category_details['main_category']) ?></span>
                        </div>
                        <div class="related-app-card-details">
                            <div class="related-app-detail">
                                <svg class="svg-inline--fa fa-arrow-down-to-bracket" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="arrow-down-to-bracket" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" data-fa-i2svg="">
                                    <path fill="#4ccb70" d="M448 416v-64c0-17.67-14.33-32-32-32s-32 14.33-32 32v64c0 17.67-14.33 32-32 32H96c-17.67 0-32-14.33-32-32v-64c0-17.67-14.33-32-32-32s-32 14.33-32 32v64c0 53.02 42.98 96 96 96h256C405 512 448 469 448 416zM246.6 342.6l128-128c12.51-12.51 12.49-32.76 0-45.25c-12.5-12.5-32.75-12.5-45.25 0L256 242.8V32c0-17.69-14.31-32-32-32S192 14.31 192 32v210.8L118.6 169.4c-12.5-12.5-32.75-12.5-45.25 0s-12.5 32.75 0 45.25l128 128C213.9 355.1 234.1 355.1 246.6 342.6z"></path>
                                </svg>
                                <span class="related-app-size"><?= !empty($related_app['size']) ? htmlspecialchars($related_app['size']) . ' MB' : 'N/A' ?></span>
                            </div>
                            <div class="related-app-detail">
                                <svg class="svg-inline--fa fa-bolt text-lg" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="bolt" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" data-fa-i2svg="">
                                    <path fill="#4ccb70" d="M240.5 224H352C365.3 224 377.3 232.3 381.1 244.7C386.6 257.2 383.1 271.3 373.1 280.1L117.1 504.1C105.8 513.9 89.27 514.7 77.19 505.9C65.1 497.1 60.7 481.1 66.59 467.4L143.5 288H31.1C18.67 288 6.733 279.7 2.044 267.3C-2.645 254.8 .8944 240.7 10.93 231.9L266.9 7.918C278.2-1.92 294.7-2.669 306.8 6.114C318.9 14.9 323.3 30.87 317.4 44.61L240.5 224z"></path>
                                </svg>
                                <span class="related-app-version">v<?= htmlspecialchars($related_app['version']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php 
                endforeach;
            else: ?>
                <p style="grid-column: 1 / -1; text-align: center;">No related apps found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Comment Form Section -->
<div class="container">
    <div class="comment-form-card">
        <div class="section-header">
            <h3>
                <div class="section-icon" style="--gradient-from: #DF85FF; --gradient-to: #AD00FF; --shadow-color: #DF85FF;">
                    <svg class="svg-inline--fa" aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                    <path fill="currentColor" d="M362.7 19.32C387.7-5.678 428.3-5.678 453.3 19.32L492.7 58.75C517.7 83.74 517.7 124.3 492.7 149.3L444.3 197.7L314.3 67.72L362.7 19.32zM421.7 220.3L188.5 453.4C178.1 463.8 165.2 471.5 151.1 475.6L30.77 511C22.35 513.5 13.24 511.2 7.03 504.1C.8198 498.8-1.502 489.7 .976 481.2L36.37 360.9C40.53 346.8 48.16 333.9 58.57 323.5L291.7 90.34L421.7 220.3z"></path>
                </svg>
                </div>
                Leave a comment
            </h3>
        </div>
        
        <div class="comment-form-content">
            <?php if (isset($comment_success)): ?>
                <div class="success-message" style="display: block;">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($comment_success) ?>
                </div>
            <?php endif; ?>
            
            <div class="form-wrapper">
                <form class="form" method="post" id="commentForm">
                    <div class="form-group">
                        <label for="comment" class="form-label">Message</label>
                        <textarea id="comment" name="comment" class="form-input textarea" rows="5" minlength="5" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="author" class="form-label">Full Name</label>
                            <input type="text" id="author" name="author" class="form-input" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" autocomplete="email" required>
                            <?php if (isset($comment_error)): ?>
                                <div class="error-message" style="display: block;">
                                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($comment_error) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <p class="checkbox">
                        <input id="cookies-consent" name="cookies-consent" type="checkbox" value="yes">
                        <label for="cookies-consent">Save my name, email, and website in this browser for the next time I comment.</label>
                    </p>
                    
                    <button type="submit" class="btn" name="post-comment" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Post Comment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Comments List Section -->
<div class="container">
    <div class="comments-list-card">
        <div class="section-header">
            <h3>
                <div class="section-icon" style="--gradient-from: #FFC785; --gradient-to: #FF9900; --shadow-color: #FFC785;">
                    <svg class="svg-inline--fa fa-comments" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="comments" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" data-fa-i2svg="">
                        <path fill="currentColor" d="M416 176C416 78.8 322.9 0 208 0S0 78.8 0 176c0 39.57 15.62 75.96 41.67 105.4c-16.39 32.76-39.23 57.32-39.59 57.68c-2.1 2.205-2.67 5.475-1.441 8.354C1.9 350.3 4.602 352 7.66 352c38.35 0 70.76-11.12 95.74-24.04C134.2 343.1 169.8 352 208 352C322.9 352 416 273.2 416 176zM599.6 443.7C624.8 413.9 640 376.6 640 336C640 238.8 554 160 448 160c-.3145 0-.6191 .041-.9336 .043C447.5 165.3 448 170.6 448 176c0 98.62-79.68 181.2-186.1 202.5C282.7 455.1 357.1 512 448 512c33.69 0 65.32-8.008 92.85-21.98C565.2 502 596.1 512 632.3 512c3.059 0 5.76-1.725 7.02-4.605c1.229-2.879 .6582-6.148-1.441-8.354C637.6 498.7 615.9 475.3 599.6 443.7z"></path>
                    </svg>
                </div>
                Comments (<?= count($comments) ?>)
            </h3>
        </div>
        
        <div class="comments-list-content" id="commentsList">
            <?php if (empty($comments)): ?>
                <p>
                    No comments yet. Be the first to comment!
                </p>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment">
                        <div class="comment-header">
                            <div>
                                <span class="comment-author">
                                    <div class="comment-avatar">
                                        <?= strtoupper(substr($comment['name'], 0, 1)) ?>
                                    </div>
                                    <?= htmlspecialchars($comment['name']) ?>
                                </span>
                            </div>
                            <div class="comment-date"><?= date('M d, Y', strtotime($comment['created_at'])) ?></div>
                        </div>
                        <div class="comment-content">
                            <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Rating system
    const stars = document.querySelectorAll('#ratingStars .star');
    const submitBtn = document.getElementById('submitRating');
    const feedbackEl = document.getElementById('ratingFeedback');
    const avgRatingSpan = document.querySelector('.avg-rating');
    const voteCountSpan = document.querySelector('.vote-count');
    let selectedRating = <?= $user_has_rated ? $user_rating : 0 ?>;
    
    function highlightStars(count) {
        stars.forEach(star => {
            star.classList.toggle('filled', parseInt(star.dataset.rating) <= count);
        });
    }
    
    highlightStars(selectedRating);
    
    stars.forEach(star => {
        star.addEventListener('mouseover', function() {
            if (!submitBtn.disabled) {
                highlightStars(parseInt(this.dataset.rating));
            }
        });
        
        star.addEventListener('mouseout', function() {
            if (!submitBtn.disabled) {
                highlightStars(selectedRating);
            }
        });
        
        star.addEventListener('click', function() {
            if (!submitBtn.disabled) {
                selectedRating = parseInt(this.dataset.rating);
                highlightStars(selectedRating);
            }
        });
    });
    
    // Submit rating
    submitBtn.addEventListener('click', async function() {
        if (selectedRating === 0 || submitBtn.disabled) return;
        
        submitBtn.disabled = true;
        feedbackEl.textContent = 'Submitting...';
        feedbackEl.style.display = 'block';
        feedbackEl.style.color = '#4a5568';
        
        try {
            const response = await fetch('submit_rating.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `rating=${selectedRating}&slug=<?= $app['slug'] ?>`
            });
            
            if (!response.ok) {
                throw new Error(`Server returned ${response.status} status`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to submit rating');
            }
            
            submitBtn.textContent = 'Already Rated';
            feedbackEl.textContent = data.message;
            feedbackEl.style.color = '#00c35e';
            
            avgRatingSpan.textContent = data.avg_rating;
            voteCountSpan.textContent = data.total_ratings;
            
            setTimeout(() => {
                feedbackEl.style.display = 'none';
            }, 3000);
        } catch (error) {
            feedbackEl.textContent = 'Error submitting rating. Please try again.';
            feedbackEl.style.color = '#f72585';
            submitBtn.disabled = false;
            
            console.error('Rating error:', error);
            
            setTimeout(() => {
                feedbackEl.style.display = 'none';
            }, 5000);
        }
    });
    
    // Download button behavior
    const mainDownloadBtn = document.getElementById('main-download-btn');
    const getLinkContainer = document.getElementById('get-link-container');
    const getLinkButton = document.getElementById('get-link-button');
    const descriptionEnd = document.getElementById('app-description-end');

    function handleDownloadClick(e) {
        e.preventDefault();
        
        // Scroll to description end
        descriptionEnd.scrollIntoView({ behavior: 'smooth' });
        
        // Show get link button
        getLinkContainer.style.display = 'block';
        getLinkContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
        // Show processing state with countdown
        let secondsLeft = 10;
        getLinkButton.innerHTML = `<i class="fas fa-circle-notch fa-spin"></i> Scanning...Please Wait ${secondsLeft}s`;
        getLinkButton.classList.add('processing');
        
        // Start countdown
        const countdownInterval = setInterval(() => {
            secondsLeft--;
            getLinkButton.innerHTML = `<i class="fas fa-circle-notch fa-spin"></i> Scanning...Please Wait ${secondsLeft}s`;
            
            if (secondsLeft <= 0) {
                clearInterval(countdownInterval);
                getLinkButton.innerHTML = '<i class="fas fa-link"></i> Get Download Link';
                getLinkButton.classList.remove('processing');
                getLinkButton.href = "/<?= $app['slug'] ?>/download";
            }
        }, 1000);
    }

    if (mainDownloadBtn) {
        mainDownloadBtn.addEventListener('click', handleDownloadClick);
    }
    
    // Prevent the scanning button from triggering download
    getLinkButton.addEventListener('click', function(e) {
        if (this.classList.contains('processing')) {
            e.preventDefault();
        }
    });
    
    // Comment form validation and submission
    const commentForm = document.getElementById('commentForm');
    const emailInput = document.getElementById('email');
    const submitCommentBtn = document.getElementById('submitBtn');
    const commentsList = document.getElementById('commentsList');
    
    function validateEmail() {
        const email = emailInput.value;
        if (!email.endsWith('@gmail.com')) {
            emailInput.style.borderColor = '#e74c3c';
            return false;
        } else {
            emailInput.style.borderColor = '#ddd';
            return true;
        }
    }
    
    emailInput.addEventListener('input', validateEmail);
    
    commentForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!validateEmail()) {
        alert('Please use a valid @gmail.com email address');
        return;
    }
    
    submitCommentBtn.disabled = true;
    submitCommentBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';
    
    try {
        const formData = new FormData(commentForm);
        formData.append('app_id', '<?= $app['id'] ?>');
        
        const response = await fetch('submit_comment.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to post comment');
        }
        
        // Create the new comment HTML
        const commentHtml = `
            <div class="comment">
                <div class="comment-header">
                    <div>
                        <span class="comment-author">
                            <div class="comment-avatar">
                                ${data.comment.name.charAt(0).toUpperCase()}
                            </div>
                            ${data.comment.name}
                        </span>
                    </div>
                    <div class="comment-date">${data.comment.date}</div>
                </div>
                <div class="comment-content">
                    ${data.comment.comment}
                </div>
            </div>
        `;
        
        // Add the new comment to the top of the comments list
        if (commentsList.querySelector('p')) {
            // If there was a "no comments" message, replace it
            commentsList.innerHTML = commentHtml;
        } else {
            // Otherwise prepend the new comment
            commentsList.insertAdjacentHTML('afterbegin', commentHtml);
        }
        
        // Update the comment count - SAFER VERSION
        const commentCount = document.querySelector('.section-header h3');
        if (commentCount) {
            const match = commentCount.textContent.match(/\((\d+)\)/);
            const currentCount = match ? parseInt(match[1]) : 0;
            commentCount.innerHTML = commentCount.innerHTML.replace(/\(\d+\)/, `(${currentCount + 1})`) || 
                                   `Comments (1)`;
        }
        
        // Show success message
        let successMessage = document.querySelector('.success-message');
        if (!successMessage) {
            successMessage = document.createElement('div');
            successMessage.className = 'success-message';
            document.querySelector('.comment-form-content').prepend(successMessage);
        }
        successMessage.style.display = 'block';
        successMessage.innerHTML = '<i class="fas fa-check-circle"></i> Comment posted successfully!';
        
        // Hide success message after 5 seconds
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
        
        // Reset the form
        commentForm.reset();
        
    } catch (error) {
        console.error('Error:', error);
        alert(error.message || 'An error occurred while submitting the comment');
    } finally {
        submitCommentBtn.disabled = false;
        submitCommentBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Post Comment';
    }
});
});
</script>

<?php echo displayStickySidebarAds('index'); ?>
    
<?php echo displayPopupAd(); ?>

<?php 
require_once 'includes/footer.php';
?>
</body>
</html>
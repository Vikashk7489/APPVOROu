<?php
require_once 'config.php';

function getRelatedApps($current_app_id, $category_id, $limit = 4) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT apps.*, categories.name as category_name 
        FROM apps 
        LEFT JOIN categories ON apps.category_id = categories.id
        WHERE apps.category_id = :category_id 
        AND apps.id != :current_app_id
        ORDER BY RAND()
        LIMIT :limit
    ");
    
    $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    $stmt->bindParam(':current_app_id', $current_app_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCategories($withCount = false) {
    global $pdo;
    
    // Base query
    $query = "SELECT c.*";
    
    // Add count if requested
    if ($withCount) {
        $query .= ", COUNT(a.id) as app_count";
    }
    
    $query .= " FROM categories c";
    
    // Join with apps table if counting
    if ($withCount) {
        $query .= " LEFT JOIN apps a ON a.category_id = c.id";
    }
    
    $query .= " GROUP BY c.id ORDER BY c.name ASC";
    
    $stmt = $pdo->query($query);
    $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hierarchy = [];
    foreach ($allCategories as $category) {
        // Check if name contains ">" delimiter
        if (strpos($category['name'], ' > ') !== false) {
            list($mainCat, $subCat) = explode(' > ', $category['name'], 2);
            
            if (!isset($hierarchy[$mainCat])) {
                $hierarchy[$mainCat] = [
                    'name' => $mainCat,
                    'slug' => slugify($mainCat),
                    'app_count' => 0, // Initialize main category count
                    'subcategories' => []
                ];
            }
            
            $hierarchy[$mainCat]['subcategories'][] = [
                'name' => $subCat,
                'slug' => $category['slug'],
                'id' => $category['id'],
                'app_count' => $withCount ? $category['app_count'] : 0
            ];
            
            // Sum subcategory counts to parent
            if ($withCount) {
                $hierarchy[$mainCat]['app_count'] += $category['app_count'];
            }
        } else {
            // This is a main category
            if (!isset($hierarchy[$category['name']])) {
                $hierarchy[$category['name']] = [
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'id' => $category['id'],
                    'app_count' => $withCount ? $category['app_count'] : 0,
                    'subcategories' => []
                ];
            }
        }
    }
    
    return array_values($hierarchy);
}

function getCategoryDetails($category_name) {
    if (strpos($category_name, ' > ') !== false) {
        $parts = explode(' > ', $category_name);
        return [
            'main_category' => $parts[0],
            'subcategory' => $parts[1]
        ];
    }
    return [
        'main_category' => $category_name,
        'subcategory' => null
    ];
}

function getApps($category_id = null) {
    global $pdo;
    $sql = "SELECT apps.*, categories.name as category_name FROM apps LEFT JOIN categories ON apps.category_id = categories.id";
    
    if ($category_id) {
        $sql .= " WHERE apps.category_id = :category_id";
    }
    
    $sql .= " ORDER BY apps.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    
    if ($category_id) {
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAppBySlug($slug) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT apps.*, categories.name as category_name FROM apps LEFT JOIN categories ON apps.category_id = categories.id WHERE apps.slug = :slug");
    $stmt->bindParam(':slug', $slug);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function searchApps($query) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT apps.*, categories.name as category_name FROM apps LEFT JOIN categories ON apps.category_id = categories.id WHERE apps.name LIKE :query OR apps.description LIKE :query");
    $searchQuery = "%$query%";
    $stmt->bindParam(':query', $searchQuery);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function deleteApp($app_id) {
    global $pdo;
    
    try {
        // First get the app details to delete associated files
        $stmt = $pdo->prepare("SELECT icon, apk_file FROM apps WHERE id = ?");
        $stmt->execute([$app_id]);
        $app = $stmt->fetch();
        
        if ($app) {
            // Delete icon file if exists
            if (!empty($app['icon'])) {
                $iconPath = '../uploads/icons/' . $app['icon'];
                if (file_exists($iconPath)) {
                    unlink($iconPath);
                }
            }
            
            // Delete APK file if exists
            if (!empty($app['apk_file'])) {
                $apkPath = '../uploads/apks/' . $app['apk_file'];
                if (file_exists($apkPath)) {
                    unlink($apkPath);
                }
            }
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM apps WHERE id = ?");
        $stmt->execute([$app_id]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        // Log error or handle it appropriately
        error_log("Error deleting app: " . $e->getMessage());
        return false;
    }
}

function incrementDownloadCount($app_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE apps SET downloads = downloads + 1 WHERE id = :id");
    $stmt->bindParam(':id', $app_id);
    $stmt->execute();
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

/**
 * Strips <script> tags and on*="" event handler attributes from an SVG
 * file on disk. Uploaded SVGs can otherwise carry embedded JavaScript,
 * which is a stored-XSS risk since the file is later served/viewed
 * directly. This is a best-effort cleanup, not a full XML sanitizer.
 */
function sanitizeSvgFile($path) {
    $svg = @file_get_contents($path);
    if ($svg === false) {
        return false;
    }

    $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
    $svg = preg_replace('#\son\w+\s*=\s*"[^"]*"#i', '', $svg);
    $svg = preg_replace("#\son\w+\s*=\s*'[^']*'#i", '', $svg);
    $svg = preg_replace('#(href|xlink:href)\s*=\s*"\s*javascript:[^"]*"#i', '', $svg);
    $svg = preg_replace('#<foreignObject\b.*?</foreignObject>#is', '', $svg);

    return @file_put_contents($path, $svg) !== false;
}

function uploadFile($file, $targetDir, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid upload'];
    }

    // Trusted MIME type -> extension we will save the file as. The
    // client-supplied MIME type and original filename/extension are
    // never trusted directly (both are attacker-controlled) — the real
    // saved extension always comes from this map or the APK check below.
    $imageMap = [
        'image/jpeg'               => 'jpg',
        'image/png'                => 'png',
        'image/gif'                => 'gif',
        'image/webp'               => 'webp',
        'image/svg+xml'            => 'svg',
        'image/x-icon'             => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    $isApkUpload = in_array('application/vnd.android.package-archive', $allowedTypes, true);
    $isSvg = false;

    if ($isApkUpload) {
        // APK files are ZIP archives. Browsers/OSes report inconsistent
        // MIME types for them (application/octet-stream is common and
        // unavoidable), so $file['type'] can't be trusted here. Instead
        // we verify the real file content starts with the ZIP file
        // signature, and always force a .apk extension.
        $handle = @fopen($file['tmp_name'], 'rb');
        $header = $handle ? fread($handle, 4) : '';
        if ($handle) fclose($handle);

        if ($header !== "PK\x03\x04" && $header !== "PK\x05\x06") {
            return ['success' => false, 'message' => 'File does not look like a valid APK (not a zip archive)'];
        }

        $extension = 'apk';
    } else {
        // Images: verify using the real file content on disk, not the
        // client-sent MIME type or filename extension.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) finfo_close($finfo);

        if (!$realMime || !isset($imageMap[$realMime]) || !in_array($realMime, $allowedTypes, true)) {
            return ['success' => false, 'message' => 'Invalid file type'];
        }

        // getimagesize() confirms the file really decodes as a raster
        // image. SVG (XML) and ICO (not reliably supported by
        // getimagesize) are exempt from this specific check.
        $skipImageSizeCheck = in_array($realMime, ['image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'], true);
        if (!$skipImageSizeCheck && @getimagesize($file['tmp_name']) === false) {
            return ['success' => false, 'message' => 'Invalid or corrupted image file'];
        }

        $isSvg = ($realMime === 'image/svg+xml');
        $extension = $imageMap[$realMime];
    }

    // The saved filename is always freshly generated — the original
    // filename (and any extension it carried) is discarded entirely.
    $filename = bin2hex(random_bytes(12)) . '.' . $extension;
    $targetPath = rtrim($targetDir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }

    @chmod($targetPath, 0644);

    if ($isSvg) {
        sanitizeSvgFile($targetPath);
    }

    return ['success' => true, 'filename' => $filename];
}

/** ---------------- Blog Functions ---------------- **/

function ensureBlogTable() {
    global $pdo;
    static $checked = false;
    if ($checked) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
        id INT NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        excerpt TEXT,
        content LONGTEXT NOT NULL,
        featured_image VARCHAR(255) DEFAULT NULL,
        meta_title VARCHAR(255) DEFAULT NULL,
        meta_description TEXT DEFAULT NULL,
        status VARCHAR(10) NOT NULL DEFAULT 'published',
        view_count INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT current_timestamp(),
        updated_at DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (id),
        UNIQUE KEY (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Migration: add view_count to installs that created blog_posts
    // before view tracking existed.
    $hasViewCount = $pdo->query("SHOW COLUMNS FROM blog_posts LIKE 'view_count'")->fetch();
    if (!$hasViewCount) {
        $pdo->exec("ALTER TABLE blog_posts ADD COLUMN view_count INT NOT NULL DEFAULT 0");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_comments (
        id INT NOT NULL AUTO_INCREMENT,
        post_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id),
        KEY post_id (post_id),
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $checked = true;
}

/** ---------------- FAQ Functions ---------------- **/

function ensureFaqTable() {
    global $pdo;
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS faqs (
        id INT NOT NULL AUTO_INCREMENT,
        question VARCHAR(500) NOT NULL,
        answer TEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT current_timestamp(),
        updated_at DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Seed with the original FAQ content (proofread) the first time the
    // table is created, so existing sites don't lose their FAQ section.
    $count = (int)$pdo->query("SELECT COUNT(*) FROM faqs")->fetchColumn();
    if ($count > 0) return;

    $defaults = [
        [
            'question' => 'What is Apk Droid?',
            'answer' => "Apk Droid is a website for downloading modified versions of popular apps and games. We test every file before publishing it, so you don't need to look anywhere else for the modded version you need."
        ],
        [
            'question' => 'How Do I Install an OBB File?',
            'answer' => "An OBB file is an expansion file used by some Android apps and games to store extra data — such as graphics, audio, or levels — that doesn't fit inside the main APK.\n\nTo install one:\n1. Download the APK and OBB file from our site.\n2. The OBB file is provided as a .zip archive.\n3. Extract it with a file manager app (such as CX File Explorer), then copy the OBB folder to Android/obb/ (for example, Android/obb/com.pubg.imobile).\n4. Install the APK and open the app. That's it."
        ],
        [
            'question' => 'What Is an APK Installer?',
            'answer' => "An APK Installer is an alternative way to install a game that comes bundled with its OBB file, combining both into a single, faster installation process."
        ],
        [
            'question' => 'Why Is My Download Not Working?',
            'answer' => "We use fast cloud storage links for downloads, but occasionally a link can go down. If a download isn't working, please leave a comment on the app's page and we'll fix it as soon as possible."
        ],
        [
            'question' => 'What Are OBB Files Used For?',
            'answer' => "Some apps and games use advanced graphics, audio, or simulation features that require extra data beyond what fits in the APK. That extra data is stored in OBB files, which the app loads separately.\n\nWhen you install an app from the Play Store, its OBB files are downloaded automatically, so you don't need to install them manually. With APKs from outside the Play Store, however, you'll usually need to install the OBB file yourself."
        ],
        [
            'question' => 'Why Won\'t the APK Install on My Device?',
            'answer' => "This is usually caused by having the same app already installed from another source.\n\n1. Uninstall the existing version of the app or game from your device.\n2. Try installing our version again — it should now install successfully."
        ],
        [
            'question' => 'Why Is the APK Not Working Properly?',
            'answer' => "This usually happens when the original app has been updated and our modded version hasn't caught up yet. Check Apk Droid for the latest version of the mod and download that instead."
        ],
        [
            'question' => 'How Do I Install a Modded APK?',
            'answer' => "Follow these simple steps:\n1. Search for the app on Apk Droid.\n2. Scroll to the download section.\n3. Tap the download button.\n4. Wait for the download link to appear, then tap it.\n5. Wait for the file to finish downloading.\n6. Tap the downloaded file to begin installation.\n7. If prompted, go to Settings > Privacy and allow installation from unknown sources.\n8. Wait for installation to complete.\n9. Open the app and enjoy."
        ],
        [
            'question' => 'Can I Get Paid Apps for Free as Mods?',
            'answer' => "Yes. Every premium app mod on our site is unlocked, so you get all the pro features for free."
        ],
    ];

    $stmt = $pdo->prepare("INSERT INTO faqs (question, answer, sort_order, is_active) VALUES (?, ?, ?, 1)");
    foreach ($defaults as $index => $faq) {
        $stmt->execute([$faq['question'], $faq['answer'], $index]);
    }
}

function getFaqs($activeOnly = false) {
    global $pdo;
    ensureFaqTable();
    $sql = "SELECT * FROM faqs";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC, id ASC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getFaqById($id) {
    global $pdo;
    ensureFaqTable();
    $stmt = $pdo->prepare("SELECT * FROM faqs WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function ensureDefaultMenuItems() {
    global $pdo;
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $count = (int)$pdo->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
    if ($count > 0) return;

    $defaults = [
        [
            'title' => 'Home',
            'url' => SITE_URL . '/',
            'icon_svg' => '<svg aria-hidden="true" role="img" viewBox="0 0 576 512"><path fill="currentColor" d="M575.8 255.5C575.8 273.5 560.8 287.6 543.8 287.6H511.8L512.5 447.7C512.5 450.5 512.3 453.1 512 455.8V472C512 494.1 494.1 512 472 512H456C454.9 512 453.8 511.1 452.7 511.9C451.3 511.1 449.9 512 448.5 512H392C369.9 512 352 494.1 352 472V384C352 366.3 337.7 352 320 352H256C238.3 352 224 366.3 224 384V472C224 494.1 206.1 512 184 512H128.1C126.6 512 125.1 511.9 123.6 511.8C122.4 511.9 121.2 512 120 512H104C81.91 512 64 494.1 64 472V360C64 359.1 64.03 358.1 64.09 357.2V287.6H32.05C14.02 287.6 0 273.5 0 255.5C0 246.5 3.004 238.5 10.01 231.5L266.4 8.016C273.4 1.002 281.4 0 288.4 0C295.4 0 303.4 2.004 309.5 7.014L564.8 231.5C572.8 238.5 576.9 246.5 575.8 255.5L575.8 255.5z"></path></svg>',
            'gradient_from' => '#FFC785',
            'gradient_to' => '#FF9900',
            'shadow_color' => '#FFC785',
            'is_category' => 0,
        ],
        [
            'title' => 'Games',
            'url' => SITE_URL . '/category/games',
            'icon_svg' => '<svg class="svg-inline--fa" aria-hidden="true" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path fill="currentColor" d="M640 384.2c0-5.257-.4576-10.6-1.406-15.98l-33.38-211.6C591.4 77.96 522 32 319.1 32C119 32 48.71 77.46 34.78 156.6l-33.38 211.6c-.9487 5.383-1.406 10.72-1.406 15.98c0 51.89 44.58 95.81 101.5 95.81c49.69 0 93.78-30.06 109.5-74.64l7.5-21.36h203l7.5 21.36c15.72 44.58 59.81 74.64 109.5 74.64C595.4 479.1 640 436.1 640 384.2zM247.1 248l-31.96-.0098L215.1 280c0 13.2-10.78 24-23.98 24c-13.2 0-24.02-10.8-24.02-24l.0367-32.01L135.1 248c-13.2 0-23.98-10.8-23.98-24c0-13.2 10.77-24 23.98-24l32.04-.011L167.1 168c0-13.2 10.82-24 24.02-24c13.2 0 23.98 10.8 23.98 24l.0368 31.99L247.1 200c13.2 0 24.02 10.8 24.02 24C271.1 237.2 261.2 248 247.1 248zM432 311.1c-22.09 0-40-17.92-40-40c0-22.08 17.91-40 40-40s40 17.92 40 40C472 294.1 454.1 311.1 432 311.1zM496 215.1c-22.09 0-40-17.92-40-40c0-22.08 17.91-40 40-40s40 17.92 40 40C536 198.1 518.1 215.1 496 215.1z"></path></svg>',
            'gradient_from' => '#FF8A8A',
            'gradient_to' => '#FF3B3B',
            'shadow_color' => '#FF8A8A',
            'is_category' => 0,
        ],
        [
            'title' => 'Apps',
            'url' => SITE_URL . '/category/apps',
            'icon_svg' => '<svg class="svg-inline--fa" aria-hidden="true" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M411.4 175.5C417.4 185.4 417.5 197.7 411.8 207.8C406.2 217.8 395.5 223.1 384 223.1H192C180.5 223.1 169.8 217.8 164.2 207.8C158.5 197.7 158.6 185.4 164.6 175.5L260.6 15.54C266.3 5.897 276.8 0 288 0C299.2 0 309.7 5.898 315.4 15.54L411.4 175.5zM288 312C288 289.9 305.9 272 328 272H472C494.1 272 512 289.9 512 312V456C512 478.1 494.1 496 472 496H328C305.9 496 288 478.1 288 456V312zM0 384C0 313.3 57.31 256 128 256C198.7 256 256 313.3 256 384C256 454.7 198.7 512 128 512C57.31 512 0 454.7 0 384z"></path></svg>',
            'gradient_from' => '#DF85FF',
            'gradient_to' => '#AD00FF',
            'shadow_color' => '#DF85FF',
            'is_category' => 0,
        ],
        [
            'title' => 'Articles',
            'url' => SITE_URL . '/blog',
            'icon_svg' => '<svg class="svg-inline--fa" aria-hidden="true" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M480 32H128C110.3 32 96 46.33 96 64v336C96 408.8 88.84 416 80 416S64 408.8 64 400V96H32C14.33 96 0 110.3 0 128v288c0 35.35 28.65 64 64 64h384c35.35 0 64-28.65 64-64V64C512 46.33 497.7 32 480 32zM272 416h-96C167.2 416 160 408.8 160 400C160 391.2 167.2 384 176 384h96c8.836 0 16 7.162 16 16C288 408.8 280.8 416 272 416zM272 320h-96C167.2 320 160 312.8 160 304C160 295.2 167.2 288 176 288h96C280.8 288 288 295.2 288 304C288 312.8 280.8 320 272 320zM432 416h-96c-8.836 0-16-7.164-16-16c0-8.838 7.164-16 16-16h96c8.836 0 16 7.162 16 16C448 408.8 440.8 416 432 416zM432 320h-96C327.2 320 320 312.8 320 304C320 295.2 327.2 288 336 288h96C440.8 288 448 295.2 448 304C448 312.8 440.8 320 432 320zM448 208C448 216.8 440.8 224 432 224h-256C167.2 224 160 216.8 160 208v-96C160 103.2 167.2 96 176 96h256C440.8 96 448 103.2 448 112V208z"></path></svg>',
            'gradient_from' => '#FFC061',
            'gradient_to' => '#FF8C00',
            'shadow_color' => '#FFC061',
            'is_category' => 0,
        ],
        [
            'title' => 'FAQ',
            'url' => SITE_URL . '/faq',
            'icon_svg' => '<svg class="svg-inline--fa" aria-hidden="true" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path fill="currentColor" d="M416 256V63.1C416 28.75 387.3 0 352 0H64C28.75 0 0 28.75 0 63.1v192C0 291.2 28.75 320 64 320l32 .0106v54.25c0 7.998 9.125 12.62 15.5 7.875l82.75-62.12L352 319.9C387.3 320 416 291.2 416 256zM576 128H448v128c0 52.87-43.13 95.99-96 95.99l-96 .0013v31.98c0 35.25 28.75 63.1 63.1 63.1l125.8-.0073l82.75 62.12C534.9 514.8 544 510.2 544 502.2v-54.24h32c35.25 0 64-28.75 64-63.1V191.1C640 156.7 611.3 128 576 128z"></path></svg>',
            'gradient_from' => '#6EE7A8',
            'gradient_to' => '#17A05B',
            'shadow_color' => '#6EE7A8',
            'is_category' => 0,
        ],
    ];

    $stmt = $pdo->prepare("INSERT INTO menu_items (title, url, icon_svg, gradient_from, gradient_to, shadow_color, is_category, is_external, position) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)");
    foreach ($defaults as $i => $item) {
        $stmt->execute([
            $item['title'],
            $item['url'],
            $item['icon_svg'],
            $item['gradient_from'],
            $item['gradient_to'],
            $item['shadow_color'],
            $item['is_category'],
            $i,
        ]);
    }
}

function incrementBlogPostViews($post_id) {
    global $pdo;
    ensureBlogTable();
    $stmt = $pdo->prepare("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?");
    $stmt->execute([$post_id]);
}

function getBlogComments($post_id) {
    global $pdo;
    ensureBlogTable();
    $stmt = $pdo->prepare("SELECT * FROM blog_comments WHERE post_id = ? ORDER BY created_at DESC");
    $stmt->execute([$post_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function countBlogComments($post_id) {
    global $pdo;
    ensureBlogTable();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_comments WHERE post_id = ?");
    $stmt->execute([$post_id]);
    return (int) $stmt->fetchColumn();
}

function getBlogPosts($limit = null, $offset = 0, $status = 'published') {
    global $pdo;
    ensureBlogTable();

    $sql = "SELECT blog_posts.*, 
                   (SELECT COUNT(*) FROM blog_comments WHERE blog_comments.post_id = blog_posts.id) AS comment_count
            FROM blog_posts WHERE status = :status ORDER BY created_at DESC";
    if ($limit !== null) {
        $sql .= " LIMIT :limit OFFSET :offset";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':status', $status);
    if ($limit !== null) {
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function countBlogPosts($status = 'published') {
    global $pdo;
    ensureBlogTable();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE status = :status");
    $stmt->bindValue(':status', $status);
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function getAllBlogPostsAdmin() {
    global $pdo;
    ensureBlogTable();
    return $pdo->query("SELECT * FROM blog_posts ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

function getBlogPostBySlug($slug, $publishedOnly = true) {
    global $pdo;
    ensureBlogTable();
    $sql = "SELECT * FROM blog_posts WHERE slug = :slug";
    if ($publishedOnly) {
        $sql .= " AND status = 'published'";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':slug', $slug);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getBlogPostById($id) {
    global $pdo;
    ensureBlogTable();
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getRecentBlogPosts($limit = 3, $excludeId = null) {
    global $pdo;
    ensureBlogTable();
    $sql = "SELECT blog_posts.*, 
                   (SELECT COUNT(*) FROM blog_comments WHERE blog_comments.post_id = blog_posts.id) AS comment_count
            FROM blog_posts WHERE status = 'published'";
    if ($excludeId) {
        $sql .= " AND id != :excludeId";
    }
    $sql .= " ORDER BY created_at DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    if ($excludeId) {
        $stmt->bindValue(':excludeId', $excludeId, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function deleteBlogPost($post_id) {
    global $pdo;
    ensureBlogTable();
    try {
        $stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();

        if ($post && !empty($post['featured_image'])) {
            $imagePath = '../uploads/blog/' . $post['featured_image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
        $stmt->execute([$post_id]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error deleting blog post: " . $e->getMessage());
        return false;
    }
}

/** Display ads without any tracking **/
function displayAd($position, $currentPage = 'index', $paragraphCount = null) {
    global $pdo;
    
    $query = "SELECT * FROM ads 
              WHERE position = :position 
              AND (pages = 'all' OR FIND_IN_SET(:currentPage, pages))
              AND is_active = 1";
    
    // For between_paragraph ads, only show when paragraphCount exactly matches the interval
    if ($position === 'between_paragraph' && $paragraphCount !== null) {
        $query .= " AND paragraph_interval = :paragraphCount";
    }
    
    $query .= " ORDER BY RAND() LIMIT 1";
    
    $stmt = $pdo->prepare($query);
    $params = [
        ':position' => $position,
        ':currentPage' => $currentPage
    ];
    
    if ($position === 'between_paragraph' && $paragraphCount !== null) {
        $params[':paragraphCount'] = $paragraphCount;
    }
    
    $stmt->execute($params);
    
    $ad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ad) {
        return '<div class="ad-container ad-' . htmlspecialchars($position) . '">' . $ad['ad_code'] . '</div>';
    }
    
    return '';
}

/** Display Popup ads **/
function displayPopupAd() {
    global $pdo;
    
    // Get active popup ads for homepage
    $stmt = $pdo->prepare("SELECT * FROM ads WHERE position = 'popup' AND (pages = 'index' OR pages = 'all') AND is_active = 1");
    $stmt->execute();
    $popupAds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($popupAds)) {
        // Select a random popup ad
        $randomAd = $popupAds[array_rand($popupAds)];
        
        return '
<style>
* {
	margin:0;
	padding:0;
	box-sizing:border-box
}
.overlay {
	display:none;
	position:fixed;
	top:0;
	left:0;
	width:100%;
	height:100%;
	background:rgba(0,0,0,0.5);
	align-items:center;
	justify-content:center;
	z-index:5
}
.popup {
	background:#fff;
	padding:10px;
	border-radius:5px;
	text-align:center;
	position:relative
}
.popup-content {
    margin-bottom: -6px;
}
.close-icon {
	position:absolute;
	top:11px;
	right:11px;
	cursor:pointer;
	font-size:14px;
	display:none;
	color:#000;
	box-shadow:0 2px 8px rgba(99,99,99,0.2);
	background:#fff;
	border-radius:50%;
	width:22px;
	height:22px;
	line-height:22px;
	transition:all 0.3s ease;
	z-index:1
}
span#timer {
    color: black;
    background: #ffffff;
    border-radius: 50px;
    width: 17px;
    height: 17px;
}
.close-icon:hover {
	color:#555;
	background:#eee;
	transform:rotate(90deg)
}
#timer-container {
	position:absolute;
	top:-53px;
	right:-53px;
	width:150px;
	height:150px
}
#progress-container {
	position:absolute;
	top:0;
	left:0;
	width:100%;
	height:100%
}
.progress {
	transform:rotate(90deg) scale(-1,1)
}
.text {
	position:absolute;
	width:100%;
	height:100%;
	display:flex;
	align-items:center;
	justify-content:center;
	font-size:10px;
	font-weight:bold;
	color:#333
}
.overlay-circle {
	animation:overlayAnimation 66s linear forwards
}
@keyframes overlayAnimation {
	from {
	stroke-dashoffset:377
}
to {
	stroke-dashoffset:0
}
}
</style>

<div class="overlay" id="popup-ad-overlay">
  <div class="popup">
    <span class="close-icon" id="closeIcon" onclick="closePopup()">✖</span>
    <div id="timer-container">
      <div id="progress-container">
        <svg class="progress" width="150" height="150">
          <circle cx="75" cy="75" r="10" fill="transparent" stroke="#c1c1c1" stroke-width="3"></circle>
          <circle class="overlay-circle" cx="75" cy="75" r="10" fill="transparent" stroke="#f3f3f3" stroke-width="3" stroke-dasharray="377" stroke-dashoffset="377"></circle>
        </svg>
      </div>
      <div class="text"><span id="timer">10</span></div>
    </div>
<div class="popup-content">'.$randomAd['ad_code'].'</div>
    </div>
</div>

        <script>
        function openPopup() {
            document.getElementById("popup-ad-overlay").style.display = "flex";
            startTimer();
        }
        
        function startTimer() {
            var timer = document.getElementById("timer");
            var closeIcon = document.getElementById("closeIcon");
            var progressContainer = document.getElementById("progress-container");
            var seconds = 10;
            
            var interval = setInterval(function() {
                timer.textContent = seconds--;
                if (seconds < 0) {
                    clearInterval(interval);
                    closeIcon.style.display = "block";
                    timer.style.display = "none";
                    progressContainer.style.display = "none";
                }
            }, 1000);
        }
        
        function closePopup() {
            document.getElementById("popup-ad-overlay").style.display = "none";
        }
        
        // Show popup after 3 seconds
        setTimeout(openPopup, 3000);
        </script>
        ';
    }
    
    return '';
}




/** Display Sidebar ads **/
function displayStickySidebarAds($currentPage = 'index') {
    global $pdo;
    
    // Get left and right sidebar ads
    $query = "SELECT * FROM ads 
              WHERE position = 'sidebar' 
              AND (pages = 'all' OR FIND_IN_SET(:currentPage, pages))
              AND is_active = 1
              ORDER BY RAND() LIMIT 2";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':currentPage' => $currentPage]);
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($ads) >= 2) {
        // We have at least 2 ads - use one for left and one for right
        $leftAd = $ads[0];
        $rightAd = $ads[1];
    } elseif (count($ads) == 1) {
        // Only one ad - use it for both sides
        $leftAd = $ads[0];
        $rightAd = $ads[0];
    } else {
        return ''; // No ads to display
    }
    
    return <<<HTML
    <!-- Right Ads -->
    <div class="ad-contain right-ad" id="stickyright">
      <span class="close-btn outside-close left-close" id="closePopup1">⨯</span>
      <div class="ad-content">{$rightAd['ad_code']}</div>
    </div>

    <!-- Left Ads -->
    <div class="ad-contain left-ad" id="stickyleft">
      <span class="close-btn outside-close right-close" id="closePopup2">⨯</span>
      <div class="ad-content">{$leftAd['ad_code']}</div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
      const popupRight = document.getElementById('stickyright');
      const popupLeft = document.getElementById('stickyleft');
      
      setTimeout(() => {
        popupRight.style.transform = 'translateX(0) translateY(-50%)';
        popupLeft.style.transform = 'translateX(0) translateY(-50%)';
      }, 500);
      
      document.getElementById('closePopup1').addEventListener('click', () => {
        popupRight.style.transform = 'translateX(120%) translateY(-50%)';
      });
      
      document.getElementById('closePopup2').addEventListener('click', () => {
        popupLeft.style.transform = 'translateX(-120%) translateY(-50%)';
      });
    });
    </script>

    <style>
      .ad-contain {
        position: fixed;
        top: 50%;
        transform: translateY(-50%);
        background: #fff;
        border-radius: 12px;
        padding: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        z-index: 999;
        border: 1px solid rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease;
      }
      
      .right-ad {
        right: 0;
        transform: translateX(120%) translateY(-50%);
      }
      
      .left-ad {
        left: 0;
        transform: translateX(-120%) translateY(-50%);
      }
      
      .close-btn {
        width: 25px;
        height: 25px;
        background: #ff4757;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-weight: bold;
        position: absolute;
        top: -10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
      }
      
      .right-close {
        right: -10px;
      }
      
      .left-close {
        left: -10px;
      }
      
      .close-btn:hover {
        background: #0cd32d;
        transform: scale(1.1);
      }

      .ad-content {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
      }
      
      @media (max-width: 768px) {
        .ad-contain {
          width: 150px;
          padding: 6px;
        }
      }
    </style>
HTML;
}
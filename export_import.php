<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

/* ------------------------------------------------------------------ *
 *  Export / Import — full site backup & restore
 *
 *  Exports (as one .zip): categories, apps, ads, menu items,
 *  site settings, footer social links, footer links, pages, blog
 *  posts, faqs, SEO settings, header/footer code, homepage layout
 *  settings, and (optionally) the uploaded logo/icon/favicon/hero/
 *  blog images and APK files.
 *
 *  Deliberately NOT exported: admin_users (login credentials),
 *  ratings, comments, blog_comments, download_stats — these are
 *  per-installation security/activity data, not site content.
 * ------------------------------------------------------------------ */

// Uploads sub-folders that can be bundled into the export
$UPLOAD_DIRS = [
    'logos'     => '../uploads/logos/',
    'icons'     => '../uploads/icons/',
    'favicons'  => '../uploads/favicons/',
    'hero'      => '../uploads/hero/',
    'blog'      => '../uploads/blog/',
    'apks'      => '../uploads/apks/',
];

// Content tables: table => [natural key columns used to detect duplicates on Merge]
$LIST_TABLES = [
    'categories'    => ['slug'],
    'apps'          => ['slug'],
    'ads'           => ['title'],
    'menu_items'    => ['title', 'url'],
    'footer_social' => ['platform'],
    'footer_links'  => ['section_name', 'link_text', 'link_url'],
    'pages'         => ['slug'],
    'blog_posts'    => ['slug'],
    'faqs'          => ['question'],
];

// Singleton / key-value settings tables: always upserted regardless of mode
$SETTINGS_TABLES = ['site_settings', 'seo_settings', 'header_footer'];

$EXPORT_VERSION = 1;

function formatBytesEI($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function addFolderToZip(ZipArchive $zip, $localDir, $zipSubDir) {
    if (!is_dir($localDir)) return 0;
    $count = 0;
    foreach (scandir($localDir) as $file) {
        if ($file === '.' || $file === '..' || $file === '.htaccess') continue;
        $path = $localDir . $file;
        if (is_file($path)) {
            $zip->addFile($path, 'files/' . $zipSubDir . '/' . $file);
            $count++;
        }
    }
    return $count;
}

/* ============================== EXPORT ============================== */
if (isset($_POST['do_export'])) {
    csrf_verify();

    if (!class_exists('ZipArchive')) {
        $_SESSION['error_message'] = 'The PHP Zip extension is not enabled on this server, so an export archive cannot be built.';
        header("Location: export_import.php");
        exit();
    }

    $includeImages = isset($_POST['include_images']);
    $includeApks   = isset($_POST['include_apks']);

    $data = ['_meta' => [
        'exported_at'    => date('c'),
        'export_version' => $EXPORT_VERSION,
        'source'         => defined('SITE_URL') ? SITE_URL : '',
    ]];

    foreach (array_merge(array_keys($LIST_TABLES), $SETTINGS_TABLES) as $table) {
        try {
            $data[$table] = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $data[$table] = [];
        }
    }

    // Homepage layout (data/homepage_settings.json)
    $homepageFile = '../data/homepage_settings.json';
    $data['homepage_settings'] = file_exists($homepageFile)
        ? json_decode(file_get_contents($homepageFile), true)
        : null;

    $tmpZipPath = tempnam(sys_get_temp_dir(), 'export_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        $_SESSION['error_message'] = 'Could not create the export archive. Check that the server temp directory is writable.';
        header("Location: export_import.php");
        exit();
    }

    $zip->addFromString('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    if ($includeImages) {
        foreach (['logos', 'icons', 'favicons', 'hero', 'blog'] as $sub) {
            addFolderToZip($zip, $UPLOAD_DIRS[$sub], $sub);
        }
    }
    if ($includeApks) {
        addFolderToZip($zip, $UPLOAD_DIRS['apks'], 'apks');
    }

    $zip->close();

    $downloadName = 'appcenter-export-' . date('Y-m-d_His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($tmpZipPath));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($tmpZipPath);
    unlink($tmpZipPath);
    exit();
}

/* ============================== IMPORT ============================== */
if (isset($_POST['do_import'])) {
    csrf_verify();

    $mode = ($_POST['import_mode'] ?? 'merge') === 'replace' ? 'replace' : 'merge';

    if (!class_exists('ZipArchive')) {
        $_SESSION['error_message'] = 'The PHP Zip extension is not enabled on this server, so an import archive cannot be read.';
        header("Location: export_import.php");
        exit();
    }

    if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = 'Please choose a valid export .zip file to import.';
        header("Location: export_import.php");
        exit();
    }

    $tmpUpload = $_FILES['import_file']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'zip') {
        $_SESSION['error_message'] = 'The import file must be a .zip archive created by this tool\'s Export.';
        header("Location: export_import.php");
        exit();
    }

    $extractDir = sys_get_temp_dir() . '/appcenter_import_' . uniqid();
    mkdir($extractDir, 0755, true);

    $zip = new ZipArchive();
    if ($zip->open($tmpUpload) !== true) {
        $_SESSION['error_message'] = 'The uploaded file could not be opened as a zip archive.';
        header("Location: export_import.php");
        exit();
    }
    $zip->extractTo($extractDir);
    $zip->close();

    $jsonPath = $extractDir . '/data.json';
    if (!file_exists($jsonPath)) {
        $_SESSION['error_message'] = 'This does not look like a valid export archive (data.json is missing).';
        header("Location: export_import.php");
        exit();
    }

    $data = json_decode(file_get_contents($jsonPath), true);
    if (!is_array($data) || !isset($data['_meta'])) {
        $_SESSION['error_message'] = 'The export archive is corrupted or unreadable.';
        header("Location: export_import.php");
        exit();
    }

    $summary = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'files' => 0];

    try {
        $pdo->beginTransaction();

        // ---- Generic helper: import a list-style table ----
        $importListTable = function ($table, $naturalKeyCols, $rows, array $fkRemap = []) use ($pdo, $mode, &$summary) {
            $rows = $rows ?? [];
            $idMap = [];
            if (empty($rows)) return $idMap;

            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $oldId = $row['id'] ?? null;
                unset($row['id']);

                // remap foreign keys (e.g. apps.category_id) to already-imported/matched ids
                foreach ($fkRemap as $fkCol => $map) {
                    if (isset($row[$fkCol]) && isset($map[$row[$fkCol]])) {
                        $row[$fkCol] = $map[$row[$fkCol]];
                    }
                }

                if ($mode === 'merge' && $naturalKeyCols) {
                    $where = [];
                    $params = [];
                    foreach ($naturalKeyCols as $c) {
                        $where[] = "`$c` = ?";
                        $params[] = $row[$c] ?? null;
                    }
                    $stmt = $pdo->prepare("SELECT id FROM `$table` WHERE " . implode(' AND ', $where) . " LIMIT 1");
                    $stmt->execute($params);
                    $existingId = $stmt->fetchColumn();
                    if ($existingId) {
                        $summary['skipped']++;
                        if ($oldId !== null) $idMap[$oldId] = (int)$existingId;
                        continue;
                    }
                }

                $cols = array_keys($row);
                if (empty($cols)) continue;
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $colList = implode(',', array_map(fn($c) => "`$c`", $cols));
                $stmt = $pdo->prepare("INSERT INTO `$table` ($colList) VALUES ($placeholders)");
                $stmt->execute(array_values($row));
                $newId = (int)$pdo->lastInsertId();
                if ($oldId !== null) $idMap[$oldId] = $newId;
                $summary['inserted']++;
            }
            return $idMap;
        };

        // On Replace, wipe dependent rows first (apps before categories), independents in any order
        if ($mode === 'replace') {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            foreach (['apps', 'categories', 'ads', 'menu_items', 'footer_social', 'footer_links', 'pages', 'blog_posts', 'faqs'] as $t) {
                if (isset($data[$t])) $pdo->exec("DELETE FROM `$t`");
            }
        }

        // Categories first, then apps (remap category_id via the id map)
        $categoryMap = $importListTable('categories', ['slug'], $data['categories'] ?? []);
        $importListTable('apps', ['slug'], $data['apps'] ?? [], ['category_id' => $categoryMap]);

        foreach (['ads', 'menu_items', 'footer_social', 'footer_links', 'pages', 'blog_posts', 'faqs'] as $t) {
            $importListTable($t, $LIST_TABLES[$t], $data[$t] ?? []);
        }

        if ($mode === 'replace') {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        }

        // ---- Settings tables: always upserted ----
        if (!empty($data['site_settings'])) {
            foreach ($data['site_settings'] as $row) {
                if (empty($row['setting_key'])) continue;
                $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$row['setting_key'], $row['setting_value'] ?? null]);
                $summary['updated']++;
            }
        }

        if (!empty($data['seo_settings'][0])) {
            $row = $data['seo_settings'][0];
            unset($row['id']);
            $exists = $pdo->query("SELECT id FROM seo_settings LIMIT 1")->fetchColumn();
            if ($exists) {
                $cols = array_keys($row);
                $set = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
                $stmt = $pdo->prepare("UPDATE seo_settings SET $set WHERE id = ?");
                $stmt->execute([...array_values($row), $exists]);
            } else {
                $cols = array_keys($row);
                $colList = implode(',', array_map(fn($c) => "`$c`", $cols));
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO seo_settings ($colList) VALUES ($placeholders)")->execute(array_values($row));
            }
            $summary['updated']++;
        }

        if (!empty($data['header_footer'][0])) {
            $row = $data['header_footer'][0];
            unset($row['id']);
            $cols = array_keys($row);
            $set = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
            $stmt = $pdo->prepare("UPDATE header_footer SET $set WHERE id = 1");
            $stmt->execute(array_values($row));
            $summary['updated']++;
        }

        // ---- Homepage layout settings ----
        if (isset($data['homepage_settings']) && is_array($data['homepage_settings'])) {
            file_put_contents('../data/homepage_settings.json', json_encode($data['homepage_settings'], JSON_PRETTY_PRINT));
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = 'Import failed, no changes were saved: ' . $e->getMessage();
        header("Location: export_import.php");
        exit();
    }

    // ---- Copy bundled files (logos/icons/favicons/hero/blog/apks) ----
    foreach ($UPLOAD_DIRS as $sub => $destDir) {
        $srcDir = $extractDir . '/files/' . $sub;
        if (!is_dir($srcDir)) continue;
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        foreach (scandir($srcDir) as $file) {
            if ($file === '.' || $file === '..') continue;
            $src = $srcDir . '/' . $file;
            $dest = $destDir . $file;
            if (!is_file($src)) continue;
            if ($mode === 'merge' && file_exists($dest)) continue; // don't clobber existing files on merge
            if (copy($src, $dest)) $summary['files']++;
        }
    }

    // Cleanup temp extraction directory
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
    rmdir($extractDir);

    $_SESSION['success_message'] = "Import complete (" . ($mode === 'replace' ? 'Replace All' : 'Merge') . " mode) — "
        . "{$summary['inserted']} row(s) added, {$summary['updated']} setting(s) updated, {$summary['skipped']} duplicate(s) skipped, {$summary['files']} file(s) copied.";
    header("Location: export_import.php");
    exit();
}

// Row counts for the summary cards
$counts = [];
foreach (array_keys($LIST_TABLES) as $t) {
    try { $counts[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); }
    catch (PDOException $e) { $counts[$t] = 0; }
}
$uploadSizes = [];
foreach ($UPLOAD_DIRS as $sub => $dir) {
    $size = 0; $n = 0;
    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..' || $f === '.htaccess') continue;
            $p = $dir . $f;
            if (is_file($p)) { $size += filesize($p); $n++; }
        }
    }
    $uploadSizes[$sub] = ['count' => $n, 'size' => $size];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export / Import - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/export_import.css">
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
                <a href="optimize.php" class="menu-item"><span>⚡</span> Image Optimizer</a>
                <a href="export_import.php" class="menu-item active"><span>🔁</span> Export / Import</a>
                <a href="logout.php" class="menu-item logout"><span>🔚</span> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="header">
                <div style="display: flex; align-items: center;">
                    <button class="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Export / Import</h1>
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

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $_SESSION['error_message'] ?>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <div class="ei-grid">

                <div class="ei-card">
                    <h3><i class="fas fa-download"></i> Export All Data</h3>
                    <p class="ei-desc">Downloads a single .zip backup containing your apps/games, categories, ads, menu, site &amp; SEO settings, header/footer code, homepage layout, footer links, pages, blog posts, FAQs — plus your logo, icons and favicon.</p>

                    <table class="ei-summary-table">
                        <tr><td>Apps / Games</td><td><?= $counts['apps'] ?></td></tr>
                        <tr><td>Categories</td><td><?= $counts['categories'] ?></td></tr>
                        <tr><td>Ads</td><td><?= $counts['ads'] ?></td></tr>
                        <tr><td>Menu items</td><td><?= $counts['menu_items'] ?></td></tr>
                        <tr><td>Pages</td><td><?= $counts['pages'] ?></td></tr>
                        <tr><td>Blog posts</td><td><?= $counts['blog_posts'] ?></td></tr>
                        <tr><td>FAQs</td><td><?= $counts['faqs'] ?></td></tr>
                        <tr><td>Footer links / social</td><td><?= $counts['footer_links'] + $counts['footer_social'] ?></td></tr>
                    </table>

                    <form method="POST" action="export_import.php">
                        <?= csrf_field() ?>
                        <label class="ei-checkbox">
                            <input type="checkbox" name="include_images" value="1" checked>
                            Include logo, icons, favicon, hero &amp; blog images
                            (<?php
                                $imgCount = $uploadSizes['logos']['count'] + $uploadSizes['icons']['count'] + $uploadSizes['favicons']['count'] + $uploadSizes['hero']['count'] + $uploadSizes['blog']['count'];
                                $imgSize = $uploadSizes['logos']['size'] + $uploadSizes['icons']['size'] + $uploadSizes['favicons']['size'] + $uploadSizes['hero']['size'] + $uploadSizes['blog']['size'];
                                echo $imgCount . ' files, ' . formatBytesEI($imgSize);
                            ?>)
                        </label>
                        <label class="ei-checkbox">
                            <input type="checkbox" name="include_apks" value="1">
                            Include uploaded APK files
                            (<?= $uploadSizes['apks']['count'] ?> files, <?= formatBytesEI($uploadSizes['apks']['size']) ?> — can make the download large)
                        </label>
                        <button type="submit" name="do_export" value="1" class="btn btn-primary">
                            <i class="fas fa-file-export"></i> Export &amp; Download .zip
                        </button>
                    </form>
                </div>

                <div class="ei-card">
                    <h3><i class="fas fa-upload"></i> Import Data</h3>
                    <p class="ei-desc">Restore or clone from a .zip file created by Export above. Choose how imported rows should interact with what's already in your database.</p>

                    <form method="POST" action="export_import.php" enctype="multipart/form-data" onsubmit="return confirm(document.querySelector('input[name=import_mode]:checked').value === 'replace' ? 'Replace All will permanently delete existing apps, categories, ads, pages, blog posts, menu items and FAQs before restoring from the file. This cannot be undone. Continue?' : 'Import this file now?');">
                        <?= csrf_field() ?>

                        <label class="ei-radio">
                            <input type="radio" name="import_mode" value="merge" checked>
                            <strong>Merge</strong> — only add items that don't already exist (matched by slug/title). Nothing existing is deleted.
                        </label>
                        <label class="ei-radio">
                            <input type="radio" name="import_mode" value="replace">
                            <strong>Replace All</strong> — deletes existing apps, categories, ads, pages, blog posts, menu items, footer links and FAQs, then restores everything from the file.
                        </label>

                        <input type="file" name="import_file" accept=".zip" required class="ei-file-input">

                        <button type="submit" name="do_import" value="1" class="btn btn-primary">
                            <i class="fas fa-file-import"></i> Import from .zip
                        </button>
                    </form>

                    <p class="ei-note"><i class="fas fa-circle-info"></i> Login credentials, ratings, comments and download stats are never exported or imported.</p>
                </div>

            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const menuToggle = document.querySelector('.menu-toggle');
            const closeSidebar = document.querySelector('.close-sidebar');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');

            menuToggle.addEventListener('click', function () {
                sidebar.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
            closeSidebar.addEventListener('click', function () {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        });
        setTimeout(() => document.querySelectorAll('.alert').forEach(e => e.classList.add('hide')), 6000);
    </script>
</body>
</html>

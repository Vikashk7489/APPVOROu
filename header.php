<?php
// Get SEO settings
$seoSettings = $pdo->query("SELECT * FROM seo_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

// Get logo settings
$logoText = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'logo_text'")->fetchColumn();
$logoImage = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'logo_image'")->fetchColumn();

// Get menu items (seed sensible defaults the first time the menu is empty)
ensureDefaultMenuItems();
$menuItems = $pdo->query("SELECT * FROM menu_items ORDER BY position ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get categories (for the categories section of the site)
$categories = getCategories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seoSettings['site_title'] ?? 'Your Site Name') ?></title>
    <meta name="description" content="<?= htmlspecialchars($seoSettings['meta_description'] ?? 'Default meta description') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($seoSettings['meta_keywords'] ?? 'keyword1, keyword2, keyword3') ?>">
    
    <?php if (!empty($seoSettings['favicon'])):
        $faviconExt = strtolower(pathinfo($seoSettings['favicon'], PATHINFO_EXTENSION));
        $faviconMimeMap = [
            'ico'  => 'image/x-icon',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
        ];
        $faviconMime = $faviconMimeMap[$faviconExt] ?? 'image/x-icon';
    ?>
        <link rel="icon" href="<?= SITE_URL ?>/uploads/favicons/<?= htmlspecialchars($seoSettings['favicon']) ?>" type="<?= $faviconMime ?>">
        <link rel="shortcut icon" href="<?= SITE_URL ?>/uploads/favicons/<?= htmlspecialchars($seoSettings['favicon']) ?>" type="<?= $faviconMime ?>">
    <?php endif; ?>
    
    <?php
    // Output head start code from database
    $headStartCode = $pdo->query("SELECT head_start_code FROM header_footer WHERE id = 1")->fetchColumn();
    if ($headStartCode) {
        echo htmlspecialchars_decode($headStartCode);
    }
    ?>
    
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public/header.css">
    
    <?php
    // Output header code from database
    $headerCode = $pdo->query("SELECT header_code FROM header_footer WHERE id = 1")->fetchColumn();
    if ($headerCode) {
        echo htmlspecialchars_decode($headerCode);
    }
    ?>
    
</head>
<body>
    
    <?php
    // Output body start code from database
    $bodyStartCode = $pdo->query("SELECT body_start_code FROM header_footer WHERE id = 1")->fetchColumn();
    if ($bodyStartCode) {
        echo htmlspecialchars_decode($bodyStartCode);
    }
    ?>
    
    <nav class="nav-container">
        <div class="nav-wrapper">
            <button class="mobile-menu-btn" aria-label="Toggle navigation bar" onclick="toggleMobileNav()">
                <svg aria-hidden="true" focusable="false" role="img" viewBox="0 0 448 512">
                    <path fill="currentColor" d="M0 96C0 78.33 14.33 64 32 64H416C433.7 64 448 78.33 448 96C448 113.7 433.7 128 416 128H32C14.33 128 0 113.7 0 96zM0 256C0 238.3 14.33 224 32 224H416C433.7 224 448 238.3 448 256C448 273.7 433.7 288 416 288H32C14.33 288 0 273.7 0 256zM416 448H32C14.33 448 0 433.7 0 416C0 398.3 14.33 384 32 384H416C433.7 384 448 398.3 448 416C448 433.7 433.7 448 416 448z"></path>
                </svg>
            </button>
            
            <div class="logo">
                <a href="/" aria-label="<?= htmlspecialchars($logoText) ?>">
                    <?php if ($logoImage): ?>
                        <img src="<?= SITE_URL ?>/uploads/logos/<?= htmlspecialchars($logoImage) ?>" alt="<?= htmlspecialchars($logoText) ?>" class="light-logo">
                        <img src="<?= SITE_URL ?>/uploads/logos/<?= htmlspecialchars($logoImage) ?>" alt="<?= htmlspecialchars($logoText) ?>" class="dark-logo" style="display: none;">
                    <?php else: ?>
                        <span class="light-logo"><?= htmlspecialchars($logoText) ?></span>
                        <span class="dark-logo" style="display: none;"><?= htmlspecialchars($logoText) ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- Main Navigation Items -->
            <ul class="nav-items" id="mainNavItems">
                <?php foreach ($menuItems as $item): 
                    // Default to home icon if no SVG provided and not a category
                    $defaultIcon = $item['is_category'] ? 
                        '<svg aria-hidden="true" role="img" viewBox="0 0 640 512"><path fill="currentColor" d="M640 384.2c0-5.257-.4576-10.6-1.406-15.98l-33.38-211.6C591.4 77.96 522 32 319.1 32C119 32 48.71 77.46 34.78 156.6l-33.38 211.6c-.9487 5.383-1.406 10.72-1.406 15.98c0 51.89 44.58 95.81 101.5 95.81c49.69 0 93.78-30.06 109.5-74.64l7.5-21.36h203l7.5 21.36c15.72 44.58 59.81 74.64 109.5 74.64C595.4 479.1 640 436.1 640 384.2zM247.1 248l-31.96-.0098L215.1 280c0 13.2-10.78 24-23.98 24c-13.2 0-24.02-10.8-24.02-24l.0367-32.01L135.1 248c-13.2 0-23.98-10.8-23.98-24c0-13.2 10.77-24 23.98-24l32.04-.011L167.1 168c0-13.2 10.82-24 24.02-24c13.2 0 23.98 10.8 23.98 24l.0368 31.99L247.1 200c13.2 0 24.02 10.8 24.02 24C271.1 237.2 261.2 248 247.1 248zM432 311.1c-22.09 0-40-17.92-40-40c0-22.08 17.91-40 40-40s40 17.92 40 40C472 294.1 454.1 311.1 432 311.1zM496 215.1c-22.09 0-40-17.92-40-40c0-22.08 17.91-40 40-40s40 17.92 40 40C536 198.1 518.1 215.1 496 215.1z"></path></svg>' : 
                        '<svg aria-hidden="true" role="img" viewBox="0 0 576 512"><path fill="currentColor" d="M575.8 255.5C575.8 273.5 560.8 287.6 543.8 287.6H511.8L512.5 447.7C512.5 450.5 512.3 453.1 512 455.8V472C512 494.1 494.1 512 472 512H456C454.9 512 453.8 511.1 452.7 511.9C451.3 511.1 449.9 512 448.5 512H392C369.9 512 352 494.1 352 472V384C352 366.3 337.7 352 320 352H256C238.3 352 224 366.3 224 384V472C224 494.1 206.1 512 184 512H128.1C126.6 512 125.1 511.9 123.6 511.8C122.4 511.9 121.2 512 120 512H104C81.91 512 64 494.1 64 472V360C64 359.1 64.03 358.1 64.09 357.2V287.6H32.05C14.02 287.6 0 273.5 0 255.5C0 246.5 3.004 238.5 10.01 231.5L266.4 8.016C273.4 1.002 281.4 0 288.4 0C295.4 0 303.4 2.004 309.5 7.014L564.8 231.5C572.8 238.5 576.9 246.5 575.8 255.5L575.8 255.5z"></path></svg>';
                    
                    $iconSvg = !empty($item['icon_svg']) ? $item['icon_svg'] : $defaultIcon;
                    $gradientFrom = $item['gradient_from'] ?? '#FFC785';
                    $gradientTo = $item['gradient_to'] ?? '#FF9900';
                    $shadowColor = $item['shadow_color'] ?? '#FFC785';
                ?>
                <li class="nav-item">
                    <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link" <?= $item['is_external'] ? 'target="_blank"' : '' ?>>
                        <div class="icon" style="--gradient-from: <?= $gradientFrom ?>; --gradient-to: <?= $gradientTo ?>; --shadow-color: <?= $shadowColor ?>;">
                            <?= $iconSvg ?>
                        </div>
                        <?= htmlspecialchars($item['title']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <!-- Navigation Actions (Dark Mode/Search Toggles) -->
            <div class="nav-actions">
                <li class="nav-item icon-only" style="--color: #FF2E00;">
                    <a class="nav-link" aria-label="Toggle Dark Mode" title="Toggle Dark Mode" onclick="toggleDarkMode()">
                        <div class="icon dark-mode-icon" style="margin-right: 0; --gradient-from: #FFA985; --gradient-to: #FF2E00; --shadow-color: #FFA985;">
                            <svg aria-hidden="true" focusable="false" role="img" viewBox="0 0 512 512">
                                <path fill="currentColor" d="M32 256c0-123.8 100.3-224 223.8-224c11.36 0 29.7 1.668 40.9 3.746c9.616 1.777 11.75 14.63 3.279 19.44C245 86.5 211.2 144.6 211.2 207.8c0 109.7 99.71 193 208.3 172.3c9.561-1.805 16.28 9.324 10.11 16.95C387.9 448.6 324.8 480 255.8 480C132.1 480 32 379.6 32 256z"></path>
                            </svg>
                        </div>
                    </a>
                </li>
                <li class="nav-item icon-only" style="--color: #2400FF;">
                    <a class="nav-link" aria-label="Toggle Search" title="Toggle Search" onclick="toggleSearchBox()">
                        <div class="icon" style="margin-right: 0; --gradient-from: #9D85FF; --gradient-to: #2400FF; --shadow-color: #9D85FF;">
                            <svg aria-hidden="true" focusable="false" role="img" viewBox="0 0 512 512">
                                <path fill="currentColor" d="M504.1 471l-134-134C399.1 301.5 415.1 256.8 415.1 208c0-114.9-93.13-208-208-208S-.0002 93.13-.0002 208S93.12 416 207.1 416c48.79 0 93.55-16.91 129-45.04l134 134C475.7 509.7 481.9 512 488 512s12.28-2.344 16.97-7.031C514.3 495.6 514.3 480.4 504.1 471zM48 208c0-88.22 71.78-160 160-160s160 71.78 160 160s-71.78 160-160 160S48 296.2 48 208z"></path>
                            </svg>
                        </div>
                    </a>
                </li>
            </div>
            
            <button class="icon-btn" aria-label="Toggle search box" onclick="toggleSearchBox()">
                <svg aria-hidden="true" focusable="false" role="img" viewBox="0 0 512 512">
                    <path fill="currentColor" d="M504.1 471l-134-134C399.1 301.5 415.1 256.8 415.1 208c0-114.9-93.13-208-208-208S-.0002 93.13-.0002 208S93.12 416 207.1 416c48.79 0 93.55-16.91 129-45.04l134 134C475.7 509.7 481.9 512 488 512s12.28-2.344 16.97-7.031C514.3 495.6 514.3 480.4 504.1 471zM48 208c0-88.22 71.78-160 160-160s160 71.78 160 160s-71.78 160-160 160S48 296.2 48 208z"></path>
                </svg>
            </button>
        </div>
    </nav>

    <!-- Mobile Navigation Menu -->
    <div class="mobile-nav-links" id="mobileNav">
        <div class="close-btn-container">
            <button aria-label="Toggle navigation bar" class="close-btn" onclick="toggleMobileNav()">
                <svg aria-hidden="true" focusable="false" viewBox="0 0 320 512" width="16" height="16" fill="currentColor">
                    <path d="M315.3 411.3c-6.253 6.253-16.37 6.253-22.63 0L160 278.6l-132.7 132.7c-6.253 6.253-16.37 6.253-22.63 0c-6.253-6.253-6.253-16.37 0-22.63L137.4 256L4.69 123.3c-6.253-6.253-6.253-16.37 0-22.63c6.253-6.253 16.37-6.253 22.63 0L160 233.4l132.7-132.7c6.253-6.253 16.37-6.253 22.63 0c6.253 6.253 6.253 16.37 0 22.63L182.6 256l132.7 132.7C321.6 394.9 321.6 405.1 315.3 411.3z"></path>
                </svg>
            </button>
        </div>
        <div class="search-container">
            <label for="mobileSearch">Search</label>
            <div class="search-input-container">
                <form action="/" role="search" method="get">
                    <input type="text" name="s" minlength="3" maxlength="156" required id="mobileSearch" placeholder="I want to find...">
                    <button type="submit">
                        <svg aria-hidden="true" focusable="false" viewBox="0 0 512 512" width="16" height="16" fill="currentColor">
                            <path d="M504.1 471l-134-134C399.1 301.5 415.1 256.8 415.1 208c0-114.9-93.13-208-208-208S-.0002 93.13-.0002 208S93.12 416 207.1 416c48.79 0 93.55-16.91 129-45.04l134 134C475.7 509.7 481.9 512 488 512s12.28-2.344 16.97-7.031C514.3 495.6 514.3 480.4 504.1 471zM48 208c0-88.22 71.78-160 160-160s160 71.78 160 160s-71.78 160-160 160S48 296.2 48 208z"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
        <div class="nav-items-container" id="mobileNavItems">
            <!-- Items will be populated by JavaScript -->
        </div>
    </div>
    
    <div class="nav-overlay" id="navOverlay"></div>
    
    <!-- Search Box -->
    <div class="search-box-container" id="searchBox">
        <div class="search-box-content">
            <h2 class="search-box-heading">
                <center>Search here</center>
            </h2>
            <center>
                <p class="search-box-description">Search here your favourite games or apps to download it instantly.</p>
            </center>
            <div class="search-input-container">
                <span class="search-icon">
                    <svg class="icon" aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                        <path fill="currentColor" d="M504.1 471l-134-134C399.1 301.5 415.1 256.8 415.1 208c0-114.9-93.13-208-208-208S-.0002 93.13-.0002 208S93.12 416 207.1 416c48.79 0 93.55-16.91 129-45.04l134 134C475.7 509.7 481.9 512 488 512s12.28-2.344 16.97-7.031C514.3 495.6 514.3 480.4 504.1 471zM48 208c0-88.22 71.78-160 160-160s160 71.78 160 160s-71.78 160-160 160S48 296.2 48 208z"></path>
                    </svg>
                </span>
                <form action="/" class="search-form" role="search" method="get">
                    <input id="search-input" type="search" name="s" minlength="3" maxlength="156" required class="search-input" placeholder="Search for apps...">
                    <button type="submit" class="search-button">Go</button>
                </form>
            </div>
        </div>
        <button class="close-button" id="closeButton">
            <svg class="icon" aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
                <path fill="currentColor" d="M315.3 411.3c-6.253 6.253-16.37 6.253-22.63 0L160 278.6l-132.7 132.7c-6.253 6.253-16.37 6.253-22.63 0c-6.253-6.253-6.253-16.37 0-22.63L137.4 256L4.69 123.3c-6.253-6.253-6.253-16.37 0-22.63c6.253-6.253 16.37-6.253 22.63 0L160 233.4l132.7-132.7c6.253-6.253 16.37-6.253 22.63 0c6.253 6.253 6.253 16.37 0 22.63L182.6 256l132.7 132.7C321.6 394.9 321.6 405.1 315.3 411.3z"></path>
            </svg>
        </button>
    </div>

    <!-- New ad container after header -->
<div class="header-ad-container">
<?php echo displayAd('header', 'index'); ?>
</div>
    
    <script>
        // Toggle mobile navigation
        function toggleMobileNav() {
            const mobileNav = document.getElementById('mobileNav');
            const overlay = document.getElementById('navOverlay');
            
            mobileNav.classList.toggle('active');
            overlay.classList.toggle('active');
            
            // Prevent scrolling when mobile nav is open
            if (mobileNav.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
        
        // Toggle dark mode
        function toggleDarkMode() {
            document.body.classList.toggle('dark');
            
            // Toggle logo visibility
            const lightLogo = document.querySelector('.light-logo');
            const darkLogo = document.querySelector('.dark-logo');
            
            if (document.body.classList.contains('dark')) {
                lightLogo.style.display = 'none';
                darkLogo.style.display = 'block';
                
                // Change icon to sun when in dark mode
                const darkModeIcons = document.querySelectorAll('.dark-mode-icon');
                darkModeIcons.forEach(icon => {
                    icon.innerHTML = `<svg class="svg-inline--fa fa-sun" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="sun" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 159.1c-53.02 0-95.1 42.98-95.1 95.1S202.1 351.1 256 351.1s95.1-42.98 95.1-95.1S309 159.1 256 159.1zM509.3 347L446.1 255.1l63.15-91.01c6.332-9.125 1.104-21.74-9.826-23.72l-109-19.7l-19.7-109c-1.975-10.93-14.59-16.16-23.72-9.824L256 65.89L164.1 2.736c-9.125-6.332-21.74-1.107-23.72 9.824L121.6 121.6L12.56 141.3C1.633 143.2-3.596 155.9 2.736 164.1L65.89 256l-63.15 91.01c-6.332 9.125-1.105 21.74 9.824 23.72l109 19.7l19.7 109c1.975 10.93 14.59 16.16 23.72 9.824L256 446.1l91.01 63.15c9.127 6.334 21.75 1.107 23.72-9.822l19.7-109l109-19.7C510.4 368.8 515.6 356.1 509.3 347zM256 383.1c-70.69 0-127.1-57.31-127.1-127.1c0-70.69 57.31-127.1 127.1-127.1s127.1 57.3 127.1 127.1C383.1 326.7 326.7 383.1 256 383.1z"></path></svg>`;
                });
            } else {
                lightLogo.style.display = 'block';
                darkLogo.style.display = 'none';
                
                // Change icon back to moon when in light mode
                const darkModeIcons = document.querySelectorAll('.dark-mode-icon');
                darkModeIcons.forEach(icon => {
                    icon.innerHTML = `<svg aria-hidden="true" focusable="false" role="img" viewBox="0 0 512 512"><path fill="currentColor" d="M32 256c0-123.8 100.3-224 223.8-224c11.36 0 29.7 1.668 40.9 3.746c9.616 1.777 11.75 14.63 3.279 19.44C245 86.5 211.2 144.6 211.2 207.8c0 109.7 99.71 193 208.3 172.3c9.561-1.805 16.28 9.324 10.11 16.95C387.9 448.6 324.8 480 255.8 480C132.1 480 32 379.6 32 256z"></path></svg>`;
                });
            }
            
            // Save preference to localStorage
            const isDark = document.body.classList.contains('dark');
            localStorage.setItem('darkMode', isDark);
        }
        
        // Toggle search box
        function toggleSearchBox() {
            const searchBox = document.getElementById('searchBox');
            searchBox.style.display = searchBox.style.display === 'flex' ? 'none' : 'flex';
            
            document.body.classList.toggle('search-active');
            
            // Focus on input when search box is shown
            if (searchBox.style.display === 'flex') {
                setTimeout(() => {
                    document.getElementById('search-input').focus();
                }, 100);
            }
        }
        
        document.getElementById('closeButton').addEventListener('click', function() {
            document.getElementById('searchBox').style.display = 'none';
            document.body.classList.remove('search-active');
        });
        
        // Check for saved dark mode preference
        document.addEventListener('DOMContentLoaded', function() {
            const darkMode = localStorage.getItem('darkMode') === 'true';
            if (darkMode) {
                document.body.classList.add('dark');
                document.querySelector('.light-logo').style.display = 'none';
                document.querySelector('.dark-logo').style.display = 'block';
                
                // Set sun icon if in dark mode
                const darkModeIcons = document.querySelectorAll('.dark-mode-icon');
                darkModeIcons.forEach(icon => {
                    icon.innerHTML = `<svg class="svg-inline--fa fa-sun" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="sun" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 159.1c-53.02 0-95.1 42.98-95.1 95.1S202.1 351.1 256 351.1s95.1-42.98 95.1-95.1S309 159.1 256 159.1zM509.3 347L446.1 255.1l63.15-91.01c6.332-9.125 1.104-21.74-9.826-23.72l-109-19.7l-19.7-109c-1.975-10.93-14.59-16.16-23.72-9.824L256 65.89L164.1 2.736c-9.125-6.332-21.74-1.107-23.72 9.824L121.6 121.6L12.56 141.3C1.633 143.2-3.596 155.9 2.736 164.1L65.89 256l-63.15 91.01c-6.332 9.125-1.105 21.74 9.824 23.72l109 19.7l19.7 109c1.975 10.93 14.59 16.16 23.72 9.824L256 446.1l91.01 63.15c9.127 6.334 21.75 1.107 23.72-9.822l19.7-109l109-19.7C510.4 368.8 515.6 356.1 509.3 347zM256 383.1c-70.69 0-127.1-57.31-127.1-127.1c0-70.69 57.31-127.1 127.1-127.1s127.1 57.3 127.1 127.1C383.1 326.7 326.7 383.1 256 383.1z"></path></svg>`;
                });
            }
            
            // Populate mobile nav from main nav
            populateMobileNav();
        });
        
        // Close mobile nav when clicking overlay
        document.getElementById('navOverlay').addEventListener('click', toggleMobileNav);
        
        // Modified populateMobileNav to work with database items
        function populateMobileNav() {
            const mainNavItems = document.querySelectorAll('#mainNavItems .nav-item:not(.icon-only)');
            const mobileNavContainer = document.getElementById('mobileNavItems');
            
            // Clear existing items
            mobileNavContainer.innerHTML = '';
            
            // Add regular navigation items
            mainNavItems.forEach((item, index) => {
                const link = item.querySelector('.nav-link');
                const icon = link.querySelector('.icon').cloneNode(true);
                icon.className = 'icon';
                icon.style.marginRight = '0';
                
                const mobileItem = document.createElement('li');
                mobileItem.className = 'nav-item';
                
                const mobileLink = document.createElement('a');
                mobileLink.className = 'nav-link';
                mobileLink.href = link.href;
                if (link.target) mobileLink.target = link.target;
                
                const iconContainer = document.createElement('div');
                iconContainer.className = 'icon-container';
                
                iconContainer.appendChild(icon);
                mobileLink.appendChild(iconContainer);
                mobileLink.appendChild(document.createTextNode(link.textContent.trim()));
                
                mobileItem.appendChild(mobileLink);
                mobileNavContainer.appendChild(mobileItem);
            });
            
            // Add dark mode toggle item
            const darkModeItem = document.createElement('li');
            darkModeItem.className = 'nav-item';
            
            const darkModeLink = document.createElement('a');
            darkModeLink.className = 'nav-link';
            darkModeLink.onclick = toggleDarkMode;
            
            const darkIconContainer = document.createElement('div');
            darkIconContainer.className = 'icon-container';
            
            const darkIcon = document.createElement('div');
            darkIcon.className = 'icon dark-mode-icon';
            darkIcon.style.marginRight = '0';
            darkIcon.style.setProperty('--gradient-from', '#FFA985');
            darkIcon.style.setProperty('--gradient-to', '#FF2E00');
            darkIcon.style.setProperty('--shadow-color', '#FFA985');
            
            // Set initial icon based on current mode
            if (document.body.classList.contains('dark')) {
                darkIcon.innerHTML = `<svg class="svg-inline--fa fa-sun" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="sun" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 159.1c-53.02 0-95.1 42.98-95.1 95.1S202.1 351.1 256 351.1s95.1-42.98 95.1-95.1S309 159.1 256 159.1zM509.3 347L446.1 255.1l63.15-91.01c6.332-9.125 1.104-21.74-9.826-23.72l-109-19.7l-19.7-109c-1.975-10.93-14.59-16.16-23.72-9.824L256 65.89L164.1 2.736c-9.125-6.332-21.74-1.107-23.72 9.824L121.6 121.6L12.56 141.3C1.633 143.2-3.596 155.9 2.736 164.1L65.89 256l-63.15 91.01c-6.332 9.125-1.105 21.74 9.824 23.72l109 19.7l19.7 109c1.975 10.93 14.59 16.16 23.72 9.824L256 446.1l91.01 63.15c9.127 6.334 21.75 1.107 23.72-9.822l19.7-109l109-19.7C510.4 368.8 515.6 356.1 509.3 347zM256 383.1c-70.69 0-127.1-57.31-127.1-127.1c0-70.69 57.31-127.1 127.1-127.1s127.1 57.3 127.1 127.1C383.1 326.7 326.7 383.1 256 383.1z"></path></svg>`;
            } else {
                darkIcon.innerHTML = `<svg aria-hidden="true" focusable="false" viewBox="0 0 512 512" width="16" height="16" fill="currentColor"><path d="M32 256c0-123.8 100.3-224 223.8-224c11.36 0 29.7 1.668 40.9 3.746c9.616 1.777 11.75 14.63 3.279 19.44C245 86.5 211.2 144.6 211.2 207.8c0 109.7 99.71 193 208.3 172.3c9.561-1.805 16.28 9.324 10.11 16.95C387.9 448.6 324.8 480 255.8 480C132.1 480 32 379.6 32 256z"></path></svg>`;
            }
            
            darkIconContainer.appendChild(darkIcon);
            darkModeLink.appendChild(darkIconContainer);
            darkModeLink.appendChild(document.createTextNode('Dark Mode'));
            
            darkModeItem.appendChild(darkModeLink);
            mobileNavContainer.appendChild(darkModeItem);
        }
    </script>
</body>
</html>
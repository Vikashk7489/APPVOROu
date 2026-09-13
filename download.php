<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Get app slug from URL
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: /");
    exit;
}

// Get app details
$app = getAppBySlug($slug);
if (!$app) {
    header("Location: /");
    exit;
}

// Track download stats
incrementDownloadCount($app['id']);

// Track daily download stats
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    INSERT INTO download_stats (app_id, download_date, download_count) 
    VALUES (:app_id, :download_date, 1)
    ON DUPLICATE KEY UPDATE download_count = download_count + 1
");
$stmt->bindParam(':app_id', $app['id']);
$stmt->bindParam(':download_date', $today);
$stmt->execute();

// Log the download (if you implement the logDownload function)
if (function_exists('logDownload')) {
    $user_ip = $_SERVER['REMOTE_ADDR'];
    logDownload($app['id'], $user_ip);
}

// Include header
require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download <?= htmlspecialchars($app['name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public/download.css">
</head>
<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="safe-download-container">
        <div class="neo-app-card">
            <div class="neo-app-header">
                <img src="<?= SITE_URL ?>/uploads/icons/<?= htmlspecialchars($app['icon']) ?>" 
                     class="neo-app-icon" 
                     alt="<?= htmlspecialchars($app['name']) ?>">
                <div class="neo-app-meta">
                    <h1><?= htmlspecialchars($app['name']) ?></h1>
                    <div class="neo-app-tags">
                        <span class="neo-tag version">v<?= htmlspecialchars($app['version']) ?></span>
                        <span class="neo-tag premium">PREMIUM</span>
                        <span class="neo-tag verified">
                            <i class="fas fa-check-circle"></i> VERIFIED
                        </span>
                    </div>
                </div>
            </div>
            <div id="safetyInfoContainer" class="safety-info">
                <i class="fas fa-shield-alt"></i>
                <p>This file has been scanned for viruses and malware</p>
            </div>
            
            <!-- Progress Bar -->
            <div class="neo-progress-container" id="progressContainer">
                <div class="neo-progress-header">
                    <span class="neo-progress-title">Preparing download...</span>
                    <span class="neo-progress-percent" id="progressPercent">0%</span>
                </div>
                <div class="neo-progress-bar">
                    <div class="neo-progress-fill" id="progressFill"></div>
                </div>
            </div>
            
            <?php echo displayAd('before_post', 'download'); ?>
            
            <!-- Download CTA -->
            <div class="neo-download-actions">
                <button id="neoProcessBtn" class="neo-process-btn">
                    <i class="fas fa-cog"></i> Process Download
                </button>
                <div id="downloadBtnContainer" style="display: none; flex: 1;">
                    <?php if (!empty($app['external_url'])): ?>
                        <a href="<?= htmlspecialchars($app['external_url']) ?>" 
                           id="neoDownloadBtn" 
                           class="neo-download-btn">
                            <i class="fas fa-download"></i> Download Now
                        </a>
                    <?php else: ?>
                        <a href="/<?= $app['slug'] ?>?download=true&slug=<?= $app['slug'] ?>" 
                           id="neoDownloadBtn" 
                           class="neo-download-btn">
                            <i class="fas fa-download"></i> Download Now
                        </a>
                    <?php endif; ?>
                </div>
                <button class="neo-cancel-btn" id="neoCancelBtn">
                    Back
                </button>
            </div>
            <div id="downloadHelpText">
                <i class="fas fa-exclamation-circle"></i> If download doesn't start automatically, click the button above
            </div>

            <!-- Safety Card -->
            <div class="neo-safety-card">
                <div class="neo-safety-header">
                    <i class="fas fa-shield-alt neo-safety-icon"></i>
                    <h3 class="neo-safety-title">Safety Tips</h3>
                </div>
                <ul class="neo-safety-list">
                    <li class="neo-safety-item">
                        <i class="fas fa-check-circle"></i>
                        <span class="neo-safety-text">Enable "Unknown Sources" in Android Settings to install APK files</span>
                    </li>
                    <li class="neo-safety-item">
                        <i class="fas fa-check-circle"></i>
                        <span class="neo-safety-text">Scan downloaded file with antivirus before installation</span>
                    </li>
                    <li class="neo-safety-item">
                        <i class="fas fa-check-circle"></i>
                        <span class="neo-safety-text">Only download from trusted sources like this website</span>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- App Details Section -->
        <div class="modern-download-container">
            <div class="app-details-section">
                <h2 class="section-title">App Details</h2>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="detail-content">
                            <span class="detail-label">Last Updated</span>
                            <span class="detail-value"><?= date('M d, Y', strtotime($app['updated_at'])) ?></span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="detail-content">
                            <span class="detail-label">Downloads</span>
                            <span class="detail-value"><?= number_format($app['downloads']) ?></span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-microchip"></i>
                        </div>
                        <div class="detail-content">
                            <span class="detail-label">Requirements</span>
                            <span class="detail-value">Android 7.0+</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="detail-content">
                            <span class="detail-label">Category</span>
                            <span class="detail-value"><?= htmlspecialchars($app['category_name']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php echo displayAd('after_post', 'app'); ?>
        
        <!-- FAQ Section -->
        <div class="faq-section">
            <div class="faq-card">
                <h2 class="faq-title">
                    <div class="icon">
                        <svg aria-hidden="true" focusable="false" role="img" viewBox="0 0 640 512">
                            <path fill="currentColor" d="M416 256V63.1C416 28.75 387.3 0 352 0H64C28.75 0 0 28.75 0 63.1v192C0 291.2 28.75 320 64 320l32 .0106v54.25c0 7.998 9.125 12.62 15.5 7.875l82.75-62.12L352 319.9C387.3 320 416 291.2 416 256zM576 128H448v128c0 52.87-43.13 95.99-96 95.99l-96 .0013v31.98c0 35.25 28.75 63.1 63.1 63.1l125.8-.0073l82.75 62.12C534.9 514.8 544 510.2 544 502.2v-54.24h32c35.25 0 64-28.75 64-63.1V191.1C640 156.7 611.3 128 576 128z"></path>
                        </svg>
                    </div>
                    Download FAQs
                </h2>
                <div class="faq-items-container">
                    <ul class="faq-list">
                        <!-- FAQ Item 1 -->
                        <li class="faq-list-item">
                            <button class="faq-question-btn" onclick="toggleFAQ(0)">
                                <div class="faq-question-content">
                                    <h3 class="faq-question-text">How to download?</h3>
                                    <span class="faq-arrow-icon">
                                        <i class="fas fa-chevron-down"></i>
                                    </span>
                                </div>
                            </button>
                            <div class="faq-answer hidden" faq-id="0">
                                Just wait a few seconds and the download button will appear, just click on it and the download will start. If the download button does not appear, please disable Adblock on this site and try again.
                            </div>
                        </li>
                        
                        <!-- FAQ Item 2 -->
                        <li class="faq-list-item">
                            <button class="faq-question-btn" onclick="toggleFAQ(1)">
                                <div class="faq-question-content">
                                    <h3 class="faq-question-text">How to install?</h3>
                                    <span class="faq-arrow-icon">
                                        <i class="fas fa-chevron-down"></i>
                                    </span>
                                </div>
                            </button>
                            <div class="faq-answer hidden" faq-id="1">
                                Each version has a different installation method. So on the download page of each version, we have specific installation instructions.
                            </div>
                        </li>
                        
                        <!-- FAQ Item 3 -->
                        <li class="faq-list-item">
                            <button class="faq-question-btn" onclick="toggleFAQ(2)">
                                <div class="faq-question-content">
                                    <h3 class="faq-question-text">Never mind the Play Protect warning!</h3>
                                    <span class="faq-arrow-icon">
                                        <i class="fas fa-chevron-down"></i>
                                    </span>
                                </div>
                            </button>
                            <div class="faq-answer hidden" faq-id="2">
                                As you know, MOD means editing APK files. As a result, the MOD APK files will not match the version available on the Google Play Store. That's why Play Protect now warns you every time you want to install MOD APK. So the best way is to turn off Play Protect completely and never mind it if you want to install and use the MOD APK.
                            </div>
                        </li>
                        
                        <!-- FAQ Item 4 -->
                        <li class="faq-list-item">
                            <button class="faq-question-btn" onclick="toggleFAQ(3)">
                                <div class="faq-question-content">
                                    <h3 class="faq-question-text">Is the file I download from us safe?</h3>
                                    <span class="faq-arrow-icon">
                                        <i class="fas fa-chevron-down"></i>
                                    </span>
                                </div>
                            </button>
                            <div class="faq-answer hidden" faq-id="3">
                                Of course, every file is checked by antivirus software and our editors before being uploaded to the website. Our hosting server is also regularly checked & backed up to avoid any threats.
                            </div>
                        </li>

                        <!-- FAQ Item 5 -->
                        <li class="faq-list-item">
                            <button class="faq-question-btn" onclick="toggleFAQ(4)">
                                <div class="faq-question-content">
                                    <h3 class="faq-question-text">Can I get paid Apps for free in Mod?</h3>
                                    <span class="faq-arrow-icon">
                                        <i class="fas fa-chevron-down"></i>
                                    </span>
                                </div>
                            </button>
                            <div class="faq-answer hidden" faq-id="4">
                                Yes, download any of the premium application mods here on our website, and you will get the premium version of the app for free with all the pro benefits and features unlocked.
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('neoProcessBtn').addEventListener('click', function() {
            // Hide the safety info text
            document.getElementById('safetyInfoContainer').style.display = 'none';
            
            // Hide the process button
            this.style.display = 'none';
            
            // Show the progress bar
            document.getElementById('progressContainer').style.display = 'block';
            
            // Make the back button full width
            document.getElementById('neoCancelBtn').style.flex = '1';
            document.getElementById('neoCancelBtn').style.width = '100%';
            
            // Start the progress animation
            const progressFill = document.getElementById('progressFill');
            const progressPercent = document.getElementById('progressPercent');
            const downloadBtnContainer = document.getElementById('downloadBtnContainer');
            const downloadHelpText = document.getElementById('downloadHelpText');
            
            let progress = 0;
            const totalDuration = 30000; // 3 seconds total
            const interval = 30; // update every 30ms
            const steps = totalDuration / interval;
            const increment = 100 / steps;
            
            const progressInterval = setInterval(() => {
                progress += increment;
                if (progress > 100) progress = 100;
                
                progressFill.style.width = progress + '%';
                progressPercent.textContent = Math.round(progress) + '%';
                
                if (progress >= 100) {
                    clearInterval(progressInterval);
                    // Show the download button
                    downloadBtnContainer.style.display = 'flex';
                    // Hide the progress bar
                    document.getElementById('progressContainer').style.display = 'none';
                    
                    // Reset the back button width
                    document.getElementById('neoCancelBtn').style.flex = '';
                    document.getElementById('neoCancelBtn').style.width = '';
                    
                    // Auto-click the download button after a brief delay
                    setTimeout(() => {
                        document.getElementById('neoDownloadBtn').click();
                        
                        // Show help text after 3 seconds if download didn't start
                        setTimeout(() => {
                            downloadHelpText.style.display = 'block';
                        }, 3000);
                    }, 500);
                }
            }, interval);
        });

        // Cancel button functionality
        document.getElementById('neoCancelBtn').addEventListener('click', function() {
            window.location.href = '/<?= $app['slug'] ?>';
        });

        // FAQ Toggle Function
        function toggleFAQ(index) {
            const faqItem = document.querySelector(`[faq-id="${index}"]`).parentElement;
            const isActive = faqItem.classList.contains('active');
            
            // Close all other FAQs
            document.querySelectorAll('.faq-list-item').forEach(item => {
                if (item !== faqItem) {
                    item.classList.remove('active');
                    item.querySelector('.faq-answer').classList.add('hidden');
                }
            });
            
            // Toggle current FAQ
            faqItem.classList.toggle('active');
            const answer = faqItem.querySelector('.faq-answer');
            answer.classList.toggle('hidden');
        }
    </script>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html>
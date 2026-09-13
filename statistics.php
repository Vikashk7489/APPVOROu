<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get total stats
$totalApps = $pdo->query("SELECT COUNT(*) as count FROM apps")->fetch()['count'];
$totalDownloads = $pdo->query("SELECT SUM(downloads) as total FROM apps")->fetch()['total'];
$totalCategories = $pdo->query("SELECT COUNT(*) as count FROM categories")->fetch()['count'];

// Replace the daily downloads query with this:
$dateRange = new DatePeriod(
    new DateTime('-29 days'), // 30 days total (including today)
    new DateInterval('P1D'),
    new DateTime('tomorrow')
);

// Initialize array with all dates set to zero
$dailyDownloads = [];
foreach ($dateRange as $date) {
    $formattedDate = $date->format('Y-m-d');
    $dailyDownloads[$formattedDate] = 0;
}

// Get actual download data and merge it
$actualDownloads = $pdo->query("
    SELECT download_date, SUM(download_count) as daily_count 
    FROM download_stats 
    WHERE download_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY download_date 
    ORDER BY download_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($actualDownloads as $download) {
    $dailyDownloads[$download['download_date']] = (int)$download['daily_count'];
}

// Prepare data for chart
$dates = [];
$downloadCounts = [];
foreach ($dailyDownloads as $date => $count) {
    $dates[] = date('M j', strtotime($date));
    $downloadCounts[] = $count;
}

// Get most popular apps
$popularApps = $pdo->query("
    SELECT a.name, a.downloads, c.name as category_name, a.icon 
    FROM apps a 
    JOIN categories c ON a.category_id = c.id 
    ORDER BY downloads DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get downloads by category
$downloadsByCategory = $pdo->query("
    SELECT c.name, SUM(a.downloads) as total_downloads 
    FROM apps a 
    JOIN categories c ON a.category_id = c.id 
    GROUP BY c.name 
    ORDER BY total_downloads DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity
$recentActivity = $pdo->query("
    SELECT a.name, a.downloads, a.updated_at, c.name as category_name
    FROM apps a 
    JOIN categories c ON a.category_id = c.id
    ORDER BY a.updated_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Calculate percentage changes
$last30DaysDownloads = $pdo->query("
    SELECT SUM(download_count) as total 
    FROM download_stats 
    WHERE download_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch()['total'];

$prev30DaysDownloads = $pdo->query("
    SELECT SUM(download_count) as total 
    FROM download_stats 
    WHERE download_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch()['total'];

$downloadChange = $prev30DaysDownloads > 0 
    ? round((($last30DaysDownloads - $prev30DaysDownloads) / $prev30DaysDownloads * 100), 1)
    : 100;

// Get new apps added in last 30 days
$newAppsCount = $pdo->query("
    SELECT COUNT(*) as count 
    FROM apps 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch()['count'];

// Get new categories added in last 30 days
$newCategoriesCount = $pdo->query("
    SELECT COUNT(*) as count 
    FROM categories 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - AppCenter Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/statistics.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
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
                <a href="statistics.php" class="menu-item active"><span>📉</span> Statistics</a>
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
                <h1>Statistics</h1>
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
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Apps</h3>
                    <p><?= number_format($totalApps) ?></p>
                    <span class="stat-change <?= $newAppsCount > 0 ? 'positive' : 'negative' ?>">
                        <i class="fas fa-arrow-<?= $newAppsCount > 0 ? 'up' : 'down' ?>"></i>
                        <?= $newAppsCount > 0 ? '+' . $newAppsCount . ' this month' : 'No new apps' ?>
                    </span>
                </div>
                <div class="stat-card">
                    <h3>Total Downloads</h3>
                    <p><?= number_format($totalDownloads) ?></p>
                    <span class="stat-change <?= $downloadChange >= 0 ? 'positive' : 'negative' ?>">
                        <i class="fas fa-arrow-<?= $downloadChange >= 0 ? 'up' : 'down' ?>"></i>
                        <?= $downloadChange >= 0 ? '+' . $downloadChange . '%' : $downloadChange . '%' ?> this month
                    </span>
                </div>
                <div class="stat-card">
                    <h3>Categories</h3>
                    <p><?= number_format($totalCategories) ?></p>
                    <span class="stat-change <?= $newCategoriesCount > 0 ? 'positive' : 'negative' ?>">
                        <i class="fas fa-arrow-<?= $newCategoriesCount > 0 ? 'up' : 'down' ?>"></i>
                        <?= $newCategoriesCount > 0 ? '+' . $newCategoriesCount . ' this month' : 'No new categories' ?>
                    </span>
                </div>
                <div class="stat-card">
                    <h3>30-Day Downloads</h3>
                    <p><?= number_format($last30DaysDownloads) ?></p>
                    <span class="stat-change <?= $downloadChange >= 0 ? 'positive' : 'negative' ?>">
                        <i class="fas fa-chart-line"></i>
                        <?= $downloadChange >= 0 ? '+' . $downloadChange . '%' : $downloadChange . '%' ?> change
                    </span>
                </div>
            </div>
            
            <div class="chart-row">
                <div class="chart-container">
                    <h2 class="chart-title"><i>📉</i> Downloads Last 30 Days</h2>
                    <div class="chart-wrapper">
                        <canvas id="downloadsOverTimeChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container">
                    <h2 class="chart-title"><i>📊</i> Downloads by Category</h2>
                    <div class="chart-wrapper">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="chart-row">
                <div class="chart-container">
                    <h2 class="chart-title"><i>🏆</i> Top 5 Most Popular Apps</h2>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>App</th>
                                    <th>Downloads</th>
                                    <th>Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($popularApps as $app): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center;">
                                                <?php if (!empty($app['icon'])): ?>
                                                    <img src="<?= SITE_URL ?>/uploads/icons/<?= htmlspecialchars($app['icon']) ?>" alt="App Icon" class="app-icon-sm">
                                                <?php endif; ?>
                                                <div>
                                                    <div><?= htmlspecialchars($app['name']) ?></div>
                                                    <div style="font-size: 0.8rem; color: #6c757d;"><?= htmlspecialchars($app['category_name']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= number_format($app['downloads']) ?></td>
                                        <td>
                                            <div class="progress-container">
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?= ($app['downloads'] / $totalDownloads) * 100 ?>%"></div>
                                                </div>
                                                <small><?= round(($app['downloads'] / $totalDownloads) * 100, 1) ?>%</small>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="chart-container">
                    <h2 class="chart-title"><i>🔄</i> Recent Activity</h2>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>App</th>
                                    <th>Downloads</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center;">
                                                <div>
                                                    <div><?= htmlspecialchars($activity['name']) ?></div>
                                                    <div style="font-size: 0.8rem; color: #6c757d;"><?= htmlspecialchars($activity['category_name']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= number_format($activity['downloads']) ?></td>
                                        <td><?= date('M d, Y', strtotime($activity['updated_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Downloads Over Time Chart
        const downloadsOverTimeCtx = document.getElementById('downloadsOverTimeChart').getContext('2d');
        new Chart(downloadsOverTimeCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($dates) ?>,
                datasets: [{
                    label: 'Daily Downloads',
                    data: <?= json_encode($downloadCounts) ?>,
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderColor: '#4361ee',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($downloadsByCategory, 'name')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($downloadsByCategory, 'total_downloads')) ?>,
                    backgroundColor: [
                        '#4361ee', '#3f37c9', '#4895ef', '#4cc9f0', '#f72585',
                        '#b5179e', '#560bad', '#7209b7', '#480ca8', '#3a0ca3'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
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
</script>
</body>
</html>
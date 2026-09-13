<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['change_username'])) {
        // Change username logic
        $newUsername = trim($_POST['new_username']);
        $currentPassword = $_POST['current_password_username'];
        
        if (empty($newUsername)) {
            $error = 'New username cannot be empty';
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE username = :username");
            $stmt->bindParam(':username', $_SESSION['admin_username']);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($currentPassword, $user['password_hash'])) {
                // Update username
                $updateStmt = $pdo->prepare("UPDATE admin_users SET username = :new_username WHERE username = :current_username");
                $updateStmt->bindParam(':new_username', $newUsername);
                $updateStmt->bindParam(':current_username', $_SESSION['admin_username']);
                
                if ($updateStmt->execute()) {
                    $_SESSION['admin_username'] = $newUsername;
                    $success = 'Username updated successfully!';
                } else {
                    $error = 'Failed to update username. Please try again.';
                }
            } else {
                $error = 'Current password is incorrect';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password logic
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (empty($newPassword) || empty($confirmPassword)) {
            $error = 'New password fields cannot be empty';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match';
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE username = :username");
            $stmt->bindParam(':username', $_SESSION['admin_username']);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($currentPassword, $user['password_hash'])) {
                // Update password
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE admin_users SET password_hash = :password_hash WHERE username = :username");
                $updateStmt->bindParam(':password_hash', $newPasswordHash);
                $updateStmt->bindParam(':username', $_SESSION['admin_username']);
                
                if ($updateStmt->execute()) {
                    $success = 'Password updated successfully!';
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            } else {
                $error = 'Current password is incorrect';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy & Security - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/privacy.css">
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
                <a href="privacy.php" class="menu-item active"><span>🔒</span> Privacy & Security</a>
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
                    <h1>Privacy</h1>
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
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <div class="privacy-cards">
                <!-- Change Username Card - Modern Design -->
                <div class="privacy-card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-edit"></i> Change Username</h2>
                        <div class="card-icon">
                            <div class="icon-circle">
                                <i class="fas fa-user-cog"></i>
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <div class="input-label">
                                <label for="current_username">Current Username</label>
                            </div>
                            <div class="input-field">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" id="current_username" value="<?= htmlspecialchars($_SESSION['admin_username']) ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="input-label">
                                <label for="new_username">New Username</label>
                            </div>
                            <div class="input-field">
                                <i class="fas fa-user-plus input-icon"></i>
                                <input type="text" id="new_username" name="new_username" required placeholder="Enter new username">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="input-label">
                                <label for="current_password_username">Current Password</label>
                            </div>
                            <div class="input-field">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" id="current_password_username" name="current_password_username" required placeholder="Enter current password">
                                <i class="fas fa-eye password-toggle" data-target="current_password_username"></i>
                            </div>
                        </div>
                        <button type="submit" name="change_username" class="btn">
                            <i class="fas fa-save btn-icon"></i> Update Username
                        </button>
                    </form>
                </div>
                
                <!-- Change Password Card - Modern Design -->
                <div class="privacy-card">
                    <div class="card-header">
                        <h2><i class="fas fa-key"></i> Change Password</h2>
                        <div class="card-icon">
                            <div class="icon-circle">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <div class="input-label">
                                <label for="current_password">Current Password</label>
                            </div>
                            <div class="input-field">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" id="current_password" name="current_password" required placeholder="Enter current password">
                                <i class="fas fa-eye password-toggle" data-target="current_password"></i>
                            </div>
                        </div>
                        
                        <div class="password-field-wrapper">
                            <div class="form-group" style="margin-bottom: 8px;">
                                <div class="input-label">
                                    <label for="new_password">New Password</label>
                                </div>
                                <div class="input-field">
                                    <i class="fas fa-key input-icon"></i>
                                    <input type="password" id="new_password" name="new_password" required placeholder="Enter new password">
                                    <i class="fas fa-eye password-toggle" data-target="new_password"></i>
                                </div>
                            </div>
                            <div class="password-strength-container">
                                <div class="password-strength">
                                    <span class="strength-bar"></span>
                                    <span class="strength-bar"></span>
                                    <span class="strength-bar"></span>
                                    <span class="strength-bar"></span>
                                </div>
                                <div class="strength-text" id="strength-text">Password strength</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="input-label">
                                <label for="confirm_password">Confirm New Password</label>
                            </div>
                            <div class="input-field">
                                <i class="fas fa-key input-icon"></i>
                                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
                                <i class="fas fa-eye password-toggle" data-target="confirm_password"></i>
                            </div>
                        </div>
                        <button type="submit" name="change_password" class="btn">
                            <i class="fas fa-sync-alt btn-icon"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

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
            
            // Password visibility toggle functionality
            const passwordToggles = document.querySelectorAll('.password-toggle');
            passwordToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);
                    
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        this.classList.remove('fa-eye');
                        this.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        this.classList.remove('fa-eye-slash');
                        this.classList.add('fa-eye');
                    }
                });
            });

            // Password strength indicator
            const newPasswordInput = document.getElementById('new_password');
            const strengthBars = document.querySelectorAll('.strength-bar');
            const strengthText = document.getElementById('strength-text');
            
            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    const strength = calculatePasswordStrength(this.value);
                    updateStrengthIndicator(strength);
                });
            }
            
            function calculatePasswordStrength(password) {
                if (password.length === 0) return 0;
                
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength++;
                if (password.length >= 12) strength++;
                
                // Complexity checks
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                
                // Cap at 4 for our 4-bar display
                return Math.min(strength, 4);
            }
            
            function updateStrengthIndicator(strength) {
                strengthBars.forEach((bar, index) => {
                    if (index < strength) {
                        bar.style.background = getStrengthColor(strength);
                    } else {
                        bar.style.background = '#e0e0e0';
                    }
                });
                
                setTimeout(()=>document.querySelectorAll('.alert').forEach(e=>e.classList.add('hide')),3000)
                
                // Update text
                const messages = ['Very weak', 'Weak', 'Moderate', 'Strong', 'Very strong'];
                const colors = ['#ef4444', '#f59e0b', '#3b82f6', '#10b981', '#10b981'];
                strengthText.textContent = strength === 0 ? 'Password strength' : messages[strength - 1];
                strengthText.style.color = strength === 0 ? '#666' : colors[strength - 1];
            }
            
            function getStrengthColor(strength) {
                switch(strength) {
                    case 1: return '#ef4444'; // red
                    case 2: return '#f59e0b'; // yellow
                    case 3: return '#3b82f6'; // blue
                    case 4: return '#10b981'; // green
                    default: return '#e0e0e0'; // gray
                }
            }
        });
    </script>
</body>
</html>
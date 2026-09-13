<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (isAdminLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (adminLogin($username, $password)) {
        header("Location: index.php");
        exit();
    } else {
        $error = 'Invalid credentials. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | AppCenter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/login.css">
</head>
<body>
    <div class="particles" id="particles"></div>
    
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <h1>Admin Portal</h1>
            <p>Secure access to your dashboard</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>
        
        <form action="login.php" method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter admin username" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Access Dashboard
            </button>
        </form>
    </div>

    <script>
        // Create floating particles
        document.addEventListener('DOMContentLoaded', function() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 20;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Random properties
                const size = Math.random() * 6 + 2;
                const posX = Math.random() * 100;
                const delay = Math.random() * 15;
                const duration = 10 + Math.random() * 20;
                const opacity = Math.random() * 0.4 + 0.1;
                
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${posX}%`;
                particle.style.bottom = `-${size}px`;
                particle.style.animationDelay = `${delay}s`;
                particle.style.animationDuration = `${duration}s`;
                particle.style.opacity = opacity;
                
                particlesContainer.appendChild(particle);
            }

            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

ensureFaqTable();

$error = '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['success']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Add new FAQ
    if (isset($_POST['add_faq'])) {
        $question = trim($_POST['question']);
        $answer = trim($_POST['answer']);

        if ($question === '' || $answer === '') {
            $error = 'Both question and answer are required.';
        } else {
            $maxOrder = $pdo->query("SELECT MAX(sort_order) FROM faqs")->fetchColumn();
            $sortOrder = $maxOrder !== false && $maxOrder !== null ? $maxOrder + 1 : 0;

            $stmt = $pdo->prepare("INSERT INTO faqs (question, answer, sort_order, is_active) VALUES (?, ?, ?, 1)");
            if ($stmt->execute([$question, $answer, $sortOrder])) {
                $_SESSION['success'] = 'FAQ added successfully!';
                header("Location: faq.php");
                exit();
            } else {
                $error = 'Failed to add FAQ. Please try again.';
            }
        }
    }

    // Update existing FAQ
    if (isset($_POST['edit_faq'])) {
        $id = (int)$_POST['faq_id'];
        $question = trim($_POST['question']);
        $answer = trim($_POST['answer']);

        if ($question === '' || $answer === '') {
            $error = 'Both question and answer are required.';
        } else {
            $stmt = $pdo->prepare("UPDATE faqs SET question = ?, answer = ? WHERE id = ?");
            if ($stmt->execute([$question, $answer, $id])) {
                $_SESSION['success'] = 'FAQ updated successfully!';
                header("Location: faq.php");
                exit();
            } else {
                $error = 'Failed to update FAQ. Please try again.';
            }
        }
    }

    // Save new drag-and-drop order
    if (isset($_POST['update_order'])) {
        $order = json_decode($_POST['order'], true);
        if (is_array($order)) {
            foreach ($order as $position => $id) {
                $stmt = $pdo->prepare("UPDATE faqs SET sort_order = ? WHERE id = ?");
                $stmt->execute([$position, (int)$id]);
            }
            $_SESSION['success'] = 'FAQ order updated successfully!';
        }
        header("Location: faq.php");
        exit();
    }
}

// Toggle active / inactive
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE faqs SET is_active = 1 - is_active WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: faq.php");
    exit();
}

// Delete FAQ
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM faqs WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['success'] = 'FAQ deleted successfully!';
    } else {
        $error = 'Failed to delete FAQ. Please try again.';
    }
    header("Location: faq.php");
    exit();
}

// Get FAQ to edit
$editFaq = null;
if (isset($_GET['edit'])) {
    $editFaq = getFaqById((int)$_GET['edit']);
    if (!$editFaq) {
        $error = 'FAQ not found.';
    }
}

$faqs = getFaqs();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage FAQ - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-base.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin/faq.css">
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
                <a href="faq.php" class="menu-item active"><span>❓</span> Manage FAQ</a>
                <a href="seo.php" class="menu-item"><span>🔍</span> SEO Settings</a>
                <a href="statistics.php" class="menu-item"><span>📉</span> Statistics</a>
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
                    <h1>Manage FAQ</h1>
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

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2><?= $editFaq ? 'Edit FAQ' : 'Add New FAQ' ?></h2>
                <form method="POST">
                    <?= csrf_field() ?>
                    <?php if ($editFaq): ?>
                        <input type="hidden" name="faq_id" value="<?= $editFaq['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="question">Question</label>
                        <input type="text" id="question" name="question" class="form-control"
                               value="<?= htmlspecialchars($editFaq['question'] ?? '') ?>"
                               placeholder="e.g. How do I install an OBB file?" required>
                    </div>

                    <div class="form-group">
                        <label for="answer">Answer</label>
                        <textarea id="answer" name="answer" class="form-control" rows="6"
                                  placeholder="Write the answer here. Basic HTML like <strong>, <ul><li>, and <br> is allowed."
                                  required><?= htmlspecialchars($editFaq['answer'] ?? '') ?></textarea>
                        <small>You can use basic HTML tags (e.g. &lt;strong&gt;, &lt;ul&gt;&lt;li&gt;, &lt;br&gt;) to format the answer, or just plain text with line breaks.</small>
                    </div>

                    <?php if ($editFaq): ?>
                        <button type="submit" name="edit_faq" class="btn"><i class="fa-solid fa-pencil"></i> Update FAQ</button>
                        <a href="faq.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="add_faq" class="btn"><i class="fa-solid fa-plus"></i> Add FAQ</button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card">
                <h2>All FAQs</h2>
                <p class="hint-text">Drag <i class="fas fa-grip-vertical"></i> to reorder. Toggle to show or hide an FAQ on the site without deleting it.</p>

                <?php if (empty($faqs)): ?>
                    <p>No FAQs found. Add your first FAQ above.</p>
                <?php else: ?>
                    <ul class="faq-admin-list" id="sortable-faq">
                        <?php foreach ($faqs as $faq): ?>
                            <li class="faq-admin-card <?= $faq['is_active'] ? '' : 'is-inactive' ?>" data-id="<?= $faq['id'] ?>">
                                <i class="fas fa-grip-vertical drag-handle"></i>
                                <div class="faq-admin-content">
                                    <div class="faq-admin-question">
                                        <?= htmlspecialchars($faq['question']) ?>
                                        <?php if (!$faq['is_active']): ?>
                                            <span class="badge badge-hidden">Hidden</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="faq-admin-answer"><?= nl2br(htmlspecialchars($faq['answer'])) ?></div>
                                </div>
                                <div class="faq-admin-actions">
                                    <a href="faq.php?toggle=<?= $faq['id'] ?>" class="action-btn <?= $faq['is_active'] ? 'toggle-on' : 'toggle-off' ?>" title="<?= $faq['is_active'] ? 'Hide from site' : 'Show on site' ?>">
                                        <i class="fas <?= $faq['is_active'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                    </a>
                                    <a href="faq.php?edit=<?= $faq['id'] ?>" class="action-btn edit"><i class="fas fa-edit"></i></a>
                                    <a href="faq.php?delete=<?= $faq['id'] ?>" class="action-btn delete" onclick="return confirm('Delete this FAQ? This cannot be undone.');"><i class="fas fa-trash-alt"></i></a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <form method="POST" id="order-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order" id="faq-order" value="">
                        <button type="submit" name="update_order" id="save-order-btn" class="btn" style="display:none; margin-top: 15px;">
                            <i class="fa-solid fa-floppy-disk"></i> Save Order
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
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

    setTimeout(() => document.querySelectorAll('.alert').forEach(e => e.classList.add('hide')), 3000);

    // Drag-and-drop reordering
    const list = document.getElementById('sortable-faq');
    if (list) {
        new Sortable(list, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            handle: '.drag-handle',
            onEnd: function() {
                const order = [];
                document.querySelectorAll('#sortable-faq li').forEach(item => {
                    order.push(item.getAttribute('data-id'));
                });
                document.getElementById('faq-order').value = JSON.stringify(order);
                document.getElementById('save-order-btn').style.display = 'inline-block';
            }
        });
    }
});
    </script>
</body>
</html>

<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

ensureBlogTable();

if (!isset($_GET['slug'])) {
    header("Location: " . SITE_URL . "/blog");
    exit();
}

$post = getBlogPostBySlug($_GET['slug']);

if (!$post) {
    header("HTTP/1.0 404 Not Found");
    require_once 'includes/header.php';
    ?>
    <div style="max-width:700px;margin:60px auto;text-align:center;padding:0 20px;">
        <h1 style="margin-bottom:15px;">Post not found</h1>
        <p style="margin-bottom:25px;color:#666;">This blog post may have been removed or the link is incorrect.</p>
        <a href="<?= SITE_URL ?>/blog" style="color:#00c35e;font-weight:600;text-decoration:none;">&larr; Back to Blog</a>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit();
}

// Count this visit. Simple per-pageview counter (not de-duplicated by IP),
// matching how the rest of the site tracks lightweight metrics.
incrementBlogPostViews($post['id']);

// Handle comment submission (non-JS fallback; the form also submits via
// AJAX to submit_blog_comment.php when JavaScript is available)
$comment_error = null;
$comment_success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post-comment'])) {
    $name = filter_input(INPUT_POST, 'author', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING);

    if (!preg_match('/@gmail\.com$/', (string)$email)) {
        $comment_error = "Please use a @gmail.com email address";
    } elseif (!$name || !$comment) {
        $comment_error = "All fields are required";
    } else {
        $stmt = $pdo->prepare("INSERT INTO blog_comments (post_id, name, email, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$post['id'], $name, $email, $comment]);
        $comment_success = "Comment posted successfully!";
    }
}

$comments = getBlogComments($post['id']);

require_once 'includes/header.php';

$relatedPosts = getRecentBlogPosts(3, $post['id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(!empty($post['meta_title']) ? $post['meta_title'] : $post['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars(!empty($post['meta_description']) ? $post['meta_description'] : $post['excerpt']) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public/breadcrumb-pill.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public/blog_single.css">
</head>
<body>
    <div class="container">
        <div class="post-card">
            <div class="post-title-card">
                <div class="breadcrumb-pill">
                    <a href="<?= SITE_URL ?>">Home</a>
                    <span class="separator">/</span>
                    <a href="<?= SITE_URL ?>/blog">Blog</a>
                    <span class="separator">/</span>
                    <?= htmlspecialchars($post['title']) ?>
                </div>
                <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>
            </div>

            <?php if (!empty($post['featured_image'])): ?>
                <img class="post-featured-image" src="<?= SITE_URL ?>/uploads/blog/<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>">
            <?php endif; ?>

            <div class="post-meta-row">
                <span class="post-date"><i class="far fa-calendar"></i> <?= date('F d, Y', strtotime($post['created_at'])) ?></span>
                <span class="post-views"><i class="far fa-eye"></i> <?= number_format((int)($post['view_count'] ?? 0)) ?></span>
                <span class="post-comments-count"><i class="far fa-comment"></i> <?= count($comments) ?></span>
            </div>

            <div class="post-content">
                <?= $post['content'] ?>
            </div>
        </div>

        <?php if (!empty($relatedPosts)): ?>
            <div class="related-posts">
                <h2>More Posts</h2>
                <div class="related-grid">
                    <?php foreach ($relatedPosts as $related): ?>
                        <a class="related-card" href="<?= SITE_URL ?>/blog/<?= htmlspecialchars($related['slug']) ?>">
                            <div class="related-card-img">
                                <?php if (!empty($related['featured_image'])): ?>
                                    <img src="<?= SITE_URL ?>/uploads/blog/<?= htmlspecialchars($related['featured_image']) ?>" alt="<?= htmlspecialchars($related['title']) ?>" loading="lazy">
                                <?php endif; ?>
                            </div>
                            <div class="related-card-body">
                                <div class="related-card-title"><?= htmlspecialchars($related['title']) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Comment Form Section -->
        <div class="comment-form-card">
            <div class="section-header">
                <h3>
                    <div class="section-icon" style="--gradient-from: #DF85FF; --gradient-to: #AD00FF; --shadow-color: #DF85FF;">
                        <svg class="svg-inline--fa icon" aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
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

        <!-- Comments List Section -->
        <div class="comments-list-card">
            <div class="section-header">
                <h3>
                    <div class="section-icon" style="--gradient-from: #FFC785; --gradient-to: #FF9900; --shadow-color: #FFC785;">
                        <svg class="svg-inline--fa fa-comment icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="comment" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                            <path fill="currentColor" d="M256 32C114.6 32 .0272 125.1 .0272 240c0 49.63 21.35 94.98 56.97 130.7c-12.5 50.37-54.27 95.27-54.77 95.77c-2.25 2.25-2.875 5.734-1.5 8.734C1.979 478.2 4.75 480 8 480c66.25 0 115.1-31.76 140.6-51.39C181.2 440.9 217.6 448 256 448c141.4 0 255.1-93.13 255.1-208S397.4 32 256 32z"></path>
                        </svg>
                    </div>
                    Comments <?= count($comments) ?>
                </h3>
            </div>

            <div class="comments-list-content" id="commentsList">
                <?php if (empty($comments)): ?>
                    <p>
                        No comments. Write your first comment.
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
        const commentForm = document.getElementById('commentForm');
        const emailInput = document.getElementById('email');
        const submitCommentBtn = document.getElementById('submitBtn');
        const commentsList = document.getElementById('commentsList');
        const commentsHeading = document.querySelector('.comments-list-card .section-header h3');

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
                formData.append('post_id', '<?= (int)$post['id'] ?>');

                const response = await fetch('<?= SITE_URL ?>/submit_blog_comment.php', {
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

                if (commentsList.querySelector('p')) {
                    commentsList.innerHTML = commentHtml;
                } else {
                    commentsList.insertAdjacentHTML('afterbegin', commentHtml);
                }

                if (commentsHeading) {
                    const match = commentsHeading.textContent.match(/(\d+)/);
                    const currentCount = match ? parseInt(match[1]) : 0;
                    commentsHeading.innerHTML = commentsHeading.innerHTML.replace(/\d+/, currentCount + 1);
                }

                const postCommentsCount = document.querySelector('.post-comments-count');
                if (postCommentsCount) {
                    const match = postCommentsCount.textContent.match(/(\d+)/);
                    const currentCount = match ? parseInt(match[1]) : 0;
                    postCommentsCount.innerHTML = postCommentsCount.innerHTML.replace(/\d+/, currentCount + 1);
                }

                let successMessage = document.querySelector('.success-message');
                if (!successMessage) {
                    successMessage = document.createElement('div');
                    successMessage.className = 'success-message';
                    document.querySelector('.comment-form-content').prepend(successMessage);
                }
                successMessage.style.display = 'block';
                successMessage.innerHTML = '<i class="fas fa-check-circle"></i> Comment posted successfully!';

                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 5000);

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

    <?php
    require_once 'includes/footer.php';
    ?>
</body>
</html>

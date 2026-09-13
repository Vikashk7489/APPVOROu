<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// Get the app slug from referrer or session
$slug = $_POST['slug'] ?? '';
if (empty($slug)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'App slug missing']));
}

// Get app details
$app = getAppBySlug($slug);
if (!$app) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'App not found']));
}

try {
    $rating = (int)$_POST['rating'];
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $app_id = $app['id'];
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        throw new Exception('Invalid rating value');
    }

    // Check if user has already rated
    $stmt = $pdo->prepare("SELECT id FROM ratings WHERE app_id = ? AND user_ip = ?");
    $stmt->execute([$app_id, $user_ip]);
    $existing_rating = $stmt->fetch();

    if ($existing_rating) {
        $stmt = $pdo->prepare("UPDATE ratings SET rating = ? WHERE id = ?");
        $stmt->execute([$rating, $existing_rating['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO ratings (app_id, user_ip, rating) VALUES (?, ?, ?)");
        $stmt->execute([$app_id, $user_ip, $rating]);
    }
    
    // Calculate new average
    $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM ratings WHERE app_id = ?");
    $stmt->execute([$app_id]);
    $rating_data = $stmt->fetch();

    // Update app
    $stmt = $pdo->prepare("UPDATE apps SET rating = ?, total_ratings = ? WHERE id = ?");
    $stmt->execute([round($rating_data['avg_rating'], 1), $rating_data['total_ratings'], $app_id]);

    // Return success
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'avg_rating' => round($rating_data['avg_rating'], 1),
        'total_ratings' => $rating_data['total_ratings'],
        'message' => 'Thank you for rating!'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
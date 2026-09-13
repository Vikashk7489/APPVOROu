<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $name = filter_input(INPUT_POST, 'author', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING);
    $app_id = filter_input(INPUT_POST, 'app_id', FILTER_VALIDATE_INT);

    if (!$name || !$email || !$comment || !$app_id) {
        throw new Exception('All fields are required');
    }

    if (!preg_match('/@gmail\.com$/', $email)) {
        throw new Exception('Please use a @gmail.com email address');
    }

    $stmt = $pdo->prepare("INSERT INTO comments (app_id, name, email, comment) VALUES (?, ?, ?, ?)");
    $stmt->execute([$app_id, $name, $email, $comment]);

    $response['success'] = true;
    $response['message'] = 'Comment posted successfully';
    $response['comment'] = [
        'name' => $name,
        'email' => $email,
        'comment' => $comment,
        'date' => date('M d, Y h:i A')
    ];
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
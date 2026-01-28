<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['endpoint'], $input['keys']['p256dh'], $input['keys']['auth'])) {
    // Aboneliği kaydet veya güncelle
    $stmt = $pdo->prepare("INSERT INTO push_subscriptions (user_id, endpoint, public_key, auth_token) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), public_key = VALUES(public_key), auth_token = VALUES(auth_token)");
    $stmt->execute([$_SESSION['user_id'], $input['endpoint'], $input['keys']['p256dh'], $input['keys']['auth']]);
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
}
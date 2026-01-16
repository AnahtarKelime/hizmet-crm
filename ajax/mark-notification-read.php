<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = $_POST['id'] ?? null; // offer_id veya demand_id
$type = $_POST['type'] ?? 'customer_offer'; // customer_offer, offer_accepted, new_lead

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing ID']);
    exit;
}

try {
    $stmt = null;

    if ($type === 'customer_offer') {
        // Müşteri: Gelen teklifi okundu işaretle
        $stmt = $pdo->prepare("
            UPDATE offers o
            JOIN demands d ON o.demand_id = d.id
            SET o.is_read = 1
            WHERE o.id = ? AND d.user_id = ?
        ");
        $stmt->execute([$id, $_SESSION['user_id']]);

    } elseif ($type === 'offer_accepted') {
        // Hizmet Veren: Kabul edilen teklifi okundu işaretle
        // Not: offers tablosunda provider_read sütunu olmalı
        $stmt = $pdo->prepare("UPDATE offers SET provider_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);

    } elseif ($type === 'new_lead') {
        // Hizmet Veren: Yeni iş fırsatını görüntülendi olarak işaretle
        $stmt = $pdo->prepare("INSERT IGNORE INTO lead_access_logs (demand_id, user_id, access_type) VALUES (?, ?, 'notification_click')");
        $stmt->execute([$id, $_SESSION['user_id']]);
    }

    if (!$stmt) {
        throw new Exception('Invalid type');
    }

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No changes made or unauthorized']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
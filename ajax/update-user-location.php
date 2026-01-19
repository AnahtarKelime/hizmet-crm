<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$address = $_POST['address'] ?? null;
$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;
$city = $_POST['city'] ?? null;
$district = $_POST['district'] ?? null;

if (!$address) {
    echo json_encode(['success' => false, 'error' => 'Missing address']);
    exit;
}

try {
    // Kullanıcının adres bilgilerini güncelle
    // city ve district alanları zaten users tablosunda var, onları da güncelliyoruz
    $stmt = $pdo->prepare("UPDATE users SET address_text = ?, latitude = ?, longitude = ?, city = IFNULL(?, city), district = IFNULL(?, district) WHERE id = ?");
    $stmt->execute([$address, $lat, $lng, $city, $district, $userId]);

    // Session'daki konumu da güncelle (Opsiyonel, eğer session'da tutuluyorsa)
    // $_SESSION['user_location'] = ...

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
<?php
require_once '../../config/db.php';
session_start();

// Sadece admin erişebilir
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    exit;
}

$type = $_POST['type'] ?? '';

if ($type) {
    $table = '';
    $column = 'is_read_by_admin'; // Varsayılan sütun adı

    switch ($type) {
        case 'demands': $table = 'demands'; break;
        case 'offers': $table = 'offers'; break;
        case 'users': $table = 'users'; break;
        case 'reviews': $table = 'reviews'; break;
        case 'reports': $table = 'reports'; break;
        case 'transactions': $table = 'transactions'; break;
        case 'messages': $table = 'contact_messages'; $column = 'is_read'; break;
        case 'support': $table = 'support_tickets'; break;
        case 'applications': $table = 'provider_details'; $column = 'application_status'; break;
    }

    if ($table) {
        if ($type === 'applications') {
            $sql = "UPDATE `$table` SET `$column` = 'viewed' WHERE `$column` = 'pending'";
        } else {
            $sql = "UPDATE `$table` SET `$column` = 1 WHERE `$column` = 0";
        }
        $pdo->exec($sql);
        echo json_encode(['status' => 'success']);
    }
}
?>
<?php
require_once '../config/db.php';

// Admin kontrolü
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// DataTables parametreleri
$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$search = $_POST['search']['value'] ?? '';
$orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
$orderDir = $_POST['order'][0]['dir'] ?? 'asc';

$columns = ['id', 'city', 'district', 'neighborhood', 'slug', 'id'];

// Temel Sorgu
$sql = "SELECT * FROM locations";
$where = [];
$params = [];

// Arama
if (!empty($search)) {
    $where[] = "(city LIKE ? OR district LIKE ? OR neighborhood LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

// Toplam Kayıt Sayısı (Filtresiz)
$totalStmt = $pdo->query("SELECT COUNT(*) FROM locations");
$totalRecords = $totalStmt->fetchColumn();

// Toplam Kayıt Sayısı (Filtreli)
$filteredSql = "SELECT COUNT(*) FROM locations";
if (!empty($where)) {
    $filteredSql .= " WHERE " . implode(" AND ", $where);
}
$filteredStmt = $pdo->prepare($filteredSql);
$filteredStmt->execute($params);
$filteredRecords = $filteredStmt->fetchColumn();

// Sıralama
$orderColumn = $columns[$orderColumnIndex] ?? 'id';
$sql .= " ORDER BY $orderColumn $orderDir";

// Limit
$sql .= " LIMIT $length OFFSET $start";

// Verileri Çek
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Veriyi Formatla
$result = [];
foreach ($data as $row) {
    $actions = '<div class="flex items-center gap-2 justify-end">';
    $actions .= '<button onclick="openEditModal('.$row['id'].', \''.htmlspecialchars($row['city'], ENT_QUOTES).'\', \''.htmlspecialchars($row['district'], ENT_QUOTES).'\', \''.htmlspecialchars($row['neighborhood'], ENT_QUOTES).'\')" class="text-indigo-600 hover:text-indigo-900 p-1" title="Düzenle"><span class="material-symbols-outlined text-base">edit</span></button>';
    $actions .= '<a href="locations.php?delete='.$row['id'].'" onclick="return confirm(\'Bu kaydı silmek istediğinize emin misiniz?\')" class="text-red-600 hover:text-red-900 p-1" title="Sil"><span class="material-symbols-outlined text-base">delete</span></a>';
    $actions .= '</div>';

    $result[] = [
        $row['id'],
        htmlspecialchars($row['city']),
        htmlspecialchars($row['district']),
        htmlspecialchars($row['neighborhood']),
        htmlspecialchars($row['slug']),
        $actions
    ];
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($filteredRecords),
    "data" => $result
]);
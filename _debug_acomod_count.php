<?php
require_once __DIR__ . "/globals.php";
require_once __DIR__ . "/db.php";

header('Content-Type: application/json; charset=utf-8');

$hospital = isset($_GET['hospital']) ? (int)$_GET['hospital'] : 0;
if ($hospital <= 0) {
    echo json_encode(['ok' => false, 'error' => 'hospital required']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id_acomodacao, fk_hospital, acomodacao_aco, valor_aco
    FROM tb_acomodacao
    WHERE fk_hospital = :hospital
    ORDER BY id_acomodacao DESC
");
$stmt->bindValue(':hospital', $hospital, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'ok' => true,
    'hospital' => $hospital,
    'count' => count($rows),
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

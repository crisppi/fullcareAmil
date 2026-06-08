<?php
include_once(__DIR__ . '/../check_logado.php');
include_once(__DIR__ . '/../globals.php');

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['id_usuario'] ?? 0);
$payload = [
    'success' => true,
    'count' => 0,
];

if ($userId <= 0) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT COUNT(*)
          FROM tb_mensagem
         WHERE para_usuario = :usuario
           AND vista = 0
    ");
    $stmt->bindValue(':usuario', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $payload['count'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $payload['success'] = false;
    $payload['count'] = 0;
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);

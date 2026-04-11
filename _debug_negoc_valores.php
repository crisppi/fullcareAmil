<?php
require_once __DIR__ . "/globals.php";
require_once __DIR__ . "/db.php";

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id_internacao']) ? (int)$_GET['id_internacao'] : 0;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'id_internacao required']);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        i.id_internacao,
        i.fk_hospital_int,
        i.data_intern_int,
        a.data_alta_alt,
        n.id_negociacao,
        n.tipo_negociacao,
        n.troca_de,
        n.troca_para,
        n.qtd,
        n.saving
    FROM tb_internacao i
    LEFT JOIN tb_alta a ON a.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_negociacao n ON n.fk_id_int = i.id_internacao
    WHERE i.id_internacao = :id
    ORDER BY a.id_alta DESC, n.id_negociacao DESC
    LIMIT 20
");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo json_encode(['ok' => false, 'error' => 'internacao not found']);
    exit;
}

$hospitalId = (int)($rows[0]['fk_hospital_int'] ?? 0);

$stmtAco = $conn->prepare("
    SELECT id_acomodacao, acomodacao_aco, valor_aco, data_contrato_aco
    FROM tb_acomodacao
    WHERE fk_hospital = :hospital
    ORDER BY id_acomodacao DESC
");
$stmtAco->bindValue(':hospital', $hospitalId, PDO::PARAM_INT);
$stmtAco->execute();
$acomodacoes = $stmtAco->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'ok' => true,
    'internacao' => $rows,
    'acomodacoes' => $acomodacoes,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

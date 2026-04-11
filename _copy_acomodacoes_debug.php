<?php
require_once __DIR__ . "/globals.php";
require_once __DIR__ . "/db.php";

header('Content-Type: application/json; charset=utf-8');

$src = isset($_GET['src']) ? (int)$_GET['src'] : 0;
$dst = isset($_GET['dst']) ? (int)$_GET['dst'] : 0;

if ($src <= 0 || $dst <= 0 || $src === $dst) {
    echo json_encode(['ok' => false, 'error' => 'invalid src/dst']);
    exit;
}

try {
    $conn->beginTransaction();

    $selSrc = $conn->prepare("
        SELECT acomodacao_aco, valor_aco, data_contrato_aco
        FROM tb_acomodacao
        WHERE fk_hospital = :src
        ORDER BY id_acomodacao ASC
    ");
    $selSrc->bindValue(':src', $src, PDO::PARAM_INT);
    $selSrc->execute();
    $rows = $selSrc->fetchAll(PDO::FETCH_ASSOC);

    $selDst = $conn->prepare("
        SELECT LOWER(TRIM(acomodacao_aco)) AS nome
        FROM tb_acomodacao
        WHERE fk_hospital = :dst
    ");
    $selDst->bindValue(':dst', $dst, PDO::PARAM_INT);
    $selDst->execute();
    $existing = [];
    foreach ($selDst->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $existing[(string)($r['nome'] ?? '')] = true;
    }

    $ins = $conn->prepare("
        INSERT INTO tb_acomodacao (
            acomodacao_aco,
            fk_hospital,
            valor_aco,
            fk_usuario_acomodacao,
            usuario_create_acomodacao,
            data_create_acomodacao,
            data_contrato_aco
        ) VALUES (
            :acomodacao_aco,
            :fk_hospital,
            :valor_aco,
            :fk_usuario_acomodacao,
            :usuario_create_acomodacao,
            :data_create_acomodacao,
            :data_contrato_aco
        )
    ");

    $inserted = 0;
    foreach ($rows as $row) {
        $nome = trim((string)($row['acomodacao_aco'] ?? ''));
        if ($nome === '') {
            continue;
        }
        $key = mb_strtolower($nome);
        if (isset($existing[$key])) {
            continue;
        }

        $ins->bindValue(':acomodacao_aco', $nome);
        $ins->bindValue(':fk_hospital', $dst, PDO::PARAM_INT);
        $ins->bindValue(':valor_aco', (float)($row['valor_aco'] ?? 0));
        $ins->bindValue(':fk_usuario_acomodacao', (int)($_SESSION['id_usuario'] ?? 0), PDO::PARAM_INT);
        $ins->bindValue(':usuario_create_acomodacao', (string)($_SESSION['email_user'] ?? 'debug-copy'));
        $ins->bindValue(':data_create_acomodacao', date('Y-m-d H:i:s'));
        $dataContrato = trim((string)($row['data_contrato_aco'] ?? ''));
        $ins->bindValue(':data_contrato_aco', $dataContrato !== '' ? $dataContrato : null);
        $ins->execute();
        $existing[$key] = true;
        $inserted++;
    }

    $conn->commit();

    echo json_encode([
        'ok' => true,
        'src' => $src,
        'dst' => $dst,
        'source_count' => count($rows),
        'inserted' => $inserted,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

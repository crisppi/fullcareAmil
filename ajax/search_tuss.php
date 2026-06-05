<?php
require_once(__DIR__ . '/../globals.php');
require_once(__DIR__ . '/../db.php');
require_once(__DIR__ . '/_auth_scope.php');

header('Content-Type: application/json; charset=utf-8');

ajax_require_active_session();

$q = trim((string)($_GET['q'] ?? ''));

try {
    if ($q === '') {
        $stmt = $conn->prepare(
            "SELECT cod_tuss, terminologia_tuss
             FROM tb_tuss_ans
             GROUP BY cod_tuss, terminologia_tuss
             ORDER BY cod_tuss
             LIMIT 10"
        );
        $stmt->execute();
    } elseif (strlen($q) < 2) {
        echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        $like = '%' . $q . '%';
        $starts = $q . '%';
        $stmt = $conn->prepare(
            "SELECT cod_tuss, terminologia_tuss
             FROM tb_tuss_ans
             WHERE cod_tuss LIKE :q_cod OR terminologia_tuss LIKE :q_term
             GROUP BY cod_tuss, terminologia_tuss
             ORDER BY CASE WHEN cod_tuss LIKE :starts THEN 0 ELSE 1 END, cod_tuss
             LIMIT 20"
        );
        $stmt->bindValue(':q_cod', $like);
        $stmt->bindValue(':q_term', $like);
        $stmt->bindValue(':starts', $starts);
        $stmt->execute();
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $results = array_map(function ($r) {
        return [
            'id'   => $r['cod_tuss'],
            'text' => $r['cod_tuss'] . ' - ' . $r['terminologia_tuss'],
        ];
    }, $rows);

    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['results' => []]);
}

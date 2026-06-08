<?php
// ajax/pacientes_search.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Muda o diretório de trabalho para a raiz do projeto (um nível acima de /ajax)
$ROOT = dirname(__DIR__);
chdir($ROOT);

// Agora pode requerer usando caminhos relativos à raiz
require_once 'globals.php';
require_once 'db.php';
require_once 'ajax/_auth_scope.php';
require_once 'app/services/AuditorActionService.php';
require_once 'models/message.php';
require_once 'models/paciente.php'; // opcional, mas não atrapalha (require_once)
require_once 'dao/pacienteDao.php';

ajax_require_active_session();
$ctx = ajax_user_context($conn);

$basePath = (string)(parse_url((string)$BASE_URL, PHP_URL_PATH) ?? '/');
$basePath = '/' . trim($basePath, '/') . '/';
if ($basePath === '//') {
    $basePath = '/';
}
$requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
if ($basePath === '/' && preg_match('#^/(fullcareAmil|FullCare|FullConex(?:Aud)?)(/|$)#i', $requestPath, $mBaseApp)) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $BASE_URL = ($isHttps ? 'https' : 'http') . '://' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . trim((string)$mBaseApp[1], '/') . '/';
}

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? trim((string)$_GET['type']) : 'paciente';


if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    if (AuditorActionService::canUseOperationalSearch($_SESSION)) {
        $auditorSearch = new AuditorActionService($conn, $BASE_URL);
        echo json_encode($auditorSearch->globalSearch($q, $_SESSION, 12, $type));
        exit;
    }

    $scopeParams = [];
    $scopePacSql = ajax_scope_clause_for_paciente($ctx, 'pa', $scopeParams, 'psh');
    $scopeIntSql = ajax_scope_clause_for_internacao($ctx, 'i2', $scopeParams, 'psh2');
    $scopeIntExistsSql = ajax_scope_clause_for_internacao($ctx, 'i', $scopeParams, 'psh3');

    $sql = "
        SELECT
            pa.id_paciente,
            pa.nome_pac,
            pa.matricula_pac,
            pa.data_nasc_pac,
            (
                SELECT i2.senha_int
                FROM tb_internacao i2
                WHERE i2.fk_paciente_int = pa.id_paciente
                {$scopeIntSql}
                ORDER BY i2.data_intern_int DESC, i2.id_internacao DESC
                LIMIT 1
            ) AS ultima_senha
        FROM tb_paciente pa
        WHERE
            IFNULL(pa.deletado_pac, 'n') <> 's'
            {$scopePacSql}
            AND (
                pa.nome_pac LIKE :like_nome
                OR CONCAT(
                    pa.matricula_pac,
                    CASE WHEN pa.recem_nascido_pac = 's' THEN 'RN' ELSE '' END,
                    IFNULL(pa.numero_rn_pac, '')
                ) LIKE :like_matricula
                OR EXISTS (
                    SELECT 1
                    FROM tb_internacao i
                    WHERE i.fk_paciente_int = pa.id_paciente
                      AND i.senha_int LIKE :like_senha
                      {$scopeIntExistsSql}
                )
            )
        ORDER BY pa.nome_pac ASC
        LIMIT 10
    ";
    $stmt = $conn->prepare($sql);
    $params = array_merge([
        ':like_nome' => '%' . $q . '%',
        ':like_matricula' => '%' . $q . '%',
        ':like_senha' => '%' . $q . '%',
    ], $scopeParams);
    ajax_bind_params($stmt, $params);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Formatação leve para o front
    $out = array_map(function ($r) {
        $nasc_fmt = null;
        if (!empty($r['data_nasc_pac'])) {
            $dt = new DateTime($r['data_nasc_pac']);
            $nasc_fmt = $dt->format('d/m/Y');
        }
        return [
            'id_paciente' => (int) $r['id_paciente'],
            'nome' => $r['nome_pac'] ?? '',
            'matricula' => $r['matricula_pac'] ?? '',
            'nascimento_fmt' => $nasc_fmt,
            'senha' => $r['ultima_senha'] ?? ''
        ];
    }, $rows);

    echo json_encode($out);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno']);
}

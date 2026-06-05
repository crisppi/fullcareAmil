<?php

if (!defined("FLOW_LOGGER_AUTO_V1")) {
    define("FLOW_LOGGER_AUTO_V1", 1);
    @require_once(__DIR__ . "/../../utils/flow_logger.php");
    if (function_exists("flowLogStart") && function_exists("flowLog")) {
        $__flowCtxAuto = flowLogStart(basename(__FILE__, ".php"), [
            "type" => $_POST["type"] ?? $_GET["type"] ?? null,
            "method" => $_SERVER["REQUEST_METHOD"] ?? null,
        ]);
        register_shutdown_function(function () use ($__flowCtxAuto) {
            $err = error_get_last();
            if ($err && in_array(($err["type"] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                flowLog($__flowCtxAuto, "shutdown.fatal", "ERROR", [
                    "message" => $err["message"] ?? null,
                    "file" => $err["file"] ?? null,
                    "line" => $err["line"] ?? null,
                ]);
            }
            flowLog($__flowCtxAuto, "request.finish", "INFO");
        });
    }
}

require_once __DIR__ . '/../../globals.php';
require_once __DIR__ . '/../../db.php';
include_once __DIR__ . '/../../dao/permissionDao.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');

/* Mesma checagem de Diretoria usada na página */
$cargo  = $_SESSION['cargo']  ?? '';
$nivel  = $_SESSION['nivel']  ?? '';
$ativo  = strtolower((string)($_SESSION['ativo'] ?? ''));
$idUser = (int)($_SESSION['id_usuario'] ?? 0);

function nrm($s)
{
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = $c !== false ? $c : $s;
    return preg_replace('/[^a-z]/', '', $s);
}
$isDiretoria = in_array(nrm($cargo), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || in_array(nrm($nivel), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || ((int)$nivel === -1);

if (!$idUser || !$isDiretoria || $ativo !== 's') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado.']);
    exit;
}

/* Lê JSON e valida CSRF */
$raw = file_get_contents('php://input');
$req = json_decode($raw, true) ?: [];

if (empty($req['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $req['csrf'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'CSRF inválido']);
    exit;
}

$perm = $req['perm'] ?? null;
if (!is_array($perm) || !$perm) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nenhuma permissão recebida']);
    exit;
}

/* Salva */
try {
    $dao = new PermissionDAO($conn, $BASE_URL);
    $dao->bulkUpdate($perm);
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    error_log('[PERMISSOES] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar permissões.']);
}

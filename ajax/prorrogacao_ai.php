<?php
header('Content-Type: application/json; charset=utf-8');

session_start();

$ROOT = dirname(__DIR__);
chdir($ROOT);

require_once 'db.php';
require_once 'ajax/_auth_scope.php';
require_once 'app/services/ProrrogacaoAiService.php';

ajax_require_active_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'metodo_nao_permitido']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'json_invalido']);
    exit;
}

$report = trim((string)($input['report'] ?? ''));
if ($report === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'contexto_obrigatorio']);
    exit;
}

try {
    $service = new ProrrogacaoAiService();
    echo json_encode([
        'success' => true,
        'data' => $service->analyze($report),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'falha_parecer_prorrogacao_ia',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

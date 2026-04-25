<?php
header('Content-Type: application/json; charset=utf-8');

session_start();

$ROOT = dirname(__DIR__);
chdir($ROOT);

require_once 'db.php';
require_once 'ajax/_auth_scope.php';
require_once 'app/services/TextSecurityService.php';
require_once 'app/services/UtiAuditAiService.php';

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
    echo json_encode(['success' => false, 'error' => 'relatorio_obrigatorio']);
    exit;
}

try {
    $security = new TextSecurityService();
    $assessment = $security->assess($report, 'relatorio_uti_ia', true);
    if ($security->shouldBlock($assessment)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'conteudo_suspeito',
            'message' => 'O relatorio contem padroes suspeitos e nao foi enviado para IA.',
            'security' => $assessment,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $service = new UtiAuditAiService();
    echo json_encode([
        'success' => true,
        'data' => $service->analyzeReport($report),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'falha_parecer_ia',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

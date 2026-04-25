<?php
header('Content-Type: application/json; charset=utf-8');

session_start();

$ROOT = dirname(__DIR__);
chdir($ROOT);

require_once 'db.php';
require_once 'ajax/_auth_scope.php';
require_once 'app/services/TextSecurityService.php';
require_once 'app/services/ClinicalTextAiService.php';

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

$action = trim((string)($input['action'] ?? 'improve'));
$field = trim((string)($input['field'] ?? 'texto'));
$text = trim((string)($input['text'] ?? ''));

$context = is_array($input['context'] ?? null) ? $input['context'] : [];

if ($text === '' && $action !== 'checklist') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'texto_obrigatorio', 'message' => 'Informe um texto para organizar.']);
    exit;
}
if ($action === 'checklist') {
    $contextText = trim((string)($context['relatorio'] ?? '') . "\n" . (string)($context['acoes'] ?? '') . "\n" . (string)($context['programacao'] ?? ''));
    if ($contextText === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'texto_obrigatorio', 'message' => 'Informe relatório, ações ou programação para gerar o checklist.']);
        exit;
    }
}
if (!in_array($action, ['improve', 'checklist'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'acao_invalida']);
    exit;
}

$labels = [
    'rel_int' => 'Relatório da Auditoria',
    'acoes_int' => 'Ações da Auditoria',
    'programacao_int' => 'Programação Terapêutica',
    'rel_visita_vis' => 'Relatório da Visita',
    'acoes_int_vis' => 'Ações da Visita',
    'programacao_enf' => 'Programação Terapêutica da Visita',
];
$fieldLabel = $labels[$field] ?? 'Texto clínico';

try {
    $security = new TextSecurityService();
    $securityText = $action === 'checklist'
        ? trim((string)($context['relatorio'] ?? '') . "\n" . (string)($context['acoes'] ?? '') . "\n" . (string)($context['programacao'] ?? ''))
        : $text;
    $assessment = $security->assess($securityText, $field, true);
    if ($security->shouldBlock($assessment)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'conteudo_suspeito',
            'message' => 'O texto contém padrões suspeitos e não foi enviado para IA.',
            'security' => $assessment,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $service = new ClinicalTextAiService();
    if ($action === 'checklist') {
        echo json_encode([
            'success' => true,
            'data' => $service->checklist($context),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'field' => $field,
            'text' => $service->improve($text, $fieldLabel),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'falha_ia_texto',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

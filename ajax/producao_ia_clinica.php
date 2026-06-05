<?php
define('SKIP_HEADER', true);
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../globals.php';
require_once __DIR__ . '/_auth_scope.php';
require_once __DIR__ . '/../app/services/AuditoriaClinicaAIService.php';
include_once __DIR__ . '/../check_logado.php';

header('Content-Type: application/json; charset=utf-8');

try {
    ajax_require_active_session();
    $ctx = ajax_user_context($conn);

    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $service = new AuditoriaClinicaAIService($conn, $BASE_URL);
    $result = $service->answer(
        (string)($payload['question'] ?? ''),
        is_array($payload['filters'] ?? null) ? $payload['filters'] : [],
        $ctx
    );

    echo json_encode(['success' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

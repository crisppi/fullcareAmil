<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = (string)($_GET['action'] ?? '');

try {
    if ($method === 'POST' && $action === 'login') {
        $input = mobileJsonInput();
        mobileHandleLogin($conn, $input);
    }

    $authUser = mobileRequireAuth($conn);

    if ($method === 'GET' && $action === 'me') {
        mobileJsonResponse([
            'success' => true,
            'data' => $authUser,
        ]);
    }

    if ($method === 'GET' && $action === 'patients') {
        $query = trim((string)($_GET['query'] ?? ''));
        mobileJsonResponse([
            'success' => true,
            'data' => [
                'items' => mobileSearchPatients($conn, $authUser, $query),
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'admissions') {
        $query = trim((string)($_GET['query'] ?? ''));
        mobileJsonResponse([
            'success' => true,
            'data' => [
                'items' => mobileListAdmissions($conn, $authUser, $query),
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'admission') {
        $admissionId = (int)($_GET['id'] ?? 0);
        $admission = mobileFindAdmission($conn, $authUser, $admissionId);
        if ($admission === null) {
            mobileJsonResponse(['success' => false, 'message' => 'Internacao nao encontrada.'], 404);
        }

        mobileJsonResponse([
            'success' => true,
            'data' => [
                'admission' => $admission,
                'tuss_items' => mobileListAdmissionTuss($conn, $admissionId),
                'extensions' => mobileListAdmissionExtensions($conn, $admissionId),
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'admission-evolutions') {
        $admissionId = (int)($_GET['id'] ?? 0);
        if ($admissionId <= 0 || mobileFindAdmission($conn, $authUser, $admissionId) === null) {
            mobileJsonResponse(['success' => false, 'message' => 'Internacao invalida.'], 422);
        }

        mobileJsonResponse([
            'success' => true,
            'data' => [
                'items' => mobileListAdmissionEvolutions($conn, $admissionId),
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'tuss-catalog') {
        $query = trim((string)($_GET['query'] ?? ''));
        mobileJsonResponse([
            'success' => true,
            'data' => [
                'items' => mobileSearchTussCatalog($conn, $query),
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'discharge-types') {
        mobileJsonResponse([
            'success' => true,
            'data' => [
                'items' => mobileListDischargeTypes(),
            ],
        ]);
    }

    if ($method === 'POST' && $action === 'admission-tuss') {
        $input = mobileJsonInput();
        $admissionId = (int)($input['admission_id'] ?? 0);
        if ($admissionId <= 0 || mobileFindAdmission($conn, $authUser, $admissionId) === null) {
            mobileJsonResponse(['success' => false, 'message' => 'Internacao invalida.'], 422);
        }

        $item = mobileCreateAdmissionTuss($conn, $authUser, $input);
        mobileJsonResponse([
            'success' => true,
            'message' => 'TUSS salvo com sucesso.',
            'data' => $item,
        ], 201);
    }

    if ($method === 'POST' && $action === 'admission-extension') {
        $input = mobileJsonInput();
        $admissionId = (int)($input['admission_id'] ?? 0);
        if ($admissionId <= 0 || mobileFindAdmission($conn, $authUser, $admissionId) === null) {
            mobileJsonResponse(['success' => false, 'message' => 'Internacao invalida.'], 422);
        }

        $extension = mobileCreateAdmissionExtension($conn, $authUser, $input);
        mobileJsonResponse([
            'success' => true,
            'message' => 'Prorrogacao salva com sucesso.',
            'data' => $extension,
        ], 201);
    }

    if ($method === 'POST' && $action === 'admission-discharge') {
        $input = mobileJsonInput();
        $admissionId = (int)($input['admission_id'] ?? 0);
        if ($admissionId <= 0 || mobileFindAdmission($conn, $authUser, $admissionId) === null) {
            mobileJsonResponse(['success' => false, 'message' => 'Internacao invalida.'], 422);
        }

        $discharge = mobileCreateAdmissionDischarge($conn, $authUser, $input);
        mobileJsonResponse([
            'success' => true,
            'message' => 'Alta registrada com sucesso.',
            'data' => $discharge,
        ], 201);
    }

    if ($method === 'POST' && $action === 'admission-evolution') {
        $input = mobileJsonInput();
        $admissionId = (int)($input['admission_id'] ?? 0);
        if ($admissionId <= 0 || mobileFindAdmission($conn, $authUser, $admissionId) === null) {
            mobileJsonResponse(['success' => false, 'message' => 'Internacao invalida.'], 422);
        }

        try {
            $evolution = mobileCreateAdmissionEvolution($conn, $authUser, $input);
        } catch (InvalidArgumentException $exception) {
            mobileJsonResponse(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        mobileJsonResponse([
            'success' => true,
            'message' => 'Evolucao salva com sucesso.',
            'data' => $evolution,
        ], 201);
    }

    mobileJsonResponse(['success' => false, 'message' => 'Rota nao encontrada.'], 404);
} catch (Throwable $exception) {
    mobileJsonResponse([
        'success' => false,
        'message' => 'Erro interno ao processar a requisicao.',
        'details' => $exception->getMessage(),
    ], 500);
}

<?php
declare(strict_types=1);

function mobileApiSecret(): string
{
    $secret = trim((string)(getenv('MOBILE_API_SECRET') ?: ''));
    if ($secret !== '') {
        return $secret;
    }

    return 'fullcare-mobile-dev-secret-change-me';
}

function mobileBase64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function mobileBase64UrlDecode(string $value): string
{
    $padding = 4 - (strlen($value) % 4);
    if ($padding < 4) {
        $value .= str_repeat('=', $padding);
    }

    return (string)base64_decode(strtr($value, '-_', '+/'));
}

function mobileGenerateToken(array $user): string
{
    $payload = [
        'uid' => (int)($user['id_usuario'] ?? 0),
        'email' => (string)($user['email_user'] ?? ''),
        'name' => (string)($user['usuario_user'] ?? ''),
        'nivel' => (int)($user['nivel_user'] ?? 99),
        'seguradora_id' => isset($user['fk_seguradora_user']) ? (int)$user['fk_seguradora_user'] : null,
        'exp' => time() + (60 * 60 * 12),
    ];

    $encodedPayload = mobileBase64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $signature = hash_hmac('sha256', $encodedPayload, mobileApiSecret());

    return $encodedPayload . '.' . $signature;
}

function mobileParseToken(string $token): ?array
{
    $parts = explode('.', trim($token), 2);
    if (count($parts) !== 2) {
        return null;
    }

    [$encodedPayload, $signature] = $parts;
    $expected = hash_hmac('sha256', $encodedPayload, mobileApiSecret());
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $decoded = json_decode(mobileBase64UrlDecode($encodedPayload), true);
    if (!is_array($decoded) || (int)($decoded['exp'] ?? 0) < time()) {
        return null;
    }

    return $decoded;
}

function mobileAuthorizationToken(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '');
    if (stripos($header, 'Bearer ') === 0) {
        return trim(substr($header, 7));
    }

    $queryToken = trim((string)($_GET['token'] ?? ''));
    if ($queryToken !== '') {
        return $queryToken;
    }

    return $header !== '' ? trim($header) : '';
}

function mobileFindUserByEmail(PDO $conn, string $email): ?array
{
    $sql = "
        SELECT
            id_usuario,
            usuario_user,
            email_user,
            senha_user,
            senha_default_user,
            ativo_user,
            nivel_user,
            cargo_user,
            foto_usuario,
            fk_seguradora_user
        FROM tb_user
        WHERE email_user = :email
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($user) ? $user : null;
}

function mobileFindUserById(PDO $conn, int $userId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            id_usuario,
            usuario_user,
            email_user,
            ativo_user,
            nivel_user,
            cargo_user,
            foto_usuario,
            fk_seguradora_user
        FROM tb_user
        WHERE id_usuario = :id
        LIMIT 1
    ");
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($user) ? $user : null;
}

function mobileHandleLogin(PDO $conn, array $input): void
{
    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($email === '' || $password === '') {
        mobileJsonResponse(['success' => false, 'message' => 'Informe e-mail e senha.'], 422);
    }

    $user = mobileFindUserByEmail($conn, $email);
    if ($user === null || ($user['ativo_user'] ?? 'n') !== 's') {
        mobileJsonResponse(['success' => false, 'message' => 'Credenciais invalidas.'], 401);
    }

    if (!password_verify($password, (string)($user['senha_user'] ?? ''))) {
        mobileJsonResponse(['success' => false, 'message' => 'Credenciais invalidas.'], 401);
    }

    $token = mobileGenerateToken($user);
    mobileJsonResponse([
        'success' => true,
        'data' => [
            'token' => $token,
            'user' => [
                'id' => (int)$user['id_usuario'],
                'name' => (string)$user['usuario_user'],
                'email' => (string)$user['email_user'],
                'role_level' => (int)($user['nivel_user'] ?? 99),
                'role_name' => (string)($user['cargo_user'] ?? ''),
                'seguradora_id' => isset($user['fk_seguradora_user']) ? (int)$user['fk_seguradora_user'] : null,
                'photo' => (string)($user['foto_usuario'] ?? ''),
            ],
        ],
    ]);
}

function mobileRequireAuth(PDO $conn): array
{
    $token = mobileAuthorizationToken();
    if ($token === '') {
        mobileJsonResponse(['success' => false, 'message' => 'Token ausente.'], 401);
    }

    $payload = mobileParseToken($token);
    if ($payload === null) {
        mobileJsonResponse(['success' => false, 'message' => 'Token invalido ou expirado.'], 401);
    }

    $user = mobileFindUserById($conn, (int)($payload['uid'] ?? 0));
    if ($user === null || ($user['ativo_user'] ?? 'n') !== 's') {
        mobileJsonResponse(['success' => false, 'message' => 'Usuario nao autorizado.'], 401);
    }

    return [
        'id' => (int)$user['id_usuario'],
        'name' => (string)$user['usuario_user'],
        'email' => (string)$user['email_user'],
        'role_level' => (int)($user['nivel_user'] ?? 99),
        'role_name' => (string)($user['cargo_user'] ?? ''),
        'seguradora_id' => isset($user['fk_seguradora_user']) ? (int)$user['fk_seguradora_user'] : null,
        'photo' => (string)($user['foto_usuario'] ?? ''),
    ];
}

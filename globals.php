<?php
// ==========================================================
// globals.php — Bootstrap comum do app (Hostinger/AMPPS)
// Compatível com PHP < 8 (inclui polyfills)
// ==========================================================

// ------------------ 0) Polyfills PHP 8 --------------------
if (!function_exists('str_contains')) {
    function str_contains(string $h, string $n): bool
    {
        return $n === '' || strpos($h, $n) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $h, string $n): bool
    {
        return $n === '' || strncmp($h, $n, strlen($n)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $h, string $n): bool
    {
        if ($n === '') return true;
        $len = strlen($n);
        return substr($h, -$len) === $n;
    }
}

// ------------------ 1) Descobrir BASE PATH ----------------
// Ajuste manual padrão (produção na raiz):
$APP_BASE_PATH = '/';

// Detecta automaticamente a subpasta real do app (ex.: /FullCare) usando o DOCUMENT_ROOT
$__docroot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$__appDir  = str_replace('\\', '/', realpath(__DIR__) ?: __DIR__);
if ($__docroot !== '' && strpos($__appDir, $__docroot) === 0) {
    $relative = trim(substr($__appDir, strlen($__docroot)), '/');
    if ($relative !== '') {
        $APP_BASE_PATH = '/' . $relative . '/';
    }
}

// Fallback para ambientes locais antigos (mantém suporte a /FullConex, etc.)
$__host   = $_SERVER['HTTP_HOST']   ?? '';
$__script = $_SERVER['SCRIPT_NAME'] ?? '';
if ($APP_BASE_PATH === '/' && $__host && stripos($__host, 'localhost') !== false) {
    if (preg_match('#^/(FullCare|FullConex(?:Aud)?)(/|$)#i', $__script, $match)) {
        $APP_BASE_PATH = '/' . trim($match[1], '/') . '/';
    }
}
// Normaliza
$APP_BASE_PATH = '/' . trim($APP_BASE_PATH, '/') . '/';
if ($APP_BASE_PATH === '//') $APP_BASE_PATH = '/';

// ------------------ 2) Descobrir scheme/host ---------------
$httpsForwarded = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    || (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"https"') !== false); // Cloudflare

$isHttps = $httpsForwarded
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

$SCHEME = $isHttps ? 'https' : 'http';
$HOST   = $_SERVER['HTTP_HOST'] ?? 'localhost';

// ------------------ 2.1) Security headers -------------------
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// ------------------ 3) BASE_URL estável --------------------
$BASE_URL = $SCHEME . '://' . $HOST . rtrim($APP_BASE_PATH, '/') . '/'; // sempre termina com '/'

// ------------------ 4) Sessão (com path correto) ----------
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Definições coerentes com o path da app
    $cookiePath = rtrim($APP_BASE_PATH, '/') ?: '/';

    if (version_compare(PHP_VERSION, '7.3', '>=')) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $cookiePath,
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        // Fallback para PHP < 7.3
        ini_set('session.cookie_secure', $isHttps ? '1' : '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_path', $cookiePath);
        // Alguns ambientes aceitam:
        @ini_set('session.cookie_samesite', 'Lax');
        session_set_cookie_params(0, $cookiePath, '', $isHttps, true);
    }
    session_start();
}

if (empty($_SESSION['csrf'])) {
    try {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

// ------------------ 5) DB primeiro -------------------------
require_once __DIR__ . '/db.php';   // aqui dentro você cria $conn (PDO)

if (!function_exists('fullcare_sync_session_user')) {
    function fullcare_sync_session_user(PDO $conn): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $sessionId = (int)($_SESSION['id_usuario'] ?? 0);
        $sessionEmail = trim((string)($_SESSION['email_user'] ?? ''));
        $sessionLogin = trim((string)($_SESSION['login_user'] ?? ''));
        $sessionName = trim((string)($_SESSION['usuario_user'] ?? ''));

        if ($sessionId <= 0 && $sessionEmail === '' && $sessionLogin === '' && $sessionName === '') {
            return;
        }

        try {
            $sqlBase = "
                SELECT
                    id_usuario,
                    usuario_user,
                    email_user,
                    login_user,
                    ativo_user,
                    nivel_user,
                    cargo_user,
                    foto_usuario,
                    fk_seguradora_user
                FROM tb_user
            ";

            $user = null;

            if ($sessionId > 0) {
                $stmtById = $conn->prepare($sqlBase . " WHERE id_usuario = :id LIMIT 1");
                $stmtById->bindValue(':id', $sessionId, PDO::PARAM_INT);
                $stmtById->execute();
                $user = $stmtById->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if (!is_array($user)) {
                $lookup = $sessionEmail !== '' ? mb_strtolower($sessionEmail, 'UTF-8') : '';
                $loginLookup = $sessionLogin !== '' ? mb_strtolower($sessionLogin, 'UTF-8') : $lookup;
                $nameLookup = $sessionName !== '' ? mb_strtolower($sessionName, 'UTF-8') : '';

                if ($lookup !== '' || $loginLookup !== '' || $nameLookup !== '') {
                    $stmtByIdentity = $conn->prepare($sqlBase . "
                        WHERE LOWER(TRIM(email_user)) = :email
                           OR LOWER(TRIM(email02_user)) = :email2
                           OR LOWER(TRIM(login_user)) = :login
                           OR LOWER(TRIM(usuario_user)) = :uname
                        LIMIT 1
                    ");
                    $stmtByIdentity->bindValue(':email', $lookup, PDO::PARAM_STR);
                    $stmtByIdentity->bindValue(':email2', $lookup, PDO::PARAM_STR);
                    $stmtByIdentity->bindValue(':login', $loginLookup, PDO::PARAM_STR);
                    $stmtByIdentity->bindValue(':uname', $nameLookup, PDO::PARAM_STR);
                    $stmtByIdentity->execute();
                    $user = $stmtByIdentity->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }

            if (!is_array($user)) {
                return;
            }

            $resolvedId = (int)($user['id_usuario'] ?? 0);
            if ($resolvedId <= 0) {
                return;
            }

            $_SESSION['id_usuario'] = $resolvedId;
            $_SESSION['usuario_user'] = (string)($user['usuario_user'] ?? ($_SESSION['usuario_user'] ?? ''));
            $_SESSION['email_user'] = (string)($user['email_user'] ?? ($_SESSION['email_user'] ?? ''));
            $_SESSION['login_user'] = (string)($user['login_user'] ?? ($_SESSION['login_user'] ?? $_SESSION['email_user'] ?? ''));
            $_SESSION['ativo'] = (string)($user['ativo_user'] ?? ($_SESSION['ativo'] ?? ''));
            $_SESSION['nivel'] = (int)($user['nivel_user'] ?? ($_SESSION['nivel'] ?? 99));
            $_SESSION['cargo'] = (string)($user['cargo_user'] ?? ($_SESSION['cargo'] ?? ''));
            $_SESSION['foto_usuario'] = (string)($user['foto_usuario'] ?? ($_SESSION['foto_usuario'] ?? ''));
            $_SESSION['fk_seguradora_user'] = isset($user['fk_seguradora_user'])
                ? (int)$user['fk_seguradora_user']
                : ($_SESSION['fk_seguradora_user'] ?? null);

            if ($sessionId > 0 && $sessionId !== $resolvedId) {
                error_log('[SESSION][SYNC] id_usuario ajustado de ' . $sessionId . ' para ' . $resolvedId . ' com base no usuario atual do banco.');
            }
        } catch (Throwable $e) {
            error_log('[SESSION][SYNC][ERROR] ' . $e->getMessage());
        }
    }
}

fullcare_sync_session_user($conn);

// ------------------ 6) Guard (autorização) -----------------
require_once __DIR__ . '/authz.php';

if (!function_exists('enforce_authenticated_session')) {
    function enforce_authenticated_session(string $BASE_URL): void
    {
        $idUser = (int)($_SESSION['id_usuario'] ?? 0);
        $ativo  = strtolower((string)($_SESSION['ativo'] ?? ''));
        $isAuth = $idUser > 0 && $ativo === 's';
        if ($isAuth) return;

        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || str_contains($accept, 'application/json')
            || str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');

        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . rtrim($BASE_URL, '/') . '/index.php', true, 303);
        exit;
    }
}

// Métodos que alteram estado
$__method     = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$__scriptBase = strtolower(basename($_SERVER['SCRIPT_NAME'] ?? ''));

// Endpoints liberados do Guard (não exigem sessão prévia)
$__guardSkip = [
    'check_login.php',   // login
    'logout.php',        // logout
    'index.php',         // tela de login
    'index_novo.php',    // sua tela de login nova
    'nova_senha.php',    // troca de senha inicial
    'process_recuperar_senha.php',
    'process_redefinir_senha.php',
    // acrescente aqui quaisquer webhooks ou callbacks públicos, se existirem
];

// Qualquer endpoint process_* (exceto os públicos acima) exige sessão válida,
// inclusive em GET, para bloquear execução por link direto sem autenticação.
if (str_starts_with($__scriptBase, 'process_') && !in_array($__scriptBase, $__guardSkip, true)) {
    enforce_authenticated_session($BASE_URL);
}

// Só aplica o Gate em métodos mutantes e quando o script NÃO está na whitelist
if (in_array($__method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !in_array($__scriptBase, $__guardSkip, true)) {
    Gate::autoEnforce($conn, $BASE_URL);
}

// Em páginas públicas de login, evita checagens/DDL de schema para acelerar carregamento.
$__schemaSkip = [
    'index.php',
    'index_novo.php',
    'check_login.php',
];
if (!in_array($__scriptBase, $__schemaSkip, true)) {
    require_once __DIR__ . '/app/schemaEnsurer.php';
    ensure_visita_timer_column($conn);
    ensure_visita_faturamento_columns($conn);
    ensure_internacao_timer_column($conn);
    ensure_internacao_core_columns($conn);
    ensure_internacao_forecast_columns($conn);
    ensure_schema_version_table($conn);
    ensure_password_reset_table($conn);
    ensure_operational_list_indexes($conn);
    ensure_hospital_related_tables($conn);
}
require_once __DIR__ . '/app/version.php';

// ------------------ 7) Helpers globais (opcional) ----------

if (!function_exists('app_url')) {
    /**
     * Gera URL absoluta baseada no BASE_URL
     * @example app_url('menu_app.php')
     */
    function app_url(string $path = ''): string
    {
        global $BASE_URL;
        $p = ltrim($path, '/');
        return rtrim($BASE_URL, '/') . ($p ? "/{$p}" : '/');
    }
}

if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}

if (!function_exists('flash')) {
    /**
     * Define / lê flash message simples
     * flash('msg', 'texto');  // define
     * $m = flash('msg');      // lê e apaga
     */
    function flash(string $key, $val = null)
    {
        if ($val === null) {
            if (!isset($_SESSION[$key])) return null;
            $tmp = $_SESSION[$key];
            unset($_SESSION[$key]);
            return $tmp;
        }
        $_SESSION[$key] = $val;
        return true;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return (string)($_SESSION['csrf'] ?? '');
    }
}

if (!function_exists('csrf_is_valid')) {
    function csrf_is_valid(?string $token): bool
    {
        $sessionToken = (string)($_SESSION['csrf'] ?? '');
        $token = (string)$token;
        return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
    }
}

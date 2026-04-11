<?php

if (!function_exists('db_env_value')) {
    function db_env_value(string $key): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}

if (!function_exists('db_load_env_file')) {
    function db_load_env_file(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key === '') {
                continue;
            }
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('db_try_local_profile')) {
    function db_try_local_profile(): ?array
    {
        $hosts = ['127.0.0.1', 'localhost'];
        $ports = [3306];
        $users = ['root'];
        $passes = ['', 'root', 'mysql'];
        $dbNames = array_values(array_unique(array_filter([
            db_env_value('DB_NAME_LOCAL'),
            db_env_value('DB_NAME'),
            'fullcare',
            'mydb_accert',
            'mydb_accert_new',
            'u650318666_mydb_accert_ho',
            'u650318666_mydb_accerthos',
        ])));

        foreach ($hosts as $host) {
            foreach ($ports as $port) {
                foreach ($users as $user) {
                    foreach ($passes as $pass) {
                        try {
                            $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass);
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $exists = array_flip($pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN, 0) ?: []);
                            foreach ($dbNames as $dbName) {
                                if (isset($exists[$dbName])) {
                                    return [
                                        'host' => $host,
                                        'name' => $dbName,
                                        'user' => $user,
                                        'pass' => $pass,
                                        'port' => $port,
                                        'charset' => 'utf8mb4',
                                        'label' => 'AutoLocal',
                                    ];
                                }
                            }
                        } catch (Throwable $e) {
                            // segue tentando outras combinacoes locais
                        }
                    }
                }
            }
        }
        return null;
    }
}

if (!function_exists('db_legacy_profiles')) {
    function db_legacy_profiles(): array
    {
        // Modo de compatibilidade para evitar quebra após pull em ambientes sem .env.
        return [
            [
                'host' => 'srv953.hstgr.io',
                'name' => 'u650318666_mydb_accert_ho',
                'user' => 'u650318666_diretoria10',
                'pass' => 'FullCare@BD2025!',
                'port' => 3306,
                'charset' => 'utf8',
                'label' => 'LegacyHostinger',
            ],
            [
                'host' => 'mydb-accert-new.mysql.uhserver.com',
                'name' => 'mydb_accert_new',
                'user' => 'diretoria5',
                'pass' => 'Fullcare12@',
                'port' => 3306,
                'charset' => 'utf8',
                'label' => 'LegacyUolNew',
            ],
            [
                'host' => 'mdb-accert.mysql.uhserver.com',
                'name' => 'mydb_accert',
                'user' => 'diretoria2',
                'pass' => 'Guga@0401',
                'port' => 3306,
                'charset' => 'utf8',
                'label' => 'LegacyUolFallback',
            ],
        ];
    }
}

// Carrega .env local sem sobrescrever variaveis ja exportadas no ambiente.
db_load_env_file(__DIR__ . '/.env');

$dbStrictMode = in_array(strtolower((string)(db_env_value('DB_STRICT_MODE') ?? '')), ['1', 'true', 'on', 'yes'], true);

$fonte_conexao = '';
$profiles = [];

$profileSuffixes = $dbStrictMode ? [''] : ['', '_2', '_3'];
foreach ($profileSuffixes as $suffix) {
    $host = db_env_value('DB_HOST' . $suffix);
    $name = db_env_value('DB_NAME' . $suffix);
    $user = db_env_value('DB_USER' . $suffix);
    $pass = db_env_value('DB_PASS' . $suffix) ?? '';
    $port = (int)(db_env_value('DB_PORT' . $suffix) ?? '3306');
    $charset = db_env_value('DB_CHARSET' . $suffix) ?? 'utf8mb4';
    $label = db_env_value('DB_LABEL' . $suffix) ?? ('Profile' . ($suffix === '' ? '1' : str_replace('_', '', $suffix)));

    if ($host && $name && $user) {
        $profiles[] = [
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
            'port' => $port > 0 ? $port : 3306,
            'charset' => $charset,
            'label' => $label,
        ];
    }
}

if (!$profiles) {
    $autoLocal = db_try_local_profile();
    if ($autoLocal) {
        $profiles[] = $autoLocal;
    } else {
        $profiles = db_legacy_profiles();
    }
}

$conn = null;
$errors = [];

foreach ($profiles as $profile) {
    try {
        $dsn = "mysql:host={$profile['host']};port={$profile['port']};dbname={$profile['name']};charset={$profile['charset']}";
        $conn = new PDO($dsn, $profile['user'], $profile['pass']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $fonte_conexao = "{$profile['label']} ({$profile['name']})";
        break;
    } catch (Throwable $e) {
        $errors[] = "{$profile['label']}: " . $e->getMessage();
    }
}

if (!$conn) {
    if ($dbStrictMode) {
        error_log('[DB] Modo estrito ativo: sem fallback para profiles legados.');
        error_log('[DB] Falha em todos os profiles: ' . implode(' | ', $errors));
        header("Location: sem_conexao.html");
        exit("Falha na conexao com banco.");
    }

    // Segunda chance: se veio de .env e falhou, tenta perfis legados.
    $hasLegacyTried = false;
    foreach ($profiles as $p) {
        if (strpos((string)($p['label'] ?? ''), 'Legacy') === 0) {
            $hasLegacyTried = true;
            break;
        }
    }

    if (!$hasLegacyTried) {
        foreach (db_legacy_profiles() as $profile) {
            try {
                $dsn = "mysql:host={$profile['host']};port={$profile['port']};dbname={$profile['name']};charset={$profile['charset']}";
                $conn = new PDO($dsn, $profile['user'], $profile['pass']);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                $fonte_conexao = "{$profile['label']} ({$profile['name']})";
                break;
            } catch (Throwable $e) {
                $errors[] = "{$profile['label']}: " . $e->getMessage();
            }
        }
    }
}

if (!$conn) {
    error_log('[DB] Falha em todos os profiles: ' . implode(' | ', $errors));
    header("Location: sem_conexao.html");
    exit("Falha na conexao com banco.");
}

try {
    $userId = $_SESSION['id_usuario'] ?? null;
    $userName = $_SESSION['usuario_user'] ?? null;
    $userEmail = $_SESSION['email_user'] ?? null;
    $ipAddr = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $conn->prepare(
        "SET @app_user_id = :uid,
             @app_user_nome = :uname,
             @app_user_email = :uemail,
             @app_ip = :ip,
             @app_user_agent = :ua"
    );
    $stmt->bindValue(':uid', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':uname', $userName);
    $stmt->bindValue(':uemail', $userEmail);
    $stmt->bindValue(':ip', $ipAddr);
    $stmt->bindValue(':ua', $userAgent);
    $stmt->execute();
} catch (Throwable $e) {
    // Se falhar, os triggers ainda registram sem contexto de usuario.
}

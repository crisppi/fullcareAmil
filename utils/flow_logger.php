<?php

if (!defined('FLOW_LOG_DB_ENABLED')) {
    define('FLOW_LOG_DB_ENABLED', true);
}
if (!defined('FLOW_LOG_FILE_FALLBACK')) {
    define('FLOW_LOG_FILE_FALLBACK', true);
}
if (!defined('FLOW_LOG_DB_RETENTION_DAYS')) {
    define('FLOW_LOG_DB_RETENTION_DAYS', 90);
}
if (!defined('FLOW_LOG_PAGE_ACCESS_ENABLED')) {
    define('FLOW_LOG_PAGE_ACCESS_ENABLED', false);
}

if (!function_exists('flowLogDefaultFile')) {
    function flowLogDefaultFile(): string
    {
        return __DIR__ . '/../logs/flow_operacional.log';
    }
}

if (!function_exists('flowLogStringLimit')) {
    function flowLogStringLimit($value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = (string)$value;
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $limit, 'UTF-8');
        }

        return substr($text, 0, $limit);
    }
}

if (!function_exists('flowLogJsonEncode')) {
    function flowLogJsonEncode($value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            return $json;
        }

        return json_encode([
            '_json_error' => json_last_error_msg(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}

if (!function_exists('flowLogFileWrite')) {
    function flowLogFileWrite(string $file, array $line): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents(
            $file,
            flowLogJsonEncode($line) . PHP_EOL,
            FILE_APPEND
        );
    }
}

if (!function_exists('flowLogPdo')) {
    function flowLogPdo(): ?PDO
    {
        global $conn;
        return ($conn instanceof PDO) ? $conn : null;
    }
}

if (!function_exists('flowLogEnsureTable')) {
    function flowLogEnsureTable(PDO $pdo): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tb_flow_operacional_log (
                id_log BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                level VARCHAR(10) NOT NULL DEFAULT 'INFO',
                flow VARCHAR(80) NULL,
                stage VARCHAR(120) NULL,
                trace_id VARCHAR(64) NULL,
                user_id INT NULL,
                user_name VARCHAR(150) NULL,
                request_method VARCHAR(12) NULL,
                request_uri VARCHAR(500) NULL,
                ip VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                ctx_json LONGTEXT NULL,
                data_json LONGTEXT NULL,
                PRIMARY KEY (id_log),
                INDEX idx_flow_created (flow, created_at),
                INDEX idx_user_created (user_id, created_at),
                INDEX idx_trace (trace_id),
                INDEX idx_level_created (level, created_at),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $ensured = true;

        try {
            if (random_int(1, 100) === 1) {
                $days = max(7, (int)FLOW_LOG_DB_RETENTION_DAYS);
                $pdo->exec("DELETE FROM tb_flow_operacional_log WHERE created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 5000");
            }
        } catch (Throwable $e) {
            error_log('[FLOW_LOG_RETENTION] ' . $e->getMessage());
        }
    }
}

if (!function_exists('flowLogDbWrite')) {
    function flowLogDbWrite(PDO $pdo, array $line): bool
    {
        try {
            flowLogEnsureTable($pdo);

            $ctx = (isset($line['ctx']) && is_array($line['ctx'])) ? $line['ctx'] : [];
            $data = (isset($line['data']) && is_array($line['data'])) ? $line['data'] : [];

            $stmt = $pdo->prepare("
                INSERT INTO tb_flow_operacional_log (
                    created_at, level, flow, stage, trace_id, user_id, user_name,
                    request_method, request_uri, ip, user_agent, ctx_json, data_json
                ) VALUES (
                    :created_at, :level, :flow, :stage, :trace_id, :user_id, :user_name,
                    :request_method, :request_uri, :ip, :user_agent, :ctx_json, :data_json
                )
            ");

            $userId = $ctx['session_user_id'] ?? null;
            if ($userId !== null && $userId !== '' && !is_numeric($userId)) {
                $userId = null;
            }

            $stmt->execute([
                ':created_at' => date('Y-m-d H:i:s'),
                ':level' => flowLogStringLimit($line['level'] ?? 'INFO', 10),
                ':flow' => flowLogStringLimit($line['flow'] ?? null, 80),
                ':stage' => flowLogStringLimit($line['stage'] ?? null, 120),
                ':trace_id' => flowLogStringLimit($line['trace_id'] ?? null, 64),
                ':user_id' => $userId !== null && $userId !== '' ? (int)$userId : null,
                ':user_name' => flowLogStringLimit($ctx['session_user_name'] ?? null, 150),
                ':request_method' => flowLogStringLimit($ctx['request_method'] ?? null, 12),
                ':request_uri' => flowLogStringLimit($ctx['request_uri'] ?? null, 500),
                ':ip' => flowLogStringLimit($ctx['ip'] ?? null, 45),
                ':user_agent' => flowLogStringLimit($ctx['user_agent'] ?? null, 500),
                ':ctx_json' => flowLogJsonEncode($ctx),
                ':data_json' => flowLogJsonEncode($data),
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('[FLOW_LOG_DB] ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('flowLogStart')) {
    function flowLogStart(string $flow, array $context = [], ?string $logFile = null): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $sessionUserId = $_SESSION['id_usuario'] ?? null;
        $sessionUserName = $_SESSION['usuario_user'] ?? ($_SESSION['login_user'] ?? ($_SESSION['email_user'] ?? null));

        if (isset($context['trace_id']) && $context['trace_id']) {
            $traceId = (string)$context['trace_id'];
        } else {
            try {
                $traceId = bin2hex(random_bytes(8));
            } catch (Throwable $e) {
                $traceId = uniqid('trace_', true);
            }
        }
        $file = $logFile ?: flowLogDefaultFile();

        $base = [
            'flow' => $flow,
            'trace_id' => $traceId,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_user_id' => $sessionUserId,
            'session_user_name' => $sessionUserName,
            'ts' => date('c')
        ];

        $ctx = array_merge($base, $context);
        $ctx['_log_file'] = $file;

        flowLog($ctx, 'request.start', 'INFO');

        return $ctx;
    }
}

if (!function_exists('flowLog')) {
    function flowLog(array $ctx, string $stage, string $level = 'INFO', array $data = []): void
    {
        if (!FLOW_LOG_PAGE_ACCESS_ENABLED && (($ctx['flow'] ?? '') === 'page_access' || $stage === 'page.access')) {
            return;
        }

        $file = $ctx['_log_file'] ?? flowLogDefaultFile();

        $line = [
            'ts' => date('c'),
            'level' => strtoupper($level),
            'flow' => $ctx['flow'] ?? null,
            'trace_id' => $ctx['trace_id'] ?? null,
            'stage' => $stage,
            'ctx' => array_diff_key($ctx, array_flip(['_log_file'])),
            'data' => $data
        ];

        if (FLOW_LOG_DB_ENABLED) {
            $pdo = flowLogPdo();
            if ($pdo instanceof PDO && flowLogDbWrite($pdo, $line)) {
                return;
            }
        }

        if (FLOW_LOG_FILE_FALLBACK) {
            flowLogFileWrite($file, $line);
        }
    }
}

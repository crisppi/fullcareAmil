<?php

if (!function_exists('fullcare_mfa_env_value')) {
    function fullcare_mfa_env_value(string $key): string
    {
        $value = getenv($key);
        return $value === false ? '' : trim((string)$value);
    }
}

if (!function_exists('fullcare_mfa_master_key')) {
    function fullcare_mfa_master_key(): string
    {
        $key = fullcare_mfa_env_value('FULLCARE_MFA_SECRET_KEY');
        if ($key === '') {
            $key = fullcare_mfa_env_value('MOBILE_API_SECRET');
        }
        if ($key === '') {
            $key = 'fullcare-mfa-local-fallback-change-me';
        }

        return hash('sha256', $key, true);
    }
}

if (!function_exists('fullcare_mfa_base32_encode')) {
    function fullcare_mfa_base32_encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $encoded = '';

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        for ($i = 0, $len = strlen($bits); $i < $len; $i += 5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }
}

if (!function_exists('fullcare_mfa_base32_decode')) {
    function fullcare_mfa_base32_decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $value = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $value));
        $bits = '';

        for ($i = 0, $len = strlen($value); $i < $len; $i++) {
            $idx = strpos($alphabet, $value[$i]);
            if ($idx === false) {
                continue;
            }
            $bits .= str_pad(decbin($idx), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
            $decoded .= chr(bindec(substr($bits, $i, 8)));
        }

        return $decoded;
    }
}

if (!function_exists('fullcare_mfa_generate_secret')) {
    function fullcare_mfa_generate_secret(): string
    {
        return fullcare_mfa_base32_encode(random_bytes(20));
    }
}

if (!function_exists('fullcare_mfa_encrypt_secret')) {
    function fullcare_mfa_encrypt_secret(string $secret): string
    {
        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(16);
            $cipher = openssl_encrypt($secret, 'AES-256-CBC', fullcare_mfa_master_key(), OPENSSL_RAW_DATA, $iv);
            if ($cipher !== false) {
                return 'enc:v1:' . base64_encode($iv) . ':' . base64_encode($cipher);
            }
        }

        return 'plain:' . base64_encode($secret);
    }
}

if (!function_exists('fullcare_mfa_decrypt_secret')) {
    function fullcare_mfa_decrypt_secret(?string $stored): string
    {
        $stored = trim((string)$stored);
        if ($stored === '') {
            return '';
        }

        if (strpos($stored, 'enc:v1:') === 0 && function_exists('openssl_decrypt')) {
            $parts = explode(':', $stored, 4);
            if (count($parts) === 4) {
                $iv = base64_decode($parts[2], true);
                $cipher = base64_decode($parts[3], true);
                if ($iv !== false && $cipher !== false) {
                    $plain = openssl_decrypt($cipher, 'AES-256-CBC', fullcare_mfa_master_key(), OPENSSL_RAW_DATA, $iv);
                    return $plain === false ? '' : $plain;
                }
            }
        }

        if (strpos($stored, 'plain:') === 0) {
            $plain = base64_decode(substr($stored, 6), true);
            return $plain === false ? '' : $plain;
        }

        return $stored;
    }
}

if (!function_exists('fullcare_mfa_time_step')) {
    function fullcare_mfa_time_step(?int $timestamp = null): int
    {
        return (int)floor(($timestamp ?? time()) / 30);
    }
}

if (!function_exists('fullcare_mfa_totp_at_step')) {
    function fullcare_mfa_totp_at_step(string $secret, int $step): string
    {
        $key = fullcare_mfa_base32_decode($secret);
        if ($key === '') {
            return '';
        }

        $counter = pack('N*', 0, $step);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary =
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string)($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('fullcare_mfa_find_valid_step')) {
    function fullcare_mfa_find_valid_step(string $secret, string $code, int $window = 1): ?int
    {
        $code = preg_replace('/\D+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $currentStep = fullcare_mfa_time_step();
        for ($offset = -$window; $offset <= $window; $offset++) {
            $step = $currentStep + $offset;
            if ($step < 0) {
                continue;
            }
            if (hash_equals(fullcare_mfa_totp_at_step($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }
}

if (!function_exists('fullcare_mfa_user_enabled')) {
    function fullcare_mfa_user_enabled(array $user): bool
    {
        return in_array(strtolower((string)($user['mfa_enabled'] ?? '0')), ['1', 's', 'sim', 'true'], true)
            && trim((string)($user['mfa_secret'] ?? '')) !== '';
    }
}

if (!function_exists('fullcare_mfa_fetch_user')) {
    function fullcare_mfa_fetch_user(PDO $conn, int $userId): ?array
    {
        $stmt = $conn->prepare("
            SELECT *
              FROM tb_user
             WHERE id_usuario = :id
             LIMIT 1
        ");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($user) ? $user : null;
    }
}

if (!function_exists('fullcare_mfa_verify_totp_for_user')) {
    function fullcare_mfa_verify_totp_for_user(PDO $conn, array $user, string $code, bool $consume = true): bool
    {
        if (!fullcare_mfa_user_enabled($user)) {
            return false;
        }

        $secret = fullcare_mfa_decrypt_secret((string)($user['mfa_secret'] ?? ''));
        $step = fullcare_mfa_find_valid_step($secret, $code, 2);
        if ($step === null) {
            return false;
        }

        $lastStep = isset($user['mfa_last_used_step']) ? (int)$user['mfa_last_used_step'] : 0;
        if ($lastStep > 0 && $step <= $lastStep) {
            return false;
        }

        if ($consume) {
            $stmt = $conn->prepare("
                UPDATE tb_user
                   SET mfa_last_used_step = :step
                 WHERE id_usuario = :id
                 LIMIT 1
            ");
            $stmt->bindValue(':step', $step, PDO::PARAM_INT);
            $stmt->bindValue(':id', (int)$user['id_usuario'], PDO::PARAM_INT);
            $stmt->execute();
        }

        return true;
    }
}

if (!function_exists('fullcare_mfa_recovery_code_plain')) {
    function fullcare_mfa_recovery_code_plain(): string
    {
        return strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
    }
}

if (!function_exists('fullcare_mfa_hash_recovery_code')) {
    function fullcare_mfa_hash_recovery_code(string $code): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code));
        return hash_hmac('sha256', $normalized, fullcare_mfa_master_key());
    }
}

if (!function_exists('fullcare_mfa_generate_recovery_codes')) {
    function fullcare_mfa_generate_recovery_codes(PDO $conn, int $userId, int $count = 10): array
    {
        $conn->beginTransaction();
        try {
            $delete = $conn->prepare("DELETE FROM tb_user_mfa_recovery_code WHERE user_id = :id");
            $delete->bindValue(':id', $userId, PDO::PARAM_INT);
            $delete->execute();

            $codes = [];
            $insert = $conn->prepare("
                INSERT INTO tb_user_mfa_recovery_code (user_id, code_hash, created_at)
                VALUES (:user_id, :code_hash, NOW())
            ");
            for ($i = 0; $i < $count; $i++) {
                $code = fullcare_mfa_recovery_code_plain();
                $codes[] = $code;
                $insert->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $insert->bindValue(':code_hash', fullcare_mfa_hash_recovery_code($code), PDO::PARAM_STR);
                $insert->execute();
            }

            $conn->commit();
            return $codes;
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('fullcare_mfa_consume_recovery_code')) {
    function fullcare_mfa_consume_recovery_code(PDO $conn, int $userId, string $code): bool
    {
        $hash = fullcare_mfa_hash_recovery_code($code);
        $stmt = $conn->prepare("
            SELECT id
              FROM tb_user_mfa_recovery_code
             WHERE user_id = :user_id
               AND code_hash = :code_hash
               AND used_at IS NULL
             LIMIT 1
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':code_hash', $hash, PDO::PARAM_STR);
        $stmt->execute();
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id <= 0) {
            return false;
        }

        $update = $conn->prepare("
            UPDATE tb_user_mfa_recovery_code
               SET used_at = NOW()
             WHERE id = :id
             LIMIT 1
        ");
        $update->bindValue(':id', $id, PDO::PARAM_INT);
        $update->execute();
        return true;
    }
}

if (!function_exists('fullcare_mfa_verify_code_for_user')) {
    function fullcare_mfa_verify_code_for_user(PDO $conn, array $user, string $code, bool $consume = true): bool
    {
        if (fullcare_mfa_verify_totp_for_user($conn, $user, $code, $consume)) {
            return true;
        }

        if (!$consume) {
            $hash = fullcare_mfa_hash_recovery_code($code);
            $stmt = $conn->prepare("
                SELECT COUNT(*)
                  FROM tb_user_mfa_recovery_code
                 WHERE user_id = :user_id
                   AND code_hash = :code_hash
                   AND used_at IS NULL
            ");
            $stmt->bindValue(':user_id', (int)$user['id_usuario'], PDO::PARAM_INT);
            $stmt->bindValue(':code_hash', $hash, PDO::PARAM_STR);
            $stmt->execute();

            return (int)$stmt->fetchColumn() > 0;
        }

        return fullcare_mfa_consume_recovery_code($conn, (int)$user['id_usuario'], $code);
    }
}

if (!function_exists('fullcare_mfa_provisioning_uri')) {
    function fullcare_mfa_provisioning_uri(string $issuer, string $account, string $secret): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        return 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }
}

if (!function_exists('fullcare_mfa_qr_svg_data_uri')) {
    function fullcare_mfa_qr_svg_data_uri(string $text): string
    {
        $qrFile = __DIR__ . '/../vendor/tecnickcom/tcpdf/include/barcodes/qrcode.php';
        if (!is_readable($qrFile)) {
            return '';
        }
        require_once $qrFile;
        if (!class_exists('QRcode')) {
            return '';
        }

        try {
            $qr = new QRcode($text, 'M');
            $barcode = $qr->getBarcodeArray();
            $matrix = $barcode['bcode'] ?? [];
            if (!is_array($matrix) || !$matrix) {
                return '';
            }
            $size = count($matrix);
            $scale = 6;
            $quiet = 4;
            $svgSize = ($size + ($quiet * 2)) * $scale;
            $rects = '';
            foreach ($matrix as $y => $row) {
                foreach ($row as $x => $cell) {
                    if ((int)$cell === 1) {
                        $rects .= '<rect x="' . (($x + $quiet) * $scale) . '" y="' . (($y + $quiet) * $scale) . '" width="' . $scale . '" height="' . $scale . '"/>';
                    }
                }
            }
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $svgSize . '" height="' . $svgSize . '" viewBox="0 0 ' . $svgSize . ' ' . $svgSize . '"><rect width="100%" height="100%" fill="#fff"/><g fill="#111827">' . $rects . '</g></svg>';
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (Throwable $e) {
            error_log('[MFA][QR] ' . $e->getMessage());
            return '';
        }
    }
}

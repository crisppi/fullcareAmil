<?php

if (!function_exists('fullcare_prorrog_alta_has_hora_column')) {
    function fullcare_prorrog_alta_has_hora_column(PDO $conn): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $stmt = $conn->query("SHOW COLUMNS FROM tb_alta LIKE 'hora_alta_alt'");
            $cache = $stmt && $stmt->fetch() ? true : false;
        } catch (Throwable $e) {
            $cache = false;
        }

        return $cache;
    }
}

if (!function_exists('fullcare_prorrog_alta_payload_from_post')) {
    function fullcare_prorrog_alta_payload_from_post(array $source, callable $normalizeDateTimeInput, ?int $fallbackUserId = null, ?string $fallbackUserEmail = null): ?array
    {
        $flag = trim((string)($source['prorrog_gerar_alta'] ?? 'n'));
        if (!in_array($flag, ['s', '1', 'sim', 'true', 'on'], true)) {
            return null;
        }

        $rawDate = $source['prorrog_data_alta_alt'] ?? null;
        $normalized = $normalizeDateTimeInput($rawDate);
        if (!$normalized) {
            return null;
        }

        $tipoAlta = trim((string)($source['prorrog_tipo_alta_alt'] ?? ''));
        if ($tipoAlta === '') {
            return null;
        }

        $fkUsuarioAlt = isset($source['prorrog_fk_usuario_alt']) ? (int)$source['prorrog_fk_usuario_alt'] : 0;
        if ($fkUsuarioAlt <= 0) {
            $fkUsuarioAlt = $fallbackUserId && $fallbackUserId > 0 ? $fallbackUserId : null;
        }

        return [
            'data_alta_alt' => substr($normalized, 0, 10),
            'hora_alta_alt' => substr($normalized, 11, 8),
            'tipo_alta_alt' => $tipoAlta,
            'fk_usuario_alt' => $fkUsuarioAlt,
            'usuario_alt' => trim((string)($source['prorrog_usuario_alt'] ?? ($fallbackUserEmail ?? 'sistema'))),
            'data_create_alt' => date('Y-m-d'),
        ];
    }
}

if (!function_exists('fullcare_upsert_prorrog_alta')) {
    function fullcare_upsert_prorrog_alta(PDO $conn, int $internacaoId, array $payload): void
    {
        if ($internacaoId <= 0) {
            return;
        }

        $hasHora = fullcare_prorrog_alta_has_hora_column($conn);

        $stmtExisting = $conn->prepare("SELECT id_alta FROM tb_alta WHERE fk_id_int_alt = :id ORDER BY id_alta DESC LIMIT 1");
        $stmtExisting->bindValue(':id', $internacaoId, PDO::PARAM_INT);
        $stmtExisting->execute();
        $existingId = (int)($stmtExisting->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $sql = "UPDATE tb_alta
                       SET tipo_alta_alt = :tipo_alta_alt,
                           internado_alt = 'n',
                           usuario_alt = :usuario_alt,
                           data_alta_alt = :data_alta_alt,
                           fk_usuario_alt = :fk_usuario_alt";
            if ($hasHora) {
                $sql .= ",
                           hora_alta_alt = :hora_alta_alt";
            }
            $sql .= "
                     WHERE id_alta = :id_alta
                     LIMIT 1";

            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':tipo_alta_alt', $payload['tipo_alta_alt'], PDO::PARAM_STR);
            $stmt->bindValue(':usuario_alt', $payload['usuario_alt'], PDO::PARAM_STR);
            $stmt->bindValue(':data_alta_alt', $payload['data_alta_alt'], PDO::PARAM_STR);
            if ($payload['fk_usuario_alt'] !== null) {
                $stmt->bindValue(':fk_usuario_alt', (int)$payload['fk_usuario_alt'], PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':fk_usuario_alt', null, PDO::PARAM_NULL);
            }
            if ($hasHora) {
                $stmt->bindValue(':hora_alta_alt', $payload['hora_alta_alt'], PDO::PARAM_STR);
            }
            $stmt->bindValue(':id_alta', $existingId, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $sql = "INSERT INTO tb_alta (
                        fk_id_int_alt,
                        tipo_alta_alt,
                        internado_alt,
                        usuario_alt,
                        data_create_alt,
                        data_alta_alt" . ($hasHora ? ", hora_alta_alt" : "") . ",
                        fk_usuario_alt
                    ) VALUES (
                        :fk_id_int_alt,
                        :tipo_alta_alt,
                        'n',
                        :usuario_alt,
                        :data_create_alt,
                        :data_alta_alt" . ($hasHora ? ", :hora_alta_alt" : "") . ",
                        :fk_usuario_alt
                    )";

            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':fk_id_int_alt', $internacaoId, PDO::PARAM_INT);
            $stmt->bindValue(':tipo_alta_alt', $payload['tipo_alta_alt'], PDO::PARAM_STR);
            $stmt->bindValue(':usuario_alt', $payload['usuario_alt'], PDO::PARAM_STR);
            $stmt->bindValue(':data_create_alt', $payload['data_create_alt'], PDO::PARAM_STR);
            $stmt->bindValue(':data_alta_alt', $payload['data_alta_alt'], PDO::PARAM_STR);
            if ($hasHora) {
                $stmt->bindValue(':hora_alta_alt', $payload['hora_alta_alt'], PDO::PARAM_STR);
            }
            if ($payload['fk_usuario_alt'] !== null) {
                $stmt->bindValue(':fk_usuario_alt', (int)$payload['fk_usuario_alt'], PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':fk_usuario_alt', null, PDO::PARAM_NULL);
            }
            $stmt->execute();
        }

        $stmtIntern = $conn->prepare("UPDATE tb_internacao SET internado_int = 'n' WHERE id_internacao = :id LIMIT 1");
        $stmtIntern->bindValue(':id', $internacaoId, PDO::PARAM_INT);
        $stmtIntern->execute();
    }
}

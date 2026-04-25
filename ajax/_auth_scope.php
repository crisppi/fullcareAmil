<?php

function ajax_require_active_session(): void
{
    if (empty($_SESSION['id_usuario']) || strtolower((string)($_SESSION['ativo'] ?? '')) !== 's') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'nao_autenticado']);
        exit;
    }
}

function ajax_normalize_role(?string $value): string
{
    $txt = mb_strtolower(trim((string)$value), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $ascii !== false ? $ascii : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
}

function ajax_user_context(PDO $conn): array
{
    $userId = (int)($_SESSION['id_usuario'] ?? 0);
    $nivel = (int)($_SESSION['nivel'] ?? 99);
    $cargo = (string)($_SESSION['cargo'] ?? '');
    $cargoNorm = ajax_normalize_role($cargo);

    $isDiretoria = in_array($cargoNorm, ['diretoria', 'diretor', 'board'], true)
        || (strpos($cargoNorm, 'diretor') !== false)
        || (strpos($cargoNorm, 'diretoria') !== false)
        || ($nivel === -1);

    // Admin de sistema (técnico) != administrativo operacional.
    $isSystemAdmin = in_array($cargoNorm, [
        'adminsistema',
        'administradordesistema',
        'superadmin',
        'root',
        'tiadmin',
    ], true);

    $isSeguradoraRole = (strpos($cargoNorm, 'seguradora') !== false);
    $isPlanoSaudeRole = (strpos($cargoNorm, 'planosaude') !== false)
        || in_array($cargoNorm, ['gestorplanosaude'], true);
    $seguradoraId = (int)($_SESSION['fk_seguradora_user'] ?? 0);
    if ($isSeguradoraRole && $seguradoraId <= 0 && $userId > 0) {
        try {
            $stmt = $conn->prepare("SELECT fk_seguradora_user FROM tb_user WHERE id_usuario = :id LIMIT 1");
            $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $seguradoraId = (int)($stmt->fetchColumn() ?: 0);
            if ($seguradoraId > 0) {
                $_SESSION['fk_seguradora_user'] = $seguradoraId;
            }
        } catch (Throwable $e) {
            $seguradoraId = 0;
        }
    }

    return [
        'user_id' => $userId,
        'nivel' => $nivel,
        'cargo_norm' => $cargoNorm,
        'is_diretoria' => $isDiretoria,
        'is_system_admin' => $isSystemAdmin,
        'is_seguradora' => ($isSeguradoraRole || $isPlanoSaudeRole),
        'seguradora_id' => $seguradoraId,
    ];
}

function ajax_starts_with_any(string $value, array $prefixes): bool
{
    foreach ($prefixes as $prefix) {
        if ($prefix !== '' && strpos($value, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

function ajax_scope_mode(array $ctx): string
{
    if (!empty($ctx['is_diretoria']) || !empty($ctx['is_system_admin'])) {
        return 'full';
    }
    if (!empty($ctx['is_seguradora'])) {
        return 'seguradora';
    }

    $cargo = (string)($ctx['cargo_norm'] ?? '');

    // Cargos com escopo por hospital (menor privilégio por padrão assistencial)
    $hospitalScopedPrefixes = [
        'medico',
        'enfermeiro',
        'enfermagem',
        'secretaria',
        'auditor',
        'medaudit',
        'enfaudit',
        'administrativo',
        'fisioterapeuta',
        'nutricionista',
        'assistentesocial',
        'psicologo',
    ];

    if (ajax_starts_with_any($cargo, $hospitalScopedPrefixes)) {
        return 'hospital';
    }

    // Fallback seguro para qualquer cargo novo: escopo por hospital.
    return 'hospital';
}

function ajax_scope_clause_for_internacao(array $ctx, string $alias, array &$params, string $prefix = 'scp'): string
{
    $mode = ajax_scope_mode($ctx);
    if ($mode === 'full') {
        return '';
    }

    if ($mode === 'seguradora') {
        $segId = (int)($ctx['seguradora_id'] ?? 0);
        if ($segId <= 0) {
            return ' AND 1=0 ';
        }
        $k = ':' . $prefix . '_seg';
        $params[$k] = $segId;
        return " AND EXISTS (
            SELECT 1
              FROM tb_paciente pa_scope
             WHERE pa_scope.id_paciente = {$alias}.fk_paciente_int
               AND pa_scope.fk_seguradora_pac = {$k}
        ) ";
    }

    $uid = (int)($ctx['user_id'] ?? 0);
    if ($uid <= 0) {
        return ' AND 1=0 ';
    }
    $k = ':' . $prefix . '_uid';
    $params[$k] = $uid;
    return " AND EXISTS (
        SELECT 1
          FROM tb_hospitalUser hu_scope
         WHERE hu_scope.fk_hospital_user = {$alias}.fk_hospital_int
           AND hu_scope.fk_usuario_hosp = {$k}
    ) ";
}

function ajax_scope_clause_for_paciente(array $ctx, string $alias, array &$params, string $prefix = 'scp'): string
{
    $mode = ajax_scope_mode($ctx);
    if ($mode === 'full') {
        return '';
    }

    if ($mode === 'seguradora') {
        $segId = (int)($ctx['seguradora_id'] ?? 0);
        if ($segId <= 0) {
            return ' AND 1=0 ';
        }
        $k = ':' . $prefix . '_seg';
        $params[$k] = $segId;
        return " AND {$alias}.fk_seguradora_pac = {$k} ";
    }

    $uid = (int)($ctx['user_id'] ?? 0);
    if ($uid <= 0) {
        return ' AND 1=0 ';
    }
    $k = ':' . $prefix . '_uid';
    $kOwn = ':' . $prefix . '_own_uid';
    $params[$k] = $uid;
    $params[$kOwn] = $uid;
    return " AND (
        EXISTS (
        SELECT 1
          FROM tb_internacao ac_scope
          JOIN tb_hospitalUser hu_scope ON hu_scope.fk_hospital_user = ac_scope.fk_hospital_int
         WHERE ac_scope.fk_paciente_int = {$alias}.id_paciente
           AND hu_scope.fk_usuario_hosp = {$k}
        )
        OR {$alias}.fk_usuario_pac = {$kOwn}
    ) ";
}

function ajax_bind_params(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
            continue;
        }
        if (is_bool($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_BOOL);
            continue;
        }
        if ($value === null) {
            $stmt->bindValue($key, null, PDO::PARAM_NULL);
            continue;
        }
        $stmt->bindValue($key, (string)$value, PDO::PARAM_STR);
    }
}

function ajax_user_scope_literal(array $ctx, string $alias = 'ac'): string
{
    $mode = ajax_scope_mode($ctx);
    if ($mode === 'full') {
        return '';
    }

    if ($mode === 'seguradora') {
        $segId = (int)($ctx['seguradora_id'] ?? 0);
        if ($segId <= 0) {
            return ' AND 1=0 ';
        }
        return " AND EXISTS (
            SELECT 1
              FROM tb_paciente pa_scope
             WHERE pa_scope.id_paciente = {$alias}.fk_paciente_int
               AND pa_scope.fk_seguradora_pac = {$segId}
        ) ";
    }

    $uid = (int)($ctx['user_id'] ?? 0);
    if ($uid <= 0) {
        return ' AND 1=0 ';
    }
    return " AND EXISTS (
        SELECT 1
          FROM tb_hospitalUser hu_scope
         WHERE hu_scope.fk_hospital_user = {$alias}.fk_hospital_int
           AND hu_scope.fk_usuario_hosp = {$uid}
    ) ";
}

function ajax_assert_patient_access(PDO $conn, array $ctx, int $patientId): bool
{
    if ($patientId <= 0) {
        return false;
    }

    $params = [':id' => $patientId];
    $scope = ajax_scope_clause_for_paciente($ctx, 'pa', $params, 'guardp');
    $sql = "SELECT pa.id_paciente
              FROM tb_paciente pa
             WHERE pa.id_paciente = :id {$scope}
             LIMIT 1";
    $stmt = $conn->prepare($sql);
    ajax_bind_params($stmt, $params);
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
}

function ajax_assert_hospital_access(PDO $conn, array $ctx, int $hospitalId): bool
{
    if ($hospitalId <= 0) {
        return false;
    }

    $mode = ajax_scope_mode($ctx);
    if ($mode === 'full') {
        return true;
    }

    if ($mode === 'hospital') {
        $uid = (int)($ctx['user_id'] ?? 0);
        if ($uid <= 0) {
            return false;
        }
        $stmt = $conn->prepare("SELECT 1
                                  FROM tb_hospitalUser hu
                                 WHERE hu.fk_hospital_user = :hid
                                   AND hu.fk_usuario_hosp = :uid
                                 LIMIT 1");
        $stmt->bindValue(':hid', $hospitalId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }

    $segId = (int)($ctx['seguradora_id'] ?? 0);
    if ($segId <= 0) {
        return false;
    }
    $stmt = $conn->prepare("SELECT 1
                              FROM tb_internacao ac
                              JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
                             WHERE ac.fk_hospital_int = :hid
                               AND pa.fk_seguradora_pac = :seg
                             LIMIT 1");
    $stmt->bindValue(':hid', $hospitalId, PDO::PARAM_INT);
    $stmt->bindValue(':seg', $segId, PDO::PARAM_INT);
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
}

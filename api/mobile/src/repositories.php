<?php
declare(strict_types=1);

function mobileUserScopeWhere(array $authUser, string $patientAlias = 'p'): array
{
    return ['', []];
}

function mobileBindAll(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, (string)$value, PDO::PARAM_STR);
        }
    }
}

function mobileValidateClinicalTextSecurity(array $fields): void
{
    if (!class_exists('TextSecurityService')) {
        return;
    }

    $security = new TextSecurityService();
    foreach ($fields as $fieldName => $value) {
        $value = trim((string)$value);
        if ($value === '') {
            continue;
        }

        $assessment = $security->assess($value, $fieldName, true);
        if ($security->shouldBlock($assessment)) {
            throw new InvalidArgumentException('Conteúdo suspeito no relatório. Revise o texto antes de salvar.');
        }
    }
}

function mobileSearchPatients(PDO $conn, array $authUser, string $query): array
{
    [$scopeSql, $scopeParams] = mobileUserScopeWhere($authUser, 'p');
    $params = $scopeParams;
    $where = " WHERE COALESCE(p.deletado_pac, 'n') <> 's' ";

    if ($query !== '') {
        $where .= " AND (
            p.nome_pac LIKE :query_name
            OR p.cpf_pac LIKE :query_cpf
            OR p.matricula_pac LIKE :query_card
            OR p.num_atendimento_pac LIKE :query_attendance
        ) ";
        $like = '%' . $query . '%';
        $params[':query_name'] = $like;
        $params[':query_cpf'] = $like;
        $params[':query_card'] = $like;
        $params[':query_attendance'] = $like;
    }

    $sql = "
        SELECT
            p.id_paciente AS id,
            p.nome_pac AS name,
            p.cpf_pac AS cpf,
            p.matricula_pac AS card_number,
            p.num_atendimento_pac AS attendance_number,
            p.data_nasc_pac AS birth_date,
            p.telefone01_pac AS phone,
            s.seguradora_seg AS insurance_name
        FROM tb_paciente p
        LEFT JOIN tb_seguradora s ON s.id_seguradora = p.fk_seguradora_pac
        {$where}
        {$scopeSql}
        ORDER BY p.nome_pac ASC
        LIMIT 30
    ";

    $stmt = $conn->prepare($sql);
    mobileBindAll($stmt, $params);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mobileListAdmissions(PDO $conn, array $authUser, string $query): array
{
    [$scopeSql, $scopeParams] = mobileUserScopeWhere($authUser, 'p');
    $params = $scopeParams;
    $where = " WHERE COALESCE(i.internado_int, 's') = 's' ";

    if ($query !== '') {
        $where .= " AND (
            p.nome_pac LIKE :query_patient
            OR h.nome_hosp LIKE :query_hospital
            OR i.senha_int LIKE :query_password
            OR cid.cat LIKE :query_cid
        ) ";
        $like = '%' . $query . '%';
        $params[':query_patient'] = $like;
        $params[':query_hospital'] = $like;
        $params[':query_password'] = $like;
        $params[':query_cid'] = $like;
    }

    $sql = "
        SELECT
            i.id_internacao AS id,
            i.data_intern_int AS admission_date,
            i.data_visita_int AS visit_date,
            i.senha_int AS authorization_code,
            i.rel_int AS evolution_report,
            i.internado_int AS active_flag,
            i.tipo_admissao_int AS admission_type,
            i.modo_internacao_int AS admission_mode,
            i.acomodacao_int AS accommodation,
            i.grupo_patologia_int AS pathology_group,
            p.id_paciente AS patient_id,
            p.nome_pac AS patient_name,
            s.seguradora_seg AS insurance_name,
            h.nome_hosp AS hospital_name,
            cid.cat AS cid_code,
            cid.descricao AS cid_description
        FROM tb_internacao i
        LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
        LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
        LEFT JOIN tb_seguradora s ON s.id_seguradora = p.fk_seguradora_pac
        LEFT JOIN tb_cid cid ON cid.id_cid = i.fk_cid_int
        {$where}
        {$scopeSql}
        ORDER BY i.id_internacao DESC
        LIMIT 50
    ";

    $stmt = $conn->prepare($sql);
    mobileBindAll($stmt, $params);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mobileFindAdmission(PDO $conn, array $authUser, int $admissionId): ?array
{
    if ($admissionId <= 0) {
        return null;
    }

    [$scopeSql, $scopeParams] = mobileUserScopeWhere($authUser, 'p');
    $sql = "
        SELECT
            i.id_internacao AS id,
            i.data_intern_int AS admission_date,
            i.data_visita_int AS visit_date,
            i.senha_int AS authorization_code,
            i.internado_int AS active_flag,
            i.tipo_admissao_int AS admission_type,
            i.modo_internacao_int AS admission_mode,
            i.acomodacao_int AS accommodation,
            i.grupo_patologia_int AS pathology_group,
            i.especialidade_int AS specialty,
            i.origem_int AS origin,
            al.data_alta_alt AS discharge_date,
            al.tipo_alta_alt AS discharge_type,
            p.id_paciente AS patient_id,
            p.nome_pac AS patient_name,
            p.cpf_pac AS patient_cpf,
            p.matricula_pac AS patient_card_number,
            s.seguradora_seg AS insurance_name,
            h.nome_hosp AS hospital_name,
            cid.cat AS cid_code,
            cid.descricao AS cid_description
        FROM tb_internacao i
        LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
        LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
        LEFT JOIN tb_seguradora s ON s.id_seguradora = p.fk_seguradora_pac
        LEFT JOIN tb_cid cid ON cid.id_cid = i.fk_cid_int
        LEFT JOIN (
            SELECT a1.*
            FROM tb_alta a1
            INNER JOIN (
                SELECT fk_id_int_alt, MAX(id_alta) AS last_id
                FROM tb_alta
                GROUP BY fk_id_int_alt
            ) a2 ON a2.last_id = a1.id_alta
        ) al ON al.fk_id_int_alt = i.id_internacao
        WHERE i.id_internacao = :id
        {$scopeSql}
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $params = array_merge([':id' => $admissionId], $scopeParams);
    mobileBindAll($stmt, $params);
    $stmt->execute();

    $admission = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($admission) ? $admission : null;
}

function mobileListAdmissionTuss(PDO $conn, int $admissionId): array
{
    $stmt = $conn->prepare("
        SELECT
            t.id_tuss AS id,
            t.tuss_solicitado AS code,
            a.terminologia_tuss AS description,
            t.tuss_liberado_sn AS released_flag,
            t.qtd_tuss_solicitado AS requested_quantity,
            t.qtd_tuss_liberado AS released_quantity,
            COALESCE(t.data_realizacao_tuss, DATE(t.data_create_tuss)) AS performed_at,
            COALESCE(t.data_realizacao_tuss, DATE(t.data_create_tuss)) AS released_at,
            u.usuario_user AS released_by
        FROM tb_tuss t
        LEFT JOIN tb_tuss_ans a ON a.cod_tuss = t.tuss_solicitado
        LEFT JOIN tb_user u ON u.id_usuario = t.fk_usuario_tuss
        WHERE t.fk_int_tuss = :id
        ORDER BY t.id_tuss DESC
    ");
    $stmt->bindValue(':id', $admissionId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mobileFindAdmissionTussById(PDO $conn, int $tussId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            t.id_tuss AS id,
            t.tuss_solicitado AS code,
            a.terminologia_tuss AS description,
            t.tuss_liberado_sn AS released_flag,
            t.qtd_tuss_solicitado AS requested_quantity,
            t.qtd_tuss_liberado AS released_quantity,
            COALESCE(t.data_realizacao_tuss, DATE(t.data_create_tuss)) AS performed_at,
            COALESCE(t.data_realizacao_tuss, DATE(t.data_create_tuss)) AS released_at,
            u.usuario_user AS released_by
        FROM tb_tuss t
        LEFT JOIN tb_tuss_ans a ON a.cod_tuss = t.tuss_solicitado
        LEFT JOIN tb_user u ON u.id_usuario = t.fk_usuario_tuss
        WHERE t.id_tuss = :id
        LIMIT 1
    ");
    $stmt->bindValue(':id', $tussId, PDO::PARAM_INT);
    $stmt->execute();

    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($item) ? $item : null;
}

function mobileSearchTussCatalog(PDO $conn, string $query): array
{
    $query = trim($query);
    if ($query === '') {
        $stmt = $conn->query("
            SELECT
                cod_tuss AS code,
                terminologia_tuss AS description
            FROM tb_tuss_ans
            WHERE cod_tuss IS NOT NULL
              AND cod_tuss <> ''
            ORDER BY cod_tuss ASC
            LIMIT 30
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $stmt = $conn->prepare("
        SELECT
            cod_tuss AS code,
            terminologia_tuss AS description
        FROM tb_tuss_ans
        WHERE cod_tuss LIKE :query_code
           OR terminologia_tuss LIKE :query_description
        ORDER BY cod_tuss ASC
        LIMIT 30
    ");
    $like = '%' . $query . '%';
    $stmt->bindValue(':query_code', $like, PDO::PARAM_STR);
    $stmt->bindValue(':query_description', $like, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mobileCreateAdmissionTuss(PDO $conn, array $authUser, array $input): array
{
    $stmt = $conn->prepare("
        INSERT INTO tb_tuss (
            fk_usuario_tuss,
            fk_int_tuss,
            data_create_tuss,
            tuss_solicitado,
            tuss_liberado_sn,
            qtd_tuss_solicitado,
            qtd_tuss_liberado,
            data_realizacao_tuss
        ) VALUES (
            :user_id,
            :admission_id,
            NOW(),
            :code,
            :released_flag,
            :requested_quantity,
            :released_quantity,
            :performed_at
        )
    ");
    $stmt->bindValue(':user_id', (int)$authUser['id'], PDO::PARAM_INT);
    $stmt->bindValue(':admission_id', (int)($input['admission_id'] ?? 0), PDO::PARAM_INT);
    $stmt->bindValue(':code', trim((string)($input['code'] ?? '')), PDO::PARAM_STR);
    $stmt->bindValue(':released_flag', trim((string)($input['released_flag'] ?? 'n')), PDO::PARAM_STR);
    $stmt->bindValue(':requested_quantity', (int)($input['requested_quantity'] ?? 1), PDO::PARAM_INT);
    $stmt->bindValue(':released_quantity', (int)($input['released_quantity'] ?? 0), PDO::PARAM_INT);
    $performedAt = trim((string)($input['performed_at'] ?? ''));
    $stmt->bindValue(':performed_at', $performedAt !== '' ? $performedAt : null, $performedAt !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->execute();

    $id = (int)$conn->lastInsertId();
    $item = mobileFindAdmissionTussById($conn, $id);
    if ($item !== null) {
        return $item;
    }

    return ['id' => $id];
}

function mobileListAdmissionExtensions(PDO $conn, int $admissionId): array
{
    $stmt = $conn->prepare("
        SELECT
            id_prorrogacao AS id,
            acomod1_pror AS accommodation,
            isol_1_pror AS isolation_flag,
            prorrog1_ini_pror AS start_date,
            prorrog1_fim_pror AS end_date,
            diarias_1 AS days
        FROM tb_prorrogacao
        WHERE fk_internacao_pror = :id
        ORDER BY id_prorrogacao DESC
    ");
    $stmt->bindValue(':id', $admissionId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mobileListAdmissionEvolutions(PDO $conn, int $admissionId): array
{
    $stmt = $conn->prepare("
        SELECT
            v.id_visita AS id,
            v.data_visita_vis AS visited_at,
            v.rel_visita_vis AS report,
            v.usuario_create AS created_by,
            v.visita_no_vis AS visit_number
        FROM tb_visita v
        WHERE v.fk_internacao_vis = :id
          AND (v.retificado IS NULL OR v.retificado = 0)
          AND COALESCE(v.rel_visita_vis, '') <> ''
        ORDER BY v.data_visita_vis DESC, v.id_visita DESC
    ");
    $stmt->bindValue(':id', $admissionId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mobileCreateAdmissionExtension(PDO $conn, array $authUser, array $input): array
{
    $startDate = trim((string)($input['start_date'] ?? ''));
    $days = (int)($input['days'] ?? 1);
    $endDate = trim((string)($input['end_date'] ?? ''));

    if ($startDate !== '' && $endDate === '' && $days > 0) {
        $endDate = date('Y-m-d', strtotime($startDate . ' +' . max(0, $days - 1) . ' days'));
    }

    $stmt = $conn->prepare("
        INSERT INTO tb_prorrogacao (
            fk_internacao_pror,
            fk_usuario_pror,
            acomod1_pror,
            isol_1_pror,
            prorrog1_ini_pror,
            prorrog1_fim_pror,
            diarias_1
        ) VALUES (
            :admission_id,
            :user_id,
            :accommodation,
            :isolation_flag,
            :start_date,
            :end_date,
            :days
        )
    ");
    $stmt->bindValue(':admission_id', (int)($input['admission_id'] ?? 0), PDO::PARAM_INT);
    $stmt->bindValue(':user_id', (int)$authUser['id'], PDO::PARAM_INT);
    $stmt->bindValue(':accommodation', trim((string)($input['accommodation'] ?? '')), PDO::PARAM_STR);
    $stmt->bindValue(':isolation_flag', trim((string)($input['isolation_flag'] ?? 'n')), PDO::PARAM_STR);
    $stmt->bindValue(':start_date', $startDate !== '' ? $startDate : null, $startDate !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':end_date', $endDate !== '' ? $endDate : null, $endDate !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();

    $id = (int)$conn->lastInsertId();
    $items = mobileListAdmissionExtensions($conn, (int)($input['admission_id'] ?? 0));
    foreach ($items as $item) {
        if ((int)$item['id'] === $id) {
            return $item;
        }
    }

    return ['id' => $id];
}

function mobileCreateAdmissionDischarge(PDO $conn, array $authUser, array $input): array
{
    $date = trim((string)($input['date'] ?? ''));
    $time = trim((string)($input['time'] ?? ''));
    $type = trim((string)($input['type'] ?? ''));

    $stmt = $conn->prepare("
        INSERT INTO tb_alta (
            fk_id_int_alt,
            tipo_alta_alt,
            internado_alt,
            usuario_alt,
            data_create_alt,
            data_alta_alt,
            hora_alta_alt,
            fk_usuario_alt
        ) VALUES (
            :admission_id,
            :type,
            'n',
            :user_name,
            NOW(),
            :date,
            :time,
            :user_id
        )
    ");
    $stmt->bindValue(':admission_id', (int)($input['admission_id'] ?? 0), PDO::PARAM_INT);
    $stmt->bindValue(':type', $type, PDO::PARAM_STR);
    $stmt->bindValue(':user_name', (string)$authUser['name'], PDO::PARAM_STR);
    $stmt->bindValue(':date', $date !== '' ? $date : null, $date !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':time', $time !== '' ? $time : null, $time !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':user_id', (int)$authUser['id'], PDO::PARAM_INT);
    $stmt->execute();

    $update = $conn->prepare("
        UPDATE tb_internacao
        SET internado_int = 'n'
        WHERE id_internacao = :id
        LIMIT 1
    ");
    $update->bindValue(':id', (int)($input['admission_id'] ?? 0), PDO::PARAM_INT);
    $update->execute();

    return [
        'id' => (int)$conn->lastInsertId(),
        'date' => $date,
        'time' => $time,
        'type' => $type,
    ];
}

function mobileCreateAdmissionEvolution(PDO $conn, array $authUser, array $input): array
{
    $admissionId = (int)($input['admission_id'] ?? 0);
    $report = trim((string)($input['report'] ?? ''));

    mobileValidateClinicalTextSecurity([
        'rel_int' => $report,
        'rel_visita_vis' => $report,
    ]);

    $lastVisitStmt = $conn->prepare("
        SELECT COALESCE(MAX(visita_no_vis), 0) AS max_visit
        FROM tb_visita
        WHERE fk_internacao_vis = :admission_id
    ");
    $lastVisitStmt->bindValue(':admission_id', $admissionId, PDO::PARAM_INT);
    $lastVisitStmt->execute();
    $nextVisitNumber = ((int)$lastVisitStmt->fetchColumn()) + 1;

    $updateStmt = $conn->prepare("
        UPDATE tb_internacao
        SET
            rel_int = :report,
            fk_usuario_int = :user_id
        WHERE id_internacao = :admission_id
        LIMIT 1
    ");
    $updateStmt->bindValue(':report', $report, PDO::PARAM_STR);
    $updateStmt->bindValue(':user_id', (int)$authUser['id'], PDO::PARAM_INT);
    $updateStmt->bindValue(':admission_id', $admissionId, PDO::PARAM_INT);
    $updateStmt->execute();

    $insertStmt = $conn->prepare("
        INSERT INTO tb_visita (
            fk_internacao_vis,
            rel_visita_vis,
            acoes_int_vis,
            usuario_create,
            visita_auditor_prof_med,
            visita_auditor_prof_enf,
            visita_med_vis,
            visita_enf_vis,
            visita_no_vis,
            fk_usuario_vis,
            data_visita_vis,
            data_lancamento_vis,
            faturado_vis,
            exames_enf,
            oportunidades_enf,
            programacao_enf,
            timer_vis,
            retificou
        ) VALUES (
            :admission_id,
            :report,
            '',
            :created_by,
            '',
            '',
            'n',
            'n',
            :visit_number,
            :user_id,
            NOW(),
            NOW(),
            'n',
            'Sem exames relevantes no período',
            '',
            '',
            '',
            0
        )
    ");
    $insertStmt->bindValue(':admission_id', $admissionId, PDO::PARAM_INT);
    $insertStmt->bindValue(':report', $report, PDO::PARAM_STR);
    $insertStmt->bindValue(':created_by', (string)($authUser['name'] ?? 'Mobile'), PDO::PARAM_STR);
    $insertStmt->bindValue(':visit_number', $nextVisitNumber, PDO::PARAM_INT);
    $insertStmt->bindValue(':user_id', (int)$authUser['id'], PDO::PARAM_INT);
    $insertStmt->execute();

    $id = (int)$conn->lastInsertId();
    $items = mobileListAdmissionEvolutions($conn, $admissionId);
    foreach ($items as $item) {
        if ((int)$item['id'] === $id) {
            return $item;
        }
    }

    return ['id' => $id, 'report' => $report];
}

function mobileListDischargeTypes(): array
{
    require __DIR__ . '/../../../array_dados.php';

    $items = is_array($dados_alta ?? null) ? $dados_alta : [];
    $items = array_values(array_filter(array_map(
        static fn ($item): string => trim((string)$item),
        $items
    ), static fn (string $item): bool => $item !== ''));

    sort($items, SORT_ASC);

    return $items;
}

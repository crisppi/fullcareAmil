<?php
// ajax/paciente_insights.php
header('Content-Type: application/json; charset=utf-8');
session_start();

$ROOT = dirname(__DIR__);
chdir($ROOT);

require_once 'globals.php';
require_once 'db.php';
require_once 'ajax/_auth_scope.php';
require_once 'models/internacao.php';
require_once 'dao/internacaoDao.php';
require_once 'app/cuidadoContinuado.php';

ajax_require_active_session();

try {
    $ctx = ajax_user_context($conn);
    $pacienteId = filter_input(INPUT_GET, 'id_paciente', FILTER_VALIDATE_INT);
    if (!$pacienteId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'id_paciente obrigatório'
        ]);
        exit;
    }

    if (!ajax_assert_patient_access($conn, $ctx, (int)$pacienteId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'acesso_negado']);
        exit;
    }

    $scopeParams = [];
    $scopeSql = ajax_scope_clause_for_internacao($ctx, 'ac', $scopeParams, 'pins');
    $params = array_merge([':pac' => (int)$pacienteId], $scopeParams);

    $stmtTotal = $conn->prepare("SELECT COUNT(*) AS total
                                   FROM tb_internacao ac
                                  WHERE ac.fk_paciente_int = :pac {$scopeSql}");
    ajax_bind_params($stmtTotal, $params);
    $stmtTotal->execute();
    $totalInternacoes = (int)($stmtTotal->fetchColumn() ?: 0);

    $stmtDias = $conn->prepare("SELECT COALESCE(SUM(total_diarias),0) AS total_diarias
                                  FROM (
                                        SELECT DATEDIFF(COALESCE(al.data_alta_alt, CURRENT_DATE()), ac.data_intern_int) AS total_diarias
                                          FROM tb_internacao ac
                                          LEFT JOIN tb_alta al ON ac.id_internacao = al.fk_id_int_alt
                                         WHERE ac.fk_paciente_int = :pac {$scopeSql}
                                       ) interns");
    ajax_bind_params($stmtDias, $params);
    $stmtDias->execute();
    $totalDiarias = (int)($stmtDias->fetchColumn() ?: 0);

    $mp = $totalInternacoes > 0 ? round($totalDiarias / $totalInternacoes, 1) : 0;

    ensure_cuidado_continuado_schema($conn);

    $stmtCronicos = $conn->prepare("SELECT COUNT(*) AS total,
                                           GROUP_CONCAT(DISTINCT condicao ORDER BY condicao SEPARATOR ', ') AS condicoes
                                      FROM tb_paciente_cronico
                                     WHERE fk_paciente = :pac");
    $stmtCronicos->bindValue(':pac', (int)$pacienteId, PDO::PARAM_INT);
    $stmtCronicos->execute();
    $cronicosRow = $stmtCronicos->fetch(PDO::FETCH_ASSOC) ?: [];

    $cronicosTotal = (int)($cronicosRow['total'] ?? 0);
    $programas = [];
    if ($cronicosTotal > 0) {
        $programas[] = 'Gestão de Crônicos';
    }

    $stmtPaciente = $conn->prepare("
        SELECT pa.matricula_pac,
               se.seguradora_seg,
               COALESCE(se.longa_permanencia_seg, 20) AS longa_permanencia_seg
          FROM tb_paciente pa
          LEFT JOIN tb_seguradora se ON se.id_seguradora = pa.fk_seguradora_pac
         WHERE pa.id_paciente = :pac
         LIMIT 1
    ");
    $stmtPaciente->bindValue(':pac', (int)$pacienteId, PDO::PARAM_INT);
    $stmtPaciente->execute();
    $pacienteRow = $stmtPaciente->fetch(PDO::FETCH_ASSOC) ?: [];
    $longStayThreshold = max(1, (int)($pacienteRow['longa_permanencia_seg'] ?? 20));

    $scopeParamsLast = [];
    $scopeSqlLast = ajax_scope_clause_for_internacao($ctx, 'ac', $scopeParamsLast, 'pins_last');
    $stmtLastIntern = $conn->prepare("
        SELECT ac.id_internacao,
               ac.data_intern_int,
               ac.acomodacao_int,
               ac.grupo_patologia_int,
               ac.internado_int,
               ho.nome_hosp,
               pat.patologia_pat,
               NULLIF(al.data_alta_alt, '0000-00-00') AS data_alta_alt,
               GREATEST(DATEDIFF(COALESCE(NULLIF(al.data_alta_alt, '0000-00-00'), CURRENT_DATE), ac.data_intern_int), 0) AS dias
          FROM tb_internacao ac
          LEFT JOIN tb_hospital ho ON ho.id_hospital = ac.fk_hospital_int
          LEFT JOIN tb_patologia pat ON pat.id_patologia = ac.fk_patologia_int
          LEFT JOIN (
                SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
                  FROM tb_alta
                 GROUP BY fk_id_int_alt
          ) al ON al.fk_id_int_alt = ac.id_internacao
         WHERE ac.fk_paciente_int = :pac {$scopeSqlLast}
         ORDER BY ac.data_intern_int DESC, ac.id_internacao DESC
         LIMIT 1
    ");
    ajax_bind_params($stmtLastIntern, array_merge([':pac' => (int)$pacienteId], $scopeParamsLast));
    $stmtLastIntern->execute();
    $lastIntern = $stmtLastIntern->fetch(PDO::FETCH_ASSOC) ?: null;

    $scopeParamsVisit = [];
    $scopeSqlVisit = ajax_scope_clause_for_internacao($ctx, 'ac', $scopeParamsVisit, 'pins_visit');
    $stmtLastVisit = $conn->prepare("
        SELECT vi.id_visita,
               vi.data_visita_vis,
               vi.visita_no_vis,
               COALESCE(NULLIF(vi.rel_visita_vis, ''), NULLIF(ac.rel_int, '')) AS relatorio
          FROM tb_visita vi
          INNER JOIN tb_internacao ac ON ac.id_internacao = vi.fk_internacao_vis
         WHERE ac.fk_paciente_int = :pac {$scopeSqlVisit}
         ORDER BY vi.data_visita_vis DESC, vi.id_visita DESC
         LIMIT 1
    ");
    ajax_bind_params($stmtLastVisit, array_merge([':pac' => (int)$pacienteId], $scopeParamsVisit));
    $stmtLastVisit->execute();
    $lastVisit = $stmtLastVisit->fetch(PDO::FETCH_ASSOC) ?: null;

    $scopeParamsPend = [];
    $scopeSqlPend = ajax_scope_clause_for_internacao($ctx, 'ac', $scopeParamsPend, 'pins_pend');
    $stmtPend = $conn->prepare("
        SELECT
            SUM(CASE WHEN al.data_alta_alt IS NULL OR al.data_alta_alt = '0000-00-00' THEN 1 ELSE 0 END) AS internacoes_abertas,
            SUM(CASE WHEN (ac.rel_int IS NULL OR TRIM(ac.rel_int) = '') THEN 1 ELSE 0 END) AS sem_relatorio,
            SUM(CASE WHEN (ac.acoes_int IS NULL OR TRIM(ac.acoes_int) = '') THEN 1 ELSE 0 END) AS sem_acoes
          FROM tb_internacao ac
          LEFT JOIN (
                SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
                  FROM tb_alta
                 GROUP BY fk_id_int_alt
          ) al ON al.fk_id_int_alt = ac.id_internacao
         WHERE ac.fk_paciente_int = :pac {$scopeSqlPend}
    ");
    ajax_bind_params($stmtPend, array_merge([':pac' => (int)$pacienteId], $scopeParamsPend));
    $stmtPend->execute();
    $pendRow = $stmtPend->fetch(PDO::FETCH_ASSOC) ?: [];

    $scopeParamsPat = [];
    $scopeSqlPat = ajax_scope_clause_for_internacao($ctx, 'ac', $scopeParamsPat, 'pins_pat');
    $stmtPat = $conn->prepare("
        SELECT GROUP_CONCAT(DISTINCT COALESCE(NULLIF(pat.patologia_pat, ''), NULLIF(ac.grupo_patologia_int, '')) ORDER BY ac.data_intern_int DESC SEPARATOR ', ') AS patologias
          FROM tb_internacao ac
          LEFT JOIN tb_patologia pat ON pat.id_patologia = ac.fk_patologia_int
         WHERE ac.fk_paciente_int = :pac {$scopeSqlPat}
    ");
    ajax_bind_params($stmtPat, array_merge([':pac' => (int)$pacienteId], $scopeParamsPat));
    $stmtPat->execute();
    $patologiasText = trim((string)($stmtPat->fetchColumn() ?: ''));

    $longStayAlerts = [];
    if ($lastIntern && empty($lastIntern['data_alta_alt']) && (int)($lastIntern['dias'] ?? 0) >= $longStayThreshold) {
        $longStayAlerts[] = 'Internação atual com ' . (int)$lastIntern['dias'] . ' dia(s), acima do limite de ' . $longStayThreshold . ' dia(s).';
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'total_internacoes' => $totalInternacoes,
            'total_diarias'     => $totalDiarias,
            'mp'                => $mp,
            'cadastro'          => [
                'seguradora' => (string)($pacienteRow['seguradora_seg'] ?? ''),
                'matricula'  => (string)($pacienteRow['matricula_pac'] ?? ''),
            ],
            'ultima_internacao' => $lastIntern ? [
                'id' => (int)$lastIntern['id_internacao'],
                'hospital' => (string)($lastIntern['nome_hosp'] ?? ''),
                'data_internacao' => (string)($lastIntern['data_intern_int'] ?? ''),
                'data_alta' => (string)($lastIntern['data_alta_alt'] ?? ''),
                'dias' => (int)($lastIntern['dias'] ?? 0),
                'acomodacao' => (string)($lastIntern['acomodacao_int'] ?? ''),
                'patologia' => (string)($lastIntern['patologia_pat'] ?: ($lastIntern['grupo_patologia_int'] ?? '')),
                'status' => empty($lastIntern['data_alta_alt']) ? 'Aberta' : 'Encerrada',
            ] : null,
            'ultima_visita' => $lastVisit ? [
                'id' => (int)$lastVisit['id_visita'],
                'data' => (string)($lastVisit['data_visita_vis'] ?? ''),
                'numero' => (string)($lastVisit['visita_no_vis'] ?? ''),
                'resumo' => mb_substr(trim((string)($lastVisit['relatorio'] ?? '')), 0, 180, 'UTF-8'),
            ] : null,
            'patologias' => $patologiasText,
            'pendencias' => [
                'internacoes_abertas' => (int)($pendRow['internacoes_abertas'] ?? 0),
                'sem_relatorio' => (int)($pendRow['sem_relatorio'] ?? 0),
                'sem_acoes' => (int)($pendRow['sem_acoes'] ?? 0),
            ],
            'alertas_longa_permanencia' => $longStayAlerts,
            'cuidado_programa'  => [
                'em_programa' => !empty($programas),
                'programas' => $programas,
                'cronicos_total' => $cronicosTotal,
                'condicoes' => (string)($cronicosRow['condicoes'] ?? ''),
            ],
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Erro ao recuperar dados do paciente',
    ]);
}

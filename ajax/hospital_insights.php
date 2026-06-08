<?php
// ajax/hospital_insights.php
header('Content-Type: application/json; charset=utf-8');
session_start();

$ROOT = dirname(__DIR__);
chdir($ROOT);

require_once 'globals.php';
require_once 'db.php';
require_once 'ajax/_auth_scope.php';

ajax_require_active_session();

try {
    $ctx = ajax_user_context($conn);
    $hospitalId = filter_input(INPUT_GET, 'id_hospital', FILTER_VALIDATE_INT);
    if (!$hospitalId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'id_hospital obrigatório'
        ]);
        exit;
    }

    if (!ajax_assert_hospital_access($conn, $ctx, (int)$hospitalId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'acesso_negado']);
        exit;
    }

    $threshold = 5;

    $stmtNeg = $conn->prepare("
        SELECT COUNT(*) AS total_negociacoes, COALESCE(SUM(ng.saving), 0) AS total_saving
          FROM tb_negociacao ng
          INNER JOIN tb_internacao ac ON ng.fk_id_int = ac.id_internacao
         WHERE ac.fk_hospital_int = :hospId
           AND UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'
    ");
    $stmtNeg->bindValue(':hospId', $hospitalId, PDO::PARAM_INT);
    $stmtNeg->execute();
    $negRow           = $stmtNeg->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalNegociacoes = (int)   ($negRow['total_negociacoes'] ?? 0);
    $totalSaving      = (float) ($negRow['total_saving'] ?? 0);

    $stmtNegTipo = $conn->prepare("
        SELECT COALESCE(NULLIF(TRIM(ng.tipo_negociacao), ''), 'Não informado') AS tipo,
               COUNT(*) AS qtd
          FROM tb_negociacao ng
          INNER JOIN tb_internacao ac ON ng.fk_id_int = ac.id_internacao
         WHERE ac.fk_hospital_int = :hospId
           AND UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'
         GROUP BY tipo
         ORDER BY qtd DESC, tipo ASC
         LIMIT 1
    ");
    $stmtNegTipo->bindValue(':hospId', $hospitalId, PDO::PARAM_INT);
    $stmtNegTipo->execute();
    $negTipoRow = $stmtNegTipo->fetch(PDO::FETCH_ASSOC) ?: [];
    $tipoNegociacaoPredominante = trim((string)($negTipoRow['tipo'] ?? ''));

    $stmtGlosa = $conn->prepare("
        SELECT COALESCE(SUM(ca.valor_glosa_total), 0) AS total_glosa,
               COUNT(ca.id_capeante)                  AS qtd_glosa,
               COALESCE(SUM(ca.glosa_diaria), 0) AS glosa_diaria,
               COALESCE(SUM(ca.glosa_matmed), 0) AS glosa_matmed,
               COALESCE(SUM(ca.glosa_medicamentos), 0) AS glosa_medicamentos,
               COALESCE(SUM(ca.glosa_materiais), 0) AS glosa_materiais,
               COALESCE(SUM(ca.glosa_taxas), 0) AS glosa_taxas,
               COALESCE(SUM(ca.glosa_honorarios), 0) AS glosa_honorarios,
               COALESCE(SUM(ca.glosa_sadt), 0) AS glosa_sadt,
               COALESCE(SUM(ca.glosa_oxig), 0) AS glosa_oxig,
               COALESCE(SUM(ca.glosa_opme), 0) AS glosa_opme
          FROM tb_capeante ca
          INNER JOIN tb_internacao ac ON ca.fk_int_capeante = ac.id_internacao
         WHERE ac.fk_hospital_int = :hospId
           AND ca.valor_glosa_total > 0
    ");
    $stmtGlosa->bindValue(':hospId', $hospitalId, PDO::PARAM_INT);
    $stmtGlosa->execute();
    $glosaRow   = $stmtGlosa->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalGlosa = (float) ($glosaRow['total_glosa'] ?? 0);
    $qtdGlosa   = (int)   ($glosaRow['qtd_glosa']   ?? 0);
    $glosaTipos = [];
    foreach ([
        'Diárias' => 'glosa_diaria',
        'Mat/Med' => 'glosa_matmed',
        'Medicamentos' => 'glosa_medicamentos',
        'Materiais' => 'glosa_materiais',
        'Taxas' => 'glosa_taxas',
        'Honorários' => 'glosa_honorarios',
        'SADT' => 'glosa_sadt',
        'Oxigênio' => 'glosa_oxig',
        'OPME' => 'glosa_opme',
    ] as $label => $column) {
        $value = (float)($glosaRow[$column] ?? 0);
        if ($value > 0) {
            $glosaTipos[] = ['tipo' => $label, 'valor' => $value];
        }
    }
    usort($glosaTipos, static function (array $a, array $b): int {
        return ((float)$b['valor']) <=> ((float)$a['valor']);
    });

    $stmtEA = $conn->prepare("
        SELECT COUNT(*) AS qtd_ea
          FROM tb_gestao g
          INNER JOIN tb_internacao ac ON g.fk_internacao_ges = ac.id_internacao
         WHERE ac.fk_hospital_int = :hospId
           AND g.evento_adverso_ges = 's'
    ");
    $stmtEA->bindValue(':hospId', $hospitalId, PDO::PARAM_INT);
    $stmtEA->execute();
    $eventosAdversos = (int) ($stmtEA->fetchColumn() ?: 0);

    $stmtTotal = $conn->prepare("
        SELECT COUNT(*) 
          FROM tb_internacao ac
         WHERE ac.fk_hospital_int = :hospId
    ");
    $stmtTotal->bindValue(':hospId', $hospitalId, PDO::PARAM_INT);
    $stmtTotal->execute();
    $totalInternacoes = (int) $stmtTotal->fetchColumn();

    $stmtUti = $conn->prepare("
        SELECT COUNT(DISTINCT ac.id_internacao)
          FROM tb_internacao ac
          LEFT JOIN tb_uti ut ON ut.fk_internacao_uti = ac.id_internacao
         WHERE ac.fk_hospital_int = :hospId
           AND (
                ac.internado_uti_int = 's'
             OR ac.internacao_uti_int = 's'
             OR ut.internado_uti = 's'
             OR ut.internacao_uti = 's'
           )
    ");
    $stmtUti->bindValue(':hospId', $hospitalId, PDO::PARAM_INT);
    $stmtUti->execute();
    $pacientesUti = (int) $stmtUti->fetchColumn();

    $longStayThreshold = 20;
    $stmtLong = $conn->prepare("
        SELECT
            SUM(GREATEST(DATEDIFF(COALESCE(al.data_alta_alt, CURRENT_DATE), ac.data_intern_int), 0)) AS dias_total,
            COUNT(*) AS qtd_long
          FROM tb_internacao ac
          LEFT JOIN tb_alta al ON al.fk_id_int_alt = ac.id_internacao
         WHERE ac.fk_hospital_int = :hospId
           AND DATEDIFF(COALESCE(al.data_alta_alt, CURRENT_DATE), ac.data_intern_int) >= :dias
    ");
    $stmtLong->bindValue(':hospId', $hospitalId, PDO::PARAM_INT);
    $stmtLong->bindValue(':dias', $longStayThreshold, PDO::PARAM_INT);
    $stmtLong->execute();
    $longRow = $stmtLong->fetch(PDO::FETCH_ASSOC) ?: ['dias_total' => 0, 'qtd_long' => 0];
    $longStay = (int) ($longRow['qtd_long'] ?? 0);
    $totalDiasLong = (int) ($longRow['dias_total'] ?? 0);

    $stmtDiasHospital = $conn->prepare("
        SELECT SUM(GREATEST(DATEDIFF(COALESCE(al.data_alta_alt, CURRENT_DATE), ac.data_intern_int), 0)) AS total_dias
          FROM tb_internacao ac
          LEFT JOIN tb_alta al ON al.fk_id_int_alt = ac.id_internacao
         WHERE ac.fk_hospital_int = :hospId
    ");
    $stmtDiasHospital->bindValue(':hospId', $hospitalId, PDO::PARAM_INT);
    $stmtDiasHospital->execute();
    $totalDiasHosp = (int) ($stmtDiasHospital->fetchColumn() ?: 0);

    $stmtDiasUti = $conn->prepare("
        SELECT SUM(GREATEST(DATEDIFF(COALESCE(ut.data_alta_uti, CURRENT_DATE), ut.data_internacao_uti), 0)) AS total_dias
          FROM tb_internacao ac
          INNER JOIN tb_uti ut ON ut.fk_internacao_uti = ac.id_internacao
         WHERE ac.fk_hospital_int = :hospId
    ");
    $stmtDiasUti->bindValue(':hospId', $hospitalId, PDO::PARAM_INT);
    $stmtDiasUti->execute();
    $totalDiasUti = (int) ($stmtDiasUti->fetchColumn() ?: 0);

    $percentUti = $totalInternacoes > 0
        ? round(($pacientesUti / $totalInternacoes) * 100, 1)
        : 0;

    $mpHospital = $totalInternacoes > 0
        ? round($totalDiasHosp / $totalInternacoes, 1)
        : 0;
    $mpUti = $pacientesUti > 0
        ? round($totalDiasUti / $pacientesUti, 1)
        : 0;

    $opportunityScore = 0;
    $opportunityType = $tipoNegociacaoPredominante !== '' ? $tipoNegociacaoPredominante : 'Sem negociação registrada';

    if ($totalNegociacoes >= 8) {
        $opportunityScore += 3;
    } elseif ($totalNegociacoes >= 3) {
        $opportunityScore += 2;
    } elseif ($totalNegociacoes >= 1) {
        $opportunityScore += 1;
    }

    if ($totalSaving >= 5000) {
        $opportunityScore += 3;
    } elseif ($totalSaving >= 1000) {
        $opportunityScore += 2;
    } elseif ($totalSaving > 0) {
        $opportunityScore += 1;
    }

    if ($opportunityScore >= 5) {
        $opportunityLevel = 'alto';
        $opportunityLabel = 'Alto';
        $opportunityIcon = 'bi-exclamation-circle-fill';
        $opportunitySummary = 'Atuar primeiro: há concentração de trocas e saving.';
    } elseif ($opportunityScore >= 2) {
        $opportunityLevel = 'medio';
        $opportunityLabel = 'Médio';
        $opportunityIcon = 'bi-dash-circle-fill';
        $opportunitySummary = 'Revisar na rotina: há trocas/saving relevantes.';
    } else {
        $opportunityLevel = 'baixo';
        $opportunityLabel = 'Baixo';
        $opportunityIcon = 'bi-check-circle-fill';
        $opportunitySummary = 'Monitorar: sem concentração relevante de troca/saving.';
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'negociacoes'       => $totalNegociacoes,
            'total_saving'      => $totalSaving,
            'oportunidade_negociacao' => [
                'nivel'  => $opportunityLevel,
                'label'  => $opportunityLabel,
                'icon'   => $opportunityIcon,
                'tipo'   => $opportunityType,
                'score'  => $opportunityScore,
                'resumo' => $opportunitySummary,
            ],
            'total_glosa'       => $totalGlosa,
            'qtd_glosa'         => $qtdGlosa,
            'glosa_tipos'       => array_slice($glosaTipos, 0, 3),
            'eventos_adversos'  => $eventosAdversos,
            'total_internacoes' => $totalInternacoes,
            'inter_uti'         => $pacientesUti,
            'percent_uti'       => $percentUti,
            'long_stay'         => $longStay,
            'mp_hospital'       => $mpHospital,
            'mp_uti'            => $mpUti,
            'mp_long'           => $longStay > 0 ? round($totalDiasLong / $longStay, 1) : 0,
            'long_threshold'    => $longStayThreshold,
            'threshold'         => $threshold,
            'uti_alert'         => $pacientesUti >= $threshold
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Erro ao recuperar insights',
    ]);
}

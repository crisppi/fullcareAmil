<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fmt_num($value, int $decimals = 2): string
{
    return number_format((float)$value, $decimals, ',', '.');
}

function fmt_money($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function table_columns(PDO $conn, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $stmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $stmt->bindValue(':t', $table);
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $cache[$table] = array_fill_keys($cols, true);
    return $cache[$table];
}

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mesInput = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = ($mesInput !== null && $mesInput !== false) ? (int)$mesInput : null;
if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = (int)date('Y');
}

$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$tipoInternação = trim((string)(filter_input(INPUT_GET, 'tipo_internacao') ?? ''));
$modoInternação = trim((string)(filter_input(INPUT_GET, 'modo_internacao') ?? ''));
$patologiaId = filter_input(INPUT_GET, 'patologia_id', FILTER_VALIDATE_INT) ?: null;
$grupoPatologia = trim((string)(filter_input(INPUT_GET, 'grupo_patologia') ?? ''));
$internado = trim((string)(filter_input(INPUT_GET, 'internado') ?? ''));
$uti = trim((string)(filter_input(INPUT_GET, 'uti') ?? ''));
$antecedenteId = filter_input(INPUT_GET, 'antecedente_id', FILTER_VALIDATE_INT) ?: null;
$sexo = trim((string)(filter_input(INPUT_GET, 'sexo') ?? ''));
$faixaEtaria = trim((string)(filter_input(INPUT_GET, 'faixa_etaria') ?? ''));

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$tiposInt = $conn->query("SELECT DISTINCT tipo_admissao_int FROM tb_internacao WHERE tipo_admissao_int IS NOT NULL AND tipo_admissao_int <> '' ORDER BY tipo_admissao_int")
    ->fetchAll(PDO::FETCH_COLUMN);
$modos = $conn->query("SELECT DISTINCT modo_internacao_int FROM tb_internacao WHERE modo_internacao_int IS NOT NULL AND modo_internacao_int <> '' ORDER BY modo_internacao_int")
    ->fetchAll(PDO::FETCH_COLUMN);
$patologias = $conn->query("SELECT id_patologia, patologia_pat FROM tb_patologia ORDER BY patologia_pat")
    ->fetchAll(PDO::FETCH_ASSOC);
$grupos = $conn->query("SELECT DISTINCT grupo_patologia_int FROM tb_internacao WHERE grupo_patologia_int IS NOT NULL AND grupo_patologia_int <> '' ORDER BY grupo_patologia_int")
    ->fetchAll(PDO::FETCH_COLUMN);
$antecedentes = $conn->query("SELECT id_antecedente, antecedente_ant FROM tb_antecedente WHERE antecedente_ant IS NOT NULL AND antecedente_ant <> '' ORDER BY antecedente_ant")
    ->fetchAll(PDO::FETCH_ASSOC);
$anos = $conn->query("SELECT DISTINCT YEAR(data_intern_int) AS ano FROM tb_internacao WHERE data_intern_int IS NOT NULL AND data_intern_int <> '0000-00-00' ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);
if (!filter_has_var(INPUT_GET, 'ano') && $anos) {
    $ano = (int)$anos[0];
}

$faixasEtarias = [
    '0-19' => '0-19',
    '20-39' => '20-39',
    '40-59' => '40-59',
    '60-79' => '60-79',
    '80+' => '80+',
    'Sem informacao' => 'Sem informacao',
];

function idade_cond(string $faixa, string $alias = 'pa'): ?string
{
    switch ($faixa) {
        case '0-19':
            return "{$alias}.idade_pac < 20";
        case '20-39':
            return "{$alias}.idade_pac >= 20 AND {$alias}.idade_pac < 40";
        case '40-59':
            return "{$alias}.idade_pac >= 40 AND {$alias}.idade_pac < 60";
        case '60-79':
            return "{$alias}.idade_pac >= 60 AND {$alias}.idade_pac < 80";
        case '80+':
            return "{$alias}.idade_pac >= 80";
        case 'Sem informacao':
            return "{$alias}.idade_pac IS NULL";
        default:
            return null;
    }
}

function build_where_internacao(array $filters, array &$params, bool $applyUti): array
{
    $where = "1=1";
    $params = [];
    if (!empty($filters['ano'])) {
        $where .= " AND YEAR(i.data_intern_int) = :ano";
        $params[':ano'] = (int)$filters['ano'];
    }
    if (!empty($filters['mes'])) {
        $where .= " AND MONTH(i.data_intern_int) = :mes";
        $params[':mes'] = (int)$filters['mes'];
    }
    if (!empty($filters['hospital_id'])) {
        $where .= " AND i.fk_hospital_int = :hospital_id";
        $params[':hospital_id'] = (int)$filters['hospital_id'];
    }
    if (!empty($filters['tipo_internacao'])) {
        $where .= " AND i.tipo_admissao_int = :tipo_internacao";
        $params[':tipo_internacao'] = $filters['tipo_internacao'];
    }
    if (!empty($filters['modo_internacao'])) {
        $where .= " AND i.modo_internacao_int = :modo_internacao";
        $params[':modo_internacao'] = $filters['modo_internacao'];
    }
    if (!empty($filters['patologia_id'])) {
        $where .= " AND i.fk_patologia_int = :patologia_id";
        $params[':patologia_id'] = (int)$filters['patologia_id'];
    }
    if (!empty($filters['grupo_patologia'])) {
        $where .= " AND i.grupo_patologia_int = :grupo_patologia";
        $params[':grupo_patologia'] = $filters['grupo_patologia'];
    }
    if (!empty($filters['antecedente_id'])) {
        $where .= " AND i.fk_patologia2 = :antecedente_id";
        $params[':antecedente_id'] = (int)$filters['antecedente_id'];
    }
    if (!empty($filters['internado'])) {
        $where .= " AND i.internado_int = :internado";
        $params[':internado'] = $filters['internado'];
    }
    if (!empty($filters['sexo'])) {
        $where .= " AND pa.sexo_pac = :sexo";
        $params[':sexo'] = $filters['sexo'];
    }
    if (!empty($filters['faixa_etaria'])) {
        $cond = idade_cond($filters['faixa_etaria'], 'pa');
        if ($cond) {
            $where .= " AND {$cond}";
        }
    }

    $utiJoin = "LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao";
    if ($applyUti) {
        if ($filters['uti'] === 's') {
            $where .= " AND ut.fk_internacao_uti IS NOT NULL";
        } elseif ($filters['uti'] === 'n') {
            $where .= " AND ut.fk_internacao_uti IS NULL";
        }
    }

    return [$where, $utiJoin];
}

function build_where_financeiro(array $filters, array &$params, bool $applyUti): string
{
    $where = "ref_date IS NOT NULL AND ref_date <> '0000-00-00'";
    $params = [];
    if (!empty($filters['ano'])) {
        $where .= " AND YEAR(ref_date) = :ano";
        $params[':ano'] = (int)$filters['ano'];
    }
    if (!empty($filters['mes'])) {
        $where .= " AND MONTH(ref_date) = :mes";
        $params[':mes'] = (int)$filters['mes'];
    }
    if (!empty($filters['hospital_id'])) {
        $where .= " AND fk_hospital_int = :hospital_id";
        $params[':hospital_id'] = (int)$filters['hospital_id'];
    }
    if (!empty($filters['tipo_internacao'])) {
        $where .= " AND tipo_admissao_int = :tipo_internacao";
        $params[':tipo_internacao'] = $filters['tipo_internacao'];
    }
    if (!empty($filters['modo_internacao'])) {
        $where .= " AND modo_internacao_int = :modo_internacao";
        $params[':modo_internacao'] = $filters['modo_internacao'];
    }
    if (!empty($filters['patologia_id'])) {
        $where .= " AND fk_patologia_int = :patologia_id";
        $params[':patologia_id'] = (int)$filters['patologia_id'];
    }
    if (!empty($filters['grupo_patologia'])) {
        $where .= " AND grupo_patologia_int = :grupo_patologia";
        $params[':grupo_patologia'] = $filters['grupo_patologia'];
    }
    if (!empty($filters['antecedente_id'])) {
        $where .= " AND fk_patologia2 = :antecedente_id";
        $params[':antecedente_id'] = (int)$filters['antecedente_id'];
    }
    if (!empty($filters['internado'])) {
        $where .= " AND internado_int = :internado";
        $params[':internado'] = $filters['internado'];
    }
    if (!empty($filters['sexo'])) {
        $where .= " AND sexo_pac = :sexo";
        $params[':sexo'] = $filters['sexo'];
    }
    if (!empty($filters['faixa_etaria'])) {
        $cond = idade_cond($filters['faixa_etaria'], 't');
        if ($cond) {
            $where .= " AND {$cond}";
        }
    }
    if ($applyUti) {
        if ($filters['uti'] === 's') {
            $where .= " AND ut.fk_internacao_uti IS NOT NULL";
        } elseif ($filters['uti'] === 'n') {
            $where .= " AND ut.fk_internacao_uti IS NULL";
        }
    }

    return $where;
}

function internacao_stats(PDO $conn, array $filters): array
{
    $params = [];
    [$where, $utiJoin] = build_where_internacao($filters, $params, true);

    $sql = "
        SELECT
            COUNT(DISTINCT i.id_internacao) AS total_internacoes,
            SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS total_diarias
        FROM tb_internacao i
        LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
        LEFT JOIN (
            SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
            FROM tb_alta
            GROUP BY fk_id_int_alt
        ) al ON al.fk_id_int_alt = i.id_internacao
        {$utiJoin}
        WHERE {$where}
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total_internacoes' => (int)($row['total_internacoes'] ?? 0),
        'total_diarias' => (int)($row['total_diarias'] ?? 0),
    ];
}

function financeiro_stats(PDO $conn, array $filters): array
{
    $dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
    $params = [];
    $where = build_where_financeiro($filters, $params, true);

    $sql = "
        SELECT
            SUM(COALESCE(t.valor_apresentado_capeante,0)) AS valor_apresentado,
            SUM(COALESCE(t.valor_final_capeante,0)) AS valor_final,
            SUM(COALESCE(t.valor_glosa_total,0)) AS glosa_total,
            SUM(COALESCE(t.valor_glosa_med,0)) AS glosa_med,
            SUM(COALESCE(t.valor_glosa_enf,0)) AS glosa_enf
        FROM (
            SELECT
                ca.id_capeante,
                ca.fk_int_capeante,
                ca.valor_apresentado_capeante,
                ca.valor_final_capeante,
                ca.valor_glosa_total,
                ca.valor_glosa_med,
                ca.valor_glosa_enf,
                {$dateExpr} AS ref_date,
                ac.fk_hospital_int,
                ac.tipo_admissao_int,
                ac.modo_internacao_int,
                ac.fk_patologia_int,
                ac.grupo_patologia_int,
                ac.fk_patologia2,
                ac.internado_int,
                pa.sexo_pac,
                pa.idade_pac
            FROM tb_capeante ca
            INNER JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
            LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
        ) t
        LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = t.fk_int_capeante
        WHERE {$where}
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'valor_apresentado' => (float)($row['valor_apresentado'] ?? 0),
        'valor_final' => (float)($row['valor_final'] ?? 0),
        'glosa_total' => (float)($row['glosa_total'] ?? 0),
        'glosa_med' => (float)($row['glosa_med'] ?? 0),
        'glosa_enf' => (float)($row['glosa_enf'] ?? 0),
    ];
}

function custos_breakdown(PDO $conn, array $filters): array
{
    $dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
    $params = [];
    $where = build_where_financeiro($filters, $params, true);

    $diarCols = table_columns($conn, 'tb_cap_valores_diar');
    $apCols = table_columns($conn, 'tb_cap_valores_ap');
    $utiCols = table_columns($conn, 'tb_cap_valores_uti');
    $ccCols = table_columns($conn, 'tb_cap_valores_cc');

    $sumCols = function (array $cols, array $candidates): string {
        $parts = [];
        foreach ($candidates as $col) {
            if (isset($cols[$col])) {
                $parts[] = "COALESCE({$col},0)";
            }
        }
        if (!$parts) {
            return "0";
        }
        return implode(' + ', $parts);
    };

    $sumDiariasCobrado = $sumCols($diarCols, [
        'ac_quarto_cobrado', 'ac_quarto_cob',
        'ac_dayclinic_cobrado', 'ac_dayclinic_cob',
        'ac_uti_cobrado', 'ac_uti_cob',
        'ac_utisemi_cobrado', 'ac_utisemi_cob',
        'ac_enfermaria_cobrado', 'ac_enfermaria_cob',
        'ac_bercario_cobrado', 'ac_bercario_cob',
        'ac_acompanhante_cobrado', 'ac_acompanhante_cob',
        'ac_isolamento_cobrado', 'ac_isolamento_cob',
        'valor_cobrado',
    ]);
    $sumDiariasGlosa = $sumCols($diarCols, [
        'ac_quarto_glosado', 'ac_quarto_glo',
        'ac_dayclinic_glosado', 'ac_dayclinic_glo',
        'ac_uti_glosado', 'ac_uti_glo',
        'ac_utisemi_glosado', 'ac_utisemi_glo',
        'ac_enfermaria_glosado', 'ac_enfermaria_glo',
        'ac_bercario_glosado', 'ac_bercario_glo',
        'ac_acompanhante_glosado', 'ac_acompanhante_glo',
        'ac_isolamento_glosado', 'ac_isolamento_glo',
        'valor_glosado',
    ]);

    $sumHonorCobradoAp = $sumCols($apCols, ['ap_honorarios_cobrado', 'ap_honorarios_cob']);
    $sumHonorGlosaAp = $sumCols($apCols, ['ap_honorarios_glosado', 'ap_honorarios_glo']);
    $sumHonorCobradoUti = $sumCols($utiCols, ['uti_honorarios_cobrado', 'uti_honorarios_cob']);
    $sumHonorGlosaUti = $sumCols($utiCols, ['uti_honorarios_glosado', 'uti_honorarios_glo']);
    $sumHonorCobradoCc = $sumCols($ccCols, ['cc_honorarios_cobrado', 'cc_honorarios_cob']);
    $sumHonorGlosaCc = $sumCols($ccCols, ['cc_honorarios_glosado', 'cc_honorarios_glo']);

    $sumMatCobradoAp = $sumCols($apCols, [
        'ap_mat_consumo_cobrado', 'ap_mat_consumo_cob',
        'ap_medicametos_cobrado', 'ap_medicametos_cob',
        'ap_mat_espec_cobrado', 'ap_mat_espec_cob',
    ]);
    $sumMatGlosaAp = $sumCols($apCols, [
        'ap_mat_consumo_glosado', 'ap_mat_consumo_glo',
        'ap_medicametos_glosado', 'ap_medicametos_glo',
        'ap_mat_espec_glosado', 'ap_mat_espec_glo',
    ]);
    $sumMatCobradoUti = $sumCols($utiCols, [
        'uti_mat_consumo_cobrado', 'uti_mat_consumo_cob',
        'uti_medicametos_cobrado', 'uti_medicametos_cob',
        'uti_mat_espec_cobrado', 'uti_mat_espec_cob',
    ]);
    $sumMatGlosaUti = $sumCols($utiCols, [
        'uti_mat_consumo_glosado', 'uti_mat_consumo_glo',
        'uti_medicametos_glosado', 'uti_medicametos_glo',
        'uti_mat_espec_glosado', 'uti_mat_espec_glo',
    ]);
    $sumMatCobradoCc = $sumCols($ccCols, [
        'cc_mat_consumo_cobrado', 'cc_mat_consumo_cob',
        'cc_medicametos_cobrado', 'cc_medicametos_cob',
        'cc_mat_espec_cobrado', 'cc_mat_espec_cob',
    ]);
    $sumMatGlosaCc = $sumCols($ccCols, [
        'cc_mat_consumo_glosado', 'cc_mat_consumo_glo',
        'cc_medicametos_glosado', 'cc_medicametos_glo',
        'cc_mat_espec_glosado', 'cc_mat_espec_glo',
    ]);

    $sumSadtCobradoAp = $sumCols($apCols, [
        'ap_exames_cobrado', 'ap_exames_cob',
        'ap_terapias_cobrado', 'ap_terapias_cob',
        'ap_hemoderivados_cobrado', 'ap_hemoderivados_cob',
    ]);
    $sumSadtGlosaAp = $sumCols($apCols, [
        'ap_exames_glosado', 'ap_exames_glo',
        'ap_terapias_glosado', 'ap_terapias_glo',
        'ap_hemoderivados_glosado', 'ap_hemoderivados_glo',
    ]);
    $sumSadtCobradoUti = $sumCols($utiCols, [
        'uti_exames_cobrado', 'uti_exames_cob',
        'uti_terapias_cobrado', 'uti_terapias_cob',
        'uti_hemoderivados_cobrado', 'uti_hemoderivados_cob',
    ]);
    $sumSadtGlosaUti = $sumCols($utiCols, [
        'uti_exames_glosado', 'uti_exames_glo',
        'uti_terapias_glosado', 'uti_terapias_glo',
        'uti_hemoderivados_glosado', 'uti_hemoderivados_glo',
    ]);
    $sumSadtCobradoCc = $sumCols($ccCols, [
        'cc_exames_cobrado', 'cc_exames_cob',
        'cc_terapias_cobrado', 'cc_terapias_cob',
        'cc_hemoderivados_cobrado', 'cc_hemoderivados_cob',
    ]);
    $sumSadtGlosaCc = $sumCols($ccCols, [
        'cc_exames_glosado', 'cc_exames_glo',
        'cc_terapias_glosado', 'cc_terapias_glo',
        'cc_hemoderivados_glosado', 'cc_hemoderivados_glo',
    ]);

    $sumOxigCobradoAp = $sumCols($apCols, ['ap_gases_cobrado', 'ap_gases_cob']);
    $sumOxigGlosaAp = $sumCols($apCols, ['ap_gases_glosado', 'ap_gases_glo']);
    $sumOxigCobradoUti = $sumCols($utiCols, ['uti_gases_cobrado', 'uti_gases_cob']);
    $sumOxigGlosaUti = $sumCols($utiCols, ['uti_gases_glosado', 'uti_gases_glo']);
    $sumOxigCobradoCc = $sumCols($ccCols, ['cc_gases_cobrado', 'cc_gases_cob']);
    $sumOxigGlosaCc = $sumCols($ccCols, ['cc_gases_glosado', 'cc_gases_glo']);

    $sumTaxasCobradoAp = $sumCols($apCols, ['ap_taxas_cobrado', 'ap_taxas_cob']);
    $sumTaxasGlosaAp = $sumCols($apCols, ['ap_taxas_glosado', 'ap_taxas_glo']);
    $sumTaxasCobradoUti = $sumCols($utiCols, ['uti_taxas_cobrado', 'uti_taxas_cob']);
    $sumTaxasGlosaUti = $sumCols($utiCols, ['uti_taxas_glosado', 'uti_taxas_glo']);
    $sumTaxasCobradoCc = $sumCols($ccCols, ['cc_taxas_cobrado', 'cc_taxas_cob']);
    $sumTaxasGlosaCc = $sumCols($ccCols, ['cc_taxas_glosado', 'cc_taxas_glo']);

    $sql = "
        SELECT
            SUM(COALESCE(d.diarias_cobrado,0)) AS valor_diarias,
            SUM(COALESCE(a.honorarios_cobrado,0) + COALESCE(u.honorarios_cobrado,0) + COALESCE(c.honorarios_cobrado,0)) AS valor_honorarios,
            SUM(COALESCE(a.matmed_cobrado,0) + COALESCE(u.matmed_cobrado,0) + COALESCE(c.matmed_cobrado,0)) AS valor_matmed,
            SUM(COALESCE(a.sadt_cobrado,0) + COALESCE(u.sadt_cobrado,0) + COALESCE(c.sadt_cobrado,0)) AS valor_sadt,
            SUM(COALESCE(a.oxig_cobrado,0) + COALESCE(u.oxig_cobrado,0) + COALESCE(c.oxig_cobrado,0)) AS valor_oxig,
            SUM(COALESCE(a.taxas_cobrado,0) + COALESCE(u.taxas_cobrado,0) + COALESCE(c.taxas_cobrado,0)) AS valor_taxa,
            SUM(COALESCE(d.diarias_glosado,0)) AS glosa_diaria,
            SUM(COALESCE(a.honorarios_glosado,0) + COALESCE(u.honorarios_glosado,0) + COALESCE(c.honorarios_glosado,0)) AS glosa_honorarios,
            SUM(COALESCE(a.matmed_glosado,0) + COALESCE(u.matmed_glosado,0) + COALESCE(c.matmed_glosado,0)) AS glosa_matmed,
            SUM(COALESCE(a.sadt_glosado,0) + COALESCE(u.sadt_glosado,0) + COALESCE(c.sadt_glosado,0)) AS glosa_sadt,
            SUM(COALESCE(a.oxig_glosado,0) + COALESCE(u.oxig_glosado,0) + COALESCE(c.oxig_glosado,0)) AS glosa_oxig,
            SUM(COALESCE(a.taxas_glosado,0) + COALESCE(u.taxas_glosado,0) + COALESCE(c.taxas_glosado,0)) AS glosa_taxas
        FROM (
            SELECT
                ca.id_capeante,
                ca.fk_int_capeante,
                {$dateExpr} AS ref_date,
                ac.fk_hospital_int,
                ac.tipo_admissao_int,
                ac.modo_internacao_int,
                ac.fk_patologia_int,
                ac.grupo_patologia_int,
                ac.fk_patologia2,
                ac.internado_int,
                pa.sexo_pac,
                pa.idade_pac
            FROM tb_capeante ca
            INNER JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
            LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
        ) t
        LEFT JOIN (
            SELECT fk_capeante,
                SUM({$sumDiariasCobrado}) AS diarias_cobrado,
                SUM({$sumDiariasGlosa}) AS diarias_glosado
            FROM tb_cap_valores_diar
            GROUP BY fk_capeante
        ) d ON d.fk_capeante = t.id_capeante
        LEFT JOIN (
            SELECT fk_capeante,
                SUM({$sumHonorCobradoAp}) AS honorarios_cobrado,
                SUM({$sumHonorGlosaAp}) AS honorarios_glosado,
                SUM({$sumMatCobradoAp}) AS matmed_cobrado,
                SUM({$sumMatGlosaAp}) AS matmed_glosado,
                SUM({$sumSadtCobradoAp}) AS sadt_cobrado,
                SUM({$sumSadtGlosaAp}) AS sadt_glosado,
                SUM({$sumOxigCobradoAp}) AS oxig_cobrado,
                SUM({$sumOxigGlosaAp}) AS oxig_glosado,
                SUM({$sumTaxasCobradoAp}) AS taxas_cobrado,
                SUM({$sumTaxasGlosaAp}) AS taxas_glosado
            FROM tb_cap_valores_ap
            GROUP BY fk_capeante
        ) a ON a.fk_capeante = t.id_capeante
        LEFT JOIN (
            SELECT fk_capeante,
                SUM({$sumHonorCobradoUti}) AS honorarios_cobrado,
                SUM({$sumHonorGlosaUti}) AS honorarios_glosado,
                SUM({$sumMatCobradoUti}) AS matmed_cobrado,
                SUM({$sumMatGlosaUti}) AS matmed_glosado,
                SUM({$sumSadtCobradoUti}) AS sadt_cobrado,
                SUM({$sumSadtGlosaUti}) AS sadt_glosado,
                SUM({$sumOxigCobradoUti}) AS oxig_cobrado,
                SUM({$sumOxigGlosaUti}) AS oxig_glosado,
                SUM({$sumTaxasCobradoUti}) AS taxas_cobrado,
                SUM({$sumTaxasGlosaUti}) AS taxas_glosado
            FROM tb_cap_valores_uti
            GROUP BY fk_capeante
        ) u ON u.fk_capeante = t.id_capeante
        LEFT JOIN (
            SELECT fk_capeante,
                SUM({$sumHonorCobradoCc}) AS honorarios_cobrado,
                SUM({$sumHonorGlosaCc}) AS honorarios_glosado,
                SUM({$sumMatCobradoCc}) AS matmed_cobrado,
                SUM({$sumMatGlosaCc}) AS matmed_glosado,
                SUM({$sumSadtCobradoCc}) AS sadt_cobrado,
                SUM({$sumSadtGlosaCc}) AS sadt_glosado,
                SUM({$sumOxigCobradoCc}) AS oxig_cobrado,
                SUM({$sumOxigGlosaCc}) AS oxig_glosado,
                SUM({$sumTaxasCobradoCc}) AS taxas_cobrado,
                SUM({$sumTaxasGlosaCc}) AS taxas_glosado
            FROM tb_cap_valores_cc
            GROUP BY fk_capeante
        ) c ON c.fk_capeante = t.id_capeante
        LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = t.fk_int_capeante
        WHERE {$where}
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'valor_diarias' => (float)($row['valor_diarias'] ?? 0),
        'valor_honorarios' => (float)($row['valor_honorarios'] ?? 0),
        'valor_matmed' => (float)($row['valor_matmed'] ?? 0),
        'valor_sadt' => (float)($row['valor_sadt'] ?? 0),
        'valor_oxig' => (float)($row['valor_oxig'] ?? 0),
        'valor_taxa' => (float)($row['valor_taxa'] ?? 0),
        'glosa_diaria' => (float)($row['glosa_diaria'] ?? 0),
        'glosa_honorarios' => (float)($row['glosa_honorarios'] ?? 0),
        'glosa_matmed' => (float)($row['glosa_matmed'] ?? 0),
        'glosa_sadt' => (float)($row['glosa_sadt'] ?? 0),
        'glosa_oxig' => (float)($row['glosa_oxig'] ?? 0),
        'glosa_taxas' => (float)($row['glosa_taxas'] ?? 0),
    ];
}

$filtersSelected = [
    'ano' => $ano,
    'mes' => $mes,
    'hospital_id' => $hospitalId,
    'tipo_internacao' => $tipoInternação,
    'modo_internacao' => $modoInternação,
    'patologia_id' => $patologiaId,
    'grupo_patologia' => $grupoPatologia,
    'internado' => $internado,
    'uti' => $uti,
    'antecedente_id' => $antecedenteId,
    'sexo' => $sexo,
    'faixa_etaria' => $faixaEtaria,
];

$selInternação = internacao_stats($conn, $filtersSelected);
$selFinanceiro = financeiro_stats($conn, $filtersSelected);
$selCustos = custos_breakdown($conn, $filtersSelected);
$glosaMedPct = $selFinanceiro['valor_apresentado'] > 0 ? ($selFinanceiro['glosa_med'] / $selFinanceiro['valor_apresentado'] * 100) : 0.0;
$glosaEnfPct = $selFinanceiro['valor_apresentado'] > 0 ? ($selFinanceiro['glosa_enf'] / $selFinanceiro['valor_apresentado'] * 100) : 0.0;
$glosaTotalPct = $selFinanceiro['valor_apresentado'] > 0 ? ($selFinanceiro['glosa_total'] / $selFinanceiro['valor_apresentado'] * 100) : 0.0;
$custoMedioDiaria = $selInternação['total_diarias'] > 0 ? ($selFinanceiro['valor_apresentado'] / $selInternação['total_diarias']) : 0.0;

$alocSeries = [
    ['label' => 'Diárias', 'value' => $selCustos['valor_diarias'], 'color' => '#4c5bd3'],
    ['label' => 'Honorários', 'value' => $selCustos['valor_honorarios'], 'color' => '#d17aa4'],
    ['label' => 'Mat/Med', 'value' => $selCustos['valor_matmed'], 'color' => '#7395b6'],
    ['label' => 'SADT', 'value' => $selCustos['valor_sadt'], 'color' => '#7c3a56'],
    ['label' => 'Oxigenioterapia', 'value' => $selCustos['valor_oxig'], 'color' => '#1b7f86'],
];
$compSeries = [
    ['label' => 'Diárias', 'value' => $selCustos['valor_diarias'], 'color' => '#4c5bd3'],
    ['label' => 'Honorários', 'value' => $selCustos['valor_honorarios'], 'color' => '#d17aa4'],
    ['label' => 'Mat/Med', 'value' => $selCustos['valor_matmed'], 'color' => '#7395b6'],
    ['label' => 'SADT', 'value' => $selCustos['valor_sadt'], 'color' => '#7c3a56'],
    ['label' => 'Oxigenioterapia', 'value' => $selCustos['valor_oxig'], 'color' => '#1b7f86'],
    ['label' => 'Taxas', 'value' => $selCustos['valor_taxa'], 'color' => '#5f6c7b'],
];
$compTotal = array_sum(array_map(fn($s) => (float)$s['value'], $compSeries));
$compPercents = array_map(function ($s) use ($compTotal) {
    $pct = $compTotal > 0 ? ($s['value'] / $compTotal) * 100 : 0;
    return round($pct, 2);
}, $compSeries);
$glosaSeries = [
    ['label' => 'Glosa Diárias', 'value' => $selCustos['glosa_diaria'], 'color' => '#4c5bd3'],
    ['label' => 'Glosa Honorários', 'value' => $selCustos['glosa_honorarios'], 'color' => '#d17aa4'],
    ['label' => 'Glosa Mat/Med', 'value' => $selCustos['glosa_matmed'], 'color' => '#7c3a56'],
    ['label' => 'Glosa Oxigenioterapia', 'value' => $selCustos['glosa_oxig'], 'color' => '#7395b6'],
    ['label' => 'Glosa SADT', 'value' => $selCustos['glosa_sadt'], 'color' => '#1b7f86'],
    ['label' => 'Glosa Taxas', 'value' => $selCustos['glosa_taxas'], 'color' => '#5f6c7b'],
];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Consolidado Gestão</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters bi-filters-wrap bi-filters-compact" method="get">
        <div class="bi-filter">
            <label>Hospital</label>
            <select name="hospital_id">
                <option value="">Todos</option>
                <?php foreach ($hospitais as $h): ?>
                    <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                        <?= e($h['nome_hosp']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Internação</label>
            <select name="tipo_internacao">
                <option value="">Todos</option>
                <?php foreach ($tiposInt as $tipo): ?>
                    <option value="<?= e($tipo) ?>" <?= $tipoInternação === $tipo ? 'selected' : '' ?>>
                        <?= e($tipo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Modo internação</label>
            <select name="modo_internacao">
                <option value="">Todos</option>
                <?php foreach ($modos as $modo): ?>
                    <option value="<?= e($modo) ?>" <?= $modoInternação === $modo ? 'selected' : '' ?>>
                        <?= e($modo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Patologia</label>
            <select name="patologia_id">
                <option value="">Todos</option>
                <?php foreach ($patologias as $p): ?>
                    <option value="<?= (int)$p['id_patologia'] ?>" <?= $patologiaId == $p['id_patologia'] ? 'selected' : '' ?>>
                        <?= e($p['patologia_pat']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Grupo patologia</label>
            <select name="grupo_patologia">
                <option value="">Todos</option>
                <?php foreach ($grupos as $g): ?>
                    <option value="<?= e($g) ?>" <?= $grupoPatologia === $g ? 'selected' : '' ?>>
                        <?= e($g) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Internado</label>
            <select name="internado">
                <option value="">Todos</option>
                <option value="s" <?= $internado === 's' ? 'selected' : '' ?>>Sim</option>
                <option value="n" <?= $internado === 'n' ? 'selected' : '' ?>>Não</option>
            </select>
        </div>
        <div class="bi-filter">
            <label>Antecedente</label>
            <select name="antecedente_id">
                <option value="">Todos</option>
                <?php foreach ($antecedentes as $a): ?>
                    <option value="<?= (int)$a['id_antecedente'] ?>" <?= $antecedenteId == $a['id_antecedente'] ? 'selected' : '' ?>>
                        <?= e($a['antecedente_ant']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Sexo</label>
            <select name="sexo">
                <option value="">Todos</option>
                <option value="M" <?= $sexo === 'M' ? 'selected' : '' ?>>Masculino</option>
                <option value="F" <?= $sexo === 'F' ? 'selected' : '' ?>>Feminino</option>
            </select>
        </div>
        <div class="bi-filter">
            <label>Faixa etária</label>
            <select name="faixa_etaria">
                <option value="">Todos</option>
                <?php foreach ($faixasEtarias as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $faixaEtaria === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Ano</label>
            <select name="ano">
                <option value="">Todos</option>
                <?php foreach ($anos as $a): ?>
                    <option value="<?= (int)$a ?>" <?= (int)$ano === (int)$a ? 'selected' : '' ?>>
                        <?= (int)$a ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Mês</label>
            <select name="mes">
                <option value="">Todos</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $mes === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Internação UTI</label>
            <select name="uti">
                <option value="">Todos</option>
                <option value="s" <?= $uti === 's' ? 'selected' : '' ?>>Sim</option>
                <option value="n" <?= $uti === 'n' ? 'selected' : '' ?>>Não</option>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-layout" style="margin-top:16px;">
        <section class="bi-main bi-stack">
            <div class="bi-grid fixed-2">
                <div class="bi-panel">
                    <h3>Alocacao dos Custos</h3>
                    <div class="bi-chart"><canvas id="chartAlocacao"></canvas></div>
                </div>
                <div class="bi-panel">
                    <h3>Composicao do Custo (%)</h3>
                    <div class="bi-chart"><canvas id="chartComposicao"></canvas></div>
                </div>
            </div>

            <div class="bi-grid fixed-2">
                <div class="bi-panel">
                    <h3>Analise da Glosa</h3>
                    <div class="bi-chart"><canvas id="chartGlosa"></canvas></div>
                </div>
                <div class="bi-panel">
                    <h3>Glosa</h3>
                    <div class="bi-panel-compact" style="min-height:220px;">
                        <div class="text-muted">Sem dados para exibir</div>
                    </div>
                </div>
            </div>
        </section>

        <aside class="bi-sidebar bi-stack">
            <div class="bi-kpi kpi-berry">
                <small>Valor apresentado</small>
                <strong class="bi-kpi-big"><?= fmt_money($selFinanceiro['valor_apresentado']) ?></strong>
            </div>
            <div class="bi-kpi kpi-berry with-badge">
                <small>Glosa medica</small>
                <strong><?= fmt_money($selFinanceiro['glosa_med']) ?></strong>
                <span class="bi-kpi-badge"><?= fmt_num($glosaMedPct, 2) ?>%</span>
            </div>
            <div class="bi-kpi kpi-berry with-badge">
                <small>Glosa enfermagem</small>
                <strong><?= fmt_money($selFinanceiro['glosa_enf']) ?></strong>
                <span class="bi-kpi-badge"><?= fmt_num($glosaEnfPct, 2) ?>%</span>
            </div>
            <div class="bi-kpi kpi-berry with-badge">
                <small>Glosa total</small>
                <strong><?= fmt_money($selFinanceiro['glosa_total']) ?></strong>
                <span class="bi-kpi-badge"><?= fmt_num($glosaTotalPct, 2) ?>%</span>
            </div>
            <div class="bi-kpi kpi-berry">
                <small>Valor final</small>
                <strong><?= fmt_money($selFinanceiro['valor_final']) ?></strong>
            </div>
            <div class="bi-kpi kpi-berry">
                <small>Custo médio diária</small>
                <strong><?= fmt_money($custoMedioDiaria) ?></strong>
            </div>
        </aside>
    </div>
</div>

<script>
const alocSeries = <?= json_encode($alocSeries, JSON_UNESCAPED_UNICODE) ?>;
const compSeries = <?= json_encode($compSeries, JSON_UNESCAPED_UNICODE) ?>;
const compPercents = <?= json_encode($compPercents, JSON_UNESCAPED_UNICODE) ?>;
const glosaSeries = <?= json_encode($glosaSeries, JSON_UNESCAPED_UNICODE) ?>;

new Chart(document.getElementById('chartAlocacao'), {
  type: 'bar',
  data: {
    labels: ['Custos'],
    datasets: alocSeries.map(item => ({
      label: item.label,
      data: [item.value],
      backgroundColor: item.color
    }))
  },
  options: {
    plugins: { legend: { labels: { color: '#e8f1ff' } } },
    scales: {
      x: { stacked: true, ticks: { color: '#e8f1ff' }, grid: { display: false } },
      y: {
        stacked: true,
        ticks: {
          color: '#e8f1ff',
          callback: (value) => window.biMoneyTick ? window.biMoneyTick(value) : value
        },
        grid: { color: 'rgba(255,255,255,0.1)' }
      }
    }
  }
});

new Chart(document.getElementById('chartComposicao'), {
  type: 'doughnut',
  data: {
    labels: compSeries.map(item => item.label),
    datasets: [{
      data: compPercents,
      backgroundColor: compSeries.map(item => item.color)
    }]
  },
  options: {
    plugins: { legend: { position: 'left', labels: { color: '#e8f1ff' } } }
  }
});

new Chart(document.getElementById('chartGlosa'), {
  type: 'doughnut',
  data: {
    labels: glosaSeries.map(item => item.label),
    datasets: [{
      data: glosaSeries.map(item => item.value),
      backgroundColor: glosaSeries.map(item => item.color)
    }]
  },
  options: {
    plugins: { legend: { position: 'left', labels: { color: '#e8f1ff' } } }
  }
});
</script>

<?php require_once("templates/footer.php"); ?>

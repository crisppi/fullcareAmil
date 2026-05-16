<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

require_once __DIR__ . '/app/bi_cid_options.php';

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

function fmt_delta($value, int $decimals = 0): string
{
    $num = (float)$value;
    $sign = $num > 0 ? '+' : ($num < 0 ? '-' : '');
    return $sign . fmt_num(abs($num), $decimals);
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

$filterScope = [
    'ano' => $ano,
    'mes' => $mes,
    'hospital_id' => $hospitalId,
    'tipo_internacao' => $tipoInternação,
    'modo_internacao' => $modoInternação,
    'grupo_patologia' => $grupoPatologia,
    'internado' => $internado,
    'uti' => $uti,
    'antecedente_id' => $antecedenteId,
    'sexo' => $sexo,
    'faixa_etaria' => $faixaEtaria,
];

$hospitais = array_map(fn($r) => ['id_hospital' => $r['value'], 'nome_hosp' => $r['label']], bi_fetch_filter_options($conn, 'hospital', $filterScope));
$tiposInt = array_column(bi_fetch_filter_options($conn, 'tipo_internacao', $filterScope), 'label');
$modos = array_column(bi_fetch_filter_options($conn, 'modo_internacao', $filterScope), 'label');
$patologias = bi_fetch_cid_options($conn, $filterScope);
$grupos = array_column(bi_fetch_filter_options($conn, 'grupo_patologia', $filterScope), 'label');
$antecedentes = array_map(fn($r) => ['id_antecedente' => $r['value'], 'antecedente_ant' => $r['label']], bi_fetch_filter_options($conn, 'antecedente', $filterScope));
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
        $where .= " AND i.fk_cid_int = :patologia_id";
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
        $where .= " AND fk_cid_int = :patologia_id";
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

    $totalInternacoes = (int)($row['total_internacoes'] ?? 0);
    $totalDiarias = (int)($row['total_diarias'] ?? 0);
    $mp = $totalInternacoes > 0 ? ($totalDiarias / $totalInternacoes) : 0.0;

    return [
        'total_internacoes' => $totalInternacoes,
        'total_diarias' => $totalDiarias,
        'mp' => $mp,
    ];
}

function uti_stats(PDO $conn, array $filters): array
{
    if (($filters['uti'] ?? '') === 'n') {
        return ['total_internacoes' => 0, 'total_diarias' => 0, 'mp' => 0.0];
    }
    $filters['uti'] = 's';

    $params = [];
    [$where] = build_where_internacao($filters, $params, false);

    $sql = "
        SELECT
            COUNT(*) AS total_internacoes_uti,
            SUM(GREATEST(1, DATEDIFF(COALESCE(u.max_data_alta, CURDATE()), u.min_data_internacao) + 1)) AS total_diarias_uti
        FROM (
            SELECT
                u.fk_internacao_uti,
                MIN(NULLIF(u.data_internacao_uti, '0000-00-00')) AS min_data_internacao,
                MAX(NULLIF(u.data_alta_uti, '0000-00-00')) AS max_data_alta
            FROM tb_uti u
            WHERE u.data_internacao_uti IS NOT NULL AND u.data_internacao_uti <> '0000-00-00'
            GROUP BY u.fk_internacao_uti
        ) u
        INNER JOIN tb_internacao i ON i.id_internacao = u.fk_internacao_uti
        LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
        WHERE {$where}
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalInternacoes = (int)($row['total_internacoes_uti'] ?? 0);
    $totalDiarias = (int)($row['total_diarias_uti'] ?? 0);
    $mp = $totalInternacoes > 0 ? ($totalDiarias / $totalInternacoes) : 0.0;

    return [
        'total_internacoes' => $totalInternacoes,
        'total_diarias' => $totalDiarias,
        'mp' => $mp,
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
            SUM(COALESCE(t.valor_glosa_enf,0)) AS glosa_enf,
            COUNT(DISTINCT t.id_capeante) AS total_contas
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
                ac.fk_cid_int,
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
        'total_contas' => (int)($row['total_contas'] ?? 0),
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
$selUti = uti_stats($conn, $filtersSelected);
$selFinanceiro = financeiro_stats($conn, $filtersSelected);
$selFinanceiroUti = ($uti === 'n')
    ? ['valor_apresentado' => 0.0, 'total_contas' => 0]
    : financeiro_stats($conn, array_merge($filtersSelected, ['uti' => 's']));

if ($ano !== null) {
    $filtersPrev = $filtersSelected;
    $filtersPrev['ano'] = $ano - 1;
    $prevInternação = internacao_stats($conn, $filtersPrev);
    $prevUti = uti_stats($conn, $filtersPrev);
} else {
    $prevInternação = ['total_internacoes' => 0, 'total_diarias' => 0, 'mp' => 0.0];
    $prevUti = ['total_internacoes' => 0, 'total_diarias' => 0, 'mp' => 0.0];
}

$varInternacoes = $selInternação['total_internacoes'] - $prevInternação['total_internacoes'];
$varDiárias = $selInternação['total_diarias'] - $prevInternação['total_diarias'];
$varMp = $selInternação['mp'] - $prevInternação['mp'];
$varUtiInt = $selUti['total_internacoes'] - $prevUti['total_internacoes'];
$varUtiDiárias = $selUti['total_diarias'] - $prevUti['total_diarias'];
$varUtiMp = $selUti['mp'] - $prevUti['mp'];

$glosaMedPct = $selFinanceiro['valor_apresentado'] > 0 ? ($selFinanceiro['glosa_med'] / $selFinanceiro['valor_apresentado'] * 100) : 0.0;
$glosaEnfPct = $selFinanceiro['valor_apresentado'] > 0 ? ($selFinanceiro['glosa_enf'] / $selFinanceiro['valor_apresentado'] * 100) : 0.0;
$glosaTotalPct = $selFinanceiro['valor_apresentado'] > 0 ? ($selFinanceiro['glosa_total'] / $selFinanceiro['valor_apresentado'] * 100) : 0.0;

$custoMedioDiaria = $selInternação['total_diarias'] > 0 ? ($selFinanceiro['valor_apresentado'] / $selInternação['total_diarias']) : 0.0;
$custoMedioDiariaUti = $selUti['total_diarias'] > 0 ? ($selFinanceiroUti['valor_apresentado'] / $selUti['total_diarias']) : 0.0;
$custoMedioConta = $selFinanceiro['total_contas'] > 0 ? ($selFinanceiro['valor_apresentado'] / $selFinanceiro['total_contas']) : 0.0;
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme bi-consolidado-page">
    <div class="bi-header">
        <h1 class="bi-title">Consolidado Gestão Cards</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters bi-filters-wrap bi-filters-compact bi-consolidado-filters" method="get">
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
            <label>CID</label>
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
        <section class="bi-main">
            <div class="bi-grid fixed-3 bi-grid-kpi">
                <div class="bi-panel">
                    <h3 class="text-center">Ano Atual</h3>
                    <div class="bi-stack">
                        <div class="bi-kpi kpi-indigo"><small>Total internações</small><strong><?= fmt_num($selInternação['total_internacoes'], 0) ?></strong></div>
                        <div class="bi-kpi kpi-indigo"><small>Total de diárias</small><strong><?= fmt_num($selInternação['total_diarias'], 0) ?></strong></div>
                        <div class="bi-kpi kpi-indigo"><small>MP</small><strong><?= fmt_num($selInternação['mp'], 2) ?></strong></div>
                        <div class="bi-kpi kpi-indigo"><small>Internação UTI</small><strong><?= fmt_num($selUti['total_internacoes'], 0) ?></strong></div>
                        <div class="bi-kpi kpi-indigo"><small>Diárias UTI</small><strong><?= fmt_num($selUti['total_diarias'], 0) ?></strong></div>
                        <div class="bi-kpi kpi-indigo"><small>Média permanência UTI</small><strong><?= fmt_num($selUti['mp'], 2) ?></strong></div>
                    </div>
                </div>
                <div class="bi-panel">
                    <h3 class="text-center">Ano Anterior</h3>
                    <div class="bi-stack">
                        <div class="bi-kpi kpi-rose"><small>Total internações</small><strong><?= fmt_num($prevInternação['total_internacoes'], 0) ?></strong></div>
                        <div class="bi-kpi kpi-rose"><small>Total diárias (YTD)</small><strong><?= fmt_num($prevInternação['total_diarias'], 0) ?></strong></div>
                        <div class="bi-kpi kpi-rose"><small>MP - YTD</small><strong><?= fmt_num($prevInternação['mp'], 2) ?></strong></div>
                        <div class="bi-kpi kpi-rose"><small>Internação UTI</small><strong><?= fmt_num($prevUti['total_internacoes'], 0) ?></strong></div>
                        <div class="bi-kpi kpi-rose"><small>Diárias UTI</small><strong><?= fmt_num($prevUti['total_diarias'], 0) ?></strong></div>
                        <div class="bi-kpi kpi-rose"><small>Média permanência UTI</small><strong><?= fmt_num($prevUti['mp'], 2) ?></strong></div>
                    </div>
                </div>
                <div class="bi-panel">
                    <h3 class="text-center">Variação</h3>
                    <div class="bi-stack">
                        <div class="bi-kpi kpi-steel"><small>Total internações</small><strong><?= fmt_delta($varInternacoes, 0) ?></strong></div>
                        <div class="bi-kpi kpi-steel"><small>Total de diárias</small><strong><?= fmt_delta($varDiárias, 0) ?></strong></div>
                        <div class="bi-kpi kpi-steel"><small>Media de permanencia</small><strong><?= fmt_delta($varMp, 2) ?></strong></div>
                        <div class="bi-kpi kpi-steel"><small>Internação UTI</small><strong><?= fmt_delta($varUtiInt, 0) ?></strong></div>
                        <div class="bi-kpi kpi-steel"><small>Diárias UTI</small><strong><?= fmt_delta($varUtiDiárias, 0) ?></strong></div>
                        <div class="bi-kpi kpi-steel"><small>Média permanência UTI</small><strong><?= fmt_delta($varUtiMp, 2) ?></strong></div>
                    </div>
                </div>
            </div>
        </section>

        <aside class="bi-sidebar bi-stack">
            <div class="bi-kpi kpi-berry"><small>Valor apresentado</small><strong><?= fmt_money($selFinanceiro['valor_apresentado']) ?></strong></div>
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
            <div class="bi-kpi kpi-berry"><small>Valor final</small><strong><?= fmt_money($selFinanceiro['valor_final']) ?></strong></div>
            <div class="bi-kpi kpi-teal"><small>Custo médio diária</small><strong><?= fmt_money($custoMedioDiaria) ?></strong></div>
            <div class="bi-kpi kpi-indigo"><small>Custo médio diária UTI</small><strong><?= fmt_money($custoMedioDiariaUti) ?></strong></div>
            <div class="bi-kpi kpi-amber"><small>Total de contas</small><strong><?= fmt_num($selFinanceiro['total_contas'], 0) ?></strong></div>
            <div class="bi-kpi kpi-indigo"><small>Custo médio por conta</small><strong><?= fmt_money($custoMedioConta) ?></strong></div>
        </aside>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>

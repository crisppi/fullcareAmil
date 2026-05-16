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

function fmt_value($value, string $kind, int $decimals = 2): string
{
    if ($kind === 'money') {
        return fmt_money($value);
    }
    return fmt_num($value, $decimals);
}

function compare_metric(string $label, float $sel, float $global, string $better, string $kind, int $decimals = 2): array
{
    $delta = $sel - $global;
    $pct = $global != 0.0 ? ($delta / $global) * 100 : null;
    $isBetter = $better === 'higher' ? ($sel >= $global) : ($sel <= $global);
    return [
        'label' => $label,
        'sel' => $sel,
        'global' => $global,
        'delta' => $delta,
        'pct' => $pct,
        'isBetter' => $isBetter,
        'kind' => $kind,
        'decimals' => $decimals,
    ];
}

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mesInput = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = ($mesInput !== null && $mesInput !== false) ? (int)$mesInput : null;
if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = (int)date('Y');
}

$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$tipoInternacao = trim((string)(filter_input(INPUT_GET, 'tipo_internacao') ?? ''));
$modoInternacao = trim((string)(filter_input(INPUT_GET, 'modo_internacao') ?? ''));
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
    'tipo_internacao' => $tipoInternacao,
    'modo_internacao' => $modoInternacao,
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
        if (($filters['uti'] ?? '') === 's') {
            $where .= " AND fk_internacao_uti IS NOT NULL";
        } elseif (($filters['uti'] ?? '') === 'n') {
            $where .= " AND fk_internacao_uti IS NULL";
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
        if (($filters['uti'] ?? '') === 's') {
            $where .= " AND fk_internacao_uti IS NOT NULL";
        } elseif (($filters['uti'] ?? '') === 'n') {
            $where .= " AND fk_internacao_uti IS NULL";
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
    return internacao_stats($conn, $filters);
}

function financeiro_stats(PDO $conn, array $filters): array
{
    $params = [];
    $where = build_where_financeiro($filters, $params, true);

    $sql = "
        SELECT
            SUM(COALESCE(t.valor_apresentado_capeante,0)) AS valor_apresentado,
            SUM(COALESCE(t.valor_glosa_total,0)) AS glosa_total,
            SUM(COALESCE(t.valor_glosa_med,0)) AS glosa_med,
            SUM(COALESCE(t.valor_glosa_enf,0)) AS glosa_enf,
            COUNT(DISTINCT t.id_internacao) AS total_contas
        FROM (
            SELECT
                i.id_internacao,
                i.data_intern_int AS ref_date,
                i.fk_hospital_int,
                i.tipo_admissao_int,
                i.modo_internacao_int,
                i.fk_cid_int,
                i.fk_patologia_int,
                i.grupo_patologia_int,
                i.fk_patologia2,
                i.internado_int,
                pa.sexo_pac,
                pa.idade_pac,
                ca.valor_apresentado_capeante,
                ca.valor_glosa_total,
                ca.valor_glosa_med,
                ca.valor_glosa_enf,
                ut.fk_internacao_uti
            FROM tb_internacao i
            LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
            LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
            LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao
        ) t
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
    'tipo_internacao' => $tipoInternacao,
    'modo_internacao' => $modoInternacao,
    'patologia_id' => $patologiaId,
    'grupo_patologia' => $grupoPatologia,
    'internado' => $internado,
    'uti' => $uti,
    'antecedente_id' => $antecedenteId,
    'sexo' => $sexo,
    'faixa_etaria' => $faixaEtaria,
];
$filtersGlobal = [
    'ano' => $ano,
    'mes' => $mes,
];

$selInternacao = internacao_stats($conn, $filtersSelected);
$selUti = uti_stats($conn, $filtersSelected);
$selFinanceiro = financeiro_stats($conn, $filtersSelected);
$selFinanceiroUti = financeiro_stats($conn, array_merge($filtersSelected, ['uti' => 's']));

$selCustoMedioDiaria = $selInternacao['total_diarias'] > 0 ? ($selFinanceiro['valor_apresentado'] / $selInternacao['total_diarias']) : 0.0;
$selCustoMedioDiariaUti = $selUti['total_diarias'] > 0 ? ($selFinanceiroUti['valor_apresentado'] / $selUti['total_diarias']) : 0.0;
$selCustoMedioConta = $selFinanceiro['total_contas'] > 0 ? ($selFinanceiro['valor_apresentado'] / $selFinanceiro['total_contas']) : 0.0;

$globalInternacao = internacao_stats($conn, $filtersGlobal);
$globalUti = uti_stats($conn, $filtersGlobal);
$globalFinanceiro = financeiro_stats($conn, $filtersGlobal);
$globalFinanceiroUti = financeiro_stats($conn, array_merge($filtersGlobal, ['uti' => 's']));

$globalCustoMedioDiaria = $globalInternacao['total_diarias'] > 0 ? ($globalFinanceiro['valor_apresentado'] / $globalInternacao['total_diarias']) : 0.0;
$globalCustoMedioDiariaUti = $globalUti['total_diarias'] > 0 ? ($globalFinanceiroUti['valor_apresentado'] / $globalUti['total_diarias']) : 0.0;
$globalCustoMedioConta = $globalFinanceiro['total_contas'] > 0 ? ($globalFinanceiro['valor_apresentado'] / $globalFinanceiro['total_contas']) : 0.0;

$hasSelection = false;
foreach ([
    'hospital_id', 'tipo_internacao', 'modo_internacao', 'patologia_id', 'grupo_patologia',
    'internado', 'uti', 'antecedente_id', 'sexo', 'faixa_etaria', 'ano', 'mes'
] as $key) {
    if (filter_has_var(INPUT_GET, $key)) {
        $value = trim((string)($_GET[$key] ?? ''));
        if ($value !== '') {
            $hasSelection = true;
            break;
        }
    }
}

$comparisons = [
    compare_metric('Custo médio diária', $selCustoMedioDiaria, $globalCustoMedioDiaria, 'lower', 'money'),
    compare_metric('MP', $selInternacao['mp'], $globalInternacao['mp'], 'lower', 'number'),
    compare_metric('Custo médio diária UTI', $selCustoMedioDiariaUti, $globalCustoMedioDiariaUti, 'lower', 'money'),
    compare_metric('Internação UTI (MP)', $selUti['mp'], $globalUti['mp'], 'lower', 'number'),
    compare_metric('Custo médio por conta', $selCustoMedioConta, $globalCustoMedioConta, 'lower', 'money'),
    compare_metric('Valor apresentado', $selFinanceiro['valor_apresentado'], $globalFinanceiro['valor_apresentado'], 'lower', 'money'),
];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-bi-layout-3">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>
<style>
.bi-header {
    position: relative;
}
.bi-header-actions.bi-header-floating {
    position: absolute;
    right: 0;
    top: 0;
}
.bi-wrapper .bi-grid-3x3-gap {
    display: none !important;
}
.bi-nav-icon svg {
    width: 16px;
    height: 16px;
}
.bi-nav-icon svg circle {
    fill: currentColor;
}
.bi-compare-panel {
    margin-top: 16px;
}
.bi-compare-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 12px;
}
.bi-compare-card {
    position: relative;
    overflow: hidden;
    background: linear-gradient(145deg, rgba(17, 48, 73, 0.78), rgba(18, 78, 105, 0.46));
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 10px;
    padding: 14px 15px 13px;
    box-shadow: 0 12px 24px rgba(5, 28, 47, 0.18);
}
.bi-compare-card::before {
    content: "";
    position: absolute;
    inset: 0 auto 0 0;
    width: 4px;
    background: rgba(255, 255, 255, 0.2);
}
.bi-compare-card.is-better {
    border-color: rgba(90, 224, 138, 0.85);
    box-shadow: 0 0 0 1px rgba(90, 224, 138, 0.22), 0 12px 24px rgba(5, 28, 47, 0.18);
}
.bi-compare-card.is-better::before {
    background: #5ae08a;
}
.bi-compare-card.is-worse {
    border-color: rgba(255, 120, 120, 0.85);
    box-shadow: 0 0 0 1px rgba(255, 120, 120, 0.22), 0 12px 24px rgba(5, 28, 47, 0.18);
}
.bi-compare-card.is-worse::before {
    background: #ff7a7a;
}
.bi-compare-title {
    font-weight: 600;
    color: #eaf6ff;
    margin-bottom: 8px;
}
.bi-compare-values {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 0.85rem;
    color: rgba(234, 246, 255, 0.85);
}
.bi-compare-delta {
    margin-top: 8px;
    font-weight: 700;
    font-size: 0.95rem;
}
.bi-compare-delta.is-better {
    color: #5ae08a;
}
.bi-compare-delta.is-worse {
    color: #ff7a7a;
}
.bi-strategy-summary {
    align-items: stretch;
}
.bi-strategy-card {
    padding: 14px;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.11), rgba(255, 255, 255, 0.055));
}
.bi-strategy-card-selected {
    border-color: rgba(126, 215, 218, 0.52);
}
.bi-strategy-card-global {
    border-color: rgba(255, 174, 201, 0.48);
}
.bi-strategy-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px !important;
    text-align: left !important;
    letter-spacing: 0.02em;
}
.bi-strategy-heading::after {
    content: "";
    flex: 1;
    height: 1px;
    margin-left: 12px;
    background: linear-gradient(90deg, rgba(234, 246, 255, 0.28), transparent);
}
.bi-strategy-kpis {
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 10px;
    justify-items: stretch !important;
}
.bi-strategy-kpi.bi-kpi.kpi-compact {
    position: relative;
    max-width: none;
    min-height: 78px;
    padding: 12px 12px 11px;
    text-align: left;
    border-radius: 10px;
    border-color: rgba(255, 255, 255, 0.2);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.16), 0 10px 20px rgba(6, 31, 51, 0.18);
}
.bi-strategy-card-selected .bi-strategy-kpi {
    background: linear-gradient(145deg, rgba(46, 94, 176, 0.92), rgba(42, 158, 155, 0.42));
}
.bi-strategy-card-global .bi-strategy-kpi {
    background: linear-gradient(145deg, rgba(139, 58, 88, 0.9), rgba(89, 55, 126, 0.48));
}
.bi-strategy-kpi small {
    color: rgba(234, 246, 255, 0.68);
    font-size: 0.56rem;
    line-height: 1.25;
}
.bi-strategy-kpi strong {
    color: #ffffff;
    font-size: clamp(0.98rem, 1.15vw, 1.18rem);
    line-height: 1.15;
    margin-top: 8px;
}
@media (max-width: 1320px) {
    .bi-strategy-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}
@media (max-width: 760px) {
    .bi-strategy-kpis {
        grid-template-columns: 1fr !important;
    }
}
</style>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Estratégia Terapêutica</h1>
        <div class="bi-header-actions bi-header-floating">
            <div class="text-end text-muted"></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <svg viewBox="0 0 16 16" aria-hidden="true">
                    <circle cx="3" cy="3" r="1.2"></circle>
                    <circle cx="8" cy="3" r="1.2"></circle>
                    <circle cx="13" cy="3" r="1.2"></circle>
                    <circle cx="3" cy="8" r="1.2"></circle>
                    <circle cx="8" cy="8" r="1.2"></circle>
                    <circle cx="13" cy="8" r="1.2"></circle>
                    <circle cx="3" cy="13" r="1.2"></circle>
                    <circle cx="8" cy="13" r="1.2"></circle>
                    <circle cx="13" cy="13" r="1.2"></circle>
                </svg>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters bi-filters-wrap bi-filters-compact bi-strategy-filters" method="get">
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
                    <option value="<?= e($tipo) ?>" <?= $tipoInternacao === $tipo ? 'selected' : '' ?>>
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
                    <option value="<?= e($modo) ?>" <?= $modoInternacao === $modo ? 'selected' : '' ?>>
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
                <?php foreach ($grupos as $grupo): ?>
                    <option value="<?= e($grupo) ?>" <?= $grupoPatologia === $grupo ? 'selected' : '' ?>>
                        <?= e($grupo) ?>
                    </option>
                <?php endforeach; ?>
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
        <div class="bi-filter">
            <label>Antecedente</label>
            <select name="antecedente_id">
                <option value="">Todos</option>
                <?php foreach ($antecedentes as $ant): ?>
                    <option value="<?= (int)$ant['id_antecedente'] ?>" <?= $antecedenteId == $ant['id_antecedente'] ? 'selected' : '' ?>>
                        <?= e($ant['antecedente_ant']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Sexo</label>
            <select name="sexo">
                <option value="">Todos</option>
                <option value="F" <?= $sexo === 'F' ? 'selected' : '' ?>>F</option>
                <option value="M" <?= $sexo === 'M' ? 'selected' : '' ?>>M</option>
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
                <?php foreach ($anos as $anoOpt): ?>
                    <option value="<?= (int)$anoOpt ?>" <?= $ano == $anoOpt ? 'selected' : '' ?>>
                        <?= (int)$anoOpt ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Mês</label>
            <select name="mes">
                <option value="">Todos</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $mes == $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel" style="margin-top:16px; text-align:center;">
        <div style="font-weight:600; letter-spacing:0.04em;">
            Selecione os filtros para definir qual é a melhor estratégia terapêutica para determinado
            caso e onde poderá obter melhores resultados assistenciais.
        </div>
    </div>

    <div class="bi-grid fixed-2 bi-strategy-summary" style="margin-top:16px;">
        <div class="bi-panel bi-strategy-card bi-strategy-card-selected">
            <h3 class="bi-strategy-heading">Selecionado</h3>
            <div class="bi-kpis kpi-compact bi-strategy-kpis">
                <div class="bi-kpi kpi-indigo kpi-compact bi-strategy-kpi"><small>Total internações</small><strong><?= fmt_num($selInternacao['total_internacoes'], 0) ?></strong></div>
                <div class="bi-kpi kpi-indigo kpi-compact bi-strategy-kpi"><small>Custo médio diária</small><strong><?= fmt_money($selCustoMedioDiaria) ?></strong></div>
                <div class="bi-kpi kpi-indigo kpi-compact bi-strategy-kpi"><small>MP</small><strong><?= fmt_num($selInternacao['mp'], 2) ?></strong></div>
                <div class="bi-kpi kpi-indigo kpi-compact bi-strategy-kpi"><small>Custo médio diária UTI</small><strong><?= fmt_money($selCustoMedioDiariaUti) ?></strong></div>
                <div class="bi-kpi kpi-indigo kpi-compact bi-strategy-kpi"><small>Internação UTI</small><strong><?= fmt_num($selUti['total_internacoes'], 0) ?></strong></div>
                <div class="bi-kpi kpi-indigo kpi-compact bi-strategy-kpi"><small>Custo médio por conta</small><strong><?= fmt_money($selCustoMedioConta) ?></strong></div>
                <div class="bi-kpi kpi-indigo kpi-compact bi-strategy-kpi"><small>Média permanência UTI</small><strong><?= fmt_num($selUti['mp'], 2) ?></strong></div>
                <div class="bi-kpi kpi-indigo kpi-compact bi-strategy-kpi"><small>Valor apresentado</small><strong><?= fmt_money($selFinanceiro['valor_apresentado']) ?></strong></div>
            </div>
        </div>
        <div class="bi-panel bi-strategy-card bi-strategy-card-global">
            <h3 class="bi-strategy-heading">Global</h3>
            <div class="bi-kpis kpi-compact bi-strategy-kpis">
                <div class="bi-kpi kpi-rose kpi-compact bi-strategy-kpi"><small>Total internações</small><strong><?= fmt_num($globalInternacao['total_internacoes'], 0) ?></strong></div>
                <div class="bi-kpi kpi-rose kpi-compact bi-strategy-kpi"><small>Custo médio diária</small><strong><?= fmt_money($globalCustoMedioDiaria) ?></strong></div>
                <div class="bi-kpi kpi-rose kpi-compact bi-strategy-kpi"><small>MP</small><strong><?= fmt_num($globalInternacao['mp'], 2) ?></strong></div>
                <div class="bi-kpi kpi-rose kpi-compact bi-strategy-kpi"><small>Custo médio diária UTI</small><strong><?= fmt_money($globalCustoMedioDiariaUti) ?></strong></div>
                <div class="bi-kpi kpi-rose kpi-compact bi-strategy-kpi"><small>Internação UTI</small><strong><?= fmt_num($globalUti['total_internacoes'], 0) ?></strong></div>
                <div class="bi-kpi kpi-rose kpi-compact bi-strategy-kpi"><small>Custo médio por conta</small><strong><?= fmt_money($globalCustoMedioConta) ?></strong></div>
                <div class="bi-kpi kpi-rose kpi-compact bi-strategy-kpi"><small>Média permanência UTI</small><strong><?= fmt_num($globalUti['mp'], 2) ?></strong></div>
                <div class="bi-kpi kpi-rose kpi-compact bi-strategy-kpi"><small>Valor apresentado</small><strong><?= fmt_money($globalFinanceiro['valor_apresentado']) ?></strong></div>
            </div>
        </div>
    </div>

    <?php if ($hasSelection): ?>
        <div class="bi-panel bi-compare-panel">
            <h3 style="margin-bottom:12px;">Comparativo Selecionado vs Global</h3>
            <div class="bi-compare-grid">
                <?php foreach ($comparisons as $comp): ?>
                    <?php
                    $deltaLabel = ($comp['delta'] >= 0 ? '+' : '') . fmt_value($comp['delta'], $comp['kind'], $comp['decimals']);
                    $pctLabel = $comp['pct'] !== null ? ' (' . ($comp['pct'] >= 0 ? '+' : '') . fmt_num($comp['pct'], 1) . '%)' : '';
                    $statusClass = $comp['isBetter'] ? 'is-better' : 'is-worse';
                    ?>
                    <div class="bi-compare-card <?= $statusClass ?>">
                        <div class="bi-compare-title"><?= e($comp['label']) ?></div>
                        <div class="bi-compare-values">
                            <span>Selecionado: <?= e(fmt_value($comp['sel'], $comp['kind'], $comp['decimals'])) ?></span>
                            <span>Global: <?= e(fmt_value($comp['global'], $comp['kind'], $comp['decimals'])) ?></span>
                        </div>
                        <div class="bi-compare-delta <?= $statusClass ?>">
                            <?= e($deltaLabel . $pctLabel) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once("templates/footer.php"); ?>

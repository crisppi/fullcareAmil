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

function fmtIntBI($v): string
{
    return number_format((int)$v, 0, ',', '.');
}

function fmtMoneyBI($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

function fmtPctBI($v): string
{
    return number_format((float)$v, 1, ',', '.') . '%';
}

function bindBI(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

function fetchAllBI(PDO $conn, string $sql, array $params = []): array
{
    $stmt = $conn->prepare($sql);
    bindBI($stmt, $params);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fetchOneBI(PDO $conn, string $sql, array $params = []): array
{
    $rows = fetchAllBI($conn, $sql, $params);
    return $rows[0] ?? [];
}

function labelsValuesBI(array $rows): array
{
    return [
        array_map(fn($r) => (string)($r['label'] ?? 'Sem informacoes'), $rows),
        array_map(fn($r) => (float)($r['total'] ?? 0), $rows),
    ];
}

function moneyToNumberBI(string $expr): string
{
    return "CAST(REPLACE(REPLACE(REPLACE(COALESCE({$expr},'0'), 'R$', ''), '.', ''), ',', '.') AS DECIMAL(15,2))";
}

$panel = trim((string)(filter_input(INPUT_GET, 'painel') ?? 'prorrogacoes'));
$allowedPanels = [
    'prorrogacoes',
    'tuss-autorizacoes',
    'oportunidades-clinicas',
    'desfechos-alta',
    'negociacao-avancada',
    'seguranca-eventos',
];
if (!in_array($panel, $allowedPanels, true)) {
    $panel = 'prorrogacoes';
}

$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: 0;
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
if (!$ano && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = (int)date('Y');
}

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);

$filters = [
    'ano' => $ano,
    'mes' => $mes,
    'hospital_id' => $hospitalId,
];

function periodWhereBI(string $dateExpr, array $filters, array &$params): string
{
    $where = "{$dateExpr} IS NOT NULL AND {$dateExpr} <> '' AND {$dateExpr} <> '0000-00-00'";
    if (!empty($filters['ano'])) {
        $where .= " AND YEAR({$dateExpr}) = :ano";
        $params[':ano'] = (int)$filters['ano'];
    }
    if (!empty($filters['mes'])) {
        $where .= " AND MONTH({$dateExpr}) = :mes";
        $params[':mes'] = (int)$filters['mes'];
    }
    if (!empty($filters['hospital_id'])) {
        $where .= " AND i.fk_hospital_int = :hospital_id";
        $params[':hospital_id'] = (int)$filters['hospital_id'];
    }
    return $where;
}

function buildProrrogacoesBI(PDO $conn, array $filters): array
{
    $params = [];
    $where = periodWhereBI('pr.prorrog1_ini_pror', $filters, $params);
    $base = "
        FROM tb_prorrogacao pr
        JOIN tb_internacao i ON i.id_internacao = pr.fk_internacao_pror
        LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
        LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
        WHERE {$where}
    ";
    $summary = fetchOneBI($conn, "
        SELECT COUNT(*) AS prorrogacoes,
               SUM(CAST(COALESCE(NULLIF(pr.diarias_1,''),'0') AS UNSIGNED)) AS diarias,
               COUNT(DISTINCT pr.fk_internacao_pror) AS internacoes,
               SUM(CASE WHEN COALESCE(pr.isol_1_pror,'n') = 's' THEN 1 ELSE 0 END) AS isolamento
        {$base}
    ", $params);
    $diarias = (int)($summary['diarias'] ?? 0);
    $prorrogacoes = (int)($summary['prorrogacoes'] ?? 0);
    $charts = [
        ['id' => 'chartHosp', 'title' => 'Frequência por hospital', 'rows' => fetchAllBI($conn, "SELECT COALESCE(NULLIF(h.nome_hosp,''),'Sem hospital') AS label, COUNT(*) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(141, 208, 255, 0.75)'],
        ['id' => 'chartPat', 'title' => 'Prorrogação por patologia', 'rows' => fetchAllBI($conn, "SELECT COALESCE(NULLIF(p.patologia_pat,''), NULLIF(i.grupo_patologia_int,''), 'Sem patologia') AS label, COUNT(*) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(111, 223, 194, 0.75)'],
        ['id' => 'chartMes', 'title' => 'Tendência mensal', 'rows' => fetchAllBI($conn, "SELECT DATE_FORMAT(pr.prorrog1_ini_pror, '%m/%Y') AS label, COUNT(*) AS total {$base} GROUP BY YEAR(pr.prorrog1_ini_pror), MONTH(pr.prorrog1_ini_pror) ORDER BY YEAR(pr.prorrog1_ini_pror), MONTH(pr.prorrog1_ini_pror)", $params), 'color' => 'rgba(255, 198, 108, 0.75)'],
    ];
    $table = fetchAllBI($conn, "
        SELECT COALESCE(NULLIF(h.nome_hosp,''),'Sem hospital') AS hospital,
               COALESCE(NULLIF(p.patologia_pat,''), NULLIF(i.grupo_patologia_int,''), 'Sem patologia') AS patologia,
               COUNT(*) AS prorrogacoes,
               SUM(CAST(COALESCE(NULLIF(pr.diarias_1,''),'0') AS UNSIGNED)) AS diarias
        {$base}
        GROUP BY h.id_hospital, patologia
        ORDER BY prorrogacoes DESC, diarias DESC
        LIMIT 80
    ", $params);
    return [
        'title' => 'BI Prorrogações',
        'subtitle' => 'Diárias prorrogadas, frequência, isolamento e concentração por hospital/patologia.',
        'kpis' => [
            ['label' => 'Prorrogações', 'value' => fmtIntBI($prorrogacoes), 'icon' => 'bi-calendar-plus', 'variant' => 'kpi-card-v2-1', 'hint' => 'Registros no período'],
            ['label' => 'Diárias prorrogadas', 'value' => fmtIntBI($diarias), 'icon' => 'bi-moon-stars', 'variant' => 'kpi-card-v2-2', 'hint' => 'Total informado'],
            ['label' => 'Internações', 'value' => fmtIntBI($summary['internacoes'] ?? 0), 'icon' => 'bi-hospital', 'variant' => 'kpi-card-v2-3', 'hint' => 'Casos únicos'],
            ['label' => 'Isolamento', 'value' => fmtIntBI($summary['isolamento'] ?? 0), 'icon' => 'bi-shield-check', 'variant' => 'kpi-card-v2-4', 'hint' => 'Com isolamento'],
        ],
        'charts' => $charts,
        'tableTitle' => 'Casos com muitas prorrogações',
        'tableHeaders' => ['Hospital', 'Patologia', 'Prorrogações', 'Diárias'],
        'tableRows' => array_map(fn($r) => [e($r['hospital']), e($r['patologia']), fmtIntBI($r['prorrogacoes']), fmtIntBI($r['diarias'])], $table),
    ];
}

function buildTussBI(PDO $conn, array $filters): array
{
    $params = [];
    $dateExpr = "COALESCE(NULLIF(t.data_realizacao_tuss,''), NULLIF(t.data_create_tuss,''))";
    $where = periodWhereBI($dateExpr, $filters, $params);
    $base = "
        FROM tb_tuss t
        JOIN tb_internacao i ON i.id_internacao = t.fk_int_tuss
        LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
        WHERE {$where}
    ";
    $qtdSol = "CAST(COALESCE(NULLIF(t.qtd_tuss_solicitado,''),'0') AS DECIMAL(12,2))";
    $qtdLib = "CAST(COALESCE(NULLIF(t.qtd_tuss_liberado,''),'0') AS DECIMAL(12,2))";
    $glosa = "CAST(COALESCE(NULLIF(t.glosa_tuss,''),'0') AS DECIMAL(12,2))";
    $summary = fetchOneBI($conn, "
        SELECT COUNT(*) AS registros, SUM({$qtdSol}) AS solicitado, SUM({$qtdLib}) AS liberado,
               SUM(GREATEST({$qtdSol} - {$qtdLib}, 0)) AS negado, SUM({$glosa}) AS glosa
        {$base}
    ", $params);
    $charts = [
        ['id' => 'chartSolLib', 'title' => 'Solicitado vs liberado', 'rows' => [['label' => 'Solicitado', 'total' => $summary['solicitado'] ?? 0], ['label' => 'Liberado', 'total' => $summary['liberado'] ?? 0], ['label' => 'Negado', 'total' => $summary['negado'] ?? 0]], 'color' => 'rgba(141, 208, 255, 0.75)'],
        ['id' => 'chartProc', 'title' => 'Procedimentos mais solicitados', 'rows' => fetchAllBI($conn, "SELECT COALESCE(NULLIF(t.tuss_solicitado,''),'Sem informacoes') AS label, SUM({$qtdSol}) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(111, 223, 194, 0.75)'],
        ['id' => 'chartHosp', 'title' => 'Hospitais com maior divergência', 'rows' => fetchAllBI($conn, "SELECT COALESCE(NULLIF(h.nome_hosp,''),'Sem hospital') AS label, SUM(GREATEST({$qtdSol} - {$qtdLib}, 0)) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(255, 198, 108, 0.75)'],
    ];
    $table = fetchAllBI($conn, "
        SELECT COALESCE(NULLIF(h.nome_hosp,''),'Sem hospital') AS hospital,
               COALESCE(NULLIF(t.tuss_solicitado,''),'Sem informacoes') AS procedimento,
               SUM({$qtdSol}) AS solicitado, SUM({$qtdLib}) AS liberado,
               SUM(GREATEST({$qtdSol} - {$qtdLib}, 0)) AS negado, SUM({$glosa}) AS glosa
        {$base}
        GROUP BY h.id_hospital, procedimento
        ORDER BY negado DESC, glosa DESC
        LIMIT 80
    ", $params);
    return [
        'title' => 'BI TUSS / Autorizações',
        'subtitle' => 'Solicitado vs liberado, glosa, negativas e divergências por hospital.',
        'kpis' => [
            ['label' => 'Registros', 'value' => fmtIntBI($summary['registros'] ?? 0), 'icon' => 'bi-card-checklist', 'variant' => 'kpi-card-v2-1', 'hint' => 'Itens TUSS'],
            ['label' => 'Solicitado', 'value' => fmtIntBI($summary['solicitado'] ?? 0), 'icon' => 'bi-upload', 'variant' => 'kpi-card-v2-2', 'hint' => 'Quantidade total'],
            ['label' => 'Liberado', 'value' => fmtIntBI($summary['liberado'] ?? 0), 'icon' => 'bi-check2-circle', 'variant' => 'kpi-card-v2-3', 'hint' => 'Quantidade total'],
            ['label' => 'Negado', 'value' => fmtIntBI($summary['negado'] ?? 0), 'icon' => 'bi-x-octagon', 'variant' => 'kpi-card-v2-4', 'hint' => 'Diferença solicitada'],
        ],
        'charts' => $charts,
        'tableTitle' => 'Divergência por hospital/procedimento',
        'tableHeaders' => ['Hospital', 'Procedimento', 'Solicitado', 'Liberado', 'Negado', 'Glosa'],
        'tableRows' => array_map(fn($r) => [e($r['hospital']), e($r['procedimento']), fmtIntBI($r['solicitado']), fmtIntBI($r['liberado']), fmtIntBI($r['negado']), fmtMoneyBI($r['glosa'])], $table),
    ];
}

function buildOportunidadesBI(PDO $conn, array $filters): array
{
    $params = [];
    $where = periodWhereBI('v.data_visita_vis', $filters, $params);
    $where .= " AND (COALESCE(v.oportunidades_enf,'') <> '' OR COALESCE(v.acoes_int_vis,'') <> '' OR COALESCE(v.programacao_enf,'') <> '')";
    $base = "
        FROM tb_visita v
        JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
        LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
        LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
        WHERE {$where}
    ";
    $summary = fetchOneBI($conn, "
        SELECT COUNT(*) AS visitas,
               SUM(CASE WHEN COALESCE(v.oportunidades_enf,'') <> '' THEN 1 ELSE 0 END) AS oportunidades,
               SUM(CASE WHEN COALESCE(v.acoes_int_vis,'') <> '' THEN 1 ELSE 0 END) AS acoes,
               SUM(CASE WHEN COALESCE(v.programacao_enf,'') <> '' THEN 1 ELSE 0 END) AS programacoes
        {$base}
    ", $params);
    $charts = [
        ['id' => 'chartHosp', 'title' => 'Ações por hospital', 'rows' => fetchAllBI($conn, "SELECT COALESCE(NULLIF(h.nome_hosp,''),'Sem hospital') AS label, SUM(CASE WHEN COALESCE(v.acoes_int_vis,'') <> '' THEN 1 ELSE 0 END) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(141, 208, 255, 0.75)'],
        ['id' => 'chartPat', 'title' => 'Recorrência por patologia', 'rows' => fetchAllBI($conn, "SELECT COALESCE(NULLIF(p.patologia_pat,''), NULLIF(i.grupo_patologia_int,''), 'Sem patologia') AS label, COUNT(*) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(111, 223, 194, 0.75)'],
        ['id' => 'chartTipo', 'title' => 'Tipo de registro clínico', 'rows' => [['label' => 'Oportunidades', 'total' => $summary['oportunidades'] ?? 0], ['label' => 'Ações', 'total' => $summary['acoes'] ?? 0], ['label' => 'Programações', 'total' => $summary['programacoes'] ?? 0]], 'color' => 'rgba(255, 198, 108, 0.75)'],
    ];
    $table = fetchAllBI($conn, "
        SELECT COALESCE(NULLIF(h.nome_hosp,''),'Sem hospital') AS hospital,
               COALESCE(NULLIF(p.patologia_pat,''), NULLIF(i.grupo_patologia_int,''), 'Sem patologia') AS patologia,
               COUNT(*) AS visitas,
               SUM(CASE WHEN COALESCE(v.oportunidades_enf,'') <> '' THEN 1 ELSE 0 END) AS oportunidades,
               SUM(CASE WHEN COALESCE(v.acoes_int_vis,'') <> '' THEN 1 ELSE 0 END) AS acoes
        {$base}
        GROUP BY h.id_hospital, patologia
        ORDER BY oportunidades DESC, acoes DESC
        LIMIT 80
    ", $params);
    return [
        'title' => 'BI Oportunidades Clínicas',
        'subtitle' => 'Oportunidades, ações de auditoria e programação terapêutica registradas nas visitas.',
        'kpis' => [
            ['label' => 'Visitas', 'value' => fmtIntBI($summary['visitas'] ?? 0), 'icon' => 'bi-person-lines-fill', 'variant' => 'kpi-card-v2-1', 'hint' => 'Com registro clínico'],
            ['label' => 'Oportunidades', 'value' => fmtIntBI($summary['oportunidades'] ?? 0), 'icon' => 'bi-lightbulb', 'variant' => 'kpi-card-v2-2', 'hint' => 'Enfermagem'],
            ['label' => 'Ações', 'value' => fmtIntBI($summary['acoes'] ?? 0), 'icon' => 'bi-check2-square', 'variant' => 'kpi-card-v2-3', 'hint' => 'Auditoria'],
            ['label' => 'Programações', 'value' => fmtIntBI($summary['programacoes'] ?? 0), 'icon' => 'bi-clipboard2-pulse', 'variant' => 'kpi-card-v2-4', 'hint' => 'Terapêutica'],
        ],
        'charts' => $charts,
        'tableTitle' => 'Recorrência por hospital/patologia',
        'tableHeaders' => ['Hospital', 'Patologia', 'Visitas', 'Oportunidades', 'Ações'],
        'tableRows' => array_map(fn($r) => [e($r['hospital']), e($r['patologia']), fmtIntBI($r['visitas']), fmtIntBI($r['oportunidades']), fmtIntBI($r['acoes'])], $table),
    ];
}

function buildDesfechosBI(PDO $conn, array $filters): array
{
    $params = [];
    $where = periodWhereBI('al.data_alta_alt', $filters, $params);
    $base = "
        FROM tb_alta al
        JOIN tb_internacao i ON i.id_internacao = al.fk_id_int_alt
        LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
        LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
        LEFT JOIN tb_seguradora se ON se.id_seguradora = pa.fk_seguradora_pac
        WHERE {$where}
    ";
    $stayExpr = "GREATEST(1, DATEDIFF(al.data_alta_alt, i.data_intern_int) + 1)";
    $summary = fetchOneBI($conn, "
        SELECT COUNT(*) AS altas,
               SUM(CASE WHEN LOWER(COALESCE(al.tipo_alta_alt,'')) LIKE '%obito%' THEN 1 ELSE 0 END) AS obitos,
               AVG({$stayExpr}) AS mp,
               MAX({$stayExpr}) AS maior
        {$base}
    ", $params);
    $charts = [
        ['id' => 'chartTipo', 'title' => 'Altas por tipo', 'rows' => fetchAllBI($conn, "SELECT COALESCE(NULLIF(al.tipo_alta_alt,''),'Sem informacoes') AS label, COUNT(*) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(141, 208, 255, 0.75)'],
        ['id' => 'chartHosp', 'title' => 'Alta por hospital', 'rows' => fetchAllBI($conn, "SELECT COALESCE(NULLIF(h.nome_hosp,''),'Sem hospital') AS label, COUNT(*) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(111, 223, 194, 0.75)'],
        ['id' => 'chartSeg', 'title' => 'Alta por seguradora', 'rows' => fetchAllBI($conn, "SELECT COALESCE(NULLIF(se.seguradora_seg,''),'Sem seguradora') AS label, COUNT(*) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(255, 198, 108, 0.75)'],
    ];
    $table = fetchAllBI($conn, "
        SELECT COALESCE(NULLIF(h.nome_hosp,''),'Sem hospital') AS hospital,
               COALESCE(NULLIF(se.seguradora_seg,''),'Sem seguradora') AS seguradora,
               COUNT(*) AS altas,
               SUM(CASE WHEN LOWER(COALESCE(al.tipo_alta_alt,'')) LIKE '%obito%' THEN 1 ELSE 0 END) AS obitos,
               ROUND(AVG({$stayExpr}), 1) AS mp
        {$base}
        GROUP BY h.id_hospital, se.id_seguradora
        ORDER BY altas DESC
        LIMIT 80
    ", $params);
    $altas = (int)($summary['altas'] ?? 0);
    $obitos = (int)($summary['obitos'] ?? 0);
    return [
        'title' => 'BI Desfechos e Alta',
        'subtitle' => 'Altas, óbitos, permanência até alta e distribuição por hospital/seguradora.',
        'kpis' => [
            ['label' => 'Altas', 'value' => fmtIntBI($altas), 'icon' => 'bi-box-arrow-right', 'variant' => 'kpi-card-v2-1', 'hint' => 'Registros'],
            ['label' => 'Óbitos', 'value' => fmtIntBI($obitos), 'icon' => 'bi-heartbreak', 'variant' => 'kpi-card-v2-2', 'hint' => fmtPctBI($altas > 0 ? ($obitos / $altas) * 100 : 0)],
            ['label' => 'MP até alta', 'value' => number_format((float)($summary['mp'] ?? 0), 1, ',', '.'), 'icon' => 'bi-speedometer2', 'variant' => 'kpi-card-v2-3', 'hint' => 'Dias'],
            ['label' => 'Maior permanência', 'value' => fmtIntBI($summary['maior'] ?? 0), 'icon' => 'bi-hourglass-split', 'variant' => 'kpi-card-v2-4', 'hint' => 'Dias'],
        ],
        'charts' => $charts,
        'tableTitle' => 'Desfechos por hospital e seguradora',
        'tableHeaders' => ['Hospital', 'Seguradora', 'Altas', 'Óbitos', 'MP'],
        'tableRows' => array_map(fn($r) => [e($r['hospital']), e($r['seguradora']), fmtIntBI($r['altas']), fmtIntBI($r['obitos']), number_format((float)$r['mp'], 1, ',', '.')], $table),
    ];
}

function buildNegociacaoBI(PDO $conn, array $filters): array
{
    $params = [];
    $where = periodWhereBI('ng.data_inicio_neg', $filters, $params);
    $where .= " AND UPPER(COALESCE(ng.tipo_negociacao,'')) <> 'PRORROGACAO_AUTOMATICA'";
    $base = "
        FROM tb_negociacao ng
        JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
        LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
        LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
        LEFT JOIN tb_user u ON u.id_usuario = ng.fk_usuario_neg
        WHERE {$where}
    ";
    $saving = moneyToNumberBI('ng.saving');
    $qtd = "CAST(COALESCE(NULLIF(ng.qtd,''),'0') AS DECIMAL(12,2))";
    $summary = fetchOneBI($conn, "
        SELECT COUNT(*) AS negociacoes, SUM({$saving}) AS saving, SUM({$qtd}) AS quantidade,
               SUM(CASE WHEN ng.data_fim_neg IS NULL OR ng.data_fim_neg = '' OR ng.data_fim_neg = '0000-00-00' THEN 1 ELSE 0 END) AS abertas
        {$base}
    ", $params);
    $charts = [
        ['id' => 'chartTipo', 'title' => 'Saving por tipo de negociação', 'rows' => fetchAllBI($conn, "SELECT COALESCE(NULLIF(ng.tipo_negociacao,''),'Sem tipo') AS label, SUM({$saving}) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(141, 208, 255, 0.75)', 'money' => true],
        ['id' => 'chartTroca', 'title' => 'Troca de/para', 'rows' => fetchAllBI($conn, "SELECT CONCAT(COALESCE(NULLIF(ng.troca_de,''),'?'), ' -> ', COALESCE(NULLIF(ng.troca_para,''),'?')) AS label, COUNT(*) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(111, 223, 194, 0.75)'],
        ['id' => 'chartAud', 'title' => 'Saving por auditor', 'rows' => fetchAllBI($conn, "SELECT COALESCE(NULLIF(u.usuario_user,''),'Sem auditor') AS label, SUM({$saving}) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(255, 198, 108, 0.75)', 'money' => true],
    ];
    $table = fetchAllBI($conn, "
        SELECT COALESCE(NULLIF(h.nome_hosp,''),'Sem hospital') AS hospital,
               COALESCE(NULLIF(p.patologia_pat,''), NULLIF(i.grupo_patologia_int,''), 'Sem patologia') AS patologia,
               COALESCE(NULLIF(u.usuario_user,''),'Sem auditor') AS auditor,
               COUNT(*) AS negociacoes, SUM({$saving}) AS saving
        {$base}
        GROUP BY h.id_hospital, patologia, auditor
        ORDER BY saving DESC
        LIMIT 80
    ", $params);
    return [
        'title' => 'BI Negociação Avançada',
        'subtitle' => 'Saving por tipo, troca de/para, quantidade negociada, abertas e auditor.',
        'kpis' => [
            ['label' => 'Negociações', 'value' => fmtIntBI($summary['negociacoes'] ?? 0), 'icon' => 'bi-arrow-left-right', 'variant' => 'kpi-card-v2-1', 'hint' => 'Registros'],
            ['label' => 'Saving', 'value' => fmtMoneyBI($summary['saving'] ?? 0), 'icon' => 'bi-cash-stack', 'variant' => 'kpi-card-v2-2', 'hint' => 'Total'],
            ['label' => 'Quantidade', 'value' => fmtIntBI($summary['quantidade'] ?? 0), 'icon' => 'bi-123', 'variant' => 'kpi-card-v2-3', 'hint' => 'Itens negociados'],
            ['label' => 'Abertas', 'value' => fmtIntBI($summary['abertas'] ?? 0), 'icon' => 'bi-clock', 'variant' => 'kpi-card-v2-4', 'hint' => 'Sem conclusão'],
        ],
        'charts' => $charts,
        'tableTitle' => 'Saving por hospital/patologia/auditor',
        'tableHeaders' => ['Hospital', 'Patologia', 'Auditor', 'Negociações', 'Saving'],
        'tableRows' => array_map(fn($r) => [e($r['hospital']), e($r['patologia']), e($r['auditor']), fmtIntBI($r['negociacoes']), fmtMoneyBI($r['saving'])], $table),
    ];
}

function buildSegurancaEventosBI(PDO $conn, array $filters): array
{
    $params = [];
    $dateExpr = "COALESCE(g.evento_data_ges, g.data_create_ges)";
    $where = periodWhereBI($dateExpr, $filters, $params);
    $where .= " AND g.evento_adverso_ges = 's'";
    $base = "
        FROM tb_gestao g
        JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
        LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
        WHERE {$where}
    ";
    $summary = fetchOneBI($conn, "
        SELECT COUNT(*) AS eventos,
               SUM(CASE WHEN COALESCE(g.evento_encerrar_ges,'n') = 's' OR COALESCE(g.evento_concluido_ges,'n') = 's' THEN 1 ELSE 0 END) AS concluidos,
               SUM(CASE WHEN COALESCE(g.evento_encerrar_ges,'n') <> 's' AND COALESCE(g.evento_concluido_ges,'n') <> 's' THEN 1 ELSE 0 END) AS abertos,
               SUM(CASE WHEN COALESCE(g.evento_impacto_financ_ges,'n') = 's' THEN 1 ELSE 0 END) AS impacto,
               SUM(CASE WHEN COALESCE(g.evento_prolongou_internacao_ges,'n') = 's' THEN 1 ELSE 0 END) AS prolongou
        {$base}
    ", $params);
    $charts = [
        ['id' => 'chartStatus', 'title' => 'Abertos x concluídos', 'rows' => [['label' => 'Abertos', 'total' => $summary['abertos'] ?? 0], ['label' => 'Concluídos', 'total' => $summary['concluidos'] ?? 0]], 'color' => 'rgba(141, 208, 255, 0.75)'],
        ['id' => 'chartHosp', 'title' => 'Eventos sem encerramento por hospital', 'rows' => fetchAllBI($conn, "SELECT COALESCE(NULLIF(h.nome_hosp,''),'Sem hospital') AS label, SUM(CASE WHEN COALESCE(g.evento_encerrar_ges,'n') <> 's' AND COALESCE(g.evento_concluido_ges,'n') <> 's' THEN 1 ELSE 0 END) AS total {$base} GROUP BY label ORDER BY total DESC LIMIT 12", $params), 'color' => 'rgba(111, 223, 194, 0.75)'],
        ['id' => 'chartImpacto', 'title' => 'Impacto e prolongamento', 'rows' => [['label' => 'Impacto financeiro', 'total' => $summary['impacto'] ?? 0], ['label' => 'Prolongou internação', 'total' => $summary['prolongou'] ?? 0]], 'color' => 'rgba(255, 198, 108, 0.75)'],
    ];
    $table = fetchAllBI($conn, "
        SELECT COALESCE(NULLIF(h.nome_hosp,''),'Sem hospital') AS hospital,
               COALESCE(NULLIF(g.tipo_evento_adverso_gest,''),'Sem tipo') AS tipo,
               COUNT(*) AS eventos,
               SUM(CASE WHEN COALESCE(g.evento_encerrar_ges,'n') <> 's' AND COALESCE(g.evento_concluido_ges,'n') <> 's' THEN 1 ELSE 0 END) AS abertos,
               SUM(CASE WHEN COALESCE(g.evento_retorno_qual_hosp_ges,'n') = 's' THEN 1 ELSE 0 END) AS retorno
        {$base}
        GROUP BY h.id_hospital, tipo
        ORDER BY abertos DESC, eventos DESC
        LIMIT 80
    ", $params);
    return [
        'title' => 'BI Segurança e Eventos Abertos',
        'subtitle' => 'Eventos adversos abertos, concluídos, impacto, prolongamento e retorno da qualidade.',
        'kpis' => [
            ['label' => 'Eventos', 'value' => fmtIntBI($summary['eventos'] ?? 0), 'icon' => 'bi-exclamation-triangle', 'variant' => 'kpi-card-v2-1', 'hint' => 'Total'],
            ['label' => 'Abertos', 'value' => fmtIntBI($summary['abertos'] ?? 0), 'icon' => 'bi-unlock', 'variant' => 'kpi-card-v2-2', 'hint' => 'Sem encerramento'],
            ['label' => 'Impacto financeiro', 'value' => fmtIntBI($summary['impacto'] ?? 0), 'icon' => 'bi-cash-coin', 'variant' => 'kpi-card-v2-3', 'hint' => 'Marcados sim'],
            ['label' => 'Prolongou internação', 'value' => fmtIntBI($summary['prolongou'] ?? 0), 'icon' => 'bi-hourglass-split', 'variant' => 'kpi-card-v2-4', 'hint' => 'Marcados sim'],
        ],
        'charts' => $charts,
        'tableTitle' => 'Eventos sem encerramento',
        'tableHeaders' => ['Hospital', 'Tipo', 'Eventos', 'Abertos', 'Retorno qualidade'],
        'tableRows' => array_map(fn($r) => [e($r['hospital']), e($r['tipo']), fmtIntBI($r['eventos']), fmtIntBI($r['abertos']), fmtIntBI($r['retorno'])], $table),
    ];
}

$builders = [
    'prorrogacoes' => 'buildProrrogacoesBI',
    'tuss-autorizacoes' => 'buildTussBI',
    'oportunidades-clinicas' => 'buildOportunidadesBI',
    'desfechos-alta' => 'buildDesfechosBI',
    'negociacao-avancada' => 'buildNegociacaoBI',
    'seguranca-eventos' => 'buildSegurancaEventosBI',
];
$data = $builders[$panel]($conn, $filters);

$chartPayload = [];
foreach ($data['charts'] as $chart) {
    [$labels, $values] = labelsValuesBI($chart['rows']);
    $chartPayload[$chart['id']] = [
        'labels' => $labels,
        'values' => $values,
        'color' => $chart['color'],
        'money' => !empty($chart['money']),
    ];
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme bi-auditor-page">
    <div class="bi-header">
        <div>
            <h1 class="bi-title"><?= e($data['title']) ?></h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;"><?= e($data['subtitle']) ?></div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters bi-filters-wrap" method="get">
        <input type="hidden" name="painel" value="<?= e($panel) ?>">
        <div class="bi-filter">
            <label>Hospitais</label>
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
            <label>Mês</label>
            <select name="mes">
                <option value="0">Todos</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= (int)$mes === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Ano</label>
            <input type="number" name="ano" value="<?= e($ano) ?>">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel" style="margin-top:16px;">
        <div class="bi-kpis kpi-dashboard-v2">
            <?php foreach ($data['kpis'] as $kpi): ?>
                <div class="bi-kpi kpi-card-v2 <?= e($kpi['variant']) ?>">
                    <div class="kpi-card-v2-head">
                        <span class="kpi-card-v2-icon"><i class="bi <?= e($kpi['icon']) ?>"></i></span>
                        <small><?= e($kpi['label']) ?></small>
                    </div>
                    <strong><?= e($kpi['value']) ?></strong>
                    <span class="kpi-trend"><i class="bi bi-arrow-up-right"></i><?= e($kpi['hint']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bi-grid fixed-3" style="margin-top:16px;">
        <?php foreach ($data['charts'] as $chart): ?>
            <div class="bi-panel">
                <h3><?= e($chart['title']) ?></h3>
                <div class="bi-chart"><canvas id="<?= e($chart['id']) ?>"></canvas></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="bi-panel" style="margin-top:16px;">
        <h3><?= e($data['tableTitle']) ?></h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <?php foreach ($data['tableHeaders'] as $header): ?>
                        <th><?= e($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['tableRows'])): ?>
                    <tr><td colspan="<?= count($data['tableHeaders']) ?>">Sem dados para os filtros selecionados.</td></tr>
                <?php else: ?>
                    <?php foreach ($data['tableRows'] as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?= $cell ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const charts = <?= json_encode($chartPayload) ?>;
Object.keys(charts).forEach((id) => {
    const item = charts[id];
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    if (item.money && scales && scales.yAxes && scales.yAxes[0]) {
        scales.yAxes[0].ticks.callback = window.biMoneyTick;
    }
    new Chart(document.getElementById(id), {
        type: 'bar',
        data: { labels: item.labels, datasets: [{ data: item.values, backgroundColor: item.color }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            scales
        }
    });
});
</script>

<?php require_once("templates/footer.php"); ?>

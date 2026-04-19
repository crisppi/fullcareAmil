<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

if (!function_exists('e')) {
    function e($v)
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pageTitle = $pageTitle ?? 'Performance Comparativa da Rede Hospitalar';
$pageSubtitle = $pageSubtitle ?? 'Custo, qualidade e eficiencia por hospital';
$clearUrl = $clearUrl ?? 'bi/rede-comparativa';
$redeCurrent = $redeCurrent ?? 'comparativa';

$normCargoAccess = static function ($txt): string {
    $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $ascii !== false ? $ascii : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
};
$isSeguradoraRole = (strpos($normCargoAccess($_SESSION['cargo'] ?? ''), 'seguradora') !== false);
$seguradoraUserId = (int)($_SESSION['fk_seguradora_user'] ?? 0);
if ($isSeguradoraRole && $seguradoraUserId <= 0) {
    try {
        $uid = (int)($_SESSION['id_usuario'] ?? 0);
        if ($uid > 0) {
            $stmtSeg = $conn->prepare("SELECT fk_seguradora_user FROM tb_user WHERE id_usuario = :id LIMIT 1");
            $stmtSeg->bindValue(':id', $uid, PDO::PARAM_INT);
            $stmtSeg->execute();
            $seguradoraUserId = (int)($stmtSeg->fetchColumn() ?: 0);
            if ($seguradoraUserId > 0) {
                $_SESSION['fk_seguradora_user'] = $seguradoraUserId;
            }
        }
    } catch (Throwable $e) {
        error_log('[BI_REDE][SEGURADORA] ' . $e->getMessage());
    }
}

$hoje = date('Y-m-d');
$dataIni = filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-d', strtotime('-180 days'));
$dataFim = filter_input(INPUT_GET, 'data_fim') ?: $hoje;
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;
$regiao = trim((string)(filter_input(INPUT_GET, 'regiao') ?? ''));
$tipoAdmissao = trim((string)(filter_input(INPUT_GET, 'tipo_admissao') ?? ''));
$modoInternacao = trim((string)(filter_input(INPUT_GET, 'modo_internacao') ?? ''));
$uti = trim((string)(filter_input(INPUT_GET, 'uti') ?? ''));

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$seguradoras = $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")
    ->fetchAll(PDO::FETCH_ASSOC);
if ($isSeguradoraRole) {
    $seguradoraId = $seguradoraUserId > 0 ? $seguradoraUserId : -1;
    $seguradoras = array_values(array_filter($seguradoras, static function ($s) use ($seguradoraUserId) {
        return (int)($s['id_seguradora'] ?? 0) === (int)$seguradoraUserId;
    }));
}
$regioes = $conn->query("SELECT DISTINCT estado_hosp FROM tb_hospital WHERE estado_hosp IS NOT NULL AND estado_hosp <> '' ORDER BY estado_hosp")
    ->fetchAll(PDO::FETCH_COLUMN);
$tiposAdm = $conn->query("SELECT DISTINCT tipo_admissao_int FROM tb_internacao WHERE tipo_admissao_int IS NOT NULL AND tipo_admissao_int <> '' ORDER BY tipo_admissao_int")
    ->fetchAll(PDO::FETCH_COLUMN);
$modosInt = $conn->query("SELECT DISTINCT modo_internacao_int FROM tb_internacao WHERE modo_internacao_int IS NOT NULL AND modo_internacao_int <> '' ORDER BY modo_internacao_int")
    ->fetchAll(PDO::FETCH_COLUMN);

$filterValues = [
    'data_ini' => $dataIni,
    'data_fim' => $dataFim,
    'hospital_id' => $hospitalId ? (string)$hospitalId : '',
    'seguradora_id' => $seguradoraId ? (string)$seguradoraId : '',
    'regiao' => $regiao,
    'tipo_admissao' => $tipoAdmissao,
    'modo_internacao' => $modoInternacao,
    'uti' => $uti,
];

$filterOptions = [
    'hospitais' => array_map(static function ($row): array {
        return [
            'value' => (string)($row['id_hospital'] ?? ''),
            'label' => (string)($row['nome_hosp'] ?? ''),
        ];
    }, $hospitais),
    'seguradoras' => array_map(static function ($row): array {
        return [
            'value' => (string)($row['id_seguradora'] ?? ''),
            'label' => (string)($row['seguradora_seg'] ?? ''),
        ];
    }, $seguradoras),
    'regioes' => array_map(static function ($value): array {
        return [
            'value' => (string)$value,
            'label' => (string)$value,
        ];
    }, $regioes),
    'tipos_admissao' => array_map(static function ($value): array {
        return [
            'value' => (string)$value,
            'label' => (string)$value,
        ];
    }, $tiposAdm),
    'modos_internacao' => array_map(static function ($value): array {
        return [
            'value' => (string)$value,
            'label' => (string)$value,
        ];
    }, $modosInt),
];

if (!function_exists('biBindParams')) {
    function biBindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}

if (!function_exists('biRedeBuildWhere')) {
    function biRedeBuildWhere(array $filterValues, string $dateExpr, string $baseAlias = 'i', bool $includeUtiJoin = true): array
    {
        $joins = "
            LEFT JOIN tb_hospital h ON h.id_hospital = {$baseAlias}.fk_hospital_int
            LEFT JOIN tb_paciente pa ON pa.id_paciente = {$baseAlias}.fk_paciente_int
            LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
        ";
        if ($includeUtiJoin) {
            $joins .= "\nLEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = {$baseAlias}.id_internacao";
        }

        $where = "{$dateExpr} BETWEEN :data_ini AND :data_fim";
        $params = [
            ':data_ini' => (string)($filterValues['data_ini'] ?? ''),
            ':data_fim' => (string)($filterValues['data_fim'] ?? ''),
        ];

        $hospitalId = (int)($filterValues['hospital_id'] ?? 0);
        if ($hospitalId > 0) {
            $where .= " AND {$baseAlias}.fk_hospital_int = :hospital_id";
            $params[':hospital_id'] = $hospitalId;
        }

        $seguradoraId = (int)($filterValues['seguradora_id'] ?? 0);
        if ($seguradoraId > 0) {
            $where .= " AND pa.fk_seguradora_pac = :seguradora_id";
            $params[':seguradora_id'] = $seguradoraId;
        }

        $regiao = trim((string)($filterValues['regiao'] ?? ''));
        if ($regiao !== '') {
            $where .= " AND h.estado_hosp = :regiao";
            $params[':regiao'] = $regiao;
        }

        $tipoAdmissao = trim((string)($filterValues['tipo_admissao'] ?? ''));
        if ($tipoAdmissao !== '') {
            $where .= " AND {$baseAlias}.tipo_admissao_int = :tipo_admissao";
            $params[':tipo_admissao'] = $tipoAdmissao;
        }

        $modoInternacao = trim((string)($filterValues['modo_internacao'] ?? ''));
        if ($modoInternacao !== '') {
            $where .= " AND {$baseAlias}.modo_internacao_int = :modo_internacao";
            $params[':modo_internacao'] = $modoInternacao;
        }

        $uti = trim((string)($filterValues['uti'] ?? ''));
        if ($includeUtiJoin && $uti === 's') {
            $where .= " AND ut.fk_internacao_uti IS NOT NULL";
        } elseif ($includeUtiJoin && $uti === 'n') {
            $where .= " AND ut.fk_internacao_uti IS NULL";
        }

        return [
            'where' => $where,
            'params' => $params,
            'joins' => $joins,
        ];
    }
}

$where = "i.data_intern_int BETWEEN :data_ini AND :data_fim";
$params = [
    ':data_ini' => $dataIni,
    ':data_fim' => $dataFim,
];
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
if ($seguradoraId) {
    $where .= " AND pa.fk_seguradora_pac = :seguradora_id";
    $params[':seguradora_id'] = $seguradoraId;
}
if ($regiao !== '') {
    $where .= " AND h.estado_hosp = :regiao";
    $params[':regiao'] = $regiao;
}
if ($tipoAdmissao !== '') {
    $where .= " AND i.tipo_admissao_int = :tipo_admissao";
    $params[':tipo_admissao'] = $tipoAdmissao;
}
if ($modoInternacao !== '') {
    $where .= " AND i.modo_internacao_int = :modo_internacao";
    $params[':modo_internacao'] = $modoInternacao;
}

$utiJoin = "LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao";
if ($uti === 's') {
    $where .= " AND ut.fk_internacao_uti IS NOT NULL";
}
if ($uti === 'n') {
    $where .= " AND ut.fk_internacao_uti IS NULL";
}

$sqlHosp = "
    SELECT
        h.id_hospital,
        h.nome_hosp AS hospital,
        h.estado_hosp AS regiao,
        COUNT(DISTINCT i.id_internacao) AS total_internacoes,
        AVG(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS permanencia_media,
        SUM(COALESCE(ca.valor_apresentado_capeante, 0)) AS valor_apresentado,
        SUM(COALESCE(ca.valor_final_capeante, 0)) AS valor_final,
        SUM(CASE WHEN ca.conta_parada_cap = 's' THEN 1 ELSE 0 END) AS contas_rejeitadas,
        COUNT(DISTINCT ca.id_capeante) AS total_contas,
        COUNT(DISTINCT CASE WHEN ev.fk_internacao_ges IS NOT NULL THEN i.id_internacao END) AS internacoes_evento
    FROM tb_internacao i
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN (
        SELECT ca1.*
        FROM tb_capeante ca1
        INNER JOIN (
            SELECT fk_int_capeante, MAX(id_capeante) AS max_id
            FROM tb_capeante
            GROUP BY fk_int_capeante
        ) ca2 ON ca2.max_id = ca1.id_capeante
    ) ca ON ca.fk_int_capeante = i.id_internacao
    LEFT JOIN (
        SELECT fk_internacao_ges
        FROM tb_gestao
        WHERE evento_adverso_ges = 's'
        GROUP BY fk_internacao_ges
    ) ev ON ev.fk_internacao_ges = i.id_internacao
    {$utiJoin}
    WHERE {$where}
    GROUP BY h.id_hospital
";
$stmt = $conn->prepare($sqlHosp);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$whereAlta = "al.data_alta_alt BETWEEN :data_ini AND :data_fim";
if ($hospitalId) {
    $whereAlta .= " AND i.fk_hospital_int = :hospital_id";
}
if ($seguradoraId) {
    $whereAlta .= " AND pa.fk_seguradora_pac = :seguradora_id";
}
if ($regiao !== '') {
    $whereAlta .= " AND h.estado_hosp = :regiao";
}
if ($tipoAdmissao !== '') {
    $whereAlta .= " AND i.tipo_admissao_int = :tipo_admissao";
}
if ($modoInternacao !== '') {
    $whereAlta .= " AND i.modo_internacao_int = :modo_internacao";
}
if ($uti === 's') {
    $whereAlta .= " AND ut.fk_internacao_uti IS NOT NULL";
}
if ($uti === 'n') {
    $whereAlta .= " AND ut.fk_internacao_uti IS NULL";
}

$sqlReadm = "
    SELECT
        h.id_hospital,
        COUNT(*) AS total_altas,
        SUM(
            CASE WHEN EXISTS (
                SELECT 1
                FROM tb_internacao i2
                WHERE i2.fk_paciente_int = i.fk_paciente_int
                  AND i2.data_intern_int > al.data_alta_alt
                  AND i2.data_intern_int <= DATE_ADD(al.data_alta_alt, INTERVAL 30 DAY)
            ) THEN 1 ELSE 0 END
        ) AS readm
    FROM tb_alta al
    JOIN tb_internacao i ON i.id_internacao = al.fk_id_int_alt
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    {$utiJoin}
    WHERE {$whereAlta}
    GROUP BY h.id_hospital
";
$stmt = $conn->prepare($sqlReadm);
$stmt->execute($params);
$readmRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$readmMap = [];
foreach ($readmRows as $row) {
    $readmMap[(int)$row['id_hospital']] = [
        'total_altas' => (int)$row['total_altas'],
        'readm' => (int)$row['readm'],
    ];
}

$totals = [
    'internacoes' => 0,
    'contas' => 0,
    'valor_apresentado' => 0.0,
    'valor_final' => 0.0,
    'rejeitadas' => 0,
    'contas_paradas' => 0,
    'eventos' => 0,
    'readm' => 0,
    'altas' => 0,
    'permanencia_num' => 0.0,
];

foreach ($rows as &$row) {
    $totalInternacoes = (int)($row['total_internacoes'] ?? 0);
    $totalContas = (int)($row['total_contas'] ?? 0);
    $valorApresentado = (float)($row['valor_apresentado'] ?? 0);
    $valorFinal = (float)($row['valor_final'] ?? 0);
    $rejeitadas = (int)($row['contas_rejeitadas'] ?? 0);
    $eventos = (int)($row['internacoes_evento'] ?? 0);
    $permanencia = (float)($row['permanencia_media'] ?? 0);

    $readmData = $readmMap[(int)$row['id_hospital']] ?? ['total_altas' => 0, 'readm' => 0];
    $totalAltas = (int)$readmData['total_altas'];
    $readm = (int)$readmData['readm'];

    $row['custo_apresentado'] = $totalContas > 0 ? $valorApresentado / $totalContas : 0;
    $row['custo_final'] = $totalContas > 0 ? $valorFinal / $totalContas : 0;
    $row['glosa_rate'] = $valorApresentado > 0 ? ($valorApresentado - $valorFinal) / $valorApresentado : 0;
    $row['rejeicao_rate'] = $totalContas > 0 ? $rejeitadas / $totalContas : 0;
    $row['contas_paradas_rate'] = $row['rejeicao_rate'];
    $row['contas_paradas'] = $rejeitadas;
    $row['eventos_rate'] = $totalInternacoes > 0 ? $eventos / $totalInternacoes : 0;
    $row['readm_rate'] = $totalAltas > 0 ? $readm / $totalAltas : 0;
    $row['total_altas'] = $totalAltas;
    $row['readm'] = $readm;

    $totals['internacoes'] += $totalInternacoes;
    $totals['contas'] += $totalContas;
    $totals['valor_apresentado'] += $valorApresentado;
    $totals['valor_final'] += $valorFinal;
    $totals['rejeitadas'] += $rejeitadas;
    $totals['contas_paradas'] += $rejeitadas;
    $totals['eventos'] += $eventos;
    $totals['readm'] += $readm;
    $totals['altas'] += $totalAltas;
    $totals['permanencia_num'] += $permanencia * $totalInternacoes;
}
unset($row);

$network = [
    'custo_apresentado' => $totals['contas'] > 0 ? $totals['valor_apresentado'] / $totals['contas'] : 0,
    'custo_final' => $totals['contas'] > 0 ? $totals['valor_final'] / $totals['contas'] : 0,
    'glosa_rate' => $totals['valor_apresentado'] > 0 ? ($totals['valor_apresentado'] - $totals['valor_final']) / $totals['valor_apresentado'] : 0,
    'rejeicao_rate' => $totals['contas'] > 0 ? $totals['rejeitadas'] / $totals['contas'] : 0,
    'contas_paradas_rate' => $totals['contas'] > 0 ? $totals['contas_paradas'] / $totals['contas'] : 0,
    'eventos_rate' => $totals['internacoes'] > 0 ? $totals['eventos'] / $totals['internacoes'] : 0,
    'readm_rate' => $totals['altas'] > 0 ? $totals['readm'] / $totals['altas'] : 0,
    'permanencia_media' => $totals['internacoes'] > 0 ? $totals['permanencia_num'] / $totals['internacoes'] : 0,
];

$metrics = [
    'custo_final' => [],
    'glosa_rate' => [],
    'rejeicao_rate' => [],
    'permanencia_media' => [],
    'eventos_rate' => [],
    'readm_rate' => [],
];
foreach ($rows as $row) {
    $metrics['custo_final'][] = $row['custo_final'];
    $metrics['glosa_rate'][] = $row['glosa_rate'];
    $metrics['rejeicao_rate'][] = $row['rejeicao_rate'];
    $metrics['permanencia_media'][] = (float)($row['permanencia_media'] ?? 0);
    $metrics['eventos_rate'][] = $row['eventos_rate'];
    $metrics['readm_rate'][] = $row['readm_rate'];
}

$bounds = [];
foreach ($metrics as $key => $values) {
    $bounds[$key] = [
        'min' => $values ? min($values) : 0,
        'max' => $values ? max($values) : 0,
    ];
}

foreach ($rows as &$row) {
    $scoreParts = [];
    foreach ($bounds as $key => $range) {
        $min = $range['min'];
        $max = $range['max'];
        $val = $key === 'permanencia_media' ? (float)($row['permanencia_media'] ?? 0) : (float)($row[$key] ?? 0);
        $norm = ($max > $min) ? ($val - $min) / ($max - $min) : 0;
        $scoreParts[] = 1 - $norm;
    }
    $row['score'] = $scoreParts ? round((array_sum($scoreParts) / count($scoreParts)) * 100, 1) : 0;
}
unset($row);

$redeTabs = [
    ['id' => 'comparativa', 'label' => 'Comparativa', 'href' => 'bi/rede-comparativa'],
    ['id' => 'custo', 'label' => 'Custo por hospital', 'href' => 'bi/rede-custo'],
    ['id' => 'glosa', 'label' => 'Glosa por hospital', 'href' => 'bi/rede-glosa'],
    ['id' => 'rejeicao', 'label' => 'Rejeição capeante', 'href' => 'bi/rede-rejeicao-capeante'],
    ['id' => 'permanencia', 'label' => 'Permanência média', 'href' => 'bi/rede-permanencia'],
    ['id' => 'eventos', 'label' => 'Eventos adversos', 'href' => 'bi/rede-eventos-adversos'],
    ['id' => 'readmissao', 'label' => 'Readmissão 30d', 'href' => 'bi/rede-readmissao'],
    ['id' => 'ranking', 'label' => 'Ranking', 'href' => 'bi/rede-ranking'],
];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260411d">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260411d"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

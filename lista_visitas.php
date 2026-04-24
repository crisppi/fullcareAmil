<?php
ob_start();

if (!defined('SKIP_HEADER')) {
    define('SKIP_HEADER', true);
}
include_once __DIR__ . "/check_logado.php";
include_once __DIR__ . "/globals.php";
include_once __DIR__ . "/db.php";

/* ==== Helpers ==== */
function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function fmt_br($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') return '';
    $d10 = substr($raw, 0, 10);
    if ($dt = DateTime::createFromFormat('Y-m-d', $d10)) return $dt->format('d/m/Y');
    if ($dt = DateTime::createFromFormat('d/m/Y', $d10)) return $dt->format('d/m/Y');
    $ts = @strtotime($raw);
    return $ts ? date('d/m/Y', $ts) : $raw;
}
function fmt_cnpj($raw)
{
    $digits = preg_replace('/\D+/', '', (string)$raw);
    if (strlen($digits) !== 14) return $raw;
    return substr($digits,0,2) . '.' . substr($digits,2,3) . '.' . substr($digits,5,3) . '/' . substr($digits,8,4) . '-' . substr($digits,12,2);
}
function qs_keep(array $replace = [])
{
    $q = $_GET;
    foreach ($replace as $k => $v) {
        $q[$k] = $v;
    }
    return http_build_query($q);
}

$DEBUG = !empty($_GET['debug']);

/* ==== Tabelas ==== */
$T_INT = 'tb_internacao';
$T_PAC = 'tb_paciente';
$T_HOS = 'tb_hospital';
$T_VIS = 'tb_visita';
$T_ALT = 'tb_alta';
$T_PAT = 'tb_patologia';
$T_CID = 'tb_cid';
$T_USR = 'tb_user';
$T_CAP = 'tb_capeante';
$T_SEG = 'tb_seguradora';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/lista_visitas.php';

$pageContext = strtolower(trim($_GET['context'] ?? ''));
$isFaturamentoView = $pageContext === 'faturamento';
$pageTitle = $isFaturamentoView ? 'Faturamento - Visitas' : 'Lista de Visitas';

/* ==== Entrada ==== */
$nomePaciente = trim($_GET['nome'] ?? '');
$matriculaPaciente = trim($_GET['matricula'] ?? '');
$hospitalIdsRaw = $_GET['hospital_id'] ?? [];
if ($hospitalIdsRaw === '' || $hospitalIdsRaw === null) {
    $hospitalIdsRaw = [];
} elseif (!is_array($hospitalIdsRaw)) {
    $hospitalIdsRaw = [$hospitalIdsRaw];
}
$hospitalIds = [];
foreach ($hospitalIdsRaw as $hid) {
    $hid = (int)preg_replace('/\D+/', '', (string)$hid);
    if ($hid > 0) {
        $hospitalIds[] = $hid;
    }
}
$hospitalIds = array_values(array_unique($hospitalIds));
$seguradoraIdsRaw = $_GET['seguradora_id'] ?? [];
if ($seguradoraIdsRaw === '' || $seguradoraIdsRaw === null) {
    $seguradoraIdsRaw = [];
} elseif (!is_array($seguradoraIdsRaw)) {
    $seguradoraIdsRaw = [$seguradoraIdsRaw];
}
$seguradoraIds = [];
foreach ($seguradoraIdsRaw as $sidRaw) {
    foreach (explode(',', (string)$sidRaw) as $part) {
        $sid = (int)preg_replace('/\D+/', '', $part);
        if ($sid > 0) {
            $seguradoraIds[] = $sid;
        }
    }
}
$seguradoraIds = array_values(array_unique($seguradoraIds));
$seguradoraIdsRawNormalized = array_map(fn($v) => trim((string)$v), $seguradoraIdsRaw);
$dtIni        = trim($_GET['dt_ini'] ?? ''); // YYYY-MM-DD
$dtFim        = trim($_GET['dt_fim'] ?? ''); // YYYY-MM-DD
$faturadoVis  = strtolower(trim($_GET['faturado'] ?? 'n'));
if (!in_array($faturadoVis, ['s', 'n', ''], true)) {
    $faturadoVis = 'n';
}

if ($dtIni !== '' && $dtFim !== '' && $dtIni > $dtFim) {
    [$dtIni, $dtFim] = [$dtFim, $dtIni];
}

$sortField = trim($_GET['sort_field'] ?? '');
$sortDir = strtolower($_GET['sort_dir'] ?? 'desc');
$sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

$limite = isset($_GET['limite']) && ctype_digit($_GET['limite']) ? (int)$_GET['limite'] : 20;
$pag    = isset($_GET['pag'])    && ctype_digit($_GET['pag'])    ? (int)$_GET['pag']    : 1;
$limite = max(1, min(1000, $limite));
$pag    = max(1, $pag);
$offset = max(0, ($pag - 1) * $limite);

$isExport = isset($_GET['export']) && $_GET['export'] == '1';

/* ==== Campos exibíveis ==== */
$fieldsMap = [
    'id_internacao'   => ['label' => 'ID Int',      'sql' => "i.id_internacao AS id_internacao"],
    'id_visita'       => ['label' => 'Id Visita',   'sql' => "v1.id_visita AS id_visita"],
    'senha'           => ['label' => 'Senha',            'sql' => "i.senha_int AS senha"],
    'hospital'        => ['label' => 'Hospital',         'sql' => "ho.nome_hosp AS hospital"],
    'cnpj_hospital'   => ['label' => 'CNPJ do hospital', 'sql' => "ho.cnpj_hosp AS cnpj_hospital"],
    'nome_paciente'   => ['label' => 'Nome do paciente', 'sql' => "pa.nome_pac AS nome_paciente"],
    'seguradora'      => ['label' => 'Seguradora', 'sql' => "COALESCE(NULLIF(TRIM(se.seguradora_seg),''),'Sem seguradora') AS seguradora"],
    'matricula'       => ['label' => 'Matrícula do paciente', 'sql' => "pa.matricula_pac AS matricula"],
    'data_internacao' => ['label' => 'Data internação',  'sql' => "i.data_intern_int AS data_internacao"],
    'data_visita'     => ['label' => 'Data visita',      'sql' => "v.data_visita_fmt AS data_visita"],
    'data_lancamento' => [
        'label' => 'Data lançamento',
        'sql'   => "v1.data_lancamento_vis AS data_lancamento"
    ],
    'valor_liberado'  => ['label' => 'Valor Liberado',  'sql' => "ca.valor_final_capeante AS valor_liberado"],
    'periodo_faturamento' => [
        'label' => 'Período faturamento (30 dias)',
        'sql'   => "CASE WHEN v.last_data_lancamento_iso IS NULL THEN NULL ELSE CONCAT(IFNULL(v.periodo_ini_fmt,''), ' a ', v.last_data_lancamento_fmt) END AS periodo_faturamento"
    ],
    'auditor_medico'  => [
        'label' => 'Auditor médico',
        'sql'   => "COALESCE(u.usuario_user, u2.usuario_user, NULLIF(v1.visita_auditor_prof_med,'')) AS auditor_medico"
    ],
    'alta_flag'       => ['label' => 'Alta',             'sql' => "IF(a1.fk_id_int_alt IS NULL,'Não','Sim') AS alta_flag"],
    'data_alta'       => ['label' => 'Data alta',        'sql' => "a1.data_alta_alt AS data_alta"],
    'cid'             => ['label' => 'CID',              'sql' => "pc.cid AS cid"],
    'faturado_vis'    => ['label' => 'Faturado?',        'sql' => "IFNULL(NULLIF(v1.faturado_vis,''), 'n') AS faturado_vis"],
];

if (!$isFaturamentoView) {
    unset($fieldsMap['periodo_faturamento']);
}

/* ==== SELECT dinâmico ==== */
$selected = isset($_GET['fields']) && is_array($_GET['fields'])
    ? array_values(array_intersect(array_keys($fieldsMap), $_GET['fields']))
    : array_keys($fieldsMap);
if (!$selected) $selected = array_keys($fieldsMap);
$queryFields = $selected;
if ($isFaturamentoView) {
    if (!in_array('id_visita', $queryFields, true)) {
        $queryFields[] = 'id_visita';
    }
    if (!in_array('faturado_vis', $queryFields, true)) {
        $queryFields[] = 'faturado_vis';
    }
}
$queryFields = array_values(array_unique($queryFields));

/* ==== Período para escolher visita correspondente ==== */
$params = [];
$vPickDateSQL = '';
if ($dtIni !== '' || $dtFim !== '') {
    $condsPick = [];
    if ($dtIni !== '') {
        $condsPick[] = "CAST(v0.parsed_date AS DATE) >= :pini";
        $params[':pini'] = $dtIni;
    }
    if ($dtFim !== '') {
        $condsPick[] = "CAST(v0.parsed_date AS DATE) <= :pfim";
        $params[':pfim'] = $dtFim;
    }
    $condsPick[] = "v0.parsed_date IS NOT NULL";
    $vPickDateSQL = "WHERE " . implode(' AND ', $condsPick);
}

/* ==== Lógica para focar apenas em 'rel_visita_vis' ==== */
$cleanExpr = "TRIM(REPLACE(REPLACE(v0.rel_visita_vis, CHAR(13), ''), CHAR(10), ''))";
$hasTextExpr = "($cleanExpr IS NOT NULL AND $cleanExpr <> '')";

/* ==== Subselect: LÓGICA para escolher a visita ==== */
$vPick = "
LEFT JOIN (
  SELECT
    v0.fk_internacao_vis AS fk_internacao,
    COALESCE(
      SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN {$hasTextExpr} AND v0.parsed_date IS NOT NULL THEN v0.id_visita END ORDER BY v0.parsed_date DESC, v0.id_visita DESC SEPARATOR ','), ',', 1),
      SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN v0.parsed_date IS NOT NULL THEN v0.id_visita END ORDER BY v0.parsed_date DESC, v0.id_visita DESC SEPARATOR ','), ',', 1),
      SUBSTRING_INDEX(GROUP_CONCAT(v0.id_visita ORDER BY v0.id_visita DESC SEPARATOR ','), ',', 1)
    ) AS id_visita_pick,
    DATE_FORMAT(COALESCE(MAX(CASE WHEN {$hasTextExpr} THEN v0.parsed_date END), MAX(v0.parsed_date)), '%d/%m/%Y') AS data_visita_fmt,
    MAX(v0.data_lancamento_vis) AS last_data_lancamento_raw,
    DATE_FORMAT(MAX(v0.data_lancamento_vis), '%Y-%m-%d') AS last_data_lancamento_iso,
    DATE_FORMAT(MAX(v0.data_lancamento_vis), '%d/%m/%Y') AS last_data_lancamento_fmt,
    DATE_FORMAT(DATE_SUB(MAX(v0.data_lancamento_vis), INTERVAL 30 DAY), '%d/%m/%Y') AS periodo_ini_fmt
  FROM (
    SELECT t.*,
      COALESCE(
        STR_TO_DATE(NULLIF(t.data_visita_vis,''), '%Y-%m-%d %H:%i:%s'), STR_TO_DATE(NULLIF(t.data_visita_vis,''), '%Y-%m-%dT%H:%i:%s'), STR_TO_DATE(NULLIF(t.data_visita_vis,''), '%Y-%m-%d'),
        STR_TO_DATE(NULLIF(t.data_visita_vis,''), '%d/%m/%Y %H:%i:%s'), STR_TO_DATE(NULLIF(t.data_visita_vis,''), '%d/%m/%Y %H:%i'), STR_TO_DATE(NULLIF(t.data_visita_vis,''), '%d/%m/%Y'),
        STR_TO_DATE(NULLIF(t.data_visita_vis,''), '%d-%m-%Y %H:%i:%s'), STR_TO_DATE(NULLIF(t.data_visita_vis,''), '%d-%m-%Y')
      ) AS parsed_date
    FROM $T_VIS t
    WHERE (t.retificado IS NULL OR t.retificado IN (0,'0','','n','N'))
  ) v0
  $vPickDateSQL
  GROUP BY v0.fk_internacao_vis
) v ON v.fk_internacao = i.id_internacao
";

$capPick = "
LEFT JOIN (
  SELECT fk_int_capeante, MAX(id_capeante) AS id_capeante_pick
  FROM $T_CAP
  GROUP BY fk_int_capeante
) cap ON cap.fk_int_capeante = i.id_internacao
";

$capJoin = "LEFT JOIN $T_CAP ca ON ca.id_capeante = cap.id_capeante_pick";

$segJoin = "LEFT JOIN $T_SEG se ON se.id_seguradora = pa.fk_seguradora_pac";

/* ==== JOINs ==== */
$v1Join = "LEFT JOIN $T_VIS v1 ON v1.id_visita = CAST(v.id_visita_pick AS UNSIGNED)";
$uJoin  = "LEFT JOIN $T_USR u  ON u.id_usuario  = v1.fk_usuario_vis";
$uJoin2 = "LEFT JOIN $T_USR u2 ON u2.id_usuario = CAST(NULLIF(v1.visita_auditor_prof_med,'') AS UNSIGNED)";

$aLast = "
LEFT JOIN (
  SELECT fk_id_int_alt, DATE_FORMAT(MAX(COALESCE(STR_TO_DATE(NULLIF(data_alta_alt,''), '%Y-%m-%d %H:%i:%s'), STR_TO_DATE(NULLIF(data_alta_alt,''), '%Y-%m-%d'), STR_TO_DATE(NULLIF(data_alta_alt,''), '%d/%m/%Y'))), '%d/%m/%Y') AS data_alta_alt
  FROM $T_ALT GROUP BY fk_id_int_alt
) a1 ON a1.fk_id_int_alt = i.id_internacao
";

$pcSub = "
LEFT JOIN (
  SELECT x.id_internacao AS fk_int, GROUP_CONCAT(DISTINCT CONCAT_WS(' - ', c.cat, c.descricao) SEPARATOR ' | ') AS patologia, GROUP_CONCAT(DISTINCT c.cat SEPARATOR ' | ') AS cid
  FROM (SELECT id_internacao, fk_patologia_int AS pid FROM $T_INT UNION ALL SELECT id_internacao, fk_patologia2 AS pid FROM $T_INT) x
  LEFT JOIN $T_PAT p ON p.id_patologia = x.pid LEFT JOIN $T_CID c ON c.id_cid = p.fk_cid_10_pat GROUP BY x.id_internacao
) pc ON pc.fk_int = i.id_internacao
";

/* ==== Base + filtros ==== */
$whereConditions = " WHERE 1=1 ";
$paramsBase = $params;

if ($nomePaciente !== '') {
    $whereConditions .= " AND pa.nome_pac LIKE :nome ";
    $paramsBase[':nome'] = "%$nomePaciente%";
}
if ($matriculaPaciente !== '') {
    $whereConditions .= " AND pa.matricula_pac LIKE :matricula ";
    $paramsBase[':matricula'] = "%$matriculaPaciente%";
}
if (!empty($hospitalIds)) {
    $placeholders = [];
    foreach ($hospitalIds as $idx => $hid) {
        $ph = ":hid{$idx}";
        $placeholders[] = $ph;
        $paramsBase[$ph] = $hid;
    }
    $whereConditions .= " AND i.fk_hospital_int IN (" . implode(', ', $placeholders) . ") ";
}
if (!empty($seguradoraIds)) {
    $placeholders = [];
    foreach ($seguradoraIds as $idx => $sid) {
        $ph = ":seg{$idx}";
        $placeholders[] = $ph;
        $paramsBase[$ph] = $sid;
    }
    $whereConditions .= " AND pa.fk_seguradora_pac IN (" . implode(', ', $placeholders) . ") ";
}

// Se período definido, garante que só traga internações com visita escolhida
if ($dtIni !== '' || $dtFim !== '') {
    $whereConditions .= " AND v.id_visita_pick IS NOT NULL ";
}
if ($faturadoVis === 's') {
    $whereConditions .= " AND LOWER(IFNULL(v1.faturado_vis,'')) = 's' ";
} elseif ($faturadoVis === 'n') {
    $whereConditions .= " AND (v1.faturado_vis IS NULL OR v1.faturado_vis = '' OR LOWER(v1.faturado_vis) <> 's') ";
}

$sqlBase = "
FROM $T_INT i
JOIN $T_PAC pa ON pa.id_paciente = i.fk_paciente_int
LEFT JOIN $T_HOS ho ON ho.id_hospital = i.fk_hospital_int
$vPick
$capPick
$capJoin
$segJoin
$v1Join
$uJoin
$uJoin2
$aLast
$pcSub
$whereConditions
";

/* ==== Ordenação ==== */
$defaultOrderExpr = "
  COALESCE(STR_TO_DATE(v.data_visita_fmt,'%d/%m/%Y'), STR_TO_DATE(i.data_intern_int,'%Y-%m-%d %H:%i:%s'), STR_TO_DATE(i.data_intern_int,'%Y-%m-%d'), STR_TO_DATE(i.data_intern_int,'%d/%m/%Y')) DESC,
  pa.nome_pac ASC
";

$sortableColumns = [
    'id_internacao'   => "CAST(i.id_internacao AS UNSIGNED)",
    'id_visita'       => "CAST(v1.id_visita AS UNSIGNED)",
    'senha'           => "i.senha_int",
    'hospital'        => "ho.nome_hosp",
    'nome_paciente'   => "pa.nome_pac",
    'data_internacao' => "COALESCE(STR_TO_DATE(i.data_intern_int,'%Y-%m-%d %H:%i:%s'), STR_TO_DATE(i.data_intern_int,'%Y-%m-%d'), STR_TO_DATE(i.data_intern_int,'%d/%m/%Y'), i.data_intern_int)",
    'data_lancamento' => "v1.data_lancamento_vis",
    'seguradora'      => "COALESCE(NULLIF(TRIM(se.seguradora_seg),''),'Sem seguradora')",
    'valor_liberado'  => "IFNULL(ca.valor_final_capeante, 0)"
];
$sqlOrder = "ORDER BY " . $defaultOrderExpr;
if (isset($sortableColumns[$sortField])) {
    $orderExpr = $sortableColumns[$sortField];
    $orderDirSQL = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
    $sqlOrder = "ORDER BY $orderExpr $orderDirSQL, $defaultOrderExpr";
}

/* ==== SELECT final ==== */
$sqlData = "SELECT " . implode(", ", array_map(fn($k) => $fieldsMap[$k]['sql'], $queryFields)) . " $sqlBase $sqlOrder LIMIT $limite OFFSET $offset";

/* ==== Contagem ==== */
$total = 0;
$errCount = null;
try {
    $countSql = "SELECT COUNT(DISTINCT i.id_internacao) " . $sqlBase;
    $stc = $conn->prepare($countSql);
    foreach ($paramsBase as $k => $v) $stc->bindValue($k, $v);
    $stc->execute();
    $total = (int)$stc->fetchColumn();
} catch (Throwable $e) {
    $errCount = $e->getMessage();
}

/* ==== Dados ==== */
$rows = [];
$errRows = null;
try {
    $st = $conn->prepare($sqlData);
    foreach ($paramsBase as $k => $v) $st->bindValue($k, $v);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errRows = $e->getMessage();
}

/* ==== Hospitais ==== */
$hospitais = [];
try {
    $hStmt = $conn->query("SELECT id_hospital, nome_hosp FROM $T_HOS ORDER BY nome_hosp");
    if ($hStmt) $hospitais = $hStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}
 $seguradoras = [];
try {
    $sStmt = $conn->query("SELECT id_seguradora, seguradora_seg FROM $T_SEG WHERE COALESCE(deletado_seg,'n') <> 's' ORDER BY seguradora_seg");
    if ($sStmt) {
        $rawSeguradoras = $sStmt->fetchAll(PDO::FETCH_ASSOC);
        $bucket = [];
        foreach ($rawSeguradoras as $row) {
            $label = trim((string)($row['seguradora_seg'] ?? ''));
            if ($label === '') {
                continue;
            }
            $key = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
            $key = trim($key);
            if ($key === '') {
                continue;
            }
            if (!isset($bucket[$key])) {
                $bucket[$key] = [
                    'label' => $label,
                    'ids' => [],
                ];
            }
            $bucket[$key]['ids'][] = (int)($row['id_seguradora'] ?? 0);
        }
        $seguradoras = array_values(array_filter(array_map(function ($entry) {
            $ids = array_values(array_filter(array_unique($entry['ids'])));
            if (!$ids) {
                return null;
            }
            return [
                'label' => $entry['label'],
                'ids' => $ids,
                'value' => implode(',', $ids),
            ];
        }, $bucket)));
    }
} catch (Throwable $e) {
}

/* ==== Export (XLSX) ==== */
if ($isExport) {
    try {
        // Carrega os dados com os mesmos filtros/ordenação
        $sql = "SELECT " . implode(", ", array_map(fn($k) => $fieldsMap[$k]['sql'], $selected)) . " $sqlBase $sqlOrder";
        $st  = $conn->prepare($sql);
        foreach ($paramsBase as $k => $v) $st->bindValue($k, $v);
        $st->execute();
        $rowsExp = $st->fetchAll(PDO::FETCH_ASSOC);

        if (ob_get_length()) @ob_end_clean();

        // Autoload PhpSpreadsheet
        require_once __DIR__ . '/vendor/autoload.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Visitas');

        $logoCandidates = [
            __DIR__ . '/img/LogoFullCare.png',
            __DIR__ . '/img/fullCare-01.png',
            __DIR__ . '/img/logo.png',
        ];
        $logoPath = null;
        foreach ($logoCandidates as $candidate) {
            if (is_file($candidate)) {
                $logoPath = $candidate;
                break;
            }
        }
        if ($logoPath !== null) {
            $logo = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $logo->setName('Logo');
            $logo->setDescription('Logo FullCare');
            $logo->setPath($logoPath);
            $logo->setHeight(42);
            $logo->setCoordinates('A2');
            $logo->setWorksheet($sheet);
        }

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, count($selected)));
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(18);
        $sheet->setCellValue('D1', 'Lista de Visitas');
        $sheet->mergeCells('D1:' . $lastCol . '1');
        $sheet->getStyle('D1')->getFont()->setBold(true)->setSize(13);
        $sheet->setCellValue('D2', 'Data da extração: ' . date('d/m/Y H:i'));
        $sheet->mergeCells('D2:' . $lastCol . '2');

        // Cabeçalho
        $sheet->setShowGridlines(false);
        $headerRow = 6;
        $col = 1;
        foreach ($selected as $k) {
            $sheet->setCellValueByColumnAndRow($col, $headerRow, $fieldsMap[$k]['label']);
            $col++;
        }

        // Linhas
        $row = $headerRow + 1;
        foreach ($rowsExp as $r) {
            $col = 1;
            foreach ($selected as $k) {
                $val = $r[$k] ?? '';
                if (in_array($k, ['data_internacao', 'data_visita', 'data_alta', 'data_lancamento'], true)) {
                    $val = fmt_br($val); // saída dd/mm/aaaa
                } elseif ($k === 'faturado_vis') {
                    $val = strtolower((string)$val) === 's' ? 'Sim' : 'Não';
                } elseif ($k === 'cnpj_hospital') {
                    $val = fmt_cnpj($val);
                }
                // Como texto: preserva zeros à esquerda e evita reinterpretação
                $sheet->setCellValueExplicitByColumnAndRow(
                    $col,
                    $row,
                    (string)$val,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $col++;
            }
            $row++;
        }

        // Estilo e largura
        $headerStyle = [
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E5E5E5'],
            ],
            'font' => ['bold' => true],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'BDBDBD'],
                ],
            ],
        ];
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'D0D0D0'],
                ],
            ],
        ];

        $sheet->getStyleByColumnAndRow(1, $headerRow, count($selected), $headerRow)->applyFromArray($headerStyle);
        for ($c = 1; $c <= count($selected); $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
        $lastDataRow = $row - 1;
        if ($lastDataRow >= $headerRow) {
            $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $lastDataRow)->applyFromArray($borderStyle);
        }

        // Download
        $fname = "visitas_" . date("Ymd_His") . ".xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        // $writer->setPreCalculateFormulas(false); // opcional
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        exit;
    } catch (Throwable $e) {
        if (ob_get_length()) @ob_end_clean();
        die("EXPORT XLSX ERROR: " . $e->getMessage());
    }
}

/* ==== Render ==== */
$hideBIMenu = $isFaturamentoView;
include_once __DIR__ . "/templates/header.php";

$brandColor = $isFaturamentoView ? '#0a4fa3' : '#0b3d91';
$brandSoftColor = $isFaturamentoView ? '#d6e4ff' : '#dfe6ff';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
    :root {
        --brand: <?= htmlspecialchars($brandColor, ENT_QUOTES, 'UTF-8') ?>;
        --brand-100: <?= htmlspecialchars($brandSoftColor, ENT_QUOTES, 'UTF-8') ?>;
    }

    .page-title {
        color: #3A3A3A;
    }

    .card {
        border-radius: 14px;
    }

    .card.shadow-sm {
        box-shadow: 0 8px 24px rgba(0, 0, 0, .06) !important;
    }

    .btn-outline-brand {
        border-color: var(--brand);
        color: var(--brand);
        background: #fff;
    }

    .btn-outline-brand:hover {
        background: var(--brand-100);
    }

    .btn-check:checked+.btn-outline-brand {
        background: var(--brand);
        color: #fff;
        border-color: var(--brand);
    }

    .sticky-actions {
        position: sticky;
        bottom: -8px;
        background: #fff;
        padding-top: 6px;
    }

    .input-group>.form-select,
    .input-group>.form-control {
        border-left: 0;
    }
    .input-group {
        align-items: stretch;
    }
    .input-group-text {
        background: #fff;
        border-right: 0;
        display: flex;
        align-items: center;
    }
    .input-group .select2-container {
        flex: 1;
        min-width: 0;
    }
    .input-group .select2-container--default .select2-selection--multiple {
        border-left: 0;
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        min-height: 2.1875rem;
        height: 2.1875rem !important;
        width: 100%;
        padding: 0.25rem 0.45rem;
        display: flex;
        align-items: center;
        margin-right: 0;
    }

    .select2.select2-container {
        width: 100% !important;
    }
    .select2-container--default .select2-selection--multiple {
        border: 1px solid #ced4da;
        border-radius: .375rem;
        min-height: 2.1875rem;
        height: 2.1875rem !important;
        padding: 0.25rem 0.45rem;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: var(--brand-100, #f1f1ff);
        border: none;
        color: #333;
        padding: 0.15rem 0.35rem;
        margin-top: 0.15rem;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: flex;
        flex-wrap: wrap;
        gap: 0.2rem;
    }
    .select2-container--default .select2-selection--multiple .select2-search__field {
        margin-top: 0.15rem;
    }

    .input-group-text {
        background: #fff;
        border-right: 0;
        display: flex;
        align-items: center;
    }

    .faturamento-actions h6 {
        letter-spacing: .08em;
    }
    .faturamento-actions .badge {
        font-size: .85rem;
    }
    .table .col-select {
        width: 60px;
    }
    .table .col-id_internacao,
    .table .col-id_visita {
        width: 95px;
    }
    .th-sortable {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        white-space: nowrap;
    }
    .th-sortable .sort-icons a {
        text-decoration: none;
        font-size: 0.85rem;
        color: #ffffff;
        display: inline-flex;
        line-height: 1;
        margin-left: 2px;
        opacity: 0.7;
    }
    .th-sortable .sort-icons a.active {
        color: #ffd966;
        font-weight: bold;
        opacity: 1;
    }

    @media (min-width: 1200px) {
        .filters-inline {
            display: grid;
            grid-template-columns: minmax(180px, 1.4fr) minmax(180px, 1.4fr) minmax(160px, 1fr) minmax(160px, 1fr) auto;
            align-items: end;
            gap: 0.5rem;
        }
        .filters-inline > .filters-item,
        .filters-inline > .filters-actions {
            width: 100%;
            max-width: none;
        }
        .filters-inline > .filters-actions {
            justify-self: end;
        }
        .filters-inline .input-group,
        .filters-inline .row {
            min-width: 0;
        }
        .filters-inline .input-group > .form-control,
        .filters-inline .input-group > .form-select {
            min-width: 0;
        }
    }
</style>

<div class="container-fluid" style="margin-top:-10px;">
    <h4 class="page-title mt-0 mb-2"><?= h($pageTitle) ?></h4>
    <hr class="mt-1 mb-3">

    <?php if ($DEBUG): ?>
        <div class="alert alert-warning">
            <div><strong>DEBUG ON</strong></div>
            <div>Período: <code><?= h($dtIni) ?></code> a <code><?= h($dtFim) ?></code></div>
            <div><u>SQL DATA</u>:</div>
            <div><code style="white-space:pre-wrap"><?= h($sqlData) ?></code></div>
            <div>Params: <code><?= h(json_encode($paramsBase, JSON_UNESCAPED_UNICODE)) ?></code></div>
            <?php if ($errCount) echo "<div>Count error: <code>" . h($errCount) . "</code></div>"; ?>
            <?php if ($errRows)  echo "<div>Rows error: <code>" . h($errRows) . "</code></div>";  ?>
        </div>
    <?php endif; ?>

    <?php
$fieldIcons = [
        'id_visita'       => 'bi-hash',
        'senha'           => 'bi-key',
        'hospital'        => 'bi-hospital',
        'cnpj_hospital'   => 'bi-building',
        'nome_paciente'   => 'bi-person',
        'matricula'       => 'bi-123',
        'data_internacao' => 'bi-calendar2-plus',
        'data_visita'     => 'bi-calendar2-event',
        'data_lancamento' => 'bi-calendar-event-fill',
        'periodo_faturamento' => 'bi-calendar-range',
        'cid'             => 'bi-hash',
        'auditor_medico'  => 'bi-person-vcard',
        'faturado_vis'    => 'bi-clipboard-check',
        'alta_flag'       => 'bi-box-arrow-up-right',
        'data_alta'       => 'bi-calendar2-check',
    ];
    ?>

    <form method="get" class="card p-3 mb-3 shadow-sm border-0" id="form-visitas">
        <div class="mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <label class="form-label fw-semibold m-0 fs-5">Campos a exibir/exportar</label>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light btn-sm" id="btn-check-all"><i
                        class="bi bi-check2-all me-1"></i>Selecionar todos</button>
                <button type="button" class="btn btn-light btn-sm btn-filtro-limpar" id="btn-uncheck-all"><i
                        class="bi bi-x-lg me-1"></i>Limpar</button>
            </div>
        </div>

        <div class="field-chips d-flex flex-wrap gap-2 mb-3">
            <?php foreach ($fieldsMap as $key => $meta):
                $checked = in_array($key, $selected, true);
                $icon = $fieldIcons[$key] ?? 'bi-check'; ?>
                <input type="checkbox" class="btn-check field-check" id="f_<?= h($key) ?>" name="fields[]"
                    value="<?= h($key) ?>" <?= $checked ? 'checked' : '' ?>>
                <label class="btn btn-outline-brand btn-sm rounded-pill px-3" for="f_<?= h($key) ?>"><i
                        class="bi <?= $icon ?> me-1"></i><?= h($meta['label']) ?></label>
            <?php endforeach; ?>
        </div>

        <div class="mb-2"><label class="form-label fw-semibold m-0">Filtros</label></div>

        <div class="row g-2 align-items-end filters-inline">
            <div class="col-12 col-xl-3 filters-item">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="nome" class="form-control form-control-sm" placeholder="Nome do paciente"
                        value="<?= h($nomePaciente) ?>">
                </div>
            </div>
            <div class="col-12 col-xl-3 filters-item">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-123"></i></span>
                    <input type="text" name="matricula" class="form-control form-control-sm" placeholder="Matrícula do paciente"
                        value="<?= h($matriculaPaciente) ?>">
                </div>
            </div>
            <div class="col-12 col-xl-3 filters-item">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-hospital"></i></span>
                    <select name="hospital_id[]" id="filtro-hospital" class="form-select form-select-sm" multiple>
                        <?php foreach ($hospitais as $h): ?>
                            <?php $isSelected = in_array((int)$h['id_hospital'], $hospitalIds, true); ?>
                            <option value="<?= $h['id_hospital'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                                <?= h($h['nome_hosp']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-12 col-xl-3 filters-item">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-heart-pulse"></i></span>
                    <select name="seguradora_id[]" id="filtro-seguradora" class="form-select form-select-sm" multiple>
                        <?php foreach ($seguradoras as $s): ?>
                            <?php $segSelected = in_array($s['value'], $seguradoraIdsRawNormalized, true); ?>
                            <option value="<?= h($s['value']) ?>" <?= $segSelected ? 'selected' : '' ?>>
                                <?= h($s['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-12 col-xl-2 filters-item">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-calendar2"></i></span>
                            <input type="date" name="dt_ini" class="form-control form-control-sm" value="<?= h($dtIni) ?>" title="De">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-calendar2-check"></i></span>
                            <input type="date" name="dt_fim" class="form-control form-control-sm" value="<?= h($dtFim) ?>" title="Até">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-2 filters-item">
                <div class="row g-2">
                    <div class="col-12 col-sm-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-list-ol"></i></span>
                            <select name="limite" class="form-select form-select-sm" onchange="this.form.submit()">
                                <?php foreach ([10, 20, 50, 100] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $limite == $opt ? 'selected' : '' ?>><?= $opt ?> por página</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-cash-stack"></i></span>
                            <select name="faturado" class="form-select form-select-sm">
                                <option value="" <?= $faturadoVis === '' ? 'selected' : '' ?>>Todos</option>
                                <option value="s" <?= $faturadoVis === 's' ? 'selected' : '' ?>>Faturado</option>
                                <option value="n" <?= $faturadoVis === 'n' ? 'selected' : '' ?>>Não faturado</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-2 d-flex justify-content-end gap-2 filters-actions">
                <button class="btn btn-primary btn-sm px-3 text-nowrap" type="submit">
                    <i class="bi bi-funnel me-1"></i>Aplicar
                </button>
                <button class="btn btn-success btn-sm px-3 text-nowrap" type="submit" name="export" value="1">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar XLSX
                </button>
                <input type="hidden" name="debug" value="<?= $DEBUG ? 1 : 0 ?>">
            </div>
        </div>

        <input type="hidden" name="sort_field" value="<?= h($sortField) ?>">
        <input type="hidden" name="sort_dir" value="<?= h($sortDir) ?>">
    </form>

    <?php if ($isFaturamentoView): ?>
        <div class="card p-3 mb-3 shadow-sm border-0" id="faturamentoActionBox">
            <div class="d-flex flex-wrap gap-3 align-items-center faturamento-actions">
                <div>
                    <h6 class="text-uppercase text-muted mb-1 small">Ações para faturar</h6>
                    <div class="fw-semibold fs-5">Selecione as visitas e confirme o faturamento.</div>
                </div>
                <div class="ms-auto d-flex flex-wrap gap-3 align-items-center">
            <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                <input class="form-check-input" type="checkbox" id="chkSelectAllVisitas">
                <label class="form-check-label" for="chkSelectAllVisitas">Selecionar todos</label>
            </div>
                    <button class="btn btn-primary" id="btnFaturarVisitas" type="button" disabled>
                        <i class="bi bi-currency-dollar me-1"></i>Faturar selecionados
                        <span class="badge bg-light text-dark ms-2" id="badgeSelVisitas">0</span>
                    </button>
                </div>
            </div>
            <div class="mt-2" id="faturamentoActionFeedback"></div>
        </div>
    <?php endif; ?>

    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <?php if ($isFaturamentoView): ?>
                            <th class="text-center col-select">
                                <i class="bi bi-check2-square"></i>
                            </th>
                        <?php endif; ?>
                        <?php foreach ($selected as $k):
                            $label = $fieldsMap[$k]['label'];
                            $sortable = isset($sortableColumns[$k]);
                            $ascActive = $sortable && $sortField === $k && $sortDir === 'asc';
                            $descActive = $sortable && $sortField === $k && $sortDir === 'desc';
                            ?>
                            <th class="col-<?= h($k) ?> text-center">
                                <div class="<?= $sortable ? 'th-sortable justify-content-center' : '' ?>">
                                    <span><?= h($label) ?></span>
                                    <?php if ($sortable): ?>
                                        <span class="sort-icons">
                                            <a href="<?= h($currentPath) ?>?<?= qs_keep(['sort_field' => $k, 'sort_dir' => 'asc', 'pag' => 1]) ?>"
                                                class="<?= $ascActive ? 'active' : '' ?>" title="Ordenar crescente">↑</a>
                                            <a href="<?= h($currentPath) ?>?<?= qs_keep(['sort_field' => $k, 'sort_dir' => 'desc', 'pag' => 1]) ?>"
                                                class="<?= $descActive ? 'active' : '' ?>" title="Ordenar decrescente">↓</a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </th>
                        <?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php if ($rows): foreach ($rows as $r): ?>
                            <tr>
                                <?php if ($isFaturamentoView):
                                    $visitId = (int)($r['id_visita'] ?? 0);
                                    $isFaturado = strtolower((string)($r['faturado_vis'] ?? 'n')) === 's';
                                    ?>
                                    <td class="text-center col-select">
                                        <?php if ($visitId > 0): ?>
                                            <input type="checkbox" class="form-check-input chk-visita"
                                                value="<?= $visitId ?>"
                                                <?= $isFaturado ? 'disabled title="Visita já faturada"' : '' ?>>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php foreach ($selected as $k):
                                    $val = $r[$k] ?? '';
                                    if (in_array($k, ['data_internacao', 'data_visita', 'data_alta', 'data_lancamento'], true)) {
                                        $val = fmt_br($val);
                                    } elseif ($k === 'faturado_vis') {
                                        $val = strtolower((string)$val) === 's' ? 'Sim' : 'Não';
                                    } elseif ($k === 'cnpj_hospital') {
                                        $val = fmt_cnpj($val);
                                    }
                                ?>
                                    <td class="col-<?= h($k) ?>"><?= h($val) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="<?= count($selected) + ($isFaturamentoView ? 1 : 0) ?>">Nada encontrado para os filtros aplicados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php $totalPages = max(1, (int)ceil($total / max(1, $limite))); ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">Total: <?= (int)$total ?> registro(s)</div>
            <nav>
                <ul class="pagination m-0">
                    <li class="page-item <?= $pag <= 1 ? 'disabled' : '' ?>"><a class="page-link"
                            href="<?= h($currentPath) ?>?<?= qs_keep(['pag' => 1]) ?>">&laquo;</a></li>
                    <li class="page-item <?= $pag <= 1 ? 'disabled' : '' ?>"><a class="page-link"
                            href="<?= h($currentPath) ?>?<?= qs_keep(['pag' => max(1, $pag - 1)]) ?>">&lsaquo;</a></li>
                    <li class="page-item disabled"><span class="page-link">Página <?= $pag ?> de
                            <?= $totalPages ?></span></li>
                    <li class="page-item <?= $pag >= $totalPages ? 'disabled' : '' ?>"><a class="page-link"
                            href="<?= h($currentPath) ?>?<?= qs_keep(['pag' => min($totalPages, $pag + 1)]) ?>">&rsaquo;</a></li>
                    <li class="page-item <?= $pag >= $totalPages ? 'disabled' : '' ?>"><a class="page-link"
                            href="<?= h($currentPath) ?>?<?= qs_keep(['pag' => $totalPages]) ?>">&raquo;</a></li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.jQuery && typeof jQuery.fn.select2 === 'function') {
            const initSelect2 = (selector, placeholder) => {
                const $el = jQuery(selector);
                if (!$el.length) return;
                $el.select2({
                    placeholder,
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                    dropdownParent: $el.parent()
                });
            };
            initSelect2('#filtro-hospital', 'Hospitais');
            initSelect2('#filtro-seguradora', 'Seguradoras');
        }
        const formEl = document.getElementById('form-visitas');
        const updateColumnVisibility = (checkbox) => {
            const k = checkbox.value;
            const isChecked = checkbox.checked;
            const cells = document.querySelectorAll('th.col-' + k + ', td.col-' + k);
            if (isChecked && cells.length === 0 && formEl) {
                formEl.submit();
                return;
            }
            cells.forEach(cell => {
                cell.style.display = isChecked ? '' : 'none';
            });
        };
        const fieldCheckboxes = document.querySelectorAll('.field-check');
        fieldCheckboxes.forEach(updateColumnVisibility);
        document.addEventListener('change', e => {
            if (e.target.classList.contains('field-check')) {
                updateColumnVisibility(e.target);
            }
        });
        document.getElementById('btn-check-all')?.addEventListener('click', () => {
            fieldCheckboxes.forEach(chk => {
                if (!chk.checked) {
                    chk.checked = true;
                    updateColumnVisibility(chk);
                }
            });
        });
        document.getElementById('btn-uncheck-all')?.addEventListener('click', () => {
            fieldCheckboxes.forEach(chk => {
                if (chk.checked) {
                    chk.checked = false;
                    updateColumnVisibility(chk);
                }
            });
        });

        const actionBox = document.getElementById('faturamentoActionBox');
        if (actionBox) {
            const badge = document.getElementById('badgeSelVisitas');
            const btnFaturar = document.getElementById('btnFaturarVisitas');
            const feedback = document.getElementById('faturamentoActionFeedback');
            const selectAll = document.getElementById('chkSelectAllVisitas');

            const enabledCheckboxes = () => Array.from(document.querySelectorAll('.chk-visita:not(:disabled)'));
            const checkedCheckboxes = () => Array.from(document.querySelectorAll('.chk-visita:checked'));

            function updateBadge() {
                const total = checkedCheckboxes().length;
                if (badge) badge.textContent = total.toString();
                if (btnFaturar) btnFaturar.disabled = total === 0;
            }

            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    enabledCheckboxes().forEach(chk => {
                        chk.checked = selectAll.checked;
                    });
                    updateBadge();
                });
            }

            document.addEventListener('change', (ev) => {
                if (ev.target.classList.contains('chk-visita')) {
                    if (selectAll && ev.target.checked === false) {
                        selectAll.checked = false;
                    }
                    updateBadge();
                }
            });

            btnFaturar?.addEventListener('click', () => {
                const ids = checkedCheckboxes().map(chk => parseInt(chk.value, 10)).filter(id => id > 0);
                if (!ids.length) {
                    return;
                }
                btnFaturar.disabled = true;
                btnFaturar.classList.add('disabled');
                if (feedback) feedback.innerHTML = '';
                fetch('processa_faturamento_visitas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ ids })
                }).then(resp => resp.json())
                    .then(data => {
                        if (feedback) {
                            const cls = data.success ? 'alert-success' : 'alert-danger';
                            feedback.innerHTML = `<div class="alert ${cls} my-2 py-2">${data.message || 'Operação concluída.'}</div>`;
                        }
                        if (data.success) {
                            setTimeout(() => window.location.reload(), 1200);
                        } else {
                            btnFaturar.disabled = false;
                            btnFaturar.classList.remove('disabled');
                        }
                    })
                    .catch(() => {
                        if (feedback) {
                            feedback.innerHTML = '<div class="alert alert-danger my-2 py-2">Não foi possível concluir o faturamento. Tente novamente.</div>';
                        }
                        btnFaturar.disabled = false;
                        btnFaturar.classList.remove('disabled');
                    });
            });

            updateBadge();
        }
    });
</script>
<?php
include_once __DIR__ . "/templates/footer.php";
ob_end_flush();

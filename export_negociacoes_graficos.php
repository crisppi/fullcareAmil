<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['email_user']) || ($_SESSION['ativo'] ?? '') !== 's') {
    http_response_code(401);
    exit('Não autorizado');
}

require_once __DIR__ . '/globals.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function fetchChartData(PDO $conn, string $sql): array
{
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$baseCondition = "(ng.deletado_neg IS NULL OR ng.deletado_neg != 's')
    AND UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'";

function expandMonthlySeries(array $rows, string $valueKey = 'total'): array
{
    if (!$rows) {
        $year = (int)date('Y');
        $start = new DateTime("$year-01-01");
        $end = new DateTime("$year-12-01");
    } else {
        $periods = array_column($rows, 'periodo_ordenacao');
        sort($periods);
        $start = DateTime::createFromFormat('Y-m', $periods[0]) ?: new DateTime();
        $start->setDate((int)$start->format('Y'), 1, 1);
        $lastKey = end($periods);
        $end = DateTime::createFromFormat('Y-m', $lastKey) ?: new DateTime();
        $end->setDate((int)$end->format('Y'), 12, 1);
    }

    $map = [];
    foreach ($rows as $row) {
        $map[$row['periodo_ordenacao']] = $row[$valueKey] ?? 0;
    }

    $series = [];
    $cursor = clone $start;
    while ($cursor <= $end) {
        $key = $cursor->format('Y-m');
        $series[] = [
            'periodo_label' => $cursor->format('m/Y'),
            'value' => (float)($map[$key] ?? 0)
        ];
        $cursor->modify('+1 month');
    }

    return $series;
}

$monthlySavingRaw = fetchChartData(
    $conn,
    "
        SELECT 
            DATE_FORMAT(COALESCE(ng.data_inicio_neg, ng.data_fim_neg, ng.updated_at), '%Y-%m') AS periodo_ordenacao,
            DATE_FORMAT(COALESCE(ng.data_inicio_neg, ng.data_fim_neg, ng.updated_at), '%d/%m/%Y') AS referencia,
            SUM(COALESCE(ng.saving, 0)) AS total
        FROM tb_negociacao ng
        WHERE $baseCondition
        GROUP BY periodo_ordenacao, referencia
        ORDER BY periodo_ordenacao
    "
);

$monthlyCountRaw = fetchChartData(
    $conn,
    "
        SELECT 
            DATE_FORMAT(COALESCE(ng.data_inicio_neg, ng.data_fim_neg, ng.updated_at), '%Y-%m') AS periodo_ordenacao,
            DATE_FORMAT(COALESCE(ng.data_inicio_neg, ng.data_fim_neg, ng.updated_at), '%d/%m/%Y') AS referencia,
            COUNT(*) AS total
        FROM tb_negociacao ng
        WHERE $baseCondition
        GROUP BY periodo_ordenacao, referencia
        ORDER BY periodo_ordenacao
    "
);

$savingByAuditor = fetchChartData(
    $conn,
    "
        SELECT 
            COALESCE(us.usuario_user, 'Sem responsável') AS auditor,
            SUM(COALESCE(ng.saving, 0)) AS total
        FROM tb_negociacao ng
        LEFT JOIN tb_user us ON ng.fk_usuario_neg = us.id_usuario
        WHERE $baseCondition
        GROUP BY auditor
        ORDER BY total DESC
    "
);

$countByAuditor = fetchChartData(
    $conn,
    "
        SELECT 
            COALESCE(us.usuario_user, 'Sem responsável') AS auditor,
            COUNT(*) AS total
        FROM tb_negociacao ng
        LEFT JOIN tb_user us ON ng.fk_usuario_neg = us.id_usuario
        WHERE $baseCondition
        GROUP BY auditor
        ORDER BY total DESC
    "
);

$savingByType = fetchChartData(
    $conn,
    "
        SELECT 
            COALESCE(ng.tipo_negociacao, 'Não informado') AS tipo,
            SUM(COALESCE(ng.saving, 0)) AS total
        FROM tb_negociacao ng
        WHERE $baseCondition
        GROUP BY tipo
        ORDER BY total DESC
    "
);

$typeByAuditor = fetchChartData(
    $conn,
    "
        SELECT 
            COALESCE(us.usuario_user, 'Sem responsável') AS auditor,
            COALESCE(ng.tipo_negociacao, 'Não informado') AS tipo,
            COUNT(*) AS total
        FROM tb_negociacao ng
        LEFT JOIN tb_user us ON ng.fk_usuario_neg = us.id_usuario
        WHERE $baseCondition
        GROUP BY auditor, tipo
        ORDER BY auditor, tipo
    "
);

$savingByHospital = fetchChartData(
    $conn,
    "
        SELECT 
            COALESCE(ho.nome_hosp, 'Sem hospital') AS hospital,
            SUM(COALESCE(ng.saving, 0)) AS total
        FROM tb_negociacao ng
        LEFT JOIN tb_internacao ac ON ng.fk_id_int = ac.id_internacao
        LEFT JOIN tb_hospital ho ON ac.fk_hospital_int = ho.id_hospital
        WHERE $baseCondition
        GROUP BY hospital
        ORDER BY total DESC
    "
);

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$applyHeader = function ($sheet, string $title, int $colCount): int {
    $logoPath = __DIR__ . '/img/LogoConexAud.png';
    if (file_exists($logoPath)) {
        $logo = new Drawing();
        $logo->setName('Logo');
        $logo->setDescription('Logo Conex');
        $logo->setPath($logoPath);
        $logo->setHeight(32);
        $logo->setCoordinates('A2');
        $logo->setWorksheet($sheet);
    }

    $lastCol = Coordinate::stringFromColumnIndex(max(1, $colCount));
    $sheet->getRowDimension(1)->setRowHeight(28);
    $sheet->getRowDimension(2)->setRowHeight(18);
    $sheet->setCellValue('D1', 'Relatório de Negociações - ' . $title);
    $sheet->mergeCells('D1:' . $lastCol . '1');
    $sheet->getStyle('D1')->getFont()->setBold(true)->setSize(13);
    $sheet->setCellValue('D2', 'Data da extração: ' . date('d/m/Y H:i'));
    $sheet->mergeCells('D2:' . $lastCol . '2');

    $sheet->setShowGridlines(false);
    return 6;
};

$addSheet = function (Spreadsheet $spreadsheet, string $title, array $headers, array $rows) use ($applyHeader) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle($title);
    $headerRow = $applyHeader($sheet, $title, count($headers));
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
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, $headerRow, $header);
        $col++;
    }
    $rowIdx = $headerRow + 1;
    foreach ($rows as $row) {
        $col = 1;
        foreach ($row as $value) {
            $sheet->setCellValueByColumnAndRow($col, $rowIdx, $value);
            $col++;
        }
        $rowIdx++;
    }
    $sheet->getStyleByColumnAndRow(1, $headerRow, count($headers), $headerRow)->applyFromArray($headerStyle);
    if ($rowIdx > $headerRow) {
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A' . $headerRow . ':' . $lastCol . ($rowIdx - 1))->applyFromArray($borderStyle);
    }
    for ($i = 1; $i <= count($headers); $i++) {
        $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
    }
};

$monthlySavingSeries = expandMonthlySeries($monthlySavingRaw, 'total');
$monthlyCountSeries = expandMonthlySeries($monthlyCountRaw, 'total');

$spreadsheet->addSheet(new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'tmp'), 0);
$spreadsheet->setActiveSheetIndex(0);
$spreadsheet->getActiveSheet()->setTitle('Saving mensal');
$activeSheet = $spreadsheet->getActiveSheet();
$headerRow = $applyHeader($activeSheet, 'Saving mensal', 2);
$activeSheet->setCellValue('A' . $headerRow, 'Período');
$activeSheet->setCellValue('B' . $headerRow, 'Saving (R$)');
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
$row = $headerRow + 1;
foreach ($monthlySavingSeries as $item) {
    $activeSheet->setCellValue("A{$row}", $item['periodo_label']);
    $activeSheet->setCellValue("B{$row}", (float)$item['value']);
    $row++;
}
$activeSheet->getStyle('A' . $headerRow . ':B' . $headerRow)->applyFromArray($headerStyle);
$activeSheet->getStyle('A' . $headerRow . ':B' . ($row - 1))->applyFromArray($borderStyle);
$activeSheet->getColumnDimension('A')->setAutoSize(true);
$activeSheet->getColumnDimension('B')->setAutoSize(true);

$addSheet($spreadsheet, 'Negociações mensais', ['Período', 'Qtd'], array_map(fn($r) => [$r['periodo_label'], $r['value']], $monthlyCountSeries));
$addSheet($spreadsheet, 'Saving x Auditor', ['Auditor', 'Saving (R$)'], array_map(fn($r) => [$r['auditor'], $r['total']], $savingByAuditor));
$addSheet($spreadsheet, 'Quantidade x Auditor', ['Auditor', 'Negociações'], array_map(fn($r) => [$r['auditor'], $r['total']], $countByAuditor));
$addSheet($spreadsheet, 'Saving x Tipo', ['Tipo', 'Saving (R$)'], array_map(fn($r) => [$r['tipo'], $r['total']], $savingByType));
$addSheet($spreadsheet, 'Tipo x Auditor', ['Auditor', 'Tipo', 'Qtd'], array_map(fn($r) => [$r['auditor'], $r['tipo'], $r['total']], $typeByAuditor));
$addSheet($spreadsheet, 'Saving x Hospital', ['Hospital', 'Saving (R$)'], array_map(fn($r) => [$r['hospital'], $r['total']], $savingByHospital));

$fileName = 'graficos_negociacoes_' . date('Ymd_His') . '.xlsx';
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

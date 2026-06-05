<?php

if (!defined("FLOW_LOGGER_AUTO_V1")) {
    define("FLOW_LOGGER_AUTO_V1", 1);
    @require_once(__DIR__ . "/../../utils/flow_logger.php");
    if (function_exists("flowLogStart") && function_exists("flowLog")) {
        $__flowCtxAuto = flowLogStart(basename(__FILE__, ".php"), [
            "type" => $_POST["type"] ?? $_GET["type"] ?? null,
            "method" => $_SERVER["REQUEST_METHOD"] ?? null,
        ]);
        register_shutdown_function(function () use ($__flowCtxAuto) {
            $err = error_get_last();
            if ($err && in_array(($err["type"] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                flowLog($__flowCtxAuto, "shutdown.fatal", "ERROR", [
                    "message" => $err["message"] ?? null,
                    "file" => $err["file"] ?? null,
                    "line" => $err["line"] ?? null,
                ]);
            }
            flowLog($__flowCtxAuto, "request.finish", "INFO");
        });
    }
}

ob_start();

require_once("globals.php");
require_once("db.php");
require_once("dao/visitaDao.php");
require_once("dao/internacaoDao.php");
require_once('vendor/autoload.php');

Gate::enforceAction($conn, $BASE_URL, 'generate_pdf', 'Você não tem permissão para gerar PDF.');

$signatureFont = 'times';
$signatureFontPath = __DIR__ . '/../../fonts/Allura-Regular.ttf';
if (file_exists($signatureFontPath)) {
    try {
        $loadedFont = \TCPDF_FONTS::addTTFfont($signatureFontPath, 'TrueTypeUnicode', '', 96);
        if ($loadedFont) {
            $signatureFont = $loadedFont;
        }
    } catch (Throwable $e) {
        error_log('Erro ao carregar fonte de assinatura: ' . $e->getMessage());
    }
}

/**
 * Formata datas do banco (YYYY-MM-DD) para DD/MM/YYYY.
 */
function formatDate($date)
{
    if (!$date || $date === '0000-00-00') {
        return '';
    }
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}

/**
 * Converte indicadores 's'/'n' em 'Sim'/'Não'.
 */
function formatBool($value)
{
    $value = strtolower(trim((string) $value));
    if ($value === 's') return 'Sim';
    if ($value === 'n') return 'Não';
    return '';
}

function parseDateInput(?string $value): ?string
{
    if ($value === null) return null;
    $value = trim($value);
    if ($value === '') return null;
    $formats = ['Y-m-d', 'd/m/Y'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $value);
        if ($dt && $dt->format($fmt) === $value) {
            return $dt->format('Y-m-d');
        }
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

function renderInternacaoSection(RelatorioVisitaPDF $pdf, array $internacao, array $corCinza): void
{
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'INFORMAÇÕES DA INTERNAÇÃO', 0, 1, 'L');
    $pdf->SetDrawColor(200, 200, 200);
    $yLinhaInfo = $pdf->GetY();
    $pdf->Line(15, $yLinhaInfo, 195, $yLinhaInfo);
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetFillColor(...$corCinza);
    $pdf->Cell(50, 6, 'Nome do Paciente:', 1, 0, 'L', true);
    $pdf->Cell(0, 6, $internacao['nome_pac'] ?? '', 1, 1, 'L');
    $pdf->Ln(1);

    $cidCode = trim((string)($internacao['cid_cat'] ?? ''));
    $cidDesc = trim((string)($internacao['cid_descricao'] ?? ''));
    $cidValue = trim($cidCode . ($cidDesc ? ' - ' . $cidDesc : ''));

    $dadosInternacao = [
        'ID da Internação'    => $internacao['id_internacao'] ?? '',
        'Data da Internação'  => formatDate($internacao['data_intern_int'] ?? ''),
        'Hospital'            => $internacao['nome_hosp'] ?? '',
        'Especialidade'       => $internacao['especialidade_int'] ?? '',
        'Origem'              => $internacao['origem_int'] ?? '',
        'Modo de Internação'  => $internacao['modo_internacao_int'] ?? '',
        'Tipo de Admissão'    => $internacao['tipo_admissao_int'] ?? '',
        'Acomodação'          => $internacao['acomodacao_int'] ?? '',
        'Grupo de Patologia'  => $internacao['grupo_patologia_int'] ?? '',
        'Patologia / CID'     => trim(($internacao['patologia2_pat'] ?? '') . ($cidValue ? ' (CID: ' . $cidValue . ')' : '')),
        'UTI'                 => formatBool($internacao['internado_uti_int'] ?? ''),
        'Senha'               => $internacao['senha_int'] ?? '',
    ];

    $itensInt = [];
    foreach ($dadosInternacao as $campo => $valor) {
        $itensInt[] = ['label' => $campo, 'valor' => $valor];
    }

    $colsPerRow  = 3;
    $colWidth    = 60;
    $totalItens  = count($itensInt);
    $totalRows   = (int) ceil($totalItens / $colsPerRow);

    $pdf->SetFillColor(...$corCinza);
    $pdf->SetDrawColor(180, 180, 180);
    $startX = $pdf->GetX();
    for ($row = 0; $row < $totalRows; $row++) {
        $currentY = $pdf->GetY();
        for ($col = 0; $col < $colsPerRow; $col++) {
            $idx  = $row * $colsPerRow + $col;
            $html = '';

            if (isset($itensInt[$idx])) {
                $label = htmlspecialchars($itensInt[$idx]['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $valor = htmlspecialchars((string)$itensInt[$idx]['valor'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html  = '<b>' . $label . ':</b> ' . $valor;
            }

            $x = $startX + $col * $colWidth;
            $pdf->writeHTMLCell(
                $colWidth,
                6,
                $x,
                $currentY,
                $html,
                1,
                0,
                1,
                false,
                'L',
                true
            );
        }
        $pdf->SetY($currentY + 6);
        $pdf->SetX($startX);
    }
    $pdf->Ln(4);
}

function renderVisitaSection(
    RelatorioVisitaPDF $pdf,
    array $visita,
    array $corCinza,
    string $signatureFont,
    ?string $extraTitle = null
): void
{
    $numero = $visita['visita_no_vis'] ?? ($visita['id_visita'] ?? '');
    $titulo = 'DETALHES DA VISITA';
    if ($numero !== '') {
        $titulo .= ' #' . $numero;
    }
    if ($extraTitle) {
        $titulo .= ' - ' . $extraTitle;
    }

    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell(0, 5, $titulo, 0, 1, 'L');
    $pdf->SetDrawColor(200, 200, 200);
    $yLinhaVis = $pdf->GetY();
    $pdf->Line(15, $yLinhaVis, 195, $yLinhaVis);
    $pdf->Ln(2);

    $pdf->SetFillColor(...$corCinza);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->MultiCell(0, 6, 'Relatório da Visita:', 1, 'L', true);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->MultiCell(0, 6, $visita['rel_visita_vis'] ?? '', 1, 'L');
    $pdf->Ln(1);

    $pdf->Ln(3);
}

/**
 * TCPDF com rodapé padrão.
 */
class RelatorioVisitaPDF extends \TCPDF
{
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 7);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, 'Gerado em: ' . date('d/m/Y H:i:s'), 0, 0, 'R');
    }
}

/**
 * Cabeçalho com logo + título.
 */
function renderHeader($pdf, $logoPath)
{
    if (file_exists($logoPath)) {
        $logoWidth = 28;
        $logoY     = 10;
        $pdf->Image($logoPath, 15, $logoY, $logoWidth);
        $yAfterLogo = $pdf->getImageRBY();
        $linhaY = $yAfterLogo + 1;
        $pdf->SetLineWidth(0.1);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(15, $linhaY, 195, $linhaY);
        $pdf->SetY($linhaY + 1.5);
    } else {
        $pdf->SetY(22);
    }

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'RELATÓRIO DE VISITA', 0, 1, 'C');
    $pdf->Ln(1);
}

$idVisita = filter_input(INPUT_GET, "id_visita", FILTER_VALIDATE_INT);
$idInternacaoOverride = filter_input(INPUT_GET, "id_internacao", FILTER_VALIDATE_INT);
$rangeMode = isset($_GET['range']) && $_GET['range'] === '1';
$rangeDateIni = parseDateInput($_GET['data_ini'] ?? null);
$rangeDateFim = parseDateInput($_GET['data_fim'] ?? null);
if ($rangeMode && $rangeDateIni && $rangeDateFim && $rangeDateIni > $rangeDateFim) {
    $tmp = $rangeDateIni;
    $rangeDateIni = $rangeDateFim;
    $rangeDateFim = $tmp;
}

if (!$rangeMode && !$idVisita) {
    die("ID da visita inválido.");
}
if ($rangeMode && !$idInternacaoOverride) {
    die("Internação inválida para geração do PDF em lote.");
}

$visitaDao     = new visitaDao($conn, $BASE_URL);
$internacaoDao = new internacaoDao($conn, $BASE_URL);

$visitaList = [];
if ($rangeMode) {
    $idInternacao = (int)$idInternacaoOverride;
    $internacoes = $internacaoDao->selectAllInternacao('ac.id_internacao = :id_internacao', null, null, [
        ':id_internacao' => (int)$idInternacao
    ]);
    $internacao = $internacoes[0] ?? [];
    if (!$internacao) {
        $internacao = ['id_internacao' => $idInternacao];
    }
    $todasVisitas = $visitaDao->joinVisitaInternacao($idInternacao);
    foreach ($todasVisitas as $row) {
        $data = $row['data_visita_vis'] ?? null;
        if (!$data) continue;
        if ($rangeDateIni && $data < $rangeDateIni) continue;
        if ($rangeDateFim && $data > $rangeDateFim) continue;
        $visitaList[] = $row;
    }
    if (!$visitaList) {
        die("Nenhuma visita encontrada para o período selecionado.");
    }
    usort($visitaList, function($a, $b) {
        $cmp = strcmp($a['data_visita_vis'] ?? '', $b['data_visita_vis'] ?? '');
        if ($cmp === 0) {
            $cmp = ($a['id_visita'] ?? 0) <=> ($b['id_visita'] ?? 0);
        }
        return $cmp;
    });
} else {
    $visitaRows = $visitaDao->joinVisitaInternacaoShow($idVisita);
    if (empty($visitaRows)) {
        die("Visita não encontrada.");
    }
    $visita = $visitaRows[0];
    $idInternacao = $idInternacaoOverride ?: ($visita['id_internacao'] ?? null);
    if (!$idInternacao) {
        die("Internação relacionada não encontrada.");
    }
    $internacoes = $internacaoDao->selectAllInternacao('ac.id_internacao = :id_internacao', null, null, [
        ':id_internacao' => (int)$idInternacao
    ]);
    $internacao = $internacoes[0] ?? $visita;
    $visitaList = [$visita];
}

$pdf = new RelatorioVisitaPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('FullCare');
$pdf->SetAuthor('FullCare');
$pdfTitle = $rangeMode
    ? "Relatório de Visitas - Internação #{$idInternacao}"
    : "Relatório de Visita - #{$idVisita}";
$pdf->SetTitle($pdfTitle);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 18);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->AddPage();

$logoPath = 'img/LogoConexAud.png';
renderHeader($pdf, $logoPath);

$corAzulHeader = [0, 86, 143];
$corCinza      = [236, 239, 241];

renderInternacaoSection($pdf, $internacao, $corCinza);

if ($rangeMode) {
    $periodoDesc = sprintf(
        "Período selecionado: %s — %s",
        $rangeDateIni ? formatDate($rangeDateIni) : 'Início não definido',
        $rangeDateFim ? formatDate($rangeDateFim) : 'Fim não definido'
    );
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, $periodoDesc, 0, 1, 'L');
    $pdf->Ln(2);

    usort($visitaList, function ($a, $b) {
        $dataA = $a['data_visita_vis'] ?? '';
        $dataB = $b['data_visita_vis'] ?? '';
        if ($dataA === $dataB) {
            return ($b['id_visita'] ?? 0) <=> ($a['id_visita'] ?? 0);
        }
        return strcmp($dataB, $dataA);
    });

    foreach ($visitaList as $index => $visitaItem) {
        if ($index > 0) {
            $pdf->Ln(5);
            $pdf->SetDrawColor(210, 210, 210);
            $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
            $pdf->Ln(4);
        }
        $extraTitle = $visitaItem['data_visita_vis'] ? formatDate($visitaItem['data_visita_vis']) : null;
        renderVisitaSection($pdf, $visitaItem, $corCinza, $signatureFont, $extraTitle);
    }
} else {
    foreach ($visitaList as $index => $visitaItem) {
        if ($index > 0) {
            $pdf->AddPage();
            renderHeader($pdf, $logoPath);
        }
        $extraTitle = null;
        renderVisitaSection($pdf, $visitaItem, $corCinza, $signatureFont, $extraTitle);
    }
}

ob_end_clean();
$nomeArquivo = $rangeMode
    ? sprintf(
        "relatorio_visitas_%d_%s_%s.pdf",
        $idInternacao,
        $rangeDateIni ? str_replace('-', '', $rangeDateIni) : 'inicio',
        $rangeDateFim ? str_replace('-', '', $rangeDateFim) : 'fim'
    )
    : sprintf("relatorio_visita_%d.pdf", $idVisita);
$pdf->Output($nomeArquivo, 'D');
exit();

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

$signatureFont = 'times';
$signatureFontPath = __DIR__ . '/../../fonts/Allura-Regular.ttf';
if (file_exists($signatureFontPath)) {
    try {
        $loadedFont = \TCPDF_FONTS::addTTFfont($signatureFontPath, 'TrueTypeUnicode', '', 96);
        if ($loadedFont) {
            $signatureFont = $loadedFont;
        }
    } catch (Throwable $e) {
        error_log('Erro ao carregar fonte de assinatura (internação): ' . $e->getMessage());
    }
}

/**
 * Formata uma data no formato YYYY-MM-DD para DD/MM/YYYY
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
 * Converte valores 's'/'n' para 'Sim' ou 'Não'
 */
function formatBool($value)
{
    $value = strtolower(trim((string) $value));
    if ($value === 's') return 'Sim';
    if ($value === 'n') return 'Não';
    return '';
}

/**
 * Extensão da TCPDF para ter um rodapé padrão
 */
class RelatorioVisitaPDF extends \TCPDF
{
    public function Footer()
    {
        // Posição a 15 mm do final da página
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 7);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, 'Gerado em: ' . date('d/m/Y H:i:s'), 0, 0, 'R');
    }
}

/**
 * Cabeçalho com logo + linha + título
 */
function renderHeader($pdf, $logoPath)
{
    if (file_exists($logoPath)) {
        $logoWidth = 28;
        $logoY     = 10;

        // Logo
        $pdf->Image($logoPath, 15, $logoY, $logoWidth);
        $yAfterLogo = $pdf->getImageRBY();

        // Linha logo abaixo do logo
        $linhaY = $yAfterLogo + 1;
        $pdf->SetLineWidth(0.1);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(15, $linhaY, 195, $linhaY);

        // Cursor logo abaixo da linha
        $pdf->SetY($linhaY + 1.5);
    } else {
        // Se não houver logo, posiciona relativamente alto
        $pdf->SetY(22);
    }

    // Título
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'RELATÓRIO DE VISITA', 0, 1, 'C');
    $pdf->Ln(1);
}

function renderInternacaoResumo($pdf, $internacao, array $corAzul, array $corCinza, int $totalVisitas, $diasInternado)
{
    $pdf->SetFillColor($corAzul[0], $corAzul[1], $corAzul[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell(0, 6, 'INFORMAÇÕES DA INTERNAÇÃO', 0, 1, 'L', true);
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor($corCinza[0], $corCinza[1], $corCinza[2]);
    $pdf->SetDrawColor(180, 180, 180);

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
    $pdf->Ln(3);

    $pdf->SetFillColor($corAzul[0], $corAzul[1], $corAzul[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell(0, 6, 'RESUMO DA INTERNAÇÃO', 0, 1, 'L', true);
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor($corCinza[0], $corCinza[1], $corCinza[2]);
    $pdf->Cell(60, 6, 'Total de Visitas:', 1, 0, 'L', true);
    $pdf->Cell(0, 6, $totalVisitas, 1, 1, 'L');

    $diasInternacaoTexto = is_numeric($diasInternado) ? $diasInternado . ' dias' : $diasInternado;
    $pdf->Cell(60, 6, 'Dias Internação:', 1, 0, 'L', true);
    $pdf->Cell(0, 6, $diasInternacaoTexto, 1, 1, 'L');
    $pdf->Ln(3);
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$id) {
    die("ID de internação inválido.");
}

$visitaDao     = new visitaDao($conn, $BASE_URL);
$internacaoDao = new internacaoDao($conn, $BASE_URL);

// 1) Visitas
$visitas = $visitaDao->joinVisitaInternacao($id);

// 2) Internação
$internacoes = $internacaoDao->selectAllInternacao('ac.id_internacao = :id_internacao', null, null, [
    ':id_internacao' => (int)$id
]);
if (empty($internacoes)) {
    die("Nenhuma internação encontrada para o ID informado.");
}
$internacao = $internacoes[0];

// --- Indicadores simples ---
$totalVisitas = count($visitas);

$dataInternacaoObj   = DateTime::createFromFormat('Y-m-d', $internacao['data_intern_int'] ?? '');
$dataUltimaVisitaObj = null;
if (!empty($visitas)) {
    $datas = array_column($visitas, 'data_visita_vis');
    rsort($datas);
    $dataUltimaVisitaObj = DateTime::createFromFormat('Y-m-d', $datas[0]);
}
$baseData      = $dataUltimaVisitaObj ?: new DateTime();
$diasInternado = $dataInternacaoObj
    ? $dataInternacaoObj->diff($baseData)->days
    : '—';

// --------- PDF ---------
$pdf = new RelatorioVisitaPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('FullCare');
$pdf->SetAuthor('FullCare');
$pdf->SetTitle("Relatório de Visita - Internação #{$id}");
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 18); // margem de quebra em 18 mm

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true); // rodapé automático

$pdf->AddPage();

$logoPath = __DIR__ . '/../../img/LogoFullCare.png';
renderHeader($pdf, $logoPath);

// Cores (azul + cinza padrão)
$corAzulHeader = [0, 86, 143];     // barra de título (RESUMO)
$corCinza      = [236, 239, 241];  // fundo das células

renderInternacaoResumo($pdf, $internacao, $corAzulHeader, $corCinza, $totalVisitas, $diasInternado);

// ===================== DETALHES DAS VISITAS =====================
if (empty($visitas)) {
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->Cell(0, 6, 'Nenhuma visita cadastrada para esta internação.', 0, 1, 'L');
} else {
    $firstVisit = true;
    foreach ($visitas as $idx => $visita) {

        if ($firstVisit) {
            $firstVisit = false;
            $pdf->SetY($pdf->GetY() + 2);
        } else {
            $pdf->AddPage();
            renderHeader($pdf, $logoPath);
            renderInternacaoResumo($pdf, $internacao, $corAzulHeader, $corCinza, $totalVisitas, $diasInternado);
        }

        // Título da visita
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(0, 5, 'DETALHES DA VISITA #' . ($idx + 1), 0, 1, 'L', false);

        $pdf->SetDrawColor(200, 200, 200);
        $yLinhaVis = $pdf->GetY();
        $pdf->Line(15, $yLinhaVis, 195, $yLinhaVis);
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);

        // Campos da visita em 3 colunas
        // Removidos: Visita Médica, Visita Enfermagem, Visita Noturna, Tipo Admissão, Modo Internação, ID Internação, ID Paciente
        $dadosVisita = [
            'Id Visita'      => $visita['id_visita'] ?? '',
            'Data da Visita' => formatDate($visita['data_visita_vis'] ?? ''),
            'Hospital'       => $visita['nome_hosp'] ?? '',
            'Titular'        => $visita['titular_int'] ?? '',
            'Acomodação'     => $visita['acomodacao_int'] ?? '',
        ];

        $itensVis = [];
        foreach ($dadosVisita as $campo => $valor) {
            $itensVis[] = ['label' => $campo, 'valor' => $valor];
        }

        $colsPerRowV = 3;
        $colWidthV   = 60;
        $totalItensV = count($itensVis);
        $totalRowsV  = (int) ceil($totalItensV / $colsPerRowV);

        $pdf->SetFillColor(...$corCinza);
        $pdf->SetDrawColor(180, 180, 180);

        $startXv = $pdf->GetX();
        for ($row = 0; $row < $totalRowsV; $row++) {
            $currentYv = $pdf->GetY();

            for ($col = 0; $col < $colsPerRowV; $col++) {
                $idxV  = $row * $colsPerRowV + $col;
                $htmlV = '';

                if (isset($itensVis[$idxV])) {
                    $labelV = htmlspecialchars($itensVis[$idxV]['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $valorV = htmlspecialchars((string)$itensVis[$idxV]['valor'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $htmlV  = '<b>' . $labelV . ':</b> ' . $valorV;
                }

                $xv = $startXv + $col * $colWidthV;
                $pdf->writeHTMLCell(
                    $colWidthV,
                    6,
                    $xv,
                    $currentYv,
                    $htmlV,
                    1,
                    0,
                    1,
                    false,
                    'L',
                    true
                );
            }
            $pdf->SetY($currentYv + 6);
            $pdf->SetX($startXv);
        }
        $pdf->Ln(3);

        // Relatório da Visita (título em negrito)
        $pdf->SetFillColor(...$corCinza);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->MultiCell(0, 6, 'Relatório da Visita:', 1, 'L', true);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->MultiCell(0, 6, $visita['rel_visita_vis'] ?? '', 1, 'L', false);
        $pdf->Ln(1);

        // Ações da Visita (título em negrito)
        $pdf->SetFillColor(...$corCinza);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->MultiCell(0, 6, 'Ações da Visita:', 1, 'L', true);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->MultiCell(0, 6, $visita['acoes_int_vis'] ?? '', 1, 'L', false);
        $pdf->Ln(1);

        // Profissional responsável
        $profissionalNome     = trim((string) ($visita['auditor_nome'] ?? ''));
        $profissionalRegistro = trim((string) ($visita['auditor_registro'] ?? ''));
        $profissionalLabel    = 'Profissional:';
        $profissionalValor    = trim($profissionalNome . ($profissionalRegistro ? ' - ' . $profissionalRegistro : ''));

        $pdf->SetFillColor(...$corCinza);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->MultiCell(0, 6, $profissionalLabel, 1, 'L', true);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->MultiCell(0, 6, $profissionalValor, 1, 'L', false);
        if ($profissionalNome !== '') {
            $pdf->Ln(1);
            $pdf->SetFont($signatureFont, '', 14);
            $pdf->Cell(0, 5, $profissionalNome, 0, 1, 'C');
            $pdf->SetY($pdf->GetY() - 2.5);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->Cell(0, 3.5, str_repeat('_', 55), 0, 1, 'C');
            if ($profissionalRegistro !== '') {
                $pdf->SetFont('helvetica', '', 8);
                $pdf->Cell(0, 5, $profissionalRegistro, 0, 1, 'C');
            }
        }
        $pdf->Ln(3);
    }
}

ob_end_clean();
$filename = sprintf('rel_internacao-ID No. %s.pdf', $id);
$pdf->Output($filename, 'D');
exit();

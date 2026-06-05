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
error_reporting(E_ALL);

require_once("globals.php");
require_once("db.php");
require_once("dao/capeanteDAO.php");
require_once("vendor/autoload.php");

Gate::enforceAction($conn, $BASE_URL, 'generate_pdf', 'Você não tem permissão para gerar PDF.');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function formatDate($date)
{
    if (!$date || $date === '0000-00-00') return '';
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}

function formatMoney($valor)
{
    return 'R$ ' . number_format(floatval($valor), 2, ',', '.');
}

$id_capeante     = filter_input(INPUT_GET, "id_capeante", FILTER_SANITIZE_NUMBER_INT);
$fk_int_capeante = filter_input(INPUT_GET, "fk_int_capeante", FILTER_SANITIZE_NUMBER_INT);
$save_only = filter_input(INPUT_GET, "save_only", FILTER_SANITIZE_STRING);

if (!$id_capeante || !$fk_int_capeante) die("ID(s) inválido(s).");

$capeanteDao = new capeanteDAO($conn, $BASE_URL);
$where = "ca.id_capeante = {$id_capeante}";
$internacao = $capeanteDao->selectAllcapeante($where);

if (empty($internacao)) die("Conta Capeante não encontrada.");
$data = $internacao[0];

$data['nome_med'] = $data['nome_med'] ?? '';
$data['nome_enf'] = $data['nome_enf'] ?? '';
$data['nome_adm'] = $data['nome_adm'] ?? '';
$data['nome_aud_hosp'] = $data['nome_aud_hosp'] ?? '';

$email = trim($data['email01_hosp']);

$pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('FullCare');
$pdf->SetAuthor('FullCare');
$pdf->SetTitle("Conta Capeante #{$id_capeante}");
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

$corRoxo  = [106, 46, 126];
$corCinza = [230, 230, 230];

$logoPath = 'img/logo_novo.png';
if (file_exists($logoPath)) $pdf->Image($logoPath, 10, 10, 35);
$pdf->Ln(18);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 8, "CONTA CAPEANTE Nº {$data['id_capeante']}", 0, 1, 'C');

$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetFillColor(...$corRoxo);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, 'RESUMO DA INTERNAÇÃO', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(...$corCinza);

$dadosResumo = [
    'Hospital' => $data['nome_hosp'],
    'Paciente' => $data['nome_pac'],
    'Senha' => $data['senha_int'],
    'Data Internação' => formatDate($data['data_intern_int']),
    'Tipo Internação' => $data['tipo_admissao_int'],
    'Modo Admissão' => $data['modo_internacao_int'],
    'Data Inicial' => formatDate($data['data_inicial_capeante']),
    'Data Final' => formatDate($data['data_final_capeante']),
];

$colunas = 3;
$largura_total_util = 277;
$largura_coluna_total = $largura_total_util / $colunas;
$labelW = 45;
$valueW = $largura_coluna_total - $labelW;

foreach (array_chunk($dadosResumo, 3, true) as $linha) {
    foreach ($linha as $rotulo => $valor) {
        $pdf->SetFont('helvetica', $rotulo === 'Paciente' ? 'B' : '', 9);
        $pdf->Cell($labelW, 6, $rotulo, 1, 0, 'L', true);
        $pdf->Cell($valueW, 6, $valor, 1, 0, 'L');
    }
    $pdf->Ln();
}

$blocos = [
    'CONSOLIDADO CONTA' => [
        'Valor Apresentado' => $data['valor_apresentado_capeante'],
        'Valor Final' => $data['valor_final_capeante'],
    ],
    'GLOSAS CONSOLIDADAS' => [
        'Glosa Total' => $data['valor_glosa_total'],
        'Glosa Médica' => $data['valor_glosa_med'],
        'Glosa Enfermagem' => $data['valor_glosa_enf'],
    ],
    'VALORES POR SEGMENTO' => [
        'Honorários' => $data['valor_honorarios'],
        'MatMed' => $data['valor_matmed'],
        'SADT' => $data['valor_sadt'],
        'Oxigenioterapia' => $data['valor_oxig'],
        'Taxas' => $data['valor_taxa'],
        'Diárias' => $data['valor_diarias'],
        'OPME' => $data['valor_opme'],
    ],
    'GLOSAS POR SEGMENTO' => [
        'Honorários' => $data['glosa_honorarios'],
        'MatMed' => $data['glosa_matmed'],
        'SADT' => $data['glosa_sadt'],
        'Oxigenioterapia' => $data['glosa_oxig'],
        'Taxas' => $data['glosa_taxas'],
        'Diárias' => $data['glosa_diaria'],
        'OPME' => $data['glosa_opme'],
    ],
];

foreach ($blocos as $titulo => $valores) {
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetFillColor(...$corRoxo);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 7, $titulo, 0, 1, 'L', true);

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(...$corCinza);
    $pdf->SetTextColor(0, 0, 0);

    $col = 0;
    foreach ($valores as $rotulo => $valor) {
        $pdf->Cell($labelW, 6, $rotulo, 1, 0, 'L', true);
        $pdf->Cell($valueW, 6, formatMoney($valor), 1, 0, 'L');
        $col++;
        if ($col == 3) {
            $pdf->Ln();
            $col = 0;
        }
    }
    if ($col !== 0) $pdf->Ln();
}

// Espaço antes das assinaturas
$pdf->Ln(4);
$pdf->SetFont('times', 'BI', 12);
$pdf->Ln(10);

$assinaturas = [
    'Médico(a) Auditor(a)' => $data['nome_med'],
    'Enfermeiro(a) Auditor(a)' => $data['nome_enf'],
    'Administrativo(a)' => $data['nome_adm'],
    'Responsável Hospital' => $data['nome_aud_hosp']
];
//  Adiciona assinaturas
foreach ($assinaturas as $cargo => $nome) {
    $pdf->MultiCell(65, 9, $nome, 0, 'C', false, 0);
}
$pdf->Ln(7); // Espaço antes dos cargos
$pdf->SetFont('helvetica', '', 9);
foreach ($assinaturas as $cargo => $nome) {
    $pdf->MultiCell(65, 5, $cargo, 0, 'C', false, 0);
}

$pdf->Ln(7);
$pdf->SetFont('helvetica', 'I', 8);
setlocale(LC_TIME, 'pt_BR.utf8');
$pdf->Cell(0, 10, 'São Paulo, ' . strftime('%d de %B de %Y'), 0, 1, 'C');

// Geração ou envio do PDF
if ($save_only === '1') {
    header('Content-Type: application/pdf');
    header("Content-Disposition: attachment; filename=ContaCapeante_{$id_capeante}.pdf");
    echo $pdf->Output("ContaCapeante_{$id_capeante}.pdf", 'S');
    exit;
}

$pdfString = $pdf->Output('', 'S');

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtps.uhserver.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'diretoriaexecutiva@accertconsult.com.br';
    $mail->Password = 'Accert@1206';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

    $mail->setFrom('diretoriaexecutiva@accertconsult.com.br', 'FullCare Auditoria');
    $mail->addAddress($email, $data['nome_hosp']);
    $mail->Subject = "Conta Capeante nº {$data['id_capeante']}";
    $mail->Body = "Prezados,\n\nSegue em anexo a Conta Capeante nº {$data['id_capeante']} referente ao paciente {$data['nome_pac']}.\n\nAtenciosamente,\nEquipe FullCare";
    $mail->addStringAttachment($pdfString, "ContaCapeante_{$id_capeante}.pdf");
    $mail->send();

    header("Location: list_internacao_cap.php");
    exit;
} catch (Exception $e) {
    echo "Erro ao enviar e-mail: " . $mail->ErrorInfo;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "E-mail do hospital inválido: [" . htmlspecialchars($data['email01_hosp']) . "]";
    exit;
}

if ($save_only === '1') {
    header('Content-Type: application/pdf');
    header("Content-Disposition: attachment; filename=ContaCapeante_{$id_capeante}.pdf");
    readfile($pdfPath);
    exit;
}

if (isset($_GET['print_only']) && $_GET['print_only'] == '1') {
    header('Content-Type: application/pdf');
    echo $pdf->Output('', 'I'); // exibe o PDF diretamente no iframe
    exit;
}

<?php
define('SKIP_HEADER', true);
include_once("check_logado.php");
include_once("globals.php");

$items = [
    'contas-auditadas-hospital' => ['target' => 'bi/financeiro-realizado'],
    'custo-mensal-hospital' => ['target' => 'bi/gastos-hospital'],
    'glosa-hospital' => ['target' => 'bi/rede-glosa'],
    'contas-auditadas-auditor' => ['target' => 'IndicadorEssencialAuditorBI.php?modo=contas'],
    'glosa-auditor' => ['target' => 'IndicadorEssencialAuditorBI.php?modo=glosa'],
    'saving-hospital' => ['target' => 'bi/saving'],
    'saving-auditor' => ['target' => 'bi/saving-por-auditor'],
    'custo-patologia' => ['target' => 'bi/gastos-patologia'],
    'custo-antecedente' => ['target' => 'bi/antecedente'],
    'custo-uti' => ['target' => 'IndicadorEssencialUtiBI.php?modo=custo'],
    'percentual-internacao-uti' => ['target' => 'IndicadorEssencialUtiBI.php?modo=percentual'],
    'eventos-adversos-hospital' => ['target' => 'bi/rede-eventos-adversos'],
    'obitos-hospital' => ['target' => 'bi/qualidade-obitos'],
    'qualidade-hospital' => ['target' => 'bi/rede-comparativa'],
];

$slug = trim((string)(filter_input(INPUT_GET, 'slug') ?? ''));
if ($slug === '' || !isset($items[$slug])) {
    header('Location: ' . rtrim((string)$BASE_URL, '/') . '/IndicadoresEssenciaisHubBI.php', true, 302);
    exit;
}

$start = (string)(filter_input(INPUT_GET, 'data_inicio') ?: date('Y-m-01', strtotime('-5 months')));
$end = (string)(filter_input(INPUT_GET, 'data_fim') ?: date('Y-m-d'));
$ano = (int)date('Y', strtotime($end));

$params = [
    'data_inicio' => $start,
    'data_fim' => $end,
    'ano' => $ano,
    'ie' => $slug,
];

$target = rtrim((string)$BASE_URL, '/') . '/' . ltrim($items[$slug]['target'], '/');
$sep = (strpos($target, '?') === false) ? '?' : '&';
header('Location: ' . $target . $sep . http_build_query($params), true, 302);
exit;

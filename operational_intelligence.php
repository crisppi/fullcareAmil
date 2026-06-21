<?php
include_once("check_logado.php");
include_once("globals.php");
require_once("app/services/OperationalIntelligenceService.php");
include_once("models/hospital.php");
include_once("dao/hospitalDao.php");
include_once("models/seguradora.php");
include_once("dao/seguradoraDao.php");

$service = new OperationalIntelligenceService($conn);
$forecastData = $service->demandForecast();
$anomalyData = $service->anomalyDetection();
$conversionData = $service->conversionScores(8);

$hospitalIdFilter = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT);
$seguradoraIdFilter = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT);

$hospitalDao = new hospitalDAO($conn, $BASE_URL);
$seguradoraDao = new seguradoraDAO($conn, $BASE_URL);
$hospitalOptions = $hospitalDao->findGeral();
$seguradoraOptions = $seguradoraDao->findAll();

$hospitalForecast = null;
if ($hospitalIdFilter || $seguradoraIdFilter) {
    $hospitalForecast = $service->hospitalForecast($hospitalIdFilter ?: null, $seguradoraIdFilter ?: null);
}
$losForecasts = $service->lengthOfStayForecasts();

$insightNotes = [];
$trend = $forecastData['trend'] ?? 0;
if ($trend > 1.5) {
    $insightNotes[] = "Demanda prevista em tendência de alta (+ " . number_format($trend, 1) . " casos/dia); reforçar escala de visitas no início da semana.";
} elseif ($trend < -1.5) {
    $insightNotes[] = "Demanda em queda (" . number_format(abs($trend), 1) . " casos/dia); oportunidade de antecipar auditorias e faturamento.";
}

if (!empty($anomalyData['is_anomaly'])) {
    $insightNotes[] = "Volume de hoje (" . (int)$anomalyData['today'] . ") ultrapassou o limite histórico; investigar causas e acionar coordenação.";
}

if (!empty($conversionData)) {
    $criticalConversion = null;
    foreach ($conversionData as $conv) {
        if (($conv['risk'] ?? '') === 'alto') {
            $criticalConversion = $conv;
            break;
        }
    }
    if ($criticalConversion) {
        $insightNotes[] = "Conversão baixa em " . $criticalConversion['hospital'] . " (" .
            number_format($criticalConversion['probability'] * 100, 1) .
            "%) — revisar negociações recentes.";
    }
}

if ($hospitalForecast && !empty($hospitalForecast['available'])) {
    $contextParts = [];
    if (!empty($hospitalForecast['filters']['hospital']['nome'])) {
        $contextParts[] = $hospitalForecast['filters']['hospital']['nome'];
    }
    if (!empty($hospitalForecast['filters']['operadora']['nome'])) {
        $contextParts[] = $hospitalForecast['filters']['operadora']['nome'];
    }
    $contextLabel = $contextParts ? implode(' / ', $contextParts) : 'Seleção atual';
    $insightNotes[] = "Previsão direcionada para {$contextLabel}: média diária de " .
        number_format($hospitalForecast['avg_recent'] ?? 0, 1) .
        " com tendência " . (($hospitalForecast['trend'] ?? 0) >= 0 ? 'de alta' : 'de queda') .
        "; alinhar dimensionamento de visitas/UTI para os próximos dias.";
}

if (!empty($losForecasts['available'])) {
    $atrasados = array_filter($losForecasts['entries'], fn($row) => ($row['status'] ?? '') === 'atrasado');
    $atencao = array_filter($losForecasts['entries'], fn($row) => ($row['status'] ?? '') === 'atencao');
    if ($atrasados) {
        $nomes = array_slice(array_map(fn($row) => $row['nome_pac'] ?? ('#' . $row['id_internacao']), $atrasados), 0, 3);
        $insightNotes[] = count($atrasados) . " paciente(s) acima do previsto (" . implode(', ', $nomes) . "); priorizar negociação de alta e revisão terapêutica.";
    } elseif ($atencao) {
        $insightNotes[] = count($atencao) . " paciente(s) entrando na janela crítica de alta; alinhar giro de leitos.";
    }
}

if (!$insightNotes) {
    $insightNotes[] = "Nenhum alerta relevante identificado hoje. Manter rotinas e monitorar novos eventos.";
}

include_once("templates/header.php");
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Previsões Operacionais</title>
    <style>
    .insight-card {
        border-radius: 12px;
        border: 1px solid #e7e7e7;
        background: #fff;
        margin-bottom: 1rem;
        padding: 1rem 1.1rem;
        box-shadow: 0 10px 25px rgba(35, 102, 147, 0.08);
    }

    .insight-card h4 {
        color: #2f6f9f;
        font-weight: 600;
        font-size: .96rem;
    }

    .forecast-table td,
    .forecast-table th {
        font-size: 0.78rem;
        padding-top: .55rem;
        padding-bottom: .55rem;
    }

    .container-fluid {
        margin-top: 12px !important;
        padding-bottom: 12px !important;
    }

    .container-fluid h2,
    .container-fluid h3 {
        font-size: 1.06rem;
    }

    .container-fluid .text-muted,
    .container-fluid .small,
    .container-fluid .table,
    .container-fluid label,
    .container-fluid .form-select,
    .container-fluid .form-control,
    .container-fluid .btn {
        font-size: .78rem;
    }

    .risk-alto {
        color: #dc3545;
        font-weight: 600;
    }

    .risk-atenção {
        color: #ff9800;
        font-weight: 600;
    }

    .risk-positivo {
        color: #198754;
        font-weight: 600;
    }

    .operational-report-page {
        margin-top: 12px !important;
        padding: 0 14px 30px !important;
        color: #27364a;
    }

    .operational-report-page .row {
        --bs-gutter-x: 12px;
        --bs-gutter-y: 12px;
    }

    .operational-report-page .alert-info {
        background: linear-gradient(120deg, #e8f5fd 0%, #f7fbff 74%);
        border: 1px solid rgba(47, 111, 159, .22);
        border-radius: 12px;
        box-shadow: 0 8px 18px rgba(35, 102, 147, .08);
        color: #5b6f87;
        font-size: .78rem;
        font-weight: 560;
        line-height: 1.35;
        margin-bottom: 10px;
        padding: 12px 14px;
    }

    .operational-report-page .alert-info strong {
        color: #21364f;
        font-size: .86rem;
        font-weight: 820;
    }

    .operational-report-page .insight-card {
        background: linear-gradient(180deg, #fff 0%, #fbfdff 100%);
        border: 1px solid rgba(47, 111, 159, .16);
        border-radius: 12px;
        box-shadow: 0 8px 18px rgba(35, 102, 147, .08);
        margin-bottom: 10px;
        padding: 12px 14px;
    }

    .operational-report-page .insight-card h4 {
        color: #21364f;
        font-size: .88rem;
        font-weight: 820;
        letter-spacing: 0;
        margin: 0 0 7px;
    }

    .operational-report-page .text-muted {
        color: #63758b !important;
    }

    .operational-report-page p {
        font-size: .8rem;
        line-height: 1.35;
    }

    .operational-report-page small,
    .operational-report-page .small {
        font-size: .7rem !important;
    }

    .operational-report-page .display-5 {
        color: #1f2f45;
        font-size: 1.45rem;
        font-weight: 780;
        line-height: 1;
    }

    .operational-report-page .table {
        border-collapse: separate;
        border-spacing: 0;
        color: #34475d;
        font-size: .74rem;
        margin-bottom: 8px;
        overflow: hidden;
    }

    .operational-report-page .table thead th {
        background: #2f6f9f;
        border: 0;
        color: #fff;
        font-size: .62rem;
        font-weight: 820;
        letter-spacing: .04em;
        padding: 7px 8px;
        text-transform: uppercase;
    }

    .operational-report-page .table tbody td {
        border-bottom: 1px solid #e1edf6;
        padding: 7px 8px;
        vertical-align: middle;
    }

    .operational-report-page .table tbody tr:nth-child(even) td {
        background: #f3f8fc;
    }

    .operational-report-page .table-responsive {
        border-radius: 10px;
        overflow: hidden;
    }

    .operational-report-page .form-label {
        color: #5a6e86 !important;
        font-size: .68rem !important;
        font-weight: 760;
        margin-bottom: 4px;
    }

    .operational-report-page .form-select,
    .operational-report-page .form-control {
        background-color: #fff;
        border: 1px solid #bfd4e6;
        border-radius: 8px;
        color: #25364b;
        font-size: .76rem;
        min-height: 34px;
        padding: 5px 9px;
    }

    .operational-report-page .btn {
        border-radius: 9px;
        font-size: .74rem;
        font-weight: 720;
        min-height: 34px;
        padding: 6px 11px;
    }

    .operational-report-page .btn-primary {
        background: linear-gradient(135deg, #2f6f9f, #55b4d4);
        border: 0;
        box-shadow: 0 7px 15px rgba(35, 102, 147, .16);
    }

    .operational-report-page .border.rounded {
        background: #f8fbfe;
        border-color: #d7e6f1 !important;
        border-radius: 10px !important;
    }

    .operational-report-page ul {
        color: #34475d;
        font-size: .78rem;
        line-height: 1.5;
        padding-left: 1rem;
    }

    @media (max-width: 900px) {
        .operational-report-page {
            padding: 0 12px 28px !important;
        }
    }
    </style>
    <link href="<?= $BASE_URL ?>css/operational_reports.css?v=<?= @filemtime(__DIR__ . '/css/operational_reports.css') ?>" rel="stylesheet">
    <style>
    .operational-report-page {
        margin-top: 8px !important;
        padding: 0 14px 28px !important;
    }

    .operational-report-page .fc-module-header {
        margin-bottom: 10px !important;
    }

    .operational-report-page .op-explain {
        align-items: center;
        background: #fff !important;
        border: 1px solid rgba(47, 111, 159, .16) !important;
        border-left: 4px solid #2f6f9f !important;
        border-radius: 10px !important;
        box-shadow: 0 7px 16px rgba(35, 102, 147, .07) !important;
        color: #5b6f87 !important;
        display: flex;
        gap: 8px;
        margin-bottom: 10px !important;
        padding: 8px 11px !important;
    }

    .operational-report-page .op-explain strong {
        color: #21364f !important;
        font-size: .78rem !important;
        font-weight: 820 !important;
        white-space: nowrap;
    }

    .operational-report-page .op-explain span {
        font-size: .72rem !important;
        line-height: 1.25 !important;
    }

    .operational-report-page .row {
        --bs-gutter-x: 10px !important;
        --bs-gutter-y: 10px !important;
    }

    .operational-report-page .insight-card {
        border: 1px solid rgba(47, 111, 159, .16) !important;
        border-radius: 10px !important;
        box-shadow: 0 7px 16px rgba(35, 102, 147, .07) !important;
        margin: 0 0 10px !important;
        padding: 10px 12px !important;
    }

    .operational-report-page .insight-card h4 {
        color: #21364f !important;
        font-size: .82rem !important;
        font-weight: 820 !important;
        margin-bottom: 5px !important;
    }

    .operational-report-page .insight-card p {
        font-size: .74rem !important;
        line-height: 1.25 !important;
        margin-bottom: 7px !important;
    }

    .operational-report-page .display-5 {
        color: #1f2f45 !important;
        font-size: 1.35rem !important;
        font-weight: 820 !important;
    }

    .operational-report-page .forecast-table th,
    .operational-report-page .table thead th {
        background: #2f6f9f !important;
        color: #fff !important;
        font-size: .6rem !important;
        padding: 6px 8px !important;
    }

    .operational-report-page .forecast-table td,
    .operational-report-page .table tbody td {
        font-size: .68rem !important;
        padding: 6px 8px !important;
    }

    .operational-report-page .form-select,
    .operational-report-page .form-control {
        border: 1px solid #bfd4e6 !important;
        border-radius: 8px !important;
        font-size: .72rem !important;
        height: 32px !important;
        min-height: 32px !important;
    }

    .operational-report-page .btn {
        border-radius: 8px !important;
        font-size: .72rem !important;
        height: 32px !important;
        min-height: 32px !important;
    }

    .operational-report-page .btn-primary {
        background: linear-gradient(135deg, #2f6f9f, #55b4d4) !important;
        border: 0 !important;
    }

    .operational-report-page small,
    .operational-report-page .small,
    .operational-report-page .text-muted {
        font-size: .68rem !important;
        line-height: 1.22 !important;
    }
    </style>
</head>

<body>
    <div class="container-fluid mt-5 pt-4 operational-report-page">
        <div class="fc-module-header fc-module-header--inteligencia">
            <div class="fc-module-header__copy">
                <p class="fc-module-header__kicker">Inteligência Operacional</p>
                <h1 class="fc-module-header__title">Previsões operacionais</h1>
                <p class="fc-module-header__subtitle">Demanda, anomalias, conversão e volume previsto com base nos dados operacionais do FullCare Amil.</p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info op-explain">
                    <strong>Como funciona</strong>
                    <span>Este painel calcula previsões de demanda com base nas últimas
                    internações, procura por anomalias diárias e estima a probabilidade de conversão das negociações dos
                    últimos 90 dias. Todos os cálculos são executados dentro do FullCare Amil, sem integrar ferramentas
                    externas, e podem ser atualizados ao recarregar a página.</span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-6">
                <div class="insight-card">
                    <h4>Previsão de demanda (próximos 7 dias)</h4>
                    <p class="text-muted mb-2"><?= htmlspecialchars($forecastData['message']) ?></p>
                    <div class="table-responsive">
                        <table class="table table-sm forecast-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Casos previstos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($forecastData['forecast'] as $row): ?>
                                <tr>
                                    <td><?= date('d/m', strtotime($row['date'])) ?></td>
                                    <td><?= (int) $row['value'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">
                        Média recente: <?= number_format($forecastData['avg_recent'], 1) ?> /
                        Tendência: <?= number_format($forecastData['trend'], 1) ?> casos.
                    </small>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="insight-card">
                    <h4>Detecção de anomalias</h4>
                    <p class="display-5 mb-0"><?= (int) $anomalyData['today'] ?></p>
                    <p class="text-muted">Internações hoje</p>
                    <p class="<?= $anomalyData['is_anomaly'] ? 'text-danger' : 'text-success' ?>">
                        <?= htmlspecialchars($anomalyData['message']) ?>
                    </p>
                    <small class="text-muted">
                        Média 30 dias: <?= number_format($anomalyData['average'], 1) ?>
                        • Limite: <?= number_format($anomalyData['threshold'], 1) ?>
                        • Desvio padrão: <?= number_format($anomalyData['std_dev'], 1) ?>
                    </small>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="insight-card">
                    <h4>Probabilidade de conversão por hospital (últimos 90 dias)</h4>
                    <?php if (empty($conversionData)): ?>
                    <p class="text-muted mb-0">Ainda não há negociações suficientes para projeção.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Hospital</th>
                                    <th class="text-center">Negociações</th>
                                    <th class="text-center">Concluídas</th>
                                    <th class="text-center">Prob. de Conversão</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conversionData as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['hospital']) ?></td>
                                    <td class="text-center"><?= $item['total'] ?></td>
                                    <td class="text-center"><?= $item['concluidas'] ?></td>
                                    <td class="text-center <?= 'risk-' . $item['risk'] ?>">
                                        <?= number_format($item['probability'] * 100, 1) ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">Classificação automática: abaixo de 45% (risco alto), entre 45% e 65%
                        (atenção), acima de 65% (positivo).</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="insight-card">
                    <h4>Previsão de volume por hospital / operadora</h4>
                    <p class="text-muted mb-3">
                        Utilize os filtros abaixo para estimar novas internações por hospital e/ou operadora.
                        A previsão considera os últimos 120 dias de histórico e projeta até 21 dias à frente.
                    </p>
                    <form method="GET" class="row g-3 align-items-end mb-3">
                        <div class="col-md-4">
                            <label for="hospital_id" class="form-label small text-muted">Hospital</label>
                            <select class="form-select" id="hospital_id" name="hospital_id">
                                <option value="">(opcional)</option>
                                <?php foreach ($hospitalOptions as $hospital): ?>
                                <option value="<?= (int)$hospital['id_hospital'] ?>"
                                    <?= ($hospitalIdFilter == (int)$hospital['id_hospital']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($hospital['nome_hosp'] ?? ('#' . $hospital['id_hospital'])) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="seguradora_id" class="form-label small text-muted">Operadora</label>
                            <select class="form-select" id="seguradora_id" name="seguradora_id">
                                <option value="">(opcional)</option>
                                <?php foreach ($seguradoraOptions as $seg): ?>
                                <option value="<?= (int)$seg['id_seguradora'] ?>"
                                    <?= ($seguradoraIdFilter == (int)$seg['id_seguradora']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($seg['seguradora_seg'] ?? ('#' . $seg['id_seguradora'])) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-bolt me-1"></i> Gerar previsão
                            </button>
                        </div>
                        <?php if ($hospitalIdFilter || $seguradoraIdFilter): ?>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">&nbsp;</label>
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary w-100">
                                Limpar
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>

                    <?php if ($hospitalForecast && !empty($hospitalForecast['available'])): ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-muted text-uppercase">Contexto</div>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($hospitalForecast['filters']['hospital']['nome'] ?? 'Todos os hospitais') ?>
                                </div>
                                <div class="text-muted">
                                    <?= htmlspecialchars($hospitalForecast['filters']['operadora']['nome'] ?? 'Todas as operadoras') ?>
                                </div>
                                <div class="small mt-2 text-muted">
                                    Média recente: <?= number_format($hospitalForecast['avg_recent'], 1) ?> casos/dia<br>
                                    Tendência: <?= number_format($hospitalForecast['trend'], 1) ?> casos/dia
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-muted text-uppercase mb-2">Projeção</div>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Data</th>
                                                <th>Casos previstos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($hospitalForecast['forecast'] as $row): ?>
                                            <tr>
                                                <td><?= date('d/m', strtotime($row['date'])) ?></td>
                                                <td><?= (int)$row['value'] ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted mb-0"><?= htmlspecialchars($hospitalForecast['message']) ?></p>
                    <?php elseif ($hospitalIdFilter || $seguradoraIdFilter): ?>
                    <p class="text-muted mb-0">
                        <?= htmlspecialchars($hospitalForecast['message'] ?? 'Não foi possível gerar a previsão para esta seleção.') ?>
                    </p>
                    <?php else: ?>
                    <p class="text-muted mb-0">Selecione um hospital e/ou operadora para calcular a previsão direcionada.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-12">
                <div class="insight-card">
                    <h4>Insights automáticos</h4>
                    <ul class="mb-0">
                        <?php foreach ($insightNotes as $note): ?>
                        <li><?= htmlspecialchars($note) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>

</html>

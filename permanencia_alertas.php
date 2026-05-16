<?php
include_once("check_logado.php");
include_once("globals.php");
require_once("app/services/OperationalIntelligenceService.php");

$service = new OperationalIntelligenceService($conn);
$losForecasts = $service->lengthOfStayForecasts();

include_once("templates/header.php");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Previsão de permanência</title>
    <style>
        .insight-card {
            border-radius: 12px;
            border: 1px solid #e7e7e7;
            background: #fff;
            margin: 0.35rem 0 0.8rem;
            padding: 1rem 1.1rem;
            box-shadow: 0 10px 25px rgba(95,35,99,0.08);
        }
        .container-fluid {
            margin-top: 12px !important;
            padding: 0 0 12px !important;
        }
        .container-fluid h2 {
            font-size: 1.06rem;
        }
        .container-fluid .alert,
        .container-fluid .table,
        .container-fluid .text-muted,
        .container-fluid small {
            font-size: .78rem;
        }
        .table thead th,
        .table td {
            padding-top: .55rem;
            padding-bottom: .55rem;
        }
        .alert {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid" style="margin-top:24px; padding:0 0 16px;">
        <div class="row mb-2">
            <div class="col-12">
                <h2 class="mb-0 fw-semibold" style="color:#5e2363;">Previsão de permanência e alertas</h2>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-12">
                <div class="alert alert-info">
                    <strong>Previsão de permanência e alertas de alta</strong> — Estimativa de quantos dias cada paciente deve permanecer internado, baseada no histórico dos últimos 12 meses (regressão simplificada). Casos acima do intervalo previsto são sinalizados para acelerar giro de leitos, visitas e negociações com operadoras.
                </div>
            </div>
        </div>

        <div class="insight-card">
            <?php if (!empty($losForecasts['available'])): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Hospital</th>
                            <th>Operadora</th>
                            <th class="text-center">Dias internado</th>
                            <th class="text-center">Previsão total (±)</th>
                            <th class="text-center">Restante estimado</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($losForecasts['entries'] as $entry): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($entry['nome_pac'] ?? 'Paciente') ?></div>
                                <small class="text-muted">Int. #<?= (int)$entry['id_internacao'] ?> · <?= date('d/m/Y', strtotime($entry['data_intern_int'])) ?></small>
                            </td>
                            <td><?= htmlspecialchars($entry['nome_hosp'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($entry['seguradora_seg'] ?? '-') ?></td>
                            <td class="text-center fw-semibold"><?= (int)$entry['dias_atual'] ?>d</td>
                            <td class="text-center">
                                <div><?= (int)$entry['prev_total'] ?>d</div>
                                <small class="text-muted">Intervalo: <?= (int)$entry['prev_min'] ?> - <?= (int)$entry['prev_max'] ?>d</small>
                            </td>
                            <td class="text-center">
                                <?php if ($entry['prev_remaining'] > 0): ?>
                                <?= (int)$entry['prev_remaining'] ?>d
                                <?php else: ?>
                                <span class="text-success fw-semibold">Elegível p/ alta</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $statusClass = [
                                    'atrasado' => 'badge bg-danger',
                                    'atencao'  => 'badge bg-warning text-dark',
                                    'no_prazo' => 'badge bg-success'
                                ];
                                $label = [
                                    'atrasado' => 'Acima do previsto',
                                    'atencao'  => 'Fase crítica',
                                    'no_prazo' => 'Dentro do previsto'
                                ];
                                $state = $entry['status'] ?? 'no_prazo';
                                ?>
                                <span class="<?= $statusClass[$state] ?? 'badge bg-secondary' ?>">
                                    <?= $label[$state] ?? 'Monitorar' ?>
                                </span>
                                <small class="d-block text-muted"><?= htmlspecialchars($entry['status_message']) ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <small class="text-muted"><?= htmlspecialchars($losForecasts['message']) ?></small>
            <?php else: ?>
            <p class="text-muted mb-0">
                <?= htmlspecialchars($losForecasts['message'] ?? 'Sem dados suficientes para estimar permanência.') ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

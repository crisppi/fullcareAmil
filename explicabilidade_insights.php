<?php
include_once("check_logado.php");
include_once("globals.php");
require_once("app/services/OperationalIntelligenceService.php");

$service = new OperationalIntelligenceService($conn);
$drivers = $service->explainabilityDrivers();

include_once("templates/header.php");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Insights explicáveis</title>
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
        .container-fluid small,
        .factor-chip {
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
        .factor-chip {
            display: inline-flex;
            align-items: center;
            background: #f5f3ff;
            color: #4b1c50;
            font-size: 0.76rem;
            border-radius: 999px;
            padding: 0.18rem 0.62rem;
            margin: 0.15rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid" style="margin-top:24px; padding:0 0 16px;">
        <div class="row mb-2">
            <div class="col-12">
                <h2 class="mb-0 fw-semibold" style="color:#5e2363;">Insights explicáveis</h2>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-12">
                <div class="alert alert-info">
                    <strong>Explicabilidade dos modelos</strong> — Como auditores da operadora, precisamos justificar o risco projetado. 
                    A tabela abaixo mostra fatores-chave (heurística interna) que elevaram o alerta para cada paciente, 
                    incluindo permanência longa, ausência de visita, prorrogações e eventos adversos.
                </div>
            </div>
        </div>

        <div class="insight-card">
            <?php if (!empty($drivers['available'])): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Hospital</th>
                            <th>Operadora</th>
                            <th class="text-center">Dias internado</th>
                            <th class="text-center">Status</th>
                            <th>Fatores que elevaram o risco</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drivers['entries'] as $entry): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($entry['nome_pac'] ?? 'Paciente') ?></div>
                                <small class="text-muted">Int. #<?= (int)$entry['id_internacao'] ?> · <?= date('d/m/Y', strtotime($entry['data_intern_int'])) ?></small>
                            </td>
                            <td><?= htmlspecialchars($entry['nome_hosp'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($entry['seguradora_seg'] ?? '-') ?></td>
                            <td class="text-center fw-semibold"><?= (int)$entry['dias_atual'] ?>d</td>
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
                            </td>
                            <td>
                                <?php foreach (($entry['factors'] ?? []) as $factor): ?>
                                <span class="factor-chip"><?= htmlspecialchars($factor) ?></span>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <small class="text-muted"><?= htmlspecialchars($drivers['message']) ?></small>
            <?php else: ?>
            <p class="text-muted mb-0">
                <?= htmlspecialchars($drivers['message'] ?? 'Sem dados suficientes para gerar explicabilidade.') ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

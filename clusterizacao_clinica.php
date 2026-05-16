<?php
include_once("check_logado.php");
include_once("globals.php");
require_once("app/services/OperationalIntelligenceService.php");

$service = new OperationalIntelligenceService($conn);
$clusters = $service->clinicalClusters();

include_once("templates/header.php");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Clusterização clínica</title>
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
        .cluster-pill {
            display:inline-flex;
            align-items:center;
            padding:0.18rem 0.7rem;
            border-radius:999px;
            font-weight:600;
            gap:6px;
            font-size:.76rem;
        }
        .cluster-alta {background:#fee2e2;color:#991b1b;}
        .cluster-cronico {background:#fff1c3;color:#9a3412;}
        .cluster-risco {background:#e0f2fe;color:#075985;}
        .cluster-padrao {background:#e2e8f0;color:#1f2937;}
    </style>
</head>
<body>
    <div class="container-fluid" style="margin-top:24px; padding:0 0 16px;">
        <div class="row mb-2">
            <div class="col-12">
                <h2 class="mb-0 fw-semibold" style="color:#5e2363;">Clusterização clínica</h2>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-12">
                <div class="alert alert-info">
                    <strong>Clusterização clínica</strong> — Agrupamento heurístico de pacientes com base em permanência, recorrência e eventos adversos. Cada cluster ajuda a direcionar protocolos da auditoria da operadora.
                </div>
            </div>
        </div>

        <div class="insight-card">
            <?php if (!empty($clusters['available'])): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Operadora</th>
                            <th class="text-center">Internações (12m)</th>
                            <th class="text-center">Média dias</th>
                            <th class="text-center">Longas / Abertas</th>
                            <th>Cluster</th>
                            <th>Plano sugerido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clusters['entries'] as $entry): ?>
                        <?php
                            $classMap = [
                                'Alta complexidade' => 'cluster-alta',
                                'Crônico recorrente' => 'cluster-cronico',
                                'Risco clínico' => 'cluster-risco',
                                'Perfil padrão' => 'cluster-padrao'
                            ];
                            $pillClass = $classMap[$entry['cluster_label']] ?? 'cluster-padrao';
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($entry['nome_pac'] ?? 'Paciente') ?></div>
                                <small class="text-muted">ID #<?= (int)$entry['paciente_id'] ?></small>
                            </td>
                            <td><?= htmlspecialchars($entry['operadora'] ?? '-') ?></td>
                            <td class="text-center fw-semibold"><?= (int)$entry['total_intern'] ?></td>
                            <td class="text-center"><?= number_format($entry['media_dias'], 1, ',', '.') ?></td>
                            <td class="text-center">
                                <?= (int)$entry['longas'] ?> longas<br>
                                <?= (int)$entry['abertas'] ?> abertas
                            </td>
                            <td>
                                <span class="cluster-pill <?= $pillClass ?>">
                                    <?= htmlspecialchars($entry['cluster_label']) ?>
                                </span>
                                <div class="small text-muted"><?= htmlspecialchars($entry['cluster_desc']) ?></div>
                            </td>
                            <td>
                                <div class="small"><?= htmlspecialchars($entry['acao']) ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <small class="text-muted"><?= htmlspecialchars($clusters['message']) ?></small>
            <?php else: ?>
            <p class="text-muted mb-0">
                <?= htmlspecialchars($clusters['message'] ?? 'Sem dados suficientes para clusterização.') ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

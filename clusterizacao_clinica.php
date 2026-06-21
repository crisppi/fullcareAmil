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
            box-shadow: 0 10px 25px rgba(76, 142, 187,0.08);
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
    <link href="<?= $BASE_URL ?>css/operational_reports.css?v=<?= @filemtime(__DIR__ . '/css/operational_reports.css') ?>" rel="stylesheet">
    <style>
        .cluster-page {
            padding: 10px 14px 24px !important;
        }

        .cluster-page .fc-module-header {
            margin-bottom: 10px !important;
            padding: 9px 14px !important;
            border-radius: 10px !important;
        }

        .cluster-page .cluster-note {
            margin-bottom: 10px !important;
            border: 1px solid rgba(94, 180, 216, .32) !important;
            background: linear-gradient(135deg, #eef9fc 0%, #f8fbff 100%) !important;
            color: #53657b;
            box-shadow: inset 4px 0 0 #58abc6;
        }

        .cluster-page .insight-card {
            margin-top: 0 !important;
            padding: 12px !important;
            border-radius: 12px !important;
            border-color: rgba(76, 142, 187, .16) !important;
            box-shadow: 0 8px 18px rgba(44, 84, 114, .06) !important;
        }

        .cluster-page .cluster-table-wrap {
            border: 1px solid rgba(76, 142, 187, .13);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        .cluster-page .cluster-table {
            table-layout: fixed;
            width: 100%;
        }

        .cluster-page .cluster-table th,
        .cluster-page .cluster-table td {
            white-space: normal !important;
            vertical-align: middle !important;
        }

        .cluster-page .cluster-table .patient-col { width: 19%; }
        .cluster-page .cluster-table .operator-col { width: 9%; }
        .cluster-page .cluster-table .count-col { width: 8%; }
        .cluster-page .cluster-table .days-col { width: 8%; }
        .cluster-page .cluster-table .long-col { width: 10%; }
        .cluster-page .cluster-table .cluster-col { width: 24%; }
        .cluster-page .cluster-table .plan-col { width: 22%; }

        .cluster-page .cluster-patient-name {
            color: #24384f;
            font-weight: 700;
        }

        .cluster-page .cluster-pill {
            margin-bottom: 3px;
            font-size: .58rem !important;
            font-weight: 800 !important;
        }

        .cluster-page .cluster-description,
        .cluster-page .cluster-action {
            color: #5d6b7c;
            font-size: .68rem !important;
            line-height: 1.22 !important;
        }

        .cluster-page .cluster-action {
            color: #3f4854;
        }

        .cluster-page .table tbody tr:nth-child(even) > * {
            --bs-table-bg: #f3f9fd !important;
            background: #f3f9fd !important;
        }

        @media (max-width: 1199px) {
            .cluster-page .cluster-table {
                min-width: 980px;
            }

            .cluster-page .cluster-table-wrap {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid operational-report-page cluster-page" style="margin-top:24px; padding:0 0 16px;">
        <div class="fc-module-header fc-module-header--inteligencia">
            <div class="fc-module-header__copy">
                <p class="fc-module-header__kicker">Inteligência Operacional</p>
                <h1 class="fc-module-header__title">Clusterização clínica</h1>
                <p class="fc-module-header__subtitle">Agrupamento de pacientes por permanência, recorrência e eventos adversos.</p>
            </div>
        </div>
        <div class="row mb-2 g-0">
            <div class="col-12">
                <div class="alert alert-info cluster-note">
                    <strong>Clusterização clínica</strong> — Agrupamento heurístico de pacientes com base em permanência, recorrência e eventos adversos. Cada cluster ajuda a direcionar protocolos da auditoria da operadora.
                </div>
            </div>
        </div>

        <div class="insight-card">
            <?php if (!empty($clusters['available'])): ?>
            <div class="table-responsive cluster-table-wrap">
                <table class="table table-hover align-middle cluster-table">
                    <colgroup>
                        <col class="patient-col">
                        <col class="operator-col">
                        <col class="count-col">
                        <col class="days-col">
                        <col class="long-col">
                        <col class="cluster-col">
                        <col class="plan-col">
                    </colgroup>
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
                                <div class="cluster-patient-name"><?= htmlspecialchars($entry['nome_pac'] ?? 'Paciente') ?></div>
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
                                <div class="cluster-description"><?= htmlspecialchars($entry['cluster_desc']) ?></div>
                            </td>
                            <td>
                                <div class="cluster-action"><?= htmlspecialchars($entry['acao']) ?></div>
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

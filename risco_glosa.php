<?php
include_once("check_logado.php");
include_once("globals.php");
require_once("app/services/OperationalIntelligenceService.php");

$page = isset($_GET['pag']) ? max(1, (int) $_GET['pag']) : 1;
$perPage = isset($_GET['reg_pag']) ? max(5, min(100, (int) $_GET['reg_pag'])) : 15;

$service = new OperationalIntelligenceService($conn);
$glosaData = $service->glosaRiskAlerts($perPage, $page);

include_once("templates/header.php");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Oportunidade de glosa / conta parada</title>
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
        .glosa-toolbar .meta,
        .glosa-legend .item {
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
        .risk-pill {
            display:inline-flex;
            align-items:center;
            gap:6px;
            border-radius:999px;
            padding:0.2rem 0.75rem;
            font-weight:600;
        }
        .risk-pill.alto {
            background: linear-gradient(135deg, #ffe0e0 0%, #ffc9c9 100%);
            color:#8f1111;
            box-shadow: 0 0 0 1px rgba(177, 22, 22, 0.14), 0 8px 18px rgba(177, 22, 22, 0.14);
        }
        .risk-pill.moderado {background:#fff1c3;color:#a15c00;}
        .risk-pill.baixo {background:#dcfce7;color:#166534;}
        .factor-chip {
            display:inline-flex;
            align-items:center;
            background:#f4f4ff;
            color:#4338ca;
            font-size:0.76rem;
            border-radius:999px;
            padding:0.18rem 0.58rem;
            margin:0.15rem;
        }
        .glosa-toolbar {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:1rem;
            margin-bottom:.75rem;
            flex-wrap:wrap;
        }
        .glosa-toolbar .meta {
            color:#6b7280;
            font-size:0.78rem;
        }
        .glosa-legend {
            display:flex;
            flex-wrap:wrap;
            gap:0.5rem;
            margin:0.15rem 0 0.75rem;
        }
        .glosa-legend .item {
            display:inline-flex;
            align-items:center;
            gap:0.45rem;
            padding:0.28rem 0.65rem;
            border-radius:999px;
            font-size:0.76rem;
            border:1px solid rgba(94,35,99,0.12);
            background:#faf7fc;
            color:#5e2363;
        }
    </style>
</head>
<body>
    <div class="container-fluid" style="margin-top:24px; padding:0 0 16px;">
        <div class="row mb-2">
            <div class="col-12">
                <h2 class="mb-0 fw-semibold" style="color:#5e2363;">Painel de oportunidade de glosa</h2>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-12">
                <div class="alert alert-info">
                    <strong>Oportunidade de glosa ou conta parada</strong> — visão dedicada da auditoria da operadora. 
                    Identifica contas com maior potencial de glosa (glosa alta, processos parados, auditoria sem retorno) e sugere ações antes do faturamento.
                </div>
            </div>
        </div>

        <div class="insight-card">
            <?php if (!empty($glosaData['available'])): ?>
            <?php
                $pagination = $glosaData['pagination'] ?? ['page' => 1, 'per_page' => $perPage, 'total' => count($glosaData['entries'] ?? []), 'pages' => 1];
                $startItem = (($pagination['page'] - 1) * $pagination['per_page']) + 1;
                $endItem = min($pagination['total'], $startItem + count($glosaData['entries']) - 1);
                $paramsBase = $_GET;
            ?>
            <div class="glosa-legend">
                <span class="item"><strong>Baixo</strong> oportunidade abaixo de 15%</span>
                <span class="item"><strong>Moderado</strong> oportunidade entre 15% e 39,9%</span>
                <span class="item"><strong>Alto</strong> oportunidade a partir de 40%</span>
            </div>
            <div class="glosa-toolbar">
                <div class="meta">
                    Exibindo <?= (int)$startItem ?> a <?= (int)$endItem ?> de <?= (int)$pagination['total'] ?> contas analisadas.
                </div>
                <form method="get" class="d-flex align-items-center gap-2">
                    <label for="reg_pag" class="small text-muted mb-0">Reg por pág</label>
                    <select name="reg_pag" id="reg_pag" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
                        <?php foreach ([15, 30, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= (int)$perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="pag" value="1">
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Conta / Paciente</th>
                            <th>Hospital</th>
                            <th>Operadora</th>
                            <th class="text-center">Dias em aberto</th>
                            <th class="text-center">Oportunidade de glosa</th>
                            <th>Fatores chave</th>
                            <th>Ação recomendada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($glosaData['entries'] as $entry): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold">Capeante #<?= (int)$entry['id_capeante'] ?> · <?= htmlspecialchars($entry['nome_pac'] ?? 'Paciente') ?></div>
                                <small class="text-muted">
                                    Internação <?= (int)$entry['internacao_id'] ?> · Início <?= $entry['data_inicial'] ? date('d/m/Y', strtotime($entry['data_inicial'])) : '—' ?>
                                </small>
                            </td>
                            <td><?= htmlspecialchars($entry['nome_hosp'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($entry['operadora'] ?? '-') ?></td>
                            <td class="text-center fw-semibold"><?= (int)$entry['dias_aberto'] ?>d</td>
                            <td class="text-center">
                                <div class="risk-pill <?= $entry['risk_level'] ?>">
                                    <?= ucfirst($entry['risk_level']) ?> · oportunidade <?= number_format($entry['probability'] * 100, 1) ?>%
                                </div>
                                <small class="d-block text-muted">
                                    Glosa projetada: <?= number_format($entry['glosa_ratio'] * 100, 1) ?>% | Valor: R$ <?= number_format($entry['valor_glosa'], 2, ',', '.') ?>
                                </small>
                            </td>
                            <td>
                                <?php foreach ($entry['factors'] as $factor): ?>
                                <span class="factor-chip"><?= htmlspecialchars($factor) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <div class="small"><?= htmlspecialchars($entry['recommendation']) ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (($pagination['pages'] ?? 1) > 1): ?>
            <nav class="d-flex justify-content-center mt-3" aria-label="Paginação glosa">
                <ul class="pagination pagination-sm mb-0">
                    <?php
                        $currentPage = (int) $pagination['page'];
                        $totalPages = (int) $pagination['pages'];
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $startPage + 4);
                        if (($endPage - $startPage) < 4) {
                            $startPage = max(1, $endPage - 4);
                        }
                    ?>
                    <?php
                        $prevParams = $paramsBase;
                        $prevParams['pag'] = max(1, $currentPage - 1);
                    ?>
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query($prevParams), ENT_QUOTES, 'UTF-8') ?>">‹</a>
                    </li>
                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <?php $pageParams = $paramsBase; $pageParams['pag'] = $p; ?>
                        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= htmlspecialchars(http_build_query($pageParams), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php
                        $nextParams = $paramsBase;
                        $nextParams['pag'] = min($totalPages, $currentPage + 1);
                    ?>
                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query($nextParams), ENT_QUOTES, 'UTF-8') ?>">›</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            <small class="text-muted"><?= htmlspecialchars($glosaData['message']) ?></small>
            <?php else: ?>
            <p class="text-muted mb-0"><?= htmlspecialchars($glosaData['message'] ?? 'Sem dados para análise.') ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

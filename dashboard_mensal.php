<?php
include_once("check_logado.php");
require_once("templates/header.php");
require_once("dao/internacaoDao.php");
require_once("dao/capeanteDao.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexão inválida.");
}

$selectedHospital = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$selectedSeguradora = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;

$monthsWindowOptions = [6, 12, 24];
$monthsWindow = (int)(filter_input(INPUT_GET, 'janela', FILTER_VALIDATE_INT) ?: 12);
if (!in_array($monthsWindow, $monthsWindowOptions, true)) $monthsWindow = 12;
$startDate = date('Y-m-01', strtotime('-' . ($monthsWindow - 1) . ' months'));

$hospitaisList = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")->fetchAll(PDO::FETCH_ASSOC);
$seguradorasList = $conn->query("SELECT id_seguradora, seguradora_seg AS nome FROM tb_seguradora ORDER BY seguradora_seg")->fetchAll(PDO::FETCH_ASSOC);

$filterCondition = '';
if ($selectedHospital) {
    $filterCondition .= " AND ac.fk_hospital_int = :hospital_id";
}
if ($selectedSeguradora) {
    $filterCondition .= " AND pa.fk_seguradora_pac = :seguradora_id";
}

function buildMonthsArray($monthsWindow)
{
    $months = [];
    for ($i = $monthsWindow - 1; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("-{$i} months"));
        $months[$key] = [
            'label' => date('m/Y', strtotime($key . '-01')),
            'value' => 0
        ];
    }
    return $months;
}

$internacoesPorMes = buildMonthsArray($monthsWindow);
$sqlInt = "SELECT DATE_FORMAT(ac.data_intern_int, '%Y-%m') as ym, COUNT(*) as total
           FROM tb_internacao ac
           LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
           WHERE ac.data_intern_int >= :inicio {$filterCondition}
           GROUP BY ym";
$stmt = $conn->prepare($sqlInt);
$stmt->bindValue(':inicio', $startDate);
if ($selectedHospital) {
    $stmt->bindValue(':hospital_id', $selectedHospital, PDO::PARAM_INT);
}
if ($selectedSeguradora) {
    $stmt->bindValue(':seguradora_id', $selectedSeguradora, PDO::PARAM_INT);
}
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $ym = $row['ym'];
    if (isset($internacoesPorMes[$ym])) {
        $internacoesPorMes[$ym]['value'] = (int)$row['total'];
    }
}

$custoApresentado = buildMonthsArray($monthsWindow);
$custoFinal = buildMonthsArray($monthsWindow);
$diariasPorMes = buildMonthsArray($monthsWindow);

$sqlCap = "SELECT DATE_FORMAT(ca.data_final_capeante, '%Y-%m') as ym, 
                  SUM(valor_apresentado_capeante) as total_apr,
                  SUM(valor_final_capeante) as total_final
           FROM tb_capeante ca
           LEFT JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
           LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
           WHERE ca.data_final_capeante >= :inicio {$filterCondition}
           GROUP BY ym";
$stmtCap = $conn->prepare($sqlCap);
$stmtCap->bindValue(':inicio', $startDate);
if ($selectedHospital) $stmtCap->bindValue(':hospital_id', $selectedHospital, PDO::PARAM_INT);
if ($selectedSeguradora) $stmtCap->bindValue(':seguradora_id', $selectedSeguradora, PDO::PARAM_INT);
$stmtCap->execute();
foreach ($stmtCap->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $ym = $row['ym'];
    if (!isset($custoApresentado[$ym])) continue;
    $custoApresentado[$ym]['value'] = (float)$row['total_apr'];
    $custoFinal[$ym]['value'] = (float)$row['total_final'];
}

$sqlDiasMes = "SELECT DATE_FORMAT(ac.data_intern_int, '%Y-%m') as ym,
                      SUM(
                        GREATEST(1,
                            DATEDIFF(
                                CASE 
                                    WHEN alt.data_alta_alt IS NOT NULL THEN alt.data_alta_alt
                                    ELSE CURDATE()
                                END,
                                ac.data_intern_int
                            ) + 1
                        )
                      ) AS total_dias
               FROM tb_internacao ac
               LEFT JOIN (
                    SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
                    FROM tb_alta
                    GROUP BY fk_id_int_alt
               ) alt ON alt.fk_id_int_alt = ac.id_internacao
               LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
               WHERE ac.data_intern_int >= :inicio {$filterCondition}
               GROUP BY ym";
$stmtDiasMes = $conn->prepare($sqlDiasMes);
$stmtDiasMes->bindValue(':inicio', $startDate);
if ($selectedHospital) $stmtDiasMes->bindValue(':hospital_id', $selectedHospital, PDO::PARAM_INT);
if ($selectedSeguradora) $stmtDiasMes->bindValue(':seguradora_id', $selectedSeguradora, PDO::PARAM_INT);
$stmtDiasMes->execute();
foreach ($stmtDiasMes->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $ym = $row['ym'];
    if (isset($diariasPorMes[$ym])) {
        $diariasPorMes[$ym]['value'] = (int)$row['total_dias'];
    }
}

$labels = array_column($internacoesPorMes, 'label');
$valuesInt = array_column($internacoesPorMes, 'value');
$valuesApr = array_column($custoApresentado, 'value');
$valuesFin = array_column($custoFinal, 'value');
$valuesDias = array_column($diariasPorMes, 'value');
$valuesMP = [];
foreach ($internacoesPorMes as $key => $int) {
    $inc = $int['value'] ?: 0;
    $dias = $diariasPorMes[$key]['value'] ?? 0;
    $valuesMP[] = $inc > 0 ? round($dias / $inc, 2) : 0;
}

$stats = [
    'internacoes_total' => array_sum($valuesInt),
    'diarias_total' => 0,
    'mp_global' => 0,
    'valor_apresentado_total' => array_sum($valuesApr),
    'glosa_total' => max(0, array_sum($valuesApr) - array_sum($valuesFin)),
];

$sqlDias = "SELECT SUM(
                GREATEST(1,
                    DATEDIFF(
                        CASE 
                            WHEN alt.data_alta_alt IS NOT NULL THEN alt.data_alta_alt
                            ELSE CURDATE()
                        END,
                        ac.data_intern_int
                    ) + 1
                )
            ) AS total_dias
            FROM tb_internacao ac
            LEFT JOIN (
                SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
                FROM tb_alta
                GROUP BY fk_id_int_alt
            ) alt ON alt.fk_id_int_alt = ac.id_internacao
            LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
            WHERE ac.data_intern_int >= :inicio {$filterCondition}";
$stmtDias = $conn->prepare($sqlDias);
$stmtDias->bindValue(':inicio', $startDate);
if ($selectedHospital) $stmtDias->bindValue(':hospital_id', $selectedHospital, PDO::PARAM_INT);
if ($selectedSeguradora) $stmtDias->bindValue(':seguradora_id', $selectedSeguradora, PDO::PARAM_INT);
$stmtDias->execute();
$stats['diarias_total'] = (int)($stmtDias->fetchColumn() ?: 0);

if ($stats['internacoes_total'] > 0) {
    $stats['mp_global'] = round(($stats['diarias_total'] / $stats['internacoes_total']), 2);
}

$sqlHosp = "SELECT ho.nome_hosp AS hospital, COUNT(*) as total
            FROM tb_internacao ac
            LEFT JOIN tb_hospital ho ON ho.id_hospital = ac.fk_hospital_int
            LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
            WHERE ac.data_intern_int >= :inicio" .
            ($selectedHospital ? " AND ac.fk_hospital_int = :hospital_id" : "") .
            ($selectedSeguradora ? " AND pa.fk_seguradora_pac = :seguradora_id" : "") .
            " GROUP BY ho.nome_hosp
            ORDER BY total DESC
            LIMIT 5";
$stmtHosp = $conn->prepare($sqlHosp);
$stmtHosp->bindValue(':inicio', $startDate);
if ($selectedHospital) {
    $stmtHosp->bindValue(':hospital_id', $selectedHospital, PDO::PARAM_INT);
}
if ($selectedSeguradora) {
    $stmtHosp->bindValue(':seguradora_id', $selectedSeguradora, PDO::PARAM_INT);
}
$stmtHosp->execute();
$internacoesPorHospital = $stmtHosp->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
.dashboard-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.10);
}
.dashboard-card .card-header {
    border-bottom: none;
    font-weight: 600;
    color: #3A3A3A;
    font-size: .78rem;
    padding: 9px 12px 0;
}
.filter-chip {
    border-radius: 999px;
    border: 1px solid #ddd;
    padding: 6px 16px;
    margin-right: 10px;
}
.filter-chip.active {
    background-color: #5e2363;
    color: #fff;
    border-color: transparent;
}
.chart-container {
    min-height: 200px;
}
</style>
<style>
    .container-fluid.py-4 {
        padding-top: 10px !important;
        padding-bottom: 18px !important;
    }

    .container-fluid .mb-4 {
        margin-bottom: .8rem !important;
    }

    .container-fluid h4 {
        font-size: 1.04rem;
    }

    .container-fluid .text-muted,
    .container-fluid .small,
    .container-fluid .form-select,
    .container-fluid .card-body,
    .container-fluid .card small {
        font-size: .78rem;
    }

    .dashboard-card.p-3 {
        padding: .72rem !important;
    }

    .dashboard-card h3 {
        font-size: 1.14rem;
    }
</style>
<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h4 class="mb-0" style="color:#3A3A3A;">Painel mensal</h4>
            <span class="text-muted">Resumo dos últimos <?= $monthsWindow ?> meses</span>
        </div>
        <form method="get" class="row g-2 align-items-center mt-3 mt-lg-0">
            <div class="col-auto">
                <label class="mb-0 me-2 text-muted small">Janela</label>
                <select name="janela" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($monthsWindowOptions as $opt): ?>
                    <option value="<?= $opt ?>" <?= $opt === $monthsWindow ? 'selected' : '' ?>><?= $opt ?> meses</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="mb-0 me-2 text-muted small">Hospital</label>
                <select name="hospital_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($hospitaisList as $h): ?>
                    <option value="<?= (int)$h['id_hospital'] ?>" <?= $selectedHospital == $h['id_hospital'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($h['nome_hosp']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="mb-0 me-2 text-muted small">Seguradora</label>
                <select name="seguradora_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($seguradorasList as $s): ?>
                    <option value="<?= (int)$s['id_seguradora'] ?>" <?= $selectedSeguradora == $s['id_seguradora'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card dashboard-card p-3">
                <small class="text-muted text-uppercase">Total de internações</small>
                <h3 class="mt-2 mb-0"><?= number_format($stats['internacoes_total'], 0, ',', '.') ?></h3>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card dashboard-card p-3">
                <small class="text-muted text-uppercase">Número de diárias</small>
                <h3 class="mt-2 mb-0"><?= number_format($stats['diarias_total'], 0, ',', '.') ?></h3>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card dashboard-card p-3">
                <small class="text-muted text-uppercase">MP (Diárias/Internações)</small>
                <h3 class="mt-2 mb-0"><?= number_format($stats['mp_global'], 2, ',', '.') ?></h3>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card dashboard-card p-3">
                <small class="text-muted text-uppercase">Valor apresentado</small>
                <h3 class="mt-2 mb-0">R$ <?= number_format($stats['valor_apresentado_total'], 2, ',', '.') ?></h3>
                <small class="text-muted">Glosa total: R$ <?= number_format($stats['glosa_total'], 2, ',', '.') ?></small>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card dashboard-card">
                <div class="card-header bg-white">Internações mensais</div>
                <div class="card-body chart-container">
                    <canvas id="chartInternacoes"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dashboard-card">
                <div class="card-header bg-white">Média de permanência (dias)</div>
                <div class="card-body chart-container">
                    <canvas id="chartMP"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card dashboard-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span>Custo apresentado x pós-auditoria</span>
                    <small class="text-muted">Valores em R$</small>
                </div>
                <div class="card-body chart-container">
                    <canvas id="chartCustos"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card dashboard-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span>Internações por hospital</span>
                    <small class="text-muted">Top 5</small>
                </div>
                <div class="card-body chart-container">
                    <canvas id="chartHospitais"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode($labels) ?>;
const intData = <?= json_encode($valuesInt) ?>;
const aprData = <?= json_encode($valuesApr) ?>;
const finData = <?= json_encode($valuesFin) ?>;
const mpData = <?= json_encode($valuesMP) ?>;
const hospLabels = <?= json_encode(array_column($internacoesPorHospital, 'hospital')) ?>;
const hospData = <?= json_encode(array_map('intval', array_column($internacoesPorHospital, 'total'))) ?>;

const fmtCurrency = value => (value || value === 0)
    ? 'R$ ' + Number(value).toLocaleString('pt-BR', { minimumFractionDigits: 2 })
    : 'R$ 0,00';

const ctxInt = document.getElementById('chartInternacoes').getContext('2d');
new Chart(ctxInt, {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Internações',
            data: intData,
            backgroundColor: '#5e2363',
            borderRadius: 8,
            maxBarThickness: 50
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => `${ctx.parsed.y} internações`
                }
            }
        },
        scales: {
            x: { grid: { display: false }},
            y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { precision:0 } }
        }
    }
});

const ctxMP = document.getElementById('chartMP').getContext('2d');
new Chart(ctxMP, {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: 'Dias por internação',
            data: mpData,
            borderColor: '#2b8a3e',
            backgroundColor: 'rgba(43,138,62,0.2)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => value + ' dias'
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: ctx => `${ctx.parsed.y} dias em média`
                }
            }
        }
    }
});

const ctxCustos = document.getElementById('chartCustos').getContext('2d');
new Chart(ctxCustos, {
    type: 'line',
    data: {
        labels,
        datasets: [
            {
                label: 'Valor apresentado',
                data: aprData,
                borderColor: '#f59f00',
                backgroundColor: 'rgba(245,159,0,0.15)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'Valor final',
                data: finData,
                borderColor: '#1c7ed6',
                backgroundColor: 'rgba(28,126,214,0.15)',
                tension: 0.3,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f0f0f0' },
                ticks: { callback: value => fmtCurrency(value) }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: context => `${context.dataset.label}: ${fmtCurrency(context.parsed.y)}`
                }
            }
        }
    }
});

const ctxHosp = document.getElementById('chartHospitais').getContext('2d');
new Chart(ctxHosp, {
    type: 'bar',
    data: {
        labels: hospLabels,
        datasets: [{
            label: 'Internações',
            data: hospData,
            backgroundColor: '#5bd9f3',
            borderRadius: 8,
            maxBarThickness: 40
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        scales: {
            x: { beginAtZero: true, grid: { color: '#f0f0f0' } },
            y: { grid: { display: false } }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: ctx => `${ctx.parsed.x} internações`
                }
            },
            legend: { display: false }
        }
    }
});
</script>

<?php require_once("templates/footer.php"); ?>

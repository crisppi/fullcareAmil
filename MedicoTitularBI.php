<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fmt_num($value, int $decimals = 0): string
{
    return number_format((float)$value, $decimals, ',', '.');
}

function fmt_money($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$tipoInternação = trim((string)(filter_input(INPUT_GET, 'tipo_internacao') ?? ''));
$modoInternação = trim((string)(filter_input(INPUT_GET, 'modo_internacao') ?? ''));
$internado = trim((string)(filter_input(INPUT_GET, 'internado') ?? ''));
$uti = trim((string)(filter_input(INPUT_GET, 'uti') ?? ''));
$dataInicio = trim((string)(filter_input(INPUT_GET, 'data_inicio') ?? ''));
$dataFim = trim((string)(filter_input(INPUT_GET, 'data_fim') ?? ''));

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$tiposInt = $conn->query("SELECT DISTINCT tipo_admissao_int FROM tb_internacao WHERE tipo_admissao_int IS NOT NULL AND tipo_admissao_int <> '' ORDER BY tipo_admissao_int")
    ->fetchAll(PDO::FETCH_COLUMN);
$modos = $conn->query("SELECT DISTINCT modo_internacao_int FROM tb_internacao WHERE modo_internacao_int IS NOT NULL AND modo_internacao_int <> '' ORDER BY modo_internacao_int")
    ->fetchAll(PDO::FETCH_COLUMN);

function build_where_medico(array $filters, array &$params): string
{
    $where = "1=1";
    $params = [];
    if (!empty($filters['hospital_id'])) {
        $where .= " AND i.fk_hospital_int = :hospital_id";
        $params[':hospital_id'] = (int)$filters['hospital_id'];
    }
    if (!empty($filters['tipo_internacao'])) {
        $where .= " AND i.tipo_admissao_int = :tipo_internacao";
        $params[':tipo_internacao'] = $filters['tipo_internacao'];
    }
    if (!empty($filters['modo_internacao'])) {
        $where .= " AND i.modo_internacao_int = :modo_internacao";
        $params[':modo_internacao'] = $filters['modo_internacao'];
    }
    if (!empty($filters['internado'])) {
        $where .= " AND i.internado_int = :internado";
        $params[':internado'] = $filters['internado'];
    }
    if (!empty($filters['data_inicio'])) {
        $where .= " AND i.data_intern_int >= :data_inicio";
        $params[':data_inicio'] = $filters['data_inicio'];
    }
    if (!empty($filters['data_fim'])) {
        $where .= " AND i.data_intern_int <= :data_fim";
        $params[':data_fim'] = $filters['data_fim'];
    }
    if (!empty($filters['uti'])) {
        if ($filters['uti'] === 's') {
            $where .= " AND ut.fk_internacao_uti IS NOT NULL";
        } elseif ($filters['uti'] === 'n') {
            $where .= " AND ut.fk_internacao_uti IS NULL";
        }
    }
    return $where;
}

$filtersSelected = [
    'hospital_id' => $hospitalId,
    'tipo_internacao' => $tipoInternação,
    'modo_internacao' => $modoInternação,
    'internado' => $internado,
    'uti' => $uti,
    'data_inicio' => $dataInicio,
    'data_fim' => $dataFim,
];

$params = [];
$where = build_where_medico($filtersSelected, $params);

$sqlMedicos = "
    SELECT
        i.titular_int AS medico,
        SUM(COALESCE(ca.valor_apresentado_capeante,0)) AS valor_apresentado,
        AVG(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS mp
    FROM tb_internacao i
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao
    WHERE {$where} AND i.titular_int IS NOT NULL AND i.titular_int <> ''
    GROUP BY i.titular_int
    ORDER BY valor_apresentado DESC
    LIMIT 10
";
$stmtMed = $conn->prepare($sqlMedicos);
foreach ($params as $key => $value) {
    $stmtMed->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtMed->execute();
$rowsMedicos = $stmtMed->fetchAll(PDO::FETCH_ASSOC);



// Filtra médicos válidos (sem branco ou nulo) e reindexa
$medicosFiltrados = [];
foreach ($rowsMedicos as $r) {
    $nome = isset($r['medico']) ? trim($r['medico']) : '';
    if ($nome !== '') {
        $medicosFiltrados[] = $r;
    }
}
// Ordena por valor apresentado desc (grafico valor) e por MP desc (grafico MP)
$medicosValor = $medicosFiltrados;
usort($medicosValor, function ($a, $b) {
    return ($b['valor_apresentado'] ?? 0) <=> ($a['valor_apresentado'] ?? 0);
});
$medicosMp = $medicosFiltrados;
usort($medicosMp, function ($a, $b) {
    return ($b['mp'] ?? 0) <=> ($a['mp'] ?? 0);
});

// Limita a 10 médicos em cada gráfico
$medicosValor = array_slice($medicosValor, 0, 10);
$medicosMp = array_slice($medicosMp, 0, 10);

$medicoLabelsValor = array_map(fn($r) => trim($r['medico']), $medicosValor);
$medicoValores = array_map(fn($r) => (float)($r['valor_apresentado'] ?? 0), $medicosValor);
$medicoLabelsMp = array_map(fn($r) => trim($r['medico']), $medicosMp);
$medicoMp = array_map(fn($r) => (float)($r['mp'] ?? 0), $medicosMp);

$sqlTable = "
    SELECT
        i.crm_int,
        i.titular_int AS medico,
        COALESCE(NULLIF(pa.nome_pac,''), 'Sem informações') AS paciente,
        GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1) AS mp,
        COALESCE(ca.valor_apresentado_capeante,0) AS valor_apresentado,
        i.tipo_admissao_int,
        i.modo_internacao_int
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao
    WHERE {$where}
    ORDER BY valor_apresentado DESC
    LIMIT 50
";
$stmtTable = $conn->prepare($sqlTable);
foreach ($params as $key => $value) {
    $stmtTable->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtTable->execute();
$tableRows = $stmtTable->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Médico Titular</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters bi-filters-wrap bi-filters-compact" method="get">
        <div class="bi-filter">
            <label>Internado</label>
            <select name="internado">
                <option value="">Todos</option>
                <option value="s" <?= $internado === 's' ? 'selected' : '' ?>>Sim</option>
                <option value="n" <?= $internado === 'n' ? 'selected' : '' ?>>Não</option>
            </select>
        </div>
        <div class="bi-filter">
            <label>Hospitais</label>
            <select name="hospital_id">
                <option value="">Todos</option>
                <?php foreach ($hospitais as $h): ?>
                    <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                        <?= e($h['nome_hosp']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Tipo internação</label>
            <select name="tipo_internacao">
                <option value="">Todos</option>
                <?php foreach ($tiposInt as $tipo): ?>
                    <option value="<?= e($tipo) ?>" <?= $tipoInternação === $tipo ? 'selected' : '' ?>>
                        <?= e($tipo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Modo admissão</label>
            <select name="modo_internacao">
                <option value="">Todos</option>
                <?php foreach ($modos as $modo): ?>
                    <option value="<?= e($modo) ?>" <?= $modoInternação === $modo ? 'selected' : '' ?>>
                        <?= e($modo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>UTI</label>
            <select name="uti">
                <option value="">Todos</option>
                <option value="s" <?= $uti === 's' ? 'selected' : '' ?>>Sim</option>
                <option value="n" <?= $uti === 'n' ? 'selected' : '' ?>>Não</option>
            </select>
        </div>
        <div class="bi-filter">
            <label>Data internação</label>
            <input type="date" name="data_inicio" value="<?= e($dataInicio) ?>">
        </div>
        <div class="bi-filter">
            <label>Data final</label>
            <input type="date" name="data_fim" value="<?= e($dataFim) ?>">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-grid fixed-2" style="margin-top:16px;">
        <div class="bi-panel">
            <h3>Valor por Médico</h3>
            <div class="bi-chart"><canvas id="chartValorMedico"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>MP por Médico</h3>
            <div class="bi-chart"><canvas id="chartMpMedico"></canvas></div>
        </div>
    </div>

    <div class="bi-panel" style="margin-top:16px;">
        <h3>Médico</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>CRM</th>
                        <th>Médico</th>
                        <th>Paciente</th>
                        <th>MP</th>
                        <th>Valor apresentado</th>
                        <th>Tipo admissão</th>
                        <th>Modo internação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$tableRows): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Sem dados para exibir</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tableRows as $row): ?>
                            <tr>
                                <td><?= e($row['crm_int'] ?? '-') ?></td>
                                <td><?= e($row['medico'] ?? '-') ?></td>
                                <td><?= e($row['paciente'] ?? '-') ?></td>
                                <td><?= fmt_num($row['mp'] ?? 0, 0) ?></td>
                                <td><?= fmt_money($row['valor_apresentado'] ?? 0) ?></td>
                                <td><?= e($row['tipo_admissao_int'] ?? '-') ?></td>
                                <td><?= e($row['modo_internacao_int'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const medicoLabelsValor = <?= json_encode($medicoLabelsValor, JSON_UNESCAPED_UNICODE) ?>;
    const medicoLabelsMp = <?= json_encode($medicoLabelsMp, JSON_UNESCAPED_UNICODE) ?>;
    const medicoValores = <?= json_encode($medicoValores, JSON_NUMERIC_CHECK) ?>;
    const medicoMp = <?= json_encode($medicoMp, JSON_NUMERIC_CHECK) ?>;

    const tickColor = '#c7d4e2';

    function barOptionsMoney() {
        return {
            legend: {
                display: false
            },
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: tickColor
                    },
                    grid: {
                        display: false
                    }
                },
                y: {
                    ticks: {
                        color: tickColor,
                        callback: (value) => window.biMoneyTick ? window.biMoneyTick(value) : value
                    },
                    grid: {
                        color: 'rgba(255,255,255,0.1)'
                    },
                    title: {
                        display: true,
                        text: 'Valor (R$)',
                        color: tickColor
                    }
                },
                xAxes: [{
                    ticks: {
                        fontColor: tickColor
                    },
                    gridLines: {
                        display: false
                    }
                }],
                yAxes: [{
                    ticks: {
                        fontColor: tickColor,
                        callback: (value) => window.biMoneyTick ? window.biMoneyTick(value) : value
                    },
                    gridLines: {
                        color: 'rgba(255,255,255,0.1)'
                    },
                    scaleLabel: {
                        display: true,
                        labelString: 'Valor (R$)',
                        fontColor: tickColor
                    }
                }]
            }
        };
    }

    new Chart(document.getElementById('chartValorMedico'), {
        type: 'bar',
        data: {
            labels: medicoLabelsValor,
            datasets: [{
                data: medicoValores,
                backgroundColor: 'rgba(126,150,255,0.82)',
                borderRadius: 10
            }]
        },
        options: barOptionsMoney()
    });

    new Chart(document.getElementById('chartMpMedico'), {
        type: 'bar',
        data: {
            labels: medicoLabelsMp,
            datasets: [{
                data: medicoMp,
                backgroundColor: 'rgba(126,150,255,0.82)',
                borderRadius: 10
            }]
        },
        options: {
            legend: {
                display: false
            },
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: tickColor
                    },
                    grid: {
                        display: false
                    }
                },
                y: {
                    ticks: {
                        color: tickColor
                    },
                    grid: {
                        color: 'rgba(255,255,255,0.1)'
                    },
                    title: {
                        display: true,
                        text: 'MP (dias)',
                        color: tickColor
                    }
                },
                xAxes: [{
                    ticks: {
                        fontColor: tickColor
                    },
                    gridLines: {
                        display: false
                    }
                }],
                yAxes: [{
                    ticks: {
                        fontColor: tickColor
                    },
                    gridLines: {
                        color: 'rgba(255,255,255,0.1)'
                    },
                    scaleLabel: {
                        display: true,
                        labelString: 'MP (dias)',
                        fontColor: tickColor
                    }
                }]
            }
        }
    });
</script>

<?php require_once("templates/footer.php"); ?>

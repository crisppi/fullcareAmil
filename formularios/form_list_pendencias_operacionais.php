<?php
require_once("templates/header.php");
require_once("models/message.php");

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function dateToTs(?string $date): ?int
{
    if (!$date) return null;
    $ts = strtotime(substr((string)$date, 0, 10));
    return $ts ? (int)$ts : null;
}

function daysExclusive(int $startTs, int $endTs): int
{
    if ($endTs <= $startTs) return 0;
    return (int) floor(($endTs - $startTs) / 86400);
}

function computeCoverageAndGaps(array $intervals, int $startTs, int $endTs): array
{
    if (!$intervals) {
        return [0, daysExclusive($startTs, $endTs), [[date('d/m/Y', $startTs), date('d/m/Y', $endTs)]]];
    }

    usort($intervals, fn($a, $b) => $a['s'] <=> $b['s']);

    $coveredDays = 0;
    $gaps = [];
    $curS = $intervals[0]['s'];
    $curE = $intervals[0]['e'];

    foreach ($intervals as $idx => $it) {
        if ($idx === 0) continue;

        if ($it['s'] <= $curE) {
            if ($it['e'] > $curE) $curE = $it['e'];
            continue;
        }

        if ($curS > $startTs) {
            $gapStart = $startTs;
            $gapEnd = $curS;
            if ($gapEnd > $gapStart) $gaps[] = [date('d/m/Y', $gapStart), date('d/m/Y', $gapEnd)];
        }

        $coveredDays += daysExclusive($curS, $curE);
        $curS = $it['s'];
        $curE = $it['e'];
    }

    if ($curS > $startTs) {
        $gapStart = $startTs;
        $gapEnd = $curS;
        if ($gapEnd > $gapStart) $gaps[] = [date('d/m/Y', $gapStart), date('d/m/Y', $gapEnd)];
    }

    $coveredDays += daysExclusive($curS, $curE);

    if ($curE < $endTs) {
        $gapStart = $curE;
        $gapEnd = $endTs;
        if ($gapEnd > $gapStart) $gaps[] = [date('d/m/Y', $gapStart), date('d/m/Y', $gapEnd)];
    }

    $totalDays = daysExclusive($startTs, $endTs);
    $missingDays = max(0, $totalDays - $coveredDays);

    return [$coveredDays, $missingDays, $gaps];
}

$pesquisa_nome = trim((string)filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS));
$pesquisa_pac = trim((string)filter_input(INPUT_GET, 'pesquisa_pac', FILTER_SANITIZE_SPECIAL_CHARS));
$tipoPendencia = trim((string)filter_input(INPUT_GET, 'tipo', FILTER_SANITIZE_SPECIAL_CHARS));
$limite = (int)(filter_input(INPUT_GET, 'limite_pag') ?: 20);
$limite = max(5, $limite);
$paginaAtual = (int)(filter_input(INPUT_GET, 'pag') ?: 1);
$paginaAtual = max(1, $paginaAtual);
$limiteSemVisitaMed = (int)(filter_input(INPUT_GET, 'dias_sem_visita_med') ?: 7);
$limiteSemVisitaMed = max(1, $limiteSemVisitaMed);

$whereParts = [];
$params = [];
if ($pesquisa_nome !== '') {
    $whereParts[] = 'ho.nome_hosp LIKE :hospital';
    $params[':hospital'] = "%{$pesquisa_nome}%";
}
if ($pesquisa_pac !== '') {
    $whereParts[] = 'pa.nome_pac LIKE :paciente';
    $params[':paciente'] = "%{$pesquisa_pac}%";
}
$whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

$sqlBase = "
    SELECT
        i.id_internacao,
        i.data_intern_int,
        i.internado_int,
        i.senha_int,
        pa.nome_pac,
        ho.nome_hosp,
        se.seguradora_seg,
        (
            SELECT MAX(v.data_visita_vis)
            FROM tb_visita v
            WHERE v.fk_internacao_vis = i.id_internacao
        ) AS ultima_visita,
        (
            SELECT MAX(v2.data_visita_vis)
            FROM tb_visita v2
            WHERE v2.fk_internacao_vis = i.id_internacao
              AND LOWER(COALESCE(v2.visita_med_vis, '')) IN ('s', 'sim', '1')
        ) AS ultima_visita_med,
        alt.data_alta_alt,
        alt.tipo_alta_alt
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital ho ON ho.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_seguradora se ON se.id_seguradora = pa.fk_seguradora_pac
    LEFT JOIN (
        SELECT fk_id_int_alt,
               MAX(data_alta_alt) AS data_alta_alt,
               MAX(tipo_alta_alt) AS tipo_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) alt ON alt.fk_id_int_alt = i.id_internacao
    {$whereSql}
    ORDER BY i.id_internacao DESC
";

$stmt = $conn->prepare($sqlBase);
$stmt->execute($params);
$internacoes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$ids = array_values(array_unique(array_filter(array_map(fn($r) => (int)($r['id_internacao'] ?? 0), $internacoes))));
$prorrogacoesByInternacao = [];
if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmtPr = $conn->prepare("\n        SELECT fk_internacao_pror, prorrog1_ini_pror, prorrog1_fim_pror\n        FROM tb_prorrogacao\n        WHERE fk_internacao_pror IN ({$ph})\n        ORDER BY fk_internacao_pror, prorrog1_ini_pror\n    ");
    $stmtPr->execute($ids);
    while ($row = $stmtPr->fetch(PDO::FETCH_ASSOC)) {
        $fk = (int)($row['fk_internacao_pror'] ?? 0);
        if ($fk > 0) $prorrogacoesByInternacao[$fk][] = $row;
    }
}

$today = new DateTime('today');
$todayTs = (int)$today->format('U');
$pendencias = [];
$resumo = [
    'sem_senha' => 0,
    'sem_visita_medica' => 0,
    'prorrogacao_aberta' => 0,
    'alta_sem_fechamento' => 0,
];

$addPendencia = function (array $row, string $tipo, string $titulo, string $detalhe, string $acaoUrl, string $acaoLabel) use (&$pendencias, &$resumo) {
    $resumo[$tipo] = ($resumo[$tipo] ?? 0) + 1;

    $pendencias[] = [
        'tipo' => $tipo,
        'tipo_label' => $titulo,
        'id_internacao' => (int)($row['id_internacao'] ?? 0),
        'hospital' => $row['nome_hosp'] ?? '--',
        'paciente' => $row['nome_pac'] ?? '--',
        'seguradora' => $row['seguradora_seg'] ?? '--',
        'data_internacao' => $row['data_intern_int'] ?? null,
        'detalhe' => $detalhe,
        'acao_url' => $acaoUrl,
        'acao_label' => $acaoLabel,
    ];
};

foreach ($internacoes as $row) {
    $id = (int)($row['id_internacao'] ?? 0);
    if ($id <= 0) continue;

    $internado = strtolower(trim((string)($row['internado_int'] ?? '')));
    $senha = trim((string)($row['senha_int'] ?? ''));
    $dataInternTs = dateToTs($row['data_intern_int'] ?? null);
    $dataAltaTs = dateToTs($row['data_alta_alt'] ?? null);

    if ($internado === 's' && $senha === '') {
        $addPendencia(
            $row,
            'sem_senha',
            'Internação sem senha',
            'Senha não informada para internação ativa.',
            rtrim($BASE_URL, '/') . '/edit_internacao.php?id_internacao=' . $id,
            'Editar internação'
        );
    }

    if ($internado === 's') {
        $ultimaMedTs = dateToTs($row['ultima_visita_med'] ?? null);
        $diasSemVisitaMed = $ultimaMedTs
            ? (new DateTime(date('Y-m-d', $ultimaMedTs)))->diff($today)->days
            : ($dataInternTs ? (new DateTime(date('Y-m-d', $dataInternTs)))->diff($today)->days : 0);

        if ($diasSemVisitaMed > $limiteSemVisitaMed) {
            $addPendencia(
                $row,
                'sem_visita_medica',
                'Sem visita médica',
                $diasSemVisitaMed . ' dias sem visita médica (limite ' . $limiteSemVisitaMed . ').',
                rtrim($BASE_URL, '/') . '/cad_visita.php?id_internacao=' . $id,
                'Lançar visita'
            );
        }
    }

    if ($dataInternTs) {
        $endTs = $dataAltaTs ?: $todayTs;
        if ($endTs > $dataInternTs) {
            $intervals = [];
            foreach (($prorrogacoesByInternacao[$id] ?? []) as $p) {
                $iniTs = dateToTs($p['prorrog1_ini_pror'] ?? null);
                if (!$iniTs) continue;
                $fimTs = dateToTs($p['prorrog1_fim_pror'] ?? null) ?: $endTs;
                if ($fimTs <= $dataInternTs || $iniTs >= $endTs) continue;
                $intervals[] = [
                    's' => max($dataInternTs, $iniTs),
                    'e' => min($endTs, $fimTs),
                ];
            }

            $coverageStartTs = $intervals ? min(array_column($intervals, 's')) : $dataInternTs;
            [, $missingDays, $gaps] = computeCoverageAndGaps($intervals, $coverageStartTs, $endTs);
            if ($missingDays > 0) {
                $parts = array_map(fn($g) => $g[0] . ' -> ' . $g[1], $gaps);
                $detalhe = $missingDays . ' dias em aberto';
                if ($parts) $detalhe .= ' | ' . implode(' | ', $parts);

                $addPendencia(
                    $row,
                    'prorrogacao_aberta',
                    'Prorrogação em aberto',
                    $detalhe,
                    rtrim($BASE_URL, '/') . '/edit_internacao.php?id_internacao=' . $id . '&section=prorrog#collapseProrrog',
                    'Editar prorrogação'
                );
            }
        }
    }

    $tipoAlta = trim((string)($row['tipo_alta_alt'] ?? ''));
    if ($dataAltaTs && $tipoAlta === '') {
        $addPendencia(
            $row,
            'alta_sem_fechamento',
            'Alta sem fechamento',
            'Data de alta registrada sem motivo de alta.',
            rtrim($BASE_URL, '/') . '/edit_alta.php?type=alta&id_internacao=' . $id,
            'Fechar alta'
        );
    }
}

if ($tipoPendencia !== '' && isset($resumo[$tipoPendencia])) {
    $pendencias = array_values(array_filter($pendencias, fn($p) => $p['tipo'] === $tipoPendencia));
}

$total = count($pendencias);
$totalPaginas = max(1, (int)ceil($total / $limite));
$paginaAtual = max(1, min($paginaAtual, $totalPaginas));
$offset = ($paginaAtual - 1) * $limite;
$itensPagina = array_slice($pendencias, $offset, $limite);

$baseParams = [
    'pesquisa_nome' => $pesquisa_nome,
    'pesquisa_pac' => $pesquisa_pac,
    'tipo' => $tipoPendencia,
    'limite_pag' => $limite,
    'dias_sem_visita_med' => $limiteSemVisitaMed,
];

function pendenciasUrl(array $params): string
{
    $query = http_build_query(array_filter($params, fn($v) => $v !== null && $v !== ''));
    return $query ? ('list_pendencias_operacionais.php?' . $query) : 'list_pendencias_operacionais.php';
}
?>

<style>
.pend-card-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(180px, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}

.pend-card {
    border: 1px solid #e7e0f0;
    border-radius: 12px;
    background: #faf8fd;
    padding: 10px 12px;
}

.pend-card .label {
    font-size: .82rem;
    color: #5b4a66;
    font-weight: 600;
}

.pend-card .value {
    font-size: 1.35rem;
    font-weight: 800;
    color: #4b1f63;
    line-height: 1.1;
}

.btn-pend-action {
    font-weight: 600;
    border-width: 1px;
}

.btn-pend-sem-senha {
    color: #b02a37;
    border-color: #f1aeb5;
    background: #fff5f6;
}

.btn-pend-sem-senha:hover {
    color: #fff;
    background: #dc3545;
    border-color: #dc3545;
}

.btn-pend-sem-visita {
    color: #664d03;
    border-color: #ffe69c;
    background: #fffbea;
}

.btn-pend-sem-visita:hover {
    color: #fff;
    background: #f0ad00;
    border-color: #f0ad00;
}

.btn-pend-prorrog {
    color: #0a58ca;
    border-color: #9ec5fe;
    background: #eef5ff;
}

.btn-pend-prorrog:hover {
    color: #fff;
    background: #0d6efd;
    border-color: #0d6efd;
}

.btn-pend-alta {
    color: #146c43;
    border-color: #a3cfbb;
    background: #eefaf4;
}

.btn-pend-alta:hover {
    color: #fff;
    background: #198754;
    border-color: #198754;
}

@media (max-width: 1200px) {
    .pend-card-grid {
        grid-template-columns: repeat(2, minmax(180px, 1fr));
    }
}

@media (max-width: 640px) {
    .pend-card-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container-fluid form_container" style="margin-top:15px;">
    <h4 class="page-title">Pendências Operacionais</h4>
    <hr style="margin-top: 5px; margin-bottom: 10px;">

    <div class="pend-card-grid">
        <div class="pend-card">
            <div class="label">Sem senha</div>
            <div class="value"><?= (int)$resumo['sem_senha'] ?></div>
        </div>
        <div class="pend-card">
            <div class="label">Sem visita médica</div>
            <div class="value"><?= (int)$resumo['sem_visita_medica'] ?></div>
        </div>
        <div class="pend-card">
            <div class="label">Prorrogação em aberto</div>
            <div class="value"><?= (int)$resumo['prorrogacao_aberta'] ?></div>
        </div>
        <div class="pend-card">
            <div class="label">Alta sem fechamento</div>
            <div class="value"><?= (int)$resumo['alta_sem_fechamento'] ?></div>
        </div>
    </div>

    <div class="complete-table">
        <div class="table-filters fc-list-filters">
            <form method="GET" class="fc-list-filters-line">
                <div class="fc-filter-item w-hospital">
                    <input class="form-control form-control-sm" type="text" name="pesquisa_nome" placeholder="Hospital"
                        value="<?= e($pesquisa_nome) ?>">
                </div>
                <div class="fc-filter-item w-paciente">
                    <input class="form-control form-control-sm" type="text" name="pesquisa_pac" placeholder="Paciente"
                        value="<?= e($pesquisa_pac) ?>">
                </div>
                <div class="fc-filter-item w-select">
                    <select class="form-control form-control-sm" name="tipo">
                        <option value="">Todas as pendências</option>
                        <option value="sem_senha" <?= $tipoPendencia === 'sem_senha' ? 'selected' : '' ?>>Sem senha</option>
                        <option value="sem_visita_medica" <?= $tipoPendencia === 'sem_visita_medica' ? 'selected' : '' ?>>Sem visita médica</option>
                        <option value="prorrogacao_aberta" <?= $tipoPendencia === 'prorrogacao_aberta' ? 'selected' : '' ?>>Prorrogação em aberto</option>
                        <option value="alta_sem_fechamento" <?= $tipoPendencia === 'alta_sem_fechamento' ? 'selected' : '' ?>>Alta sem fechamento</option>
                    </select>
                </div>
                <div class="fc-filter-item w-short">
                    <input class="form-control form-control-sm" type="number" min="1" max="60" name="dias_sem_visita_med"
                        title="Limite de dias sem visita médica" value="<?= (int)$limiteSemVisitaMed ?>">
                </div>
                <div class="fc-filter-item w-limit">
                    <select class="form-control form-control-sm" name="limite_pag">
                        <option value="10" <?= $limite == 10 ? 'selected' : '' ?>>10</option>
                        <option value="20" <?= $limite == 20 ? 'selected' : '' ?>>20</option>
                        <option value="50" <?= $limite == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limite == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
                <div class="fc-filter-item w-actions">
                    <button type="submit" class="btn btn-primary"
                        style="background-color:#5e2363;width:42px;height:32px;border-color:#5e2363">
                        <span class="material-icons" style="margin-left:-3px;margin-top:-2px;">search</span>
                    </button>
                    <a href="<?= e(pendenciasUrl([])) ?>" class="btn btn-light btn-sm" title="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>

        <div id="table-content">
            <table class="table table-sm table-striped table-hover table-condensed">
                <thead>
                    <tr>
                        <th style="min-width:160px;">Pendência</th>
                        <th style="min-width:70px;">Id-Int</th>
                        <th style="min-width:180px;">Hospital</th>
                        <th style="min-width:180px;">Paciente</th>
                        <th style="min-width:140px;">Seguradora</th>
                        <th style="min-width:95px;">Data Int</th>
                        <th style="min-width:320px;">Detalhe</th>
                        <th style="min-width:120px;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$itensPagina): ?>
                    <tr>
                        <td colspan="8" class="text-muted">Nenhuma pendência para os filtros aplicados.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($itensPagina as $item): ?>
                    <?php
                        $btnClass = 'btn-pend-prorrog';
                        if (($item['tipo'] ?? '') === 'sem_senha') {
                            $btnClass = 'btn-pend-sem-senha';
                        } elseif (($item['tipo'] ?? '') === 'sem_visita_medica') {
                            $btnClass = 'btn-pend-sem-visita';
                        } elseif (($item['tipo'] ?? '') === 'alta_sem_fechamento') {
                            $btnClass = 'btn-pend-alta';
                        }
                    ?>
                    <tr>
                        <td><strong><?= e($item['tipo_label']) ?></strong></td>
                        <td><?= (int)$item['id_internacao'] ?></td>
                        <td><?= e($item['hospital']) ?></td>
                        <td><?= e($item['paciente']) ?></td>
                        <td><?= e($item['seguradora']) ?></td>
                        <td><?= !empty($item['data_internacao']) ? e(date('d/m/Y', strtotime($item['data_internacao']))) : '--' ?></td>
                        <td><?= e($item['detalhe']) ?></td>
                        <td>
                            <a class="btn btn-sm btn-pend-action <?= e($btnClass) ?>" href="<?= e($item['acao_url']) ?>">
                                <?= e($item['acao_label']) ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="display:flex; align-items:center; margin-top:20px;">
                <div class="pagination" style="margin: 0 auto;">
                    <?php if ($totalPaginas > 1): ?>
                    <ul class="pagination">
                        <?php if ($paginaAtual > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= e(pendenciasUrl($baseParams + ['pag' => 1])) ?>"><i class="fa-solid fa-angles-left"></i></a></li>
                        <li class="page-item"><a class="page-link" href="<?= e(pendenciasUrl($baseParams + ['pag' => $paginaAtual - 1])) ?>"><i class="fa-solid fa-angle-left"></i></a></li>
                        <?php endif; ?>

                        <?php
                        $ini = max(1, $paginaAtual - 5);
                        $fim = min($totalPaginas, $paginaAtual + 5);
                        for ($i = $ini; $i <= $fim; $i++):
                        ?>
                        <li class="page-item <?= $paginaAtual === $i ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e(pendenciasUrl($baseParams + ['pag' => $i])) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($paginaAtual < $totalPaginas): ?>
                        <li class="page-item"><a class="page-link" href="<?= e(pendenciasUrl($baseParams + ['pag' => $paginaAtual + 1])) ?>"><i class="fa-solid fa-angle-right"></i></a></li>
                        <li class="page-item"><a class="page-link" href="<?= e(pendenciasUrl($baseParams + ['pag' => $totalPaginas])) ?>"><i class="fa-solid fa-angles-right"></i></a></li>
                        <?php endif; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <div class="table-counter">
                    <p style="margin-bottom:25px;font-size:1em; font-weight:600; font-family:var(--bs-font-sans-serif); text-align:right">
                        Total: <?= (int)$total ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once("templates/header.php");
require_once("models/message.php");

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function dateToTs(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    $ts = strtotime(substr((string)$date, 0, 10));
    return $ts ? (int)$ts : null;
}

function daysExclusive(int $startTs, int $endTs): int
{
    if ($endTs <= $startTs) {
        return 0;
    }
    return (int)floor(($endTs - $startTs) / 86400);
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
        if ($idx === 0) {
            continue;
        }

        if ($it['s'] <= $curE) {
            if ($it['e'] > $curE) {
                $curE = $it['e'];
            }
            continue;
        }

        if ($curS > $startTs) {
            $gapStart = $startTs;
            $gapEnd = $curS;
            if ($gapEnd > $gapStart) {
                $gaps[] = [date('d/m/Y', $gapStart), date('d/m/Y', $gapEnd)];
            }
        }

        $coveredDays += daysExclusive($curS, $curE);
        $curS = $it['s'];
        $curE = $it['e'];
    }

    if ($curS > $startTs) {
        $gapStart = $startTs;
        $gapEnd = $curS;
        if ($gapEnd > $gapStart) {
            $gaps[] = [date('d/m/Y', $gapStart), date('d/m/Y', $gapEnd)];
        }
    }

    $coveredDays += daysExclusive($curS, $curE);

    if ($curE < $endTs) {
        $gapStart = $curE;
        $gapEnd = $endTs;
        if ($gapEnd > $gapStart) {
            $gaps[] = [date('d/m/Y', $gapStart), date('d/m/Y', $gapEnd)];
        }
    }

    $totalDays = daysExclusive($startTs, $endTs);
    $missingDays = max(0, $totalDays - $coveredDays);

    return [$coveredDays, $missingDays, $gaps];
}

$pesquisa_nome       = trim((string)filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS));
$pesquisa_pac        = trim((string)filter_input(INPUT_GET, 'pesquisa_pac', FILTER_SANITIZE_SPECIAL_CHARS));
$pesquisa_seguradora = trim((string)filter_input(INPUT_GET, 'pesquisa_seguradora', FILTER_SANITIZE_SPECIAL_CHARS));
$pesquisa_matricula  = trim((string)filter_input(INPUT_GET, 'pesquisa_matricula', FILTER_SANITIZE_SPECIAL_CHARS));
$senha_int           = trim((string)filter_input(INPUT_GET, 'senha_int', FILTER_SANITIZE_SPECIAL_CHARS));
$pesqInternado       = trim((string)filter_input(INPUT_GET, 'pesqInternado', FILTER_SANITIZE_SPECIAL_CHARS));
$pesqInternado       = $pesqInternado !== '' ? $pesqInternado : 's';

$data_intern_int     = filter_input(INPUT_GET, 'data_intern_int') ?: '';
$data_intern_int_max = filter_input(INPUT_GET, 'data_intern_int_max') ?: date('Y-m-d');

$limite              = (int)(filter_input(INPUT_GET, 'limite_pag') ?: 10);
$limite              = max(1, $limite);
$paginaAtual         = (int)(filter_input(INPUT_GET, 'pag') ?: 1);
$paginaAtual         = max(1, $paginaAtual);

$normRole = static function ($txt): string {
    $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $ascii !== false ? $ascii : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
};
$startsWithAny = static function (string $value, array $prefixes): bool {
    foreach ($prefixes as $prefix) {
        if ($prefix !== '' && strpos($value, $prefix) === 0) {
            return true;
        }
    }
    return false;
};

$idUsuarioSessao = (int)($_SESSION['id_usuario'] ?? 0);
$cargoNormSessao = $normRole($_SESSION['cargo'] ?? '');
$nivelSessao = (int)($_SESSION['nivel'] ?? 99);
$isDiretoriaSessao = in_array($cargoNormSessao, ['diretoria', 'diretor', 'board'], true)
    || (strpos($cargoNormSessao, 'diretor') !== false)
    || (strpos($cargoNormSessao, 'diretoria') !== false)
    || ($nivelSessao === -1);
$isPerfilOperacional = $startsWithAny($cargoNormSessao, [
    'medico',
    'med',
    'enfermeiro',
    'enf',
    'secretaria',
    'administrativo',
    'adm'
]);

$whereParts = [];
$params = [];

if ($isPerfilOperacional && !$isDiretoriaSessao) {
    if ($idUsuarioSessao > 0) {
        $whereParts[] = 'i.fk_hospital_int IN (SELECT hu.fk_hospital_user FROM tb_hospitalUser hu WHERE hu.fk_usuario_hosp = :scope_user)';
        $params[':scope_user'] = $idUsuarioSessao;
    } else {
        $whereParts[] = '1=0';
    }
}

if ($pesquisa_nome !== '') {
    $whereParts[] = 'ho.nome_hosp LIKE :hospital';
    $params[':hospital'] = "%{$pesquisa_nome}%";
}
if ($pesquisa_pac !== '') {
    $whereParts[] = 'pa.nome_pac LIKE :paciente';
    $params[':paciente'] = "%{$pesquisa_pac}%";
}
if ($pesquisa_seguradora !== '') {
    $whereParts[] = 's.seguradora_seg LIKE :seguradora';
    $params[':seguradora'] = "%{$pesquisa_seguradora}%";
}
if ($pesquisa_matricula !== '') {
    $whereParts[] = 'pa.matricula_pac LIKE :matricula';
    $params[':matricula'] = "%{$pesquisa_matricula}%";
}
if ($senha_int !== '') {
    $whereParts[] = 'i.senha_int LIKE :senha';
    $params[':senha'] = "%{$senha_int}%";
}
if ($pesqInternado !== '') {
    $whereParts[] = 'i.internado_int = :internado';
    $params[':internado'] = $pesqInternado;
}
if ($data_intern_int !== '') {
    $whereParts[] = 'i.data_intern_int BETWEEN :data_ini AND :data_fim';
    $params[':data_ini'] = $data_intern_int;
    $params[':data_fim'] = $data_intern_int_max;
}

$whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

$sqlInternacoes = "
    SELECT
        i.id_internacao,
        i.data_intern_int,
        i.senha_int,
        i.internado_int,
        pa.nome_pac,
        pa.matricula_pac,
        ho.nome_hosp,
        s.seguradora_seg
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital ho ON ho.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    {$whereSql}
    ORDER BY i.id_internacao DESC
";

$stmtIntern = $conn->prepare($sqlInternacoes);
$stmtIntern->execute($params);
$internacoes = $stmtIntern->fetchAll(PDO::FETCH_ASSOC) ?: [];

$ids = array_values(array_unique(array_filter(array_map(fn($r) => (int)($r['id_internacao'] ?? 0), $internacoes))));

$prorrogacoesByInternacao = [];
$altasByInternacao = [];
$visitasByInternacao = [];

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmtPr = $conn->prepare("\n        SELECT fk_internacao_pror, prorrog1_ini_pror, prorrog1_fim_pror\n        FROM tb_prorrogacao\n        WHERE fk_internacao_pror IN ({$placeholders})\n        ORDER BY fk_internacao_pror, prorrog1_ini_pror\n    ");
    $stmtPr->execute($ids);
    while ($row = $stmtPr->fetch(PDO::FETCH_ASSOC)) {
        $fk = (int)($row['fk_internacao_pror'] ?? 0);
        if ($fk > 0) {
            $prorrogacoesByInternacao[$fk][] = $row;
        }
    }

    $stmtAlta = $conn->prepare("\n        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt\n        FROM tb_alta\n        WHERE fk_id_int_alt IN ({$placeholders})\n        GROUP BY fk_id_int_alt\n    ");
    $stmtAlta->execute($ids);
    while ($row = $stmtAlta->fetch(PDO::FETCH_ASSOC)) {
        $fk = (int)($row['fk_id_int_alt'] ?? 0);
        if ($fk > 0) {
            $altasByInternacao[$fk] = $row['data_alta_alt'] ?? null;
        }
    }

    $stmtVis = $conn->prepare("\n        SELECT fk_internacao_vis, data_visita_vis\n        FROM tb_visita\n        WHERE fk_internacao_vis IN ({$placeholders})\n        ORDER BY fk_internacao_vis, data_visita_vis\n    ");
    $stmtVis->execute($ids);
    while ($row = $stmtVis->fetch(PDO::FETCH_ASSOC)) {
        $fk = (int)($row['fk_internacao_vis'] ?? 0);
        if ($fk > 0) {
            $visitasByInternacao[$fk][] = $row;
        }
    }
}

$hojeDate = date('Y-m-d');
$todayTs = strtotime($hojeDate);
$todayDateObj = new DateTime($hojeDate);

$pendentes = [];

foreach ($internacoes as $intern) {
    $idInternacao = (int)($intern['id_internacao'] ?? 0);
    if ($idInternacao <= 0) {
        continue;
    }

    $startTs = dateToTs($intern['data_intern_int'] ?? null);
    if (!$startTs) {
        continue;
    }

    $dataAlta = $altasByInternacao[$idInternacao] ?? null;
    $endTs = dateToTs($dataAlta) ?: $todayTs;
    if ($endTs <= $startTs) {
        continue;
    }

    $intervals = [];
    foreach (($prorrogacoesByInternacao[$idInternacao] ?? []) as $p) {
        $iniTs = dateToTs($p['prorrog1_ini_pror'] ?? null);
        if (!$iniTs) {
            continue;
        }

        $fimTs = dateToTs($p['prorrog1_fim_pror'] ?? null) ?: $endTs;
        if ($fimTs <= $startTs || $iniTs >= $endTs) {
            continue;
        }

        $intervals[] = [
            's' => max($startTs, $iniTs),
            'e' => min($endTs, $fimTs),
        ];
    }

    $coverageStartTs = $intervals ? min(array_column($intervals, 's')) : $startTs;
    [$coveredDays, $missingDays, $gaps] = computeCoverageAndGaps($intervals, $coverageStartTs, $endTs);
    if ($missingDays <= 0) {
        continue;
    }

    $visitas = $visitasByInternacao[$idInternacao] ?? [];
    $ultimaVisita = null;

    foreach ($visitas as $vis) {
        $dataVisita = $vis['data_visita_vis'] ?? null;
        if ($dataVisita) {
            if (!$ultimaVisita || strtotime($dataVisita) > strtotime($ultimaVisita)) {
                $ultimaVisita = $dataVisita;
            }
        }
    }

    $diasInternado = (new DateTime(date('Y-m-d', $startTs)))->diff($todayDateObj)->days;

    $parts = array_map(fn($g) => $g[0] . ' -> ' . $g[1], $gaps);
    $periodoAberto = $missingDays . ' dias';
    if ($parts) {
        $periodoAberto .= ' | ' . implode(' | ', $parts);
    }

    $pendentes[] = [
        'id_internacao' => $idInternacao,
        'nome_hosp' => $intern['nome_hosp'] ?? '--',
        'nome_pac' => $intern['nome_pac'] ?? '--',
        'seguradora_seg' => $intern['seguradora_seg'] ?? '--',
        'data_intern_int' => $intern['data_intern_int'] ?? null,
        'senha_int' => $intern['senha_int'] ?? '',
        'dias_int' => $diasInternado,
        'ultima_visita' => $ultimaVisita,
        'periodo_aberto' => $periodoAberto,
        'covered_days' => $coveredDays,
    ];
}

$total = count($pendentes);
$totalPaginas = max(1, (int)ceil($total / $limite));
$paginaAtual = min($paginaAtual, $totalPaginas);
$offset = ($paginaAtual - 1) * $limite;
$paginaItens = array_slice($pendentes, $offset, $limite);

$window = 5;
$paginaInicio = max(1, $paginaAtual - $window);
$paginaFim = min($totalPaginas, $paginaAtual + $window);

function buildListUrl(array $params): string
{
    $base = 'list_prorrogacao_pendente.php';
    $query = http_build_query(array_filter($params, fn($v) => $v !== null && $v !== ''));
    return $query ? ($base . '?' . $query) : $base;
}

$baseParams = [
    'pesquisa_nome' => $pesquisa_nome,
    'pesquisa_pac' => $pesquisa_pac,
    'pesquisa_seguradora' => $pesquisa_seguradora,
    'pesquisa_matricula' => $pesquisa_matricula,
    'senha_int' => $senha_int,
    'data_intern_int' => $data_intern_int,
    'data_intern_int_max' => $data_intern_int_max,
    'pesqInternado' => $pesqInternado,
    'limite_pag' => $limite,
];
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

<div class="container-fluid form_container" style="margin-top:15px;">
    <h4 class="page-title">Prorrogações Pendentes</h4>
    <hr style="margin-top: 5px; margin-bottom: 10px;">

    <div class="complete-table">
        <div class="table-filters fc-list-filters">
            <form method="GET" class="fc-list-filters-line">
                <div class="fc-filter-item w-hospital">
                    <input class="form-control form-control-sm" type="text" name="pesquisa_nome"
                        placeholder="Hospital" value="<?= e($pesquisa_nome) ?>">
                </div>
                <div class="fc-filter-item w-paciente">
                    <input class="form-control form-control-sm" type="text" name="pesquisa_pac"
                        placeholder="Paciente" value="<?= e($pesquisa_pac) ?>">
                </div>
                <div class="fc-filter-item w-seguradora">
                    <input class="form-control form-control-sm" type="text" name="pesquisa_seguradora"
                        placeholder="Seguradora" value="<?= e($pesquisa_seguradora) ?>">
                </div>
                <div class="fc-filter-item w-short">
                    <input class="form-control form-control-sm" type="text" name="pesquisa_matricula"
                        placeholder="Matrícula" value="<?= e($pesquisa_matricula) ?>">
                </div>
                <div class="fc-filter-item w-short">
                    <input class="form-control form-control-sm" type="text" name="senha_int"
                        placeholder="Senha" value="<?= e($senha_int) ?>">
                </div>
                <div class="fc-filter-item w-select">
                    <select class="form-control form-control-sm" name="pesqInternado">
                        <option value="s" <?= $pesqInternado === 's' ? 'selected' : '' ?>>Internados</option>
                        <option value="n" <?= $pesqInternado === 'n' ? 'selected' : '' ?>>Não internados</option>
                    </select>
                </div>
                <div class="fc-filter-item w-limit">
                    <select class="form-control form-control-sm" name="limite_pag">
                        <option value="5" <?= $limite == 5 ? 'selected' : '' ?>>5</option>
                        <option value="10" <?= $limite == 10 ? 'selected' : '' ?>>10</option>
                        <option value="20" <?= $limite == 20 ? 'selected' : '' ?>>20</option>
                        <option value="50" <?= $limite == 50 ? 'selected' : '' ?>>50</option>
                    </select>
                </div>
                <div class="fc-filter-item w-date">
                    <input class="form-control form-control-sm" type="date" name="data_intern_int"
                        value="<?= e($data_intern_int) ?>">
                </div>
                <div class="fc-filter-item w-date">
                    <input class="form-control form-control-sm" type="date" name="data_intern_int_max"
                        value="<?= e($data_intern_int_max) ?>">
                </div>
                <div class="fc-filter-item w-actions">
                    <button type="submit" class="btn btn-primary"
                        style="background-color:#5e2363;width:42px;height:32px;border-color:#5e2363">
                        <span class="material-icons" style="margin-left:-3px;margin-top:-2px;">search</span>
                    </button>
                    <a href="<?= e(buildListUrl([])) ?>" class="btn btn-light btn-sm" title="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>

        <div id="table-content">
            <table class="table table-sm table-striped table-hover table-condensed">
                <thead>
                    <tr>
                        <th scope="col" style="min-width: 60px;">Id-Int</th>
                        <th scope="col" style="min-width: 150px;">Hospital</th>
                        <th scope="col" style="min-width: 150px;">Paciente</th>
                        <th scope="col" style="min-width: 150px;">Seguradora</th>
                        <th scope="col" style="min-width: 100px;">Data Int</th>
                        <th scope="col" style="min-width: 80px;">Senha</th>
                        <th scope="col" style="min-width: 80px;">Dias Int</th>
                        <th scope="col" style="min-width: 90px;">Últ Visita</th>
                        <th scope="col" style="min-width: 240px;">Períodos em aberto para prorrogar</th>
                        <th scope="col" style="min-width: 80px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$paginaItens): ?>
                    <tr>
                        <td colspan="10" class="text-muted">Sem registros para os filtros aplicados.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($paginaItens as $row): ?>
                    <tr style="font-size:13px;">
                        <td><?= (int)$row['id_internacao'] ?></td>
                        <td style="font-weight:bolder;"><?= e($row['nome_hosp']) ?></td>
                        <td><?= e($row['nome_pac']) ?></td>
                        <td><?= e($row['seguradora_seg']) ?></td>
                        <td><?= !empty($row['data_intern_int']) ? e(date('d/m/Y', strtotime($row['data_intern_int']))) : '--' ?></td>
                        <td style="font-weight:bolder;"><?= e($row['senha_int']) ?></td>
                        <td><?= (int)$row['dias_int'] ?></td>
                        <td><?= !empty($row['ultima_visita']) ? e(date('d/m/Y', strtotime($row['ultima_visita']))) : '--' ?></td>
                        <td style="font-weight:600;color:#8a1538;"><?= e($row['periodo_aberto']) ?></td>
                        <td class="fc-list-action">
                            <div class="dropdown">
                                <button class="btn btn-default dropdown-toggle" id="navbarScrollingDropdown"
                                    role="button" data-bs-toggle="dropdown" style="color:#5e2363" aria-expanded="false">
                                    <i class="bi bi-stack"></i>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="navbarScrollingDropdown">
                                    <li>
                                        <button class="btn btn-default"
                                            onclick="window.location.href='<?= e(rtrim($BASE_URL, '/')) ?>/internacoes/visualizar/<?= (int)$row['id_internacao'] ?>'"
                                            style="font-size: .9rem;">
                                            <i class="fas fa-eye"
                                                style="font-size: 1rem;margin-right:5px; color: rgb(27,156, 55);"></i>
                                            Visualização
                                        </button>
                                    </li>
                                    <li>
                                        <button class="btn btn-default"
                                            onclick="window.location.href='<?= e($BASE_URL) ?>edit_internacao.php?id_internacao=<?= (int)$row['id_internacao'] ?>&section=prorrog#collapseProrrog'"
                                            style="font-size: .9rem;">
                                            <i class="bi bi-pencil-square"
                                                style="font-size: 1rem; margin-right: 5px; color: rgba(113, 27, 156, 1);"></i>
                                            Editar Prorrogação
                                        </button>
                                    </li>
                                </ul>
                            </div>
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
                        <li class="page-item">
                            <a class="page-link" href="<?= e(buildListUrl($baseParams + ['pag' => 1])) ?>">
                                <i class="fa-solid fa-angles-left"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="<?= e(buildListUrl($baseParams + ['pag' => $paginaAtual - 1])) ?>">
                                <i class="fa-solid fa-angle-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php for ($i = $paginaInicio; $i <= $paginaFim; $i++): ?>
                        <li class="page-item <?= $paginaAtual === $i ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e(buildListUrl($baseParams + ['pag' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($paginaAtual < $totalPaginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= e(buildListUrl($baseParams + ['pag' => $paginaAtual + 1])) ?>">
                                <i class="fa-solid fa-angle-right"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="<?= e(buildListUrl($baseParams + ['pag' => $totalPaginas])) ?>">
                                <i class="fa-solid fa-angles-right"></i>
                            </a>
                        </li>
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

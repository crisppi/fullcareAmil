<?php
// ===== DEV ONLY (remova em produção) =====
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php-error.log');
error_reporting(E_ALL);

ob_start();
require_once(__DIR__ . "/../templates/header.php");
require_once(__DIR__ . "/../dao/internacaoDao.php");
require_once(__DIR__ . "/../models/pagination.php");

$internacaoDAO = new internacaoDAO($conn, $BASE_URL);

// ---------------------
// Helpers
// ---------------------
function e($v)
{
    return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8');
}
function firstField(array $row, array $candidates, $default = null)
{
    foreach ($candidates as $c) {
        if (array_key_exists($c, $row) && $row[$c] !== null && $row[$c] !== '') return $row[$c];
    }
    return $default;
}

/**
 * Dias de PRORROGAÇÃO:
 * - início inclusivo, fim EXCLUSIVO;
 * - se $fim === null, conta até hoje INCLUSIVO (usa amanhã como limite exclusivo).
 */
function daysPror(?string $ini, ?string $fim): int
{
    if (!$ini) return 0;
    try {
        $d1 = new DateTime($ini);
    } catch (Exception $e) {
        return 0;
    }

    if ($fim) {
        try {
            $d2 = new DateTime($fim);
        } catch (Exception $e) {
            return 0;
        }
    } else {
        $d2 = new DateTime('tomorrow'); // hoje inclusive
    }
    $days = (int)$d1->diff($d2)->days; // fim exclusivo
    return max(1, $days);
}

/** normaliza str para matching simples (lowercase e sem acentos) */
function norm($s)
{
    $s = mb_strtolower((string)$s, 'UTF-8');
    $map = [
        'á' => 'a',
        'à' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'é' => 'e',
        'è' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'í' => 'i',
        'ì' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ó' => 'o',
        'ò' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'ú' => 'u',
        'ù' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ç' => 'c'
    ];
    return strtr($s, $map);
}

/** classe CSS por acomodação */
function acomodacaoClass(string $label): string
{
    $t = norm($label);
    if (preg_match('/\b(uti|cti|intensiv)/', $t)) return 'acom-uti';
    if (preg_match('/\b(apto|apart)/', $t))      return 'acom-apto';
    if (preg_match('/semi/', $t))                return 'acom-semi';
    if (preg_match('/enferm|^enf\b/', $t))      return 'acom-enfermaria';
    if (preg_match('/\bps\b|pronto\s*socorro|emerg/', $t)) return 'acom-ps';
    if (preg_match('/isol/', $t))                return 'acom-isol';
    if (preg_match('/centro.*cir|(^|\b)cc($|\b)/', $t)) return 'acom-cc';
    return 'acom-outros';
}

/**
 * Consolida linhas (selectAllProrrogacao) em ciclos por internação,
 * somando trechos consecutivos da mesma acomodação e guardando ini/fim.
 */
function consolidarCiclosPorInternacao(array $linhas): array
{
    $CAMPOS_ACOM = ['acomod1_pror', 'acomodacao_pror', 'acomodacao', 'acom', 'tipo_acomodacao', 'desc_acom'];
    $CAMPOS_INI  = ['prorrog1_ini_pror', 'data_ini_pror', 'dt_ini_pror', 'data_inicio', 'dt_ini', 'inicio_acom'];
    $CAMPOS_FIM  = ['prorrog1_fim_pror', 'data_fim_pror', 'dt_fim_pror', 'data_fim', 'dt_fim', 'fim_acom'];

    $group = [];
    foreach ($linhas as $L) {
        $id = (int)($L['id_internacao'] ?? 0);
        if (!$id) continue;

        if (!isset($group[$id])) {
            $group[$id] = [
                'base' => [
                    'id_internacao'   => $id,
                    'nome_hosp'       => $L['nome_hosp'] ?? '',
                    'nome_pac'        => $L['nome_pac'] ?? '',
                    'data_intern_int' => $L['data_intern_int'] ?? null,
                ],
                'faixas' => []
            ];
        }

        $acom = trim((string) firstField($L, $CAMPOS_ACOM, ''));
        $ini  = firstField($L, $CAMPOS_INI, null);
        $fim  = firstField($L, $CAMPOS_FIM, null);
        if (!$ini) continue;

        $group[$id]['faixas'][] = [
            'acomodacao' => ($acom !== '' ? $acom : '—'),
            'dt_ini'     => $ini,
            'dt_fim'     => $fim
        ];
    }

    // ordenar e consolidar
    foreach ($group as $id => $pack) {
        $faixas = $pack['faixas'];
        usort($faixas, fn($a, $b) => strcmp((string)$a['dt_ini'], (string)$b['dt_ini']));

        $ciclos = [];
        foreach ($faixas as $fx) {
            $dias = daysPror($fx['dt_ini'], $fx['dt_fim']);
            $n = count($ciclos);

            if ($n > 0 && strcasecmp($ciclos[$n - 1]['acomodacao'], $fx['acomodacao']) === 0) {
                $ciclos[$n - 1]['dias'] += $dias;

                // atualiza fim do consolidado
                if ($ciclos[$n - 1]['fim'] !== null && $fx['dt_fim'] === null) {
                    $ciclos[$n - 1]['fim'] = null; // em aberto
                } elseif ($ciclos[$n - 1]['fim'] !== null && $fx['dt_fim'] !== null) {
                    $ciclos[$n - 1]['fim'] = max($ciclos[$n - 1]['fim'], $fx['dt_fim']);
                }
            } else {
                $ciclos[] = [
                    'acomodacao' => $fx['acomodacao'],
                    'dias'       => $dias,
                    'ini'        => $fx['dt_ini'],
                    'fim'        => $fx['dt_fim'], // pode ser null
                ];
            }
        }

        $group[$id]['ciclos'] = $ciclos;
        unset($group[$id]['faixas']);
    }
    return $group;
}

// ---------------------
// Filtros
// ---------------------
$pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$pesquisa_pac  = filter_input(INPUT_GET, 'pesquisa_pac',   FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$id_internacao = (int)(filter_input(INPUT_GET, 'id_internacao') ?: 0);
$limite        = (int)(filter_input(INPUT_GET, 'limite') ?: 10);
$ordenarParam  = filter_input(INPUT_GET, 'ordenar') ?: 'id_internacao';
$paginaAtual   = (int)(filter_input(INPUT_GET, 'pag') ?: 1);

// ---------------------
// WHERE (aliases: ho, pa, i, p)
// ---------------------
$cond = [];
$whereParams = [];

$idUsuarioSessao = (int)($_SESSION['id_usuario'] ?? 0);
$cargoSessaoNorm = norm((string)($_SESSION['cargo'] ?? ''));
$isMedicoSessao = (strpos($cargoSessaoNorm, 'medico') === 0) || (strpos($cargoSessaoNorm, 'med') === 0);

if ($isMedicoSessao && $idUsuarioSessao > 0) {
    $cond[] = 'i.fk_hospital_int IN (SELECT hu.fk_hospital_user FROM tb_hospitalUser hu WHERE hu.fk_usuario_hosp = :id_usuario_sessao)';
    $whereParams[':id_usuario_sessao'] = $idUsuarioSessao;
}

if ($pesquisa_nome !== '') {
    $cond[] = 'ho.nome_hosp LIKE :pesquisa_nome';
    $whereParams[':pesquisa_nome'] = '%' . $pesquisa_nome . '%';
}
if ($pesquisa_pac  !== '') {
    $cond[] = 'pa.nome_pac LIKE :pesquisa_pac';
    $whereParams[':pesquisa_pac'] = '%' . $pesquisa_pac . '%';
}
if ($id_internacao > 0) {
    $cond[] = 'p.fk_internacao_pror = :id_internacao';
    $whereParams[':id_internacao'] = (int)$id_internacao;
}

$where = implode(' AND ', $cond);

// ---------------------
// Ordenação -> aliases reais
// ---------------------
$ordenarPermitidos = ['id_internacao', 'nome_pac', 'nome_hosp', 'data_intern_int', 'prorrog1_ini_pror'];
$ordenarParam = preg_replace('/^\s*order\s+by\s+/i', '', $ordenarParam);
list($col, $dir) = array_pad(preg_split('/\s+/', trim($ordenarParam), 2), 2, '');
$col = in_array($col, $ordenarPermitidos, true) ? $col : 'id_internacao';
$dir = strtoupper($dir);
$dir = in_array($dir, ['ASC', 'DESC'], true) ? $dir : 'ASC';

$map = [
    'id_internacao'     => 'i.id_internacao',
    'nome_pac'          => 'pa.nome_pac',
    'nome_hosp'         => 'ho.nome_hosp',
    'data_intern_int'   => 'i.data_intern_int',
    'prorrog1_ini_pror' => 'p.prorrog1_ini_pror',
];
$ordenarSql = ($map[$col] ?? 'i.id_internacao') . ' ' . $dir;

// ---------------------
// BUSCA via DAO
// ---------------------
$linhas = $internacaoDAO->selectAllProrrogacao($where, $ordenarSql, null, $whereParams);

// Converte e consolida
$porInternacao = consolidarCiclosPorInternacao($linhas);

// Paginação (por internação)
$itens = array_values($porInternacao);
$total = count($itens);
$limite = max(1, $limite);
$totalPaginas = max(1, (int)ceil($total / $limite));
$paginaAtual = max(1, min($paginaAtual, $totalPaginas));
$offset = ($paginaAtual - 1) * $limite;
$paginaItens = array_slice($itens, $offset, $limite);

$window = 3;
$paginaInicio = max(1, $paginaAtual - $window);
$paginaFim = min($totalPaginas, $paginaAtual + $window);
if ($paginaFim - $paginaInicio < 2 * $window) {
    if ($paginaInicio == 1) {
        $paginaFim = min($totalPaginas, $paginaInicio + 2 * $window);
    } elseif ($paginaFim == $totalPaginas) {
        $paginaInicio = max(1, $paginaFim - 2 * $window);
    }
}
$paginasSimples = [];
for ($i = $paginaInicio; $i <= $paginaFim; $i++) {
    $paginasSimples[] = [
        'pg' => $i,
        'current' => ($i === $paginaAtual)
    ];
}

$temPagAnterior = $paginaAtual > 1;
$temPagProxima = $paginaAtual < $totalPaginas;

// URL base
$self      = basename($_SERVER['PHP_SELF']);
$actionUrl = $self;
$urlParams = http_build_query([
    'pesquisa_nome' => $pesquisa_nome,
    'pesquisa_pac'  => $pesquisa_pac,
    'id_internacao' => $id_internacao,
    'limite'        => $limite,
    'ordenar'       => "$col $dir",
]);
$urlBase = $self . '?' . $urlParams;
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <title>Internações - Rota do Paciente</title>
    <style>
        .ciclos-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            /* CENTRALIZADO */
            flex-wrap: wrap;
            gap: .35rem;
        }

        .ciclo-chip {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .25rem .5rem;
            border-radius: 999px;
            background: #f1f3f5;
            border: 1px solid #e3e6e8;
            font-size: 12px;
            font-weight: 600;
        }

        .ciclo-chip .dias {
            font-weight: 700;
            padding: .1rem .35rem;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #dcdcdc;
        }

        .ciclo-chip .periodo {
            font-weight: 500;
            opacity: .8;
            margin-left: .25rem;
        }

        .ciclo-sep {
            margin: 0 .15rem;
            opacity: .6;
        }

        .total-badge {
            font-size: 12px;
            background: #eef9f0;
            border: 1px solid #d5f0da;
            color: #1f7a2e;
            border-radius: 6px;
            padding: .15rem .4rem;
            font-weight: 700;
        }

        th.th-tight {
            white-space: nowrap;
        }

        /* cores por acomodação */
        .ciclo-chip.acom-uti {
            background: #fde2e1;
            border-color: #f8b4b4;
            color: #a61b1b;
        }

        .ciclo-chip.acom-apto {
            background: #e3f2fd;
            border-color: #90caf9;
            color: #0d47a1;
        }

        .ciclo-chip.acom-semi {
            background: #fff3e0;
            border-color: #ffcc80;
            color: #e65100;
        }

        .ciclo-chip.acom-enfermaria {
            background: #e8f5e9;
            border-color: #a5d6a7;
            color: #1b5e20;
        }

        .ciclo-chip.acom-ps {
            background: #ede7f6;
            border-color: #b39ddb;
            color: #4527a0;
        }

        .ciclo-chip.acom-isol {
            background: #fffde7;
            border-color: #fff59d;
            color: #827717;
        }

        .ciclo-chip.acom-cc {
            background: #f3e5f5;
            border-color: #ce93d8;
            color: #6a1b9a;
        }

        .ciclo-chip.acom-outros {
            background: #eceff1;
            border-color: #cfd8dc;
            color: #37474f;
        }
    </style>
</head>

<body>

    <div class="container-fluid" style="margin-top:12px;">
        <h4 class="page-title m-0 mb-3 text-center" style="color:#3A3A3A;">Internações - Rota do Paciente</h4>

        <form action="<?= e($actionUrl) ?>" id="filtros-form" method="GET">
            <div class="filters-row pb-1">

                <div class="filter-flex-2">
                    <label class="form-label mb-0 small text-muted">Hospital</label>
                    <input class="form-control form-control-sm w-100" type="text" name="pesquisa_nome"
                        placeholder="Nome do Hospital" value="<?= e($pesquisa_nome) ?>">
                </div>

                <div class="filter-flex-2">
                    <label class="form-label mb-0 small text-muted">Paciente</label>
                    <input class="form-control form-control-sm w-100" type="text" name="pesquisa_pac"
                        placeholder="Nome do Paciente" value="<?= e($pesquisa_pac) ?>">
                </div>

                <div class="filter-flex-1">
                    <label class="form-label mb-0 small text-muted">ID Internação</label>
                    <input class="form-control form-control-sm w-100" type="number" name="id_internacao"
                        placeholder="Opcional" value="<?= $id_internacao ?: '' ?>">
                </div>

                <div class="filter-flex-1">
                    <label class="form-label mb-0 small text-muted">Registros</label>
                    <select class="form-select form-select-sm w-100" name="limite">
                        <option value="10" <?= $limite == 10 ? 'selected' : '' ?>>10 por pág.</option>
                        <option value="20" <?= $limite == 20 ? 'selected' : '' ?>>20 por pág.</option>
                        <option value="50" <?= $limite == 50 ? 'selected' : '' ?>>50 por pág.</option>
                    </select>
                </div>

                <div class="filter-flex-1">
                    <label class="form-label mb-0 small text-muted">Ordenar</label>
                    <select class="form-select form-select-sm w-100" name="ordenar">
                        <option value="id_internacao ASC"
                            <?= $col === 'id_internacao'   && $dir === 'ASC'  ? 'selected' : '' ?>>Internação (↑)</option>
                        <option value="id_internacao DESC"
                            <?= $col === 'id_internacao'   && $dir === 'DESC' ? 'selected' : '' ?>>Internação (↓)</option>
                        <option value="nome_pac ASC" <?= $col === 'nome_pac'        && $dir === 'ASC'  ? 'selected' : '' ?>>
                            Paciente (A→Z)</option>
                        <option value="nome_pac DESC"
                            <?= $col === 'nome_pac'        && $dir === 'DESC' ? 'selected' : '' ?>>Paciente (Z→A)</option>
                        <option value="nome_hosp ASC"
                            <?= $col === 'nome_hosp'       && $dir === 'ASC'  ? 'selected' : '' ?>>Hospital (A→Z)</option>
                        <option value="nome_hosp DESC"
                            <?= $col === 'nome_hosp'       && $dir === 'DESC' ? 'selected' : '' ?>>Hospital (Z→A)</option>
                        <option value="data_intern_int ASC"
                            <?= $col === 'data_intern_int' && $dir === 'ASC'  ? 'selected' : '' ?>>Data Intern. (↑)</option>
                        <option value="data_intern_int DESC"
                            <?= $col === 'data_intern_int' && $dir === 'DESC' ? 'selected' : '' ?>>Data Intern. (↓)</option>
                        <option value="prorrog1_ini_pror ASC"
                            <?= $col === 'prorrog1_ini_pror' && $dir === 'ASC'  ? 'selected' : '' ?>>Início Prorr. (↑)
                        </option>
                        <option value="prorrog1_ini_pror DESC"
                            <?= $col === 'prorrog1_ini_pror' && $dir === 'DESC' ? 'selected' : '' ?>>Início Prorr. (↓)
                        </option>
                    </select>
                </div>

                <div class="filter-btn">
                    <label class="form-label mb-0 small text-muted">&nbsp;</label>
                    <button type="submit"
                        class="btn btn-primary btn-compact d-inline-flex align-items-center justify-content-center"
                        style="background-color:#5e2363;border-color:#5e2363;">
                        <span class="material-icons" style="font-size:14px;line-height:1;">search</span>
                    </button>
                </div>

            </div>
        </form>

        <div id="table-container" class="mt-3">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th class="th-tight text-center">Reg</th>
                            <th class="th-tight">Hospital</th>
                            <th class="th-tight">Paciente</th>
                            <th class="th-tight text-center">Data Inter.</th>
                            <th style="width:50%" class="text-center">Ciclo de Acomodação (ordem, dias e período)</th>
                            <th class="text-center th-tight">Total Dias</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($paginaItens)) : ?>
                            <?php foreach ($paginaItens as $item):
                                $idInt   = (int)($item['base']['id_internacao'] ?? 0);
                                $hosp    = $item['base']['nome_hosp'] ?? '';
                                $pac     = $item['base']['nome_pac'] ?? '';
                                $dInter  = $item['base']['data_intern_int'] ?? null;
                                $ciclos  = $item['ciclos'] ?? [];

                                $totalDias = 0;
                                foreach ($ciclos as $c) $totalDias += (int)$c['dias'];
                            ?>
                                <tr>
                                    <td class="text-center"><b><?= $idInt ?></b></td>
                                    <td><?= e($hosp) ?></td>
                                    <td><?= e($pac) ?></td>
                                    <td class="text-center"><?= $dInter ? date('d/m/Y', strtotime($dInter)) : '' ?></td>
                                    <td class="text-center">
                                        <?php
                                        if (empty($ciclos)) {
                                            echo '<span class="text-muted">Sem prorrogações</span>';
                                        } else {
                                            echo '<div class="ciclos-wrap">';
                                            $last = count($ciclos) - 1;
                                            foreach ($ciclos as $idx => $c) {
                                                $lab  = e($c['acomodacao']);
                                                $dias = (int)$c['dias'];
                                                $cls  = acomodacaoClass($lab);

                                                // período sem ano: d/m
                                                $iniFmt = '';
                                                $fimFmt = '';
                                                if (!empty($c['ini'])) $iniFmt = date('d/m', strtotime($c['ini']));
                                                if (array_key_exists('fim', $c)) {
                                                    $fimFmt = $c['fim'] ? date('d/m', strtotime($c['fim'])) : '…';
                                                }
                                                $periodo = ($iniFmt || $fimFmt) ? " ({$iniFmt}–{$fimFmt})" : '';

                                                echo '<div class="ciclo-chip ' . $cls . '" title="' . $lab . '">' .
                                                    $lab .
                                                    '<span class="dias">' . $dias . 'd</span>' .
                                                    '<span class="periodo">' . e($periodo) . '</span>' .
                                                    '</div>';
                                                if ($idx !== $last) echo '<div class="ciclo-sep" aria-hidden="true">➜</div>';
                                            }
                                            echo '</div>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center"><span class="total-badge"><?= (int)$totalDias ?>d</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center p-4">Nenhum registro encontrado com os filtros aplicados.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação CENTRAL -->
            <div class="d-flex justify-content-center align-items-center mt-3 position-relative">
                <?php if ($totalPaginas > 1) : ?>
                    <nav aria-label="Navegação das páginas">
                        <ul class="pagination m-0">
                            <li class="page-item <?= !$temPagAnterior ? 'disabled' : '' ?>">
                                <a class="page-link ajax-link" href="<?= $urlBase ?>&pag=1" aria-label="Primeira">&laquo;</a>
                            </li>
                            <li class="page-item <?= !$temPagAnterior ? 'disabled' : '' ?>">
                                <a class="page-link ajax-link" href="<?= $urlBase ?>&pag=<?= max(1, $paginaAtual - 1) ?>"
                                    aria-label="Anterior">&lsaquo;</a>
                            </li>

                            <?php foreach ($paginasSimples as $p): ?>
                                <li class="page-item <?= $p['current'] ? 'active' : '' ?>">
                                    <a class="page-link ajax-link" href="<?= $urlBase ?>&pag=<?= $p['pg'] ?>">
                                        <?= $p['pg'] ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>

                            <li class="page-item <?= !$temPagProxima ? 'disabled' : '' ?>">
                                <a class="page-link ajax-link"
                                    href="<?= $urlBase ?>&pag=<?= min($totalPaginas, $paginaAtual + 1) ?>"
                                    aria-label="Próxima">&rsaquo;</a>
                            </li>
                            <li class="page-item <?= !$temPagProxima ? 'disabled' : '' ?>">
                                <a class="page-link ajax-link" href="<?= $urlBase ?>&pag=<?= $totalPaginas ?>"
                                    aria-label="Última">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JS (usa jQuery/Bootstrap já carregados no header global) -->
    <script>
        (function() {
            const $wrap = $('#table-container');

            function loadContent(url) {
                $wrap.html('<div class="text-center p-5">Carregando...</div>');
                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        const $resp = $('<div>').html(response);
                        const $chunk = $resp.find('#table-container');
                        if ($chunk.length) {
                            $wrap.html($chunk.html());
                        } else {
                            $wrap.html(
                                '<div class="p-3 border rounded text-danger"><b>Erro:</b> #table-container não encontrado na resposta.</div>'
                            );
                        }
                        history.pushState(null, '', url);
                    },
                    error: function(xhr) {
                        $wrap.html(
                            '<div class="text-center text-danger p-4"><b>Erro na requisição AJAX.</b> Status: ' +
                            xhr.status + ' ' + xhr.statusText + '</div>');
                    }
                });
            }

            // Paginação por AJAX
            $(document).on('click', 'a.ajax-link', function(e) {
                e.preventDefault();
                loadContent($(this).attr('href'));
            });

            // Submissão dos filtros por AJAX
            $(document).on('submit', '#filtros-form', function(e) {
                e.preventDefault();
                const url = $(this).attr('action') + '?' + $(this).serialize();
                loadContent(url);
            });

            // Navegação do browser (voltar/avançar mantém recorte AJAX)
            window.addEventListener('popstate', function() {
                loadContent(location.href);
            });
        })();
    </script>

</body>

</html>

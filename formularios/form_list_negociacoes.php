<?php
ob_start();

require_once("templates/header.php");
require_once("models/message.php");

include_once("models/negociacao.php");
include_once("dao/negociacaoDao.php");
include_once("models/pagination.php");

$negociacaoDao = new negociacaoDAO($conn, $BASE_URL);

$pesquisa_hosp  = trim((string)(filter_input(INPUT_GET, 'pesquisa_hosp',  FILTER_SANITIZE_SPECIAL_CHARS) ?: ''));
$pesquisa_pac   = trim((string)(filter_input(INPUT_GET, 'pesquisa_pac',   FILTER_SANITIZE_SPECIAL_CHARS) ?: ''));
$tipo_neg       = trim((string)(filter_input(INPUT_GET, 'tipo_neg',       FILTER_SANITIZE_SPECIAL_CHARS) ?: ''));
$data_ini       = trim((string)(filter_input(INPUT_GET, 'data_ini',       FILTER_SANITIZE_SPECIAL_CHARS) ?: ''));
$data_fim       = trim((string)(filter_input(INPUT_GET, 'data_fim',       FILTER_SANITIZE_SPECIAL_CHARS) ?: ''));
$saving_min     = trim((string)(filter_input(INPUT_GET, 'saving_min',     FILTER_SANITIZE_SPECIAL_CHARS) ?: ''));
$limite         = filter_input(INPUT_GET, 'limite', FILTER_VALIDATE_INT) ?: 20;
$ordenar        = filter_input(INPUT_GET, 'ordenar', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'data_recente';
$pagAtual       = filter_input(INPUT_GET, 'pag', FILTER_VALIDATE_INT) ?: 1;

$orderOptions = [
    'data_recente' => 'ng.data_inicio_neg DESC',
    'data_antiga'  => 'ng.data_inicio_neg ASC',
    'saving_desc'  => 'ng.saving DESC',
    'saving_asc'   => 'ng.saving ASC',
];
$ordenar = array_key_exists($ordenar, $orderOptions) ? $ordenar : 'data_recente';

if ($data_ini && !$data_fim) {
    $data_fim = $data_ini;
}

$condicoes = ['(ng.deletado_neg IS NULL OR ng.deletado_neg != :deletado_neg)'];
$whereParams = [':deletado_neg' => 's'];

if ($pesquisa_hosp !== '') {
    $condicoes[] = 'ho.nome_hosp LIKE :pesquisa_hosp';
    $whereParams[':pesquisa_hosp'] = '%' . $pesquisa_hosp . '%';
}
if ($pesquisa_pac !== '') {
    $condicoes[] = 'pa.nome_pac LIKE :pesquisa_pac';
    $whereParams[':pesquisa_pac'] = '%' . $pesquisa_pac . '%';
}
if ($tipo_neg !== '') {
    $condicoes[] = 'ng.tipo_negociacao = :tipo_neg';
    $whereParams[':tipo_neg'] = $tipo_neg;
}
if ($data_ini !== '') {
    $ini = $data_ini;
    $fim = $data_fim ?: $data_ini;
    $condicoes[] = 'DATE(ng.data_inicio_neg) BETWEEN :data_ini AND :data_fim';
    $whereParams[':data_ini'] = $ini;
    $whereParams[':data_fim'] = $fim;
}
if ($saving_min !== '' && is_numeric($saving_min)) {
    $condicoes[] = 'ng.saving >= :saving_min';
    $whereParams[':saving_min'] = (float)$saving_min;
}

$where = implode(' AND ', $condicoes);

$totalItens = $negociacaoDao->countNegociacoesDetalhes($where, $whereParams);
$totalPages = max(1, (int)ceil($totalItens / max(1, $limite)));
$pagAtual = min(max(1, $pagAtual), $totalPages);
$paginationObj = new pagination($totalItens, $pagAtual, $limite);
$listaNegociacoes = $negociacaoDao->selectNegociacoesDetalhes($where, $orderOptions[$ordenar], $paginationObj->getLimit(), $whereParams);

$paginationParams = [
    'pesquisa_hosp' => $pesquisa_hosp,
    'pesquisa_pac'  => $pesquisa_pac,
    'tipo_neg'      => $tipo_neg,
    'data_ini'      => $data_ini,
    'data_fim'      => $data_fim,
    'saving_min'    => $saving_min,
    'limite'        => $limite,
    'ordenar'       => $ordenar
];

$buildLink = function ($pagina) use ($paginationParams, $BASE_URL) {
    $params = array_filter($paginationParams, function ($value) {
        return $value !== '' && $value !== null && $value !== false;
    });
    $path = rtrim($BASE_URL, '/') . '/negociacoes/pagina/' . max(1, (int)$pagina);
    $query = http_build_query($params);
    return $path . ($query ? '?' . $query : '');
};

$paginationWindowSize = 5;
$paginationBlock = (int)floor(($pagAtual - 1) / $paginationWindowSize);
$firstPageInWindow = ($paginationBlock * $paginationWindowSize) + 1;
$lastPageInWindow = min($totalPages, $firstPageInWindow + $paginationWindowSize - 1);

$exportUrl = $BASE_URL . 'exportar_excel_negociacoes.php?' . http_build_query($paginationParams);

$tiposDisponiveis = [
    "TROCA UTI/APTO",
    "TROCA UTI/SEMI",
    "TROCA SEMI/APTO",
    "VESPERA",
    "GLOSA UTI",
    "GLOSA APTO",
    "GLOSA SEMI",
    "1/2 DIARIA APTO",
    "TARDIA APTO",
    "TARDIA UTI",
    "DIARIA ADM"
];
sort($tiposDisponiveis);
?>

<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/table_style.css?v=' . filemtime(__DIR__ . '/../css/table_style.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/listagem_padrao.css?v=' . filemtime(__DIR__ . '/../css/listagem_padrao.css'), ENT_QUOTES, 'UTF-8') ?>">

<style>
    .negociacoes-filter-row {
        flex-wrap: nowrap !important;
        column-gap: 6px;
    }

    #main-container.listagem-page {
        margin-top: 2px !important;
        padding-top: 0;
    }

    .negociacoes-hero {
        min-height: 54px;
        margin-bottom: 8px;
        padding: 7px 14px;
        border-radius: 10px;
    }

    .negociacoes-hero .listagem-title {
        font-size: 1rem;
        line-height: 1.1;
    }

    .negociacoes-list-card {
        border-radius: 9px;
        box-shadow: 0 2px 9px rgba(34, 45, 60, 0.16) !important;
    }

    .negociacoes-list-card .table-filters {
        margin-left: 0;
        padding: 8px 10px 7px;
    }

    .negociacoes-filter-row > [class*="neg-filter-"] {
        padding-left: 0;
        padding-right: 0;
    }

    .neg-filter-hospital,
    .neg-filter-paciente {
        flex: 1.2 1 0;
        min-width: 0;
    }

    .neg-filter-tipo {
        flex: .95 1 0;
        min-width: 0;
    }

    .neg-filter-saving,
    .neg-filter-data {
        flex: .72 1 0;
        min-width: 0;
    }

    .neg-filter-limite {
        flex: 0 0 92px;
        max-width: 92px;
    }

    .neg-filter-ordenar {
        flex: 0 0 145px;
        max-width: 145px;
    }

    .neg-filter-actions {
        flex: 0 0 78px;
        max-width: 78px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .neg-filter-actions .btn {
        flex: 0 0 34px;
    }

    .negociacoes-export-btn {
        min-height: 30px;
        border-color: rgba(255, 255, 255, .44);
        background: rgba(255, 255, 255, .18);
        box-shadow: none;
        font-size: .66rem;
    }

    .negociacoes-export-btn:hover,
    .negociacoes-export-btn:focus {
        background: rgba(255, 255, 255, .26);
        border-color: rgba(255, 255, 255, .58);
        color: #fff !important;
    }

    .negociacoes-table th,
    .negociacoes-table td {
        white-space: nowrap;
    }

    .negociacoes-table thead th {
        font-size: .52rem !important;
        padding: 5px 7px !important;
    }

    .negociacoes-table tbody td {
        font-size: .62rem !important;
        padding: 5px 7px !important;
    }

    .negociacoes-table .col-paciente {
        min-width: 190px;
        white-space: normal;
    }

    @media (max-width: 1200px) {
        .negociacoes-filter-row {
            flex-wrap: wrap !important;
        }

        .neg-filter-hospital,
        .neg-filter-paciente,
        .neg-filter-tipo,
        .neg-filter-saving,
        .neg-filter-data,
        .neg-filter-limite,
        .neg-filter-ordenar,
        .neg-filter-actions {
            flex: 1 1 180px;
            max-width: none;
        }
    }
</style>

<div class="container-fluid form_container listagem-page" id="main-container">
    <div class="listagem-hero listagem-hero--module listagem-hero--gestao negociacoes-hero">
        <div class="listagem-hero__copy">
            <p class="listagem-kicker">Gestão</p>
            <h1 class="listagem-title">Negociações realizadas</h1>
        </div>
        <div class="listagem-hero__actions">
            <a href="<?= $exportUrl ?>" class="btn listagem-btn-top negociacoes-export-btn">
                <i class="fa-solid fa-file-excel listagem-btn-top__icon" aria-hidden="true"></i>
                Exportar Excel
            </a>
        </div>
    </div>

    <div class="complete-table negociacoes-list-card">
        <div class="table-filters">
            <form method="GET">
                <div class="row negociacoes-filter-row">
                    <div class="neg-filter-hospital">
                        <input type="text" name="pesquisa_hosp" class="form-control form-control-sm"
                            value="<?= htmlspecialchars($pesquisa_hosp, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nome do hospital">
                    </div>
                    <div class="neg-filter-paciente">
                        <input type="text" name="pesquisa_pac" class="form-control form-control-sm"
                            value="<?= htmlspecialchars($pesquisa_pac, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nome do paciente">
                    </div>
                    <div class="neg-filter-tipo">
                        <select name="tipo_neg" class="form-control form-control-sm">
                            <option value="">Todos</option>
                            <?php foreach ($tiposDisponiveis as $tipo): ?>
                            <option value="<?= htmlspecialchars($tipo) ?>" <?= $tipo_neg === $tipo ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tipo) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="neg-filter-saving">
                        <input type="number" step="0.01" name="saving_min" class="form-control form-control-sm"
                            value="<?= htmlspecialchars($saving_min, ENT_QUOTES, 'UTF-8') ?>" placeholder="Saving mín.">
                    </div>
                    <div class="neg-filter-data">
                        <input type="date" name="data_ini" class="form-control form-control-sm"
                            value="<?= htmlspecialchars($data_ini, ENT_QUOTES, 'UTF-8') ?>" title="Data inicial">
                    </div>
                    <div class="neg-filter-data">
                        <input type="date" name="data_fim" class="form-control form-control-sm"
                            value="<?= htmlspecialchars($data_fim, ENT_QUOTES, 'UTF-8') ?>" title="Data final">
                    </div>
                    <div class="neg-filter-limite">
                        <select name="limite" class="form-control form-control-sm">
                            <?php foreach ([10, 20, 50] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $limite == $opt ? 'selected' : '' ?>><?= $opt ?> por pág.</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="neg-filter-ordenar">
                        <select name="ordenar" class="form-control form-control-sm">
                            <option value="data_recente" <?= $ordenar === 'data_recente' ? 'selected' : '' ?>>Data recente</option>
                            <option value="data_antiga" <?= $ordenar === 'data_antiga' ? 'selected' : '' ?>>Data antiga</option>
                            <option value="saving_desc" <?= $ordenar === 'saving_desc' ? 'selected' : '' ?>>Saving maior</option>
                            <option value="saving_asc" <?= $ordenar === 'saving_asc' ? 'selected' : '' ?>>Saving menor</option>
                        </select>
                    </div>
                    <div class="neg-filter-actions">
                        <button type="submit" class="btn btn-primary btn-filtro-buscar btn-filtro-limpar-icon" title="Pesquisar" aria-label="Pesquisar">
                            <span class="material-icons" aria-hidden="true">search</span>
                        </button>
                        <a href="<?= $BASE_URL ?>negociacoes" class="btn btn-light btn-sm btn-filtro-limpar btn-filtro-limpar-icon" title="Limpar filtros" aria-label="Limpar filtros">
                            <i class="bi bi-trash3" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div id="table-content">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover table-condensed negociacoes-table mb-0">
                    <thead>
                        <tr>
                            <th>Internação</th>
                            <th>Senha</th>
                            <th>Matrícula</th>
                            <th>Hospital</th>
                            <th>Paciente</th>
                            <th>Tipo</th>
                            <th>Troca</th>
                            <th>Qtd.</th>
                            <th>Saving</th>
                            <th>Data início</th>
                            <th>Data fim</th>
                            <th>Auditor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$listaNegociacoes): ?>
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">Nenhuma negociação encontrada.</td>
                        </tr>
                        <?php else: foreach ($listaNegociacoes as $neg):
                            $trocaDe = trim((string)($neg['troca_de'] ?? ''));
                            $trocaPara = trim((string)($neg['troca_para'] ?? ''));
                            $trocaLabel = ($trocaDe !== '' && $trocaPara !== '')
                                ? $trocaDe . ' - ' . $trocaPara
                                : ($trocaDe ?: ($trocaPara ?: '-'));
                        ?>
                        <tr>
                            <td><?= (int)($neg['fk_id_int'] ?? 0) ?></td>
                            <td><?= htmlspecialchars($neg['senha_int'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($neg['matricula_pac'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($neg['nome_hosp'] ?? '-') ?></td>
                            <td class="col-paciente"><?= htmlspecialchars($neg['nome_pac'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($neg['tipo_negociacao'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($trocaLabel, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($neg['qtd'] ?? '0')) ?></td>
                            <td>R$ <?= number_format((float)($neg['saving'] ?? 0), 2, ',', '.') ?></td>
                            <td><?= $neg['data_inicio_neg'] ? date('d/m/Y', strtotime($neg['data_inicio_neg'])) : '—' ?></td>
                            <td><?= $neg['data_fim_neg'] ? date('d/m/Y', strtotime($neg['data_fim_neg'])) : '—' ?></td>
                            <td><?= htmlspecialchars($neg['nome_usuario'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="listagem-footer-row">
                <div class="listagem-pagination-slot">
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Paginação">
                        <ul class="pagination mb-0">
                            <?php if ($firstPageInWindow > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= htmlspecialchars($buildLink(1), ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa-solid fa-angles-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="<?= htmlspecialchars($buildLink(max(1, $pagAtual - 1)), ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa-solid fa-angle-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = $firstPageInWindow; $i <= $lastPageInWindow; $i++): ?>
                            <li class="page-item <?= $i == $pagAtual ? 'active' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars($buildLink($i), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($lastPageInWindow < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= htmlspecialchars($buildLink(min($totalPages, $pagAtual + 1)), ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa-solid fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="<?= htmlspecialchars($buildLink($totalPages), ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa-solid fa-angles-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
                <div class="listagem-total">
                    <p>Total: <?= $totalItens ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

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
$ordenar        = filter_input(INPUT_GET, 'ordenar', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'ng.data_inicio_neg DESC';
$pagAtual       = filter_input(INPUT_GET, 'pag', FILTER_VALIDATE_INT) ?: 1;

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
$paginationObj = new pagination($totalItens, $pagAtual, $limite);
$listaNegociacoes = $negociacaoDao->selectNegociacoesDetalhes($where, $ordenar, $paginationObj->getLimit(), $whereParams);
$totalPages = max(1, (int)ceil($totalItens / $limite));

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

<style>
    .negociacoes-table thead th {
        padding: 0.9rem 1rem;
        font-size: 0.85rem;
    }

    .negociacoes-table tbody td {
        padding: 0.85rem 1rem;
        font-size: 0.85rem;
        vertical-align: middle;
    }
</style>

<div class="container-fluid form_container" id="main-container" style="margin-top:-20px;">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="page-title">Negociações realizadas</h4>
        <a href="<?= $exportUrl ?>" class="btn btn-success btn-sm text-white">
            <i class="fa-solid fa-file-excel me-2"></i>Exportar Excel
        </a>
    </div>
    <hr>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form class="row g-2" method="GET">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Hospital</label>
                    <input type="text" name="pesquisa_hosp" class="form-control form-control-sm"
                        value="<?= htmlspecialchars($pesquisa_hosp) ?>" placeholder="Nome do hospital">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Paciente</label>
                    <input type="text" name="pesquisa_pac" class="form-control form-control-sm"
                        value="<?= htmlspecialchars($pesquisa_pac) ?>" placeholder="Nome do paciente">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Tipo</label>
                    <select name="tipo_neg" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($tiposDisponiveis as $tipo): ?>
                        <option value="<?= htmlspecialchars($tipo) ?>" <?= $tipo_neg === $tipo ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tipo) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Saving mínimo</label>
                    <input type="number" step="0.01" name="saving_min" class="form-control form-control-sm"
                        value="<?= htmlspecialchars($saving_min) ?>" placeholder="0,00">
                </div>
                <div class="col-md-1">
                    <label class="form-label small text-muted">Registros</label>
                    <select name="limite" class="form-select form-select-sm">
                        <?php foreach ([10, 20, 50] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $limite == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small text-muted">Ordenar</label>
                    <select name="ordenar" class="form-select form-select-sm">
                        <option value="ng.data_inicio_neg DESC" <?= $ordenar === 'ng.data_inicio_neg DESC' ? 'selected' : '' ?>>Data (Recente)</option>
                        <option value="ng.data_inicio_neg ASC" <?= $ordenar === 'ng.data_inicio_neg ASC' ? 'selected' : '' ?>>Data (Antiga)</option>
                        <option value="ng.saving DESC" <?= $ordenar === 'ng.saving DESC' ? 'selected' : '' ?>>Saving ↓</option>
                        <option value="ng.saving ASC" <?= $ordenar === 'ng.saving ASC' ? 'selected' : '' ?>>Saving ↑</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Data inicial</label>
                    <input type="date" name="data_ini" class="form-control form-control-sm"
                        value="<?= htmlspecialchars($data_ini) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Data final</label>
                    <input type="date" name="data_fim" class="form-control form-control-sm"
                        value="<?= htmlspecialchars($data_fim) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button class="btn btn-sm btn-primary" style="background:#5e2363;border:none;">Filtrar</button>
                    <a href="<?= $BASE_URL ?>negociacoes" class="btn btn-sm btn-outline-secondary btn-filtro-limpar"><i class="bi bi-trash3 me-1" aria-hidden="true"></i>Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover negociacoes-table mb-0">
                    <thead style="background:#4c1652;color:#fff;">
                        <tr>
                            <th>Internação</th>
                            <th>Senha</th>
                            <th>Matrícula</th>
                            <th>Hospital</th>
                            <th>Paciente</th>
                            <th>Tipo</th>
                            <th>Troca de</th>
                            <th>Troca para</th>
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
                        <?php else: foreach ($listaNegociacoes as $neg): ?>
                        <tr>
                            <td><?= (int)($neg['fk_id_int'] ?? 0) ?></td>
                            <td><?= htmlspecialchars($neg['senha_int'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($neg['matricula_pac'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($neg['nome_hosp'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($neg['nome_pac'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($neg['tipo_negociacao'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($neg['troca_de'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($neg['troca_para'] ?? '-') ?></td>
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

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 p-3">
                <span class="text-muted small">Total encontrado: <?= $totalItens ?></span>
                <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $pagAtual ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $buildLink($i) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

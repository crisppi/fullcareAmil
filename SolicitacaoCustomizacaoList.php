<?php
include_once("check_logado.php");
require_once("globals.php");
require_once("templates/header.php");
require_once("dao/solicitacaoCustomizacaoDao.php");

$norm = function ($txt) {
    $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
    $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $c !== false ? $c : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
};
$isDiretoria = in_array($norm($_SESSION['cargo'] ?? ''), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || in_array($norm($_SESSION['nivel'] ?? ''), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || ((int)($_SESSION['nivel'] ?? 0) === -1);

if (!$isDiretoria) {
    http_response_code(403);
    die('Acesso negado. Requer cargo/nível: Diretoria.');
}

$dao = new SolicitacaoCustomizacaoDAO($conn, $BASE_URL);

$busca = trim((string)filter_input(INPUT_GET, 'q'));
$statusFiltro = trim((string)filter_input(INPUT_GET, 'status'));
$prioridadeFiltro = trim((string)filter_input(INPUT_GET, 'prioridade'));
$dataInicio = trim((string)filter_input(INPUT_GET, 'data_inicio'));
$dataFim = trim((string)filter_input(INPUT_GET, 'data_fim'));

$rows = $dao->findAll();
if ($busca !== '') {
    $q = mb_strtolower($busca, 'UTF-8');
    $rows = array_filter($rows, function ($row) use ($q) {
        $hay = mb_strtolower(trim(($row['nome'] ?? '') . ' ' . ($row['empresa'] ?? '') . ' ' . ($row['email'] ?? '')), 'UTF-8');
        return strpos($hay, $q) !== false;
    });
}
if ($statusFiltro !== '') {
    $rows = array_filter($rows, function ($row) use ($statusFiltro) {
        return ($row['status'] ?? '') === $statusFiltro;
    });
}
if ($prioridadeFiltro !== '') {
    $rows = array_filter($rows, function ($row) use ($prioridadeFiltro) {
        return ($row['prioridade'] ?? '') === $prioridadeFiltro;
    });
}
if ($dataInicio !== '' || $dataFim !== '') {
    $rows = array_filter($rows, function ($row) use ($dataInicio, $dataFim) {
        $data = $row['data_solicitacao'] ?? '';
        if ($data === '') {
            return false;
        }
        if ($dataInicio !== '' && $data < $dataInicio) {
            return false;
        }
        if ($dataFim !== '' && $data > $dataFim) {
            return false;
        }
        return true;
    });
}
?>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/form_cad_internacao.css?v=<?= @filemtime(__DIR__ . '/css/form_cad_internacao.css') ?>">
<style>
    #main-container.internacao-page {
        margin: 2px 0 0 !important;
        padding-inline: 5px !important;
        padding-top: 0 !important;
        width: auto !important;
        max-width: 100% !important;
        overflow-x: hidden;
    }

    #main-container.internacao-page .internacao-page__hero {
        margin: 0 0 6px !important;
    }

    #main-container.internacao-page .hero-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }

    #main-container.internacao-page .hero-back-btn {
        border-radius: 999px;
        border: 1px solid #d9c3f4;
        color: #5e2363;
        padding: 7px 14px;
        text-decoration: none;
        font-weight: 600;
        font-size: .85rem;
        background: #f4ecfb;
    }

    #main-container.internacao-page .hero-back-btn:hover {
        color: #4a1b4e;
        background: #eadcf8;
    }

    #main-container.internacao-page .internacao-card__eyebrow {
        font-weight: 700 !important;
    }

    .customizacao-filters .form-control,
    .customizacao-filters .form-select {
        min-height: 42px;
        border-radius: 10px;
    }

    .customizacao-modal .modal-content {
        border-radius: 18px;
        border: 1px solid #eadff4;
        box-shadow: 0 25px 50px rgba(45, 18, 70, .18);
    }

    .customizacao-modal .modal-header {
        border-bottom: 1px solid #efe3f8;
        background: linear-gradient(180deg, #fbf7fe, #f6effb);
    }

    .customizacao-modal .modal-title {
        color: #4a1b4e;
        font-weight: 700;
    }

    .modal-section {
        padding: 18px 20px;
        border-radius: 18px;
        border: 1px solid #ece2f5;
    }

    .modal-section-title {
        font-weight: 700;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 12px;
    }

    .conex-bloco {
        background: #f5f5f9;
        border-color: #e4d9f0;
    }

    .conex-bloco .modal-section-title {
        color: #4b2a60;
    }

    .fullcare-bloco {
        background: #fbf7fd;
        border-color: #ead8f6;
    }

    .fullcare-bloco .modal-section-title {
        color: #5e2363;
    }

    .detail-item strong {
        display: block;
        font-size: .76rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #6b7280;
        margin-bottom: 4px;
    }

    .detail-item div {
        color: #2e1d3d;
        line-height: 1.45;
        word-break: break-word;
    }
</style>

<div class="internacao-page" id="main-container">
    <div class="internacao-page__hero">
        <div>
            <h1>Solicitações de customização</h1>
            <p>Acompanhe o andamento, filtre prioridades e visualize o detalhamento completo de cada solicitação.</p>
        </div>
        <div class="hero-actions">
            <a class="hero-back-btn" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/SolicitacaoCustomizacao.php', ENT_QUOTES, 'UTF-8') ?>">Nova solicitação</a>
            <span class="internacao-page__tag"><?= count($rows) ?> registro(s)</span>
        </div>
    </div>

    <div class="internacao-page__content">
        <div class="internacao-card internacao-card--fields mb-3">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Filtros</p>
                    <h2 class="internacao-card__title">Consulta da listagem</h2>
                </div>
            </div>
            <div class="internacao-card__body">
                <form class="row g-2 align-items-end customizacao-filters" method="GET" action="<?= $BASE_URL ?>SolicitacaoCustomizacaoList.php">
                    <div class="col-md-4">
                        <label class="form-label" for="q">Busca</label>
                        <input type="text" class="form-control" id="q" name="q" placeholder="Buscar por nome, empresa ou e-mail" value="<?= htmlspecialchars($busca) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos</option>
                            <?php foreach (['Aberto', 'Em análise', 'Resolvido', 'Cancelado'] as $opt) { ?>
                                <option value="<?= htmlspecialchars($opt) ?>" <?= $statusFiltro === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="prioridade">Prioridade</label>
                        <select class="form-select" id="prioridade" name="prioridade">
                            <option value="">Todas</option>
                            <?php foreach (['Urgente', 'Alta', 'Média', 'Baixa'] as $opt) { ?>
                                <option value="<?= htmlspecialchars($opt) ?>" <?= $prioridadeFiltro === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="data_inicio">Data início</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="data_fim">Data fim</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                        <a class="hero-back-btn" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/SolicitacaoCustomizacaoList.php', ENT_QUOTES, 'UTF-8') ?>">Limpar</a>
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="internacao-card internacao-card--fields mb-3">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Listagem</p>
                    <h2 class="internacao-card__title">Solicitações registradas</h2>
                </div>
            </div>
            <div class="internacao-card__body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Solicitante</th>
                                <th>Empresa</th>
                                <th>Data</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                                <th>Versão</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows) { ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Nenhuma solicitação registrada.</td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach ($rows as $row) { ?>
                                    <tr>
                                        <td><?= (int)$row['id_solicitacao'] ?></td>
                                        <td><?= htmlspecialchars($row['nome'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['empresa'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['data_solicitacao'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['prioridade'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['status'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['versao_sistema'] ?? '-') ?></td>
                                        <td class="text-end">
                                            <button
                                                class="btn btn-sm btn-outline-secondary me-2"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalSolicitacaoView"
                                                data-id="<?= (int)$row['id_solicitacao'] ?>"
                                                data-nome="<?= htmlspecialchars($row['nome'] ?? '', ENT_QUOTES) ?>"
                                                data-empresa="<?= htmlspecialchars($row['empresa'] ?? '', ENT_QUOTES) ?>"
                                                data-cargo="<?= htmlspecialchars($row['cargo'] ?? '', ENT_QUOTES) ?>"
                                                data-email="<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES) ?>"
                                                data-telefone="<?= htmlspecialchars($row['telefone'] ?? '', ENT_QUOTES) ?>"
                                                data-data="<?= htmlspecialchars($row['data_solicitacao'] ?? '', ENT_QUOTES) ?>"
                                                data-modulo-outro="<?= htmlspecialchars($row['modulo_outro'] ?? '', ENT_QUOTES) ?>"
                                                data-prioridade="<?= htmlspecialchars($row['prioridade'] ?? '', ENT_QUOTES) ?>"
                                                data-status="<?= htmlspecialchars($row['status'] ?? '', ENT_QUOTES) ?>"
                                                data-modulos="<?= htmlspecialchars($row['modulos'] ?? '', ENT_QUOTES) ?>"
                                                data-tipos="<?= htmlspecialchars($row['tipos'] ?? '', ENT_QUOTES) ?>"
                                                data-descricao="<?= htmlspecialchars($row['descricao'] ?? '', ENT_QUOTES) ?>"
                                                data-problema="<?= htmlspecialchars($row['problema_atual'] ?? '', ENT_QUOTES) ?>"
                                                data-resultado="<?= htmlspecialchars($row['resultado_esperado'] ?? '', ENT_QUOTES) ?>"
                                                data-impacto="<?= htmlspecialchars($row['impacto_nivel'] ?? '', ENT_QUOTES) ?>"
                                                data-impacto-desc="<?= htmlspecialchars($row['descricao_impacto'] ?? '', ENT_QUOTES) ?>"
                                                data-prazo-desejado="<?= htmlspecialchars($row['prazo_desejado'] ?? '', ENT_QUOTES) ?>"
                                                data-responsavel="<?= htmlspecialchars($row['responsavel'] ?? '', ENT_QUOTES) ?>"
                                                data-assinatura="<?= htmlspecialchars($row['assinatura'] ?? '', ENT_QUOTES) ?>"
                                                data-data-aprovacao="<?= htmlspecialchars($row['data_aprovacao'] ?? '', ENT_QUOTES) ?>"
                                                data-prazo-resposta="<?= htmlspecialchars($row['prazo_resposta'] ?? '', ENT_QUOTES) ?>"
                                                data-precificacao="<?= htmlspecialchars($row['precificacao'] ?? '', ENT_QUOTES) ?>"
                                                data-observacoes="<?= htmlspecialchars($row['observacoes_resposta'] ?? '', ENT_QUOTES) ?>"
                                                data-aprovacao-resposta="<?= htmlspecialchars($row['aprovacao_resposta'] ?? '', ENT_QUOTES) ?>"
                                                data-data-resposta="<?= htmlspecialchars($row['data_resposta'] ?? '', ENT_QUOTES) ?>"
                                                data-resolvido-em="<?= htmlspecialchars($row['resolvido_em'] ?? '', ENT_QUOTES) ?>"
                                                data-resolvido-por="<?= htmlspecialchars($row['resolvido_por'] ?? '', ENT_QUOTES) ?>"
                                                data-versao="<?= htmlspecialchars($row['versao_sistema'] ?? '', ENT_QUOTES) ?>"
                                                data-aprovacao-conex="<?= htmlspecialchars($row['aprovacao_conex'] ?? '', ENT_QUOTES) ?>"
                                                data-data-aprovacao-conex="<?= htmlspecialchars($row['data_aprovacao_conex'] ?? '', ENT_QUOTES) ?>"
                                                data-responsavel-aprovacao-conex="<?= htmlspecialchars($row['responsavel_aprovacao_conex'] ?? '', ENT_QUOTES) ?>"
                                            >
                                                <i class="bi bi-eye"></i> Ver
                                            </button>
                                            <a class="btn btn-sm btn-primary" href="<?= $BASE_URL ?>SolicitacaoCustomizacaoEdit.php?id=<?= (int)$row['id_solicitacao'] ?>">
                                                <i class="bi bi-pencil-square"></i> Editar
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade customizacao-modal" id="modalSolicitacaoView" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Solicitação <span id="view-id"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 modal-section conex-bloco">
                    <div class="col-12">
                        <div class="modal-section-title">Bloco Conex</div>
                    </div>
                    <div class="col-md-4 detail-item"><strong>Solicitante</strong><div id="view-nome"></div></div>
                    <div class="col-md-4 detail-item"><strong>Empresa</strong><div id="view-empresa"></div></div>
                    <div class="col-md-4 detail-item"><strong>Cargo</strong><div id="view-cargo"></div></div>
                    <div class="col-md-4 detail-item"><strong>E-mail</strong><div id="view-email"></div></div>
                    <div class="col-md-4 detail-item"><strong>Telefone</strong><div id="view-telefone"></div></div>
                    <div class="col-md-4 detail-item"><strong>Data da solicitação</strong><div id="view-data"></div></div>
                    <div class="col-md-4 detail-item"><strong>Prioridade</strong><div id="view-prioridade"></div></div>
                    <div class="col-md-4 detail-item"><strong>Status</strong><div id="view-status"></div></div>
                    <div class="col-md-4 detail-item"><strong>Módulo outro</strong><div id="view-modulo-outro"></div></div>
                    <div class="col-md-6 detail-item"><strong>Módulos</strong><div id="view-modulos"></div></div>
                    <div class="col-md-6 detail-item"><strong>Tipos</strong><div id="view-tipos"></div></div>
                    <div class="col-md-4 detail-item"><strong>Impacto</strong><div id="view-impacto"></div></div>
                    <div class="col-md-8 detail-item"><strong>Descrição do impacto</strong><div id="view-impacto-desc"></div></div>
                    <div class="col-md-4 detail-item"><strong>Prazo desejado</strong><div id="view-prazo-desejado"></div></div>
                    <div class="col-md-4 detail-item"><strong>Responsável inicial</strong><div id="view-responsavel"></div></div>
                    <div class="col-md-4 detail-item"><strong>Assinatura inicial</strong><div id="view-assinatura"></div></div>
                    <div class="col-md-4 detail-item"><strong>Data aprovação inicial</strong><div id="view-data-aprovacao"></div></div>
                    <div class="col-12 detail-item"><strong>Descrição</strong><div id="view-descricao"></div></div>
                    <div class="col-12 detail-item"><strong>Problema atual</strong><div id="view-problema"></div></div>
                    <div class="col-12 detail-item"><strong>Resultado esperado</strong><div id="view-resultado"></div></div>
                    <div class="col-md-4 detail-item"><strong>Aprovação Conex</strong><div id="view-aprovacao-conex"></div></div>
                    <div class="col-md-4 detail-item"><strong>Data aprovação Conex</strong><div id="view-data-aprovacao-conex"></div></div>
                    <div class="col-md-4 detail-item"><strong>Responsável aprovação Conex</strong><div id="view-responsavel-aprovacao-conex"></div></div>
                </div>
                <div class="row g-3 modal-section fullcare-bloco mt-3">
                    <div class="col-12">
                        <div class="modal-section-title">Bloco FullCare</div>
                    </div>
                    <div class="col-md-4 detail-item"><strong>Prazo resposta</strong><div id="view-prazo-resposta"></div></div>
                    <div class="col-md-4 detail-item"><strong>Orçamento</strong><div id="view-precificacao"></div></div>
                    <div class="col-md-4 detail-item"><strong>Aprovação final</strong><div id="view-aprovacao-resposta"></div></div>
                    <div class="col-md-4 detail-item"><strong>Data resposta</strong><div id="view-data-resposta"></div></div>
                    <div class="col-md-4 detail-item"><strong>Resolvido em</strong><div id="view-resolvido-em"></div></div>
                    <div class="col-md-4 detail-item"><strong>Resolvido por</strong><div id="view-resolvido-por"></div></div>
                    <div class="col-md-4 detail-item"><strong>Versão</strong><div id="view-versao"></div></div>
                    <div class="col-12 detail-item"><strong>Observações</strong><div id="view-observacoes"></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php include_once("templates/footer.php"); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('modalSolicitacaoView');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        if (!button) return;

        var get = function(name) {
            var value = button.getAttribute(name);
            return value && value !== '' ? value : '-';
        };

        document.getElementById('view-id').textContent = '#' + get('data-id');
        document.getElementById('view-nome').textContent = get('data-nome');
        document.getElementById('view-empresa').textContent = get('data-empresa');
        document.getElementById('view-cargo').textContent = get('data-cargo');
        document.getElementById('view-email').textContent = get('data-email');
        document.getElementById('view-telefone').textContent = get('data-telefone');
        document.getElementById('view-data').textContent = get('data-data');
        document.getElementById('view-prioridade').textContent = get('data-prioridade');
        document.getElementById('view-status').textContent = get('data-status');
        document.getElementById('view-modulos').textContent = get('data-modulos');
        document.getElementById('view-tipos').textContent = get('data-tipos');
        document.getElementById('view-modulo-outro').textContent = get('data-modulo-outro');
        document.getElementById('view-impacto').textContent = get('data-impacto');
        document.getElementById('view-impacto-desc').textContent = get('data-impacto-desc');
        document.getElementById('view-prazo-desejado').textContent = get('data-prazo-desejado');
        document.getElementById('view-responsavel').textContent = get('data-responsavel');
        document.getElementById('view-assinatura').textContent = get('data-assinatura');
        document.getElementById('view-data-aprovacao').textContent = get('data-data-aprovacao');
        document.getElementById('view-descricao').textContent = get('data-descricao');
        document.getElementById('view-problema').textContent = get('data-problema');
        document.getElementById('view-resultado').textContent = get('data-resultado');
        document.getElementById('view-prazo-resposta').textContent = get('data-prazo-resposta');
        document.getElementById('view-precificacao').textContent = get('data-precificacao');
        document.getElementById('view-observacoes').textContent = get('data-observacoes');
        document.getElementById('view-aprovacao-resposta').textContent = get('data-aprovacao-resposta');
        document.getElementById('view-data-resposta').textContent = get('data-data-resposta');
        document.getElementById('view-resolvido-em').textContent = get('data-resolvido-em');
        document.getElementById('view-resolvido-por').textContent = get('data-resolvido-por');
        document.getElementById('view-versao').textContent = get('data-versao');
        document.getElementById('view-aprovacao-conex').textContent = get('data-aprovacao-conex');
        document.getElementById('view-data-aprovacao-conex').textContent = get('data-data-aprovacao-conex');
        document.getElementById('view-responsavel-aprovacao-conex').textContent = get('data-responsavel-aprovacao-conex');
    });
});
</script>

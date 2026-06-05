<?php
include_once("check_logado.php");
require_once("dao/solicitacaoCustomizacaoDao.php");

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$norm = function ($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = $c !== false ? $c : $s;
    return preg_replace('/[^a-z]/', '', $s);
};
$cargo = (string)($_SESSION['cargo'] ?? '');
$nivel = (string)($_SESSION['nivel'] ?? '');
$isDiretoria = in_array($norm($cargo), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || in_array($norm($nivel), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || ((int)$nivel === -1);

if (!$isDiretoria) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Acesso restrito à diretoria.</div></div>";
    require_once("templates/footer.php");
    exit;
}

$dao = new SolicitacaoCustomizacaoDAO($conn, $BASE_URL);
$status = trim((string)filter_input(INPUT_GET, 'status'));
$prioridade = trim((string)filter_input(INPUT_GET, 'prioridade'));
$dataIni = trim((string)filter_input(INPUT_GET, 'data_ini'));
$dataFim = trim((string)filter_input(INPUT_GET, 'data_fim'));
$busca = trim((string)filter_input(INPUT_GET, 'busca'));
$rows = $dao->findAll([
    'status' => $status,
    'prioridade' => $prioridade,
    'data_ini' => $dataIni,
    'data_fim' => $dataFim,
    'busca' => $busca,
]);
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Solicitações de Customização</h4>
        <a class="btn btn-primary" href="solicitacao_customizacao.php">Nova solicitação</a>
    </div>

    <form class="row g-2 mb-3" method="get">
        <div class="col-md-3">
            <input type="text" class="form-control" name="busca" placeholder="Buscar por nome/empresa/email" value="<?= e($busca) ?>">
        </div>
        <div class="col-md-3">
            <select class="form-select" name="status">
                <option value="">Status (todos)</option>
                <option value="Aberto" <?= $status === 'Aberto' ? 'selected' : '' ?>>Aberto</option>
                <option value="Em analise" <?= $status === 'Em analise' ? 'selected' : '' ?>>Em análise</option>
                <option value="Resolvido" <?= $status === 'Resolvido' ? 'selected' : '' ?>>Resolvido</option>
                <option value="Cancelado" <?= $status === 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="prioridade">
                <option value="">Prioridade</option>
                <option value="Urgente" <?= $prioridade === 'Urgente' ? 'selected' : '' ?>>Urgente</option>
                <option value="Alta" <?= $prioridade === 'Alta' ? 'selected' : '' ?>>Alta</option>
                <option value="Media" <?= $prioridade === 'Media' ? 'selected' : '' ?>>Média</option>
                <option value="Baixa" <?= $prioridade === 'Baixa' ? 'selected' : '' ?>>Baixa</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" class="form-control" name="data_ini" value="<?= e($dataIni) ?>" placeholder="Data inicial">
        </div>
        <div class="col-md-2">
            <input type="date" class="form-control" name="data_fim" value="<?= e($dataFim) ?>" placeholder="Data final">
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-secondary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Solicitante</th>
                        <th>Empresa</th>
                        <th>Data</th>
                        <th>Prioridade</th>
                        <th>Status</th>
                        <th>Resolvido em</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="8" class="text-center py-3">Nenhuma solicitação encontrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= (int)($row['id_solicitacao'] ?? 0) ?></td>
                                <td><?= e($row['nome'] ?? '') ?></td>
                                <td><?= e($row['empresa'] ?? '') ?></td>
                                <td><?= e($row['data_solicitacao'] ?? '') ?></td>
                                <td><?= e($row['prioridade'] ?? '') ?></td>
                                <td><?= e($row['status'] ?? '') ?></td>
                                <td><?= e($row['resolvido_em'] ?? '') ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#scViewModal" data-id="<?= (int)($row['id_solicitacao'] ?? 0) ?>" title="Ver">
                                        <i class="bi bi-eye"></i>
                                        <span class="ms-1">Ver</span>
                                    </button>
                                    <a class="btn btn-sm btn-outline-primary" href="solicitacao_customizacao.php?id=<?= (int)($row['id_solicitacao'] ?? 0) ?>" title="Editar">
                                        <i class="bi bi-pencil-square"></i>
                                        <span class="ms-1">Editar</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="scViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable sc-view-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Solicitação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="sc-view-loading text-center py-5">Carregando...</div>
                <div class="sc-view-body d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<style>
    .sc-view-modal {
        max-width: 96vw;
        width: 96vw;
    }

    .sc-view-modal .modal-content {
        border-radius: 12px;
        min-height: 92vh;
    }

    .sc-view-modal .modal-body {
        background: #f3f5f7;
    }

    .sc-view-modal .modal-header {
        border-bottom: 0;
    }

    .sc-view-modal .modal-footer {
        border-top: 0;
    }

    .sc-view-section {
        border: 1px solid #d9dee6;
        border-radius: 6px;
        margin-bottom: 12px;
        overflow: hidden;
        background: #f5f6f8;
    }

    .sc-view-section h6 {
        margin: 0;
        padding: 8px 12px;
        background: #4b4f57;
        color: #fff;
        font-weight: 600;
        font-size: 0.92rem;
    }

    .sc-view-section .sc-view-content {
        padding: 12px 14px;
    }

    .sc-view-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .sc-view-item small {
        display: block;
        color: #6b7280;
        font-weight: 600;
        margin-bottom: 2px;
    }

    .sc-view-fullcare h6 {
        background: #5e2363;
    }

    .sc-view-fullcare .sc-view-content {
        background: #f6eafd;
    }

    .sc-view-block {
        background: #eef1f5;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 16px;
    }

    .sc-view-block-title {
        font-weight: 700;
        margin: 2px 0 14px;
        color: #394150;
        text-transform: uppercase;
        font-size: 0.9rem;
        letter-spacing: 0.02em;
    }

    .sc-view-block--fullcare {
        background: #f2e6fb;
    }

    .sc-view-block--fullcare .sc-view-block-title {
        color: #5e2363;
    }

    @media (max-width: 992px) {
        .sc-view-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('scViewModal');
        const loading = modal.querySelector('.sc-view-loading');
        const body = modal.querySelector('.sc-view-body');
        const title = modal.querySelector('.modal-title');

        modal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button ? button.getAttribute('data-id') : '';
            title.textContent = id ? `Solicitação #${id}` : 'Solicitação';
            loading.classList.remove('d-none');
            body.classList.add('d-none');
            body.innerHTML = '';

            if (!id) {
                loading.textContent = 'Solicitação não encontrada.';
                return;
            }

            fetch(`solicitacao_customizacao_view.php?id=${id}`)
                .then(response => response.text())
                .then(html => {
                    body.innerHTML = html;
                    loading.classList.add('d-none');
                    body.classList.remove('d-none');
                })
                .catch(() => {
                    loading.textContent = 'Erro ao carregar os dados.';
                });
        });
    });
</script>

<?php require_once("templates/footer.php"); ?>

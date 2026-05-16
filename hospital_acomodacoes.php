<?php
include_once("check_logado.php");

require_once("templates/header.php");
require_once("dao/hospitalDao.php");
require_once("dao/acomodacaoDao.php");
require_once("models/message.php");
include_once("array_dados.php");

$hospitalDao = new hospitalDAO($conn, $BASE_URL);
$acomodacaoDao = new acomodacaoDAO($conn, $BASE_URL);

$id_hospital = filter_input(INPUT_GET, "id_hospital", FILTER_VALIDATE_INT);
$hospital = $id_hospital ? $hospitalDao->findById($id_hospital) : null;

if (!$hospital) {
    header("Location: " . rtrim($BASE_URL, '/') . "/hospitais");
    exit;
}

$acomodacoes = $acomodacaoDao->findGeralByHospital((int) $id_hospital);

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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

    #main-container .form-control {
        min-height: 42px;
        border-radius: 8px;
    }

    #main-container select.form-control {
        height: 42px;
    }
</style>

<div class="internacao-page" id="main-container">
    <div class="internacao-page__hero">
        <div>
            <h1>Gerenciar acomodações</h1>
        </div>
        <div class="hero-actions">
            <a class="hero-back-btn" href="<?= h(rtrim($BASE_URL, '/') . '/hospitais') ?>">Voltar para hospitais</a>
            <span class="internacao-page__tag"><?= h($hospital->nome_hosp) ?></span>
        </div>
    </div>

    <div class="internacao-page__content">
        <div class="internacao-card internacao-card--general">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Nova acomodação</p>
                </div>
            </div>
            <div class="internacao-card__body">
                <form action="<?= h($BASE_URL) ?>process_acomodacao.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="type" value="create">
                    <input type="hidden" name="fk_hospital" value="<?= (int) $id_hospital ?>">
                    <input type="hidden" name="redirect_hospital_id" value="<?= (int) $id_hospital ?>">
                    <input type="hidden" name="usuario_create_acomodacao" value="<?= h($_SESSION['email_user'] ?? '') ?>">
                    <input type="hidden" name="fk_usuario_acomodacao" value="<?= (int) ($_SESSION['id_usuario'] ?? 0) ?>">
                    <input type="hidden" name="fk_usuario_aco" value="<?= (int) ($_SESSION['id_usuario'] ?? 0) ?>">
                    <input type="hidden" name="data_create_acomodacao" value="<?= h(date('Y-m-d H:i:s')) ?>">

                    <div class="row">
                        <div class="form-group col-md-4 mb-3">
                            <label for="acomodacao_aco">Acomodação</label>
                            <select class="form-control" id="acomodacao_aco" name="acomodacao_aco" required>
                                <option value="">Selecione</option>
                                <?php
                                sort($dados_acomodacao, SORT_ASC);
                                foreach ($dados_acomodacao as $acomd): ?>
                                <option value="<?= h($acomd) ?>"><?= h($acomd) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4 mb-3">
                            <label for="valor_aco">Valor Diária</label>
                            <input onkeyup="formatAcomod(event)" type="text" placeholder="R$0,00" class="dinheiro form-control"
                                id="valor_aco" maxlength="12" name="valor_aco" required>
                        </div>
                        <div class="form-group col-md-4 mb-3">
                            <label for="data_contrato_aco">Data contrato</label>
                            <input type="date" class="form-control" id="data_contrato_aco" name="data_contrato_aco">
                        </div>
                    </div>

                    <hr>
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-plus edit-icon"></i> Adicionar acomodação
                    </button>
                </form>
            </div>
        </div>

        <div class="internacao-card internacao-card--general mt-3">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Acomodações cadastradas</p>
                </div>
            </div>
            <div class="internacao-card__body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Acomodação</th>
                                <th>Valor Diária</th>
                                <th>Data Contrato</th>
                                <th style="width: 180px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($acomodacoes)): ?>
                                <?php foreach ($acomodacoes as $aco): ?>
                                <tr>
                                    <td><?= (int) ($aco['id_acomodacao'] ?? 0) ?></td>
                                    <td><?= h($aco['acomodacao_aco'] ?? '-') ?></td>
                                    <td>
                                        <?php
                                        $valor = isset($aco['valor_aco']) ? (float) $aco['valor_aco'] : 0;
                                        echo 'R$ ' . number_format($valor, 2, ',', '.');
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $dataContrato = (string) ($aco['data_contrato_aco'] ?? '');
                                        if ($dataContrato) {
                                            $dt = date_create($dataContrato);
                                            echo $dt ? h(date_format($dt, 'd/m/Y')) : h($dataContrato);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="<?= h(rtrim($BASE_URL, '/') . '/edit_acomodacao.php?id_acomodacao=' . (int) $aco['id_acomodacao']) ?>">
                                            Editar
                                        </a>
                                        <form method="POST" action="<?= h($BASE_URL) ?>process_acomodacao.php" class="d-inline"
                                            onsubmit="return confirm('Confirma excluir esta acomodação?');">
                                            <input type="hidden" name="type" value="delete">
                                            <input type="hidden" name="id_acomodacao" value="<?= (int) $aco['id_acomodacao'] ?>">
                                            <input type="hidden" name="redirect_hospital_id" value="<?= (int) $id_hospital ?>">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Nenhuma acomodação cadastrada para este hospital.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/scriptMoedaAcomod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once("templates/footer.php"); ?>

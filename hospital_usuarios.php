<?php
include_once("check_logado.php");

require_once("templates/header.php");
require_once("dao/hospitalDao.php");
require_once("dao/hospitalUserDao.php");
require_once("dao/usuarioDao.php");

$hospitalDao = new hospitalDAO($conn, $BASE_URL);
$hospitalUserDao = new hospitalUserDAO($conn, $BASE_URL);
$usuarioDao = new UserDAO($conn, $BASE_URL);

$id_hospital = filter_input(INPUT_GET, "id_hospital", FILTER_VALIDATE_INT);
$hospital = $id_hospital ? $hospitalDao->findById($id_hospital) : null;

if (!$hospital) {
    header("Location: " . rtrim($BASE_URL, '/') . "/hospitais", true, 303);
    exit;
}

$vinculos = $hospitalUserDao->listarPorHospital((int) $id_hospital);
$usuariosAtivos = $usuarioDao->selectAllUsuario('ativo_user IN ("s","S","1","true","TRUE","ATIVO","ativo")', 'usuario_user ASC', null);

$jaVinculados = [];
foreach ($vinculos as $v) {
    $uid = (int) ($v['fk_usuario_hosp'] ?? 0);
    if ($uid > 0) {
        $jaVinculados[$uid] = true;
    }
}

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
</style>

<div class="internacao-page" id="main-container">
    <div class="internacao-page__hero">
        <div>
            <h1>Gerenciar usuários do hospital</h1>
        </div>
        <div class="hero-actions">
            <a class="hero-back-btn" href="<?= h(rtrim($BASE_URL, '/') . '/hospitais') ?>">Voltar para hospitais</a>
            <a class="hero-back-btn" href="<?= h(rtrim($BASE_URL, '/') . '/hospital_acomodacoes.php?id_hospital=' . (int) $id_hospital) ?>">Acomodações</a>
            <span class="internacao-page__tag"><?= h($hospital->nome_hosp) ?></span>
        </div>
    </div>

    <div class="internacao-page__content">
        <div class="internacao-card internacao-card--general">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Novo vínculo</p>
                </div>
            </div>
            <div class="internacao-card__body">
                <form action="<?= h(rtrim($BASE_URL, '/') . '/process_hospitalUser.php') ?>" method="POST">
                    <input type="hidden" name="type" value="create">
                    <input type="hidden" name="fk_hospital_user" value="<?= (int) $id_hospital ?>">
                    <input type="hidden" name="redirect_hospital_id" value="<?= (int) $id_hospital ?>">

                    <div class="row">
                        <div class="form-group col-md-6 mb-3">
                            <label for="fk_usuario_hosp">Usuário</label>
                            <select class="form-control"
                                id="fk_usuario_hosp"
                                name="fk_usuario_hosp"
                                required
                                >
                                <option value="">Selecione o usuário</option>
                                <?php foreach ($usuariosAtivos as $u): ?>
                                    <?php
                                    $uid = (int) ($u['id_usuario'] ?? 0);
                                    $nome = (string) ($u['usuario_user'] ?? '');
                                    $cargo = (string) ($u['cargo_user'] ?? '');
                                    if ($uid <= 0 || $nome === '') {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?= $uid ?>" <?= isset($jaVinculados[$uid]) ? 'disabled' : '' ?>>
                                        <?= h($nome) ?><?= $cargo ? ' - ' . h($cargo) : '' ?><?= isset($jaVinculados[$uid]) ? ' (já vinculado)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-plus edit-icon"></i> Vincular usuário
                    </button>
                </form>
            </div>
        </div>

        <div class="internacao-card internacao-card--general mt-3">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Usuários vinculados</p>
                </div>
            </div>
            <div class="internacao-card__body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID vínculo</th>
                                <th>ID usuário</th>
                                <th>Usuário</th>
                                <th>E-mail</th>
                                <th>Cargo</th>
                                <th>Nível</th>
                                <th style="width: 120px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($vinculos)): ?>
                                <?php foreach ($vinculos as $v): ?>
                                    <tr>
                                        <td><?= (int) ($v['id_hospitalUser'] ?? 0) ?></td>
                                        <td><?= (int) ($v['id_usuario'] ?? $v['fk_usuario_hosp'] ?? 0) ?></td>
                                        <td><?= h($v['usuario_user'] ?? '-') ?></td>
                                        <td><?= h($v['email_user'] ?? '-') ?></td>
                                        <td><?= h($v['cargo_user'] ?? '-') ?></td>
                                        <td><?= h($v['nivel_user'] ?? '-') ?></td>
                                        <td>
                                            <form method="POST" action="<?= h(rtrim($BASE_URL, '/') . '/del_hosp_user.php') ?>" class="d-inline"
                                                onsubmit="return confirm('Confirma excluir este vínculo?');">
                                                <input type="hidden" name="type" value="delete">
                                                <input type="hidden" name="id_hospitalUser" value="<?= (int) ($v['id_hospitalUser'] ?? 0) ?>">
                                                <input type="hidden" name="redirect_hospital_id" value="<?= (int) $id_hospital ?>">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Nenhum usuário vinculado para este hospital.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once("templates/footer.php"); ?>

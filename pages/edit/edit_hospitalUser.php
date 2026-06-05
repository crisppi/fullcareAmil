<?php
ini_set('log_errors', '1');
error_reporting(E_ALL);

include_once("check_logado.php");
require_once("templates/header.php");

require_once("models/hospitalUser.php");
require_once("dao/hospitalUserDao.php");

require_once("models/hospital.php");
require_once("dao/hospitalDao.php");

require_once("models/usuario.php");
require_once("dao/usuarioDao.php");

/* Helpers */
function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* DAOs */
$hospitalUserDao = new hospitalUserDAO($conn, $BASE_URL);
$hospitalDAO     = new hospitalDAO($conn, $BASE_URL);
$usuarioDao      = new userDAO($conn, $BASE_URL);

/* Listas base (com fallback SQL) */
$limite = 500;
$inicio = 0;

$hospitals = [];
try {
    $tmp = $hospitalDAO->findGeral($limite, $inicio);
    if (is_array($tmp) && $tmp) $hospitals = $tmp;
} catch (Throwable $e) {
}
if (!$hospitals) {
    $hospitals = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
        ->fetchAll(PDO::FETCH_ASSOC);
}

$usuarios = [];
try {
    $tmp = $usuarioDao->findGeral($limite, $inicio);
    if (is_array($tmp) && $tmp) $usuarios = $tmp;
} catch (Throwable $e) {
}
if (!$usuarios) {
    $usuarios = $conn->query("SELECT id_usuario, usuario_user, email_user, cargo_user FROM tb_user ORDER BY usuario_user")
        ->fetchAll(PDO::FETCH_ASSOC);
}

/* ID pela URL: aceita ?id_hospitalUser ou ?id */
$idParam = filter_input(INPUT_GET, "id_hospitalUser", FILTER_VALIDATE_INT);
if (!$idParam) {
    $idParam = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
}

/* Buscar o vínculo de forma robusta */
$hospitalUser = [];
if ($idParam) {
    if (method_exists($hospitalUserDao, 'findByPk')) {
        $row = $hospitalUserDao->findByPk((int)$idParam);
        if (is_array($row) && $row) $hospitalUser = $row;
    }
    if (!$hospitalUser && method_exists($hospitalUserDao, 'joinHospitalUser')) {
        $res = $hospitalUserDao->joinHospitalUser((int)$idParam);
        if ($res) {
            if (isset($res[0]) && is_array($res[0])) {
                $hospitalUser = $res[0];
            } elseif (is_array($res)) {
                $hospitalUser = $res;
            }
        }
    }
    if (!$hospitalUser && method_exists($hospitalUserDao, 'findByIdUser')) {
        $row = $hospitalUserDao->findByIdUser((int)$idParam);
        if (is_array($row) && $row) $hospitalUser = $row;
    }
}

/* IDs selecionados nos selects */
$selHospId = isset($hospitalUser['fk_hospital_user']) ? (int)$hospitalUser['fk_hospital_user'] : 0;
$selUserId = isset($hospitalUser['fk_usuario_hosp']) ? (int)$hospitalUser['fk_usuario_hosp'] : 0;
?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<div id="main-container" class="container">
    <div class="row">
        <h4 class="page-title">Atualizar Hospital por Usuário</h4>

        <?php if (!$idParam): ?>
        <div class="alert alert-warning" style="margin-top:10px;">
            Parâmetro de identificação ausente. Use <code>?id_hospitalUser=</code> ou <code>?id=</code>.
        </div>
        <?php elseif (!$hospitalUser): ?>
        <div class="alert alert-danger" style="margin-top:10px;">
            Vínculo não encontrado para o identificador <strong><?= (int)$idParam ?></strong>.
        </div>
        <?php endif; ?>

        <form class="formulario-borderless" action="<?= h($BASE_URL) ?>process_hospitalUser.php" method="POST"
            enctype="multipart/form-data">
            <input type="hidden" name="type" value="update">
            <input type="hidden" name="id_hospitalUser"
                value="<?= (int)($hospitalUser['id_hospitalUser'] ?? $idParam ?? 0) ?>">

            <div class="form-group row">

                <!-- Select Hospital -->
                <div class="form-group col-sm-3">
                    <label class="control-label" for="fk_hospital_user">Hospital</label>
                    <select class="form-control" id="fk_hospital_user" name="fk_hospital_user" required>
                        <option value="">Selecione o Hospital</option>
                        <?php foreach ($hospitals as $hospital):
                            $hid = (int)($hospital['id_hospital'] ?? 0);
                            if (!$hid) continue;
                            $hnm = h($hospital['nome_hosp'] ?? '');
                            $selected = ($hid === $selHospId) ? 'selected' : '';
                        ?>
                        <option value="<?= $hid ?>" <?= $selected ?>><?= $hnm ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Select Usuário -->
                <div class="form-group col-sm-4">
                    <label class="control-label" for="fk_usuario_hosp">Usuário e e-mail</label>
                    <select class="form-control" id="fk_usuario_hosp" name="fk_usuario_hosp" required>
                        <option value="">Selecione o usuário</option>
                        <?php foreach ($usuarios as $usuario):
                            $uid   = (int)($usuario['id_usuario'] ?? 0);
                            if (!$uid) continue;
                            $nome  = h($usuario['usuario_user'] ?? '');
                            $email = h($usuario['email_user'] ?? '');
                            $cargo = h($usuario['cargo_user'] ?? '');
                            $selected = ($uid === $selUserId) ? 'selected' : '';
                        ?>
                        <option value="<?= $uid ?>" <?= $selected ?>><?= $nome ?> - <?= $email ?> - <?= $cargo ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <br>
            <button type="submit" class="btn btn-primary">
                <i style="font-size:1rem;margin-right:5px;" class="fa-solid fa-check edit-icon"></i>
                Atualizar
            </button>
        </form>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
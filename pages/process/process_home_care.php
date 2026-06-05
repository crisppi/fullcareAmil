<?php
include_once("check_logado.php");
require_once("globals.php");
require_once("dao/homeCareDao.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $BASE_URL . "home_care_gestao.php");
    exit;
}

if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['_csrf'] ?? ''))) {
    $_SESSION['msg'] = 'Falha de validacao da requisicao.';
    $_SESSION['type'] = 'danger';
    header("Location: " . $BASE_URL . "home_care_gestao.php");
    exit;
}

$internacaoId = filter_input(INPUT_POST, 'fk_internacao_hc', FILTER_VALIDATE_INT) ?: 0;
if ($internacaoId <= 0) {
    $_SESSION['msg'] = 'Internacao invalida para Home Care.';
    $_SESSION['type'] = 'danger';
    header("Location: " . $BASE_URL . "home_care_gestao.php");
    exit;
}

$dao = new HomeCareDAO($conn, $BASE_URL);
$payload = $_POST;
$payload['fk_internacao_hc'] = $internacaoId;
$payload['fk_usuario_hc'] = (int)($_SESSION['id_usuario'] ?? 0);

try {
    $dao->createUpdate($payload);
    $_SESSION['msg'] = 'Atualizacao de Home Care registrada com sucesso.';
    $_SESSION['type'] = 'success';
} catch (Throwable $e) {
    $_SESSION['msg'] = 'Nao foi possivel salvar a atualizacao de Home Care.';
    $_SESSION['type'] = 'danger';
    error_log('[HOME_CARE][SAVE][ERROR] ' . $e->getMessage());
}

header("Location: " . $BASE_URL . "home_care_avaliacao.php?id_internacao=" . $internacaoId);
exit;

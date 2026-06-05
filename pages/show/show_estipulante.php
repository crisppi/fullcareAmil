<?php
include_once("check_logado.php");
include_once("globals.php");

$id_estipulante = filter_input(INPUT_GET, "id_estipulante", FILTER_VALIDATE_INT);
$redirectUrl = $id_estipulante
    ? $BASE_URL . "estipulantes/editar/" . (int)$id_estipulante
    : $BASE_URL . "estipulantes";

header("Location: " . $redirectUrl);
exit;

<?php
include_once("check_logado.php");
include_once("globals.php");

$id_seguradora = filter_input(INPUT_GET, "id_seguradora", FILTER_VALIDATE_INT);
$redirectUrl = $id_seguradora
    ? $BASE_URL . "seguradoras/editar/" . (int)$id_seguradora
    : $BASE_URL . "seguradoras";

header("Location: " . $redirectUrl);
exit;

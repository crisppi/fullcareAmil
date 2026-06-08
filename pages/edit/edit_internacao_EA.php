<?php
include_once("check_logado.php");
include_once("globals.php");

$id_internacao = filter_input(INPUT_GET, 'id_internacao', FILTER_VALIDATE_INT) ?: 1;

header('Location: ' . $BASE_URL . 'internacoes/editar/' . (int)$id_internacao . '?section=gestao#div_evento');
exit;

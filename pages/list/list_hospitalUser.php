<?php
include_once("check_logado.php");
include_once("globals.php");
include_once("models/pagination.php");
include_once("templates/header.php");

$busca = filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS);
$ativo_hosp = filter_input(INPUT_GET, 'ativo_hosp', FILTER_SANITIZE_SPECIAL_CHARS);
$bl = filter_input(INPUT_GET, 'bl', FILTER_SANITIZE_SPECIAL_CHARS);

include_once("formularios/form_list_HospitalUser.php");
include_once("templates/footer.php");

<?php
include_once("check_logado.php");

include_once("models/pagination.php");

$RAH_LIST_CONTEXT = 'auditar';
$RAH_FORM_ACTION  = 'internacoes/rah';
if (!isset($_GET['encerrado_cap']) || $_GET['encerrado_cap'] === '') {
    $_GET['encerrado_cap'] = 'n';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<script src="js/timeout.js"></script>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/timeout.js"></script>
</head>

<?php
include_once("formularios/form_list_internacao_cap_rah.php");
$pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS);
$pesqInternado = filter_input(INPUT_GET, 'pesqInternado', FILTER_SANITIZE_SPECIAL_CHARS);
$senha_fin = filter_input(INPUT_GET, 'senha_fin', FILTER_SANITIZE_SPECIAL_CHARS);
$med_check = filter_input(INPUT_GET, 'med_check', FILTER_SANITIZE_SPECIAL_CHARS);
$enf_check = filter_input(INPUT_GET, 'enf_check', FILTER_SANITIZE_SPECIAL_CHARS);
$adm_check = filter_input(INPUT_GET, 'adm_check', FILTER_SANITIZE_SPECIAL_CHARS);
$data_intern_int = filter_input(INPUT_GET, 'data_intern_int', FILTER_SANITIZE_SPECIAL_CHARS);
$auditor = filter_input(INPUT_GET, 'auditor', FILTER_SANITIZE_SPECIAL_CHARS);
$bl = filter_input(INPUT_GET, 'bl', FILTER_SANITIZE_SPECIAL_CHARS);

include_once("templates/footer.php");
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

</html>

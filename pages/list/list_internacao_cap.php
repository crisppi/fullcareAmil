<?php
include_once("check_logado.php");

include_once("models/pagination.php");
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
    <link rel="stylesheet" href="./css/table_style.css">
</head>

<?php
include_once("formularios/form_list_internacao_cap.php");
$pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome');
$pesqInternado = filter_input(INPUT_GET, 'pesqInternado');
$senha_fin = filter_input(INPUT_GET, 'senha_fin');
$med_check = filter_input(INPUT_GET, 'med_check');
$enf_check = filter_input(INPUT_GET, 'enf_check');
$adm_check = filter_input(INPUT_GET, 'adm_check');
$data_intern_int = filter_input(INPUT_GET, 'data_intern_int');
$auditor = filter_input(INPUT_GET, 'auditor');
$bl = filter_input(INPUT_GET, 'bl');

include_once("templates/footer.php");
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

</html>
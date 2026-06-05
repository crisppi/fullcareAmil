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
$dt_ini = filter_input(INPUT_GET, 'dt_ini', FILTER_SANITIZE_SPECIAL_CHARS);
$dt_fim = filter_input(INPUT_GET, 'dt_fim', FILTER_SANITIZE_SPECIAL_CHARS);
$seguradora_id = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT);
$responsavel = filter_input(INPUT_GET, 'responsavel', FILTER_SANITIZE_SPECIAL_CHARS);

include_once("formularios/form_list_fila_tarefas.php");
include_once("templates/footer.php");
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

</html>

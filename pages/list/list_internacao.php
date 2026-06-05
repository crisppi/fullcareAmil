<?php
include_once("check_logado.php");

include_once("models/pagination.php");
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/timeout.js"></script>
    <link rel="stylesheet" href="./css/table_style.css">

</head>

<body>
    <div>
        <?php
        include_once("formularios/form_list_internacao.php");
        $pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS);
        $pesqInternado = filter_input(INPUT_GET, 'pesqInternado', FILTER_SANITIZE_SPECIAL_CHARS);
        $pesquisa_pac = filter_input(INPUT_GET, 'pesquisa_pac', FILTER_SANITIZE_SPECIAL_CHARS);
        $bl = filter_input(INPUT_GET, 'bl', FILTER_SANITIZE_SPECIAL_CHARS);


        ?>
    </div>
</body>

<script src="js/scriptDataAltaHospitalar.js"></script>
<script src="js/timeout.js"></script>

</html>

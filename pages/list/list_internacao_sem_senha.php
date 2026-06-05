<?php
include_once("check_logado.php");
include_once("models/pagination.php");

if (!isset($_GET['sem_senha'])) {
    $_GET['sem_senha'] = $_REQUEST['sem_senha'] = '1';
}
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
        <?php include_once("formularios/form_list_internacao.php"); ?>
    </div>
</body>

<script src="js/scriptDataAltaHospitalar.js"></script>
<script src="js/timeout.js"></script>

</html>

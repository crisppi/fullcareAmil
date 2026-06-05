<!DOCTYPE html>
<html lang="pt-br">
<script src="js/timeout.js"></script>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"> </script>

    <script type="text/javascript">
    </script>

</head>

<body>
    <?php
    include_once("check_logado.php");

    include_once("globals.php");
    include_once("models/internacao.php");
    require_once("dao/internacaoDao.php");

    require_once("models/message.php");
    include_once("models/hospital.php");
    include_once("dao/hospitalDao.php");

    include_once("models/patologia.php");
    include_once("dao/patologiaDao.php");

    require_once("dao/pacienteDAO.php");

    $internacaoDAO = new internacaoDAO($conn, $BASE_URL);

    $id_internacao = filter_input(INPUT_GET, "id_internacao");
    // $niveis = $internacaoDAO->joininternacaoHospitalshow($id_internacao);

    // coverter formato data
    // $formatData = date('d/m/Y', strtotime($niveis['data_intern_int']));
    ?>
    <!-- <h4>Prorrogação Paciente</h4>
    <hr>
    <div id="view-contact-container" class="container-fluid" style="align-items:center">
        <span class="card-title bold" style="font-weight: 500; margin:0px 5px 0px 00px">Hospital:</span>
        <span class="card-title bold" style=" font-weight: 800; margin:0px 10px 0px 0px"><?= $niveis['nome_hosp'] ?></span>
        <span style="font-weight: 500; margin:0px 5px 0px 80px">Paciente:</span>
        <span style=" font-weight: 800; margin:0px 10px 0px 0px"><?= $niveis['nome_pac'] ?></span>
        <span style="font-weight: 500; margin:0px 5px 0px 80px">Data internação:</span>
        <span style="font-weight: 800; margin:0px 80px 0px 0px"><?= $formatData  ?></span>
        <span style="font-weight: 500; margin:0px 5px 0px 40px ">Internação:</span>
        <span style="font-weight: 500; margin:0px 80px 0px 5px "><?= $niveis['id_internacao'] ?></span>
    </div>
    <hr> -->

    <!-- FORMULARIO INTERNACAO -->
    <?php include_once('formularios/form_cad_internacao_censo.php'); ?>

    <!-- <div>
        <button class="btn-primary btn-int-niveis" id="btn-prorrog">Prorrogação</button>
        <button class="btn-primary btn-int-niveis" id="btn-uti">UTI</button>
        <button class="btn-primary btn-int-niveis" id="btn-gestao">Gestão</button>
        <button class="btn-primary btn-int-niveis" id="btn-negoc">Negociações</button>
    </div> -->

    <!-- FORMULARIO DE GESTÃO -->
    <?php include_once('formularios/form_cad_internacao_censo_gestao.php'); ?>

    <!-- FORMULARIO DE UTI -->
    <?php include_once('formularios/form_cad_internacao_censo_uti.php'); ?>

    <!-- FORMULARIO DE PRORROGACOES -->
    <?php include_once('formularios/form_cad_internacao_censo_prorrog.php'); ?>

    <!-- <FORMULARO DE NEGOCIACOES -->
    <?php include_once('formularios/form_cad_internacao_censo_negoc.php'); ?>

    <br>
    <?php include_once("diversos/backbtn_internacao.php"); ?>

    <hr>
    <div>
        <a class="btn btn-success styled btn-int-niveis" style="margin-left:10px; width: 150px"
            href="internacoes/nova">Nova internação</a>
    </div>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
    </script>
    <?php require_once("templates/footer.php");
    ?>
</body>

</html>
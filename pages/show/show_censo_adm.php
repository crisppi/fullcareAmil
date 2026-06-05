<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/timeout.js"></script>

</head>

<?php
include_once ("check_logado.php");

include_once ("globals.php");
include_once ("templates/header.php");
include_once ("models/censo.php");
require_once ("dao/censoDao.php");
require_once ("models/message.php");
include_once ("models/hospital.php");
include_once ("dao/hospitalDao.php");
include_once ("models/patologia.php");
include_once ("dao/patologiaDao.php");
include_once ("dao/pacienteDAO.php");


// Pegar o id da censo
$id_censo = filter_input(INPUT_GET, "id_censo", FILTER_SANITIZE_NUMBER_INT);
$order = null;
$obLimite = 1;
$censoDao = new censoDAO($conn, $BASE_URL);
$hospitalDao = new HospitalDAO($conn, $BASE_URL);
$pacienteDao = new PacienteDAO($conn, $BASE_URL);

//Instanciar o metodo censo   
$censo = $censoDao->findById($id_censo);
$hospital = $hospitalDao->findById($censo->fk_hospital_censo);
$paciente = $pacienteDao->findById($censo->fk_paciente_censo);
?>
<div class="container" id="main-container">
    <span><button type="submit"
            style="margin-left:3px; font-size: 25px; background:transparent; border-color:transparent; color:green"
            class="delete-btn"><i class="d-inline-block fas fa-eye check-icon"></i></button>
        <h4 style="margin-top:10px; margin-left:20px">Dados do censo do paciente:
            <?= $paciente['0']['nome_pac'] ?>
        </h4>
    </span>

    <div class="card-header container-fluid" id="view-contact-container">
        <span style="font-weight: 500;" class="card-title bold">Censo:</span>
        <span class="card-title bold">
            <?= $id_censo ?>
        </span>
        <br>
    </div>

    <div class="card-body">

        <span style="font-weight: 500;" class=" card-text bold">Hospital:</span>
        <span class=" card-text bold">
            <?= $hospital->nome_hosp ?>
        </span>
        <br>
        <span style="font-weight: 500;" class=" card-text bold">Data censo:</span>
        <span class=" card-text bold">
            <?= date("d/m/Y", strtotime($censo->data_censo)) ?>
        </span>
        <br>
        <span style="font-weight: 500;" class=" card-text bold">Tipo censo:</span>
        <span class=" card-text bold">
            <?= $censo->tipo_admissao_censo ?>
        </span>
        <br>
        <span style="font-weight: 500;" class=" card-text bold">Modo Admissão:</span>
        <span class=" card-text bold">
            <?= $censo->modo_internacao_censo ?>
        </span>
        <br>

        <span style="font-weight: 500;" class=" card-text bold">Médico:</span>
        <span class=" card-text bold">
            <?= $censo->titular_censo ?>
        </span>
        <br>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    censoegrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
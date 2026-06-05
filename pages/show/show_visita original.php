<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</head>

<body>
    <?php
    include_once("check_logado.php");

    include_once("globals.php");
    include_once("templates/header.php");
    include_once("models/internacao.php");
    require_once("dao/internacaoDao.php");
    include_once("models/visita.php");
    require_once("dao/visitaDao.php");
    require_once("models/message.php");
    include_once("models/hospital.php");
    include_once("dao/hospitalDao.php");
    include_once("models/patologia.php");
    include_once("dao/patologiaDao.php");
    include_once("dao/pacienteDAO.php");


    // Pegar o id da internacao
    $id_visita = filter_input(INPUT_GET, "id_visita", FILTER_SANITIZE_NUMBER_INT);
    $internacao;
    $visitaDao = new visitaDAO($conn, $BASE_URL);

    //Instanciar o metodo internacao   
    $internacao = $visitaDao->joinVisitaShow($id_visita);
    // echo "</pre>";
    // print_r($internacao);
    extract($internacao);
    ?>
    <script src="js/timeout.js"></script>

    <div style="margin:15px">
        <span><button type="submit"
                style="margin-left:3px; font-size: 25px; background:transparent; border-color:transparent; color:green"
                class="delete-btn"><i class="d-inline-block fas fa-eye check-icon"></i></button>
            <h4 style="margin-top:10px; margin-left:20px">Dados da internação do paciente:
                <?= $internacao['0']['nome_pac'] ?></h4>
        </span>
        <div class="card-header container-fluid" id="view-contact-container">
            <span style="font-weight: 500;" class="card-title bold">Internação:</span>
            <span class="card-title bold"><?= $internacao['0']['id_internacao'] ?></span>
            <br>
        </div>
        <div class="card-header container-fluid" id="view-contact-container">
            <span style="font-weight: 500;" class="card-title bold">Visita:</span>
            <span class="card-title bold"><?= $internacao['0']['id_visita'] ?></span>
            <br>
        </div>
        <div class="card-header container-fluid" id="view-contact-container">
            <span style="font-weight: 500;" class="card-title bold">Visita:</span>
            <span class="card-title bold"><?= date("d/m/Y", strtotime($internacao['0']['data_visita_int']))  ?></span>
            <br>
        </div>
        <div class="card-body">

            <span style="font-weight: 500;" class=" card-text bold">Hospital:</span>
            <span class=" card-text bold"><?= $internacao['0']['nome_hosp'] ?></span>
            <br>
            <span style="font-weight: 500;" class=" card-text bold">Data Internação:</span>
            <span class="card-title bold"><?= date("d/m/Y", strtotime($internacao['0']['data_intern_int']))  ?></span>

            <br>
            <span style="font-weight: 500;" class=" card-text bold">Acomodação:</span>
            <span class=" card-text bold"><?= $internacao['0']['acomodacao_int'] ?></span>
            <br>

            <hr>
            <span style="font-weight: 500;" class=" card-text bold">Relatório auditoria:</span>
            <span class=" card-text bold"><?= $internacao['0']['rel_visita_vis'] ?></span>
            <hr>
            <span style="font-weight: 500;" class=" card-text bold">Ações da auditoria:</span>
            <span class=" card-text bold"><?= $internacao['0']['acoes_int_vis'] ?></span>
            <br>
        </div>

        <?php include_once("diversos/backbtn_visita.php"); ?>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
    <?php
    require_once("templates/footer.php");
    ?>
</body>

</html>
<?php

include_once("check_logado.php");
include_once("globals.php");
include_once("models/paciente.php");
include_once("dao/pacienteDao.php");
// include_once ("templates/header.php");
include_once("models/internacao.php");
include_once("dao/internacaoDao.php");
include_once("models/antecedente.php");
include_once("dao/antecedenteDao.php");
$id_paciente = filter_input(INPUT_GET, "id_paciente", FILTER_SANITIZE_NUMBER_INT);
$pacienteDao = new PacienteDAO($conn, $BASE_URL);
$paciente = $pacienteDao->findById($id_paciente);

extract($paciente);

$Internacao_geral = new internacaoDAO($conn, $BASE_URL);
$Internacaos = $Internacao_geral->findByPacId($paciente['0']['id_paciente']);

$antecedente_geral = new antecedenteDAO($conn, $BASE_URL);
$antecedentes = $antecedente_geral->findAntByPacId($paciente['0']['id_paciente']);

$total_capeante = $Internacao_geral->findTotalByPacId($paciente['0']['id_paciente']);
$total_diarias = $Internacao_geral->findTotalDiariasByPacId($paciente['0']['id_paciente']);
$total_diarias_uti = $Internacao_geral->findTotalDiariasUtiByPacId($paciente['0']['id_paciente']);
?>
<style>
.col-20 {
    flex: 0 0 10%;
    max-width: 10%;
}

.col-40 {
    flex: 0 0 58%;
    max-width: 58%;
    /* margin-left:2%; */
}
</style>
<script src="js/timeout.js"></script>

<div class="container" id="main-container" style="margin-top:20px;">
    <div class="row">
        <div class="col-20">

            <div class="card">
                <!-- <img src="img/user.png" class="card-img-top" alt="..."> -->
                <div class="card-body">
                    <h5 class="card-title"><?php echo $paciente['0']['nome_pac'] ?></h5>
                    <p class="card-text">Informações sobre o paciente.</p>
                </div>
                <ul class="list-group list-group-flush">
                    <?php
                    $originalDate = $paciente["0"]["data_create_pac"];

                    // Create a DateTime object from the original date
                    $date = new DateTime($originalDate);

                    // Format the date to DD/MM/YYYY
                    $formattedDate = $date->format('d/m/Y');
                    ?>
                    <li class="list-group-item"><b>Data Cadastro:</b> <?php echo $formattedDate; ?></li>
                    <li class="list-group-item"><b>Total de Diárias:</b>
                        <?php echo $total_diarias['0']['total_diarias'] ?></li>
                    <li class="list-group-item"><b>Total de Diárias UTI:</b>
                        <?php echo $total_diarias_uti['0']['total_diarias'] ?></li>
                    <li class="list-group-item"><b>Custo Total:</b>
                        <?php echo 'R$ ' . number_format($total_capeante['0']['total_capeante'], 2, ',', '.'); ?>

                        <!-- <?php echo 'R$' . $total_capeante['0']['total_capeante'] ?></li> -->

                    </li>

                </ul>
                <div class="card-body">
                    <a href="internacoes/nova" class="card-link">Lançar Internação </a>
                </div>
            </div>
        </div>
        <div class="col-40">
            <div class="row">
                <div class="col-12">
                    <h4>Histórico de Internações</h4>
                    <!-- Conteúdo do lado direito superior -->
                    <div id="table-content">

                        <table class="table table-sm table-striped  table-hover table-condensed">
                            <thead>
                                <tr>
                                    <th scope="col" width="3%">Id</th>
                                    <th scope="col" width="18%">Hospital</th>
                                    <th scope="col" width="8%">Data int</th>
                                    <th scope="col" width="8%">Capeante</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php

                                foreach ($Internacaos as $intern) :
                                    extract($Internacaos);
                                ?>
                                <tr style="font-size:13px">
                                    <td scope="row" class="col-id">
                                        <?= $intern["id_internacao"] ?>
                                    </td>

                                    <td scope="row" style="font-weight:bolder;">
                                        <?= $intern["nome_hosp"] ?>
                                    </td>
                                    <td scope="row">
                                        <?= date('d/m/Y', strtotime($intern["data_intern_int"])) ?>
                                    </td>
                                    <td scope="row">
                                        <i class="bi bi-file-text"
                                            style="font-size: 1rem;margin-right:5px; color: rgb(67, 125, 525);"></i>
                                    </td>
                                    <?php endforeach; ?>
                                    <?php if (count($Internacaos) == 0) : ?>
                                <tr>
                                    <td colspan="4" scope="row" class="col-id" style='font-size:15px'>
                                        Não foram encontrados registros
                                    </td>
                                </tr>

                                <?php endif ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-12">
                    <div class="row">
                        <div class="col-12">
                            <div id="table-content" style="margin-top:10px">

                                <table class="table table-sm table-striped  table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th scope="col" width="18%">Antecedentes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php

                                        foreach ($antecedentes as $ant) :
                                            extract($antecedentes);
                                        ?>
                                        <tr style="font-size:13px">


                                            <td scope="row" style="font-weight:bolder;">
                                                <?= $ant["antecedente_ant"] ?>
                                            </td>

                                            <?php endforeach; ?>
                                            <?php if (count($antecedentes) == 0) : ?>
                                        <tr>
                                            <td colspan="1" scope="row" class="col-id" style='font-size:15px'>
                                                Não foram encontrados registros
                                            </td>
                                        </tr>

                                        <?php endif ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
                <!-- <h1>Internacoes</h1> -->
                <!-- Conteúdo do lado direito -->

            </div>
        </div>


    </div>

</div>


<script src="js/apagarModal.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<script>
src = "https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js";
</script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
<script src="./scripts/cadastro/general.js"></script>
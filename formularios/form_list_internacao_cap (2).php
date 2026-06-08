<?php

require_once("templates/header.php");

require_once("models/message.php");

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");

include_once("models/capeante.php");
include_once("dao/capeanteDao.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/usuario.php");
include_once("dao/usuarioDao.php");

include_once("models/pagination.php");

// inicializacao de variaveis
$data_intern_int = null;
$order = null;
$obLimite = null;
$blocoNovo = null;
$where = null;

$Internacao_geral = new internacaoDAO($conn, $BASE_URL);
$Internacaos = $Internacao_geral->findGeral();

$capeante_geral = new capeanteDAO($conn, $BASE_URL);
$QtdTotalInt = new internacaoDAO($conn, $BASE_URL);
$capeante = $capeante_geral->findGeral($limite, $inicio);

$capeante_geral = new capeanteDAO($conn, $BASE_URL);
$QtdTotalInt = $capeante_geral->findGeral();

$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$pacientes = $pacienteDao->findGeral($limite, $inicio);

$usuarioDao = new userDAO($conn, $BASE_URL);
$usuarios = $usuarioDao->findGeral($limite, $inicio);
// print_r($usuarios);
$hospital_geral = new HospitalDAO($conn, $BASE_URL);
$hospitals = $hospital_geral->findGeral($limite, $inicio);

$patologiaDao = new patologiaDAO($conn, $BASE_URL);
$patologias = $patologiaDao->findGeral();

$internacao = new internacaoDAO($conn, $BASE_URL);
$limite_pag = filter_input(INPUT_GET, 'limite_pag') ? filter_input(INPUT_GET, 'limite_pag') : 10;
$limite = filter_input(INPUT_GET, 'limite_pag') ? filter_input(INPUT_GET, 'limite_pag') : 10;
$ordenar = filter_input(INPUT_GET, 'ordenar') ? filter_input(INPUT_GET, 'ordenar') : 1;
// validacao de lista de hospital por usuario (o nivel sera o filtro)
if ($_SESSION['nivel'] == 3 or $_SESSION['nivel'] == 1) {
    $auditor = ($_SESSION['id_usuario']);
} else {
    $auditor = null;
};

$QtdTotalInt = new internacaoDAO($conn, $BASE_URL);
// METODO DE BUSCA DE PAGINACAO 
$pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS);
$senha_fin = filter_input(INPUT_GET, 'senha_fin') ?: NULL;
$med_check = filter_input(INPUT_GET, 'med_check') ?: NULL;
$enf_check = filter_input(INPUT_GET, 'enf_check') ?: NULL;
$adm_check = filter_input(INPUT_GET, 'adm_check') ?: NULL;
$senha_int = filter_input(INPUT_GET, 'senha_int', FILTER_SANITIZE_SPECIAL_CHARS) ?: NULL;
$data_intern_int = filter_input(INPUT_GET, 'data_intern_int') ?: NULL;
$data_intern_int_max = filter_input(INPUT_GET, 'data_intern_int_max') ?: NULL;
if (empty($data_intern_int_max)) {
    $data_intern_int_max = date('Y-m-d'); // Formato de data compatível com SQL
}
$pesquisa_pac = filter_input(INPUT_GET, 'pesquisa_pac', FILTER_SANITIZE_SPECIAL_CHARS);
$encerrado_cap = filter_input(INPUT_GET, 'encerrado_cap') ?: '';
$limite = filter_input(INPUT_GET, 'limite') ? filter_input(INPUT_GET, 'limite') : 10;
$ordenar = filter_input(INPUT_GET, 'ordenar') ? filter_input(INPUT_GET, 'ordenar') : 1;
$em_auditoria_cap = 'n';
// $encerrado_cap = 'n';

$condicoes = [
    strlen($pesquisa_nome) ? 'ho.nome_hosp LIKE "%' . $pesquisa_nome . '%"' : NULL,
    strlen($pesquisa_pac) ? 'pa.nome_pac LIKE "%' . $pesquisa_pac . '%"' : NULL,
    strlen($senha_fin) ? 'senha_finalizada = "' . $senha_fin . '"' : NULL,
    strlen($encerrado_cap) ? 'encerrado_cap = "' . $encerrado_cap . '"' : NULL,
    strlen($med_check) ? 'med_check = "' . $med_check . '"' : NULL,
    strlen($enf_check) ? 'enfer_check = "' . $enf_check . '"' : NULL,
    strlen($adm_check) ? 'adm_check = "' . $adm_check . '"' : NULL,
    strlen($senha_int) ? 'senha_int LIKE "%' . $senha_int . '%"' : NULL,
    strlen($data_intern_int) ? 'data_intern_int BETWEEN "' . $data_intern_int . '" AND "' . $data_intern_int_max . '"' : NULL,

    strlen($auditor) ? 'hos.fk_usuario_hosp = "' . $auditor . '"' : NULL

];

$condicoes = array_filter($condicoes);
$url = 'list_internacao_cap.php?pesquisa_nome=' . $pesquisa_nome . '&pesquisa_pac=' . $pesquisa_pac . '&senha_fin=' . $senha_fin . '&encerrado_cap=' . $encerrado_cap . '&med_check=' . $med_check . '&enf_check=' . $enf_check . '&adm_check=' . $adm_check . '&senha_int=' . $senha_int . '&data_intern_int=' . $data_intern_int . '&data_intern_int_max=' . $data_intern_int_max;
// REMOVE POSICOES VAZIAS DO FILTRO
$where = implode(' AND ', $condicoes);

$QtdTotalInt = new internacaoDAO($conn, $BASE_URL);

$qtdIntItens1 = $QtdTotalInt->selectAllInternacaoCapList($where, $order, $obLimite);

$qtdIntItens = count($qtdIntItens1);
// QUANTIDADE Internacao
$totalcasos = ceil($qtdIntItens / $limite);
$pesqInternado = null;
// PAGINACAO
$order = $ordenar;

$obPagination = new pagination($qtdIntItens, $_GET['pag'] ?? 1, $limite ?? 10);

$obLimite = $obPagination->getLimit();

// PREENCHIMENTO DO FORMULARIO COM QUERY
$order = $ordenar;
$obLimite = $obPagination->getLimit();
$query = $internacao->selectAllInternacaoCapList($where, $order, $obLimite);
// PAGINACAO
if ($qtdIntItens > $limite) {
    $paginacao = '';
    $paginas = $obPagination->getPages();
    $pagina = 1;
    $total_pages = count($paginas);

    // FUNCAO PARA CONTROLE DO NUMERO DE PAGINAS, UTILIZANDO A QUANTIDADE DE PAGINAS CALCULADAS NA VARIAVEL PAGINAS PELE METODO getPages

    function paginasAtuais($var)
    {
        $blocoAtual = isset($_GET['bl']) ? $_GET['bl'] : 0;
        return $var['bloco'] == (($blocoAtual) / 5) + 1;
    }
    $block_pages = array_filter($paginas, "paginasAtuais"); // REFERENCIA FUNCAO CRIADA ACIMA
    $first_page_in_block = reset($block_pages)["pg"];
    $last_page_in_block = end($block_pages)["pg"];
    $first_block = reset($paginas)["bloco"];
    $last_block = end($paginas)["bloco"];
    $current_block = reset($block_pages)["bloco"];
}
?>
<!-- FORMULARIO DE PESQUISAS -->
<div class="container-fluid form_container" id='main-container' style="margin-top:12px;">

    <!-- script jquery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"
        integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>

    <h4 class="page-title" style="color: #3A3A3A">Listagem - Capeantes</h4>
    <hr>
    <div>

        <div id="navbarToggleExternalContent">
            <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
            <form id="select-internacao-form" method="GET">

                <div class="form-group row">
                    <div class="form-group col-sm-2" style="padding:2px !important;padding-left:16px !important;">
                        <input class="form-control form-control-sm"
                            style="margin-top:7px; font-size:.8em; color:#878787" type=" text" name="pesquisa_nome"
                            autofocus placeholder="Selecione o Hospital" value="<?= $pesquisa_nome ?>">
                    </div>
                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <input class="form-control form-control-sm"
                            style="margin-top:7px; font-size:.8em; color:#878787" type=" text" name="pesquisa_pac"
                            placeholder="Selecione o Paciente" value="<?= $pesquisa_pac ?>">
                    </div>

                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <input class="form-control form-control-sm"
                            style="margin-top:7px; font-size:.8em; color:#878787" type="text" name="senha_int"
                            placeholder="Digite a Senha" value="<?= $pesquisa_pac ?>">
                    </div>

                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <select class="form-control form-control-sm"
                            style="margin-top:7px;font-size:.8em; color:#878787" id="limite" name="limite">
                            <option value="">Reg. por página</option>
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <select class="form-control form-control-sm"
                            style="margin-top:7px;font-size:.8em; color:#878787" id="ordenar" name="ordenar">
                            <option value="">Classificar por</option>
                            <option value="id_internacao">Internação</option>
                            <option value="id_capeante">No. capeante</option>
                            <option value="nome_pac">Senha</option>
                            <option value="nome_pac">Paciente</option>
                            <option value="nome_hosp">Hospital</option>
                            <option value="data_intern_int">Data Internação</option>
                        </select>
                    </div>
                </div>
                <div class="form-group row" style="margin-top:-20px">
                    <div class="form-group col-sm-1" style="padding:2px !important;padding-left:16px !important;">
                        <select class="form-control form-control-sm"
                            style="margin-top:7px;font-size:.8em; color:#878787" id="med_check" name="med_check">
                            <option value="">Médico </option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-1" style="padding:2px !important">
                        <select class="form-control form-control-sm"
                            style="margin-top:7px;font-size:.8em; color:#878787" id="enf_check" name="enf_check">
                            <option value="">Enf </option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-1" style="padding:2px !important">
                        <select class="form-control form-control-sm"
                            style="margin-top:7px;font-size:.8em; color:#878787" id="adm_check" name="adm_check">
                            <option value="">Adm </option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <select class="form-control form-control-sm"
                            style="margin-top:7px;font-size:.8em; color:#878787" id="encerrado_cap"
                            name="encerrado_cap">
                            <option value="">Capeante encerrado</option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <select class="form-control form-control-sm"
                            style="margin-top:7px;font-size:.8em; color:#878787" id="senha_fin" name="senha_fin">
                            <option value="">Senha finalizada</option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <input class="form-control form-control-sm" type="date"
                            style="margin-top:7px;font-size:.8em; color:#878787" name="data_intern_int"
                            placeholder="Data Internação Min" value="<?= $data_intern_int ?>">
                    </div>
                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <input class="form-control form-control-sm" type="date"
                            style="margin-top:7px;font-size:.8em; color:#878787" name="data_intern_int_max"
                            placeholder="Data Internação Max" value="<?= $data_intern_int_max ?>">
                    </div>
                    <div class="form-group col-sm-1" style="padding:2px !important" style="margin:0px 0px 20px 0px">
                        <button type="submit" class="btn btn-primary"
                            style="background-color:#5e2363;width:42px;height:32px;margin-top:7px;border-color:#5e2363"><span
                                class="material-icons" style="margin-left:-3px;margin-top:-2px;">
                                search
                            </span></button>
                    </div>

                </div>
            </form>
        </div>
        <div>
            <div id="table-content">
                <table class="table table-sm table-striped  table-hover table-condensed">
                    <thead>
                        <tr>
                            <th scope="col" style="width:4%">Reg Int</th>
                            <th scope="col" style="width:4%">Conta No.</th>
                            <th scope="col" style="width:12%">Hospital</th>
                            <th scope="col" style="width:16%">Paciente</th>
                            <th scope="col" style="width:10%">Senha</th>
                            <th scope="col" style="width:8%">Data internação</th>
                            <th scope="col" style="width:4%;">Med</th>
                            <th scope="col" style="width:4%;">Enf</th>
                            <th scope="col" style="width:4%;">Adm</th>
                            <th scope="col" style="width:4%;">Parcial</th>
                            <th scope="col" style="width:3%;">Final</th>
                            <th scope="col" style="width:3%;">Aberto</th>
                            <th scope="col" style="width:6%;">Cap Encer</th>
                            <th scope="col" style="width:6%;">Em Audit</th>
                            <?php if ($_SESSION['nivel'] > 3) : ?>
                            <th scope="col" style="width:6%;">Usuário</th>
                            <?php endif; ?>
                            <th scope="col" style="width:13%">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($query as $intern) :
                            extract($intern);
                        ?>
                        <tr style="font-size:13px">
                            <td scope="row" class="col-id"><b>
                                    <?= $intern["id_internacao"]; ?></b></td>
                            <td scope="row" class="col-id"><b>
                                    <?= $intern["id_capeante"]; ?>
                                </b></td>
                            <td scope="row" class="nome-coluna-table"><b>
                                    <?= $intern["nome_hosp"] ?></b></td>
                            <td scope="row">
                                <?= fullcare_mask_person_name_e($intern["nome_pac"] ?? "") ?>
                            </td>
                            <td scope="row">
                                <?= $intern["senha_int"] ?>
                            </td>
                            <td scope="row">
                                <?= date('d/m/Y', strtotime($intern["data_intern_int"])) ?>
                            </td>

                            <td scope="row">
                                <?php if ($intern["med_check"] === "s") { ?>
                                <a class="legenda-medico"><span id="boot-icon" class="bi bi-check-circle"
                                        style="font-size: 1.1rem; font-weight:800; color: rgb(0, 78, 86);"></span></a>
                                <?php }; ?>
                            </td>
                            <td scope="row">
                                <?php if ($intern["enfer_check"] == "s") { ?>
                                <a class="legenda-enfermagem"><span id="boot-icon" class="bi bi-check-circle"
                                        style="font-size: 1.1rem; font-weight:1000; color: rgb(234, 128, 55);"></span></a>
                                <?php }; ?>
                            </td>

                            <td scope="row">
                                <?php if ($intern["adm_check"] === "s") { ?>
                                <a class="legenda-administrativo"><span id="boot-icon" class="bi bi-check-circle"
                                        style="font-size: 1.1rem; font-weight:1000; color: rgb(25, 78, 255);"></span></a>
                                <?php }; ?>
                            </td>
                            <td scope="row">
                                <?= $intern["parcial_num"]; ?>
                            </td>
                            <td scope="row">
                                <?php if ($intern["senha_finalizada"] == "s") { ?>
                                <a class="legenda-finalizada"><span id="boot-icon" class="bi bi-briefcase"
                                        style="font-size: 1.1rem; font-weight:800; color: rgb(255, 25, 55) ;"></span></a>
                                <?php }; ?>
                            </td>

                            <td scope="row">
                                <?php if ($intern["aberto_cap"] == "s") { ?>
                                <a class="legenda-aberto"><span id="boot-icon" class="bi bi-book"
                                        style="font-size: 1.1rem; color:blue; font-weight:800"></span>
                                    <?php }; ?>
                                </a>
                            </td>

                            <td scope="row">
                                <?php if ($intern["encerrado_cap"] == "s") { ?>
                                <a class="legenda-aberto"><span id="boot-icon" class="bi bi-briefcase"
                                        style="font-size: 1.1rem; color:green; font-weight:800;"></span>
                                    <?php }; ?>
                                </a>
                            </td>
                            <td scope="row">
                                <?php if ($intern["em_auditoria_cap"] == "s") { ?>
                                <a class="legenda-em-auditoria"><span id="boot-icon" class="bi bi-pencil-square"
                                        style="font-size: 1.1rem; font-weight:800; color: orange;"></span></a>
                                <?php }; ?>
                            </td>

                            <?php
                                if ($_SESSION['nivel'] > 3) : ?>
                            <td scope="row">
                                <?= $intern["usuario_user"]; ?>
                            </td>
                            <?php endif; ?>





                            <td class="action">

                                <?php
                                    if ($intern['encerrado_cap'] <> "s")
                                        if ($intern['em_auditoria_cap'] == "s") { ?>
                                    <a class="legenda-em-auditoria"
                                    href="<?= $BASE_URL ?>cad_capeante_rah.php?id_capeante=<?= $intern['id_capeante'] ?>"><i
                                        style="color:rgb(255, 55, 25); text-decoration: none; font-size: 10px; font-weight:bold; margin-left:5px;margin-right:5px"
                                        name="type" value="capeante" class="bi bi-file-text"> Em análise</i></a>
                                <?php } else { ?>
                                    <a class="legenda-iniciar"
                                    href="<?= $BASE_URL ?>cad_capeante_rah.php?id_capeante=<?= $intern['id_capeante'] ?>"><i
                                        style="color:rgb(25, 78, 255); text-decoration: none; font-size: 10px; font-weight:bold; margin-left:5px;margin-right:5px"
                                        name="type" value="capeante" class="bi bi-file-text"> Iniciar</i></a>
                                <?php }
                                    else {; ?>
                                <a class="legenda-encerrado" href="#"><i
                                        style="color:black; text-decoration: none; font-size: 10px; font-weight:bold; margin-left:5px;margin-right:5px"
                                        name="type" value="capeante" class="bi"> Encerrado</i></a>

                                <?php }; {
                                    }
                                    ?>
                                <?php
                                    $last_cape = $capeante_geral->getLastCapeanteIdByInternacao($intern['id_internacao'])['0'] ?? null;
                                    ?>
                                <?php if ($intern['encerrado_cap'] == "s" and $intern['id_capeante'] == $last_cape) { ?>
                                <a class="legenda-parcial"
                                    href="<?= $BASE_URL ?>cad_capeante_rah.php?id_internacao=<?= $intern["id_internacao"] ?>&type=create">
                                    <i style="color:green; text-decoration: none; font-size: 10px; font-weight:bold; margin-left:5px;margin-right:5px"
                                        name="type" value="capeante" class="legenda-parcial bi bi-file-text">
                                        Parcial</i></a>
                                </form>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($qtdIntItens == 0) : ?>
                        <tr>
                            <td colspan="15" scope="row" class="col-id" style='font-size:15px'>
                                Não foram encontrados registros
                            </td>
                        </tr>

                        <?php endif ?>
                    </tbody>
                </table>
                <div style="text-align:right">
                    <input type="hidden" id="qtd" value="<?php echo $qtdIntItens ?>">
                </div>
                <div style="display: flex;margin-top:20px">

                    <div class="pagination" style="margin: 0 auto;">
                        <?php if ($total_pages ?? 1 > 1) : ?>
                        <ul class="pagination">
                            <?php
                                $blocoAtual = isset($_GET['bl']) ? $_GET['bl'] : 0;
                                $paginaAtual = isset($_GET['pag']) ? $_GET['pag'] : 1;

                                ?>
                            <?php if ($current_block > $first_block) : ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadContent('<?php print $url .= '&pag=' . 1 . '&bl=' . 0 . '&limite=' . $limite . '&ordernar=' . $ordenar ?>')">

                                    <i class="fa-solid fa-angles-left"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1) : ?>
                            <li class="page-item">
                                <a class="page-link" href="#"
                                    onclick="loadContent('<?php print $url .= '&pag=' . ($paginaAtual - 1) . '&bl=' . ($blocoAtual - 5) . '&limite=' . $limite . '&ordernar=' . $ordenar ?>')">
                                    <i class="fa-solid fa-angle-left"></i> </a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++) : ?>
                            <li class="page-item <?php print ($_GET['pag'] ?? 1) == $i ? "active" : "" ?>">

                                <a class="page-link" href="#"
                                    onclick="loadContent('<?php print $url .= '&pag=' . $i . '&bl=' . $blocoAtual . '&limite=' . $limite . '&ordenar=' . $ordenar; ?>')">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($current_block < $last_block) : ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadContent('<?php print $url .= '&pag=' . ($paginaAtual + 1) . '&bl=' . ($blocoAtual + 5) . '&limite=' . $limite . '&ordernar=' . $ordenar ?>')"><i
                                        class="fa-solid fa-angle-right"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block < $last_block) : ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadContent('<?php print $url .= '&pag=' . count($paginas) . '&bl=' . ($last_block - 1) * 5 . '&limite=' . $limite . '&ordernar=' . $ordenar ?>')"><i
                                        class="fa-solid fa-angles-right"></i></a>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <?php endif; ?>
                    </div>

                    <div>
                        <p
                            style="font-size:1em; font-weight:600; font-family:var(--bs-font-sans-serif); text-align:right">
                            <?php echo "Total: " . $qtdIntItens ?>
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<script>
// ajax para submit do formulario de pesquisa
$(document).ready(function() {
    $('#select-internacao-form').submit(function(e) {
        e.preventDefault(); // Impede o comportamento padrão de enviar o formulário

        var formData = $(this).serialize(); // Serializa os dados do formulário

        $.ajax({
            url: $(this).attr('action'), // URL do formulário
            type: $(this).attr('method'), // Método do formulário (POST)
            data: formData, // Dados serializados do formulário
            success: function(response) {
                // Crie um elemento temporário para armazenar a resposta HTML
                var tempElement = document.createElement('div');
                tempElement.innerHTML = response;

                // Encontre o elemento com o ID "table-content" dentro do elemento temporário
                var tableContent = tempElement.querySelector('#table-content');
                $('#table-content').html(tableContent);
            },
            error: function() {
                $('#responseMessage').html('Ocorreu um erro ao enviar o formulário.');
            }
        });
    });
});


$(document).ready(function() {
    loadContent(
        'list_internacao_cap.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&data_inter_int=<?php print $data_intern_int ?>&med_check=&enfer_check=&pag=<?php print 1 ?>&bl=<?php print 0 ?>&limite=<?php print $limite ?>'
    );
});
</script>


<script>
$(document).ready(function() {
    $('#encerrado_cap').val('n'); // Define o valor padrão para 'n'
    // $('#select-internacao-form').submit(); // Envia o formulário automaticamente
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

<script>
src = "https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js";
</script>
<script src="./js/ajaxNav.js"></script>
<script src="./scripts/cadastro/general.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>

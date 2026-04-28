<?php

require_once("templates/header.php");

require_once("models/message.php");

include_once("models/censo.php");
include_once("dao/censoDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/censo.php");
include_once("dao/censoDao.php");

include_once("models/capeante.php");
include_once("dao/capeanteDao.php");

include_once("models/pagination.php");

$censo_geral = new censoDAO($conn, $BASE_URL);
$censos = $censo_geral->findGeral();

$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$pacientes = $pacienteDao->findGeral($limite, $inicio);

$capeante_geral = new HospitalDAO($conn, $BASE_URL);
$capeantes = $capeante_geral->findGeral($limite, $inicio);

$hospital_geral = new HospitalDAO($conn, $BASE_URL);
$hospitals = $hospital_geral->findGeral($limite, $inicio);

$patologiaDao = new patologiaDAO($conn, $BASE_URL);
$patologias = $patologiaDao->findGeral();

$censoDao = new censoDAO($conn, $BASE_URL);

$censo = new censoDAO($conn, $BASE_URL);
$where = $order = $obLimite = null;
$user = $_SESSION['id_usuario'];

?>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/listagem_padrao.css', ENT_QUOTES, 'UTF-8') ?>">
<!-- FORMULARIO DE PESQUISAS -->
<div class="container-fluid listagem-page" id="main-container">
    <div class="listagem-hero">
        <div class="listagem-hero__copy">
            <div class="listagem-kicker">Censo</div>
            <h1 class="listagem-title">Censo hospitalar</h1>
            <p class="listagem-subtitle">Consulte pacientes do censo com filtros mais limpos e leitura mais estável da grade.</p>
        </div>
    </div>
    <div class="complete-table listagem-panel">
    <div class="container" id="navbarToggleExternalContent">
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <div>
            <form action="" id="select-censo-form" method="GET">
                <?php $pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS);
                $pesqInternado = filter_input(INPUT_GET, 'pesqInternado');
                $limite = filter_input(INPUT_GET, 'limite') ?? 10;
                $pesquisa_pac = filter_input(INPUT_GET, 'pesquisa_pac', FILTER_SANITIZE_SPECIAL_CHARS);
                $ordenar = filter_input(INPUT_GET, 'ordenar');
                ?>
                <div class="form-group row filter-inline-row">
                    <div class="form-group col-sm-3">
                        <!-- <label>Pesquisa por Hospital</label> -->
                        <input class="form-control form-control-sm" type="text"
                            style="margin-top:7px;font-size:.8em; color:#878787" name="pesquisa_nome"
                            placeholder="Selecione o Hospital" value="<?= $pesquisa_nome ?>">
                    </div>
                    <div class="form-group col-sm-3">
                        <!-- <label>Pesquisa por Paciente</label> -->
                        <input class="form-control form-control-sm" type="text"
                            style="margin-top:7px;font-size:.8em; color:#878787" name="pesquisa_pac"
                            placeholder="Selecione o Paciente" value="<?= $pesquisa_pac ?>">
                    </div>

                    <div class="form-group col-sm-2">
                        <!-- <label>Internados</label> -->
                        <select class="form-control mb-3 form-control-sm"
                            style="margin-top:7px;font-size:.8em; color:#878787" id="pesqInternado"
                            name="pesqInternado">
                            <option value="">Busca por Internados</option>
                            <option value="s" <?= $pesqInternado == 's' ? 'selected' : null ?>>Sim</option>
                            <option value="n" <?= $pesqInternado == 'n' ? 'selected' : null ?>>Não</option>
                        </select>
                    </div>
                    <div class="col-sm-1" style="padding:2px !important">
                        <select class="form-control mb-3 form-control-sm" style="margin-top:7px;" id="limite"
                            name="limite">
                            <option value="">Reg por página</option>
                            <option value="5" <?= $limite == '5' ? 'selected' : null ?>>Reg por pág = 5
                            </option>
                            <option value="10" <?= $limite == '10' ? 'selected' : null ?>>Reg por pág = 10
                            </option>
                            <option value="20" <?= $limite == '20' ? 'selected' : null ?>>Reg por pág = 20
                            </option>
                            <option value="50" <?= $limite == '50' ? 'selected' : null ?>>Reg por pág = 50
                            </option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2">
                        <!-- <label>Classificar</label> -->
                        <select class="form-control mb-3 form-control-sm"
                            style="margin-top:7px;font-size:.8em; color:#878787" id="ordenar" name="ordenar">
                            <option value="">Classificar por</option>
                            <option value="id_censo" <?= $ordenar == 'id_censo' ? 'selected' : null ?>>Internação
                            </option>
                            <option value="nome_pac" <?= $ordenar == 'nome_pac' ? 'selected' : null ?>>Paciente</option>
                            <option value="nome_hosp" <?= $ordenar == 'nome_hosp' ? 'selected' : null ?>>Hospital
                            </option>
                            <option value="data_intern_int" <?= $ordenar == 'data_intern_int' ? 'selected' : null ?>>
                                Data
                                Internação</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-1" style="margin:0px 0px 20px 0px">
                        <button type="submit" class="btn btn-primary"
                            style="background-color:#5e2363;width:42px;height:32px;margin-top:7px;border-color:#5e2363"><span
                                class="material-icons" style="margin-left:-3px;margin-top:-2px;">
                                search
                            </span></button>
                    </div>
                </div>
            </form>
        </div>

        <!-- BASE DAS PESQUISAS -->
        <?php

        // validacao de lista de hospital por usuario (o nivel sera o filtro)
        if ($_SESSION['nivel'] == 3) {
            $auditor = ($_SESSION['id_usuario']);
        } else {

            $auditor = null;
        };

        //Instanciando a classe
        $QtdTotalInt = new censoDAO($conn, $BASE_URL);
        // METODO DE BUSCA DE PAGINACAO 
        $pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome');
        $limite = filter_input(INPUT_GET, 'limite') ? filter_input(INPUT_GET, 'limite') : 10;
        $pesquisa_pac = filter_input(INPUT_GET, 'pesquisa_pac');
        $ordenar = filter_input(INPUT_GET, 'ordenar') ? filter_input(INPUT_GET, 'ordenar') : 1;
        $censo_internado = '1';

        // $buscaAtivo = in_array($buscaAtivo, ['s', 'n']) ?: "";
        $condicoes = [
            strlen($pesquisa_nome) ? 'ho.nome_hosp LIKE "%' . $pesquisa_nome . '%"' : NULL,
            strlen($pesquisa_pac) ? 'nome_pac LIKE "%' . $pesquisa_pac . '%"' : NULL,
            "internado IS NULL",
        ];
        $condicoes = array_filter($condicoes);
        // REMOVE POSICOES VAZIAS DO FILTRO
        $where = implode(' AND ', $condicoes);

        // QUANTIDADE censo
        $qtdIntItens1 = $censoDao->selectAllCensoList($where);

        $qtdIntItens = count($qtdIntItens1); // total de registros
        $totalcasos = ceil($qtdIntItens / $limite);

        // PAGINACAO
        $order = $ordenar;

        $obPagination = new pagination($qtdIntItens, $_GET['pag'] ?? 1, $limite ?? 10);

        $obLimite = $obPagination->getLimit();

        // PREENCHIMENTO DO FORMULARIO COM QUERY
        $query = $censoDao->selectAllCensoList($where, $order, $obLimite);


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

        <!-- TABELA DE REGISTROS -->
        <div>
            <div id="table-content" class="listagem-table-wrap">
                <table class="table table-sm table-striped  table-hover table-condensed">
                    <thead>
                        <tr>
                            <th scope="col">Id</th>
                            <th scope="col">Hospital</th>
                            <th scope="col">Paciente</th>
                            <th scope="col">Status</th>
                            <th scope="col">Acomodação</th>
                            <th scope="col">Modo Admissão</th>
                            <th scope="col">Médico</th>
                            <th scope="col">Senha</th>
                            <th scope="col">Ações</th>
                            <?php if (count($query) > 0) : ?>
                            <th scope="col"></th>
                            <?php endif ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($query as $intern):
                            extract($query);
                        ?>
                        <tr style="font-size:15px">
                            <td scope="row" class="col-id">
                                <?= $intern["id_censo"] ?>
                            </td>

                            <td scope="row" class="nome-coluna-table">
                                <?= $intern["nome_hosp"] ?>
                            </td>
                            <td scope="row">
                                <?= $intern["nome_pac"] ?>
                            </td>
                            <td scope="row">
                                <?php
                                    if ($intern['id_internacao'] > 0) {
                                        echo "Internado";
                                    } else {

                                        echo "Não Internado";
                                    }
                                    ?>
                            </td>
                            <td scope="row">
                                <?= $intern["acomodacao_censo"] ?>
                            </td>
                            <td scope="row">
                                <?= $intern["tipo_admissao_censo"] ?>
                            </td>

                            <td scope="row">
                                <?= $intern["titular_censo"] ?>
                            </td>
                            <td scope="row">
                                <?= $intern["senha_censo"] ?>
                            </td>
                            <td class="action">
                                <div class="dropdown">
                                    <button class="btn btn-default dropdown-toggle" id="navbarScrollingDropdown"
                                        role="button" data-bs-toggle="dropdown" style="color:#5e2363"
                                        aria-expanded="false">
                                        <i class="bi bi-stack"></i>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="navbarScrollingDropdown">
                                        <li>
                                            <button class="btn btn-default" style="font-size: .9rem;"
                                                onclick="openModal('<?= $BASE_URL ?>show_censo_adm.php?id_censo=<?= $intern['id_censo'] ?>')"
                                                data-bs-toggle="modal" data-bs-target="#myModal"><i class="fas fa-eye"
                                                    style="font-size: 1rem;margin-right:5px; color: rgb(27,156, 55);"></i>Ver</button>
                                        </li>
                                        <li>
                                            <form class="d-inline-block delete-form" action="process_censo_int.php"
                                                method="get">
                                                <input type="hidden" name="type" value="create">
                                                <input type="hidden" name="id_censo" value="<?= $intern["id_censo"] ?>">
                                                <button class="btn btn-default" style="font-size: .9rem;"><i
                                                        style="font-size: 1rem;margin-right:5px; color: rgb(67, 125, 525);"
                                                        class="bi bi-door-open"></i>Internar</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form class="d-inline-block delete-form" action="del_censo.php"
                                                method="post">
                                                <input type="hidden" name="type" value="delete">
                                                <input type="hidden" name="id_censo" value="<?= $intern["id_censo"] ?>">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                <button class="btn btn-default" style="font-size: .9rem;"><i
                                                        style="font-size: 1rem;margin-right:5px; color: red;"
                                                        class="bi bi-x-circle-fill"></i>Deletar</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                            <?php if (count($query) > 0) : ?>
                            <td class="action">
                                <div class="form-check">
                                    <input onclick="addList(<?= $intern['id_censo'] ?>)" class="form-check-input"
                                        type="checkbox" id="flexCheckDefault">
                                </div>
                            </td>
                            <?php endif ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($qtdIntItens == 0): ?>
                        <tr>
                            <td colspan="15" scope="row" class="col-id" style='font-size:15px'>
                                Não foram encontrados registros
                            </td>
                        </tr>
                        <?php endif ?>
                    </tbody>
                </table>
                <div class="modal fade" id="myModal">
                    <div class="modal-dialog  modal-dialog-centered modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="page-title" style="color:white">Censo</h4>
                                <p class="page-description" style="color:white; margin-top:5px">Informações sobre o
                                    censo</p>
                            </div>
                            <div class="modal-body">
                                <div id="content-php"></div>
                            </div>

                        </div>
                    </div>
                </div>
                <div style="text-align:right">
                    <input type="hidden" id="qtd" value="<?php echo $qtdIntItens ?>">
                </div>
                <div style="display: flex;margin-top:20px">
                    <div>
                        <a class="btn btn-success styled"
                            style="background-color: #35bae1;font-family:var(--bs-font-sans-serif);box-shadow: 0px 10px 15px -3px rgba(0,0,0,0.1);border:none"
                            href="censo/novo"><i class="fa-solid fa-plus"
                                style='font-size: 1rem;margin-right:5px;'></i>Novo lançamento</a>
                    </div>
                    <div>
                        <a onclick="sendIdListToPHP()" id="submitInter" class="btn btn-success styled"
                            style="color:white;margin-left:20px;background-color: #5e2363;font-family:var(--bs-font-sans-serif);box-shadow: 0px 10px 15px -3px rgba(0,0,0,0.1);border:none">Internar
                            selecionados</a>
                    </div>

                    <div class="pagination" style="margin: 0 auto;">
                        <?php if ($total_pages ?? 1 > 1): ?>
                        <ul class="pagination">
                            <?php
                                $blocoAtual = isset($_GET['bl']) ? $_GET['bl'] : 0;
                                $paginaAtual = isset($_GET['pag']) ? $_GET['pag'] : 1;
                                ?>
                            <?php if ($current_block > $first_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadContent('list_censo_adm.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print 1 ?>&bl=<?php print 0 ?>')">
                                    <i class="fa-solid fa-angles-left"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="#"
                                    onclick="loadContent('list_censo_adm.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print $paginaAtual - 1 ?>&bl=<?php print $blocoAtual - 5 ?>')">
                                    <i class="fa-solid fa-angle-left"></i></a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++): ?>
                            <li class="page-item <?php print ($_GET['pag'] ?? 1) == $i ? "active" : "" ?>">

                                <a class="page-link" href="#"
                                    onclick="loadContent('list_censo_adm.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print $i ?>&bl=<?php print $blocoAtual ?>')">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($current_block < $last_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadContent('list_censo_adm.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print $paginaAtual + 1 ?>&bl=<?php print $blocoAtual + 5 ?>')"><i
                                        class="fa-solid fa-angle-right"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block < $last_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadContent('list_censo_adm.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print count($paginas) ?>&bl=<?php print ($last_block - 1) * 5 ?>')"><i
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
    $('#select-censo-form').submit(function(e) {
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
        'list_censo_adm.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print 1 ?>&bl=<?php print 0 ?>'
    );
});
</script>


<style>
.modal-backdrop {
    display: none;

}

.modal {
    background: rgba(0, 0, 0, 0.5);

}

.modal-header {
    color: white;
    background: #35bae1;
}

/* Center the checkbox both vertically and horizontally */
.form-check {
    display: flex;
    justify-content: center;
    /* Center horizontally */
    align-items: center;
    /* Center vertically */
    height: 100%;
    /* Ensure the container takes full height of the cell */
}

/* Increase the size of the checkbox */
.form-check-input[type=checkbox] {
    width: 17px;
    /* Set your desired width */
    height: 17px;
    /* Set your desired height */
}
</style>
<script src="./js/input-estilo.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<script>
src = "https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js";
</script>
<script src="./scripts/cadastro/general.js"></script>
<script src="./js/ajaxNav.js"></script>

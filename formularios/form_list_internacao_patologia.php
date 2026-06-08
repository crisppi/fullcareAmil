<?php

require_once("templates/header.php");

require_once("models/message.php");

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/capeante.php");
include_once("dao/capeanteDao.php");

include_once("models/pagination.php");

$Internacao_geral = new internacaoDAO($conn, $BASE_URL);
$Internacaos = $Internacao_geral->findGeral();

$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$pacientes = $pacienteDao->findGeral($limite, $inicio);

$capeante_geral = new HospitalDAO($conn, $BASE_URL);
$capeantes = $capeante_geral->findGeral($limite, $inicio);

$hospital_geral = new HospitalDAO($conn, $BASE_URL);
$hospitals = $hospital_geral->findGeral($limite, $inicio);

$patologiaDao = new patologiaDAO($conn, $BASE_URL);
$patologias = $patologiaDao->findGeral();

$internacao = new internacaoDAO($conn, $BASE_URL);


?>

<!-- FORMULARIO DE PESQUISAS -->
<script src="./js/ajaxNav.js"></script>
<link rel="stylesheet" href="css/style.css">

<div class="container-fluid form_container" id='main-container' style="margin-top:-5px">

    <h4 class="page-title">Patologia - Internações</h4>

    <hr>
    <div class="complete-table">
        <div id="navbarToggleExternalContent" class="table-filters">

            <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

            <form action="" id="select-internacao-form" method="GET">
                <?php $pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS);
                $pesqInternado = filter_input(INPUT_GET, 'pesqInternado') ?: "s";
                $limite = filter_input(INPUT_GET, 'limite');
                $pesquisa_pac = filter_input(INPUT_GET, 'pesquisa_pac', FILTER_SANITIZE_SPECIAL_CHARS);
                $ordenar = filter_input(INPUT_GET, 'ordenar');
                ?>
                <div class="form-group row">
                    <div class="form-group col-sm-2" style="padding:2px !important;padding-left:16px !important;">

                        <input class="form-control form-control-sm" style="margin-top:7px;" type="text"
                            name="pesquisa_nome" placeholder="Selecione o Hospital" value="<?= $pesquisa_nome ?>">

                    </div>
                    <div class="form-group col-sm-2" style="padding:2px !important">

                        <input class="form-control form-control-sm" style="margin-top:7px;" type="text"
                            name="pesquisa_pac" placeholder="Selecione o Paciente" value="<?= $pesquisa_pac ?>">
                    </div>

                    <div class="form-group col-sm-1" style="padding:2px !important">
                        <select class="form-control mb-2 form-control-sm" style="margin-top:7px;" id="pesqInternado"
                            name="pesqInternado">
                            <option value="">Busca por Internados</option>
                            <option value="s" <?= $pesqInternado == 's' ? 'selected' : null ?>>Internados</option>
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
                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <select class="form-control mb-1 form-control-sm" style="margin-top:7px;" id="ordenar"
                            name="ordenar">
                            <option value="">Classificar por</option>
                            <option value="id_internacao" <?= $ordenar == 'id_internacao' ? 'selected' : null ?>>
                                Internação
                            </option>
                            <option value="nome_pac" <?= $ordenar == 'nome_pac' ? 'selected' : null ?>>Paciente</option>
                            <option value="nome_hosp" <?= $ordenar == 'nome_hosp' ? 'selected' : null ?>>Hospital
                            </option>
                            <option value="data_intern_int" <?= $ordenar == 'data_intern_int' ? 'selected' : null ?>>
                                Data
                                Internação</option>
                        </select>
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

        <!-- BASE DAS PESQUISAS -->
        <?php

        // validacao de lista de hospital por usuario (o nivel sera o filtro)
        if ($_SESSION['nivel'] == 3) {
            $auditor = ($_SESSION['id_usuario']);
        } else {
            $auditor = null;
        };

        //Instanciando a classe
        $QtdTotalInt = new internacaoDAO($conn, $BASE_URL);
        // METODO DE BUSCA DE PAGINACAO 
        $pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome');
        $pesqInternado = filter_input(INPUT_GET, 'pesqInternado') ?: "s";
        $limite = filter_input(INPUT_GET, 'limite') ? filter_input(INPUT_GET, 'limite') : 10;
        $pesquisa_pac = filter_input(INPUT_GET, 'pesquisa_pac');
        $ordenar = filter_input(INPUT_GET, 'ordenar') ? filter_input(INPUT_GET, 'ordenar') : 1;
        $data_intern_int = filter_input(INPUT_GET, 'data_intern_int') ?: null;
        $dias_pato = ' 1 ';

        // $buscaAtivo = in_array($buscaAtivo, ['s', 'n']) ?: "";

        $condicoes = [
            strlen($pesquisa_nome) ? 'ho.nome_hosp LIKE "%' . $pesquisa_nome . '%"' : NULL,
            strlen($pesquisa_pac) ? 'nome_pac LIKE "%' . $pesquisa_pac . '%"' : NULL,
            strlen($pesqInternado) ? 'internado_int = "' . $pesqInternado . '"' : NULL,
            strlen($data_intern_int) ? 'data_intern_int = "' . $data_intern_int . '"' : NULL,
            strlen($auditor) ? 'hos.fk_usuario_hosp = "' . $auditor . '"' : NULL,
            strlen($dias_pato) ? 'dias_pato > ' . $dias_pato . ' ' : NULL,
        ];

        // REMOVE POSICOES VAZIAS DO FILTRO
        $condicoes = array_filter($condicoes);
        // REMOVE POSICOES VAZIAS DO FILTRO
        $where = implode(' AND ', $condicoes);

        // QUANTIDADE DE ITENS
        $qtdIntItens1 = $internacao->selectAllInternacaoPatoList($where, $order ?? null, $obLimite ?? null);

        $qtdIntItens = count($qtdIntItens1);

        // PAGINACAO
        $order = $ordenar;

        $obPagination = new pagination($qtdIntItens, $_GET['pag'] ?? 1, $limite ?? 10);

        $obLimite = $obPagination->getLimit();

        // PREENCHIMENTO DO FORMULARIO COM QUERY
        $query = $internacao->selectAllInternacaoPatoList($where, $order, $obLimite);


        $condicoesPreditivo = [
            strlen($pesquisa_nome) ? 'ac.fk_patologia_int LIKE "%' . $pesquisa_nome . '%"' : NULL,
            strlen($pesquisa_pac) ? 'nome_pac LIKE "%' . $pesquisa_pac . '%"' : NULL,

        ];

        // REMOVE POSICOES VAZIAS DO FILTRO
        $condicoesPreditivo = array_filter($condicoesPreditivo);
        // REMOVE POSICOES VAZIAS DO FILTRO
        $wherePreditivo = implode(' AND ', $condicoesPreditivo);

        $preditivoPatologia = $internacao->PreditivoIntPatologAntec($wherePreditivo);

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
        <div>
            <div style="margin-top:-10px" id="table-content">
                <table class="table table-sm table-striped  table-hover table-condensed">
                    <thead>
                        <tr>
                            <th scope="col" style="width:2.5%;">Id</th>
                            <th scope="col" style="width:3%;">Internado</th>
                            <th scope="col" style="width:10%">Hospital</th>
                            <th scope="col" style="width:12%">Paciente</th>
                            <th scope="col" style="width:4%">Senha</th>
                            <th scope="col" style="width:4%">Data internação</th>
                            <th scope="col" style="width:7%">Patologia</th>
                            <th scope="col" style="width:4%">Dias Internação</th>
                            <th scope="col" style="width:4%">Meta DRG</th>
                            <th scope="col" style="width:4%">&Delta; dias</th>
                            <th scope="col" style="width:5%;">
                                Preditivo <i data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="Este campo mostra o valor preditivo da internação, baseado na patologia, antecedentes e faixa etária de internações do banco de dados, gerando uma média de internação de paciente com mesmas características."
                                    class="bi bi-eye" style="font-size: 1.2em; margin-left: 5px;"></i>
                            </th>

                            <th scope="col" style="width:5%">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hoje = date('Y-m-d');
                        $atual = new DateTime($hoje);
                        foreach ($query as $intern):
                            extract($query);

                        ?>
                        <tr style="font-size:15px">
                            <td scope="row" class="col-id">
                                <?= $intern["id_internacao"] ?>
                            </td>
                            <td scope="row" class="nome-coluna-table">
                                <?php if ($intern["internado_int"] == "s") {
                                        echo "Sim";
                                    } else {
                                        echo "Não";
                                    }; ?>
                            </td>
                            <td scope="row" class="nome-coluna-table">
                                <?= $intern["nome_hosp"] ?>
                            </td>
                            <td scope="row">
                                <?= fullcare_mask_person_name_e($intern["nome_pac"] ?? "") ?>
                            </td>
                            <td scope="row">
                                <?= $intern["senha_int"] ?>
                            </td>
                            <td scope="row">
                                <?= date('d/m/Y', strtotime($intern["data_intern_int"])) ?>
                            </td>
                            <td scope="row" style="font-weight:400">
                                <?= $intern["patologia_pat"] ?>
                            </td>

                            <td scope="row">
                                <?php
                                    $diasintern = date("Y-m-d", strtotime($intern['data_intern_int']));
                                    $dataIntern = new DateTime($diasintern);
                                    $diasIntern = $dataIntern->diff($atual);
                                    echo "<span style='font-size:1.2em; color:blue; font-weight:800;'>{$diasIntern->days}</span>";
                                    $qtdDias = $diasIntern->days;
                                    ?>
                            <td scope="row" style="font-size:1em; font-weight:800">
                                <?= $intern["dias_pato"] != '' ? $intern["dias_pato"] : "--" ?>
                            </td>
                            </td>
                            <?php
                                if ($intern["dias_pato"] != '') {
                                    $var_dias = (number_format($intern["dias_pato"]) - (number_format($qtdDias)));
                                } else {
                                    $var_dias = 0;
                                }
                                ?>
                            <td scope="row" <?php
                                                if ($var_dias >= 0) { ?>
                                style="font-size:1em;color:green; font-weight:600" <?php
                                                                                    } else { ?>
                                style="font-size:1em;color:red; font-weight:800" <?php
                                                                                    } ?> ?>
                                <?php echo $var_dias ?>
                                <?php
                                    if ($var_dias < 0) { ?>
                                <i style="font-size:larger"></i>
                                <?php } ?>
                            </td>
                            <td scope="row">
                                <?php
                                    $condicoesPreditivo = [
                                        strlen($intern["fk_patologia_int"]) ? 'ac.fk_patologia_int = ' . $intern["fk_patologia_int"] : null,
                                        strlen($intern["intern_antec_ant_int"]) ? 'an.intern_antec_ant_int = ' . $intern["intern_antec_ant_int"] : null,
                                    ];

                                    // Remove condições vazias
                                    $condicoesPreditivo = array_filter($condicoesPreditivo);

                                    // Concatena as condições com 'AND'
                                    $wherePreditivo = implode(' AND ', $condicoesPreditivo);

                                    // Passa para a função
                                    $preditivoPatologia = $internacao->PreditivoIntPatologAntec($wherePreditivo);

                                    // Transformar o valor do índice [4] em um inteiro
                                    $valorInteiro = intval($preditivoPatologia[4]);

                                    // Imprime o valor convertido
                                    echo "<span style='font-size:1.4em; color:orange; font-weight:800;'>$valorInteiro</span>";
                                    ?>
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
                                            <button class="btn btn-default"
                                                onclick="edit('<?= $BASE_URL ?>show_internacao_patologia.php?id_internacao=<?= $intern['id_internacao'] ?>')"
                                                style="font-size: .9rem;"><i class="fas fa-eye"
                                                    style="font-size: 1rem;margin-right:5px; color: rgb(27,156, 55);"></i>
                                                Ver</button>
                                        </li>
                                        <li>
                                            <?php
                                                if ($intern['internado_int'] == "s" and $intern['censo_int'] == "s") { ?>
                                            <button class="btn btn-default"
                                                onclick="edit('<?= $BASE_URL ?>cad_internacao_censo.php?id_internacao=<?= $intern['id_internacao'] ?>')"><i
                                                    name="type" value="visita" class="bi bi-file-text"
                                                    style="font-size: 1rem;margin-right:5px; color: rgb(27,156, 55);">
                                                    Rel
                                                    inicial</i></button>
                                            <?php } else { ?>
                                            <button class="btn btn-default"
                                                onclick="edit('<?= $BASE_URL ?>cad_visita.php?id_internacao=<?= $intern['id_internacao'] ?>')"
                                                style="font-size: .9rem;"><i name="type" value="visita"
                                                    class="bi bi-file-text"
                                                    style="font-size: 1rem;margin-right:5px; color: rgb(67, 125, 525);">
                                                </i>Visita</button>
                                            <?php } ?>
                                        </li>
                                        <?php if ($pesqInternado == "s") { ?>
                                        <form class="d-inline-block delete-form" action="edit_alta.php" method="get">
                                            <input type="hidden" name="type" value="alta">
                                            <input type="hidden" name="id_internacao"
                                                value="<?= $intern["id_internacao"] ?>">
                                            <button class="btn btn-default" class="delete-btn" style="font-size: .9rem;"
                                                style="font-size: .9rem;"><i class="bi bi-door-open"
                                                    style="font-size: 1rem;margin-right:5px; color: rgb(27,156, 55);">
                                                </i>Alta</button>
                                        </form>
                                        <?php }; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align:right">
                    <input type="hidden" id="qtd" value="<?php echo $qtdIntItens ?>">
                </div>
                <div style="display: flex;margin-top:20px">
                    <!-- <div class="table-new-btn">
                        <a class="btn btn-success styled"
                            style="background-color: #35bae1;font-family:var(--bs-font-sans-serif);box-shadow: 0px 10px 15px -3px rgba(0,0,0,0.1);border:none"
                            href="internacoes/nova">Nova internação</a>
                    </div> -->

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
                                    onclick="loadContent('list_internacao_patologia.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print 1 ?>&bl=<?php print 0 ?>')">
                                    <i class="fa-solid fa-angles-left"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="#"
                                    onclick="loadContent('list_internacao_patologia.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print $paginaAtual - 1 ?>&bl=<?php print $blocoAtual - 5 ?>')">
                                    <i class="fa-solid fa-angle-left"></i> </a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++): ?>
                            <li class="page-item <?php print ($_GET['pag'] ?? 1) == $i ? "active" : "" ?>">

                                <a class="page-link" href="#"
                                    onclick="loadContent('list_internacao_patologia.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print $i ?>&bl=<?php print $blocoAtual ?>')">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($current_block < $last_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadContent('list_internacao_patologia.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print $paginaAtual + 1 ?>&bl=<?php print $blocoAtual + 5 ?>')"><i
                                        class="fa-solid fa-angle-right"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block < $last_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadContent('list_internacao_patologia.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print count($paginas) ?>&bl=<?php print ($last_block - 1) * 5 ?>')"><i
                                        class="fa-solid fa-angles-right"></i></a>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <?php endif; ?>
                    </div>

                    <div class="table-counter">
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
<script src="./js/input-estilo.js"></script>


<script>
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>


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
        'list_internacao_patologia.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print 1 ?>&bl=<?php print 0 ?>'
    );
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

<script>
src = "https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js";
</script>

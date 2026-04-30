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

$internacao_geral = new internacaoDAO($conn, $BASE_URL);
$internacaos = $internacao_geral->findGeral();

$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$pacientes = $pacienteDao->findGeral($limite, $inicio);

$capeante_geral = new capeanteDAO($conn, $BASE_URL);
$capeante = $capeante_geral->findGeral($limite, $inicio);

$hospital_geral = new HospitalDAO($conn, $BASE_URL);
$hospitals = $hospital_geral->findGeral($limite, $inicio);

$patologiaDao = new patologiaDAO($conn, $BASE_URL);
$patologias = $patologiaDao->findGeral();

$internacao = new internacaoDAO($conn, $BASE_URL);

$pesqInternado = null;
$pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome');
$senha_int = filter_input(INPUT_GET, 'senha_int');
$senha_fin = filter_input(INPUT_GET, 'senha_fin') ?: null;
$med_check = filter_input(INPUT_GET, 'med_check') ?: null;
$enf_check = filter_input(INPUT_GET, 'enf_check') ?: null;
$adm_check = filter_input(INPUT_GET, 'adm_check') ?: null;
$id_internacao = filter_input(INPUT_GET, 'id_internacao') ?: null;
$id_capeante = filter_input(INPUT_GET, 'id_capeante') ?: null;
$limite = filter_input(INPUT_GET, 'limite');
$pesquisa_pac = filter_input(INPUT_GET, 'pesquisa_pac') ?: NULL;
$ordenar = filter_input(INPUT_GET, 'ordenar');
$data_intern_int = filter_input(INPUT_GET, 'data_intern_int') ?: null;
$data_intern_int_max = filter_input(INPUT_GET, 'data_intern_int_max') ?: null;

?>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/listagem_padrao.css', ENT_QUOTES, 'UTF-8') ?>">
<style>
    .listagem-page { padding: 4px 4px 14px; }
    .legacy-list-title { font-size:.96rem; margin-bottom:6px; }
    .complete-table { padding: 8px 8px 6px; border-radius:16px; border:1px solid #eee8f6; background:#fff; box-shadow:0 10px 28px -22px rgba(89,46,131,.28); }
    #table-content thead th { padding:7px 10px; font-size:.54rem; letter-spacing:.08em; }
    #table-content tbody td, #table-content tbody th { padding:6px 10px; font-size:.7rem; vertical-align:middle; }
</style>
<!-- FORMULARIO DE PESQUISAS -->
<div class="container-fluid listagem-page" id="main-container" style="margin-top:-5px">
    <h4 class="page-title legacy-list-title">Contas paradas</h4>
    <hr>
    <div id="navbarToggleExternalContent">
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <script src="./js/ajaxNav.js"></script>
        <script src="js/scriptPdf.js" defer> </script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"
            integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <form action="" id="select-internacao-form" method="GET">

            <?php
            $pesqInternado = null;
            $pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome');
            $senha_int = filter_input(INPUT_GET, 'senha_int');
            $senha_fin = "s";
            $med_check = filter_input(INPUT_GET, 'senhamed_check') ?: null;
            $enf_check = filter_input(INPUT_GET, 'senhaenf_check') ?: null;
            $adm_check = filter_input(INPUT_GET, 'senhaadm_check') ?: null;
            $limite = filter_input(INPUT_GET, 'limite');
            $pesquisa_pac = filter_input(INPUT_GET, 'pesquisa_pac');
            $ordenar = filter_input(INPUT_GET, 'ordenar');
            ?>
            <div class="form-group row legacy-filter-row">
                <div class="form-group col-sm-3" style="padding:2px !important;padding-left:16px !important;">
                    <input class="form-control form-control-sm" style="font-size:.8em; color:#878787" type="text"
                        name="pesquisa_nome" placeholder="Selecione o Hospital" value="<?= $pesquisa_nome ?>">
                </div>
                <div class="form-group col-sm-3" style="padding:2px !important">
                    <input class="form-control form-control-sm" style="font-size:.8em; color:#878787" type="text"
                        name="pesquisa_pac" placeholder="Selecione o Paciente" value="<?= $pesquisa_pac ?>">
                </div>
                <div class="form-group col-sm-2" style="padding:2px !important">
                    <input class="form-control form-control-sm" style="font-size:.8em; color:#878787" type="text"
                        name="senha_int" placeholder="Digite a Senha" value="<?= $pesquisa_pac ?>">
                </div>
                <div class="col-sm-1" style="padding:2px !important">
                    <select class="form-control mb-3 form-control-sm" id="limite" style="font-size:.8em; color:#878787"
                        name="limite">
                        <option value="">Reg por pag</option>
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
                <div class="form-group col-sm-1" style="padding:2px !important">
                    <select class="form-control mb-3 form-control-sm" style="font-size:.8em; color:#878787" id="ordenar"
                        name="ordenar">
                        <option value="">Classificar por</option>
                        <option value="id_internacao" <?= $ordenar == 'id_internacao' ? 'selected' : null ?>>Internação
                        </option>
                        <option value="nome_pac" <?= $ordenar == 'nome_pac' ? 'selected' : null ?>>Paciente</option>
                        <option value="nome_hosp" <?= $ordenar == 'nome_hosp' ? 'selected' : null ?>>Hospital</option>
                        <option value="data_intern_int" <?= $ordenar == 'data_intern_int' ? 'selected' : null ?>>Data
                            Internação</option>
                    </select>
                </div>
            </div>
            <div class="row legacy-filter-row" style="margin-top:0">
                <div class="form-group col-sm-1" style="padding:2px !important;padding-left:16px !important;">
                    <select class="form-control mb-3 form-control-sm" style="font-size:.8em; color:#878787"
                        id="med_check" name="med_check">
                        <option value="">Médico</option>
                        <option value="s" <?= $med_check == 's' ? 'selected' : null ?>>Sim</option>
                        <option value="n" <?= $med_check == 'n' ? 'selected' : null ?>>Não</option>
                        <!-- <option value="" <?= ($med_check != 's' and $med_check != 'n') ? 'selected' : null ?>>Todos
                        </option> -->
                    </select>
                </div>
                <div class="form-group col-sm-1" style="padding:2px !important">
                    <select class="form-control mb-3 form-control-sm" style="font-size:.8em; color:#878787"
                        id="enf_check" name="enf_check">
                        <option value="">Enferm</option>
                        <option value="s" <?= $enf_check == 's' ? 'selected' : null ?>>Sim</option>
                        <option value="n" <?= $enf_check == 'n' ? 'selected' : null ?>>Não</option>
                        <!-- <option value="" <?= ($enf_check != 's' and $enf_check != 'n') ? 'selected' : null ?>>Todos
                        </option> -->
                    </select>
                </div>
                <div class="form-group col-sm-1" style="padding:2px !important">
                    <select class="form-control mb-3 form-control-sm" style="font-size:.8em; color:#878787"
                        id="adm_check" name="adm_check">
                        <option value="">Adm </option>
                        <option value="s" <?= $adm_check == 's' ? 'selected' : null ?>>Sim</option>
                        <option value="n" <?= $adm_check == 'n' ? 'selected' : null ?>>Não</option>
                        <!-- <option value="" <?= ($adm_check != 's' and $adm_check != 'n') ? 'selected' : null ?>>Todos -->
                        </option>
                    </select>
                </div>
                <div class="form-group col-sm-2" style="padding:2px !important">
                    <input class="form-control form-control-sm" type="date" style="font-size:.8em; color:#878787"
                        name="data_intern_int" placeholder="Data Internação Min" value="<?= $data_intern_int ?>">
                </div>
                <div class="form-group col-sm-2" style="padding:2px !important">
                    <input class="form-control form-control-sm" type="date" style="font-size:.8em; color:#878787"
                        name="data_intern_int_max" placeholder="Data Internação Max"
                        value="<?= $data_intern_int_max ?>">
                </div>
                <div class="form-group col-sm-1 d-flex align-items-start gap-2" style="padding:2px !important">
                    <button type="submit" class="btn btn-primary btn-filtro-buscar btn-filtro-limpar-icon"
                        style="background-color:#5e2363;width:42px;height:32px;border-color:#5e2363"><span
                            class="material-icons" style="margin-left:-3px;margin-top:-2px;">
                            search
                        </span></button>
                    <a href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/list_internacao_cap_par.php', ENT_QUOTES, 'UTF-8') ?>"
                        class="btn btn-light btn-sm btn-filtro-limpar btn-filtro-limpar-icon"
                        title="Limpar filtros" aria-label="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
        </form>
    </div>
</div>
<!-- BASE DAS PESQUISAS -->
<?php

// validacao de lista de hospital por usuario (o nivel sera o filtro)
if ($_SESSION['nivel'] == 3 or $_SESSION['nivel'] == 1) {
    $auditor = ($_SESSION['id_usuario']);
} else {
    $auditor = null;
};

//Instanciando a classe
$QtdTotalInt = new internacaoDAO($conn, $BASE_URL);
// METODO DE BUSCA DE PAGINACAO 
$pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS);
$encerrado_cap = 's';
$med_check = filter_input(INPUT_GET, 'med_check', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
$enf_check = filter_input(INPUT_GET, 'enf_check', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
$adm_check = filter_input(INPUT_GET, 'adm_check', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
$limite = filter_input(INPUT_GET, 'limite') ? filter_input(INPUT_GET, 'limite') : 10;
$pesquisa_pac = filter_input(INPUT_GET, 'pesquisa_pac', FILTER_SANITIZE_SPECIAL_CHARS);
$senha_int = filter_input(INPUT_GET, 'senha_int', FILTER_SANITIZE_SPECIAL_CHARS);
$ordenar = filter_input(INPUT_GET, 'ordenar') ? filter_input(INPUT_GET, 'ordenar') : 1;
$data_intern_int = filter_input(INPUT_GET, 'data_intern_int') ?: null;
$data_intern_int_max = filter_input(INPUT_GET, 'data_intern_int_max');
if (empty($data_intern_int_max)) {
    $data_intern_int_max = date('Y-m-d'); // Formato de data compatível com SQL
}

// $buscaAtivo = in_array($buscaAtivo, ['s', 'n']) ?: "";

$condicoes = [
    strlen($pesquisa_nome) ? 'ho.nome_hosp LIKE "%' . $pesquisa_nome . '%"' : NULL,
    strlen($pesquisa_pac) ? 'pa.nome_pac LIKE "%' . $pesquisa_pac . '%"' : NULL,
    strlen($senha_int) ? 'senha_int LIKE "%' . $senha_int . '%"' : NULL,
    strlen($senha_fin) ? 'conta_parada_cap = "' . $encerrado_cap . '"' : NULL,
    strlen($med_check) ? 'med_check = "' . $med_check . '"' : NULL,
    strlen($enf_check) ? 'enfer_check = "' . $enf_check . '"' : NULL,
    strlen($adm_check) ? 'adm_check = "' . $adm_check . '"' : NULL,
    strlen($data_intern_int) ? 'data_intern_int BETWEEN "' . $data_intern_int . '" AND "' . $data_intern_int_max . '"' : NULL,
    strlen($auditor) ? 'hos.fk_usuario_hosp = "' . $auditor . '"' : NULL,

];
$condicoes = array_filter($condicoes);
// REMOVE POSICOES VAZIAS DO FILTRO

$where = implode(' AND ', $condicoes);
// QUANTIDADE InternacaoS
$qtdIntItens1 = $QtdTotalInt->QtdInternacaoCapList($where);

$qtdIntItens = ($qtdIntItens1['qtd']) ?? 0;

// PAGINACAO
$order = $ordenar;

$obPagination = new pagination($qtdIntItens, $_GET['pag'] ?? 1, $limite ?? 10);

$obLimite = $obPagination->getLimit();

// PREENCHIMENTO DO FORMULARIO COM QUERY
$query = $internacao_geral->selectAllInternacaoCapList($where, $order, $obLimite);

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
    <div id="table-content">
        <table class="table table-sm table-striped  table-hover table-condensed">
            <thead>
                <tr>
                    <th scope="col" style="width:4%">Reg</th>
                    <th scope="col" style="width:6%">Conta No.</th>
                    <th scope="col" style="width:23%">Hospital</th>
                    <th scope="col" style="width:23%">Paciente</th>
                    <th scope="col" style="width:13%">Senha</th>
                    <th scope="col" style="width:12%">Data internação</th>
                    <th scope="col" style="width:14%">Motivo</th>
                    <th scope="col" style="width:13%">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($query as $intern):
                    extract($intern);

                ?>
                <tr>
                    <td scope="row" class="col-id">
                        <?= $intern["id_internacao"]; ?>
                    </td>
                    <td scope="row" class="col-id">
                        <?= $intern["id_capeante"]; ?>
                    </td>
                    <td scope="row" class="nome-coluna-table"><b>
                            <?= $intern["nome_hosp"] ?>
                        </b></td>
                    <td scope="row">
                        <?= $intern["nome_pac"] ?>
                    </td>
                    <td scope="row">
                        <?= $intern["senha_int"] ?>
                    </td>
                    <td scope="row">
                        <?= date('d/m/Y', strtotime($intern["data_intern_int"])) ?>
                    </td>

                    <td scope="row">
                        <?= $intern["parada_motivo_cap"] ?>
                    </td>
                    <td class="action">
                        <div class="dropdown">
                            <button class="btn btn-default dropdown-toggle" id="navbarScrollingDropdown" role="button"
                                data-bs-toggle="dropdown" style="color:#5e2363" aria-expanded="false">
                                <i class="bi bi-stack"></i>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="navbarScrollingDropdown">
                                <li>
                                    <button class="dropdown-item"
                                        onclick="edit('<?= $BASE_URL ?>show_capeante.php?id_capeante=<?= $intern['id_capeante'] ?>')">
                                        <i style="color:green; margin-right:10px" class="fas fa-eye check-icon"></i> Ver
                                        Detalhes
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item"
                                        onclick="edit('<?= $BASE_URL ?>show_capeantePrt.php?id_capeante=<?= $intern['id_capeante'] ?>')">
                                        <i style="color:brown; margin-right:10px" class="bi bi-printer"></i> Imprimir
                                    </button>
                                </li>
                        </div>
                    </td>


                </tr>
                <?php endforeach; ?>
                <?php if ($qtdIntItens == 0): ?>
                <tr>
                    <td colspan="11" scope="row" class="col-id">
                        Não foram encontrados registros
                    </td>
                </tr>

                <?php endif ?>
            </tbody>
        </table>


        <!-- salvar variavel qtdIntItens no PHP para passar para JS -->
        <div style="text-align:right;margin-top:20px;">
            <input type="hidden" id="qtd" value="<?php echo $qtdIntItens ?>">
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
                        onclick="loadContent('list_internacao_cap_new.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&data_intern_int=<?php print $data_intern_int ?>&senha_int=<?php print $senha_int ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print 1 ?>&bl=<?php print 0 ?>')">
                        <i class="fa-solid fa-angles-left"></i></a>
                </li>
                <?php endif; ?>
                <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1): ?>
                <li class="page-item">
                    <a class="page-link" href="#"
                        onclick="loadContent('list_internacao_cap_new.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&data_intern_int=<?php print $data_intern_int ?>&senha_int=<?php print $senha_int ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&med_check=<?php print $med_check ?>&enf_check=<?php print $enf_check ?>&adm_check=<?php print $adm_check ?>&senha_fin=<?php print $senha_fin ?>&pag=<?php print print $paginaAtual - 1 ?>&bl=<?php print print $blocoAtual - 5 ?>')">
                        <i class="fa-solid fa-angle-left"></i> </a>
                </li>
                <?php endif; ?>

                <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++): ?>
                <li class="page-item <?php print ($_GET['pag'] ?? 1) == $i ? "active" : "" ?>">

                    <a class="page-link" href="#"
                        onclick="loadContent('list_internacao_cap_new.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&data_intern_int=<?php print $data_intern_int ?>&senha_int=<?php print $senha_int ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&med_check=<?php print $med_check ?>&enf_check=<?php print $enf_check ?>&adm_check=<?php print $adm_check ?>&senha_fin=<?php print $senha_fin ?>&pag=<?php print $i ?>&bl=<?php print $blocoAtual ?>')">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if ($current_block < $last_block): ?>
                <li class="page-item">
                    <a class="page-link" id="blocoNovo" href="#"
                        onclick="loadContent('list_internacao_cap_new.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&data_intern_int=<?php print $data_intern_int ?>&senha_int=<?php print $senha_int ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&med_check=<?php print $med_check ?>&enf_check=<?php print $enf_check ?>&adm_check=<?php print $adm_check ?>&senha_fin=<?php print $senha_fin ?>&pag=<?php print $paginaAtual + 1 ?>&bl=<?php print $blocoAtual + 5 ?>')"><i
                            class="fa-solid fa-angle-right"></i></a>
                </li>
                <?php endif; ?>
                <?php if ($current_block < $last_block): ?>
                <li class="page-item">
                    <a class="page-link" id="blocoNovo" href="#"
                        onclick="loadContent('list_internacao_cap_new.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&data_intern_int=<?php print $data_intern_int ?>&senha_int=<?php print $senha_int ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&med_check=<?php print $med_check ?>&enf_check=<?php print $enf_check ?>&adm_check=<?php print $adm_check ?>&senha_fin=<?php print $senha_fin ?>&pag=<?php print print count($paginas) ?>&bl=<?php print print ($last_block - 1) * 5 ?>')"><i
                            class="fa-solid fa-angles-right"></i></a>
                </li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>
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
// ajax para navegacao 
function loadContent(url) {
    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'html',
        success: function(data) {
            // Crie um elemento temporário para armazenar a resposta HTML
            var tempElement = document.createElement('div');
            tempElement.innerHTML = data;

            // Encontre o elemento com o ID "table-content" dentro do elemento temporário
            var tableContent = tempElement.querySelector('#table-content');
            $('#table-content').html(tableContent);
        },
        error: function() {
        }
    });
}
$(document).ready(function() {
    loadContent('list_internacao_cap_par.php?&pag=<?php print 1 ?>&bl=<?php print 0 ?>');
});
</script>
<script src="./js/input-estilo.js"></script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js">
</script>
<?php
require_once("templates/footer.php");
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js">

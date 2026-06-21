<?php

include_once("models/hospitalUser.php");
include_once("dao/hospitalUserDao.php");

include_once("models/message.php");

include_once("array_dados.php");

include_once("models/pagination.php");

// Debug simples por querystring (?debug=1)
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';
$__t0 = microtime(true);

//Instanciando a classe 
$hospitalUser = new hospitalUserDAO($conn, $BASE_URL);
$QtdTotalpac = new hospitalUserDAO($conn, $BASE_URL);
// $QtdTotalHosp = new hospitalUserDAO($conn, $BASE_URL);
$obLimite = null;
// // METODO DE BUSCA DE PAGINACAO
$busca = filter_input(INPUT_GET, 'pesquisa_nome') ? filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS) : "";
$busca_user = filter_input(INPUT_GET, 'pesquisa_user') ? filter_input(INPUT_GET, 'pesquisa_user', FILTER_SANITIZE_SPECIAL_CHARS) : "";
// $buscaAtivo = filter_input(INPUT_GET, 'ativo_user');
$limite = filter_input(INPUT_GET, 'limite') ? filter_input(INPUT_GET, 'limite') : 10;
$ordenar = filter_input(INPUT_GET, 'ordenar') ? filter_input(INPUT_GET, 'ordenar') : 1;
$QtdTotalhosp = new hospitalUserDAO($conn, $BASE_URL);
// $buscaAtivo = in_array($buscaAtivo, ['s', 'n']) ?: "";
$condicoes = [
    strlen($busca) ? '(nome_hosp LIKE "%' . $busca . '%" OR cnpj_hosp LIKE "%' . $busca . '%")' : null,
    strlen($busca_user) ? '(usuario_user LIKE "%' . $busca_user . '%" OR email_user LIKE "%' . $busca_user . '%")' : null,
    'ativo_user = "s"'
];

$condicoes = array_filter($condicoes);
$order = $ordenar;
// REMOVE POSICOES VAZIAS DO FILTRO
$where = implode(' AND ', $condicoes);

$qtdRow = $QtdTotalpac->QtdhospitalUser($where);
$qtdIntItens = (int)($qtdRow['qtd'] ?? 0); // total de registros
$order = $ordenar;

// PAGINACAO
$obPagination = new pagination($qtdIntItens, $_GET['pag'] ?? 1, $limite ?? 10);
$obLimite = $obPagination->getLimit();

// PREENCHIMENTO DO FORMULARIO COM QUERY
$query = $hospitalUser->selectAllhospitalUser($where, $order, $obLimite);

$__t1 = microtime(true);

if (!function_exists('formatCargoLabel')) {
    function formatCargoLabel(?string $cargo): string
    {
        $cargo = trim((string)$cargo);
        $map = [
            'Med_auditor' => 'Médico Auditor',
            'Enf_auditor' => 'Enfermeiro Auditor',
            'Enf_Auditor' => 'Enfermeiro Auditor',
        ];

        return $map[$cargo] ?? $cargo;
    }
}

// GETS 
// unset($_GET['pag']);
// $gets = http_build_query($_GET['pag']);


// PAGINACAO
$paginacao = '';
$paginas = $obPagination->getPages();

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
};
?>

<div class="container-fluid form_container" style="margin-top:12px;">
    <?php if ($debug): ?>
        <div class="alert alert-warning" style="font-size:0.9rem;">
            <strong>DEBUG list_hospitalUser</strong><br>
            where: <?= htmlspecialchars($where, ENT_QUOTES, 'UTF-8') ?><br>
            order: <?= htmlspecialchars((string)$order, ENT_QUOTES, 'UTF-8') ?><br>
            limit: <?= htmlspecialchars((string)$obLimite, ENT_QUOTES, 'UTF-8') ?><br>
            total: <?= (int)$qtdIntItens ?><br>
            query_count: <?= is_array($query) ? count($query) : 0 ?><br>
            tempo: <?= number_format((($__t1 ?? microtime(true)) - $__t0), 4, '.', '') ?>s
        </div>
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <div class="d-flex justify-content-between align-items-center">
        <h4 style="margin-top:8px;margin-bottom:8px" class="page-title">Distribuição de usuários por Hospital</h4>
        <div>
            <a href="exportar_excel_list_hosp_user.php" class="btn btn-success"
                style="border-radius:10px; margin-right: 10px;">Exportar para
                Excel</a>

            <button onclick="openModal('cad_hospitalUser.php')" data-bs-toggle="modal" data-bs-target="#myModal"
                class="btn btn-success"
                style="border-radius:10px;background-color: #35bae1;font-family:var(--bs-font-sans-serif);box-shadow: 0px 10px 15px -3px rgba(0,0,0,0.1);border:none">
                <i class="fa-solid fa-plus" style='font-size: 1rem;margin-right:5px;'></i>Novo Hospital/Usuário
            </button>
        </div>
    </div>
    <hr style="margin-top: 5px; margin-bottom: 10px;">
    <div class="complete-table">
        <div id="navbarToggleExternalContent" class="table-filters">
            <form id="form_pesquisa" method="GET">
                <div class="row">

                    <div class="col-sm-3" style="padding:2px !important;padding-left:16px !important;">
                        <input class="form-control form-control-sm" style="margin-top:7px;" type="text"
                            name="pesquisa_nome" placeholder="Selecione o Hospital (nome ou CNPJ)"
                            value="<?= $busca ?>">
                        <?php isset($_get['pesquisa_nome']) ? $_get['pesquisa_nome'] : ""; ?>
                    </div>
                    <div class="col-sm-3" style="padding:2px !important">
                        <input class="form-control form-control-sm" style="margin-top:7px;" type="text"
                            name="pesquisa_user" placeholder="Selecione o Usuário (nome ou email)"
                            value="<?= $busca_user ?>">
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
                    <div class="col-sm-2" style="padding:2px !important">
                        <select class="form-control mb-3 form-control-sm" style="margin-top:7px;" id="ordenar"
                            name="ordenar">
                            <option value="">Classificar por</option>
                            <option value="usuario_user" <?= $ordenar == 'usuario_user' ? 'selected' : null ?>>Usuário
                            </option>
                            <option value="nome_hosp" <?= $ordenar == 'nome_hosp' ? 'selected' : null ?>>Hospital
                            </option>
                        </select>
                    </div>
                    <div class="col-sm-1" style="padding:2px !important" style="margin:0px 0px 20px 0px">
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
                <?php
                $groupedHospitais = [];
                foreach ($query as $hospitalUserSel) {
                    $groupKey = (string)($hospitalUserSel['nome_hosp'] ?? 'Sem hospital');
                    if (!isset($groupedHospitais[$groupKey])) {
                        $groupedHospitais[$groupKey] = [];
                    }
                    $groupedHospitais[$groupKey][] = $hospitalUserSel;
                }
                ?>

                <div class="hospital-user-groups">
                    <?php foreach ($groupedHospitais as $hospitalNome => $usuariosHospital): ?>
                        <section class="hospital-user-group">
                            <div class="hospital-user-group__header">
                                <div>
                                    <div class="hospital-user-group__title"><?= htmlspecialchars((string)$hospitalNome, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="hospital-user-group__meta">
                                        <?= count($usuariosHospital) ?> usuário<?= count($usuariosHospital) !== 1 ? 's' : '' ?> vinculado<?= count($usuariosHospital) !== 1 ? 's' : '' ?>
                                    </div>
                                </div>
                            </div>

                            <div class="hospital-user-group__rows">
                                <?php foreach ($usuariosHospital as $hospitalUserSel):
                                    extract($hospitalUserSel);
                                    ?>
                                    <article class="hospital-user-card">
                                        <div class="hospital-user-card__main">
                                            <div class="hospital-user-card__name"><?= htmlspecialchars((string)$usuario_user, ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="hospital-user-card__email"><?= htmlspecialchars((string)$email_user, ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>

                                        <div class="hospital-user-card__chips">
                                            <span class="hospital-user-chip">Usuário #<?= (int)$fk_usuario_hosp ?></span>
                                            <span class="hospital-user-chip"><?= htmlspecialchars(formatCargoLabel((string)$cargo_user), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="hospital-user-chip">Nível <?= htmlspecialchars((string)$nivel_user, ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>

                                        <div class="hospital-user-card__actions fc-list-action">
                                            <a href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/usuarios/editar/' . (int) $fk_usuario_hosp, ENT_QUOTES, 'UTF-8') ?>"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Editar usuário"
                                                style="border-radius:8px; margin-right:8px;">
                                                <i class="bi bi-person-gear"></i>
                                            </a>
                                            <div class="dropdown">
                                                <button class="btn btn-default dropdown-toggle" id="navbarScrollingDropdown"
                                                    role="button" data-bs-toggle="dropdown" style="color:#5e2363"
                                                    aria-expanded="false">
                                                    <i class="bi bi-stack"></i>
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="navbarScrollingDropdown">

                                                    <li>
                                                        <button data-bs-toggle="modal" data-bs-target="#myModal"
                                                            class="btn btn-default"
                                                            onclick="openModal('<?= $BASE_URL ?>edit_hospitalUser.php?id_hospitalUser=<?= $id_hospitalUser ?>')"><i
                                                                style="font-size: 1rem;margin-right:5px;color:blue" name="type"
                                                                value="edite"
                                                                class="aparecer-acoes far fa-edit edit-icon"></i>Editar</button>
                                                    </li>
                                                    <li>
                                                        <form class="d-inline-block delete-form" action="del_hosp_user.php"
                                                            method="post">
                                                            <input type="hidden" name="type" value="delete">
                                                            <input type="hidden" name="id_hospitalUser"
                                                                value="<?= $id_hospitalUser ?>">
                                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                            <button class="btn btn-default"><i
                                                                    style="font-size: 1rem;margin-right:5px; color: red;"
                                                                    class="bi bi-x-circle-fill"></i>Deletar</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>

                    <?php if ($qtdIntItens == 0): ?>
                        <div class="hospital-user-empty">
                            Não foram encontrados registros
                        </div>
                    <?php endif ?>
                </div>
                <!-- Modal para abrir tela de cadastro -->
                <div class="modal fade" id="myModal">
                    <div class="modal-dialog  modal-dialog-centered modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="page-title" style="color:white;">Hospital</h4>
                                <p class="page-description" style="color:white; margin-top:5px">Informações
                                    sobre o Hospital</p>
                            </div>
                            <div class="modal-body">
                                <div id="content-php"></div>
                            </div>

                        </div>
                    </div>
                </div>
                <!-- salvar variavel qtdIntItens no PHP para passar para JS -->
                <div style="text-align:right">
                    <input type="hidden" id="qtd" value="<?php echo $qtdIntItens ?>">
                </div>

                <!-- paginacao que aparece abaixo da tabela -->
                <div style="display: flex;margin-top:20px">

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
                                    onclick="loadContent('list_hospitalUser.php?pesquisa_nome=<?php print $pesquisa_nome ?>&pesquisa_user=<?php print $busca_user ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print 1 ?>&bl=<?php print 0 ?>')">
                                    <i class="fa-solid fa-angles-left"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="#"
                                    onclick="loadContent('list_hospitalUser.php?pesquisa_nome=<?php print $busca ?>&pesquisa_user=<?php print $busca_user ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print $paginaAtual - 1 ?>&bl=<?php print $blocoAtual - 5 ?>')">
                                    <i class="fa-solid fa-angle-left"></i> </a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++): ?>
                            <li class="page-item <?php print ($_GET['pag'] ?? 1) == $i ? "active" : "" ?>">

                                <a class="page-link" href="#"
                                    onclick="loadContent('list_hospitalUser.php?pesquisa_nome=<?php print $busca ?>&pesquisa_user=<?php print $busca_user ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print $i ?>&bl=<?php print $blocoAtual ?>')">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($current_block < $last_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadContent('list_hospitalUser.php?pesquisa_nome=<?php print $busca ?>&pesquisa_user=<?php print $busca_user ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print $paginaAtual + 1 ?>&bl=<?php print $blocoAtual + 5 ?>')"><i
                                        class="fa-solid fa-angle-right"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block < $last_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadContent('list_hospitalUser.php?pesquisa_nome=<?php print $busca ?>&pesquisa_user=<?php print $busca_user ?>&pesquisa_pac=<?php print $pesquisa_pac ?>&pesqInternado=<?php print $pesqInternado ?>&limite_pag=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print count($paginas) ?>&bl=<?php print ($last_block - 1) * 5 ?>')"><i
                                        class="fa-solid fa-angles-right"></i></a>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <?php endif; ?>
                    </div>


                    <div class="table-counter">
                        <p
                            style="margin-bottom:25px; font-size:1em; font-weight:600; font-family:var(--bs-font-sans-serif); text-align:right">
                            <?php echo "Total: " . $qtdIntItens ?>
                        </p>
                    </div>

                </div>
            </div>

        </div>
        <div id="id-confirmacao" class="btn_acoes oculto">
            <p>Deseja deletar este Relacionamento?</p>
            <button class="btn btn-success styled" onclick=cancelar() type="button" id="cancelar"
                name="cancelar">Cancelar</button>
            <button class="btn btn-danger styled" onclick=deletar() value="default" type="button" id="deletar-btn"
                name="deletar">Deletar</button>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="<?= $BASE_URL ?>scripts/cadastro/general.js"></script>
<script>
// ajax para submit do formulario de pesquisa
$(document).ready(function() {
    $('#form_pesquisa').submit(function(e) {
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

</script>
<style>
.hospital-user-groups {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.hospital-user-group {
    border: 1px solid rgba(94, 35, 99, 0.12);
    border-radius: 18px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 243, 251, 0.98));
    box-shadow: 0 12px 25px -18px rgba(94, 35, 99, 0.35);
    overflow: hidden;
}

.hospital-user-group__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 10px 18px 9px;
    background: #e8d8ef;
    color: #4f2d5a;
    border-bottom: 1px solid rgba(94, 35, 99, 0.14);
    border-left: 5px solid #7b4d8a;
}

.hospital-user-group__title {
    font-size: 0.9rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    text-transform: none;
    line-height: 1.35;
}

.hospital-user-group__meta {
    font-size: 0.8rem;
    opacity: 0.9;
    font-weight: 400;
    color: #7a6a86;
}

.hospital-user-group__rows {
    display: flex;
    flex-direction: column;
}

.hospital-user-card {
    display: grid;
    grid-template-columns: minmax(260px, 1.5fr) minmax(250px, 1.2fr) auto;
    gap: 14px;
    align-items: center;
    padding: 14px 18px;
    border-top: 1px solid rgba(94, 35, 99, 0.08);
}

.hospital-user-card:nth-child(odd) {
    background: rgba(255, 255, 255, 0.85);
}

.hospital-user-card:nth-child(even) {
    background: rgba(239, 232, 244, 0.7);
}

.hospital-user-card__name {
    font-size: 0.98rem;
    font-weight: 700;
    color: #2b2230;
}

.hospital-user-card__email {
    margin-top: 2px;
    font-size: 0.9rem;
    color: #64556f;
    word-break: break-word;
}

.hospital-user-card__chips {
    display: grid;
    grid-template-columns: repeat(3, minmax(132px, 148px));
    gap: 8px;
    justify-content: start;
}

.hospital-user-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 38px;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(94, 35, 99, 0.08);
    color: #5e2363;
    font-size: 0.84rem;
    font-weight: 700;
    border: 1px solid rgba(94, 35, 99, 0.12);
    text-align: center;
}

.hospital-user-card__actions {
    display: flex;
    justify-content: flex-end;
}

.hospital-user-empty {
    padding: 20px;
    text-align: center;
    border: 1px dashed rgba(94, 35, 99, 0.2);
    border-radius: 16px;
    color: #6f617a;
    background: rgba(255, 255, 255, 0.85);
}

@media (max-width: 991.98px) {
    .hospital-user-card {
        grid-template-columns: 1fr;
    }

    .hospital-user-card__chips {
        grid-template-columns: repeat(auto-fit, minmax(132px, 1fr));
    }

    .hospital-user-card__actions {
        justify-content: flex-start;
    }
}

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
</style>

<script src="./js/input-estilo.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

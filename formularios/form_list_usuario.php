<?php
include_once("globals.php");
include_once("models/usuario.php");
include_once("models/message.php");
include_once("dao/usuarioDao.php");
include_once("templates/header.php");
include_once("array_dados.php");

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

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = (string)$_SESSION['csrf'];

// Debug simples por querystring (?debug=1)
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';
$__t0 = microtime(true);

//Instanciando a classe
$usuarioDAO = new UserDAO($conn, $BASE_URL);
$QtdTotalUser = new UserDAO($conn, $BASE_URL);

// METODO DE BUSCA DE PAGINACAO
$usuario = filter_input(INPUT_GET, 'pesquisa_nome');
$pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome');
$busca = $pesquisa_nome ?? '';
$cargo = filter_input(INPUT_GET, 'cargo');
$depto = filter_input(INPUT_GET, 'depto');
$buscaAtivo = filter_input(INPUT_GET, 'ativo_user');
$limite = filter_input(INPUT_GET, 'limite') ? filter_input(INPUT_GET, 'limite') : 10;
$ordenar = filter_input(INPUT_GET, 'ordenar') ? filter_input(INPUT_GET, 'ordenar') : 1;

$order = null;
$obLimite = null;
// $pag = null;

$condicoes = [
    strlen($usuario) ? 'usuario_user LIKE "%' . $usuario . '%"' : null,
    strlen($cargo) ? 'cargo_user LIKE "%' . $cargo . '%"' : null,
    strlen($depto) ? 'depto_user LIKE "%' . $depto . '%"' : null,
    strlen($buscaAtivo) ? 'ativo_user = "' . $buscaAtivo . '"' : null
];
$condicoes = array_filter($condicoes);
// REMOVE POSICOES VAZIAS DO FILTRO
$where = implode(' AND ', $condicoes);
// print_r($condicoes);
$qtdIntItens = (int) $QtdTotalUser->countUsuario($where);

$order = $ordenar;
// PAGINACAO
$obPagination = new pagination($qtdIntItens, $_GET['pag'] ?? 1, $limite ?? 10);
$obLimite = $obPagination->getLimit();

// PREENCHIMENTO DO FORMULARIO COM QUERY
$query = $usuarioDAO->selectAllUsuario($where, $order, $obLimite);

$__t1 = microtime(true);

// PAGINACAO
$paginacao = '';
$paginas = $obPagination->getPages();
$paginaAtual = isset($_GET['pag']) ? $_GET['pag'] : 1;
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
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/listagem_padrao.css', ENT_QUOTES, 'UTF-8') ?>">

<!--tabela evento-->
<div class="container-fluid form_container listagem-page" style="margin-top:18px;">
    <?php if ($debug): ?>
        <div class="alert alert-warning" style="font-size:0.9rem;">
            <strong>DEBUG list_usuario</strong><br>
            where: <?= htmlspecialchars($where, ENT_QUOTES, 'UTF-8') ?><br>
            order: <?= htmlspecialchars((string)$order, ENT_QUOTES, 'UTF-8') ?><br>
            limit: <?= htmlspecialchars((string)$obLimite, ENT_QUOTES, 'UTF-8') ?><br>
            total: <?= (int)$qtdIntItens ?><br>
            query_count: <?= is_array($query) ? count($query) : 0 ?><br>
            tempo: <?= number_format((($__t1 ?? microtime(true)) - $__t0), 4, '.', '') ?>s
        </div>
    <?php endif; ?>
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="./scripts/cadastro/general.js"></script>

    <div class="listagem-hero">
            <div class="listagem-hero__copy">
                <div class="listagem-kicker">Cadastros</div>
                <h1 class="listagem-title">Usuários</h1>
            </div>
        <div class="listagem-hero__actions">
            <a href="<?= $BASE_URL ?>cad_usuario.php"
                class="btn listagem-btn-top listagem-btn-top--blue">
                <i class="bi bi-person-plus-fill listagem-btn-top__icon" aria-hidden="true"></i>
                <span>Novo Usuário</span>
            </a>
        </div>
    </div>
    <div class="complete-table">
        <div id="navbarToggleExternalContent" class="table-filters">
            <div class="row">
                <form id="form_pesquisa" method="GET">
                    <div class="row">

                        <div class="col-sm-3" style="padding:2px !important;padding-left:16px !important;">
                            <input style="margin-top:7px;" class="form-control form-control-sm" type="text"
                                name="pesquisa_nome" placeholder="Selecione o Usuário" value="<?= $busca ?>">
                        </div>
                        <div class="col-sm-2" style="padding:2px !important">
                            <input style="margin-top:7px;" class="form-control form-control-sm" type="text" name="cargo"
                                placeholder="Selecione o Cargo" value="<?= $cargo ?>">
                        </div>
                        <div class="col-sm-2" style="padding:2px !important">
                            <input style="margin-top:7px;" class="form-control form-control-sm" type="text" name="depto"
                                placeholder="Selecione o Depto" value="<?= $depto ?>">
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
                            <select class="form-control mb-3 form-control-sm"
                                style="margin-top:7px;font-size:.8em; color:#878787" style="font-size:0.6em"
                                id="ordenar" name="ordenar">
                                <option value="">Classificar por</option>
                                <option value="id_usuario" <?= $ordenar == 'id_usuario' ? 'selected' : null ?>>ID
                                    Usuário
                                </option>
                                <option value="usuario_user" <?= $ordenar == 'usuario_user' ? 'selected' : null ?>>
                                    Usuário
                                </option>
                                <option value="cargo_user" <?= $ordenar == 'cargo_user' ? 'selected' : null ?>>Cargo
                                </option>
                                <option value="depto_user" <?= $ordenar == 'depto_user' ? 'selected' : null ?>>Depto
                                </option>
                                <option value="nivel_user" <?= $ordenar == 'nivel_user' ? 'selected' : null ?>>Nível
                                </option>

                            </select>
                        </div>
                        <div class="col-sm-2" style="padding:2px !important">
                            <div class="filtro-acoes">
                                <button type="submit" class="btn btn-primary btn-filtro-buscar" title="Buscar">
                                    <i class="bi bi-search"></i>
                                </button>
                                <button type="button" id="btnLimparFiltro" class="btn btn-light btn-filtro-limpar"
                                    title="Limpar filtros" aria-label="Limpar filtros">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div>
            <div id="table-content">
                <!-- <div> -->
                <!-- <h6 class="page-title">Relação de usuários</h6> -->
                <!-- </div> -->
                <div class="table-responsive table-responsive-page">
                    <table class="table table-sm table-striped table-hover table-condensed">
                        <thead>
                            <tr>
                                <th scope="col">Id</th>
                                <th scope="col">Usuário</th>
                                <th scope="col">CPF</th>
                                <th scope="col">Endereço</th>
                                <th scope="col">Cargo</th>
                                <th scope="col">Depto</th>
                                <th scope="col">Nível</th>
                                <th scope="col">Email</th>
                                <th scope="col">Telefone</th>
                                <th scope="col">Ativo</th>
                                <th scope="col" width="8%">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php

                        foreach ($query as $usuario):
                            extract($usuario);

                            if (strlen($cpf_user) > 0) {
                                $cpf_format = substr($cpf_user, 0, 3) . '.' .
                                    substr($cpf_user, 3, 3) . '.' .
                                    substr($cpf_user, 6, 3) . '-' .
                                    substr($cpf_user, 9, 2);
                            } else {
                                $cpf_format = null;
                            }

                            if (strlen($telefone01_user) > 0) {

                                $telefone01_format = '(' .
                                    substr($telefone01_user, 0, 2) . ') ' .
                                    substr($telefone01_user, 2, 4) . '-' .
                                    substr($telefone01_user, 6, 9);
                            } else {
                                $telefone01_format = null;
                            }

                        ?>
                        <tr style='font-size:15px'>
                            <td scope="row" class="col-id">
                                <?= $id_usuario ?>
                            </td>
                            <td scope="row" class="nome-coluna-table">
                                <?= $usuario_user ?>
                            </td>
                            <td scope="row" class="nome-coluna-table">
                                <?= $cpf_format ?>
                            </td>
                            <td scope="row" class="nome-coluna-table">
                                <?= $endereco_user ?>
                            </td>
                            <td scope="row" class="nome-coluna-table">
                                <?= htmlspecialchars(formatCargoLabel($cargo_user), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td scope="row" class="nome-coluna-table">
                                <?= $depto_user ?>
                            </td>
                            <td scope="row" class="nome-coluna-table">
                                <?= $nivel_user ?>
                            </td>
                            <td scope="row" class="nome-coluna-table user-email-cell">
                                <?= htmlspecialchars(mb_strtolower((string)$email_user, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td scope="row" class="nome-coluna-table">
                                <?= $telefone01_format ?>
                            </td>
                            <td scope="row" class="nome-coluna-table">
                                <?= $ativo_user ?>
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
                                            <a class="dropdown-item" style="font-size: .9rem;"
                                                href="<?= $BASE_URL ?>show_usuario.php?id_usuario=<?= $id_usuario ?>">
                                                <i class="bi bi-eye"
                                                    style="font-size:1rem;margin-right:8px;color:#16a34a;"></i>Ver</a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" style="font-size: .9rem;"
                                                href="<?= $BASE_URL ?>edit_usuario.php?id_usuario=<?= $id_usuario ?>">
                                                <i class="bi bi-pencil-square" style="font-size:1rem;margin-right:8px;color:#3b82f6;"></i>Editar</a>
                                        </li>
                                        <li>

                                            <button onclick="resetSenha('<?= $id_usuario ?>')" class="dropdown-item"
                                                style="font-size: .9rem;"><i
                                                    class="bi bi-arrow-clockwise" style="font-size:1rem;margin-right:8px;color:purple;"></i>Resetar
                                                Senha</button>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                                <a class="page-link" id="blocoNovo"
                                    href="list_usuario.php?pesquisa_nome=<?php print $pesquisa_nome ?>&cargo=<?php print $cargo ?>&depto=<?php print $depto ?>&ativo_user=<?php print $buscaAtivo ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print 1 ?>&bl=<?php print 0 ?>">
                                    <i class="fa-solid fa-angles-left"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1): ?>
                            <li class="page-item">
                                <a class="page-link"
                                    href="list_usuario.php?pesquisa_nome=<?php print $pesquisa_nome ?>&cargo=<?php print $cargo ?>&depto=<?php print $depto ?>&ativo_user=<?php print $buscaAtivo ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print $paginaAtual - 1 ?>&bl=<?php print $blocoAtual - 5 ?>">
                                    <i class="fa-solid fa-angle-left"></i> </a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++): ?>
                            <li class="page-item <?php print ($_GET['pag'] ?? 1) == $i ? "active" : "" ?>">

                                <a class="page-link"
                                    href="list_usuario.php?pesquisa_nome=<?php print $pesquisa_nome ?>&cargo=<?php print $cargo ?>&depto=<?php print $depto ?>&ativo_user=<?php print $buscaAtivo ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print $i ?>&bl=<?php print $blocoAtual ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($current_block < $last_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo"
                                    href="list_usuario.php?pesquisa_nome=<?php print $pesquisa_nome ?>&cargo=<?php print $cargo ?>&depto=<?php print $depto ?>&ativo_user=<?php print $buscaAtivo ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print $paginaAtual + 1 ?>&bl=<?php print $blocoAtual + 5 ?>"><i
                                        class="fa-solid fa-angle-right"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block < $last_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo"
                                    href="list_usuario.php?pesquisa_nome=<?php print $pesquisa_nome ?>&cargo=<?php print $cargo ?>&depto=<?php print $depto ?>&ativo_user=<?php print $buscaAtivo ?>&limite=<?php print $limite ?>&ordenar=<?php print $ordenar ?>&pag=<?php print count($paginas) ?>&bl=<?php print ($last_block - 1) * 5 ?>"><i
                                        class="fa-solid fa-angles-right"></i></a>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <?php endif; ?>
                    </div>

                    <div class="table-counter">
                        <p
                            style="margin-bottom:25px;font-size:1em; font-weight:600; font-family:var(--bs-font-sans-serif); text-align:right">
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

$(document).ready(function() {
    $('#btnLimparFiltro').on('click', function() {
        const $form = $('#form_pesquisa');
        $form[0].reset();
        $form.find('input[type="text"]').val('');
        $form.find('select').prop('selectedIndex', 0);
        window.location.href = 'list_usuario.php';
    });
});


function resetSenha(id_user, evt) {
    if (evt && typeof evt.preventDefault === 'function') {
        evt.preventDefault();
    }

    // Verifica se o id_user é válido
    if (!id_user) {
        console.error("ID do usuário não fornecido.");
        $('#responseMessage').html('ID do usuário inválido.');
        return;
    }

    // Dados a serem enviados para o backend
    const formData = {
        id: id_user,
        csrf: <?= json_encode($csrfToken) ?>
    };

    // Faz a requisição AJAX
    $.ajax({
        url: '<?= $BASE_URL ?>process_reset_senha.php',
        type: 'POST',
        dataType: 'json',
        data: formData,
        success: function(response) {
            if (response && response.success) {
                const temp = response.temporary_password ?
                    `<br><strong>Senha temporária:</strong> ${response.temporary_password}` : '';
                $('#responseMessage').html(`Senha resetada com sucesso.${temp}`);
                return;
            }
            $('#responseMessage').html((response && response.message) ?
                response.message : 'Falha ao resetar senha.');
        },
        error: function(xhr, status, error) {
            console.error("Erro:", error);
            $('#responseMessage').html('Ocorreu um erro ao processar a solicitação.');
        }
    });
}


// carregamento inicial já vem do servidor, não precisa de AJAX aqui
</script>
<style>
.table-responsive-page {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
}

.complete-table {
    width: 100%;
}

.filtro-acoes {
    margin-top: 7px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-filtro-buscar,
.btn-filtro-limpar {
    width: 42px;
    min-width: 42px;
    max-width: 42px;
    height: 42px;
    min-height: 42px;
    max-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
    border-radius: 12px;
    flex: 0 0 42px;
}

.btn-filtro-buscar {
    background-color: #5e2363;
    border-color: #5e2363;
    box-shadow: 0 10px 18px rgba(94, 35, 99, 0.18);
}

.btn-filtro-limpar {
    color: #8b5a7a;
    border: 1px solid #eadff3;
    background: linear-gradient(180deg, #fff 0%, #f8f2fd 100%);
    box-shadow: 0 8px 16px rgba(94, 35, 99, 0.08);
}

.btn-filtro-limpar:hover {
    color: #7a294f !important;
    border-color: #e1bfd2 !important;
    background: linear-gradient(180deg, #fff9fc 0%, #f8eaf1 100%) !important;
    box-shadow: 0 10px 18px rgba(94, 35, 99, 0.12);
}

.btn-filtro-limpar:hover i {
    color: inherit !important;
}

.btn-filtro-buscar i,
.btn-filtro-limpar i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    font-size: 1rem;
    line-height: 1;
    margin: 0;
}

.user-email-cell {
    text-transform: none !important;
    text-transform: lowercase !important;
}
</style>
<script src="./js/input-estilo.js"></script>


<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<script>
src = "https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js";
</script>

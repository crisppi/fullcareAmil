<?php

if (!defined("FLOW_LOGGER_AUTO_V1")) {
    define("FLOW_LOGGER_AUTO_V1", 1);
    @require_once(__DIR__ . "/utils/flow_logger.php");
    if (function_exists("flowLogStart") && function_exists("flowLog")) {
        $__flowCtxAuto = flowLogStart(basename(__FILE__, ".php"), [
            "type" => $_POST["type"] ?? $_GET["type"] ?? null,
            "method" => $_SERVER["REQUEST_METHOD"] ?? null,
        ]);
        register_shutdown_function(function () use ($__flowCtxAuto) {
            $err = error_get_last();
            if ($err && in_array(($err["type"] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                flowLog($__flowCtxAuto, "shutdown.fatal", "ERROR", [
                    "message" => $err["message"] ?? null,
                    "file" => $err["file"] ?? null,
                    "line" => $err["line"] ?? null,
                ]);
            }
            flowLog($__flowCtxAuto, "request.finish", "INFO");
        });
    }
}


require_once("globals.php");
require_once("db.php");
require_once("models/estipulante.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("dao/estipulanteDao.php");
require_once("utils/audit_logger.php");

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$estipulanteDao = new estipulanteDAO($conn, $BASE_URL);

function normalizeDigitsEst(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?? '';
}

function postArrayEst(string $key): array
{
    $values = filter_input(INPUT_POST, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
    return is_array($values) ? $values : [];
}

function insertEstipulanteRelatedRows(PDO $conn, int $idEstipulante, array $enderecos, array $telefones, array $contatos): void
{
    if ($idEstipulante <= 0) return;

    $stmtEnd = $conn->prepare("INSERT INTO tb_estipulante_endereco (fk_estipulante, tipo_endereco, cep_endereco, endereco_endereco, numero_endereco, bairro_endereco, cidade_endereco, estado_endereco, complemento_endereco, principal_endereco, ativo_endereco, data_create_endereco) VALUES (:fk_estipulante, :tipo_endereco, :cep_endereco, :endereco_endereco, :numero_endereco, :bairro_endereco, :cidade_endereco, :estado_endereco, :complemento_endereco, :principal_endereco, 's', NOW())");
    foreach ($enderecos as $item) {
        $logradouro = trim((string) ($item['endereco'] ?? ''));
        if ($logradouro === '') continue;
        $stmtEnd->execute([
            ':fk_estipulante' => $idEstipulante,
            ':tipo_endereco' => trim((string) ($item['tipo'] ?? '')),
            ':cep_endereco' => normalizeDigitsEst((string) ($item['cep'] ?? '')),
            ':endereco_endereco' => $logradouro,
            ':numero_endereco' => trim((string) ($item['numero'] ?? '')),
            ':bairro_endereco' => trim((string) ($item['bairro'] ?? '')),
            ':cidade_endereco' => trim((string) ($item['cidade'] ?? '')),
            ':estado_endereco' => trim((string) ($item['estado'] ?? '')),
            ':complemento_endereco' => trim((string) ($item['complemento'] ?? '')),
            ':principal_endereco' => ((string) ($item['principal'] ?? 'n') === 's') ? 1 : 0,
        ]);
    }

    $stmtTel = $conn->prepare("INSERT INTO tb_estipulante_telefone (fk_estipulante, tipo_telefone, numero_telefone, ramal_telefone, contato_telefone, principal_telefone, ativo_telefone, data_create_telefone) VALUES (:fk_estipulante, :tipo_telefone, :numero_telefone, :ramal_telefone, :contato_telefone, :principal_telefone, 's', NOW())");
    foreach ($telefones as $item) {
        $numero = normalizeDigitsEst((string) ($item['numero'] ?? ''));
        if ($numero === '') continue;
        $stmtTel->execute([
            ':fk_estipulante' => $idEstipulante,
            ':tipo_telefone' => trim((string) ($item['tipo'] ?? '')),
            ':numero_telefone' => $numero,
            ':ramal_telefone' => trim((string) ($item['ramal'] ?? '')),
            ':contato_telefone' => trim((string) ($item['contato'] ?? '')),
            ':principal_telefone' => ((string) ($item['principal'] ?? 'n') === 's') ? 1 : 0,
        ]);
    }

    $stmtCont = $conn->prepare("INSERT INTO tb_estipulante_contato (fk_estipulante, nome_contato, cargo_contato, setor_contato, email_contato, telefone_contato, principal_contato, ativo_contato, data_create_contato) VALUES (:fk_estipulante, :nome_contato, :cargo_contato, :setor_contato, :email_contato, :telefone_contato, :principal_contato, 's', NOW())");
    foreach ($contatos as $item) {
        $nome = trim((string) ($item['nome'] ?? ''));
        if ($nome === '') continue;
        $stmtCont->execute([
            ':fk_estipulante' => $idEstipulante,
            ':nome_contato' => $nome,
            ':cargo_contato' => trim((string) ($item['cargo'] ?? '')),
            ':setor_contato' => trim((string) ($item['setor'] ?? '')),
            ':email_contato' => trim((string) ($item['email'] ?? '')),
            ':telefone_contato' => normalizeDigitsEst((string) ($item['telefone'] ?? '')),
            ':principal_contato' => ((string) ($item['principal'] ?? 'n') === 's') ? 1 : 0,
        ]);
    }
}

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");
$typeDel = filter_input(INPUT_POST, "typeDel");


if ($type === "create") {

    $tipo = ($_FILES['logo_est']['type']);
    $tamanho_perm = 1024 * 1024 * 2;
    $size = $_FILES['logo_est']['size'];

    $erros = "";

    if (($_FILES['logo_est']['size']) > $tamanho_perm) {
        // codigo de erro caso arquivo maior que permitido
    } else {
        // condicao caso arquivo permitido

        $tipo = ($_FILES['logo_est']['type']);
        $arquivo = ($_FILES['logo_est']['name']);
        $temp_arq = ($_FILES['logo_est']['tmp_name']);
        $size = ($_FILES['logo_est']['size']);
        $pasta = "uploads";

        move_uploaded_file($temp_arq, $pasta . "/" . $arquivo);
        // Receber os dados dos inputs
        $nome_est = filter_input(INPUT_POST, "nome_est", FILTER_SANITIZE_SPECIAL_CHARS);
        $nome_est = strtoupper($nome_est);
        $endereco_est = filter_input(INPUT_POST, "endereco_est", FILTER_SANITIZE_SPECIAL_CHARS);

        $email01_est = filter_input(INPUT_POST, "email01_est", FILTER_SANITIZE_EMAIL);
        $email01_est = strtolower($email01_est);

        $email02_est = filter_input(INPUT_POST, "email02_est", FILTER_SANITIZE_EMAIL);
        $email02_est = strtolower($email02_est);

        $cidade_est = filter_input(INPUT_POST, "cidade_est", FILTER_SANITIZE_SPECIAL_CHARS);
        $bairro_est = filter_input(INPUT_POST, "bairro_est", FILTER_SANITIZE_SPECIAL_CHARS);
        $estado_est = filter_input(INPUT_POST, "estado_est", FILTER_SANITIZE_SPECIAL_CHARS);

        $cnpj_est = filter_input(INPUT_POST, "cnpj_est", FILTER_SANITIZE_SPECIAL_CHARS);
        $cnpj_est = str_replace('/', '', $cnpj_est);
        $cnpj_est = str_replace('-', '', $cnpj_est);
        $cnpj_est = str_replace('.', '', $cnpj_est);

        $telefone01_est = filter_input(INPUT_POST, "telefone01_est", FILTER_SANITIZE_SPECIAL_CHARS);
        $telefone01_est = str_replace('-', '', $telefone01_est);
        $telefone01_est = str_replace('(', '', $telefone01_est);
        $telefone01_est = str_replace(') ', '', $telefone01_est);

        $telefone02_est = filter_input(INPUT_POST, "telefone02_est", FILTER_SANITIZE_SPECIAL_CHARS);
        $telefone02_est = str_replace('-', '', $telefone02_est);
        $telefone02_est = str_replace('(', '', $telefone02_est);
        $telefone02_est = str_replace(') ', '', $telefone02_est);

        $data_create_est = filter_input(INPUT_POST, "data_create_est");
        $usuario_create_est = filter_input(INPUT_POST, "usuario_create_est");
        $fk_usuario_est = filter_input(INPUT_POST, "fk_usuario_est");
        $deletado_est = filter_input(INPUT_POST, "deletado_est");

        $nome_contato_est = filter_input(INPUT_POST, "nome_contato_est");
        $nome_responsavel_est = filter_input(INPUT_POST, "nome_responsavel_est");
        $email_contato_est = filter_input(INPUT_POST, "email_contato_est");
        $email_responsavel_est = filter_input(INPUT_POST, "email_responsavel_est");
        $telefone_contato_est = filter_input(INPUT_POST, "telefone_contato_est");
        $telefone_responsavel_est = filter_input(INPUT_POST, "telefone_responsavel_est");
        $cep_est = filter_input(INPUT_POST, "cep_est");

        $numero_est = filter_input(INPUT_POST, "numero_est");
        $logo_est = $arquivo;

        $estipulante = new estipulante();

        // Validação mínima de dados
        if (!empty($nome_est)) {

            $estipulante->nome_est = $nome_est;
            $estipulante->endereco_est = $endereco_est;
            $estipulante->bairro_est = $bairro_est;

            $estipulante->email02_est = $email02_est;
            $estipulante->email01_est = $email01_est;

            $estipulante->cidade_est = $cidade_est;
            $estipulante->estado_est = $estado_est;
            $estipulante->cnpj_est = $cnpj_est;
            $estipulante->telefone01_est = $telefone01_est;
            $estipulante->telefone02_est = $telefone02_est;

            $estipulante->numero_est = $numero_est;
            $estipulante->fk_usuario_est = $fk_usuario_est;
            $estipulante->logo_est = $logo_est;
            $estipulante->cep_est = $cep_est;

            $estipulante->data_create_est = $data_create_est;
            $estipulante->usuario_create_est = $usuario_create_est;
            $estipulante->fk_usuario_est = $fk_usuario_est;
            $estipulante->deletado_est = $deletado_est;

            $estipulante->nome_contato_est = $nome_contato_est;
            $estipulante->nome_responsavel_est = $nome_responsavel_est;
            $estipulante->email_contato_est = $email_contato_est;
            $estipulante->email_responsavel_est = $email_responsavel_est;
            $estipulante->telefone_contato_est = $telefone_contato_est;
            $estipulante->telefone_responsavel_est = $telefone_responsavel_est;


            $estipulanteDao->create($estipulante);

            $idNovo = (int) $conn->lastInsertId();
            $enderecos = [];
            $endTipo = postArrayEst('end_tipo');
            $endCep = postArrayEst('end_cep');
            $endLog = postArrayEst('end_logradouro');
            $endNum = postArrayEst('end_numero');
            $endBairro = postArrayEst('end_bairro');
            $endCidade = postArrayEst('end_cidade');
            $endEstado = postArrayEst('end_estado');
            $endComp = postArrayEst('end_complemento');
            $endPrin = postArrayEst('end_principal');
            $endCount = max(count($endTipo), count($endLog));
            for ($i = 0; $i < $endCount; $i++) {
                $enderecos[] = ['tipo' => $endTipo[$i] ?? '', 'cep' => $endCep[$i] ?? '', 'endereco' => $endLog[$i] ?? '', 'numero' => $endNum[$i] ?? '', 'bairro' => $endBairro[$i] ?? '', 'cidade' => $endCidade[$i] ?? '', 'estado' => $endEstado[$i] ?? '', 'complemento' => $endComp[$i] ?? '', 'principal' => $endPrin[$i] ?? 'n'];
            }
            if (empty($enderecos) && !empty($endereco_est)) {
                $enderecos[] = ['tipo' => 'Principal', 'cep' => $cep_est, 'endereco' => $endereco_est, 'numero' => $numero_est, 'bairro' => $bairro_est, 'cidade' => $cidade_est, 'estado' => $estado_est, 'complemento' => '', 'principal' => 's'];
            }

            $telefones = [];
            $telTipo = postArrayEst('tel_tipo');
            $telNumero = postArrayEst('tel_numero');
            $telRamal = postArrayEst('tel_ramal');
            $telContato = postArrayEst('tel_contato');
            $telPrin = postArrayEst('tel_principal');
            $telCount = max(count($telTipo), count($telNumero));
            for ($i = 0; $i < $telCount; $i++) {
                $telefones[] = ['tipo' => $telTipo[$i] ?? '', 'numero' => $telNumero[$i] ?? '', 'ramal' => $telRamal[$i] ?? '', 'contato' => $telContato[$i] ?? '', 'principal' => $telPrin[$i] ?? 'n'];
            }
            if (empty($telefones) && !empty($telefone01_est)) {
                $telefones[] = ['tipo' => 'Principal', 'numero' => $telefone01_est, 'ramal' => '', 'contato' => '', 'principal' => 's'];
                if (!empty($telefone02_est)) $telefones[] = ['tipo' => 'Alternativo', 'numero' => $telefone02_est, 'ramal' => '', 'contato' => '', 'principal' => 'n'];
            }

            $contatos = [];
            $contNome = postArrayEst('cont_nome');
            $contCargo = postArrayEst('cont_cargo');
            $contSetor = postArrayEst('cont_setor');
            $contEmail = postArrayEst('cont_email');
            $contTelefone = postArrayEst('cont_telefone');
            $contPrin = postArrayEst('cont_principal');
            $contCount = max(count($contNome), count($contEmail));
            for ($i = 0; $i < $contCount; $i++) {
                $contatos[] = ['nome' => $contNome[$i] ?? '', 'cargo' => $contCargo[$i] ?? '', 'setor' => $contSetor[$i] ?? '', 'email' => $contEmail[$i] ?? '', 'telefone' => $contTelefone[$i] ?? '', 'principal' => $contPrin[$i] ?? 'n'];
            }
            if (empty($contatos) && !empty($nome_contato_est)) {
                $contatos[] = ['nome' => $nome_contato_est, 'cargo' => 'Contato', 'setor' => '', 'email' => $email_contato_est, 'telefone' => $telefone_contato_est, 'principal' => 's'];
            }
            if (empty($contatos) && !empty($nome_responsavel_est)) {
                $contatos[] = ['nome' => $nome_responsavel_est, 'cargo' => 'Responsável', 'setor' => '', 'email' => $email_responsavel_est, 'telefone' => $telefone_responsavel_est, 'principal' => 's'];
            }

            insertEstipulanteRelatedRows($conn, $idNovo, $enderecos, $telefones, $contatos);
            $estipulanteCriado = $idNovo > 0 ? $estipulanteDao->findById($idNovo) : null;
            fullcareAuditLog($conn, [
                'action' => 'create',
                'entity_type' => 'estipulante',
                'entity_id' => $idNovo > 0 ? $idNovo : null,
                'summary' => 'Estipulante criado.',
                'after' => $estipulanteCriado ?: $estipulante,
                'context' => [
                    'enderecos' => count($enderecos),
                    'telefones' => count($telefones),
                    'contatos' => count($contatos),
                ],
                'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
                'source' => 'process_estipulante.php',
            ], $BASE_URL);
        } else {

            $message->setMessage("Você precisa adicionar pelo menos: nome_est do estipulante!", "error", "back");
        }
        header('Location: ' . $BASE_URL . 'estipulantes');
    }
}
if ($type === "update") {

    $tipo = ($_FILES['logo_est']['type']);
    $tamanho_perm = 1024 * 1024 * 2;
    $size = $_FILES['logo_est']['size'];

    $erros = "";

    if (($_FILES['logo_est']['size']) > $tamanho_perm) {
        // codigo de erro caso arquivo maior que permitido
    } else {
        // condicao caso arquivo permitido
        $tipo = ($_FILES['logo_est']['type']);
        $arquivo = ($_FILES['logo_est']['name']);
        $temp_arq = ($_FILES['logo_est']['tmp_name']);
        $size = ($_FILES['logo_est']['size']);
        $pasta = "uploads";

        move_uploaded_file($temp_arq, $pasta . "/" . $arquivo);
        $estipulanteDao = new estipulanteDAO($conn, $BASE_URL);
    }
    // Receber os dados dos inputs
    $id_estipulante = filter_input(INPUT_POST, "id_estipulante");
    $nome_est = filter_input(INPUT_POST, "nome_est", FILTER_SANITIZE_SPECIAL_CHARS);
    $nome_est = strtoupper($nome_est);
    $endereco_est = filter_input(INPUT_POST, "endereco_est", FILTER_SANITIZE_SPECIAL_CHARS);

    $email01_est = filter_input(INPUT_POST, "email01_est", FILTER_SANITIZE_EMAIL);
    $email01_est = strtolower($email01_est);

    $email02_est = filter_input(INPUT_POST, "email02_est", FILTER_SANITIZE_EMAIL);
    $email02_est = strtolower($email02_est);

    $cidade_est = filter_input(INPUT_POST, "cidade_est", FILTER_SANITIZE_SPECIAL_CHARS);
    $estado_est = filter_input(INPUT_POST, "estado_est", FILTER_SANITIZE_SPECIAL_CHARS);

    $cnpj_est = filter_input(INPUT_POST, "cnpj_est", FILTER_SANITIZE_SPECIAL_CHARS);
    $cnpj_est = str_replace('/', '', $cnpj_est);
    $cnpj_est = str_replace('-', '', $cnpj_est);
    $cnpj_est = str_replace('.', '', $cnpj_est);

    $telefone01_est = filter_input(INPUT_POST, "telefone01_est", FILTER_SANITIZE_SPECIAL_CHARS);
    $telefone01_est = str_replace('-', '', $telefone01_est);
    $telefone01_est = str_replace('(', '', $telefone01_est);
    $telefone01_est = str_replace(') ', '', $telefone01_est);

    $telefone02_est = filter_input(INPUT_POST, "telefone02_est");
    $telefone02_est = str_replace('-', '', $telefone02_est);
    $telefone02_est = str_replace('(', '', $telefone02_est);
    $telefone02_est = str_replace(') ', '', $telefone02_est);
    $cep_est = filter_input(INPUT_POST, "cep_est");

    $nome_contato_est = filter_input(INPUT_POST, "nome_contato_est");
    $nome_responsavel_est = filter_input(INPUT_POST, "nome_responsavel_est");
    $email_contato_est = filter_input(INPUT_POST, "email_contato_est");
    $email_responsavel_est = filter_input(INPUT_POST, "email_responsavel_est");
    $telefone_contato_est = filter_input(INPUT_POST, "telefone_contato_est");
    $telefone_responsavel_est = filter_input(INPUT_POST, "telefone_responsavel_est");

    $data_create_est = filter_input(INPUT_POST, "data_create_est");
    $usuario_create_est = filter_input(INPUT_POST, "usuario_create_est");
    $fk_usuario_est = filter_input(INPUT_POST, "fk_usuario_est");

    $numero_est = filter_input(INPUT_POST, "numero_est");
    $bairro_est = filter_input(INPUT_POST, "bairro_est");
    $logo_est = $arquivo;

    $estipulanteData = $estipulanteDao->findById($id_estipulante);
    $estipulanteAntes = $estipulanteData ? clone $estipulanteData : null;

    $estipulanteData->id_estipulante = $id_estipulante;
    $estipulanteData->nome_est = $nome_est;
    $estipulanteData->endereco_est = $endereco_est;
    $estipulanteData->email01_est = $email01_est;
    $estipulanteData->email02_est = $email02_est;
    $estipulanteData->cidade_est = $cidade_est;
    $estipulanteData->estado_est = $estado_est;

    $estipulanteData->telefone01_est = $telefone01_est;
    $estipulanteData->telefone02_est = $telefone02_est;
    $estipulanteData->numero_est = $numero_est;

    $estipulanteData->bairro_est = $bairro_est;
    $estipulanteData->logo_est = $logo_est;
    $estipulanteData->cnpj_est = $cnpj_est;

    $estipulanteData->data_create_est = $data_create_est;
    $estipulanteData->usuario_create_est = $usuario_create_est;
    $estipulanteData->fk_usuario_est = $fk_usuario_est;

    $estipulanteData->nome_contato_est = $nome_contato_est;
    $estipulanteData->nome_responsavel_est = $nome_responsavel_est;
    $estipulanteData->email_contato_est = $email_contato_est;
    $estipulanteData->email_responsavel_est = $email_responsavel_est;
    $estipulanteData->telefone_contato_est = $telefone_contato_est;
    $estipulanteData->telefone_responsavel_est = $telefone_responsavel_est;
    $estipulanteData->cep_est = $cep_est;

    $estipulanteDao->update($estipulanteData);

    $hasRelatedPayload = isset($_POST['end_tipo']) || isset($_POST['tel_tipo']) || isset($_POST['cont_nome']);
    if ($hasRelatedPayload) {
        $conn->prepare("DELETE FROM tb_estipulante_endereco WHERE fk_estipulante = :id")->execute([':id' => (int) $id_estipulante]);
        $conn->prepare("DELETE FROM tb_estipulante_telefone WHERE fk_estipulante = :id")->execute([':id' => (int) $id_estipulante]);
        $conn->prepare("DELETE FROM tb_estipulante_contato WHERE fk_estipulante = :id")->execute([':id' => (int) $id_estipulante]);
    }

    $enderecos = [];
    $endTipo = postArrayEst('end_tipo');
    $endCep = postArrayEst('end_cep');
    $endLog = postArrayEst('end_logradouro');
    $endNum = postArrayEst('end_numero');
    $endBairro = postArrayEst('end_bairro');
    $endCidade = postArrayEst('end_cidade');
    $endEstado = postArrayEst('end_estado');
    $endComp = postArrayEst('end_complemento');
    $endPrin = postArrayEst('end_principal');
    $endCount = max(count($endTipo), count($endLog));
    for ($i = 0; $i < $endCount; $i++) {
        $enderecos[] = ['tipo' => $endTipo[$i] ?? '', 'cep' => $endCep[$i] ?? '', 'endereco' => $endLog[$i] ?? '', 'numero' => $endNum[$i] ?? '', 'bairro' => $endBairro[$i] ?? '', 'cidade' => $endCidade[$i] ?? '', 'estado' => $endEstado[$i] ?? '', 'complemento' => $endComp[$i] ?? '', 'principal' => $endPrin[$i] ?? 'n'];
    }
    if (empty($enderecos) && !empty($endereco_est)) {
        $enderecos[] = ['tipo' => 'Principal', 'cep' => $cep_est, 'endereco' => $endereco_est, 'numero' => $numero_est, 'bairro' => $bairro_est, 'cidade' => $cidade_est, 'estado' => $estado_est, 'complemento' => '', 'principal' => 's'];
    }

    $telefones = [];
    $telTipo = postArrayEst('tel_tipo');
    $telNumero = postArrayEst('tel_numero');
    $telRamal = postArrayEst('tel_ramal');
    $telContato = postArrayEst('tel_contato');
    $telPrin = postArrayEst('tel_principal');
    $telCount = max(count($telTipo), count($telNumero));
    for ($i = 0; $i < $telCount; $i++) {
        $telefones[] = ['tipo' => $telTipo[$i] ?? '', 'numero' => $telNumero[$i] ?? '', 'ramal' => $telRamal[$i] ?? '', 'contato' => $telContato[$i] ?? '', 'principal' => $telPrin[$i] ?? 'n'];
    }
    if (empty($telefones) && !empty($telefone01_est)) {
        $telefones[] = ['tipo' => 'Principal', 'numero' => $telefone01_est, 'ramal' => '', 'contato' => '', 'principal' => 's'];
        if (!empty($telefone02_est)) $telefones[] = ['tipo' => 'Alternativo', 'numero' => $telefone02_est, 'ramal' => '', 'contato' => '', 'principal' => 'n'];
    }

    $contatos = [];
    $contNome = postArrayEst('cont_nome');
    $contCargo = postArrayEst('cont_cargo');
    $contSetor = postArrayEst('cont_setor');
    $contEmail = postArrayEst('cont_email');
    $contTelefone = postArrayEst('cont_telefone');
    $contPrin = postArrayEst('cont_principal');
    $contCount = max(count($contNome), count($contEmail));
    for ($i = 0; $i < $contCount; $i++) {
        $contatos[] = ['nome' => $contNome[$i] ?? '', 'cargo' => $contCargo[$i] ?? '', 'setor' => $contSetor[$i] ?? '', 'email' => $contEmail[$i] ?? '', 'telefone' => $contTelefone[$i] ?? '', 'principal' => $contPrin[$i] ?? 'n'];
    }
    if (empty($contatos) && !empty($nome_contato_est)) {
        $contatos[] = ['nome' => $nome_contato_est, 'cargo' => 'Contato', 'setor' => '', 'email' => $email_contato_est, 'telefone' => $telefone_contato_est, 'principal' => 's'];
    }
    if (empty($contatos) && !empty($nome_responsavel_est)) {
        $contatos[] = ['nome' => $nome_responsavel_est, 'cargo' => 'Responsável', 'setor' => '', 'email' => $email_responsavel_est, 'telefone' => $telefone_responsavel_est, 'principal' => 's'];
    }
    if ($hasRelatedPayload) {
        insertEstipulanteRelatedRows($conn, (int) $id_estipulante, $enderecos, $telefones, $contatos);
    }

    $estipulanteDepois = $estipulanteDao->findById((int)$id_estipulante);
    fullcareAuditLog($conn, [
        'action' => 'update',
        'entity_type' => 'estipulante',
        'entity_id' => (int)$id_estipulante,
        'summary' => 'Estipulante atualizado.',
        'before' => $estipulanteAntes,
        'after' => $estipulanteDepois,
        'context' => [
            'enderecos' => count($enderecos),
            'telefones' => count($telefones),
            'contatos' => count($contatos),
        ],
        'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
        'source' => 'process_estipulante.php',
    ], $BASE_URL);

    header('Location: ' . $BASE_URL . 'estipulantes');
}


if ($type === "delUpdate") {

    $estipulanteDao = new estipulanteDAO($conn, $BASE_URL);

    $id_estipulante = filter_input(INPUT_POST, "id_estipulante");
    $deletado_est = 's';
    $estipulanteData = $estipulanteDao->findById($id_estipulante);
    $estipulanteAntesSoftDelete = $estipulanteData ? clone $estipulanteData : null;

    $estipulanteData->id_estipulante = $id_estipulante;
    $estipulanteData->deletado_est = $deletado_est;

    $estipulanteDao->deletarUpdate($estipulanteData);
    $estipulanteDepoisSoftDelete = $estipulanteDao->findById((int)$id_estipulante);
    fullcareAuditLog($conn, [
        'action' => 'soft_delete',
        'entity_type' => 'estipulante',
        'entity_id' => (int)$id_estipulante,
        'summary' => 'Estipulante marcado como deletado.',
        'before' => $estipulanteAntesSoftDelete,
        'after' => $estipulanteDepoisSoftDelete,
        'source' => 'process_estipulante.php',
    ], $BASE_URL);
    header('Location: ' . $BASE_URL . 'estipulantes');
}

if ($type === "delete") {
    // Recebe os dados do form
    $id_estipulante = filter_input(INPUT_POST, "id_estipulante");

    $estipulanteDao = new estipulanteDAO($conn, $BASE_URL);

    $estipulante = $estipulanteDao->findById($id_estipulante);

    if (3 < 4) {
        $estipulanteAntesDelete = $estipulante ? clone $estipulante : null;
        $estipulanteDao->destroy($id_estipulante);
        fullcareAuditLog($conn, [
            'action' => 'delete',
            'entity_type' => 'estipulante',
            'entity_id' => (int)$id_estipulante,
            'summary' => 'Estipulante excluído.',
            'before' => $estipulanteAntesDelete,
            'source' => 'process_estipulante.php',
        ], $BASE_URL);

        include_once('list_estipulante.php');
    } else {

        $message->setMessage("Informações inválidas!", "error", "index.php");
    }
}

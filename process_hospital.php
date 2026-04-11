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
require_once("models/hospital.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("dao/hospitalDao.php");

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$hospitalDao = new hospitalDAO($conn, $BASE_URL);

function normalizeDigits(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?? '';
}

function normalizeMoneyToFloat(?string $value): ?float
{
    $raw = str_replace(['R$', ' '], '', (string) $value);
    $raw = str_replace('.', '', $raw);
    $raw = str_replace(',', '.', $raw);
    return is_numeric($raw) ? (float) $raw : null;
}

function postArray(string $key): array
{
    $values = filter_input(INPUT_POST, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
    return is_array($values) ? $values : [];
}

function ensureHospitalUserLink(PDO $conn, int $hospitalId, int $userId): void
{
    if ($hospitalId <= 0 || $userId <= 0) {
        return;
    }

    $stmtCheck = $conn->prepare("
        SELECT id_hospitalUser
          FROM tb_hospitalUser
         WHERE fk_hospital_user = :hospital
           AND fk_usuario_hosp = :user
         LIMIT 1
    ");
    $stmtCheck->bindValue(':hospital', $hospitalId, PDO::PARAM_INT);
    $stmtCheck->bindValue(':user', $userId, PDO::PARAM_INT);
    $stmtCheck->execute();

    if ($stmtCheck->fetchColumn()) {
        return;
    }

    $stmtInsert = $conn->prepare("
        INSERT INTO tb_hospitalUser (fk_usuario_hosp, fk_hospital_user)
        VALUES (:user, :hospital)
    ");
    $stmtInsert->bindValue(':user', $userId, PDO::PARAM_INT);
    $stmtInsert->bindValue(':hospital', $hospitalId, PDO::PARAM_INT);
    $stmtInsert->execute();
}

function insertHospitalRelatedRows(PDO $conn, int $idHospital, array $enderecos, array $telefones, array $contatos): void
{
    if ($idHospital <= 0) {
        return;
    }

    $stmtEnd = $conn->prepare("
        INSERT INTO tb_hospital_endereco (
            fk_hospital, tipo_endereco, cep_endereco, endereco_endereco, numero_endereco,
            bairro_endereco, cidade_endereco, estado_endereco, complemento_endereco,
            principal_endereco, ativo_endereco, data_create_endereco
        ) VALUES (
            :fk_hospital, :tipo_endereco, :cep_endereco, :endereco_endereco, :numero_endereco,
            :bairro_endereco, :cidade_endereco, :estado_endereco, :complemento_endereco,
            :principal_endereco, 's', NOW()
        )
    ");
    foreach ($enderecos as $item) {
        $endereco = trim((string) ($item['endereco'] ?? ''));
        if ($endereco === '') {
            continue;
        }
        $stmtEnd->execute([
            ':fk_hospital' => $idHospital,
            ':tipo_endereco' => trim((string) ($item['tipo'] ?? '')),
            ':cep_endereco' => normalizeDigits((string) ($item['cep'] ?? '')),
            ':endereco_endereco' => $endereco,
            ':numero_endereco' => trim((string) ($item['numero'] ?? '')),
            ':bairro_endereco' => trim((string) ($item['bairro'] ?? '')),
            ':cidade_endereco' => trim((string) ($item['cidade'] ?? '')),
            ':estado_endereco' => trim((string) ($item['estado'] ?? '')),
            ':complemento_endereco' => trim((string) ($item['complemento'] ?? '')),
            ':principal_endereco' => ((string) ($item['principal'] ?? 'n') === 's') ? 1 : 0,
        ]);
    }

    $stmtTel = $conn->prepare("
        INSERT INTO tb_hospital_telefone (
            fk_hospital, tipo_telefone, numero_telefone, ramal_telefone,
            contato_telefone, principal_telefone, ativo_telefone, data_create_telefone
        ) VALUES (
            :fk_hospital, :tipo_telefone, :numero_telefone, :ramal_telefone,
            :contato_telefone, :principal_telefone, 's', NOW()
        )
    ");
    foreach ($telefones as $item) {
        $numero = normalizeDigits((string) ($item['numero'] ?? ''));
        if ($numero === '') {
            continue;
        }
        $stmtTel->execute([
            ':fk_hospital' => $idHospital,
            ':tipo_telefone' => trim((string) ($item['tipo'] ?? '')),
            ':numero_telefone' => $numero,
            ':ramal_telefone' => trim((string) ($item['ramal'] ?? '')),
            ':contato_telefone' => trim((string) ($item['contato'] ?? '')),
            ':principal_telefone' => ((string) ($item['principal'] ?? 'n') === 's') ? 1 : 0,
        ]);
    }

    $stmtCont = $conn->prepare("
        INSERT INTO tb_hospital_contato (
            fk_hospital, nome_contato, cargo_contato, setor_contato, email_contato,
            telefone_contato, principal_contato, ativo_contato, data_create_contato
        ) VALUES (
            :fk_hospital, :nome_contato, :cargo_contato, :setor_contato, :email_contato,
            :telefone_contato, :principal_contato, 's', NOW()
        )
    ");
    foreach ($contatos as $item) {
        $nome = trim((string) ($item['nome'] ?? ''));
        if ($nome === '') {
            continue;
        }
        $stmtCont->execute([
            ':fk_hospital' => $idHospital,
            ':nome_contato' => $nome,
            ':cargo_contato' => trim((string) ($item['cargo'] ?? '')),
            ':setor_contato' => trim((string) ($item['setor'] ?? '')),
            ':email_contato' => trim((string) ($item['email'] ?? '')),
            ':telefone_contato' => normalizeDigits((string) ($item['telefone'] ?? '')),
            ':principal_contato' => ((string) ($item['principal'] ?? 'n') === 's') ? 1 : 0,
        ]);
    }
}

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");
$typeDel = filter_input(INPUT_POST, "typeDel");


// Resgata dados do usuário
if ($type === "create") {

    $erros = "";

    // Receber os dados dos inputs
    $nome_hosp = filter_input(INPUT_POST, "nome_hosp");
    $nome_hosp = ucwords(strtolower($nome_hosp));
    $endereco_hosp = filter_input(INPUT_POST, "endereco_hosp");
    $email01_hosp = filter_input(INPUT_POST, "email01_hosp");
    $email02_hosp = filter_input(INPUT_POST, "email02_hosp");
    $cidade_hosp = filter_input(INPUT_POST, "cidade_hosp");
    $estado_hosp = filter_input(INPUT_POST, "estado_hosp");
    $ativo_hosp = filter_input(INPUT_POST, "ativo_hosp");
    $cep_hosp = normalizeDigits(filter_input(INPUT_POST, "cep_hosp"));

    $cnpj_hosp = normalizeDigits(filter_input(INPUT_POST, "cnpj_hosp"));

    $telefone01_hosp = normalizeDigits(filter_input(INPUT_POST, "telefone01_hosp"));
    $telefone02_hosp = normalizeDigits(filter_input(INPUT_POST, "telefone02_hosp"));

    $numero_hosp = filter_input(INPUT_POST, "numero_hosp");
    $bairro_hosp = filter_input(INPUT_POST, "bairro_hosp");
    $fk_usuario_hosp = filter_input(INPUT_POST, "fk_usuario_hosp", FILTER_VALIDATE_INT);
    $usuario_create_hosp = filter_input(INPUT_POST, "usuario_create_hosp");
    $data_create_hosp = filter_input(INPUT_POST, "data_create_hosp");
    $longitude_hosp = filter_input(INPUT_POST, "longitude_hosp");
    $latitude_hosp = filter_input(INPUT_POST, "latitude_hosp");
    $coordenador_medico_hosp = filter_input(INPUT_POST, "coordenador_medico_hosp");
    $diretor_hosp = filter_input(INPUT_POST, "diretor_hosp");
    $coordenador_fat_hosp = filter_input(INPUT_POST, "coordenador_fat_hosp");
    $deletado_hosp = filter_input(INPUT_POST, "deletado_hosp");

    $hospital = new hospital();

    // Validação mínima de dados
    if (!empty($nome_hosp)) {

        $hospital->nome_hosp = $nome_hosp;
        $hospital->ativo_hosp = $ativo_hosp;
        $hospital->endereco_hosp = $endereco_hosp;
        $hospital->bairro_hosp = $bairro_hosp;
        $hospital->cep_hosp = $cep_hosp;
        $hospital->email02_hosp = $email02_hosp;
        $hospital->email01_hosp = $email01_hosp;
        $hospital->cidade_hosp = $cidade_hosp;
        $hospital->estado_hosp = $estado_hosp;
        $hospital->cnpj_hosp = $cnpj_hosp;
        $hospital->telefone01_hosp = $telefone01_hosp;
        $hospital->telefone02_hosp = $telefone02_hosp;
        $hospital->numero_hosp = $numero_hosp;
        $hospital->bairro_hosp = $bairro_hosp;
        $creatorUserId = (int) ($fk_usuario_hosp ?: ($_SESSION['id_usuario'] ?? 0));
        $creatorUserLabel = trim((string) ($usuario_create_hosp ?: ($_SESSION['usuario_user'] ?? $_SESSION['email_user'] ?? '')));
        $creatorDate = trim((string) ($data_create_hosp ?: date('Y-m-d H:i:s')));

        $hospital->fk_usuario_hosp = $creatorUserId > 0 ? $creatorUserId : null;
        $hospital->usuario_create_hosp = $creatorUserLabel;
        $hospital->data_create_hosp = $creatorDate;
        $hospital->longitude_hosp = $longitude_hosp;
        $hospital->latitude_hosp = $latitude_hosp;
        $hospital->coordenador_medico_hosp = $coordenador_medico_hosp;
        $hospital->diretor_hosp = $diretor_hosp;
        $hospital->coordenador_fat_hosp = $coordenador_fat_hosp;
        $hospital->deletado_hosp = $deletado_hosp;

        $hospitalDao->create($hospital);

        $id_hospital_novo = (int) $conn->lastInsertId();
        ensureHospitalUserLink($conn, $id_hospital_novo, $creatorUserId);

        $enderecos = [];
        $endTipo = postArray("end_tipo");
        $endCep = postArray("end_cep");
        $endLogradouro = postArray("end_logradouro");
        $endNumero = postArray("end_numero");
        $endBairro = postArray("end_bairro");
        $endCidade = postArray("end_cidade");
        $endEstado = postArray("end_estado");
        $endComplemento = postArray("end_complemento");
        $endPrincipal = postArray("end_principal");
        $endCount = max(count($endTipo), count($endLogradouro));
        for ($i = 0; $i < $endCount; $i++) {
            $enderecos[] = [
                'tipo' => $endTipo[$i] ?? '',
                'cep' => $endCep[$i] ?? '',
                'endereco' => $endLogradouro[$i] ?? '',
                'numero' => $endNumero[$i] ?? '',
                'bairro' => $endBairro[$i] ?? '',
                'cidade' => $endCidade[$i] ?? '',
                'estado' => $endEstado[$i] ?? '',
                'complemento' => $endComplemento[$i] ?? '',
                'principal' => $endPrincipal[$i] ?? 'n',
            ];
        }
        if (empty($enderecos) && !empty($endereco_hosp)) {
            array_unshift($enderecos, [
                'tipo' => 'Principal',
                'cep' => $cep_hosp,
                'endereco' => $endereco_hosp,
                'numero' => $numero_hosp,
                'bairro' => $bairro_hosp,
                'cidade' => $cidade_hosp,
                'estado' => $estado_hosp,
                'complemento' => '',
                'principal' => 's',
            ]);
        }

        $telefones = [];
        $telTipo = postArray("tel_tipo");
        $telNumero = postArray("tel_numero");
        $telRamal = postArray("tel_ramal");
        $telContato = postArray("tel_contato");
        $telPrincipal = postArray("tel_principal");
        $telCount = max(count($telTipo), count($telNumero));
        for ($i = 0; $i < $telCount; $i++) {
            $telefones[] = [
                'tipo' => $telTipo[$i] ?? '',
                'numero' => $telNumero[$i] ?? '',
                'ramal' => $telRamal[$i] ?? '',
                'contato' => $telContato[$i] ?? '',
                'principal' => $telPrincipal[$i] ?? 'n',
            ];
        }
        if (empty($telefones) && !empty($telefone01_hosp)) {
            array_unshift($telefones, [
                'tipo' => 'Principal',
                'numero' => $telefone01_hosp,
                'ramal' => '',
                'contato' => '',
                'principal' => 's',
            ]);
            if (!empty($telefone02_hosp)) {
                $telefones[] = [
                    'tipo' => 'Alternativo',
                    'numero' => $telefone02_hosp,
                    'ramal' => '',
                    'contato' => '',
                    'principal' => 'n',
                ];
            }
        }

        $contatos = [];
        $contNome = postArray("cont_nome");
        $contCargo = postArray("cont_cargo");
        $contSetor = postArray("cont_setor");
        $contEmail = postArray("cont_email");
        $contTelefone = postArray("cont_telefone");
        $contPrincipal = postArray("cont_principal");
        $contCount = max(count($contNome), count($contEmail));
        for ($i = 0; $i < $contCount; $i++) {
            $contatos[] = [
                'nome' => $contNome[$i] ?? '',
                'cargo' => $contCargo[$i] ?? '',
                'setor' => $contSetor[$i] ?? '',
                'email' => $contEmail[$i] ?? '',
                'telefone' => $contTelefone[$i] ?? '',
                'principal' => $contPrincipal[$i] ?? 'n',
            ];
        }

        insertHospitalRelatedRows($conn, $id_hospital_novo, $enderecos, $telefones, $contatos);
        if ($id_hospital_novo > 0) {
            $acomodacoesNome = filter_input(INPUT_POST, "acomodacao_nome", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];
            $acomodacoesValor = filter_input(INPUT_POST, "acomodacao_valor", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];
            $acomodacoesData = filter_input(INPUT_POST, "acomodacao_data", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];

            if (!empty($acomodacoesNome)) {
                $stmtAco = $conn->prepare("INSERT INTO tb_acomodacao (
                    acomodacao_aco,
                    fk_hospital,
                    valor_aco,
                    fk_usuario_acomodacao,
                    usuario_create_acomodacao,
                    data_create_acomodacao,
                    data_contrato_aco
                ) VALUES (
                    :acomodacao_aco,
                    :fk_hospital,
                    :valor_aco,
                    :fk_usuario_acomodacao,
                    :usuario_create_acomodacao,
                    :data_create_acomodacao,
                    :data_contrato_aco
                )");

                foreach ($acomodacoesNome as $i => $nomeAcoRaw) {
                    $nomeAco = trim((string) $nomeAcoRaw);
                    if ($nomeAco === '') {
                        continue;
                    }

                    $valorAco = normalizeMoneyToFloat(isset($acomodacoesValor[$i]) ? (string) $acomodacoesValor[$i] : '');

                    $dataContrato = isset($acomodacoesData[$i]) ? trim((string) $acomodacoesData[$i]) : '';
                    if ($dataContrato === '') {
                        $dataContrato = null;
                    }

                    $stmtAco->bindValue(':acomodacao_aco', $nomeAco);
                    $stmtAco->bindValue(':fk_hospital', $id_hospital_novo, PDO::PARAM_INT);
                    $stmtAco->bindValue(':valor_aco', $valorAco);
                    $stmtAco->bindValue(':fk_usuario_acomodacao', $creatorUserId, PDO::PARAM_INT);
                    $stmtAco->bindValue(':usuario_create_acomodacao', $creatorUserLabel);
                    $stmtAco->bindValue(':data_create_acomodacao', $creatorDate);
                    $stmtAco->bindValue(':data_contrato_aco', $dataContrato);
                    $stmtAco->execute();
                }
            }
        }
    }
    header("Location: " . $BASE_URL . "hospitais");
    exit;
}
if ($type === "update") {


    $erros = "";


    $hospitalDao = new hospitalDAO($conn, $BASE_URL);

    // Receber os dados dos inputs
    $nome_hosp = filter_input(INPUT_POST, "nome_hosp");
    $nome_hosp = ucwords(strtolower($nome_hosp));
    $endereco_hosp = filter_input(INPUT_POST, "endereco_hosp");
    $email01_hosp = filter_input(INPUT_POST, "email01_hosp");
    $email01_hosp = strtolower($email01_hosp);

    $email02_hosp = filter_input(INPUT_POST, "email02_hosp");
    $email02_hosp = strtolower($email02_hosp);

    $cidade_hosp = filter_input(INPUT_POST, "cidade_hosp");
    $estado_hosp = filter_input(INPUT_POST, "estado_hosp");
    $ativo_hosp = filter_input(INPUT_POST, "ativo_hosp");
    $cep_hosp = normalizeDigits(filter_input(INPUT_POST, "cep_hosp"));

    $cnpj_hosp = normalizeDigits(filter_input(INPUT_POST, "cnpj_hosp"));

    $telefone01_hosp = normalizeDigits(filter_input(INPUT_POST, "telefone01_hosp"));
    $telefone02_hosp = normalizeDigits(filter_input(INPUT_POST, "telefone02_hosp"));


    $numero_hosp = filter_input(INPUT_POST, "numero_hosp");
    $bairro_hosp = filter_input(INPUT_POST, "bairro_hosp");
    $longitude_hosp = filter_input(INPUT_POST, "longitude_hosp");
    $latitude_hosp = filter_input(INPUT_POST, "latitude_hosp");
    $coordenador_medico_hosp = filter_input(INPUT_POST, "coordenador_medico_hosp");
    $diretor_hosp = filter_input(INPUT_POST, "diretor_hosp");
    $coordenador_fat_hosp = filter_input(INPUT_POST, "coordenador_fat_hosp");
    $id_hospital = filter_input(INPUT_POST, "id_hospital");

    $hospitalData = $hospitalDao->findById($id_hospital);

    $hospitalData->id_hospital = $id_hospital;
    $hospitalData->nome_hosp = $nome_hosp;
    $hospitalData->endereco_hosp = $endereco_hosp;
    $hospitalData->email01_hosp = $email01_hosp;
    $hospitalData->email02_hosp = $email02_hosp;
    $hospitalData->cidade_hosp = $cidade_hosp;
    $hospitalData->estado_hosp = $estado_hosp;
    $hospitalData->cep_hosp = $cep_hosp;
    $hospitalData->cnpj_hosp = $cnpj_hosp;
    $hospitalData->telefone01_hosp = $telefone01_hosp;
    $hospitalData->telefone02_hosp = $telefone02_hosp;
    $hospitalData->numero_hosp = $numero_hosp;
    $hospitalData->bairro_hosp = $bairro_hosp;
    $hospitalData->longitude_hosp = $longitude_hosp;
    $hospitalData->latitude_hosp = $latitude_hosp;
    $hospitalData->coordenador_medico_hosp = $coordenador_medico_hosp;
    $hospitalData->diretor_hosp = $diretor_hosp;
    $hospitalData->coordenador_fat_hosp = $coordenador_fat_hosp;
    $hospitalData->ativo_hosp = $ativo_hosp;

    $hospitalDao->update($hospitalData);

    $conn->prepare("DELETE FROM tb_hospital_endereco WHERE fk_hospital = :id")->execute([':id' => (int) $id_hospital]);
    $conn->prepare("DELETE FROM tb_hospital_telefone WHERE fk_hospital = :id")->execute([':id' => (int) $id_hospital]);
    $conn->prepare("DELETE FROM tb_hospital_contato WHERE fk_hospital = :id")->execute([':id' => (int) $id_hospital]);

    $enderecos = [];
    $endTipo = postArray("end_tipo");
    $endCep = postArray("end_cep");
    $endLogradouro = postArray("end_logradouro");
    $endNumero = postArray("end_numero");
    $endBairro = postArray("end_bairro");
    $endCidade = postArray("end_cidade");
    $endEstado = postArray("end_estado");
    $endComplemento = postArray("end_complemento");
    $endPrincipal = postArray("end_principal");
    $endCount = max(count($endTipo), count($endLogradouro));
    for ($i = 0; $i < $endCount; $i++) {
        $enderecos[] = [
            'tipo' => $endTipo[$i] ?? '',
            'cep' => $endCep[$i] ?? '',
            'endereco' => $endLogradouro[$i] ?? '',
            'numero' => $endNumero[$i] ?? '',
            'bairro' => $endBairro[$i] ?? '',
            'cidade' => $endCidade[$i] ?? '',
            'estado' => $endEstado[$i] ?? '',
            'complemento' => $endComplemento[$i] ?? '',
            'principal' => $endPrincipal[$i] ?? 'n',
        ];
    }
    if (empty($enderecos) && !empty($endereco_hosp)) {
        array_unshift($enderecos, [
            'tipo' => 'Principal',
            'cep' => $cep_hosp,
            'endereco' => $endereco_hosp,
            'numero' => $numero_hosp,
            'bairro' => $bairro_hosp,
            'cidade' => $cidade_hosp,
            'estado' => $estado_hosp,
            'complemento' => '',
            'principal' => 's',
        ]);
    }

    $telefones = [];
    $telTipo = postArray("tel_tipo");
    $telNumero = postArray("tel_numero");
    $telRamal = postArray("tel_ramal");
    $telContato = postArray("tel_contato");
    $telPrincipal = postArray("tel_principal");
    $telCount = max(count($telTipo), count($telNumero));
    for ($i = 0; $i < $telCount; $i++) {
        $telefones[] = [
            'tipo' => $telTipo[$i] ?? '',
            'numero' => $telNumero[$i] ?? '',
            'ramal' => $telRamal[$i] ?? '',
            'contato' => $telContato[$i] ?? '',
            'principal' => $telPrincipal[$i] ?? 'n',
        ];
    }
    if (empty($telefones) && !empty($telefone01_hosp)) {
        array_unshift($telefones, [
            'tipo' => 'Principal',
            'numero' => $telefone01_hosp,
            'ramal' => '',
            'contato' => '',
            'principal' => 's',
        ]);
        if (!empty($telefone02_hosp)) {
            $telefones[] = [
                'tipo' => 'Alternativo',
                'numero' => $telefone02_hosp,
                'ramal' => '',
                'contato' => '',
                'principal' => 'n',
            ];
        }
    }

    $contatos = [];
    $contNome = postArray("cont_nome");
    $contCargo = postArray("cont_cargo");
    $contSetor = postArray("cont_setor");
    $contEmail = postArray("cont_email");
    $contTelefone = postArray("cont_telefone");
    $contPrincipal = postArray("cont_principal");
    $contCount = max(count($contNome), count($contEmail));
    for ($i = 0; $i < $contCount; $i++) {
        $contatos[] = [
            'nome' => $contNome[$i] ?? '',
            'cargo' => $contCargo[$i] ?? '',
            'setor' => $contSetor[$i] ?? '',
            'email' => $contEmail[$i] ?? '',
            'telefone' => $contTelefone[$i] ?? '',
            'principal' => $contPrincipal[$i] ?? 'n',
        ];
    }

    insertHospitalRelatedRows($conn, (int) $id_hospital, $enderecos, $telefones, $contatos);

    $deleteExistingIds = filter_input(INPUT_POST, "delete_existing_acomodacao_ids", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];
    if (!empty($deleteExistingIds)) {
        $stmtDelAco = $conn->prepare("DELETE FROM tb_acomodacao WHERE id_acomodacao = :id AND fk_hospital = :fk_hospital");
        foreach ($deleteExistingIds as $delIdRaw) {
            $delId = (int) $delIdRaw;
            if ($delId <= 0) {
                continue;
            }
            $stmtDelAco->bindValue(':id', $delId, PDO::PARAM_INT);
            $stmtDelAco->bindValue(':fk_hospital', (int) $id_hospital, PDO::PARAM_INT);
            $stmtDelAco->execute();
        }
    }

    $acomodacoesNome = filter_input(INPUT_POST, "acomodacao_nome", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];
    $acomodacoesValor = filter_input(INPUT_POST, "acomodacao_valor", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];
    $acomodacoesData = filter_input(INPUT_POST, "acomodacao_data", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];

    if (!empty($acomodacoesNome)) {
        $stmtAco = $conn->prepare("INSERT INTO tb_acomodacao (
            acomodacao_aco,
            fk_hospital,
            valor_aco,
            fk_usuario_acomodacao,
            usuario_create_acomodacao,
            data_create_acomodacao,
            data_contrato_aco
        ) VALUES (
            :acomodacao_aco,
            :fk_hospital,
            :valor_aco,
            :fk_usuario_acomodacao,
            :usuario_create_acomodacao,
            :data_create_acomodacao,
            :data_contrato_aco
        )");

        foreach ($acomodacoesNome as $i => $nomeAcoRaw) {
            $nomeAco = trim((string) $nomeAcoRaw);
            if ($nomeAco === '') {
                continue;
            }

            $valorAco = normalizeMoneyToFloat(isset($acomodacoesValor[$i]) ? (string) $acomodacoesValor[$i] : '');

            $dataContrato = isset($acomodacoesData[$i]) ? trim((string) $acomodacoesData[$i]) : '';
            if ($dataContrato === '') {
                $dataContrato = null;
            }

            $stmtAco->bindValue(':acomodacao_aco', $nomeAco);
            $stmtAco->bindValue(':fk_hospital', (int) $id_hospital, PDO::PARAM_INT);
            $stmtAco->bindValue(':valor_aco', $valorAco);
            $stmtAco->bindValue(':fk_usuario_acomodacao', (int) ($_SESSION['id_usuario'] ?? 0), PDO::PARAM_INT);
            $stmtAco->bindValue(':usuario_create_acomodacao', (string) ($_SESSION['email_user'] ?? ''));
            $stmtAco->bindValue(':data_create_acomodacao', date('Y-m-d H:i:s'));
            $stmtAco->bindValue(':data_contrato_aco', $dataContrato);
            $stmtAco->execute();
        }
    }

    header("Location: " . $BASE_URL . "hospitais");
    exit;
}

if ($type === "delUpdate") {

    $hospitalDao = new hospitalDAO($conn, $BASE_URL);

    $id_hospital = filter_input(INPUT_POST, "id_hospital");
    $deletado_hosp = 's';
    $hospitalData = $hospitalDao->findById($id_hospital);

    $hospitalData->id_hospital = $id_hospital;
    $hospitalData->deletado_hosp = $deletado_hosp;

    $hospitalDao->deletarUpdate($hospitalData);

    header("Location: " . $BASE_URL . "hospitais");
    exit;
}

if ($type === "delete") {
    // Recebe os dados do form
    $id_hospital = filter_input(INPUT_POST, "id_hospital");

    $hospitalDao = new hospitalDAO($conn, $BASE_URL);

    $hospital = $hospitalDao->findById($id_hospital);

    if (3 < 4) {

        $hospitalDao->destroy($id_hospital);

        header("Location: " . $BASE_URL . "hospitais");
        exit;
    } else {

        $message->setMessage("Informações inválidas!", "error", "index.php");
    }
}
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     var_dump($_POST); // Exibe os dados enviados
//     exit;
// }

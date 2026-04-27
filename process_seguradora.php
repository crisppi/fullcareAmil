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

require_once("models/seguradora.php");
require_once("dao/seguradoraDao.php");

require_once("models/usuario.php");
require_once("dao/usuarioDao.php");

require_once("models/message.php");
require_once("utils/audit_logger.php");

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$seguradoraDao = new seguradoraDAO($conn, $BASE_URL);

function normalizeDigitsSeg(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?? '';
}

function postArraySeg(string $key): array
{
    $values = filter_input(INPUT_POST, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
    return is_array($values) ? $values : [];
}

function insertSeguradoraRelatedRows(PDO $conn, int $idSeguradora, array $enderecos, array $telefones, array $contatos): void
{
    if ($idSeguradora <= 0) return;

    $stmtEnd = $conn->prepare("INSERT INTO tb_seguradora_endereco (fk_seguradora, tipo_endereco, cep_endereco, endereco_endereco, numero_endereco, bairro_endereco, cidade_endereco, estado_endereco, complemento_endereco, principal_endereco, ativo_endereco, data_create_endereco) VALUES (:fk_seguradora, :tipo_endereco, :cep_endereco, :endereco_endereco, :numero_endereco, :bairro_endereco, :cidade_endereco, :estado_endereco, :complemento_endereco, :principal_endereco, 's', NOW())");
    foreach ($enderecos as $item) {
        $logradouro = trim((string) ($item['endereco'] ?? ''));
        if ($logradouro === '') continue;
        $stmtEnd->execute([
            ':fk_seguradora' => $idSeguradora,
            ':tipo_endereco' => trim((string) ($item['tipo'] ?? '')),
            ':cep_endereco' => normalizeDigitsSeg((string) ($item['cep'] ?? '')),
            ':endereco_endereco' => $logradouro,
            ':numero_endereco' => trim((string) ($item['numero'] ?? '')),
            ':bairro_endereco' => trim((string) ($item['bairro'] ?? '')),
            ':cidade_endereco' => trim((string) ($item['cidade'] ?? '')),
            ':estado_endereco' => trim((string) ($item['estado'] ?? '')),
            ':complemento_endereco' => trim((string) ($item['complemento'] ?? '')),
            ':principal_endereco' => ((string) ($item['principal'] ?? 'n') === 's') ? 1 : 0,
        ]);
    }

    $stmtTel = $conn->prepare("INSERT INTO tb_seguradora_telefone (fk_seguradora, tipo_telefone, numero_telefone, ramal_telefone, contato_telefone, principal_telefone, ativo_telefone, data_create_telefone) VALUES (:fk_seguradora, :tipo_telefone, :numero_telefone, :ramal_telefone, :contato_telefone, :principal_telefone, 's', NOW())");
    foreach ($telefones as $item) {
        $numero = normalizeDigitsSeg((string) ($item['numero'] ?? ''));
        if ($numero === '') continue;
        $stmtTel->execute([
            ':fk_seguradora' => $idSeguradora,
            ':tipo_telefone' => trim((string) ($item['tipo'] ?? '')),
            ':numero_telefone' => $numero,
            ':ramal_telefone' => trim((string) ($item['ramal'] ?? '')),
            ':contato_telefone' => trim((string) ($item['contato'] ?? '')),
            ':principal_telefone' => ((string) ($item['principal'] ?? 'n') === 's') ? 1 : 0,
        ]);
    }

    $stmtCont = $conn->prepare("INSERT INTO tb_seguradora_contato (fk_seguradora, nome_contato, cargo_contato, setor_contato, email_contato, telefone_contato, principal_contato, ativo_contato, data_create_contato) VALUES (:fk_seguradora, :nome_contato, :cargo_contato, :setor_contato, :email_contato, :telefone_contato, :principal_contato, 's', NOW())");
    foreach ($contatos as $item) {
        $nome = trim((string) ($item['nome'] ?? ''));
        if ($nome === '') continue;
        $stmtCont->execute([
            ':fk_seguradora' => $idSeguradora,
            ':nome_contato' => $nome,
            ':cargo_contato' => trim((string) ($item['cargo'] ?? '')),
            ':setor_contato' => trim((string) ($item['setor'] ?? '')),
            ':email_contato' => trim((string) ($item['email'] ?? '')),
            ':telefone_contato' => normalizeDigitsSeg((string) ($item['telefone'] ?? '')),
            ':principal_contato' => ((string) ($item['principal'] ?? 'n') === 's') ? 1 : 0,
        ]);
    }
}

function storeSeguradoraLogo(array $file, string $targetDir, int $maxBytes = 2097152): ?string
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload do logo.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $originalName = (string)($file['name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        throw new RuntimeException('Logo inválido ou maior que 2MB.');
    }
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Arquivo de upload inválido.');
    }

    $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
    ];
    if (!isset($allowed[$ext])) {
        throw new RuntimeException('Extensão do logo não permitida.');
    }

    if ($ext !== 'svg') {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        if ($mime !== $allowed[$ext]) {
            throw new RuntimeException('Tipo MIME do logo inválido.');
        }
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Não foi possível preparar diretório de upload.');
    }

    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($tmp, rtrim($targetDir, '/') . '/' . $safeName)) {
        throw new RuntimeException('Não foi possível salvar o logo enviado.');
    }

    return $safeName;
}

$type = filter_input(INPUT_POST, "type");
$typeDel = filter_input(INPUT_POST, "typeDel");

if ($type === "create") {
    $arquivo = null;
    try {
        $arquivo = storeSeguradoraLogo($_FILES['logo_seg'] ?? [], __DIR__ . '/uploads');
    } catch (Throwable $e) {
        $message->setMessage($e->getMessage(), "error", "back");
        exit;
    }

        // Receber os dados dos inputs
        $seguradora_seg = filter_input(INPUT_POST, "seguradora_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $seguradora_seg = strtoupper($seguradora_seg);
        $endereco_seg = filter_input(INPUT_POST, "endereco_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $bairro_seg = filter_input(INPUT_POST, "bairro_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $email01_seg = filter_input(INPUT_POST, "email01_seg", FILTER_SANITIZE_EMAIL);
        $email02_seg = filter_input(INPUT_POST, "email02_seg", FILTER_SANITIZE_EMAIL);
        $cidade_seg = filter_input(INPUT_POST, "cidade_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $estado_seg = filter_input(INPUT_POST, "estado_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $cep_seg = filter_input(INPUT_POST, "cep_seg", FILTER_SANITIZE_SPECIAL_CHARS);

        $cnpj_seg = filter_input(INPUT_POST, "cnpj_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $cnpj_seg = str_replace('/', '', $cnpj_seg);
        $cnpj_seg = str_replace('-', '', $cnpj_seg);
        $cnpj_seg = str_replace('.', '', $cnpj_seg);

        $telefone01_seg = filter_input(INPUT_POST, "telefone01_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $telefone01_seg = str_replace('-', '', $telefone01_seg);
        $telefone01_seg = str_replace('(', '', $telefone01_seg);
        $telefone01_seg = str_replace(') ', '', $telefone01_seg);

        $telefone02_seg = filter_input(INPUT_POST, "telefone02_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $telefone02_seg = str_replace('-', '', $telefone02_seg);
        $telefone02_seg = str_replace('(', '', $telefone02_seg);
        $telefone02_seg = str_replace(') ', '', $telefone02_seg);

        $numero_seg = filter_input(INPUT_POST, "numero_seg");
        $data_create_seg = filter_input(INPUT_POST, "data_create_seg");
        $fk_usuario_seg = filter_input(INPUT_POST, "fk_usuario_seg");
        $usuario_create_seg = filter_input(INPUT_POST, "usuario_create_seg");
        $ativo_seg = filter_input(INPUT_POST, "ativo_seg");
        $coordenador_seg = filter_input(INPUT_POST, "coordenador_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $contato_seg = filter_input(INPUT_POST, "contato_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $coord_rh_seg = filter_input(INPUT_POST, "coord_rh_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $deletado_seg = filter_input(INPUT_POST, "deletado_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $valor_alto_custo_seg = filter_input(INPUT_POST, "valor_alto_custo_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $dias_visita_seg = filter_input(INPUT_POST, "dias_visita_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $dias_visita_uti_seg = filter_input(INPUT_POST, "dias_visita_uti_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $longa_permanencia_seg = filter_input(INPUT_POST, "longa_permanencia_seg", FILTER_SANITIZE_SPECIAL_CHARS);

        $logo_seg = (string)($arquivo ?? '');

        $seguradora = new seguradora();

        // Validação mínima de dados
        if (!empty($seguradora_seg)) {

            $seguradora->seguradora_seg = $seguradora_seg;
            $seguradora->endereco_seg = $endereco_seg;
            $seguradora->bairro_seg = $bairro_seg;
            $seguradora->email01_seg = $email01_seg;
            $seguradora->email02_seg = $email02_seg;
            $seguradora->cidade_seg = $cidade_seg;
            $seguradora->estado_seg = $estado_seg;
            $seguradora->cnpj_seg = $cnpj_seg;
            $seguradora->telefone01_seg = $telefone01_seg;
            $seguradora->telefone02_seg = $telefone02_seg;
            $seguradora->numero_seg = $numero_seg;
            $seguradora->numero_seg = $numero_seg;
            $seguradora->data_create_seg = $data_create_seg;
            $seguradora->fk_usuario_seg = $fk_usuario_seg;
            $seguradora->usuario_create_seg = $usuario_create_seg;
            $seguradora->coordenador_seg = $coordenador_seg;
            $seguradora->contato_seg = $contato_seg;
            $seguradora->coord_rh_seg = $coord_rh_seg;
            $seguradora->ativo_seg = $ativo_seg;
            $seguradora->dias_visita_seg = $dias_visita_seg;
            $seguradora->dias_visita_uti_seg = $dias_visita_uti_seg;
            $seguradora->valor_alto_custo_seg = $valor_alto_custo_seg;
            $seguradora->longa_permanencia_seg = $longa_permanencia_seg;
            $seguradora->logo_seg = $logo_seg;
            $seguradora->deletado_seg = $deletado_seg;
            $seguradora->cep_seg = $cep_seg;

            $seguradoraDao->create($seguradora);
            $idNovo = (int) $conn->lastInsertId();

            $enderecos = [];
            $endTipo = postArraySeg('end_tipo');
            $endCep = postArraySeg('end_cep');
            $endLog = postArraySeg('end_logradouro');
            $endNum = postArraySeg('end_numero');
            $endBairro = postArraySeg('end_bairro');
            $endCidade = postArraySeg('end_cidade');
            $endEstado = postArraySeg('end_estado');
            $endComp = postArraySeg('end_complemento');
            $endPrin = postArraySeg('end_principal');
            $endCount = max(count($endTipo), count($endLog));
            for ($i = 0; $i < $endCount; $i++) {
                $enderecos[] = ['tipo' => $endTipo[$i] ?? '', 'cep' => $endCep[$i] ?? '', 'endereco' => $endLog[$i] ?? '', 'numero' => $endNum[$i] ?? '', 'bairro' => $endBairro[$i] ?? '', 'cidade' => $endCidade[$i] ?? '', 'estado' => $endEstado[$i] ?? '', 'complemento' => $endComp[$i] ?? '', 'principal' => $endPrin[$i] ?? 'n'];
            }
            if (empty($enderecos) && !empty($endereco_seg)) {
                $enderecos[] = ['tipo' => 'Principal', 'cep' => $cep_seg, 'endereco' => $endereco_seg, 'numero' => $numero_seg, 'bairro' => $bairro_seg, 'cidade' => $cidade_seg, 'estado' => $estado_seg, 'complemento' => '', 'principal' => 's'];
            }

            $telefones = [];
            $telTipo = postArraySeg('tel_tipo');
            $telNumero = postArraySeg('tel_numero');
            $telRamal = postArraySeg('tel_ramal');
            $telContato = postArraySeg('tel_contato');
            $telPrin = postArraySeg('tel_principal');
            $telCount = max(count($telTipo), count($telNumero));
            for ($i = 0; $i < $telCount; $i++) {
                $telefones[] = ['tipo' => $telTipo[$i] ?? '', 'numero' => $telNumero[$i] ?? '', 'ramal' => $telRamal[$i] ?? '', 'contato' => $telContato[$i] ?? '', 'principal' => $telPrin[$i] ?? 'n'];
            }
            if (empty($telefones) && !empty($telefone01_seg)) {
                $telefones[] = ['tipo' => 'Principal', 'numero' => $telefone01_seg, 'ramal' => '', 'contato' => '', 'principal' => 's'];
                if (!empty($telefone02_seg)) $telefones[] = ['tipo' => 'Alternativo', 'numero' => $telefone02_seg, 'ramal' => '', 'contato' => '', 'principal' => 'n'];
            }

            $contatos = [];
            $contNome = postArraySeg('cont_nome');
            $contCargo = postArraySeg('cont_cargo');
            $contSetor = postArraySeg('cont_setor');
            $contEmail = postArraySeg('cont_email');
            $contTelefone = postArraySeg('cont_telefone');
            $contPrin = postArraySeg('cont_principal');
            $contCount = max(count($contNome), count($contEmail));
            for ($i = 0; $i < $contCount; $i++) {
                $contatos[] = ['nome' => $contNome[$i] ?? '', 'cargo' => $contCargo[$i] ?? '', 'setor' => $contSetor[$i] ?? '', 'email' => $contEmail[$i] ?? '', 'telefone' => $contTelefone[$i] ?? '', 'principal' => $contPrin[$i] ?? 'n'];
            }
            if (empty($contatos) && !empty($contato_seg)) {
                $contatos[] = ['nome' => $contato_seg, 'cargo' => 'Contato', 'setor' => '', 'email' => $email01_seg, 'telefone' => $telefone01_seg, 'principal' => 's'];
            }

            insertSeguradoraRelatedRows($conn, $idNovo, $enderecos, $telefones, $contatos);
            $seguradoraCriada = $idNovo > 0 ? $seguradoraDao->findById($idNovo) : null;
            fullcareAuditLog($conn, [
                'action' => 'create',
                'entity_type' => 'seguradora',
                'entity_id' => $idNovo > 0 ? $idNovo : null,
                'summary' => 'Seguradora criada.',
                'after' => $seguradoraCriada ?: $seguradora,
                'context' => [
                    'enderecos' => count($enderecos),
                    'telefones' => count($telefones),
                    'contatos' => count($contatos),
                ],
                'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
                'source' => 'process_seguradora.php',
            ], $BASE_URL);
            header("Location: " . $BASE_URL . "seguradoras");
        }
} else if ($type === "update") {
    $arquivo = null;
    try {
        $arquivo = storeSeguradoraLogo($_FILES['logo_seg'] ?? [], __DIR__ . '/uploads');
    } catch (Throwable $e) {
        $message->setMessage($e->getMessage(), "error", "back");
        exit;
    }

        // Receber os dados dos inputs
        $id_seguradora = filter_input(INPUT_POST, "id_seguradora");
        $seguradora_seg = filter_input(INPUT_POST, "seguradora_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $seguradora_seg = strtoupper($seguradora_seg);
        $endereco_seg = filter_input(INPUT_POST, "endereco_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $email01_seg = filter_input(INPUT_POST, "email01_seg", FILTER_SANITIZE_EMAIL);
        $email01_seg = strtolower($email01_seg);

        $email02_seg = filter_input(INPUT_POST, "email02_seg", FILTER_SANITIZE_EMAIL);
        $email02_seg = strtolower($email02_seg);

        $cidade_seg = filter_input(INPUT_POST, "cidade_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $estado_seg = filter_input(INPUT_POST, "estado_seg", FILTER_SANITIZE_SPECIAL_CHARS);

        $cnpj_seg = filter_input(INPUT_POST, "cnpj_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $cnpj_seg = str_replace('/', '', $cnpj_seg);
        $cnpj_seg = str_replace('-', '', $cnpj_seg);
        $cnpj_seg = str_replace('.', '', $cnpj_seg);

        $telefone01_seg = filter_input(INPUT_POST, "telefone01_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $telefone01_seg = str_replace('-', '', $telefone01_seg);
        $telefone01_seg = str_replace('(', '', $telefone01_seg);
        $telefone01_seg = str_replace(') ', '', $telefone01_seg);

        $telefone02_seg = filter_input(INPUT_POST, "telefone02_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $telefone02_seg = str_replace('-', '', $telefone02_seg);
        $telefone02_seg = str_replace('(', '', $telefone02_seg);
        $telefone02_seg = str_replace(') ', '', $telefone02_seg);

        $numero_seg = filter_input(INPUT_POST, "numero_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $bairro_seg = filter_input(INPUT_POST, "bairro_seg");
        $data_create_seg = filter_input(INPUT_POST, "data_create_seg");
        $usuario_create_seg = filter_input(INPUT_POST, "usuario_create_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $fk_usuario_seg = filter_input(INPUT_POST, "fk_usuario_seg");
        $coordenador_seg = filter_input(INPUT_POST, "coordenador_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $contato_seg = filter_input(INPUT_POST, "contato_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $ativo_seg = filter_input(INPUT_POST, "ativo_seg");
        $cep_seg = filter_input(INPUT_POST, "cep_seg");

        $dias_visita_seg = filter_input(INPUT_POST, "dias_visita_seg");
        $dias_visita_uti_seg = filter_input(INPUT_POST, "dias_visita_uti_seg");
        $longa_permanencia_seg = filter_input(INPUT_POST, "longa_permanencia_seg");
        $valor_alto_custo_seg = filter_input(INPUT_POST, "valor_alto_custo_seg");
        $valor_alto_custo_seg = str_replace('R$', '', $valor_alto_custo_seg);
        $valor_alto_custo_seg = str_replace('.', '', $valor_alto_custo_seg);
        // $valor_alto_custo_seg = str_replace('(', '', $valor_alto_custo_seg);

        $deletado_seg = filter_input(INPUT_POST, "deletado_seg");

        $coord_rh_seg = filter_input(INPUT_POST, "coord_rh_seg", FILTER_SANITIZE_SPECIAL_CHARS);
        $seguradoraData = $seguradoraDao->findById($id_seguradora);
        $seguradoraAntes = $seguradoraData ? clone $seguradoraData : null;
        $logo_seg = $arquivo !== null ? $arquivo : (string)($seguradoraData->logo_seg ?? '');

        $seguradoraData->id_seguradora = $id_seguradora;
        $seguradoraData->seguradora_seg = $seguradora_seg;
        $seguradoraData->endereco_seg = $endereco_seg;
        $seguradoraData->email01_seg = $email01_seg;
        $seguradoraData->email02_seg = $email02_seg;
        $seguradoraData->cidade_seg = $cidade_seg;
        $seguradoraData->estado_seg = $estado_seg;
        $seguradoraData->cnpj_seg = $cnpj_seg;
        $seguradoraData->telefone01_seg = $telefone01_seg;
        $seguradoraData->telefone02_seg = $telefone02_seg;
        $seguradoraData->numero_seg = $numero_seg;
        $seguradoraData->bairro_seg = $bairro_seg;
        $seguradoraData->data_create_seg = $data_create_seg;
        $seguradoraData->fk_usuario_seg = $fk_usuario_seg;
        $seguradoraData->usuario_create_seg = $usuario_create_seg;
        $seguradoraData->coordenador_seg = $coordenador_seg;
        $seguradoraData->contato_seg = $contato_seg;
        $seguradoraData->ativo_seg = $ativo_seg;
        $seguradoraData->coord_rh_seg = $coord_rh_seg;
        $seguradoraData->valor_alto_custo_seg = $valor_alto_custo_seg;
        $seguradoraData->dias_visita_uti_seg = $dias_visita_uti_seg;
        $seguradoraData->dias_visita_seg = $dias_visita_seg;
        $seguradoraData->longa_permanencia_seg = $longa_permanencia_seg;
        $seguradoraData->deletado_seg = $deletado_seg;
        $seguradoraData->logo_seg = $logo_seg;
        $seguradoraData->cep_seg = $cep_seg;

        $seguradoraDao->update($seguradoraData);

        $hasRelatedPayload = isset($_POST['end_tipo']) || isset($_POST['tel_tipo']) || isset($_POST['cont_nome']);
        if ($hasRelatedPayload) {
            $conn->prepare("DELETE FROM tb_seguradora_endereco WHERE fk_seguradora = :id")->execute([':id' => (int) $id_seguradora]);
            $conn->prepare("DELETE FROM tb_seguradora_telefone WHERE fk_seguradora = :id")->execute([':id' => (int) $id_seguradora]);
            $conn->prepare("DELETE FROM tb_seguradora_contato WHERE fk_seguradora = :id")->execute([':id' => (int) $id_seguradora]);
        }

        $enderecos = [];
        $endTipo = postArraySeg('end_tipo');
        $endCep = postArraySeg('end_cep');
        $endLog = postArraySeg('end_logradouro');
        $endNum = postArraySeg('end_numero');
        $endBairro = postArraySeg('end_bairro');
        $endCidade = postArraySeg('end_cidade');
        $endEstado = postArraySeg('end_estado');
        $endComp = postArraySeg('end_complemento');
        $endPrin = postArraySeg('end_principal');
        $endCount = max(count($endTipo), count($endLog));
        for ($i = 0; $i < $endCount; $i++) {
            $enderecos[] = ['tipo' => $endTipo[$i] ?? '', 'cep' => $endCep[$i] ?? '', 'endereco' => $endLog[$i] ?? '', 'numero' => $endNum[$i] ?? '', 'bairro' => $endBairro[$i] ?? '', 'cidade' => $endCidade[$i] ?? '', 'estado' => $endEstado[$i] ?? '', 'complemento' => $endComp[$i] ?? '', 'principal' => $endPrin[$i] ?? 'n'];
        }
        if (empty($enderecos) && !empty($endereco_seg)) {
            $enderecos[] = ['tipo' => 'Principal', 'cep' => $cep_seg, 'endereco' => $endereco_seg, 'numero' => $numero_seg, 'bairro' => $bairro_seg, 'cidade' => $cidade_seg, 'estado' => $estado_seg, 'complemento' => '', 'principal' => 's'];
        }

        $telefones = [];
        $telTipo = postArraySeg('tel_tipo');
        $telNumero = postArraySeg('tel_numero');
        $telRamal = postArraySeg('tel_ramal');
        $telContato = postArraySeg('tel_contato');
        $telPrin = postArraySeg('tel_principal');
        $telCount = max(count($telTipo), count($telNumero));
        for ($i = 0; $i < $telCount; $i++) {
            $telefones[] = ['tipo' => $telTipo[$i] ?? '', 'numero' => $telNumero[$i] ?? '', 'ramal' => $telRamal[$i] ?? '', 'contato' => $telContato[$i] ?? '', 'principal' => $telPrin[$i] ?? 'n'];
        }
        if (empty($telefones) && !empty($telefone01_seg)) {
            $telefones[] = ['tipo' => 'Principal', 'numero' => $telefone01_seg, 'ramal' => '', 'contato' => '', 'principal' => 's'];
            if (!empty($telefone02_seg)) $telefones[] = ['tipo' => 'Alternativo', 'numero' => $telefone02_seg, 'ramal' => '', 'contato' => '', 'principal' => 'n'];
        }

        $contatos = [];
        $contNome = postArraySeg('cont_nome');
        $contCargo = postArraySeg('cont_cargo');
        $contSetor = postArraySeg('cont_setor');
        $contEmail = postArraySeg('cont_email');
        $contTelefone = postArraySeg('cont_telefone');
        $contPrin = postArraySeg('cont_principal');
        $contCount = max(count($contNome), count($contEmail));
        for ($i = 0; $i < $contCount; $i++) {
            $contatos[] = ['nome' => $contNome[$i] ?? '', 'cargo' => $contCargo[$i] ?? '', 'setor' => $contSetor[$i] ?? '', 'email' => $contEmail[$i] ?? '', 'telefone' => $contTelefone[$i] ?? '', 'principal' => $contPrin[$i] ?? 'n'];
        }
        if (empty($contatos) && !empty($contato_seg)) {
            $contatos[] = ['nome' => $contato_seg, 'cargo' => 'Contato', 'setor' => '', 'email' => $email01_seg, 'telefone' => $telefone01_seg, 'principal' => 's'];
        }

        if ($hasRelatedPayload) {
            insertSeguradoraRelatedRows($conn, (int) $id_seguradora, $enderecos, $telefones, $contatos);
        }

        $seguradoraDepois = $seguradoraDao->findById((int)$id_seguradora);
        fullcareAuditLog($conn, [
            'action' => 'update',
            'entity_type' => 'seguradora',
            'entity_id' => (int)$id_seguradora,
            'summary' => 'Seguradora atualizada.',
            'before' => $seguradoraAntes,
            'after' => $seguradoraDepois,
            'context' => [
                'enderecos' => count($enderecos),
                'telefones' => count($telefones),
                'contatos' => count($contatos),
            ],
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_seguradora.php',
        ], $BASE_URL);

        header("Location: " . $BASE_URL . "seguradoras");
        exit;
}

if ($type === "delete") {
    // Recebe os dados do form
    $id_seguradora = filter_input(INPUT_POST, "id_seguradora");

    $seguradoraDao = new seguradoraDAO($conn, $BASE_URL);

    $seguradora = $seguradoraDao->findById($id_seguradora);

    if ($seguradora) {
        $seguradoraAntesDelete = clone $seguradora;
        $seguradoraDao->destroy($id_seguradora);
        fullcareAuditLog($conn, [
            'action' => 'delete',
            'entity_type' => 'seguradora',
            'entity_id' => (int)$id_seguradora,
            'summary' => 'Seguradora excluída.',
            'before' => $seguradoraAntesDelete,
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_seguradora.php',
        ], $BASE_URL);
        header("Location: " . $BASE_URL . "seguradoras");
        exit;
    } else {

        //$message->setMessage("Informações inválidas!", "error", "index.php");
    }
}

if ($type === "delUpdate") {

    $seguradoraDao = new seguradoraDAO($conn, $BASE_URL);

    $id_seguradora = filter_input(INPUT_POST, "id_seguradora");
    $deletado_seg = 's';

    $seguradoraData = $seguradoraDao->findById($id_seguradora);
    $seguradoraAntesSoftDelete = $seguradoraData ? clone $seguradoraData : null;

    $seguradoraData->id_seguradora = $id_seguradora;
    $seguradoraData->deletado_seg = $deletado_seg;

    $seguradoraDao->deletarUpdate($seguradoraData);
    $seguradoraDepoisSoftDelete = $seguradoraDao->findById((int)$id_seguradora);
    fullcareAuditLog($conn, [
        'action' => 'soft_delete',
        'entity_type' => 'seguradora',
        'entity_id' => (int)$id_seguradora,
        'summary' => 'Seguradora marcada como deletada.',
        'before' => $seguradoraAntesSoftDelete,
        'after' => $seguradoraDepoisSoftDelete,
        'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
        'source' => 'process_seguradora.php',
    ], $BASE_URL);

    header("Location: " . $BASE_URL . "seguradoras");
    exit;
}

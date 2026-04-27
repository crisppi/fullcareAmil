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

require_once("models/imagem.php");
require_once("dao/imagemDao.php");

require_once("models/usuario.php");
require_once("dao/usuarioDao.php");

require_once("models/message.php");
require_once("utils/audit_logger.php");

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$imagemDao = new imagemDAO($conn, $BASE_URL);

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");
$fk_imagem = filter_input(INPUT_POST, "fk_imagem");

// Resgata dados do usuário
if ($type === "create") {

    // Receber os dados dos inputs
    $imagemSegImg = filter_input(INPUT_POST, "imagemSegImg");
    $pasta_temp = $_FILES['imagem']['tmp_name'] ?? '';
    $arquivoOrig = (string)($_FILES['imagem']['name'] ?? '');

    if (!is_uploaded_file($pasta_temp)) {
        http_response_code(400);
        exit('Upload inválido');
    }

    $size = (int)($_FILES['imagem']['size'] ?? 0);
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        http_response_code(400);
        exit('Arquivo inválido ou maior que 2MB');
    }

    $ext = strtolower(pathinfo($arquivoOrig, PATHINFO_EXTENSION));
    $allowed = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    if (!isset($allowed[$ext])) {
        http_response_code(400);
        exit('Extensão de arquivo não permitida');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($pasta_temp);
    if ($mime !== $allowed[$ext]) {
        http_response_code(400);
        exit('Tipo MIME de arquivo não permitido');
    }

    $arquivo = bin2hex(random_bytes(8)) . '.' . $ext;
    $conteudo = file_get_contents($pasta_temp);
    $dataImg = base64_encode($conteudo);
    $pasta = "uploads";
    move_uploaded_file($pasta_temp, $pasta . "/" . $arquivo);

    $imagem = new imagem();

    // Validação mínima de dados
    if (3 < 4) {

        $imagem->fk_imagem = $fk_imagem;
        $imagem->imagem_img = $dataImg;
        $imagem->imagem_name_img = $arquivo;


        $imagemDao->create($imagem);
        $idImagem = (int)$conn->lastInsertId();
        fullcareAuditLog($conn, [
            'action' => 'create',
            'entity_type' => 'imagem',
            'entity_id' => $idImagem > 0 ? $idImagem : null,
            'after' => [
                'id_imagem' => $idImagem > 0 ? $idImagem : null,
                'fk_imagem' => $imagem->fk_imagem,
                'imagem_name_img' => $imagem->imagem_name_img,
            ],
            'source' => 'process_imagem.php',
        ], $BASE_URL);
    }
}

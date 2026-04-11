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
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("app/passwordPolicy.php");

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);

function normalizeCargoLabel(?string $cargo): string
{
    $cargo = mb_strtolower(trim((string)$cargo), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $cargo);
    $cargo = $ascii !== false ? $ascii : $cargo;
    return preg_replace('/[^a-z]/', '', $cargo);
}

function isGestorSeguradoraCargo(?string $cargo): bool
{
    $norm = normalizeCargoLabel($cargo);
    return strpos($norm, 'gestorseguradora') === 0;
}

function getSeguradoraNomeById(PDO $conn, ?int $seguradoraId): ?string
{
    $id = (int)($seguradoraId ?? 0);
    if ($id <= 0) {
        return null;
    }

    try {
        $stmt = $conn->prepare("SELECT seguradora_seg FROM tb_seguradora WHERE id_seguradora = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $nome = trim((string)($stmt->fetchColumn() ?: ''));
        return $nome !== '' ? $nome : null;
    } catch (Throwable $e) {
        return null;
    }
}

function resolveUsuarioHomeUrl(string $baseUrl, ?string $cargo, $nivel): string
{
    $nivel = (int)$nivel;
    $cargo = mb_strtolower(trim((string)$cargo), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $cargo);
    $cargo = $ascii !== false ? $ascii : $cargo;
    $cargo = preg_replace('/[^a-z]/', '', $cargo);

    $isDiretoria = in_array($cargo, ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
        || strpos($cargo, 'diretor') !== false
        || strpos($cargo, 'diretoria') !== false
        || $nivel === -1;

    if ($nivel === -1) {
        return $baseUrl . 'list_internacao_cap_fin.php';
    }

    return $isDiretoria ? $baseUrl . 'dashboard' : $baseUrl . 'menu_app.php';
}

function storeUsuarioImage(array $file, string $targetDir, int $maxBytes = 2097152): ?string
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload da imagem.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $originalName = (string)($file['name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        throw new RuntimeException('Imagem inválida ou maior que 2MB.');
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
    ];
    if (!isset($allowed[$ext])) {
        throw new RuntimeException('Extensão de imagem não permitida.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    if ($mime !== $allowed[$ext]) {
        throw new RuntimeException('Tipo MIME da imagem inválido.');
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Não foi possível preparar diretório de upload.');
    }

    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($tmp, rtrim($targetDir, '/') . '/' . $safeName)) {
        throw new RuntimeException('Não foi possível salvar a imagem enviada.');
    }

    return $safeName;
}

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");

// Resgata dados do usuário

if ($type === "create") {
    $arquivo = null;
    try {
        $arquivo = storeUsuarioImage($_FILES['foto_usuario'] ?? [], __DIR__ . '/uploads/usuarios');
    } catch (Throwable $e) {
        $message->setMessage($e->getMessage(), "error", "back");
        exit;
    }
        // Receber os dados dos inputs
        $usuario_user = filter_input(INPUT_POST, "usuario_user");
        $login_user = filter_input(INPUT_POST, "login_user");
        $fk_usuario_user = filter_input(INPUT_POST, "fk_usuario_user");
        $sexo_user = filter_input(INPUT_POST, "sexo_user");
        $idade_user = filter_input(INPUT_POST, "idade_user");
        $endereco_user = filter_input(INPUT_POST, "endereco_user");
        $numero_user = filter_input(INPUT_POST, "numero_user");
        $cidade_user = filter_input(INPUT_POST, "cidade_user");
        $bairro_user = filter_input(INPUT_POST, "bairro_user");
        $estado_user = filter_input(INPUT_POST, "estado_user");

        $cpf_user = filter_input(INPUT_POST, "cpf_user");
        $cpf_user = str_replace('-', '', $cpf_user);
        $cpf_user = str_replace('.', '', $cpf_user);

        $telefone01_user = filter_input(INPUT_POST, "telefone01_user");
        $telefone01_user = str_replace('-', '', $telefone01_user);
        $telefone01_user = str_replace('(', '', $telefone01_user);
        $telefone01_user = str_replace(') ', '', $telefone01_user);

        $telefone02_user = filter_input(INPUT_POST, "telefone02_user");
        $telefone02_user = str_replace('-', '', $telefone02_user);
        $telefone02_user = str_replace('(', '', $telefone02_user);
        $telefone02_user = str_replace(') ', '', $telefone02_user);
        $telefone02_user = filter_input(INPUT_POST, "telefone02_user");

        $email_user = filter_input(INPUT_POST, "email_user");
        $email_user = strtolower($email_user);

        $email02_user = filter_input(INPUT_POST, "email02_user");
        $email02_user = strtolower($email02_user);

        $ativo_user = filter_input(INPUT_POST, "ativo_user");
        $vinculo_user = filter_input(INPUT_POST, "vinculo_user");
        $depto_user = filter_input(INPUT_POST, "depto_user");
        $nivel_user = filter_input(INPUT_POST, "nivel_user");
        $reg_profissional_user = filter_input(INPUT_POST, "reg_profissional_user");
        $tipo_reg_user = filter_input(INPUT_POST, "tipo_reg_user");
        $depto_user = filter_input(INPUT_POST, "depto_user");

        $usuario_create_user = filter_input(INPUT_POST, "usuario_create_user");
        $data_create_user = filter_input(INPUT_POST, "data_create_user");
        $data_admissao_user = filter_input(INPUT_POST, "data_admissao_user") ?: null;
        $cargo_user = filter_input(INPUT_POST, "cargo_user");
        $obs_user = filter_input(INPUT_POST, "obs_user");
        $senha_default_user = filter_input(INPUT_POST, "senha_default_user");
        $fk_seguradora_user = filter_input(INPUT_POST, "fk_seguradora_user", FILTER_VALIDATE_INT);
        if (isGestorSeguradoraCargo($cargo_user)) {
            $segNome = getSeguradoraNomeById($conn, $fk_seguradora_user);
            if ($segNome) {
                $cargo_user = 'Gestor Seguradora - ' . $segNome;
            } else {
                $cargo_user = 'Gestor Seguradora';
                $fk_seguradora_user = null;
            }
        } else {
            $fk_seguradora_user = null;
        }

        // $hash_user = password_hash(filter_input(INPUT_POST, "senha_user"), PASSWORD_DEFAULT);
        // $senha_user = filter_input(INPUT_POST, "senha_user");
        $senha_user_raw = (string)filter_input(INPUT_POST, "senha_user");
        $passwordErrors = password_policy_errors($senha_user_raw);
        if ($passwordErrors) {
            $message->setMessage($passwordErrors[0], "error", "back");
            exit;
        }
        $senha_user = password_hash($senha_user_raw, PASSWORD_DEFAULT);

        $foto_usuario = (string)($arquivo ?? '');
        $usuario = new Usuario();

        // Validação mínima de dados
        if (!empty($usuario_user)) {

            $usuario->usuario_user = $usuario_user;
            $usuario->login_user = $login_user;
            $usuario->fk_usuario_user = $fk_usuario_user;
            $usuario->sexo_user = $sexo_user;
            $usuario->idade_user = $idade_user;

            $usuario->endereco_user = $endereco_user;
            $usuario->numero_user = $numero_user;
            $usuario->bairro_user = $bairro_user;
            $usuario->cidade_user = $cidade_user;
            $usuario->estado_user = $estado_user;

            $usuario->email_user = $email_user;
            $usuario->email02_user = $email02_user;

            $usuario->telefone01_user = $telefone02_user;
            $usuario->telefone02_user = $telefone02_user;
            $usuario->ativo_user = $ativo_user;

            $usuario->reg_profissional_user = $reg_profissional_user;
            $usuario->tipo_reg_user = $tipo_reg_user;

            $usuario->cpf_user = $cpf_user;
            $usuario->senha_user = $senha_user;
            $usuario->senha_default_user = $senha_default_user;

            $usuario->usuario_create_user = $usuario_create_user;
            $usuario->data_admissao_user = $data_admissao_user;
            $usuario->data_create_user = $data_create_user;

            $usuario->vinculo_user = $vinculo_user;
            $usuario->nivel_user = $nivel_user;
            $usuario->depto_user = $depto_user;
            $usuario->cargo_user = $cargo_user;
            $usuario->obs_user = $obs_user;
            $usuario->foto_usuario = $foto_usuario;
            $usuario->fk_seguradora_user = $fk_seguradora_user;

            $userDao->create($usuario);
            header("location:list_usuario.php");
        } else {

            //$message->setMessage("Você precisa adicionar pelo menos: nome do useriente!", "error", "back");
        }
} else if ($type === "update") {
    $arquivo = null;
    try {
        $arquivo = storeUsuarioImage($_FILES['foto_usuario'] ?? [], __DIR__ . '/uploads/usuarios');
    } catch (Throwable $e) {
        $message->setMessage($e->getMessage(), "error", "back");
        exit;
    }
        $usuarioDao = new userDAO($conn, $BASE_URL);

        // Receber os dados dos inputs
        $id_usuario = filter_input(INPUT_POST, "id_usuario");
        $usuario_user = filter_input(INPUT_POST, "usuario_user");
        $login_user = filter_input(INPUT_POST, "login_user");
        $fk_usuario_user = filter_input(INPUT_POST, "fk_usuario_user");
        $sexo_user = filter_input(INPUT_POST, "sexo_user");
        $idade_user = filter_input(INPUT_POST, "idade_user");

        $cpf_user = filter_input(INPUT_POST, "cpf_user");
        $cpf_user = str_replace('-', '', $cpf_user);
        $cpf_user = str_replace('.', '', $cpf_user);

        $endereco_user = filter_input(INPUT_POST, "endereco_user");
        $numero_user = filter_input(INPUT_POST, "numero_user");
        $cidade_user = filter_input(INPUT_POST, "cidade_user");
        $bairro_user = filter_input(INPUT_POST, "bairro_user");
        $estado_user = filter_input(INPUT_POST, "estado_user") ?: null;

        $email_user = filter_input(INPUT_POST, "email_user");
        $email02_user = filter_input(INPUT_POST, "email02_user");

        $telefone01_user = filter_input(INPUT_POST, "telefone01_user");
        $telefone01_user = str_replace('-', '', $telefone01_user);
        $telefone01_user = str_replace('(', '', $telefone01_user);
        $telefone01_user = str_replace(') ', '', $telefone01_user);

        $telefone02_user = filter_input(INPUT_POST, "telefone02_user");
        $telefone02_user = str_replace('-', '', $telefone02_user);
        $telefone02_user = str_replace('(', '', $telefone02_user);
        $telefone02_user = str_replace(') ', '', $telefone02_user);
        $telefone02_user = filter_input(INPUT_POST, "telefone02_user");

        $ativo_user = filter_input(INPUT_POST, "ativo_user");
        $usuario_create_user = filter_input(INPUT_POST, "usuario_create_user");
        $data_create_user = filter_input(INPUT_POST, "data_create_user");

        $cargo_user = filter_input(INPUT_POST, "cargo_user");
        $depto_user = filter_input(INPUT_POST, "depto_user");
        $vinculo_user = filter_input(INPUT_POST, "vinculo_user");
        $nivel_user = filter_input(INPUT_POST, "nivel_user");
        $fk_seguradora_user = filter_input(INPUT_POST, "fk_seguradora_user", FILTER_VALIDATE_INT);
        if (isGestorSeguradoraCargo($cargo_user)) {
            $segNome = getSeguradoraNomeById($conn, $fk_seguradora_user);
            if ($segNome) {
                $cargo_user = 'Gestor Seguradora - ' . $segNome;
            } else {
                $cargo_user = 'Gestor Seguradora';
                $fk_seguradora_user = null;
            }
        } else {
            $fk_seguradora_user = null;
        }

        $senha_user_raw = trim((string)filter_input(INPUT_POST, "senha_user"));
        $senha_default_user = filter_input(INPUT_POST, "senha_default_user");

        $reg_profissional_user = filter_input(INPUT_POST, "reg_profissional_user");
        $tipo_reg_user = filter_input(INPUT_POST, "tipo_reg_user");

        $data_admissao_user = filter_input(INPUT_POST, "data_admissao_user") ?: null;
        $data_demissao_user = filter_input(INPUT_POST, "data_demissao_user") ?: null;

        $obs_user = filter_input(INPUT_POST, "obs_user");

        $usuarioData = $usuarioDao->findById_user($id_usuario);
        $foto_usuario = $arquivo !== null ? $arquivo : (string)($usuarioData->foto_usuario ?? '');

        if ($senha_user_raw !== '') {
            $passwordErrors = password_policy_errors($senha_user_raw);
            if ($passwordErrors) {
                $message->setMessage($passwordErrors[0], "error", "back");
                exit;
            }
            $senha_user = password_hash($senha_user_raw, PASSWORD_DEFAULT);
        } else {
            $senha_user = (string)($usuarioData->senha_user ?? '');
            $senha_default_user = (string)($usuarioData->senha_default_user ?? $senha_default_user);
        }

        $usuarioData->id_usuario = $id_usuario;
        $usuarioData->usuario_user = $usuario_user;
        $usuarioData->login_user = $login_user;
        $usuarioData->fk_usuario_user = $fk_usuario_user;
        $usuarioData->sexo_user = $sexo_user;
        $usuarioData->idade_user = $idade_user;

        $usuarioData->endereco_user = $endereco_user;
        $usuarioData->cidade_user = $cidade_user;
        $usuarioData->numero_user = $numero_user;
        $usuarioData->bairro_user = $bairro_user;
        $usuarioData->estado_user = $estado_user;

        $usuarioData->email_user = $email_user;
        $usuarioData->email02_user = $email02_user;

        $usuarioData->telefone01_user = $telefone01_user;
        $usuarioData->telefone02_user = $telefone02_user;

        $usuarioData->usuario_create_user = $usuario_create_user;
        $usuarioData->data_create_user = $data_create_user;

        $usuarioData->cargo_user = $cargo_user;
        $usuarioData->depto_user = $depto_user;
        $usuarioData->vinculo_user = $vinculo_user;
        $usuarioData->nivel_user = $nivel_user;

        $usuarioData->senha_user = $senha_user;
        $usuarioData->ativo_user = $ativo_user;
        $usuarioData->senha_default_user = $senha_default_user;

        $usuarioData->reg_profissional_user = $reg_profissional_user;
        $usuarioData->tipo_reg_user = $tipo_reg_user;

        $usuarioData->data_admissao_user = $data_admissao_user;
        $usuarioData->data_demissao_user = $data_demissao_user;

        $usuarioData->cpf_user = $cpf_user;
        $usuarioData->obs_user = $obs_user;

        $usuarioData->foto_usuario = $foto_usuario;
        $usuarioData->fk_seguradora_user = $fk_seguradora_user;

        $usuarioDao->update($usuarioData);

        header("location:list_usuario.php");
}
// atualizacao de senha default //
if ($type === "update-senha") {

    $senha_user = (string)filter_input(INPUT_POST, "nova_senha_user");
    $senha_default_user = filter_input(INPUT_POST, "senha_default_user");
    $senha_usuario = (string)filter_input(INPUT_POST, "senha_user");
    $usuarioDao = new userDAO($conn, $BASE_URL);

    // Receber os dados dos inputs
    $id_usuario = filter_input(INPUT_POST, "id_usuario");

    $usuarioData = $usuarioDao->findById_user($id_usuario);
    if (!$usuarioData) {
        $message->setMessage("Usuário não encontrado.", "error", "back");
        exit;
    }

    if ($senha_usuario === '' || !password_verify($senha_usuario, (string)$usuarioData->senha_user)) {
        $message->setMessage("Senha atual incorreta.", "error", "back");
        exit;
    }

    $passwordErrors = password_policy_errors($senha_user);
    if ($passwordErrors) {
        $message->setMessage($passwordErrors[0], "error", "back");
        exit;
    }

    $usuarioData->id_usuario = $id_usuario;

    $usuarioData->senha_user = password_hash($senha_user, PASSWORD_DEFAULT);
    $usuarioData->senha_default_user = $senha_default_user;

    $usuarioDao->update($usuarioData);

    header("Location: " . resolveUsuarioHomeUrl($BASE_URL, $usuarioData->cargo_user ?? '', $usuarioData->nivel_user ?? 0));
    exit;
}

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
require_once("models/solicitacao_customizacao.php");
require_once("dao/solicitacaoCustomizacaoDao.php");
require_once("utils/audit_logger.php");

$message = new Message($BASE_URL);
$dao = new SolicitacaoCustomizacaoDAO($conn, $BASE_URL);

$type = filter_input(INPUT_POST, 'type');
$idSolicitacao = filter_input(INPUT_POST, 'id_solicitacao', FILTER_VALIDATE_INT);

$emailSessao = strtolower(trim((string)($_SESSION['email_user'] ?? '')));
$isConex = $emailSessao !== '' && strpos($emailSessao, '@conex.') !== false;

$nome = trim((string)filter_input(INPUT_POST, 'nome'));
$empresa = trim((string)filter_input(INPUT_POST, 'empresa'));
$cargo = trim((string)filter_input(INPUT_POST, 'cargo'));
$email = trim((string)filter_input(INPUT_POST, 'email'));
$telefone = trim((string)filter_input(INPUT_POST, 'telefone'));
$dataSolicitacao = filter_input(INPUT_POST, 'data_solicitacao');
$moduloOutro = trim((string)filter_input(INPUT_POST, 'modulo_outro'));
$descricao = trim((string)filter_input(INPUT_POST, 'descricao'));
$problemaAtual = trim((string)filter_input(INPUT_POST, 'problema_atual'));
$resultadoEsperado = trim((string)filter_input(INPUT_POST, 'resultado_esperado'));
$impactoNivel = trim((string)filter_input(INPUT_POST, 'impacto_nivel'));
$descricaoImpacto = trim((string)filter_input(INPUT_POST, 'descricao_impacto'));
$prioridade = trim((string)filter_input(INPUT_POST, 'prioridade'));
$prazoDesejado = filter_input(INPUT_POST, 'prazo_desejado');
$responsavel = trim((string)filter_input(INPUT_POST, 'responsavel'));
$assinatura = trim((string)filter_input(INPUT_POST, 'assinatura'));
$dataAprovacao = filter_input(INPUT_POST, 'data_aprovacao');
$prazoResposta = trim((string)filter_input(INPUT_POST, 'prazo_resposta'));
$precificacao = trim((string)filter_input(INPUT_POST, 'precificacao'));
$observacoesResposta = trim((string)filter_input(INPUT_POST, 'observacoes_resposta'));
$aprovacaoResposta = trim((string)filter_input(INPUT_POST, 'aprovacao_resposta'));
$dataResposta = filter_input(INPUT_POST, 'data_resposta');
$aprovacaoConex = trim((string)filter_input(INPUT_POST, 'aprovacao_conex'));
$dataAprovacaoConex = filter_input(INPUT_POST, 'data_aprovacao_conex');
$responsavelAprovacaoConex = trim((string)filter_input(INPUT_POST, 'responsavel_aprovacao_conex'));
$status = trim((string)filter_input(INPUT_POST, 'status'));
$resolvidoEm = trim((string)filter_input(INPUT_POST, 'resolvido_em'));
$versaoSistema = trim((string)filter_input(INPUT_POST, 'versao_sistema'));

$modulos = $_POST['modulos'] ?? [];
$tipos = $_POST['tipos'] ?? [];
$removerAnexos = $_POST['remover_anexo'] ?? [];

$modulos = array_values(array_filter(array_map('trim', (array)$modulos)));
$tipos = array_values(array_filter(array_map('trim', (array)$tipos)));
$removerAnexos = array_values(array_filter(array_map('intval', (array)$removerAnexos)));

$allowedStatus = ['Aberto', 'Em análise', 'Resolvido', 'Cancelado'];
if ($status === '' && $type === 'create') {
    $status = 'Aberto';
}
if ($status !== '' && !in_array($status, $allowedStatus, true)) {
    $status = 'Aberto';
}

if ($resolvidoEm !== '') {
    $resolvidoEm = str_replace('T', ' ', $resolvidoEm);
}

if ($status === 'Resolvido' && $resolvidoEm === '') {
    $resolvidoEm = date('Y-m-d H:i:s');
}

$fkUsuarioSolicitante = filter_input(INPUT_POST, 'fk_usuario_solicitante', FILTER_VALIDATE_INT);
if (!$fkUsuarioSolicitante) {
    $fkUsuarioSolicitante = (int)($_SESSION['id_usuario'] ?? 0);
}
$resolvidoPor = filter_input(INPUT_POST, 'resolvido_por', FILTER_VALIDATE_INT);
if ($status === 'Resolvido' && !$resolvidoPor) {
    $resolvidoPor = (int)($_SESSION['id_usuario'] ?? 0);
}

$solicitacao = new SolicitacaoCustomizacao();
$solicitacao->id_solicitacao = $idSolicitacao;
$solicitacao->fk_usuario_solicitante = $fkUsuarioSolicitante;
$solicitacao->nome = $nome;
$solicitacao->empresa = $empresa;
$solicitacao->cargo = $cargo;
$solicitacao->email = $email;
$solicitacao->telefone = $telefone;
$solicitacao->data_solicitacao = $dataSolicitacao ?: date('Y-m-d');
$solicitacao->modulo_outro = $moduloOutro;
$solicitacao->descricao = $descricao;
$solicitacao->problema_atual = $problemaAtual;
$solicitacao->resultado_esperado = $resultadoEsperado;
$solicitacao->impacto_nivel = $impactoNivel;
$solicitacao->descricao_impacto = $descricaoImpacto;
$solicitacao->prioridade = $prioridade;
$solicitacao->prazo_desejado = $prazoDesejado;
$solicitacao->responsavel = $responsavel;
$solicitacao->assinatura = $assinatura;
$solicitacao->data_aprovacao = $dataAprovacao;
$solicitacao->prazo_resposta = $prazoResposta;
$solicitacao->precificacao = $precificacao;
$solicitacao->observacoes_resposta = $observacoesResposta;
$solicitacao->aprovacao_resposta = $aprovacaoResposta;
$solicitacao->data_resposta = $dataResposta;
$solicitacao->aprovacao_conex = $aprovacaoConex;
$solicitacao->data_aprovacao_conex = $dataAprovacaoConex;
$solicitacao->responsavel_aprovacao_conex = $responsavelAprovacaoConex;
$solicitacao->status = $status;
$solicitacao->resolvido_em = $resolvidoEm ?: null;
$solicitacao->resolvido_por = $resolvidoPor;
$solicitacao->versao_sistema = $versaoSistema;

$existing = null;
if ($type === 'update' && $idSolicitacao) {
    $existing = $dao->findById($idSolicitacao);
}

if ($type === 'update' && $existing) {
    if ($isConex) {
        $solicitacao->prazo_resposta = $existing->prazo_resposta;
        $solicitacao->precificacao = $existing->precificacao;
        $solicitacao->observacoes_resposta = $existing->observacoes_resposta;
        $solicitacao->aprovacao_resposta = $existing->aprovacao_resposta;
        $solicitacao->data_resposta = $existing->data_resposta;
        $solicitacao->status = $existing->status;
        $solicitacao->resolvido_em = $existing->resolvido_em;
        $solicitacao->resolvido_por = $existing->resolvido_por;
        $solicitacao->versao_sistema = $existing->versao_sistema;
    } else {
        $solicitacao->nome = $existing->nome;
        $solicitacao->empresa = $existing->empresa;
        $solicitacao->cargo = $existing->cargo;
        $solicitacao->email = $existing->email;
        $solicitacao->telefone = $existing->telefone;
        $solicitacao->data_solicitacao = $existing->data_solicitacao;
        $solicitacao->modulo_outro = $existing->modulo_outro;
        $solicitacao->descricao = $existing->descricao;
        $solicitacao->problema_atual = $existing->problema_atual;
        $solicitacao->resultado_esperado = $existing->resultado_esperado;
        $solicitacao->impacto_nivel = $existing->impacto_nivel;
        $solicitacao->descricao_impacto = $existing->descricao_impacto;
        $solicitacao->prioridade = $existing->prioridade;
        $solicitacao->prazo_desejado = $existing->prazo_desejado;
        $solicitacao->responsavel = $existing->responsavel;
        $solicitacao->assinatura = $existing->assinatura;
        $solicitacao->data_aprovacao = $existing->data_aprovacao;
        $solicitacao->aprovacao_conex = $existing->aprovacao_conex;
        $solicitacao->data_aprovacao_conex = $existing->data_aprovacao_conex;
        $solicitacao->responsavel_aprovacao_conex = $existing->responsavel_aprovacao_conex;

        $modulos = $dao->findModulos($idSolicitacao);
        $tipos = $dao->findTipos($idSolicitacao);
        $removerAnexos = [];
    }
}

$anexos = [];
$uploadErrors = [];
$maxSize = 10 * 1024 * 1024; // 10MB
$allowedExt = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
$allowedMime = [
    'image/jpeg',
    'image/png',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

if ($type === 'update' && !$isConex) {
    $removerAnexos = [];
}

if (!empty($_FILES['anexos']) && is_array($_FILES['anexos']['name']) && ($type !== 'update' || $isConex)) {
    $uploadDir = __DIR__ . '/uploads/customizacao';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    foreach ($_FILES['anexos']['name'] as $idx => $name) {
        if ($name === '') {
            continue;
        }

        $tmpName = $_FILES['anexos']['tmp_name'][$idx] ?? '';
        $size = (int)($_FILES['anexos']['size'][$idx] ?? 0);
        $error = (int)($_FILES['anexos']['error'][$idx] ?? 0);
        $type = $_FILES['anexos']['type'][$idx] ?? '';

        if ($error !== UPLOAD_ERR_OK) {
            $uploadErrors[] = "Falha ao enviar o arquivo {$name}.";
            continue;
        }

        if ($size > $maxSize) {
            $uploadErrors[] = "Arquivo {$name} excede o limite de 10MB.";
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $uploadErrors[] = "Formato não permitido: {$name}.";
            continue;
        }

        if ($type && !in_array($type, $allowedMime, true)) {
            $uploadErrors[] = "Tipo inválido para {$name}.";
            continue;
        }

        $newName = sprintf('custom_%s_%s.%s', date('YmdHis'), substr(sha1($name . microtime(true)), 0, 6), $ext);
        $dest = $uploadDir . '/' . $newName;

        if (!move_uploaded_file($tmpName, $dest)) {
            $uploadErrors[] = "Não foi possível salvar {$name}.";
            continue;
        }

        $anexos[] = [
            'caminho_arquivo' => 'uploads/customizacao/' . $newName,
            'nome_original' => $name,
            'mime' => $type,
            'tamanho' => $size,
        ];
    }
}

if ($nome === '') {
    $_SESSION['mensagem'] = 'Informe o nome do solicitante.';
    $_SESSION['mensagem_tipo'] = 'danger';
    header('Location: ' . $BASE_URL . 'SolicitacaoCustomizacao.php');
    exit;
}

if ($type === 'create') {
    $id = $dao->create($solicitacao, $modulos, $tipos, $anexos);
    $after = $dao->findById($id);
    fullcareAuditLog($conn, [
        'action' => 'create',
        'entity_type' => 'solicitacao_customizacao',
        'entity_id' => (int)$id,
        'after' => $after,
        'context' => [
            'modulos' => $dao->findModulos($id),
            'tipos' => $dao->findTipos($id),
            'anexos' => $dao->findAnexos($id),
        ],
        'source' => 'process_solicitacao_customizacao.php',
    ], $BASE_URL);
    $msg = 'Solicitação registrada com sucesso.';
    if ($uploadErrors) {
        $msg .= ' Alguns anexos não foram enviados: ' . implode(' ', $uploadErrors);
    }
    $_SESSION['mensagem'] = $msg;
    $_SESSION['mensagem_tipo'] = 'success';
    header('Location: ' . $BASE_URL . 'SolicitacaoCustomizacao.php');
    exit;
}

if ($type === 'update' && $idSolicitacao) {
    $before = $dao->findById($idSolicitacao);
    $dao->update($solicitacao, $modulos, $tipos, $anexos, $removerAnexos);
    $after = $dao->findById($idSolicitacao);
    fullcareAuditLog($conn, [
        'action' => 'update',
        'entity_type' => 'solicitacao_customizacao',
        'entity_id' => (int)$idSolicitacao,
        'before' => $before,
        'after' => $after,
        'context' => [
            'modulos' => $dao->findModulos($idSolicitacao),
            'tipos' => $dao->findTipos($idSolicitacao),
            'anexos' => $dao->findAnexos($idSolicitacao),
            'remover_anexos' => $removerAnexos,
        ],
        'source' => 'process_solicitacao_customizacao.php',
    ], $BASE_URL);
    $msg = 'Solicitação atualizada com sucesso.';
    if ($uploadErrors) {
        $msg .= ' Alguns anexos não foram enviados: ' . implode(' ', $uploadErrors);
    }
    $_SESSION['mensagem'] = $msg;
    $_SESSION['mensagem_tipo'] = 'success';
    header('Location: ' . $BASE_URL . 'SolicitacaoCustomizacaoEdit.php?id=' . $idSolicitacao);
    exit;
}

$_SESSION['mensagem'] = 'Ação inválida para solicitação.';
$_SESSION['mensagem_tipo'] = 'danger';
header('Location: ' . $BASE_URL . 'SolicitacaoCustomizacao.php');
exit;

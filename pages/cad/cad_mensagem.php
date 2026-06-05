<?php
include_once("check_logado.php");
include_once("globals.php");
include_once("models/mensagem.php");
include_once("dao/mensagemDao.php");
require_once("app/services/AssistenteVirtualService.php");

$mensagemDao = new mensagemDAO($conn, $BASE_URL);
$assistantService = new AssistenteVirtualService($conn, $BASE_URL);

$de_usuario = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : 0;
$para_usuario = isset($_POST['para_usuario']) ? (int) $_POST['para_usuario'] : 0;
$mensagem_content = trim($_POST['mensagem'] ?? '');

if ($de_usuario <= 0 || $para_usuario <= 0 || $mensagem_content === '') {
    http_response_code(400);
    exit('Dados inválidos para envio da mensagem.');
}

$agora = date("Y-m-d H:i:s");

$mensagem = new Mensagem();
$mensagem->de_usuario = $de_usuario;
$mensagem->para_usuario = $para_usuario;
$mensagem->mensagem = $mensagem_content;
$mensagem->data_mensagem = $agora;
$mensagem->vista = 0;

$mensagemDao->create($mensagem, false);

if ($assistantService->isAssistantUser($para_usuario)) {
    $reply = new Mensagem();
    $reply->de_usuario = $assistantService->getAssistantUserId();
    $reply->para_usuario = $de_usuario;
    $reply->mensagem = $assistantService->buildAutomatedReply($mensagem_content);
    $reply->data_mensagem = date("Y-m-d H:i:s");
    $reply->vista = 0;

    $mensagemDao->create($reply, false);
}

<?php
include_once("check_logado.php");
include_once("db.php");
include_once("globals.php");
include_once("models/mensagem.php");
include_once("dao/mensagemDao.php");

$mensagemDao = new mensagemDAO($conn, $BASE_URL);

$de_usuario = (int) ($_SESSION['id_usuario'] ?? 0);
$para_usuario = isset($_GET['para_usuario']) ? (int) $_GET['para_usuario'] : 0;
$ultima_msg = isset($_GET['ultima_msg']) ? (int) $_GET['ultima_msg'] : 0;

if ($de_usuario <= 0 || $para_usuario <= 0) {
  exit;
}

$messages = $mensagemDao->getMensagemsBetweenUsers($de_usuario, $para_usuario, $ultima_msg);

foreach ($messages as $mensagem) {
  $messageClass = ($mensagem->de_usuario == $de_usuario) ? 'sent' : 'received';
  $dataMensagem = date('d/m/Y H:i', strtotime($mensagem->data_mensagem)); // Formata a data/hora da mensagem

  // Substituir o padrão "link_capeante=numero" por um link
  $mensagemComLink = preg_replace_callback('/link_capeante=(\d+)/', function ($matches) {
    $id_capeante = $matches[1]; // Captura o número após o "link_capeante="
    return "<a href='cad_capeante_rah.php?id_capeante={$id_capeante}' target='_blank'>Link Capeante #{$id_capeante}</a>";
  }, $mensagem->mensagem);

  echo "<div class='message $messageClass' data-id='{$mensagem->id_mensagem}'>
            <span class='text'>{$mensagemComLink}</span>
            <span class='message-date'>$dataMensagem</span>
          </div>";
}
?>

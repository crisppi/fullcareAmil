<?php
require_once("globals.php");
require_once("db.php");
require_once("check_logado.php");
require_once("dao/longaPermanenciaDao.php");

$internacaoId = filter_input(INPUT_POST, 'fk_internacao_lp', FILTER_VALIDATE_INT) ?: 0;
if ($internacaoId <= 0) {
    header("Location: " . $BASE_URL . "longa_permanencia_gestao.php");
    exit;
}

$dao = new LongaPermanenciaDAO($conn, $BASE_URL);
$dao->createUpdate([
    'fk_internacao_lp' => $internacaoId,
    'fk_usuario_lp' => (int)($_SESSION['id_usuario'] ?? 0),
    'status_lp' => filter_input(INPUT_POST, 'status_lp'),
    'motivo_principal_lp' => filter_input(INPUT_POST, 'motivo_principal_lp'),
    'barreira_clinica_lp' => filter_input(INPUT_POST, 'barreira_clinica_lp'),
    'barreira_administrativa_lp' => filter_input(INPUT_POST, 'barreira_administrativa_lp'),
    'plano_acao_lp' => filter_input(INPUT_POST, 'plano_acao_lp'),
    'responsavel_lp' => filter_input(INPUT_POST, 'responsavel_lp'),
    'prazo_acao_lp' => filter_input(INPUT_POST, 'prazo_acao_lp'),
    'previsao_alta_lp' => filter_input(INPUT_POST, 'previsao_alta_lp'),
    'proxima_revisao_lp' => filter_input(INPUT_POST, 'proxima_revisao_lp'),
    'potencial_desospitalizacao_lp' => filter_input(INPUT_POST, 'potencial_desospitalizacao_lp') === 's' ? 's' : 'n',
    'necessita_escalonamento_lp' => filter_input(INPUT_POST, 'necessita_escalonamento_lp') === 's' ? 's' : 'n',
    'risco_sinistro_lp' => filter_input(INPUT_POST, 'risco_sinistro_lp'),
    'observacoes_lp' => filter_input(INPUT_POST, 'observacoes_lp'),
]);

header("Location: " . $BASE_URL . "longa_permanencia_editar.php?id_internacao=" . $internacaoId . "&success=1");
exit;

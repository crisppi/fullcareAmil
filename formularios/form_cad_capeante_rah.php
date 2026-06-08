<?php

/** =========================================================================
 *  formularios/form_capeante_auditRah.php
 *  Formulário “Capeante RAH” — página única, layout TUSS
 *  - Empilhado por setores (Apto/Enfermaria, UTI, Centro Cirúrgico)
 *  - Cada linha: Descrição | Qtd | Cobrado | Glosado | Cobrado Após (calc) | Observação
 *  - Usa selectAllInternacaoCap2() para carregar identificação
 *  ====================================================================== */

if (isset($conn) && $conn instanceof PDO) {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* Dependências principais do projeto */
require_once "models/usuario.php";
require_once "dao/usuarioDao.php";

require_once "dao/internacaoDao.php";
require_once "dao/pacienteDao.php";
require_once "dao/capeanteDao.php";
require_once "dao/patologiaDao.php";
require_once "dao/gestaoDao.php";

// === CAD CENTRAL: DAOs e listas ===
if (!isset($usuarioDao) || !($usuarioDao instanceof UserDAO)) {
    $usuarioDao = new UserDAO($conn, $BASE_URL);
}
$usuariosAtivos = $usuarioDao->findMedicosEnfermeiros(); // médicos e enfermeiros
$usuariosAdm    = $usuarioDao->findAdministrativos();    // administrativos

// Se o projeto às vezes usa usuarioDAO (minúsculo), mantém alias:
if (!class_exists('userDAO') && class_exists('usuarioDAO')) {
    class_alias('usuarioDAO', 'userDAO');
}

/* Helpers */
$h = function ($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
};
$hi = function ($v) {
    return (int)($v ?? 0);
};
$fmtDateBR = function ($d): string {
    if (!is_string($d) || $d === '' || $d === '0000-00-00') return '';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : '';
};
$fmtSN = function ($value): string {
    $val = strtolower((string)($value ?? ''));
    if ($val === 's') {
        return 'Sim';
    }
    if ($val === 'n') {
        return 'Não';
    }
    return $val !== '' ? ucfirst($val) : '—';
};

/* Instâncias */
$internacaoDAO = new internacaoDAO($conn, $BASE_URL);
$capeanteDAO   = new capeanteDAO($conn, $BASE_URL);
$gestaoDAO     = new gestaoDAO($conn, $BASE_URL);

/* Parâmetros */
$id_capeante   = filter_input(INPUT_GET, 'id_capeante', FILTER_VALIDATE_INT) ?: null;
$id_internacao = filter_input(INPUT_GET, 'id_internacao', FILTER_VALIDATE_INT) ?: null;
$type          = (string)(filter_input(INPUT_GET, 'type') ?? 'update');

/* Recupera 1 linha principal */
$defaults = [
    'id_capeante' => null,
    'fk_int_capeante' => null,
    'id_internacao' => null,
    'nome_pac' => null,
    'nome_hosp' => null,
    'data_intern_int' => null,
    'pacote' => 'n',
    'parcial_capeante' => 'n',
    'parcial_num' => null,
    'data_inicial_capeante' => null,
    'data_final_capeante' => null,
    'valor_apresentado_capeante' => null,
    'valor_final_capeante' => null,
    'glosa_total' => null,
    'valor_diarias' => null,
    'glosa_diaria' => null,
    'valor_taxa' => null,
    'valor_materiais' => null,
    'valor_medicamentos' => null,
    'valor_sadt' => null,
    'valor_honorarios' => null,
    'valor_opme' => null,
    'desconto_valor_cap' => null,
    'comentarios_obs' => null,
    'conta_parada_cap' => 'n',
    'parada_motivo_cap' => null
];

$where = '';
$order = 'ac.data_intern_int DESC, ac.id_internacao DESC';
if ($type === 'create' && $id_internacao)      $where = 'ac.id_internacao = ' . (int)$id_internacao;
elseif ($id_capeante)                           $where = 'ca.id_capeante = ' . (int)$id_capeante;

$row = $defaults;
if ($where) {
    $lista = $internacaoDAO->selectAllInternacaoCap2($where, $order, null);
    if (is_array($lista) && isset($lista[0]) && is_array($lista[0])) {
        $row = array_merge($defaults, $lista[0]);
    }
}
$prevParcialRow = null;
$prevParcialInfo = null;
$parciaisLista = [];
if ($id_internacao) {
    $sqlPrev = "SELECT id_capeante, parcial_num, data_inicial_capeante, data_final_capeante,
                        valor_apresentado_capeante, valor_final_capeante
                 FROM tb_capeante
                 WHERE fk_int_capeante = :fk" . ($id_capeante ? " AND id_capeante <> :atual" : '') . "
                 ORDER BY COALESCE(data_final_capeante, data_inicial_capeante) DESC, id_capeante DESC
                 LIMIT 1";
    $stmtPrev = $conn->prepare($sqlPrev);
    $stmtPrev->bindValue(':fk', (int)$id_internacao, PDO::PARAM_INT);
    if ($id_capeante) {
        $stmtPrev->bindValue(':atual', (int)$id_capeante, PDO::PARAM_INT);
    }
    if ($stmtPrev->execute()) {
        $prevParcialRow = $stmtPrev->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($prevParcialRow) {
        $prevParcialInfo = [
            'nome'       => $row['nome_pac'] ?? '',
            'senha'      => $row['senha_int'] ?? '',
            'matricula'  => $row['matricula_pac'] ?? '',
            'hospital'   => $row['nome_hosp'] ?? '',
            'numero'     => $prevParcialRow['parcial_num'] ?? null,
            'id_capeante'=> $prevParcialRow['id_capeante'] ?? null,
            'data_ini'   => $prevParcialRow['data_inicial_capeante'] ?? '',
            'data_fim'   => $prevParcialRow['data_final_capeante'] ?? '',
            'valor_apr'  => $prevParcialRow['valor_apresentado_capeante'] ?? null,
            'valor_fin'  => $prevParcialRow['valor_final_capeante'] ?? null
        ];
        }
    }
    try {
        $sqlLista = "SELECT 
                id_capeante,
                parcial_num,
                data_inicial_capeante,
                data_final_capeante,
                data_fech_capeante,
                data_digit_capeante,
                valor_apresentado_capeante,
                valor_final_capeante
            FROM tb_capeante
            WHERE fk_int_capeante = :fk_lista
            ORDER BY COALESCE(data_final_capeante, data_inicial_capeante, data_fech_capeante, data_digit_capeante, id_capeante) DESC";
        $stmtLista = $conn->prepare($sqlLista);
        $stmtLista->bindValue(':fk_lista', (int)$id_internacao, PDO::PARAM_INT);
        $stmtLista->execute();
        $parciaisLista = $stmtLista->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$prevParcialInfo && $parciaisLista) {
            $primeira = $parciaisLista[0];
            $prevParcialInfo = [
                'nome'       => $row['nome_pac'] ?? '',
                'senha'      => $row['senha_int'] ?? '',
                'matricula'  => $row['matricula_pac'] ?? '',
                'hospital'   => $row['nome_hosp'] ?? '',
                'numero'     => $primeira['parcial_num'] ?? null,
                'id_capeante'=> $primeira['id_capeante'] ?? null,
                'data_ini'   => $primeira['data_inicial_capeante'] ?? '',
                'data_fim'   => $primeira['data_final_capeante'] ?? '',
                'valor_apr'  => $primeira['valor_apresentado_capeante'] ?? null,
                'valor_fin'  => $primeira['valor_final_capeante'] ?? null
            ];
        }
    } catch (Throwable $e) {
        $parciaisLista = [];
    }
}
$eventoAdversoInfo = null;
$eventoEditLink = null;
$internacaoParaEvento = (int)($row['id_internacao'] ?? $row['fk_int_capeante'] ?? 0);
if (!$internacaoParaEvento && $id_internacao) {
    $internacaoParaEvento = (int)$id_internacao;
}
if ($internacaoParaEvento > 0) {
    try {
        $gestoesBrutas = $gestaoDAO->selectRawByInternacao($internacaoParaEvento);
        if (is_array($gestoesBrutas) && $gestoesBrutas) {
            foreach (array_reverse($gestoesBrutas) as $registroGestao) {
                if (strtolower((string)($registroGestao['evento_adverso_ges'] ?? 'n')) === 's') {
                    $eventoAdversoInfo = [
                        'tipo' => $registroGestao['tipo_evento_adverso_gest'] ?? '',
                        'relatorio' => trim((string)($registroGestao['rel_evento_adverso_ges'] ?? '')),
                        'data' => $registroGestao['evento_data_ges'] ?? '',
                        'classificacao' => $registroGestao['evento_classificacao_ges'] ?? '',
                        'impacto' => $registroGestao['evento_impacto_financ_ges'] ?? '',
                        'prolongou' => $registroGestao['evento_prolongou_internacao_ges'] ?? '',
                        'retorno' => $registroGestao['evento_retorno_qual_hosp_ges'] ?? '',
                        'sinalizado' => $registroGestao['evento_sinalizado_ges'] ?? '',
                        'discutido' => $registroGestao['evento_discutido_ges'] ?? '',
                        'negociado' => $registroGestao['evento_negociado_ges'] ?? '',
                        'valor_negociado' => $registroGestao['evento_valor_negoc_ges'] ?? '',
                        'encerrado' => strtolower((string)($registroGestao['evento_encerrar_ges'] ?? 'n')) === 's'
                    ];
                    $eventoEditLink = rtrim($BASE_URL, '/') . '/internacoes/editar/' . $internacaoParaEvento . '#div_evento';
                    break;
                }
            }
        }
    } catch (Exception $ex) {
        $eventoAdversoInfo = null;
    }
}

if (!empty($rahFormFieldOverrides) && is_array($rahFormFieldOverrides)) {
    $row = array_merge($row, $rahFormFieldOverrides);
}

$nextAutoDate = '';
if ($type === 'create' && !empty($prevParcialRow['data_final_capeante']) && $prevParcialRow['data_final_capeante'] !== '0000-00-00') {
    $ts = strtotime($prevParcialRow['data_final_capeante'] . ' +1 day');
    if ($ts) {
        $nextAutoDate = date('Y-m-d', $ts);
        if (empty($row['data_inicial_capeante'])) {
            $row['data_inicial_capeante'] = $nextAutoDate;
        }
        if (empty($row['data_final_capeante'])) {
            $row['data_final_capeante'] = $nextAutoDate;
        }
    }
}

$novaParcial = filter_input(INPUT_GET, 'nova_parcial') ? true : false;
if ($type === 'create' && $novaParcial && $id_internacao) {
    $row['parcial_capeante'] = 's';
    if (empty($row['parcial_num'])) {
        try {
            $count = $capeanteDAO->getCapeantesCountByInternacao((int)$id_internacao);
            $row['parcial_num'] = $count + 1;
        } catch (Throwable $e) {
            $row['parcial_num'] = null;
        }
    }
}
$fv = function (string $k) use ($row) {
    return $row[$k] ?? null;
};
$hojeYMD = date('Y-m-d');
$motivosParadaPadrao = [
    'OPME pendente',
    'Sem autorização',
    'Fora do prazo',
    'Senha cancelada',
    'Documentação pendente',
    'Outros'
];

// === CAD CENTRAL: helpers de cargo/visibilidade ===
$cargoSessao = (string)($_SESSION['cargo'] ?? '');

$isMed = function ($cargo) {
    $c = mb_strtolower((string)$cargo, 'UTF-8');
    return in_array($c, ['med_auditor', 'medico_auditor'], true) || str_contains($c, 'med');
};
$isEnf = function ($cargo) {
    $c = mb_strtolower((string)$cargo, 'UTF-8');
    return in_array($c, ['enf_auditor', 'enfer_auditor'], true) || str_contains($c, 'enf');
};
$isAdm = function ($cargo) {
    $c = mb_strtolower((string)$cargo, 'UTF-8');
    return in_array($c, ['adm', 'administrador', 'administrativo'], true);
};

// Quem pode ver o bloco? (oculta para Méd/Enf)
$mostrarCadastroCentral = !($isMed($cargoSessao) || $isEnf($cargoSessao));

// Estado padrão do seletor "Ativar"
function isProfAssistencial(string $cargo): bool
{
    $norm = mb_strtolower(trim($cargo), 'UTF-8');
    $norm = preg_replace('/[\s\-]+/', '_', $norm);
    if (in_array($norm, ['med_auditor', 'enf_auditor', 'adm'], true)) return true;
    return (bool)preg_match('/^(med|enf)_?auditor$|^adm$/i', $norm);
}
$cadastroCentralDefault = isProfAssistencial($cargoSessao) ? 'n' : 's';

// Valores previamente salvos (se edição)
$medSelecionado = (int)($fv('fk_id_aud_med') ?? 0);
$enfSelecionado = (int)($fv('fk_id_aud_enf') ?? 0);
$admSelecionado = (int)($fv('fk_id_aud_adm') ?? 0);
?>

<link rel="stylesheet" href="<?= $h($BASE_URL) ?>css/rah.css?v=<?= @filemtime(__DIR__ . '/../css/rah.css') ?: time() ?>">

<!-- ========================= FORM ========================= -->
<form id="form-capeante-rah" action="<?= $h($BASE_URL) ?>process_rah.php" method="POST" enctype="multipart/form-data">
    <?php if ($novaParcial): ?>
    <input type="hidden" name="nova_parcial" value="1">
    <?php endif; ?>
    <input type="hidden" name="type" value="<?= $h($type) ?>">
    <input type="hidden" name="id_capeante" value="<?= $type === 'create' ? '' : $hi($fv('id_capeante')) ?>">
    <input type="hidden" name="fk_int_capeante" value="<?= $hi($fv('id_internacao') ?: $fv('fk_int_capeante')) ?>">
    <input type="hidden" id="fk_id_aud_med" name="fk_id_aud_med" value="<?= (int)($fv('fk_id_aud_med') ?? 0) ?>">
    <input type="hidden" id="fk_id_aud_enf" name="fk_id_aud_enf" value="<?= (int)($fv('fk_id_aud_enf') ?? 0) ?>">
    <input type="hidden" id="fk_id_aud_adm" name="fk_id_aud_adm" value="<?= (int)($fv('fk_id_aud_adm') ?? 0) ?>">
    <input type="hidden" name="aud_med_capeante" value="<?= $h($fv('aud_med_capeante') ?? 'n') ?>">
    <input type="hidden" name="aud_enf_capeante" value="<?= $h($fv('aud_enf_capeante') ?? 'n') ?>">
    <input type="hidden" name="aud_adm_capeante" value="<?= $h($fv('aud_adm_capeante') ?? 'n') ?>">
    <input type="hidden" name="med_check" value="<?= $h($fv('med_check') ?? 'n') ?>">
    <input type="hidden" name="enfer_check" value="<?= $h($fv('enfer_check') ?? 'n') ?>">
    <input type="hidden" name="adm_check" value="<?= $h($fv('adm_check') ?? 'n') ?>">
    <input type="hidden" id="timer_cap" name="timer_cap" value="">


    <!-- IDENTIFICAÇÃO -->
    <div class="id-card">
        <div class="id-header">
            <!-- Avatar com inicial do paciente -->
            <div class="id-avatar">
                <?= strtoupper(mb_substr($h($fv('nome_pac') ?: 'P'), 0, 1, 'UTF-8')) ?>
            </div>

            <!-- Título + chips -->
            <div class="id-title">
                <div class="id-name"><?= $h($fv('nome_pac')) ?></div>
                <div class="id-sub">
                    <span class="id-chip">
                        <i class="bi bi-hospital" style="margin-right:6px;"></i><?= $h($fv('nome_hosp')) ?>
                    </span>
                    <span class="id-sep">•</span>
                    <span class="id-chip">
                        <i class="bi bi-calendar-event" style="margin-right:6px;"></i>Data
                        Internação: <?= $fmtDateBR($fv('data_intern_int')) ?>
                    </span>
                    <span class="id-chip">
                        <i class="bi bi-card-list" style="margin-right:6px;"></i>Senha: <?= ($fv('senha_int')) ?>
                    </span>
                </div>
            </div>

            <!-- Infos à direita -->
            <div class="id-right">
                <div class="id-pill">ID Capeante: <?= $hi($fv('id_capeante')) ?></div>
                <div class="id-pill">ID Internação: <?= $hi($fv('id_internacao')) ?></div>
            </div>
        </div>
    </div>

    <?php if ($eventoAdversoInfo):
        $eventoEncerrado = (bool)($eventoAdversoInfo['encerrado'] ?? false);
        $eventoStatusLabel = $eventoEncerrado ? 'Encerrado' : 'Aberto';
        $eventoDescricao = $eventoEncerrado
            ? 'Este evento foi encerrado pela gestão e permanece disponível para rastreabilidade.'
            : 'Evento adverso ativo informado na visita. Revise os impactos antes de finalizar esta conta.';
    ?>
    <?php
        $eventoCardStyle = $eventoEncerrado ? '' : 'background:#fff;border:2px solid #ffb3cc;';
        $eventoHeaderStyle = $eventoEncerrado ? '' : 'color:#5e2363;border-bottom:1px solid rgba(255,154,174,.3);background:#fff;';
        $eventoStatusStyle = $eventoEncerrado ? '' : 'background:#ffedf2;border-color:#f7b9c6;color:#8a1433;';
        $eventoBodyStyle = $eventoEncerrado ? '' : 'border:none;background:#fff;';
    ?>
    <div class="sec-card rah-event-card <?= $eventoEncerrado ? 'is-closed' : 'is-open' ?>" style="<?= $eventoCardStyle ?>">
        <div class="sec-header" style="<?= $eventoHeaderStyle ?>">
            <div class="sec-title">Evento adverso</div>
            <div class="rah-event-actions">
                <div class="rah-event-status" style="<?= $eventoStatusStyle ?>"><?= $eventoStatusLabel ?></div>
                <?php if ($eventoEditLink): ?>
                <a class="rah-event-btn" href="<?= $h($eventoEditLink) ?>" target="_blank" rel="noopener">
                    Editar evento
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="sec-body" style="<?= $eventoBodyStyle ?>">
            <p class="rah-event-desc"><?= $eventoDescricao ?></p>
            <div class="rah-event-grid">
                <div>
                    <span>Tipo</span>
                    <strong><?= $eventoAdversoInfo['tipo'] ? $h($eventoAdversoInfo['tipo']) : '—' ?></strong>
                </div>
                <div>
                    <span>Data do evento</span>
                    <strong><?= $fmtDateBR($eventoAdversoInfo['data']) ?: '—' ?></strong>
                </div>
                <div>
                    <span>Classificação</span>
                    <strong><?= $eventoAdversoInfo['classificacao'] ? $h($eventoAdversoInfo['classificacao']) : '—' ?></strong>
                </div>
                <div>
                    <span>Impacto financeiro</span>
                    <strong><?= $fmtSN($eventoAdversoInfo['impacto'] ?? null) ?></strong>
                </div>
                <div>
                    <span>Prolongou internação</span>
                    <strong><?= $fmtSN($eventoAdversoInfo['prolongou'] ?? null) ?></strong>
                </div>
                <div>
                    <span>Retorno qualidade hospital</span>
                    <strong><?= $fmtSN($eventoAdversoInfo['retorno'] ?? null) ?></strong>
                </div>
                <?php if (!empty(trim((string)($eventoAdversoInfo['valor_negociado'] ?? '')))): ?>
                <div>
                    <span>Valor negociado</span>
                    <strong><?= $h($eventoAdversoInfo['valor_negociado']) ?></strong>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($eventoAdversoInfo['relatorio'])): ?>
            <div class="rah-event-notes">
                <span>Resumo / relatório</span>
                <p><?= nl2br($h($eventoAdversoInfo['relatorio'])) ?></p>
            </div>
            <?php endif; ?>
            <div class="rah-event-flags">
                <?php
                $statusFlags = [
                    'Sinalizado' => $eventoAdversoInfo['sinalizado'] ?? '',
                    'Discutido' => $eventoAdversoInfo['discutido'] ?? '',
                    'Negociado' => $eventoAdversoInfo['negociado'] ?? ''
                ];
                foreach ($statusFlags as $flagLabel => $flagValue):
                    $flagValueLower = strtolower((string)$flagValue);
                    $flagClass = $flagValueLower === 's' ? 'yes' : ($flagValueLower === 'n' ? 'no' : 'neutral');
                ?>
                <span class="rah-event-flag <?= $flagClass ?>">
                    <?= $flagLabel ?>: <strong><?= $fmtSN($flagValue) ?></strong>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- PERÍODO E VALORES GERAIS -->
    <div class="sec-card">
        <div class="sec-header">
            <div class="sec-title">Período e Totais</div>
        </div>

        <div class="sec-body">
            <div class="row g-3">
                <div class="col-lg-2 col-md-3">
                    <label class="form-label">Data Inicial</label>
                    <input type="date" class="form-control" name="data_inicial_capeante"
                        value="<?= $h($fv('data_inicial_capeante') ?: $fv('data_intern_int')) ?>">
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label">Data Final</label>
                    <input type="date" class="form-control" name="data_final_capeante"
                        value="<?= $h($fv('data_final_capeante')) ?>">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label">Valor Apresentado</label>
                    <input type="text" class="form-control dinheiro" id="inp_val_apr" name="valor_apresentado_capeante"
                        value="<?= is_numeric($fv('valor_apresentado_capeante')) ? number_format((float)$fv('valor_apresentado_capeante'), 2, ',', '.') : '' ?>"
                        placeholder="R$ 0,00">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label">Glosa Total</label>
                    <input type="text" class="form-control dinheiro" id="inp_val_glosa" name="valor_glosa_total"
                        value="<?= is_numeric($fv('valor_glosa_total')) ? number_format((float)$fv('valor_glosa_total'), 2, ',', '.') : '' ?>"
                        placeholder="R$ 0,00" readonly>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label">Valor Liberado</label>
                    <input type="text" class="form-control dinheiro" id="inp_val_fin" name="valor_final_capeante"
                        value="<?= is_numeric($fv('valor_final_capeante')) ? number_format((float)$fv('valor_final_capeante'), 2, ',', '.') : '' ?>"
                        placeholder="R$ 0,00">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label text-danger fw-semibold">Desconto (R$)</label>
                    <input type="text" class="form-control dinheiro" id="desconto_valor_cap"
                        name="desconto_valor_cap"
                        value="<?= is_numeric($fv('desconto_valor_cap')) ? number_format((float)$fv('desconto_valor_cap'), 2, ',', '.') : '' ?>"
                        placeholder="R$ 0,00">
                    <small class="text-muted">Valor abatido diretamente no total final.</small>
                </div>
            </div>

            <div class="form-line-grid">
                <div class="form-group">
                    <label class="form-label">Data Fechamento</label>
                    <input type="date" class="form-control" name="data_fech_capeante" value="<?= $h($hojeYMD) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Data Digitação</label>
                    <input type="date" class="form-control" name="data_digit_capeante" value="<?= $h($hojeYMD) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Pacote</label>
                    <?php $pacoteVal = ($fv('pacote') ?? 'n'); ?>
                    <select name="pacote" class="form-select">
                        <option value="n" <?= $pacoteVal === 'n' ? 'selected' : ''; ?>>Não</option>
                        <option value="s" <?= $pacoteVal === 's' ? 'selected' : ''; ?>>Sim</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Parcial</label>
                    <?php $parcialVal = ($fv('parcial_capeante') ?? 'n'); ?>
                    <select name="parcial_capeante" class="form-select" id="parcial_capeante">
                        <option value="n" <?= $parcialVal === 'n' ? 'selected' : ''; ?>>Não</option>
                        <option value="s" <?= $parcialVal === 's' ? 'selected' : ''; ?>>Sim</option>
                    </select>
                </div>

                <div class="form-group fg-parcial-num" id="wrap_parcial_num"
                    style="<?= $parcialVal === 's' ? '' : 'display:none' ?>">
                    <label class="form-label">Número da Parcial</label>
                    <input type="number" class="form-control" name="parcial_num" value="<?= $h($fv('parcial_num')) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Encerrado</label>
                    <?php $encerradoVal = ($fv('encerrado_cap') ?? 's'); ?>
                    <select name="encerrado_cap" class="form-select" id="encerrado_cap">
                        <option value="s" <?= $encerradoVal === 's' ? 'selected' : ''; ?>>Sim</option>
                        <option value="n" <?= $encerradoVal === 'n' ? 'selected' : ''; ?>>Não</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Senha finalizada</label>
                    <?php $senhaFinalVal = ($fv('senha_finalizada') ?? 'n'); ?>
                    <select name="senha_finalizada" class="form-select" id="senha_finalizada">
                        <option value="n" <?= $senhaFinalVal === 'n' ? 'selected' : ''; ?>>Não</option>
                        <option value="s" <?= $senhaFinalVal === 's' ? 'selected' : ''; ?>>Sim</option>
                    </select>
                </div>

                <?php $contaParadaVal = strtolower($fv('conta_parada_cap') ?? 'n') === 's' ? 's' : 'n'; ?>
                <div class="form-group">
                    <label class="form-label">Conta parada</label>
                    <select name="conta_parada_cap" class="form-select" id="conta_parada_cap">
                        <option value="n" <?= $contaParadaVal === 'n' ? 'selected' : ''; ?>>Não</option>
                        <option value="s" <?= $contaParadaVal === 's' ? 'selected' : ''; ?>>Sim</option>
                    </select>
                </div>
                <div class="form-group" id="parada-motivo-wrapper"
                    style="<?= $contaParadaVal === 's' ? '' : 'display:none'; ?>">
                    <label class="form-label">Motivo da parada</label>
                    <select name="parada_motivo_cap" class="form-select" id="parada_motivo_cap">
                        <option value="">Selecione...</option>
                        <?php foreach ($motivosParadaPadrao as $motivo): ?>
                            <option value="<?= $h($motivo) ?>" <?= ($fv('parada_motivo_cap') === $motivo ? 'selected' : '') ?>>
                                <?= $h($motivo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <?php if ($mostrarCadastroCentral): ?>
    <div class="sec-card">
        <div class="sec-header">
            <div class="sec-title">Cadastro Equipe</div>
            <div class="sec-right">
                <div class="pill"><span>Status:</span> <strong id="cc-pill">—</strong></div>
            </div>
        </div>
        <div class="sec-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-lg-2">
                    <label for="cadastro_central_cap" class="form-label">Ativar</label>
                    <select class="form-select form-select-sm" id="cadastro_central_cap" name="cadastro_central_cap">
                        <option value="n" <?= $cadastroCentralDefault === 'n' ? 'selected' : '' ?>>Não</option>
                        <option value="s" <?= $cadastroCentralDefault === 's' ? 'selected' : '' ?>>Sim</option>
                    </select>
                </div>

                <div class="col-12 col-lg-3">
                    <label class="form-label" for="cad_central_med_id">Médico(a)</label>
                    <select class="form-select form-select-sm" id="cad_central_med_id" name="fk_id_aud_med">
                        <option value="">Selecione</option>
                        <?php foreach ($usuariosAtivos as $u): if ($isMed($u['cargo_user'] ?? '')):
                                    $id = (int)($u['id_usuario'] ?? 0);
                                    $nome = (string)($u['usuario_user'] ?? '');
                                    $sel = ($id === $medSelecionado) ? 'selected' : ''; ?>
                        <option value="<?= $id ?>" <?= $sel ?>><?= $h($nome) ?></option>
                        <?php endif;
                            endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-lg-3">
                    <label class="form-label" for="cad_central_enf_id">Enfermeiro(a)</label>
                    <select class="form-select form-select-sm" id="cad_central_enf_id" name="fk_id_aud_enf">
                        <option value="">Selecione</option>
                        <?php foreach ($usuariosAtivos as $u): if ($isEnf($u['cargo_user'] ?? '')):
                                    $id = (int)($u['id_usuario'] ?? 0);
                                    $nome = (string)($u['usuario_user'] ?? '');
                                    $sel = ($id === $enfSelecionado) ? 'selected' : ''; ?>
                        <option value="<?= $id ?>" <?= $sel ?>><?= $h($nome) ?></option>
                        <?php endif;
                            endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-lg-3">
                    <label class="form-label" for="cad_central_adm_id">Administrativo(a)</label>
                    <select class="form-select form-select-sm" id="cad_central_adm_id" name="fk_id_aud_adm">
                        <option value="">Selecione</option>
                        <?php foreach ($usuariosAdm as $u):
                                $id = (int)($u['id_usuario'] ?? 0);
                                $nome = (string)($u['usuario_user'] ?? '');
                                $sel = ($id === $admSelecionado) ? 'selected' : ''; ?>
                        <option value="<?= $id ?>" <?= $sel ?>><?= $h($nome) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- DIÁRIAS (formato PDF) -->
    <div class="block" data-group="diarias">
        <!-- TÍTULO / TOGGLER -->
        <h5>
            <button class="block-toggle collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#grp-diarias" aria-expanded="false" aria-controls="grp-diarias">
                Diárias
            </button>
        </h5>

        <!-- CONTEÚDO COLAPSÁVEL (tuss-grid + totais) -->
        <div id="grp-diarias" class="collapse">
            <div class="tuss-grid mt-3">
                <div class="tg-head tg-col-desc">Diária</div>
                <div class="tg-head tg-col-qtd">Qtd.</div>
                <div class="tg-head tg-col-cob">Cobrado</div>
                <div class="tg-head tg-col-glo">Glosado</div>
                <div class="tg-head tg-col-lib">Cobrado Após</div>
                <div class="tg-head tg-col-obs">Observação</div>

                <!-- QUARTO / APTO -->
                <div class="tuss-row rah-row">
                    <div class="tg-lab tg-col-desc">Quarto / Apto</div>
                    <input name="ac_quarto_qtd" class="form-control tg-col-qtd" placeholder="Qtd.">
                    <input name="ac_quarto_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                        placeholder="R$ 0,00">
                    <input name="ac_quarto_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                        placeholder="R$ 0,00">
                    <input name="ac_quarto_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                        placeholder="R$ 0,00" readonly>
                    <input name="ac_quarto_obs" class="form-control tg-col-obs" placeholder="Observação">
                </div>

                <!-- DAY CLINIC -->
                <div class="tuss-row rah-row">
                    <div class="tg-lab tg-col-desc">Day Clinic</div>
                    <input name="ac_dayclinic_qtd" class="form-control tg-col-qtd" placeholder="Qtd.">
                    <input name="ac_dayclinic_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                        placeholder="R$ 0,00">
                    <input name="ac_dayclinic_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                        placeholder="R$ 0,00">
                    <input name="ac_dayclinic_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                        placeholder="R$ 0,00" readonly>
                    <input name="ac_dayclinic_obs" class="form-control tg-col-obs" placeholder="Observação">
                </div>

                <!-- UTI -->
                <div class="tuss-row rah-row">
                    <div class="tg-lab tg-col-desc">UTI</div>
                    <input name="ac_uti_qtd" class="form-control tg-col-qtd" placeholder="Qtd.">
                    <input name="ac_uti_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                        placeholder="R$ 0,00">
                    <input name="ac_uti_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                        placeholder="R$ 0,00">
                    <input name="ac_uti_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                        placeholder="R$ 0,00" readonly>
                    <input name="ac_uti_obs" class="form-control tg-col-obs" placeholder="Observação">
                </div>

                <!-- UTI / SEMI -->
                <div class="tuss-row rah-row">
                    <div class="tg-lab tg-col-desc">UTI / Semi</div>
                    <input name="ac_utisemi_qtd" class="form-control tg-col-qtd" placeholder="Qtd.">
                    <input name="ac_utisemi_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                        placeholder="R$ 0,00">
                    <input name="ac_utisemi_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                        placeholder="R$ 0,00">
                    <input name="ac_utisemi_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                        placeholder="R$ 0,00" readonly>
                    <input name="ac_utisemi_obs" class="form-control tg-col-obs" placeholder="Observação">
                </div>

                <!-- ENFERMARIA -->
                <div class="tuss-row rah-row">
                    <div class="tg-lab tg-col-desc">Enfermaria</div>
                    <input name="ac_enfermaria_qtd" class="form-control tg-col-qtd" placeholder="Qtd.">
                    <input name="ac_enfermaria_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                        placeholder="R$ 0,00">
                    <input name="ac_enfermaria_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                        placeholder="R$ 0,00">
                    <input name="ac_enfermaria_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                        placeholder="R$ 0,00" readonly>
                    <input name="ac_enfermaria_obs" class="form-control tg-col-obs" placeholder="Observação">
                </div>

                <!-- BERÇÁRIO -->
                <div class="tuss-row rah-row">
                    <div class="tg-lab tg-col-desc">Berçário</div>
                    <input name="ac_bercario_qtd" class="form-control tg-col-qtd" placeholder="Qtd.">
                    <input name="ac_bercario_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                        placeholder="R$ 0,00">
                    <input name="ac_bercario_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                        placeholder="R$ 0,00">
                    <input name="ac_bercario_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                        placeholder="R$ 0,00" readonly>
                    <input name="ac_bercario_obs" class="form-control tg-col-obs" placeholder="Observação">
                </div>

                <!-- ACOMPANHANTE -->
                <div class="tuss-row rah-row">
                    <div class="tg-lab tg-col-desc">Acompanhante</div>
                    <input name="ac_acompanhante_qtd" class="form-control tg-col-qtd" placeholder="Qtd.">
                    <input name="ac_acompanhante_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                        placeholder="R$ 0,00">
                    <input name="ac_acompanhante_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                        placeholder="R$ 0,00">
                    <input name="ac_acompanhante_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                        placeholder="R$ 0,00" readonly>
                    <input name="ac_acompanhante_obs" class="form-control tg-col-obs" placeholder="Observação">
                </div>

                <!-- ISOLAMENTO -->
                <div class="tuss-row rah-row">
                    <div class="tg-lab tg-col-desc">Isolamento</div>
                    <input name="ac_isolamento_qtd" class="form-control tg-col-qtd" placeholder="Qtd.">
                    <input name="ac_isolamento_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                        placeholder="R$ 0,00">
                    <input name="ac_isolamento_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                        placeholder="R$ 0,00">
                    <input name="ac_isolamento_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                        placeholder="R$ 0,00" readonly>
                    <input name="ac_isolamento_obs" class="form-control tg-col-obs" placeholder="Observação">
                </div>
            </div>

            <!-- CONSOLIDADO LOCAL (Diárias) -->
            <div class="row g-2 mt-2 grp-totais">
                <div class="col-md-3">
                    <label class="form-label">Total Cobrado (Diárias)</label>
                    <input type="text" name="diarias_total_cobrado" class="form-control dinheiro grp-total-cobrado"
                        readonly value="R$ 0,00">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Total Glosado (Diárias)</label>
                    <input type="text" name="diarias_total_glosado" class="form-control dinheiro grp-total-glosado"
                        readonly value="R$ 0,00">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Total Cobrado Após (Diárias)</label>
                    <input type="text" name="diarias_total_liberado" class="form-control dinheiro grp-total-liberado"
                        readonly value="R$ 0,00">
                </div>
            </div>
        </div>
    </div>




    <!-- SETOR: APTO / ENFERMARIA -->
    <div class="block apto" data-group="apto">
        <h5>
            <button class="block-toggle collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#grp-apto" aria-expanded="false" aria-controls="grp-apto">
                Apto / Enfermaria
            </button>
        </h5>

        <div id="grp-apto" class="collapse">
            <div class="tuss-grid">
            <div class="tg-head tg-col-desc">Descrição</div>
            <div class="tg-head tg-col-qtd">Qtd.</div>
            <div class="tg-head tg-col-cob">Cobrado</div>
            <div class="tg-head tg-col-glo">Glosado</div>
            <div class="tg-head tg-col-lib">Cobrado Após</div>
            <div class="tg-head tg-col-obs">Observação</div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Terapias</div>
                <input name="ap_terapias_qtd" class="form-control tg-col-qtd" placeholder="Qtd">
                <input name="ap_terapias_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="ap_terapias_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="ap_terapias_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="ap_terapias_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Taxas / Aluguéis</div>
                <input name="ap_taxas_qtd" class="form-control tg-col-qtd">
                <input name="ap_taxas_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="ap_taxas_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="ap_taxas_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="ap_taxas_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Material de Consumo</div>
                <input name="ap_mat_consumo_qtd" class="form-control tg-col-qtd">
                <input name="ap_mat_consumo_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="ap_mat_consumo_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="ap_mat_consumo_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="ap_mat_consumo_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Medicamentos</div>
                <input name="ap_medicametos_qtd" class="form-control tg-col-qtd">
                <input name="ap_medicametos_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="ap_medicametos_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="ap_medicametos_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="ap_medicametos_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Gases Medicinais</div>
                <input name="ap_gases_qtd" class="form-control tg-col-qtd">
                <input name="ap_gases_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="ap_gases_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="ap_gases_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="ap_gases_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Material Especial</div>
                <input name="ap_mat_espec_qtd" class="form-control tg-col-qtd">
                <input name="ap_mat_espec_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="ap_mat_espec_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="ap_mat_espec_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="ap_mat_espec_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Exames / SADT</div>
                <input name="ap_exames_qtd" class="form-control tg-col-qtd">
                <input name="ap_exames_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="ap_exames_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="ap_exames_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="ap_exames_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Hemoderivados</div>
                <input name="ap_hemoderivados_qtd" class="form-control tg-col-qtd">
                <input name="ap_hemoderivados_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="ap_hemoderivados_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="ap_hemoderivados_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="ap_hemoderivados_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Honorários</div>
                <input name="ap_honorarios_qtd" class="form-control tg-col-qtd">
                <input name="ap_honorarios_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="ap_honorarios_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="ap_honorarios_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="ap_honorarios_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>
        </div>

        <!-- CONSOLIDADO LOCAL (Apto) -->
        <div class="row g-2 mt-2 grp-totais">
            <div class="col-md-3">
                <label class="form-label">Total Cobrado (Apto)</label>
                <input type="text" name="apto_total_cobrado" class="form-control dinheiro grp-total-cobrado" readonly
                    value="R$ 0,00">
            </div>
            <div class="col-md-3">
                <label class="form-label">Total Glosado (Apto)</label>
                <input type="text" name="apto_total_glosado" class="form-control dinheiro grp-total-glosado" readonly
                    value="R$ 0,00">
            </div>
            <div class="col-md-3">
                <label class="form-label">Total Cobrado Após (Apto)</label>
                <input type="text" name="apto_total_liberado" class="form-control dinheiro grp-total-liberado" readonly
                    value="R$ 0,00">
            </div>
        </div>
        </div>
    </div>


    <!-- SETOR: UTI -->
    <div class="block uti" data-group="uti">
        <h5>
            <button class="block-toggle collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#grp-uti" aria-expanded="false" aria-controls="grp-uti">
                UTI
            </button>
        </h5>

        <div id="grp-uti" class="collapse">
            <div class="tuss-grid">
            <div class="tg-head tg-col-desc">Descrição</div>
            <div class="tg-head tg-col-qtd">Qtd.</div>
            <div class="tg-head tg-col-cob">Cobrado</div>
            <div class="tg-head tg-col-glo">Glosado</div>
            <div class="tg-head tg-col-lib">Cobrado Após</div>
            <div class="tg-head tg-col-obs">Observação</div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Terapias</div>
                <input name="uti_terapias_qtd" class="form-control tg-col-qtd">
                <input name="uti_terapias_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="uti_terapias_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="uti_terapias_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="uti_terapias_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Taxas / Aluguéis</div>
                <input name="uti_taxas_qtd" class="form-control tg-col-qtd">
                <input name="uti_taxas_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="uti_taxas_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="uti_taxas_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="uti_taxas_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Material de Consumo</div>
                <input name="uti_mat_consumo_qtd" class="form-control tg-col-qtd">
                <input name="uti_mat_consumo_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="uti_mat_consumo_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="uti_mat_consumo_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="uti_mat_consumo_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Medicamentos</div>
                <input name="uti_medicametos_qtd" class="form-control tg-col-qtd">
                <input name="uti_medicametos_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="uti_medicametos_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="uti_medicametos_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="uti_medicametos_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Gases Medicinais</div>
                <input name="uti_gases_qtd" class="form-control tg-col-qtd">
                <input name="uti_gases_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="uti_gases_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="uti_gases_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="uti_gases_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Material Especial</div>
                <input name="uti_mat_espec_qtd" class="form-control tg-col-qtd">
                <input name="uti_mat_espec_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="uti_mat_espec_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="uti_mat_espec_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="uti_mat_espec_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Exames / SADT</div>
                <input name="uti_exames_qtd" class="form-control tg-col-qtd">
                <input name="uti_exames_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="uti_exames_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="uti_exames_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="uti_exames_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Hemoderivados</div>
                <input name="uti_hemoderivados_qtd" class="form-control tg-col-qtd">
                <input name="uti_hemoderivados_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="uti_hemoderivados_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="uti_hemoderivados_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="uti_hemoderivados_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Honorários</div>
                <input name="uti_honorarios_qtd" class="form-control tg-col-qtd">
                <input name="uti_honorarios_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="uti_honorarios_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="uti_honorarios_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="uti_honorarios_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>
        </div>

        <!-- CONSOLIDADO LOCAL (UTI) -->
        <div class="row g-2 mt-2 grp-totais">
            <div class="col-md-3">
                <label class="form-label">Total Cobrado (UTI)</label>
                <input type="text" name="uti_total_cobrado" class="form-control dinheiro grp-total-cobrado" readonly
                    value="R$ 0,00">
            </div>
            <div class="col-md-3">
                <label class="form-label">Total Glosado (UTI)</label>
                <input type="text" name="uti_total_glosado" class="form-control dinheiro grp-total-glosado" readonly
                    value="R$ 0,00">
            </div>
            <div class="col-md-3">
                <label class="form-label">Total Cobrado Após (UTI)</label>
                <input type="text" name="uti_total_liberado" class="form-control dinheiro grp-total-liberado" readonly
                    value="R$ 0,00">
            </div>
        </div>
        </div>
    </div>

    <!-- SETOR: CENTRO CIRÚRGICO -->
    <div class="block cc" data-group="cc">
        <h5>
            <button class="block-toggle collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#grp-cc" aria-expanded="false" aria-controls="grp-cc">
                Centro Cirúrgico
            </button>
        </h5>

        <div id="grp-cc" class="collapse">
            <div class="tuss-grid">
            <div class="tg-head tg-col-desc">Descrição</div>
            <div class="tg-head tg-col-qtd">Qtd.</div>
            <div class="tg-head tg-col-cob">Cobrado</div>
            <div class="tg-head tg-col-glo">Glosado</div>
            <div class="tg-head tg-col-lib">Cobrado Após</div>
            <div class="tg-head tg-col-obs">Observação</div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Terapias</div>
                <input name="cc_terapias_qtd" class="form-control tg-col-qtd">
                <input name="cc_terapias_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="cc_terapias_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="cc_terapias_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="cc_terapias_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Taxas / Aluguéis</div>
                <input name="cc_taxas_qtd" class="form-control tg-col-qtd">
                <input name="cc_taxas_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="cc_taxas_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="cc_taxas_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="cc_taxas_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Material de Consumo</div>
                <input name="cc_mat_consumo_qtd" class="form-control tg-col-qtd">
                <input name="cc_mat_consumo_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="cc_mat_consumo_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="cc_mat_consumo_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="cc_mat_consumo_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Medicamentos</div>
                <input name="cc_medicametos_qtd" class="form-control tg-col-qtd">
                <input name="cc_medicametos_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="cc_medicametos_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="cc_medicametos_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="cc_medicametos_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Gases Medicinais</div>
                <input name="cc_gases_qtd" class="form-control tg-col-qtd">
                <input name="cc_gases_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="cc_gases_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="cc_gases_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="cc_gases_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Material Especial</div>
                <input name="cc_mat_espec_qtd" class="form-control tg-col-qtd">
                <input name="cc_mat_espec_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="cc_mat_espec_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="cc_mat_espec_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="cc_mat_espec_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Exames / SADT</div>
                <input name="cc_exames_qtd" class="form-control tg-col-qtd">
                <input name="cc_exames_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="cc_exames_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="cc_exames_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="cc_exames_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Hemoderivados</div>
                <input name="cc_hemoderivados_qtd" class="form-control tg-col-qtd">
                <input name="cc_hemoderivados_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="cc_hemoderivados_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="cc_hemoderivados_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="cc_hemoderivados_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Honorários</div>
                <input name="cc_honorarios_qtd" class="form-control tg-col-qtd">
                <input name="cc_honorarios_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="cc_honorarios_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="cc_honorarios_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="cc_honorarios_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>
        </div>

        <!-- CONSOLIDADO LOCAL (CC) -->
        <div class="row g-2 mt-2 grp-totais">
            <div class="col-md-3">
                <label class="form-label">Total Cobrado (CC)</label>
                <input type="text" name="cc_total_cobrado" class="form-control dinheiro grp-total-cobrado" readonly
                    value="R$ 0,00">
            </div>
            <div class="col-md-3">
                <label class="form-label">Total Glosado (CC)</label>
                <input type="text" name="cc_total_glosado" class="form-control dinheiro grp-total-glosado" readonly
                    value="R$ 0,00">
            </div>
            <div class="col-md-3">
                <label class="form-label">Total Cobrado Após (CC)</label>
                <input type="text" name="cc_total_liberado" class="form-control dinheiro grp-total-liberado" readonly
                    value="R$ 0,00">
            </div>
        </div>
        </div>
    </div>
    <!-- SETOR: OUTROS -->
    <div class="block" data-group="outros">
        <h5>
            <button class="block-toggle collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#grp-outros" aria-expanded="false" aria-controls="grp-outros">
                Outros
            </button>
        </h5>

        <div id="grp-outros" class="collapse">
            <div class="tuss-grid">
            <div class="tg-head tg-col-desc">Descrição</div>
            <div class="tg-head tg-col-qtd">Qtd.</div>
            <div class="tg-head tg-col-cob">Cobrado</div>
            <div class="tg-head tg-col-glo">Glosado</div>
            <div class="tg-head tg-col-lib">Cobrado Após</div>
            <div class="tg-head tg-col-obs">Observação</div>

            <!-- PACOTE -->
            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Pacote</div>
                <input name="outros_pacote_qtd" class="form-control tg-col-qtd" placeholder="Qtd">
                <input name="outros_pacote_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="outros_pacote_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="outros_pacote_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="outros_pacote_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>

            <!-- REMOÇÃO -->
            <div class="tuss-row rah-row">
                <div class="tg-lab tg-col-desc">Remoção</div>
                <input name="outros_remocao_qtd" class="form-control tg-col-qtd" placeholder="Qtd">
                <input name="outros_remocao_cobrado" class="form-control dinheiro tg-col-cob rah-cobrado"
                    placeholder="R$ 0,00">
                <input name="outros_remocao_glosado" class="form-control dinheiro tg-col-glo rah-glosado"
                    placeholder="R$ 0,00">
                <input name="outros_remocao_liberado" class="form-control dinheiro tg-col-lib rah-liberado"
                    placeholder="R$ 0,00" readonly>
                <input name="outros_remocao_obs" class="form-control tg-col-obs" placeholder="Observação">
            </div>
        </div>

        <!-- CONSOLIDADO LOCAL (Outros) -->
        <div class="row g-2 mt-2 grp-totais">
            <div class="col-md-3">
                <label class="form-label">Total Cobrado (Outros)</label>
                <input type="text" name="outros_total_cobrado" class="form-control dinheiro grp-total-cobrado" readonly
                    value="R$ 0,00">
            </div>
            <div class="col-md-3">
                <label class="form-label">Total Glosado (Outros)</label>
                <input type="text" name="outros_total_glosado" class="form-control dinheiro grp-total-glosado" readonly
                    value="R$ 0,00">
            </div>
            <div class="col-md-3">
                <label class="form-label">Total Cobrado Após (Outros)</label>
                <input type="text" name="outros_total_liberado" class="form-control dinheiro grp-total-liberado"
                    readonly value="R$ 0,00">
            </div>
        </div>
        </div>
    </div>

    <!-- OBSERVAÇÕES FINAIS -->
    <div class="block">
        <div id="alertPeriodo" class="alert alert-danger d-none" role="alert">
            A data final não pode ser anterior à data inicial.
        </div>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div class="flex-grow-1">
                <label class="form-label">Observações finais</label>
                <div id="comentarios_obs_preview" class="border rounded p-3 bg-light"
                    style="min-height:80px; white-space:pre-wrap;">
                    <?= $h($fv('comentarios_obs') ?: 'Nenhuma observação adicionada.') ?>
                </div>
            </div>
            <div class="text-end">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                    data-bs-target="#modalComentariosObs">
                    <i class="bi bi-pencil-square me-1"></i>Editar observações
                </button>
            </div>
        </div>
    </div>

    <!-- AÇÕES -->
    <div class="block">
        <div class="actions">
            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Salvar</button>
            <button type="button" class="btn btn-outline-primary" id="btnSalvarPDF"><i class="bi bi-download"></i>
                Salvar PDF</button>
            <button type="button" class="btn btn-outline-secondary" id="btnEnviarEmail"><i
                    class="bi bi-envelope-fill"></i>
                Enviar PDF por e-mail</button>
        </div>
        <iframe id="iframeDownload" name="iframeDownload" style="display:none;"></iframe>
        <div id="mensagemStatus"
            style="display:none;margin-top:10px;padding:10px;border-radius:5px;font-weight:bold;text-align:center;">
        </div>
    </div>

    <!-- Modal de Observações -->
    <div class="modal fade" id="modalComentariosObs" tabindex="-1" aria-labelledby="modalComentariosObsLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:1400px;width:95vw">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalComentariosObsLabel">Observações finais</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <textarea class="form-control" name="comentarios_obs" id="comentarios_obs" rows="8"
                        placeholder="Digite as observações que devem aparecer no final do RAH"><?= $h($fv('comentarios_obs')) ?></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnSalvarComentariosObs">Salvar observações</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal informações da parcial anterior -->
    <div class="modal fade" id="modalInfoParcial" tabindex="-1" aria-labelledby="modalInfoParcialLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background:#f2f2f2;border-bottom:1px solid #dadada;">
                    <h5 class="modal-title" id="modalInfoParcialLabel">Parcial anterior</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="color:#3a3a3a;">
                    <div id="prevParcialEmpty" class="text-muted" style="<?= !empty($parciaisLista) ? 'display:none' : '' ?>">
                        Nenhuma parcial anterior registrada.
                    </div>
                    <div id="prevParcialContent" style="<?= !empty($parciaisLista) ? '' : 'display:none' ?>">
                        <div class="row g-3 mb-3 parciais-header" id="prevParcialTituloRow">
                            <div class="col-12 col-md-4">
                                <strong>Nome</strong>
                                <div id="prevParcial_nome" class="text-uppercase fw-semibold"></div>
                            </div>
                            <div class="col-12 col-md-4">
                                <strong>Senha</strong>
                                <div id="prevParcial_senha" class="text-muted"></div>
                            </div>
                            <div class="col-12 col-md-4">
                                <strong>Matrícula</strong>
                                <div id="prevParcial_matricula" class="text-muted"></div>
                            </div>
                        </div>
                        <div class="table-responsive mt-4">
                            <table class="table table-striped align-middle" id="prevParcialTabela">
                                <thead class="table-light text-dark fw-semibold" style="background:#dfe9ff;color:#1f2a44;">
                                    <tr>
                                        <th>Parcial</th>
                                        <th>Período</th>
                                        <th>Valor apresentado</th>
                                        <th>Valor final</th>
                                        <th>Data fechamento</th>
                                        <th>Data lançamento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="text-muted">
                                        <td colspan="6" class="text-center">Nenhuma parcial registrada.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de período conflitante -->
    <div class="modal fade" id="modalPeriodoConflito" tabindex="-1" aria-labelledby="modalPeriodoConflitoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPeriodoConflitoLabel">Período inválido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Período em conflito com capeantes anteriores.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
#modalInfoParcial .modal-dialog.modal-xl {
    max-width: 1200px;
    width: 95%;
}
#modalInfoParcial .parciais-header strong {
    display: block;
    font-size: 0.95rem;
    color: #5c5c5c;
}
#modalInfoParcial .parciais-header div {
    font-size: 1.15rem;
}
#modalInfoParcial #prevParcialTabela thead th {
    font-size: 1.2rem;
    background-color: #dfe9ff !important;
    color: #1f2a44 !important;
    border-bottom: 2px solid #c0cde6;
}
#modalInfoParcial #prevParcialTabela td {
    font-size: 1.15rem;
}
</style>

<?php
$prevParcialData = base64_encode(json_encode($prevParcialInfo ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$listaParciaisData = base64_encode(json_encode($parciaisLista ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
?>
<div id="prevParcialData"
    data-prev-parcial="<?= htmlspecialchars($prevParcialData, ENT_QUOTES, 'UTF-8') ?>"
    data-parciais="<?= htmlspecialchars($listaParciaisData, ENT_QUOTES, 'UTF-8') ?>"
    data-capeante-id="<?= (int)$id_capeante ?>">
</div>
</div>
</div>

<!-- Vendors -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer></script>

<!-- RAH (agora em /js) -->
<script src="<?= $h($BASE_URL) ?>js/rah-core.js" defer></script>
<script src="<?= $h($BASE_URL) ?>js/rah-calc.js" defer></script>
<script src="<?= $h($BASE_URL) ?>js/rah-ui.js" defer></script>
<script src="<?= $h($BASE_URL) ?>js/rah-pdf.js" defer></script>
<script src="<?= $h($BASE_URL) ?>js/rah-cadcentral.js" defer></script>
<script src="<?= $h($BASE_URL) ?>js/form_cad_capeante_timer.js" defer></script>
<script defer>
document.addEventListener('DOMContentLoaded', function () {
    var textarea = document.getElementById('comentarios_obs');
    var preview = document.getElementById('comentarios_obs_preview');
    var btnSalvar = document.getElementById('btnSalvarComentariosObs');
    var modalEl = document.getElementById('modalComentariosObs');
    var parcialSelect = document.getElementById('parcial_capeante');
    var modalInfoParcial = document.getElementById('modalInfoParcial');
    var prevDataHolder = document.getElementById('prevParcialData');
    var prevParcialInfo = null;
    var listaParciais = [];

    if (prevDataHolder && prevDataHolder.dataset.prevParcial) {
        try {
            var decoded = atob(prevDataHolder.dataset.prevParcial);
            var parsed = JSON.parse(decoded);
            if (parsed && Object.keys(parsed).length > 0) {
                prevParcialInfo = parsed;
            }
        } catch (err) {
            console.error('Falha ao decodificar dados da parcial anterior', err);
        }
    }
    if (prevDataHolder && prevDataHolder.dataset.parciais) {
        try {
            var decodedList = atob(prevDataHolder.dataset.parciais);
            var parsedList = JSON.parse(decodedList);
            if (Array.isArray(parsedList)) listaParciais = parsedList;
        } catch (err) {
            console.error('Falha ao decodificar lista de parciais', err);
        }
    }

    var currentCapeanteId = null;
    if (prevDataHolder && prevDataHolder.dataset.capeanteId) {
        var parsedCapeanteId = Number(prevDataHolder.dataset.capeanteId);
        if (!Number.isNaN(parsedCapeanteId) && parsedCapeanteId > 0) {
            currentCapeanteId = parsedCapeanteId;
        }
    }
    var modalPeriodoConflito = document.getElementById('modalPeriodoConflito');
    var modalPeriodoConflitoInstance = modalPeriodoConflito && window.bootstrap
        ? bootstrap.Modal.getOrCreateInstance(modalPeriodoConflito)
        : null;
    var periodoConflitoAtivo = false;
    var periodosAnteriores = [];

    function parseValidDateYMD(value) {
        if (!value || value === '0000-00-00') return null;
        var normalized = value.replace(/-/g, '/');
        var parsed = new Date(normalized);
        return isNaN(parsed.getTime()) ? null : parsed;
    }

    function rebuildPeriodos() {
        periodosAnteriores = [];
        if (!Array.isArray(listaParciais) || !listaParciais.length) return;
        periodosAnteriores = listaParciais.map(function (item) {
            var inicio = parseValidDateYMD(item.data_inicial_capeante);
            if (!inicio) return null;
            var fim = parseValidDateYMD(item.data_final_capeante) || inicio;
            if (!fim) return null;
            return {
                id: item.id_capeante ? Number(item.id_capeante) : null,
                start: inicio,
                end: fim
            };
        }).filter(function (item) {
            return item !== null;
        });
    }

    rebuildPeriodos();

    function formatCurrencyBR(value) {
        var num = Number(value);
        if (isNaN(num)) return 'R$ 0,00';
        return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function formatDateBR(value) {
        if (!value) return '-';
        var dt = new Date(value.replace(/-/g, '/'));
        return isNaN(dt.getTime()) ? value : dt.toLocaleDateString('pt-BR');
    }

    function formatPeriodoRange(ini, fim) {
        if (!ini && !fim) return '-';
        var iniFmt = formatDateBR(ini);
        var fimFmt = fim ? formatDateBR(fim) : '';
        return fimFmt ? (iniFmt + ' a ' + fimFmt) : iniFmt;
    }

    function renderTabelaParciais(lista) {
        var tabela = document.getElementById('prevParcialTabela');
        if (!tabela) return;
        var corpo = tabela.querySelector('tbody');
        if (!corpo) return;
        corpo.innerHTML = '';
        if (!Array.isArray(lista) || lista.length === 0) {
            corpo.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Nenhuma parcial registrada.</td></tr>';
            return;
        }
        lista.forEach(function (item) {
            var periodo = formatPeriodoRange(item.data_inicial_capeante, item.data_final_capeante);
            corpo.insertAdjacentHTML('beforeend', `
                <tr>
                    <td>${item.parcial_num ? ('#' + item.parcial_num) : '-'}</td>
                    <td>${periodo}</td>
                    <td>${formatCurrencyBR(item.valor_apresentado_capeante)}</td>
                    <td>${formatCurrencyBR(item.valor_final_capeante)}</td>
                    <td>${formatDateBR(item.data_fech_capeante)}</td>
                    <td>${formatDateBR(item.data_digit_capeante)}</td>
                </tr>
            `);
        });
    }

        renderTabelaParciais(listaParciais);
        var campo = function(id, texto) {
            var el = document.getElementById(id);
            if (el) el.textContent = texto || '-';
        };
        campo('prevParcial_nome', prevParcialInfo && prevParcialInfo.nome ? prevParcialInfo.nome : '-');
        campo('prevParcial_senha', prevParcialInfo && prevParcialInfo.senha ? prevParcialInfo.senha : '-');
        campo('prevParcial_matricula', prevParcialInfo && prevParcialInfo.matricula ? prevParcialInfo.matricula : '-');


    function atualizaPreview() {
        if (!preview || !textarea) return;
        var texto = (textarea.value || '').trim();
        preview.textContent = texto ? texto : 'Nenhuma observação adicionada.';
    }
    if (textarea) {
        textarea.addEventListener('input', atualizaPreview);
        textarea.addEventListener('change', atualizaPreview);
    }

    if (btnSalvar) {
        btnSalvar.addEventListener('click', function () {
            atualizaPreview();
            if (modalEl && window.bootstrap) {
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
        });
    }
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', atualizaPreview);
    }
    atualizaPreview();

    function preencherModalParcial() {
        if (!modalInfoParcial) return;
        var emptyMsg = document.getElementById('prevParcialEmpty');
        var content = document.getElementById('prevParcialContent');
        var temLista = Array.isArray(listaParciais) && listaParciais.length > 0;
        if (!temLista) {
            if (emptyMsg) emptyMsg.style.display = '';
            if (content) content.style.display = 'none';
            return;
        }
        if (emptyMsg) emptyMsg.style.display = 'none';
        if (content) content.style.display = '';
        renderTabelaParciais(listaParciais);
        const titulo = document.getElementById('prevParcialTitulo');
        if (titulo) {
            var partes = [];
            if (prevParcialInfo && prevParcialInfo.nome) partes.push(prevParcialInfo.nome);
            if (prevParcialInfo && prevParcialInfo.senha) partes.push('Senha ' + prevParcialInfo.senha);
            if (prevParcialInfo && prevParcialInfo.matricula) partes.push('Matricula ' + prevParcialInfo.matricula);
            titulo.textContent = partes.length ? partes.join(' • ') : 'Parciais anteriores';
        }
    }

    if (parcialSelect && modalInfoParcial) {
        parcialSelect.addEventListener('change', function () {
            if (this.value === 's') {
                preencherModalParcial();
                var modalInstance = window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalInfoParcial) : null;
                if (modalInstance) modalInstance.show();
            }
        });
        if (parcialSelect.value === 's' && Array.isArray(listaParciais) && listaParciais.length) {
            preencherModalParcial();
            setTimeout(function () {
                var modalInstance = window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalInfoParcial) : null;
                if (modalInstance) modalInstance.show();
            }, 300);
        }
    }

    // Validação visual do período
    var inputIni = document.querySelector('input[name="data_inicial_capeante"]');
    var inputFim = document.querySelector('input[name="data_final_capeante"]');
    var alertPeriodo = document.getElementById('alertPeriodo');

    function resetPeriodosCampos() {
        if (inputIni) inputIni.value = '';
        if (inputFim) inputFim.value = '';
        if (alertPeriodo) alertPeriodo.classList.add('d-none');
    }

    function abrirModalPeriodoConflito() {
        if (modalPeriodoConflitoInstance) {
            periodoConflitoAtivo = true;
            modalPeriodoConflitoInstance.show();
        } else {
            alert('Período em conflito com capeantes anteriores.');
            resetPeriodosCampos();
        }
    }

    function hasPeriodoConflito(iniValue, fimValue) {
        if (!iniValue || !fimValue || !Array.isArray(periodosAnteriores) || !periodosAnteriores.length) return false;
        var inicio = parseValidDateYMD(iniValue);
        var fim = parseValidDateYMD(fimValue) || inicio;
        if (!inicio || !fim || fim < inicio) return false;
        for (var i = 0; i < periodosAnteriores.length; i++) {
            var periodo = periodosAnteriores[i];
            if (currentCapeanteId && periodo.id && Number(periodo.id) === currentCapeanteId) continue;
            if (inicio <= periodo.end && periodo.start <= fim) {
                return true;
            }
        }
        return false;
    }

    function verificarPeriodoConflito(iniValue, fimValue) {
        if (periodoConflitoAtivo) return;
        if (!parcialSelect || parcialSelect.value !== 's') return;
        if (hasPeriodoConflito(iniValue, fimValue)) {
            abrirModalPeriodoConflito();
        }
    }

    if (modalPeriodoConflito) {
        modalPeriodoConflito.addEventListener('hidden.bs.modal', function () {
            if (!periodoConflitoAtivo) return;
            periodoConflitoAtivo = false;
            resetPeriodosCampos();
        });
    }

    function validarPeriodo() {
        if (!inputIni || !inputFim || !alertPeriodo) return;
        var ini = inputIni.value;
        var fim = inputFim.value;
        if (ini && fim && new Date(fim) < new Date(ini)) {
            inputFim.value = '';
            alertPeriodo.classList.remove('d-none');
            setTimeout(function () {
                alertPeriodo.classList.add('d-none');
            }, 5000);
        } else {
            alertPeriodo.classList.add('d-none');
            verificarPeriodoConflito(ini, fim);
        }
    }
    if (inputIni) inputIni.addEventListener('change', validarPeriodo);
    if (inputFim) inputFim.addEventListener('change', validarPeriodo);
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const contaParada = document.getElementById('conta_parada_cap');
    const motivoWrap = document.getElementById('parada-motivo-wrapper');
    const motivoSelect = document.getElementById('parada_motivo_cap');

    if (!contaParada || !motivoWrap) return;

    const toggleMotivo = () => {
        if (contaParada.value === 's') {
            motivoWrap.style.display = '';
        } else {
            motivoWrap.style.display = 'none';
            if (motivoSelect) motivoSelect.value = '';
        }
    };

    contaParada.addEventListener('change', toggleMotivo);
    toggleMotivo();
});
</script>

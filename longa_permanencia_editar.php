<?php
include_once("check_logado.php");
require_once("globals.php");
require_once("db.php");
require_once("templates/header.php");
require_once("dao/longaPermanenciaDao.php");

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$internacaoId = filter_input(INPUT_GET, 'id_internacao', FILTER_VALIDATE_INT) ?: 0;
$dao = new LongaPermanenciaDAO($conn, $BASE_URL);
$context = $internacaoId > 0 ? $dao->getContextByInternacao($internacaoId) : null;
if (!$context) {
    echo '<div class="container mt-4"><div class="alert alert-warning">Internação não encontrada.</div></div>';
    require_once("templates/footer.php");
    exit;
}

$history = $dao->getHistoryByInternacao($internacaoId);
$latest = $history[0] ?? null;
$statusOptions = $dao->getStatusOptions();
$motivoOptions = $dao->getMotivoOptions();
$riscoOptions = $dao->getRiscoOptions();
$success = isset($_GET['success']) ? (int)$_GET['success'] : 0;

$dias = (int)($context['diarias'] ?? 0);
$limiar = (int)($context['limiar'] ?? 30);
$excesso = max(0, $dias - $limiar);
$statusAtualLabel = $statusOptions[$latest['status_lp'] ?? ''] ?? 'Sem status';
$motivoAtualLabel = $motivoOptions[$latest['motivo_principal_lp'] ?? ''] ?? (!empty($latest['motivo_principal_lp']) ? (string)$latest['motivo_principal_lp'] : 'Não definido');
$riscoAtualLabel = $riscoOptions[$latest['risco_sinistro_lp'] ?? ''] ?? (!empty($latest['risco_sinistro_lp']) ? (string)$latest['risco_sinistro_lp'] : 'Não definido');
$ultimaAtualizacaoLabel = !empty($latest['data_atualizacao_lp']) ? date('d/m/Y H:i', strtotime((string)$latest['data_atualizacao_lp'])) : 'Sem atualização';
?>

<style>
.lpd-shell { padding:22px 18px 36px; background:
    radial-gradient(circle at top left, rgba(144, 121, 206, 0.12), transparent 30%),
    linear-gradient(180deg, #f7f5fb 0%, #eef2f7 100%); min-height:calc(100vh - 100px); }
.lpd-hero { display:flex; justify-content:space-between; align-items:flex-end; gap:18px; margin-bottom:18px; }
.lpd-hero h1 { margin:0; font-size:1.7rem; line-height:1.05; color:#2f2240; }
.lpd-hero p { margin:8px 0 0; color:#6b6580; max-width:780px; }
.lpd-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:40px; padding:0 15px; border-radius:12px; border:1px solid rgba(94,35,99,.14); background:#fff; color:#4f2b63; font-weight:700; text-decoration:none; box-shadow:0 8px 18px rgba(40,26,64,.05); }
.lpd-btn--primary { background:linear-gradient(135deg, #5e2363, #7d52a1); color:#fff; border:none; }
.lpd-grid { display:grid; grid-template-columns:minmax(0, 1.15fr) minmax(320px, .85fr); gap:16px; }
.lpd-card { background:#fff; border:1px solid rgba(94,35,99,.08); border-radius:22px; box-shadow:0 18px 38px rgba(40,26,64,.08); overflow:hidden; }
.lpd-card--hero { margin-bottom:16px; background:linear-gradient(135deg, rgba(94,35,99,.98), rgba(125,82,161,.92)); color:#fff; }
.lpd-card__head { padding:16px 18px; border-bottom:1px solid rgba(94,35,99,.08); display:flex; align-items:center; justify-content:space-between; gap:12px; }
.lpd-card__head h2 { margin:0; font-size:1.02rem; color:#2f2240; }
.lpd-card__body { padding:18px; }
.lpd-card--hero .lpd-card__body { padding:18px 18px 20px; }
.lpd-card--hero .lpd-hero-top { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin-bottom:16px; }
.lpd-card--hero .lpd-patient-name { margin:0; font-size:1.38rem; font-weight:800; letter-spacing:-.03em; }
.lpd-card--hero .lpd-patient-meta { margin-top:7px; color:rgba(255,255,255,.82); font-size:.88rem; }
.lpd-status-rail { display:flex; flex-wrap:wrap; gap:10px; }
.lpd-status-card { min-width:150px; padding:12px 14px; border-radius:16px; background:rgba(255,255,255,.11); border:1px solid rgba(255,255,255,.14); backdrop-filter:blur(8px); }
.lpd-status-card small { display:block; text-transform:uppercase; letter-spacing:.08em; font-size:.62rem; color:rgba(255,255,255,.66); margin-bottom:6px; }
.lpd-status-card strong { display:block; font-size:.95rem; color:#fff; }
.lpd-context { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; }
.lpd-kpi { padding:13px 14px; border-radius:16px; background:#faf8fd; border:1px solid #ece7f4; }
.lpd-kpi small { display:block; text-transform:uppercase; letter-spacing:.08em; font-size:.64rem; color:#7a728f; margin-bottom:6px; }
.lpd-kpi strong { color:#2f2240; font-size:1.14rem; }
.lpd-kpi--critical { background:linear-gradient(180deg, #fff5f5, #fff0f0); border-color:#f3cccc; }
.lpd-kpi--critical strong { color:#a33d3d; }
.lpd-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:14px; }
.lpd-form-section { grid-column:1 / -1; display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:14px; padding-top:6px; }
.lpd-form-section + .lpd-form-section { margin-top:2px; }
.lpd-section-title { grid-column:1 / -1; display:flex; align-items:center; gap:8px; margin:2px 0 -2px; font-size:.74rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#6b6580; }
.lpd-section-title::before { content:""; width:18px; height:2px; border-radius:999px; background:#7c57a6; }
.lpd-field label { display:block; margin-bottom:6px; font-size:.73rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#6b6580; }
.lpd-field input, .lpd-field select, .lpd-field textarea { width:100%; min-height:42px; border-radius:12px; border:1px solid #d8d2e4; padding:10px 12px; font-size:.83rem; color:#342944; background:#fff; transition:border-color .15s ease, box-shadow .15s ease, background .15s ease; }
.lpd-field input:focus, .lpd-field select:focus, .lpd-field textarea:focus { border-color:#a27bc4; box-shadow:0 0 0 3px rgba(124,87,166,.14); outline:none; background:#fff; }
.lpd-field textarea { min-height:108px; resize:vertical; line-height:1.45; }
.lpd-span-2 { grid-column:1 / -1; }
.lpd-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:18px; padding-top:14px; border-top:1px solid #eee8f6; }
.lpd-timeline { display:flex; flex-direction:column; gap:12px; }
.lpd-entry { border:1px solid #ece7f4; background:linear-gradient(180deg, #fcfbfe, #f8f5fc); border-radius:16px; padding:14px; }
.lpd-entry-top { display:flex; justify-content:space-between; gap:12px; margin-bottom:8px; align-items:flex-start; }
.lpd-badge { display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:999px; background:#efe9f8; color:#5b4377; font-size:.7rem; font-weight:800; }
.lpd-entry-grid { display:grid; grid-template-columns:1fr; gap:8px; }
.lpd-entry-row { color:#403651; font-size:.82rem; line-height:1.45; }
.lpd-entry-row strong { color:#2f2240; }
.lpd-muted { color:#7e7692; font-size:.74rem; }
.lpd-alert { margin-bottom:14px; padding:12px 14px; border-radius:12px; background:#e9f7ee; border:1px solid #b9e2c8; color:#2f6a43; font-weight:700; }
@media (max-width: 1180px) { .lpd-grid { grid-template-columns:1fr; } .lpd-context { grid-template-columns:repeat(2, minmax(0,1fr)); } }
@media (max-width: 760px) { .lpd-form-grid, .lpd-form-section, .lpd-context { grid-template-columns:1fr; } .lpd-hero, .lpd-card--hero .lpd-hero-top { flex-direction:column; align-items:flex-start; } .lpd-status-card { width:100%; } }
</style>

<div class="lpd-shell">
    <div class="lpd-hero">
        <div>
            <h1>Gestão clínica da longa permanência</h1>
            <p><?= e($context['nome_pac'] ?? 'Paciente') ?> · <?= e($context['nome_hosp'] ?? 'Hospital') ?> · senha <?= e($context['senha_int'] ?? '-') ?></p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="lpd-btn" href="<?= $BASE_URL ?>longa_permanencia_gestao.php">Voltar à fila</a>
            <a class="lpd-btn" href="<?= $BASE_URL ?>show_internacao.php?id_internacao=<?= (int)$internacaoId ?>">Abrir internação</a>
        </div>
    </div>

    <?php if ($success === 1): ?>
        <div class="lpd-alert">Atualização registrada com sucesso.</div>
    <?php endif; ?>

    <div class="lpd-card lpd-card--hero">
        <div class="lpd-card__body">
            <div class="lpd-hero-top">
                <div>
                    <h2 class="lpd-patient-name"><?= e($context['nome_pac'] ?? 'Paciente') ?></h2>
                    <div class="lpd-patient-meta">
                        <?= e($context['nome_hosp'] ?? 'Hospital') ?> ·
                        <?= e($context['seguradora_seg'] ?? 'Sem seguradora') ?> ·
                        Matrícula <?= e($context['matricula_pac'] ?? '-') ?> ·
                        Senha <?= e($context['senha_int'] ?? '-') ?>
                    </div>
                </div>
                <div class="lpd-status-rail">
                    <div class="lpd-status-card">
                        <small>Status atual</small>
                        <strong><?= e($statusAtualLabel) ?></strong>
                    </div>
                    <div class="lpd-status-card">
                        <small>Motivo principal</small>
                        <strong><?= e($motivoAtualLabel) ?></strong>
                    </div>
                    <div class="lpd-status-card">
                        <small>Risco atual</small>
                        <strong><?= e($riscoAtualLabel) ?></strong>
                    </div>
                    <div class="lpd-status-card">
                        <small>Última atualização</small>
                        <strong><?= e($ultimaAtualizacaoLabel) ?></strong>
                    </div>
                </div>
            </div>
            <div class="lpd-context">
                <div class="lpd-kpi"><small>Dias internado</small><strong><?= number_format($dias, 0, ',', '.') ?>d</strong></div>
                <div class="lpd-kpi"><small>Limiar</small><strong><?= number_format($limiar, 0, ',', '.') ?>d</strong></div>
                <div class="lpd-kpi lpd-kpi--critical"><small>Excesso</small><strong><?= number_format($excesso, 0, ',', '.') ?>d</strong></div>
                <div class="lpd-kpi"><small>Seguradora</small><strong><?= e($context['seguradora_seg'] ?? 'Sem seguradora') ?></strong></div>
            </div>
        </div>
    </div>

    <div class="lpd-grid">
        <section class="lpd-card">
            <div class="lpd-card__head">
                <h2>Nova atualização</h2>
                <?php if ($latest): ?>
                    <span class="lpd-badge">Último status: <?= e($statusOptions[$latest['status_lp']] ?? 'Sem status') ?></span>
                <?php endif; ?>
            </div>
            <div class="lpd-card__body">
                <form method="post" action="<?= $BASE_URL ?>process_longa_permanencia.php">
                    <input type="hidden" name="fk_internacao_lp" value="<?= (int)$internacaoId ?>">
                    <div class="lpd-form-grid">
                        <div class="lpd-form-section">
                            <div class="lpd-section-title">Classificação do caso</div>
                            <div class="lpd-field">
                                <label>Status do caso</label>
                                <select name="status_lp" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($statusOptions as $key => $label): ?>
                                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="lpd-field">
                                <label>Motivo principal</label>
                                <select name="motivo_principal_lp">
                                    <option value="">Selecione</option>
                                    <?php foreach ($motivoOptions as $key => $label): ?>
                                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="lpd-field">
                                <label>Responsável</label>
                                <input type="text" name="responsavel_lp" placeholder="Nome do responsável pelo caso">
                            </div>
                            <div class="lpd-field">
                                <label>Risco de sinistro</label>
                                <select name="risco_sinistro_lp">
                                    <option value="">Selecione</option>
                                    <?php foreach ($riscoOptions as $key => $label): ?>
                                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="lpd-form-section">
                            <div class="lpd-section-title">Prazos e governança</div>
                            <div class="lpd-field">
                                <label>Prazo da ação</label>
                                <input type="date" name="prazo_acao_lp">
                            </div>
                            <div class="lpd-field">
                                <label>Próxima revisão</label>
                                <input type="date" name="proxima_revisao_lp">
                            </div>
                            <div class="lpd-field">
                                <label>Previsão de alta / transição</label>
                                <input type="date" name="previsao_alta_lp">
                            </div>
                            <div class="lpd-field">
                                <label>Desospitalização potencial</label>
                                <select name="potencial_desospitalizacao_lp">
                                    <option value="n">Não</option>
                                    <option value="s">Sim</option>
                                </select>
                            </div>
                            <div class="lpd-field lpd-span-2">
                                <label>Necessita escalonamento</label>
                                <select name="necessita_escalonamento_lp">
                                    <option value="n">Não</option>
                                    <option value="s">Sim</option>
                                </select>
                            </div>
                        </div>
                        <div class="lpd-form-section">
                            <div class="lpd-section-title">Análise clínica e barreiras</div>
                            <div class="lpd-field lpd-span-2">
                                <label>Barreira clínica</label>
                                <textarea name="barreira_clinica_lp" placeholder="Ex.: dependência de suporte, instabilidade clínica, infecção, desmame, reabilitação..."></textarea>
                            </div>
                            <div class="lpd-field lpd-span-2">
                                <label>Barreira administrativa / social</label>
                                <textarea name="barreira_administrativa_lp" placeholder="Ex.: autorização, família, vaga, home care, material, documentação..."></textarea>
                            </div>
                            <div class="lpd-field lpd-span-2">
                                <label>Plano de ação pactuado</label>
                                <textarea name="plano_acao_lp" placeholder="Descreva a conduta, o combinado com hospital/seguradora/família e o próximo passo objetivo."></textarea>
                            </div>
                            <div class="lpd-field lpd-span-2">
                                <label>Observações adicionais</label>
                                <textarea name="observacoes_lp" placeholder="Resumo clínico objetivo, risco, travas, oportunidade de redução de permanência, etc."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="lpd-actions">
                        <button class="lpd-btn lpd-btn--primary" type="submit">Registrar atualização</button>
                    </div>
                </form>
            </div>
        </section>

        <aside class="lpd-card">
            <div class="lpd-card__head">
                <h2>Histórico do caso</h2>
                <span class="lpd-muted"><?= number_format(count($history), 0, ',', '.') ?> atualização(ões)</span>
            </div>
            <div class="lpd-card__body">
                <?php if (!$history): ?>
                    <div class="lpd-muted">Nenhuma atualização registrada ainda.</div>
                <?php else: ?>
                    <div class="lpd-timeline">
                        <?php foreach ($history as $entry): ?>
                            <div class="lpd-entry">
                                <div class="lpd-entry-top">
                                    <div>
                                        <div class="lpd-badge"><?= e($statusOptions[$entry['status_lp']] ?? 'Sem status') ?></div>
                                        <div class="lpd-muted" style="margin-top:6px;">
                                            <?= !empty($entry['data_atualizacao_lp']) ? e(date('d/m/Y H:i', strtotime((string)$entry['data_atualizacao_lp']))) : '-' ?>
                                            · <?= e($entry['usuario_user'] ?? 'Usuário não identificado') ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($entry['risco_sinistro_lp'])): ?>
                                        <div class="lpd-muted">Risco: <?= e($riscoOptions[$entry['risco_sinistro_lp']] ?? $entry['risco_sinistro_lp']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="lpd-entry-grid">
                                    <?php if (!empty($entry['motivo_principal_lp'])): ?><div class="lpd-entry-row"><strong>Motivo:</strong> <?= e($motivoOptions[$entry['motivo_principal_lp']] ?? $entry['motivo_principal_lp']) ?></div><?php endif; ?>
                                    <?php if (!empty($entry['responsavel_lp'])): ?><div class="lpd-entry-row"><strong>Responsável:</strong> <?= e($entry['responsavel_lp']) ?></div><?php endif; ?>
                                    <?php if (!empty($entry['barreira_clinica_lp'])): ?><div class="lpd-entry-row"><strong>Barreira clínica:</strong><br><?= nl2br(e($entry['barreira_clinica_lp'])) ?></div><?php endif; ?>
                                    <?php if (!empty($entry['barreira_administrativa_lp'])): ?><div class="lpd-entry-row"><strong>Barreira administrativa:</strong><br><?= nl2br(e($entry['barreira_administrativa_lp'])) ?></div><?php endif; ?>
                                    <?php if (!empty($entry['plano_acao_lp'])): ?><div class="lpd-entry-row"><strong>Plano:</strong><br><?= nl2br(e($entry['plano_acao_lp'])) ?></div><?php endif; ?>
                                    <?php if (!empty($entry['observacoes_lp'])): ?><div class="lpd-entry-row"><strong>Observações:</strong><br><?= nl2br(e($entry['observacoes_lp'])) ?></div><?php endif; ?>
                                </div>
                                <div class="lpd-muted" style="margin-top:8px;">
                                    Próxima revisão: <?= !empty($entry['proxima_revisao_lp']) ? e(date('d/m/Y', strtotime((string)$entry['proxima_revisao_lp']))) : '-' ?>
                                    · Previsão de alta: <?= !empty($entry['previsao_alta_lp']) ? e(date('d/m/Y', strtotime((string)$entry['previsao_alta_lp']))) : '-' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>

<?php
include_once("check_logado.php");
require_once("globals.php");
require_once("db.php");
require_once("templates/header.php");
require_once("dao/homeCareDao.php");

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$internacaoId = filter_input(INPUT_GET, 'id_internacao', FILTER_VALIDATE_INT) ?: 0;
if ($internacaoId <= 0) {
    echo "<div class='container py-4'><div class='alert alert-danger'>Internação inválida para avaliação de Home Care.</div></div>";
    require_once("templates/footer.php");
    exit;
}

$dao = new HomeCareDAO($conn, $BASE_URL);
$context = $dao->getContextByInternacao($internacaoId);
$history = $dao->getHistoryByInternacao($internacaoId);
$latest = $history[0] ?? null;

if (!$context) {
    echo "<div class='container py-4'><div class='alert alert-danger'>Internação não encontrada.</div></div>";
    require_once("templates/footer.php");
    exit;
}

$statusOptions = $dao->getStatusOptions();
$modalidadeOptions = $dao->getModalidadeOptions();
$barreiraOptions = $dao->getBarreiraOptions();
$preview = $dao->calculateNead($latest ?? []);
if (!$latest) {
    $preview['nead_classificacao_hc'] = 'Sem avaliação anterior';
    $preview['nead_pontuacao_hc'] = 0;
    $preview['nead_elegivel_hc'] = 'n';
    $preview['nead_indicacao_imediata_hc'] = 'n';
}
?>

<style>
.hce-shell { padding:20px 18px 34px; background:linear-gradient(180deg, #f5f8fc 0%, #eef4fb 100%); min-height:calc(100vh - 100px); }
.hce-hero { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:16px; align-items:center; margin-bottom:16px; }
.hce-hero-card { background:linear-gradient(135deg, #1e5f95, #5ca6ea); color:#fff; border-radius:22px; padding:22px 24px; box-shadow:0 20px 42px rgba(29,84,141,.22); }
.hce-overline { text-transform:uppercase; letter-spacing:.14em; font-size:.72rem; opacity:.86; font-weight:700; }
.hce-hero h1 { margin:4px 0 0; font-size:2rem; color:#fff; }
.hce-meta { display:flex; gap:16px; flex-wrap:wrap; margin-top:10px; font-size:.88rem; opacity:.95; }
.hce-btn { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:0 15px; border-radius:12px; background:#fff; color:#235685; font-weight:700; text-decoration:none; border:1px solid rgba(34,88,148,.14); }
.hce-strip { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin-bottom:16px; }
.hce-pillcard, .hce-kpi, .hce-card { background:#fff; border:1px solid rgba(34,88,148,.08); border-radius:18px; box-shadow:0 16px 34px rgba(30,60,110,.08); }
.hce-pillcard { padding:14px 16px; }
.hce-pillcard small, .hce-kpi small { display:block; color:#728198; text-transform:uppercase; letter-spacing:.08em; font-size:.66rem; margin-bottom:6px; }
.hce-pillcard strong, .hce-kpi strong { font-size:1.25rem; color:#1f3150; }
.hce-kpis { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin-bottom:16px; }
.hce-kpi { padding:14px 16px; }
.hce-layout { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr); gap:16px; }
.hce-card__head { padding:16px 18px 12px; border-bottom:1px solid rgba(34,88,148,.08); display:flex; align-items:center; justify-content:space-between; gap:12px; }
.hce-card__head h2 { margin:0; font-size:1.02rem; color:#1f3150; }
.hce-card__body { padding:18px; }
.hce-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:14px 16px; }
.hce-form-grid--full { grid-template-columns:1fr; }
.hce-field label { display:block; margin-bottom:6px; font-weight:700; color:#38506d; font-size:.82rem; }
.hce-field input, .hce-field select, .hce-field textarea { width:100%; min-height:40px; border-radius:12px; border:1px solid #d7e1ef; padding:9px 12px; font-size:.88rem; color:#243d5c; background:#fff; }
.hce-field textarea { min-height:92px; resize:vertical; }
.hce-section-title { margin:0 0 10px; font-size:.88rem; color:#5c6d86; text-transform:uppercase; letter-spacing:.1em; font-weight:700; }
.hce-checkgrid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; margin-top:6px; }
.hce-check { display:flex; align-items:flex-start; gap:10px; padding:10px 12px; border:1px solid #dce6f2; border-radius:12px; background:#f9fbfe; }
.hce-check input { width:auto; min-height:auto; margin-top:2px; }
.hce-scoregrid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px; }
.hce-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:18px; }
.hce-submit { min-height:42px; padding:0 18px; border:none; border-radius:12px; background:linear-gradient(135deg, #2a78c2, #58a0eb); color:#fff; font-weight:700; }
.hce-secondary { min-height:42px; padding:0 18px; border-radius:12px; border:1px solid rgba(34,88,148,.14); background:#fff; color:#235685; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; }
.hce-history { display:grid; gap:12px; }
.hce-history-card { border:1px solid #e1eaf5; border-radius:16px; padding:14px 15px; background:#fbfdff; }
.hce-history-head { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:8px; }
.hce-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:.72rem; font-weight:700; }
.hce-chip--ok { background:#e6f7ec; color:#2b7a46; }
.hce-chip--warn { background:#fff2da; color:#946200; }
.hce-chip--neutral { background:#eef5ff; color:#315b8d; }
.hce-sub { color:#7b8ba3; font-size:.78rem; }
@media (max-width: 1100px) { .hce-layout, .hce-kpis, .hce-strip { grid-template-columns:1fr 1fr; } }
@media (max-width: 760px) { .hce-hero { grid-template-columns:1fr; } .hce-layout, .hce-kpis, .hce-strip, .hce-form-grid, .hce-checkgrid, .hce-scoregrid { grid-template-columns:1fr; } }
</style>

<div class="hce-shell">
    <div class="hce-hero">
        <div class="hce-hero-card">
            <div class="hce-overline">Fluxo Home Care</div>
            <h1>Avaliação e gestão do caso</h1>
            <div class="hce-meta">
                <span><strong>Paciente:</strong> <?= e($context['nome_pac'] ?? 'Sem nome') ?></span>
                <span><strong>Hospital:</strong> <?= e($context['nome_hosp'] ?? 'Sem hospital') ?></span>
                <span><strong>Seguradora:</strong> <?= e($context['seguradora_seg'] ?? 'Sem seguradora') ?></span>
                <span><strong>Dias internado:</strong> <?= number_format((int)($context['diarias'] ?? 0), 0, ',', '.') ?></span>
            </div>
        </div>
        <div>
            <a class="hce-btn" href="<?= $BASE_URL ?>home_care_gestao.php">Voltar à fila</a>
        </div>
    </div>

    <div class="hce-strip">
        <div class="hce-pillcard"><small>Status atual</small><strong><?= e($statusOptions[$latest['status_hc'] ?? ''] ?? 'Sem status') ?></strong></div>
        <div class="hce-pillcard"><small>Classificação NEAD</small><strong><?= e($latest['nead_classificacao_hc'] ?? $preview['nead_classificacao_hc']) ?></strong></div>
        <div class="hce-pillcard"><small>Modalidade sugerida</small><strong><?= e($modalidadeOptions[$latest['modalidade_sugerida_hc'] ?? ''] ?? 'Sem modalidade') ?></strong></div>
        <div class="hce-pillcard"><small>Última atualização</small><strong><?= !empty($latest['data_atualizacao_hc']) ? e(date('d/m/Y H:i', strtotime((string)$latest['data_atualizacao_hc']))) : 'Sem histórico' ?></strong></div>
    </div>

    <div class="hce-kpis">
        <div class="hce-kpi"><small>Score NEAD</small><strong><?= number_format((int)($latest['nead_pontuacao_hc'] ?? $preview['nead_pontuacao_hc']), 0, ',', '.') ?></strong></div>
        <div class="hce-kpi"><small>Elegibilidade</small><strong><?= (($latest['nead_elegivel_hc'] ?? $preview['nead_elegivel_hc']) === 's') ? 'Elegível' : 'Pendente' ?></strong></div>
        <div class="hce-kpi"><small>Implantação prevista</small><strong><?= !empty($latest['previsao_implantacao_hc']) ? e(date('d/m/Y', strtotime((string)$latest['previsao_implantacao_hc']))) : '-' ?></strong></div>
        <div class="hce-kpi"><small>Fornecedor</small><strong><?= e($latest['fornecedor_hc'] ?? '-') ?></strong></div>
    </div>

    <div class="hce-layout">
        <section class="hce-card">
            <div class="hce-card__head">
                <h2>Nova avaliação / atualização</h2>
                <span class="hce-sub">Preencha a avaliação NEAD e o plano de implantação do cuidado domiciliar.</span>
            </div>
            <div class="hce-card__body">
                <form method="post" action="<?= $BASE_URL ?>process_home_care.php">
                    <input type="hidden" name="_csrf" value="<?= e($_SESSION['csrf'] ?? '') ?>">
                    <input type="hidden" name="fk_internacao_hc" value="<?= (int)$internacaoId ?>">

                    <h3 class="hce-section-title">Governança do caso</h3>
                    <div class="hce-form-grid">
                        <div class="hce-field">
                            <label>Status do caso</label>
                            <select name="status_hc">
                                <option value="">Selecione</option>
                                <?php foreach ($statusOptions as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= (($latest['status_hc'] ?? '') === $key) ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="hce-field">
                            <label>Barreira principal</label>
                            <select name="barreira_principal_hc">
                                <option value="">Selecione</option>
                                <?php foreach ($barreiraOptions as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= (($latest['barreira_principal_hc'] ?? '') === $key) ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="hce-field">
                            <label>Fornecedor sugerido</label>
                            <input type="text" name="fornecedor_hc" value="<?= e($latest['fornecedor_hc'] ?? '') ?>" placeholder="Ex.: empresa de atenção domiciliar">
                        </div>
                        <div class="hce-field">
                            <label>Modalidade aprovada</label>
                            <select name="modalidade_aprovada_hc">
                                <option value="">Não definida</option>
                                <?php foreach ($modalidadeOptions as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= (($latest['modalidade_aprovada_hc'] ?? '') === $key) ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="hce-field">
                            <label>Data da visita domiciliar</label>
                            <input type="date" name="data_visita_domiciliar_hc" value="<?= e($latest['data_visita_domiciliar_hc'] ?? '') ?>">
                        </div>
                        <div class="hce-field">
                            <label>Previsão de implantação</label>
                            <input type="date" name="previsao_implantacao_hc" value="<?= e($latest['previsao_implantacao_hc'] ?? '') ?>">
                        </div>
                    </div>

                    <h3 class="hce-section-title" style="margin-top:18px;">Tabela NEAD - Grupo 1 (elegibilidade)</h3>
                    <div class="hce-checkgrid">
                        <label class="hce-check"><input type="checkbox" name="nead_grupo1_cuidador_hc" value="s" <?= (($latest['nead_grupo1_cuidador_hc'] ?? '') === 's') ? 'checked' : '' ?>><span>Cuidador integral disponível</span></label>
                        <label class="hce-check"><input type="checkbox" name="nead_grupo1_ambiente_hc" value="s" <?= (($latest['nead_grupo1_ambiente_hc'] ?? '') === 's') ? 'checked' : '' ?>><span>Domicílio com condições mínimas</span></label>
                        <label class="hce-check"><input type="checkbox" name="nead_grupo1_locomocao_hc" value="s" <?= (($latest['nead_grupo1_locomocao_hc'] ?? '') === 's') ? 'checked' : '' ?>><span>Dificuldade / impossibilidade de locomoção para cuidado convencional</span></label>
                    </div>

                    <h3 class="hce-section-title" style="margin-top:18px;">Tabela NEAD - Grupo 2 (indicação imediata)</h3>
                    <div class="hce-checkgrid">
                        <label class="hce-check"><input type="checkbox" name="nead_grupo2_vm_hc" value="s" <?= (($latest['nead_grupo2_vm_hc'] ?? '') === 's') ? 'checked' : '' ?>><span>Ventilação mecânica / suporte ventilatório complexo</span></label>
                        <label class="hce-check"><input type="checkbox" name="nead_grupo2_aspiracao_hc" value="s" <?= (($latest['nead_grupo2_aspiracao_hc'] ?? '') === 's') ? 'checked' : '' ?>><span>Aspiração frequente / manejo intensivo de vias aéreas</span></label>
                        <label class="hce-check"><input type="checkbox" name="nead_grupo2_medicacao_ev_hc" value="s" <?= (($latest['nead_grupo2_medicacao_ev_hc'] ?? '') === 's') ? 'checked' : '' ?>><span>Medicação endovenosa contínua / recorrente</span></label>
                        <label class="hce-check"><input type="checkbox" name="nead_grupo2_dieta_parenteral_hc" value="s" <?= (($latest['nead_grupo2_dieta_parenteral_hc'] ?? '') === 's') ? 'checked' : '' ?>><span>Dieta parenteral</span></label>
                        <label class="hce-check"><input type="checkbox" name="nead_grupo2_lesao_complexa_hc" value="s" <?= (($latest['nead_grupo2_lesao_complexa_hc'] ?? '') === 's') ? 'checked' : '' ?>><span>Lesão complexa / curativo avançado intensivo</span></label>
                    </div>

                    <h3 class="hce-section-title" style="margin-top:18px;">Tabela NEAD - Grupo 3 (pontuação de apoio)</h3>
                    <div class="hce-scoregrid">
                        <div class="hce-field">
                            <label>Katz / dependência funcional</label>
                            <select name="nead_grupo3_katz_hc">
                                <option value="0" <?= ((int)($latest['nead_grupo3_katz_hc'] ?? 0) === 0) ? 'selected' : '' ?>>0</option>
                                <option value="1" <?= ((int)($latest['nead_grupo3_katz_hc'] ?? 0) === 1) ? 'selected' : '' ?>>1</option>
                                <option value="2" <?= ((int)($latest['nead_grupo3_katz_hc'] ?? 0) === 2) ? 'selected' : '' ?>>2</option>
                            </select>
                        </div>
                        <div class="hce-field">
                            <label>Nutrição enteral / suporte alimentar</label>
                            <select name="nead_grupo3_enteral_hc">
                                <option value="0" <?= ((int)($latest['nead_grupo3_enteral_hc'] ?? 0) === 0) ? 'selected' : '' ?>>0</option>
                                <option value="1" <?= ((int)($latest['nead_grupo3_enteral_hc'] ?? 0) === 1) ? 'selected' : '' ?>>1</option>
                                <option value="2" <?= ((int)($latest['nead_grupo3_enteral_hc'] ?? 0) === 2) ? 'selected' : '' ?>>2</option>
                            </select>
                        </div>
                        <div class="hce-field">
                            <label>Oxigenoterapia</label>
                            <select name="nead_grupo3_oxigenio_hc">
                                <option value="0" <?= ((int)($latest['nead_grupo3_oxigenio_hc'] ?? 0) === 0) ? 'selected' : '' ?>>0</option>
                                <option value="1" <?= ((int)($latest['nead_grupo3_oxigenio_hc'] ?? 0) === 1) ? 'selected' : '' ?>>1</option>
                                <option value="2" <?= ((int)($latest['nead_grupo3_oxigenio_hc'] ?? 0) === 2) ? 'selected' : '' ?>>2</option>
                            </select>
                        </div>
                        <div class="hce-field">
                            <label>Traqueostomia / cânula</label>
                            <select name="nead_grupo3_traqueostomia_hc">
                                <option value="0" <?= ((int)($latest['nead_grupo3_traqueostomia_hc'] ?? 0) === 0) ? 'selected' : '' ?>>0</option>
                                <option value="1" <?= ((int)($latest['nead_grupo3_traqueostomia_hc'] ?? 0) === 1) ? 'selected' : '' ?>>1</option>
                                <option value="2" <?= ((int)($latest['nead_grupo3_traqueostomia_hc'] ?? 0) === 2) ? 'selected' : '' ?>>2</option>
                            </select>
                        </div>
                        <div class="hce-field">
                            <label>Diálise / terapia complexa recorrente</label>
                            <select name="nead_grupo3_dialise_hc">
                                <option value="0" <?= ((int)($latest['nead_grupo3_dialise_hc'] ?? 0) === 0) ? 'selected' : '' ?>>0</option>
                                <option value="1" <?= ((int)($latest['nead_grupo3_dialise_hc'] ?? 0) === 1) ? 'selected' : '' ?>>1</option>
                                <option value="2" <?= ((int)($latest['nead_grupo3_dialise_hc'] ?? 0) === 2) ? 'selected' : '' ?>>2</option>
                            </select>
                        </div>
                    </div>

                    <h3 class="hce-section-title" style="margin-top:18px;">Plano de transição e custos</h3>
                    <div class="hce-form-grid">
                        <div class="hce-field">
                            <label>Custo hospitalar / dia</label>
                            <input type="text" name="custo_hospital_dia_hc" value="<?= e($latest['custo_hospital_dia_hc'] ?? '') ?>" placeholder="Ex.: 2500,00">
                        </div>
                        <div class="hce-field">
                            <label>Custo home care / dia</label>
                            <input type="text" name="custo_home_care_dia_hc" value="<?= e($latest['custo_home_care_dia_hc'] ?? '') ?>" placeholder="Ex.: 980,00">
                        </div>
                        <div class="hce-field">
                            <label>Potencial de economia</label>
                            <input type="text" name="potencial_economia_hc" value="<?= e($latest['potencial_economia_hc'] ?? '') ?>" placeholder="Ex.: 1520,00">
                        </div>
                        <div class="hce-field">
                            <label>Equipamentos necessários</label>
                            <input type="text" name="equipamentos_hc" value="<?= e($latest['equipamentos_hc'] ?? '') ?>" placeholder="Ex.: cama hospitalar, O2, concentrador, bomba">
                        </div>
                    </div>

                    <div class="hce-form-grid hce-form-grid--full" style="margin-top:14px;">
                        <div class="hce-field">
                            <label>Plano de transição</label>
                            <textarea name="plano_transicao_hc" placeholder="Plano assistencial e operacional para implantação do Home Care"><?= e($latest['plano_transicao_hc'] ?? '') ?></textarea>
                        </div>
                        <div class="hce-field">
                            <label>Pendência com família</label>
                            <textarea name="pendencia_familia_hc" placeholder="Ex.: aceitar modalidade, organizar cuidador, adequação do domicílio"><?= e($latest['pendencia_familia_hc'] ?? '') ?></textarea>
                        </div>
                        <div class="hce-field">
                            <label>Pendência com hospital</label>
                            <textarea name="pendencia_hospital_hc" placeholder="Ex.: relatório, prescrição, estabilidade clínica"><?= e($latest['pendencia_hospital_hc'] ?? '') ?></textarea>
                        </div>
                        <div class="hce-field">
                            <label>Pendência com operadora</label>
                            <textarea name="pendencia_operadora_hc" placeholder="Ex.: autorização, fornecedor, aditivo contratual"><?= e($latest['pendencia_operadora_hc'] ?? '') ?></textarea>
                        </div>
                        <div class="hce-field">
                            <label>Observações</label>
                            <textarea name="observacoes_hc" placeholder="Observações clínicas e gerenciais relevantes"><?= e($latest['observacoes_hc'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="hce-actions">
                        <button class="hce-submit" type="submit">Salvar avaliação</button>
                        <a class="hce-secondary" href="<?= $BASE_URL ?>home_care_gestao.php">Voltar à fila</a>
                    </div>
                </form>
            </div>
        </section>

        <aside class="hce-card">
            <div class="hce-card__head">
                <h2>Histórico do caso</h2>
                <span class="hce-sub"><?= number_format(count($history), 0, ',', '.') ?> atualização(ões)</span>
            </div>
            <div class="hce-card__body">
                <?php if (!$history): ?>
                    <div class="hce-sub">Nenhuma avaliação registrada ainda.</div>
                <?php else: ?>
                    <div class="hce-history">
                        <?php foreach ($history as $item): ?>
                            <div class="hce-history-card">
                                <div class="hce-history-head">
                                    <div>
                                        <div><strong><?= e($statusOptions[$item['status_hc']] ?? 'Sem status') ?></strong></div>
                                        <div class="hce-sub"><?= !empty($item['data_atualizacao_hc']) ? e(date('d/m/Y H:i', strtotime((string)$item['data_atualizacao_hc']))) : '-' ?> · <?= e($item['usuario_user'] ?? 'Sistema') ?></div>
                                    </div>
                                    <div class="hce-chip <?= (($item['nead_elegivel_hc'] ?? 'n') === 's') ? 'hce-chip--ok' : 'hce-chip--warn' ?>">
                                        <?= (($item['nead_elegivel_hc'] ?? 'n') === 's') ? 'Elegível' : 'Pendente' ?>
                                    </div>
                                </div>
                                <div class="hce-sub">NEAD: <?= e($item['nead_classificacao_hc'] ?? 'Sem classificação') ?> · score <?= number_format((int)($item['nead_pontuacao_hc'] ?? 0), 0, ',', '.') ?></div>
                                <?php if (!empty($item['modalidade_sugerida_hc'])): ?>
                                    <div class="hce-sub">Modalidade sugerida: <?= e($modalidadeOptions[$item['modalidade_sugerida_hc']] ?? $item['modalidade_sugerida_hc']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item['barreira_principal_hc'])): ?>
                                    <div class="hce-sub">Barreira: <?= e($barreiraOptions[$item['barreira_principal_hc']] ?? $item['barreira_principal_hc']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item['plano_transicao_hc'])): ?>
                                    <div style="margin-top:8px; color:#30445f; font-size:.84rem;"><?= nl2br(e($item['plano_transicao_hc'])) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>

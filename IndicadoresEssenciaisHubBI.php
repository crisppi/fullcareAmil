<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!function_exists('e')) {
    function e($v)
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$items = [
    ['slug' => 'contas-auditadas-hospital', 'label' => 'Contas auditadas por hospital', 'target' => 'bi/financeiro-realizado'],
    ['slug' => 'custo-mensal-hospital', 'label' => 'Custo mensal por hospital', 'target' => 'bi/gastos-hospital'],
    ['slug' => 'glosa-hospital', 'label' => 'Glosa por hospital', 'target' => 'bi/rede-glosa'],
    ['slug' => 'contas-auditadas-auditor', 'label' => 'Contas auditadas por auditor', 'target' => 'IndicadorEssencialAuditorBI.php?modo=contas'],
    ['slug' => 'glosa-auditor', 'label' => 'Glosa por auditor', 'target' => 'IndicadorEssencialAuditorBI.php?modo=glosa'],
    ['slug' => 'saving-hospital', 'label' => 'Saving por hospital', 'target' => 'bi/saving'],
    ['slug' => 'saving-auditor', 'label' => 'Saving por auditor', 'target' => 'bi/saving-por-auditor'],
    ['slug' => 'custo-patologia', 'label' => 'Custo por patologia', 'target' => 'bi/gastos-patologia'],
    ['slug' => 'custo-antecedente', 'label' => 'Custo por antecedente', 'target' => 'bi/antecedente'],
    ['slug' => 'custo-uti', 'label' => 'Custo por UTI', 'target' => 'IndicadorEssencialUtiBI.php?modo=custo'],
    ['slug' => 'percentual-internacao-uti', 'label' => '% internacao UTI', 'target' => 'IndicadorEssencialUtiBI.php?modo=percentual'],
    ['slug' => 'eventos-adversos-hospital', 'label' => 'Eventos adversos por hospital', 'target' => 'bi/rede-eventos-adversos'],
    ['slug' => 'obitos-hospital', 'label' => 'Obitos por hospital', 'target' => 'bi/qualidade-obitos'],
    ['slug' => 'qualidade-hospital', 'label' => 'Qualidade hospitalar', 'target' => 'bi/rede-comparativa'],
];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme bi-ie-hub">
    <div class="bi-header">
        <h1 class="bi-title">Indicadores Essenciais</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Uma pagina por indicador</div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao"><i class="bi bi-grid-3x3-gap"></i></a>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Selecione o indicador</h3>
        <div class="ie-grid">
            <?php foreach ($items as $item): ?>
                <a class="ie-card" href="<?= $BASE_URL ?>bi/indicadores-essenciais/<?= e($item['slug']) ?>">
                    <strong><?= e($item['label']) ?></strong>
                    <small>Abrir indicador</small>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.bi-ie-hub .ie-grid { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px; }
.bi-ie-hub .ie-card { display:flex; flex-direction:column; gap:6px; padding:14px; border-radius:12px; border:1px solid rgba(130,164,196,.35); background:rgba(22,68,108,.34); color:#eaf6ff; text-decoration:none; }
.bi-ie-hub .ie-card small { color:#9fc6df; }
.bi-ie-hub .ie-card:hover { transform: translateY(-1px); border-color: rgba(144,199,241,.75); }
@media (max-width: 980px) { .bi-ie-hub .ie-grid { grid-template-columns: 1fr; } }
</style>

<?php require_once("templates/footer.php"); ?>

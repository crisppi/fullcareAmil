<?php
$clearUrl = $clearUrl ?? 'bi/rede-comparativa';
?>
<form class="bi-panel bi-filters bi-filters-wrap bi-filters-compact" method="get">
    <div class="bi-filter">
        <label>Data inicial</label>
        <input type="date" name="data_ini" value="<?= e($dataIni) ?>">
    </div>
    <div class="bi-filter">
        <label>Data final</label>
        <input type="date" name="data_fim" value="<?= e($dataFim) ?>">
    </div>
    <div class="bi-filter">
        <label>Hospital</label>
        <select name="hospital_id">
            <option value="">Todos</option>
            <?php foreach ($hospitais as $h): ?>
                <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                    <?= e($h['nome_hosp']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="bi-filter">
        <label>Seguradora</label>
        <?php if (!empty($isSeguradoraRole)): ?>
            <input type="hidden" name="seguradora_id" value="<?= (int)$seguradoraId ?>">
            <input type="text" value="<?= e($seguradoras[0]['seguradora_seg'] ?? 'Minha seguradora') ?>" readonly>
        <?php else: ?>
            <select name="seguradora_id">
                <option value="">Todas</option>
                <?php foreach ($seguradoras as $s): ?>
                    <option value="<?= (int)$s['id_seguradora'] ?>" <?= $seguradoraId == $s['id_seguradora'] ? 'selected' : '' ?>>
                        <?= e($s['seguradora_seg']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>
    <div class="bi-filter">
        <label>Região</label>
        <select name="regiao">
            <option value="">Todas</option>
            <?php foreach ($regioes as $reg): ?>
                <option value="<?= e($reg) ?>" <?= $regiao === $reg ? 'selected' : '' ?>>
                    <?= e($reg) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="bi-filter">
        <label>Tipo de admissão</label>
        <select name="tipo_admissao">
            <option value="">Todos</option>
            <?php foreach ($tiposAdm as $tipo): ?>
                <?php if ($tipo === null || $tipo === '') continue; ?>
                <option value="<?= e($tipo) ?>" <?= $tipoAdmissao === $tipo ? 'selected' : '' ?>>
                    <?= e($tipo) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="bi-filter">
        <label>Modo de internação</label>
        <select name="modo_internacao">
            <option value="">Todos</option>
            <?php foreach ($modosInt as $modo): ?>
                <?php if ($modo === null || $modo === '') continue; ?>
                <option value="<?= e($modo) ?>" <?= $modoInternacao === $modo ? 'selected' : '' ?>>
                    <?= e($modo) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="bi-filter">
        <label>UTI</label>
        <select name="uti">
            <option value="">Todos</option>
            <option value="s" <?= $uti === 's' ? 'selected' : '' ?>>Sim</option>
            <option value="n" <?= $uti === 'n' ? 'selected' : '' ?>>Não</option>
        </select>
    </div>
    <div class="bi-actions">
        <button class="bi-btn" type="submit">Aplicar filtros</button>
        <a class="bi-btn bi-btn-secondary bi-btn-reset" href="<?= $BASE_URL . $clearUrl ?>">Limpar</a>
    </div>
</form>

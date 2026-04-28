<?php
/*--------------------------------------------------------------
 *  BLOCO “GESTÃO” – EDIÇÃO
 *-------------------------------------------------------------*/

/* 1) OBJETO $int_gestao VEM DO DAO
 *    Se não havia registro ele chega como new gestao() – possivelmente com
 *    propriedades = null. Garantimos valores padrão para todas as flags.
 */
$camposFlag = [
    'select_gestao',
    'alto_custo_ges',
    'home_care_ges',
    'opme_ges',
    'desospitalizacao_ges',
    'evento_adverso_ges',
    'evento_sinalizado_ges',
    'evento_discutido_ges',
    'evento_negociado_ges',
    'evento_prorrogar_ges',
    'evento_fech_ges'
];
foreach ($camposFlag as $f) {
    $int_gestao->$f = $int_gestao->$f ?? 'n';
}
$int_gestao->tipo_evento_adverso_gest = $int_gestao->tipo_evento_adverso_gest ?? '';
$int_gestao->evento_valor_negoc_ges = $int_gestao->evento_valor_negoc_ges ?? '';
/* 2) HELPERS TOLERANTES A null */
function sel(?string $cur, string $val): string
{
    return ($cur ?? '') === $val ? 'selected' : '';
}
function showIf(?string $cur): string
{
    return ($cur ?? '') === 's' ? 'block' : 'none';
}

$showGestaoContainer = isset($activeEditSection) && $activeEditSection === 'gestao';
?>

<!-- --------------------- HTML / BOOTSTRAP --------------------------- -->
<div id="container-gestao" style="display:<?= $showGestaoContainer ? 'block' : 'none' ?>; margin:5px;">

    <!-- FK internação -->
    <input type="hidden" name="fk_internacao_ges" value="<?= $intern['id_internacao'] ?>">
    <input type="hidden" name="id_gestao" value="<?= $int_gestao->id_gestao ?>">
    <input type="hidden" name="type" value="edit_gestao">
    <!-- ========== ALTO CUSTO ======================================== -->
    <div class="form-group row">
        <div class="form-group col-sm-2">
            <label for="alto_custo_ges">Alto Custo</label>
            <select class="form-control-sm form-control" id="alto_custo_ges" name="alto_custo_ges">
                <option value="n" <?= sel($int_gestao->alto_custo_ges, 'n') ?>>Não</option>
                <option value="s" <?= sel($int_gestao->alto_custo_ges, 's') ?>>Sim</option>
            </select>
        </div>
        <div class="form-group col-sm-10" id="div_rel_alto_custo"
            style="display:<?= showIf($int_gestao->alto_custo_ges) ?>">
            <label for="rel_alto_custo_ges">Relatório alto custo</label>
            <textarea rows="2" class="form-control" id="rel_alto_custo_ges" name="rel_alto_custo_ges"
                onfocus="this.rows=6"
                onblur="this.rows=2"><?= htmlspecialchars((string)($int_gestao->rel_alto_custo_ges ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>


    <!-- ========== HOME CARE ========================================= -->
    <div class="form-group row">
        <div class="form-group col-sm-2">
            <label for="home_care_ges">Home care</label>
            <select class="form-control-sm form-control" id="home_care_ges" name="home_care_ges">
                <option value="n" <?= sel($int_gestao->home_care_ges, 'n') ?>>Não</option>
                <option value="s" <?= sel($int_gestao->home_care_ges, 's') ?>>Sim</option>
            </select>
        </div>
        <div class="form-group col-sm-10" id="div_rel_home_care"
            style="display:<?= showIf($int_gestao->home_care_ges) ?>">
            <label for="rel_home_care_ges">Relatório Home care</label>
            <textarea rows="2" class="form-control" id="rel_home_care_ges" name="rel_home_care_ges"
                onfocus="this.rows=6"
                onblur="this.rows=2"><?= htmlspecialchars((string)($int_gestao->rel_home_care_ges ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>


    <!-- ========== OPME ============================================== -->
    <div class="form-group row">
        <div class="form-group col-sm-2">
            <label for="opme_ges">OPME</label>
            <select class="form-control-sm form-control" id="opme_ges" name="opme_ges">
                <option value="n" <?= sel($int_gestao->opme_ges, 'n') ?>>Não</option>
                <option value="s" <?= sel($int_gestao->opme_ges, 's') ?>>Sim</option>
            </select>
        </div>
        <div class="form-group col-sm-10" id="div_rel_opme" style="display:<?= showIf($int_gestao->opme_ges) ?>">
            <label for="rel_opme_ges">Relatório OPME</label>
            <textarea rows="2" class="form-control" id="rel_opme_ges" name="rel_opme_ges" onfocus="this.rows=6"
                onblur="this.rows=2"><?= htmlspecialchars((string)($int_gestao->rel_opme_ges ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>


    <!-- ========== DESOSPITALIZAÇÃO ================================== -->
    <div class="form-group row">
        <div class="form-group col-sm-2">
            <label for="desospitalizacao_ges">Desospitalização</label>
            <select class="form-control-sm form-control" id="desospitalizacao_ges" name="desospitalizacao_ges">
                <option value="n" <?= sel($int_gestao->desospitalizacao_ges, 'n') ?>>Não</option>
                <option value="s" <?= sel($int_gestao->desospitalizacao_ges, 's') ?>>Sim</option>
            </select>
        </div>
        <div class="form-group col-sm-10" id="div_rel_desospitalizacao"
            style="display:<?= showIf($int_gestao->desospitalizacao_ges) ?>">
            <label for="rel_desospitalizacao_ges">Relatório Desospitalização</label>
            <textarea rows="2" class="form-control" id="rel_desospitalizacao_ges" name="rel_desospitalizacao_ges"
                onfocus="this.rows=6"
                onblur="this.rows=2"><?= htmlspecialchars((string)($int_gestao->rel_desospitalizacao_ges ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
    <hr>

    <!-- ========== EVENTO ADVERSO ==================================== -->
    <div class="form-group row">
        <div class="form-group col-sm-2">
            <label for="evento_adverso_ges">Evento Adverso</label>
            <select class="form-control-sm form-control" id="evento_adverso_ges" name="evento_adverso_ges">
                <option value="n" <?= sel($int_gestao->evento_adverso_ges, 'n') ?>>Não</option>
                <option value="s" <?= sel($int_gestao->evento_adverso_ges, 's') ?>>Sim</option>
            </select>
        </div>

        <div id="div_evento" class="col-sm-10" style="display:<?= showIf($int_gestao->evento_adverso_ges) ?>">

            <!-- Tipo de evento adverso -->
            <div class="form-group">
                <label for="tipo_evento_adverso_gest">Tipo de Evento Adverso</label>
                <select class="form-control-sm form-control" id="tipo_evento_adverso_gest"
                    name="tipo_evento_adverso_gest">
                    <?php
                    sort($dados_tipo_evento, SORT_NATURAL | SORT_FLAG_CASE);
                    foreach ($dados_tipo_evento as $ev) {
                        echo '<option value="' . htmlspecialchars($ev) . '" '
                            . sel($int_gestao->tipo_evento_adverso_gest, $ev)
                            . '>' . htmlspecialchars($ev) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Relatório evento adverso -->
            <div class="form-group">
                <label for="rel_evento_adverso_ges">Relatório Evento Adverso</label>
                <textarea rows="2" class="form-control" id="rel_evento_adverso_ges" name="rel_evento_adverso_ges"
                    onfocus="this.rows=6"
                    onblur="this.rows=2"><?= htmlspecialchars((string)($int_gestao->rel_evento_adverso_ges ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <!-- Flags + Valor negociado -->
            <div class="form-group row">
                <?php
                $flags = [
                    'evento_sinalizado_ges' => 'Evento sinalizado',
                    'evento_discutido_ges' => 'Evento discutido',
                    'evento_negociado_ges' => 'Evento negociado',
                    'evento_prorrogar_ges' => 'Seguir prorrogação',
                    'evento_fech_ges' => 'Fechar conta'
                ];
                foreach ($flags as $f => $lbl) { ?>
                    <div class="form-group col-sm-2">
                        <label for="<?= $f ?>">
                            <?= $lbl ?>
                            <?php if ($f === 'evento_prorrogar_ges'): ?>
                                <span class="assist-anchor" data-assist-key="prorrogacao"></span>
                            <?php endif; ?>
                        </label>
                        <select class="form-control-sm form-control" id="<?= $f ?>" name="<?= $f ?>">
                            <option value="n" <?= sel($int_gestao->$f, 'n') ?>>Não</option>
                            <option value="s" <?= sel($int_gestao->$f, 's') ?>>Sim</option>
                        </select>
                    </div>
                <?php } ?>

                <div class="form-group col-sm-2">
                    <label for="evento_valor_negoc_ges">Valor negociado</label>
                    <input type="text" class="form-control form-control-sm" id="evento_valor_negoc_ges"
                        name="evento_valor_negoc_ges"
                        value="<?= htmlspecialchars((string)($int_gestao->evento_valor_negoc_ges ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
        </div>
    </div>
    <hr>
</div> <!-- /container-gestao -->

<!-- --------------------- JS: mostrar/ocultar ------------------------ -->
<script>
    (function () {
        const toggles = [{
            s: '#alto_custo_ges',
            d: '#div_rel_alto_custo'
        },
        {
            s: '#home_care_ges',
            d: '#div_rel_home_care'
        },
        {
            s: '#opme_ges',
            d: '#div_rel_opme'
        },
        {
            s: '#desospitalizacao_ges',
            d: '#div_rel_desospitalizacao'
        },
        {
            s: '#evento_adverso_ges',
            d: '#div_evento'
        }
        ];
        toggles.forEach(t => {
            const sel = document.querySelector(t.s);
            const div = document.querySelector(t.d);
            sel.addEventListener('change', () => {
                div.style.display = (sel.value === 's') ? 'block' : 'none';
            });
        });
    })();
</script>

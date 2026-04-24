<div id="container-prorrog" style="display:none; margin:5px">
    <style>
        .prorrog-ia-box {
            margin: 18px 0;
            padding: 14px;
            border-radius: 16px;
            border: 1px solid #bfdbfe;
            background: linear-gradient(135deg, #eff6ff 0%, #eef2ff 48%, #f8fafc 100%);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.9), 0 14px 30px rgba(37,99,235,.08);
        }
        .prorrog-ia-box__header {
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:14px;
            flex-wrap:wrap;
            margin-bottom:12px;
        }
        .prorrog-ia-box__eyebrow {
            margin:0 0 2px;
            font-size:.72rem;
            font-weight:800;
            letter-spacing:.14em;
            text-transform:uppercase;
            color:#1d4ed8;
        }
        .prorrog-ia-box__title {
            margin:0;
            font-size:1.08rem;
            font-weight:800;
            color:#0f172a;
        }
        .prorrog-ia-powered {
            display:inline-flex;
            align-items:center;
            gap:6px;
            min-height:28px;
            padding:4px 10px;
            border-radius:999px;
            background:rgba(255,255,255,.78);
            border:1px solid rgba(99,102,241,.22);
            color:#1d4ed8;
            font-size:.76rem;
            font-weight:800;
            letter-spacing:.03em;
            text-transform:uppercase;
        }
        .prorrog-ia-card {
            border:1px solid #c7d2fe;
            border-radius:12px;
            background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);
            overflow:hidden;
            box-shadow:0 12px 28px rgba(37,99,235,.10);
        }
        .prorrog-ia-card__header {
            min-height:44px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            padding:8px 12px;
            background:linear-gradient(135deg,#dbeafe 0%,#eef2ff 50%,#ecfeff 100%);
            border-bottom:1px solid #c7d2fe;
        }
        .prorrog-ia-card__body { padding:12px; }
        .prorrog-ia-toggle {
            width:32px;height:32px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#374151;
            display:inline-flex;align-items:center;justify-content:center;
        }
        .prorrog-ia-status { margin:10px 12px 0;padding:8px 10px;border-radius:8px;font-weight:700;font-size:.88rem; }
        .prorrog-ia-status--info { background:#e0f2fe;color:#075985; }
        .prorrog-ia-status--success { background:#dcfce7;color:#166534; }
        .prorrog-ia-status--error { background:#fee2e2;color:#991b1b; }
        .prorrog-ia-badge { display:inline-flex;align-items:center;min-height:28px;padding:4px 10px;border-radius:999px;font-size:.78rem;font-weight:800;letter-spacing:.04em; }
        .prorrog-ia-badge--ok { background:#dcfce7;color:#166534; }
        .prorrog-ia-badge--info { background:#dbeafe;color:#1d4ed8; }
        .prorrog-ia-badge--warn { background:#ffedd5;color:#c2410c; }
        .prorrog-ia-badge--danger { background:#fee2e2;color:#b91c1c; }
        .prorrog-ia-badge--neutral { background:#fef3c7;color:#92400e; }
        .prorrog-ia-section { margin-top:10px;color:#1f2937; }
        .prorrog-ia-section p, .prorrog-ia-section ul { margin:4px 0 0; }
        .prorrog-ia-section ul { padding-left:18px; }
        .prorrog-ia-empty { margin:0;color:#6b7280; }
        .prorrog-ia-final-alert { margin-top:14px;padding:12px 14px;border-radius:10px;border:1px solid #fecaca;background:linear-gradient(135deg,#fff1f2,#fee2e2);color:#b91c1c;font-weight:800;line-height:1.4; }
    </style>
    <hr>
    <h6 class="page-title">Cadastrar dados de prorrogação</h6>
    <input type="hidden" name="type" value="create">
    <div class="form-group row">
        <div class="form-group col-sm-1">
            <input type="hidden" class="form-control" readonly id="fk_internacao_pror" name="fk_internacao_pror"
                value="<?= $id_internacao ?>">
        </div>
        <div class="form-group col-sm-1">
            <input type="hidden" class="form-control" readonly id="fk_visita_pror" name="fk_visita_pror"
                value="<?= $visitaMax['0']['id_visita']; ?>">
        </div>
    </div>
    <div>
        <input type="text" hidden readonly class="form-control" id="data_intern_int" name="data_intern_int"
            value="<?= date("Y-m-d", strtotime($internacaoList['0']['data_intern_int'])); ?> ">
    </div> <!-- PRORROGACAO 1 -->
    <div class="form-group row">
        <div class="form-group col-sm-2">
            <label class="control-label" for="acomod1_pror">Acomodação</label>
            <select class="form-control" id="acomod1_pror" name="acomod1_pror">
                <option value=""> </option>
                <?php sort($dados_acomodacao, SORT_ASC);
                foreach ($dados_acomodacao as $acomd) { ?>
                <option value="<?= $acomd; ?>"><?= $acomd; ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-group col-sm-2">
            <label class="control-label" for="prorrog1_ini_pror">Data inicial</label>
            <input type="date" class="form-control" id="prorrog1_ini_pror" name="prorrog1_ini_pror">
            <div class="notif-input oculto" id="notif-input1">
                Data inválida !
            </div>
        </div>
        <div class="form-group col-sm-2">
            <label class="control-label" for="prorrog1_fim_pror">Data final</label>
            <input type="date" class="form-control" id="prorrog1_fim_pror" name="prorrog1_fim_pror">
            <div class="notif-input oculto" id="notif-input2">
                Data inválida !
            </div>
        </div>
        <div id="div_diarias_1" class="form-group col-sm-1" style="display:none">
            <label class="control-label" for="diarias_1">Diárias </label>
            <input type="text" style="text-align:center; font-weight:600; background-color:darkgray" readonly
                class="form-control" id="diarias_1" name="diarias_1">
        </div>
        <div class="form-group col-sm-2">
            <label class="control-label" for="isol_1_pror">Isolamento</label>
            <select class="form-control" id="isol_1_pror" name="isol_1_pror">
                <option value="n">Não</option>
                <option value="s">Sim</option>
            </select>
        </div>
        <div class="form-group col-sm-1">
            <label for="adic1">Adicionar</label><br>
            <input style="margin-left:30px" type="checkbox" id="adic1" name="adic1" value="adic1">
        </div>
    </div>
    <!-- PRORROGACAO 2  -->
    <div style="display:none" id="div-prorrog2" class="form-group row">
        <div class="form-group col-sm-2">
            <label class="control-label" for="acomod2_pror">2ª Acomodação</label>
            <select class="form-control" id="acomod2_pror" name="acomod2_pror">
                <option value=""> </option>
                <?php sort($dados_acomodacao, SORT_ASC);
                foreach ($dados_acomodacao as $acomd) { ?>
                <option value="<?= $acomd; ?>"><?= $acomd; ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-group col-sm-2">
            <label class="control-label" for="prorrog2_ini_pror">Data inicial</label>
            <input type="date" class="form-control" id="prorrog2_ini_pror" name="prorrog2_ini_pror">
            <div class="notif-input oculto" id="notif-input3">
                Data inválida !
            </div>
        </div>
        <div class="form-group col-sm-2">
            <label class="control-label" for="prorrog2_fim_pror">Data final</label>
            <input type="date" class="form-control" id="prorrog2_fim_pror" name="prorrog2_fim_pror">
            <div class="notif-input oculto" id="notif-input4">
                Data inválida !
            </div>
        </div>
        <div id="div_diarias_2" class="form-group col-sm-1" style="display:none">
            <label class="control-label" for="diarias_2">Diárias </label>
            <input type="text" style="text-align:center; font-weight:600; background-color:darkgray" readonly
                class="form-control" id="diarias_2" name="diarias_2">
        </div>
        <div class="form-group col-sm-2">
            <label class="control-label" for="isol_2_pror">Isolamento</label>
            <select class="form-control" id="isol_2_pror" name="isol_2_pror">
                <option value="n">Não</option>
                <option value="s">Sim</option>
            </select>
        </div>
        <div class="form-group col-sm-1">
            <label for="adic2">Adicionar</label><br>
            <input style="margin-left:30px" type="checkbox" id="adic2" name="adic2" value="adic2">
        </div>
    </div>
    <!-- PRORROGACAO 3 -->
    <div style="display:none" id="div-prorrog3" class="form-group row">
        <div class="form-group col-sm-2">
            <label class="control-label" for="acomod3_pror">3ª Acomodação</label>
            <select class="form-control" id="acomod3_pror" name="acomod3_pror">
                <option value=""> </option>
                <?php sort($dados_acomodacao, SORT_ASC);
                foreach ($dados_acomodacao as $acomd) { ?>
                <option value="<?= $acomd; ?>"><?= $acomd; ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="form-group col-sm-2">
            <label class="control-label" for="prorrog1_ini_pror">Data inicial</label>
            <input type="date" class="form-control" id="prorrog3_ini_pror" name="prorrog3_ini_pror">
            <div class="notif-input oculto" id="notif-input5">
                Data inválida !
            </div>
        </div>

        <div class="form-group col-sm-2">
            <label class="control-label" for="prorrog3_fim_pror">Data final</label>
            <input type="date" class="form-control" id="prorrog3_fim_pror" name="prorrog3_fim_pror">
            <div class="notif-input oculto" id="notif-input6">
                Data inválida !
            </div>
        </div>
        <div id="div_diarias_3" class="form-group col-sm-1" style="display:none">
            <label class="control-label" for="diarias_3">Diárias </label>
            <input type="text" style="text-align:center; font-weight:600; background-color:darkgray" readonly
                class="form-control" id="diarias_3" name="diarias_3">
        </div>
        <div class="form-group col-sm-2">
            <label class="control-label" for="isol_3_pror">Isolamento</label>
            <select class="form-control" id="isol_3_pror" name="isol_3_pror">
                <option value="n">Não</option>
                <option value="s">Sim</option>
            </select>
        </div>
    </div>

    <div class="prorrog-ia-box">
        <div class="prorrog-ia-box__header">
            <div>
                <p class="prorrog-ia-box__eyebrow">Inteligência Artificial</p>
                <h4 class="prorrog-ia-box__title">Parecer para prorrogação assistencial</h4>
            </div>
            <span class="prorrog-ia-powered">
                <i class="bi bi-stars"></i>
                IA conectada
            </span>
        </div>
        <div class="form-group">
            <label for="prorrog-ia-contexto">Contexto complementar</label>
            <textarea class="form-control" id="prorrog-ia-contexto" rows="3" placeholder="Opcional: acrescente observações clínicas, barreiras de alta ou plano de desospitalização."></textarea>
            <small style="display:block;margin-top:6px;color:#475569;font-weight:600;">
                A IA já considera automaticamente o relatório da auditoria, as ações da auditoria e a programação terapêutica desta tela.
            </small>
        </div>
        <div style="margin:10px 0 12px;">
            <button type="button" class="btn btn-primary" id="btn-executar-prorrog-ia">
                <i class="bi bi-cpu"></i>
                Executar IA Prorrogação
            </button>
        </div>
        <div class="prorrog-ia-card">
            <div class="prorrog-ia-card__header">
                <h5 style="margin:0;">Parecer IA de Prorrogação</h5>
                <button type="button" class="prorrog-ia-toggle" id="btn-toggle-prorrog-ia" aria-expanded="false" aria-controls="prorrog-ia-body">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div id="prorrog-ia-status" class="prorrog-ia-status" hidden></div>
            <div class="prorrog-ia-card__body" id="prorrog-ia-body" hidden>
                <div id="prorrog-ia-content">
                    <p class="prorrog-ia-empty">Nenhum parecer gerado.</p>
                </div>
            </div>
        </div>
    </div>

</div>
<script src="js/scriptDataProrVisita.js"></script>
<script>
// Aguarde até que o DOM esteja carregado
$(document).ready(function() {
    // Adicione um ouvinte de mudança ao checkbox button
    $('#adic1').change(function() {
        // Verifique se o checkbox button está marcado
        if ($(this).is(':checked')) {
            // Se estiver marcado, mostre a div
            $('#div-prorrog2').show();
        } else {
            // Se não estiver marcado, oculte a div
            $('#div-prorrog2').hide();
        }
    });
});

$(document).ready(function() {
    // Adicione um ouvinte de mudança ao checkbox button
    $('#adic2').change(function() {
        // Verifique se o checkbox button está marcado
        if ($(this).is(':checked')) {
            // Se estiver marcado, mostre a div
            $('#div-prorrog3').show();
        } else {
            // Se não estiver marcado, oculte a div
            $('#div-prorrog3').hide();
        }
    });
});
</script>
<script>
window.prorrogAiConfig = Object.assign({}, window.prorrogAiConfig || {}, {
    baseUrl: <?= json_encode((string)$BASE_URL, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
});
</script>
<script src="<?= $BASE_URL ?>js/prorrogacao_ai.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

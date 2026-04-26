<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>

<style>
.adicional-card {
    background:#f5f5f9;
    border-radius:22px;
    border:1px solid #ebe1f5;
    box-shadow:0 12px 28px rgba(45,18,70,.08);
    padding:22px 24px;
    margin-top:10px;
}
.adicional-card__header {
    display:flex;
    align-items:center;
    margin-bottom:18px;
}
.adicional-card__title {
    display:flex;
    align-items:center;
    margin:0;
    color:#2f1846;
    font-weight:600;
}
.adicional-card__marker {
    width:6px;
    height:26px;
    border-radius:10px;
    margin-right:12px;
    background:linear-gradient(180deg,#4b9fa4,#7ad0c8);
}

#container-gestao .adicional-card > .form-group.row {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
    align-items: end;
    width: 100%;
}

#container-gestao .adicional-card > .form-group.row > .form-group[class*="col-"] {
    width: 100% !important;
    min-width: 0 !important;
    max-width: none !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-bottom: 0 !important;
}

#container-gestao .adicional-card > .form-group.row > #div_evento,
#container-gestao .adicional-card > .form-group.row > #div_rel_alto_custo,
#container-gestao .adicional-card > .form-group.row > #div_rel_home_care,
#container-gestao .adicional-card > .form-group.row > #div_rel_opme,
#container-gestao .adicional-card > .form-group.row > #div_rel_desospitalizacao {
    grid-column: 1 / -1;
    width: 100% !important;
    min-width: 0 !important;
    max-width: none !important;
}

#container-gestao .adicional-card > .form-group.row > [style*="display:none"] {
    display: none !important;
}

#container-gestao #div_evento > .form-group.row {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 14px;
    align-items: end;
    width: 100%;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

#container-gestao #div_evento > .form-group.row > .form-group[class*="col-"],
#container-gestao #div_evento #div_rel_evento {
    width: 100% !important;
    min-width: 0 !important;
    max-width: none !important;
    flex: none !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-bottom: 0 !important;
}

#container-gestao #div_evento #div_rel_evento {
    grid-column: 1 / -1;
}

#container-gestao .adicional-card .form-control,
#container-gestao .adicional-card .form-control-sm.form-control {
    width: 100% !important;
    min-height: 42px !important;
    height: 42px !important;
}

#container-gestao .adicional-card textarea.form-control {
    min-height: 92px !important;
    height: auto !important;
}

@media (max-width: 768px) {
    #container-gestao .adicional-card > .form-group.row {
        grid-template-columns: 1fr;
    }
}
</style>

<div id="container-gestao" style="display:none; margin:5px">
    <div class="adicional-card">
        <div class="adicional-card__header">
            <h4 class="adicional-card__title">
                <span class="adicional-card__marker"></span>
                Gestão Assistencial
            </h4>
        </div>
    <input type="hidden" name="type" value="create">

    <?php
    $a = ($findMaxGesInt[0]);
    $ultimoReg = ($a["ultimoReg"]) + 1;
    ?>
    <input type="hidden" readonly class="form-control" id="fk_internacao_ges" name="fk_internacao_ges"
        value="<?= ($ultimoReg) ?> ">
    <div class="form-group row">
        <div class="form-group col-sm-2">
            <label for="alto_custo_ges">Alto Custo</label>
            <select class="form-control-sm form-control" id="alto_custo_ges" name="alto_custo_ges">
                <option value="n">Não</option>
                <option value="s">Sim</option>
            </select>
        </div>
        <div style="display:none; border: 1px solid red;margin-top:25px" class="form-group col-sm-5" id="tutorial_alto">
            <p style="font-size:0.8em; font-weight:600; text-align:center;margin-left:-100px">Considerar alto custo</p>
            <p style="font-size:0.8em;text-align:center;margin-left:-100px">Antifúngicos como: Ambisome, Linfotericina,
                Micafungina</p>
            <p style="font-size:0.8em;text-align:center; margin-left:-100px">Imunoglobulinas, Imunobiológicos</p>
        </div>

        <div style="display:none" id="div_rel_alto_custo">
            <label for="rel_alto_custo_ges">Relatório alto custo</label>
            <textarea type="textarea" style="resize:none" rows="2" onclick="aumentarText('rel_alto_custo_ges')"
                onblur="reduzirText('rel_alto_custo_ges', 3)" class="form-control" id="rel_alto_custo_ges"
                name="rel_alto_custo_ges"></textarea>
        </div>
        <div class="form-group col-sm-2">
            <label for="home_care_ges">Home care</label>
            <select class="form-control-sm form-control" id="home_care_ges" name="home_care_ges">
                <option value="n">Não</option>
                <option value="s">Sim</option>
            </select>
        </div>
        <div style="display:none" id="div_rel_home_care">
            <label for="rel_home_care_ges">Relatório Home care</label>
            <textarea type="textarea" style="resize:none" rows="2" onclick="aumentarText('rel_home_care_ges')"
                onblur="reduzirText('rel_home_care_ges', 3)" class="form-control" id="rel_home_care_ges"
                name="rel_home_care_ges"></textarea>
        </div>
        <div class="form-group col-sm-2">
            <label for="opme_ges">OPME</label>
            <select class="form-control-sm form-control" id="opme_ges" name="opme_ges">
                <option value="n">Não</option>
                <option value="s">Sim</option>
            </select>
        </div>
        <div style="display:none" id="div_rel_opme">
            <label for="rel_opme_ges">Relatório OPME</label>
            <textarea type="textarea" style="resize:none" rows="2" onclick="aumentarText('rel_opme_ges')"
                onblur="reduzirText('rel_opme_ges', 3)" class="form-control" id="rel_opme_ges"
                name="rel_opme_ges"></textarea>
        </div>
        <div class="form-group col-sm-2">
            <label for="desospitalizacao_ges">Desospitalização</label>
            <select class="form-control-sm form-control" id="desospitalizacao_ges" name="desospitalizacao_ges">
                <option value="n">Não</option>
                <option value="s">Sim</option>
            </select>
        </div>
        <div style="display:none" id="div_rel_desospitalizacao">
            <label for="rel_desospitalizacao_ges">Relatório Desospitalização</label>
            <textarea type="textarea" style="resize:none" rows="2" onclick="aumentarText('rel_desospitalizacao_ges')"
                onblur="reduzirText('rel_desospitalizacao_ges', 3)" class="form-control" id="rel_desospitalizacao_ges"
                name="rel_desospitalizacao_ges"></textarea>
        </div>

        <div class="form-group col-sm-2">
            <label for="evento_adverso_ges">Evento Adverso</label>
            <select class="form-control-sm form-control" id="evento_adverso_ges" name="evento_adverso_ges">
                <option value="n">Não</option>
                <option value="s">Sim</option>
            </select>
        </div>
        <!-- DIV evento adverso -->
        <div style="display:none" id="div_evento">
            <div class="form-group row">

                <div class="form-group col-sm-2">
                    <label for="tipo_evento_adverso_gest">Tipo Evento Adverso</label>
                    <select class="form-control-sm form-control" id="tipo_evento_adverso_gest"
                        name="tipo_evento_adverso_gest">
                        <?php
                        sort($dados_tipo_evento, SORT_ASC);
                        foreach ($dados_tipo_evento as $evento) { ?>
                        <option value="<?= $evento; ?>"><?= $evento; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group col-sm-2">
                    <label for="evento_sinalizado_ges">Evento sinalizado</label>
                    <select class="form-control-sm form-control" id="evento_sinalizado_ges"
                        name="evento_sinalizado_ges">
                        <option value="n">Não</option>
                        <option value="s">Sim</option>
                    </select>
                </div>
            </div>
            <div class="form-group row">
                <div id="div_rel_evento">
                    <label for="rel_evento_adverso_ges">Relatório Evento Adverso</label>
                    <textarea type="textarea" style="resize:none" rows="2"
                        onclick=" aumentarText('rel_evento_adverso_ges')"
                        onblur="reduzirText('rel_evento_adverso_ges', 3)" class="form-control"
                        id="rel_evento_adverso_ges" name="rel_evento_adverso_ges"></textarea>
                </div>
            </div>
            <div class="form-group row">

                <div class="form-group col-sm-2">
                    <label for="evento_data_ges">Data do Evento</label>
                    <input type="date" class="form-control-sm form-control" id="evento_data_ges" name="evento_data_ges">
                    </input>

                </div>
                <div class="form-group col-sm-2">
                    <label for="evento_classificacao_ges">Como você classifica?</label>
                    <select class="form-control-sm form-control" id="evento_classificacao_ges"
                        name="evento_classificacao_ges">
                        <option value=""></option>
                        <option value="leve">Leve</option>
                        <option value="moderado">Moderado</option>
                        <option value="grave">Grave</option>
                    </select>
                </div>
                <div class="form-group col-sm-2">
                    <label for="evento_discutido_ges">Evento discutido</label>
                    <select class="form-control-sm form-control" id="evento_discutido_ges" name="evento_discutido_ges">
                        <option value="n">Não</option>
                        <option value="s">Sim</option>
                    </select>
                </div>
                <div class="form-group col-sm-2">
                    <label for="evento_negociado_ges">Evento negociado</label>
                    <select class="form-control-sm form-control" id="evento_negociado_ges" name="evento_negociado_ges">
                        <option value="n">Não</option>
                        <option value="s">Sim</option>
                    </select>
                </div>

                <div class="form-group col-sm-2">
                    <label for="evento_valor_negoc_ges">Valor negociado</label>
                    <input type="text" class="form-control dinheiro" id="evento_valor_negoc_ges" value=''
                        name="evento_valor_negoc_ges">
                </div>


            </div>
            <div class="form-group row">
                <div class="form-group col-sm-2">
                    <label for="evento_retorno_qual_hosp_ges">Retorno da Qualidade do Hospital?</label>
                    <select class="form-control-sm form-control" id="evento_retorno_qual_hosp_ges"
                        name="evento_retorno_qual_hosp_ges">
                        <option value="n">Não</option>
                        <option value="s">Sim</option>
                    </select>
                </div>

                <div class="form-group col-sm-2">
                    <label for="evento_encerrar_ges">Encerrar Evento?</label>
                    <select class="form-control-sm form-control" id="evento_encerrar_ges" name="evento_encerrar_ges">
                        <option value="n">Não</option>
                        <option value="s">Sim</option>
                    </select>
                </div>
                <div class="form-group col-sm-2">
                    <label for="evento_impacto_financ_ges">Causou impacto financeiro?</label>
                    <select class="form-control-sm form-control" id="evento_impacto_financ_ges"
                        name="evento_impacto_financ_ges">
                        <option value="n">Não</option>
                        <option value="s">Sim</option>
                    </select>
                </div>
                <div class="form-group col-sm-2">
                    <label for="evento_prolongou_internacao_ges">Prolongou internação?</label>
                    <select class="form-control-sm form-control" id="evento_prolongou_internacao_ges"
                        name="evento_prolongou_internacao_ges">
                        <option value="n">Não</option>
                        <option value="s">Sim</option>
                    </select>
                </div>
                <div class="form-group col-sm-2">
                    <label for="evento_fech_ges">Fechar conta</label>
                    <select class="form-control-sm form-control" id="evento_fech_ges" name="evento_fech_ges">
                        <option value="n">Não</option>
                        <option value="s">Sim</option>
                    </select>
                </div>
                <div class="form-group col-sm-2">
                    <label for="evento_prorrogar_ges">Seguir Prorrogação</label>
                    <select class="form-control-sm form-control" id="evento_prorrogar_ges" name="evento_prorrogar_ges">
                        <option value="n">Não</option>
                        <option value="s">Sim</option>
                    </select>
                </div>

            </div>
        </div>
        </div>
    </div>
</div>


<script src="js/valoresEA.js"></script>

<script type="text/javascript">
// JS PARA APARECER REL EVENTO ADVERSO
var select_evento = document.querySelector('#evento_adverso_ges');

select_evento.addEventListener('change', setEvento);

function setEvento() {
    var choice_evento = select_evento.value;

    if (choice_evento === 's') {
        if (div_evento.style.display === "none") {
            div_evento.style.display = "block";
            // div_tipo_evento.style.display = "block";
        }

    }
    if (choice_evento === 'n') {

        if (div_evento.style.display === "block") {
            div_evento.style.display = "none";
            div_evento.style.display = "none";
        }
    }
}
// JS PARA APARECER REL OPME
var select_opme = document.querySelector('#opme_ges');

select_opme.addEventListener('change', setOpme);

function setOpme() {
    var choice_opme = select_opme.value;

    if (choice_opme === 's') {

        if (div_rel_opme.style.display === "none") {
            div_rel_opme.style.display = "block";
        }

    }
    if (choice_opme === 'n') {

        if (div_rel_opme.style.display === "block") {
            div_rel_opme.style.display = "none";
        }
    }
}
// JS PARA APARECER REL DESOSPITALIZACAO
var select_desospitalizacao = document.querySelector('#desospitalizacao_ges');

select_desospitalizacao.addEventListener('change', setdesospitalizacao);

function setdesospitalizacao() {
    var choice_desospitalizacao = select_desospitalizacao.value;

    if (choice_desospitalizacao === 's') {

        if (div_rel_desospitalizacao.style.display === "none") {
            div_rel_desospitalizacao.style.display = "block";
        }

    }
    if (choice_desospitalizacao === 'n') {

        if (div_rel_desospitalizacao.style.display === "block") {
            div_rel_desospitalizacao.style.display = "none";
        }
    }
}
// JS PARA APARECER REL HOME CARE
var select_home_care = document.querySelector('#home_care_ges');

select_home_care.addEventListener('change', sethome_care);

function sethome_care() {
    var choice_home_care = select_home_care.value;

    if (choice_home_care === 's') {

        if (div_rel_home_care.style.display === "none") {
            div_rel_home_care.style.display = "block";
        }

    }
    if (choice_home_care === 'n') {

        if (div_rel_home_care.style.display === "block") {
            div_rel_home_care.style.display = "none";
        }
    }
}
// JS PARA APARECER REL ALTO CUSTO
// Seleciona o elemento do select
var select_alto_custo = document.querySelector('#alto_custo_ges');
// Seleciona as divs que serão exibidas ou escondidas
var div_rel_alto_custo = document.querySelector('#div_rel_alto_custo');
var div_tutorial_alto = document.querySelector('#tutorial_alto');

// Adiciona o evento de mudança (change) ao select
select_alto_custo.addEventListener('change', setalto_custo);

// Função para exibir ou esconder as divs com base na seleção
function setalto_custo() {
    // Obtém o valor selecionado no select
    var choice_alto_custo = select_alto_custo.value;

    // Verifica o valor e ajusta a exibição das divs
    if (choice_alto_custo === 's') {
        // Exibe as duas divs se o valor for 's' (Sim)
        div_rel_alto_custo.style.display = "block";
        div_tutorial_alto.style.display = "block";
    } else if (choice_alto_custo === 'n') {
        // Esconde as duas divs se o valor for 'n' (Não)
        div_rel_alto_custo.style.display = "none";
        div_tutorial_alto.style.display = "none";
    }
}
</script>

</script>

<script>
function aumentarText(textareaId) {
    document.getElementById(textareaId).rows = 20;
}

function reduzirText(textareaId, originalRows) {
    document.getElementById(textareaId).rows = originalRows;
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

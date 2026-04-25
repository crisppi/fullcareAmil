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
    background:linear-gradient(180deg,#2db6c4,#6be4dc);
}

#container-uti .uti-grid-row {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
    align-items: end;
    width: 100%;
    margin: 0 0 16px !important;
}

#container-uti .uti-grid-row > .form-group[class*="col-"] {
    width: 100% !important;
    min-width: 0 !important;
    max-width: none !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-bottom: 0 !important;
}

#container-uti .uti-grid-row > [style*="display:none"] {
    display: none !important;
}

#container-uti .adicional-card .form-control,
#container-uti .adicional-card .form-control-sm.form-control {
    width: 100% !important;
    min-height: 42px !important;
    height: 42px !important;
}

#container-uti .adicional-card textarea.form-control {
    min-height: 92px !important;
    height: auto !important;
}

#container-uti .uti-report {
    width: 100%;
    max-width: 620px;
    margin-top: 4px;
}

@media (max-width: 768px) {
    #container-uti .uti-grid-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div id="container-uti" style="display:none; margin:5px">
    <div class="adicional-card">
        <div class="adicional-card__header">
            <h4 class="adicional-card__title">
                <span class="adicional-card__marker"></span>
                UTI
            </h4>
        </div>
    <input type="hidden" name="type" value="create">
    <!-- <input type="text" class="form-control" id="id_internacao" name="id_internacao" value="<?= ($ultimoReg) + 1  ?> "> -->

    <!-- DADOS PARA FORMULARIO UTI -->
    <div class="uti-grid-row">
        <?php
            $fkInternacaoUtiValue = 0;
            if (!empty($id_internacao)) {
                $fkInternacaoUtiValue = (int) $id_internacao;
            } elseif (!empty($internacaoList[0]['id_internacao'])) {
                $fkInternacaoUtiValue = (int) $internacaoList[0]['id_internacao'];
            } elseif (isset($ultimoReg)) {
                $fkInternacaoUtiValue = (int) $ultimoReg;
            } elseif (!empty($findMaxUtiInt) && isset($findMaxUtiInt[0]['ultimoReg'])) {
                $fkInternacaoUtiValue = (int) $findMaxUtiInt[0]['ultimoReg'] + 1;
            }
        ?>
        <input type="hidden" class="form-control" readonly id="fk_internacao_uti" name="fk_internacao_uti"
            value="<?= $fkInternacaoUtiValue ?>">
        <input type="hidden" class="form-control" id="internacao_uti" name="internacao_uti" value="s">
        <input type="hidden" class="form-control" id="internado_uti_int" name="internado_uti_int" value="s">
        <input type="hidden" class="form-control" id="fk_user_uti" value="<?= $_SESSION['id_usuario'] ?>"
            name="fk_user_uti">

        <div class="form-group col-sm-2">
            <label for="internado_uti">Internado UTI</label>
            <select class="form-control-sm form-control" id="internado_uti" name="internado_uti">
                <option value="s">Sim</option>
                <option value="n">Não</option>
            </select>
        </div>
        <div class="form-group col-sm-2">
            <label for="motivo_uti">Motivo</label>
            <select class="form-control-sm form-control" id="motivo_uti" name="motivo_uti">
                <option value=" ">Selecione</option>
                <?php
                sort($dados_UTI, SORT_ASC);
                foreach ($dados_UTI as $uti) { ?>
                <option value="<?= $uti; ?>">
                    <?= $uti; ?>
                </option>
                <?php } ?>
            </select>
        </div>
        <div class="form-group col-sm-2">
            <label for="just_uti">Justificativa</label>
            <select class="form-control-sm form-control" id="just_uti" name="just_uti">
                <option value="Pertinente">Pertinente</option>
                <option value="Não pertinente">Não pertinente</option>
            </select>
        </div>
        <div class="form-group col-sm-2">
            <label for="criterio_uti">Critério</label>
            <select class="form-control-sm form-control" id="criterio_uti" name="criterio_uti">
                <option value=" ">Selecione</option>
                <?php
                sort($criterios_UTI, SORT_ASC);
                foreach ($criterios_UTI as $uti) { ?>
                <option value="<?= $uti; ?>">
                    <?= $uti; ?>
                </option>
                <?php } ?>
            </select>
        </div>
        <div class="form-group col-sm-2">
            <label for="data_internacao_uti">Data internação UTI</label>
            <input type="date" class="form-control-sm form-control" id="data_internacao_uti"
                value="<?php echo date('Y-m-d') ?>" name="data_internacao_uti">
        </div>
        <div class="form-group col-sm-1">
            <label for="hora_internacao_uti">Hora</label>
            <input type="time" class="form-control-sm form-control" id="hora_internacao_uti"
                value="<?php echo date('H:i') ?>" name="hora_internacao_uti">
        </div>
    </div>

        <div class="uti-grid-row">
            <div class="form-group col-sm-2">
                <label for="vm_uti">Ventilação Mecânica</label>
                <select class="form-control-sm form-control" id="vm_uti" name="vm_uti">
                    <option value="n">Não</option>
                    <option value="s">Sim</option>
                </select>
            </div>
            <div class="form-group col-sm-2">
                <label for="dva_uti">DVA</label>
                <select class="form-control-sm form-control" id="dva_uti" name="dva_uti">
                    <option value="n">Não</option>
                    <option value="s">Sim</option>
                </select>
            </div>
            <div class="form-group col-sm-2">
                <label for="suporte_vent_uti">Suporte Ventil Não invasivo </label>
                <select class="form-control-sm form-control" id="suporte_vent_uti" name="suporte_vent_uti">
                    <option value="n">Não</option>
                    <option value="s">Sim</option>
                </select>
            </div>
            <div class="form-group col-sm-2">
                <label for="glasgow_uti">Escala de Glasgow</label>
                <select class="form-control-sm form-control" id="glasgow_uti" name="glasgow_uti">
                    <option value="">Sel</option>
                    <option value="3-4">3-4</option>
                    <option value="5-8">5-8</option>
                    <option value="8-10">8-10</option>
                    <option value="10-12">10-12</option>
                    <option value="12-15">12-15</option>
                </select>
            </div>
            <div class="form-group col-sm-2">
                <label for="dist_met_uti">Distúrbio Metabólico</label>
                <select class="form-control-sm form-control" id="dist_met_uti" name="dist_met_uti">
                    <option value="n">Não</option>
                    <option value="s">Sim</option>
                </select>
            </div>
        </div>
        <div class="uti-grid-row">
            <div class="form-group col-sm-2">
                <label for="score_uti">Score</label>
                <select class="form-control-sm form-control" id="score_uti" name="score_uti">
                    <option value="">Selecione</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                </select>
            </div>
            <div class="form-group col-sm-9" id="justifique_uti" style="display: none;">
                <label for="justifique_uti">Justifique permanência - Critério baixo</label>
                <p id="criteria_message" style="display: inline; margin-left: 10px; font-size: 0.9em; color: #555;"></p>
                <textarea type="textarea" style="resize:none" rows="2" class="form-control" id="justifique_uti"
                    name="justifique_uti"></textarea>
            </div>

            <div class="form-group col-sm-2">
                <label for="saps_uti">Saps</label>
                <select class="form-control-sm form-control" id="saps_uti" name="saps_uti">
                    <option value=" ">Selecione</option>
                    <?php
                    sort($dados_saps, SORT_ASC);
                    foreach ($dados_saps as $saps) { ?>
                    <option value="<?= $saps; ?>">
                        <?= $saps; ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div style="margin-top:30px " class="form-group col-sm-2">
                <a style="color:blue; font-size:0.8em" href="https://www.rccc.eu/ppc/indicadores/saps3.html"
                    target="_blank">Calcular SAPS</a>
            </div>
        </div>
        <div class="uti-report">
            <label for="rel_uti">Relatório UTI</label>
            <textarea type="textarea" style="resize:none" onclick="aumentarTextUTI()" rows="2" class="form-control"
                id="rel_uti" name="rel_uti"></textarea>
        </div>
    </div>
</div>

<script>
// mudar linhas do text relatorio UTI 
var text_relatorio_uti = document.querySelector("#rel_uti");

function aumentarTextUTI() {
    if (text_relatorio_uti.rows == "2") {
        text_relatorio_uti.rows = "30"
    } else {
        text_relatorio_uti.rows = "2"
    }
}
</script>
<style>
#criteria_message {
    display: inline;
    /* Mantém na mesma linha */
    margin-left: 10px;
    /* Espaço entre o label e a mensagem */
    font-size: 0.9em;
    /* Tamanho da fonte ajustado */
}
</style>
<script>
// Selecionar os elementos necessários apenas uma vez
const dva = document.querySelector("#dva_uti");
const vm = document.querySelector("#vm_uti");
const glasgow = document.querySelector("#glasgow_uti");
const dist = document.querySelector("#dist_met_uti");
const suporteVent = document.querySelector("#suporte_vent_uti");
const criterioSelect = document.querySelector("#criterio_uti"); // Select para critério
var scoreSelect = document.querySelector("#score_uti");
var justifyDiv = document.querySelector("#justifique_uti");
var criteriaMessage = document.querySelector("#criteria_message");

// Função para avaliar as condições
function avaliarStatus() {
    let status = "";
    let score = ""; // Variável para o score
    let color = ""; // Variável para a cor

    // Regras para UTI
    if (
        dva?.value === "s" ||
        vm?.value === "s" ||
        (glasgow?.value === "3-4" || glasgow?.value === "5-8" || glasgow?.value === "8-10") ||
        dist?.value === "s"
    ) {
        status = "UTI";
        criterioSelect.value = "1"; // Alterar select para 1
        score = "1"; // Alterar score para 1
        justifyDiv.style.display = "none"; // Esconder justificativa
        criteriaMessage.textContent = ""; // Limpar mensagem
        criteriaMessage.style.color = ""; // Resetar cor
        color = "green"; // Cor para UTI
    }
    // Regras para Semi
    else if (
        dva?.value === "n" &&
        vm?.value === "n" &&
        (glasgow?.value === "10-12" || glasgow?.value === "12-15") &&
        suporteVent?.value === "s"
    ) {
        status = "Semi";
        criterioSelect.value = "2"; // Alterar select para 2
        score = "2"; // Alterar score para 2
        justifyDiv.style.display = "block"; // Mostrar justificativa
        criteriaMessage.textContent = "Paciente com critérios para Semi";
        criteriaMessage.style.color = "orange"; // Cor para Semi
        color = "orange"; // Cor para Semi
    }
    // Caso contrário, Apto
    else {
        status = "Apto";
        criterioSelect.value = "3"; // Alterar select para 3
        score = "3"; // Alterar score para 3
        justifyDiv.style.display = "block"; // Mostrar justificativa
        criteriaMessage.textContent = "Paciente com critérios para Apto";
        criteriaMessage.style.color = "red"; // Cor para Apto
        color = "red"; // Cor para Apto
    }

    // Atualizar o scoreSelect
    if (scoreSelect) {
        scoreSelect.value = score;
        // Alterar a cor da borda e texto do select scoreSelect
        scoreSelect.style.borderColor = color;
        scoreSelect.style.color = color;
    } else {
        console.error("scoreSelect não encontrado.");
    }


}

// Adicionar eventos de mudança para todos os campos relevantes
[dva, vm, glasgow, dist, suporteVent].forEach((element) => {
    if (element) {
        element.addEventListener("change", () => {
            avaliarStatus();
        });
    } else {
        console.error("Elemento não encontrado ou inválido:", element);
    }
});

// Inicializar a lógica ao carregar a página
document.addEventListener("DOMContentLoaded", avaliarStatus);
</script>


<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

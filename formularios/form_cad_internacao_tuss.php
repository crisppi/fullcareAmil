<style>
#container-tuss .form-group.row {
    display:flex;
    flex-wrap:wrap;
    gap:15px;
    align-items:flex-end;
}
#container-tuss .form-group {margin-bottom:15px;}
#container-tuss .form-group label {margin-bottom:2px;font-weight:400;}
#container-tuss .form-control {width:100%;padding:5px;}
#container-tuss .btn {padding:5px 10px;font-size:.9rem;border:none;border-radius:5px;cursor:pointer;}
#container-tuss .btn-add {background-color:#007bff;color:#fff;}
#container-tuss .btn-remove {background-color:#dc3545;color:#fff;}

/* Corrige o wrapper do bootstrap-select para não criar caixa branca extra */
#container-tuss .bootstrap-select.form-control,
#container-tuss .bootstrap-select.form-control-sm {
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
}

#container-tuss .adicional-card {
    background:#f5f5f9;
    border-radius:22px;
    border:1px solid #ebe1f5;
    box-shadow:0 12px 28px rgba(45,18,70,.08);
    padding:22px 24px;
}
#container-tuss .adicional-card__header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:1rem;
    margin-bottom:18px;
}
#container-tuss .adicional-card__title {
    display:flex;
    align-items:center;
    font-weight:600;
    margin:0;
    color:#3a184f;
}
#container-tuss .adicional-card__marker {
    width:6px;
    height:26px;
    border-radius:10px;
    margin-right:12px;
    background:linear-gradient(180deg,#9654c8,#b983f1);
}

#container-tuss .tuss-field-container {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
    align-items: end;
    width: 100%;
}

#container-tuss .tuss-field-container > .form-group[class*="col-"] {
    flex: none !important;
    min-width: 0 !important;
    max-width: none !important;
    width: 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-bottom: 0 !important;
}

#container-tuss .tuss-actions-col {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    min-width: 0 !important;
    width: 56px !important;
}

#container-tuss .tuss-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    min-height: 42px;
}

#container-tuss .btn-add,
#container-tuss .btn-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    width: 38px;
    height: 38px;
    padding: 0;
    border-radius: 8px;
    line-height: 1;
    font-size: 1rem;
    font-weight: 700;
}

@media (max-width: 768px) {
    #container-tuss .tuss-field-container {
        grid-template-columns: 1fr;
    }

    #container-tuss .tuss-actions-col {
        width: 56px !important;
    }
}
</style>

<div id="container-tuss" style="display:none; margin:5px;">
    <div class="adicional-card">
        <div class="adicional-card__header">
            <h4 class="adicional-card__title">
                <span class="adicional-card__marker"></span>
                TUSS
            </h4>
            <?php if (!empty($tussIntern) && count($tussIntern) > 0): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTUSS"
                id="openmodal">
                <i class="fas fa-eye"></i> TUSS Liberados
            </button>
            <?php endif; ?>
        </div>

    <div id="tussFieldsContainer">
        <!-- ===== Linha inicial (sem botão "-") ===== -->
        <div class="tuss-field-container form-group row" data-initial="true">
            <!-- IDs apenas na linha inicial -->
            <input type="hidden" id="tuss-json" name="tuss-json">
            <input type="hidden" class="form-control" id="fk_int_tuss" name="fk_int_tuss" value="<?= $ultimoReg + 1 ?>">
            <input type="hidden" value="<?= $_SESSION["id_usuario"] ?>" id="fk_usuario_tuss" name="fk_usuario_tuss">

            <div class="form-group col-sm-3">
                <label class="control-label" for="tuss_solicitado">Descrição Tuss</label>
                <select onchange="generateTussJSON()" class="form-control-sm form-control"
                    data-size="5" data-live-search="true" id="tuss_solicitado" name="tuss_solicitado">
                    <option value="">...</option>
                    <?php foreach ($tussGeral as $tuss): ?>
                    <option value="<?= $tuss["cod_tuss"] ?>">
                        <?= $tuss['cod_tuss'] . " - " . $tuss["terminologia_tuss"] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" for="data_realizacao_tuss">Data</label>
                <input onchange="generateTussJSON()" type="date" class="form-control-sm form-control"
                    id="data_realizacao_tuss" value="<?php echo date('Y-m-d') ?>" name="data_realizacao_tuss">
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" for="qtd_tuss_solicitado">Qtd Solicitada</label>
                <input onchange="generateTussJSON()" type="text" class="form-control-sm form-control"
                    id="qtd_tuss_solicitado" name="qtd_tuss_solicitado">
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" for="qtd_tuss_liberado">Qtd Liberada</label>
                <input onchange="generateTussJSON()" type="text" class="form-control-sm form-control"
                    id="qtd_tuss_liberado" name="qtd_tuss_liberado">
            </div>

            <div class="form-group col-sm-1">
                <label class="control-label" for="tuss_liberado_sn">Liberado</label>
                <select onchange="generateTussJSON()" class="form-control-sm form-control" id="tuss_liberado_sn"
                    name="tuss_liberado_sn">
                    <option value="">Selecione</option>
                    <option value="s">Sim</option>
                    <option value="n">Não</option>
                </select>
            </div>

            <div class="form-group col-sm-1 tuss-actions-col">
                <div class="tuss-actions">
                    <button type="button" class="btn btn-add" onclick="addTussField()">+</button>
                    <!-- sem botão "-" na linha inicial -->
                </div>
            </div>
        </div>
    </div>

        <div id="success-message" class="alert alert-success" style="display:none; margin-top:10px;">
            TUSS gravados com sucesso!
        </div>
    </div>
</div>

<!-- ===== Modal ===== -->
<div class="modal fade" id="modalTUSS">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="page-title" style="color:white">TUSS Liberados</h4>
                <p class="page-description" style="color:white;margin-top:5px">Informações sobre TUSS liberados</p>
            </div>
            <div class="modal-body">
                <?php
                if (empty($visitas)) {
                    echo ("<br>");
                    echo ("<p style='margin-left:100px'> <b>-- Esta internação ainda não possui TUSS liberados -- </b></p>");
                    echo ("<br>");
                } else { ?>
                <table class="table table-sm table-striped table-hover table-condensed">
                    <thead>
                        <tr>
                            <th scope="col" style="width:15%">TUSS Solicitado</th>
                            <th scope="col" style="width:15%">TUSS Liberado?</th>
                            <th scope="col" style="width:15%">Quantidade Solicitada</th>
                            <th scope="col" style="width:10%">Quantidade Liberada</th>
                            <th scope="col" style="width:10%">Data TUSS</th>
                            <th scope="col" style="width:5%">Visualizar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tussIntern as $intern) {
                                $visitaId = $intern["fk_internacao_vis"] ?? "N/A";
                                $dataVisita = !empty($intern['data_visita_vis'])
                                    ? date("d/m/Y", strtotime($intern['data_visita_vis']))
                                    : date("d/m/Y", strtotime($intern['data_visita_int']));
                                $tussSolicitado = $intern["terminologia_tuss"] ?? "Desconhecido";
                                $tussLiberado = ($intern["tuss_liberado_sn"] ?? '') === 's' ? 'Sim' : 'Não';
                                $qtdSolicitado = $intern["qtd_tuss_solicitado"] ?? "Desconhecido";
                                $qtdLiberado = $intern["qtd_tuss_liberado"] ?? "--";
                                $dataTuss = date("d/m/Y", strtotime($intern['data_realizacao_tuss']));
                                $linkVisualizar = $BASE_URL . "show_visita.php?id_visita=" . $visitaId;
                            ?>
                        <tr>
                            <td><?= $tussSolicitado ?></td>
                            <td><?= $tussLiberado ?></td>
                            <td><?= $qtdSolicitado ?></td>
                            <td><?= $qtdLiberado ?></td>
                            <td><?= $dataTuss ?></td>
                            <td><a href="<?= $linkVisualizar ?>"><i style="color:green;margin-right:10px"
                                        class="fas fa-eye check-icon"></i></a></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>
                <br>
            </div>
        </div>
    </div>
</div>

<script>
// Mostrar/esconder container conforme select_tuss
document.addEventListener("DOMContentLoaded", function() {
    const selectTuss = document.getElementById("select_tuss");
    const containerTuss = document.getElementById("container-tuss");
    if (selectTuss && containerTuss) {
        const toggleTussContainer = () => {
            containerTuss.style.display = (selectTuss.value === "s") ? "block" : "none";
        };
        selectTuss.addEventListener("change", toggleTussContainer);
        toggleTussContainer();
    }
});

// Adiciona nova linha (com + e -)
function addTussField() {
    const tussFieldsContainer = document.getElementById("tussFieldsContainer");
    const newField = `
    <div class="tuss-field-container form-group row">
      <!-- nas linhas novas, só name (sem id) para evitar duplicados -->
      <input type="hidden" class="form-control" name="fk_int_tuss" value="<?= $ultimoReg + 1 ?>">
      <input type="hidden" name="fk_usuario_tuss" value="<?= $_SESSION["id_usuario"] ?>">

      <div class="form-group col-sm-3">
        <label class="control-label">Descrição Tuss</label>
        <select onchange="generateTussJSON()" class="form-control-sm form-control" data-size="5" data-live-search="true" name="tuss_solicitado">
          <option value="">...</option>
          <?php foreach ($tussGeral as $tuss): ?>
            <option value="<?= $tuss["cod_tuss"] ?>">
              <?= $tuss['cod_tuss'] . " - " . $tuss["terminologia_tuss"] ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group col-sm-2">
        <label class="control-label">Data</label>
        <input onchange="generateTussJSON()" type="date" class="form-control-sm form-control"
               name="data_realizacao_tuss" value="<?php echo date('Y-m-d') ?>">
      </div>

      <div class="form-group col-sm-2">
        <label class="control-label">Qtd Solicitada</label>
        <input onchange="generateTussJSON()" type="text" class="form-control-sm form-control" name="qtd_tuss_solicitado">
      </div>

      <div class="form-group col-sm-2">
        <label class="control-label">Qtd Liberada</label>
        <input onchange="generateTussJSON()" type="text" class="form-control-sm form-control" name="qtd_tuss_liberado">
      </div>

      <div class="form-group col-sm-1">
        <label class="control-label">Liberado</label>
        <select onchange="generateTussJSON()" class="form-control-sm form-control" name="tuss_liberado_sn">
          <option value="">Selecione</option>
          <option value="s">Sim</option>
          <option value="n">Não</option>
        </select>
      </div>

      <div class="form-group col-sm-1 tuss-actions-col">
        <div class="tuss-actions">
          <button type="button" class="btn btn-add" onclick="addTussField()">+</button>
          <button type="button" class="btn btn-remove" onclick="removeTussField(this)">-</button>
        </div>
      </div>
    </div>
  `;
    tussFieldsContainer.insertAdjacentHTML("beforeend", newField);

    // atualiza JSON
    generateTussJSON();
}

// Remove linha (nunca a inicial)
function removeTussField(button) {
    const fieldContainer = button.closest(".tuss-field-container");
    if (!fieldContainer) return;

    // se for a linha inicial, não remove
    if (fieldContainer.hasAttribute("data-initial")) return;

    fieldContainer.remove();
    generateTussJSON();
}

// Gera JSON consolidado das linhas
function generateTussJSON() {
    const tussFieldContainers = document.querySelectorAll(".tuss-field-container");
    const entries = Array.from(tussFieldContainers).map(container => {
        // Pega fk_* na própria linha; se faltar, tenta pegar da linha inicial
        const fkIntInput = container.querySelector('[name="fk_int_tuss"]') || document.querySelector(
            '.tuss-field-container[data-initial="true"] [name="fk_int_tuss"]');
        const fkUserInput = container.querySelector('[name="fk_usuario_tuss"]') || document.querySelector(
            '.tuss-field-container[data-initial="true"] [name="fk_usuario_tuss"]');

        return {
            fk_int_tuss: fkIntInput ? fkIntInput.value : "",
            fk_usuario_tuss: fkUserInput ? fkUserInput.value : "",
            tuss_solicitado: (container.querySelector('[name="tuss_solicitado"]') || {}).value || "",
            data_realizacao_tuss: (container.querySelector('[name="data_realizacao_tuss"]') || {}).value || "",
            qtd_tuss_solicitado: (container.querySelector('[name="qtd_tuss_solicitado"]') || {}).value || "",
            qtd_tuss_liberado: (container.querySelector('[name="qtd_tuss_liberado"]') || {}).value || "",
            tuss_liberado_sn: (container.querySelector('[name="tuss_liberado_sn"]') || {}).value || ""
        };
    });

    const jsonString = JSON.stringify({
        tussEntries: entries
    }, null, 2);
    const tussJsonField = document.getElementById("tuss-json");
    if (tussJsonField) tussJsonField.value = jsonString;

}

// Limpa inputs mantendo só a primeira linha
function clearTussInputs() {
    const containers = document.querySelectorAll(".tuss-field-container");
    containers.forEach((container, idx) => {
        if (container.hasAttribute("data-initial")) {
            // limpa valores da inicial
            container.querySelectorAll('input:not([type="hidden"])').forEach(i => i.value = '');
            const s1 = container.querySelector('[name="tuss_solicitado"]');
            const s2 = container.querySelector('[name="tuss_liberado_sn"]');
            if (s1) {
                s1.value = '';
            }
            if (s2) {
                s2.value = '';
            }
        } else {
            container.remove();
        }
    });
    const tussJsonField = document.getElementById("tuss-json");
    if (tussJsonField) tussJsonField.value = "";
}
</script>

<!-- Dependências Bootstrap (caso ainda não estejam na página) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

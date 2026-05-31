<?php
require_once("templates/header.php");
require_once("models/message.php");

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/acomodacao.php");
include_once("dao/acomodacaoDao.php");

include_once("dao/cidDao.php");
$cid = new cidDAO($conn, $BASE_URL);
$cids = $cid->findAll();

// ...
$id_paciente_get = filter_input(INPUT_GET, 'id_paciente', FILTER_VALIDATE_INT) ?: 0;
// ...

/* === UsuarioDAO: usar somente findMedicosEnfermeiros() === */
include_once("dao/usuarioDao.php");
$usuarioDao = new userDAO($conn, $BASE_URL);

/* === DAOs auxiliares / util === */
$Internacao_geral = new internacaoDAO($conn, $BASE_URL);
$acomodacaoDao    = new acomodacaoDAO($conn, $BASE_URL);
$acomodacao       = $acomodacaoDao->findGeral();

/* === Sessão === */
$idSessao    = $_SESSION["id_usuario"] ?? '';
$cargoSessao = $_SESSION['cargo'] ?? ($_SESSION['cargo_user'] ?? '');
$emailSessao = $_SESSION['email_user'] ?? '';

$dataAtual = date('Y-m-d');
$agora     = date('Y-m-d');

/* ==========================================================
   CONTROLE DE ACESSO POR CARGO
   ========================================================== */
$cargo  = $_SESSION['cargo']        ?? '';
$userId = (int)($_SESSION['id_usuario'] ?? 0);
$rolesFiltrados       = ['Med_auditor', 'Enf_Auditor', 'Adm'];
$aplicarFiltroUsuario = in_array($cargo, $rolesFiltrados, true) ? $userId : null;

/* === AUDITORES via UsuarioDAO::findMedicosEnfermeiros() === */
$medicosAud = [];
$enfsAud    = [];
try {
    $todos = $usuarioDao->findMedicosEnfermeiros();
    if (!is_array($todos)) $todos = [];
    foreach ($todos as $u) {
        $id    = $u['id_usuario']   ?? null;
        $nome  = $u['usuario_user'] ?? null;
        $email = $u['email_user']   ?? null;
        $cargo = $u['cargo_user']   ?? '';
        if (!$id) continue;

        $row = [
            'id_usuario'   => (int)$id,
            'usuario_user' => (string)$nome,
            'email_user'   => (string)$email,
            'cargo_user'   => (string)$cargo,
        ];

        $c = mb_strtoupper((string)$cargo, 'UTF-8');
        if (strpos($c, 'MED') === 0)      $medicosAud[] = $row;
        elseif (strpos($c, 'ENF') === 0)  $enfsAud[]    = $row;
    }
} catch (Throwable $e) {
    $medicosAud = $enfsAud = [];
}
echo "\n<!-- via findMedicosEnfermeiros | med=" . count($medicosAud) . " enf=" . count($enfsAud) . " -->\n";

/* ===== Mostrar Cadastro Central APENAS se NÃO for médico nem enfermeiro ===== */
$normCargo = mb_strtolower(str_replace([' ', '-'], '_', (string)$cargoSessao), 'UTF-8');
$mostrarCadastroCentral = !in_array($normCargo, ['med_auditor', 'medico_auditor', 'enf_auditor', 'enfer_auditor'], true);
?>
<link href="<?= $BASE_URL ?>css/style.css" rel="stylesheet">

<style>
/* z-index do dropdown do header */
.navbar .dropdown-menu {
    z-index: 1055;
}

/* Selects roxos (tabelas adicionais) */
.select-purple {
    color: #fff;
    background-color: #5e2363;
    border: 1px solid #5e2363;
}

.select-purple:focus {
    box-shadow: 0 0 0 .25rem rgba(94, 35, 99, .25);

}

.is-invalid {
    border-color: #dc3545 !important;
}
</style>

<!-- Shim BS4 -> BS5 (data-toggle -> data-bs-*) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-toggle="dropdown"]').forEach(function(el) {
        el.setAttribute('data-bs-toggle', 'dropdown');
    });
    document.querySelectorAll('[data-toggle="collapse"]').forEach(function(el) {
        el.setAttribute('data-bs-toggle', 'collapse');
    });
    document.querySelectorAll('[data-target]').forEach(function(el) {
        if (!el.getAttribute('data-bs-target')) el.setAttribute('data-bs-target', el.getAttribute(
            'data-target'));
    });
});
</script>

<div class="row" style="margin-top:-35px;">
    <div class="form-group row">
        <h4 class="text-center w-100"
            style="margin:-7px 10px;background-color:#5e2363;color:#fff;padding:13px 0;border-radius:.25rem;">
            Cadastrar internação
        </h4>
        <hr>

        <div class="col-12 d-flex align-items-end flex-wrap justify-content-between" style="margin-top:-20px;">
            <div class="d-flex flex-wrap align-items-end" style="gap:30px;flex:2;">
                <div class="form-group mb-0">
                    <label class="control-label" for="RegInt">Id-Int</label>
                    <input type="text" id="RegInt" name="RegInt" readonly class="form-control"
                        style="height:45px;background-color:#fff;color:#000;font-weight:500;opacity:1;cursor:default;"
                        value="<?= ($ultimoReg + 1) ?>">
                </div>

                <div class="form-group mb-0" style="min-width:300px;">
                    <label class="control-label" for="hospital_selected" style="margin-bottom:2px;">
                        <span style="color:red;">*</span> Hospital
                    </label>
                    <select onchange="myFunctionSelected()"
                        style="height:45px !important;border:1px solid #555;font-size:1em;background-color:#fff;color:#000;width:100%;"
                        class="form-select botao_select" id="hospital_selected" name="hospital_selected" required>
                        <option value="">Selecione</option>
                        <?php foreach ($listHopitaisPerfil as $hospital): ?>
                        <option value="<?= htmlspecialchars($hospital['id_hospital']); ?>">
                            <?= htmlspecialchars($hospital["nome_hosp"]) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-center align-items-center" style="flex:1">
                <div id="hospitalNomeTexto"
                    style="width:100%;display:none;max-width:500px;margin-left:-500px;height:75px;padding:0 50px;border:2px solid #28a745;border-radius:8px;font-size:1.2em;font-weight:600;color:#000;background-color:#f8fff8;align-items:center;justify-content:center;text-align:center;">
                </div>
            </div>
        </div>

        <hr class="w-100">
    </div>

    <form class="visible" action="<?= $BASE_URL ?>process_internacao.php" id="myForm" method="POST"
        enctype="multipart/form-data">
        <div style="text-align:right;">
            <p style="font-size:.6em;color:red;margin-top:-20px;">* Campos Obrigatórios</p>
        </div>

        <input type="hidden" name="type" value="create">
        <p style="display:none" id="proximoId_int">0</p>
        <input type="hidden" value="n" id="censo_int" name="censo_int">

        <!-- fk_usuario_int: padrão = usuário logado; Cadastro Central pode sobrescrever -->
        <input type="hidden" value="<?= htmlspecialchars($idSessao) ?>" id="fk_usuario_int" name="fk_usuario_int">

        <div class="form-group row">
            <input type="hidden" value="<?= $hospital["id_hospital"] ?? '' ?>" name="fk_hospital_int"
                id="fk_hospital_int">

            <div class="form-group col-sm-3" style="margin-bottom:-5px">
                <label class="control-label" for="fk_paciente_int"><span style="color:red;">*</span> Paciente </label>
                <select onchange="teste()" data-size="5" data-live-search="true"
                    class="form-control form-control-sm selectpicker show-tick" id="fk_paciente_int"
                    name="fk_paciente_int" required>
                    <option value="">Selecione</option>
                    <?php
                    usort($pacientes, fn($a, $b) => strcmp($a["nome_pac"], $b["nome_pac"]));
                    foreach ($pacientes as $paciente): ?>
                    <option value="<?= (int)$paciente["id_paciente"] ?>"><?= htmlspecialchars($paciente["nome_pac"]) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <a style="font-size:.8em;margin-left:7px;color:blue;" href="#"
                        onclick="openModalPac('<?= $BASE_URL ?>cad_paciente.php', 'Cadastrar paciente'); return false;">
                        <i style="color:blue;margin-bottom:7px;" class="far fa-edit edit-icon"></i> Novo Paciente
                    </a>
                    <div id="alert_intern" style="font-size:1em;margin-left:7px;color:red;display:none">Paciente já
                        internado</div>
                </div>
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" for="data_intern_int"><span style="color:red;">*</span> Data
                    Internação</label>
                <input type="date" class="form-control form-control-sm" id="data_intern_int" required value=""
                    name="data_intern_int">
                <p id="erro-data-internacao" style="color:red;font-size:.7em;display:none;margin-top:5px;"></p>
            </div>

            <div class="form-group col-sm-1">
                <label class="control-label" for="hora_intern_int">Hora</label>
                <input type="time" class="form-control form-control-sm" id="hora_intern_int" value=""
                    name="hora_intern_int">
            </div>

            <div class="form-group col-sm-1">
                <label for="data_visita_int"><span style="color:red;">*</span> Data Visita</label>
                <input type="date" value='<?= $dataAtual; ?>' class="form-control form-control-sm" id="data_visita_int"
                    name="data_visita_int">
                <p id="error-message" style="color:red;display:none;font-size:.6em;"></p>
            </div>

            <div class="form-group col-sm-1">
                <label class="control-label" for="internado_int">Internado</label>
                <select class="form-control-sm form-control" id="internado_int" name="internado_int">
                    <option value="s">Sim</option>
                    <option value="n">Não</option>
                </select>
            </div>

            <div class="form-group col-sm-1" id="div-data-alta" style="display:none">
                <label class="control-label" for="data_alta_alt"> Data Alta</label>
                <input type="date" class="form-control form-control-sm" id="data_alta_alt" name="data_alta_alt">
            </div>

            <div class="form-group col-sm-2" id="div-motivo-alta" style="display:none">
                <label class="control-label" for="tipo_alta_alt"> Motivo Alta</label>
                <select class="form-control" id="tipo_alta_alt" name="tipo_alta_alt">
                    <option value="">Selecione o motivo da alta</option>
                    <?php sort($dados_alta, SORT_ASC);
                    foreach ($dados_alta as $alta): ?>
                    <option value="<?= htmlspecialchars($alta); ?>"><?= htmlspecialchars($alta); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="hidden" id="id_internacao" readonly class="form-control" name="id_internacao"
                value="<?= $ultimoReg ?>">
            <input type="hidden" value="s" id="primeira_vis_int" name="primeira_vis_int">
            <input type="hidden" value="0" id="visita_no_int" name="visita_no_int">

            <!-- Flags do responsável (atualizadas pelo JS unificado) -->
            <input type="hidden" id="visita_enf_int" name="visita_enf_int" value="n">
            <input type="hidden" id="visita_med_int" name="visita_med_int" value="n">
            <input type="hidden" id="visita_auditor_prof_enf" name="visita_auditor_prof_enf" value="">
            <input type="hidden" id="visita_auditor_prof_med" name="visita_auditor_prof_med" value="">
        </div>

        <!-- ===== CADASTRO CENTRAL (só aparece se NÃO for med/enf) ===== -->
        <?php if ($mostrarCadastroCentral): ?>
        <div id="cadastro-central-wrapper" class="form-group row"
            style="margin-top:8px;display:block !important;border:2px dashed #8a2be2;padding:10px;border-radius:8px;">
            <div class="form-group col-sm-12" style="margin-bottom:6px;">
                <span style="font-weight:700;color:#5e2363;">Cadastro Central ativo</span>
                <small style="margin-left:8px;color:#666;">(opcional: escolha o tipo e o responsável)</small>
            </div>

            <div class="form-group row align-items-end">
                <div class="form-group col-sm-3">
                    <label class="control-label" for="resp_tipo">Responsável pela visita</label>
                    <select id="resp_tipo" class="form-control form-control-sm">
                        <option value="">(sem seleção)</option>
                        <option value="med">Médico auditor</option>
                        <option value="enf">Enfermeiro auditor</option>
                    </select>
                </div>

                <div class="form-group col-sm-4 d-none" id="box_resp_med">
                    <label class="control-label" for="resp_med_id">Selecionar médico</label>
                    <select id="resp_med_id" class="form-control form-control-sm selectpicker" data-live-search="true"
                        data-size="5" title="Selecione">
                        <option value="">Selecione</option>
                        <?php foreach ($medicosAud as $m): ?>
                        <option value="<?= (int)$m['id_usuario'] ?>"
                            data-email="<?= htmlspecialchars($m['email_user'] ?? '') ?>">
                            <?= htmlspecialchars($m['usuario_user'] ?? ('#' . $m['id_usuario'])) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group col-sm-5 d-none" id="box_resp_enf">
                    <label class="control-label" for="resp_enf_id">Selecionar enfermeiro</label>
                    <select id="resp_enf_id" class="form-control form-control-sm selectpicker" data-live-search="true"
                        data-size="5" title="Selecione">
                        <option value="">Selecione</option>
                        <?php foreach ($enfsAud as $e): ?>
                        <option value="<?= (int)$e['id_usuario'] ?>"
                            data-email="<?= htmlspecialchars($e['email_user'] ?? '') ?>">
                            <?= htmlspecialchars($e['usuario_user'] ?? ('#' . $e['id_usuario'])) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- ===== /CADASTRO CENTRAL ===== -->

        <div class="row">
            <div class="form-group col-sm-2">
                <label class="control-label" for="acomodacao_int">Acomodação</label>
                <select class="form-control-sm form-control" id="acomodacao_int" name="acomodacao_int">
                    <option value="">Selecione</option>
                    <?php sort($dados_acomodacao, SORT_ASC);
                    foreach ($dados_acomodacao as $acomd): ?>
                    <option value="<?= htmlspecialchars($acomd) ?>"><?= htmlspecialchars($acomd) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-sm-2">
                <label class="control-label" for="especialidade_int">Especialidade</label>
                <input list="especialidade-options" class="form-control-sm form-control" id="especialidade_int"
                    name="especialidade_int" placeholder="Selecione ou digite">
                <datalist id="especialidade-options">
                    <?php sort($dados_especialidade, SORT_ASC);
                    foreach ($dados_especialidade as $especial): ?>
                    <option value="<?= htmlspecialchars($especial) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group col-sm-3">
                <label for="titular_int">Médico</label>
                <input type="text" maxlength="100" class="form-control form-control-sm" id="titular_int"
                    name="titular_int">
            </div>
            <div class="form-group col-sm-1">
                <label for="crm_int">CRM</label>
                <input type="text" maxlength="10" class="form-control form-control-sm" id="crm_int" name="crm_int">
            </div>
            <div class="form-group col-sm-2">
                <label class="control-label" for="modo_internacao_int">Modo Admissão</label>
                <select class="form-control-sm form-control" id="modo_internacao_int" name="modo_internacao_int">
                    <option value="">Selecione</option>
                    <option value="Clínica">Clínica</option>
                    <option value="Pediatria">Pediatria</option>
                    <option value="Ortopedia">Ortopedia</option>
                    <option value="Obstetrícia">Obstetrícia</option>
                </select>
            </div>
            <div class="form-group col-sm-2">
                <label class="control-label" for="tipo_admissao_int">Tipo Internação</label>
                <select class="form-control-sm form-control" id="tipo_admissao_int" name="tipo_admissao_int">
                    <option value="">Selecione</option>
                    <option value="Eletiva">Eletiva</option>
                    <option value="Urgência">Urgência</option>
                </select>
            </div>
        </div>

        <div class="form-group row">
            <div style="display:none;" id="div_int_pertinente_int" class="form-group col-sm-2">
                <label class="control-label" for="int_pertinente_int"><span style="color:red;">*</span> Internação
                    pertinente?</label>
                <select class="form-control-sm form-control" id="int_pertinente_int" name="int_pertinente_int">
                    <option value="">Selecione</option>
                    <option value="s">Sim</option>
                    <option value="n">Não</option>
                </select>
            </div>
            <div id="div_rel_pertinente_int" style="display:none;" class="form-group col-sm-8">
                <label for="rel_pertinente_int">Justifique não pertinência</label>
                <textarea style="resize:none" rows="3" class="form-control" id="rel_pertinente_int"
                    name="rel_pertinente_int"></textarea>
            </div>
        </div>

        <div class="form-group row">
            <div class="form-group col-sm-3">
                <label class="control-label" for="fk_patologia_int">Patologia</label>
                <select class="form-control-sm form-control selectpicker show-tick" data-size="5"
                    data-live-search="true" id="fk_patologia_int" name="fk_patologia_int">
                    <option value="">Selecione</option>
                    <?php
                    usort($patologias, fn($a, $b) => strcmp($a["patologia_pat"], $b["patologia_pat"]));
                    foreach ($patologias as $patologia): ?>
                    <option value="<?= (int)$patologia["id_patologia"] ?>">
                        <?= htmlspecialchars($patologia["patologia_pat"]) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" for="grupo_patologia_int">Grupo Patologia</label>
                <select class="form-control-sm form-control" id="grupo_patologia_int" name="grupo_patologia_int">
                    <option value="">Selecione</option>
                    <?php foreach ($dados_grupo_pat as $grupo): ?>
                    <option value="<?= htmlspecialchars($grupo) ?>"><?= htmlspecialchars($grupo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group col-sm-1">
                <label class="control-label" for="origem_int">Origem</label>
                <select class="form-control-sm form-control" id="origem_int" name="origem_int">
                    <option value="">Selecione</option>
                    <?php foreach ($origem as $origens): ?>
                    <option value="<?= htmlspecialchars($origens) ?>"><?= htmlspecialchars($origens) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group col-sm-1">
                <label for="senha_int">Senha</label>
                <input type="text" maxlength="20" class="form-control form-control-sm" id="senha_int" name="senha_int">
            </div>
            <div class="form-group col-sm-2">
                <label for="num_atendimento_int">Num. Atendimento</label>
                <input type="text" maxlength="20" class="form-control form-control-sm" id="num_atendimento_int"
                    name="num_atendimento_int">
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" for="fk_patologia2">Antecedente</label>
                <select class="form-control-sm form-control selectpicker show-tick" data-size="5"
                    data-live-search="true" id="fk_patologia2" name="fk_patologia2[]" multiple title="Selecione">
                    <?php
                    usort($antecedentes, fn($a, $b) => strcmp($a["antecedente_ant"], $b["antecedente_ant"]));
                    foreach ($antecedentes as $antecedente): ?>
                    <option value="<?= (int)$antecedente["id_antecedente"] ?>">
                        <?= htmlspecialchars($antecedente["antecedente_ant"]) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" value="" id="json-antec" name="json-antec">
        </div>

        <div><br></div>

        <div class="form-group" style="margin-left:0px; margin-top:-15px">
            <div>
                <label for="rel_int">Relatório de Auditoria</label>
                <textarea maxlength="5000" style="resize:none" rows="2" onclick="aumentarText('rel_int')"
                    class="form-control" id="rel_int" name="rel_int"></textarea>
            </div>

            <!-- Chat Widget -->
            <div id="chat-widget" style="position: fixed; bottom: 20px; right: 20px; width: 300px; z-index: 9999;">
                <div id="chat-header" style="background-color: #007bff; color: white; padding: 10px; cursor: pointer;">
                    Chat - Assistente Virtual
                </div>
                <div id="chat-body"
                    style="display: none; border: 1px solid #ccc; background: white; max-height: 400px; overflow-y: auto;">
                    <div id="chat-messages" style="padding: 10px; font-size: 0.9em;"></div>
                    <div style="padding: 10px;">
                        <input type="text" id="chat-input" placeholder="Digite sua mensagem..."
                            style="width: 100%; padding: 5px; border: 1px solid #ccc;">
                        <button id="chat-send"
                            style="margin-top: 5px; width: 100%; background-color: #007bff; color: white; border: none; padding:5px;">Enviar</button>
                    </div>
                </div>
            </div>

            <div style="margin-top: 10px;">
                <label for="acoes_int">Ações da Auditoria</label>
                <textarea rows="2" style="resize:none" onclick="aumentarText('acoes_int')" class="form-control"
                    maxlength="5000" id="acoes_int" name="acoes_int"></textarea>
            </div>

            <div style="margin-top: 10px;">
                <label for="programacao_int">Programação Terapêutica</label>
                <textarea style="resize:none" maxlength="5000" rows="2" onclick="aumentarText('programacao_int')"
                    class="form-control" id="programacao_int" name="programacao_int"></textarea>
            </div>

            <div><br></div>
            <hr>
            <h4 class="text-center w-100"
                style="margin: 7px 10px 0px 0px;background-color: #5e2363;color: #fff;padding: 13px 0;border-radius: 0.25rem;">
                Detalhes do relatório
            </h4>
            <hr>

            <input type="hidden" class="form-control" id="select_detalhes" name="select_detalhes">

            <div class="form-group row">
                <div class="form-group col-sm-2" style="margin-left:10px;">
                    <label class="control-label" style="font-weight: bold;" for="relatorio-detalhado">Relatório
                        detalhado</label>
                    <select class="form-control-sm form-control" id="relatorio-detalhado" name="relatorio-detalhado"
                        style="color:white; font-weight:normal; border:1px solid #5e2363; background-color:#5e2363;">
                        <option value="">Selecione</option>
                        <option value="s">Sim</option>
                        <option value="n">Não</option>
                    </select>
                    <p id="text-detalhado" style="font-size:0.7em; text-align:center; margin-top:8px; margin-left:8px">
                        Selecione este campo caso deseje detalhar a visita
                    </p>
                </div>
                <div class="form-group col-sm-3">
                    <input type="hidden" id="data_create_int" value='<?= $agora; ?>' name="data_create_int">
                </div>
            </div>

            <div id="div-detalhado" class="form-group row" style="margin-left:-12px">
                <div class="form-group row">
                    <input type="text" readonly id="fk_int_det" name="fk_int_det" value="<?= ($ultimoReg + 1) ?>">

                    <div class="form-group col-sm-2">
                        <label class="control-label" for="curativo_det">Curativo</label>
                        <select class="form-control-sm form-control" id="curativo_det" name="curativo_det">
                            <option value="">Selecione</option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2">
                        <label class="control-label" for="dieta_det">Tipo dieta</label>
                        <select class="form-control-sm form-control" id="dieta_det" name="dieta_det">
                            <option value="">Selecione</option>
                            <option value="Oral">Oral</option>
                            <option value="Enteral">Enteral</option>
                            <option value="NPP">NPP</option>
                            <option value="Jejum">Jejum</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2">
                        <label class="control-label" for="nivel_consc_det">Nível de Consciência</label>
                        <select class="form-control-sm form-control" id="nivel_consc_det" name="nivel_consc_det">
                            <option value="">Selecione</option>
                            <option value="Consciente">Consciente</option>
                            <option value="Comatoso">Comatoso</option>
                            <option value="Vigil">Vigil</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2">
                        <label class="control-label" for="oxig_det">Oxigênio</label>
                        <select class="form-control-sm form-control" id="oxig_det" name="oxig_det">
                            <option value="">Selecione</option>
                            <option value="Cateter">Cateter</option>
                            <option value="Mascara">Máscara</option>
                            <option value="VNI">VNI</option>
                            <option value="Alto Fluxo">Alto Fluxo</option>
                        </select>
                    </div>
                    <div id="div-oxig" class="form-group col-sm-1">
                        <label class="control-label" for="oxig_uso_det">Lts O2</label>
                        <input class="form-control-sm form-control" type="text" name="oxig_uso_det">
                    </div>

                    <div class="form-group col-sm-3">
                        <label class="control-label">Dispositivos</label>
                        <div class="d-flex flex-wrap align-items-center">
                            <div class="form-check ">
                                <label style="margin-left:-30px" class="control-label" for="tqt_det">TQT</label>
                                <input class="form-check-input" type="checkbox" name="tqt_det" id="tqt_det" value="TQT">
                            </div>
                            <div class="form-check">
                                <label style="margin-left:-30px" class="control-label" for="svd_det">SVD</label>
                                <input class="form-check-input" type="checkbox" name="svd_det" id="svd_det" value="SVD">
                            </div>
                            <div class="form-check" style="text-align: center;">
                                <label style="margin-left:-30px" class="control-label" for="sne_det">SNE</label>
                                <input class="form-check-input" type="checkbox" name="sne_det" id="sne_det" value="SNE">
                            </div>
                            <div class="form-check">
                                <label style="margin-left:-30px" class="control-label" for="gtt_det">GTT</label>
                                <input class="form-check-input" type="checkbox" name="gtt_det" id="gtt_det" value="GTT">
                            </div>
                            <div class="form-check">
                                <label style="margin-left:-30px" class="control-label" for="dreno_det">Dreno</label>
                                <input class="form-check-input" type="checkbox" name="dreno_det" id="dreno_det"
                                    value="Dreno">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group row" style="margin-top: -20px;">
                    <div class="form-group col-sm-2">
                        <label class="control-label" for="hemoderivados_det">Hemoderivados</label>
                        <select class="form-control-sm form-control" id="hemoderivados_det" name="hemoderivados_det">
                            <option value="">Selecione</option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2">
                        <label class="control-label" for="dialise_det">Diálise</label>
                        <select class="form-control-sm form-control" id="dialise_det" name="dialise_det">
                            <option value="">Selecione</option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2">
                        <label class="control-label" for="oxigenio_hiperbarica_det">Oxigenioterapia Hiperbárica</label>
                        <select class="form-control-sm form-control" id="oxigenio_hiperbarica_det"
                            name="oxigenio_hiperbarica_det">
                            <option value="">Selecione</option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-1">
                        <label class="control-label" for="qt_det">QT</label>
                        <select class="form-control-sm form-control" id="qt_det" name="qt_det">
                            <option value=""></option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-1">
                        <label class="control-label" for="rt_det">RT</label>
                        <select class="form-control-sm form-control" id="rt_det" name="rt_det">
                            <option value=""></option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-1">
                        <label class="control-label" for="acamado_det">Acamado</label>
                        <select class="form-control-sm form-control" id="acamado_det" name="acamado_det">
                            <option value=""></option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-1">
                        <label class="control-label" for="atb_det">Antibiótico</label>
                        <select class="form-control-sm form-control" id="atb_det" name="atb_det">
                            <option value=""></option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div id="atb" class="form-group col-sm-3">
                        <label class="control-label" for="atb_uso_det">Antibiótico em uso</label>
                        <input class="form-control" type="text" name="atb_uso_det">
                    </div>
                    <div class="form-group col-sm-1">
                        <label class="control-label" for="medic_alto_custo_det">Medicação</label>
                        <select class="form-control-sm form-control" id="medic_alto_custo_det"
                            name="medic_alto_custo_det">
                            <option value="n">Não</option>
                            <option value="s">Sim</option>
                        </select>
                    </div>
                    <div id="medicacaoDet" class="form-group col-sm-3">
                        <label class="control-label" for="qual_medicamento_det">Medicação alto custo</label>
                        <input class="form-control-sm form-control" type="text" name="qual_medicamento_det">
                    </div>
                    <div>
                        <label for="exames_det">Exames relevantes</label>
                        <textarea style="resize:none" maxlength="5000" rows="3" onclick="aumentarText('exames_det')"
                            onblur="reduzirText('exames_det', 3)" class="form-control" id="exames_det"
                            name="exames_det"></textarea>
                    </div>
                    <div>
                        <label for="oportunidades_det">Oportunidades</label>
                        <textarea style="resize:none" maxlength="5000" rows="2"
                            onclick="aumentarText('oportunidades_det')" class="form-control" id="oportunidades_det"
                            onblur="reduzirText('oportunidades_det', 3)" name="oportunidades_det"></textarea>
                    </div>
                </div>

                <div class="form-group row">
                    <div class="form-group col-sm-2">
                        <label class="control-label" for="liminar_det">Possui Liminar?</label>
                        <select class="form-control-sm form-control" id="liminar_det" name="liminar_det">
                            <option value="n">Não</option>
                            <option value="s">Sim</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2">
                        <label class="control-label" for="paliativos_det">Está em Cuidados Paliativos?</label>
                        <select class="form-control-sm form-control" id="paliativos_det" name="paliativos_det">
                            <option value="n">Não</option>
                            <option value="s">Sim</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2">
                        <label class="control-label" for="parto_det">Parto</label>
                        <select class="form-control-sm form-control" id="parto_det" name="parto_det">
                            <option value="n">Não</option>
                            <option value="s">Sim</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2">
                        <label class="control-label" for="braden_det">Escala de Braden</label>
                        <select class="form-control-sm form-control" id="braden_det" name="braden_det">
                            <option value=""></option>
                            <option value="alto">Alto</option>
                            <option value="moderado">Moderado</option>
                            <option value="baixo">Baixo</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <h4 class="text-center w-100"
            style="margin:-15px 10px 0px 0px;background-color:#5e2363;color:#fff;padding:13px 0;border-radius:.25rem;">
            Tabelas Adicionais
        </h4>
        <hr>

        <div class="form-group row d-flex justify-content-center align-items-end" style="gap: 15px;">
            <?php if ($cargoSessao === 'Med_auditor' || $cargoSessao === 'Diretoria') { ?>
            <div class="form-group col-sm-2">
                <label class="control-label" style="font-weight: bold;" for="select_tuss">Tuss</label>
                <select class="form-control-sm form-control select-purple" id="select_tuss" name="select_tuss">
                    <option value="">Selecione</option>
                    <option value="s">Sim</option>
                    <option value="n">Não</option>
                </select>
            </div>
            <div class="form-group col-sm-2">
                <label class="control-label" style="font-weight: bold;" for="select_prorrog">Prorrogação</label>
                <select class="form-control-sm form-control select-purple" id="select_prorrog" name="select_prorrog">
                    <option value="">Selecione</option>
                    <option value="s">Sim</option>
                    <option value="n">Não</option>
                </select>
            </div>
            <?php } ?>

            <div class="form-group col-sm-2">
                <label class="control-label" style="font-weight: bold;" for="select_gestao">Gestão</label>
                <select class="form-control-sm form-control select-purple" id="select_gestao" name="select_gestao">
                    <option value="">Selecione</option>
                    <option value="s">Sim</option>
                    <option value="n">Não</option>
                </select>
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" style="font-weight: bold;" for="select_uti">UTI</label>
                <select class="form-control-sm form-control select-purple" id="select_uti" name="select_uti">
                    <option value="">Selecione</option>
                    <option value="s">Sim</option>
                    <option value="n">Não</option>
                </select>
            </div>

            <?php if ($cargoSessao === 'Med_auditor' || $cargoSessao === 'Diretoria') { ?>
            <div class="form-group col-sm-2">
                <label class="control-label" style="font-weight: bold;" for="select_negoc">Negociações</label>
                <select class="form-control-sm form-control select-purple" id="select_negoc" name="select_negoc">
                    <option value="">Selecione</option>
                    <option value="s">Sim</option>
                    <option value="n">Não</option>
                </select>
            </div>
            <?php } ?>
        </div>

        <input type="hidden" class="form-control" value="<?= ($ultimoReg + 1) ?>" id="fk_int_capeante"
            name="fk_int_capeante">
        <input type="hidden" class="form-control" value="n" id="encerrado_cap" name="encerrado_cap">
        <input type="hidden" class="form-control" value="s" id="aberto_cap" name="aberto_cap">
        <input type="hidden" class="form-control" value="n" id="em_auditoria_cap" name="em_auditoria_cap">
        <input type="hidden" class="form-control" value="n" id="senha_finalizada" name="senha_finalizada">

        <?php include_once('formularios/form_cad_internacao_tuss.php'); ?>
        <?php include_once('formularios/form_cad_internacao_gestao.php'); ?>
        <?php include_once('formularios/form_cad_internacao_uti.php'); ?>
        <?php include_once('formularios/form_cad_internacao_prorrog.php'); ?>
        <?php include_once('formularios/form_cad_internacao_negoc.php'); ?>

        <div class="row">
            <div class="form-group col-md-6">
                <label for="intern_files">Arquivos</label>
                <input type="file" class="form-control" name="intern_files[]" id="intern_files"
                    accept="image/png, image/jpeg" multiple>
                <div class="notif-input oculto" id="notifImagem">Tamanho do arquivo inválido!</div>
            </div>
        </div>

        <div>
            <hr>
            <button type="submit" class="btn btn-success">
                <i style="font-size: 1rem;margin-right:5px;" class="fa-solid fa-check edit-icon"></i>
                Cadastrar
            </button>
            <br><br>
            <div style="width:500px;display:none" class="alert" id="alert" role="alert"></div>
        </div>
    </form>
</div>
<?php if (!empty($id_paciente_get)): ?>
<script>
(function preselectPaciente() {
    var tentativas = 0;
    var idPac = "<?= (int)$id_paciente_get ?>";

    function aplicar() {
        var $sel = $('#fk_paciente_int');
        if (!$sel.length) return false;

        // seta o valor
        $sel.val(idPac);

        // se estiver usando bootstrap-select, atualiza a UI
        if ($.fn.selectpicker && $sel.hasClass('selectpicker')) {
            $sel.selectpicker('refresh');
        }

        // dispara sua verificação de internação ativa
        if (typeof teste === 'function') {
            try {
                teste();
            } catch (e) {
                console.warn('teste() falhou:', e);
            }
        }
        return true;
    }

    // tenta algumas vezes até o select/BS-Select estarem prontos
    (function aguardarPronto() {
        if (aplicar()) return;
        if (++tentativas < 30) return setTimeout(aguardarPronto, 100);
        console.warn('Não foi possível pré-selecionar o paciente.');
    })();
})();
</script>
<?php endif; ?>

<script>
function aumentarText(id) {
    const el = document.getElementById(id);
    if (el) el.rows = 20;
}

function reduzirText(id, rows) {
    const el = document.getElementById(id);
    if (el) el.rows = rows;
}

document.addEventListener('DOMContentLoaded', function() {
    // id do textarea + número de linhas “fechado”
    const campos = [
        ['rel_int', 2],
        ['acoes_int', 2],
        ['programacao_int', 2],
    ];

    campos.forEach(([id, rowsFechado]) => {
        const el = document.getElementById(id);
        if (!el) return;

        // ao focar, expande
        el.addEventListener('focus', () => aumentarText(id));

        // ao perder o foco, volta para o tamanho original
        el.addEventListener('blur', () => reduzirText(id, rowsFechado));
    });
});
// selectpicker só se o plugin existir (evita quebrar tudo)
$(function() {
    if ($.fn.selectpicker) {
        $('.selectpicker').selectpicker();
        $('.selectpicker').selectpicker('refresh');
        $('.selectpicker').on('loaded.bs.select', function() {
            $('.bs-searchbox input').attr('placeholder', 'Digite para pesquisar...');
        });
    }
});

function teste(evt) {
    if (evt && typeof evt.preventDefault === 'function') evt.preventDefault();

    $.ajax({
        url: "check_internacao.php",
        type: "POST",
        data: {
            id_paciente: $('#fk_paciente_int').val()
        },
        success: function(result) {
            const alert_div = document.getElementById('alert_intern');
            // garante comparação segura
            const ativo = String(result).trim() === '1';
            alert_div.style.display = ativo ? "block" : "none";
        },
        error: function(xhr, status, err) {
            console.error("Erro ao checar internação:", status, err, xhr.responseText);
        }
    });
}

document.addEventListener('paciente:cadastrado', function(event) {
    const data = event.detail || {};
    const novoId = data.id || data.id_paciente;
    if (!novoId) return;
    const select = document.getElementById('fk_paciente_int');
    if (!select) return;

    let option = Array.from(select.options).find(opt => String(opt.value) === String(novoId));
    const label = data.nome || data.nome_pac || `Paciente #${novoId}`;

    if (!option) {
        option = new Option(label, novoId, true, true);
        select.appendChild(option);
    } else {
        option.selected = true;
        option.textContent = label;
    }

    if (window.$ && $.fn.selectpicker && $(select).hasClass('selectpicker')) {
        $(select).selectpicker('refresh');
        $(select).selectpicker('val', String(novoId));
    } else {
        select.value = novoId;
    }
});
</script>

<script src="<?= $BASE_URL ?>js/text_cad_internacao.js"></script>
<script src="js/select_internacao.js"></script>

<script>
// Hospital selecionado -> mostra nome e grava hidden
function myFunctionSelected() {
    const select = document.querySelector("#hospital_selected");
    const selectedValue = select.value;
    const selectedText = select.options[select.selectedIndex].text;
    const inputHospital = document.querySelector("#fk_hospital_int");
    const divNome = document.querySelector("#hospitalNomeTexto");

    inputHospital.value = selectedValue;

    // --- INÍCIO DA ALTERAÇÃO ---
    if (selectedValue !== "") {
        // Aplica estilo de sucesso (borda verde) quando um hospital válido é selecionado
        $("#hospital_selected").css({
            "color": "black",
            "font-weight": "bold",
            "border": "2px solid green", // Borda verde sucesso
            "padding-top": "3px",
            "padding-bottom": "3px",
            "line-height": "normal"
        });
        divNome.textContent = selectedText;
        divNome.style.display = "flex";
    } else {
        // Reseta o estilo se "Selecione" for escolhido novamente
        $("#hospital_selected").css({
            "color": "#000", // Cor padrão
            "font-weight": "normal",
            "border": "1px solid #555", // Borda padrão original
            "padding-top": "", // Reseta padding
            "padding-bottom": "",
            "line-height": "" // Reseta line-height
        });
        divNome.textContent = "";
        divNome.style.display = "none";
    }
    // --- FIM DA ALTERAÇÃO ---
}

// Estilo do select "relatório detalhado"
$('#relatorio-detalhado').on('change', function() {
    const optionDetalhes = $(this).find(":selected").text();
    const baseCss = {
        "color": "white",
        "font-weight": "normal",
        "border": "1px solid #5e2363",
        "background-color": "#5e2363"
    };
    $(this).css(baseCss);
    if (optionDetalhes === "Sim") {
        $(this).css({
            "color": "black",
            "font-weight": "bold",
            "border": "2px solid green",
            "background-color": "#d8b4fe"
        });
    } else {
        $(this).val("").css(baseCss);
    }
});

// Toggle campos dependentes
$(function() {
    $('#medicacaoDet').hide();
    $('#medic_alto_custo_det').on('change', function() {
        ($(this).val() === 's') ? $('#medicacaoDet').show(): $('#medicacaoDet').hide();
    });

    $('#atb').hide();
    $('#atb_det').on('change', function() {
        ($(this).val() === 's') ? $('#atb').show(): $('#atb').hide();
    });

    $('#div-oxig').hide();
    $('#oxig_det').on('change', function() {
        ($('#oxig_det').val() === 'Cateter' || $('#oxig_det').val() === 'Mascara') ? $('#div-oxig')
            .show(): $('#div-oxig').hide();
    });
});

// Mostrar UTI se acomodação == UTI
document.getElementById("acomodacao_int").addEventListener("change", function() {
    const divUti = document.querySelector("#container-uti");
    if (divUti) divUti.style.display = (this.value === "UTI") ? "block" : "none";
});

// Checar se paciente já está internado
function teste() {
    event.preventDefault();
    $.ajax({
        url: "check_internacao.php",
        type: "POST",
        data: {
            id_paciente: $('#fk_paciente_int').val()
        },
        success: function(result) {
            const alert_div = document.getElementById('alert_intern');
            (String(result) == '1') ? alert_div.style.display = "block": alert_div.style.display = "none";
        }
    });
}

// Validação de datas
document.getElementById("data_intern_int").addEventListener("blur", function() {
    const input = this;
    const dataInternacao = new Date(input.value);
    const dataHoje = new Date();
    const erroDiv = document.getElementById("erro-data-internacao");

    erroDiv.style.display = "none";
    erroDiv.textContent = "";
    if (!input.value) return;
    const dataFormatadaHoje = dataHoje.toISOString().split("T")[0];

    if (input.value > dataFormatadaHoje) {
        erroDiv.textContent = "A data da internação não pode ser maior que a data atual.";
        erroDiv.style.display = "block";
        input.value = "";
        return setTimeout(() => {
            erroDiv.style.display = "none";
            erroDiv.textContent = "";
        }, 5000);
    }

    const diffDias = (dataHoje - dataInternacao) / (1000 * 60 * 60 * 24);
    if (diffDias > 30) {
        erroDiv.textContent = "Deseja prorrogar acima de 30 dias?";
        erroDiv.style.display = "block";
        setTimeout(() => {
            erroDiv.style.display = "none";
            erroDiv.textContent = "";
        }, 7000);
    }
});

document.getElementById("data_visita_int").addEventListener("change", function() {
    const dataInternacao = new Date(document.getElementById("data_intern_int").value);
    const dataVisita = new Date(this.value);
    const hoje = new Date();
    const seteDiasDepois = new Date();
    seteDiasDepois.setDate(hoje.getDate() + 7);
    const errorMessage = document.getElementById("error-message");
    errorMessage.style.display = "none";
    errorMessage.textContent = "";

    if (document.getElementById("data_intern_int").value && dataVisita < dataInternacao) {
        errorMessage.textContent = "A data da visita não pode ser menor que a data de internação.";
        return errorMessage.style.display = "block";
    }
    if (dataVisita > seteDiasDepois) {
        errorMessage.textContent = "A data da visita não pode ser maior que 7 dias da data atual.";
        errorMessage.style.display = "block";
    }
});

// Internação pertinente (quando tipo = Urgência)
document.getElementById("tipo_admissao_int").addEventListener("change", function() {
    const tipo = this.value;
    const divPertinente = document.getElementById("div_int_pertinente_int");
    const divRelPertinente = document.getElementById("div_rel_pertinente_int");
    divPertinente.style.display = "none";
    divRelPertinente.style.display = "none";
    if (tipo === "Urgência") {
        divPertinente.style.display = "block";
        document.getElementById("int_pertinente_int").addEventListener("change", function() {
            divRelPertinente.style.display = (this.value === "n") ? "block" : "none";
        }, {
            once: true
        });
    }
});

// JSON de antecedentes
document.getElementById('fk_patologia2').addEventListener('change', function() {
    const selectedOptions = Array.from(this.selectedOptions).map(o => parseInt(o.value, 10));
    const fkPaciente = parseInt(document.getElementById('fk_paciente_int').value || '0', 10);
    const fkInternacao = parseInt(document.getElementById('id_internacao').value || '0', 10);
    const jsonAntecedentes = selectedOptions.map(idAntecedente => ({
        fk_id_paciente: fkPaciente,
        fk_internacao_ant_int: fkInternacao + 1,
        intern_antec_ant_int: idAntecedente
    }));
    document.getElementById('json-antec').value = JSON.stringify(jsonAntecedentes);
});

// Mostrar/ocultar campos de alta conforme "Internado"
document.addEventListener("DOMContentLoaded", function() {
    const selectInternado = document.getElementById("internado_int");
    const divDataAlta = document.getElementById("div-data-alta");
    const divMotivoAlta = document.getElementById("div-motivo-alta");

    function toggleDataAlta() {
        if (selectInternado.value === "s") {
            divDataAlta.style.display = "none";
            divMotivoAlta.style.display = "none";
            document.getElementById("data_alta_alt").value = "";
            document.getElementById("tipo_alta_alt").value = "";
        } else {
            divDataAlta.style.display = "block";
            divMotivoAlta.style.display = "block";
        }
    }
    toggleDataAlta();
    selectInternado.addEventListener("change", toggleDataAlta);
});

// Chat toggle
document.getElementById("chat-header").addEventListener("click", function() {
    const chatBody = document.getElementById("chat-body");
    chatBody.style.display = (chatBody.style.display === "none") ? "block" : "none";
});
document.getElementById("chat-send").addEventListener("click", function() {
    const inputField = document.getElementById("chat-input");
    const message = (inputField.value || "").trim();
    if (!message) return;
    const messagesDiv = document.getElementById("chat-messages");
    fetch("diversos/chatgpt_handler.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                message
            })
        })
        .then(r => r.json())
        .then(data => {
            const botMessage = document.createElement("div");
            botMessage.style.color = "green";
            botMessage.textContent = "Bot: " + (data.reply || "Sem resposta");
            messagesDiv.appendChild(botMessage);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        })
        .catch(() => {
            const errorMessage = document.createElement("div");
            errorMessage.style.color = "red";
            errorMessage.textContent = "Erro ao conectar com o bot.";
            messagesDiv.appendChild(errorMessage);
        });
    inputField.value = "";
});

/* ==========================================================
   CADASTRO CENTRAL — LÓGICA ÚNICA (sem duplicações)
   Regras:
   - fk_usuario_int = ID do responsável selecionado
   - visita_med_int / visita_enf_int = 's' / 'n' conforme tipo
   - visita_auditor_prof_med = SEMPRE o ID (espelhado de fk_usuario_int) SE tipo != 'enf'; caso 'enf', fica vazio
   - visita_auditor_prof_enf não é usado (fica vazio)
   ========================================================== */
function mirrorVisitMedFromFk() {
    const fk = document.getElementById('fk_usuario_int')?.value || '';
    const tipo = document.getElementById('resp_tipo')?.value || '';
    const medHidden = document.getElementById('visita_auditor_prof_med');
    if (!medHidden) return;
    medHidden.value = (tipo === 'enf') ? '' : fk;
}
document.addEventListener('DOMContentLoaded', mirrorVisitMedFromFk);

(function() {
    const respTipo = document.getElementById('resp_tipo');
    const boxMed = document.getElementById('box_resp_med');
    const boxEnf = document.getElementById('box_resp_enf');
    const selMed = document.getElementById('resp_med_id');
    const selEnf = document.getElementById('resp_enf_id');

    const fkUsuario = document.getElementById('fk_usuario_int');
    const flgMed = document.getElementById('visita_med_int');
    const flgEnf = document.getElementById('visita_enf_int');
    const emailMed = document.getElementById('visita_auditor_prof_med'); // usado para ID do médico responsável
    const emailEnf = document.getElementById('visita_auditor_prof_enf'); // não utilizado (mantém vazio)

    const idSessao = "<?= htmlspecialchars($idSessao) ?>";
    const cargoSessao = "<?= addslashes($cargoSessao) ?>";

    function refreshPicker(el) {
        if (window.$ && $.fn.selectpicker && el && $(el).hasClass('selectpicker')) {
            $(el).selectpicker('refresh');
        }
    }

    function hide(el) {
        if (el) {
            el.classList.add('d-none');
            el.hidden = true;
            el.style.display = '';
            refreshPicker(el.querySelector('select') || el);
        }
    }

    function show(el) {
        if (el) {
            el.classList.remove('d-none');
            el.hidden = false;
            el.style.display = '';
            refreshPicker(el.querySelector('select') || el);
        }
    }

    function resetToSessionUser() {
        if (!fkUsuario) return;
        fkUsuario.value = idSessao || '';
        if (flgMed) flgMed.value = (cargoSessao === 'Med_auditor') ? 's' : 'n';
        if (flgEnf) flgEnf.value = (cargoSessao === 'Enf_Auditor') ? 's' : 'n';
        if (emailMed) emailMed.value = ''; // será setado por mirrorVisitMedFromFk
        if (emailEnf) emailEnf.value = '';
        mirrorVisitMedFromFk();
    }

    // inicia oculto
    hide(boxMed);
    hide(boxEnf);
    resetToSessionUser();

    respTipo?.addEventListener('change', function() {
        const v = this.value;
        if (selMed) selMed.value = '';
        if (selEnf) selEnf.value = '';
        if (flgMed) flgMed.value = 'n';
        if (flgEnf) flgEnf.value = 'n';
        if (emailMed) emailMed.value = '';
        if (emailEnf) emailEnf.value = '';
        if (fkUsuario) fkUsuario.value = idSessao;

        hide(boxMed);
        hide(boxEnf);
        if (v === 'med') {
            show(boxMed);
            refreshPicker(selMed);
            if (flgMed) flgMed.value = 's';
        }
        if (v === 'enf') {
            show(boxEnf);
            refreshPicker(selEnf);
            if (flgEnf) flgEnf.value = 's';
        }
        mirrorVisitMedFromFk();
    });

    selMed?.addEventListener('change', function() {
        const opt = this.selectedOptions[0];
        if (!opt?.value) {
            resetToSessionUser();
            return;
        }
        if (fkUsuario) fkUsuario.value = opt.value;
        if (flgMed) flgMed.value = 's';
        if (flgEnf) flgEnf.value = 'n';
        if (emailEnf) emailEnf.value = '';
        mirrorVisitMedFromFk();
    });

    selEnf?.addEventListener('change', function() {
        const opt = this.selectedOptions[0];
        if (!opt?.value) {
            resetToSessionUser();
            return;
        }
        if (fkUsuario) fkUsuario.value = opt.value;
        if (flgMed) flgMed.value = 'n';
        if (flgEnf) flgEnf.value = 's';
        if (emailMed) emailMed.value = ''; // tipo enf → campo do médico fica vazio
        if (emailEnf) emailEnf.value = '';
        mirrorVisitMedFromFk();
    });
})();

// SUBMIT AJAX
// formulario ajax para envio form sem refresh
$("#myForm").submit(function(event) {
    event.preventDefault(); // Impede o envio tradicional do formulário
    let post_url = $(this).attr("action"); // Obtém a URL de ação do formulário
    let request_method = $(this).attr("method"); // Obtém o método do formulário (GET/POST)
    let form_data = new FormData(this); // Cria um objeto FormData com os dados do formulário


    // 1. Salva o valor selecionado do select de hospitais
    const hospitalSelected = document.getElementById("hospital_selected").value;

    // 1.A. Validação do Hospital
    if (hospitalSelected === "") {
        // Usa a div de alerta existente para exibir o erro
        $('#alert').removeClass("alert-success").addClass("alert-danger");
        $('#alert').fadeIn().html("<b>Erro:</b> O campo Hospital é obrigatório.");

        // --- INÍCIO DA ALTERAÇÃO ---
        // Adiciona borda vermelha para indicar erro no campo
        $("#hospital_selected").css("border", "2px solid red");
        // --- FIM DA ALTERAÇÃO ---

        // Oculta a mensagem após 3 segundos
        setTimeout(function() {
            $('#alert').fadeOut('Slow');
        }, 3000);

        // Impede a execução do AJAX
        return;
    }

    // (Opcional, mas bom) Se passou na validação, garante que a borda não esteja vermelha
    // A função myFunctionSelected já deve ter deixado verde se um valor foi selecionado.
    // Esta linha é uma segurança extra caso algum cenário não dispare o 'onchange'.
    // Se a borda já for verde (ou padrão), não fará mal.
    if ($("#hospital_selected").css("border-color") === "rgb(255, 0, 0)") { // Verifica se a cor é vermelho
        $("#hospital_selected").css("border", "2px solid green"); // Muda para verde se estava vermelha
    }


    $.ajax({
        url: post_url,
        type: request_method,
        processData: false, // Impede o jQuery de processar os dados
        contentType: false, // Impede o jQuery de definir o contentType
        data: form_data,
        success: function(result) {

            if (3 < 4) { // Assumindo que esta condição é para sucesso (ajuste se necessário)

                // Increment the reg_int value
                const regIntInput = $("#RegInt");
                const currentRegInt = parseInt(regIntInput.val());
                const newRegInt = currentRegInt + 1;

                regIntInput.val(newRegInt);

                // . Success alert
                $('#alert').removeClass("alert-danger").addClass("alert-success");
                $('#alert').fadeIn().html("Cadastrado com sucesso");
                setTimeout(function() {
                    $('#alert').fadeOut('Slow');
                }, 3000);

                // 2. Resetando os campos de input, select e textarea EXCETO os campos `hidden` e o select do hospital
                document.querySelectorAll('input, select, textarea').forEach((element) => {
                    if (element.type !== "hidden" && element.id !== "hospital_selected") {
                        element.value = '';
                    }
                });

                // 3. Restaura o valor selecionado do select de hospitais (já feito antes do AJAX)
                // document.getElementById("hospital_selected").value = hospitalSelected; // Não precisa redefinir aqui

                // 4. Atualiza outros selects (exceto o de hospitais)
                $('#fk_paciente_int').val('').selectpicker('refresh');
                $('#fk_patologia2').val('').selectpicker('refresh');
                $('#fk_patologia_int').val('').selectpicker('refresh');

                // 5. Update other values
                const adicionarValor = parseInt(document.querySelector("#proximoId_int")
                    .textContent) + 1;
                const ultimoReg = <?= $ultimoReg ?>;
                const novoValorInternacao = parseInt(ultimoReg) + adicionarValor;

                $("#proximoId_int").text(adicionarValor);
                $("#proximoId_int").val(
                    novoValorInternacao); // Este seletor estava incorreto, corrigido para val()

                // $("#RegInt").val(newRegInt); // Já atualizado acima
                $("#fk_int_tuss").val(novoValorInternacao);
                $("#fk_internacao_uti").val(novoValorInternacao);
                $("#fk_id_int").val(novoValorInternacao);
                $("#fk_internacao_pror").val(novoValorInternacao);
                $("#fk_internacao_ges").val(novoValorInternacao);
                $("#fk_int_det").val(novoValorInternacao);
                document.getElementById("internado_int").value = "s";
                document.getElementById("internado_int").querySelector("option[value='s']")
                    .selected = true;

                // 6. Hide containers
                const containers = [
                    "#container-gestao",
                    "#container-tuss",
                    "#container-prorrog",
                    "#container-uti",
                    "#container-negoc",
                    "#div-detalhado"
                ];
                containers.forEach((container) => {
                    document.querySelector(container).style.display = "none";
                });

                // 7. Restaura a borda dos selects após o reset (exceto o de hospitais)
                document.querySelectorAll(
                    "#select_tuss, #select_gestao, #relatorio-detalhado, #select_prorrog, #select_uti, #select_negoc, select" // Removido 'select' genérico para evitar redefinir o hospital
                ).forEach(select => {
                    if (select.id !==
                        "hospital_selected") { // Garante que não afeta o select de hospital
                        select.value = ""; // Reseta o valor do select
                        select.style.border = "1px solid #ced4da"; // Borda padrão Bootstrap
                        select.style.color =
                            "#6c757d"; // Cor padrão Bootstrap para placeholder
                        select.style.fontWeight = "normal";
                        select.style.backgroundColor = "#fff"; // Fundo padrão
                    }
                });
                // Especificamente resetar os selects roxos para o estilo padrão deles
                $('.select-purple').css({
                    "color": "white",
                    "font-weight": "normal",
                    "border": "1px solid #5e2363",
                    "background-color": "#5e2363"
                });


                // 8. Atualiza selects que usam Bootstrap Select (exceto o de hospitais)
                // Já feito acima para paciente, patologia, etc. O reset dos selects roxos não usa selectpicker.


                // 9. Success alert (já feito no início do success)
                // $('#alert').removeClass("alert-danger").addClass("alert-success"); ...


            } else if (result == '0') {

                $('#alert').removeClass("alert-success").addClass("alert-danger");
                $('#alert').fadeIn().html("Paciente possui internação ativa");
                setTimeout(function() {
                    $('#alert').fadeOut('Slow');
                }, 2000);
            }

            // Clear additional fields
            clearTussInputs();
            clearProrrogInputs();

        },

        error: function(xhr, status, error) {
            console.error("AJAX Error:", status, error);
        }
    });
});

// Prorrogação: mostra container quando "s"
document.addEventListener("DOMContentLoaded", function() {
    const selectProrrog = document.getElementById("select_prorrog");
    const containerProrrog = document.getElementById("container-prorrog");
    if (selectProrrog && containerProrrog) {
        function toggleProrrog() {
            containerProrrog.style.display = (selectProrrog.value === "s") ? "block" : "none";
        }
        selectProrrog.addEventListener("change", toggleProrrog);
        toggleProrrog();
    }
});

// Carregar acomodações via hospital (para negociações/savings)
$(document).ready(function() {
    $('#hospital_selected').on('change', function() {
        const id_hospital = $(this).val();
        if (!id_hospital) return;
        fetchAcomodacoes(id_hospital);
    });

    function fetchAcomodacoes(id_hospital) {
        $.ajax({
            url: 'process_acomodacao.php',
            type: 'POST',
            dataType: 'json',
            data: {
                id_hospital
            },
            success: function(response) {
                if (response.status === 'success') populateSelects(response.acomodacoes);
                else console.error("Erro recebido do servidor:", response.message);
            },
            error: function(xhr, status, error) {
                console.error("Erro na requisição AJAX:", error, "Status:", status, "Resposta:", xhr
                    .responseText);
            },
        });
    }

    function populateSelects(acomodacoes) {
        let options = '<option value="">Selecione a Acomodação</option>';
        acomodacoes.forEach(ac => {
            options +=
                `<option value="${ac.id_acomodacao}-${ac.acomodacao_aco}" data-valor="${ac.valor_aco}">${ac.acomodacao_aco}</option>`;
        });
        $('select[name="troca_de"]').html(options);
        $('select[name="troca_para"]').html(options);
        $('input[name="saving"]').val('');
        $('input[name="qtd"]').val('');
        $('input[name="saving_show"]').val('').css('color', '');
    }

    $(document).on('change keyup', 'select[name="troca_de"], select[name="troca_para"], input[name="qtd"]',
        function() {
            calculateSavings($(this).closest('.negotiation-field-container'));
        });

    function calculateSavings(container) {
        const trocaDeOption = container.find('select[name="troca_de"] option:selected');
        const trocaParaOption = container.find('select[name="troca_para"] option:selected');
        const quantidadeInput = container.find('input[name="qtd"]');
        const trocaDeValor = parseFloat(trocaDeOption.attr('data-valor')) || 0;
        const trocaParaValor = parseFloat(trocaParaOption.attr('data-valor')) || 0;
        const quantidade = parseInt(quantidadeInput.val(), 10) || 0;

        if (isNaN(trocaDeValor) || isNaN(trocaParaValor) || isNaN(quantidade)) {
            container.find('input[name="saving"]').val('');
            container.find('input[name="saving_show"]').val('').css('color', '');
            return;
        }
        const saving = (trocaDeValor - trocaParaValor) * quantidade;
        container.find('input[name="saving"]').val(saving.toFixed(2));
        container.find('input[name="saving_show"]').val(
            saving >= 0 ? `R$ ${saving.toFixed(2)}` : `-R$ ${Math.abs(saving).toFixed(2)}`
        ).css('color', saving >= 0 ? 'green' : 'red');
    }
});

// Segurança extra: antes de enviar, se houver auditor selecionado em algum anexo, marca "em auditoria"
(function() {
    const fkAudMed = document.getElementById('fk_id_aud_med');
    const fkAudEnf = document.getElementById('fk_id_aud_enf');
    const aberto = document.getElementById('aberto_cap');
    const emAud = document.getElementById('em_auditoria_cap');

    document.getElementById('myForm')?.addEventListener('submit', function() {
        const temMed = fkAudMed && fkAudMed.value;
        const temEnf = fkAudEnf && fkAudEnf.value;
        if (temMed || temEnf) {
            if (aberto) aberto.value = 'n';
            if (emAud) emAud.value = 's';
        }
    });
})();

function teste(evt) {
    if (evt && typeof evt.preventDefault === 'function') evt.preventDefault();

    $.ajax({
        url: "check_internacao.php",
        type: "POST",
        data: {
            id_paciente: $('#fk_paciente_int').val()
        },
        success: function(result) {
            const alert_div = document.getElementById('alert_intern');
            // garante comparação segura
            const ativo = String(result).trim() === '1';
            alert_div.style.display = ativo ? "block" : "none";
        },
        error: function(xhr, status, err) {
            console.error("Erro ao checar internação:", status, err, xhr.responseText);
        }
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>

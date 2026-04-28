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
    if (!is_array($cids)) {
        $cids = [];
    }
    usort($cids, function ($a, $b) {
        $catA = strtoupper(trim((string) ($a['cat'] ?? '')));
        $catB = strtoupper(trim((string) ($b['cat'] ?? '')));
        if ($catA !== $catB) {
            return strcmp($catA, $catB);
        }

        $descA = strtoupper(trim((string) ($a['descricao'] ?? '')));
        $descB = strtoupper(trim((string) ($b['descricao'] ?? '')));
        return strcmp($descA, $descB);
    });

    // ...
    $id_paciente_get = filter_input(INPUT_GET, 'id_paciente', FILTER_VALIDATE_INT) ?: 0;
    // ...
    $pacientePrefill = null;

    /* === UsuarioDAO: usar somente findMedicosEnfermeiros() === */
    include_once("dao/usuarioDao.php");
    $usuarioDao = new userDAO($conn, $BASE_URL);

    // === Recupera o último ID de internação sem depender de método ultimoId() ===
    if (!isset($ultimoReg)) {
        $ultimoReg = 0;
        try {
            $stmt = $conn->query("SELECT MAX(id_internacao) AS max_id FROM internacao");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            $ultimoReg = isset($row['max_id']) ? (int) $row['max_id'] : 0;
        } catch (Throwable $e) {
            // se der erro, mantém 0 (primeiro registro)
            $ultimoReg = 0;
        }
    }

    /* === DAOs auxiliares / util === */
    $Internacao_geral = new internacaoDAO($conn, $BASE_URL);
    $acomodacaoDao = new acomodacaoDAO($conn, $BASE_URL);
    $acomodacao = $acomodacaoDao->findGeral();

    /* === Sessão === */
    $idSessao = $_SESSION["id_usuario"] ?? '';
    $cargoSessao = $_SESSION['cargo'] ?? ($_SESSION['cargo_user'] ?? '');
    $emailSessao = $_SESSION['email_user'] ?? '';
    $nivelSessaoRaw = (string) ($_SESSION['nivel'] ?? '');
    $nivelSessaoInt = (int) $nivelSessaoRaw;
    $normCargoSessao = mb_strtolower(str_replace([' ', '-'], '_', (string) $cargoSessao), 'UTF-8');
    $isMedOuEnf = in_array($normCargoSessao, ['med_auditor', 'medico_auditor', 'enf_auditor', 'enfer_auditor'], true);
    $cargoSessaoLower = mb_strtolower((string) $cargoSessao, 'UTF-8');
    $isDiretorSessao = (mb_stripos($cargoSessaoLower, 'diretor') !== false)
        || (mb_stripos($cargoSessaoLower, 'diretoria') !== false)
        || in_array($nivelSessaoRaw, ['1', '-1'], true);
    $isCadastroCentralUser = (mb_stripos($cargoSessaoLower, 'analista') !== false);
    $cadastroCentralObrigatorio = $isDiretorSessao || $isCadastroCentralUser;
    $mostrarCadastroCentral = $cadastroCentralObrigatorio || !$isMedOuEnf;

    $dataAtual = date('Y-m-d');
    $agora = date('Y-m-d');
    $agoraLanc = date('Y-m-d\TH:i');

    /* ==========================================================
    CONTROLE DE ACESSO POR CARGO
    ========================================================== */
    $cargo = $_SESSION['cargo'] ?? '';
    $userId = (int) ($_SESSION['id_usuario'] ?? 0);
    $rolesFiltrados = ['Med_auditor', 'Enf_Auditor', 'Adm'];
    $aplicarFiltroUsuario = in_array($cargo, $rolesFiltrados, true) ? $userId : null;

    /* === AUDITORES via UsuarioDAO::findMedicosEnfermeiros() === */
    $medicosAud = [];
    $enfsAud = [];
    try {
        $todos = $usuarioDao->findMedicosEnfermeiros();
        if (!is_array($todos))
            $todos = [];
        foreach ($todos as $u) {
            $id = $u['id_usuario'] ?? null;
            $nome = $u['usuario_user'] ?? null;
            $email = $u['email_user'] ?? null;
            $cargo = $u['cargo_user'] ?? '';
            if (!$id)
                continue;

            $row = [
                'id_usuario' => (int) $id,
                'usuario_user' => (string) $nome,
                'email_user' => (string) $email,
                'cargo_user' => (string) $cargo,
            ];

            $c = mb_strtoupper((string) $cargo, 'UTF-8');
            if (strpos($c, 'MED') === 0)
                $medicosAud[] = $row;
            elseif (strpos($c, 'ENF') === 0)
                $enfsAud[] = $row;
        }
    } catch (Throwable $e) {
        $medicosAud = $enfsAud = [];
    }
    if (!isset($listaHospitais) || !is_array($listaHospitais)) {
        include_once("dao/hospitalDao.php");
        include_once("dao/hospitalUserDao.php");

        $hospitalUserDao = new hospitalUserDAO($conn, $BASE_URL);
        $hospitalDao = new hospitalDAO($conn, $BASE_URL);

        $userIdSessao = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivelSessaoLista = $nivelSessaoInt;

        if ($nivelSessaoLista > 3) {
            $rawHospitais = $hospitalDao->findGeral();
        } else {
            $rawHospitais = $hospitalUserDao->listarPorUsuario($userIdSessao);
        }

        $listaHospitais = [];
        if (is_array($rawHospitais)) {
            foreach ($rawHospitais as $h) {
                $id = $h['id_hospital'] ?? $h['fk_hospital_user'] ?? null;
                $nome = trim($h['nome_hosp'] ?? '');
                if ($id && $nome) {
                    $listaHospitais[$id] = ['id_hospital' => $id, 'nome_hosp' => $nome];
                }
            }
            $listaHospitais = array_values($listaHospitais); // dedup
        }
    }

    if (!isset($hospitalUserDao) || !($hospitalUserDao instanceof hospitalUserDAO)) {
        include_once("dao/hospitalUserDao.php");
        $hospitalUserDao = new hospitalUserDAO($conn, $BASE_URL);
    }
    $hospitalUsuariosMap = [];
    try {
        $hospitalUsuariosRows = $hospitalUserDao->joinHospitalUserAll();
        if (is_array($hospitalUsuariosRows)) {
            foreach ($hospitalUsuariosRows as $hu) {
                $hid = (int) ($hu['fk_hospital_user'] ?? $hu['id_hospital'] ?? 0);
                $uid = (int) ($hu['fk_usuario_hosp'] ?? $hu['id_usuario'] ?? 0);
                if ($hid <= 0 || $uid <= 0) {
                    continue;
                }
                if (!isset($hospitalUsuariosMap[$hid])) {
                    $hospitalUsuariosMap[$hid] = [];
                }
                $hospitalUsuariosMap[$hid][$uid] = $uid;
            }
        }
    } catch (Throwable $e) {
        $hospitalUsuariosMap = [];
    }
    foreach ($hospitalUsuariosMap as $hid => $uids) {
        $hospitalUsuariosMap[$hid] = array_values(array_map('intval', array_keys($uids)));
    }

    $patientCareProgramMap = [];
    try {
        $cronicosStmt = $conn->query("
            SELECT fk_paciente,
                   GROUP_CONCAT(DISTINCT condicao ORDER BY condicao SEPARATOR ', ') AS condicoes
              FROM tb_paciente_cronico
             GROUP BY fk_paciente
        ");
        foreach (($cronicosStmt ? $cronicosStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
            $pacId = (int)($row['fk_paciente'] ?? 0);
            if ($pacId <= 0) {
                continue;
            }
            $patientCareProgramMap[$pacId] = [
                'programas' => ['Gestão de Crônicos'],
                'condicoes' => trim((string)($row['condicoes'] ?? '')),
            ];
        }

    } catch (Throwable $e) {
        $patientCareProgramMap = [];
    }
    ?>
    <link href="<?= $BASE_URL ?>css/style.css" rel="stylesheet">
    <link href="<?= $BASE_URL ?>css/form_cad_internacao.css?v=<?= filemtime(__DIR__ . '/../css/form_cad_internacao.css') ?>" rel="stylesheet">
    <style>
        .assist-select-clear {
            position: relative;
        }

        .assist-select-clear .bootstrap-select,
        .assist-select-clear > select {
            width: 100% !important;
        }

        .assist-select-clear .bootstrap-select > .dropdown-toggle {
            padding-right: 34px !important;
        }

        .assist-clear-btn {
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            z-index: 4;
            width: 18px;
            height: 18px;
            border: 0;
            border-radius: 999px;
            background: rgba(94, 35, 99, 0.10);
            color: #5e2363;
            font-size: 12px;
            font-weight: 700;
            line-height: 18px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .assist-select-clear:not(.picker-ready) .assist-clear-btn {
            opacity: 0;
            pointer-events: none;
        }

        .assist-clear-btn:hover {
            background: rgba(94, 35, 99, 0.18);
            color: #4b1850;
        }

        .assistenciais-row-full {
            display: grid;
            grid-template-columns: 3fr 3fr 2fr 1fr 1fr 2fr;
            column-gap: 12px;
            row-gap: 4px;
            width: 100%;
            align-items: end;
        }

        @media (max-width: 991.98px) {
            .assistenciais-row-full {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .assistenciais-row-full {
                grid-template-columns: 1fr;
            }
        }
    </style>


    <div class="internacao-page">
        <div class="internacao-page__hero">
            <div class="internacao-page__hero-main">
                <p class="internacao-page__eyebrow">Fluxo assistencial</p>
                <h1>Cadastrar internação</h1>
                <p class="internacao-page__summary">Preencha primeiro os dados críticos da internação e depois complemente as informações clínicas e operacionais.</p>
            </div>
            <span class="internacao-page__tag">Campos obrigatórios em destaque</span>
        </div>
        <div class="internacao-page__content">
            <form class="visible" action="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/process_internacao.php', ENT_QUOTES, 'UTF-8') ?>" id="myForm" method="POST"
                enctype="multipart/form-data">
                <div class="internacao-card internacao-card--general">
                    <div class="internacao-card__header">
                        <div class="internacao-card__title-wrap">
                            <p class="internacao-card__eyebrow">Etapa 1</p>
                            <h2 class="internacao-card__title">Dados da internação</h2>
                        </div>
                        <span class="internacao-card__tag internacao-card__tag--critical">Campos principais</span>
                    </div>
                    <div class="internacao-card__body">
                        <div class="internacao-head-row internacao-head-grid">
                            <input type="hidden" value="" name="fk_hospital_int" id="fk_hospital_int">

                            <div class="form-group hospital-col">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="control-label mb-0" for="hospital_selected">
                                        <span style="color:red;">*</span> Hospital
                                    </label>
                                </div>
                                <div class="hospital-select-wrapper">
                                    <select onchange="myFunctionSelected()" data-size="10"
                                        class="form-control input-lg-fullcare selectpicker show-tick" id="hospital_selected"
                                        name="hospital_selected" required data-live-search="true"
                                        data-live-search-placeholder="Pesquise por Hospital" data-none-selected-text="Pesquise por Hospital"
                                        data-width="100%" data-style="input-lg-fullcare"
                                        style="font-size:1em;background-color:#fff;color:#000;">
                                        <option value=""></option>
                                        <?php if (!empty($listaHospitais)): ?>
                                            <?php foreach ($listaHospitais as $h): ?>
                                                <option value="<?= htmlspecialchars($h['id_hospital']) ?>">
                                                    <?= htmlspecialchars($h['nome_hosp']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="">Nenhum hospital disponível</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="hospital-name-slot">
                                    <div id="hospitalNomeTexto" class="hospital-name-chip"></div>
                                </div>
                            </div>

                            <div class="form-group patient-col">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="control-label mb-0" for="fk_paciente_int">
                                        <span style="color:red;">*</span> Paciente
                                    </label>
                                    <button type="button" id="patientInsightToggle" class="patient-insight-inline-btn"
                                        style="display:none;"
                                        title="Mostrar resumo do paciente" aria-expanded="false">i</button>
                                </div>
                                <select data-size="10" data-live-search="true" data-live-search-placeholder="Pesquisa por nome"
                                    data-style="input-lg-fullcare" data-width="100%"
                                    data-none-selected-text="Pesquisa por nome"
                                    class="form-control input-lg-fullcare selectpicker show-tick" id="fk_paciente_int"
                                    name="fk_paciente_int" required>
                                    <option value=""></option>
                                    <?php
                                    if (!is_array($pacientes)) {
                                        $pacientes = [];
                                    };
                                    $pacientesById = [];
                                    foreach ($pacientes as $pacienteRow) {
                                        $pacienteId = (int) ($pacienteRow["id_paciente"] ?? 0);
                                        if ($pacienteId > 0) {
                                            $pacientesById[$pacienteId] = $pacienteRow;
                                        }
                                    }
                                    $pacientes = array_values($pacientesById);
                                    usort($pacientes, function ($a, $b) {
                                        $nomeA = mb_strtolower(trim((string) ($a["nome_pac"] ?? "")), 'UTF-8');
                                        $nomeB = mb_strtolower(trim((string) ($b["nome_pac"] ?? "")), 'UTF-8');
                                        return $nomeA <=> $nomeB;
                                    });
                                    foreach ($pacientes as $paciente): ?>
                                        <?php
                                        $pacienteId = (int) ($paciente["id_paciente"] ?? 0);
                                        $matriculaPac = trim((string) ($paciente["matricula_pac"] ?? ""));
                                        $pacienteLabel = $paciente["nome_pac"];
                                        $careData = $patientCareProgramMap[$pacienteId] ?? ['programas' => [], 'condicoes' => ''];
                                        if ($id_paciente_get > 0 && $pacienteId === $id_paciente_get) {
                                            $pacientePrefill = $paciente;
                                        }
                                        ?>
                                        <option value="<?= $pacienteId ?>"
                                            <?= $id_paciente_get > 0 && $pacienteId === $id_paciente_get ? 'selected' : '' ?>
                                            data-matricula="<?= htmlspecialchars($matriculaPac) ?>"
                                            data-care-programs="<?= htmlspecialchars(json_encode(array_values($careData['programas']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                                            data-care-condicoes="<?= htmlspecialchars((string) $careData['condicoes']) ?>"
                                            data-tokens="<?= htmlspecialchars(trim((string) $paciente["nome_pac"])) ?>">
                                            <?= htmlspecialchars($pacienteLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php
                                    if ($id_paciente_get > 0 && !$pacientePrefill) {
                                        $pacientePrefillData = $pacienteDao->findById($id_paciente_get);
                                        if (is_array($pacientePrefillData) && !empty($pacientePrefillData[0])) {
                                            $pacientePrefill = $pacientePrefillData[0];
                                            $careData = $patientCareProgramMap[$id_paciente_get] ?? ['programas' => [], 'condicoes' => ''];
                                            ?>
                                            <option value="<?= (int)$id_paciente_get ?>"
                                                selected
                                                data-matricula="<?= htmlspecialchars(trim((string)($pacientePrefill['matricula_pac'] ?? ''))) ?>"
                                                data-care-programs="<?= htmlspecialchars(json_encode(array_values($careData['programas']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                                                data-care-condicoes="<?= htmlspecialchars((string)$careData['condicoes']) ?>"
                                                data-tokens="<?= htmlspecialchars(trim((string)($pacientePrefill['nome_pac'] ?? ''))) ?>">
                                                <?= htmlspecialchars((string)($pacientePrefill['nome_pac'] ?? ('Paciente #' . $id_paciente_get))) ?>
                                            </option>
                                            <?php
                                        }
                                    }
                                    ?>
                                </select>
                                <div class="patient-inline-actions">
                                    <a class="patient-inline-link"
                                        href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/pacientes/novo', ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="far fa-edit edit-icon"></i> Novo Paciente
                                    </a>
                                </div>
                                <div id="patientCareProgramAlert" class="patient-care-program-alert" style="display:none;" role="status" aria-live="polite"></div>
                                <div class="patient-insight-card" id="patientInsightCard"
                                    data-hub-base="<?= $BASE_URL ?>hub_paciente.php?id_paciente=" style="display:none;">
                                    <div class="patient-insight-header">
                                        <span class="label">Resumo do paciente</span>
                                        <a href="#" id="patientInsightHub" class="disabled" target="_blank" rel="noopener">Abrir
                                            HUB</a>
                                    </div>
                                    <div id="patientInsightBody">
                                        Selecione um paciente para visualizar o histórico resumido.
                                    </div>
                                </div>
                            </div>
                            <script>
                                (function() {
                                    if (!window.jQuery || !jQuery.fn || !jQuery.fn.selectpicker) return;
                                    ['#hospital_selected', '#fk_paciente_int'].forEach(function(sel) {
                                        var $el = jQuery(sel);
                                        if (!$el.length) return;
                                        var hasWrapper = $el.siblings('div.bootstrap-select').length > 0;
                                        if (!hasWrapper && !$el.data('selectpicker')) {
                                            $el.selectpicker();
                                        }
                                        if ($el.siblings('div.bootstrap-select').length > 1) {
                                            $el.siblings('div.bootstrap-select').slice(1).remove();
                                        }
                                        $el.addClass('bs-select-hidden');
                                        $el.attr('data-fcx-picker-locked', '1');
                                    });
                                })();
                            </script>

                            <div class="form-group essential-medium">
                                <label class="control-label" for="matricula_paciente_display">Matrícula</label>
                                <input type="text" class="form-control input-lg-fullcare" id="matricula_paciente_display"
                                    placeholder="Digite para pesquisar por matrícula" list="matricula_list"
                                    value="<?= htmlspecialchars(trim((string)($pacientePrefill['matricula_pac'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                                <datalist id="matricula_list">
                                    <?php foreach ($pacientes as $paciente): ?>
                                        <?php $matriculaPac = trim((string) ($paciente["matricula_pac"] ?? "")); ?>
                                        <?php if ($matriculaPac === '') continue; ?>
                                        <option value="<?= htmlspecialchars($matriculaPac) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="form-group essential-medium">
                                <label class="control-label" for="data_intern_int_dt"><span style="color:red;">*</span> Data
                                    Internação</label>
                                <input type="datetime-local" class="form-control input-lg-fullcare" id="data_intern_int_dt" required
                                    value="" name="data_intern_int_dt">
                                <input type="hidden" id="data_intern_int" name="data_intern_int" value="">
                                <input type="hidden" id="hora_intern_int" name="hora_intern_int" value="">
                            </div>

                            <div class="form-group essential-small">
                                <label class="control-label" for="data_lancamento_int">Data lançamento</label>
                                <input type="datetime-local" class="form-control input-lg-fullcare" id="data_lancamento_int"
                                    name="data_lancamento_int" value="<?= $agoraLanc ?>">
                            </div>

                            <div class="form-group essential-small">
                                <label for="data_visita_int">Data Visita</label>
                                <input type="date" value='<?= $dataAtual; ?>' class="form-control input-lg-fullcare" id="data_visita_int"
                                    name="data_visita_int">
                                <p id="error-message" style="color:red;display:none;font-size:.6em;"></p>
                            </div>

                            <div class="form-group essential-small">
                                <label class="control-label" for="internado_int">Internado</label>
                                <select class="input-lg-fullcare form-control" id="internado_int" name="internado_int">
                                    <option value="s">Sim</option>
                                    <option value="n">Não</option>
                                </select>
                            </div>

                            <div class="form-group essential-full mb-2" style="flex:1 1 100%;">
                                <div id="erro-data-internacao" class="alert d-none w-100 mb-0" role="alert"></div>
                            </div>

                            <div class="form-group essential-full d-none" id="alta-obrigatoria-container" style="flex:1 1 100%;">
                                <div class="alta-obrigatoria-box">
                                    <div class="alta-obrigatoria-box__title">
                                        <span style="color:red;">*</span> Alta obrigatória para internação retroativa (paciente internado em outro hospital)
                                    </div>
                                    <div class="row">
                                        <div class="form-group col-sm-3 mb-0" id="div-data-alta" style="display:none">
                                            <label class="control-label" for="data_alta_alt"> Data/Hora Alta</label>
                                            <input type="datetime-local" class="form-control input-lg-fullcare" id="data_alta_alt"
                                                name="data_alta_alt" step="60">
                                        </div>

                                        <div class="form-group col-sm-3 mb-0" id="div-motivo-alta" style="display:none">
                                            <label class="control-label" for="tipo_alta_alt"> Motivo Alta</label>
                                            <select class="form-control input-lg-fullcare" id="tipo_alta_alt" name="tipo_alta_alt">
                                                <option value="">Selecione o motivo da alta</option>
                                                <?php
                                                if (!is_array($dados_alta)) {
                                                    $dados_alta = [];
                                                };
                                                sort($dados_alta, SORT_ASC);
                                                foreach ($dados_alta as $alta): ?>
                                                    <option value="<?= htmlspecialchars($alta); ?>"><?= htmlspecialchars($alta); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group essential-full d-none" id="retroativa-container" style="flex:1 1 100%;">
                                <div id="retroativa-alert" class="retroativa-banner d-none">
                                    <i class="fa-solid fa-rotate-left"></i>
                                    <span id="retroativa-alert-text"></span>
                                </div>
                            </div>

                            <input type="hidden" id="retroativa_confirmada" name="retroativa_confirmada" value="0">

                            <input type="hidden" id="id_internacao" readonly class="form-control" name="id_internacao"
                                value="<?= $ultimoReg ?>">
                            <input type="hidden" value="s" id="primeira_vis_int" name="primeira_vis_int">
                            <input type="hidden" value="0" id="visita_no_int" name="visita_no_int">

                            <!-- Flags do responsável (atualizadas pelo JS unificado) -->
                            <input type="hidden" id="visita_enf_int" name="visita_enf_int" value="n">
                            <input type="hidden" id="visita_med_int" name="visita_med_int" value="n">
                            <input type="hidden" id="visita_auditor_prof_enf" name="visita_auditor_prof_enf" value="">
                            <input type="hidden" id="visita_auditor_prof_med" name="visita_auditor_prof_med" value="">
                            <input type="hidden" id="cad_central_obrigatorio" name="cad_central_obrigatorio"
                                value="<?= $cadastroCentralObrigatorio ? '1' : '0' ?>">
                        </div>
                    </div>
                </div>
                <input type="hidden" name="type" value="create">
                <input type="hidden" name="timer_int" id="timer_int" value="">
                <p style="display:none" id="proximoId_int">0</p>
                <input type="hidden" value="n" id="censo_int" name="censo_int">

                <!-- fk_usuario_int: padrão = usuário logado; Cadastro Central pode sobrescrever -->
                <input type="hidden" value="<?= htmlspecialchars($idSessao) ?>" id="fk_usuario_int" name="fk_usuario_int">

                <!-- ===== CADASTRO CENTRAL (só aparece se NÃO for med/enf) ===== -->
                <?php if ($mostrarCadastroCentral): ?>
                    <div id="cadastro-central-wrapper" class="internacao-card internacao-card--central">
                        <div class="internacao-card__header">
                            <div>
                                <h3 class="internacao-card__title">Responsável pela visita</h3>
                            </div>
                            <span class="internacao-card__tag">
                                <?php if ($cadastroCentralObrigatorio): ?>
                                    Obrigatório selecionar tipo e responsável
                                <?php else: ?>
                                    Opcional: escolha o responsável
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="internacao-card__body">
                            <div class="form-group row align-items-end">
                                <div class="form-group col-sm-3">
                                    <label class="control-label" for="resp_tipo">Responsável pela visita</label>
                                    <select id="resp_tipo" class="form-control input-lg-fullcare">
                                        <option value="">(sem seleção)</option>
                                        <option value="med">Médico auditor</option>
                                        <option value="enf">Enfermeiro auditor</option>
                                    </select>
                                </div>

                                <div class="form-group col-sm-4 d-none" id="box_resp_med">
                                    <label class="control-label" for="resp_med_id">Selecionar médico</label>
                                    <select id="resp_med_id" class="form-control input-lg-fullcare selectpicker"
                                        data-live-search="true" data-size="5" data-style="no-shadow-picker" title="Selecione">
                                        <option value="">Selecione</option>
                                        <?php foreach ($medicosAud as $m): ?>
                                            <option value="<?= (int) $m['id_usuario'] ?>"
                                                data-email="<?= htmlspecialchars($m['email_user'] ?? '') ?>">
                                                <?= htmlspecialchars($m['usuario_user'] ?? ('#' . $m['id_usuario'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group col-sm-5 d-none" id="box_resp_enf">
                                    <label class="control-label" for="resp_enf_id">Selecionar enfermeiro</label>
                                    <select id="resp_enf_id" class="form-control input-lg-fullcare selectpicker"
                                        data-live-search="true" data-size="5" data-style="no-shadow-picker" title="Selecione">
                                        <option value="">Selecione</option>
                                        <?php foreach ($enfsAud as $e): ?>
                                            <option value="<?= (int) $e['id_usuario'] ?>"
                                                data-email="<?= htmlspecialchars($e['email_user'] ?? '') ?>">
                                                <?= htmlspecialchars($e['usuario_user'] ?? ('#' . $e['id_usuario'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="internacao-card internacao-card--fields">
                    <div class="internacao-card__header">
                        <div class="internacao-card__title-wrap">
                            <p class="internacao-card__eyebrow">Etapa 2</p>
                            <h3 class="internacao-card__title">Dados assistenciais</h3>
                        </div>
                        <span class="internacao-card__tag">Classificação clínica</span>
                    </div>
                    <div class="internacao-card__body">
                        <div class="row">
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="acomodacao_int">Acomodação</label>
                                <select class="input-lg-fullcare form-control" id="acomodacao_int" name="acomodacao_int">
                                    <option value=""></option>
                                    <?php
                                    $dados_acomodacao = is_array($dados_acomodacao ?? null) ? $dados_acomodacao : [];
                                    sort($dados_acomodacao, SORT_ASC);
                                    foreach ($dados_acomodacao as $acomd): ?>
                                        <option value="<?= htmlspecialchars($acomd) ?>"><?= htmlspecialchars($acomd) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="especialidade_int">Especialidade</label>
                                <input list="especialidade-options" class="input-lg-fullcare form-control" id="especialidade_int"
                                    name="especialidade_int" placeholder="">
                                <datalist id="especialidade-options">
                                    <?php
                                    if (!is_array($dados_especialidade)) {
                                        $dados_especialidade = [];
                                    };
                                    sort($dados_especialidade, SORT_ASC);
                                    foreach ($dados_especialidade as $especial): ?>
                                        <option value="<?= htmlspecialchars($especial) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="form-group col-sm-3">
                                <label for="titular_int">Médico</label>
                                <input type="text" maxlength="100" class="form-control input-lg-fullcare" id="titular_int"
                                    name="titular_int">
                            </div>
                            <div class="form-group col-sm-1">
                                <label for="crm_int">CRM</label>
                                <input type="text" maxlength="10" class="form-control input-lg-fullcare" id="crm_int" name="crm_int">
                            </div>
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="modo_internacao_int">Modo Admissão</label>
                                <select class="input-lg-fullcare form-control" id="modo_internacao_int" name="modo_internacao_int">
                                    <option value=""></option>
                                    <?php
                                    if (!is_array($modo_internacao)) {
                                        $modo_internacao = [];
                                    };
                                    sort($modo_internacao, SORT_ASC);
                                    foreach ($modo_internacao as $modo):  ?>
                                        <option value="<?= htmlspecialchars($modo) ?>"><?= htmlspecialchars($modo) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            </div>
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="tipo_admissao_int">Tipo Internação</label>
                                <select class="input-lg-fullcare form-control" id="tipo_admissao_int" name="tipo_admissao_int">
                                    <option value=""></option>
                                    <option value="Eletiva">Eletiva</option>
                                    <option value="Urgência">Urgência</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group row" id="row-int-pertinente" style="display:none;">
                            <div style="display:none;" id="div_int_pertinente_int" class="form-group col-sm-2">
                                <label class="control-label" for="int_pertinente_int"><span style="color:red;">*</span> Internação
                                    pertinente?</label>
                                <select class="input-lg-fullcare form-control" id="int_pertinente_int" name="int_pertinente_int">
                                    <option value=""></option>
                                    <option value="s">Sim</option>
                                    <option value="n">Não</option>
                                </select>
                            </div>
                            <div id="div_rel_pertinente_int" style="display:none;" class="form-group col-sm-8">
                                <label for="rel_pertinente_int">Justifique não pertinência</label>
                                <textarea data-saude-autocomplete="true" style="resize:none" rows="3" class="form-control"
                                    id="rel_pertinente_int" name="rel_pertinente_int"></textarea>
                            </div>
                        </div>

                        <div class="form-group assistenciais-row-full">
                            <!-- <div class="form-group col-sm-3">
                    <label class="control-label" for="fk_patologia_int">Patologia</label>
                    <select class="input-lg-fullcare form-control selectpicker show-tick" data-size="5"
                        data-live-search="true" id="fk_patologia_int" name="fk_patologia_int">
                        <option value="">Selecione</option>
                        <?php
                        if (!is_array($patologias)) {
                            $patologias = [];
                        };
                        usort($patologias, fn($a, $b) => strcmp($a["patologia_pat"], $b["patologia_pat"]));
                        foreach ($patologias as $patologia): ?>
                        <option value="<?= (int) $patologia["id_patologia"] ?>">
                            <?= htmlspecialchars($patologia["patologia_pat"]) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div> -->
                            <div class="form-group assist-col-cid">
                                <label class="control-label" for="fk_cid_int">CID (Patologia)</label>
                                <div class="assist-select-clear">
                                    <select class="form-control selectpicker show-tick" data-size="10" id="fk_cid_int" name="fk_cid_int"
                                        data-live-search="true" data-width="100%" data-style="input-lg-fullcare">
                                        <option value="">CID</option>
                                        <?php foreach ($cids as $cid): ?>
                                            <option value="<?= $cid["id_cid"] ?>">
                                                <?= $cid['cat'] . " - " . $cid["descricao"] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="assist-clear-btn" data-clear-select="fk_cid_int" aria-label="Limpar CID">&times;</button>
                                </div>
                            </div>

                            <div class="form-group assist-col-antecedente">
                                <label class="control-label" for="fk_patologia2">Antecedente</label>
                                <div class="assist-select-clear">
                                    <select class="form-control selectpicker show-tick" data-size="10" id="fk_patologia2" name="fk_patologia2"
                                        data-live-search="true" data-width="100%" data-style="input-lg-fullcare"
                                        data-live-search-placeholder="Pesquisar">
                                        <option value="">Antecedente</option>
                                        <?php foreach ($cids as $cid): ?>
                                            <option value="<?= (int)$cid["id_cid"] ?>">
                                                <?= htmlspecialchars($cid['cat'] . " - " . $cid["descricao"]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="assist-clear-btn" data-clear-select="fk_patologia2" aria-label="Limpar antecedente">&times;</button>
                                </div>
                            </div>

                            <div class="form-group assist-col-grupo">
                                <label class="control-label" for="grupo_patologia_int">Grupo Patologia</label>
                                <select class="input-lg-fullcare form-control" id="grupo_patologia_int" name="grupo_patologia_int">
                                    <option value=""></option>
                                    <?php foreach ($dados_grupo_pat as $grupo): ?>
                                        <option value="<?= htmlspecialchars($grupo) ?>"><?= htmlspecialchars($grupo) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group assist-col-origem">
                                <label class="control-label" for="origem_int">Origem</label>
                                <select class="input-lg-fullcare form-control" id="origem_int" name="origem_int">
                                    <option value=""></option>
                                    <?php foreach ($origem as $origens): ?>
                                        <option value="<?= htmlspecialchars($origens) ?>"><?= htmlspecialchars($origens) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group assist-col-senha">
                                <label for="senha_int">Senha</label>
                                <input type="text" maxlength="20" class="form-control input-lg-fullcare" id="senha_int" name="senha_int">
                            </div>
                            <div class="form-group assist-col-atendimento">
                                <label for="num_atendimento_int">Num. Atendimento</label>
                                <input type="text" maxlength="20" class="form-control input-lg-fullcare" id="num_atendimento_int"
                                    name="num_atendimento_int">
                            </div>
                        </div>
                        <script>
                            (function() {
                                if (!window.jQuery || !jQuery.fn || !jQuery.fn.selectpicker) return;
                                ['#fk_cid_int', '#fk_patologia2'].forEach(function(sel) {
                                    var $el = jQuery(sel);
                                    if (!$el.length) return;
                                    var hasWrapper = $el.siblings('div.bootstrap-select').length > 0;
                                    if (!hasWrapper && !$el.data('selectpicker')) {
                                        $el.selectpicker();
                                    }
                                    if ($el.siblings('div.bootstrap-select').length > 1) {
                                        $el.siblings('div.bootstrap-select').slice(1).remove();
                                    }
                                    if ($el.siblings('div.bootstrap-select').length) {
                                        $el.addClass('bs-select-hidden');
                                        $el.attr('data-fcx-picker-locked', '1');
                                    }
                                });
                            })();
                        </script>

                    </div>
                </div>
        </div>

        <div class="internacao-card internacao-card--notes">
            <div class="internacao-card__header">
                <div class="internacao-card__title-wrap">
                    <p class="internacao-card__eyebrow">Etapa 3</p>
                    <h3 class="internacao-card__title">Relatórios e observações</h3>
                </div>
                <span class="internacao-card__tag">Registro clínico</span>
            </div>
            <div class="internacao-card__body">
                <div class="clinical-text-field">
                    <div class="clinical-text-field__head">
                        <label for="rel_int">Relatório da Auditoria</label>
                        <div class="clinical-text-field__actions">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-clean-text="rel_int">Limpar formatação</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-ai-improve="rel_int">Organizar com IA</button>
                        </div>
                    </div>
                    <div id="cronicos-relatorio-alert"
                        style="display:none;margin-bottom:12px;padding:12px 14px;border-radius:12px;background:linear-gradient(135deg,#fff3cd,#ffe3a3);border:1px solid #f0c36d;color:#6a4a00;box-shadow:0 8px 20px rgba(240,195,109,.18);"
                        hidden>
                        <div style="display:flex;align-items:center;gap:8px;font-weight:700;margin-bottom:4px;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Alerta de condição crônica
                        </div>
                        <p style="margin:0;line-height:1.45;">
                            Foram identificados termos compatíveis com doenças crônicas no relatório:
                            <strong data-role="matched-list"></strong>.
                        </p>
                        <p style="margin:4px 0 0;line-height:1.45;" data-role="auto-note"></p>
                    </div>
                    <textarea data-saude-autocomplete="true" maxlength="5000" style="resize:none" rows="2"
                        onclick="aumentarText('rel_int')" class="form-control" id="rel_int" name="rel_int"></textarea>
                    <div class="d-flex justify-content-end mt-1">
                        <small class="text-muted" data-counter-for="rel_int">0/5000</small>
                    </div>
                </div>

                <!-- Chat Widget -->
                <!-- <div id="chat-widget" style="position: fixed; bottom: 20px; right: 20px; width: 300px; z-index: 9999;">
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
                    </div> -->

                <div class="clinical-text-field">
                    <div class="clinical-text-field__head">
                        <label for="acoes_int">Ações da Auditoria</label>
                        <div class="clinical-text-field__actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-clean-text="acoes_int">Limpar formatação</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-ai-improve="acoes_int">Organizar com IA</button>
                        </div>
                    </div>
                    <textarea data-saude-autocomplete="true" rows="2" style="resize:none"
                        onclick="aumentarText('acoes_int')" class="form-control" maxlength="5000" id="acoes_int"
                        name="acoes_int"></textarea>
                    <div class="d-flex justify-content-end mt-1">
                        <small class="text-muted" data-counter-for="acoes_int">0/5000</small>
                    </div>
                </div>

                <div class="clinical-text-field">
                    <div class="clinical-text-field__head">
                        <label for="programacao_int">Programação Terapêutica</label>
                        <div class="clinical-text-field__actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-clean-text="programacao_int">Limpar formatação</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-ai-improve="programacao_int">Organizar com IA</button>
                        </div>
                    </div>
                    <textarea data-saude-autocomplete="true" style="resize:none" maxlength="5000" rows="2"
                        onclick="aumentarText('programacao_int')" class="form-control" id="programacao_int"
                        name="programacao_int"></textarea>
                    <div class="d-flex justify-content-end mt-1">
                        <small class="text-muted" data-counter-for="programacao_int">0/5000</small>
                    </div>
                </div>

                <div class="ia-highlight-box">
                    <div class="ia-highlight-box__header">
                        <div class="ia-highlight-box__title-wrap">
                            <div>
                                <p class="ia-highlight-box__eyebrow">Inteligência Artificial</p>
                                <h3 class="ia-highlight-box__title">Assistente de parecer clínico</h3>
                            </div>
                            <span class="parecer-ia-powered">
                                <i class="bi bi-stars"></i>
                                IA conectada
                            </span>
                        </div>
                        <div class="auditoria-actions auditoria-actions--ia">
                            <input type="file" id="pdf-auditoria-input" accept="application/pdf,.pdf,image/png,image/jpeg,image/jpg,.png,.jpg,.jpeg" hidden>
                            <button type="button" class="btn btn-sm btn-outline-secondary auditoria-action-btn" id="btn-ler-pdf-auditoria">
                                <i class="bi bi-file-earmark-pdf"></i>
                                LER PDF/IMAGEM
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary auditoria-action-btn" id="btn-checklist-auditoria">
                                <i class="bi bi-card-checklist"></i>
                                Checklist Auditoria
                            </button>
                            <button type="button" class="btn btn-sm btn-primary auditoria-action-btn" id="btn-executar-prompt-uti">
                                <i class="bi bi-cpu"></i>
                                Executar Prompt UTI
                            </button>
                        </div>
                    </div>
                    <div class="parecer-ia-card">
                        <div class="parecer-ia-card__header">
                            <div class="parecer-ia-title-wrap">
                                <h4>Parecer IA</h4>
                            </div>
                            <button type="button" class="parecer-ia-toggle" id="btn-toggle-parecer-ia" aria-expanded="false" aria-controls="parecer-ia-body">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>
                        <div id="parecer-ia-status" class="parecer-ia-status" hidden></div>
                        <div class="parecer-ia-card__body" id="parecer-ia-body" hidden>
                            <div id="parecer-ia-content" class="parecer-ia-content">
                                <p class="parecer-ia-empty">Nenhum parecer gerado.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

            <div class="tabelas-adicionais-card">
                <div class="tabelas-adicionais-card__header" style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid rgba(111,69,162,.10);">
                    <div>
                        <p class="tabelas-adicionais-card__eyebrow" style="margin:0;text-transform:uppercase;letter-spacing:.18em;font-size:.72rem;font-weight:800;line-height:1.2;color:#6e4a96;">Tabelas adicionais</p>
                        <h3 class="tabelas-adicionais-card__title" style="margin:2px 0 0;font-size:1.22rem;font-weight:800;color:#2a1b43;">Complementos da visita</h3>
                    </div>
                </div>

            <div class="tabelas-selects d-flex flex-wrap justify-content-between align-items-end">
                <div class="form-group tabelas-col">
                    <label class="control-label" style="font-weight: bold;" for="relatorio-detalhado">Relatório detalhado</label>
                    <select class="input-lg-fullcare form-control detail-select" id="relatorio-detalhado" name="relatorio-detalhado">
                        <option value="">Selecione</option>
                        <option value="s">Sim</option>
                        <option value="n">Não</option>
                    </select>
                </div>
                <?php if ($cargoSessao === 'Med_auditor' || $cargoSessao === 'Diretoria') { ?>
                    <div class="form-group tabelas-col">
                        <label class="control-label" style="font-weight: bold;" for="select_tuss">Tuss</label>
                        <select class="input-lg-fullcare form-control select-purple" id="select_tuss" name="select_tuss">
                            <option value="">Selecione</option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                    <div class="form-group tabelas-col">
                        <label class="control-label" style="font-weight: bold;" for="select_prorrog">Prorrogação</label>
                        <select class="input-lg-fullcare form-control select-purple" id="select_prorrog"
                            name="select_prorrog">
                            <option value="">Selecione</option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                <?php } ?>

                <div class="form-group tabelas-col">
                    <label class="control-label" style="font-weight: bold;" for="select_gestao">Gestão Assistencial</label>
                    <select class="input-lg-fullcare form-control select-purple" id="select_gestao" name="select_gestao">
                        <option value="">Selecione</option>
                        <option value="s">Sim</option>
                        <option value="n">Não</option>
                    </select>
                </div>

                <div class="form-group tabelas-col">
                    <label class="control-label" style="font-weight: bold;" for="select_uti">UTI</label>
                    <select class="input-lg-fullcare form-control select-purple" id="select_uti" name="select_uti">
                        <option value="">Selecione</option>
                        <option value="s">Sim</option>
                        <option value="n">Não</option>
                    </select>
                </div>

                <?php if ($cargoSessao === 'Med_auditor' || $cargoSessao === 'Diretoria') { ?>
                    <div class="form-group tabelas-col">
                        <label class="control-label" style="font-weight: bold;" for="select_negoc">Negociações</label>
                        <select class="input-lg-fullcare form-control select-purple" id="select_negoc" name="select_negoc">
                            <option value="">Selecione</option>
                            <option value="s">Sim</option>
                            <option value="n">Não</option>
                        </select>
                    </div>
                <?php } ?>
                </div>

                <?php include_once('formularios/form_cad_internacao_detalhes.php'); ?>
                <?php include_once('formularios/form_cad_internacao_tuss.php'); ?>
                <?php include_once('formularios/form_cad_internacao_gestao.php'); ?>
                <?php include_once('formularios/form_cad_internacao_uti.php'); ?>
                <?php include_once('formularios/form_cad_internacao_prorrog.php'); ?>
                <?php include_once('formularios/form_cad_internacao_negoc.php'); ?>
            </div>

        <input type="hidden" class="form-control" value="<?= ($ultimoReg + 1) ?>" id="fk_int_capeante"
            name="fk_int_capeante">
        <input type="hidden" class="form-control" value="n" id="encerrado_cap" name="encerrado_cap">
        <input type="hidden" class="form-control" value="s" id="aberto_cap" name="aberto_cap">
        <input type="hidden" class="form-control" value="n" id="em_auditoria_cap" name="em_auditoria_cap">
        <input type="hidden" class="form-control" value="n" id="senha_finalizada" name="senha_finalizada">

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
            <!-- ... dentro do <form id="myForm"> ... -->
            <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                <small id="autosave-status" class="text-muted">Rascunho automático: ativo</small>
                <button type="button" id="btn-clear-draft" class="btn btn-sm btn-outline-secondary">Limpar rascunho</button>
            </div>

            <button type="submit" class="btn btn-success btn-lg fixed-submit btn-submit-standard">
                <i class="fas fa-save edit-icon" style="font-size:1rem;margin-right:8px;"></i>
                Cadastrar
            </button>


            <br><br>
            <div style="width:500px;display:none" class="alert" id="alert" role="alert"></div>
        </div>
        </form>
    </div>
    </div>
    <!-- Modal retroativa -->
    <div class="modal fade" id="modalInternacaoAtiva" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-warning">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        Paciente já internado
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>
                        Paciente internado no <strong id="modalInternacaoHospital">—</strong> desde
                        <strong id="modalInternacaoData">—</strong>.
                    </p>
                    <p class="mb-0">
                        Deseja registrar uma nova internação retroativa? Ela deve ser salva já com a alta informada.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-action="cancel-retroativa">Cancelar</button>
                    <button type="button" class="btn btn-primary" data-action="confirm-retroativa">Continuar</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal senha duplicada -->
    <div class="modal fade" id="modalSenhaDuplicada" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        Senha já cadastrada
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p id="modalSenhaDuplicadaTexto" class="mb-0">
                        Esta senha já está vinculada a outra internação. Informe uma senha diferente.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ok</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.hospitalUsuariosMap = <?= json_encode($hospitalUsuariosMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.formInternacaoConfig = Object.assign({}, window.formInternacaoConfig || {}, {
            baseUrl: <?= json_encode((string) $BASE_URL, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            prefillPacienteId: <?= $id_paciente_get > 0 ? (int)$id_paciente_get : 'null' ?>,
            idSessao: <?= json_encode((string) $idSessao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            cargoSessao: <?= json_encode((string) $cargoSessao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        });
    </script>
    <script src="<?= $BASE_URL ?>js/form_cad_internacao.js?v=<?= filemtime(__DIR__ . '/../js/form_cad_internacao.js') ?>"></script>
    <script src="<?= $BASE_URL ?>js/internacao_cronicos_alert.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <script src="<?= $BASE_URL ?>js/uti_audit_ai.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function syncAssistClearButtons() {
                document.querySelectorAll('.assist-select-clear').forEach(function(wrapper) {
                    var select = wrapper.querySelector('select');
                    if (!select) return;
                    var hasPicker = !!wrapper.querySelector('.bootstrap-select');
                    if (hasPicker || !select.classList.contains('selectpicker')) {
                        wrapper.classList.add('picker-ready');
                    }
                });
            }

            syncAssistClearButtons();

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.selectpicker) {
                window.jQuery('#fk_cid_int, #fk_patologia2').on('loaded.bs.select rendered.bs.select refreshed.bs.select', function() {
                    syncAssistClearButtons();
                });
                setTimeout(syncAssistClearButtons, 0);
                setTimeout(syncAssistClearButtons, 120);
                setTimeout(syncAssistClearButtons, 300);
            }

            document.querySelectorAll('[data-clear-select]').forEach(function(button) {
                button.addEventListener('click', function() {
                    var targetId = button.getAttribute('data-clear-select');
                    var select = document.getElementById(targetId);
                    if (!select) return;
                    select.value = '';
                    if (window.jQuery && window.jQuery.fn && window.jQuery(select).hasClass('selectpicker')) {
                        window.jQuery(select).selectpicker('val', '');
                    }
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        });

        (function() {
            var dataInternDt = document.getElementById('data_intern_int_dt');
            var dataIntern = document.getElementById('data_intern_int');
            var horaIntern = document.getElementById('hora_intern_int');
            var erroDiv = document.getElementById('erro-data-internacao');
            var alertTimer = null;

            if (!dataInternDt || !erroDiv) return;

            function hideAlert() {
                erroDiv.classList.add('d-none');
                erroDiv.classList.remove('alert-danger');
                erroDiv.textContent = '';
            }

            function showAlert(message) {
                erroDiv.classList.remove('d-none');
                erroDiv.classList.add('alert-danger');
                erroDiv.textContent = message;
                erroDiv.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                if (alertTimer) clearTimeout(alertTimer);
                alertTimer = setTimeout(hideAlert, 5000);
            }

            function parseLocalDateTime(value) {
                if (!value || value.indexOf('T') === -1) return null;
                var parts = value.split('T');
                var d = (parts[0] || '').split('-').map(Number);
                var t = (parts[1] || '').split(':').map(Number);
                if (d.length !== 3 || t.length < 2) return null;
                if (!d[0] || !d[1] || !d[2] || Number.isNaN(t[0]) || Number.isNaN(t[1])) return null;
                return new Date(d[0], d[1] - 1, d[2], t[0], t[1], 0, 0);
            }

            function validateFutureInternacaoDate() {
                hideAlert();
                if (!dataInternDt.value) return true;

                var selecionada = parseLocalDateTime(dataInternDt.value);
                if (!selecionada || Number.isNaN(selecionada.getTime())) return true;

                var agora = new Date();
                if (selecionada > agora) {
                    showAlert('A data da internação não pode ser maior que a data atual.');
                    dataInternDt.value = '';
                    if (dataIntern) dataIntern.value = '';
                    if (horaIntern) horaIntern.value = '';
                    dataInternDt.focus();
                    return false;
                }
                return true;
            }

            dataInternDt.addEventListener('change', validateFutureInternacaoDate);
            dataInternDt.addEventListener('blur', validateFutureInternacaoDate);
            dataInternDt.addEventListener('input', validateFutureInternacaoDate);

            // Garante a validação também no submit (o JS externo já usa essa função)
            window.validateDataInternacaoFuture = validateFutureInternacaoDate;
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
    <!-- <script src="<?= $BASE_URL ?>js/saude-autocomplete.js?v=2"></script> -->

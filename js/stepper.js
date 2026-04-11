// Validação do Bootstrap
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')

    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }

                form.classList.add('was-validated')
            }, false)
        })
})();

function nextStep(step) {
    // Seleciona todos os inputs dentro da etapa atual
    const currentStepForm = document.querySelector(`#step-${step - 1}`);
    const inputs = currentStepForm.querySelectorAll('input, select, textarea');

    let valid = true;
    inputs.forEach((input) => {
        if (!input.checkValidity()) {
            valid = false;
        }
    });

    // Se os inputs não são válidos, não prosseguir e aplicar as classes de validação
    if (!valid) {
        currentStepForm.classList.add('was-validated');
        return;
    }

    // Remover a classe de validação para a próxima etapa
    currentStepForm.classList.remove('was-validated');

    // Mostrar a próxima etapa
    document.querySelectorAll('.step').forEach((stepElement) => {
        stepElement.style.display = 'none';
    });
    document.getElementById('step-' + step).style.display = 'block';

    // Atualizar a barra de progresso
    document.getElementById('progressBar').style.width = (step) * 33.4 + '%';
    document.getElementById('progressBar').innerHTML = `Etapa ${step} de 3`;
}

function prevStep(step) {
    document.querySelectorAll('.step').forEach((stepElement) => {
        stepElement.style.display = 'none';
    });
    document.getElementById('step-' + step).style.display = 'block';

    // Atualizar a barra de progresso
    document.getElementById('progressBar').style.width = (step) * 33.4 + '%';
    document.getElementById('progressBar').innerHTML = `Etapa ${step} de 3`;
}

function nextStep2(step) {
    // Seleciona todos os inputs dentro da etapa atual
    const currentStepForm = document.querySelector(`#step-${step - 1}`);
    const inputs = currentStepForm.querySelectorAll('input, select, textarea');

    let valid = true;
    inputs.forEach((input) => {
        if (!input.checkValidity()) {
            valid = false;
        }
    });

    // Se os inputs não são válidos, não prosseguir e aplicar as classes de validação
    if (!valid) {
        currentStepForm.classList.add('was-validated');
        return;
    }

    // Remover a classe de validação para a próxima etapa
    currentStepForm.classList.remove('was-validated');

    // Mostrar a próxima etapa
    document.querySelectorAll('.step').forEach((stepElement) => {
        stepElement.style.display = 'none';
    });
    document.getElementById('step-' + step).style.display = 'block';

    // Atualizar a barra de progresso
    document.getElementById('progressBar').style.width = (step) * 25 + '%';
    document.getElementById('progressBar').innerHTML = `Etapa ${step} de 4`;
}

function prevStep2(step) {
    document.querySelectorAll('.step').forEach((stepElement) => {
        stepElement.style.display = 'none';
    });
    document.getElementById('step-' + step).style.display = 'block';

    // Atualizar a barra de progresso
    document.getElementById('progressBar').style.width = (step) * 25 + '%';
    document.getElementById('progressBar').innerHTML = `Etapa ${step} de 4`;
}

function validarCpfExistente(i, t) {
    var v = i.value;
    var formData = new FormData();
    formData.append('cpf', v.replaceAll('.', '').replaceAll("-", ""));
    if (v.length > 0) {
        $.ajax({
            url: 'process_cpf_paciente.php', // URL do arquivo PHP
            type: 'POST', // Método de envio
            processData: false, // Não processar os dados
            contentType: false, // Não definir o tipo de conteúdo
            data: formData, // Dados a serem enviados
            success: function (response) {
                if (response == 0) {
                    document.getElementById("validar_cpf").style.display = 'none'
                    document.getElementById("step-1").disabled = false
                } else {
                    document.getElementById("validar_cpf").style.display = 'block'
                    document.getElementById("step-1").disabled = true
                }

            },
            error: function () {
            }
        });
    }
}

function validarMatriculaExistente() {
    var v = document.getElementById('matricula_pac').value.trim();
    var recem = document.getElementById('recem_nascido_pac');
    var numeroRNInput = document.getElementById('numero_recem_nascido_pac');
    var feedbackMatricula = document.getElementById("validar_matricula");
    var botaoFinalizar = document.getElementById("finalizar_etapa1");
    var isRN = recem && recem.value === 's';

    if (isRN) {
        // Número RN (somente dígitos)
        var numeroRN = (numeroRNInput ? numeroRNInput.value : '').trim().replace(/\D+/g, '');

        // Concatena no formato: MATRICULA + RN + NUMERO
        // Se não tiver número, ainda assim anexa "RN"
        v = v + 'RN' + (numeroRN ? numeroRN : '');
    }

    var formData = new FormData();
    formData.append('matricula', v);

    if (v.length === 0) {
        if (feedbackMatricula) feedbackMatricula.style.display = 'none';
        if (botaoFinalizar) botaoFinalizar.disabled = false;
        return;
    }

    var baseUrl = (typeof buildBaseUrl === 'function' ? buildBaseUrl() : '/');
    var checkUrl = String(baseUrl).replace(/\/?$/, '/') + 'process_matricula_paciente.php';

    if (v.length > 0) {
        $.ajax({
            url: checkUrl,
            type: 'POST',
            dataType: 'json',
            processData: false,
            contentType: false,
            data: formData,
            success: function (response) {
                var existe = !!(response && response.exists);

                if (!existe) {
                    if (feedbackMatricula) feedbackMatricula.style.display = 'none';
                    if (botaoFinalizar) botaoFinalizar.disabled = false;
                } else {
                    if (feedbackMatricula) feedbackMatricula.style.display = 'block';
                    if (botaoFinalizar) botaoFinalizar.disabled = true;
                }
            },
            error: function () {
                if (feedbackMatricula) feedbackMatricula.style.display = 'none';
                if (botaoFinalizar) botaoFinalizar.disabled = false;
            }
        });
    }
}




function clearAndDisable(input, { required = false } = {}) {
    if (!input) return;
    input.value = '';
    input.required = !!required;
    input.disabled = true;
}

function enableAndRequire(input, { required = false } = {}) {
    if (!input) return;
    input.disabled = false;
    input.required = !!required;
}

function show(el) { if (el) el.style.display = 'block'; }
function hide(el) { if (el) el.style.display = 'none'; }

function handleRecemNascidoChange() {
    const recem = document.getElementById('recem_nascido_pac');

    const maeTitularGroup = document.getElementById('mae_titular_group');
    const maeTitularSelect = document.getElementById('mae_titular_pac');

    const numeroRNGroup = document.getElementById('numero_recem_nascido_group');
    const numeroRNInput = document.getElementById('numero_recem_nascido_pac');

    const matriculaTitularGroup = document.getElementById('matricula_titular_group');
    const matriculaInput = document.getElementById('matricula_titular_pac');

    if (!recem || !maeTitularGroup || !maeTitularSelect || !numeroRNGroup || !numeroRNInput || !matriculaTitularGroup || !matriculaInput) {
        return;
    }

    if (recem.value === 's') {
        // Exibe e habilita campos relativos a RN
        show(maeTitularGroup);
        show(numeroRNGroup);
        enableAndRequire(maeTitularSelect);
        enableAndRequire(numeroRNInput, { required: true });

        // Decide a matrícula da titular conforme escolha da mãe
        handleMaeTitularChange();
    } else {
        // Esconde tudo e limpa/relaxa validações
        hide(maeTitularGroup);
        hide(numeroRNGroup);
        hide(matriculaTitularGroup);

        maeTitularSelect.value = '';
        clearAndDisable(maeTitularSelect);

        clearAndDisable(numeroRNInput);
        clearAndDisable(matriculaInput);
    }
}

function handleMaeTitularChange() {
    const recem = document.getElementById('recem_nascido_pac');

    const maeTitularSelect = document.getElementById('mae_titular_pac');
    const matriculaTitularGroup = document.getElementById('matricula_titular_group');
    const matriculaInput = document.getElementById('matricula_titular_pac');

    if (!recem || !maeTitularSelect || !matriculaTitularGroup || !matriculaInput) return;

    // Só controla matrícula quando for RN
    if (recem.value !== 's') return;

    if (maeTitularSelect.value === 'n') {
        // Mostrar e exigir matrícula da titular
        show(matriculaTitularGroup);
        enableAndRequire(matriculaInput, { required: true });
    } else {
        // Esconder e limpar
        hide(matriculaTitularGroup);
        clearAndDisable(matriculaInput);
    }
}

function initPacienteHomonimoCheck(root = document) {
    if (!root) return;
    const form = root.querySelector ? root.querySelector('#multi-step-form') : document.getElementById('multi-step-form');
    if (!form || form.dataset.homonimoBound === '1') return;

    const hiddenConfirm = form.querySelector('#confirmar_homonimo_pac');
    const nomeInput = form.querySelector('#nome_pac');
    const bodyEl = form.querySelector('#dupPacienteBody') || document.getElementById('dupPacienteBody');
    const confirmBtn = form.querySelector('#btnConfirmarHomonimo') || document.getElementById('btnConfirmarHomonimo');
    const modalEl = form.querySelector('#modalNomeDuplicadoPaciente') || document.getElementById('modalNomeDuplicadoPaciente');
    if (!hiddenConfirm || !nomeInput) return;

    form.dataset.homonimoBound = '1';
    const modal = (window.bootstrap && modalEl) ? new bootstrap.Modal(modalEl) : null;
    let pendingSubmit = false;
    let ultimoNomeConsultado = '';
    let ultimosMatches = [];
    let confirmandoHomonimo = false;

    function resetNomeAposCancelar() {
        hiddenConfirm.value = '0';
        ultimoNomeConsultado = '';
        ultimosMatches = [];
        nomeInput.value = '';
        nomeInput.focus();
    }

    function fmtDate(value) {
        if (!value) return '-';
        const m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) return value;
        return m[3] + '/' + m[2] + '/' + m[1];
    }

    function renderRows(rows) {
        if (!bodyEl) return;
        if (!Array.isArray(rows) || rows.length === 0) {
            bodyEl.innerHTML = '<tr><td colspan="6" class="text-muted text-center">Sem dados.</td></tr>';
            return;
        }
        bodyEl.innerHTML = rows.map(function (r) {
            return '<tr>'
                + '<td>' + (r.id_paciente || '-') + '</td>'
                + '<td>' + (r.nome_pac || '-') + '</td>'
                + '<td>' + (r.matricula_pac || '-') + '</td>'
                + '<td>' + (r.cpf_pac_formatado || '-') + '</td>'
                + '<td>' + fmtDate(r.data_nasc_pac) + '</td>'
                + '<td>' + (r.seguradora_seg || '-') + '</td>'
                + '</tr>';
        }).join('');
    }

    if (confirmBtn && confirmBtn.dataset.homonimoBound !== '1') {
        confirmBtn.dataset.homonimoBound = '1';
        confirmBtn.addEventListener('click', function () {
            confirmandoHomonimo = true;
            hiddenConfirm.value = '1';
            pendingSubmit = true;
            if (modal) modal.hide();
            form.requestSubmit();
        });
    }

    if (modalEl && modalEl.dataset.homonimoCancelBound !== '1') {
        modalEl.dataset.homonimoCancelBound = '1';
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (confirmandoHomonimo) {
                confirmandoHomonimo = false;
                return;
            }
            resetNomeAposCancelar();
        });
    }

    function openDuplicatePopup(rows) {
        if (modal && bodyEl && confirmBtn) {
            renderRows(rows);
            modal.show();
            return Promise.resolve(false);
        }
        const preview = rows.slice(0, 3).map(function (r) {
            return '#'+ (r.id_paciente || '-') + ' - ' + (r.nome_pac || '-') + ' / Mat: ' + (r.matricula_pac || '-');
        }).join('\n');
        const msg = "Já existe paciente com nome igual/parecido.\n\n" + preview + "\n\nÉ outro paciente (homônimo)?";
        return Promise.resolve(window.confirm(msg)).then(function (ok) {
            if (!ok) {
                resetNomeAposCancelar();
            }
            return ok;
        });
    }

    function buildBaseUrl() {
        if (typeof window.BASE_URL === 'string' && window.BASE_URL.length) {
            return window.BASE_URL;
        }
        const baseEl = document.querySelector('base[href]');
        if (baseEl && baseEl.href) return baseEl.href;
        return '/';
    }

    function consultarNome(nome, abrirModalQuandoDuplicado) {
        const nomeNormalizado = (nome || '').trim();
        if (!nomeNormalizado) {
            ultimoNomeConsultado = '';
            ultimosMatches = [];
            hiddenConfirm.value = '0';
            return Promise.resolve([]);
        }

        const payload = new URLSearchParams();
        payload.set('nome_pac', nomeNormalizado);
        const checkUrl = String(buildBaseUrl()).replace(/\/?$/, '/') + 'ajax/check_paciente_nome.php';

        return fetch(checkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload.toString()
        })
            .then(function (resp) { return resp.json(); })
            .then(function (data) {
                const rows = Array.isArray(data && data.matches) ? data.matches : [];
                ultimoNomeConsultado = nomeNormalizado;
                ultimosMatches = rows;
                hiddenConfirm.value = rows.length ? '0' : '1';

                if (rows.length && abrirModalQuandoDuplicado) {
                    return openDuplicatePopup(rows).then(function (ok) {
                        if (ok) {
                            hiddenConfirm.value = '1';
                            return rows;
                        }
                        hiddenConfirm.value = '0';
                        return rows;
                    });
                }
                return rows;
            });
    }

    if (nomeInput.dataset.homonimoBlurBound !== '1') {
        nomeInput.dataset.homonimoBlurBound = '1';
        nomeInput.addEventListener('input', function () {
            // Mudou o nome -> exige nova confirmação
            hiddenConfirm.value = '0';
            ultimoNomeConsultado = '';
            ultimosMatches = [];
        });
        nomeInput.addEventListener('blur', function () {
            const nome = (nomeInput.value || '').trim();
            if (!nome) return;
            consultarNome(nome, true).catch(function () {
                alert('Não foi possível verificar duplicidade de nome agora. Tente novamente.');
            });
        });
    }

    form.addEventListener('submit', function (e) {
        const typeInput = form.querySelector('input[name="type"]');
        const formType = typeInput ? String(typeInput.value || '') : '';
        if (formType !== 'create') return;
        if (pendingSubmit) {
            pendingSubmit = false;
            return;
        }
        if (hiddenConfirm.value === '1') return;

        const nome = (nomeInput.value || '').trim();
        if (!nome) return;

        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') {
            e.stopImmediatePropagation();
        }
        const jaConsultado = (ultimoNomeConsultado === nome);
        const possuiDuplicadosJaConhecidos = jaConsultado && Array.isArray(ultimosMatches) && ultimosMatches.length > 0;
        if (possuiDuplicadosJaConhecidos) {
            openDuplicatePopup(ultimosMatches).then(function (ok) {
                if (ok) {
                    hiddenConfirm.value = '1';
                    pendingSubmit = true;
                    form.requestSubmit();
                }
            });
            return;
        }

        consultarNome(nome, false)
            .then(function (rows) {
                if (!rows.length) {
                    pendingSubmit = true;
                    form.requestSubmit();
                    return;
                }
                openDuplicatePopup(rows).then(function (ok) {
                    if (ok) {
                        hiddenConfirm.value = '1';
                        pendingSubmit = true;
                        form.requestSubmit();
                    }
                });
            })
            .catch(function () {
                hiddenConfirm.value = '1';
                pendingSubmit = true;
                form.requestSubmit();
            });
    }, true);
}

document.addEventListener('DOMContentLoaded', function () {
    initPacienteHomonimoCheck(document);
});

window.initPacienteHomonimoCheck = initPacienteHomonimoCheck;


function handleMaeTitularChange() {

    const recem = document.getElementById('recem_nascido_pac');
    const maeTitularSelect = document.getElementById('mae_titular_pac');
    const matriculaTitularGroup = document.getElementById('matricula_titular_group');
    const matriculaInput = document.getElementById('matricula_titular_pac');



    if (!recem || !maeTitularSelect || !matriculaTitularGroup || !matriculaInput) return;

    if (recem.value !== 's') return;
    if (maeTitularSelect.value === 'n') {
        // Mãe NÃO é titular -> pedir matrícula da titular
        matriculaTitularGroup.style.display = 'block';
        matriculaInput.disabled = false;
        matriculaInput.required = true;
    } else {
        // Mãe é titular (ou não selecionado) -> esconder e limpar matrícula
        matriculaTitularGroup.style.display = 'none';
        matriculaInput.value = '';
        matriculaInput.required = false;
        matriculaInput.disabled = true;
    }
}

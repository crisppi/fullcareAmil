(function(window, document) {
    'use strict';
    const config = window.formInternacaoConfig || {};

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

function triggerInternacaoAutoSave() {
    const form = document.getElementById('myForm');
    if (!form) return;
    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');

    // impede salvar se houver campos obrigatórios faltando
    if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
        form.reportValidity && form.reportValidity();
        return;
    }

    const restoreVisual = () => {
        form.style.filter = '';
        form.style.opacity = '';
        if (submitBtn) submitBtn.disabled = false;
    };

    if (submitBtn) submitBtn.disabled = true;
    form.style.filter = 'blur(2px)';
    form.style.opacity = '0.6';

    setTimeout(function() {
        const hasJquery = typeof window.jQuery === 'function';
        if (hasJquery) {
            window.jQuery(form).trigger('submit');
            restoreVisual();
            return;
        }
        const evt = new Event('submit', { cancelable: true, bubbles: true });
        const notCanceled = form.dispatchEvent(evt);
        if (notCanceled) {
            form.submit();
        } else {
            restoreVisual();
        }
    }, 150);
}

document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('myForm');
    var timerField = document.getElementById('timer_int');
    var pacienteSelect = document.getElementById('fk_paciente_int');
    var matriculaField = document.getElementById('matricula_paciente_display');
    var dataInternDt = document.getElementById('data_intern_int_dt');
    var dataIntern = document.getElementById('data_intern_int');
    var horaIntern = document.getElementById('hora_intern_int');
    var timerStart = null;
    var intervalId = null;
    var lastMatriculaMatch = '';

    function normalizeMatricula(value) {
        return (value || '').toLowerCase().replace(/[^0-9a-z]/g, '');
    }

    function selectPacienteByMatricula(value, options = {}) {
        if (!pacienteSelect) return;
        var search = normalizeMatricula(value);
        if (!search) return;
        var listOptions = Array.from(pacienteSelect.options || []);
        var match = listOptions.find(function(opt) {
            return normalizeMatricula(opt.getAttribute('data-matricula') || '') === search;
        });
        if (!match && (options.allowPartial !== false) && search.length >= 3) {
            match = listOptions.find(function(opt) {
                return normalizeMatricula(opt.getAttribute('data-matricula') || '').includes(search);
            });
        }
        if (match && match.value) {
            pacienteSelect.value = match.value;
            if (window.jQuery && window.jQuery.fn && window.jQuery(pacienteSelect).hasClass('selectpicker')) {
                window.jQuery(pacienteSelect).selectpicker('val', match.value);
            }
            handlePacienteChange();
            return true;
        }
        return false;
    }

    window.sortPacienteOptionsDesc = function() {
        var select = document.getElementById('fk_paciente_int');
        if (!select || select.options.length <= 1) return;
        var options = Array.from(select.options).slice(1);
        options.sort(function(a, b) {
            return parseInt(b.value || '0', 10) - parseInt(a.value || '0', 10);
        });
        options.forEach(function(opt) {
            select.appendChild(opt);
        });
    };

    function startTimer() {
        if (timerStart === null) {
            timerStart = Date.now();
        }
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }

    function scheduleValueWatch() {
        if (!pacienteSelect || intervalId) return;
        intervalId = setInterval(function() {
            if (pacienteSelect.value) {
                startTimer();
                handlePacienteChange();
            }
        }, 700);
    }

    function handlePacienteChange() {
        if (!pacienteSelect) return;
        var selectedText = pacienteSelect.options[pacienteSelect.selectedIndex]?.text?.trim() || '';
        var id = pacienteSelect.value;
        var selectedOption = pacienteSelect.options[pacienteSelect.selectedIndex] || null;
        if (patientInsightDisplay && typeof patientInsightDisplay.setEnabled === 'function') {
            patientInsightDisplay.setEnabled(!!id);
        }
        if (matriculaField) {
            var matricula = selectedOption ? (selectedOption.getAttribute('data-matricula') || '') : '';
            matriculaField.value = id ? matricula : '';
        }
        if (id) startTimer();
        if (patientInsightsHelper && typeof patientInsightsHelper.showFromOption === 'function') {
            patientInsightsHelper.showFromOption(selectedOption, selectedText);
        }
        if (patientInsightsHelper && typeof patientInsightsHelper.fetch === 'function') {
            patientInsightsHelper.fetch(id, selectedText);
        }
        if (typeof window.triggerInternacaoCheck === 'function') {
            window.triggerInternacaoCheck();
        }
    }

    if (pacienteSelect) {
        window.sortPacienteOptionsDesc();
        if (pacienteSelect.value) {
            startTimer();
            handlePacienteChange();
        } else {
            scheduleValueWatch();
        }
        pacienteSelect.addEventListener('change', handlePacienteChange);

        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.on === 'function') {
            window.jQuery(function() {
                $('#fk_paciente_int').on('changed.bs.select', function () {
                    handlePacienteChange();
                });
            });
        }
    } else {
        startTimer();
    }

    function triggerMatriculaSearch(eventSource) {
        if (!matriculaField) return;
        var value = matriculaField.value;
        if (eventSource === 'input') {
            var normalized = normalizeMatricula(value);
            if (!normalized) {
                lastMatriculaMatch = '';
                return;
            }
            if (normalized === lastMatriculaMatch) return;
            if (selectPacienteByMatricula(value, {
                    allowPartial: false
                })) {
                lastMatriculaMatch = normalized;
            }
            return;
        }
        lastMatriculaMatch = '';
        selectPacienteByMatricula(value);
    }

    if (matriculaField) {
        matriculaField.addEventListener('keydown', function(evt) {
            if (evt.key === 'Enter') {
                evt.preventDefault();
                triggerMatriculaSearch('enter');
            }
        });
        matriculaField.addEventListener('blur', function() {
            triggerMatriculaSearch('blur');
        });
        matriculaField.addEventListener('input', function() {
            triggerMatriculaSearch('input');
        });
    }

    ['pacienteSelecionado', 'paciente-selecionado'].forEach(function(evtName) {
        document.addEventListener(evtName, startTimer);
    });

    if (form && timerField) {
        form.addEventListener('submit', function() {
            var elapsed = 0;
            if (timerStart !== null) {
                elapsed = Math.max(0, Math.round((Date.now() - timerStart) / 1000));
            }
            timerField.value = elapsed;
        });
    }

    function syncInternacaoHidden() {
        if (!dataInternDt || !dataIntern || !horaIntern) return;
        if (!dataInternDt.value) {
            dataIntern.value = '';
            horaIntern.value = '';
            return;
        }
        var parts = dataInternDt.value.split('T');
        dataIntern.value = parts[0] || '';
        horaIntern.value = parts[1] ? parts[1].slice(0, 5) : '';
    }

    if (dataInternDt) {
        dataInternDt.addEventListener('change', syncInternacaoHidden);
        dataInternDt.addEventListener('input', syncInternacaoHidden);
        syncInternacaoHidden();
    }
    if (form) {
        form.addEventListener('submit', syncInternacaoHidden);
    }
});

if (config.prefillPacienteId) {
    (function preselectPaciente() {
        var tentativas = 0;
        var idPac = String(config.prefillPacienteId || '');
        if (!idPac) return;

        function aplicar() {
            var $sel = $('#fk_paciente_int');
            if (!$sel.length) return false;

            $sel.val(idPac);

            if ($.fn.selectpicker && $sel.hasClass('selectpicker')) {
                $sel.selectpicker('val', idPac);
            }

            if (typeof window.triggerInternacaoCheck === 'function') {
                try {
                    window.triggerInternacaoCheck();
                } catch (e) {
                    console.warn('triggerInternacaoCheck falhou:', e);
                }
            }
            return true;
        }

        (function aguardarPronto() {
            if (aplicar()) return;
            if (++tentativas < 30) return setTimeout(aguardarPronto, 100);
            console.warn('Não foi possível pré-selecionar o paciente.');
        })();
    })();
}

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
        function stripCentralPickerShadow() {
            const selectors = [
                '.bootstrap-select > .dropdown-toggle[data-id="resp_med_id"]',
                '.bootstrap-select > .dropdown-toggle[data-id="resp_enf_id"]',
                '#box_resp_med .bootstrap-select > .dropdown-toggle',
                '#box_resp_enf .bootstrap-select > .dropdown-toggle'
            ].join(', ');
            $(selectors).each(function() {
                this.style.setProperty('box-shadow', 'none', 'important');
                this.style.setProperty('outline', 'none', 'important');
                this.style.setProperty('border-color', '#ced4da', 'important');
            });
        }

        var $allPickers = $('.selectpicker').filter(function() {
            return $(this).attr('data-fcx-picker-locked') !== '1';
        });
        $allPickers.each(function() {
            var $el = $(this);
            var hasWrapper = $el.siblings('div.bootstrap-select').length > 0;
            if (!hasWrapper && !$el.data('selectpicker')) {
                $el.selectpicker();
            }
            if ($el.siblings('div.bootstrap-select').length > 1) {
                $el.siblings('div.bootstrap-select').slice(1).remove();
            }
            if ($el.siblings('div.bootstrap-select').length) {
                $el.addClass('bs-select-hidden');
            }
        });

        $allPickers.on('loaded.bs.select', function() {
            $(this).parent().find('.bs-searchbox input').attr('placeholder', 'Digite para pesquisar...');
            stripCentralPickerShadow();
        });

        $allPickers.on('rendered.bs.select refreshed.bs.select shown.bs.select hidden.bs.select changed.bs.select', function() {
            stripCentralPickerShadow();
        });

        // fallback imediato
        setTimeout(stripCentralPickerShadow, 0);
        setTimeout(stripCentralPickerShadow, 200);
    }
});

const hospitalInsightsHelper = (function() {
    const button = document.getElementById('hospitalTipButton');
    const popover = document.getElementById('hospitalTipPopover');
    const alertBox = document.getElementById('hospitalUtiAlert');
    const defaultMessage = 'Selecione um hospital para ver negociações e pacientes em UTI.';

    function hideAlert() {
        if (alertBox) {
            alertBox.textContent = '';
            alertBox.classList.remove('show');
        }
    }

    function showAlert(message) {
        if (!alertBox) return;
        alertBox.textContent = message;
        alertBox.classList.add('show');
    }

    function setPopover(content) {
        if (!popover) return;
        popover.innerHTML = content;
    }

    function setLoading(hospitalName) {
        if (button) {
            button.disabled = true;
        }
        if (popover) {
            popover.classList.remove('show');
        }
        setPopover(`Carregando dados de <strong>${hospitalName}</strong>...`);
        hideAlert();
    }

    function reset() {
        if (button) button.disabled = true;
        if (popover) popover.classList.remove('show');
        setPopover(defaultMessage, false);
        hideAlert();
    }

    async function fetchInsights(hospitalId, hospitalName) {
        if (!hospitalId) {
            reset();
            return;
        }
        setLoading(hospitalName || 'hospital selecionado');
        try {
            const response = await fetch('ajax/hospital_insights.php?id_hospital=' + encodeURIComponent(hospitalId), {
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('Falha ao consultar insights.');
            const payload = await response.json();
            if (!payload.success || !payload.data) {
                throw new Error(payload.error || 'Resposta inválida.');
            }
            const data = payload.data;
            if (button) button.disabled = false;
            const percent = data.percent_uti ?? 0;
            const longStay = data.long_stay ?? 0;
            const longThreshold = data.long_threshold ?? 0;
            const html = `
                <div><strong>${hospitalName || 'Hospital selecionado'}</strong></div>
                <div>Negociações registradas: <strong>${data.negociacoes ?? 0}</strong></div>
                <div>Internações em UTI: <strong>${data.inter_uti ?? 0}</strong></div>
                <div>Total de internações: <strong>${data.total_internacoes ?? 0}</strong></div>
                <div>UTI vs Total: <strong>${percent}%</strong></div>
                <div>MP Hospital: <strong>${data.mp_hospital ?? 0} dias</strong></div>
                <div>MP UTI: <strong>${data.mp_uti ?? 0} dias</strong></div>
                <div>Longa permanência (&ge; ${longThreshold} dias): <strong>${longStay}</strong></div>
            `;
            setPopover(html);
            if (data.uti_alert) {
                const threshold = data.threshold ?? 0;
                showAlert(`Alerta: ${data.inter_uti} internações em UTI neste hospital (limite ${threshold}).`);
            } else {
                hideAlert();
            }
        } catch (err) {
            if (button) button.disabled = true;
            setPopover(`Não foi possível carregar os dados. ${err.message}`);
            showAlert('Não foi possível verificar os pacientes em UTI agora.');
        }
    }

    if (button && popover) {
        button.addEventListener('click', function() {
            if (button.disabled) return;
            popover.classList.toggle('show');
        });
        document.addEventListener('click', function(evt) {
            if (!popover || !button) return;
            if (popover.contains(evt.target) || button.contains(evt.target)) return;
            popover.classList.remove('show');
        });
    }

    reset();
    return {
        fetch: fetchInsights,
        reset: reset
    };
})();

const patientInsightsHelper = (function() {
    const card = document.getElementById('patientInsightCard');
    const body = document.getElementById('patientInsightBody');
    const hubLink = document.getElementById('patientInsightHub');
    const careAlert = document.getElementById('patientCareProgramAlert');
    const hubBase = card ? card.dataset.hubBase || '' : '';
    const defaultMessage = 'Selecione um paciente para visualizar o histórico resumido.';
    let requestId = 0;
    let careAlertTimer = null;

    function setMessage(msg) {
        if (body) body.innerHTML = msg;
    }

    function disableHub() {
        if (hubLink) {
            hubLink.classList.add('disabled');
            hubLink.href = '#';
        }
    }

    function enableHub(idPaciente) {
        if (hubLink) {
            hubLink.classList.remove('disabled');
            hubLink.href = hubBase ? hubBase + encodeURIComponent(idPaciente) : '#';
        }
    }

    function reset() {
        setMessage(defaultMessage);
        disableHub();
        if (careAlertTimer) {
            clearTimeout(careAlertTimer);
            careAlertTimer = null;
        }
        if (careAlert) {
            careAlert.style.display = 'none';
            careAlert.innerHTML = '';
        }
    }

    function showCareProgramAlert(data, pacName) {
        if (!careAlert) return;
        if (!data || !data.em_programa) {
            careAlert.style.display = 'none';
            careAlert.innerHTML = '';
            return;
        }
        const nome = pacName || 'Paciente';
        const programas = Array.isArray(data.programas) ? data.programas.join(' e ') : '';
        const condicoes = (data.condicoes || '').trim();
        const detalhe = condicoes ? `<br>Condições registradas: <strong>${condicoes}</strong>.` : '';
        careAlert.innerHTML = `<strong>${nome}</strong><br>Paciente em <strong>${programas}</strong>.${detalhe}`;
        careAlert.style.display = 'block';
        if (careAlertTimer) clearTimeout(careAlertTimer);
        careAlertTimer = setTimeout(function() {
            careAlert.style.display = 'none';
            careAlert.innerHTML = '';
        }, 7000);
    }

    function showCareProgramAlertFromOption(option, pacName) {
        if (!option) return;
        let programas = [];
        try {
            programas = JSON.parse(option.getAttribute('data-care-programs') || '[]') || [];
        } catch (e) {
            programas = [];
        }
        showCareProgramAlert({
            em_programa: programas.length > 0,
            programas: programas,
            condicoes: option.getAttribute('data-care-condicoes') || ''
        }, pacName);
    }

    async function fetchInsights(pacId, pacName) {
        if (!card || !body) return;
        if (!pacId) {
            reset();
            return;
        }
        const current = ++requestId;
        setMessage(`Carregando dados de <strong>${pacName || 'paciente'}</strong>...`);
        disableHub();
        try {
            const response = await fetch('ajax/paciente_insights.php?id_paciente=' + encodeURIComponent(pacId), {
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('Falha ao consultar resumo.');
            const payload = await response.json();
            if (current !== requestId) return;
            if (!payload.success || !payload.data) throw new Error(payload.error || 'Resposta inválida.');
            const data = payload.data;
            const html = `
                <div class="patient-insight-metrics">
                    <div>
                        Total internações
                        <strong>${data.total_internacoes ?? 0}</strong>
                    </div>
                    <div>
                        Nº de diárias
                        <strong>${data.total_diarias ?? 0}</strong>
                    </div>
                    <div>
                        MP (dias)
                        <strong>${data.mp ?? 0}</strong>
                    </div>
                </div>
            `;
            setMessage(html);
            enableHub(pacId);
            showCareProgramAlert(data.cuidado_programa || null, pacName);
        } catch (err) {
            if (current !== requestId) return;
            setMessage(`Não foi possível carregar o resumo. ${err.message}`);
            disableHub();
            if (careAlert) {
                careAlert.style.display = 'none';
                careAlert.innerHTML = '';
            }
        }
    }

    reset();
    return { fetch: fetchInsights, reset, showFromOption: showCareProgramAlertFromOption };
})();

const patientInsightDisplay = (function() {
    const card = document.getElementById('patientInsightCard');
    const btn = document.getElementById('patientInsightToggle');
    let visible = false;
    let enabled = false;

    function update() {
        if (btn) {
            btn.style.display = enabled ? 'inline-flex' : 'none';
        }
        if (card) card.style.display = (enabled && visible) ? 'block' : 'none';
        if (btn) {
            btn.classList.toggle('active', visible);
            btn.setAttribute('aria-expanded', visible ? 'true' : 'false');
        }
    }

    if (btn) {
        btn.addEventListener('click', function() {
            visible = !visible;
            update();
        });
    }

    update();

    return {
        hide() {
            visible = false;
            update();
        },
        setEnabled(value) {
            enabled = !!value;
            if (!enabled) {
                visible = false;
            }
            update();
        },
        isVisible() {
            return visible;
        }
    };
})();

function syncHospitalInsightAvailability(hasHospital) {
    const button = document.getElementById('hospitalTipButton');
    const popover = document.getElementById('hospitalTipPopover');
    const alertBox = document.getElementById('hospitalUtiAlert');
    if (button) {
        button.style.display = hasHospital ? 'inline-flex' : 'none';
        button.disabled = !hasHospital;
        button.setAttribute('aria-hidden', hasHospital ? 'false' : 'true');
    }
    if (!hasHospital) {
        if (popover) popover.classList.remove('show');
        if (alertBox) {
            alertBox.textContent = '';
            alertBox.classList.remove('show');
        }
    }
}

// Hospital selecionado -> mostra nome e grava hidden
function getHospitalPickerToggle() {
    if (!window.jQuery) return null;
    const $toggle = window.jQuery('.bootstrap-select > .dropdown-toggle[data-id="hospital_selected"]').first();
    return $toggle.length ? $toggle : null;
}

function myFunctionSelected() {
    const select = document.getElementById("hospital_selected");
    const inputHospital = document.getElementById("fk_hospital_int");
    const divNome = document.getElementById("hospitalNomeTexto");
    const $toggle = getHospitalPickerToggle();

    if (!select || !inputHospital || !divNome) return;

    const id = select.value || "";
    const nome = select.options[select.selectedIndex]?.text || "";

    inputHospital.value = id;
    syncHospitalInsightAvailability(!!id);

    if (id) {
        select.style.color = "#495057";
        select.style.fontWeight = "normal";
        select.style.border = "1px solid #ced4da";
        if ($toggle) {
            $toggle.css({
                color: '#495057',
                'font-weight': 'normal',
                border: '1px solid #ced4da',
                'box-shadow': 'none'
            });
        }
        divNome.textContent = nome;
        divNome.style.display = "flex";
        if (hospitalInsightsHelper && typeof hospitalInsightsHelper.fetch === 'function') {
            hospitalInsightsHelper.fetch(id, nome);
        }
    } else {
        select.style.color = "#495057";
        select.style.fontWeight = "normal";
        select.style.border = "1px solid #ced4da";
        if ($toggle) {
            $toggle.css({
                color: '#495057',
                'font-weight': 'normal',
                border: '1px solid #ced4da',
                'box-shadow': 'none'
            });
        }
        divNome.textContent = "";
        divNome.style.display = "none";
        if (hospitalInsightsHelper && typeof hospitalInsightsHelper.reset === 'function') {
            hospitalInsightsHelper.reset();
        }
    }

    if (window.cadastroCentralHelper && typeof window.cadastroCentralHelper.applyHospitalFilter === 'function') {
        window.cadastroCentralHelper.applyHospitalFilter(id);
    }
}

function syncHospitalHiddenField() {
    const select = document.getElementById("hospital_selected");
    const inputHospital = document.getElementById("fk_hospital_int");
    if (!select || !inputHospital) return "";
    const id = select.value || "";
    inputHospital.value = id;
    return id;
}

document.addEventListener('DOMContentLoaded', function() {
    const pacienteSelect = document.getElementById('fk_paciente_int');
    const hospitalSelect = document.getElementById('hospital_selected');
    if (patientInsightDisplay && typeof patientInsightDisplay.setEnabled === 'function') {
        patientInsightDisplay.setEnabled(!!(pacienteSelect && pacienteSelect.value));
    }
    syncHospitalInsightAvailability(!!(hospitalSelect && hospitalSelect.value));
    syncHospitalHiddenField();

    if (hospitalSelect) {
        hospitalSelect.addEventListener('change', function() {
            myFunctionSelected();
            syncHospitalHiddenField();
        });
    }

    if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.on === 'function') {
        window.jQuery(function() {
            window.jQuery('#hospital_selected').on('changed.bs.select', function() {
                myFunctionSelected();
                syncHospitalHiddenField();
            });
        });
    }
});

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

// Validação de data/hora da internação (campo visível)
(function() {
    const dataInternDt = document.getElementById("data_intern_int_dt");
    const dataIntern = document.getElementById("data_intern_int");
    const horaIntern = document.getElementById("hora_intern_int");
    const erroDiv = document.getElementById("erro-data-internacao");
    let alertTimer = null;

    function hideInternacaoAlert() {
        if (!erroDiv) return;
        erroDiv.classList.add("d-none");
        erroDiv.classList.remove("alert-danger", "alert-warning");
        erroDiv.textContent = "";
    }

    function showInternacaoAlert(message, type) {
        if (!erroDiv) return;
        const isWarning = type === "warning";
        erroDiv.classList.remove("d-none");
        erroDiv.classList.remove("alert-danger", "alert-warning");
        erroDiv.classList.add(isWarning ? "alert-warning" : "alert-danger");
        erroDiv.textContent = message;
        erroDiv.scrollIntoView({ behavior: "smooth", block: "start" });
        if (alertTimer) clearTimeout(alertTimer);
        alertTimer = setTimeout(hideInternacaoAlert, 5000);
    }

    function parseLocalDateTime(value) {
        if (!value || value.indexOf('T') === -1) return null;
        const parts = value.split('T');
        const datePart = parts[0] || '';
        const timePart = parts[1] || '';
        const d = datePart.split('-').map(Number);
        const t = timePart.split(':').map(Number);
        if (d.length !== 3 || t.length < 2) return null;
        const year = d[0];
        const month = d[1];
        const day = d[2];
        const hour = t[0];
        const minute = t[1];
        if (!year || !month || !day || Number.isNaN(hour) || Number.isNaN(minute)) return null;
        return new Date(year, month - 1, day, hour, minute, 0, 0);
    }

    function clearInternacaoDateField() {
        if (dataInternDt) {
            dataInternDt.value = "";
            dataInternDt.focus();
        }
        if (dataIntern) dataIntern.value = "";
        if (horaIntern) horaIntern.value = "";
    }

    function validarDataInternacao() {
        if (!dataInternDt) return true;
        hideInternacaoAlert();
        if (!dataInternDt.value) return true;

        const dataSelecionada = parseLocalDateTime(dataInternDt.value);
        if (!dataSelecionada || Number.isNaN(dataSelecionada.getTime())) return true;

        const agora = new Date();
        if (dataSelecionada > agora) {
            showInternacaoAlert("A data da internação não pode ser maior que a data atual.", "error");
            clearInternacaoDateField();
            return false;
        }

        const diffDias = (agora - dataSelecionada) / (1000 * 60 * 60 * 24);
        if (diffDias > 30) {
            showInternacaoAlert("Internação com mais de 30 dias. Verifique a necessidade de prorrogação.", "warning");
        }
        return true;
    }

    if (dataInternDt) {
        dataInternDt.removeAttribute("max");
        dataInternDt.addEventListener("change", validarDataInternacao);
        dataInternDt.addEventListener("blur", validarDataInternacao);
        dataInternDt.addEventListener("input", validarDataInternacao);
    }

    window.validateDataInternacaoFuture = validarDataInternacao;
})();

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
const rowPertinente = document.getElementById("row-int-pertinente");
document.getElementById("tipo_admissao_int").addEventListener("change", function() {
    const tipo = this.value;
    const divPertinente = document.getElementById("div_int_pertinente_int");
    const divRelPertinente = document.getElementById("div_rel_pertinente_int");
    if (rowPertinente) {
        rowPertinente.style.display = "none";
    }
    divPertinente.style.display = "none";
    divRelPertinente.style.display = "none";
    if (tipo === "Urgência") {
        if (rowPertinente) {
            rowPertinente.style.display = "flex";
        }
        divPertinente.style.display = "block";
        document.getElementById("int_pertinente_int").addEventListener("change", function() {
            divRelPertinente.style.display = (this.value === "n") ? "block" : "none";
        }, {
            once: true
        });
    }
});

// JSON de antecedentes
document.addEventListener('DOMContentLoaded', function() {
    var selectAntecedente = document.getElementById('fk_patologia2');
    var jsonAntecedenteField = document.getElementById('json-antec');
    if (!selectAntecedente || !jsonAntecedenteField) return;

    function buildAntecedentePayload() {
        var pacienteField = document.getElementById('fk_paciente_int');
        var pacienteId = pacienteField ? parseInt(pacienteField.value || '0', 10) : null;
        var selected = Array.from(selectAntecedente.selectedOptions || []);
        var payload = selected.map(function(option) {
            var idAntecedente = parseInt(option.value, 10);
            if (!idAntecedente) return null;
            return {
                fk_id_paciente: pacienteId,
                fk_internacao_ant_int: null,
                intern_antec_ant_int: idAntecedente
            };
        }).filter(function(item) { return item !== null; });
        jsonAntecedenteField.value = payload.length ? JSON.stringify(payload) : '';
    }

    selectAntecedente.addEventListener('change', buildAntecedentePayload);
    buildAntecedentePayload();
});

// Mostrar/ocultar campos de alta conforme "Internado"
document.addEventListener("DOMContentLoaded", function() {
    const altaContainer = document.getElementById("alta-obrigatoria-container");
    const divDataAlta = document.getElementById("div-data-alta");
    const divMotivoAlta = document.getElementById("div-motivo-alta");
    const dataAltaInput = document.getElementById("data_alta_alt");
    const motivoAltaInput = document.getElementById("tipo_alta_alt");
    const retroativaInput = document.getElementById("retroativa_confirmada");

    if (!altaContainer || !divDataAlta || !divMotivoAlta) return;

    function toggleAltaObrigatoria(ativa, clearValues) {
        if (!ativa) {
            altaContainer.classList.add('d-none');
            divDataAlta.style.display = "none";
            divMotivoAlta.style.display = "none";
            altaContainer.hidden = true;
            divDataAlta.hidden = true;
            divMotivoAlta.hidden = true;
            if (dataAltaInput) dataAltaInput.required = false;
            if (motivoAltaInput) motivoAltaInput.required = false;
            divDataAlta.classList.add('d-none');
            divMotivoAlta.classList.add('d-none');
            if (clearValues) {
                if (dataAltaInput) dataAltaInput.value = "";
                if (motivoAltaInput) motivoAltaInput.value = "";
            }
        } else {
            altaContainer.classList.remove('d-none');
            divDataAlta.style.display = "block";
            divMotivoAlta.style.display = "block";
            altaContainer.hidden = false;
            divDataAlta.hidden = false;
            divMotivoAlta.hidden = false;
            if (dataAltaInput) dataAltaInput.required = true;
            if (motivoAltaInput) motivoAltaInput.required = true;
            divDataAlta.classList.remove('d-none');
            divMotivoAlta.classList.remove('d-none');
        }
    }

    window.setAltaObrigatoriaForRetroativa = function(ativa, opts) {
        var clearValues = Boolean(opts && opts.clearValues);
        toggleAltaObrigatoria(Boolean(ativa), clearValues);
    };

    toggleAltaObrigatoria(retroativaInput?.value === '1', false);
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
    const updateGroup = (selector) => {
        document.querySelectorAll(selector).forEach(el => {
            if (el) el.value = fk;
        });
    };
    if (medHidden) {
        medHidden.value = (tipo === 'enf') ? '' : fk;
    }
    updateGroup('#fk_usuario_neg');
    updateGroup('#fk_usuario_tuss');
    updateGroup('input[name="fk_usuario_tuss"]');
    updateGroup('#fk_usuario_pror');
    updateGroup('input[name="fk_usuario_pror"]');
    updateGroup('#fk_user_uti');
}
document.addEventListener('DOMContentLoaded', mirrorVisitMedFromFk);
document.addEventListener('DOMContentLoaded', function() {
    const formInternacao = document.getElementById('myForm');
    if (formInternacao) {
        formInternacao.addEventListener('submit', mirrorVisitMedFromFk);
    }
});

(function() {
    const respTipo = document.getElementById('resp_tipo');
    const boxMed = document.getElementById('box_resp_med');
    const boxEnf = document.getElementById('box_resp_enf');
    const selMed = document.getElementById('resp_med_id');
    const selEnf = document.getElementById('resp_enf_id');
    const hospitalUsuarioMap = (window.hospitalUsuariosMap && typeof window.hospitalUsuariosMap === 'object') ? window.hospitalUsuariosMap : {};
    const allMedOptions = selMed ? Array.from(selMed.options).map(opt => opt.cloneNode(true)) : [];
    const allEnfOptions = selEnf ? Array.from(selEnf.options).map(opt => opt.cloneNode(true)) : [];

    const fkUsuario = document.getElementById('fk_usuario_int');
    const flgMed = document.getElementById('visita_med_int');
    const flgEnf = document.getElementById('visita_enf_int');
    const emailMed = document.getElementById('visita_auditor_prof_med'); // usado para ID do médico responsável
    const emailEnf = document.getElementById('visita_auditor_prof_enf'); // não utilizado (mantém vazio)

    const idSessao = config.idSessao || "";
    const cargoSessao = config.cargoSessao || "";
    const cargoSessaoNorm = String(cargoSessao).toLowerCase().replace(/[\s-]+/g, '_');
    const isMedSessao = cargoSessaoNorm.indexOf('med') !== -1;
    const isEnfSessao = cargoSessaoNorm.indexOf('enf') !== -1;
    const clearInvalid = (el) => {
        if (el) el.classList.remove('is-invalid');
    };

    function neutralizeNativeSelectShadow(el) {
        if (!el) return;
        const apply = () => {
            el.style.setProperty('box-shadow', 'none', 'important');
            el.style.setProperty('outline', 'none', 'important');
            el.style.setProperty('border-color', '#ced4da', 'important');
        };
        apply();
        el.addEventListener('focus', apply);
        el.addEventListener('blur', apply);
        el.addEventListener('change', apply);
        el.addEventListener('click', apply);
    }

    function neutralizePickerShadow(selectEl) {
        if (!(window.$ && $.fn.selectpicker && selectEl)) return;
        const $select = $(selectEl);
        const apply = () => {
            const $btn = $select.siblings('div.bootstrap-select').find('> button.dropdown-toggle');
            if (!$btn.length) return;
            $btn.each(function() {
                this.style.setProperty('box-shadow', 'none', 'important');
                this.style.setProperty('outline', 'none', 'important');
                this.style.setProperty('border-color', '#ced4da', 'important');
                this.removeAttribute('title');
                this.setAttribute('data-original-title', '');
            });
        };
        apply();
        setTimeout(apply, 0);
        setTimeout(apply, 120);
        setTimeout(apply, 300);

        $select.on('loaded.bs.select rendered.bs.select refreshed.bs.select shown.bs.select hidden.bs.select changed.bs.select', apply);
        $(document).on('focus click mousedown', 'div.bootstrap-select > button.dropdown-toggle', apply);
    }

    function refreshPicker(el) {
        if (window.$ && $.fn.selectpicker && el && $(el).hasClass('selectpicker')) {
            $(el).selectpicker('refresh');
            neutralizePickerShadow(el);
        }
    }

    function getAllowedSet(hospitalId) {
        const hid = String(hospitalId || '');
        const ids = hospitalUsuarioMap[hid] || hospitalUsuarioMap[Number(hid)] || [];
        const set = new Set();
        if (Array.isArray(ids)) {
            ids.forEach(id => set.add(String(id)));
        }
        return set;
    }

    function filterSelectByHospital(selectEl, allOptions, allowedSet, hasHospital) {
        if (!selectEl || !Array.isArray(allOptions)) return;
        const current = String(selectEl.value || '');
        const nextOptions = [];

        allOptions.forEach(function(opt, idx) {
            const val = String(opt.value || '');
            if (val === '' || !hasHospital || allowedSet.has(val)) {
                nextOptions.push(opt.cloneNode(true));
            } else if (idx === 0 && val === '') {
                nextOptions.push(opt.cloneNode(true));
            }
        });

        selectEl.innerHTML = '';
        nextOptions.forEach(function(opt) {
            selectEl.appendChild(opt);
        });

        if (current && Array.from(selectEl.options).some(opt => String(opt.value || '') === current)) {
            selectEl.value = current;
        } else {
            selectEl.value = '';
        }

        refreshPicker(selectEl);
    }

    function applyHospitalFilter(hospitalId) {
        const hasHospital = Boolean(String(hospitalId || '').trim());
        const allowedSet = getAllowedSet(hospitalId);
        filterSelectByHospital(selMed, allMedOptions, allowedSet, hasHospital);
        filterSelectByHospital(selEnf, allEnfOptions, allowedSet, hasHospital);

        const tipo = (respTipo && respTipo.value) ? respTipo.value : '';
        if (tipo === 'med' && selMed && !selMed.value) {
            if (fkUsuario) fkUsuario.value = idSessao || '';
            mirrorVisitMedFromFk();
        }
        if (tipo === 'enf' && selEnf && !selEnf.value) {
            if (fkUsuario) fkUsuario.value = idSessao || '';
            mirrorVisitMedFromFk();
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
        if (flgMed) flgMed.value = isMedSessao ? 's' : 'n';
        if (flgEnf) flgEnf.value = isEnfSessao ? 's' : 'n';
        if (emailMed) emailMed.value = ''; // será setado por mirrorVisitMedFromFk
        if (emailEnf) emailEnf.value = '';
        mirrorVisitMedFromFk();
    }

    function resetCadastroCentralUI() {
        respTipo?.classList.remove('is-invalid');
        if (respTipo) respTipo.value = '';

        [selMed, selEnf].forEach(function(selectEl) {
            if (!selectEl) return;
            selectEl.classList.remove('is-invalid');
            selectEl.value = '';
            refreshPicker(selectEl);
        });

        hide(boxMed);
        hide(boxEnf);
        resetToSessionUser();
    }

    // inicia oculto
    hide(boxMed);
    hide(boxEnf);

    // remove halo/sombra no cadastro central (nativo + selectpicker)
    neutralizeNativeSelectShadow(respTipo);
    neutralizePickerShadow(selMed);
    neutralizePickerShadow(selEnf);
    resetToSessionUser();

    window.cadastroCentralHelper = window.cadastroCentralHelper || {};
    window.cadastroCentralHelper.reset = resetCadastroCentralUI;
    window.cadastroCentralHelper.resetToSessionUser = resetToSessionUser;
    window.cadastroCentralHelper.applyHospitalFilter = applyHospitalFilter;

    const hospitalInicial = document.getElementById('fk_hospital_int')?.value || document.getElementById('hospital_selected')?.value || '';
    applyHospitalFilter(hospitalInicial);

    respTipo?.addEventListener('change', function() {
        clearInvalid(respTipo);
        clearInvalid(selMed);
        clearInvalid(selEnf);
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
        clearInvalid(selMed);
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
        clearInvalid(selEnf);
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


// SUBMIT AJAX
// formulario ajax para envio form sem refresh
$("#myForm").submit(function(event) {
    event.preventDefault(); // Impede o envio tradicional do formulário
    syncHospitalHiddenField();
    let post_url = $(this).attr("action"); // Obtém a URL de ação do formulário
    let request_method = $(this).attr("method"); // Obtém o método do formulário (GET/POST)
    let form_data = new FormData(this); // Cria um objeto FormData com os dados do formulário

    if (typeof window.validateDataInternacaoFuture === 'function') {
        const okDataInternacao = window.validateDataInternacaoFuture();
        if (!okDataInternacao) {
            return;
        }
    }


    // 1. Salva o valor selecionado do select de hospitais
    const hospitalSelected = document.getElementById("hospital_selected").value;

    // 1.A. Validação do Hospital
    if (hospitalSelected === "") {
        // Usa a div de alerta existente para exibir o erro
        $('#alert').removeClass("alert-success").addClass("alert-danger");
        $('#alert').fadeIn().html("<b>Erro:</b> O campo Hospital é obrigatório.");
        const $hospitalToggle = getHospitalPickerToggle();
        if ($hospitalToggle) {
            $hospitalToggle.css({
                border: '1px solid #dc3545',
                'box-shadow': '0 0 0 0.2rem rgba(220, 53, 69, 0.12)'
            });
        }

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
    const $hospitalToggle = getHospitalPickerToggle();
    if ($hospitalToggle) {
        $hospitalToggle.css({
            border: '1px solid #ced4da',
            'box-shadow': 'none'
        });
    }

    if (typeof window.isSenhaDuplicada === 'function' && window.isSenhaDuplicada()) {
        $('#alert').removeClass("alert-success").addClass("alert-danger");
        $('#alert').fadeIn().html("Esta senha já está cadastrada para outra internação.");
        setTimeout(function() {
            $('#alert').fadeOut('Slow');
        }, 3500);
        return;
    }

    const cadCentralObrig = document.getElementById('cad_central_obrigatorio')?.value === '1';
    if (cadCentralObrig) {
        const respTipoEl = document.getElementById('resp_tipo');
        const respMedEl = document.getElementById('resp_med_id');
        const respEnfEl = document.getElementById('resp_enf_id');
        [respTipoEl, respMedEl, respEnfEl].forEach(function(el) {
            if (el) el.classList.remove('is-invalid');
        });

        const respTipoVal = respTipoEl?.value || '';
        let cadMsg = '';

        if (!respTipoVal) {
            cadMsg = 'Selecione o tipo de responsável pela visita.';
            respTipoEl?.classList.add('is-invalid');
        } else if (respTipoVal === 'med' && !(respMedEl?.value)) {
            cadMsg = 'Selecione o médico responsável pela visita.';
            respMedEl?.classList.add('is-invalid');
        } else if (respTipoVal === 'enf' && !(respEnfEl?.value)) {
            cadMsg = 'Selecione o enfermeiro responsável pela visita.';
            respEnfEl?.classList.add('is-invalid');
        }

        if (cadMsg) {
            $('#alert').removeClass("alert-success").addClass("alert-danger");
            $('#alert').fadeIn().html("<b>Erro:</b> " + cadMsg);
            setTimeout(function() {
                $('#alert').fadeOut('Slow');
            }, 3000);
            return;
        }
    }

    const showSubmitAlert = function(type, message, timeoutMs = 3000) {
        const $alert = $('#alert');
        if (!$alert.length) return;
        $alert.stop(true, true);
        $alert.removeClass("alert-success alert-danger")
            .addClass(type === 'success' ? 'alert-success' : 'alert-danger')
            .html(message)
            .fadeIn();
        if (timeoutMs > 0) {
            setTimeout(function() {
                $alert.fadeOut('Slow');
            }, timeoutMs);
        }
    };


    $.ajax({
        url: post_url,
        type: request_method,
        processData: false, // Impede o jQuery de processar os dados
        contentType: false, // Impede o jQuery de definir o contentType
        data: form_data,
        success: function(result) {
            const resposta = String(result || '').trim();
            if (resposta === 'paciente_internado') {
                showSubmitAlert('error', "Paciente possui internação ativa e precisa confirmar retroativa.");
                return;
            }
            if (resposta === 'retroativa_sem_alta') {
                showSubmitAlert('error', "Para retroativa, marque 'Internado = Não' e informe a data/motivo da alta.", 3500);
                return;
            }
            if (resposta === 'senha_duplicada') {
                showSubmitAlert('error', "Esta senha já está cadastrada para outra internação.", 3500);
                return;
            }
            if (resposta === 'hospital_required') {
                showSubmitAlert('error', "Selecione um hospital antes de cadastrar.", 3500);
                return;
            }

            if (resposta === '0') {
                showSubmitAlert('error', "Paciente possui internação ativa", 2000);
                return;
            }

            // Sucesso (resposta vazia ou texto padrão)
            {
                showSubmitAlert('success', "Cadastrado com sucesso", 3000);

                const regIntInput = $("#RegInt");
                if (regIntInput.length) {
                    const currentRegInt = parseInt(regIntInput.val(), 10);
                    const newRegInt = Number.isFinite(currentRegInt) ? currentRegInt + 1 : 1;
                    regIntInput.val(newRegInt);
                }

                // 2. Resetando os campos de input, select e textarea EXCETO os campos `hidden` e o select do hospital
                document.querySelectorAll('input, select, textarea').forEach((element) => {
                    if (element.type !== "hidden" && element.id !== "hospital_selected") {
                        element.value = '';
                    }
                });

                if (window.cadastroCentralHelper && typeof window.cadastroCentralHelper.reset === 'function') {
                    window.cadastroCentralHelper.reset();
                }

                // 3. Restaura o valor selecionado do select de hospitais (já feito antes do AJAX)
                // document.getElementById("hospital_selected").value = hospitalSelected; // Não precisa redefinir aqui

                // 4. Atualiza outros selects (exceto o de hospitais)
                const forceClearPicker = (selector) => {
                    const el = document.querySelector(selector);
                    if (!el) return;
                    el.value = '';
                    el.selectedIndex = 0;
                    Array.from(el.options || []).forEach((opt, idx) => {
                        opt.selected = idx === 0;
                    });
                    if (window.jQuery && $.fn.selectpicker && $(el).hasClass('selectpicker')) {
                        $(el).selectpicker('val', '');
                        $(el).selectpicker('render');
                        $(el).selectpicker('refresh');
                    }
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                };

                forceClearPicker('#fk_paciente_int');
                forceClearPicker('#fk_cid_int');
                forceClearPicker('#fk_patologia2');
                forceClearPicker('#fk_patologia_int');
                $('#matricula_paciente_display').val('');
                if (typeof window.triggerInternacaoCheck === 'function') {
                    window.triggerInternacaoCheck();
                    setTimeout(window.triggerInternacaoCheck, 120);
                }

                // 5. Update other values
                const adicionarValor = parseInt(document.querySelector("#proximoId_int")
                    .textContent) + 1;
                const ultimoReg = Number(config.ultimoReg || 0);
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
                document.getElementById("internado_int").dispatchEvent(new Event('change'));
                if (typeof window.setAltaObrigatoriaForRetroativa === 'function') {
                    window.setAltaObrigatoriaForRetroativa(false, { clearValues: true });
                }

                // 6. Hide containers
                const containers = [
                    "#container-gestao",
                    "#container-tuss",
                    "#container-prorrog",
                    "#container-uti",
                    "#container-negoc",
                    "#div-detalhado",
                    "#detalhes-card-wrapper"
                ];
                containers.forEach((container) => {
                    const el = document.querySelector(container);
                    if (el) {
                        el.style.display = "none";
                    }
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


                $('#retroativa_confirmada').val('0');
                $('#retroativa-alert').addClass('d-none');
                $('#retroativa-container').addClass('d-none');
            }

            // Clear additional fields
            clearTussInputs();
            clearProrrogInputs();

        },

        error: function(xhr, status, error) {
            console.error("AJAX Error:", status, error);
            const responseText = String(xhr?.responseText || '').trim();
            const msg = responseText
                ? "Falha ao cadastrar. " + responseText.slice(0, 180)
                : "Falha ao cadastrar. Verifique os dados e tente novamente.";
            showSubmitAlert('error', msg, 5000);
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

// Mostrar UTI se acomodação == UTI
document.getElementById("acomodacao_int").addEventListener("change", function() {
    const divUti = document.querySelector("#container-uti");
    if (divUti) divUti.style.display = (this.value === "UTI") ? "block" : "none";
});

// Tabelas adicionais (Tuss, Gestão, UTI, Prorrogação, Negociações)
document.addEventListener('DOMContentLoaded', function() {

    function setupToggle(selectId, containerId) {
        const selectEl = document.getElementById(selectId);
        const containerEl = document.getElementById(containerId);

        if (!selectEl || !containerEl) return;

        function aplicar() {
            if (selectEl.value === 's') {
                containerEl.style.display = 'block';
            } else {
                containerEl.style.display = 'none';
            }
        }

        // garante estado inicial
        aplicar();
        // atualiza ao mudar
        selectEl.addEventListener('change', aplicar);
    }

    // Tuss
    setupToggle('select_tuss', 'container-tuss');

    // Prorrogação
    setupToggle('select_prorrog', 'container-prorrog');

    // Gestão
    setupToggle('select_gestao', 'container-gestao');

    // Negociações
    setupToggle('select_negoc', 'container-negoc');

    // UTI: depende do select_uti e da acomodação
    (function() {
        const selectUti = document.getElementById('select_uti');
        const acomEl = document.getElementById('acomodacao_int');
        const containerUti = document.getElementById('container-uti');

        if (!containerUti) return;

        function aplicarUti() {
            const viaSelect = selectUti && selectUti.value === 's';
            const viaAcomod = acomEl && acomEl.value === 'UTI';
            containerUti.style.display = (viaSelect || viaAcomod) ? 'block' : 'none';
        }

        aplicarUti();

        if (selectUti) {
            selectUti.addEventListener('change', aplicarUti);
        }
        if (acomEl) {
            acomEl.addEventListener('change', aplicarUti);
        }
    })();
});

// Relatório Detalhado
(function() {
    const selectDet = document.getElementById('relatorio-detalhado');
    const wrapperDet = document.getElementById('detalhes-card-wrapper');
    const divDet = document.getElementById('div-detalhado');
    const hiddenDet = document.getElementById('select_detalhes');

    if (!selectDet || !divDet) return;

    function aplicar() {
        if (selectDet.value === 's') {
            if (wrapperDet) wrapperDet.style.display = 'block';
            divDet.style.display = 'block';
            if (hiddenDet) hiddenDet.value = 's';
        } else {
            divDet.style.display = 'none';
            if (wrapperDet) wrapperDet.style.display = 'none';
            if (hiddenDet) hiddenDet.value = 'n';
        }
    }

    aplicar();
    selectDet.addEventListener('change', aplicar);
})();


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
        const prorrogValorMap = {};
        const normKey = (v) => (v || '')
            .toString()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/^\d+\s*-\s*/, '')
            .trim();

        const parseDateSafe = (v) => {
            if (!v) return 0;
            const t = Date.parse(v);
            return Number.isNaN(t) ? 0 : t;
        };

        // Dedupe por acomodação (evita pegar valor antigo e inverter saving)
        const dedupMap = new Map();
        (acomodacoes || []).forEach((ac) => {
            const nome = (ac?.acomodacao_aco || '').toString();
            const key = normKey(nome);
            if (!key) return;
            const curr = dedupMap.get(key);
            if (!curr) {
                dedupMap.set(key, ac);
                return;
            }
            const currDate = parseDateSafe(curr.data_contrato_aco);
            const nextDate = parseDateSafe(ac.data_contrato_aco);
            const currId = parseInt(curr.id_acomodacao || 0, 10) || 0;
            const nextId = parseInt(ac.id_acomodacao || 0, 10) || 0;
            if (nextDate > currDate || (nextDate === currDate && nextId > currId)) {
                dedupMap.set(key, ac);
            }
        });
        const acomodacoesUnicas = Array.from(dedupMap.values());

        let options = '<option value="">Selecione a Acomodação</option>';
        acomodacoesUnicas.forEach(ac => {
            const valorNum = parseFloat(String(ac.valor_aco ?? '0').replace(',', '.')) || 0;
            options +=
                `<option value="${ac.id_acomodacao}-${ac.acomodacao_aco}" data-valor="${valorNum}">${ac.acomodacao_aco}</option>`;
            const key = normKey(ac.acomodacao_aco);
            if (key) prorrogValorMap[key] = valorNum;
        });
        $('select[name="troca_de"]').html(options);
        $('select[name="troca_para"]').html(options);
        $('input[name="saving"]').val('');
        $('input[name="qtd"]').val('');
        $('input[name="saving_show"]').val('').css('color', '');

        // Prorrogação: mantém valores alinhados às acomodações do hospital selecionado
        let prorrogOptions = '<option value=""></option>';
        acomodacoesUnicas.forEach(ac => {
            const valorNum = parseFloat(String(ac.valor_aco ?? '0').replace(',', '.')) || 0;
            prorrogOptions +=
                `<option value="${ac.acomodacao_aco}" data-valor="${valorNum}">${ac.acomodacao_aco}</option>`;
        });

        const prorrogSelects = document.querySelectorAll(
            '#container-prorrog select[name="acomod_solicitada_pror"], #container-prorrog select[name="acomod1_pror"]'
        );
        prorrogSelects.forEach((sel) => {
            const prevValue = sel.value || '';
            const prevText = sel.options?.[sel.selectedIndex]?.text || prevValue;
            sel.innerHTML = prorrogOptions;
            const match = Array.from(sel.options).find(opt =>
                opt.value === prevValue || opt.text === prevValue || opt.text === prevText
            );
            if (match) {
                sel.value = match.value;
            }
        });

        if (typeof window.generateProrJSON === 'function') {
            window.generateProrJSON();
        }
        window.__PRORROG_ACOMOD_VALOR_MAP = prorrogValorMap;
        if (typeof window.recalculateProrrogSavings === 'function') {
            window.recalculateProrrogSavings();
        }
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

document.addEventListener('DOMContentLoaded', function() {
    window.triggerInternacaoCheck = window.triggerInternacaoCheck || function() {};
    var pacienteSelect = document.getElementById('fk_paciente_int');
    var retroInput = document.getElementById('retroativa_confirmada');
    var retroContainer = document.getElementById('retroativa-container');
    var retroBanner = document.getElementById('retroativa-alert');
    var retroText = document.getElementById('retroativa-alert-text');
    var internadoSelect = document.getElementById('internado_int');
    var dataAltaField = document.getElementById('data_alta_alt');
    var modalEl = document.getElementById('modalInternacaoAtiva');
    var modalInstance = modalEl ? new bootstrap.Modal(modalEl) : null;
    var modalHospital = document.getElementById('modalInternacaoHospital');
    var modalData = document.getElementById('modalInternacaoData');
    var confirmBtn = modalEl ? modalEl.querySelector('[data-action="confirm-retroativa"]') : null;
    var cancelBtn = modalEl ? modalEl.querySelector('[data-action="cancel-retroativa"]') : null;
    var activeInfo = null;

    function hideRetroBanner() {
        if (retroContainer) retroContainer.classList.add('d-none');
        if (retroBanner) retroBanner.classList.add('d-none');
        if (retroInput) retroInput.value = '0';
        if (typeof window.setAltaObrigatoriaForRetroativa === 'function') {
            window.setAltaObrigatoriaForRetroativa(false, { clearValues: true });
        }
    }

    function showRetroBanner(info) {
        if (!retroBanner || !retroText) return;
        var hosp = info?.hospital || 'hospital não informado';
        var data = info?.data_formatada || 'data não informada';
        retroText.textContent = "Paciente internado no " + hosp + " desde " + data +
            ". Informe a alta ao lançar esta internação retroativa.";
        if (retroContainer) retroContainer.classList.remove('d-none');
        retroBanner.classList.remove('d-none');
    }

    function formatDateTimeLocal(dateObj) {
        if (!(dateObj instanceof Date)) return '';
        var local = new Date(dateObj.getTime() - dateObj.getTimezoneOffset() * 60000);
        return local.toISOString().slice(0, 16);
    }

    function forcarAltaCampos() {
        if (internadoSelect && internadoSelect.value !== 'n') {
            internadoSelect.value = 'n';
            internadoSelect.dispatchEvent(new Event('change'));
        }
        if (dataAltaField && !dataAltaField.value) {
            dataAltaField.value = formatDateTimeLocal(new Date());
        }
    }

    function consultarInternacaoAtiva(pacienteId, skipModal) {
        if (!pacienteId) {
            hideRetroBanner();
            activeInfo = null;
            return;
        }
        fetch('ajax/check_internacao_ativa.php?id_paciente=' + encodeURIComponent(pacienteId))
            .then(function(resp) {
                return resp.json();
            })
            .then(function(data) {
                if (!data || !data.success) {
                    throw new Error(data?.error || 'Erro ao consultar internação ativa.');
                }
                if (data.hasActive) {
                    activeInfo = data.active;
                    if (modalHospital) modalHospital.textContent = data.active.hospital || '—';
                    if (modalData) modalData.textContent = data.active.data_formatada || '—';
                    if (!skipModal && modalInstance) {
                        modalInstance.show();
                    }
                } else {
                    activeInfo = null;
                    hideRetroBanner();
                }
            })
            .catch(function(err) {
                console.error('Falha ao verificar internação ativa:', err);
            });
    }

    if (pacienteSelect) {
        pacienteSelect.addEventListener('change', function() {
            hideRetroBanner();
            consultarInternacaoAtiva(this.value, false);
        });
        if (window.jQuery && jQuery.fn && typeof jQuery.fn.on === 'function') {
            jQuery(function($) {
                $('#fk_paciente_int').on('changed.bs.select', function() {
                    hideRetroBanner();
                    consultarInternacaoAtiva(this.value, false);
                });
            });
        }
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (retroInput) retroInput.value = '1';
            if (activeInfo) showRetroBanner(activeInfo);
            if (typeof window.setAltaObrigatoriaForRetroativa === 'function') {
                window.setAltaObrigatoriaForRetroativa(true, { clearValues: false });
            }
            forcarAltaCampos();
            modalInstance && modalInstance.hide();
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            modalInstance && modalInstance.hide();
            activeInfo = null;
            if (pacienteSelect) {
                pacienteSelect.value = '';
                if (window.jQuery && jQuery.fn.selectpicker && jQuery(pacienteSelect).hasClass(
                        'selectpicker')) {
                    jQuery(pacienteSelect).selectpicker('val', '');
                }
            }
        });
    }

    window.triggerInternacaoCheck = function() {
        if (pacienteSelect) {
            consultarInternacaoAtiva(pacienteSelect.value, false);
        }
    };
});

document.addEventListener('DOMContentLoaded', function() {
    var senhaInput = document.getElementById('senha_int');
    var senhaModalEl = document.getElementById('modalSenhaDuplicada');
    var senhaModal = senhaModalEl ? new bootstrap.Modal(senhaModalEl) : null;
    var senhaTexto = document.getElementById('modalSenhaDuplicadaTexto');
    var senhaDuplicadaFlag = false;

    function verificarSenhaDuplicada(valor) {
        if (!valor) {
            senhaDuplicadaFlag = false;
            return;
        }
        fetch('ajax/check_senha_internacao.php?senha=' + encodeURIComponent(valor))
            .then(function(resp) {
                return resp.json();
            })
            .then(function(data) {
                if (data && data.success && data.exists) {
                    senhaDuplicadaFlag = true;
                    if (senhaTexto) {
                        senhaTexto.textContent = 'A senha "' + valor +
                            '" já está vinculada a outra internação. Informe uma senha diferente.';
                    }
                    if (senhaModal) senhaModal.show();
                } else {
                    senhaDuplicadaFlag = false;
                }
            })
            .catch(function(err) {
                console.error('Erro ao verificar senha:', err);
            });
    }

    if (senhaInput) {
        senhaInput.addEventListener('blur', function() {
            var valor = (this.value || '').trim();
            if (valor) verificarSenhaDuplicada(valor);
        });
        senhaInput.addEventListener('input', function() {
            senhaDuplicadaFlag = false;
        });
    }

    window.isSenhaDuplicada = function() {
        return senhaDuplicadaFlag;
    };
});

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

    window.triggerInternacaoAutoSave = triggerInternacaoAutoSave;
    window.aumentarText = aumentarText;
    window.reduzirText = reduzirText;
    window.myFunctionSelected = myFunctionSelected;
    window.mirrorVisitMedFromFk = mirrorVisitMedFromFk;

})(window, document);

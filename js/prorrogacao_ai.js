(function() {
    var initialized = false;

    function byId(id) {
        return document.getElementById(id);
    }

    function setStatus(message, type) {
        var status = byId('prorrog-ia-status');
        if (!status) return;
        status.textContent = message || '';
        status.className = 'prorrog-ia-status' + (type ? ' prorrog-ia-status--' + type : '');
        status.hidden = !message;
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function levelLabel(value) {
        if (value === 'SEMI_UTI') return 'Semi-UTI';
        if (value === 'UTI') return 'UTI';
        if (value === 'INTERNACAO') return 'Internação';
        if (value === 'DESOSPITALIZACAO') return 'Desospitalização';
        return 'Indeterminado';
    }

    function levelClass(value) {
        if (value === 'UTI') return 'prorrog-ia-badge prorrog-ia-badge--danger';
        if (value === 'SEMI_UTI') return 'prorrog-ia-badge prorrog-ia-badge--warn';
        if (value === 'INTERNACAO') return 'prorrog-ia-badge prorrog-ia-badge--info';
        if (value === 'DESOSPITALIZACAO') return 'prorrog-ia-badge prorrog-ia-badge--ok';
        return 'prorrog-ia-badge prorrog-ia-badge--neutral';
    }

    function openPanel() {
        var body = byId('prorrog-ia-body');
        var toggle = byId('btn-toggle-prorrog-ia');
        if (!body || !toggle) return;
        body.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        toggle.innerHTML = '<i class="bi bi-chevron-up"></i>';
    }

    function togglePanel() {
        var body = byId('prorrog-ia-body');
        var toggle = byId('btn-toggle-prorrog-ia');
        if (!body || !toggle) return;
        var willOpen = body.hidden;
        body.hidden = !willOpen;
        toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        toggle.innerHTML = willOpen ? '<i class="bi bi-chevron-up"></i>' : '<i class="bi bi-chevron-down"></i>';
    }

    function listHtml(items, emptyText) {
        if (!Array.isArray(items) || !items.length) {
            return '<p class="prorrog-ia-empty">' + escapeHtml(emptyText) + '</p>';
        }
        return '<ul>' + items.map(function(item) {
            return '<li>' + escapeHtml(item) + '</li>';
        }).join('') + '</ul>';
    }

    function renderResult(data) {
        var content = byId('prorrog-ia-content');
        if (!content) return;

        var level = data && data.nivel_recomendado ? data.nivel_recomendado : 'INDETERMINADO';
        content.innerHTML = ''
            + '<div class="prorrog-ia-result-head">'
            + '<span class="' + levelClass(level) + '">' + escapeHtml(levelLabel(level)) + '</span>'
            + '</div>'
            + '<div class="prorrog-ia-section"><strong>Resumo clínico</strong><p>' + escapeHtml(data.resumo_clinico || '-') + '</p></div>'
            + '<div class="prorrog-ia-section"><strong>Justificativa</strong><p>' + escapeHtml(data.justificativa || '-') + '</p></div>'
            + '<div class="prorrog-ia-section"><strong>Sinais favoráveis</strong>' + listHtml(data.sinais_favoraveis, 'Sem sinais favoráveis destacados.') + '</div>'
            + '<div class="prorrog-ia-section"><strong>Riscos / pendências</strong>' + listHtml(data.riscos_pendencias, 'Sem pendências destacadas.') + '</div>'
            + '<div class="prorrog-ia-final-alert">' + escapeHtml(data.frase_final || 'Parecer IA de prorrogação concluído.') + '</div>';
    }

    function buildContext() {
        var chunks = [];
        var report = byId('rel_visita_vis') || byId('rel_int');
        var actions = byId('acoes_int_vis') || byId('acoes_int');
        var plan = byId('programacao_enf') || byId('programacao_int');
        var extra = byId('prorrog-ia-contexto');
        var acomod = byId('acomod1_pror');
        var ini = byId('prorrog1_ini_pror');
        var fim = byId('prorrog1_fim_pror');
        var isol = byId('isol_1_pror');

        if (report && report.value.trim()) chunks.push('RELATORIO DA AUDITORIA:\n' + report.value.trim());
        if (actions && actions.value.trim()) chunks.push('ACOES DA AUDITORIA:\n' + actions.value.trim());
        if (plan && plan.value.trim()) chunks.push('PROGRAMACAO TERAPEUTICA:\n' + plan.value.trim());
        if (acomod && acomod.value) chunks.push('ACOMODACAO SOLICITADA NA PRORROGACAO: ' + acomod.value);
        if (ini && ini.value) chunks.push('DATA INICIAL DA PRORROGACAO: ' + ini.value);
        if (fim && fim.value) chunks.push('DATA FINAL DA PRORROGACAO: ' + fim.value);
        if (isol && isol.value) chunks.push('ISOLAMENTO: ' + (isol.value === 's' ? 'Sim' : 'Não'));
        if (extra && extra.value.trim()) chunks.push('CONTEXTO COMPLEMENTAR:\n' + extra.value.trim());

        return chunks.join('\n\n').trim();
    }

    function setupPrompt() {
        var button = byId('btn-executar-prorrog-ia');
        if (!button) return;
        if (button.dataset.aiBound === '1') return;
        button.dataset.aiBound = '1';

        button.addEventListener('click', async function() {
            var report = buildContext();
            if (!report) {
                setStatus('Preencha o relatório da auditoria ou o contexto complementar antes de executar.', 'error');
                return;
            }

            button.disabled = true;
            openPanel();
            setStatus('Gerando parecer de prorrogação...', 'info');

            try {
                var baseUrl = (window.prorrogAiConfig && window.prorrogAiConfig.baseUrl) || '';
                var response = await fetch(baseUrl + 'ajax/prorrogacao_ai.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ report: report })
                });
                var raw = await response.text();
                var payload = {};
                try {
                    payload = raw ? JSON.parse(raw) : {};
                } catch (parseError) {
                    throw new Error(raw ? String(raw).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : 'Resposta inválida do servidor.');
                }
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || payload.error || 'Falha ao gerar parecer de prorrogação.');
                }
                renderResult(payload.data || {});
                setStatus('Parecer de prorrogação atualizado.', 'success');
            } catch (error) {
                setStatus(error.message || 'Erro ao executar IA de prorrogação.', 'error');
            } finally {
                button.disabled = false;
            }
        });
    }

    function init() {
        if (initialized) return;
        initialized = true;

        var toggle = byId('btn-toggle-prorrog-ia');
        if (toggle && toggle.dataset.aiBound !== '1') {
            toggle.dataset.aiBound = '1';
            toggle.addEventListener('click', togglePanel);
        }
        setupPrompt();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

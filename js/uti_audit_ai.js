(function() {
    function byId(id) {
        return document.getElementById(id);
    }

    function setStatus(message, type) {
        var status = byId('parecer-ia-status');
        if (!status) return;
        status.textContent = message || '';
        status.className = 'parecer-ia-status' + (type ? ' parecer-ia-status--' + type : '');
        status.hidden = !message;
    }

    function openPanel() {
        var body = byId('parecer-ia-body');
        var toggle = byId('btn-toggle-parecer-ia');
        if (!body || !toggle) return;
        body.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        toggle.innerHTML = '<i class="bi bi-chevron-up"></i>';
    }

    function togglePanel() {
        var body = byId('parecer-ia-body');
        var toggle = byId('btn-toggle-parecer-ia');
        if (!body || !toggle) return;
        var willOpen = body.hidden;
        body.hidden = !willOpen;
        toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        toggle.innerHTML = willOpen ? '<i class="bi bi-chevron-up"></i>' : '<i class="bi bi-chevron-down"></i>';
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

    function labelClass(value) {
        if (value === 'JUSTIFICADO') return 'parecer-ia-badge parecer-ia-badge--ok';
        if (value === 'NAO_JUSTIFICADO') return 'parecer-ia-badge parecer-ia-badge--bad';
        return 'parecer-ia-badge parecer-ia-badge--neutral';
    }

    function readableKey(key) {
        return String(key || '').replace(/_/g, ' ');
    }

    function renderResult(data) {
        var content = byId('parecer-ia-content');
        if (!content) return;

        var criterios = data && data.criterios ? data.criterios : {};
        var criterioHtml = Object.keys(criterios).map(function(key) {
            return '<li><strong>' + escapeHtml(readableKey(key)) + ':</strong> ' + escapeHtml(criterios[key]) + '</li>';
        }).join('');

        var pendencias = Array.isArray(data && data.pendencias_documentais) ? data.pendencias_documentais : [];
        var pendenciasHtml = pendencias.length
            ? '<ul>' + pendencias.map(function(item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') + '</ul>'
            : '<p class="parecer-ia-empty">Sem pendências documentais apontadas.</p>';

        var classificacao = data && data.classificacao ? data.classificacao : 'DADOS_INSUFICIENTES';
        content.innerHTML = ''
            + '<div class="parecer-ia-result-head">'
            + '<span class="' + labelClass(classificacao) + '">' + escapeHtml(classificacao.replace(/_/g, ' ')) + '</span>'
            + '</div>'
            + '<div class="parecer-ia-section"><strong>Resumo clínico</strong><p>' + escapeHtml(data.resumo_clinico || '-') + '</p></div>'
            + '<div class="parecer-ia-section"><strong>Critérios</strong><ul>' + criterioHtml + '</ul></div>'
            + '<div class="parecer-ia-section"><strong>Justificativa técnica</strong><p>' + escapeHtml(data.justificativa_tecnica || '-') + '</p></div>'
            + '<div class="parecer-ia-section"><strong>Pendências documentais</strong>' + pendenciasHtml + '</div>';
    }

    async function extractPdfText(file) {
        if (!window.pdfjsLib) {
            throw new Error('Leitor de PDF indisponível.');
        }

        var buffer = await file.arrayBuffer();
        var pdf = await window.pdfjsLib.getDocument({ data: buffer }).promise;
        var pages = [];

        for (var pageNum = 1; pageNum <= pdf.numPages; pageNum += 1) {
            var page = await pdf.getPage(pageNum);
            var content = await page.getTextContent();
            var text = content.items.map(function(item) {
                return item.str || '';
            }).join(' ');
            if (text.trim()) {
                pages.push(text.trim());
            }
        }

        return pages.join('\n\n').trim();
    }

    function setupPdfReader() {
        var button = byId('btn-ler-pdf-auditoria');
        var input = byId('pdf-auditoria-input');
        var relatorio = byId('rel_int');
        if (!button || !input || !relatorio) return;

        button.addEventListener('click', function() {
            input.click();
        });

        input.addEventListener('change', async function() {
            var file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) return;

            button.disabled = true;
            setStatus('Lendo PDF...', 'info');
            try {
                var text = await extractPdfText(file);
                if (!text) {
                    throw new Error('Não foi possível extrair texto do PDF.');
                }
                relatorio.value = text.slice(0, Number(relatorio.getAttribute('maxlength') || 5000));
                relatorio.dispatchEvent(new Event('input', { bubbles: true }));
                setStatus('PDF lido e relatório preenchido.', 'success');
            } catch (error) {
                setStatus(error.message || 'Erro ao ler PDF.', 'error');
            } finally {
                button.disabled = false;
                input.value = '';
            }
        });
    }

    function setupAiPrompt() {
        var button = byId('btn-executar-prompt-uti');
        var relatorio = byId('rel_int');
        if (!button || !relatorio) return;

        button.addEventListener('click', async function() {
            var report = relatorio.value.trim();
            if (!report) {
                setStatus('Informe ou leia um relatório de auditoria antes de executar.', 'error');
                relatorio.focus();
                return;
            }

            button.disabled = true;
            openPanel();
            setStatus('Gerando parecer IA...', 'info');

            try {
                var baseUrl = (window.formInternacaoConfig && window.formInternacaoConfig.baseUrl) || '';
                var response = await fetch(baseUrl + 'ajax/uti_audit_ai.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ report: report })
                });
                var payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || payload.error || 'Falha ao gerar parecer.');
                }
                renderResult(payload.data || {});
                setStatus('Parecer IA atualizado.', 'success');
            } catch (error) {
                setStatus(error.message || 'Erro ao executar prompt UTI.', 'error');
            } finally {
                button.disabled = false;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (window.pdfjsLib && window.pdfjsLib.GlobalWorkerOptions) {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
        var toggle = byId('btn-toggle-parecer-ia');
        if (toggle) {
            toggle.addEventListener('click', togglePanel);
        }
        setupPdfReader();
        setupAiPrompt();
    });
})();

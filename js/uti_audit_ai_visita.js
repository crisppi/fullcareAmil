(function() {
    function byId(id) {
        return document.getElementById(id);
    }

    function setStatus(message, type) {
        var status = byId('parecer-visita-ia-status');
        if (!status) return;
        status.textContent = message || '';
        status.className = 'parecer-ia-status' + (type ? ' parecer-ia-status--' + type : '');
        status.hidden = !message;
    }

    function openPanel() {
        var body = byId('parecer-visita-ia-body');
        var toggle = byId('btn-toggle-parecer-visita-ia');
        if (!body || !toggle) return;
        body.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        toggle.innerHTML = '<i class="bi bi-chevron-up"></i>';
    }

    function togglePanel() {
        var body = byId('parecer-visita-ia-body');
        var toggle = byId('btn-toggle-parecer-visita-ia');
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

    function levelLabel(value) {
        if (value === 'SEMI_UTI') return 'Semi-UTI';
        if (value === 'UTI') return 'UTI';
        if (value === 'APTO') return 'Apto';
        return 'Indeterminado';
    }

    function levelClass(value) {
        if (value === 'UTI') return 'parecer-ia-badge parecer-ia-badge--danger';
        if (value === 'SEMI_UTI') return 'parecer-ia-badge parecer-ia-badge--warn';
        if (value === 'APTO') return 'parecer-ia-badge parecer-ia-badge--info';
        return 'parecer-ia-badge parecer-ia-badge--neutral';
    }

    function readableKey(key) {
        return String(key || '').replace(/_/g, ' ');
    }

    function renderResult(data) {
        var content = byId('parecer-visita-ia-content');
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
        var nivel = data && data.nivel_recomendado ? data.nivel_recomendado : 'INDETERMINADO';
        var fraseFinal = data && data.frase_final ? data.frase_final : '';

        content.innerHTML = ''
            + '<div class="parecer-ia-result-head">'
            + '<div class="parecer-ia-chip-row">'
            + '<span class="' + labelClass(classificacao) + '">' + escapeHtml(classificacao.replace(/_/g, ' ')) + '</span>'
            + '<span class="' + levelClass(nivel) + '">' + escapeHtml(levelLabel(nivel)) + '</span>'
            + '</div>'
            + '</div>'
            + '<div class="parecer-ia-section"><strong>Resumo clínico</strong><p>' + escapeHtml(data.resumo_clinico || '-') + '</p></div>'
            + '<div class="parecer-ia-section"><strong>Critérios</strong><ul>' + criterioHtml + '</ul></div>'
            + '<div class="parecer-ia-section"><strong>Justificativa técnica</strong><p>' + escapeHtml(data.justificativa_tecnica || '-') + '</p></div>'
            + '<div class="parecer-ia-section"><strong>Pendências documentais</strong>' + pendenciasHtml + '</div>'
            + '<div class="parecer-ia-final-alert">' + escapeHtml(fraseFinal || 'Parecer IA concluído.') + '</div>';
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

    async function extractImageText(file) {
        if (!window.Tesseract || typeof window.Tesseract.recognize !== 'function') {
            throw new Error('Leitor de imagem indisponível.');
        }

        var result = await window.Tesseract.recognize(file, 'por+eng');
        return String(result && result.data && result.data.text ? result.data.text : '').trim();
    }

    async function extractFileText(file) {
        var type = String(file && file.type || '').toLowerCase();
        var name = String(file && file.name || '').toLowerCase();

        if (type === 'application/pdf' || /\.pdf$/i.test(name)) {
            return extractPdfText(file);
        }

        if (type.indexOf('image/') === 0 || /\.(png|jpg|jpeg|webp|bmp)$/i.test(name)) {
            return extractImageText(file);
        }

        throw new Error('Formato não suportado. Use PDF, JPG, JPEG ou PNG.');
    }

    function setupPdfReader() {
        var button = byId('btn-ler-pdf-visita');
        var input = byId('pdf-visita-input');
        var relatorio = byId('rel_visita_vis');
        if (!button || !input || !relatorio) return;

        button.addEventListener('click', function() {
            input.click();
        });

        input.addEventListener('change', async function() {
            var file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) return;

            button.disabled = true;
            setStatus('Lendo arquivo...', 'info');
            try {
                var text = await extractFileText(file);
                if (!text) {
                    throw new Error('Não foi possível extrair texto do arquivo.');
                }
                relatorio.value = text.slice(0, Number(relatorio.getAttribute('maxlength') || 5000));
                relatorio.dispatchEvent(new Event('input', { bubbles: true }));
                setStatus('Arquivo lido e relatório preenchido.', 'success');
            } catch (error) {
                setStatus(error.message || 'Erro ao ler arquivo.', 'error');
            } finally {
                button.disabled = false;
                input.value = '';
            }
        });
    }

    function setupAiPrompt() {
        var button = byId('btn-executar-prompt-uti-visita');
        var relatorio = byId('rel_visita_vis');
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
                var baseUrl = (window.visitaAiConfig && window.visitaAiConfig.baseUrl) || '';
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
        var toggle = byId('btn-toggle-parecer-visita-ia');
        if (toggle) {
            toggle.addEventListener('click', togglePanel);
        }
        setupPdfReader();
        setupAiPrompt();
    });
})();

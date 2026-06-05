(function () {
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function byId(id) {
        return document.getElementById(id);
    }

    function esc(value) {
        return String(value || '').replace(/[&<>"']/g, function (ch) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[ch];
        });
    }

    function cleanText(value) {
        return String(value || '')
            .replace(/\r/g, '\n')
            .replace(/[ \t]+/g, ' ')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function setValue(id, value, append) {
        const el = byId(id);
        if (!el || !value) return;
        const current = cleanText(el.value);
        el.value = append && current ? current + '\n\n' + value : value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function openPanel() {
        const body = byId('parecer-ia-body');
        const toggle = byId('btn-toggle-parecer-ia');
        if (body) body.hidden = false;
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
            const icon = toggle.querySelector('i');
            if (icon) {
                icon.classList.remove('bi-chevron-down');
                icon.classList.add('bi-chevron-up');
            }
        }
    }

    function setStatus(message, type) {
        const status = byId('parecer-ia-status');
        if (!status) return;
        status.hidden = false;
        status.textContent = message;
        status.classList.remove('is-success', 'is-error', 'is-info', 'parecer-ia-status--success', 'parecer-ia-status--error', 'parecer-ia-status--info');
        if (type === 'error') {
            status.classList.add('is-error', 'parecer-ia-status--error');
        } else if (type === 'success') {
            status.classList.add('is-success', 'parecer-ia-status--success');
        } else {
            status.classList.add('is-info', 'parecer-ia-status--info');
        }
    }

    function renderResult(title, text, extraHtml) {
        const content = byId('parecer-ia-content');
        if (!content) return;
        const preview = cleanText(text).slice(0, 2500);
        content.innerHTML = [
            '<div class="parecer-ia-section">',
            '<h5>' + esc(title) + '</h5>',
            extraHtml || '',
            preview ? '<pre style="white-space:pre-wrap;font:inherit;margin:8px 0 0;">' + esc(preview) + '</pre>' : '',
            '</div>'
        ].join('');
    }

    async function readPdf(file) {
        if (!window.pdfjsLib) {
            throw new Error('Leitor de PDF não carregou. Atualize a página e tente novamente.');
        }
        if (window.pdfjsLib.GlobalWorkerOptions && !window.pdfjsLib.GlobalWorkerOptions.workerSrc) {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
        const buffer = await file.arrayBuffer();
        const pdf = await window.pdfjsLib.getDocument({ data: buffer }).promise;
        const parts = [];
        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum += 1) {
            const page = await pdf.getPage(pageNum);
            const text = await page.getTextContent();
            const line = text.items.map(function (item) { return item.str || ''; }).join(' ');
            if (line.trim()) parts.push(line);
        }
        return cleanText(parts.join('\n\n'));
    }

    async function readImage(file) {
        if (!window.Tesseract || typeof window.Tesseract.recognize !== 'function') {
            throw new Error('Leitor de imagem não carregou. Atualize a página e tente novamente.');
        }
        const result = await window.Tesseract.recognize(file, 'por+eng');
        return cleanText(result && result.data ? result.data.text : '');
    }

    async function extractText(file) {
        const type = String(file.type || '').toLowerCase();
        if (type === 'application/pdf' || /\.pdf$/i.test(file.name)) {
            return readPdf(file);
        }
        if (type.indexOf('image/') === 0 || /\.(png|jpe?g)$/i.test(file.name)) {
            return readImage(file);
        }
        throw new Error('Formato não suportado. Use PDF, PNG ou JPG.');
    }

    function buildUtiOpinion() {
        const rel = cleanText((byId('rel_int') || {}).value);
        const acoes = cleanText((byId('acoes_int') || {}).value);
        const prog = cleanText((byId('programacao_int') || {}).value);
        const acomodacao = cleanText((byId('acomodacao_int') || {}).value);
        const text = [rel, acoes, prog, acomodacao].join(' ').toLowerCase();
        const sinais = [];
        [
            ['ventilacao mecanica', /ventila[cç][aã]o|vm\b|intuba/],
            ['droga vasoativa', /droga vasoativa|dva|noradrenalina|vasopressor/],
            ['instabilidade hemodinamica', /instabilidade|hemodin[aâ]mica|choque|hipotens/],
            ['sepse/infeccao grave', /sepse|s[eé]ptico|infec[cç][aã]o grave/],
            ['rebaixamento neurologico', /glasgow|rebaixamento|coma|convuls/]
        ].forEach(function (item) {
            if (item[1].test(text)) sinais.push(item[0]);
        });
        const pertinente = acomodacao.toUpperCase() === 'UTI' || sinais.length > 0;
        return {
            pertinente,
            sinais,
            texto: [
                pertinente ? 'Parecer preliminar: há indícios clínicos compatíveis com suporte intensivo.' : 'Parecer preliminar: não encontrei critérios fortes de UTI no texto disponível.',
                sinais.length ? 'Sinais encontrados: ' + sinais.join(', ') + '.' : 'Sinais encontrados: nenhum critério objetivo foi detectado automaticamente.',
                'Revise o texto extraído e complemente com dados clínicos antes de salvar.'
            ].join('\n')
        };
    }

    ready(function () {
        const readBtn = byId('btn-ler-pdf-auditoria');
        const input = byId('pdf-auditoria-input');
        const promptBtn = byId('btn-executar-prompt-uti');

        if (readBtn && input) {
            readBtn.addEventListener('click', function () {
                input.click();
            });

            input.addEventListener('change', async function () {
                const file = input.files && input.files[0];
                if (!file) return;
                const originalHtml = readBtn.innerHTML;
                readBtn.disabled = true;
                readBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Lendo...';
                openPanel();
                setStatus('Lendo arquivo e extraindo texto...', 'info');
                try {
                    const text = await extractText(file);
                    if (!text) throw new Error('Não encontrei texto legível no arquivo.');
                    setValue('rel_int', text, true);
                    renderResult('Texto extraído de ' + file.name, text, '<p>O conteúdo foi adicionado ao Relatório de Auditoria.</p>');
                    setStatus('Arquivo lido com sucesso. Revise o relatório antes de salvar.', 'success');
                } catch (error) {
                    renderResult('Falha ao ler arquivo', error.message || 'Erro inesperado.', '');
                    setStatus(error.message || 'Erro ao ler arquivo.', 'error');
                } finally {
                    input.value = '';
                    readBtn.disabled = false;
                    readBtn.innerHTML = originalHtml;
                }
            });
        }

        if (promptBtn) {
            promptBtn.addEventListener('click', function () {
                const opinion = buildUtiOpinion();
                openPanel();
                renderResult('Parecer preliminar UTI', opinion.texto, opinion.sinais.length ? '<p><strong>Critérios:</strong> ' + esc(opinion.sinais.join(', ')) + '</p>' : '');
                setStatus('Parecer preliminar gerado localmente. Revise antes de salvar.', opinion.pertinente ? 'success' : 'info');
            });
        }
    });
})();

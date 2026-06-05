(function () {
    const config = window.clinicalTextToolsConfig || {};
    const fields = Array.isArray(config.fields) ? config.fields : [];
    const draftKey = config.draftKey || '';
    const statusEl = config.autosaveStatusId ? document.getElementById(config.autosaveStatusId) : null;
    const autosaveEnabled = config.autosave !== false;
    const restoreDrafts = config.restoreDrafts !== false;

    function setStatus(text) {
        if (statusEl) statusEl.textContent = text;
    }

    function getDrafts() {
        if (!draftKey || !window.localStorage) return {};
        try {
            return JSON.parse(localStorage.getItem(draftKey) || '{}') || {};
        } catch (error) {
            return {};
        }
    }

    function saveDrafts(data) {
        if (!draftKey || !window.localStorage) return;
        try {
            localStorage.setItem(draftKey, JSON.stringify(data));
            setStatus('Rascunho automatico: salvo');
        } catch (error) {
            setStatus('Rascunho automatico: indisponivel');
        }
    }

    function clearDraft() {
        if (!draftKey || !window.localStorage) return;
        try {
            localStorage.removeItem(draftKey);
            setStatus(autosaveEnabled ? 'Rascunho automatico: limpo' : 'Rascunho local limpo');
        } catch (error) {
            setStatus('Rascunho automatico: indisponivel');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!fields.length) return;

        const drafts = getDrafts();
        const inputs = fields
            .map(function (id) { return document.getElementById(id); })
            .filter(Boolean);

        document.querySelectorAll('[data-clear-clinical-draft]').forEach(function (button) {
            button.addEventListener('click', clearDraft);
        });

        if (!autosaveEnabled) {
            clearDraft();
            setStatus('Alteracoes salvam somente ao clicar em Atualizar');
            return;
        }

        inputs.forEach(function (input) {
            if (restoreDrafts && !input.value && drafts[input.id]) {
                input.value = drafts[input.id];
            }

            input.addEventListener('input', function () {
                const nextDrafts = getDrafts();
                nextDrafts[input.id] = input.value;
                saveDrafts(nextDrafts);
            });
        });

        const form = inputs.length ? inputs[0].closest('form') : null;
        if (form && draftKey && window.localStorage) {
            form.addEventListener('submit', function () {
                try {
                    localStorage.removeItem(draftKey);
                } catch (error) {
                    // Local storage is optional; form submission should not depend on it.
                }
            });
        }
    });
})();

(function() {
    'use strict';

    const config = window.clinicalTextToolsConfig || {};
    const fields = Array.isArray(config.fields) && config.fields.length
        ? config.fields
        : Array.from(document.querySelectorAll('[data-counter-for]')).map(function(counter) {
            return counter.getAttribute('data-counter-for');
        }).filter(Boolean);

    if (!fields.length) {
        return;
    }

    const maxLength = Number(config.maxLength || 5000);
    const baseUrl = config.baseUrl || '';
    const draftKey = config.draftKey || ('fullcare:clinical-text:' + window.location.pathname + window.location.search);
    const statusEl = document.getElementById(config.autosaveStatusId || 'clinical-autosave-status');
    const initialValues = {};
    let saveTimer = null;

    function byId(id) {
        return document.getElementById(id);
    }

    function fieldValue(id) {
        const field = byId(id);
        return field ? field.value : '';
    }

    function setFieldValue(id, value) {
        const field = byId(id);
        if (!field) return;
        field.value = value || '';
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setStatus(text) {
        if (statusEl) {
            statusEl.textContent = text;
        }
    }

    function normalizeText(text) {
        return String(text || '')
            .replace(/\r\n/g, '\n')
            .replace(/[ \t]+\n/g, '\n')
            .replace(/\n{3,}/g, '\n\n')
            .replace(/[ \t]{2,}/g, ' ')
            .trim();
    }

    function updateCounter(id) {
        const field = byId(id);
        const counter = document.querySelector('[data-counter-for="' + id + '"]');
        if (!field || !counter) return;

        const length = field.value.length;
        counter.textContent = length + '/' + maxLength;
        counter.classList.toggle('text-danger', length > maxLength);
        counter.classList.toggle('text-muted', length <= maxLength);
    }

    function snapshot() {
        const values = {};
        fields.forEach(function(id) {
            values[id] = fieldValue(id);
        });
        return values;
    }

    function sameValues(left, right) {
        return fields.every(function(id) {
            return String((left || {})[id] || '') === String((right || {})[id] || '');
        });
    }

    function saveDraft() {
        try {
            localStorage.setItem(draftKey, JSON.stringify({
                values: snapshot(),
                initial: initialValues,
                updatedAt: Date.now()
            }));
            setStatus('Rascunho automático: salvo');
        } catch (error) {
            setStatus('Rascunho automático: indisponível');
        }
    }

    function scheduleSave() {
        setStatus('Rascunho automático: salvando...');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveDraft, 350);
    }

    function restoreDraft() {
        try {
            const raw = localStorage.getItem(draftKey);
            if (!raw) return;

            const draft = JSON.parse(raw);
            if (!draft || !draft.values) return;

            const current = snapshot();
            const allEmpty = fields.every(function(id) {
                return String(current[id] || '') === '';
            });
            const stillOriginal = draft.initial && sameValues(current, draft.initial);

            if (!allEmpty && !stillOriginal) {
                return;
            }

            fields.forEach(function(id) {
                if (Object.prototype.hasOwnProperty.call(draft.values, id)) {
                    setFieldValue(id, draft.values[id]);
                }
            });
            setStatus('Rascunho automático: restaurado');
        } catch (error) {
            setStatus('Rascunho automático: ativo');
        }
    }

    function clearDraft(clearFields) {
        try {
            localStorage.removeItem(draftKey);
        } catch (error) {}

        if (clearFields) {
            fields.forEach(function(id) {
                setFieldValue(id, '');
                updateCounter(id);
            });
        }

        setStatus(clearFields ? 'Rascunho limpo' : 'Rascunho automático: limpo');
    }

    function requestImprove(id, button) {
        const field = byId(id);
        if (!field || !field.value.trim()) {
            setStatus('Informe um texto para organizar.');
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Organizando...';
        setStatus('Organizando texto com IA...');

        fetch(baseUrl + 'ajax/clinical_text_ai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'improve',
                field: id,
                text: field.value
            })
        })
            .then(function(response) {
                return response.json().then(function(payload) {
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Não foi possível organizar o texto.');
                    }
                    return payload;
                });
            })
            .then(function(payload) {
                const improved = payload && payload.data ? payload.data.text : '';
                if (improved) {
                    setFieldValue(id, improved);
                    saveDraft();
                    setStatus('Texto organizado com IA.');
                }
            })
            .catch(function(error) {
                setStatus(error.message || 'Falha ao organizar com IA.');
            })
            .finally(function() {
                button.disabled = false;
                button.textContent = originalText;
            });
    }

    function init() {
        fields.forEach(function(id) {
            const field = byId(id);
            if (!field) return;

            initialValues[id] = field.value || '';
            updateCounter(id);

            ['input', 'keyup', 'change', 'paste'].forEach(function(eventName) {
                field.addEventListener(eventName, function() {
                    setTimeout(function() {
                        updateCounter(id);
                        scheduleSave();
                    }, eventName === 'paste' ? 0 : 0);
                });
            });
        });

        restoreDraft();
        fields.forEach(updateCounter);

        document.querySelectorAll('[data-clean-text]').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = button.getAttribute('data-clean-text');
                const field = byId(id);
                if (!field) return;

                field.value = normalizeText(field.value);
                field.dispatchEvent(new Event('input', { bubbles: true }));
                updateCounter(id);
                saveDraft();
                setStatus('Formatação limpa.');
            });
        });

        document.querySelectorAll('[data-ai-improve]').forEach(function(button) {
            button.addEventListener('click', function() {
                requestImprove(button.getAttribute('data-ai-improve'), button);
            });
        });

        document.querySelectorAll('[data-clear-clinical-draft]').forEach(function(button) {
            button.addEventListener('click', function() {
                clearDraft(button.getAttribute('data-clear-clinical-draft') === 'fields');
            });
        });

        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function() {
                clearDraft(false);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

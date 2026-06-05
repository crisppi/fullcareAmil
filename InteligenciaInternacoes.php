<?php
include_once("check_logado.php");
require_once("templates/header.php");
require_once("ajax/_auth_scope.php");
require_once("app/services/InternacaoChatService.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexão não disponível.");
}

$ctx = ajax_user_context($conn);
$chatService = new InternacaoChatService($conn, $BASE_URL);
$hospitais = $chatService->listHospitals($ctx);
?>

<style>
.intern-chat-page {
    padding: 0 14px 28px;
    color: #27364a;
}
.intern-chat-page .fc-module-header {
    margin-bottom: 12px;
}
.intern-chat-shell {
    display: grid;
    grid-template-columns: 300px minmax(0, 1fr) 360px;
    gap: 12px;
    align-items: stretch;
}
.intern-chat-panel {
    background: #fff;
    border: 1px solid #dceaf2;
    border-radius: 8px;
    box-shadow: 0 8px 20px rgba(31, 76, 110, .08);
    min-height: 0;
}
.intern-chat-filters {
    padding: 16px;
    background:
        linear-gradient(165deg, rgba(47, 111, 159, .98) 0%, rgba(35, 82, 124, .98) 52%, rgba(32, 139, 122, .96) 100%);
    border-color: rgba(31, 76, 110, .22);
    color: #fff;
    box-shadow: 0 14px 30px rgba(31, 76, 110, .2);
    overflow: hidden;
}
.intern-chat-filters h2,
.intern-chat-results h2 {
    margin: 0 0 12px;
    font-size: .78rem;
    text-transform: uppercase;
    color: #2f6f9f;
    font-weight: 800;
}
.intern-chat-filters h2 {
    color: #fff;
    letter-spacing: .02em;
}
.intern-chat-field {
    margin-bottom: 12px;
}
.intern-chat-field label {
    display: block;
    margin-bottom: 5px;
    color: #526277;
    font-size: .68rem;
    font-weight: 800;
    text-transform: uppercase;
}
.intern-chat-filters .intern-chat-field label {
    color: rgba(255, 255, 255, .78);
}
.intern-chat-field select {
    width: 100%;
    height: 38px;
    border: 1px solid #cadde8;
    border-radius: 8px;
    color: #26384f;
    padding: 0 10px;
    background: #f9fcff;
}
.intern-chat-filters .intern-chat-field select {
    border-color: rgba(255, 255, 255, .28);
    background: rgba(255, 255, 255, .96);
    color: #223247;
    box-shadow: 0 8px 18px rgba(18, 48, 78, .16);
}
.intern-chat-suggestions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 14px;
}
.intern-chat-suggestion {
    border: 1px solid rgba(255, 255, 255, .18);
    border-left: 4px solid rgba(255, 255, 255, .72);
    background: rgba(255, 255, 255, .14);
    color: #fff;
    border-radius: 8px;
    padding: 9px 10px;
    text-align: left;
    font-size: .78rem;
    font-weight: 800;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .14);
}
.intern-chat-suggestion:hover {
    background: rgba(255, 255, 255, .24);
    border-top-color: rgba(255, 255, 255, .34);
    border-right-color: rgba(255, 255, 255, .34);
    border-bottom-color: rgba(255, 255, 255, .34);
    transform: translateY(-1px);
}
.intern-chat-suggestion:nth-child(4n + 1) { border-left-color: #8fd6ff; }
.intern-chat-suggestion:nth-child(4n + 2) { border-left-color: #b7f0cf; }
.intern-chat-suggestion:nth-child(4n + 3) { border-left-color: #ffd36e; }
.intern-chat-suggestion:nth-child(4n + 4) { border-left-color: #ff9aa6; }
.intern-chat-suggestion:active {
    transform: translateY(0);
}
.intern-chat-main {
    display: flex;
    flex-direction: column;
    min-height: 640px;
}
.intern-chat-messages {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    background: #f7fbfd;
    border-radius: 8px 8px 0 0;
}
.intern-chat-entry {
    width: min(86%, 780px);
    margin-bottom: 12px;
}
.intern-chat-entry.user {
    margin-left: auto;
}
.intern-chat-meta {
    display: flex;
    gap: 8px;
    align-items: center;
    margin: 0 0 5px;
    color: #637286;
    font-size: .66rem;
    font-weight: 700;
}
.intern-chat-entry.user .intern-chat-meta {
    justify-content: flex-end;
}
.intern-chat-speaker {
    color: #24384f;
}
.intern-chat-time {
    color: #7b8ca0;
    font-weight: 600;
}
.intern-chat-message {
    width: 100%;
    padding: 12px 14px;
    border-radius: 8px;
    line-height: 1.45;
    white-space: pre-wrap;
}
.intern-chat-message.user {
    margin-left: auto;
    background: #2f6f9f;
    color: #fff;
}
.intern-chat-message.assistant {
    margin-right: auto;
    background: linear-gradient(180deg, #ffffff 0%, #f2f8fc 100%);
    border: 1px solid #c7deea;
    border-left: 4px solid #5eb4d8;
    color: #26384f;
    box-shadow: 0 10px 24px rgba(31, 76, 110, .12);
}
.intern-chat-composer {
    display: flex;
    gap: 10px;
    padding: 12px;
    border-top: 1px solid #dbe8f0;
    background: #fff;
    border-radius: 0 0 8px 8px;
}
.intern-chat-composer textarea {
    flex: 1;
    min-height: 48px;
    max-height: 130px;
    border: 1px solid #cadde8;
    border-radius: 8px;
    padding: 10px 12px;
    resize: vertical;
}
.intern-chat-send {
    width: 48px;
    border: 0;
    border-radius: 8px;
    background: linear-gradient(145deg, #5e3db8, #1aa58d);
    color: #fff;
    font-size: 1.05rem;
    box-shadow: 0 8px 18px rgba(94, 61, 184, .22);
}
.intern-chat-clear {
    width: 44px;
    border: 1px solid #d5e3ec;
    border-radius: 8px;
    background: #f7fbfd;
    color: #5c6f84;
    font-size: 1rem;
}
.intern-chat-clear:hover {
    background: #edf5fa;
    color: #26384f;
}
.intern-chat-results {
    padding: 14px;
    overflow-y: auto;
    max-height: 720px;
}
.intern-chat-result {
    display: block;
    text-decoration: none;
    color: inherit;
    border: 1px solid #e2edf4;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 10px;
    background: #fbfdff;
}
.intern-chat-result strong {
    display: block;
    color: #24384f;
    font-size: .82rem;
}
.intern-chat-result small {
    display: block;
    color: #69788b;
    margin-top: 3px;
}
.intern-chat-flags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 8px;
}
.intern-chat-flag {
    font-size: .62rem;
    font-weight: 800;
    color: #2f6f9f;
    background: #edf7ff;
    border: 1px solid #cfe4f2;
    border-radius: 999px;
    padding: 3px 7px;
}
.intern-chat-empty {
    color: #7b8796;
    font-size: .82rem;
}
@media (max-width: 1200px) {
    .intern-chat-shell {
        grid-template-columns: 260px minmax(0, 1fr);
    }
    .intern-chat-results {
        grid-column: 1 / -1;
        max-height: none;
    }
}
@media (max-width: 820px) {
    .intern-chat-shell {
        grid-template-columns: 1fr;
    }
    .intern-chat-main {
        min-height: 560px;
    }
}
</style>

<div class="intern-chat-page">
    <div class="fc-module-header fc-module-header--inteligencia">
        <div class="fc-module-header__copy">
            <p class="fc-module-header__kicker">Inteligência Operacional</p>
            <h1 class="fc-module-header__title">IA de Internações</h1>
            <p class="fc-module-header__subtitle">Converse sobre internações, custos, faturamento, saving, negociações, eventos de gestão, visitas, UTI e próximos passos operacionais.</p>
        </div>
        <div class="fc-module-header__actions">
            <a class="btn btn-light btn-sm" href="<?= htmlspecialchars($BASE_URL . 'internacoes/lista', ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi bi-list-ul"></i> Internações
            </a>
        </div>
    </div>

    <div class="intern-chat-shell">
        <aside class="intern-chat-panel intern-chat-filters">
            <h2>Filtros</h2>
            <div class="intern-chat-field">
                <label for="chatHospital">Hospital</label>
                <select id="chatHospital">
                    <option value="">Todos os hospitais</option>
                    <?php foreach ($hospitais as $h): ?>
                        <option value="<?= (int)$h['id_hospital'] ?>"><?= htmlspecialchars((string)$h['nome_hosp'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="intern-chat-field">
                <label for="chatStatus">Status</label>
                <select id="chatStatus">
                    <option value="internados">Internados</option>
                    <option value="todos">Todos</option>
                    <option value="alta">Com alta</option>
                </select>
            </div>
            <div class="intern-chat-field">
                <label for="chatDays">Período</label>
                <select id="chatDays">
                    <option value="30">Últimos 30 dias</option>
                    <option value="90">Últimos 90 dias</option>
                    <option value="180" selected>Últimos 180 dias</option>
                    <option value="365">Últimos 12 meses</option>
                    <option value="730">Últimos 24 meses</option>
                </select>
            </div>

            <div class="intern-chat-suggestions">
                <button type="button" class="intern-chat-suggestion" data-question="Quais internações merecem prioridade hoje e por quê?">Casos prioritários hoje</button>
                <button type="button" class="intern-chat-suggestion" data-question="Resuma saving, negociações e maiores concentrações por hospital no período.">Saving e negociações</button>
                <button type="button" class="intern-chat-suggestion" data-question="Resuma faturamento, glosa, contas abertas e contas paradas no período.">Faturamento e glosa</button>
                <button type="button" class="intern-chat-suggestion" data-question="Resuma eventos de gestão, alto custo, OPME, home care e desospitalização.">Eventos de gestão</button>
                <button type="button" class="intern-chat-suggestion" data-question="Liste os principais casos de longa permanência e possíveis próximos passos operacionais.">Longa permanência</button>
                <button type="button" class="intern-chat-suggestion" data-question="Quais pacientes estão sem visita recente e qual o impacto operacional?">Sem visita recente</button>
                <button type="button" class="intern-chat-suggestion" data-question="Resuma eventos adversos ou sinais de atenção nas internações filtradas.">Eventos e atenção</button>
            </div>
        </aside>

        <main class="intern-chat-panel intern-chat-main">
            <div id="chatMessages" class="intern-chat-messages">
                <div class="intern-chat-entry assistant">
                    <div class="intern-chat-meta">
                        <span class="intern-chat-speaker">FullCare - IA</span>
                        <span class="intern-chat-time"><?= htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="intern-chat-message assistant">Olá. Posso analisar as internações filtradas e destacar riscos, pendências, longa permanência, visitas atrasadas e próximos passos operacionais.</div>
                </div>
            </div>
            <form id="chatForm" class="intern-chat-composer">
                <textarea id="chatQuestion" placeholder="Pergunte sobre internações, saving, faturamento, glosa, eventos, visitas ou UTI..." required></textarea>
                <button class="intern-chat-send" type="submit" title="Enviar"><i class="bi bi-send"></i></button>
                <button id="chatClear" class="intern-chat-clear" type="button" title="Limpar conteúdo"><i class="bi bi-x-lg"></i></button>
            </form>
        </main>

        <aside class="intern-chat-panel intern-chat-results">
            <h2>Resultados citados</h2>
            <div id="chatResults" class="intern-chat-empty">Os casos relacionados à resposta aparecerão aqui.</div>
        </aside>
    </div>
</div>

<script>
(function() {
    const endpoint = <?= json_encode($BASE_URL . 'ajax/internacao_chat.php') ?>;
    const messages = document.getElementById('chatMessages');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatQuestion');
    const results = document.getElementById('chatResults');
    const clearBtn = document.getElementById('chatClear');
    const initialMessage = 'Olá. Posso analisar as internações filtradas e destacar riscos, pendências, longa permanência, visitas atrasadas e próximos passos operacionais.';
    const initialResults = 'Os casos relacionados à resposta aparecerão aqui.';
    const loggedUserName = <?= json_encode(trim((string)($_SESSION['usuario_user'] ?? $_SESSION['login_user'] ?? $_SESSION['email_user'] ?? 'Usuário')) ?: 'Usuário') ?>;

    function filters() {
        return {
            hospital_id: document.getElementById('chatHospital').value,
            status: document.getElementById('chatStatus').value,
            risk: 'geral',
            days: document.getElementById('chatDays').value
        };
    }

    function addMessage(type, text) {
        const entry = document.createElement('div');
        entry.className = 'intern-chat-entry ' + type;

        const meta = document.createElement('div');
        meta.className = 'intern-chat-meta';

        const speaker = document.createElement('span');
        speaker.className = 'intern-chat-speaker';
        speaker.textContent = type === 'user' ? loggedUserName : 'FullCare - IA';

        const time = document.createElement('span');
        time.className = 'intern-chat-time';
        time.textContent = formatMessageDate(new Date());

        meta.appendChild(speaker);
        meta.appendChild(time);

        const el = document.createElement('div');
        el.className = 'intern-chat-message ' + type;
        el.textContent = text;

        entry.appendChild(meta);
        entry.appendChild(el);
        messages.appendChild(entry);
        messages.scrollTop = messages.scrollHeight;
        return el;
    }

    function formatMessageDate(date) {
        return date.toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function renderResults(items) {
        if (!items || !items.length) {
            results.className = 'intern-chat-empty';
            results.textContent = 'Nenhum caso estruturado retornado para esta pergunta.';
            return;
        }
        results.className = '';
        results.innerHTML = items.map(function(item) {
            const flags = (item.flags || []).map(function(flag) {
                return '<span class="intern-chat-flag">' + escapeHtml(flag) + '</span>';
            }).join('');
            return '<a class="intern-chat-result" href="' + escapeAttr(item.url) + '">' +
                '<strong>#' + escapeHtml(String(item.id)) + ' · ' + escapeHtml(item.paciente || 'Paciente') + '</strong>' +
                '<small>' + escapeHtml(item.hospital || '-') + ' · ' + escapeHtml(item.seguradora || '-') + '</small>' +
                '<small>' + escapeHtml(String(item.dias_internado || 0)) + ' dia(s) internado(s)' +
                (item.dias_sem_visita !== null && item.dias_sem_visita !== undefined ? ' · ' + escapeHtml(String(item.dias_sem_visita)) + ' dia(s) sem visita' : '') +
                '</small>' +
                (flags ? '<div class="intern-chat-flags">' + flags + '</div>' : '') +
                '</a>';
        }).join('');
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function(ch) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[ch];
        });
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/`/g, '&#096;');
    }

    function resetContent() {
        input.value = '';
        messages.innerHTML = '';
        addMessage('assistant', initialMessage);
        results.className = 'intern-chat-empty';
        results.textContent = initialResults;
        input.focus();
    }

    async function send(question) {
        addMessage('user', question);
        input.value = '';
        const waiting = addMessage('assistant', 'Analisando internações filtradas...');
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({question: question, filters: filters()})
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Não foi possível gerar a resposta.');
            }
            waiting.textContent = data.answer || 'Sem resposta.';
            renderResults(data.results || []);
        } catch (err) {
            waiting.textContent = 'Não consegui responder agora: ' + (err && err.message ? err.message : 'erro inesperado');
        }
    }

    form.addEventListener('submit', function(event) {
        event.preventDefault();
        const question = input.value.trim();
        if (question) {
            send(question);
        }
    });

    document.querySelectorAll('.intern-chat-suggestion').forEach(function(btn) {
        btn.addEventListener('click', function() {
            send(btn.getAttribute('data-question') || btn.textContent);
        });
    });

    clearBtn.addEventListener('click', resetContent);
})();
</script>

<?php include_once("templates/footer.php"); ?>

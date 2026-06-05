<?php
include_once("check_logado.php");
require_once("templates/header.php");
require_once("ajax/_auth_scope.php");
require_once("app/services/AuditoriaClinicaAIService.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao nao disponivel.");
}

function fc_clinical_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$ctx = ajax_user_context($conn);
$clinicalService = new AuditoriaClinicaAIService($conn, $BASE_URL);
$hospitais = $clinicalService->listHospitals($ctx);
$clinicalScopeMode = function_exists('ajax_scope_mode') ? ajax_scope_mode($ctx) : 'hospital';
$clinicalHospitalScoped = ($clinicalScopeMode === 'hospital');
?>

<style>
.clinical-ai-page {
    padding: 0 14px 28px;
    color: #26364b;
}
.clinical-ai-page .fc-module-header {
    margin-bottom: 12px;
}
.clinical-ai-shell {
    display: grid;
    grid-template-columns: 300px minmax(0, 1fr) 360px;
    gap: 12px;
    align-items: start;
}
.clinical-ai-panel {
    background: #fff;
    border: 1px solid #dceaf2;
    border-radius: 8px;
    box-shadow: 0 8px 20px rgba(31, 76, 110, .08);
    min-height: 0;
}
.clinical-ai-filters {
    padding: 16px;
    background:
        linear-gradient(165deg, rgba(32, 139, 122, .98) 0%, rgba(38, 103, 139, .98) 52%, rgba(84, 71, 167, .95) 100%);
    border-color: rgba(31, 76, 110, .22);
    color: #fff;
    box-shadow: 0 14px 30px rgba(31, 76, 110, .2);
    overflow: hidden;
}
.clinical-ai-filters h2,
.clinical-ai-results h2 {
    margin: 0 0 12px;
    font-size: .78rem;
    text-transform: uppercase;
    color: #2f6f9f;
    font-weight: 800;
}
.clinical-ai-filters h2 {
    color: #fff;
    letter-spacing: .02em;
}
.clinical-ai-field {
    margin-bottom: 12px;
}
.clinical-ai-field label {
    display: block;
    margin-bottom: 5px;
    color: rgba(255, 255, 255, .78);
    font-size: .68rem;
    font-weight: 800;
    text-transform: uppercase;
}
.clinical-ai-field select {
    width: 100%;
    height: 38px;
    border: 1px solid rgba(255, 255, 255, .28);
    border-radius: 8px;
    color: #223247;
    padding: 0 10px;
    background: rgba(255, 255, 255, .96);
    box-shadow: 0 8px 18px rgba(18, 48, 78, .16);
}
.clinical-ai-note {
    border: 1px solid rgba(255, 255, 255, .2);
    border-radius: 8px;
    padding: 9px 10px;
    background: rgba(255, 255, 255, .12);
    color: rgba(255, 255, 255, .9);
    font-size: .74rem;
    line-height: 1.35;
}
.clinical-ai-suggestions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 14px;
}
.clinical-ai-suggestion {
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
.clinical-ai-suggestion:hover {
    background: rgba(255, 255, 255, .24);
    border-top-color: rgba(255, 255, 255, .34);
    border-right-color: rgba(255, 255, 255, .34);
    border-bottom-color: rgba(255, 255, 255, .34);
    transform: translateY(-1px);
}
.clinical-ai-suggestion:nth-child(4n + 1) { border-left-color: #b7f0cf; }
.clinical-ai-suggestion:nth-child(4n + 2) { border-left-color: #8fd6ff; }
.clinical-ai-suggestion:nth-child(4n + 3) { border-left-color: #ffd36e; }
.clinical-ai-suggestion:nth-child(4n + 4) { border-left-color: #d7b8ff; }
.clinical-ai-main {
    display: flex;
    flex-direction: column;
    height: clamp(430px, calc(100vh - 360px), 620px);
    min-height: 0;
}
.clinical-ai-messages {
    flex: 1;
    min-height: 0;
    padding: 16px;
    overflow-y: auto;
    background:
        linear-gradient(180deg, rgba(247, 251, 253, .96), rgba(245, 248, 252, .96));
    border-radius: 8px 8px 0 0;
}
.clinical-ai-entry {
    width: min(86%, 780px);
    margin-bottom: 12px;
}
.clinical-ai-entry.user {
    margin-left: auto;
}
.clinical-ai-meta {
    display: flex;
    gap: 8px;
    align-items: center;
    margin: 0 0 5px;
    color: #637286;
    font-size: .66rem;
    font-weight: 700;
}
.clinical-ai-entry.user .clinical-ai-meta {
    justify-content: flex-end;
}
.clinical-ai-speaker {
    color: #24384f;
}
.clinical-ai-time {
    color: #7b8ca0;
    font-weight: 600;
}
.clinical-ai-message {
    width: 100%;
    padding: 12px 14px;
    border-radius: 8px;
    line-height: 1.45;
    white-space: pre-wrap;
}
.clinical-ai-message.user {
    margin-left: auto;
    background: #2d7c73;
    color: #fff;
}
.clinical-ai-message.assistant {
    margin-right: auto;
    background: linear-gradient(180deg, #ffffff 0%, #f2f8fc 100%);
    border: 1px solid #c7deea;
    border-left: 4px solid #5eb4d8;
    color: #26384f;
    box-shadow: 0 10px 24px rgba(31, 76, 110, .12);
}
.clinical-ai-composer {
    display: flex;
    gap: 10px;
    flex: 0 0 auto;
    padding: 8px 10px;
    border-top: 1px solid #dbe8f0;
    background: #fff;
    border-radius: 0 0 8px 8px;
}
.clinical-ai-composer textarea {
    flex: 1;
    min-height: 38px;
    max-height: 82px;
    border: 1px solid #cadde8;
    border-radius: 8px;
    padding: 8px 11px;
    resize: vertical;
}
.clinical-ai-send {
    width: 42px;
    border: 0;
    border-radius: 8px;
    background: linear-gradient(145deg, #1aa58d, #5e3db8);
    color: #fff;
    font-size: 1.05rem;
    box-shadow: 0 8px 18px rgba(26, 165, 141, .22);
}
.clinical-ai-clear {
    width: 40px;
    border: 1px solid #d5e3ec;
    border-radius: 8px;
    background: #f7fbfd;
    color: #5c6f84;
    font-size: 1rem;
}
.clinical-ai-clear:hover {
    background: #edf5fa;
    color: #26384f;
}
.clinical-ai-results {
    padding: 14px;
    overflow-y: auto;
    max-height: clamp(430px, calc(100vh - 360px), 620px);
}
.clinical-ai-result {
    display: block;
    text-decoration: none;
    color: inherit;
    border: 1px solid #e2edf4;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 10px;
    background: #fbfdff;
}
.clinical-ai-result strong {
    display: block;
    color: #24384f;
    font-size: .82rem;
}
.clinical-ai-result small {
    display: block;
    color: #69788b;
    margin-top: 3px;
}
.clinical-ai-flags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 8px;
}
.clinical-ai-flag {
    font-size: .62rem;
    font-weight: 800;
    color: #257468;
    background: #edf9f6;
    border: 1px solid #caebe4;
    border-radius: 999px;
    padding: 3px 7px;
}
.clinical-ai-empty {
    color: #7b8796;
    font-size: .82rem;
}
@media (max-width: 1200px) {
    .clinical-ai-shell {
        grid-template-columns: 260px minmax(0, 1fr);
    }
    .clinical-ai-results {
        grid-column: 1 / -1;
        max-height: none;
    }
}
@media (max-width: 820px) {
    .clinical-ai-shell {
        grid-template-columns: 1fr;
    }
    .clinical-ai-main {
        height: min(560px, calc(100vh - 300px));
    }
}
</style>

<div class="clinical-ai-page">
    <div class="fc-module-header fc-module-header--producao">
        <div class="fc-module-header__copy">
            <p class="fc-module-header__kicker">Producao</p>
            <h1 class="fc-module-header__title">IA Cl&iacute;nica</h1>
            <p class="fc-module-header__subtitle">Pesquisa para auditoria assistencial com foco em internacao, patologia, UTI, visitas e eventos clinicos. Sem custos, faturamento ou saving real.</p>
        </div>
        <div class="fc-module-header__actions">
            <a class="btn btn-light btn-sm" href="<?= fc_clinical_e($BASE_URL . 'internacoes/lista') ?>">
                <i class="bi bi-list-ul"></i> Internacoes
            </a>
        </div>
    </div>

    <div class="clinical-ai-shell">
        <aside class="clinical-ai-panel clinical-ai-filters">
            <h2>Filtros</h2>
            <div class="clinical-ai-field">
                <label for="clinicalHospital">Hospital</label>
                <select id="clinicalHospital">
                    <option value=""><?= $clinicalHospitalScoped ? 'Todos os meus hospitais' : 'Todos os hospitais' ?></option>
                    <?php foreach ($hospitais as $h): ?>
                        <option value="<?= (int)$h['id_hospital'] ?>"><?= fc_clinical_e($h['nome_hosp']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="clinical-ai-field">
                <label for="clinicalStatus">Status</label>
                <select id="clinicalStatus">
                    <option value="internados">Internados</option>
                    <option value="todos">Todos</option>
                    <option value="alta">Com alta</option>
                </select>
            </div>
            <div class="clinical-ai-field">
                <label for="clinicalFocus">Foco clinico</label>
                <select id="clinicalFocus">
                    <option value="geral">Geral</option>
                    <option value="uti">UTI</option>
                    <option value="eventos">Eventos adversos</option>
                    <option value="patologia">Patologia</option>
                    <option value="longa_permanencia">Longa permanencia</option>
                    <option value="sem_visita">Sem visita recente</option>
                    <option value="oportunidade">Oportunidade qualitativa</option>
                </select>
            </div>
            <div class="clinical-ai-field">
                <label for="clinicalDays">Periodo</label>
                <select id="clinicalDays">
                    <option value="30">Ultimos 30 dias</option>
                    <option value="90">Ultimos 90 dias</option>
                    <option value="180" selected>Ultimos 180 dias</option>
                    <option value="365">Ultimos 12 meses</option>
                    <option value="730">Ultimos 24 meses</option>
                </select>
            </div>

            <div class="clinical-ai-suggestions">
                <button type="button" class="clinical-ai-suggestion" data-question="Quais casos precisam de revisao clinica hoje e por que?">Casos para revisar hoje</button>
                <button type="button" class="clinical-ai-suggestion" data-question="Resuma os pacientes com registro de UTI e os pontos de auditoria clinica.">UTI e pontos de atencao</button>
                <button type="button" class="clinical-ai-suggestion" data-question="Quais patologias concentram maior permanencia no periodo?">Patologias e permanencia</button>
                <button type="button" class="clinical-ai-suggestion" data-question="Liste eventos adversos, tipo de evento e pendencias clinicas registradas.">Eventos adversos</button>
                <button type="button" class="clinical-ai-suggestion" data-question="Onde pode existir oportunidade qualitativa de economia assistencial sem analisar valores?">Oportunidade qualitativa</button>
                <button type="button" class="clinical-ai-suggestion" data-question="Quais internacoes estao sem visita recente e precisam atualizar evolucao?">Sem visita recente</button>
            </div>
        </aside>

        <main class="clinical-ai-panel clinical-ai-main">
            <div id="clinicalMessages" class="clinical-ai-messages">
                <div class="clinical-ai-entry assistant">
                    <div class="clinical-ai-meta">
                        <span class="clinical-ai-speaker">FullCare - IA</span>
                        <span class="clinical-ai-time"><?= fc_clinical_e(date('d/m/Y H:i')) ?></span>
                    </div>
                    <div class="clinical-ai-message assistant">Ola. Posso pesquisar as internacoes filtradas com foco clinico para auditoria: patologia, permanencia, UTI, visitas, eventos adversos e oportunidades qualitativas de cuidado.</div>
                </div>
            </div>
            <form id="clinicalForm" class="clinical-ai-composer">
                <textarea id="clinicalQuestion" placeholder="Pergunte sobre quadro clinico, patologia, UTI, eventos, visitas ou permanencia..." required></textarea>
                <button class="clinical-ai-send" type="submit" title="Enviar"><i class="bi bi-send"></i></button>
                <button id="clinicalClear" class="clinical-ai-clear" type="button" title="Limpar conteúdo"><i class="bi bi-x-lg"></i></button>
            </form>
        </main>

        <aside class="clinical-ai-panel clinical-ai-results">
            <h2>Casos citados</h2>
            <div id="clinicalResults" class="clinical-ai-empty">As internacoes relacionadas a resposta aparecerao aqui.</div>
        </aside>
    </div>
</div>

<script>
(function() {
    const endpoint = <?= json_encode($BASE_URL . 'ajax/producao_ia_clinica.php') ?>;
    const messages = document.getElementById('clinicalMessages');
    const form = document.getElementById('clinicalForm');
    const input = document.getElementById('clinicalQuestion');
    const results = document.getElementById('clinicalResults');
    const clearBtn = document.getElementById('clinicalClear');
    const initialMessage = 'Ola. Posso pesquisar as internacoes filtradas com foco clinico para auditoria: patologia, permanencia, UTI, visitas, eventos adversos e oportunidades qualitativas de cuidado.';
    const initialResults = 'As internacoes relacionadas a resposta aparecerao aqui.';
    const loggedUserName = <?= json_encode(trim((string)($_SESSION['usuario_user'] ?? $_SESSION['login_user'] ?? $_SESSION['email_user'] ?? 'Usuário')) ?: 'Usuário') ?>;

    function filters() {
        return {
            hospital_id: document.getElementById('clinicalHospital').value,
            status: document.getElementById('clinicalStatus').value,
            focus: document.getElementById('clinicalFocus').value,
            days: document.getElementById('clinicalDays').value
        };
    }

    function addMessage(type, text) {
        const entry = document.createElement('div');
        entry.className = 'clinical-ai-entry ' + type;

        const meta = document.createElement('div');
        meta.className = 'clinical-ai-meta';

        const speaker = document.createElement('span');
        speaker.className = 'clinical-ai-speaker';
        speaker.textContent = type === 'user' ? loggedUserName : 'FullCare - IA';

        const time = document.createElement('span');
        time.className = 'clinical-ai-time';
        time.textContent = formatMessageDate(new Date());

        meta.appendChild(speaker);
        meta.appendChild(time);

        const el = document.createElement('div');
        el.className = 'clinical-ai-message ' + type;
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
            results.className = 'clinical-ai-empty';
            results.textContent = 'Nenhum caso estruturado retornado para esta pergunta.';
            return;
        }
        results.className = '';
        results.innerHTML = items.map(function(item) {
            const flags = (item.flags || []).map(function(flag) {
                return '<span class="clinical-ai-flag">' + escapeHtml(flag) + '</span>';
            }).join('');
            return '<a class="clinical-ai-result" href="' + escapeAttr(item.url) + '">' +
                '<strong>#' + escapeHtml(String(item.id)) + ' &middot; ' + escapeHtml(item.paciente || 'Paciente') + '</strong>' +
                '<small>' + escapeHtml(item.hospital || '-') + '</small>' +
                '<small>' + escapeHtml(item.patologia || 'Sem patologia') + '</small>' +
                '<small>' + escapeHtml(String(item.dias_internado || 0)) + ' dia(s) de permanencia' +
                (item.dias_sem_visita !== null && item.dias_sem_visita !== undefined ? ' &middot; ' + escapeHtml(String(item.dias_sem_visita)) + ' dia(s) sem visita' : '') +
                '</small>' +
                (flags ? '<div class="clinical-ai-flags">' + flags + '</div>' : '') +
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
        results.className = 'clinical-ai-empty';
        results.textContent = initialResults;
        input.focus();
    }

    async function send(question) {
        addMessage('user', question);
        input.value = '';
        const waiting = addMessage('assistant', 'Analisando internacoes pelo recorte clinico...');
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({question: question, filters: filters()})
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Nao foi possivel gerar a resposta.');
            }
            waiting.textContent = data.answer || 'Sem resposta.';
            renderResults(data.results || []);
        } catch (err) {
            waiting.textContent = 'Nao consegui responder agora: ' + (err && err.message ? err.message : 'erro inesperado');
        }
    }

    form.addEventListener('submit', function(event) {
        event.preventDefault();
        const question = input.value.trim();
        if (question) {
            send(question);
        }
    });

    document.querySelectorAll('.clinical-ai-suggestion').forEach(function(btn) {
        btn.addEventListener('click', function() {
            send(btn.getAttribute('data-question') || btn.textContent);
        });
    });

    clearBtn.addEventListener('click', resetContent);
})();
</script>

<?php include_once("templates/footer.php"); ?>

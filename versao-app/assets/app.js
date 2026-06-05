const API_URL = `${window.location.origin}/fullcareAmil/api/mobile/index.php`;
const TOKEN_KEY = "fullcare_mobile_web_token";

const state = {
    token: localStorage.getItem(TOKEN_KEY) || "",
    user: null,
    admissions: [],
    currentAdmission: null,
    evolutions: [],
    dischargeTypes: [],
    tussCatalog: [],
    selectedTuss: null,
};

const authScreen = document.getElementById("auth-screen");
const appScreen = document.getElementById("app-screen");
const feedback = document.getElementById("feedback");
const loginForm = document.getElementById("login-form");
const logoutButton = document.getElementById("logout-button");
const refreshButton = document.getElementById("refresh-button");
const searchInput = document.getElementById("search-input");
const admissionsList = document.getElementById("admissions-list");
const admissionsTotal = document.getElementById("admissions-total");
const admissionsView = document.getElementById("admissions-view");
const detailView = document.getElementById("detail-view");
const evolutionsView = document.getElementById("evolutions-view");
const detailCard = document.getElementById("detail-card");
const latestExtensionPanel = document.getElementById("latest-extension-panel");
const latestExtensionContent = document.getElementById("latest-extension-content");
const tussList = document.getElementById("tuss-list");
const extensionsList = document.getElementById("extensions-list");
const tussCount = document.getElementById("tuss-count");
const extensionsCount = document.getElementById("extensions-count");
const userName = document.getElementById("user-name");
const userRole = document.getElementById("user-role");
const evolutionsPatientName = document.getElementById("evolutions-patient-name");
const evolutionsList = document.getElementById("evolutions-list");
const modalShell = document.getElementById("modal-shell");
const modalTitle = document.getElementById("modal-title");
const modalForm = document.getElementById("modal-form");
const closeModalButton = document.getElementById("close-modal");
const emptyCardTemplate = document.getElementById("empty-card-template");

document.getElementById("back-to-list").addEventListener("click", () => showView("admissions"));
document.getElementById("back-to-detail").addEventListener("click", () => showView("detail"));
document.getElementById("open-tuss-modal").addEventListener("click", openTussModal);
document.getElementById("open-extension-modal").addEventListener("click", openExtensionModal);
document.getElementById("open-discharge-modal").addEventListener("click", openDischargeModal);
document.getElementById("open-evolutions-view").addEventListener("click", openEvolutionsView);
document.getElementById("open-evolution-modal").addEventListener("click", openEvolutionModal);
closeModalButton.addEventListener("click", closeModal);
modalShell.addEventListener("click", (event) => {
    if (event.target.hasAttribute("data-close-modal")) {
        closeModal();
    }
});

function showFeedback(message, type = "success") {
    feedback.hidden = false;
    feedback.className = `feedback ${type}`;
    feedback.textContent = message;

    window.clearTimeout(showFeedback.timer);
    showFeedback.timer = window.setTimeout(() => {
        feedback.hidden = true;
    }, 3200);
}

window.addEventListener("error", (event) => {
    showFeedback(event.message || "Erro inesperado na interface.", "error");
});

window.addEventListener("unhandledrejection", (event) => {
    const reason = event.reason;
    const message = reason instanceof Error ? reason.message : String(reason || "Falha assíncrona.");
    showFeedback(message, "error");
});

function formatDate(value) {
    if (!value) return "-";
    const datePart = String(value).trim().split(" ")[0];
    const bits = datePart.split("-");
    if (bits.length !== 3) return value;
    return `${bits[2]}/${bits[1]}/${bits[0]}`;
}

function formatDateTime(value) {
    if (!value) return "-";
    const parts = String(value).trim().split(" ");
    const formattedDate = formatDate(parts[0]);
    if (parts.length < 2) return formattedDate;
    const time = parts[1].split(":");
    return `${formattedDate} ${time[0] || "00"}:${time[1] || "00"}`;
}

function extensionPeriodText(item) {
    const start = item.start_date || "";
    const end = item.end_date || "";
    if (!start && !end) return "Sem datas informadas";
    if (start && end) return `${formatDate(start)} até ${formatDate(end)}`;
    if (start) return `Início: ${formatDate(start)}`;
    return `Fim: ${formatDate(end)}`;
}

function statusLabel(value) {
    return String(value || "").replaceAll("_", " ");
}

function buildEmptyCard(text) {
    const node = emptyCardTemplate.content.firstElementChild.cloneNode(true);
    node.querySelector(".empty-state").textContent = text;
    return node;
}

async function request(action, options = {}) {
    const method = options.method || "GET";
    const query = new URLSearchParams({ action, ...(options.query || {}) });
    const headers = {
        "Content-Type": "application/json",
    };

    if (state.token) {
        headers.Authorization = `Bearer ${state.token}`;
    }

    let response;
    try {
        response = await fetch(`${API_URL}?${query.toString()}`, {
            method,
            headers,
            body: options.body ? JSON.stringify(options.body) : undefined,
        });
    } catch (error) {
        throw new Error("Falha de conexão com a API mobile.");
    }

    let payload;
    try {
        payload = await response.json();
    } catch (error) {
        throw new Error("Resposta inválida da API mobile.");
    }
    if (response.status === 401) {
        clearSession();
        throw new Error(payload.message || "Sessão expirada.");
    }
    if (!response.ok || payload.success !== true) {
        throw new Error(payload.message || "Falha na requisição.");
    }

    return payload.data;
}

function saveToken(token) {
    state.token = token;
    localStorage.setItem(TOKEN_KEY, token);
}

function clearSession() {
    state.token = "";
    state.user = null;
    localStorage.removeItem(TOKEN_KEY);
    authScreen.hidden = false;
    appScreen.hidden = true;
}

function showView(name) {
    admissionsView.hidden = name !== "admissions";
    detailView.hidden = name !== "detail";
    evolutionsView.hidden = name !== "evolutions";
}

function renderUser() {
    if (!state.user) return;
    userName.textContent = state.user.name || "-";
    userRole.textContent = `${state.user.email || ""} • ${state.user.role_name || ""}`;
}

function renderAdmissions() {
    const query = searchInput.value.trim().toLowerCase();
    const filtered = state.admissions.filter((item) => {
        const haystack = [
            item.patient_name,
            item.hospital_name,
            item.insurance_name,
            item.cid_code,
            item.authorization_code,
        ].join(" ").toLowerCase();
        return !query || haystack.includes(query);
    });

    admissionsTotal.textContent = `Total de internados: ${filtered.length}`;
    admissionsList.innerHTML = "";

    if (!filtered.length) {
        admissionsList.appendChild(buildEmptyCard("Nenhuma internação encontrada."));
        return;
    }

    filtered.forEach((item) => {
        const card = document.createElement("article");
        card.className = "admission-card";
        card.innerHTML = `
            <div class="admission-card-header">
                <div>
                    <h3>${item.patient_name || "-"}</h3>
                    <p class="meta">Hospital: ${item.hospital_name || "-"}<br>Convênio: ${item.insurance_name || "-"}<br>CID: ${item.cid_code || "-"}<br>Data: ${formatDate(item.admission_date)}</p>
                </div>
                <span class="badge">Internado</span>
            </div>
            <button class="primary-button compact-inline" type="button">Abrir internação</button>
        `;
        card.querySelector("button").addEventListener("click", () => openAdmission(item.id));
        admissionsList.appendChild(card);
    });
}

function renderDetail() {
    const detail = state.currentAdmission;
    if (!detail) return;

    const admission = detail.admission;
    detailCard.innerHTML = `
        <h3>${admission.patient_name || "-"}</h3>
        <div class="meta">
            Hospital: ${admission.hospital_name || "-"}<br>
            Convênio: ${admission.insurance_name || "-"}<br>
            CID: ${admission.cid_code || "-"}<br>
            Senha: ${admission.authorization_code || "-"}<br>
            Data: ${formatDate(admission.admission_date)}<br>
            Alta: ${admission.discharge_date ? formatDate(admission.discharge_date) : "Sem alta"}
            ${admission.discharge_type ? `<br>Tipo alta: ${admission.discharge_type}` : ""}
        </div>
    `;

    const latestExtension = detail.extensions[0];
    if (latestExtension) {
        latestExtensionPanel.hidden = false;
        latestExtensionContent.innerHTML = `
            <div class="history-item">
                <strong>${extensionPeriodText(latestExtension)}</strong>
                <div class="history-meta">Diárias: ${latestExtension.days || 0}<br>Acomodação: ${latestExtension.accommodation || "-"}</div>
            </div>
        `;
    } else {
        latestExtensionPanel.hidden = true;
        latestExtensionContent.innerHTML = "";
    }

    tussCount.textContent = `${detail.tuss_items.length} item(ns)`;
    tussList.innerHTML = "";
    if (!detail.tuss_items.length) {
        tussList.appendChild(buildEmptyCard("Nenhum TUSS lançado."));
    } else {
        detail.tuss_items
            .filter((item) => String(item.code || "").trim() !== "")
            .forEach((item) => {
                const node = document.createElement("article");
                node.className = "history-item tuss";
                node.innerHTML = `
                    <strong>${item.code} • ${item.description || "-"}</strong>
                    <div class="history-meta">
                        Solicitado: ${item.requested_quantity || 0} • Liberado: ${item.released_quantity || 0} • Status: ${item.released_flag || "-"}<br>
                        Data liberação: ${item.released_at ? formatDate(item.released_at) : "-"} • Por: ${item.released_by || "-"}
                    </div>
                `;
                tussList.appendChild(node);
            });
    }

    extensionsCount.textContent = `${detail.extensions.length} registro(s)`;
    extensionsList.innerHTML = "";
    if (!detail.extensions.length) {
        extensionsList.appendChild(buildEmptyCard("Nenhuma prorrogação lançada."));
    } else {
        detail.extensions.forEach((item) => {
            const node = document.createElement("article");
            node.className = "history-item";
            node.innerHTML = `
                <strong>${extensionPeriodText(item)}</strong>
                <div class="history-meta">Diárias: ${item.days || 0} • Acomodação: ${item.accommodation || "-"}</div>
            `;
            extensionsList.appendChild(node);
        });
    }
}

function renderEvolutions() {
    evolutionsList.innerHTML = "";
    evolutionsPatientName.textContent = state.currentAdmission?.admission?.patient_name || "-";

    if (!state.evolutions.length) {
        evolutionsList.appendChild(buildEmptyCard("Nenhuma evolução registrada."));
        return;
    }

    state.evolutions.forEach((item) => {
        const node = document.createElement("article");
        node.className = "history-item evolution";
        node.innerHTML = `
            <strong>${formatDateTime(item.visited_at)}</strong>
            <div class="history-meta">Visita ${item.visit_number || 0} • ${item.created_by || "-"}</div>
            <p>${(item.report || "-").replace(/\n/g, "<br>")}</p>
        `;
        evolutionsList.appendChild(node);
    });
}

async function openAdmission(id) {
    const data = await request("admission", { query: { id: String(id) } });
    state.currentAdmission = data;
    renderDetail();
    showView("detail");
}

async function loadAdmissions() {
    const data = await request("admissions", { query: { query: searchInput.value.trim() } });
    state.admissions = data.items || [];
    renderAdmissions();
}

async function loadEvolutions() {
    if (!state.currentAdmission) return;
    const data = await request("admission-evolutions", {
        query: { id: String(state.currentAdmission.admission.id) },
    });
    state.evolutions = data.items || [];
    renderEvolutions();
}

function openModal(title) {
    modalTitle.textContent = title;
    modalForm.innerHTML = "";
    modalShell.hidden = false;
}

function closeModal() {
    modalShell.hidden = true;
    modalForm.innerHTML = "";
    state.tussCatalog = [];
    state.selectedTuss = null;
}

function createField(label, inputHtml) {
    const wrapper = document.createElement("label");
    wrapper.innerHTML = `${label}${inputHtml}`;
    return wrapper;
}

async function openTussModal() {
    openModal("Novo TUSS");
    modalForm.appendChild(createField("Consultar TUSS", '<input id="tuss-search" type="search" placeholder="Digite código ou descrição">'));
    const picker = document.createElement("div");
    picker.className = "picker-list";
    picker.id = "tuss-picker";
    modalForm.appendChild(picker);
    modalForm.appendChild(createField("Código TUSS", '<input id="tuss-code" type="text" readonly>'));
    modalForm.appendChild(createField("Qtd solicitada", '<input id="tuss-requested" type="number" min="1" value="1">'));
    modalForm.appendChild(createField("Qtd liberada", '<input id="tuss-released" type="number" min="0" value="0">'));

    const button = document.createElement("button");
    button.className = "primary-button";
    button.type = "submit";
    button.textContent = "Salvar TUSS";
    modalForm.appendChild(button);

    const searchField = modalForm.querySelector("#tuss-search");
    const codeField = modalForm.querySelector("#tuss-code");
    let timer = 0;

    async function searchTuss() {
        const query = searchField.value.trim();
        picker.innerHTML = "";
        state.selectedTuss = null;
        codeField.value = "";
        if (query.length < 2) {
            if (query.length === 1) {
                picker.innerHTML = '<p class="support-text">Digite pelo menos 2 caracteres para consultar.</p>';
            }
            return;
        }

        picker.innerHTML = '<p class="support-text">Buscando TUSS no banco...</p>';

        const data = await request("tuss-catalog", { query: { query } });
        const unique = new Map();
        (data.items || []).forEach((item) => {
            const code = String(item.code || "").trim();
            if (code && !unique.has(code)) unique.set(code, item);
        });
        state.tussCatalog = [...unique.values()];

        picker.innerHTML = "";
        if (!state.tussCatalog.length) {
            picker.innerHTML = '<p class="support-text">Nenhum TUSS encontrado.</p>';
            return;
        }

        state.tussCatalog.slice(0, 4).forEach((item) => {
            const option = document.createElement("button");
            option.type = "button";
            option.className = "picker-item";
            option.innerHTML = `<strong>${item.code}</strong><div class="history-meta">${item.description || "-"}</div>`;
            option.addEventListener("click", () => {
                state.selectedTuss = item;
                codeField.value = item.code || "";
                picker.innerHTML = `
                    <div class="selected-item">
                        <strong>${item.code || "-"}</strong>
                        <div class="history-meta">${item.description || "-"}</div>
                    </div>
                `;
            });
            picker.appendChild(option);
        });
    }

    searchField.addEventListener("input", () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            searchTuss().catch((error) => showFeedback(error.message, "error"));
        }, 320);
    });

    modalForm.onsubmit = async (event) => {
        event.preventDefault();
        if (!state.currentAdmission) return;
        if (!codeField.value.trim()) {
            showFeedback("Selecione um TUSS.", "error");
            return;
        }

        const now = new Date();
        const performedAt = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
        await request("admission-tuss", {
            method: "POST",
            body: {
                admission_id: state.currentAdmission.admission.id,
                code: codeField.value.trim(),
                requested_quantity: Number(modalForm.querySelector("#tuss-requested").value || 1),
                released_quantity: Number(modalForm.querySelector("#tuss-released").value || 0),
                released_flag: "s",
                performed_at: performedAt,
            },
        });

        closeModal();
        await openAdmission(state.currentAdmission.admission.id);
        showFeedback("TUSS salvo com sucesso.");
    };
}

function openExtensionModal() {
    openModal("Nova prorrogação");
    modalForm.appendChild(createField("Acomodação", '<input id="extension-accommodation" type="text">'));
    modalForm.appendChild(createField("Data inicial", '<input id="extension-start" type="date" required>'));
    modalForm.appendChild(createField("Data final", '<input id="extension-end" type="date" required>'));

    const button = document.createElement("button");
    button.className = "primary-button";
    button.type = "submit";
    button.textContent = "Salvar prorrogação";
    modalForm.appendChild(button);

    modalForm.onsubmit = async (event) => {
        event.preventDefault();
        if (!state.currentAdmission) return;

        const start = modalForm.querySelector("#extension-start").value;
        const end = modalForm.querySelector("#extension-end").value;
        if (!start || !end) {
            showFeedback("Selecione as duas datas.", "error");
            return;
        }

        const startDate = new Date(`${start}T00:00:00`);
        const endDate = new Date(`${end}T00:00:00`);
        const days = Math.floor((endDate - startDate) / 86400000) + 1;
        if (days <= 0) {
            showFeedback("Data final deve ser maior ou igual à inicial.", "error");
            return;
        }

        await request("admission-extension", {
            method: "POST",
            body: {
                admission_id: state.currentAdmission.admission.id,
                accommodation: modalForm.querySelector("#extension-accommodation").value.trim(),
                start_date: start,
                end_date: end,
                days,
                isolation_flag: "n",
            },
        });

        closeModal();
        await openAdmission(state.currentAdmission.admission.id);
        showFeedback("Prorrogação salva com sucesso.");
    };
}

async function openDischargeModal() {
    if (!state.dischargeTypes.length) {
        const data = await request("discharge-types");
        state.dischargeTypes = data.items || [];
    }

    openModal("Lançar alta");
    const options = state.dischargeTypes.map((item) => `<option value="${item}">${item}</option>`).join("");
    modalForm.appendChild(createField("Tipo de alta", `<select id="discharge-type">${options}</select>`));
    modalForm.appendChild(createField("Data da alta", '<input id="discharge-date" type="date" required>'));
    modalForm.appendChild(createField("Hora da alta", '<input id="discharge-time" type="time">'));

    const button = document.createElement("button");
    button.className = "primary-button";
    button.type = "submit";
    button.textContent = "Salvar alta";
    modalForm.appendChild(button);

    modalForm.onsubmit = async (event) => {
        event.preventDefault();
        if (!state.currentAdmission) return;

        await request("admission-discharge", {
            method: "POST",
            body: {
                admission_id: state.currentAdmission.admission.id,
                type: modalForm.querySelector("#discharge-type").value,
                date: modalForm.querySelector("#discharge-date").value,
                time: modalForm.querySelector("#discharge-time").value,
            },
        });

        closeModal();
        await loadAdmissions();
        showView("admissions");
        showFeedback("Alta registrada com sucesso.");
    };
}

async function openEvolutionsView() {
    await loadEvolutions();
    showView("evolutions");
}

function openEvolutionModal() {
    openModal("Nova evolução");
    modalForm.appendChild(createField("Evolução / Relatório", '<textarea id="evolution-report" rows="7" maxlength="5000" required></textarea>'));

    const button = document.createElement("button");
    button.className = "primary-button";
    button.type = "submit";
    button.textContent = "Salvar evolução";
    modalForm.appendChild(button);

    modalForm.onsubmit = async (event) => {
        event.preventDefault();
        if (!state.currentAdmission) return;

        const report = modalForm.querySelector("#evolution-report").value.trim();
        if (!report) {
            showFeedback("Informe a evolução.", "error");
            return;
        }

        await request("admission-evolution", {
            method: "POST",
            body: {
                admission_id: state.currentAdmission.admission.id,
                report,
            },
        });

        closeModal();
        await loadEvolutions();
        await openAdmission(state.currentAdmission.admission.id);
        showView("evolutions");
        showFeedback("Evolução salva com sucesso.");
    };
}

loginForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = Object.fromEntries(new FormData(loginForm).entries());

    try {
        const data = await request("login", {
            method: "POST",
            body: {
                email: formData.email,
                password: formData.password,
            },
        });
        saveToken(data.token);
        state.user = data.user;
        authScreen.hidden = true;
        appScreen.hidden = false;
        renderUser();
        await loadAdmissions();
        showView("admissions");
    } catch (error) {
        showFeedback(error.message, "error");
    }
});

logoutButton.addEventListener("click", () => {
    clearSession();
    showFeedback("Sessão encerrada.");
});

refreshButton.addEventListener("click", () => {
    loadAdmissions()
        .then(() => showFeedback("Painel atualizado."))
        .catch((error) => showFeedback(error.message, "error"));
});

searchInput.addEventListener("input", renderAdmissions);

async function bootstrap() {
    if (!state.token) {
        clearSession();
        return;
    }

    try {
        const data = await request("me");
        state.user = data;
        authScreen.hidden = true;
        appScreen.hidden = false;
        renderUser();
        await loadAdmissions();
        showView("admissions");
    } catch (error) {
        clearSession();
        showFeedback(error.message, "error");
    }
}

bootstrap();

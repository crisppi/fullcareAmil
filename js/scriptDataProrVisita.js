let dataIntP_vis = document.getElementById("data_intern_int");

let dataPro = document.getElementById("prorrog1_ini_pror");
let dataProF = document.getElementById("prorrog1_fim_pror");

let dataPro2 = document.getElementById("prorrog2_ini_pror");
let dataProF2 = document.getElementById("prorrog2_fim_pror");

let dataPro3 = document.getElementById("prorrog3_ini_pror");
let dataProF3 = document.getElementById("prorrog3_fim_pror");

function parseDateOnly(value) {
    const raw = (value || "").toString().trim();
    if (!raw) return null;

    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        const [y, m, d] = raw.split("-").map(Number);
        return new Date(y, m - 1, d);
    }

    if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) {
        const [d, m, y] = raw.split("/").map(Number);
        return new Date(y, m - 1, d);
    }

    const fallback = new Date(raw);
    return Number.isNaN(fallback.getTime()) ? null : fallback;
}

function setFieldError(field, msgSelector) {
    const msg = document.querySelector(msgSelector);
    if (msg) msg.style.display = "block";
    if (field) {
        field.style.borderColor = "red";
        field.value = "";
        field.focus();
    }
}

function clearFieldError(field, msgSelector) {
    const msg = document.querySelector(msgSelector);
    if (msg) msg.style.display = "none";
    if (field) field.style.borderColor = "#d3d3d3";
}

function setDiarias(fieldId, wrapperId, startValue, endValue) {
    const start = parseDateOnly(startValue);
    const end = parseDateOnly(endValue);
    if (!start || !end) return;

    const field = document.getElementById(fieldId);
    const wrapper = document.getElementById(wrapperId);
    const days = (end.getTime() - start.getTime()) / 86400000;

    if (wrapper) wrapper.style.display = "block";
    if (field) field.value = days;
}

if (dataPro) {
    dataPro.addEventListener("blur", function() {
        const internacao = parseDateOnly(dataIntP_vis ? dataIntP_vis.value : "");
        const inicio = parseDateOnly(dataPro.value);
        if (!inicio || (internacao && internacao.getTime() > inicio.getTime())) {
            setFieldError(dataPro, "#notif-input1");
            return;
        }
        clearFieldError(dataPro, "#notif-input1");
    });
}

if (dataProF) {
    dataProF.addEventListener("blur", function() {
        const inicio = parseDateOnly(dataPro ? dataPro.value : "");
        const fim = parseDateOnly(dataProF.value);
        if (!inicio || !fim || inicio.getTime() >= fim.getTime()) {
            setFieldError(dataProF, "#notif-input2");
            return;
        }
        clearFieldError(dataProF, "#notif-input2");
        setDiarias("diarias_1", "div_diarias_1", dataPro.value, dataProF.value);
    });
}

if (dataPro2) {
    dataPro2.addEventListener("blur", function() {
        const fimAnterior = parseDateOnly(dataProF ? dataProF.value : "");
        const inicioAtual = parseDateOnly(dataPro2.value);
        if (!inicioAtual || (fimAnterior && inicioAtual.getTime() < fimAnterior.getTime())) {
            setFieldError(dataPro2, "#notif-input3");
            return;
        }
        clearFieldError(dataPro2, "#notif-input3");
    });
}

if (dataProF2) {
    dataProF2.addEventListener("blur", function() {
        const inicio = parseDateOnly(dataPro2 ? dataPro2.value : "");
        const fim = parseDateOnly(dataProF2.value);
        if (!inicio || !fim || inicio.getTime() >= fim.getTime()) {
            setFieldError(dataProF2, "#notif-input4");
            return;
        }
        clearFieldError(dataProF2, "#notif-input4");
        setDiarias("diarias_2", "div_diarias_2", dataPro2.value, dataProF2.value);
    });
}

if (dataPro3) {
    dataPro3.addEventListener("blur", function() {
        const fimAnterior = parseDateOnly(dataProF2 ? dataProF2.value : "");
        const inicioAtual = parseDateOnly(dataPro3.value);
        if (!inicioAtual || (fimAnterior && inicioAtual.getTime() < fimAnterior.getTime())) {
            setFieldError(dataPro3, "#notif-input5");
            return;
        }
        clearFieldError(dataPro3, "#notif-input5");
    });
}

if (dataProF3) {
    dataProF3.addEventListener("blur", function() {
        const inicio = parseDateOnly(dataPro3 ? dataPro3.value : "");
        const fim = parseDateOnly(dataProF3.value);
        if (!inicio || !fim || inicio.getTime() >= fim.getTime()) {
            setFieldError(dataProF3, "#notif-input6");
            return;
        }
        clearFieldError(dataProF3, "#notif-input6");
        setDiarias("diarias_3", "div_diarias_3", dataPro3.value, dataProF3.value);
    });
}

const currentDate = new Date();

const dataIntP = document.getElementById("data_intern_int");
const dataPro = document.getElementById("prorrog1_ini_pror");
const dataProF = document.getElementById("prorrog1_fim_pror");
const dataPro2 = document.getElementById("prorrog2_ini_pror");
const dataProF2 = document.getElementById("prorrog2_fim_pror");
const dataPro3 = document.getElementById("prorrog3_ini_pror");
const dataProF3 = document.getElementById("prorrog3_fim_pror");

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

function isDateValid(date, referenceDate = currentDate) {
    const dateObj = parseDateOnly(date);
    return !!dateObj && dateObj.getTime() <= referenceDate.getTime();
}

function showError(element, messageElement) {
    if (!element || !messageElement) return;
    messageElement.style.display = "block";
    element.style.borderColor = "red";
    element.value = "";
    element.focus();
}

function hideError(element, messageElement) {
    if (!element || !messageElement) return;
    messageElement.style.display = "none";
    element.style.borderColor = "#d3d3d3";
}

function calculateDaysDifference(startDate, endDate) {
    const start = parseDateOnly(startDate);
    const end = parseDateOnly(endDate);
    if (!start || !end) return 0;
    const diffTime = end.getTime() - start.getTime();
    return diffTime / (1000 * 60 * 60 * 24);
}

function checkDaysLimit(days) {
    if (days > 15) {
        return confirm("Deseja prorrogar mais do que 15 diárias?");
    }
    return true;
}

if (dataPro) {
    dataPro.addEventListener("change", function () {
        const dataIntV = parseDateOnly(dataIntP ? dataIntP.value : "");
        const dataProV = parseDateOnly(dataPro.value);
        const divMsg1 = document.querySelector("#notif-input1");

        if (!dataProV || !isDateValid(dataPro.value) || (dataIntV && dataIntV.getTime() > dataProV.getTime())) {
            showError(dataPro, divMsg1);
        } else {
            hideError(dataPro, divMsg1);
        }
    });
}

if (dataProF) {
    dataProF.addEventListener("change", function () {
        const dataProV = parseDateOnly(dataPro ? dataPro.value : "");
        const dataProFV = parseDateOnly(dataProF.value);
        const divMsg2 = document.querySelector("#notif-input2");

        if (!dataProFV || !dataProV || !isDateValid(dataProF.value) || dataProV.getTime() > dataProFV.getTime()) {
            showError(dataProF, divMsg2);
        } else {
            hideError(dataProF, divMsg2);

            const diarias1 = document.getElementById("diarias_1");
            const diariasDiv1 = document.getElementById("div_diarias_1");
            const daysDifference = calculateDaysDifference(dataPro.value, dataProF.value);

            if (checkDaysLimit(daysDifference)) {
                if (diariasDiv1) diariasDiv1.style.display = "block";
                if (diarias1) diarias1.value = daysDifference;
            } else {
                showError(dataProF, divMsg2);
            }
        }
    });
}

if (dataPro2) {
    dataPro2.addEventListener("change", function () {
        const dataProFV2 = parseDateOnly(dataProF ? dataProF.value : "");
        const dataPro2V = parseDateOnly(dataPro2.value);
        const divMsg3 = document.querySelector("#notif-input3");

        if (!dataPro2V || (dataProFV2 && dataPro2V.getTime() < dataProFV2.getTime())) {
            showError(dataPro2, divMsg3);
        } else {
            hideError(dataPro2, divMsg3);
        }
    });
}

if (dataProF2) {
    dataProF2.addEventListener("change", function () {
        const dataPro2V = parseDateOnly(dataPro2 ? dataPro2.value : "");
        const dataProF2V = parseDateOnly(dataProF2.value);
        const divMsg4 = document.querySelector("#notif-input4");

        if (!dataProF2V || !dataPro2V || !isDateValid(dataProF2.value) || dataPro2V.getTime() > dataProF2V.getTime()) {
            showError(dataProF2, divMsg4);
        } else {
            hideError(dataProF2, divMsg4);

            const diarias2 = document.getElementById("diarias_2");
            const diariasDiv2 = document.getElementById("div_diarias_2");
            const daysDifference = calculateDaysDifference(dataPro2.value, dataProF2.value);

            if (checkDaysLimit(daysDifference)) {
                if (diariasDiv2) diariasDiv2.style.display = "block";
                if (diarias2) diarias2.value = daysDifference;
            } else {
                showError(dataProF2, divMsg4);
            }
        }
    });
}

if (dataPro3) {
    dataPro3.addEventListener("change", function () {
        const dataProF2V = parseDateOnly(dataProF2 ? dataProF2.value : "");
        const dataPro3V = parseDateOnly(dataPro3.value);
        const divMsg5 = document.querySelector("#notif-input5");

        if (!dataPro3V || (dataProF2V && dataPro3V.getTime() < dataProF2V.getTime())) {
            showError(dataPro3, divMsg5);
        } else {
            hideError(dataPro3, divMsg5);
        }
    });
}

if (dataProF3) {
    dataProF3.addEventListener("change", function () {
        const dataPro3V = parseDateOnly(dataPro3 ? dataPro3.value : "");
        const dataProF3V = parseDateOnly(dataProF3.value);
        const divMsg6 = document.querySelector("#notif-input6");

        if (!dataProF3V || !dataPro3V || !isDateValid(dataProF3.value) || dataPro3V.getTime() > dataProF3V.getTime()) {
            showError(dataProF3, divMsg6);
        } else {
            hideError(dataProF3, divMsg6);

            const diarias3 = document.getElementById("diarias_3");
            const diariasDiv3 = document.getElementById("div_diarias_3");
            const daysDifference = calculateDaysDifference(dataPro3.value, dataProF3.value);

            if (checkDaysLimit(daysDifference)) {
                if (diariasDiv3) diariasDiv3.style.display = "block";
                if (diarias3) diarias3.value = daysDifference;
            } else {
                showError(dataProF3, divMsg6);
            }
        }
    });
}

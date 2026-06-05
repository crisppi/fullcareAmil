<?php
include_once("check_logado.php");
include_once("templates/header.php");
include_once("models/message.php");

include_once("models/visita.php");
include_once("dao/visitaDao.php");
include_once("models/internacao.php");
include_once("dao/internacaoDao.php");
include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

// ================= Helpers =================
function safe($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function formatDateBr($dateStr)
{
    if (!$dateStr || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') return '';
    try {
        $dt = new DateTime($dateStr);
        return $dt->format('d/m/Y');
    } catch (Throwable $e) {
        return $dateStr; // fallback
    }
}
function initials_from_name($name)
{
    $name = trim((string)$name);
    if ($name === '') return 'PA';
    $parts = preg_split('/\s+/', $name);
    $first = substr($parts[0] ?? '', 0, 1);
    $second = substr($parts[1] ?? '', 0, 1);
    return strtoupper($first . $second);
}

// ================= GET / Query =================
$id_visita = filter_input(INPUT_GET, 'id_visita', FILTER_SANITIZE_NUMBER_INT);
if (!$id_visita) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Visita não informada.</div></div>";
    include_once("templates/footer.php");
    exit;
}


if (!$rows || !isset($rows[0])) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>Registro da visita não encontrado.</div></div>";
    include_once("templates/footer.php");
    exit;
}
$v = $rows[0];

// ================= Campos =================
$nome_pac       = $v['nome_pac']        ?? '';
$ini            = initials_from_name($nome_pac);
$id_internacao  = $v['id_internacao']   ?? '';
$id_visita_row  = $v['id_visita']       ?? $id_visita;
$hospital_nome  = $v['nome_hosp']       ?? '';
$data_visita    = formatDateBr($v['data_visita_int'] ?? '');
$data_intern    = formatDateBr($v['data_intern_int'] ?? '');
$acomodacao     = $v['acomodacao_int']  ?? '';
$relatorio      = trim((string)($v['rel_visita_vis'] ?? ''));
$acoes          = trim((string)($v['acoes_int_vis']  ?? ''));

?>

<div class="container-fluid py-3">
    <!-- Cabeçalho / Identificação do Paciente e Visita -->
    <div class="card shadow-sm mb-3" style="border-radius:14px;">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div class="d-flex gap-3 align-items-center">
                <div
                    style="width:64px;height:64px;border-radius:50%;background:#ecd5f9;display:flex;align-items:center;justify-content:center;font-weight:700;color:#5e2363">
                    <?= safe($ini) ?>
                </div>
                <div>
                    <h4 class="mb-1"><?= safe($nome_pac ?: 'Paciente') ?></h4>
                    <div class="d-flex flex-wrap gap-2 text-secondary small">
                        <span><i
                                class="fa-solid fa-hospital me-1"></i><?= safe($hospital_nome ?: 'Hospital não informado') ?></span>
                        <span>•</span>
                        <span><i class="fa-solid fa-bed-pulse me-1"></i>Internação
                            #<?= safe($id_internacao ?: '—') ?></span>
                        <span>•</span>
                        <span><i class="fa-solid fa-user-nurse me-1"></i>Visita
                            #<?= safe($id_visita_row ?: '—') ?></span>
                        <span>•</span>
                        <span><i class="fa-regular fa-calendar me-1"></i>Data da visita:
                            <?= safe($data_visita ?: '—') ?></span>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <div class="small text-secondary">Data da internação</div>
                <div class="fw-semibold"><?= safe($data_intern ?: '—') ?></div>
                <div class="small text-secondary mt-2">Acomodação</div>
                <div class="fw-semibold"><?= safe($acomodacao ?: '—') ?></div>
            </div>
        </div>
    </div>

    <!-- Conteúdo com abas (mesmo estilo do Hub do Paciente) -->
    <div class="card shadow-sm" style="border-radius:14px;">
        <div class="card-body">
            <ul class="nav nav-pills mb-3" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-resumo" type="button"
                        role="tab">
                        <i class="fa-solid fa-stream me-2"></i>Resumo
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-relatorio" type="button"
                        role="tab">
                        <i class="fa-regular fa-clipboard me-2"></i>Relatório da Auditoria
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-acoes" type="button" role="tab">
                        <i class="fa-solid fa-list-check me-2"></i>Ações da Auditoria
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Resumo -->
                <div class="tab-pane fade show active" id="tab-resumo" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-12 col-lg-4">
                            <div class="card ov-card ov-int">
                                <div class="card-body">
                                    <div class="ov-head">
                                        <div class="ov-icon"><i class="fa-solid fa-bed-pulse"></i></div>
                                        <h6 class="ov-title mb-0">Internação</h6>
                                    </div>
                                    <div class="small text-secondary">Código</div>
                                    <div class="fw-semibold mb-2">#<?= safe($id_internacao ?: '—') ?></div>
                                    <div class="small text-secondary">Data de internação</div>
                                    <div class="fw-semibold mb-2"><?= safe($data_intern ?: '—') ?></div>
                                    <div class="small text-secondary">Acomodação</div>
                                    <div class="fw-semibold"><?= safe($acomodacao ?: '—') ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="card ov-card ov-vis">
                                <div class="card-body">
                                    <div class="ov-head">
                                        <div class="ov-icon"><i class="fa-solid fa-user-nurse"></i></div>
                                        <h6 class="ov-title mb-0">Visita</h6>
                                    </div>
                                    <div class="small text-secondary">Código</div>
                                    <div class="fw-semibold mb-2">#<?= safe($id_visita_row ?: '—') ?></div>
                                    <div class="small text-secondary">Data</div>
                                    <div class="fw-semibold mb-2"><?= safe($data_visita ?: '—') ?></div>
                                    <div class="small text-secondary">Hospital</div>
                                    <div class="fw-semibold"><?= safe($hospital_nome ?: '—') ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="card ov-card ov-recent">
                                <div class="card-body">
                                    <div class="ov-head">
                                        <div class="ov-icon"><i class="fa-solid fa-circle-info"></i></div>
                                        <h6 class="ov-title mb-0">Notas rápidas</h6>
                                    </div>
                                    <div class="text-secondary">Use as abas para ver o relatório detalhado e as ações de
                                        auditoria desta visita.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Relatório -->
                <div class="tab-pane fade" id="tab-relatorio" role="tabpanel">
                    <?php if ($relatorio !== ''): ?>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="mb-2"><i class="fa-regular fa-clipboard me-2"></i>Relatório da auditoria</h6>
                                <div class="text-body" style="white-space: pre-line;"><?= safe($relatorio) ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border text-secondary">Sem relatório registrado para esta visita.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ações -->
                <div class="tab-pane fade" id="tab-acoes" role="tabpanel">
                    <?php if ($acoes !== ''): ?>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="mb-2"><i class="fa-solid fa-list-check me-2"></i>Ações da auditoria</h6>
                                <div class="text-body" style="white-space: pre-line;"><?= safe($acoes) ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border text-secondary">Sem ações registradas para esta visita.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="small text-secondary">Atualizado: <?= safe(date('d/m/Y H:i')) ?></div>
                <div class="d-flex gap-2">
                    <!-- Exemplo de botões de ação futuros
          <a href="<?= safe($BASE_URL) ?>relatorios/visita_pdf.php?id_visita=<?= (int)$id_visita_row ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-file-pdf me-2"></i>Exportar PDF
          </a> -->
                    <?php include_once("diversos/backbtn_visita.php"); ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ===== Estilos da marca (copiado do Hub do Paciente para manter consistência) ===== -->
<style>
    :root {
        --brand: #5e2363;
        --brand-700: #4b1c50;
        --brand-800: #431945;
        --brand-100: #f2e8f7;
        --brand-050: #f9f3fc;

        --teal: #0f766e;
        --teal-100: #d1fae5;
        --amber: #b45309;
        --amber-100: #fef3c7;
    }

    .btn-primary {
        background-color: var(--brand) !important;
        border-color: var(--brand) !important;
    }

    .btn-primary:hover {
        background-color: var(--brand-700) !important;
        border-color: var(--brand-700) !important;
    }

    .btn-primary:focus,
    .btn-primary:active {
        background-color: var(--brand-800) !important;
        border-color: var(--brand-800) !important;
        box-shadow: 0 0 0 .2rem rgba(94, 35, 99, .25) !important;
    }

    .btn-outline-primary,
    .btn-outline-info,
    .btn-outline-secondary {
        color: var(--brand) !important;
        border-color: var(--brand) !important;
    }

    .btn-outline-primary:hover,
    .btn-outline-info:hover,
    .btn-outline-secondary:hover {
        color: #fff !important;
        background-color: var(--brand) !important;
        border-color: var(--brand) !important;
    }

    .btn-outline-primary:focus,
    .btn-outline-info:focus,
    .btn-outline-secondary:focus {
        box-shadow: 0 0 0 .2rem rgba(94, 35, 99, .25) !important;
    }

    .nav-pills .nav-link {
        color: var(--brand);
    }

    .nav-pills .nav-link:hover {
        background: var(--brand-050);
    }

    .nav-pills .nav-link.active {
        background-color: var(--brand) !important;
    }

    .table thead {
        background: var(--brand-100);
    }

    .table thead th {
        color: var(--brand);
        border-color: #eadcf3 !important;
        font-size: 14px;
    }

    .table td {
        font-size: 13px;
    }

    .pagination .page-link {
        color: var(--brand);
        border-color: #e7ddef;
    }

    .pagination .page-item.active .page-link {
        color: #fff;
        background-color: var(--brand);
        border-color: var(--brand);
    }

    .pagination .page-link:hover {
        background: var(--brand-050);
        border-color: var(--brand);
    }

    .form-control:focus {
        border-color: var(--brand) !important;
        box-shadow: 0 0 0 .2rem rgba(94, 35, 99, .15) !important;
    }

    .input-group-text {
        background: var(--brand-100);
        color: var(--brand);
        border-color: #eadcf3 !important;
    }

    .card {
        border-radius: 14px;
    }

    .card.shadow-sm {
        box-shadow: 0 8px 24px rgba(0, 0, 0, .06) !important;
    }

    .badge-brand {
        background: var(--brand);
        color: #fff;
    }

    /* Overview cards */
    .ov-card {
        position: relative;
        border: 0 !important;
        border-radius: 14px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .06) !important;
        background: #fff;
    }

    .ov-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 6px;
        border-top-left-radius: 14px;
        border-bottom-left-radius: 14px;
        background: var(--ov-accent, var(--brand));
        opacity: .9;
    }

    .ov-head {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-bottom: .5rem;
    }

    .ov-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--ov-accent-100, var(--brand-100));
        color: var(--ov-accent, var(--brand));
        flex: 0 0 36px;
        font-size: 16px;
    }

    .ov-title {
        margin: 0;
        font-weight: 600;
        color: var(--ov-accent, var(--brand));
    }

    .ov-int {
        --ov-accent: var(--brand);
        --ov-accent-100: var(--brand-100);
    }

    .ov-vis {
        --ov-accent: var(--teal);
        --ov-accent-100: var(--teal-100);
    }

    .ov-recent {
        --ov-accent: var(--amber);
        --ov-accent-100: var(--amber-100);
    }
</style>

<script src="js/timeout.js"></script>
<?php include_once("templates/footer.php"); ?>
<?php
include_once("check_logado.php");
include_once("globals.php");
require_once("app/services/TextAutomationService.php");

$service = new TextAutomationService($conn);
$internacaoId = filter_input(INPUT_GET, 'id_internacao', FILTER_VALIDATE_INT);
$generated = null;
$error = null;

if ($internacaoId) {
    try {
        $generated = $service->generateTexts($internacaoId);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

include_once("templates/header.php");
?>

<style>
    .container-fluid.mt-5.pt-4 {
        margin-top: 12px !important;
        padding-top: 0 !important;
        padding-bottom: 14px !important;
    }

    .automation-card {
        border-radius: 12px;
        border: 1px solid #e7e7e7;
        padding: 1rem 1.1rem;
        background: #fff;
        box-shadow: 0 10px 20px rgba(95, 35, 99, 0.07);
    }

    .automation-card h4 {
        color: #5e2363;
        font-weight: 600;
        font-size: .94rem;
    }

    .automation-card textarea {
        width: 100%;
        min-height: 138px;
        resize: vertical;
        border-radius: 8px;
        border: 1px solid #d9d9d9;
        padding: 0.7rem;
        font-size: 0.82rem;
    }
    .container-fluid .alert,
    .container-fluid .form-label,
    .container-fluid .form-control,
    .container-fluid .btn,
    .container-fluid ul,
    .container-fluid li {
        font-size: .78rem;
    }
    </style>

<main class="container-fluid mt-5 pt-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <strong>Assistente de Textos:</strong> informe o ID da internação para gerar rascunhos de
                    evoluções e justificativas. As minutas são baseadas nas informações já registradas (dados do
                    paciente, visitas e solicitações de prorrogação) e devem ser revisadas antes de envio.
                </div>
            </div>
        </div>

        <form class="row g-3 mb-4" method="GET">
            <div class="col-md-3">
                <label for="id_internacao" class="form-label">ID da internação</label>
                <input type="number" class="form-control" name="id_internacao" id="id_internacao"
                    placeholder="Ex.: 1234" value="<?= htmlspecialchars((string) $internacaoId) ?>" required>
            </div>
            <div class="col-md-2 align-self-end">
                <button type="submit" class="btn btn-primary w-100">Gerar textos</button>
            </div>
        </form>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($generated): ?>
        <section class="row g-4">
            <div class="col-xl-6">
                <div class="automation-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h4>Resumo de Visitas</h4>
                        <button class="btn btn-sm btn-outline-secondary"
                            onclick="copyText('visit-text')">Copiar</button>
                    </div>
                    <textarea id="visit-text" readonly><?= htmlspecialchars($generated['visit_summary']) ?></textarea>
                    <?php if (!empty($generated['visit_bullets'])): ?>
                    <ul class="mt-3">
                        <?php foreach ($generated['visit_bullets'] as $item): ?>
                        <li><?= htmlspecialchars($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="automation-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h4>Justificativa de Prorrogação</h4>
                        <button class="btn btn-sm btn-outline-secondary"
                            onclick="copyText('prorrogacao-text')">Copiar</button>
                    </div>
                    <textarea id="prorrogacao-text"
                        readonly><?= htmlspecialchars($generated['prorrogacao_summary'] ?? 'Sem prorrogações registradas para esta internação.') ?></textarea>
                    <?php if (!empty($generated['prorrogacao_bullets'])): ?>
                    <ul class="mt-3">
                        <?php foreach ($generated['prorrogacao_bullets'] as $item): ?>
                        <li><?= htmlspecialchars($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

<script>
function copyText(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.select();
    el.setSelectionRange(0, 99999);
    document.execCommand('copy');
}
</script>
<?php include_once("templates/footer.php"); ?>

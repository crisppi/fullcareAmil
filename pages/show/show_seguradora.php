<?php
include_once("check_logado.php");
include_once("globals.php");
include_once("models/seguradora.php");
include_once("dao/seguradoraDao.php");
include_once("templates/header.php");

$id_seguradora = filter_input(INPUT_GET, "id_seguradora", FILTER_VALIDATE_INT);
$seguradoraDao = new seguradoraDAO($conn, $BASE_URL);
$seguradora = $id_seguradora ? $seguradoraDao->findById($id_seguradora) : null;

if (!$seguradora || empty($seguradora->id_seguradora)) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>Seguradora não encontrada.</div></div>";
    include_once("templates/footer.php");
    exit;
}

function seguradoraShowEsc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function seguradoraShowValue($value): string
{
    $value = trim((string)$value);
    return $value !== '' ? seguradoraShowEsc($value) : '-';
}

function seguradoraShowPhone($value): string
{
    $digits = preg_replace('/\D+/', '', (string)$value);
    if ($digits === '') {
        return '-';
    }
    if (strlen($digits) === 10) {
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 4) . '-' . substr($digits, 6);
    }
    if (strlen($digits) === 11) {
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7);
    }
    return seguradoraShowEsc((string)$value);
}

function seguradoraShowCnpj($value): string
{
    $digits = preg_replace('/\D+/', '', (string)$value);
    if ($digits === '') {
        return '-';
    }
    if (strlen($digits) === 14) {
        return substr($digits, 0, 2) . '.' .
            substr($digits, 2, 3) . '.' .
            substr($digits, 5, 3) . '/' .
            substr($digits, 8, 4) . '-' .
            substr($digits, 12, 2);
    }
    return seguradoraShowEsc((string)$value);
}

function seguradoraShowDate($value): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : seguradoraShowEsc($value);
}

function seguradoraShowLogoUrl($logo, string $baseUrl): ?string
{
    $logo = trim((string)$logo);
    if ($logo === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $logo)) {
        return $logo;
    }

    $logoPath = ltrim($logo, '/');
    $relativePath = stripos($logoPath, 'uploads/') === 0 ? $logoPath : 'uploads/' . $logoPath;
    $localPath = dirname(__DIR__, 2) . '/' . $relativePath;
    return is_file($localPath) ? $baseUrl . $relativePath : null;
}

$statusAtivo = strtolower((string)($seguradora->ativo_seg ?? '')) !== 'n'
    && strtolower((string)($seguradora->deletado_seg ?? '')) !== 's';
$statusLabel = $statusAtivo ? 'Ativa' : 'Inativa';
$statusClass = $statusAtivo ? 'is-active' : 'is-inactive';
$logoUrl = seguradoraShowLogoUrl($seguradora->logo_seg ?? '', $BASE_URL);
$endereco = trim(implode(' ', array_filter([
    trim((string)($seguradora->endereco_seg ?? '')),
    trim((string)($seguradora->numero_seg ?? '')) !== '' ? ', ' . trim((string)$seguradora->numero_seg) : '',
])));
?>
<script src="js/timeout.js"></script>
<link rel="stylesheet" href="css/form_cad_internacao.css?v=<?= @filemtime(dirname(__DIR__, 2) . '/css/form_cad_internacao.css') ?>">

<style>
.seguradora-show-page {
    padding: 0 16px 96px;
}

.seguradora-show-page .internacao-page__hero {
    margin-bottom: 14px;
}

.seguradora-profile-card {
    display: grid;
    grid-template-columns: minmax(220px, 300px) minmax(0, 1fr);
    gap: 16px;
    align-items: stretch;
}

.seguradora-profile-summary,
.seguradora-info-card {
    background: #fff;
    border: 1px solid rgba(47, 111, 159, 0.12);
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(47, 60, 85, 0.08);
}

.seguradora-profile-summary {
    padding: 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    min-height: 100%;
}

.seguradora-logo {
    width: 112px;
    height: 112px;
    border-radius: 28px;
    display: grid;
    place-items: center;
    object-fit: contain;
    background: #eef6fb;
    border: 4px solid #eef6fb;
    box-shadow: 0 10px 24px rgba(47, 111, 159, 0.16);
}

.seguradora-logo-placeholder {
    color: #2f6f9f;
    font-size: 2.8rem;
}

.seguradora-name {
    margin: 14px 0 4px;
    color: #1f2937;
    font-size: 1.22rem;
    font-weight: 800;
}

.seguradora-location {
    margin: 0;
    color: #667085;
    font-size: 0.92rem;
}

.seguradora-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 800;
}

.seguradora-status::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: currentColor;
}

.seguradora-status.is-active {
    background: #eaf8f0;
    color: #16834d;
}

.seguradora-status.is-inactive {
    background: #fff1f2;
    color: #be123c;
}

.seguradora-summary-meta {
    width: 100%;
    display: grid;
    gap: 8px;
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid #edf2f7;
    text-align: left;
}

.seguradora-summary-meta span {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #667085;
    font-size: 0.82rem;
}

.seguradora-summary-meta strong {
    color: #334155;
    font-weight: 800;
}

.seguradora-info-stack {
    display: grid;
    gap: 14px;
}

.seguradora-info-card {
    padding: 16px;
}

.seguradora-info-card h3 {
    margin: 0;
    color: #24384f;
    font-size: 1rem;
    font-weight: 800;
}

.seguradora-card-subtitle {
    margin: 3px 0 0;
    color: #64748b;
    font-size: 0.84rem;
}

.seguradora-field-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 14px;
}

.seguradora-field {
    min-height: 74px;
    padding: 11px 12px;
    border: 1px solid #e5edf4;
    border-radius: 10px;
    background: #f8fbfd;
}

.seguradora-field label {
    display: block;
    margin: 0 0 5px;
    padding: 0;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.seguradora-field div {
    color: #1f2937;
    font-size: 0.94rem;
    font-weight: 600;
    word-break: break-word;
}

@media (max-width: 980px) {
    .seguradora-profile-card,
    .seguradora-field-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main id="main-container" class="internacao-page cadastro-layout seguradora-show-page">
    <div class="internacao-page__hero">
        <div class="internacao-page__hero-main">
            <h1>Dados da seguradora</h1>
        </div>
        <div class="hero-actions">
            <a href="<?= $BASE_URL ?>seguradoras" class="hero-back-btn">Voltar para lista</a>
            <a href="<?= $BASE_URL ?>seguradoras/editar/<?= (int)$seguradora->id_seguradora ?>" class="hero-back-btn">Editar seguradora</a>
            <span class="internacao-page__tag">Registro #<?= (int)$seguradora->id_seguradora ?></span>
        </div>
    </div>

    <div class="seguradora-profile-card">
        <aside class="seguradora-profile-summary">
            <?php if ($logoUrl): ?>
                <img src="<?= seguradoraShowEsc($logoUrl) ?>" alt="Logo de <?= seguradoraShowValue($seguradora->seguradora_seg ?? '') ?>" class="seguradora-logo">
            <?php else: ?>
                <div class="seguradora-logo seguradora-logo-placeholder" aria-hidden="true">
                    <i class="bi bi-building"></i>
                </div>
            <?php endif; ?>
            <h2 class="seguradora-name"><?= seguradoraShowValue($seguradora->seguradora_seg ?? '') ?></h2>
            <p class="seguradora-location"><?= seguradoraShowValue(trim((string)($seguradora->cidade_seg ?? '') . ' / ' . (string)($seguradora->estado_seg ?? ''), ' /')) ?></p>
            <span class="seguradora-status <?= $statusClass ?>"><?= $statusLabel ?></span>

            <div class="seguradora-summary-meta">
                <span><strong>CNPJ</strong><?= seguradoraShowCnpj($seguradora->cnpj_seg ?? '') ?></span>
                <span><strong>CEP</strong><?= seguradoraShowValue($seguradora->cep_seg ?? '') ?></span>
                <span><strong>Cadastrada</strong><?= seguradoraShowDate($seguradora->data_create_seg ?? '') ?></span>
            </div>
        </aside>

        <section class="seguradora-info-stack">
            <div class="seguradora-info-card">
                <h3>Identificação</h3>
                <p class="seguradora-card-subtitle">Dados institucionais e responsáveis principais.</p>
                <div class="seguradora-field-grid">
                    <div class="seguradora-field">
                        <label>Seguradora</label>
                        <div><?= seguradoraShowValue($seguradora->seguradora_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>CNPJ</label>
                        <div><?= seguradoraShowCnpj($seguradora->cnpj_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>Contato</label>
                        <div><?= seguradoraShowValue($seguradora->contato_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>Coordenador</label>
                        <div><?= seguradoraShowValue($seguradora->coordenador_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>Coordenação RH</label>
                        <div><?= seguradoraShowValue($seguradora->coord_rh_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>Criado por</label>
                        <div><?= seguradoraShowValue($seguradora->usuario_create_seg ?? '') ?></div>
                    </div>
                </div>
            </div>

            <div class="seguradora-info-card">
                <h3>Contato</h3>
                <p class="seguradora-card-subtitle">Canais para comunicação administrativa.</p>
                <div class="seguradora-field-grid">
                    <div class="seguradora-field">
                        <label>E-mail principal</label>
                        <div><?= seguradoraShowValue($seguradora->email01_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>E-mail secundário</label>
                        <div><?= seguradoraShowValue($seguradora->email02_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>Telefone principal</label>
                        <div><?= seguradoraShowPhone($seguradora->telefone01_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>Telefone secundário</label>
                        <div><?= seguradoraShowPhone($seguradora->telefone02_seg ?? '') ?></div>
                    </div>
                </div>
            </div>

            <div class="seguradora-info-card">
                <h3>Endereço</h3>
                <p class="seguradora-card-subtitle">Localização registrada para a seguradora.</p>
                <div class="seguradora-field-grid">
                    <div class="seguradora-field">
                        <label>Endereço</label>
                        <div><?= seguradoraShowValue($endereco) ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>Bairro</label>
                        <div><?= seguradoraShowValue($seguradora->bairro_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>Cidade</label>
                        <div><?= seguradoraShowValue($seguradora->cidade_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>Estado</label>
                        <div><?= seguradoraShowValue($seguradora->estado_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>CEP</label>
                        <div><?= seguradoraShowValue($seguradora->cep_seg ?? '') ?></div>
                    </div>
                </div>
            </div>

            <div class="seguradora-info-card">
                <h3>Parâmetros operacionais</h3>
                <p class="seguradora-card-subtitle">Regras e limites usados nos processos assistenciais.</p>
                <div class="seguradora-field-grid">
                    <div class="seguradora-field">
                        <label>Alto custo</label>
                        <div>R$ <?= number_format((float)($seguradora->valor_alto_custo_seg ?? 0), 2, ',', '.') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>Dias visita</label>
                        <div><?= seguradoraShowValue($seguradora->dias_visita_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>Dias visita UTI</label>
                        <div><?= seguradoraShowValue($seguradora->dias_visita_uti_seg ?? '') ?></div>
                    </div>
                    <div class="seguradora-field">
                        <label>Longa permanência</label>
                        <div><?= seguradoraShowValue($seguradora->longa_permanencia_seg ?? '') ?></div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once("templates/footer.php"); ?>

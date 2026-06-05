<?php
include_once("check_logado.php");
include_once("globals.php");
include_once("models/hospital.php");
include_once("dao/hospitalDao.php");
include_once("templates/header.php");

$id_hospital = filter_input(INPUT_GET, "id_hospital", FILTER_VALIDATE_INT);
$hospitalDao = new hospitalDAO($conn, $BASE_URL);
$hospital = $id_hospital ? $hospitalDao->findById($id_hospital) : null;

if (!$hospital) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>Hospital não encontrado.</div></div>";
    include_once("templates/footer.php");
    exit;
}

function hospitalShowEsc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hospitalShowValue($value): string
{
    $value = trim((string)$value);
    return $value !== '' ? hospitalShowEsc($value) : '-';
}

function hospitalShowPhone($value): string
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
    return hospitalShowEsc((string)$value);
}

function hospitalShowCnpj($value): string
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
    return hospitalShowEsc((string)$value);
}

function hospitalShowDate($value): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : hospitalShowEsc($value);
}

function hospitalShowLogoUrl($logo, string $baseUrl): ?string
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

$statusAtivo = strtolower((string)($hospital->ativo_hosp ?? '')) === 's';
$statusLabel = $statusAtivo ? 'Ativo' : 'Inativo';
$statusClass = $statusAtivo ? 'is-active' : 'is-inactive';
$logoUrl = hospitalShowLogoUrl($hospital->logo_hosp ?? '', $BASE_URL);
$endereco = trim(implode(' ', array_filter([
    trim((string)($hospital->endereco_hosp ?? '')),
    trim((string)($hospital->numero_hosp ?? '')) !== '' ? ', ' . trim((string)$hospital->numero_hosp) : '',
])));
?>
<script src="js/timeout.js"></script>
<link rel="stylesheet" href="css/form_cad_internacao.css?v=<?= @filemtime(dirname(__DIR__, 2) . '/css/form_cad_internacao.css') ?>">

<style>
.hospital-show-page {
    padding: 0 16px 96px;
}

.hospital-show-page .internacao-page__hero {
    margin-bottom: 14px;
}

.hospital-profile-card {
    display: grid;
    grid-template-columns: minmax(220px, 300px) minmax(0, 1fr);
    gap: 16px;
    align-items: stretch;
}

.hospital-profile-summary,
.hospital-info-card {
    background: #fff;
    border: 1px solid rgba(47, 111, 159, 0.12);
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(47, 60, 85, 0.08);
}

.hospital-profile-summary {
    padding: 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    min-height: 100%;
}

.hospital-logo {
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

.hospital-logo-placeholder {
    color: #2f6f9f;
    font-size: 2.8rem;
}

.hospital-name {
    margin: 14px 0 4px;
    color: #1f2937;
    font-size: 1.22rem;
    font-weight: 800;
}

.hospital-location {
    margin: 0;
    color: #667085;
    font-size: 0.92rem;
}

.hospital-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 800;
}

.hospital-status::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: currentColor;
}

.hospital-status.is-active {
    background: #eaf8f0;
    color: #16834d;
}

.hospital-status.is-inactive {
    background: #fff1f2;
    color: #be123c;
}

.hospital-summary-meta {
    width: 100%;
    display: grid;
    gap: 8px;
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid #edf2f7;
    text-align: left;
}

.hospital-summary-meta span {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #667085;
    font-size: 0.82rem;
}

.hospital-summary-meta strong {
    color: #334155;
    font-weight: 800;
}

.hospital-info-stack {
    display: grid;
    gap: 14px;
}

.hospital-info-card {
    padding: 16px;
}

.hospital-info-card h3 {
    margin: 0;
    color: #24384f;
    font-size: 1rem;
    font-weight: 800;
}

.hospital-card-subtitle {
    margin: 3px 0 0;
    color: #64748b;
    font-size: 0.84rem;
}

.hospital-field-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 14px;
}

.hospital-field {
    min-height: 74px;
    padding: 11px 12px;
    border: 1px solid #e5edf4;
    border-radius: 10px;
    background: #f8fbfd;
}

.hospital-field label {
    display: block;
    margin: 0 0 5px;
    padding: 0;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.hospital-field div {
    color: #1f2937;
    font-size: 0.94rem;
    font-weight: 600;
    word-break: break-word;
}

@media (max-width: 980px) {
    .hospital-profile-card,
    .hospital-field-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main id="main-container" class="internacao-page cadastro-layout hospital-show-page">
    <div class="internacao-page__hero">
        <div class="internacao-page__hero-main">
            <h1>Dados do hospital</h1>
        </div>
        <div class="hero-actions">
            <a href="<?= $BASE_URL ?>hospitais" class="hero-back-btn">Voltar para lista</a>
            <a href="<?= $BASE_URL ?>hospitais/editar/<?= (int)$hospital->id_hospital ?>" class="hero-back-btn">Editar hospital</a>
            <a href="<?= $BASE_URL ?>hospital_usuarios.php?id_hospital=<?= (int)$hospital->id_hospital ?>" class="hero-back-btn">Usuários</a>
            <a href="<?= $BASE_URL ?>hospital_acomodacoes.php?id_hospital=<?= (int)$hospital->id_hospital ?>" class="hero-back-btn">Acomodações</a>
            <span class="internacao-page__tag">Registro #<?= (int)$hospital->id_hospital ?></span>
        </div>
    </div>

    <div class="hospital-profile-card">
        <aside class="hospital-profile-summary">
            <?php if ($logoUrl): ?>
                <img src="<?= hospitalShowEsc($logoUrl) ?>" alt="Logo de <?= hospitalShowValue($hospital->nome_hosp ?? '') ?>" class="hospital-logo">
            <?php else: ?>
                <div class="hospital-logo hospital-logo-placeholder" aria-hidden="true">
                    <i class="bi bi-hospital"></i>
                </div>
            <?php endif; ?>
            <h2 class="hospital-name"><?= hospitalShowValue($hospital->nome_hosp ?? '') ?></h2>
            <p class="hospital-location"><?= hospitalShowValue(trim((string)($hospital->cidade_hosp ?? '') . ' / ' . (string)($hospital->estado_hosp ?? ''), ' /')) ?></p>
            <span class="hospital-status <?= $statusClass ?>"><?= $statusLabel ?></span>

            <div class="hospital-summary-meta">
                <span><strong>CNPJ</strong><?= hospitalShowCnpj($hospital->cnpj_hosp ?? '') ?></span>
                <span><strong>CEP</strong><?= hospitalShowValue($hospital->cep_hosp ?? '') ?></span>
                <span><strong>Cadastrado</strong><?= hospitalShowDate($hospital->data_create_hosp ?? '') ?></span>
            </div>
        </aside>

        <section class="hospital-info-stack">
            <div class="hospital-info-card">
                <h3>Identificação</h3>
                <p class="hospital-card-subtitle">Dados institucionais e responsáveis principais.</p>
                <div class="hospital-field-grid">
                    <div class="hospital-field">
                        <label>Hospital</label>
                        <div><?= hospitalShowValue($hospital->nome_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>CNPJ</label>
                        <div><?= hospitalShowCnpj($hospital->cnpj_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>Diretor</label>
                        <div><?= hospitalShowValue($hospital->diretor_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>Coordenação médica</label>
                        <div><?= hospitalShowValue($hospital->coordenador_medico_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>Coordenação faturamento</label>
                        <div><?= hospitalShowValue($hospital->coordenador_fat_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>Criado por</label>
                        <div><?= hospitalShowValue($hospital->usuario_create_hosp ?? '') ?></div>
                    </div>
                </div>
            </div>

            <div class="hospital-info-card">
                <h3>Contato</h3>
                <p class="hospital-card-subtitle">Canais administrativos do hospital.</p>
                <div class="hospital-field-grid">
                    <div class="hospital-field">
                        <label>E-mail principal</label>
                        <div><?= hospitalShowValue($hospital->email01_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>E-mail secundário</label>
                        <div><?= hospitalShowValue($hospital->email02_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>Telefone principal</label>
                        <div><?= hospitalShowPhone($hospital->telefone01_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>Telefone secundário</label>
                        <div><?= hospitalShowPhone($hospital->telefone02_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>E-mail coord. médica</label>
                        <div><?= hospitalShowValue($hospital->emailCoordMedico_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>E-mail coord. faturamento</label>
                        <div><?= hospitalShowValue($hospital->email_coordFat_hosp ?? '') ?></div>
                    </div>
                </div>
            </div>

            <div class="hospital-info-card">
                <h3>Endereço</h3>
                <p class="hospital-card-subtitle">Localização e referência cadastrada.</p>
                <div class="hospital-field-grid">
                    <div class="hospital-field">
                        <label>Endereço</label>
                        <div><?= hospitalShowValue($endereco) ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>Bairro</label>
                        <div><?= hospitalShowValue($hospital->bairro_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>Cidade</label>
                        <div><?= hospitalShowValue($hospital->cidade_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>Estado</label>
                        <div><?= hospitalShowValue($hospital->estado_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>Latitude</label>
                        <div><?= hospitalShowValue($hospital->latitude_hosp ?? '') ?></div>
                    </div>
                    <div class="hospital-field">
                        <label>Longitude</label>
                        <div><?= hospitalShowValue($hospital->longitude_hosp ?? '') ?></div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once("templates/footer.php"); ?>

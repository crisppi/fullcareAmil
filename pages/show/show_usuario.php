<?php
include_once("check_logado.php");
include_once("globals.php");
include_once("models/usuario.php");
include_once("dao/usuarioDao.php");
include_once("templates/header.php");

$id_usuario = filter_input(INPUT_GET, "id_usuario", FILTER_VALIDATE_INT);
$usuarioDao = new userDAO($conn, $BASE_URL);
$usuario = $id_usuario ? $usuarioDao->findById_user($id_usuario) : null;

if (!$usuario) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>Usuário não encontrado.</div></div>";
    include_once("templates/footer.php");
    exit;
}

function usuarioShowEsc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function usuarioShowValue($value): string
{
    $value = trim((string)$value);
    return $value !== '' ? usuarioShowEsc($value) : '-';
}

function usuarioShowPhone($value): string
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
    return usuarioShowEsc((string)$value);
}

function usuarioShowDate($value): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : usuarioShowEsc($value);
}

function usuarioShowPhotoUrl($foto, string $baseUrl): string
{
    $defaultPhoto = 'uploads/usuarios/default-user.jpeg';
    $foto = trim((string)$foto);
    if ($foto === '') {
        return $baseUrl . $defaultPhoto;
    }
    if (preg_match('#^https?://#i', $foto)) {
        return $foto;
    }

    $fotoPath = ltrim($foto, '/');
    $relativePath = stripos($fotoPath, 'uploads/') === 0 ? $fotoPath : 'uploads/usuarios/' . $fotoPath;
    $localPath = dirname(__DIR__, 2) . '/' . $relativePath;
    return is_file($localPath) ? $baseUrl . $relativePath : $baseUrl . $defaultPhoto;
}

$statusAtivo = strtolower((string)($usuario->ativo_user ?? '')) === 's';
$statusLabel = $statusAtivo ? 'Ativo' : 'Inativo';
$statusClass = $statusAtivo ? 'is-active' : 'is-inactive';
$fotoUrl = usuarioShowPhotoUrl($usuario->foto_usuario ?? '', $BASE_URL);
$endereco = trim(implode(' ', array_filter([
    trim((string)($usuario->endereco_user ?? '')),
    trim((string)($usuario->numero_user ?? '')) !== '' ? ', ' . trim((string)$usuario->numero_user) : '',
])));
?>
<script src="js/timeout.js"></script>
<link rel="stylesheet" href="css/form_cad_internacao.css?v=<?= @filemtime(dirname(__DIR__, 2) . '/css/form_cad_internacao.css') ?>">

<style>
.usuario-show-page {
    padding: 0 16px 96px;
}

.usuario-show-page .internacao-page__hero {
    margin-bottom: 14px;
}

.usuario-profile-card {
    display: grid;
    grid-template-columns: minmax(220px, 300px) minmax(0, 1fr);
    gap: 16px;
    align-items: stretch;
}

.usuario-profile-summary,
.usuario-info-card,
.usuario-danger-card {
    background: #fff;
    border: 1px solid rgba(47, 111, 159, 0.12);
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(47, 60, 85, 0.08);
}

.usuario-profile-summary {
    padding: 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    min-height: 100%;
}

.usuario-avatar {
    width: 112px;
    height: 112px;
    border-radius: 28px;
    object-fit: cover;
    border: 4px solid #eef6fb;
    box-shadow: 0 10px 24px rgba(47, 111, 159, 0.16);
}

.usuario-name {
    margin: 14px 0 4px;
    color: #1f2937;
    font-size: 1.25rem;
    font-weight: 800;
}

.usuario-role {
    margin: 0;
    color: #667085;
    font-size: 0.92rem;
}

.usuario-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 800;
}

.usuario-status::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: currentColor;
}

.usuario-status.is-active {
    background: #eaf8f0;
    color: #16834d;
}

.usuario-status.is-inactive {
    background: #fff1f2;
    color: #be123c;
}

.usuario-summary-meta {
    width: 100%;
    display: grid;
    gap: 8px;
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid #edf2f7;
    text-align: left;
}

.usuario-summary-meta span {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #667085;
    font-size: 0.82rem;
}

.usuario-summary-meta strong {
    color: #334155;
    font-weight: 800;
}

.usuario-info-stack {
    display: grid;
    gap: 14px;
}

.usuario-info-card {
    padding: 16px;
}

.usuario-info-card h3,
.usuario-danger-card h3 {
    margin: 0;
    color: #24384f;
    font-size: 1rem;
    font-weight: 800;
}

.usuario-card-subtitle {
    margin: 3px 0 0;
    color: #64748b;
    font-size: 0.84rem;
}

.usuario-field-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 14px;
}

.usuario-field {
    min-height: 74px;
    padding: 11px 12px;
    border: 1px solid #e5edf4;
    border-radius: 10px;
    background: #f8fbfd;
}

.usuario-field label {
    display: block;
    margin: 0 0 5px;
    padding: 0;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.usuario-field div {
    color: #1f2937;
    font-size: 0.94rem;
    font-weight: 600;
    word-break: break-word;
}

.usuario-danger-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 14px;
    padding: 16px;
    border-color: rgba(190, 18, 60, 0.18);
    background: linear-gradient(135deg, #fff 0%, #fff7f7 100%);
}

.usuario-danger-card p {
    margin: 4px 0 0;
    color: #667085;
    font-size: 0.88rem;
}

.usuario-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.usuario-actions .btn {
    border-radius: 10px;
    font-weight: 700;
    padding: 9px 14px;
}

@media (max-width: 980px) {
    .usuario-profile-card,
    .usuario-field-grid {
        grid-template-columns: 1fr;
    }

    .usuario-danger-card {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<main id="main-container" class="internacao-page cadastro-layout usuario-show-page">
    <div class="internacao-page__hero">
        <div class="internacao-page__hero-main">
            <h1>Dados do usuário</h1>
        </div>
        <div class="hero-actions">
            <a href="<?= $BASE_URL ?>usuarios" class="hero-back-btn">Voltar para lista</a>
            <a href="<?= $BASE_URL ?>usuarios/editar/<?= (int)$usuario->id_usuario ?>" class="hero-back-btn">Editar usuário</a>
            <span class="internacao-page__tag">Registro #<?= (int)$usuario->id_usuario ?></span>
        </div>
    </div>

    <div class="usuario-profile-card">
        <aside class="usuario-profile-summary">
            <img src="<?= usuarioShowEsc($fotoUrl) ?>" alt="Foto de <?= usuarioShowValue($usuario->usuario_user ?? '') ?>" class="usuario-avatar">
            <h2 class="usuario-name"><?= usuarioShowValue($usuario->usuario_user ?? '') ?></h2>
            <p class="usuario-role"><?= usuarioShowValue($usuario->cargo_user ?? '') ?></p>
            <span class="usuario-status <?= $statusClass ?>"><?= $statusLabel ?></span>

            <div class="usuario-summary-meta">
                <span><strong>Nível</strong><?= usuarioShowValue($usuario->nivel_user ?? '') ?></span>
                <span><strong>Departamento</strong><?= usuarioShowValue($usuario->depto_user ?? '') ?></span>
                <span><strong>Vínculo</strong><?= usuarioShowValue($usuario->vinculo_user ?? '') ?></span>
            </div>
        </aside>

        <section class="usuario-info-stack">
            <div class="usuario-info-card">
                <h3>Identificação</h3>
                <p class="usuario-card-subtitle">Dados principais e profissionais do cadastro.</p>
                <div class="usuario-field-grid">
                    <div class="usuario-field">
                        <label>CPF</label>
                        <div><?= usuarioShowValue($usuario->cpf_user ?? '') ?></div>
                    </div>
                    <div class="usuario-field">
                        <label>Sexo</label>
                        <div><?= strtolower((string)($usuario->sexo_user ?? '')) === 'f' ? 'Feminino' : (strtolower((string)($usuario->sexo_user ?? '')) === 'm' ? 'Masculino' : '-') ?></div>
                    </div>
                    <div class="usuario-field">
                        <label>Idade</label>
                        <div><?= usuarioShowValue($usuario->idade_user ?? '') ?></div>
                    </div>
                    <div class="usuario-field">
                        <label>Registro profissional</label>
                        <div><?= usuarioShowValue($usuario->reg_profissional_user ?? '') ?></div>
                    </div>
                    <div class="usuario-field">
                        <label>Tipo de registro</label>
                        <div><?= usuarioShowValue($usuario->tipo_reg_user ?? '') ?></div>
                    </div>
                    <div class="usuario-field">
                        <label>Admissão</label>
                        <div><?= usuarioShowDate($usuario->data_admissao_user ?? '') ?></div>
                    </div>
                </div>
            </div>

            <div class="usuario-info-card">
                <h3>Contato</h3>
                <p class="usuario-card-subtitle">Canais para comunicação administrativa.</p>
                <div class="usuario-field-grid">
                    <div class="usuario-field">
                        <label>E-mail principal</label>
                        <div><?= usuarioShowValue($usuario->email_user ?? '') ?></div>
                    </div>
                    <div class="usuario-field">
                        <label>E-mail secundário</label>
                        <div><?= usuarioShowValue($usuario->email02_user ?? '') ?></div>
                    </div>
                    <div class="usuario-field">
                        <label>Telefone principal</label>
                        <div><?= usuarioShowPhone($usuario->telefone01_user ?? '') ?></div>
                    </div>
                    <div class="usuario-field">
                        <label>Telefone secundário</label>
                        <div><?= usuarioShowPhone($usuario->telefone02_user ?? '') ?></div>
                    </div>
                </div>
            </div>

            <div class="usuario-info-card">
                <h3>Endereço</h3>
                <p class="usuario-card-subtitle">Localização registrada para o usuário.</p>
                <div class="usuario-field-grid">
                    <div class="usuario-field">
                        <label>Endereço</label>
                        <div><?= usuarioShowValue($endereco) ?></div>
                    </div>
                    <div class="usuario-field">
                        <label>Bairro</label>
                        <div><?= usuarioShowValue($usuario->bairro_user ?? '') ?></div>
                    </div>
                    <div class="usuario-field">
                        <label>Cidade</label>
                        <div><?= usuarioShowValue($usuario->cidade_user ?? '') ?></div>
                    </div>
                    <div class="usuario-field">
                        <label>Estado</label>
                        <div><?= usuarioShowValue($usuario->estado_user ?? '') ?></div>
                    </div>
                </div>
            </div>

            <?php if (trim((string)($usuario->obs_user ?? '')) !== ''): ?>
            <div class="usuario-info-card">
                <h3>Observações</h3>
                <div class="usuario-field-grid">
                    <div class="usuario-field" style="grid-column: 1 / -1;">
                        <label>Nota interna</label>
                        <div><?= nl2br(usuarioShowEsc($usuario->obs_user ?? '')) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="usuario-danger-card">
                <div>
                    <h3>Inativar usuário</h3>
                    <p>Use esta ação apenas quando o usuário não deve mais acessar o sistema.</p>
                </div>
                <div class="usuario-actions">
                    <a href="<?= $BASE_URL ?>usuarios" class="btn btn-outline-secondary">Cancelar</a>
                    <button class="btn btn-danger" onclick="deletar()" value="default" type="button" id="deletar-btn" name="deletar">Inativar</button>
                </div>
                <form id="delete-usuario-form" action="<?= $BASE_URL ?>del_usuario.php" method="POST" style="display:none;">
                    <input type="hidden" name="id_usuario" value="<?= (int)$id_usuario ?>">
                    <input type="hidden" name="csrf" value="<?= usuarioShowEsc(csrf_token()) ?>">
                    <input type="hidden" name="type" value="delete">
                </form>
            </div>
        </section>
    </div>
</main>

<script>
function deletar() {
    var form = document.getElementById('delete-usuario-form');
    if (form) form.submit();
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once("templates/footer.php"); ?>

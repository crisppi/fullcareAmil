<?php
/* admin_permissao.php  —  Gestão de permissões por usuário (Criar/Editar/Deletar) */

require_once __DIR__ . '/globals.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/models/message.php';
include_once __DIR__ . '/dao/permissionDao.php';
include_once __DIR__ . '/dao/usuarioDao.php';

/* =========================
   SESSÃO E GUARDA DE ACESSO
   ========================= */
if (session_status() !== PHP_SESSION_ACTIVE) {
    $cookiePath = rtrim((string)parse_url($BASE_URL, PHP_URL_PATH), '/');
    if ($cookiePath === '') {
        $cookiePath = '/';
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookiePath,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/* Guard de login */
if (empty($_SESSION['id_usuario'])) {
    $next = urlencode($_SERVER['REQUEST_URI'] ?? '/admin_permissao.php');
    header("Location: {$BASE_URL}index.php?next={$next}");
    exit;
}

/* Checagem de Diretoria */
$cargo  = (string)($_SESSION['cargo'] ?? '');
$nivel  = (string)($_SESSION['nivel'] ?? '');
$ativo  = strtolower((string)($_SESSION['ativo'] ?? ''));
$idUser = (int)($_SESSION['id_usuario'] ?? 0);

$norm = function ($txt) {
    $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
    $c   = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $c !== false ? $c : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
};

$isDiretoria = in_array($norm($cargo), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || in_array($norm($nivel), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || ((int)$nivel === -1);

if (!$idUser || $ativo !== 's' || !$isDiretoria) {
    http_response_code(403);
    die('Acesso negado. Requer cargo/nível: Diretoria.');
}

/* CSRF e DADOS */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

$permDao = new PermissionDAO($conn, $BASE_URL);
$rows    = $permDao->findAllWithUsers();

$isDiretoriaRole = static function ($cargoTxt): bool {
    $txt = mb_strtolower(trim((string)$cargoTxt), 'UTF-8');
    $c   = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $c !== false ? $c : $txt;
    $txt = preg_replace('/[^a-z]/', '', $txt);
    return in_array($txt, ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
        || (strpos($txt, 'diretor') !== false)
        || (strpos($txt, 'diretoria') !== false);
};

/* UI */
include_once __DIR__ . '/templates/header.php';
?>
<style>
    .admin-perms-page {
        max-width: 100% !important;
        padding: 14px 16px 32px !important;
    }

    .perms-hero {
        align-items: center;
        background: linear-gradient(120deg, #e8f5fd 0%, #f7fbff 72%);
        border: 1px solid rgba(47, 111, 159, .22);
        border-radius: 12px;
        box-shadow: 0 8px 18px rgba(35, 102, 147, .08);
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        padding: 14px 16px;
    }

    .perms-hero h3 {
        color: #21364f;
        font-size: 1.25rem;
        font-weight: 820;
        line-height: 1.1;
        margin: 0 0 4px;
    }

    .perms-hero p {
        color: #5b6f87;
        font-size: .78rem;
        font-weight: 560;
        line-height: 1.35;
        margin: 0;
    }

    .perms-hero .perms-count {
        background: #fff;
        border: 1px solid rgba(47, 111, 159, .18);
        border-radius: 999px;
        color: #2f6f9f;
        font-size: .72rem;
        font-weight: 760;
        padding: 6px 10px;
        white-space: nowrap;
    }

    .perms-toolbar {
        align-items: center;
        background: #fff;
        border: 1px solid rgba(47, 111, 159, .16);
        border-radius: 12px;
        box-shadow: 0 8px 16px rgba(35, 102, 147, .07);
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        margin-bottom: 10px;
        padding: 9px 10px;
    }

    .perms-toolbar .btn {
        align-items: center;
        border-radius: 9px;
        display: inline-flex;
        font-size: .72rem;
        font-weight: 720;
        min-height: 31px;
        padding: 5px 10px;
    }

    .perms-toolbar .btn-primary {
        background: linear-gradient(135deg, #2f6f9f, #55b4d4);
        border: 0;
        box-shadow: 0 6px 14px rgba(35, 102, 147, .16);
    }

    .perms-toolbar .btn-outline-secondary {
        background: #f8fbfe;
        border-color: #c9ddeb;
        color: #40556d;
    }

    .perms-toolbar .btn-outline-danger {
        background: #fff7f7;
        border-color: #ffc9c9;
        color: #d24141;
    }

    .perms-table-card {
        background: #fff;
        border: 1px solid rgba(47, 111, 159, .16);
        border-radius: 12px;
        box-shadow: 0 8px 18px rgba(35, 102, 147, .08);
        overflow: auto;
    }

    .table-perms {
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
        min-width: 1180px;
    }

    .perms-table-card .table.table-perms > :not(caption) > * > * {
        border-color: #e1edf6 !important;
        padding: .58rem .62rem !important;
        vertical-align: middle;
    }

    .table-perms tbody tr {
        height: 34px;
    }

    .table-perms tbody tr:hover td {
        background: #eef7fc !important;
    }

    .table-perms thead th {
        background: #2f6f9f !important;
        border-color: #2f6f9f !important;
        color: #fff !important;
        font-size: .64rem;
        font-weight: 820;
        letter-spacing: .045em;
        position: sticky;
        text-transform: uppercase;
        top: 0;
        vertical-align: middle;
        z-index: 2;
    }

    .table-perms tbody td {
        color: #34475d;
        font-size: .74rem;
        font-weight: 560;
        line-height: 1.28;
    }

    .table-perms tbody tr:nth-child(odd) td {
        background: #fff;
    }

    .table-perms tbody tr:nth-child(even) td {
        background: #f3f8fc;
    }

    .table-perms td:nth-child(2) {
        color: #22364e;
        font-weight: 740;
    }

    .table-perms td:nth-child(3) {
        color: #63758b;
        font-size: .7rem;
        text-transform: lowercase;
    }

    .badge-updated {
        background: #f4f8fc;
        border: 1px solid #d9e6f1;
        border-radius: 7px;
        color: #34475d !important;
        font-size: .66rem;
        font-weight: 680;
        letter-spacing: .1px;
        padding: 3px 7px;
    }

    .table-perms td.text-center {
        white-space: nowrap;
    }

    .perm-wrapper {
        align-items: center;
        border-radius: .45rem;
        display: inline-flex;
        justify-content: center;
        padding: .08rem;
        transition: background-color .15s ease;
    }

    td.text-center .perm-wrapper:hover {
        background-color: #eef7fc;
    }

    .perm-checkbox {
        -webkit-appearance: none;
        appearance: none;
        background: #fff;
        border: 2px solid #cbd8e6;
        border-radius: 6px;
        cursor: pointer;
        display: inline-grid;
        height: 17px;
        place-content: center;
        transition: border-color .15s ease, box-shadow .15s ease, transform .05s ease;
        vertical-align: middle;
        width: 17px;
    }

    .perm-checkbox::before {
        background: currentColor;
        clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 18%, 80% 0, 43% 62%);
        content: "";
        height: 9px;
        transform: scale(0);
        transition: transform .12s ease-in-out;
        width: 9px;
    }

    .perm-checkbox:checked {
        border-color: currentColor;
    }

    .perm-checkbox:checked::before {
        transform: scale(1);
    }

    .perm-checkbox:hover {
        box-shadow: 0 0 0 3px rgba(47, 111, 159, .12);
    }

    .perm-checkbox:active {
        transform: scale(.98);
    }

    .perm-checkbox:focus-visible {
        border-radius: 4px;
        outline: 2px solid #2f6f9f;
        outline-offset: 2px;
    }

    .perm-checkbox[data-field="view"] { color: #2563eb; }
    .perm-checkbox[data-field="create"] { color: #22c55e; }
    .perm-checkbox[data-field="edit"] { color: #f59e0b; }
    .perm-checkbox[data-field="delete"] { color: #ef4444; }
    .perm-checkbox[data-field="discharge"] { color: #0ea5a3; }
    .perm-checkbox[data-field="close_management"] { color: #9333ea; }
    .perm-checkbox[data-field="generate_pdf"] { color: #0284c7; }

    tr.table-warning td {
        background: #fff6df !important;
    }

    @media (max-width: 900px) {
        .admin-perms-page {
            padding: 10px 12px 28px !important;
        }

        .perms-hero {
            align-items: flex-start;
            flex-direction: column;
            gap: 8px;
        }
    }
</style>

<div class="container-fluid admin-perms-page">
    <section class="perms-hero">
        <div>
            <h3>Permissões por usuário</h3>
            <p>Matriz por ação para visualizar, criar, editar, deletar, dar alta, fechar gestão e gerar PDF.</p>
        </div>
        <span class="perms-count"><?= count($rows) ?> usuário<?= count($rows) === 1 ? '' : 's' ?></span>
    </section>

    <div class="perms-toolbar">
        <button id="btnSaveAll" class="btn btn-primary">Salvar</button>
        <button id="btnSelectAllView" class="btn btn-outline-secondary btn-sm">Visualizar todos</button>
        <button id="btnSelectAllCreate" class="btn btn-outline-secondary btn-sm">Criar todos</button>
        <button id="btnSelectAllEdit" class="btn btn-outline-secondary btn-sm">Editar todos</button>
        <button id="btnSelectAllDelete" class="btn btn-outline-secondary btn-sm">Deletar todos</button>
        <button id="btnSelectAllDischarge" class="btn btn-outline-secondary btn-sm">Dar alta todos</button>
        <button id="btnSelectAllCloseMgmt" class="btn btn-outline-secondary btn-sm">Fechar gestão todos</button>
        <button id="btnSelectAllPdf" class="btn btn-outline-secondary btn-sm">Gerar PDF todos</button>
        <button id="btnClearAll" class="btn btn-outline-danger btn-sm">Limpar</button>
    </div>

    <div class="perms-table-card table-responsive">
        <table class="table table-striped table-perms">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Usuário</th>
                    <th>E-mail</th>
                    <th class="text-center">Visualizar</th>
                    <th class="text-center">Criar</th>
                    <th class="text-center">Editar</th>
                    <th class="text-center">Deletar</th>
                    <th class="text-center">Dar Alta</th>
                    <th class="text-center">Fechar Gestão</th>
                    <th class="text-center">Gerar PDF</th>
                    <th>Atualizado em</th>
                </tr>
            </thead>
            <tbody id="tbodyPerms">
                <?php foreach ($rows as $i => $r): ?>
                <?php $rowIsDiretoria = $isDiretoriaRole($r['cargo'] ?? ''); ?>
                <tr data-user-id="<?= (int)$r['id_user'] ?>">
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($r['nome'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                    <td class="text-center">
                        <label class="perm-wrapper" title="Visualizar">
                            <input type="checkbox" class="perm-checkbox" data-field="view"
                                <?= (((int)($r['can_view'] ?? 1) === 1 || $rowIsDiretoria) ? 'checked' : '') ?>
                                <?= $rowIsDiretoria ? 'disabled' : '' ?>>
                        </label>
                    </td>

                    <td class="text-center">
                        <label class="perm-wrapper" title="Criar">
                            <input type="checkbox" class="perm-checkbox" data-field="create"
                                <?= (((int)$r['can_create'] === 1) || $rowIsDiretoria ? 'checked' : '') ?>
                                <?= $rowIsDiretoria ? 'disabled' : '' ?>>
                        </label>
                    </td>

                    <td class="text-center">
                        <label class="perm-wrapper" title="Editar">
                            <input type="checkbox" class="perm-checkbox" data-field="edit"
                                <?= (((int)$r['can_edit'] === 1) || $rowIsDiretoria ? 'checked' : '') ?>
                                <?= $rowIsDiretoria ? 'disabled' : '' ?>>
                        </label>
                    </td>

                    <td class="text-center">
                        <label class="perm-wrapper" title="Deletar">
                            <input type="checkbox" class="perm-checkbox" data-field="delete"
                                <?= (((int)$r['can_delete'] === 1) || $rowIsDiretoria ? 'checked' : '') ?>
                                <?= $rowIsDiretoria ? 'disabled' : '' ?>>
                        </label>
                    </td>
                    <td class="text-center">
                        <label class="perm-wrapper" title="Dar Alta">
                            <input type="checkbox" class="perm-checkbox" data-field="discharge"
                                <?= (((int)($r['can_discharge'] ?? 0) === 1) || $rowIsDiretoria ? 'checked' : '') ?>
                                <?= $rowIsDiretoria ? 'disabled' : '' ?>>
                        </label>
                    </td>
                    <td class="text-center">
                        <label class="perm-wrapper" title="Fechar Gestão">
                            <input type="checkbox" class="perm-checkbox" data-field="close_management"
                                <?= (((int)($r['can_close_management'] ?? 0) === 1) || $rowIsDiretoria ? 'checked' : '') ?>
                                <?= $rowIsDiretoria ? 'disabled' : '' ?>>
                        </label>
                    </td>
                    <td class="text-center">
                        <label class="perm-wrapper" title="Gerar PDF">
                            <input type="checkbox" class="perm-checkbox" data-field="generate_pdf"
                                <?= (((int)($r['can_generate_pdf'] ?? 0) === 1) || $rowIsDiretoria ? 'checked' : '') ?>
                                <?= $rowIsDiretoria ? 'disabled' : '' ?>>
                        </label>
                    </td>

                    <td>
                        <?php
                            // dd/mm/aaaa hh:mm
                            $fmtUpdated = '';
                            if (!empty($r['updated_at'])) {
                                $ts = strtotime($r['updated_at']);
                                if ($ts !== false) $fmtUpdated = date('d/m/Y H:i', $ts);
                            }
                            ?>
                        <?php if ($fmtUpdated): ?>
                        <span class="badge bg-light text-dark badge-updated">
                            <?= htmlspecialchars($fmtUpdated) ?>
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="11" class="text-muted">Nenhum usuário encontrado.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    const csrf = "<?= $csrf ?>";
    const tbody = document.getElementById('tbodyPerms');
    const dirty = new Set();

    tbody.addEventListener('change', (e) => {
        const cb = e.target;
        if (!cb.classList.contains('perm-checkbox')) return;
        const tr = cb.closest('tr');
        if (tr) {
            tr.classList.add('table-warning');
            dirty.add(tr.dataset.userId);
        }
    });

    const setAll = (field, value) => {
        document.querySelectorAll(`.perm-checkbox[data-field="${field}"]`).forEach(cb => {
            if (cb.disabled) return;
            if (cb.checked !== value) {
                cb.checked = value;
                const tr = cb.closest('tr');
                if (tr) {
                    tr.classList.add('table-warning');
                    dirty.add(tr.dataset.userId);
                }
            }
        });
    };

    document.getElementById('btnSelectAllView')?.addEventListener('click', () => setAll('view', true));
    document.getElementById('btnSelectAllCreate')?.addEventListener('click', () => setAll('create', true));
    document.getElementById('btnSelectAllEdit')?.addEventListener('click', () => setAll('edit', true));
    document.getElementById('btnSelectAllDelete')?.addEventListener('click', () => setAll('delete', true));
    document.getElementById('btnSelectAllDischarge')?.addEventListener('click', () => setAll('discharge', true));
    document.getElementById('btnSelectAllCloseMgmt')?.addEventListener('click', () => setAll('close_management', true));
    document.getElementById('btnSelectAllPdf')?.addEventListener('click', () => setAll('generate_pdf', true));
    document.getElementById('btnClearAll')?.addEventListener('click', () => {
        document.querySelectorAll('.perm-checkbox').forEach(cb => {
            if (cb.disabled) return;
            if (cb.checked) {
                cb.checked = false;
                const tr = cb.closest('tr');
                if (tr) {
                    tr.classList.add('table-warning');
                    dirty.add(tr.dataset.userId);
                }
            }
        });
    });

    document.getElementById('btnSaveAll')?.addEventListener('click', async () => {
        const payload = {
            csrf,
            perm: {}
        };

        document.querySelectorAll('tr[data-user-id]').forEach(tr => {
            const uid = tr.dataset.userId;
            if (!dirty.has(uid)) return;
            const get = f => tr.querySelector(`.perm-checkbox[data-field="${f}"]`)?.checked ?
                '1' : '0';
            payload.perm[uid] = {
                view: get('view'),
                create: get('create'),
                edit: get('edit'),
                delete: get('delete'),
                discharge: get('discharge'),
                close_management: get('close_management'),
                generate_pdf: get('generate_pdf')
            };
        });

        if (Object.keys(payload.perm).length === 0) {
            alert('Nada para salvar.');
            return;
        }

        try {
            const res = await fetch('process_permissoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'ok') throw new Error(data.message || 'Erro ao salvar');

            dirty.clear();
            document.querySelectorAll('tr.table-warning').forEach(tr => tr.classList.remove(
                'table-warning'));
            alert('Permissões atualizadas com sucesso!');
        } catch (err) {
            console.error(err);
            alert('Falha ao salvar: ' + err.message);
        }
    });
})();
</script>

<?php include_once __DIR__ . '/templates/footer.php'; ?>

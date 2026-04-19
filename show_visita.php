<?php
include_once("check_logado.php");
include_once("globals.php"); // $conn, $BASE_URL
Gate::enforceAction($conn, $BASE_URL, 'view', 'Você não tem permissão para visualizar este registro.');
include_once("templates/header.php");

include_once("models/message.php");
include_once("models/visita.php");
include_once("dao/visitaDao.php");
include_once("models/internacao.php");
include_once("dao/internacaoDao.php");
include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

/* ============== Helpers ============== */
function safe($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function normalize_date_ymd_from_string($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') return '';
    $d10 = substr($raw, 0, 10);
    $dt  = DateTime::createFromFormat('Y-m-d', $d10);
    if ($dt) return $dt->format('Y-m-d');
    $dt2 = DateTime::createFromFormat('d/m/Y', $d10);
    if ($dt2) return $dt2->format('Y-m-d');
    return '';
}
function pick_visit_date_raw(array $r)
{
    foreach (['data_visita_vis', 'data_visita', 'data', 'created_at', 'data_visita_int'] as $k) {
        if (!empty($r[$k])) return $r[$k];
    }
    return '';
}
function pick_visit_user(array $r)
{
    foreach (['usuario_vis', 'usuario_create', 'usuario', 'responsavel', 'profissional', 'medico', 'nome_usuario'] as $k) {
        if (!empty($r[$k])) return $r[$k];
    }
    return '';
}
function pick_visit_text(array $r)
{
    foreach (['rel_visita', 'rel_visita_vis', 'rel_vis', 'relatorio', 'observacao', 'obs', 'descricao'] as $k) {
        if (empty($r[$k])) {
            continue;
        }
        $text = trim((string)$r[$k]);
        if (preg_match('/^(Importado do OCR do PDF|Complementado via OCR)/i', $text)) {
            continue;
        }
        return $text;
    }
    return '';
}

function formatDateBr($dateYmd)
{
    if (!$dateYmd || $dateYmd === '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', substr($dateYmd, 0, 10));
    return $dt ? $dt->format('d/m/Y') : $dateYmd;
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

/* ============== GET / Base ============== */
$id_visita = filter_input(INPUT_GET, 'id_visita', FILTER_SANITIZE_NUMBER_INT);
$vpage     = max(1, (int)($_GET['vpage'] ?? 1));
$pageSize  = 7;

if (!$id_visita) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Visita não informada.</div></div>";
    include_once("templates/footer.php");
    exit;
}

$visitaDao = new visitaDAO($conn, $BASE_URL);
$rows = $visitaDao->joinVisitaShow($id_visita);

if (!$rows || !isset($rows[0])) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>Registro da visita não encontrado.</div></div>";
    include_once("templates/footer.php");
    exit;
}
$v = $rows[0];

/* ============== Cabeçalho ============== */
$nome_pac       = $v['nome_pac']        ?? '';
$ini            = initials_from_name($nome_pac);
$id_internacao  = $v['id_internacao']   ?? ($v['fk_internacao_vis'] ?? '');
$id_visita_row  = $v['id_visita']       ?? $id_visita;
$hospital_nome  = $v['nome_hosp']       ?? '';

$dv_raw         = $v['data_visita_int'] ?? ($v['data_visita_vis'] ?? ($v['data_visita'] ?? ''));
$dv_norm        = normalize_date_ymd_from_string($dv_raw);
$data_visita    = formatDateBr($dv_norm);

$data_intern    = formatDateBr($v['data_intern_int'] ?? '');
$acomodacao     = $v['acomodacao_int']  ?? '';

/* ============== Descobrir id_paciente (se disponível) ============== */
$id_paciente = $v['id_paciente'] ?? ($v['fk_paciente_int'] ?? ($v['id_pac'] ?? null));

/* ============== Carregar TODAS as visitas da internação ============== */
$all = [];
try {
    if (!empty($id_internacao) && method_exists($visitaDao, 'findGeralByIntern')) {
        $all = $visitaDao->findGeralByIntern((int)$id_internacao) ?: [];
        foreach ($all as &$r) {
            if (!isset($r['id_internacao']) && isset($r['fk_internacao_vis'])) {
                $r['id_internacao'] = $r['fk_internacao_vis'];
            }
            $r['data_visita_int'] = normalize_date_ymd_from_string(pick_visit_date_raw($r));
            if (!isset($r['usuario_vis'])) {
                $r['usuario_vis'] = !empty($r['usuario_create'])
                    ? $r['usuario_create']
                    : (isset($r['fk_usuario_vis']) ? ('ID ' . $r['fk_usuario_vis']) : '');
            }
            if (!isset($r['nome_hosp']) || $r['nome_hosp'] === '') {
                $r['nome_hosp'] = $hospital_nome;
            }
        }
        unset($r);
    }
} catch (Throwable $e) {
    $all = [];
}

/* ======= Ordenação: mais antiga -> mais recente (ASC) ======= */
if ($all) {
    usort($all, function ($a, $b) {
        return strcmp($a['data_visita_int'] ?? '', $b['data_visita_int'] ?? '');
    });
}

$total_intern = count($all);

/* ====== Extremos globais para o range do rodapé ====== */
$allTs = array_values(array_filter(array_map(function ($r) {
    $d = $r['data_visita_int'] ?? '';
    $ts = $d ? @strtotime($d) : null;
    return $ts ?: null;
}, $all)));
$minAllTs = $allTs ? min($allTs) : null;
$maxAllTs = $allTs ? max($allTs) : null;

/* Opcional: total por PACIENTE (todas as internações) */
$total_paciente = null;
if ($id_paciente && $conn instanceof PDO) {
    try {
        $sqlCnt = "SELECT COUNT(*) AS c
                 FROM tb_visita v
                 JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
                WHERE v.retificado IS NULL
                  AND i.fk_paciente_int = :pid";
        $stc = $conn->prepare($sqlCnt);
        $stc->bindValue(':pid', (int)$id_paciente, PDO::PARAM_INT);
        $stc->execute();
        $rowC = $stc->fetch(PDO::FETCH_ASSOC);
        if ($rowC && isset($rowC['c'])) $total_paciente = (int)$rowC['c'];
    } catch (Throwable $e) { /* ignore */
    }
}
$TOTAL_VISITAS_EXIBIR = $total_paciente ?? $total_intern;

/* Paginação: 7 por página (página 1 = mais antigas) */
$pages  = max(1, (int)ceil($total_intern / $pageSize));
if ($vpage > $pages) $vpage = $pages;
$offset = ($vpage - 1) * $pageSize;
$timeline = array_slice($all, $offset, $pageSize);

/* Posição proporcional baseada no subset da página (crescente) */
$datesTs = [];
foreach ($timeline as $tt) {
    $raw = $tt['data_visita_int'] ?? '';
    $ts  = $raw ? @strtotime($raw) : null;
    if ($ts) $datesTs[] = $ts;
}
$minTs = $datesTs ? min($datesTs) : null;
$maxTs = $datesTs ? max($datesTs) : null;
$count = count($timeline);

/* Trilho: largura base; contido via max-width:100% no CSS */
$trackWidthPx = max(800, $count * 160);

$baseUrlSelf = strtok($_SERVER["REQUEST_URI"], '?');
$queryBase   = "id_visita={$id_visita_row}&tab=timeline";
$prevUrl     = "{$baseUrlSelf}?{$queryBase}&vpage=" . max(1, $vpage - 1);      // mais antigas
$nextUrl     = "{$baseUrlSelf}?{$queryBase}&vpage=" . min($pages, $vpage + 1); // mais recentes
?>
<div class="container-fluid py-3">
    <!-- Cabeçalho -->
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

    <!-- Abas -->
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
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-timeline" type="button"
                        role="tab">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i>Linha do tempo
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Resumo -->
                <div class="tab-pane fade show active" id="tab-resumo" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
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

                        <div class="col-12 col-lg-6">
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
                    </div>
                </div>

                <!-- Timeline -->
                <div class="tab-pane fade" id="tab-timeline" role="tabpanel">
                    <?php if ($timeline && count($timeline) > 0): ?>
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                            <div class="small text-secondary">
                                Mostrando
                                <strong><?= $offset + 1 ?></strong>–<strong><?= min($offset + $pageSize, $total_intern) ?></strong>
                                de <strong><?= $total_intern ?></strong> visitas desta internação
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <?php if (empty($v['retificado']) && $total_intern > 1): ?>
                                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#modalDeleteVisita" data-delete-visita="<?= (int) $id_visita_row ?>">
                                        <i class="fa-solid fa-trash-can me-1"></i>Remover visita
                                    </button>
                                <?php endif; ?>
                                <div class="btn-group btn-group-sm" role="group" aria-label="pager">
                                    <a class="btn btn-outline-secondary <?= $vpage <= 1 ? 'disabled' : '' ?>"
                                        href="<?= safe($prevUrl) ?>">‹ Mais antigas</a>
                                    <span class="btn btn-outline-secondary disabled">Página
                                        <?= (int)$vpage ?>/<?= (int)$pages ?></span>
                                    <a class="btn btn-outline-secondary <?= $vpage >= $pages ? 'disabled' : '' ?>"
                                        href="<?= safe($nextUrl) ?>">Mais recentes ›</a>
                                </div>
                            </div>
                        </div>

                        <div class="ht-container">
                            <div class="ht-track" style="width: <?= (int)$trackWidthPx ?>px">
                                <div class="ht-bar"></div>
                                <?php foreach ($timeline as $idx => $t):
                                    $idv   = (int)($t['id_visita'] ?? 0);
                                    $raw   = $t['data_visita_int'] ?? '';
                                    $dtBr  = formatDateBr($raw);
                                    $orig  = trim((string)($t['data_visita_vis'] ?? ''));
                                    $label = $dtBr !== '' ? $dtBr : ($orig !== '' ? $orig : '—');

                                    $ts = $raw ? @strtotime($raw) : null;
                                    if ($minTs && $maxTs && $minTs !== $maxTs && $ts) {
                                        // crescente: antiga (0%) -> nova (100%)
                                        $pos = round((($ts - $minTs) / ($maxTs - $minTs)) * 100, 2);
                                    } else {
                                        $pos = $count > 1 ? round(($idx / ($count - 1)) * 100, 2) : 50;
                                    }

                                    // clamp e classes por posição (evita corte)
                                    $pos = max(2, min(98, $pos));
                                    $edgeLeft  = ($pos <= 3.5);
                                    $edgeRight = ($pos >= 96.5);
                                    $edgeCls   = ($edgeLeft ? ' edge-left' : '') . ($edgeRight ? ' edge-right' : '');

                                    $responsavel = trim((string)($t['usuario_vis'] ?? ($t['usuario_create'] ?? '')));
                                    $hosp        = $t['nome_hosp'] ?? '';
                                    $isCurrent   = ((int)$id_visita_row === $idv);
                                    $rel_html    = safe($t['rel_visita_vis'] ?? '');
                                    $retificadoFlag = !empty($t['retificado']);
                                ?>
                                    <a class="ht-marker <?= $isCurrent ? 'active' : '' ?><?= $edgeCls ?>" href="#"
                                        style="left: <?= $pos ?>%;" data-id="<?= $idv ?>" data-date="<?= safe($label) ?>"
                                        data-user="<?= safe($responsavel) ?>" data-hosp="<?= safe($hosp) ?>"
                                        data-retificado="<?= $retificadoFlag ? '1' : '0' ?>"
                                        data-rel="<?= $rel_html ?>">
                                        <span class="ht-label"><?= safe($label) ?></span>
                                        <span class="ht-dot"></span>
                                        <div class="ht-pop">
                                            <?php if ($responsavel): ?>
                                                <div class="small text-secondary mb-1"><i
                                                        class="fa-solid fa-user-nurse me-1"></i><?= safe($responsavel) ?></div>
                                            <?php endif; ?>
                                            <div class="small"><i class="fa-solid fa-hospital me-1"></i><?= safe($hosp) ?></div>
                                            <div class="small mt-1">Visita #<?= $idv ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Detalhes -->
                        <div id="tl-details" class="card border-0 shadow-sm mt-3" style="display:none;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <h6 class="mb-0">
                                        <i class="fa-regular fa-clipboard me-2"></i>
                                        Relatório da visita <span id="tl-id" class="badge bg-secondary"></span>
                                    </h6>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <div class="small text-secondary">
                                            <i class="fa-regular fa-calendar me-1"></i><span id="tl-date"></span>
                                            <span id="tl-sep1" style="display:none;"> • </span>
                                            <i class="fa-solid fa-user-nurse ms-2 me-1"></i><span id="tl-user"></span>
                                            <span id="tl-sep2" style="display:none;"> • </span>
                                            <i class="fa-solid fa-hospital ms-2 me-1"></i><span id="tl-hosp"></span>
                                            <span id="tl-sep3" style="display:none;"> • </span>
                                            <span id="tl-total-wrap" style="display:none;">Total visitas: <span
                                                    id="tl-total"></span></span>
                                        </div>
                                        <button type="button" id="tl-delete-btn" class="btn btn-outline-danger btn-sm d-none"
                                            data-bs-toggle="modal" data-bs-target="#modalDeleteVisita"
                                            data-delete-visita="<?= (int) $id_visita_row ?>">
                                            <i class="fa-solid fa-trash-can me-1"></i>Remover
                                        </button>
                                    </div>
                                </div>
                                <div id="tl-rel" class="text-body" style="white-space: pre-line;"></div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="small text-secondary">
                                <?php if ($minAllTs && $maxAllTs): ?>
                                    <?= safe(date('d/m/Y', $minAllTs)) ?> — <?= safe(date('d/m/Y', $maxAllTs)) ?>
                                <?php endif; ?>
                            </div>
                            <div class="small"><span class="legend-dot"></span> Clique nas datas para ver o relatório abaixo
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border text-secondary">Não há visitas nesta página.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="small text-secondary">Atualizado: <?= safe(date('d/m/Y H:i')) ?></div>
                <div class="d-flex gap-2">
                    <?php
                    $backHref = !empty($_SERVER['HTTP_REFERER']) ? 'javascript:history.back()' : $BASE_URL . 'internacoes.php';
                    ?>
                    <a href="<?= safe($backHref) ?>" class="btn btn-ghost-brand btn-sm rounded-pill shadow-sm">
                        <i class="fa-solid fa-arrow-left me-2"></i>
                        Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDeleteVisita" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Remover visita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza de que deseja remover esta visita? Essa ação não exclui os dados do banco,
                    mas a visita deixará de aparecer nas listas e relatórios.</p>
                <div class="alert alert-danger d-none js-delete-feedback" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" data-action="confirm-delete">Remover visita</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Estilos ===== -->
<style>
    :root {
        --brand: #5e2363;
        --brand-700: #4b1c50;
        --brand-800: #431945;
        --brand-100: #f2e8f7;
        --brand-050: #f9f3fc;
        --teal: #0f766e;
        --teal-100: #d1fae5;
        --padX: 56px;
        /* respiro lateral maior para não cortar etiquetas */
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

    .btn-outline-secondary {
        border-color: #e0d4ea !important;
    }

    .btn-outline-secondary:hover {
        background: var(--brand-050) !important;
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

    .card {
        border-radius: 14px;
    }

    .card.shadow-sm {
        box-shadow: 0 8px 24px rgba(0, 0, 0, .06) !important;
    }

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

    /* Timeline centralizada e SEM corte nas bordas */
    .ht-container {
        position: relative;
        overflow-x: auto;
        padding: 24px var(--padX) 8px;
        display: flex;
        justify-content: center;
        scroll-snap-type: x mandatory;
    }

    .ht-track {
        position: relative;
        height: 110px;
        margin: 0 auto;
        max-width: 100%;
    }

    .ht-bar {
        position: absolute;
        left: var(--padX);
        right: var(--padX);
        top: 56px;
        height: 6px;
        background: #eadcf3;
        border-radius: 999px;
        box-shadow: inset 0 0 0 1px #e5d8ef
    }

    .ht-marker {
        position: absolute;
        top: 0;
        transform: translateX(-50%);
        text-align: center;
        cursor: pointer;
        color: inherit;
        text-decoration: none;
        scroll-snap-align: center;
        max-width: 45%;
    }

    .ht-marker.edge-left {
        transform: none;
    }

    .ht-marker.edge-right {
        transform: translateX(-100%);
    }

    /* Label da data */
    .ht-label {
        display: inline-block;
        font-size: 12px;
        color: var(--brand);
        margin-bottom: 6px;
        white-space: nowrap;
        transition: all .2s ease;
        padding: 4px 8px;
        border-radius: 8px;
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Hover e ativo */
    .ht-marker:hover .ht-label {
        background: var(--brand-100);
        color: var(--brand-800)
    }

    .ht-marker.active .ht-label {
        background: var(--brand);
        color: #fff;
        font-weight: 700;
        transform: scale(1.02)
    }

    .ht-dot {
        display: inline-block;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: var(--brand);
        border: 2px solid #fff;
        box-shadow: 0 0 0 3px var(--brand-100), 0 4px 10px rgba(0, 0, 0, .08);
        transition: all .2s ease
    }

    .ht-marker:hover .ht-dot {
        transform: scale(1.1)
    }

    .ht-marker.active .ht-dot {
        background: var(--brand-800);
        box-shadow: 0 0 0 4px var(--brand-100), 0 6px 14px rgba(0, 0, 0, .12)
    }

    .ht-pop {
        display: none;
        position: absolute;
        bottom: 78px;
        left: 50%;
        transform: translateX(-50%);
        background: #fff;
        border: 1px solid #eadcf3;
        border-radius: 10px;
        padding: 8px 10px;
        min-width: 220px;
        box-shadow: 0 12px 24px rgba(0, 0, 0, .08);
        z-index: 5
    }

    .ht-marker:hover .ht-pop {
        display: block
    }

    .legend-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--brand);
        margin-right: 6px
    }

    /* Botão "ghost" brand */
    .btn-ghost-brand {
        color: var(--brand);
        background: var(--brand-050);
        border: 1px solid #eadcf3;
    }

    .btn-ghost-brand:hover {
        background: var(--brand-100);
        color: var(--brand-800);
    }
</style>

<script>
    (function ensureBootstrap() {
        if (typeof window.bootstrap === 'undefined') {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
            s.defer = true;
            document.body.appendChild(s);
        }
    })();

    // total para detalhes
    window.TOTAL_VISITAS = <?= (int)$TOTAL_VISITAS_EXIBIR ?>;
    window.TOTAL_VISITAS_INTERNACAO = <?= (int)$total_intern ?>;

    function updateTimelineDetailsFromMarker(marker) {
        if (!marker) return;
        var box = document.getElementById('tl-details');
        var idEl = document.getElementById('tl-id');
        var date = document.getElementById('tl-date');
        var user = document.getElementById('tl-user');
        var hosp = document.getElementById('tl-hosp');
        var rel = document.getElementById('tl-rel');
        var sep1 = document.getElementById('tl-sep1');
        var sep2 = document.getElementById('tl-sep2');
        var sep3 = document.getElementById('tl-sep3');
        var totW = document.getElementById('tl-total-wrap');
        var tot = document.getElementById('tl-total');

        if (idEl) idEl.textContent = '#' + (marker.dataset.id || '');
        if (date) date.textContent = marker.dataset.date || '—';
        if (user) user.textContent = marker.dataset.user || '';
        if (hosp) hosp.textContent = marker.dataset.hosp || '';
        if (rel) rel.innerHTML = marker.dataset.rel || '';
        var tlDeleteBtn = document.getElementById('tl-delete-btn');
        if (tlDeleteBtn) {
            if (marker.dataset.id) {
                tlDeleteBtn.setAttribute('data-delete-visita', marker.dataset.id);
            }
            var isRetificado = marker.dataset.retificado === '1';
            var totalIntern = Number(window.TOTAL_VISITAS_INTERNACAO || 0);
            var preventDelete = isRetificado || totalIntern <= 1;
            tlDeleteBtn.classList.toggle('d-none', preventDelete);
            tlDeleteBtn.disabled = preventDelete;
        }

        if (sep1) sep1.style.display = (user && user.textContent) ? '' : 'none';
        if (sep2) sep2.style.display = (hosp && hosp.textContent) ? '' : 'none';

        if (tot) tot.textContent = String(window.TOTAL_VISITAS || '');
        if (totW) totW.style.display = (window.TOTAL_VISITAS && Number(window.TOTAL_VISITAS) > 0) ? '' : 'none';
        if (sep3) sep3.style.display = (totW && totW.style.display !== 'none') ? '' : 'none';

        if (box) box.style.display = 'block';

        document.querySelectorAll('#tab-timeline .ht-marker').forEach(function(a) {
            a.classList.remove('active');
        });
        marker.classList.add('active');

        var container = document.querySelector('#tab-timeline .ht-container');
        if (container) {
            container.scrollLeft = Math.max(0, marker.offsetLeft - (container.clientWidth / 2));
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var timeline = document.querySelector('#tab-timeline');
        if (!timeline) return;

        timeline.addEventListener('click', function(ev) {
            var a = ev.target.closest('.ht-marker');
            if (!a) return;
            ev.preventDefault();
            updateTimelineDetailsFromMarker(a);
        });

        // Seleção inicial: ativo ou ÚLTIMO (mais recente)
        var active = timeline.querySelector('.ht-marker.active');
        var markers = timeline.querySelectorAll('.ht-marker');
        var last = markers.length ? markers[markers.length - 1] : null;

        updateTimelineDetailsFromMarker(active || last);

        // Rola tudo para a direita para garantir a última etiqueta visível
        var container = document.querySelector('#tab-timeline .ht-container');
        if (container) {
            container.scrollLeft = container.scrollWidth;
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        var modalDelete = document.getElementById('modalDeleteVisita');
        if (!modalDelete) return;
        var confirmBtn = modalDelete.querySelector('[data-action="confirm-delete"]');
        var feedback = modalDelete.querySelector('.js-delete-feedback');
        var redirectUrl = <?= json_encode(rtrim($BASE_URL, '/') . '/internacoes/visualizar/' . (int)$id_internacao . '#tab-visitas') ?>;
        var defaultVisitaId = <?= (int)$id_visita_row ?>;
        var selectedVisitaId = defaultVisitaId;

        if (!confirmBtn) return;

        modalDelete.addEventListener('show.bs.modal', function(event) {
            var trigger = event.relatedTarget;
            var btnId = trigger ? parseInt(trigger.getAttribute('data-delete-visita'), 10) : NaN;
            selectedVisitaId = Number.isFinite(btnId) && btnId > 0 ? btnId : defaultVisitaId;
        });

        confirmBtn.addEventListener('click', function() {
            confirmBtn.disabled = true;
            if (feedback) {
                feedback.classList.add('d-none');
                feedback.textContent = '';
            }

            var formData = new FormData();
            formData.append('type', 'delete');
            formData.append('id_visita', selectedVisitaId);
            formData.append('redirect', redirectUrl);
            formData.append('ajax', '1');
            formData.append('csrf', '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>');

            fetch('process_visita.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function(resp) { return resp.json(); })
                .then(function(res) {
                    if (res && res.success) {
                        window.location.href = res.redirect || redirectUrl;
                        return;
                    }
                    var msg = (res && res.message) ? res.message : 'Não foi possível remover a visita.';
                    if (feedback) {
                        feedback.textContent = msg;
                        feedback.classList.remove('d-none');
                    } else {
                        alert(msg);
                    }
                })
                .catch(function() {
                    if (feedback) {
                        feedback.textContent = 'Falha inesperada ao remover a visita.';
                        feedback.classList.remove('d-none');
                    } else {
                        alert('Falha inesperada ao remover a visita.');
                    }
                })
                .finally(function() {
                    confirmBtn.disabled = false;
                });
        });
    });
</script>

<?php include_once("templates/footer.php"); ?>

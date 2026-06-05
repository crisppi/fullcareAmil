<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <title>Internação - Detalhes</title>
    <script src="js/timeout.js"></script>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1G1q9ctK3S2m6U5LprNkwdF8l+MTwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<?php
include_once("check_logado.php");
include_once("globals.php");
include_once("templates/header.php");

// Models / DAOs
include_once("models/internacao.php");
require_once("dao/internacaoDao.php");
include_once("models/hospital.php");
include_once("dao/hospitalDao.php");
include_once("models/patologia.php");
include_once("dao/patologiaDao.php");
include_once("dao/pacienteDao.php");
include_once("models/prorrogacao.php");
include_once("dao/prorrogacaoDao.php");
include_once("models/tuss.php");
include_once("dao/tussDao.php");


// === Helpers ===
function e($v)
{
    return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8');
}
function fmtDate($s)
{
    if (empty($s) || $s === '0000-00-00') return '-';
    $ts = strtotime(substr($s, 0, 10));
    return $ts ? date("d/m/Y", $ts) : '-';
}
function daysFrom($dateStr)
{
    if (empty($dateStr)) return null;
    try {
        $d1 = new DateTimeImmutable(date('Y-m-d'));
        $d0 = new DateTimeImmutable((new DateTime(substr($dateStr, 0, 10)))->format('Y-m-d'));
        return $d0->diff($d1)->days;
    } catch (Throwable $t) {
        return null;
    }
}
function initials_from_name($name)
{
    $name = trim((string)$name);
    if ($name === '') return 'PA';
    $parts = preg_split('/\s+/', $name);
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = mb_substr(($parts[count($parts) - 1] ?? ''), 0, 1);
    return mb_strtoupper($first . $last);
}

// === Entrada ===
$id_internacao = filter_input(INPUT_GET, "id_internacao", FILTER_SANITIZE_NUMBER_INT);
$id_internacao = $id_internacao !== null ? trim($id_internacao) : '';

$internacaoDao = new internacaoDAO($conn, $BASE_URL);

// WHERE por ID
$whereParts = [];
if ($id_internacao !== '' && ctype_digit($id_internacao)) {
    $whereParts[] = 'ac.id_internacao = ' . (int)$id_internacao;
}
$where = implode(' AND ', $whereParts);
$order = null;
$limit = 1;

$internacoes = $internacaoDao->selectAllInternacao($where, $order, $limit);
$data = $internacoes && isset($internacoes[0]) ? $internacoes[0] : null;

if (!$data) {
?>
    <div class="container mt-4">
        <div class="alert alert-warning">Nenhuma internação encontrada para o parâmetro informado.</div>
        <?php include_once("diversos/backbtn_internacao.php"); ?>
    </div>
<?php
    include_once("templates/footer.php");
    exit;
}

// Datas / auxiliares
$iniciais = initials_from_name($data['nome_pac'] ?? '');
$data_intern_format = fmtDate($data['data_intern_int'] ?? '');

// (Opcional) outras listas para as abas
$visitas = [];
if (class_exists('visitaDAO')) {
    include_once("dao/visitaDAO.php");
    $visitaDAO = new visitaDAO($conn, $BASE_URL);
    if (method_exists($visitaDAO, 'joinVisitaInternacao')) {
        $visitas = $visitaDAO->joinVisitaInternacao((int)$id_internacao) ?: [];
    }
}

// === PRORROGAÇÕES (usa seu método) ===
$prorrogacoes = [];
if (file_exists(__DIR__ . "/dao/prorrogacaoDao.php")) {
    include_once("dao/prorrogacaoDao.php");
    if (class_exists('prorrogacaoDAO')) {
        $prDAO = new prorrogacaoDAO($conn, $BASE_URL);
        if (method_exists($prDAO, 'selectInternacaoProrrog')) {
            $prorrogacoes = $prDAO->selectInternacaoProrrog((int)$id_internacao) ?: [];
        }
    }
}
// ===== Filtro/ordenação das prorrogações =====
$pr_ini_raw = filter_input(INPUT_GET, 'pr_ini', FILTER_DEFAULT) ?: '';
$pr_fim_raw = filter_input(INPUT_GET, 'pr_fim', FILTER_DEFAULT) ?: '';

function ymd($s){
  if (!$s) return null;
  $s = substr($s, 0, 10);
  $ts = strtotime($s);
  return $ts ? date('Y-m-d', $ts) : null;
}

$pr_ini = ymd($pr_ini_raw);
$pr_fim = ymd($pr_fim_raw);

// Base para trabalho (se $prorrogacoes vier vazio, fica vazio mesmo)
$pr_filtered = $prorrogacoes;

// Filtra por período (interseção do intervalo)
if ($pr_ini || $pr_fim) {
  $pr_filtered = array_filter($prorrogacoes, function($p) use($pr_ini, $pr_fim){
    $ini = ymd($p['ini'] ?? null);
    $fim = ymd($p['fim'] ?? ($p['ini'] ?? null));
    if (!$ini && !$fim) return false;
    if ($pr_ini && $pr_fim) return ($fim >= $pr_ini) && ($ini <= $pr_fim);
    if ($pr_ini) return $fim >= $pr_ini;
    if ($pr_fim) return $ini <= $pr_fim;
    return true;
  });
}

// Ordena por data mais recente (usa fim; se vazio, usa ini)
usort($pr_filtered, function($a, $b){
  $da = strtotime($a['fim'] ?: ($a['ini'] ?? ''));
  $db = strtotime($b['fim'] ?: ($b['ini'] ?? ''));
  return $db <=> $da; // DESC
});

// Total de diárias (do conjunto filtrado)
$pr_total_diarias = array_reduce($pr_filtered, function($sum, $p){
  return $sum + (int)($p['diarias'] ?? 0);
}, 0);

$tussItens = [];
$negociacoes = [];
?>

<div id="main-container" class="container-fluid py-3">
    <div class="v2-max mx-auto">

        <!-- Header Card (faixa roxa à esquerda + sombra forte) -->
        <div class="card shadow-sm mb-3 header-card">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex gap-3 align-items-center">
                    <div class="v2-avatar"><?= e($iniciais) ?></div>
                    <div>
                        <h4 class="mb-1"><?= e(mb_strtoupper($data['nome_pac'] ?? '-')) ?></h4>
                        <div class="d-flex flex-wrap gap-2 text-secondary small">
                            <span><i class="fa-solid fa-hospital me-1"></i><?= e($data['nome_hosp'] ?? '-') ?></span>
                            <span>•</span>
                            <span><i class="fa-solid fa-bed-pulse me-1"></i>Internação <?= e($data['id_internacao'] ?? '-') ?></span>
                            <span>•</span>
                            <span><i class="fa-regular fa-calendar me-1"></i>Data da internação: <?= e($data_intern_format) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Caixa de conteúdo com abas -->
        <div class="card shadow-sm">
            <div class="card-body">
                <ul class="nav nav-pills mb-3" id="internTabs" role="tablist"
                    style="--bs-nav-pills-link-active-bg:#5e2363; --bs-nav-pills-link-active-color:#fff; --bs-nav-link-color:#5e2363; --bs-nav-link-hover-color:#5e2363;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="resumo-tab" data-bs-toggle="pill" data-bs-target="#resumo" type="button" role="tab">
                            <i class="fa-solid fa-bars me-2"></i>Resumo
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="visitas-tab" data-bs-toggle="pill" data-bs-target="#visitas" type="button" role="tab">
                            <i class="fa-solid fa-stethoscope me-2"></i>Visitas
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="prorrog-tab" data-bs-toggle="pill" data-bs-target="#prorrog" type="button" role="tab">
                            <i class="fa-solid fa-clock-rotate-left me-2"></i>Prorrogações
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tuss-tab" data-bs-toggle="pill" data-bs-target="#tuss" type="button" role="tab">
                            <i class="fa-solid fa-list-check me-2"></i>TUSS
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="neg-tab" data-bs-toggle="pill" data-bs-target="#neg" type="button" role="tab">
                            <i class="fa-solid fa-handshake me-2"></i>Negociações
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="internTabsContent">
                    <!-- Resumo -->
                    <div class="tab-pane fade show active" id="resumo" role="tabpanel" aria-labelledby="resumo-tab">
                        <div class="row g-3">
                            <!-- Card Internação -->
                            <div class="col-12 col-lg-6">
                                <div class="card ov-card ov-int" style="
                      border-radius:14px; background:#fff;
                      box-shadow:0 8px 24px rgba(0,0,0,.06);
                      background-image:linear-gradient(to right, var(--ov, #5e2363) 6px, #fff 6px);
                  ">
                                    <div class="card-body">
                                        <div class="ov-head">
                                            <div class="ov-icon"><i class="fa-solid fa-bed-pulse"></i></div>
                                            <h6 class="ov-title mb-0">Internação</h6>
                                        </div>

                                        <dl class="details-dl">
                                            <dt>Código</dt>
                                            <dd><?= e($data['id_internacao'] ?? '-') ?></dd>
                                            <dt>Senha</dt>
                                            <dd><?= e($data['senha_int'] ?? '-') ?></dd>
                                            <dt>Acomodação</dt>
                                            <dd><?= e($data['acomodacao_int'] ?? '—') ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Detalhes -->
                            <div class="col-12 col-lg-6">
                                <div class="card ov-card ov-vis" style="
                      border-radius:14px; background:#fff;
                      box-shadow:0 8px 24px rgba(0,0,0,.06);
                      background-image:linear-gradient(to right, var(--ov, #0f766e) 6px, #fff 6px);
                  ">
                                    <div class="card-body">
                                        <div class="ov-head">
                                            <div class="ov-icon"><i class="fa-solid fa-user-nurse"></i></div>
                                            <h6 class="ov-title mb-0">Detalhes</h6>
                                        </div>

                                        <dl class="details-dl">
                                            <dt>Tipo admissão</dt>
                                            <dd><?= e($data['tipo_admissao_int'] ?? '-') ?></dd>
                                            <dt>Modo Internação</dt>
                                            <dd><?= e($data['modo_internacao_int'] ?? '-') ?></dd>
                                            <dt>Especialidade</dt>
                                            <dd><?= e($data['especialidade_int'] ?? '-') ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card full-width: Relatório Internação -->
                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <div class="card ov-card ov-int" style="
                      border-radius:14px; background:#fff;
                      box-shadow:0 8px 24px rgba(0,0,0,.06);
                      background-image:linear-gradient(to right, var(--ov, #5e2363) 6px, #fff 6px);
                  ">
                                    <div class="card-body">
                                        <div class="ov-head">
                                            <h6 class="ov-title mb-0">Relatório Internação</h6>
                                        </div>
                                        <div class="v2-relatorio">
                                            <?= nl2br(e($data['rel_int'] ?? '-')) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- VISITAS -->
                    <div class="tab-pane fade" id="visitas" role="tabpanel">
                        <p class="text-muted mb-0">Conteúdo da aba Visitas...</p>
                    </div>

                    <!-- PRORROGAÇÕES -->
                <div class="tab-pane fade" id="prorrog" role="tabpanel" aria-labelledby="prorrog-tab">
  <div class="card ov-card ov-int" style="
      border-radius:14px; background:#fff;
      box-shadow:0 8px 24px rgba(0,0,0,.06);
      background-image:linear-gradient(to right, var(--ov, #5e2363) 6px, #fff 6px);
  ">
    <div class="card-body">
      <div class="ov-head">
        <h6 class="ov-title mb-0">Prorrogações</h6>
      </div>

      <!-- Filtro por período -->
      <form method="get" action="<?= e($_SERVER['PHP_SELF']) ?>#prorrog" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="id_internacao" value="<?= e($id_internacao) ?>">
        <div class="col-sm-4 col-md-3">
          <label class="form-label small text-muted">Início</label>
          <input type="date" name="pr_ini" value="<?= e($pr_ini ?? $pr_ini_raw) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-sm-4 col-md-3">
          <label class="form-label small text-muted">Fim</label>
          <input type="date" name="pr_fim" value="<?= e($pr_fim ?? $pr_fim_raw) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
          <button class="btn btn-sm btn-primary" style="background:#5e2363;border-color:#5e2363;">Filtrar</button>
        </div>
        <div class="col-auto">
          <a class="btn btn-sm btn-outline-secondary"
             href="<?= e($_SERVER['PHP_SELF']).'?id_internacao='.urlencode($id_internacao) ?>#prorrog">Limpar</a>
        </div>
      </form>

      <?php if (!empty($pr_filtered)): ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-2">
            <thead class="table-light">
              <tr>
                <th>Acomodação</th>
                <th>Período</th>
                <th class="text-center">Diárias</th>
                <th class="text-center">Isolamento</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pr_filtered as $p):
                $acom = e($p['acomod'] ?? '-');
                $ini  = fmtDate($p['ini'] ?? '');
                $fim  = fmtDate($p['fim'] ?? '');
                $periodo = ($ini !== '-' || $fim !== '-') ? ($ini . ' — ' . $fim) : '-';
                $dias = (int)($p['diarias'] ?? 0);
                $isoRaw = strtolower((string)($p['isolamento'] ?? $p['isol_1_pror'] ?? ''));
                $iso = ($isoRaw === 's' || $isoRaw === 'sim' || $isoRaw === '1') ? 'Sim' : 'Não';
              ?>
              <tr>
                <td><?= $acom ?></td>
                <td><?= $periodo ?></td>
                <td class="text-center"><?= $dias ?></td>
                <td class="text-center">
                  <?php if ($iso === 'Sim'): ?>
                    <span class="badge rounded-pill text-bg-danger">Sim</span>
                  <?php else: ?>
                    <span class="badge rounded-pill text-bg-secondary">Não</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="text-end fw-semibold">Total de diárias <?= (int)$pr_total_diarias ?></div>
      <?php else: ?>
        <div class="text-muted">Nenhuma prorrogação<?= ($pr_ini || $pr_fim) ? ' no período selecionado.' : ' registrada para esta internação.' ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>






                    <!-- TUSS -->
                    <div class="tab-pane fade" id="tuss" role="tabpanel">
                        <p class="text-muted mb-0">Conteúdo da aba TUSS...</p>
                    </div>

                    <!-- NEGOCIAÇÕES -->
                    <div class="tab-pane fade" id="neg" role="tabpanel">
                        <p class="text-muted mb-0">Conteúdo da aba Negociações...</p>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="small text-muted">Atualizado: <?= e(date('d/m/Y H:i')) ?></div>
                    <a href="<?= !empty($_SERVER['HTTP_REFERER']) ? 'javascript:history.back()' : $BASE_URL . 'internacoes.php' ?>"
                        class="btn btn-ghost-brand btn-sm rounded-pill shadow-sm">
                        <i class="fa-solid fa-arrow-left me-2"></i>Voltar
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Bootstrap JS (se já vem no header.php, ok deixar também) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Preferencial: usa Bootstrap para ativar abas e atualizar URL
    (function() {
        var hash = window.location.hash;
        if (hash) {
            var triggerEl = document.querySelector('#internTabs button[data-bs-target="' + hash + '"]');
            if (triggerEl && window.bootstrap && bootstrap.Tab) new bootstrap.Tab(triggerEl).show();
        }
        document.querySelectorAll('#internTabs button[data-bs-toggle="pill"]').forEach(function(btn) {
            btn.addEventListener('shown.bs.tab', function(ev) {
                var target = ev.target.getAttribute('data-bs-target');
                if (target) history.replaceState(null, '', target);
            });
        });
    })();

    // Fallback manual (se Bootstrap não estiver ativo por algum motivo)
    (function() {
        if (window.bootstrap && bootstrap.Tab) return; // já tem Bootstrap funcionando
        var tabs = document.getElementById('internTabs');
        if (!tabs) return;

        tabs.addEventListener('click', function(ev) {
            var btn = ev.target.closest('[data-bs-toggle="pill"]');
            if (!btn) return;
            ev.preventDefault();
            var target = btn.getAttribute('data-bs-target');
            if (!target) return;

            tabs.querySelectorAll('.nav-link').forEach(function(el) {
                el.classList.remove('active');
                el.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('.tab-pane').forEach(function(p) {
                p.classList.remove('show', 'active');
            });

            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
            var pane = document.querySelector(target);
            if (pane) {
                pane.classList.add('show', 'active');
            }
            history.replaceState(null, '', target);
        });
    })();
</script>

<!-- Overrides/estilos -->
<style id="fix-override">
    :root {
        --brand: #5e2363;
        --brand-050: #f9f3fc;
        --brand-100: #f2e8f7;
        --brand-700: #4b1c50;
        --brand-800: #431945;
        --teal: #0f766e;
        --ink: #111827;
        --muted: #6b7280;
    }

    /* ===== Header card: faixa roxa à esquerda + sombra ===== */
    .card.shadow-sm.mb-3 {
        position: relative !important;
        overflow: hidden !important;
        border-radius: 14px !important;
        border: 1px solid #e9ddf2 !important;
        box-shadow: 0 14px 28px rgba(94, 35, 99, .12), 0 8px 22px rgba(0, 0, 0, .06) !important;
        background: #fff !important;
    }

    .card.shadow-sm.mb-3::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 8px;
        background: #5e2363;
        border-radius: 14px 0 0 14px;
        pointer-events: none;
    }

    /* ===== Abas roxas (nav-pills) ===== */
    #internTabs button.nav-link {
        background: var(--brand-050) !important;
        color: var(--brand) !important;
        border: 1px solid rgba(94, 35, 99, .15) !important;
        border-radius: 12px !important;
        font-weight: 700 !important;
    }

    #internTabs button.nav-link:not(.active):hover {
        background: rgba(94, 35, 99, .18) !important;
        color: var(--brand) !important;
    }

    #internTabs button.nav-link.active,
    #internTabs .show>button.nav-link {
        background-color: var(--brand) !important;
        border-color: var(--brand) !important;
        color: #fff !important;
    }

    #internTabs button.nav-link i {
        color: inherit !important;
    }

    /* ===== Botões de navegação roxos ===== */
    .btn-outline-secondary {
        color: var(--brand) !important;
        border-color: var(--brand) !important;
        background: var(--brand-050) !important;
    }

    .btn-outline-secondary:hover {
        background: var(--brand) !important;
        color: #fff !important;
        border-color: var(--brand) !important;
    }

    .btn-ghost-brand {
        background: var(--brand) !important;
        color: #fff !important;
        border-color: var(--brand) !important;
    }

    .btn-ghost-brand:hover {
        filter: brightness(.92);
    }

    /* ===== Subcards com barra lateral (via gradient inline) ===== */
    .ov-card {
        position: relative !important;
    }

    /* Título dos blocos */
    .ov-head {
        display: flex;
        align-items: center;
        margin-bottom: 1.25rem;
    }

    .ov-title {
        margin: 0;
        font-weight: 600;
        font-size: 1rem;
        color: var(--ink);
    }

    /* Lista de detalhes (dt/dd padrão do Bootstrap já dá o look empilhado) */
    .details-dl {
        display: grid;
        grid-template-columns: 140px 1fr;
        gap: 10px 16px;
        margin: 0;
    }

    .details-dl dt {
        color: var(--muted);
        font-weight: 600;
    }

    .details-dl dd {
        color: var(--ink);
        font-weight: 700;
        margin: 0;
    }

    /* Avatar do topo */
    .v2-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: #ecd5f9;
        color: var(--brand);
        font-weight: 700;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    /* ===== Tabela de prorrogações ===== */
    .prr-table {
        width: 100%;
    }

    .prr-row {
        display: grid;
        grid-template-columns: 110px 1fr 130px 130px 90px 130px;
        /* Cod | Acomod | Início | Fim | Diárias | Isolamento */
        gap: 8px 16px;
        align-items: center;
        padding: 10px 0;
        border-top: 1px solid #eee;
    }

    .prr-row:first-child {
        border-top: none;
    }

    .prr-head {
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .02em;
        color: var(--muted);
        font-weight: 700;
    }

    .chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 2px 10px;
        border-radius: 999px;
        font-size: .80rem;
        font-weight: 700;
        background: var(--brand-050);
        color: var(--brand);
        border: 1px solid rgba(94, 35, 99, .15);
    }

    .chip.ok {
        background: #dcfce7;
        color: #166534;
        border-color: rgba(22, 101, 52, .15);
    }

    /* Responsivo */
    @media (max-width: 768px) {
        .prr-row {
            grid-template-columns: 90px 1fr 110px 110px 70px 110px;
        }
    }

    @media (max-width: 576px) {
        .prr-head {
            display: none;
        }

        .prr-row {
            grid-template-columns: 1fr 1fr;
            gap: 6px 12px;
            border-top: 1px solid #eee;
        }

        .prr-row>div:nth-child(1) {
            grid-column: 1 / span 2;
            font-weight: 700;
        }

        /* Código */
        .prr-row>div:nth-child(2)::before {
            content: "Acomodação: ";
            color: var(--muted);
        }

        .prr-row>div:nth-child(3)::before {
            content: "Início: ";
            color: var(--muted);
        }

        .prr-row>div:nth-child(4)::before {
            content: "Fim: ";
            color: var(--muted);
        }

        .prr-row>div:nth-child(5)::before {
            content: "Diárias: ";
            color: var(--muted);
        }

        .prr-row>div:nth-child(6)::before {
            content: "Isolamento: ";
            color: var(--muted);
        }
    }

    /* ===== Prorrogações: layout enxuto ===== */
    .prr-table {
        width: 100%;
    }

    .prr-row {
        display: grid;
        grid-template-columns: 1.2fr 1.4fr 0.7fr 0.9fr;
        /* Acomod | Período | Diárias | Isolamento */
        gap: 10px 16px;
        align-items: center;
        padding: 12px 0;
        border-top: 1px solid #eee;
    }

    .prr-row:first-child {
        border-top: none;
    }

    .prr-head {
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .02em;
        color: var(--muted);
        font-weight: 700;
    }

    /* colunas */
    .prr-acom {
        font-weight: 700;
        color: var(--ink);
        display: flex;
        align-items: center;
    }

    .prr-periodo {
        color: var(--ink);
        font-weight: 600;
    }

    .prr-diarias {
        font-weight: 700;
        color: var(--ink);
    }

    .prr-iso {
        display: flex;
        align-items: center;
    }

    /* chips */
    .chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 2px 10px;
        border-radius: 999px;
        font-size: .80rem;
        font-weight: 700;
        background: var(--brand-050);
        color: var(--brand);
        border: 1px solid rgba(94, 35, 99, .15);
    }

    .chip.ok {
        background: #dcfce7;
        color: #166534;
        border-color: rgba(22, 101, 52, .15);
    }

    .chip.no {
        background: #fff1f2;
        color: #991b1b;
        border-color: rgba(153, 27, 27, .2);
    }

    /* resumo no rodapé do card */
    .prr-summary {
        margin-top: 12px;
        padding: 10px 12px;
        border: 1px dashed rgba(94, 35, 99, .25);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #faf5fb;
    }

    .prr-summary span {
        color: var(--muted);
        font-weight: 600;
    }

    .prr-summary strong {
        color: var(--ink);
        font-size: 1rem;
    }

    /* responsivo */
    @media (max-width: 768px) {
        .prr-row {
            grid-template-columns: 1fr 1fr;
            /* quebra em 2 colunas */
        }

        .prr-head {
            display: none;
        }

        .prr-row>.prr-acom {
            grid-column: 1 / span 2;
        }

        .prr-periodo::before {
            content: "Período: ";
            color: var(--muted);
            font-weight: 600;
        }

        .prr-diarias::before {
            content: "Diárias: ";
            color: var(--muted);
            font-weight: 600;
        }

        .prr-iso::before {
            content: "Isolamento: ";
            color: var(--muted);
            font-weight: 600;
            margin-right: 6px;
        }
    }
</style>

<?php require_once("templates/footer.php"); ?>

</html>
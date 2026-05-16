<?php
ob_start();

require_once("templates/header.php");
require_once("models/message.php");

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");
include_once("models/paciente.php");
include_once("dao/pacienteDao.php");
include_once("models/hospital.php");
include_once("dao/hospitalDao.php");
include_once("models/pagination.php");
include_once("array_dados.php");

$internacaoDao = new internacaoDAO($conn, $BASE_URL);
$paginationObj = new pagination(0, 1, 10);

$pesquisa_hosp = trim((string)(filter_input(INPUT_GET, 'pesquisa_hosp', FILTER_SANITIZE_SPECIAL_CHARS) ?: ''));
$pesquisa_pac  = trim((string)(filter_input(INPUT_GET, 'pesquisa_pac', FILTER_SANITIZE_SPECIAL_CHARS) ?: ''));
$pesquisa_matricula = trim((string)(filter_input(INPUT_GET, 'pesquisa_matricula', FILTER_SANITIZE_SPECIAL_CHARS) ?: ''));
$limite        = filter_input(INPUT_GET, 'limite', FILTER_VALIDATE_INT) ?: 10;
$ordenar       = filter_input(INPUT_GET, 'ordenar', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'data_intern_int DESC';
$pagAtual      = filter_input(INPUT_GET, 'pag', FILTER_VALIDATE_INT) ?: 1;

$whereParams = [':internado_int' => 's'];
$condicoes = ['ac.internado_int = :internado_int'];
if ($pesquisa_hosp !== '') {
    $condicoes[] = 'ho.nome_hosp LIKE :pesquisa_hosp';
    $whereParams[':pesquisa_hosp'] = '%' . $pesquisa_hosp . '%';
}
if ($pesquisa_pac !== '') {
    $condicoes[] = 'pa.nome_pac LIKE :pesquisa_pac';
    $whereParams[':pesquisa_pac'] = '%' . $pesquisa_pac . '%';
}
if ($pesquisa_matricula !== '') {
    $condicoes[] = 'pa.matricula_pac LIKE :pesquisa_matricula';
    $whereParams[':pesquisa_matricula'] = '%' . $pesquisa_matricula . '%';
}
$where = implode(' AND ', $condicoes);

$dadosTotais = $internacaoDao->selectAllInternacaoList($where, $ordenar, null, $whereParams);
$qtdItens = is_array($dadosTotais) ? count($dadosTotais) : 0;
$paginationObj = new pagination($qtdItens, $pagAtual, $limite);
$lista = $internacaoDao->selectAllInternacaoList($where, $ordenar, $paginationObj->getLimit(), $whereParams);
$totalPages = $qtdItens > 0 ? (int)ceil($qtdItens / $limite) : 1;

$dadosAlta = $dados_alta ?? [];
sort($dadosAlta);
?>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/listagem_padrao.css', ENT_QUOTES, 'UTF-8') ?>">

<div class="container-fluid form_container listagem-page" id="main-container" style="margin-top:-4px;">
    <div class="gerar-alta-hero">
        <div>
            <span class="gerar-alta-kicker">Internações abertas</span>
            <h4 class="page-title gerar-alta-title">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                Gerar altas
            </h4>
            <p class="gerar-alta-subtitle">Filtre os pacientes internados e gere altas em lote com data, hora e motivo.</p>
        </div>
    </div>

    <div class="card shadow-sm mb-3 gerar-alta-filter-card">
        <div class="card-body">
            <form class="gerar-alta-filter-form">
                <div class="gerar-alta-filter-grid">
                    <div class="gerar-alta-filter-field">
                        <label class="form-label small text-muted">Hospital</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-hospital"></i></span>
                            <input type="text" class="form-control form-control-sm" name="pesquisa_hosp"
                                value="<?= htmlspecialchars($pesquisa_hosp) ?>" placeholder="Nome do hospital">
                        </div>
                    </div>
                    <div class="gerar-alta-filter-field">
                        <label class="form-label small text-muted">Paciente</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control form-control-sm" name="pesquisa_pac"
                                value="<?= htmlspecialchars($pesquisa_pac) ?>" placeholder="Nome do paciente">
                        </div>
                    </div>
                    <div class="gerar-alta-filter-field">
                        <label class="form-label small text-muted">Matrícula</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-123"></i></span>
                            <input type="text" class="form-control form-control-sm" name="pesquisa_matricula"
                                value="<?= htmlspecialchars($pesquisa_matricula) ?>" placeholder="Matrícula do paciente">
                        </div>
                    </div>
                    <div class="gerar-alta-filter-field">
                        <label class="form-label small text-muted">Registros</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-list-ol"></i></span>
                            <select name="limite" class="form-select form-select-sm">
                                <?php foreach ([10, 20, 50] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $limite == $opt ? 'selected' : '' ?>>
                                    <?= $opt ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="gerar-alta-filter-actions">
                        <button class="btn btn-sm btn-primary btn-filtro-buscar btn-filtro-limpar-icon"
                            type="submit" title="Filtrar" aria-label="Filtrar">
                            <span class="material-icons" aria-hidden="true">search</span>
                        </button>
                        <a href="list_internacao_gerar_alta.php"
                            class="btn btn-sm btn-light btn-filtro-limpar btn-filtro-limpar-icon"
                            title="Limpar filtros" aria-label="Limpar filtros">
                            <i class="bi bi-trash3" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <style>
        .listagem-page { padding: 4px 4px 14px; }
        .gerar-alta-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            margin: 0 0 10px;
            padding: 2px 4px 0;
        }
        .gerar-alta-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #7b5a9a;
            font-size: .58rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .gerar-alta-kicker::before {
            content: "";
            width: 18px;
            height: 2px;
            border-radius: 999px;
            background: currentColor;
        }
        .gerar-alta-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 2px 0 0 !important;
            color: #2d203d;
            font-size: 1.08rem !important;
            font-weight: 800;
        }
        .gerar-alta-title i {
            color: #5e2363;
            font-size: 1rem;
        }
        .gerar-alta-subtitle {
            margin: 3px 0 0;
            color: #7b7b8d;
            font-size: .72rem;
        }
        #main-container .card { border-radius: 16px; border:1px solid #eee8f6; box-shadow: 0 10px 28px -22px rgba(89,46,131,.28); }
        #main-container .form-label { font-size: .66rem; margin-bottom: 4px; }
        #main-container .form-control,
        #main-container .form-select,
        #main-container .btn { min-height: 32px; height: 32px; font-size: .72rem; line-height: 1.2; }
        #main-container .btn-lg { min-height: 32px; padding: 6px 12px !important; font-size: .72rem; }
        .gerar-alta-filter-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, #fff 0%, #fbf8fe 100%);
        }
        .gerar-alta-filter-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background: #5e2363;
        }
        .gerar-alta-filter-card .card-body { padding: .9rem 1rem .95rem 1.15rem; }
        .gerar-alta-filter-form { margin: 0; }
        .gerar-alta-filter-grid {
            display: grid;
            grid-template-columns: minmax(230px, 1.65fr) minmax(190px, 1.25fr) minmax(180px, 1fr) minmax(130px, .62fr) auto;
            align-items: end;
            gap: 10px;
        }
        .gerar-alta-filter-field,
        .gerar-alta-filter-actions {
            min-width: 0;
        }
        .gerar-alta-filter-card .input-group { min-width: 0; }
        .gerar-alta-filter-card .input-group-text {
            min-width: 34px;
            justify-content: center;
            border-color: #ddd6e7;
            background: #f8f2fd;
            color: #5e2363;
            padding-left: .55rem;
            padding-right: .55rem;
        }
        .gerar-alta-filter-card .input-group .form-control,
        .gerar-alta-filter-card .input-group .form-select {
            border-color: #ddd6e7;
            background-color: #fff;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
        }
        .gerar-alta-filter-card .input-group:focus-within .input-group-text,
        .gerar-alta-filter-card .input-group:focus-within .form-control,
        .gerar-alta-filter-card .input-group:focus-within .form-select {
            border-color: #cdb8dd;
        }
        .gerar-alta-filter-actions {
            display: flex;
            align-items: end;
            gap: 8px;
            padding-bottom: 0;
        }
        #main-container .gerar-alta-filter-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            min-width: 34px;
            max-width: 34px;
            padding: 0 !important;
            border-radius: 10px;
        }
        #main-container .gerar-alta-filter-actions .btn-primary {
            background: #5e2363;
            border-color: #5e2363;
            box-shadow: 0 8px 16px rgba(94, 35, 99, 0.16);
        }
        #main-container .gerar-alta-filter-actions .btn-filtro-limpar {
            border-color: #eadff3;
            background: linear-gradient(180deg, #fff 0%, #f8f2fd 100%);
            color: #8b5a7a;
            box-shadow: 0 8px 16px rgba(94, 35, 99, 0.08);
        }
        #main-container .gerar-alta-filter-actions .btn i {
            margin: 0;
            font-size: .95rem;
            line-height: 1;
        }
        #main-container .gerar-alta-filter-actions .material-icons {
            margin: 0;
            font-size: 16px;
            line-height: 1;
        }
        .gerar-alta-actionbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            padding: 10px 12px;
            border: 1px solid #eee6f5;
            border-radius: 14px;
            background: #fff;
            color: #767184;
            font-size: .76rem;
            box-shadow: 0 10px 24px -22px rgba(94, 35, 99, .45);
        }
        #main-container .gerar-alta-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 36px;
            height: 36px;
            border: 0;
            border-radius: 11px !important;
            padding: 0 14px !important;
            background: linear-gradient(135deg, #5e2363 0%, #7b2dbf 100%) !important;
            box-shadow: 0 10px 18px rgba(94, 35, 99, .18);
        }
        .gerar-alta-card {
            position: relative;
            border-radius: 16px;
            padding: .85rem 1rem 1rem 1.35rem;
            margin-bottom: .75rem;
            background: #fff;
            border: 1px solid #efe7f6;
            box-shadow: 0 12px 35px -22px rgba(97, 35, 133, 0.6);
        }
        .gerar-alta-card::before {
            content: "";
            position: absolute;
            left: 0.55rem;
            top: .8rem;
            bottom: .8rem;
            width: 4px;
            border-radius: 10px;
            background: linear-gradient(180deg, #7b2dbf, #c35c91);
        }
        .gerar-alta-meta-grid {
            display: grid;
            grid-template-columns: minmax(210px, 1.45fr) minmax(210px, 1.45fr) minmax(140px, .75fr) minmax(95px, .5fr);
            gap: 8px;
        }
        .gerar-alta-meta-card {
            min-width: 0;
            min-height: 58px;
            padding: .45rem .62rem;
            border: 1px solid #dfcdea;
            border-left: 3px solid #7b5a9a;
            border-radius: 10px;
            background: linear-gradient(180deg, #fff 0%, #f8f2fd 100%);
            box-shadow: 0 8px 16px rgba(94, 35, 99, .06), inset 0 1px 0 rgba(255,255,255,.9);
        }
        .gerar-alta-meta-card strong,
        .gerar-alta-meta-card small {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .gerar-alta-meta-card strong {
            margin-top: 3px;
            color: #2c2742;
            font-size: .78rem;
            font-weight: 700;
        }
        .gerar-alta-meta-card small {
            margin-top: 0;
            min-height: 1em;
            color: #8b8ca5;
            font-size: .62rem;
        }
        .gerar-alta-meta-label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #5e2363;
            font-size: .55rem;
            font-weight: 800;
            letter-spacing: .08em;
            line-height: 1;
            text-transform: uppercase;
        }
        .gerar-alta-meta-label i {
            font-size: .68rem;
            letter-spacing: 0;
        }
        .gerar-alta-meta-card-id {
            text-align: center;
        }
        .gerar-alta-meta-card-id .gerar-alta-meta-label {
            justify-content: center;
        }
        .gerar-alta-card .tag {
            font-size: 0.62rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #8b8ca5;
        }
        .gerar-alta-card .shadow-field {
            background: #f9fafc;
            border-radius: 12px;
            padding: .5rem .75rem;
            border: 1px solid #eef0f4;
            min-height: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .gerar-alta-card .shadow-field .form-control,
        .gerar-alta-card .shadow-field .form-select {
            width: 100%;
        }
        .gerar-alta-card .shadow-field.bg-light {
            display: block;
            height: auto;
            min-height: 58px;
        }
        .gerar-alta-card .gerar-alta-check-field {
            justify-content: center;
            cursor: pointer;
        }
        .gerar-alta-card .gerar-alta-check-field .form-check-input {
            width: 1.05rem;
            height: 1.05rem;
            margin: 0;
            cursor: pointer;
        }
        .gerar-alta-card hr {
            margin: .8rem 0;
            opacity: .12;
            border-color: #7b2dbf;
        }
        @media (max-width: 991px) {
            .gerar-alta-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .gerar-alta-filter-actions {
                justify-content: flex-start;
            }
            .gerar-alta-meta-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .gerar-alta-card {
                padding: .8rem 1rem;
            }
        }
        @media (max-width: 575px) {
            .gerar-alta-filter-grid {
                grid-template-columns: 1fr;
            }
            .gerar-alta-meta-grid {
                grid-template-columns: 1fr;
            }
            .gerar-alta-meta-card-id,
            .gerar-alta-meta-card-id .gerar-alta-meta-label {
                text-align: left;
                justify-content: flex-start;
            }
        }
    </style>

    <form action="process_gerar_altas.php" method="POST" id="form-gerar-altas">
        <input type="hidden" name="type" value="gerar_altas">
        <?php if ($lista): ?>
        <div class="gerar-alta-actionbar">
            <span>
                Marque as internações, preencha data/hora/motivo e clique em <strong>Gerar altas</strong>.
            </span>
            <button type="submit" class="btn btn-lg text-white gerar-alta-submit">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                Gerar altas selecionadas
            </button>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (!$lista): ?>
                <div class="text-center text-muted py-4">Nenhum paciente internado.</div>
                <?php else: ?>
                <?php foreach ($lista as $row):
                $idIntern = (int)($row['id_internacao'] ?? 0);
                $fieldPrefix = 'alta_' . $idIntern;
                $dataInternacaoFormatada = !empty($row['data_intern_int'])
                    ? date('d/m/Y', strtotime($row['data_intern_int']))
                    : '—';
                $internadoUti = strtolower((string)($row['internado_uti'] ?? 'n')) === 's';
                $idUti = (int)($row['id_uti'] ?? 0);
                $fkInternacaoUti = (int)($row['fk_internacao_uti'] ?? $idIntern);
            ?>
                <div class="gerar-alta-card">
                    <input type="hidden" name="<?= $fieldPrefix ?>_uti_flag" value="<?= $internadoUti ? 's' : 'n' ?>">
                    <?php if ($internadoUti && $idUti): ?>
                    <input type="hidden" name="<?= $fieldPrefix ?>_uti_id" value="<?= $idUti ?>">
                    <input type="hidden" name="<?= $fieldPrefix ?>_uti_fk" value="<?= $fkInternacaoUti ?>">
                    <?php endif; ?>
                <div class="gerar-alta-meta-grid">
                    <div class="gerar-alta-meta-card">
                        <span class="gerar-alta-meta-label"><i class="bi bi-hospital" aria-hidden="true"></i>Hospital</span>
                        <strong><?= htmlspecialchars($row['nome_hosp'] ?? '-') ?></strong>
                        <small><?= htmlspecialchars($row['acomodacao_int'] ?? '') ?></small>
                    </div>
                    <div class="gerar-alta-meta-card">
                        <span class="gerar-alta-meta-label"><i class="bi bi-person" aria-hidden="true"></i>Paciente</span>
                        <strong><?= htmlspecialchars($row['nome_pac'] ?? '-') ?></strong>
                        <small><?= htmlspecialchars($row['titular_int'] ?? '') ?></small>
                    </div>
                    <div class="gerar-alta-meta-card">
                        <span class="gerar-alta-meta-label"><i class="bi bi-calendar2-plus" aria-hidden="true"></i>Internação</span>
                        <strong><?= $dataInternacaoFormatada ?></strong>
                        <small>Data de entrada</small>
                    </div>
                    <div class="gerar-alta-meta-card gerar-alta-meta-card-id">
                        <span class="gerar-alta-meta-label"><i class="bi bi-hash" aria-hidden="true"></i>ID</span>
                        <strong><?= $idIntern ?></strong>
                        <small>Internação</small>
                    </div>
                </div>

                <hr>

                <div class="row g-3 align-items-end">
                    <?php if ($internadoUti && $idUti): ?>
                    <div class="col-12">
                        <div class="shadow-field bg-light">
                            <span class="d-block text-danger fw-semibold">Paciente na UTI</span>
                            <small class="text-muted">Informe a data da alta da UTI antes de gerar.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="tag mb-1">Data alta UTI</div>
                        <div class="shadow-field">
                            <input type="date" class="form-control form-control-sm border-0 bg-transparent p-0"
                                name="<?= $fieldPrefix ?>_uti_data">
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <div class="tag mb-1">Data da alta</div>
                        <div class="shadow-field">
                            <input type="date" class="form-control form-control-sm border-0 bg-transparent p-0"
                                name="<?= $fieldPrefix ?>_data">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="tag mb-1">Hora da alta</div>
                        <div class="shadow-field">
                            <input type="time" class="form-control form-control-sm border-0 bg-transparent p-0"
                                name="<?= $fieldPrefix ?>_hora">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="tag mb-1">Motivo da alta</div>
                        <div class="shadow-field">
                            <select class="form-select form-select-sm border-0 bg-transparent p-0"
                                name="<?= $fieldPrefix ?>_motivo">
                                <option value="">Selecione...</option>
                                <?php foreach ($dadosAlta as $motivo): ?>
                                <option value="<?= htmlspecialchars($motivo) ?>"><?= htmlspecialchars($motivo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="tag mb-1">Gerar</div>
                        <label class="shadow-field gerar-alta-check-field">
                            <input type="checkbox" class="form-check-input" name="gerar[]" value="<?= $idIntern ?>">
                        </label>
                    </div>
                </div>
            </div>
                <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>

    </form>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">
        <span class="text-muted small">Total encontrado: <?= $qtdItens ?></span>
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Paginação">
            <ul class="pagination justify-content-center mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i == $pagAtual ? 'active' : '' ?>">
                    <a class="page-link" href="<?= 'internacoes/gerar-alta/pagina/' . $i ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

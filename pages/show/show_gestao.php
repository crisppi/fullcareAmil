<!DOCTYPE html>
<html lang="pt-br">
<script src="js/timeout.js"></script>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background: #f6f3ff;
        }

        .gestao-page {
            width: 100%;
            margin: 0;
            padding: 24px 0 60px;
        }

        .gestao-hero {
            background: linear-gradient(135deg, #1d4ed8, #38bdf8);
            color: #fff;
            border-radius: 24px;
            padding: 20px 28px;
            box-shadow: 0 18px 40px rgba(24, 0, 30, 0.28);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 18px;
        }

        .gestao-hero h1 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            color: #fff;
        }

        .gestao-hero small {
            color: rgba(255, 255, 255, 0.9);
        }

        .gestao-content {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .gestao-card {
            background: #fbf7ff;
            border: 1px solid #d9c8ef;
            border-radius: 22px;
            padding: 18px 22px;
            box-shadow: 0 14px 32px rgba(45, 18, 70, 0.08);
        }

        .gestao-card--primary {
            background: #eef6ff;
            border-color: #c7ddf6;
        }

        .gestao-card__title {
            margin: 0 0 10px;
            font-size: 1.05rem;
            font-weight: 700;
            color: #2f1149;
        }

        .gestao-info-row {
            display: flex;
            flex-wrap: wrap;
            gap: 18px 28px;
            font-size: 0.95rem;
        }

        .gestao-info-row strong {
            color: #2f1149;
        }

        .gestao-field {
            margin: 6px 0;
            color: #333;
        }

        .gestao-back {
            margin-top: 18px;
        }
    </style>

</head>

<body>
    <?php
    include_once("check_logado.php");

    include_once("globals.php");
    Gate::enforceAction($conn, $BASE_URL, 'view', 'Você não tem permissão para visualizar este registro.');
    include_once("templates/header.php");

    include_once("models/internacao.php");
    require_once("dao/internacaoDao.php");

    require_once("models/message.php");

    include_once("models/hospital.php");
    include_once("dao/hospitalDao.php");

    include_once("models/paciente.php");
    include_once("dao/pacienteDAO.php");

    include_once("models/gestao.php");
    include_once("dao/gestaoDao.php");


    // Pegar o id da internacao
    $id_gestao = filter_input(INPUT_GET, "id_gestao", FILTER_SANITIZE_NUMBER_INT);
    // $where = $id_internacao;
    $internacao;
    $order = null;
    $obLimite = 1;
    $gestaoDao = new gestaoDAO($conn, $BASE_URL);

    //Instanciar o metodo internacao   
    $whereParams = [];
    $condicoes = [];
    if (strlen((string)$id_gestao)) {
        $condicoes[] = 'ge.id_gestao LIKE :id_gestao';
        $whereParams[':id_gestao'] = '%' . (string)$id_gestao . '%';
    }
    $condicoes = array_filter($condicoes);
    // REMOVE POSICOES VAZIAS DO FILTRO
    $where = implode(' AND ', $condicoes);
    $gestao = $gestaoDao->selectAllGestaoLis($where, $order, $obLimite, $whereParams);
    // print_r($gestao);

    ?>
    <div class="gestao-page">
        <div class="container-fluid">
            <div class="gestao-hero">
                <div>
                    <h1>Gestão do paciente</h1>
                </div>
                <div>
                    <i class="fa-solid fa-clipboard-list" style="font-size:1.4rem;"></i>
                </div>
            </div>

            <div class="gestao-content">
                <div class="gestao-card gestao-card--primary">
                    <h2 class="gestao-card__title">Dados principais</h2>
                    <div class="gestao-info-row">
                        <div><strong>Paciente:</strong> <?= htmlspecialchars($gestao['0']['nome_pac'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>ID Internação:</strong> <?= $gestao['0']['id_internacao'] ?></div>
                        <div><strong>Hospital:</strong> <?= htmlspecialchars($gestao['0']['nome_hosp'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>Data Internação:</strong> <?= date("d/m/Y", strtotime($gestao['0']['data_intern_int'])) ?></div>
                    </div>
                </div>

                <?php if (($gestao['0']['home_care_ges'] ?? '') === "s") { ?>
                <div class="gestao-card">
                    <h2 class="gestao-card__title">Notificação de Home Care</h2>
                    <div class="gestao-field"><strong>Relatório:</strong> <?= htmlspecialchars($gestao['0']['rel_home_care_ges'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php } ?>

                <?php if (($gestao['0']['desospitalizacao_ges'] ?? '') === "s") { ?>
                <div class="gestao-card">
                    <h2 class="gestao-card__title">Notificação de Desospitalização</h2>
                    <div class="gestao-field"><strong>Relatório:</strong> <?= htmlspecialchars($gestao['0']['rel_desospitalizacao_ges'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php } ?>

                <?php if (($gestao['0']['alto_custo_ges'] ?? '') === "s") { ?>
                <div class="gestao-card">
                    <h2 class="gestao-card__title">Notificação de Alto Custo</h2>
                    <div class="gestao-field"><strong>Relatório:</strong> <?= htmlspecialchars($gestao['0']['rel_alto_custo_ges'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php } ?>

                <?php if (($gestao['0']['opme_ges'] ?? '') === "s") { ?>
                <div class="gestao-card">
                    <h2 class="gestao-card__title">Notificação de OPME</h2>
                    <div class="gestao-field"><strong>Relatório:</strong> <?= htmlspecialchars($gestao['0']['rel_opme_ges'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php } ?>

                <?php if (($gestao['0']['evento_adverso_ges'] ?? '') === "s") { ?>
                <div class="gestao-card">
                    <h2 class="gestao-card__title">Notificação de Evento Adverso</h2>
                    <div class="gestao-field"><strong>Relatório:</strong> <?= htmlspecialchars($gestao['0']['rel_evento_adverso_ges'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="gestao-field"><strong>Tipo Evento:</strong> <?= htmlspecialchars($gestao['0']['tipo_evento_adverso_gest'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php } ?>

                <div class="gestao-back">
                    <?php include_once("diversos/backbtn_gestao.php"); ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

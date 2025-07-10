<?php
include "inc_valida_secao.php";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Index</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="capa.png">
    <style>
        h3, h4 {
            color: #FFD700 !important;
        }
        card-container {
            background-color: #FFFFFF;
            border-radius: 10px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
            aspect-ratio: 1 / 1;
            max-width: 60%;
            margin: 50px auto;
            height: 60%;
        }
        .card-container:hover {
            transform: translateY(-3px);
        }
        .card-image {
            width: 60%;
            height: 65%;
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFD700;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            padding: 20px;
            margin: 0 auto;
        }
        .card-image i {
            margin: 0 auto;
            display: block;
        }
        .card-content {
            height: 40%;
            padding: 15px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-title {
            color: black !important;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .card-button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 20px;
        }
        .card-button:hover {
            background-color: #4CAF50;
        }
        @media (max-width: 768px) {
            .card-container {
                max-width: 250px;
            }
            .card-image {
                font-size: 14px;
            }
            .card-title {
                font-size: 14px;
            }
            .card-button {
                padding: 5px 10px;
                font-size: 12px;
            }
        }
        @media (max-width: 576px) {
            .card-container {
                max-width: 200px;
            }
            .card-image {
                font-size: 12px;
            }
            .card-title {
                font-size: 13px;
            }
            .card-button {
                padding: 4px 8px;
                font-size: 11px;
            }
        }
        .row {
            margin-top: 100px;
        }
    </style>
</head>
<body>
<?php include "menu.php"; ?>
<div class="container-fluid">
    <h3 class="text-center">PCM - Programa de Compliance Público Municipal</h3>
    <p class="text-center"></p>
    <?php
    // MOSTRA PREFEITURA LOGADA
    if(isset($row) && !empty($row)) {
        echo "<h4 class=\"text-center\">Prefeitura de ".$row['municipio']."</h4>\n";
        echo "<p class=\"text-center\"> </p>\n";
    } else {
        echo "<h4 class=\"text-center\">Logado como Administrador do Sistema</h4>\n";
    }
    ?>
    <!-- Container para os Cards -->
    <div class="row justify-content-center">
        <?php
        if(isset($row) && !empty($row)) {
            // Usuário NÃO é administrador
            ?>
            <!-- Card Home -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="card-container">
                    <div class="card-image">
                        <i class="fas fa-id-card-clip fa-10x"></i>
                    </div>
                    <div class="card-content">
                        <div class="card-title">Questionario de identificação</div>
                        <a href="prefeitura.php" class="card-button">Acessar</a>
                    </div>
                </div>
            </div>
            <!-- Card Editar Questionário -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="card-container">
                    <div class="card-image">
                        <i class="fas fa-file-signature fa-10x"></i>
                    </div>
                    <div class="card-content">
                        <div class="card-title">Editar Questionário de Seleção</div>
                        <a href="editar_questionario.php" class="card-button">Acessar</a>
                    </div>
                </div>
            </div>
            <!-- Card Power Bi -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="card-container">
                    <div class="card-image">
                         <i class="fas fa-signal  fa-8x"></i>
                    </div>
                    <div class="card-content">
                        <div class="card-title">Classificação do Municipio</div>
                        <a href="powerbi4.php?cnpj=<?php echo urlencode($_SESSION['cnpj_prefeitura']); ?>" class="card-button">Acessar</a>
                    </div>
                </div>
            </div>
            <?php
        } else {
            // Usuário É administrador
            ?>
            <!-- Card Listagem -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="card-container">
                    <div class="card-image" style="background-image: url('listagem.png');"></div>
                    <div class="card-content">
                        <div class="card-title">Listagem</div>
                        <a href="listagem.php" class="card-button">Acessar</a>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="card-container">
                    <div class="card-image">
                         <i class="fas fa-signal  fa-8x"></i>
                    </div>
                    <div class="card-content">
                        <div class="card-title">Power Bi</div>
                        <a href="powerbi4.php" class="card-button">Acessar</a>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
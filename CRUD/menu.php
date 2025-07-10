<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .navbar {
            background: linear-gradient(to bottom right, #013220, #013824);
        }
        .navbar-nav .nav-link {
            color: #FFD700 !important;
        }
        .navbar-nav .nav-link:hover {
            color: #FFA500 !important;
        }
        .navbar-brand img {
            max-width: 180px;
            height: auto;
            transition: max-width 0.3s ease;
        }
        @media (max-width: 768px) {
            .navbar-brand img {
                max-width: 120px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="capa.png" alt="PCM Logo" class="img-fluid">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    if (isset($_SESSION['cnpj_prefeitura']) && $_SESSION['cnpj_prefeitura'] != "11111111111111"): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="prefeitura.php">Questionário de Identificação</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="editar_questionario.php">Questionário de Seleção</a>
                        </li>
                    <?php elseif (isset($_SESSION['cnpj_prefeitura']) && $_SESSION['cnpj_prefeitura'] == "11111111111111"): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="listagem.php">Listagem</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="prefeitura.php">Cadastro</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Sair</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
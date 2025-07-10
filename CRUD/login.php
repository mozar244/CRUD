<?php
session_start();
include 'conexao.php'; // Arquivo para conexão com o banco

// Processar login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cnpj = str_replace(".", "", str_replace("-", "", str_replace("/", "", $_POST['cnpj'])));
    $senha = md5($_POST['senha']); // Substituir por password_hash() em produção
    
    if ($cnpj == "11111111111111" && $senha == md5("pcm@2025")) { // Substituir por hash seguro
        $_SESSION['cnpj_prefeitura'] = $cnpj;
        $_SESSION['id_municipio'] = null; // Caso especial para o CNPJ padrão, sem id_municipio
        header("Location: index.php");
        exit;
    } else {
        $sql = "SELECT cnpj_prefeitura, senha, id_municipio FROM pcm2025_prefeitura WHERE cnpj_prefeitura = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $cnpj);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($senha == $row['senha']) { // Substituir por password_verify()
                $_SESSION['cnpj_prefeitura'] = $cnpj;
                $_SESSION['id_municipio'] = $row['id_municipio'];
                header("Location: index.php");
                exit;
            } else {
                $erro = "CNPJ ou senha inválidos.";
            }
        } else {
            $erro = "CNPJ ou senha inválidos.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PCM - Programa de Compliance Público Municipal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="icon" type="image/png" href="capa.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(to bottom right, #013220, #013824);
            color: #FFD700;
            font-family: Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            padding: 20px;
        }
        .logo {
            position: absolute;
            top: 0;
            left: 0;
            width: 200px;
            height: 100px;
            object-fit: cover;
            object-position: top left;
        }
        .map-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('capa.png');
            background-size: cover;
            background-position: center;
            opacity: 0.1;
            z-index: -1;
        }
        .content-container {
            text-align: center;
            margin-bottom: 20px;
            width: 100%;
            max-width: 600px;
        }
        .title {
            color: #FFA500;
            font-size: clamp(18px, 5vw, 24px);
            margin-bottom: 10px;
        }
        .login-container {
            background-color: #FFFFFF;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            position: relative;
        }
        .login-container label {
            color: #000000;
            font-weight: bold;
        }
        .login-container input {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #F0F8FF;
        }
        .btn-login {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            margin-bottom: 10px;
        }
        .btn-login:hover {
            background-color: #45a049;
        }
        .error {
            color: #FF0000;
            text-align: center;
            margin-bottom: 10px;
        }
        .support-text {
            color: #000000;
            text-align: center;
            font-size: 14px;
            margin-top: 10px;
        }
        .cadastro-container {
            text-align: center;
            margin-top: 20px;
            width: 100%;
            max-width: 400px;
        }
        .first-access {
            color: #FFA500;
            font-size: clamp(14px, 4vw, 16px);
            margin-bottom: 10px;
        }
        @media (max-width: 768px) {
            .logo {
                width: 150px;
                height: 75px;
            }
            .login-container {
                padding: 15px;
            }
            .cadastro-container {
                position: static;
                margin-top: 20px;
            }
            .btn-cadastro {
                width: 100%;
                max-width: 150px;
            }
        }
        @media (max-width: 480px) {
            .logo {
                width: 120px;
                height: 60px;
            }
            .title {
                font-size: 18px;
            }
            .login-container {
                padding: 10px;
            }
            .btn-login, .btn-cadastro {
                padding: 8px;
            }
            .support-text {
                font-size: 12px;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        $(document).ready(function(){
            $('input[name="cnpj"]').mask('00.000.000/0000-00');
        });
    </script>
</head>
<body>
    <img src="capa.png" alt="PCM Logo" class="logo">
    <div class="map-background"></div>
    <div class="content-container">
        <h2 class="title">Acesse seu cadastro</h2>
    </div>
    <div class="login-container">
        <?php if (isset($erro)) echo "<div class='error'>$erro</div>"; ?>
        <form method="POST">
            <label>CNPJ do Município:</label>
            <input type="text" name="cnpj" class="form-control" placeholder="Informe o cnpj" required>
            <label>Senha:</label>
            <input type="password" name="senha" class="form-control" required>
            <button type="submit" class="btn-login">Entrar</button>
            <p class="support-text">Esqueceu a senha? Entre em contato pelo número <br>(62) 3201-5369.</p>
        </form>
    </div>
</body>
</html>
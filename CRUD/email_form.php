<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Habilite a exibição de erros para depuração (remova em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'lib/PHPMailer/PHPMailer-master/src/PHPMailer.php';
require 'lib/PHPMailer/PHPMailer-master/src/SMTP.php';
require 'lib/PHPMailer/PHPMailer-master/src/Exception.php';

// Verifique se o FPDF está disponível
$fpdf_path = 'lib/fpdf/fpdf.php';
if (!file_exists($fpdf_path)) {
    die("Erro: Biblioteca FPDF não encontrada em '$fpdf_path'. Por favor, baixe a biblioteca em http://www.fpdf.org/ e coloque o arquivo 'fpdf.php' no diretório 'lib/fpdf/'.");
}

// Verifique se a pasta de fontes está disponível
$font_path = 'lib/fpdf/font/helveticab.php';
if (!file_exists($font_path)) {
    die("Erro: Arquivo de fonte 'helveticab.php' não encontrado em 'lib/fpdf/font/'. Certifique-se de que a pasta 'font/' do FPDF foi copiada corretamente para 'lib/fpdf/'.");
}

require $fpdf_path;

include "conexao.php"; // Inclua o arquivo de conexão com o banco de dados

// Verificar se os dados do e-mail estão na sessão
if (!isset($_SESSION['email_data'])) {
    header("Location: questionario.php?erro=Dados+nao+encontrados");
    exit;
}

$email_data = $_SESSION['email_data'];
$cnpj = $email_data['cnpj'];
$municipio = $email_data['municipio'];
$data_finalizacao = $email_data['data_finalizacao'];

$erro = '';
$sucesso = '';

// Buscar o ID da prefeitura com base no CNPJ
$sql = "SELECT id FROM pcm2025_prefeitura WHERE cnpj_prefeitura = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Erro na preparação da consulta: " . $conn->error);
}
$stmt->bind_param("s", $cnpj);
$stmt->execute();
$result = $stmt->get_result();
$prefeitura = $result->fetch_assoc();
$prefeitura_id = $prefeitura ? $prefeitura['id'] : null;
$stmt->close();

if (!$prefeitura_id) {
    $erro = "Erro: Não foi possível identificar a prefeitura.";
}

// Função para converter strings UTF-8 para ISO-8859-1 (compatível com FPDF)
function convertToIso($text) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
}

// Função para formatar respostas do tipo "planilha"
function formatarRespostaPlanilha($resposta) {
    $linhas = explode(';', $resposta);
    $resultado = [];
    foreach ($linhas as $linha) {
        $dados = explode('|', $linha);
        if (count($dados) >= 4) {
            $secretaria = $dados[0];
            $efetivos = $dados[1];
            $comissionados = $dados[2];
            $temporarios = $dados[3];
            $total = isset($dados[4]) ? $dados[4] : ($efetivos + $comissionados + $temporarios);
            $resultado[] = "Secretaria: $secretaria\n  Efetivos: $efetivos\n  Comissionados: $comissionados\n  Temporários: $temporarios\n  Total: $total";
        }
    }
    return implode("\n", $resultado);
}

// Função para gerar o PDF e retornar o caminho do arquivo temporário
function gerarPDF($conn, $prefeitura_id, $municipio, $cnpj, $data_finalizacao) {
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Definir codificação para suportar caracteres acentuados
    $municipio = convertToIso($municipio);
    $cnpj = convertToIso($cnpj);
    $data_finalizacao = convertToIso($data_finalizacao);

    // Cabeçalho
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, convertToIso('Respostas do Questionário - ') . $municipio, 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, "CNPJ: $cnpj", 0, 1);
    $pdf->Cell(0, 8, "Município: $municipio", 0, 1);
    $pdf->Cell(0, 8, "Data de Finalização: $data_finalizacao", 0, 1);
    $pdf->Ln(10);

    // Buscar perguntas e respostas
    $sql = "SELECT p.id, p.pergunta, p.tipo_resposta, r.resposta 
            FROM pcm2025_resposta r 
            JOIN pcm2025_pergunta p ON r.pergunta_id = p.id 
            WHERE r.prefeitura_id = ? 
            ORDER BY p.ordem ASC, p.id ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Erro na preparação da consulta: " . $conn->error);
    }
    $stmt->bind_param("i", $prefeitura_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, convertToIso('Respostas:'), 0, 1);
    $pdf->SetFont('Arial', '', 12);

    $contador = 1;
    while ($row = $result->fetch_assoc()) {
        $pergunta = convertToIso($row['pergunta']);
        $resposta = convertToIso($row['resposta']);
        $tipo_resposta = $row['tipo_resposta'];

        // Numerar a pergunta
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->MultiCell(0, 8, "$contador. $pergunta", 0, 'L');
        $pdf->SetFont('Arial', '', 12);

        // Formatar a resposta
        if ($tipo_resposta == 'planilha' && !empty($resposta)) {
            $resposta_formatada = formatarRespostaPlanilha($resposta);
            $pdf->MultiCell(0, 8, $resposta_formatada, 0, 'L');
        } else {
            $pdf->MultiCell(0, 8, "Resposta: $resposta", 0, 'L');
        }
        $pdf->Ln(5);
        $contador++;
    }

    $stmt->close();

    // Salvar o PDF em um arquivo temporário
    $temp_dir = sys_get_temp_dir();
    if (!is_writable($temp_dir)) {
        die("Erro: O diretório temporário '$temp_dir' não tem permissão de escrita.");
    }
    $filename = tempnam($temp_dir, "Respostas_$municipio_") . ".pdf";
    if (!$filename) {
        die("Erro: Não foi possível criar o arquivo temporário no diretório '$temp_dir'.");
    }
    $pdf->Output('F', $filename); // 'F' salva no sistema de arquivos
    return $filename;
}

// Processar envio de e-mail com PDF anexado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    // Validação básica
    if (empty($email)) {
        $erro = "Por favor, preencha o e-mail.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Por favor, insira um e-mail válido.";
    } elseif (!$prefeitura_id) {
        $erro = "Erro: Não foi possível identificar a prefeitura.";
    } else {
        // Gerar o PDF
        $pdf_file = gerarPDF($conn, $prefeitura_id, $municipio, $cnpj, $data_finalizacao);

        // Enviar o e-mail usando PHPMailer
        $mail = new PHPMailer(true);

        try {
            // Configurações do servidor SMTP
            $mail->isSMTP();
            $mail->Host = 'mail.goias.gov.br';
            $mail->SMTPAuth = true;
            $mail->Username = 'svcpcm.cge@sistemas.goias.gov.br';
            $mail->Password = 'ABFG72kH';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            // Configurações do e-mail
            $mail->setFrom('svcpcm.cge@sistemas.goias.gov.br', 'Sistema PCM');
            $mail->addAddress($email);
            $mail->addBCC('pcm.cge@goias.gov.br', 'PCM');
            

            // Anexar o PDF
            if (file_exists($pdf_file)) {
                $mail->addAttachment($pdf_file, "Respostas_$municipio.pdf");
            } else {
                throw new Exception("Arquivo PDF não encontrado para anexo.");
            }

            $mail->isHTML(false);
            $mail->Subject = 'Questionario Finalizado - ' . $municipio;
            $mail->Body = "CNPJ: $cnpj\nMunicipio: $municipio\nMensagem: Finalizou o questionario\nData de Finalizacao: $data_finalizacao\n\nEm anexo, as respostas completas do questionário.";

            $mail->send();
            $sucesso = "E-mail enviado com sucesso, com as respostas em anexo!";
            
            // Limpar o arquivo temporário
            if (file_exists($pdf_file)) {
                unlink($pdf_file);
            }

            // Limpar os dados da sessão
            unset($_SESSION['email_data']);
            
            // Redirecionar para login.php com mensagem de sucesso
            header("Location: login.php");
            exit;
        } catch (Exception $e) {
            $erro = "Erro ao enviar e-mail: {$mail->ErrorInfo}";
            // Tentar remover o arquivo temporário em caso de erro
            if (file_exists($pdf_file)) {
                unlink($pdf_file);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inserir Credenciais de E-mail</title>
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
        h3 {
            color: #FFA500;
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
        .container {
            background-color: #FFFFFF;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            position: relative;
        }
        .form-label {
            color: #000000;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-primary:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        p {
            color: #000000;
        }
    </style>
</head>
<body>
    <img src="capa.png" alt="PCM Logo" class="logo">
    <div class="map-background"></div>
    <div class="container mt-5">
        <h3 class="text-center">Finalizar Questionário</h3>
        <p class="text-center">Para enviar a notificação de finalização com as respostas em anexo, insira o e-mail de destino.</p>
        
        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger text-center"><?php echo $erro; ?></div>
        <?php endif; ?>
        <?php if (!empty($sucesso)): ?>
            <div class="alert alert-success text-center"><?php echo $sucesso; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">E-mail de Destino</label>
                <input type="email" name="email" id="email" class="form-control" required placeholder="exemplo@dominio.com">
            </div>
            <div class="text-center">
                <button type="submit" class="btn btn-primary">Enviar Notificação</button>
            </div>
        </form>
    </div>
</body>
</html>
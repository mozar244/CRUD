<?php
include 'inc_valida_secao.php';

// Verificar se o usuário é administrador ou se há um CNPJ válido na URL
if (isset($_SESSION['cnpj_prefeitura']) && !empty($_SESSION['cnpj_prefeitura']) && $_SESSION['cnpj_prefeitura'] != "11111111111111") {
    echo "<p><b>Erro: acesso negado!</b></p>";
    exit();
} elseif (isset($_GET['cnpj']) && !empty($_GET['cnpj'])) {
    // PEGA DADOS DO CNPJ CLICADO
    $sql = "SELECT p.id,
            p.id_municipio,
            p.cnpj_prefeitura,
            p.fone_prefeitura,
            p.endereco_prefeitura,
            p.cep_prefeitura,
            p.nome_prefeito,
            p.cpf_prefeito,
            p.nome_responsavel_pela_inscricao,
            p.cpf_responsavel_pela_inscricao,
            p.cargo_responsavel_pela_inscricao,
            p.fone_responsavel_pela_inscricao,
            p.data_alteracao, 
            m.municipio 
            FROM pcm2025_prefeitura p 
            JOIN pcm2025_municipio m ON p.id_municipio = m.id  
            WHERE p.cnpj_prefeitura = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo "<div class='text-center text-danger'>Erro na preparação da consulta: " . htmlspecialchars($conn->error) . "</div>";
        exit();
    }

    $stmt->bind_param("s", $_GET['cnpj']);
    $stmt->execute();
    $result = $stmt->get_result();
    $rowPref = $result->fetch_assoc();
    $stmt->close();

    if (!$rowPref) {
        echo "<div class='text-center text-danger'>Erro: Prefeitura não encontrada para o CNPJ informado.</div>";
        exit();
    }

    // PEGA PERGUNTAS E RESPOSTAS DA PREFEITURA
    $sql = "SELECT per.ordem, 
            per.pergunta, 
            resp.resposta, 
            DATE_FORMAT(resp.data_cadastro, '%d/%m/%Y %H:%i:%s') AS data_cadastro,
            DATE_FORMAT(resp.data_alteracao, '%d/%m/%Y %H:%i:%s') AS data_alteracao
            FROM pcm2025_pergunta per
            JOIN pcm2025_resposta resp ON resp.pergunta_id = per.id
            WHERE resp.prefeitura_id = ?
            ORDER BY per.ordem";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo "<div class='text-center text-danger'>Erro na preparação da consulta: " . htmlspecialchars($conn->error) . "</div>";
        exit();
    }

    $stmt->bind_param("i", $rowPref['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questionário Preenchido</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="icon" type="image/png" href="capa.png">
</head>
<body>
<?php include "menu.php"; ?>
<div class="container-lg">
    <h3 class="text-center">Questionário Preenchido</h3>
    <p> </p>
    <?php 
    if (isset($rowPref) && is_array($rowPref) && !empty($rowPref['municipio'])) {
        echo "<h4 class=\"text-center\">Prefeitura de " . htmlspecialchars($rowPref['municipio']) . "</h4>\n";
    } else {
        echo "<div class=\"text-center text-danger\">Erro: Não foi possível identificar a prefeitura.</div>\n";
        exit();
    }
    ?>
    <p> </p>
    <table class="table table-bordered table-striped mt-3">
        <thead>
            <tr>
                <th>Pergunta</th>
                <th>Data Cadastro</th>
                <th>Data Alteração</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <p><b><?php echo htmlspecialchars($row['pergunta']); ?></b></p>
                        <p><?php echo htmlspecialchars($row['resposta']); ?></p>
                    </td>
                    <td><?php echo htmlspecialchars($row['data_cadastro']); ?></td>
                    <td><?php echo htmlspecialchars($row['data_alteracao'] ?? 'Não alterada'); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
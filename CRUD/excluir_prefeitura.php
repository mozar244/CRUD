<?php
include 'inc_valida_secao.php';

// Verificar se o usuário é administrador
if (isset($_SESSION['cnpj_prefeitura']) && !empty($_SESSION['cnpj_prefeitura']) && $_SESSION['cnpj_prefeitura'] != "11111111111111") {
    echo "<p><b>Erro: acesso negado!</b></p>";
    exit();
}

// Verificar se o CNPJ foi passado
if (!isset($_GET['cnpj']) || empty($_GET['cnpj'])) {
    echo "<p><b>Erro: CNPJ não informado!</b></p>";
    exit();
}

$cnpj = $_GET['cnpj'];

// Conexão com o banco de dados (substitua com sua conexão)
include 'conexao.php'; // Certifique-se de que este arquivo contém a conexão $conn

// Preparar a query para evitar SQL Injection
$stmt = $conn->prepare("DELETE FROM pcm2025_prefeitura WHERE cnpj_prefeitura = ?");
$stmt->bind_param("s", $cnpj);

// Executar a exclusão
if ($stmt->execute()) {
    // Redirecionar de volta à listagem com uma mensagem de sucesso
    header("Location: listagem.php?msg=Prefeitura excluída com sucesso!");
} else {
    // Exibir erro caso algo dê errado
    echo "<p><b>Erro ao excluir: " . htmlspecialchars($conn->error) . "</b></p>";
}

$stmt->close();
$conn->close();
?>
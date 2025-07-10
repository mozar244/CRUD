<?php
include 'inc_valida_secao.php';

// Verificar se o usuário é administrador (apenas administradores podem acessar a listagem)
if (isset($_SESSION['cnpj_prefeitura']) && !empty($_SESSION['cnpj_prefeitura']) && $_SESSION['cnpj_prefeitura'] != "11111111111111") {
    echo "<p><b>Erro: acesso negado!</b></p>";
    exit();
} else {
    // Definir a ordenação com validação para evitar SQL Injection
    $allowed_columns = ['id', 'municipio', 'cnpj_prefeitura', 'email_prefeitura', 'nome_prefeito', 'nome_responsavel_pela_inscricao'];
    $coluna = isset($_GET['coluna']) && in_array($_GET['coluna'], $allowed_columns) ? $_GET['coluna'] : 'id';
    $ordem = isset($_GET['ordem']) && $_GET['ordem'] == 'desc' ? 'DESC' : 'ASC';

    // Query consulta dos dados com nomes corretos das tabelas
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
            DATE_FORMAT(p.data_cadastro, '%d/%m/%Y %H:%i:%s') AS data_cadastro,
            DATE_FORMAT(p.data_alteracao, '%d/%m/%Y %H:%i:%s') AS data_alteracao,
            m.municipio 
            FROM pcm2025_prefeitura p 
            JOIN pcm2025_municipio m ON p.id_municipio = m.id 
            ORDER BY $coluna $ordem";

    // Executar a consulta
    $result = $conn->query($sql);

    // Verificar se a consulta foi bem-sucedida
    if (!$result) {
        echo "<div class='text-center text-danger'>Erro na consulta: " . htmlspecialchars($conn->error) . "</div>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listagem de Prefeituras</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="icon" type="image/png" href="capa.png">
</head>
<body>
<?php include "menu.php"; ?>

<div class="container-fluid">
    <h3 class="text-center">Listagem de Prefeituras</h3>
    <table class="table table-bordered table-striped mt-3">
        <thead>
            <tr>
                <th><a href="?coluna=id&ordem=<?php echo $ordem == 'ASC' ? 'desc' : 'asc'; ?>">ID</a></th>
                <th><a href="?coluna=municipio&ordem=<?php echo $ordem == 'ASC' ? 'desc' : 'asc'; ?>">Município</a></th>
                <th><a href="?coluna=cnpj_prefeitura&ordem=<?php echo $ordem == 'ASC' ? 'desc' : 'asc'; ?>">CNPJ</a></th>
                <th><a href="?coluna=email_prefeitura&ordem=<?php echo $ordem == 'ASC' ? 'desc' : 'asc'; ?>">Email</a></th>
                <th>Telefone</th>
                <th>Endereço</th>
                <th>CEP</th>
                <th><a href="?coluna=nome_prefeito&ordem=<?php echo $ordem == 'ASC' ? 'desc' : 'asc'; ?>">Prefeito</a></th>
                <th><a href="?coluna=nome_responsavel&ordem=<?php echo $ordem == 'ASC' ? 'desc' : 'asc'; ?>">Responsável</a></th>
                <th>CPF Responsável</th>
                <th>Cargo Responsável</th>
                <th>Telefone Responsável</th>
                <th>Email Responsável</th>
                <th>Data Cadastro</th>
                <th>Data Alteração</th>
                <th>Editar</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><?php echo htmlspecialchars($row['municipio']); ?></td>
                    <td><?php echo htmlspecialchars($row['cnpj_prefeitura']); ?></td>
                    <td><?php echo htmlspecialchars($row['email_prefeitura']); ?></td>
                    <td><?php echo htmlspecialchars($row['fone_prefeitura']); ?></td>
                    <td><?php echo htmlspecialchars($row['endereco_prefeitura']); ?></td>
                    <td><?php echo htmlspecialchars($row['cep_prefeitura']); ?></td>
                    <td><?php echo htmlspecialchars($row['nome_prefeito']); ?></td>
                    <td><?php echo htmlspecialchars($row['nome_responsavel_pela_inscricao']); ?></td>
                    <td><?php echo htmlspecialchars($row['cpf_responsavel_pela_inscricao']); ?></td>
                    <td><?php echo htmlspecialchars($row['cargo_responsavel_pela_inscricao']); ?></td>
                    <td><?php echo htmlspecialchars($row['fone_responsavel_pela_inscricao']); ?></td>
                    <td><?php echo htmlspecialchars($row['email_responsavel_pela_inscricao']); ?></td>
                    <td><?php echo htmlspecialchars($row['data_cadastro']); ?></td>
                    <td><?php echo htmlspecialchars($row['data_alteracao']); ?></td>
                    <td><a href="prefeitura.php?cnpj=<?php echo urlencode($row['cnpj_prefeitura']); ?>" class="btn btn-primary">Editar</a></td>
                    <td><a href="questionario_preenchido.php?cnpj=<?php echo urlencode($row['cnpj_prefeitura']); ?>" class="btn btn-primary">Respostas</a></td>
                    <td>
                      <button class="btn btn-danger" onclick="confirmarExclusao('<?php echo urlencode($row['cnpj_prefeitura']); ?>', '<?php echo htmlspecialchars($row['municipio']); ?>')">Excluir</button>
                   </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<script>
function confirmarExclusao(cnpj, municipio) {
    if (confirm(`Tem certeza que deseja excluir o cadastro da prefeitura de ${municipio}? Essa ação não pode ser desfeita.`)) {
        window.location.href = `excluir_prefeitura.php?cnpj=${cnpj}`;
    }
}
</script>
</body>
</html>
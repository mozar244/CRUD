<?php
// Habilitar exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir o arquivo de conexão com o banco de dados
include "conexao.php";

// Buscar todas as respostas do tipo "planilha"
$sql = "SELECT r.id, r.prefeitura_id, r.pergunta_id, r.resposta
        FROM pcm2025_resposta r
        JOIN pcm2025_pergunta p ON r.pergunta_id = p.id
        WHERE p.tipo_resposta = 'planilha'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $resposta_id = $row['id'];
        $resposta = $row['resposta'];

        // Verificar se a resposta já contém o total geral (para evitar duplicação)
        if (strpos($resposta, '||') !== false) {
            echo "Resposta ID $resposta_id já contém total geral, pulando...\n";
            continue;
        }

        // Processar a resposta para calcular o total geral
        $total_geral = 0;
        if (!empty($resposta)) {
            $rows = explode(';', $resposta);
            foreach ($rows as $row_data) {
                $data = explode('|', $row_data);
                if (count($data) >= 5) {
                    $total_parcial = (int)$data[4]; // Total parcial está na 5ª posição
                    $total_geral += $total_parcial;
                }
            }
        }

        // Atualizar a resposta com o total geral
        $nova_resposta = $resposta . '||' . $total_geral;
        $sql_update = "UPDATE pcm2025_resposta SET resposta = ?, data_alteracao = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("si", $nova_resposta, $resposta_id);
        if ($stmt->execute()) {
            echo "Resposta ID $resposta_id atualizada com total geral: $total_geral\n";
        } else {
            echo "Erro ao atualizar resposta ID $resposta_id: " . $conn->error . "\n";
        }
        $stmt->close();
    }
} else {
    echo "Nenhuma resposta do tipo 'planilha' encontrada.\n";
}

// Fechar a conexão
$conn->close();
?>
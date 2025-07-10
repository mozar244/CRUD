<?php
include "inc_valida_secao.php";

// Receber os dados enviados via AJAX
$data = json_decode(file_get_contents('php://input'), true);

$prefeitura_id = $data['prefeitura_id'] ?? null;
$pergunta_id = $data['pergunta_id'] ?? null;
$secretarias = $data['secretarias'] ?? [];
$efetivos = $data['efetivos'] ?? [];
$comissionados = $data['comissionados'] ?? [];
$temporarios = $data['temporarios'] ?? [];

if (!$prefeitura_id || !$pergunta_id || empty($secretarias)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// Processar os dados da planilha
$resposta = '';
$has_data = false;
for ($i = 0; $i < count($secretarias); $i++) {
    if (empty($secretarias[$i]) && empty($efetivos[$i]) && empty($comissionados[$i]) && empty($temporarios[$i])) {
        continue;
    }

    if (!empty($secretarias[$i]) && !preg_match('/^[A-Za-zÀ-ÿ\s]+$/', $secretarias[$i])) {
        echo json_encode(['success' => false, 'message' => 'O nome da secretaria na linha ' . ($i + 1) . ' contém caracteres inválidos. Use apenas letras e espaços.']);
        exit;
    }

    $has_data = true;
    $efetivos[$i] = !empty($efetivos[$i]) ? (int)$efetivos[$i] : 0;
    $comissionados[$i] = !empty($comissionados[$i]) ? (int)$comissionados[$i] : 0;
    $temporarios[$i] = !empty($temporarios[$i]) ? (int)$temporarios[$i] : 0;
    $total_parcial = $efetivos[$i] + $comissionados[$i] + $temporarios[$i];
    $resposta .= htmlspecialchars($secretarias[$i]) . '|' . $efetivos[$i] . '|' . $comissionados[$i] . '|' . $temporarios[$i] . '|' . $total_parcial . ';';
}

if (!$has_data) {
    echo json_encode(['success' => false, 'message' => 'Preencha pelo menos uma secretaria.']);
    exit;
}

$resposta = rtrim($resposta, ';');

// Verificar se já existe uma resposta para essa prefeitura e pergunta
$sql = "SELECT id FROM pcm2025_resposta WHERE prefeitura_id = ? AND pergunta_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $prefeitura_id, $pergunta_id);
$stmt->execute();
$result = $stmt->get_result();
$resposta_salva = $result->fetch_assoc();
$resposta_id = $resposta_salva ? $resposta_salva['id'] : null;
$stmt->close();

// Inserir ou atualizar a resposta
if (empty($resposta_id)) {
    $sql = "INSERT INTO pcm2025_resposta (prefeitura_id, pergunta_id, resposta, data_cadastro) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $prefeitura_id, $pergunta_id, $resposta);
} else {
    $sql = "UPDATE pcm2025_resposta SET resposta = ?, data_alteracao = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $resposta, $resposta_id);
}

if ($stmt->execute()) {
    // Retornar os dados salvos para o frontend
    $planilha_data = [];
    $rows = explode(';', $resposta);
    foreach ($rows as $row) {
        $data = explode('|', $row);
        if (count($data) == 5) {
            $planilha_data[] = [
                'secretaria' => $data[0],
                'efetivos' => $data[1],
                'comissionados' => $data[2],
                'temporarios' => $data[3],
                'total_parcial' => $data[4]
            ];
        }
    }
    echo json_encode(['success' => true, 'planilha_data' => $planilha_data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar os dados no banco.']);
}

$stmt->close();
?>
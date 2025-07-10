<?php
include "inc_valida_secao.php";

// Função para registrar logs (para depuração)
function logDebug($message) {
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, "debug.log");
}

// Buscar informações da prefeitura logada (apenas para não-administradores)
$rowPref = null;
$prefeitura_id = null;

if (isset($_SESSION['cnpj_prefeitura']) && !empty($_SESSION['cnpj_prefeitura']) && $_SESSION['cnpj_prefeitura'] != "11111111111111") {
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
    $stmt->bind_param("s", $_SESSION['cnpj_prefeitura']);
    $stmt->execute();
    $result = $stmt->get_result();
    $rowPref = $result->fetch_assoc();
    $stmt->close();

    if ($rowPref) {
        $prefeitura_id = $rowPref['id'];
        logDebug("Prefeitura ID: " . $prefeitura_id);
    } else {
        logDebug("Nenhuma prefeitura encontrada para o CNPJ: " . $_SESSION['cnpj_prefeitura']);
        header("Location: logout.php");
        exit;
    }
} else {
    logDebug("Sessão inválida ou usuário administrador. Redirecionando para logout.");
    header("Location: logout.php");
    exit;
}

// Obter todas as perguntas ordenadas
$result = $conn->query("SELECT * FROM pcm2025_pergunta ORDER BY ordem ASC");
$perguntas = $result->fetch_all(MYSQLI_ASSOC);
logDebug("Total de perguntas recuperadas: " . count($perguntas));

// Obter as respostas da prefeitura
$respostas = [];
if (isset($prefeitura_id)) {
    $sql = "SELECT pergunta_id, resposta FROM pcm2025_resposta WHERE prefeitura_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $prefeitura_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $respostas[$row['pergunta_id']] = $row['resposta'];
        }
        logDebug("Respostas recuperadas: " . json_encode($respostas));
    } else {
        logDebug("Erro ao consultar respostas: " . $stmt->error);
    }
    $stmt->close();
} else {
    logDebug("Prefeitura ID não definido. Não foi possível buscar respostas.");
}

// Processar atualização das respostas (incluindo a planilha)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar'])) {
    logDebug("Formulário enviado: " . json_encode($_POST));

    foreach ($perguntas as $pergunta) {
        $pergunta_id = $pergunta['id'];
        $resposta = '';
        $justificativa = isset($_POST['justificativa'][$pergunta_id]) ? trim($_POST['justificativa'][$pergunta_id]) : '';
        $eixo_selecionado = isset($_POST['resposta'][$pergunta_id]['eixo']) ? trim($_POST['resposta'][$pergunta_id]['eixo']) : '';

        if ($pergunta_id == 2) {
            $resposta_sim_nao = isset($_POST['resposta'][$pergunta_id]['sim_nao']) ? trim($_POST['resposta'][$pergunta_id]['sim_nao']) : '';
            if ($resposta_sim_nao == '') {
                $erro = "Selecione uma opção 'Sim' ou 'Não' para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                logDebug("Erro validação pergunta $pergunta_id: $erro");
                break;
            }
            $resposta = $resposta_sim_nao;
            if ($resposta_sim_nao == 'Não') {
                if (!empty($justificativa)) {
                    $resposta .= '|' . $justificativa;
                } else {
                    $erro = "Forneça uma justificativa para a opção 'Não' na pergunta: " . htmlspecialchars($pergunta['pergunta']);
                    logDebug("Erro validação pergunta $pergunta_id: $erro");
                    break;
                }
            }
        } elseif ($pergunta_id == 4) {
            $resposta_sim_nao = isset($_POST['resposta'][$pergunta_id]['sim_nao']) ? trim($_POST['resposta'][$pergunta_id]['sim_nao']) : '';
            if ($resposta_sim_nao == '') {
                $erro = "Selecione uma opção 'Sim' ou 'Não' para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                logDebug("Erro validação pergunta $pergunta_id: $erro");
                break;
            }
            $resposta = $resposta_sim_nao;
            if ($resposta_sim_nao == 'Sim') {
                if (empty($eixo_selecionado)) {
                    $erro = "Selecione um eixo para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                    logDebug("Erro validação pergunta $pergunta_id: $erro");
                    break;
                }
                $resposta .= '|' . $eixo_selecionado;
                if (!empty($justificativa)) {
                    $resposta .= '|' . $justificativa;
                } else {
                    $erro = "Forneça uma justificativa para o eixo selecionado.";
                    logDebug("Erro validação pergunta $pergunta_id: $erro");
                    break;
                }
            }
        } elseif ($pergunta_id == 5) {
            $resposta_sim_nao = isset($_POST['resposta'][$pergunta_id]) ? trim($_POST['resposta'][$pergunta_id]) : '';
            if ($resposta_sim_nao == '') {
                $erro = "Selecione uma opção para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                logDebug("Erro validação pergunta $pergunta_id: $erro");
                break;
            }
            $resposta = $resposta_sim_nao;
            if ($resposta_sim_nao == 'Não' && !empty($justificativa)) {
                $resposta .= '|' . $justificativa;
            }
        } elseif ($pergunta_id == 7) {
            $resposta_sim_nao = isset($_POST['resposta'][$pergunta_id]['sim_nao']) ? trim($_POST['resposta'][$pergunta_id]['sim_nao']) : '';
            if ($resposta_sim_nao == '') {
                $erro = "Selecione uma opção 'Sim' ou 'Não' para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                logDebug("Erro validação pergunta $pergunta_id: $erro");
                break;
            }
            $resposta = $resposta_sim_nao;
            if ($resposta_sim_nao == 'Não') {
                if (!empty($justificativa)) {
                    $resposta .= '|' . $justificativa;
                } else {
                    $erro = "Forneça uma justificativa para a opção 'Não' na pergunta: " . htmlspecialchars($pergunta['pergunta']);
                    logDebug("Erro validação pergunta $pergunta_id: $erro");
                    break;
                }
            }
        } elseif ($pergunta['tipo_resposta'] == 'multipla' && $pergunta_id != 8) {
            $respostas_form = isset($_POST['resposta'][$pergunta_id]) ? $_POST['resposta'][$pergunta_id] : [];
            $outras_respostas = isset($_POST['outras_respostas'][$pergunta_id]) ? trim($_POST['outras_respostas'][$pergunta_id]) : '';
            
            if (!empty($outras_respostas) && in_array('Outros', $respostas_form)) {
                $respostas_form[array_search('Outros', $respostas_form)] = "Outros: " . $outras_respostas;
            }
            
            $resposta = implode(';', $respostas_form);
            
            if (empty($respostas_form)) {
                $erro = "Selecione pelo menos uma opção para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                logDebug("Erro validação pergunta $pergunta_id: $erro");
                break;
            }
        } elseif ($pergunta['tipo_resposta'] == 'planilha') {
            // Processar os dados da planilha diretamente
            $secretarias = isset($_POST['secretaria'][$pergunta_id]) ? $_POST['secretaria'][$pergunta_id] : [];
            $efetivos = isset($_POST['efetivos'][$pergunta_id]) ? $_POST['efetivos'][$pergunta_id] : [];
            $comissionados = isset($_POST['comissionados'][$pergunta_id]) ? $_POST['comissionados'][$pergunta_id] : [];
            $temporarios = isset($_POST['temporarios'][$pergunta_id]) ? $_POST['temporarios'][$pergunta_id] : [];

            $resposta_planilha = '';
            for ($i = 0; $i < count($secretarias); $i++) {
                $secretaria = trim($secretarias[$i]);
                $efetivo = isset($efetivos[$i]) ? (int)$efetivos[$i] : 0;
                $comissionado = isset($comissionados[$i]) ? (int)$comissionados[$i] : 0;
                $temporario = isset($temporarios[$i]) ? (int)$temporarios[$i] : 0;
                $total_parcial = $efetivo + $comissionado + $temporario;

                if ($secretaria) {
                    $resposta_planilha .= "$secretaria|$efetivo|$comissionado|$temporario|$total_parcial;";
                }
            }
            $resposta_planilha = rtrim($resposta_planilha, ';');
            $resposta = $resposta_planilha;
            logDebug("Pergunta $pergunta_id (planilha) - Resposta gerada: $resposta_planilha");
        } else {
            $resposta = isset($_POST['resposta'][$pergunta_id]) ? trim($_POST['resposta'][$pergunta_id]) : '';
            if ($pergunta['id'] == 8) {
                $outras_respostas = isset($_POST['outras_respostas'][$pergunta_id]) ? trim($_POST['outras_respostas'][$pergunta_id]) : '';
                if ($resposta == 'Outros Motivos') {
                    if (!empty($outras_respostas)) {
                        $resposta = "Outros Motivos: " . $outras_respostas;
                    } else {
                        $erro = "Forneça uma descrição para 'Outros Motivos' na pergunta: " . htmlspecialchars($pergunta['pergunta']);
                        logDebug("Erro validação pergunta $pergunta_id: $erro");
                        break;
                    }
                }
            }
            if ($resposta == '') {
                $erro = "A resposta é obrigatória para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                logDebug("Erro validação pergunta $pergunta_id: $erro");
                break;
            }
        }

        // Verifica se existe resposta salva
        $sql = "SELECT id, resposta FROM pcm2025_resposta WHERE prefeitura_id = ? AND pergunta_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $prefeitura_id, $pergunta_id);
        if (!$stmt->execute()) {
            $erro = "Erro ao consultar resposta no banco de dados.";
            logDebug("Erro consulta pergunta $pergunta_id: " . $stmt->error);
            break;
        }
        $result = $stmt->get_result();
        $resposta_salva = $result->fetch_assoc();
        $resposta_id = $resposta_salva ? $resposta_salva['id'] : '';
        $stmt->close();

        // Inserir ou atualizar resposta
        if (empty($resposta_id)) {
            if ($resposta !== '') { // Só insere se houver resposta
                $sql = "INSERT INTO pcm2025_resposta (prefeitura_id, pergunta_id, resposta, data_cadastro) 
                        VALUES (?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iis", $prefeitura_id, $pergunta_id, $resposta);
                if (!$stmt->execute()) {
                    $erro = "Erro ao inserir resposta no banco de dados.";
                    logDebug("Erro inserção pergunta $pergunta_id: " . $stmt->error);
                    break;
                }
                logDebug("Inserida resposta para pergunta $pergunta_id: $resposta");
                $stmt->close();
            }
        } else {
            if ($resposta === '') { // Se a resposta estiver vazia, remover do banco
                $sql = "DELETE FROM pcm2025_resposta WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $resposta_id);
                if (!$stmt->execute()) {
                    $erro = "Erro ao remover resposta do banco de dados.";
                    logDebug("Erro remoção pergunta $pergunta_id: " . $stmt->error);
                    break;
                }
                logDebug("Resposta removida para pergunta $pergunta_id");
                $stmt->close();
            } else { // Atualizar resposta existente
                $sql = "UPDATE pcm2025_resposta SET resposta = ?, data_alteracao = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $resposta, $resposta_id);
                if (!$stmt->execute()) {
                    $erro = "Erro ao atualizar resposta no banco de dados.";
                    logDebug("Erro atualização pergunta $pergunta_id: " . $stmt->error);
                    break;
                }
                logDebug("Atualizada resposta para pergunta $pergunta_id: $resposta");
                $stmt->close();
            }
        }
    }

    if (!isset($erro)) {
        $sucesso = "Questionário editado com sucesso!";
        logDebug("Salvamento concluído com sucesso");
        // Redirecionar para evitar reenvio do formulário
        header("Location: editar_questionario.php?sucesso=" . urlencode($sucesso));
        exit;
    } else {
        logDebug("Salvamento falhou: $erro");
    }
}
?>
<!DOCTYPE html>
<html lang="ptطبر-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Questionário de Seleção</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="icon" type="image/png" href="capa.png">
    <style>
        body {
            background: linear-gradient(to bottom right, #013220, #013824);
            font-family: Arial, sans-serif;
            min-height: 100vh;
            padding: 20px;
            margin: 0;
            color: #FFD700;
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
            filter: hue-rotate(120deg) saturate(150%) brightness(40%);
        }
        h3, h4 {
            color: #000000 !important;
        }
        .container-lg {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 10px;
            color: #000000;
        }
        .form-check {
            margin-bottom: 10px;
        }
        .eixos-section, .justificativa, .outras-respostas {
            display: none;
            margin-top: 10px;
        }
        .form-select {
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            color: #000000;
            background-color: #FFFFFF;
        }
        .form-select option {
            color: #000000;
        }
        .sim-nao-label {
            color: #000000 !important;
        }
        .table-planilha {
            width: 100%;
            margin-top: 20px;
        }
        .table-planilha th, .table-planilha td {
            text-align: center;
            vertical-align: middle;
            color: #000000;
        }
        .table-planilha input {
            width: 100%;
            padding: 5px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            color: #000000;
            background-color: #FFFFFF;
        }
        .table-planilha .total-parcial, .table-planilha .total-geral {
            background-color: #f8f9fa;
            color: #000000;
        }
        .alert {
            color: #000000;
            background-color: #FFFFFF;
        }
        .error-message {
            color: #dc3545;
            font-size: 0.9em;
            margin-top: 5px;
        }
    </style>
</head>
<body>
<?php include "menu.php"; ?>
<div class="map-background"></div>
<div class="container-lg">
    <h3 class="text-center">Editar Questionário de Seleção</h3>
    <p> </p>
    <h4 class="text-center">Prefeitura de <?php echo htmlspecialchars($rowPref['municipio']); ?></h4>
    <p> </p>
    
    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success text-center"><?php echo htmlspecialchars($_GET['sucesso']); ?></div>
    <?php endif; ?>
    <?php if (isset($erro)): ?>
        <div class="alert alert-danger text-center"><?php echo $erro; ?></div>
    <?php endif; ?>

    <form method="POST" id="editar-questionario-form">
        <?php foreach ($perguntas as $pergunta): ?>
            <h4><?php echo htmlspecialchars($pergunta['pergunta']); ?></h4>
            <?php 
                $pergunta_id = $pergunta['id'];
                $resposta_valor = isset($respostas[$pergunta_id]) ? $respostas[$pergunta_id] : '';
                $justificativa_valor = '';
                $eixo_selecionado = '';
                $resposta_sim_nao = '';
                $outras_respostas_valor = '';
                $planilha_data = [];
                
                logDebug("Processando pergunta $pergunta_id, resposta_valor: " . $resposta_valor);

                if ($pergunta_id == 2 && !empty($resposta_valor)) {
                    $parts = explode('|', $resposta_valor);
                    $resposta_sim_nao = isset($parts[0]) ? trim($parts[0]) : '';
                    $justificativa_valor = isset($parts[1]) ? trim($parts[1]) : '';
                    logDebug("Pergunta 2 - resposta_sim_nao: $resposta_sim_nao, justificativa_valor: $justificativa_valor");
                } elseif ($pergunta_id == 4 && !empty($resposta_valor)) {
                    $parts = explode('|', $resposta_valor);
                    $resposta_sim_nao = isset($parts[0]) ? trim($parts[0]) : '';
                    $eixo_selecionado = isset($parts[1]) ? trim($parts[1]) : '';
                    $justificativa_valor = isset($parts[2]) ? trim($parts[2]) : '';
                    logDebug("Pergunta 4 - resposta_sim_nao: $resposta_sim_nao, eixo_selecionado: $eixo_selecionado, justificativa_valor: $justificativa_valor");
                } elseif ($pergunta_id == 5 && !empty($resposta_valor)) {
                    $parts = explode('|', $resposta_valor);
                    $resposta_sim_nao = isset($parts[0]) ? trim($parts[0]) : '';
                    $justificativa_valor = isset($parts[1]) ? trim($parts[1]) : '';
                    logDebug("Pergunta 5 - resposta_sim_nao: $resposta_sim_nao, justificativa_valor: $justificativa_valor");
                } elseif ($pergunta_id == 7 && !empty($resposta_valor)) {
                    $parts = explode('|', $resposta_valor);
                    $resposta_sim_nao = isset($parts[0]) ? trim($parts[0]) : '';
                    $justificativa_valor = isset($parts[1]) ? trim($parts[1]) : '';
                    logDebug("Pergunta 7 - resposta_sim_nao: $resposta_sim_nao, justificativa_valor: $justificativa_valor");
                } elseif (($pergunta['tipo_resposta'] == 'multipla' || $pergunta['id'] == 8) && !empty($resposta_valor)) {
                    if ($pergunta['id'] == 8) {
                        if (strpos($resposta_valor, 'Outros Motivos: ') === 0) {
                            $outras_respostas_valor = substr($resposta_valor, 15);
                            $resposta_valor = 'Outros Motivos';
                        }
                        logDebug("Pergunta 8 - resposta_valor: $resposta_valor, outras_respostas_valor: $outras_respostas_valor");
                    } else {
                        $respostas_array = explode(';', $resposta_valor);
                        foreach ($respostas_array as $resp) {
                            if (strpos($resp, 'Outros: ') === 0) {
                                $outras_respostas_valor = substr($resp, 7);
                                break;
                            }
                        }
                        logDebug("Pergunta multipla - respostas_array: " . json_encode($respostas_array) . ", outras_respostas_valor: $outras_respostas_valor");
                    }
                } elseif ($pergunta['tipo_resposta'] == 'planilha' && !empty($resposta_valor)) {
                    $rows = explode(';', $resposta_valor);
                    logDebug("Pergunta $pergunta_id (planilha) - linhas brutas: " . json_encode($rows));
                    foreach ($rows as $row) {
                        $data = explode('|', $row);
                        logDebug("Pergunta $pergunta_id (planilha) - dados da linha: " . json_encode($data));
                        $planilha_data[] = [
                            'secretaria' => isset($data[0]) ? trim($data[0]) : '',
                            'efetivos' => isset($data[1]) ? trim($data[1]) : '0',
                            'comissionados' => isset($data[2]) ? trim($data[2]) : '0',
                            'temporarios' => isset($data[3]) ? trim($data[3]) : '0',
                            'total_parcial' => isset($data[4]) ? trim($data[4]) : '0'
                        ];
                    }
                    logDebug("Pergunta $pergunta_id (planilha) - planilha_data: " . json_encode($planilha_data));
                }
            ?>
            <?php if ($pergunta_id == 2): ?>
                <div class="form-check">
                    <input type="radio" name="resposta[<?php echo $pergunta_id; ?>][sim_nao]" value="Sim" class="form-check-input sim-toggle-2" id="resposta-<?php echo $pergunta_id; ?>-Sim" required <?php if ($resposta_sim_nao == 'Sim') echo 'checked'; ?>>
                    <label class="form-check-label sim-nao-label" for="resposta-<?php echo $pergunta_id; ?>-Sim">Sim</label>
                </div>
                <div class="form-check">
                    <input type="radio" name="resposta[<?php echo $pergunta_id; ?>][sim_nao]" value="Não" class="form-check-input sim-toggle-2" id="resposta-<?php echo $pergunta_id; ?>-Não" required <?php if ($resposta_sim_nao == 'Não') echo 'checked'; ?>>
                    <label class="form-check-label sim-nao-label" for="resposta-<?php echo $pergunta_id; ?>-Não">Não</label>
                </div>
                <div class="justificativa" id="justificativa-<?php echo $pergunta_id; ?>" style="display: <?php echo ($resposta_sim_nao == 'Não') ? 'block' : 'none'; ?>;">
                    <label class="sim-nao-label">Justificativa:</label>
                    <textarea name="justificativa[<?php echo $pergunta_id; ?>]" class="form-control" <?php echo ($resposta_sim_nao == 'Não') ? 'required' : ''; ?>><?php echo htmlspecialchars($justificativa_valor); ?></textarea>
                </div>
            <?php elseif ($pergunta_id == 4): ?>
                <div class="form-check">
                    <input type="radio" name="resposta[<?php echo $pergunta_id; ?>][sim_nao]" value="Sim" class="form-check-input sim-toggle-4" id="resposta-<?php echo $pergunta_id; ?>-Sim" required <?php if ($resposta_sim_nao == 'Sim') echo 'checked'; ?>>
                    <label class="form-check-label sim-nao-label" for="resposta-<?php echo $pergunta_id; ?>-Sim">Sim</label>
                </div>
                <div class="form-check">
                    <input type="radio" name="resposta[<?php echo $pergunta_id; ?>][sim_nao]" value="Não" class="form-check-input sim-toggle-4" id="resposta-<?php echo $pergunta_id; ?>-Não" required <?php if ($resposta_sim_nao == 'Não') echo 'checked'; ?>>
                    <label class="form-check-label sim-nao-label" for="resposta-<?php echo $pergunta_id; ?>-Não">Não</label>
                </div>
                <div class="eixos-section" id="eixos-4" style="display: <?php echo ($resposta_sim_nao == 'Sim') ? 'block' : 'none'; ?>;">
                    <label class="sim-nao-label">Selecione o eixo prioritário:</label>
                    <select name="resposta[4][eixo]" class="form-select eixo-select-4" <?php echo ($resposta_sim_nao == 'Sim') ? 'required' : ''; ?>>
                        <option value="">Selecione um eixo</option>
                        <option value="Gestão de Riscos" <?php if ($eixo_selecionado == 'Gestão de Riscos') echo 'selected'; ?>>Gestão de Riscos</option>
                        <option value="Ética" <?php if ($eixo_selecionado == 'Ética') echo 'selected'; ?>>Ética</option>
                        <option value="Transparência/Ouvidoria" <?php if ($eixo_selecionado == 'Transparência/Ouvidoria') echo 'selected'; ?>>Transparência/Ouvidoria</option>
                    </select>
                </div>
                <div class="justificativa" id="justificativa-4" style="display: <?php echo ($resposta_sim_nao == 'Sim' && !empty($eixo_selecionado)) ? 'block' : 'none'; ?>;">
                    <label class="sim-nao-label">Descreva o(s) motivo(s) da prioridade:</label>
                    <textarea name="justificativa[<?php echo $pergunta_id; ?>]" class="form-control" <?php echo ($resposta_sim_nao == 'Sim' && !empty($eixo_selecionado)) ? 'required' : ''; ?>><?php echo htmlspecialchars($justificativa_valor); ?></textarea>
                </div>
            <?php elseif ($pergunta_id == 5): ?>
                <div class="form-check">
                    <input type="radio" name="resposta[<?php echo $pergunta_id; ?>]" value="Sim" class="form-check-input sim-toggle-5" id="resposta-<?php echo $pergunta_id; ?>-Sim" required <?php if ($resposta_sim_nao == 'Sim') echo 'checked'; ?>>
                    <label class="form-check-label sim-nao-label" for="resposta-<?php echo $pergunta_id; ?>-Sim">Sim</label>
                </div>
                <div class="form-check">
                    <input type="radio" name="resposta[<?php echo $pergunta_id; ?>]" value="Não" class="form-check-input sim-toggle-5" id="resposta-<?php echo $pergunta_id; ?>-Não" required <?php if ($resposta_sim_nao == 'Não') echo 'checked'; ?>>
                    <label class="form-check-label sim-nao-label" for="resposta-<?php echo $pergunta_id; ?>-Não">Não</label>
                </div>
                <div class="justificativa" id="justificativa-<?php echo $pergunta_id; ?>" style="display: <?php echo ($resposta_sim_nao == 'Não') ? 'block' : 'none'; ?>;">
                    <label class="sim-nao-label">Justificativa:</label>
                    <textarea name="justificativa[<?php echo $pergunta_id; ?>]" class="form-control" <?php echo ($resposta_sim_nao == 'Não') ? 'required' : ''; ?>><?php echo htmlspecialchars($justificativa_valor); ?></textarea>
                </div>
            <?php elseif ($pergunta_id == 7): ?>
                <div class="form-check">
                    <input type="radio" name="resposta[<?php echo $pergunta_id; ?>][sim_nao]" value="Sim" class="form-check-input sim-toggle-7" id="resposta-<?php echo $pergunta_id; ?>-Sim" required <?php if ($resposta_sim_nao == 'Sim') echo 'checked'; ?>>
                    <label class="form-check-label sim-nao-label" for="resposta-<?php echo $pergunta_id; ?>-Sim">Sim</label>
                </div>
                <div class="form-check">
                    <input type="radio" name="resposta[<?php echo $pergunta_id; ?>][sim_nao]" value="Não" class="form-check-input sim-toggle-7" id="resposta-<?php echo $pergunta_id; ?>-Não" required <?php if ($resposta_sim_nao == 'Não') echo 'checked'; ?>>
                    <label class="form-check-label sim-nao-label" for="resposta-<?php echo $pergunta_id; ?>-Não">Não</label>
                </div>
                <div class="justificativa" id="justificativa-<?php echo $pergunta_id; ?>" style="display: <?php echo ($resposta_sim_nao == 'Não') ? 'block' : 'none'; ?>;">
                    <label class="sim-nao-label">Justificativa:</label>
                    <textarea name="justificativa[<?php echo $pergunta_id; ?>]" class="form-control" <?php echo ($resposta_sim_nao == 'Não') ? 'required' : ''; ?>><?php echo htmlspecialchars($justificativa_valor); ?></textarea>
                </div>
            <?php elseif ($pergunta['tipo_resposta'] == 'sim/nao' && $pergunta_id != 2 && $pergunta_id != 4 && $pergunta_id != 5 && $pergunta_id != 7): ?>
                <div class="form-check">
                    <input type="radio" name="resposta[<?php echo $pergunta_id; ?>]" value="Sim" class="form-check-input" id="resposta-<?php echo $pergunta_id; ?>-Sim" required <?php if ($resposta_valor == 'Sim') echo 'checked'; ?>>
                    <label class="form-check-label sim-nao-label" for="resposta-<?php echo $pergunta_id; ?>-Sim">Sim</label>
                </div>
                <div class="form-check">
                    <input type="radio" name="resposta[<?php echo $pergunta_id; ?>]" value="Não" class="form-check-input" id="resposta-<?php echo $pergunta_id; ?>-Não" required <?php if ($resposta_valor == 'Não') echo 'checked'; ?>>
                    <label class="form-check-label sim-nao-label" for="resposta-<?php echo $pergunta_id; ?>-Não">Não</label>
                </div>
            <?php elseif ($pergunta['tipo_resposta'] == 'texto'): ?>
                <textarea name="resposta[<?php echo $pergunta_id; ?>]" class="form-control" required><?php echo htmlspecialchars($resposta_valor); ?></textarea>
            <?php elseif ($pergunta['tipo_resposta'] == 'opcoes' && $pergunta_id != 8): ?>
                <select name="resposta[<?php echo $pergunta_id; ?>]" class="form-select" required>
                    <option value="">Selecione uma opção</option>
                    <?php foreach (explode(';', $pergunta['opcoes']) as $opcao): ?>
                        <option value="<?php echo htmlspecialchars($opcao); ?>" <?php if ($resposta_valor == $opcao) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($opcao); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($pergunta['tipo_resposta'] == 'multipla' && $pergunta_id != 8): ?>
                <?php 
                    $opcoes = explode(';', $pergunta['opcoes']);
                    $respostas_array = $resposta_valor ? explode(';', $resposta_valor) : [];
                    $respostas_array = array_map(function($resp) {
                        return strpos($resp, 'Outros: ') === 0 ? 'Outros' : $resp;
                    }, $respostas_array);
                ?>
                <?php foreach ($opcoes as $opcao): ?>
                    <div class="form-check">
                        <input type="checkbox" name="resposta[<?php echo $pergunta_id; ?>][]" value="<?php echo htmlspecialchars($opcao); ?>" class="form-check-input outros-toggle" id="opcao-<?php echo $pergunta_id; ?>-<?php echo htmlspecialchars($opcao); ?>" <?php if (in_array($opcao, $respostas_array)) echo 'checked'; ?>>
                        <label class="form-check-label sim-nao-label" for="opcao-<?php echo $pergunta_id; ?>-<?php echo htmlspecialchars($opcao); ?>"><?php echo htmlspecialchars($opcao); ?></label>
                    </div>
                <?php endforeach; ?>
                <div class="form-check">
                    <input type="checkbox" name="resposta[<?php echo $pergunta_id; ?>][]" value="Outros" class="form-check-input outros-toggle" id="outros-<?php echo $pergunta_id; ?>" <?php if (in_array('Outros', $respostas_array)) echo 'checked'; ?>>
                    <label class="form-check-label sim-nao-label" for="outros-<?php echo $pergunta_id; ?>">Outros</label>
                </div>
                <div class="outras-respostas" id="outras-respostas-<?php echo $pergunta_id; ?>" style="display: <?php echo (!empty($outras_respostas_valor)) ? 'block' : 'none'; ?>;">
                    <textarea name="outras_respostas[<?php echo $pergunta_id; ?>]" class="form-control" placeholder="Descreva o(s) motivo(s)."><?php echo htmlspecialchars($outras_respostas_valor); ?></textarea>
                </div>
            <?php elseif ($pergunta['id'] == 8): ?>
                <?php 
                    $opcoes = explode(';', $pergunta['opcoes']);
                    array_push($opcoes, 'Outros Motivos');
                ?>
                <select name="resposta[<?php echo $pergunta_id; ?>]" class="form-select outros-toggle-select" id="select-<?php echo $pergunta_id; ?>" required>
                    <option value="">Selecione uma opção</option>
                    <?php foreach ($opcoes as $opcao): ?>
                        <option value="<?php echo htmlspecialchars($opcao); ?>" <?php if ($resposta_valor == $opcao) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($opcao); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="outras-respostas" id="outras-respostas-<?php echo $pergunta_id; ?>" style="display: <?php echo ($resposta_valor == 'Outros Motivos') ? 'block' : 'none'; ?>;">
                    <textarea name="outras_respostas[<?php echo $pergunta_id; ?>]" class="form-control" placeholder="Descreva o(s) motivo(s)." <?php echo ($resposta_valor == 'Outros Motivos') ? 'required' : ''; ?>><?php echo htmlspecialchars($outras_respostas_valor); ?></textarea>
                </div>
            <?php elseif ($pergunta['tipo_resposta'] == 'planilha'): ?>
                <table class="table table-bordered table-planilha">
                    <thead>
                        <tr>
                            <th></th>
                            <th colspan="3">Quantidade de servidores</th>
                            <th></th>
                            <th></th>
                        </tr>
                        <tr>
                            <th>Nome da Secretaria</th>
                            <th>Efetivos</th>
                            <th>Comissionados</th>
                            <th>Temporários</th>
                            <th>Total Parcial</th>
                            <th>Total Geral</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="planilha-<?php echo $pergunta_id; ?>">
                        <?php if (!empty($planilha_data)): ?>
                            <?php foreach ($planilha_data as $index => $row): ?>
                                <tr class="planilha-row">
                                    <td>
                                        <input 
                                            type="text" 
                                            name="secretaria[<?php echo $pergunta_id; ?>][]" 
                                            class="form-control secretaria-input" 
                                            value="<?php echo htmlspecialchars($row['secretaria']); ?>" 
                                            required
                                        >
                                    </td>
                                    <td><input type="number" name="efetivos[<?php echo $pergunta_id; ?>][]" class="form-control planilha-input" value="<?php echo htmlspecialchars($row['efetivos']); ?>" min="0" required></td>
                                    <td><input type="number" name="comissionados[<?php echo $pergunta_id; ?>][]" class="form-control planilha-input" value="<?php echo htmlspecialchars($row['comissionados']); ?>" min="0" required></td>
                                    <td><input type="number" name="temporarios[<?php echo $pergunta_id; ?>][]" class="form-control planilha-input" value="<?php echo htmlspecialchars($row['temporarios']); ?>" min="0" required></td>
                                    <td><input type="number" class="form-control total-parcial" value="<?php echo htmlspecialchars($row['total_parcial']); ?>" readonly></td>
                                    <?php if ($index === 0): ?>
                                        <td><input type="number" class="form-control total-geral" readonly></td>
                                    <?php else: ?>
                                        <td></td>
                                    <?php endif; ?>
                                    <td><button type="button" class="btn btn-danger btn-sm remove-row">Remover</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="planilha-row">
                                <td>
                                    <input 
                                        type="text" 
                                        name="secretaria[<?php echo $pergunta_id; ?>][]" 
                                        class="form-control secretaria-input" 
                                        required
                                    >
                                </td>
                                <td><input type="number" name="efetivos[<?php echo $pergunta_id; ?>][]" class="form-control planilha-input" min="0" required></td>
                                <td><input type="number" name="comissionados[<?php echo $pergunta_id; ?>][]" class="form-control planilha-input" min="0" required></td>
                                <td><input type="number" name="temporarios[<?php echo $pergunta_id; ?>][]" class="form-control planilha-input" min="0" required></td>
                                <td><input type="number" class="form-control total-parcial" readonly></td>
                                <td><input type="number" class="form-control total-geral" readonly></td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-row">Remover</button></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="button" class="btn btn-success btn-sm mt-2" onclick="adicionarLinha(<?php echo $pergunta_id; ?>)">Adicionar Linha</button>
            <?php else: ?>
                <div class="text-danger">
                    Tipo de resposta não reconhecido: <?php echo htmlspecialchars($pergunta['tipo_resposta']); ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <div class="mt-4 text-center">
            <button type="submit" name="salvar" class="btn" style="background-color: #4CAF50; border-color: #4CAF50; color: white;">Salvar Alterações</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Função para validar entrada, permitindo apenas letras, acentos e espaços
    function validateNoNumbers(input) {
        const initialValue = input.value;
        // Remove números, mantendo letras, acentos e espaços
        input.value = input.value.replace(/[0-9]/g, '');
        // Se o valor mudou (ou seja, havia números), exibe a mensagem de erro
        if (initialValue !== input.value) {
            showErrorMessage(input, 'Digite apenas letras e espaços, sem números.');
        } else {
            removeErrorMessage(input);
        }
    }

    // Função para exibir mensagem de erro
    function showErrorMessage(input, message) {
        removeErrorMessage(input); // Remove mensagens anteriores
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message text-danger';
        errorDiv.textContent = message;
        input.parentNode.appendChild(errorDiv);
        input.classList.add('is-invalid');
    }

    // Função para remover mensagem de erro
    function removeErrorMessage(input) {
        const existingError = input.parentNode.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        input.classList.remove('is-invalid');
    }

    // Validação para os campos de secretaria
    function initializeValidation() {
        document.querySelectorAll('.secretaria-input').forEach(input => {
            input.addEventListener('input', function() {
                validateNoNumbers(this);
            });
        });
    }

    // Inicializar validação para os campos existentes
    initializeValidation();

    // Lógica para controle do fluxo do Item 2
    const simRadios2 = document.querySelectorAll('.sim-toggle-2');
    const justificativaDiv2 = document.getElementById('justificativa-2');
    
    simRadios2.forEach(radio => {
        radio.addEventListener('change', function() {
            if (justificativaDiv2) {
                justificativaDiv2.style.display = (this.value === 'Não') ? 'block' : 'none';
                if (this.value === 'Não') {
                    justificativaDiv2.querySelector('textarea').setAttribute('required', 'required');
                } else {
                    justificativaDiv2.querySelector('textarea').removeAttribute('required');
                }
            }
        });

        if (radio.checked && radio.value === 'Não' && justificativaDiv2) {
            justificativaDiv2.style.display = 'block';
            justificativaDiv2.querySelector('textarea').setAttribute('required', 'required');
        }
    });

    // Lógica para controle do fluxo do Item 4
    const form = document.getElementById('editar-questionario-form');
    const simRadios4 = document.querySelectorAll('.sim-toggle-4');
    const eixosDiv4 = document.getElementById('eixos-4');
    const justificativaDiv4 = document.getElementById('justificativa-4');
    const eixoSelect4 = document.querySelector('.eixo-select-4');

    simRadios4.forEach(radio => {
        radio.addEventListener('change', function() {
            if (eixosDiv4) {
                eixosDiv4.style.display = (this.value === 'Sim') ? 'block' : 'none';
                if (justificativaDiv4) {
                    justificativaDiv4.style.display = 'none';
                    justificativaDiv4.querySelector('textarea').removeAttribute('required');
                }
                if (eixoSelect4) {
                    eixoSelect4.value = '';
                }
            }
        });

        if (radio.checked && radio.value === 'Sim' && eixosDiv4) {
            eixosDiv4.style.display = 'block';
            eixoSelect4.setAttribute('required', 'required');
        }
    });

    if (eixoSelect4) {
        eixoSelect4.addEventListener('change', function() {
            if (justificativaDiv4) {
                justificativaDiv4.style.display = (this.value !== '') ? 'block' : 'none';
                if (this.value !== '') {
                    justificativaDiv4.querySelector('textarea').setAttribute('required', 'required');
                } else {
                    justificativaDiv4.querySelector('textarea').removeAttribute('required');
                }
            }
        });

        if (eixoSelect4.value !== '') {
            justificativaDiv4.style.display = 'block';
            justificativaDiv4.querySelector('textarea').setAttribute('required', 'required');
        }
    }

    form.addEventListener('submit', function(event) {
        const simSelected = document.querySelector('input[name="resposta[4][sim_nao]"]:checked');
        if (simSelected && simSelected.value === 'Sim') {
            if (eixoSelect4.value === '') {
                event.preventDefault();
                alert('Por favor, selecione um eixo antes de continuar.');
            } else if (justificativaDiv4.style.display === 'block' && !justificativaDiv4.querySelector('textarea').value.trim()) {
                event.preventDefault();
                alert('Por favor, forneça uma justificativa para o eixo selecionado.');
            }
        }
    });

    // Lógica para toggle da justificativa na pergunta ID 5
    const simRadios5 = document.querySelectorAll('.sim-toggle-5');
    const justificativaDiv5 = document.getElementById('justificativa-5');

    simRadios5.forEach(radio => {
        radio.addEventListener('change', function() {
            if (justificativaDiv5) {
                justificativaDiv5.style.display = (this.value === 'Não') ? 'block' : 'none';
                if (this.value === 'Não') {
                    justificativaDiv5.querySelector('textarea').setAttribute('required', 'required');
                } else {
                    justificativaDiv5.querySelector('textarea').removeAttribute('required');
                }
            }
        });

        if (radio.checked && radio.value === 'Não' && justificativaDiv5) {
            justificativaDiv5.style.display = 'block';
            justificativaDiv5.querySelector('textarea').setAttribute('required', 'required');
        }
    });

    // Lógica para controle do fluxo do Item 7
    const simRadios7 = document.querySelectorAll('.sim-toggle-7');
    const justificativaDiv7 = document.getElementById('justificativa-7');

    simRadios7.forEach(radio => {
        radio.addEventListener('change', function() {
            if (justificativaDiv7) {
                justificativaDiv7.style.display = (this.value === 'Não') ? 'block' : 'none';
                if (this.value === 'Não') {
                    justificativaDiv7.querySelector('textarea').setAttribute('required', 'required');
                } else {
                    justificativaDiv7.querySelector('textarea').removeAttribute('required');
                }
            }
        });

        if (radio.checked && radio.value === 'Não' && justificativaDiv7) {
            justificativaDiv7.style.display = 'block';
            justificativaDiv7.querySelector('textarea').setAttribute('required', 'required');
        }
    });

    // Lógica para toggle de "Outros" em perguntas múltiplas
    document.querySelectorAll('.outros-toggle').forEach(function(checkbox) {
        const perguntaId = checkbox.id.split('-')[1];
        const outrasRespostasDiv = document.getElementById('outras-respostas-' + perguntaId);

        if (checkbox.value === 'Outros' && checkbox.checked) {
            outrasRespostasDiv.style.display = 'block';
        }

        checkbox.addEventListener('change', function() {
            if (this.value === 'Outros') {
                outrasRespostasDiv.style.display = this.checked ? 'block' : 'none';
            }
        });
    });

    // Lógica para toggle de "Outros Motivos" na pergunta ID 8
    document.querySelectorAll('.outros-toggle-select').forEach(function(select) {
        const perguntaId = select.id.split('-')[1];
        const outrasRespostasDiv = document.getElementById('outras-respostas-' + perguntaId);

        if (select.value === 'Outros Motivos') {
            outrasRespostasDiv.style.display = 'block';
            outrasRespostasDiv.querySelector('textarea').setAttribute('required', 'required');
        }

        select.addEventListener('change', function() {
            outrasRespostasDiv.style.display = (this.value === 'Outros Motivos') ? 'block' : 'none';
            if (this.value === 'Outros Motivos') {
                outrasRespostasDiv.querySelector('textarea').setAttribute('required', 'required');
            } else {
                outrasRespostasDiv.querySelector('textarea').removeAttribute('required');
            }
        });
    });

    // Função para adicionar nova linha na planilha
    window.adicionarLinha = function(perguntaId) {
        const tbody = document.getElementById('planilha-' + perguntaId);
        const newRow = document.createElement('tr');
        newRow.className = 'planilha-row';
        const isFirstRow = tbody.querySelectorAll('.planilha-row').length === 0;
        newRow.innerHTML = `
            <td>
                <input 
                    type="text" 
                    name="secretaria[${perguntaId}][]" 
                    class="form-control secretaria-input" 
                    required
                >
            </td>
            <td><input type="number" name="efetivos[${perguntaId}][]" class="form-control planilha-input" min="0" required></td>
            <td><input type="number" name="comissionados[${perguntaId}][]" class="form-control planilha-input" min="0" required></td>
            <td><input type="number" name="temporarios[${perguntaId}][]" class="form-control planilha-input" min="0" required></td>
            <td><input type="number" class="form-control total-parcial" readonly></td>
            ${isFirstRow ? '<td><input type="number" class="form-control total-geral" readonly></td>' : '<td></td>'}
            <td><button type="button" class="btn btn-danger btn-sm remove-row">Remover</button></td>
        `;
        tbody.appendChild(newRow);
        updateTotalParcial(newRow);
        updateTotalGeral(tbody);

        // Adicionar validação ao novo campo
        const newInput = newRow.querySelector('.secretaria-input');
        newInput.addEventListener('input', function() {
            validateNoNumbers(this);
        });
    }

    // Função para atualizar o total parcial de uma linha
    function updateTotalParcial(row) {
        const inputs = row.querySelectorAll('.planilha-input');
        const totalParcialInput = row.querySelector('.total-parcial');
        let total = 0;

        // Calcula o total inicial
        inputs.forEach(input => {
            total += parseInt(input.value) || 0;
        });
        totalParcialInput.value = total || '';

        // Adiciona eventos de input para atualização dinâmica
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                let newTotal = 0;
                inputs.forEach(inp => {
                    newTotal += parseInt(inp.value) || 0;
                });
                totalParcialInput.value = newTotal || '';
                updateTotalGeral(row.closest('tbody'));
            });
        });

        // Adiciona evento de remoção
        const removeButton = row.querySelector('.remove-row');
        removeButton.addEventListener('click', function() {
            const tbody = row.closest('tbody');
            if (tbody.children.length > 1) {
                const isFirstRow = row === tbody.firstElementChild;
                row.remove();
                if (isFirstRow) {
                    const newFirstRow = tbody.firstElementChild;
                    if (newFirstRow) {
                        const totalGeralCell = newFirstRow.cells[5];
                        totalGeralCell.innerHTML = '<input type="number" class="form-control total-geral" readonly>';
                    }
                }
                updateTotalGeral(tbody);
            } else {
                row.querySelectorAll('input:not(.total-parcial):not(.total-geral)').forEach(input => {
                    input.value = '';
                });
                totalParcialInput.value = '';
                updateTotalGeral(tbody);
            }
        });
    }

    // Função para atualizar o total geral da planilha
    function updateTotalGeral(tbody) {
        const totalParciais = tbody.querySelectorAll('.total-parcial');
        let totalGeral = 0;
        totalParciais.forEach(tp => {
            totalGeral += parseInt(tp.value) || 0;
        });

        const firstRow = tbody.querySelector('.planilha-row');
        if (firstRow) {
            const totalGeralInput = firstRow.querySelector('.total-geral');
            if (totalGeralInput) {
                totalGeralInput.value = totalGeral || '';
            }
        }
    }

    // Inicializar eventos para as linhas existentes de forma otimizada
    document.querySelectorAll('.planilha-row').forEach(row => {
        updateTotalParcial(row);
    });

    // Atualizar totais gerais para todas as planilhas
    document.querySelectorAll('tbody[id^="planilha-"]').forEach(tbody => {
        updateTotalGeral(tbody);
    });
});
</script>

</body>
</html>
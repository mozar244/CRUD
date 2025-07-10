<?php
// Habilitar exibição de erros para depuração (remover em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "conexao.php"; // Inclua o arquivo de conexão com o banco de dados

// Buscar informações da prefeitura logada (se não for administrador)
$rowPref = null;
$prefeitura_id = null;
$is_admin = false;

if (isset($_SESSION['cnpj_prefeitura']) && !empty($_SESSION['cnpj_prefeitura'])) {
    if ($_SESSION['cnpj_prefeitura'] != "11111111111111") {
        // Usuário é uma prefeitura
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
        }
    } else {
        // Usuário é administrador
        $is_admin = true;
    }
} else {
    // Se não houver sessão válida, redirecione para logout
    header("Location: logout.php");
    exit;
}

// Obter a lista de perguntas ordenadas
$result = $conn->query("SELECT * FROM pcm2025_pergunta ORDER BY ordem ASC, id ASC");
$perguntas = $result->fetch_all(MYSQLI_ASSOC);
$total_perguntas = count($perguntas);
$ordens = array_column($perguntas, 'ordem');
$ordens_unicas = array_values(array_unique($ordens));
$total_ordens = count($ordens_unicas);

// Definir a ordem atual com base na sessão
if (!isset($_SESSION['ordem_atual'])) {
    $_SESSION['ordem_atual'] = 1;
}
$ordem_atual = $_SESSION['ordem_atual'];

// Filtrar perguntas com a mesma ordem
$perguntas_atual = array_filter($perguntas, function ($p) use ($ordem_atual) {
    return $p['ordem'] == $ordem_atual;
});

// Consultar CNPJs que responderam (para o administrador)
$responded_cnpjs = [];
if ($is_admin) {
    $sql = "SELECT p.cnpj_prefeitura, m.municipio, COUNT(r.id) as total_respostas
            FROM pcm2025_resposta r
            JOIN pcm2025_prefeitura p ON r.prefeitura_id = p.id
            JOIN pcm2025_municipio m ON p.id_municipio = m.id
            GROUP BY p.cnpj_prefeitura, m.municipio";
    $result = $conn->query($sql);
    $responded_cnpjs = $result->fetch_all(MYSQLI_ASSOC);
}

// Processar formulário (apenas para prefeituras, não para administrador)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_admin) {
    if (isset($prefeitura_id)) {
        foreach ($perguntas_atual as $pergunta) {
            $pergunta_id = $pergunta['id'];
            $resposta = '';
            $justificativa = isset($_POST['justificativa'][$pergunta_id]) ? trim($_POST['justificativa'][$pergunta_id]) : '';
            $eixo_selecionado = isset($_POST['resposta'][$pergunta_id]['eixo']) ? trim($_POST['resposta'][$pergunta_id]['eixo']) : '';
            if ($pergunta_id == 2) {
                $resposta_sim_nao = isset($_POST['resposta'][$pergunta_id]['sim_nao']) ? trim($_POST['resposta'][$pergunta_id]['sim_nao']) : '';
                if ($resposta_sim_nao == '') {
                    $erro = "Selecione uma opção 'Sim' ou 'Não' para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                    break;
                }
                $resposta = $resposta_sim_nao;
                if ($resposta_sim_nao == 'Não') {
                    if (!empty($justificativa)) {
                        $resposta .= '|' . $justificativa;
                    } else {
                        $erro = "Forneça uma justificativa para a opção 'Não' na pergunta: " . htmlspecialchars($pergunta['pergunta']);
                        break;
                    }
                }
            } elseif ($pergunta_id == 4) {
                $resposta_sim_nao = isset($_POST['resposta'][$pergunta_id]['sim_nao']) ? trim($_POST['resposta'][$pergunta_id]['sim_nao']) : '';
                if ($resposta_sim_nao == '') {
                    $erro = "Selecione uma opção 'Sim' ou 'Não' para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                    break;
                }
                $resposta = $resposta_sim_nao;
                if ($resposta_sim_nao == 'Sim') {
                    if (empty($eixo_selecionado)) {
                        $erro = "Selecione um eixo para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                        break;
                    }
                    $resposta .= '|' . $eixo_selecionado;
                    if (!empty($justificativa)) {
                        $resposta .= '|' . $justificativa;
                    } else {
                        $erro = "Forneça uma justificativa para o eixo selecionado.";
                        break;
                    }
                }
            } elseif ($pergunta_id == 5) {
                $resposta_sim_nao = isset($_POST['resposta'][$pergunta_id]) ? trim($_POST['resposta'][$pergunta_id]) : '';
                if ($resposta_sim_nao == '') {
                    $erro = "Selecione uma opção para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
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
                    break;
                }
                $resposta = $resposta_sim_nao;
                if ($resposta_sim_nao == 'Não') {
                    if (!empty($justificativa)) {
                        $resposta .= '|' . $justificativa;
                    } else {
                        $erro = "Forneça uma justificativa para a opção 'Não' na pergunta: " . htmlspecialchars($pergunta['pergunta']);
                        break;
                    }
                }
            } elseif ($pergunta_id == 8) {
                $resposta = isset($_POST['resposta'][$pergunta_id]) ? trim($_POST['resposta'][$pergunta_id]) : '';
                if ($resposta == '') {
                    $erro = "Forneça uma resposta para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                    break;
                }
            } elseif ($pergunta['tipo_resposta'] == 'multipla' && $pergunta_id != 8) {
                $respostas = isset($_POST['resposta'][$pergunta_id]) ? $_POST['resposta'][$pergunta_id] : [];
                $outras_respostas = isset($_POST['outras_respostas'][$pergunta_id]) ? trim($_POST['outras_respostas'][$pergunta_id]) : '';
                
                if (!empty($outras_respostas) && in_array('Outros', $respostas)) {
                    $respostas[array_search('Outros', $respostas)] = "Outros: " . $outras_respostas;
                }
                
                $resposta = implode(';', $respostas);
                
                if (empty($respostas)) {
                    $erro = "Selecione pelo menos uma opção para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                    break;
                }
            } elseif ($pergunta['tipo_resposta'] == 'planilha') {
                $secretarias = isset($_POST['secretaria'][$pergunta_id]) ? $_POST['secretaria'][$pergunta_id] : [];
                $efetivos = isset($_POST['efetivos'][$pergunta_id]) ? $_POST['efetivos'][$pergunta_id] : [];
                $comissionados = isset($_POST['comissionados'][$pergunta_id]) ? $_POST['comissionados'][$pergunta_id] : [];
                $temporarios = isset($_POST['temporarios'][$pergunta_id]) ? $_POST['temporarios'][$pergunta_id] : [];
                
                $resposta = '';
                $has_data = false;
                
                for ($i = 0; $i < count($secretarias); $i++) {
                    if (!empty($secretarias[$i]) && !preg_match('/^[A-Za-zÀ-ÿ\s]+$/', $secretarias[$i])) {
                        $erro = "O nome da secretaria na linha " . ($i + 1) . " contém caracteres inválidos. Use apenas letras e espaços.";
                        break;
                    }
                    if (!empty($secretarias[$i])) {
                        $has_data = true;
                        $efetivos[$i] = !empty($efetivos[$i]) ? (int)$efetivos[$i] : 0;
                        $comissionados[$i] = !empty($comissionados[$i]) ? (int)$comissionados[$i] : 0;
                        $temporarios[$i] = !empty($temporarios[$i]) ? (int)$temporarios[$i] : 0;
                        $total_parcial_de_servidores = $efetivos[$i] + $comissionados[$i] + $temporarios[$i];
                        $resposta .= htmlspecialchars($secretarias[$i]) . '|' . $efetivos[$i] . '|' . $comissionados[$i] . '|' . $temporarios[$i] . '|' . $total_parcial_de_servidores . ';';
                    }
                }
                $resposta = rtrim($resposta, ';');
                
                if (!$has_data) {
                    $erro = "Preencha pelo menos uma secretaria para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                    break;
                }
            } else {
                $resposta = isset($_POST['resposta'][$pergunta_id]) ? trim($_POST['resposta'][$pergunta_id]) : '';
                if ($resposta == '') {
                    $erro = "A resposta é obrigatória para a pergunta: " . htmlspecialchars($pergunta['pergunta']);
                    break;
                }
            }

            // Verifica se existe resposta salva
            $sql = "SELECT id, resposta FROM pcm2025_resposta WHERE prefeitura_id = ? AND pergunta_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $prefeitura_id, $pergunta_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $resposta_salva = $result->fetch_assoc();
            $resposta_id = $resposta_salva ? $resposta_salva['id'] : '';
            $stmt->close();

            // Inserir ou atualizar resposta
            if (empty($resposta_id)) {
                if ($resposta !== '') {
                    $sql = "INSERT INTO pcm2025_resposta (prefeitura_id, pergunta_id, resposta, data_cadastro) 
                            VALUES (?, ?, ?, NOW())";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iis", $prefeitura_id, $pergunta_id, $resposta);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                if ($resposta === '') {
                    $sql = "DELETE FROM pcm2025_resposta WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $resposta_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $sql = "UPDATE pcm2025_resposta SET resposta = ?, data_alteracao = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $resposta, $resposta_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        // Verifica se clicou em avançar ou voltar e grava sessão ordem_atual
        if (!isset($erro)) {
            if (isset($_POST['avancar']) && $ordem_atual < $total_ordens) {
                $_SESSION['ordem_atual']++;
                header("Location: questionario.php");
                exit;
            } elseif (isset($_POST['voltar']) && $ordem_atual > 1) {
                $_SESSION['ordem_atual']--;
                header("Location: questionario.php");
                exit;
            } elseif (isset($_POST['avancar']) && $ordem_atual == $total_ordens) {
                // Finalizar inscrição e exibir mensagem de sucesso
                $_SESSION['ordem_atual'] = 1; // Resetar para o início
                $sucesso = "Inscrição finalizada com sucesso!";
            }
        }
    } else {
        $erro = "Erro: Não foi possível identificar a prefeitura para salvar as respostas.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questionário de Seleção</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="icon" type="image/png" href="capa.png">
    <style>
        body {
            background: linear-gradient(to bottom right, #013220, #013824);
            font-family: Arial, sans-serif;
            min-height: 100vh;
            padding: 20px;
            color: #FFD700;
        }
        h3, h4 {
            color: #FFD700 !important;
        }
        .form-check {
            margin-bottom: 10px;
        }
        .eixos-section, .justificativa {
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
            color: #FFD700 !important;
        }
        .btn-primary {
            background-color: #28a745;
            border-color: #28a745;
            color: #FFFFFF;
        }
        .btn-primary:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: #FFFFFF;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .table-planilha {
            width: 100%;
            margin-top: 20px;
        }
        .table-planilha th, .table-planilha td {
            text-align: center;
            vertical-align: middle;
            color: #FFD700;
        }
        .table-planilha input {
            width: 100%;
            padding: 5px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            color: #000000;
            background-color: #FFFFFF;
        }
        .table-planilha .total-parcial {
            background-color: #f8f9fa;
            color: #000000;
        }
        .table-planilha .total-geral {
            background-color: #e9ecef;
            color: #000000;
            font-weight: bold;
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
<div class="container-lg">
    <h3 class="text-center">Questionário de Seleção</h3>
    <p> </p>
    <?php 
    if ($is_admin) {
        echo "<h4 class=\"text-center\">Administrador</h4>\n";
    } elseif (isset($rowPref) && is_array($rowPref) && !empty($rowPref['municipio'])) {
        echo "<h4 class=\"text-center\">Prefeitura de " . htmlspecialchars($rowPref['municipio']) . "</h4>\n";
    } else {
        echo "<div class=\"text-center text-danger\">Erro: Não foi possível identificar a prefeitura logada.</div>\n";
        exit;
    }
    ?>
    <p> </p>
    
    <?php if (isset($sucesso)): ?>
        <div class="alert alert-success text-center"><?php echo htmlspecialchars($sucesso); ?></div>
    <?php endif; ?>
    <?php if (isset($erro)): ?>
        <div class="alert alert-danger text-center"><?php echo $erro; ?></div>
    <?php endif; ?>

    <?php if ($is_admin): ?>
        <!-- Lista de CNPJs que responderam (para administrador) -->
        <h4 class="text-center">CNPJs que Responderam ao Questionário</h4>
        <?php if (!empty($responded_cnpjs)): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>CNPJ</th>
                        <th>Município</th>
                        <th>Total de Respostas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($responded_cnpjs as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['cnpj_prefeitura']); ?></td>
                            <td><?php echo htmlspecialchars($row['municipio']); ?></td>
                            <td><?php echo htmlspecialchars($row['total_respostas']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="text-center text-warning">
                <p>Nenhum CNPJ respondeu ao questionário ainda.</p>
            </div>
        <?php endif; ?>
    <?php elseif (!isset($sucesso)): ?>
        <!-- Formulário do questionário -->
        <h4>Item <?php echo $ordem_atual . " de " . $total_ordens; ?></h4>
        <p> </p>
        <form method="POST" id="questionario-form">
            <?php foreach ($perguntas_atual as $pergunta): ?>
                <h4><?php echo nl2br($pergunta['pergunta']); ?></h4> 
                <?php 
                    $pergunta_id = $pergunta['id'];
                    $resposta_valor = '';
                    $justificativa_valor = '';
                    $eixo_selecionado = '';
                    $resposta_sim_nao = '';
                    $planilha_data = [];
                    $outras_respostas_valor = '';
                    if (isset($prefeitura_id)) {
                        $sql = "SELECT resposta FROM pcm2025_resposta WHERE prefeitura_id = ? AND pergunta_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ii", $prefeitura_id, $pergunta_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $resposta_salva = $result->fetch_assoc();
                        $resposta_valor = $resposta_salva ? $resposta_salva['resposta'] : '';
                        $stmt->close();

                        if ($pergunta_id == 2 && !empty($resposta_valor)) {
                            $parts = explode('|', $resposta_valor);
                            $resposta_sim_nao = $parts[0];
                            $justificativa_valor = isset($parts[1]) ? $parts[1] : '';
                        } elseif ($pergunta_id == 4 && !empty($resposta_valor)) {
                            $parts = explode('|', $resposta_valor);
                            $resposta_sim_nao = $parts[0];
                            $eixo_selecionado = isset($parts[1]) ? $parts[1] : '';
                            $justificativa_valor = isset($parts[2]) ? $parts[2] : '';
                        } elseif ($pergunta_id == 5 && !empty($resposta_valor)) {
                            $parts = explode('|', $resposta_valor);
                            $resposta_sim_nao = $parts[0];
                            $justificativa_valor = isset($parts[1]) ? $parts[1] : '';
                        } elseif ($pergunta_id == 7 && !empty($resposta_valor)) {
                            $parts = explode('|', $resposta_valor);
                            $resposta_sim_nao = $parts[0];
                            $justificativa_valor = isset($parts[1]) ? $parts[1] : '';
                        } elseif ($pergunta['tipo_resposta'] == 'multipla' && $pergunta_id != 8 && !empty($resposta_valor)) {
                            $respostas_array = explode(';', $resposta_valor);
                            foreach ($respostas_array as $resp) {
                                if (strpos($resp, 'Outros: ') === 0) {
                                    $outras_respostas_valor = substr($resp, 7);
                                    break;
                                }
                            }
                        } elseif ($pergunta['tipo_resposta'] == 'planilha' && !empty($resposta_valor)) {
                            $rows = explode(';', $resposta_valor);
                            foreach ($rows as $row) {
                                $data = explode('|', $row);
                                if (count($data) >= 5) {
                                    $planilha_data[] = [
                                        'secretaria' => $data[0],
                                        'efetivos' => $data[1],
                                        'comissionados' => $data[2],
                                        'temporarios' => $data[3],
                                        'total_parcial' => $data[4]
                                    ];
                                }
                            }
                        }
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
                <?php elseif ($pergunta_id == 8): ?>
                    <textarea name="resposta[<?php echo $pergunta_id; ?>]" class="form-control" required placeholder="Descreva o(s) motivo(s) da sua escolha"><?php echo htmlspecialchars($resposta_valor); ?></textarea>
                <?php elseif ($pergunta['tipo_resposta'] == 'sim/nao' && $pergunta_id != 2 && $pergunta_id != 4 && $pergunta_id != 5 && $pergunta_id != 7): ?>
                    <div class="form-check">
                        <input type="radio" name="resposta[<?php echo $pergunta_id; ?>]" value="Sim" class="form-check-input" id="resposta-<?php echo $pergunta_id; ?>-Sim" required <?php if ($resposta_sim_nao == 'Sim') echo 'checked'; ?>>
                        <label class="form-check-label sim-nao-label" for="resposta-<?php echo $pergunta_id; ?>-Sim">Sim</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" name="resposta[<?php echo $pergunta_id; ?>]" value="Não" class="form-check-input" id="resposta-<?php echo $pergunta_id; ?>-Não" required <?php if ($resposta_sim_nao == 'Não') echo 'checked'; ?>>
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
                            <label class="form-check-label" for="opcao-<?php echo $pergunta_id; ?>-<?php echo htmlspecialchars($opcao); ?>"><?php echo htmlspecialchars($opcao); ?></label>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-check">
                        <input type="checkbox" name="resposta[<?php echo $pergunta_id; ?>][]" value="Outros" class="form-check-input outros-toggle" id="outros-<?php echo $pergunta_id; ?>" <?php if (in_array('Outros', $respostas_array)) echo 'checked'; ?>>
                        <label class="form-check-label" for="outros-<?php echo $pergunta_id; ?>">Outros</label>
                    </div>
                    <div class="outras-respostas" id="outras-respostas-<?php echo $pergunta_id; ?>" style="display: <?php echo (!empty($outras_respostas_valor)) ? 'block' : 'none'; ?>;">
                        <textarea name="outras_respostas[<?php echo $pergunta_id; ?>]" class="form-control" placeholder="Descreva o(s) motivo(s)."><?php echo htmlspecialchars($outras_respostas_valor); ?></textarea>
                    </div>
                <?php elseif ($pergunta['tipo_resposta'] == 'planilha'): ?>
                    <table class="table table-bordered table-planilha">
                        <thead>
                            <tr>
                                <th></th>
                                <th colspan="5">Quantidade de servidores</th>
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
            
            <?php if (isset($erro)) echo "<div class='text-danger mt-3'>$erro</div>"; ?>
            
            <div class="mt-3 text-center">
                <?php if ($ordem_atual > 1): ?>
                    <button type="submit" name="voltar" class="btn btn-secondary">Voltar</button>
                <?php endif; ?>
                <button type="submit" name="avancar" class="btn btn-primary"><?php echo ($ordem_atual < $total_ordens) ? 'Seguir' : 'Finalizar'; ?></button>
            </div>
        </form>
    <?php endif; ?>
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
        errorDiv.style.fontSize = '0.9em';
        errorDiv.style.marginTop = '5px';
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

    // Inicializar validação para os campos de secretaria existentes
    function initializeValidation() {
        document.querySelectorAll('.secretaria-input').forEach(input => {
            input.addEventListener('input', function() {
                validateNoNumbers(this);
            });
        });
    }

    initializeValidation();

    // Inicializar cálculos para todas as linhas da planilha ao carregar a página
    document.querySelectorAll('.planilha-row').forEach(row => {
        updateTotalParcial(row);
    });

    // Atualizar o total geral ao carregar a página
    document.querySelectorAll('tbody[id^="planilha-"]').forEach(tbody => {
        updateTotalGeral(tbody);
    });

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
    const form = document.getElementById('questionario-form');
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
            if (this.value === 'Não') {
                form.submit();
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
});
</script>

</body>
</html>
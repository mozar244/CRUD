<?php
session_start();
include 'conexao.php';
$label_botao = "Seguir com a inscrição";
$link_cancelar = "login.php"; // Mantém como padrão para o botão Cancelar
$row = array();
//PEGA TODOS OS MUNICÍPIOS DE GOIÁS QUE NÃO EXISTE PREFEITURA CADASTRADA
$sql = "SELECT id, municipio 
		FROM pcm2025_municipio 
		WHERE uf='GO' 
		AND id NOT IN (SELECT id_municipio FROM pcm2025_prefeitura) 
		ORDER BY municipio ASC";
$municipios = $conn->query($sql);

// FUNÇÕES
function removeFormatacao($string)
{
	return preg_replace('/\D/', '', $string);
}
function validarCPF($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false; 
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += $cpf[$i] * (($t + 1) - $i);
        }
        $digito = ($soma * 10) % 11;
        if ($digito == 10) $digito = 0;
        if ($cpf[$t] != $digito) {
            return false;
        }
    }
    return true;
}
function validarCNPJ($cnpj)
{
    if (strlen($cnpj) <> 14)
    return 0;
    $soma1 = ($cnpj[0] * 5) +
    ($cnpj[1] * 4) +
    ($cnpj[2] * 3) +
    ($cnpj[3] * 2) +
    ($cnpj[4] * 9) +
    ($cnpj[5] * 8) +
    ($cnpj[6] * 7) +
    ($cnpj[7] * 6) +
    ($cnpj[8] * 5) +
    ($cnpj[9] * 4) +
    ($cnpj[10] * 3) +
    ($cnpj[11] * 2);
    $resto = $soma1 % 11;
    $digito1 = $resto < 2 ? 0 : 11 - $resto;
    $soma2 = ($cnpj[0] * 6) +
    ($cnpj[1] * 5) +
    ($cnpj[2] * 4) +
    ($cnpj[3] * 3) +
    ($cnpj[4] * 2) +
    ($cnpj[5] * 9) +
    ($cnpj[6] * 8) +
    ($cnpj[7] * 7) +
    ($cnpj[8] * 6) +
    ($cnpj[9] * 5) +
    ($cnpj[10] * 4) +
    ($cnpj[11] * 3) +
    ($cnpj[12] * 2);
    $resto = $soma2 % 11;
    $digito2 = $resto < 2 ? 0 : 11 - $resto;
    return (($cnpj[12] == $digito1) && ($cnpj[13] == $digito2));
}

// RECEBE DADOS DO FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] == 'POST') 
{
	$row = $_POST;
    $id_municipio = $_POST['id_municipio'];
	// RETIRA CARACTERES DE FORMATAÇÃO DO CNPJ
    $cnpj_prefeitura = removeFormatacao($_POST['cnpj_prefeitura']);
    
    $fone_prefeitura = $_POST['fone_prefeitura'];
    $endereco_prefeitura = $_POST['endereco_prefeitura'];
    $cep_prefeitura = $_POST['cep_prefeitura'];
    $nome_prefeito = $_POST['nome_prefeito'];
	// RETIRA CARACTERES DE FORMATAÇÃO DO CPF
    $cpf_prefeito = removeFormatacao($_POST['cpf_prefeito']);
    $nome_responsavel_pela_inscricao = $_POST['nome_responsavel_pela_inscricao'];
	// RETIRA CARACTERES DE FORMATAÇÃO DO CPF
    $cpf_responsavel_pela_inscricao = removeFormatacao($_POST['cpf_responsavel_pela_inscricao']);
    $cargo_responsavel_pela_inscricao = $_POST['cargo_responsavel_pela_inscricao'];
    $fone_responsavel_pela_inscricao = $_POST['fone_responsavel_pela_inscricao'];
    // CRIPTOGRAFA SENHA COM MD5
    $senha = md5($_POST['senha']);
	$confirma_senha = md5($_POST['confirma_senha']);
	// FAZ VALIDAÇÕES
	$erro = "N";
	
	if(!validarCNPJ($cnpj_prefeitura))
	{
		$erro = "S";
		echo "<div class='text-center alert alert-danger'>Erro: CNPJ da Prefeitura não é válido.</div>";
	}
	
	if(!validarCPF($cpf_prefeito))
	{
		$erro = "S";
		echo "<div class='text-center alert alert-danger'>Erro: CPF do prefeito não é válido.</div>";
	}
	if(!validarCPF($cpf_responsavel_pela_inscricao))
	{
		$erro = "S";
		echo "<div class='text-center alert alert-danger'>Erro: CPF do responsável não é válido.</div>";
	}
	if(strlen($_POST['senha'])<5)
	{
		$erro = "S";
		echo "<div class='text-center alert alert-danger'>Erro: Senha deve conter pelo menos 5 caracteres.</div>";
	}
	if($senha != $confirma_senha)
	{
		$erro = "S";
		echo "<div class='text-center alert alert-danger'>Erro: Senha e Confirma senha não são iguais.</div>";
	}
	if(isset($cnpj_editar) && $cnpj_editar != $cnpj_prefeitura)
	{
		$erro = "S";
		echo "<div class='text-center alert alert-danger'>Erro: CNPJ não confere.</div>";
	}
	
	// SE PASSAR POR VALIDAÇÕES INSERE NOVO REGISTRO OU ALTERA REGISTRO
	if($erro == "N")
	{
		// VERIFICA SE ESTÁ LOGADO E SE É ALTERAÇÃO DE DADOS
		if (isset($_SESSION['cnpj_prefeitura']) && !empty($_SESSION['cnpj_prefeitura']) && isset($_POST['cnpj_editar']) && !empty($_POST['cnpj_editar']))
		{
			// RETIRA CARACTERES DE FORMATAÇÃO DO CNPJ
			$cnpj_editar = removeFormatacao($_POST['cnpj_editar']);
			// QUERY PARA ATUALIZAR REGISTRO EXISTENTE
			$sql = "UPDATE pcm2025_prefeitura 
					SET 
						id_municipio = '$id_municipio', 
						fone_prefeitura = '$fone_prefeitura', 
						endereco_prefeitura = '$endereco_prefeitura', 
						cep_prefeitura = '$cep_prefeitura', 
						nome_prefeito = '$nome_prefeito', 
						cpf_prefeito = '$cpf_prefeito', 
						nome_responsavel_pela_inscricao = '$nome_responsavel_pela_inscricao', 
						cpf_responsavel_pela_inscricao = '$cpf_responsavel_pela_inscricao', 
						cargo_responsavel_pela_inscricao = '$cargo_responsavel_pela_inscricao', 
						fone_responsavel_pela_inscricao = '$fone_responsavel_pela_inscricao', 
						senha = '$senha', 
						data_alteracao = NOW() 
					WHERE cnpj_prefeitura = '$cnpj_editar'";
		} 
		else // SE NÃO FOR ALTERAÇÃO (NOVO CADASTRO)
		{
			// VERIFICA SE JÁ EXISTE CNPJ CADASTRADO
			$sqlVerifica = "SELECT id FROM pcm2025_prefeitura WHERE cnpj_prefeitura = '$cnpj_prefeitura' OR id_municipio = $id_municipio";
			$exeVerifica = mysqli_query($conn, $sqlVerifica);
			$numVerifica = mysqli_num_rows($exeVerifica);
			if($numVerifica > 0)
			{
				$erro = "S";
				echo "<div class='text-center alert alert-danger'>Erro, já existe município ou CNPJ $cnpj_prefeitura cadastrado</div>";
			}
			else
			{
				// QUERY PARA INSERIR NOVO REGISTRO
				$sql = "INSERT INTO pcm2025_prefeitura (id_municipio, cnpj_prefeitura, fone_prefeitura, endereco_prefeitura, cep_prefeitura, nome_prefeito, cpf_prefeito, nome_responsavel_pela_inscricao, cpf_responsavel_pela_inscricao, cargo_responsavel_pela_inscricao, fone_responsavel_pela_inscricao, senha, data_cadastro) 
						VALUES ('$id_municipio', '$cnpj_prefeitura', '$fone_prefeitura', '$endereco_prefeitura', '$cep_prefeitura', '$nome_prefeito', '$cpf_prefeito', '$nome_responsavel_pela_inscricao', '$cpf_responsavel_pela_inscricao', '$cargo_responsavel_pela_inscricao', '$fone_responsavel_pela_inscricao', '$senha', NOW())";
				$cnpj_editar = $cnpj_prefeitura;
			}
		}
		// SE NÃO EXISTIR ERRO, EXECUTA QUERY NO BANCO DE DADOS
		if($erro == "N")
		{
			if (mysqli_query($conn, $sql))
			{
				// Armazena o CNPJ na sessão para manter o usuário logado
				$_SESSION['cnpj_prefeitura'] = $cnpj_prefeitura;
				// Redireciona para questionario.php após o sucesso
				header("Location: questionario.php");
				exit(); // Encerra a execução após o redirecionamento
			}
			else
			{
				echo "<div class='text-center alert alert-danger'>Erro ao tentar salvar dados do CNPJ $cnpj_prefeitura</div>";
			}
		}
	}
}
// VERIFICA SE ESTÁ LOGADO E SE EXISTE GET OU POST
if ((isset($_SESSION['cnpj_prefeitura']) && !empty($_SESSION['cnpj_prefeitura'])) || ((isset($_GET['cnpj']) && !empty($_GET['cnpj'])) || (isset($_POST['cnpj_editar']) && !empty($_POST['cnpj_editar']))))
{
	if(isset($_POST['cnpj_editar']) && !empty($_POST['cnpj_editar']))
	{
		$cnpj_editar = removeFormatacao($_POST['cnpj_editar']);
	}
	else if(isset($_GET['cnpj']) && !empty($_GET['cnpj']))
	{
		$cnpj_editar = $_GET['cnpj'];
	}
	else if(isset($_SESSION['cnpj_prefeitura']) && !empty($_SESSION['cnpj_prefeitura']) && $_SESSION['cnpj_prefeitura'] != "11111111111111")
	{
		$cnpj_editar = $_SESSION['cnpj_prefeitura'];
	}
	if(isset($cnpj_editar))
	{
		$label_botao = "Alterar";
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
				WHERE p.cnpj_prefeitura='$cnpj_editar'";
		$result = $conn->query($sql);
		$row = $result->fetch_assoc();
	}
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questionário de Identificação</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="icon" type="image/png" href="capa.png">
    <style>
/* Existing styles */
body {
    background: linear-gradient(to bottom right, #013220, #013824);
    margin: 0;
    padding: 0;
    min-height: 100vh;
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
.title-yellow {
    color: black;
}
.container-lg {
    background-color: rgba(255, 255, 255, 0.9);
    padding: 20px;
    border-radius: 10px;
}

/* Add custom button styles */
.btn-custom-primary, .btn-custom-secondary {
    background-color: #4CAF50 !important; /* Set the background color to #04d167 */
    border-color: #4CAF50!important; /* Set the border color to match */
    color: white !important; /* Ensure the text color is white for contrast */
}

.btn-custom-primary:hover, .btn-custom-secondary:hover {
    background-color: #4CAF50 !important; /* Slightly darker shade for hover effect */
    border-color: #4CAF50 !important;
}
</style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        $(document).ready(function(){
            $('input[name="cnpj_prefeitura"]').mask('00.000.000/0000-00');
            $('input[name="cpf_prefeito"], input[name="cpf_responsavel_pela_inscricao"]').mask('000.000.000-00');
            $('input[name="cep_prefeitura"]').mask('00000-000');
            $('input[name="fone_prefeitura"], input[name="fone_responsavel_pela_inscricao"]').mask('(00) 00000-0000');
        });
    </script>
    <script>
        function validarSenha() {
            var senha = document.getElementById("senha").value;
            var confirmaSenha = document.getElementById("confirma_senha").value;
            var qtdeDigitos = senha.length;
            if (qtdeDigitos < 5) {
                alert("Senha deve conter pelo menos 5 caracteres");
                return false;
            }
            if (senha !== confirmaSenha) {
                alert("Senha e Confirma senha não coincidem!");
                return false;
            }
            return true; 
        }
    </script>
</head>
<body>
<div class="header-container">
        <?php
        if (isset($_SESSION['cnpj_prefeitura']) && !empty($_SESSION['cnpj_prefeitura'])) {
            $link_cancelar = "index.php";
            include "menu.php";
        } else {
        ?>
            <h3 class="text-center">PCM - Programa de Compliance Público Municipal</h3>
            <p class="text-center"> </p>
        <?php
        }
        ?>
    </div>
    <div class="map-background"></div>
    <div class="container-lg">
        <h3 class="text-center title-yellow">Questionário de Identificação</h3>
        <div class="form-container">
            <form method="POST" onsubmit="return validarSenha()">
                <label>CNPJ:</label>
                <input type="text" name="cnpj_prefeitura" class="form-control" value="<?php echo @$row['cnpj_prefeitura'] ?>" required>
                <label>Município:</label>
                <select name="id_municipio" class="form-control" required>
                    <?php
                    // VERIFICA SE ESTÁ LOGADO E MOSTRA APENAS O MUNICÍPIO DE QUEM ESTÁ LOGADO PARA EDITAR
                    if(isset($_SESSION['cnpj_prefeitura']) && !empty($_SESSION['cnpj_prefeitura']) && $_SESSION['cnpj_prefeitura'] != "11111111111111")
                    {
                        echo "<option value='{$row['id_municipio']}'>{$row['municipio']}</option>";
                    }
                    else
                    {
                        // SE NÃO ESTIVER LOGADO MOSTRA TODOS OS MUNICÍPIOS, EXCETO OS JÁ CADASTRADOS
                        while ($m = $municipios->fetch_assoc()):
                            echo "<option value='{$m['id']}'>{$m['municipio']}</option>";
                        endwhile;
                    }
                    ?>
                </select>
            
               <label>Telefone:</label>
                <input type="text" name="fone_prefeitura" class="form-control" value="<?php echo @$row['fone_prefeitura'] ?>">
                <label>Endereço da prefeitura:</label>
                <input type="text" name="endereco_prefeitura" class="form-control" value="<?php echo @$row['endereco_prefeitura'] ?>" required>
                <label>CEP:</label>
                <input type="text" name="cep_prefeitura" class="form-control" value="<?php echo @$row['cep_prefeitura'] ?>">
                <label>Nome do Prefeito:</label>
                <input type="text" name="nome_prefeito" class="form-control" value="<?php echo @$row['nome_prefeito'] ?>" required>
                <label>CPF do Prefeito:</label>
                <input type="text" name="cpf_prefeito" class="form-control" value="<?php echo @$row['cpf_prefeito'] ?>" required>
                <label>Nome do Responsável pela inscrição:</label>
                <input type="text" name="nome_responsavel_pela_inscricao" class="form-control" value="<?php echo @$row['nome_responsavel_pela_inscricao'] ?>" required>
                <label>CPF do Responsável pela inscrição:</label>
                <input type="text" name="cpf_responsavel_pela_inscricao" class="form-control" value="<?php echo @$row['cpf_responsavel_pela_inscricao'] ?>" required>
                <label>Cargo do Responsável pela inscrição:</label>
                <input type="text" name="cargo_responsavel_pela_inscricao" class="form-control" value="<?php echo @$row['cargo_responsavel_pela_inscricao'] ?>" required>
                <label>Telefone para contato via WhatsApp:</label>
                <input type="text" name="fone_responsavel_pela_inscricao" class="form-control" value="<?php echo @$row['fone_responsavel_pela_inscricao'] ?>" required>
               <label>Senha: (mínimo 5 caracteres)</label>
                <input type="password" id="senha" name="senha" class="form-control" value="<?php echo @$row['senha'] ?>" required>
                <label>Confirmar Senha:</label>
                <input type="password" id="confirma_senha" name="confirma_senha" class="form-control" required>
                <?php
                if(isset($_SESSION['cnpj_prefeitura']) && !empty($_SESSION['cnpj_prefeitura']) && $_SESSION['cnpj_prefeitura'] != "11111111111111")
                {
                    echo "<input type=\"hidden\" name=\"cnpj_editar\" value=\"".$_SESSION['cnpj_prefeitura']."\">";
                }
                else if(isset($_GET['cnpj']) && !empty($_GET['cnpj']))
                {
                    echo "<input type=\"hidden\" name=\"cnpj_editar\" value=\"".$_GET['cnpj']."\">";
                }
                ?>
                <button type="submit" class="btn btn-custom-primary w-100 mt-3"><?php echo $label_botao;?></button>
                <a href="<?php echo $link_cancelar;?>" class="btn btn-custom-secondary w-100 mt-2">Cancelar</a>
            </form>
        </div>
        <p> </p>
    </div>
</body>
</html>
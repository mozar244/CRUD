<?php
session_start();
include "conexao.php";
if (!isset($_SESSION['cnpj_prefeitura']) || empty($_SESSION['cnpj_prefeitura'])) 
{
    header('Location: logout.php');
    exit;
}
$row = array();
if(isset($_SESSION['cnpj_prefeitura']) && !empty($_SESSION['cnpj_prefeitura']) && $_SESSION['cnpj_prefeitura']!="11111111111111")
{
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
			WHERE p.cnpj_prefeitura='".$_SESSION['cnpj_prefeitura']."'";
	//echo $sql; exit();
	//print_r($_SESSION['cnpj_prefeitura']);
	//$result = mysqli_query($sql);

	$result = $conn->query($sql);
	$row = $result->fetch_assoc();
	$prefeitura_id = $row['id'];	
}
?>
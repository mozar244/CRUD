<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 1);
date_default_timezone_set("America/Sao_Paulo");
setlocale(LC_ALL, 'pt_BR');
ini_set('default_charset','utf-8');

$conIp = "";
$conUsu = "";
$conSenha = "";
$conBd = "";

if($_SERVER['DOCUMENT_ROOT'] == '/var/www/html/apphomolog.controladoria.go.gov.br'){
    $conIp = "mysqlhom01.intra.goias.gov.br";
    $conUsu = "user_controla";
    $conSenha = "VEUFwSpVmh778gUVWhae";
    $conBd = "app_controladoria";
}elseif($_SERVER['DOCUMENT_ROOT'] == '/var/www/html/app.controladoria.go.gov.br'){
    $conIp = "mysqlprod13.intra.goias.gov.br";
    $conUsu = "user_controladoria";
    $conSenha = "aZeLpMNUzm7Bvb";
    $conBd = "app_controladoria";
}

$conn = @mysqli_connect ($conIp, $conUsu, $conSenha, $conBd) or die('Nao foi possivel conectar ao Banco de Dados porque: ' . mysqli_connect_error());
$conn->set_charset("utf8");

?>


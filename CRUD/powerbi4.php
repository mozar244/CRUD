<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Acessando o PowerBI via Serviço</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="powerbi.js"></script>
</head>
<body>
    <style>
        .displayed {
            display: block;
            margin-left: auto;
            margin-right: auto 
        }
    </style>
    <?php
    session_start();
    include "inc_valida_secao.php"; // Valida a sessão
    include "conexão.php"; // Conexão ao banco (fornece $conn)

    // Validar o CNPJ na tabela pcm2025_prefeitura
    $cnpj_raw = $_SESSION['cnpj_prefeitura'] ?? null;
    if (!$cnpj_raw) {
        echo "<p>Erro: CNPJ não encontrado na sessão.</p>";
        exit;
    }

    // Consultar a tabela pcm2025_prefeitura
    $stmt = mysqli_prepare($conn, "SELECT cnpj_prefeitura FROM pcm2025_prefeitura WHERE cnpj_prefeitura = ?");
    if (!$stmt) {
        echo "<p>Erro ao preparar a consulta: " . mysqli_error($conn) . "</p>";
        exit;
    }

    mysqli_stmt_bind_param($stmt, "s", $cnpj_raw);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        echo "<p>Erro: CNPJ inválido ou não encontrado na tabela pcm2025_prefeitura.</p>";
        exit;
    }

    // Formatar o CNPJ (remover pontuação e zeros à esquerda)
    $cnpj = preg_replace('/[^0-9]/', '', $row['cnpj_prefeitura']);
    $cnpj = ltrim($cnpj, '0');
    error_log("CNPJ formatado: $cnpj"); // Log para depuração

    // Obter token do Azure AD
    $curlPostToken = curl_init();
    curl_setopt_array($curlPostToken, array(
        CURLOPT_URL => "https://login.windows.net/common/oauth2/token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => array(
            'grant_type' => 'password',
            'scope' => 'openid',
            'resource' => 'https://analysis.windows.net/powerbi/api',
            'client_id' => 'fcb298f4-c7f1-40c5-8e07-77f9881c7db6',
            'client_secret' => 'ZY.8Q~jesqGhsgVNeZqlUM0rwFt1raB6Li6YlcS~',
            'username' => 'cge.gerencial@sistemas.goias.gov.br',
            'password' => 'yKs53ENduTRsSgi2TBxOn'
        )
    ));

    $tokenResponse = curl_exec($curlPostToken);
    $tokenError = curl_error($curlPostToken);
    curl_close($curlPostToken);

    $tokenResult = json_decode($tokenResponse, true);
    if (!isset($tokenResult["access_token"])) {
        echo "<pre>Erro ao obter token: " . print_r($tokenResponse, true) . "</pre>";
        exit;
    }

    $token = $tokenResult["access_token"];
    $group = '0784b815-647b-4180-a3d5-189344d68c48';
    $report = 'cc3a99ef-cf7d-4681-85e6-d5bdb26f7c43';
    $embedUrl = "https://app.powerbi.com/reportEmbed?reportId=$report&groupId=$group";
    ?>

    <h2>Acessando o PowerBI via Serviço</h2>
    <div id="reportContainer" style="height: 780px;"></div>

    <script>
    function embedPowerBIReport() {
        console.log("Iniciando incorporação");
        console.log("CNPJ usado no filtro: <?php echo $cnpj; ?>");

        var IamAFilter = {
            $schema: "http://powerbi.com/product/schema#basic",
            target: {
                table: "Dim_Municípios", // Corrigido para a tabela correta
                column: "cnpj" // Coluna confirmada na imagem
            },
            operator: "Equals",
            values: ["<?php echo $cnpj; ?>"]
        };
        var models = window['powerbi-client'].models;
        var embedConfiguration = {
            type: 'report',
            id: '<?php echo $report; ?>',
            embedUrl: "<?php echo $embedUrl ?>",
            accessToken: "<?php echo $token; ?>",
            filters: [IamAFilter],
            settings: {
                filterPaneEnabled: true,
                panes: {
                    filters: { visible: true }, // Habilitado para teste
                    pageNavigation: { visible: false }
                }
            }
        };

        var $reportContainer = $('#reportContainer');
        $reportContainer.hide();

        var report = powerbi.embed($reportContainer.get(0), embedConfiguration);

        report.on("loaded", function () {
            console.log("Relatório carregado");
        });

        report.on("error", function (event) {
            console.error("Erro: ", event.detail);
        });

        report.on("rendered", function () {
            console.log("Relatório renderizado");
            $reportContainer.show();
        });
    }

    embedPowerBIReport();
    </script>
</body>
</html>
<?php
include 'conexao.php'; // Arquivo para conexão com o banco

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

$sql = "SELECT municipio FROM pcm2025_municipio WHERE municipio LIKE ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}
$searchTerm = "%$term%";
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$municipios = [];
while ($row = $result->fetch_assoc()) {
    $municipios[] = $row['municipio'];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($municipios);
?>
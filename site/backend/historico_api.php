<?php
// site/backend/historico_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

// Adicionado para ajudar a depurar em caso de erros futuros
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Método não permitido']);
    exit;
}

$periodo = $_GET['periodo'] ?? '6meses';

// CORREÇÃO: A query agora busca 'pessoas_faladas_total' e 'dados_quadras'.
$sql = "
    SELECT 
        h.data_entrega, 
        h.data_devolucao, 
        h.pessoas_faladas_total, 
        h.dados_quadras,
        m.identificador as mapa_identificador, 
        u.nome as dirigente_nome
    FROM historico_mapas h
    JOIN mapas m ON h.mapa_id = m.id
    JOIN users u ON h.dirigente_id = u.id
";

if ($periodo === '6meses') {
    $sql .= " WHERE h.data_devolucao >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
} elseif ($periodo !== 'completo') {
    http_response_code(400);
    echo json_encode(['message' => 'Período de filtro inválido.']);
    exit;
}

$sql .= " ORDER BY h.data_devolucao DESC, m.identificador ASC";

try {
    $stmt = $pdo->query($sql);
    $resultado = $stmt->fetchAll();
    echo json_encode($resultado);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Erro interno do servidor ao consultar o histórico: ' . $e->getMessage()]);
}
?>
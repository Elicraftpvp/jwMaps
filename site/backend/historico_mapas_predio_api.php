<?php
// site/backend/historico_mapas_predio_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Método não permitido']);
    exit;
}

$periodo = $_GET['periodo'] ?? '6meses';

$sql = "
    SELECT 
        h.data_entrega, 
        h.data_devolucao, 
        h.pessoas_faladas_total, 
        h.dados_blocos,
        m.identificador as mapa_identificador, 
        u.nome as dirigente_nome,
        g.nome as grupo_nome
    FROM historico_mapas_predio h
    JOIN mapas_predio m ON h.mapa_id = m.id
    LEFT JOIN users u ON h.dirigente_id = u.id
    LEFT JOIN grupos g ON h.grupo_id = g.id
    WHERE 1=1
";

// Aplicar filtro de período
if ($periodo === '6meses') {
    $sql .= " AND h.data_devolucao >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
} elseif ($periodo !== 'completo') {
    http_response_code(400);
    echo json_encode(['message' => 'Período de filtro inválido.']);
    exit;
}

$sql .= " ORDER BY h.data_devolucao DESC, h.id DESC";

try {
    $stmt = $pdo->query($sql);
    $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($resultado);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Erro interno do servidor ao consultar o histórico de prédios: ' . $e->getMessage()]);
}
?>

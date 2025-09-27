<?php
// site/backend/mapas_em_uso_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

// Definir o fuso horário para garantir consistência na data do servidor
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Método não permitido']);
    exit;
}

try {
    // Parâmetros de paginação
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;

    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 5;

    $offset = ($page - 1) * $limit;

    // Contar o total de registros
    $total_stmt = $pdo->query("SELECT COUNT(m.id) FROM mapas m WHERE m.dirigente_id IS NOT NULL");
    $total_results = $total_stmt->fetchColumn();
    $total_pages = ceil($total_results / $limit);

    // Buscar os dados paginados
    $stmt = $pdo->prepare("
        SELECT m.identificador, m.data_entrega, u.nome as dirigente_nome
        FROM mapas m
        JOIN users u ON m.dirigente_id = u.id
        WHERE m.dirigente_id IS NOT NULL
        ORDER BY u.nome ASC, m.data_entrega ASC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $mapas_em_uso = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- INÍCIO DA MODIFICAÇÃO ---
    // Adicionar a data atual do servidor na resposta
    $current_server_date = date('Y-m-d');
    // --- FIM DA MODIFICAÇÃO ---

    echo json_encode([
        'serverDate' => $current_server_date, // Novo campo adicionado
        'data' => $mapas_em_uso,
        'page' => $page,
        'totalPages' => $total_pages,
        'totalResults' => (int) $total_results
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Erro ao buscar mapas em uso: ' . $e->getMessage()]);
}
?>
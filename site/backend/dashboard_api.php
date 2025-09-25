<?php
// site/backend/dashboard_api.php
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

try {
    // Estatísticas (estas queries não foram afetadas pela mudança)
    $stmt_disponiveis = $pdo->query("SELECT COUNT(id) as total FROM mapas WHERE dirigente_id IS NULL");
    $disponiveis = $stmt_disponiveis->fetchColumn();

    $stmt_em_uso = $pdo->query("SELECT COUNT(id) as total FROM mapas WHERE dirigente_id IS NOT NULL");
    $em_uso = $stmt_em_uso->fetchColumn();

    $stmt_dirigentes = $pdo->query("SELECT COUNT(id) as total FROM users WHERE cargo = 'dirigente' AND status = 'ativo'");
    $dirigentes = $stmt_dirigentes->fetchColumn();

    // Lista de mapas entregues recentemente (não foi afetada)
    $stmt_recentes = $pdo->query("
        SELECT m.identificador, m.data_entrega, u.nome as dirigente_nome
        FROM mapas m
        JOIN users u ON m.dirigente_id = u.id
        ORDER BY m.data_entrega DESC
        LIMIT 5
    ");
    $recentes = $stmt_recentes->fetchAll();

    // Histórico de mapas devolvidos recentemente (AQUI ESTAVA O ERRO)
    // CORREÇÃO: Trocado 'h.pessoas_faladas' por 'h.pessoas_faladas_total'
    $stmt_historico = $pdo->query("
        SELECT h.data_devolucao, h.pessoas_faladas_total, m.identificador as mapa_identificador, u.nome as dirigente_nome
        FROM historico_mapas h
        JOIN mapas m ON h.mapa_id = m.id
        JOIN users u ON h.dirigente_id = u.id
        ORDER BY h.data_devolucao DESC
        LIMIT 5
    ");
    $historico = $stmt_historico->fetchAll();

    echo json_encode([
        'stats' => [
            'disponiveis' => (int) $disponiveis,
            'em_uso' => (int) $em_uso,
            'dirigentes' => (int) $dirigentes
        ],
        'recentes' => $recentes,
        'historico' => $historico
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    // Retorna o erro real para facilitar a depuração
    echo json_encode(['message' => 'Erro ao buscar dados do dashboard: ' . $e->getMessage()]);
}
?>
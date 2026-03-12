<?php
// site/backend/dashboard_api.php
session_start();

header('Content-Type: application/json');
require_once 'conexao.php';

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Acesso não autorizado. Por favor, faça login.']);
    exit;
}

// 2. DEFINIÇÃO DAS PERMISSÕES
define('PERM_DIRIGENTE', 1);
define('PERM_ADMIN', 2);
define('PERM_CARRINHO', 4);
define('PERM_PUBLICADOR', 8);
define('PERM_CAMPANHA', 16);

// Pega os dados do usuário da sessão
$user_id = $_SESSION['user_id'];
$user_permissoes = (int) $_SESSION['permissoes'];

try {
    // 3. ESTATÍSTICAS GLOBAIS (Visíveis para todos)
    // Mapas disponíveis são os que não têm dirigente NEM grupo
    $stmt_disponiveis = $pdo->query("SELECT COUNT(id) as total FROM mapas WHERE dirigente_id IS NULL AND grupo_id IS NULL");
    $disponiveis = $stmt_disponiveis->fetchColumn();

    // Mapas em uso são os que TÊM dirigente OU grupo
    $stmt_em_uso = $pdo->query("SELECT COUNT(id) as total FROM mapas WHERE dirigente_id IS NOT NULL OR grupo_id IS NOT NULL");
    $em_uso = $stmt_em_uso->fetchColumn();

    $stmt_dirigentes = $pdo->query("SELECT COUNT(id) as total FROM users WHERE (permissoes & " . PERM_DIRIGENTE . ") = " . PERM_DIRIGENTE . " AND status = 'ativo'");
    $dirigentes = $stmt_dirigentes->fetchColumn();
    
    // --- DADOS PARA A VISÃO DE ADMIN ---
    $stmt_recentes = $pdo->query("
        SELECT m.identificador, m.data_entrega, u.nome as dirigente_nome, g.nome as grupo_nome
        FROM mapas m 
        LEFT JOIN users u ON m.dirigente_id = u.id
        LEFT JOIN grupos g ON m.grupo_id = g.id
        WHERE m.dirigente_id IS NOT NULL OR m.grupo_id IS NOT NULL 
        ORDER BY m.data_entrega DESC LIMIT 10
    ");
    $recentes = $stmt_recentes->fetchAll(PDO::FETCH_ASSOC);

    $stmt_historico = $pdo->query("
        SELECT h.data_devolucao, h.pessoas_faladas_total, m.identificador as mapa_identificador, u.nome as dirigente_nome
        FROM historico_mapas h JOIN mapas m ON h.mapa_id = m.id JOIN users u ON h.dirigente_id = u.id
        ORDER BY h.data_devolucao DESC LIMIT 10
    ");
    $historico = $stmt_historico->fetchAll(PDO::FETCH_ASSOC);


    // 4. MONTAGEM DA RESPOSTA JSON
    $response =[
        'user_permissoes' => $user_permissoes, 
        'stats' =>[
            'disponiveis' => (int) $disponiveis,
            'em_uso' => (int) $em_uso,
            'dirigentes' => (int) $dirigentes
        ],
        'recentes' => $recentes, 
        'historico' => $historico 
    ];

    // 5. DADOS CONDICIONAIS PARA DIRIGENTES / PUBLICADORES
    $is_dirigente = ($user_permissoes & PERM_DIRIGENTE) === PERM_DIRIGENTE;
    $is_publicador = ($user_permissoes & PERM_PUBLICADOR) === PERM_PUBLICADOR;

    if ($is_dirigente || $is_publicador) {
        $stmt_meus_mapas = $pdo->prepare("
            SELECT m.id, m.identificador, m.data_entrega, DATEDIFF(CURDATE(), m.data_entrega) as dias_comigo, g.nome as grupo_nome
            FROM mapas m
            LEFT JOIN grupos g ON m.grupo_id = g.id
            WHERE m.dirigente_id = ? OR m.grupo_id IN (SELECT grupo_id FROM grupo_membros WHERE user_id = ?)
            ORDER BY m.data_entrega ASC
        ");
        $stmt_meus_mapas->execute([$user_id, $user_id]);
        $response['meus_mapas'] = $stmt_meus_mapas->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Erro ao buscar dados do dashboard: ' . $e->getMessage()]);
}
?>
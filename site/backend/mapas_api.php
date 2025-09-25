<?php
// site/backend/mapas_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;
$recurso = $_GET['recurso'] ?? null;

switch ($method) {
    case 'GET': handle_get($pdo, $id, $recurso); break;
    case 'POST': handle_post($pdo); break;
    case 'PUT': handle_put($pdo, $id); break;
    case 'DELETE': handle_delete($pdo, $id); break;
    default: http_response_code(405); break;
}

function handle_get($pdo, $id, $recurso) {
    if ($recurso === 'dirigentes') {
        // Busca a lista de dirigentes para o modal de entrega
        $stmt = $pdo->query("SELECT id, nome FROM users WHERE cargo = 'dirigente' AND status = 'ativo' ORDER BY nome");
        echo json_encode($stmt->fetchAll());
        return;
    }

    if ($id) { // Busca um mapa específico para edição
        $stmt = $pdo->prepare("SELECT id, identificador, quadra_inicio, quadra_fim FROM mapas WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch());
    } else { // Lista todos os mapas
        // NOVO: Adicionado DATEDIFF para calcular os dias
        $sql = "SELECT m.id, m.identificador, m.quadra_inicio, m.quadra_fim, m.dirigente_id, m.data_entrega, u.nome as dirigente_nome,
                DATEDIFF(CURDATE(), m.data_entrega) as dias_com_dirigente
                FROM mapas m LEFT JOIN users u ON m.dirigente_id = u.id ORDER BY m.identificador";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll());
    }
}

function handle_post($pdo) { /* ... (código anterior está correto, sem alterações) ... */ }

function handle_put($pdo, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'edit_details';

    if ($action === 'entregar') {
        if (empty($data['mapa_id']) || empty($data['dirigente_id']) || empty($data['data_entrega'])) {
            http_response_code(400); echo json_encode(['message' => 'Dados insuficientes.']); return;
        }
        $sql = "UPDATE mapas SET dirigente_id = ?, data_entrega = ?, data_devolucao = NULL WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['dirigente_id'], $data['data_entrega'], $data['mapa_id']]);
        echo json_encode(['message' => 'Mapa entregue com sucesso!']);
    
    } elseif ($action === 'resgatar') {
        if (empty($data['mapa_id'])) { http_response_code(400); return; }
        // Ação do servo para "pegar de volta" o mapa. Não gera histórico. Apenas libera o mapa.
        $sql = "UPDATE mapas SET dirigente_id = NULL, data_entrega = NULL, data_devolucao = NULL WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['mapa_id']]);
        echo json_encode(['message' => 'Mapa resgatado e disponível novamente.']);
        
    } elseif ($action === 'edit_details') {
        /* ... (código anterior está correto, sem alterações) ... */
    } elseif ($action === 'devolver') {
        /* ... (código anterior está correto, sem alterações) ... */
    } elseif ($action === 'update_quadra') {
        /* ... (código anterior está correto, sem alterações) ... */
    } else {
        http_response_code(400);
        echo json_encode(['message' => 'Ação inválida']);
    }
}

function handle_delete($pdo, $id) { /* ... (código anterior está correto, sem alterações) ... */ }
?>
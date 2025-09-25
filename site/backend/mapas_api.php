<?php
// site/backend/mapas_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

$method = $_SERVER['REQUEST_METHOD'];

// Roteamento da API
switch ($method) {
    case 'GET':
        handle_get($pdo);
        break;
    case 'POST':
        handle_post($pdo);
        break;
    case 'PUT':
        handle_put($pdo);
        break;
    case 'DELETE':
        handle_delete($pdo);
        break;
    default:
        http_response_code(405);
        echo json_encode(['message' => 'Método não permitido']);
        break;
}

function handle_get($pdo) {
    $recurso = $_GET['recurso'] ?? null;
    if ($recurso === 'dirigentes') {
        // Retorna lista de dirigentes para o modal de entrega
        $stmt = $pdo->query("SELECT id, nome FROM users WHERE cargo = 'dirigente' ORDER BY nome");
        echo json_encode($stmt->fetchAll());
    } else {
        // Retorna a lista de todos os mapas
        $sql = "SELECT m.id, m.identificador, m.dirigente_id, m.data_entrega, u.nome as dirigente_nome
                FROM mapas m
                LEFT JOIN users u ON m.dirigente_id = u.id
                ORDER BY m.identificador";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll());
    }
}

function handle_post($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['identificador'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Identificador é obrigatório']);
        return;
    }
    $sql = "INSERT INTO mapas (identificador) VALUES (?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data['identificador']]);
    http_response_code(201);
    echo json_encode(['message' => 'Mapa criado com sucesso']);
}

function handle_put($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'entregar') {
        $sql = "UPDATE mapas SET dirigente_id = ?, data_entrega = ?, data_devolucao = NULL, pessoas_faladas = 0 WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['dirigente_id'], $data['data_entrega'], $data['mapa_id']]);
        echo json_encode(['message' => 'Mapa entregue com sucesso']);
    } 
    elseif ($action === 'devolver') {
        // Lógica para o dirigente devolver o mapa
        $mapa_id = $data['mapa_id'];
        $pessoas_faladas = $data['pessoas_faladas'];
        $data_devolucao = $data['data_devolucao'];

        $pdo->beginTransaction();
        try {
            // 1. Pega os dados atuais do mapa para o histórico
            $stmt_mapa = $pdo->prepare("SELECT dirigente_id, data_entrega FROM mapas WHERE id = ?");
            $stmt_mapa->execute([$mapa_id]);
            $mapa_atual = $stmt_mapa->fetch();

            if (!$mapa_atual) throw new Exception("Mapa não encontrado.");

            // 2. Insere no histórico
            $sql_hist = "INSERT INTO historico_mapas (mapa_id, dirigente_id, data_entrega, data_devolucao, pessoas_faladas) VALUES (?, ?, ?, ?, ?)";
            $stmt_hist = $pdo->prepare($sql_hist);
            $stmt_hist->execute([
                $mapa_id,
                $mapa_atual['dirigente_id'],
                $mapa_atual['data_entrega'],
                $data_devolucao,
                $pessoas_faladas
            ]);

            // 3. Reseta o mapa na tabela principal
            $sql_reset = "UPDATE mapas SET dirigente_id = NULL, data_entrega = NULL, data_devolucao = NULL, pessoas_faladas = 0 WHERE id = ?";
            $stmt_reset = $pdo->prepare($sql_reset);
            $stmt_reset->execute([$mapa_id]);

            $pdo->commit();
            echo json_encode(['message' => 'Mapa devolvido com sucesso!']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['message' => 'Falha ao devolver o mapa: ' . $e->getMessage()]);
        }

    } else {
        http_response_code(400);
        echo json_encode(['message' => 'Ação inválida']);
    }
}

function handle_delete($pdo) {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        return;
    }

    // Opcional: Verificar se o mapa está em uso antes de deletar
    $stmt_check = $pdo->prepare("SELECT dirigente_id FROM mapas WHERE id = ?");
    $stmt_check->execute([$id]);
    if ($stmt_check->fetchColumn() !== null) {
        http_response_code(409); // Conflict
        echo json_encode(['message' => 'Não é possível excluir um mapa que está em uso.']);
        return;
    }

    $sql = "DELETE FROM mapas WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    echo json_encode(['message' => 'Mapa excluído com sucesso']);
}
?>
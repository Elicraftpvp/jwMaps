<?php
// site/backend/mapas_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;
$recurso = $_GET['recurso'] ?? null;

// Roteamento
switch ($method) {
    case 'GET': handle_get($pdo, $id, $recurso); break;
    case 'POST': handle_post($pdo); break;
    case 'PUT': handle_put($pdo, $id); break;
    case 'DELETE': handle_delete($pdo, $id); break;
    default: http_response_code(405); break;
}

function handle_get($pdo, $id, $recurso) {
    if ($recurso === 'dirigentes') {
        $stmt = $pdo->query("SELECT id, nome FROM users WHERE cargo = 'dirigente' AND status = 'ativo' ORDER BY nome");
        echo json_encode($stmt->fetchAll());
        return;
    }

    if ($id) { // Busca um mapa específico para edição
        $stmt = $pdo->prepare("SELECT id, identificador, quadra_inicio, quadra_fim FROM mapas WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch());
    } else { // Lista todos os mapas para a tela de gerenciamento
        $sql = "SELECT m.id, m.identificador, m.quadra_inicio, m.quadra_fim, m.dirigente_id, m.data_entrega, u.nome as dirigente_nome,
                DATEDIFF(CURDATE(), m.data_entrega) as dias_com_dirigente
                FROM mapas m LEFT JOIN users u ON m.dirigente_id = u.id ORDER BY m.identificador";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll());
    }
}

function handle_post($pdo) { // Criar novo mapa
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['identificador']) || !isset($data['quadra_inicio']) || !isset($data['quadra_fim'])) {
        http_response_code(400); echo json_encode(['message' => 'Todos os campos são obrigatórios.']); return;
    }
    if ((int)$data['quadra_fim'] < (int)$data['quadra_inicio']) {
        http_response_code(400); echo json_encode(['message' => 'A quadra final não pode ser menor que a inicial.']); return;
    }

    $pdo->beginTransaction();
    try {
        $sql_mapa = "INSERT INTO mapas (identificador, quadra_inicio, quadra_fim) VALUES (?, ?, ?)";
        $stmt_mapa = $pdo->prepare($sql_mapa);
        $stmt_mapa->execute([$data['identificador'], $data['quadra_inicio'], $data['quadra_fim']]);
        $mapa_id = $pdo->lastInsertId();

        // Cria as quadras associadas
        $sql_quadra = "INSERT INTO quadras (mapa_id, numero) VALUES (?, ?)";
        $stmt_quadra = $pdo->prepare($sql_quadra);
        for ($i = (int)$data['quadra_inicio']; $i <= (int)$data['quadra_fim']; $i++) {
            $stmt_quadra->execute([$mapa_id, $i]);
        }
        $pdo->commit();
        http_response_code(201);
        echo json_encode(['message' => 'Mapa e quadras criados com sucesso!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['message' => 'Erro ao criar mapa: ' . $e->getMessage()]);
    }
}

function handle_put($pdo, $id) { // Atualizar, Entregar, Devolver, etc.
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'edit_details';

    try {
        switch ($action) {
            case 'entregar':
                if (empty($data['mapa_id']) || empty($data['dirigente_id']) || empty($data['data_entrega'])) throw new Exception('Dados insuficientes.', 400);
                $sql = "UPDATE mapas SET dirigente_id = ?, data_entrega = ?, data_devolucao = NULL WHERE id = ?";
                $pdo->prepare($sql)->execute([$data['dirigente_id'], $data['data_entrega'], $data['mapa_id']]);
                echo json_encode(['message' => 'Mapa entregue com sucesso!']);
                break;

            case 'resgatar':
                if (empty($data['mapa_id'])) throw new Exception('ID do mapa não fornecido.', 400);
                $sql = "UPDATE mapas SET dirigente_id = NULL, data_entrega = NULL WHERE id = ?";
                $pdo->prepare($sql)->execute([$data['mapa_id']]);
                echo json_encode(['message' => 'Mapa resgatado e disponível novamente.']);
                break;

            case 'edit_details':
                if (!$id || empty($data['identificador']) || !isset($data['quadra_inicio']) || !isset($data['quadra_fim'])) throw new Exception('Dados insuficientes.', 400);
                $pdo->beginTransaction();
                // Atualiza os dados principais
                $sql = "UPDATE mapas SET identificador = ?, quadra_inicio = ?, quadra_fim = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([$data['identificador'], $data['quadra_inicio'], $data['quadra_fim'], $id]);
                // Sincroniza as quadras (remove as antigas e cria as novas)
                $pdo->prepare("DELETE FROM quadras WHERE mapa_id = ?")->execute([$id]);
                $stmt_quadra = $pdo->prepare("INSERT INTO quadras (mapa_id, numero) VALUES (?, ?)");
                for ($i = (int)$data['quadra_inicio']; $i <= (int)$data['quadra_fim']; $i++) {
                    $stmt_quadra->execute([$id, $i]);
                }
                $pdo->commit();
                echo json_encode(['message' => 'Mapa atualizado com sucesso!']);
                break;

            case 'devolver':
                if (empty($data['mapa_id']) || empty($data['data_devolucao'])) throw new Exception('Dados insuficientes.', 400);
                $pdo->beginTransaction();
                // 1. Pega dados atuais do mapa e quadras
                $stmt_mapa = $pdo->prepare("SELECT dirigente_id, data_entrega FROM mapas WHERE id = ?");
                $stmt_mapa->execute([$data['mapa_id']]);
                $mapa_atual = $stmt_mapa->fetch();
                if (!$mapa_atual) throw new Exception("Mapa não encontrado.");

                $stmt_quadras = $pdo->prepare("SELECT numero, pessoas_faladas FROM quadras WHERE mapa_id = ?");
                $stmt_quadras->execute([$data['mapa_id']]);
                $quadras_data = $stmt_quadras->fetchAll();

                // 2. Calcula o total e prepara o JSON para o histórico
                $total_faladas = 0;
                foreach($quadras_data as $q) { $total_faladas += $q['pessoas_faladas']; }
                $dados_quadras_json = json_encode($quadras_data);

                // 3. Insere no histórico
                $sql_hist = "INSERT INTO historico_mapas (mapa_id, dirigente_id, data_entrega, data_devolucao, pessoas_faladas_total, dados_quadras) VALUES (?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql_hist)->execute([$data['mapa_id'], $mapa_atual['dirigente_id'], $mapa_atual['data_entrega'], $data['data_devolucao'], $total_faladas, $dados_quadras_json]);

                // 4. Reseta o mapa
                $sql_reset = "UPDATE mapas SET dirigente_id = NULL, data_entrega = NULL, data_devolucao = NULL WHERE id = ?";
                $pdo->prepare($sql_reset)->execute([$data['mapa_id']]);
                
                // 5. Reseta a contagem das quadras
                $sql_reset_quadras = "UPDATE quadras SET pessoas_faladas = 0 WHERE mapa_id = ?";
                $pdo->prepare($sql_reset_quadras)->execute([$data['mapa_id']]);

                $pdo->commit();
                echo json_encode(['message' => 'Mapa devolvido e registrado no histórico com sucesso!']);
                break;

            case 'update_quadra': // Auto-save da contagem de uma quadra
                if (!isset($data['quadra_id']) || !isset($data['pessoas_faladas'])) throw new Exception('Dados insuficientes.', 400);
                $sql = "UPDATE quadras SET pessoas_faladas = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([$data['pessoas_faladas'], $data['quadra_id']]);
                echo json_encode(['status' => 'success']);
                break;
            
            default: throw new Exception('Ação inválida.', 400);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
        echo json_encode(['message' => $e->getMessage()]);
    }
}

function handle_delete($pdo, $id) { // Deletar um mapa
    if (!$id) { http_response_code(400); return; }
    $pdo->beginTransaction();
    try {
        // ON DELETE CASCADE vai cuidar das quadras e histórico.
        $stmt = $pdo->prepare("DELETE FROM mapas WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        echo json_encode(['message' => 'Mapa excluído com sucesso.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['message' => 'Erro ao excluir o mapa.']);
    }
}
?>
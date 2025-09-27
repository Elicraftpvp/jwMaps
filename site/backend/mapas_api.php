<?php
// site/backend/mapas_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;
$recurso = $_GET['recurso'] ?? null;

switch ($method) {
    case 'GET':
        handle_get($pdo, $id, $recurso);
        break;
    case 'POST':
        // --- MUDANÇA PRINCIPAL: POST agora lida com TUDO ---
        handle_post_unified($pdo);
        break;
    case 'DELETE':
        handle_delete($pdo, $id);
        break;
    case 'OPTIONS':
        http_response_code(204); // No Content
        break;
    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(['message' => 'Method Not Allowed']);
        break;
}

function handle_get($pdo, $id, $recurso) {
    // Esta função permanece exatamente como estava, está correta.
    if ($recurso === 'dirigentes') {
        $stmt = $pdo->query("SELECT id, nome FROM users WHERE cargo = 'dirigente' AND status = 'ativo' ORDER BY nome");
        echo json_encode($stmt->fetchAll());
        return;
    }

    if ($id) {
        $stmt = $pdo->prepare("SELECT id, identificador, quadra_inicio, quadra_fim FROM mapas WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch());
    } else {
        $sql = "SELECT m.id, m.identificador, m.quadra_inicio, m.quadra_fim, m.dirigente_id, m.data_entrega, u.nome as dirigente_nome,
                DATEDIFF(CURDATE(), m.data_entrega) as dias_com_dirigente
                FROM mapas m LEFT JOIN users u ON m.dirigente_id = u.id ORDER BY m.identificador";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll());
    }
}

// --- FUNÇÃO UNIFICADA PARA CRIAR E ATUALIZAR ---
function handle_post_unified($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    // Se 'action' não for enviado, a ação padrão é 'create' (criar novo mapa)
    $action = $data['action'] ?? 'create';

    try {
        switch ($action) {
            case 'create':
                if (empty($data['identificador']) || empty($data['quadra_inicio']) || empty($data['quadra_fim'])) {
                    throw new Exception('Todos os campos são obrigatórios.', 400);
                }
                $pdo->beginTransaction();
                $sql = "INSERT INTO mapas (identificador, quadra_inicio, quadra_fim) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['identificador'], $data['quadra_inicio'], $data['quadra_fim']]);
                $mapa_id = $pdo->lastInsertId();
                $stmt_quadra = $pdo->prepare("INSERT INTO quadras (mapa_id, numero) VALUES (?, ?)");
                for ($i = (int)$data['quadra_inicio']; $i <= (int)$data['quadra_fim']; $i++) {
                    $stmt_quadra->execute([$mapa_id, $i]);
                }
                $pdo->commit();
                http_response_code(201);
                echo json_encode(['message' => 'Mapa e quadras criados com sucesso!']);
                break;

            case 'edit_details':
                // IMPORTANTE: O ID agora vem no corpo da requisição, não na URL
                $mapa_id = $data['id'] ?? null;
                if (!$mapa_id || empty($data['identificador']) || !isset($data['quadra_inicio']) || !isset($data['quadra_fim'])) {
                    throw new Exception('Dados insuficientes para edição.', 400);
                }
                $pdo->beginTransaction();
                $sql = "UPDATE mapas SET identificador = ?, quadra_inicio = ?, quadra_fim = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([$data['identificador'], $data['quadra_inicio'], $data['quadra_fim'], $mapa_id]);
                $pdo->prepare("DELETE FROM quadras WHERE mapa_id = ?")->execute([$mapa_id]);
                $stmt_quadra = $pdo->prepare("INSERT INTO quadras (mapa_id, numero) VALUES (?, ?)");
                for ($i = (int)$data['quadra_inicio']; $i <= (int)$data['quadra_fim']; $i++) {
                    $stmt_quadra->execute([$mapa_id, $i]);
                }
                $pdo->commit();
                echo json_encode(['message' => 'Mapa atualizado com sucesso!']);
                break;

            case 'entregar':
                if (empty($data['mapa_id']) || empty($data['dirigente_id']) || empty($data['data_entrega'])) throw new Exception('Dados insuficientes.', 400);
                $sql = "UPDATE mapas SET dirigente_id = ?, data_entrega = ?, data_devolucao = NULL WHERE id = ?";
                $pdo->prepare($sql)->execute([$data['dirigente_id'], $data['data_entrega'], $data['mapa_id']]);
                echo json_encode(['message' => 'Mapa entregue com sucesso!']);
                break;

            case 'resgatar':
                if (empty($data['mapa_id'])) throw new Exception('ID do mapa não fornecido.', 400);

                $pdo->beginTransaction();

                // Busca os dados atuais do mapa para o histórico
                $stmt_mapa = $pdo->prepare("SELECT dirigente_id, data_entrega FROM mapas WHERE id = ?");
                $stmt_mapa->execute([$data['mapa_id']]);
                $mapa_atual = $stmt_mapa->fetch();

                if (!$mapa_atual || empty($mapa_atual['dirigente_id'])) {
                    throw new Exception("Mapa não encontrado ou não está em uso.", 404);
                }

                // Soma o total de pessoas faladas nas quadras
                $stmt_quadras = $pdo->prepare("SELECT numero, pessoas_faladas FROM quadras WHERE mapa_id = ?");
                $stmt_quadras->execute([$data['mapa_id']]);
                $quadras_data = $stmt_quadras->fetchAll();
                $total_faladas = 0;
                foreach($quadras_data as $q) { $total_faladas += (int)$q['pessoas_faladas']; }
                $dados_quadras_json = json_encode($quadras_data);
                
                // Define a data de devolução como hoje
                $data_devolucao_hoje = date('Y-m-d');

                // Insere o registro no histórico
                $sql_hist = "INSERT INTO historico_mapas (mapa_id, dirigente_id, data_entrega, data_devolucao, pessoas_faladas_total, dados_quadras) VALUES (?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql_hist)->execute([
                    $data['mapa_id'], 
                    $mapa_atual['dirigente_id'], 
                    $mapa_atual['data_entrega'], 
                    $data_devolucao_hoje, 
                    $total_faladas, 
                    $dados_quadras_json
                ]);

                // Reseta o mapa na tabela principal
                $sql_reset = "UPDATE mapas SET dirigente_id = NULL, data_entrega = NULL, data_devolucao = NULL WHERE id = ?";
                $pdo->prepare($sql_reset)->execute([$data['mapa_id']]);
                
                // Reseta os contadores das quadras do mapa
                $sql_reset_quadras = "UPDATE quadras SET pessoas_faladas = 0 WHERE mapa_id = ?";
                $pdo->prepare($sql_reset_quadras)->execute([$data['mapa_id']]);
                
                $pdo->commit();
                echo json_encode(['message' => 'Mapa resgatado, movido para o histórico e disponível novamente.']);
                break;
            
            case 'devolver':
                // Lógica de devolução (iniciada pelo dirigente)
                if (empty($data['mapa_id']) || empty($data['data_devolucao'])) throw new Exception('Dados insuficientes.', 400);
                
                $pdo->beginTransaction();
                
                $stmt_mapa = $pdo->prepare("SELECT dirigente_id, data_entrega FROM mapas WHERE id = ?");
                $stmt_mapa->execute([$data['mapa_id']]);
                $mapa_atual = $stmt_mapa->fetch();
                if (!$mapa_atual) throw new Exception("Mapa não encontrado.");

                $stmt_quadras = $pdo->prepare("SELECT numero, pessoas_faladas FROM quadras WHERE mapa_id = ?");
                $stmt_quadras->execute([$data['mapa_id']]);
                $quadras_data = $stmt_quadras->fetchAll();
                $total_faladas = 0;
                foreach($quadras_data as $q) { $total_faladas += (int)$q['pessoas_faladas']; }
                $dados_quadras_json = json_encode($quadras_data);
                
                $sql_hist = "INSERT INTO historico_mapas (mapa_id, dirigente_id, data_entrega, data_devolucao, pessoas_faladas_total, dados_quadras) VALUES (?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql_hist)->execute([$data['mapa_id'], $mapa_atual['dirigente_id'], $mapa_atual['data_entrega'], $data['data_devolucao'], $total_faladas, $dados_quadras_json]);
                
                $sql_reset = "UPDATE mapas SET dirigente_id = NULL, data_entrega = NULL, data_devolucao = NULL WHERE id = ?";
                $pdo->prepare($sql_reset)->execute([$data['mapa_id']]);
                
                $sql_reset_quadras = "UPDATE quadras SET pessoas_faladas = 0 WHERE mapa_id = ?";
                $pdo->prepare($sql_reset_quadras)->execute([$data['mapa_id']]);
                
                $pdo->commit();
                echo json_encode(['message' => 'Mapa devolvido e registrado no histórico com sucesso!']);
                break;

            case 'update_quadra':
                if (!isset($data['quadra_id']) || !isset($data['pessoas_faladas'])) throw new Exception('Dados insuficientes.', 400);
                $sql = "UPDATE quadras SET pessoas_faladas = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([$data['pessoas_faladas'], $data['quadra_id']]);
                echo json_encode(['status' => 'success']);
                break;

            default:
                throw new Exception('Ação inválida.', 400);
        }
    } catch (Exception $e) {
        // Se qualquer operação falhar, desfaz a transação e envia uma resposta de erro
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Usa o código do erro da exceção, ou 500 como padrão
        $errorCode = $e->getCode() && is_int($e->getCode()) && $e->getCode() !== 0 ? $e->getCode() : 500;
        http_response_code($errorCode);
        echo json_encode(['message' => 'Erro: ' . $e->getMessage()]);
    }
}

function handle_delete($pdo, $id) {
    // Esta função permanece exatamente como estava, está correta.
    if (!$id) {
        http_response_code(400);
        echo json_encode(['message' => 'ID do mapa não fornecido.']);
        return;
    }
    // Para integridade, é bom usar transação aqui também.
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM quadras WHERE mapa_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM historico_mapas WHERE mapa_id = ?")->execute([$id]); // Adicionado para limpar histórico
        $pdo->prepare("DELETE FROM mapas WHERE id = ?")->execute([$id]);
        $pdo->commit();
        echo json_encode(['message' => 'Mapa, suas quadras e histórico foram excluídos com sucesso.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['message' => 'Erro ao excluir o mapa: ' . $e->getMessage()]);
    }
}
?>
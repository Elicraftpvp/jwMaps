<?php
// site/backend/mapas_api.php

// MODO DE DEPURAÇÃO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once 'conexao.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'OPTIONS') {
    http_response_code(204);
    exit();
}

$id = $_GET['id'] ?? null;
$recurso = $_GET['recurso'] ?? null;

try {
    switch ($method) {
        case 'GET':
            handle_get($pdo, $id, $recurso);
            break;
        case 'POST':
            handle_post_unified($pdo);
            break;
        case 'DELETE':
            handle_delete($pdo, $id);
            break;
        default:
            throw new Exception('Método não permitido', 405);
    }
} catch (Exception $e) {
    $errorCode = $e->getCode() && is_int($e->getCode()) && $e->getCode() > 0 ? $e->getCode() : 500;
    http_response_code($errorCode);
    echo json_encode([
        'message' => 'Erro geral na API: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

function handle_get($pdo, $id, $recurso) {
    try {
        if ($recurso === 'dirigentes') {
            $stmt = $pdo->query("SELECT id, nome FROM users WHERE (permissoes & 1) = 1 AND status = 'ativo' ORDER BY nome");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            return;
        }

        if ($recurso === 'history' && $id) {
            $sql = "SELECT h.*, u.nome as dirigente_nome 
                    FROM historico_mapas h 
                    JOIN users u ON h.dirigente_id = u.id 
                    WHERE h.mapa_id = ? 
                    ORDER BY h.data_devolucao DESC, h.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            return;
        }

        if ($id) {
            $stmt = $pdo->prepare("SELECT id, identificador, quadra_inicio, quadra_fim, regiao, tipo, grupo_id FROM mapas WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } else {
            $sql = "SELECT m.id, m.identificador, m.quadra_inicio, m.quadra_fim, m.regiao, m.tipo, m.dirigente_id, m.grupo_id, m.data_entrega, u.nome as dirigente_nome,
                    DATEDIFF(CURDATE(), m.data_entrega) as dias_com_dirigente
                    FROM mapas m LEFT JOIN users u ON m.dirigente_id = u.id ORDER BY m.id";
            $stmt = $pdo->query($sql);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    } catch (PDOException $e) {
        throw new Exception('Erro no Banco de Dados: ' . $e->getMessage(), 500);
    }
}

function handle_post_unified($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido recebido.', 400);
    }
    
    $action = $data['action'] ?? 'create';

    try {
        switch ($action) {
            case 'create':
                if (empty($data['identificador']) || !isset($data['quadra_inicio']) || !isset($data['quadra_fim'])) throw new Exception('Identificador e quadras são obrigatórios.', 400);
                $pdo->beginTransaction();
                $sql = "INSERT INTO mapas (identificador, quadra_inicio, quadra_fim, regiao, tipo) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['identificador'], $data['quadra_inicio'], $data['quadra_fim'], $data['regiao'], $data['tipo']]);
                $mapa_id = $pdo->lastInsertId();
                $stmt_quadra = $pdo->prepare("INSERT INTO quadras (mapa_id, numero) VALUES (?, ?)");
                for ($i = (int)$data['quadra_inicio']; $i <= (int)$data['quadra_fim']; $i++) $stmt_quadra->execute([$mapa_id, $i]);
                $pdo->commit();
                http_response_code(201);
                echo json_encode(['message' => 'Mapa e quadras criados com sucesso!']);
                break;

            case 'edit_details':
                $mapa_id = $data['id'] ?? null;
                if (!$mapa_id || empty($data['identificador']) || !isset($data['quadra_inicio']) || !isset($data['quadra_fim'])) throw new Exception('Dados insuficientes para edição.', 400);
                $pdo->beginTransaction();
                $sql = "UPDATE mapas SET identificador = ?, quadra_inicio = ?, quadra_fim = ?, regiao = ?, tipo = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([$data['identificador'], $data['quadra_inicio'], $data['quadra_fim'], $data['regiao'], $data['tipo'], $mapa_id]);
                $pdo->prepare("DELETE FROM quadras WHERE mapa_id = ?")->execute([$mapa_id]);
                $stmt_quadra = $pdo->prepare("INSERT INTO quadras (mapa_id, numero) VALUES (?, ?)");
                for ($i = (int)$data['quadra_inicio']; $i <= (int)$data['quadra_fim']; $i++) $stmt_quadra->execute([$mapa_id, $i]);
                $pdo->commit();
                echo json_encode(['message' => 'Mapa atualizado com sucesso!']);
                break;

            case 'entregar':
                if (empty($data['mapa_id']) || empty($data['dirigente_id']) || empty($data['data_entrega'])) {
                    throw new Exception('Dados insuficientes para entregar.', 400);
                }

                $pdo->beginTransaction();

                $stmt_check = $pdo->prepare("SELECT dirigente_id, data_entrega FROM mapas WHERE id = ?");
                $stmt_check->execute([$data['mapa_id']]);
                $mapa_atual = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($mapa_atual && !empty($mapa_atual['dirigente_id']) && $mapa_atual['dirigente_id'] != $data['dirigente_id']) {
                    $stmt_quadras = $pdo->prepare("SELECT numero, pessoas_faladas FROM quadras WHERE mapa_id = ?");
                    $stmt_quadras->execute([$data['mapa_id']]);
                    $quadras_data = $stmt_quadras->fetchAll(PDO::FETCH_ASSOC);
                    $dados_quadras_json = json_encode($quadras_data);
                    
                    $total_faladas_historico = 0;
                    $data_devolucao_hist = date('Y-m-d');

                    $sql_hist = "INSERT INTO historico_mapas 
                                (mapa_id, dirigente_id, data_entrega, data_devolucao, pessoas_faladas_total, dados_quadras) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $pdo->prepare($sql_hist)->execute([
                        $data['mapa_id'], 
                        $mapa_atual['dirigente_id'], 
                        $mapa_atual['data_entrega'], 
                        $data_devolucao_hist,        
                        $total_faladas_historico,    
                        $dados_quadras_json
                    ]);
                }

                // Ao entregar para um dirigente, removemos o vínculo de grupo_id
                $sql_update = "UPDATE mapas SET dirigente_id = ?, grupo_id = NULL, data_entrega = ?, data_devolucao = NULL WHERE id = ?";
                $pdo->prepare($sql_update)->execute([$data['dirigente_id'], $data['data_entrega'], $data['mapa_id']]);
                
                $pdo->commit();
                echo json_encode(['message' => 'Mapa transferido com sucesso!']);
                break;

            case 'resgatar':
                if (empty($data['mapa_id'])) throw new Exception('ID do mapa não fornecido.', 400);
                
                $pdo->beginTransaction();
                
                $stmt_mapa = $pdo->prepare("SELECT dirigente_id, data_entrega FROM mapas WHERE id = ?");
                $stmt_mapa->execute([$data['mapa_id']]);
                $mapa_atual = $stmt_mapa->fetch(PDO::FETCH_ASSOC);

                if ($mapa_atual && !empty($mapa_atual['dirigente_id'])) {
                    $stmt_quadras = $pdo->prepare("SELECT numero, pessoas_faladas FROM quadras WHERE mapa_id = ?");
                    $stmt_quadras->execute([$data['mapa_id']]);
                    $quadras_data = $stmt_quadras->fetchAll(PDO::FETCH_ASSOC);
                    
                    $total_faladas = array_sum(array_column($quadras_data, 'pessoas_faladas'));
                    $dados_quadras_json = json_encode($quadras_data);
                    
                    $data_devolucao_hoje = date('Y-m-d');
                    
                    $sql_hist = "INSERT INTO historico_mapas (mapa_id, dirigente_id, data_entrega, data_devolucao, pessoas_faladas_total, dados_quadras) VALUES (?, ?, ?, ?, ?, ?)";
                    $pdo->prepare($sql_hist)->execute([$data['mapa_id'], $mapa_atual['dirigente_id'], $mapa_atual['data_entrega'], $data_devolucao_hoje, $total_faladas, $dados_quadras_json]);
                }

                // Reseta dirigente, grupo e quadras
                $pdo->prepare("UPDATE mapas SET dirigente_id = NULL, grupo_id = NULL, data_entrega = NULL, data_devolucao = NULL WHERE id = ?")->execute([$data['mapa_id']]);
                $pdo->prepare("UPDATE quadras SET pessoas_faladas = 0 WHERE mapa_id = ?")->execute([$data['mapa_id']]);
                
                $pdo->commit();
                echo json_encode(['message' => 'Mapa devolvido e contabilizado.']);
                break;

            case 'devolver':
                if (empty($data['mapa_id']) || empty($data['data_devolucao'])) throw new Exception('Dados insuficientes.', 400);
                $pdo->beginTransaction();
                
                $stmt_mapa = $pdo->prepare("SELECT dirigente_id, data_entrega FROM mapas WHERE id = ?");
                $stmt_mapa->execute([$data['mapa_id']]);
                $mapa_atual = $stmt_mapa->fetch(PDO::FETCH_ASSOC);
                
                if (!$mapa_atual) throw new Exception("Mapa não encontrado.");
                
                $stmt_quadras = $pdo->prepare("SELECT numero, pessoas_faladas FROM quadras WHERE mapa_id = ?");
                $stmt_quadras->execute([$data['mapa_id']]);
                $quadras_data = $stmt_quadras->fetchAll(PDO::FETCH_ASSOC);
                
                $total_faladas = array_sum(array_column($quadras_data, 'pessoas_faladas'));
                $dados_quadras_json = json_encode($quadras_data);
                
                // Se houver dirigente associado, registra no histórico
                if (!empty($mapa_atual['dirigente_id'])) {
                    $sql_hist = "INSERT INTO historico_mapas (mapa_id, dirigente_id, data_entrega, data_devolucao, pessoas_faladas_total, dados_quadras) VALUES (?, ?, ?, ?, ?, ?)";
                    $pdo->prepare($sql_hist)->execute([$data['mapa_id'], $mapa_atual['dirigente_id'], $mapa_atual['data_entrega'], $data['data_devolucao'], $total_faladas, $dados_quadras_json]);
                }
                
                // Reseta vínculo com dirigente e grupo ao finalizar
                $pdo->prepare("UPDATE mapas SET dirigente_id = NULL, grupo_id = NULL, data_entrega = NULL, data_devolucao = NULL WHERE id = ?")->execute([$data['mapa_id']]);
                $pdo->prepare("UPDATE quadras SET pessoas_faladas = 0 WHERE mapa_id = ?")->execute([$data['mapa_id']]);
                
                $pdo->commit();
                echo json_encode(['message' => 'Mapa devolvido e contabilizado com sucesso!']);
                break;
            
            case 'update_quadra':
                if (!isset($data['quadra_id']) || !isset($data['pessoas_faladas'])) throw new Exception('Dados insuficientes.', 400);
                $sql = "UPDATE quadras SET pessoas_faladas = GREATEST(0, ?) WHERE id = ?";
                $pdo->prepare($sql)->execute([$data['pessoas_faladas'], $data['quadra_id']]);
                echo json_encode(['status' => 'success']);
                break;

            case 'update_quadra_increment':
                if (!isset($data['quadra_id']) || !isset($data['delta'])) throw new Exception('Dados insuficientes.', 400);
                $sql = "UPDATE quadras 
                        SET pessoas_faladas = GREATEST(0, CAST(pessoas_faladas AS SIGNED) + ?) 
                        WHERE id = ?";
                $pdo->prepare($sql)->execute([(int)$data['delta'], $data['quadra_id']]);
                echo json_encode(['status' => 'success']);
                break;

            default:
                throw new Exception('Ação inválida.', 400);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function handle_delete($pdo, $id) {
    if (!$id) throw new Exception('ID obrigatório.', 400);
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM quadras WHERE mapa_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM historico_mapas WHERE mapa_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM mapas WHERE id = ?")->execute([$id]);
        $pdo->commit();
        echo json_encode(['message' => 'Deletado com sucesso.']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw new Exception('Erro ao deletar: ' . $e->getMessage(), 500);
    }
}
?>
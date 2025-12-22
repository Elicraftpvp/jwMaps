<?php
// site/backend/grupos_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;
$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === 'true';

function gerarToken($pdo) {
    return bin2hex(random_bytes(16));
}

try {
    if ($method === 'GET') {
        if ($id) {
            // Busca um grupo específico (geralmente para edição)
            $stmt = $pdo->prepare("SELECT * FROM grupos WHERE id = ?");
            $stmt->execute([$id]);
            $grupo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($grupo) {
                // Busca IDs dos membros vinculados
                $stmtM = $pdo->prepare("SELECT user_id FROM grupo_membros WHERE grupo_id = ?");
                $stmtM->execute([$id]);
                $grupo['membros_ids'] = $stmtM->fetchAll(PDO::FETCH_COLUMN);

                // Busca IDs dos mapas vinculados
                $stmtMap = $pdo->prepare("SELECT id FROM mapas WHERE grupo_id = ?");
                $stmtMap->execute([$id]);
                $grupo['mapas_ids'] = $stmtMap->fetchAll(PDO::FETCH_COLUMN);
            }

            echo json_encode($grupo);
        } else {
            // Listagem geral com filtro de status
            $statusFiltro = $showInactive ? 'inativo' : 'ativo';
            
            $sql = "SELECT g.*, 
                    (SELECT COUNT(*) FROM grupo_membros WHERE grupo_id = g.id) as total_membros,
                    (SELECT COUNT(*) FROM mapas WHERE grupo_id = g.id) as total_mapas
                    FROM grupos g 
                    WHERE g.status = ? 
                    ORDER BY g.nome";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$statusFiltro]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    } 
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $pdo->beginTransaction();
            
            if ($action === 'create') {
                $token = gerarToken($pdo);
                $stmt = $pdo->prepare("INSERT INTO grupos (nome, token_acesso, status) VALUES (?, ?, 'ativo')");
                $stmt->execute([$data['nome'], $token]);
                $groupId = $pdo->lastInsertId();
            } else {
                $groupId = $data['id'];
                $stmt = $pdo->prepare("UPDATE grupos SET nome = ? WHERE id = ?");
                $stmt->execute([$data['nome'], $groupId]);
                
                // Limpar relações antigas de membros para reinserir
                $pdo->prepare("DELETE FROM grupo_membros WHERE grupo_id = ?")->execute([$groupId]);
                
                // Nota: A regra de negócio atual do JS envia mapas como array vazio.
                // Se desejar preservar os mapas já vinculados ao editar, 
                // remova a linha abaixo ou trate condicionalmente.
                if (isset($data['mapas'])) {
                    $pdo->prepare("UPDATE mapas SET grupo_id = NULL WHERE grupo_id = ?")->execute([$groupId]);
                }
            }

            // Inserir Membros (Vínculo Muitos-para-Muitos)
            if (!empty($data['membros'])) {
                $stmtM = $pdo->prepare("INSERT INTO grupo_membros (grupo_id, user_id) VALUES (?, ?)");
                foreach ($data['membros'] as $uid) {
                    $stmtM->execute([$groupId, $uid]);
                }
            }

            // Vincular Mapas (Vínculo Um-para-Muitos na tabela mapas)
            if (!empty($data['mapas'])) {
                $stmtMap = $pdo->prepare("UPDATE mapas SET grupo_id = ? WHERE id = ?");
                foreach ($data['mapas'] as $mid) {
                    $stmtMap->execute([$groupId, $mid]);
                }
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'id' => $groupId]);
        }
        elseif ($action === 'deactivate') {
            // Soft delete: Apenas muda o status
            $stmt = $pdo->prepare("UPDATE grupos SET status = 'inativo' WHERE id = ?");
            $stmt->execute([$data['id']]);
            echo json_encode(['status' => 'deactivated']);
        }
        elseif ($action === 'reactivate') {
            // Reativação
            $stmt = $pdo->prepare("UPDATE grupos SET status = 'ativo' WHERE id = ?");
            $stmt->execute([$data['id']]);
            echo json_encode(['status' => 'reactivated']);
        }
        elseif ($action === 'delete') {
            // Mantido caso ainda precise de exclusão física por algum motivo administrativo direto
            $pdo->prepare("DELETE FROM grupos WHERE id = ?")->execute([$data['id']]);
            echo json_encode(['status' => 'deleted_permanently']);
        }
    }
} catch (Exception $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['message' => $e->getMessage()]);
}
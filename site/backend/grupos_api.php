<?php
// site/backend/grupos_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

function gerarToken($pdo) {
    return bin2hex(random_bytes(16));
}

try {
    if ($method === 'GET') {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM grupos WHERE id = ?");
            $stmt->execute([$id]);
            $grupo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmtM = $pdo->prepare("SELECT user_id FROM grupo_membros WHERE grupo_id = ?");
            $stmtM->execute([$id]);
            $grupo['membros_ids'] = $stmtM->fetchAll(PDO::FETCH_COLUMN);

            $stmtMap = $pdo->prepare("SELECT id FROM mapas WHERE grupo_id = ?");
            $stmtMap->execute([$id]);
            $grupo['mapas_ids'] = $stmtMap->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode($grupo);
        } else {
            $sql = "SELECT g.*, 
                    (SELECT COUNT(*) FROM grupo_membros WHERE grupo_id = g.id) as total_membros,
                    (SELECT COUNT(*) FROM mapas WHERE grupo_id = g.id) as total_mapas
                    FROM grupos g ORDER BY g.nome";
            echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
        }
    } 
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $pdo->beginTransaction();
            
            if ($action === 'create') {
                $token = gerarToken($pdo);
                $stmt = $pdo->prepare("INSERT INTO grupos (nome, token_acesso) VALUES (?, ?)");
                $stmt->execute([$data['nome'], $token]);
                $groupId = $pdo->lastInsertId();
            } else {
                $groupId = $data['id'];
                $stmt = $pdo->prepare("UPDATE grupos SET nome = ? WHERE id = ?");
                $stmt->execute([$data['nome'], $groupId]);
                
                // Limpar relações antigas
                $pdo->prepare("DELETE FROM grupo_membros WHERE grupo_id = ?")->execute([$groupId]);
                $pdo->prepare("UPDATE mapas SET grupo_id = NULL WHERE grupo_id = ?")->execute([$groupId]);
            }

            // Inserir Membros
            if (!empty($data['membros'])) {
                $stmtM = $pdo->prepare("INSERT INTO grupo_membros (grupo_id, user_id) VALUES (?, ?)");
                foreach ($data['membros'] as $uid) $stmtM->execute([$groupId, $uid]);
            }

            // Vincular Mapas
            if (!empty($data['mapas'])) {
                $stmtMap = $pdo->prepare("UPDATE mapas SET grupo_id = ? WHERE id = ?");
                foreach ($data['mapas'] as $mid) $stmtMap->execute([$groupId, $mid]);
            }

            $pdo->commit();
            echo json_encode(['status' => 'success']);
        }
        elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM grupos WHERE id = ?")->execute([$data['id']]);
            echo json_encode(['status' => 'deleted']);
        }
    }
} catch (Exception $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['message' => $e->getMessage()]);
}
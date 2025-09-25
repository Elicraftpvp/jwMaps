<?php
// site/backend/dirigentes_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare("SELECT id, nome, login, cargo FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
        } else {
            $stmt = $pdo->query("SELECT id, nome, login, cargo FROM users ORDER BY nome");
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['senha'])) {
            http_response_code(400);
            echo json_encode(['message' => 'Senha é obrigatória para novos usuários.']);
            exit;
        }

        // Verifica se o login já existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login = ?");
        $stmt->execute([$data['login']]);
        if ($stmt->fetchColumn() > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['message' => 'Este login já está em uso.']);
            exit;
        }

        $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (nome, login, senha, cargo) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['nome'], $data['login'], $hash, $data['cargo']]);
        echo json_encode(['message' => 'Usuário criado com sucesso!']);
        break;

    case 'PUT':
        if (!$id) { exit(http_response_code(400)); }
        $data = json_decode(file_get_contents('php://input'), true);

        // Verifica se o login já existe em outro usuário
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login = ? AND id != ?");
        $stmt->execute([$data['login'], $id]);
        if ($stmt->fetchColumn() > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['message' => 'Este login já está em uso por outro usuário.']);
            exit;
        }

        if (!empty($data['senha'])) {
            // Atualiza com senha nova
            $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
            $sql = "UPDATE users SET nome = ?, login = ?, cargo = ?, senha = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['nome'], $data['login'], $data['cargo'], $hash, $id]);
        } else {
            // Atualiza sem alterar a senha
            $sql = "UPDATE users SET nome = ?, login = ?, cargo = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['nome'], $data['login'], $data['cargo'], $id]);
        }
        echo json_encode(['message' => 'Usuário atualizado com sucesso!']);
        break;

    case 'DELETE':
        if (!$id) { exit(http_response_code(400)); }
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Usuário excluído com sucesso!']);
        break;

    default:
        http_response_code(405);
        break;
}
?>
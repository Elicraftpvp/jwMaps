<?php
// site/backend/dirigentes_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

// Função auxiliar para gerar um token único
function gerarTokenUnico($pdo) {
    do {
        $token = bin2hex(random_bytes(16)); // Gera um token de 32 caracteres
        $stmt_token = $pdo->prepare("SELECT id FROM users WHERE token_acesso = ?");
        $stmt_token->execute([$token]);
    } while ($stmt_token->fetch());
    return $token;
}

switch ($method) {
    case 'GET':
        $show_inactive = $_GET['show_inactive'] ?? 'false';
        $whereClause = ($show_inactive === 'true') ? '' : "WHERE status = 'ativo'";
        if ($id) {
            $stmt = $pdo->prepare("SELECT id, nome, login, cargo, status, token_acesso FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
        } else {
            $stmt = $pdo->query("SELECT id, nome, login, cargo, status, token_acesso FROM users $whereClause ORDER BY nome");
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['senha'])) { http_response_code(400); echo json_encode(['message' => 'Senha é obrigatória para novos usuários.']); exit; }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login = ?");
        $stmt->execute([$data['login']]);
        if ($stmt->fetchColumn() > 0) { http_response_code(409); echo json_encode(['message' => 'Este login já está em uso.']); exit; }
        
        $token = gerarTokenUnico($pdo);
        $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (nome, login, senha, cargo, status, token_acesso) VALUES (?, ?, ?, ?, 'ativo', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['nome'], $data['login'], $hash, $data['cargo'], $token]);
        echo json_encode(['message' => 'Usuário criado com sucesso!']);
        break;

    case 'PUT':
        if (!$id) { exit(http_response_code(400)); }
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? 'edit';

        if ($action === 'reactivate') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'ativo' WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['message' => 'Usuário reativado com sucesso!']);
        } 
        elseif ($action === 'regenerate_token') {
            $novoToken = gerarTokenUnico($pdo);
            $stmt = $pdo->prepare("UPDATE users SET token_acesso = ? WHERE id = ?");
            $stmt->execute([$novoToken, $id]);
            echo json_encode(['message' => 'Novo link gerado com sucesso!', 'novoToken' => $novoToken]);
        }
        else { // Ação padrão de edição
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login = ? AND id != ?");
            $stmt->execute([$data['login'], $id]);
            if ($stmt->fetchColumn() > 0) { http_response_code(409); echo json_encode(['message' => 'Este login já está em uso por outro usuário.']); exit; }
            if (!empty($data['senha'])) {
                $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
                $sql = "UPDATE users SET nome = ?, login = ?, cargo = ?, senha = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['nome'], $data['login'], $data['cargo'], $hash, $id]);
            } else {
                $sql = "UPDATE users SET nome = ?, login = ?, cargo = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['nome'], $data['login'], $data['cargo'], $id]);
            }
            echo json_encode(['message' => 'Usuário atualizado com sucesso!']);
        }
        break;

    case 'DELETE': // Inativar
        if (!$id) { exit(http_response_code(400)); }
        $sql = "UPDATE users SET status = 'inativo' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Usuário desativado com sucesso!']);
        break;

    default:
        http_response_code(405);
        break;
}
?>
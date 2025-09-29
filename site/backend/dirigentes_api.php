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
            $stmt = $pdo->prepare("SELECT id, nome, login, cargo, status, token_acesso, telefone, email_contato FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
        } else {
            $stmt = $pdo->query("SELECT id, nome, login, cargo, status, token_acesso, telefone, email_contato FROM users $whereClause ORDER BY nome");
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? 'create'; // Define a ação esperada
        
        if ($id) {
            // LÓGICA DE UPDATE, REATIVAÇÃO, REGENERAÇÃO E DELEÇÃO (Antigos PUT e DELETE)
            
            if ($action === 'reactivate') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'ativo' WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['message' => 'Usuário reativado com sucesso!']);
                exit;
            } 
            
            elseif ($action === 'regenerate_token') {
                $novoToken = gerarTokenUnico($pdo);
                $stmt = $pdo->prepare("UPDATE users SET token_acesso = ? WHERE id = ?");
                $stmt->execute([$novoToken, $id]);
                echo json_encode(['message' => 'Novo link gerado com sucesso!', 'novoToken' => $novoToken]);
                exit;
            }
            
            elseif ($action === 'delete_user') { // Ação para inativar (antigo DELETE)
                $stmt = $pdo->prepare("UPDATE users SET status = 'inativo' WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['message' => 'Usuário desativado com sucesso!']);
                exit;
            }

            elseif ($action === 'update') { // Edição (antigo PUT)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login = ? AND id != ?");
                $stmt->execute([$data['login'], $id]);
                if ($stmt->fetchColumn() > 0) { http_response_code(409); echo json_encode(['message' => 'Este login já está em uso por outro usuário.']); exit; }
                
                if (!empty($data['senha'])) {
                    $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET nome = ?, login = ?, cargo = ?, senha = ?, telefone = ?, email_contato = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$data["nome"], $data["login"], $data["cargo"], $hash, $data["telefone"] ?? null, $data["email_contato"] ?? null, $id]);
                } else {
                    $sql = "UPDATE users SET nome = ?, login = ?, cargo = ?, telefone = ?, email_contato = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$data["nome"], $data["login"], $data["cargo"], $data["telefone"] ?? null, $data["email_contato"] ?? null, $id]);
                }
                echo json_encode(['message' => 'Usuário atualizado com sucesso!']);
                exit;
            }
            
            // Se chegou aqui com ID, mas ação não reconhecida (erro)
            http_response_code(400);
            echo json_encode(['message' => 'Ação de processamento inválida.']);
            exit;

        } else {
            // LÓGICA DE CREATE (Antigo POST)
            if (empty($data['senha'])) { http_response_code(400); echo json_encode(['message' => 'Senha é obrigatória para novos usuários.']); exit; }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login = ?");
            $stmt->execute([$data['login']]);
            if ($stmt->fetchColumn() > 0) { http_response_code(409); echo json_encode(['message' => 'Este login já está em uso.']); exit; }
            
            $token = gerarTokenUnico($pdo);
            $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (nome, login, senha, cargo, status, token_acesso, telefone, email_contato) VALUES (?, ?, ?, ?, 'ativo', ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data["nome"], $data["login"], $hash, $data["cargo"], $token, $data["telefone"] ?? null, $data["email_contato"] ?? null]);
            echo json_encode(['message' => 'Usuário criado com sucesso!']);
        }
        break;

    case 'DELETE':
        // Este bloco agora está obsoleto e não deve ser atingido se o JS foi alterado corretamente
        http_response_code(405); // Método não permitido, pois usamos POST para tudo
        break;

    default:
        http_response_code(405);
        break;
}
?>
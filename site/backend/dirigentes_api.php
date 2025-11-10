<?php
// site/backend/dirigentes_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

// Função auxiliar para gerar um token único
function gerarTokenUnico($pdo) {
    do {
        $token = bin2hex(random_bytes(16));
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
            $stmt = $pdo->prepare("SELECT id, nome, login, permissoes, status, token_acesso, telefone, email_contato FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } else {
            $stmt = $pdo->query("SELECT id, nome, login, permissoes, status, token_acesso, telefone, email_contato FROM users $whereClause ORDER BY nome");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? ($id ? 'update' : 'create');

        if ($id) {
            // LÓGICA DE UPDATE, REATIVAÇÃO, REGENERAÇÃO E DESATIVAÇÃO
            
            if ($action === 'reactivate') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'ativo' WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['message' => 'Usuário reativado com sucesso!']);
            
            } elseif ($action === 'regenerate_token') {
                $novoToken = gerarTokenUnico($pdo);
                $stmt = $pdo->prepare("UPDATE users SET token_acesso = ? WHERE id = ?");
                $stmt->execute([$novoToken, $id]);
                echo json_encode(['message' => 'Novo link gerado com sucesso!', 'novoToken' => $novoToken]);
            
            } elseif ($action === 'delete_user') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'inativo' WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['message' => 'Usuário desativado com sucesso!']);
            
            } elseif ($action === 'update') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login = ? AND id != ?");
                $stmt->execute([$data['login'], $id]);
                if ($stmt->fetchColumn() > 0) {
                    http_response_code(409);
                    echo json_encode(['message' => 'Este login já está em uso por outro usuário.']);
                    exit;
                }
                
                $is_dirigente = ($data["permissoes"] & 1) === 1;
                $token_update_sql = '';
                $params_token = [];

                if ($is_dirigente) {
                    $stmt_check = $pdo->prepare("SELECT token_acesso FROM users WHERE id = ?");
                    $stmt_check->execute([$id]);
                    if (empty($stmt_check->fetchColumn())) {
                        $token_update_sql = ", token_acesso = ?";
                        $params_token[] = gerarTokenUnico($pdo);
                    }
                } else {
                    $token_update_sql = ", token_acesso = NULL";
                }
                
                if (!empty($data['senha'])) {
                    $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET nome = ?, login = ?, permissoes = ?, senha = ?, telefone = ?, email_contato = ? $token_update_sql WHERE id = ?";
                    $params_base = [$data["nome"], $data["login"], $data["permissoes"], $hash, $data["telefone"] ?? null, $data["email_contato"] ?? null];
                } else {
                    $sql = "UPDATE users SET nome = ?, login = ?, permissoes = ?, telefone = ?, email_contato = ? $token_update_sql WHERE id = ?";
                    $params_base = [$data["nome"], $data["login"], $data["permissoes"], $data["telefone"] ?? null, $data["email_contato"] ?? null];
                }
                
                $params = array_merge($params_base, $params_token, [$id]);
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                echo json_encode(['message' => 'Usuário atualizado com sucesso!']);
            
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Ação de processamento inválida.']);
            }
        } else { // LÓGICA DE CREATE
            if (empty($data['senha'])) { http_response_code(400); echo json_encode(['message' => 'Senha é obrigatória para novos usuários.']); exit; }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login = ?");
            $stmt->execute([$data['login']]);
            if ($stmt->fetchColumn() > 0) { http_response_code(409); echo json_encode(['message' => 'Este login já está em uso.']); exit; }
            
            $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
            // Gera token apenas se a permissão de dirigente (bit 1) estiver ativa
            $token_to_save = (($data["permissoes"] & 1) === 1) ? gerarTokenUnico($pdo) : null;
            
            // Query de inserção sem a coluna 'cargo'
            $sql = "INSERT INTO users (nome, login, senha, permissoes, status, token_acesso, telefone, email_contato) VALUES (?, ?, ?, ?, 'ativo', ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            $params = [$data["nome"], $data["login"], $hash, $data["permissoes"], $token_to_save, $data["telefone"] ?? null, $data["email_contato"] ?? null];
            
            $stmt->execute($params);
            
            echo json_encode(['message' => 'Usuário criado com sucesso!', 'id' => $pdo->lastInsertId()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Método não suportado.']);
        break;
}
?>
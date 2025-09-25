<?php
// site/backend/auth_api.php
header('Content-Type: application/json');
require_once 'conexao.php';

// Apenas aceita requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['message' => 'Método não permitido.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$login = $input['login'] ?? '';
$senha = $input['senha'] ?? '';

if (empty($login) || empty($senha)) {
    http_response_code(400); // Bad Request
    echo json_encode(['message' => 'Login e senha são obrigatórios.']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, nome, senha, cargo FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    // Verifica se o usuário existe e se a senha está correta
    if ($user && password_verify($senha, $user['senha'])) {
        // Sucesso na autenticação
        // Não retornar a senha no JSON
        unset($user['senha']);

        echo json_encode([
            'status' => 'success',
            'user' => $user
        ]);
    } else {
        // Falha na autenticação
        http_response_code(401); // Unauthorized
        echo json_encode(['message' => 'Usuário ou senha inválidos.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Erro interno no servidor.']);
}
?>
<?php
// site/backend/auth_api.php

// Inicia a sessão no topo do arquivo, antes de qualquer output.
session_start();

header('Content-Type: application/json');
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Método não permitido.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$login = $input['login'] ?? '';
$senha = $input['senha'] ?? '';

if (empty($login) || empty($senha)) {
    http_response_code(400);
    echo json_encode(['message' => 'Login e senha são obrigatórios.']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, nome, senha, permissoes FROM users WHERE login = ? AND status = 'ativo'");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($senha, $user['senha'])) {
        // ▼▼▼ MUDANÇA IMPORTANTE ▼▼▼
        // Sucesso! Armazena os dados do usuário na sessão.
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nome'] = $user['nome'];
        $_SESSION['permissoes'] = $user['permissoes'];
        // ▲▲▲ FIM DA MUDANÇA ▲▲▲

        unset($user['senha']);
        echo json_encode(['status' => 'success', 'user' => $user]);

    } else {
        http_response_code(401);
        echo json_encode(['message' => 'Usuário ou senha inválidos.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Erro interno no servidor.']);
}
?>
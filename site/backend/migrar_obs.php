<?php
// site/backend/migrar_obs.php
header('Content-Type: application/json');
require_once 'conexao.php';

try {
    $pdo->exec("ALTER TABLE mapas MODIFY COLUMN obs TEXT");
    $pdo->exec("ALTER TABLE mapas_predio MODIFY COLUMN obs TEXT");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Coluna "obs" migrada com sucesso para TEXT nas tabelas "mapas" e "mapas_predio".'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao realizar a migração: ' . $e->getMessage()
    ]);
}

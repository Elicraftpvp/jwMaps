<?php
// site/backend/conexao.php
$host = 'sql208.infinityfree.com';
$dbname = 'if0_40020567_jw_mapas';
$user = 'if0_40020567';
$pass = 'sZe6Hr3xyL7p';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Não foi possível conectar ao banco de dados.'
    ]);
    exit();
}
?>
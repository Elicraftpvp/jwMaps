<?php
// site/backend/gerar_hash.php

// Mude o valor desta variável para a senha que você quer criptografar
$senhaEmTextoPuro = '102030';

// --- NÃO EDITE ABAIXO DESTA LINHA ---

if (php_sapi_name() !== 'cli' && (!isset($_GET['run']) || $_GET['run'] !== 'true')) {
    echo "<h1>Gerador de Hash de Senha</h1>";
    echo "<p>Este script gera um hash seguro para uma senha. Edite o arquivo <strong>gerar_hash.php</strong> e altere a variável <code>\$senhaEmTextoPuro</code> para a senha desejada.</p>";
    echo "<p>Depois, <a href='?run=true'>clique aqui para executar e ver o hash</a>.</p>";
    exit;
}

// Gera o hash usando o algoritmo BCRYPT, que é o padrão recomendado.
$hash = password_hash($senhaEmTextoPuro, PASSWORD_DEFAULT);

header('Content-Type: text/plain');
echo "Senha original: " . htmlspecialchars($senhaEmTextoPuro) . "\n\n";
echo "Hash gerado (copie e cole no campo 'senha' do banco de dados):\n\n";
echo $hash;

?>
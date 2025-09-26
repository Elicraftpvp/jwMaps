<?php
// site/backend/vista_publica.php

// Adicionado para depuração: remove a "página em branco" e mostra o erro real.
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'conexao.php';

// CORREÇÃO: Usando uma forma moderna e segura de obter o token, sem a função obsoleta.
$token = htmlspecialchars($_GET['token'] ?? '');

if (empty($token)) {
    http_response_code(400);
    die("<h1>Acesso Inválido</h1><p>O link de acesso parece estar incompleto ou o token não foi fornecido.</p>");
}

try {
    // Busca o dirigente pelo token e verifica se está ativo
    $stmt_user = $pdo->prepare("SELECT id, nome FROM users WHERE token_acesso = ? AND status = 'ativo' AND cargo = 'dirigente'");
    $stmt_user->execute([$token]);
    $dirigente = $stmt_user->fetch();

    if (!$dirigente) {
        http_response_code(403);
        die("<h1>Acesso Negado</h1><p>Este link é inválido, expirou ou o usuário associado foi desativado. Por favor, solicite um novo link ao administrador.</p>");
    }

    $dirigente_id = $dirigente['id'];

    // Busca os mapas associados a esse dirigente
    $stmt_mapas = $pdo->prepare("SELECT id, identificador, data_entrega, pessoas_faladas FROM mapas WHERE dirigente_id = ? AND data_devolucao IS NULL");
    $stmt_mapas->execute([$dirigente_id]);
    $mapas = $stmt_mapas->fetchAll();

} catch (PDOException $e) {
    http_response_code(500);
    // Em produção, seria melhor logar o erro: error_log($e->getMessage());
    die("Erro ao consultar o banco de dados. Por favor, contate o administrador.");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Mapas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../style/css.css">
    <style>
        body { padding: 15px; background-color: var(--content-bg); }
        .navbar-brand { font-weight: bold; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark mb-4 rounded shadow-sm">
        <div class="container-fluid">
            <span class="navbar-brand"><i class="fas fa-map-marked-alt me-2"></i>Mapas de <?php echo htmlspecialchars($dirigente['nome']); ?></span>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div id="alert-container"></div>
        <div class="row">
            <?php if (empty($mapas)): ?>
                <div class="col-12"><div class="alert alert-info text-center"><i class="fas fa-info-circle me-2"></i>Você não possui nenhum mapa atribuído no momento.</div></div>
            <?php else: ?>
                <?php foreach ($mapas as $mapa): ?>
                    <div class="col-lg-6 mb-4" id="mapa-card-<?php echo $mapa['id']; ?>">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-primary text-white"><h5 class="card-title mb-0"><i class="fas fa-map-pin me-2"></i> <?php echo htmlspecialchars($mapa['identificador']); ?></h5></div>
                            
                            <div class="p-3">
                                <a href="pdfs/<?php echo rawurlencode($mapa['identificador']) . ".pdf"; ?>" target="_blank" class="btn btn-secondary d-block">
                                    <i class="fas fa-file-pdf me-2"></i> Visualizar Mapa (PDF)
                                </a>
                            </div>

                            <div class="card-body">
                                <form class="form-devolver" data-mapa-id="<?php echo $mapa['id']; ?>">
                                    <div class="row align-items-center mb-3">
                                        <div class="col-8"><label for="pessoas_faladas_<?php echo $mapa['id']; ?>" class="form-label fw-bold">Pessoas Faladas:</label><input type="number" class="form-control form-control-lg text-center pessoas-faladas-input" id="pessoas_faladas_<?php echo $mapa['id']; ?>" min="0" value="<?php echo $mapa['pessoas_faladas']; ?>" data-previous-value="<?php echo $mapa['pessoas_faladas']; ?>" data-mapa-id="<?php echo $mapa['id']; ?>" required></div>
                                        <div class="col-4 text-end"><small class="text-muted status-save" id="status_save_<?php echo $mapa['id']; ?>"></small></div>
                                    </div>
                                    <hr>
                                    <p class="mb-2"><strong>Recebido em:</strong> <?php echo date('d/m/Y', strtotime($mapa['data_entrega'])); ?></p>
                                    <div class="mb-3"><label for="data_devolucao_<?php echo $mapa['id']; ?>" class="form-label">Data de Devolução:</label><input type="date" class="form-control" id="data_devolucao_<?php echo $mapa['id']; ?>" value="<?php echo date('Y-m-d'); ?>" required></div>
                                    <div class="d-grid"><button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i> Marcar como Devolvido</button></div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // CORREÇÃO: SCRIPT COMPLETO E FUNCIONAL ABAIXO
    document.addEventListener('DOMContentLoaded', () => {
        // API está na mesma pasta, então o caminho base é '.'
        const API_BASE_URL = '.'; 
        const alertContainer = document.getElementById('alert-container');

        // Lógica de DEVOLUÇÃO do mapa
        document.querySelectorAll('.form-devolver').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const mapaId = e.target.dataset.mapaId;
                const pessoasFaladas = document.getElementById(`pessoas_faladas_${mapaId}`).value;
                const dataDevolucao = document.getElementById(`data_devolucao_${mapaId}`).value;
                if (!confirm('Tem certeza que deseja devolver este mapa? Esta ação não pode ser desfeita.')) return;
                try {
                    const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'devolver', mapa_id: mapaId, pessoas_faladas: pessoasFaladas, data_devolucao: dataDevolucao })
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'Erro ao devolver o mapa.');
                    alertContainer.innerHTML = `<div class="alert alert-success alert-dismissible fade show" role="alert">${result.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
                    document.getElementById(`mapa-card-${mapaId}`).remove();
                } catch (error) {
                    alertContainer.innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">Erro: ${error.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
                }
            });
        });

        // Lógica de AUTO-SAVE das pessoas faladas
        const savePessoasFaladas = async (mapaId, valor) => {
            const statusDiv = document.getElementById(`status_save_${mapaId}`);
            statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_pessoas', mapa_id: mapaId, pessoas_faladas: valor })
                });
                if (!response.ok) throw new Error('Falha ao salvar');
                statusDiv.innerHTML = '<i class="fas fa-check-circle text-success"></i> Salvo';
                setTimeout(() => { statusDiv.innerHTML = ''; }, 2000);
                return true;
            } catch (error) {
                statusDiv.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Erro';
                return false;
            }
        };

        document.querySelectorAll('.pessoas-faladas-input').forEach(input => {
            input.addEventListener('change', async (e) => {
                const valorAtual = parseInt(e.target.value);
                const valorAnterior = parseInt(e.target.dataset.previousValue);
                const mapaId = e.target.dataset.mapaId;
                if (valorAtual < valorAnterior) {
                    if (!confirm('Você está diminuindo o número de pessoas. Deseja continuar?')) {
                        e.target.value = valorAnterior;
                        return;
                    }
                }
                const success = await savePessoasFaladas(mapaId, valorAtual);
                if (success) {
                    e.target.dataset.previousValue = valorAtual;
                }
            });
        });
    });
    </script>
</body>
</html>
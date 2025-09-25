<?php
// site/backend/vista_dirigente.php
require_once 'conexao.php';

// Pega o ID do dirigente da URL
$dirigente_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$dirigente_id) {
    die("ID de dirigente inválido.");
}

try {
    // Busca o nome do dirigente
    $stmt_user = $pdo->prepare("SELECT nome FROM users WHERE id = ? AND cargo = 'dirigente'");
    $stmt_user->execute([$dirigente_id]);
    $dirigente = $stmt_user->fetch();

    if (!$dirigente) {
        die("Dirigente não encontrado.");
    }

    // Busca os mapas associados a esse dirigente
    $stmt_mapas = $pdo->prepare("SELECT id, identificador, data_entrega FROM mapas WHERE dirigente_id = ? AND data_devolucao IS NULL");
    $stmt_mapas->execute([$dirigente_id]);
    $mapas = $stmt_mapas->fetchAll();

} catch (PDOException $e) {
    die("Erro ao consultar o banco de dados: " . $e->getMessage());
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
        .map-card { transition: all 0.2s ease-in-out; }
        .map-card:hover { transform: translateY(-5px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="iframe-page">

    <div class="container-fluid">
        <h2 class="mb-4">Meus Mapas - <?php echo htmlspecialchars($dirigente['nome']); ?></h2>
        
        <div id="alert-container"></div>

        <div class="row">
            <?php if (empty($mapas)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i> Você não possui nenhum mapa atribuído no momento.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($mapas as $mapa): ?>
                    <div class="col-md-6 col-lg-4 mb-4" id="mapa-card-<?php echo $mapa['id']; ?>">
                        <div class="card shadow-sm map-card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-map-pin me-2"></i> <?php echo htmlspecialchars($mapa['identificador']); ?></h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Recebido em:</strong> <?php echo date('d/m/Y', strtotime($mapa['data_entrega'])); ?></p>
                                
                                <form class="form-devolver" data-mapa-id="<?php echo $mapa['id']; ?>">
                                    <div class="mb-3">
                                        <label for="pessoas_faladas_<?php echo $mapa['id']; ?>" class="form-label">Pessoas Faladas:</label>
                                        <input type="number" class="form-control" id="pessoas_faladas_<?php echo $mapa['id']; ?>" min="0" value="0" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="data_devolucao_<?php echo $mapa['id']; ?>" class="form-label">Data de Devolução:</label>
                                        <input type="date" class="form-control" id="data_devolucao_<?php echo $mapa['id']; ?>" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check-circle me-2"></i> Marcar como Devolvido
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="../script/common.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const alertContainer = document.getElementById('alert-container');

        document.querySelectorAll('.form-devolver').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const mapaId = e.target.dataset.mapaId;
                const pessoasFaladas = document.getElementById(`pessoas_faladas_${mapaId}`).value;
                const dataDevolucao = document.getElementById(`data_devolucao_${mapaId}`).value;

                if (!confirm('Tem certeza que deseja devolver este mapa? Esta ação não pode ser desfeita.')) {
                    return;
                }

                try {
                    const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'devolver',
                            mapa_id: mapaId,
                            pessoas_faladas: pessoasFaladas,
                            data_devolucao: dataDevolucao
                        })
                    });

                    const result = await response.json();
                    if (!response.ok) {
                        throw new Error(result.message || 'Erro ao devolver o mapa.');
                    }
                    
                    // Mostra alerta de sucesso
                    alertContainer.innerHTML = `<div class="alert alert-success alert-dismissible fade show" role="alert">
                        ${result.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;

                    // Remove o card do mapa da tela
                    document.getElementById(`mapa-card-${mapaId}`).remove();

                } catch (error) {
                    alertContainer.innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        Erro: ${error.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
                }
            });
        });
    });
    </script>
</body>
</html>
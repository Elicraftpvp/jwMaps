<?php
// site/backend/vista_dirigente.php
require_once 'conexao.php';

$dirigente_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$dirigente_id) die("ID de dirigente inválido.");

try {
    $stmt_user = $pdo->prepare("SELECT nome FROM users WHERE id = ? AND cargo = 'dirigente'");
    $stmt_user->execute([$dirigente_id]);
    $dirigente = $stmt_user->fetch();
    if (!$dirigente) die("Dirigente não encontrado.");

    $stmt_mapas = $pdo->prepare("SELECT id, identificador, data_entrega, pessoas_faladas FROM mapas WHERE dirigente_id = ? AND data_devolucao IS NULL");
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
</head>
<body class="iframe-page">

    <div class="container-fluid">
        <h2 class="mb-4">Meus Mapas - <?php echo htmlspecialchars($dirigente['nome']); ?></h2>
        
        <div id="alert-container"></div>

        <div class="row">
            <?php if (empty($mapas)): ?>
                <div class="col-12"><div class="alert alert-info text-center"><i class="fas fa-info-circle me-2"></i>Você não possui nenhum mapa atribuído no momento.</div></div>
            <?php else: ?>
                <?php foreach ($mapas as $mapa): ?>
                    <div class="col-lg-6 mb-4" id="mapa-card-<?php echo $mapa['id']; ?>">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-primary text-white"><h5 class="card-title mb-0"><i class="fas fa-map-pin me-2"></i> <?php echo htmlspecialchars($mapa['identificador']); ?></h5></div>
                            
                            <?php
                                $pdf_filename = htmlspecialchars($mapa['identificador']) . ".pdf";
                                $pdf_path_relative = "pdfs/" . $pdf_filename;
                                $pdf_path_server = __DIR__ . "/pdfs/" . $pdf_filename;
                            ?>

                            <?php if (file_exists($pdf_path_server)): ?>
                                <!-- Versão para DESKTOP (embutido) -->
                                <div class="pdf-desktop-viewer pdf-viewer-container" data-bs-toggle="modal" data-bs-target="#pdfModal" data-pdf-src="<?php echo $pdf_path_relative; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                                    <embed src="<?php echo $pdf_path_relative; ?>" type="application/pdf" width="100%" height="250px" />
                                    <div class="pdf-overlay"><i class="fas fa-search-plus"></i> Ampliar Mapa</div>
                                </div>
                                <!-- Versão para MOBILE (botão de link) -->
                                <div class="pdf-mobile-link p-3">
                                    <a href="<?php echo $pdf_path_relative; ?>" target="_blank" class="btn btn-secondary d-block">
                                        <i class="fas fa-file-pdf me-2"></i> Visualizar Mapa (PDF)
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-3 text-muted"><i class="fas fa-exclamation-triangle me-2"></i> Arquivo do mapa não encontrado.</div>
                            <?php endif; ?>

                            <div class="card-body">
                                <form class="form-devolver" data-mapa-id="<?php echo $mapa['id']; ?>">
                                    <div class="row align-items-center mb-3">
                                        <div class="col-8">
                                            <label for="pessoas_faladas_<?php echo $mapa['id']; ?>" class="form-label fw-bold">Pessoas Faladas:</label>
                                            <input type="number" class="form-control form-control-lg text-center pessoas-faladas-input" id="pessoas_faladas_<?php echo $mapa['id']; ?>" min="0" value="<?php echo $mapa['pessoas_faladas']; ?>" data-previous-value="<?php echo $mapa['pessoas_faladas']; ?>" data-mapa-id="<?php echo $mapa['id']; ?>" required>
                                        </div>
                                        <div class="col-4 text-end"><small class="text-muted status-save" id="status_save_<?php echo $mapa['id']; ?>"></small></div>
                                    </div>
                                    <hr>
                                    <p class="mb-2"><strong>Recebido em:</strong> <?php echo date('d/m/Y', strtotime($mapa['data_entrega'])); ?></p>
                                    <div class="mb-3">
                                        <label for="data_devolucao_<?php echo $mapa['id']; ?>" class="form-label">Data de Devolução:</label>
                                        <input type="date" class="form-control" id="data_devolucao_<?php echo $mapa['id']; ?>" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="d-grid"><button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i> Marcar como Devolvido</button></div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal para PDF (usado apenas em DESKTOP) -->
    <div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfModalLabel">Visualizador de Mapa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" id="pdf-modal-body" style="height: 85vh;"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../script/common.js"></script>
    <script>
    // O SCRIPT ABAIXO CONTINUA O MESMO DA VERSÃO ANTERIOR
    // NENHUMA MUDANÇA É NECESSÁRIA AQUI.
    document.addEventListener('DOMContentLoaded', () => {
        const alertContainer = document.getElementById('alert-container');
        document.querySelectorAll('.form-devolver').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const mapaId = e.target.dataset.mapaId;
                const pessoasFaladas = document.getElementById(`pessoas_faladas_${mapaId}`).value;
                const dataDevolucao = document.getElementById(`data_devolucao_${mapaId}`).value;
                if (!confirm('Tem certeza que deseja devolver este mapa?')) return;
                try {
                    const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'devolver', mapa_id: mapaId, pessoas_faladas: pessoasFaladas, data_devolucao: dataDevolucao })
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message);
                    alertContainer.innerHTML = `<div class="alert alert-success alert-dismissible fade show" role="alert">${result.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
                    document.getElementById(`mapa-card-${mapaId}`).remove();
                } catch (error) {
                    alertContainer.innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">Erro: ${error.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
                }
            });
        });

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

        const pdfModal = document.getElementById('pdfModal');
        const pdfModalBody = document.getElementById('pdf-modal-body');
        const pdfModalTitle = document.getElementById('pdfModalLabel');
        pdfModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const pdfSrc = button.getAttribute('data-pdf-src');
            const pdfTitle = button.getAttribute('data-pdf-title');
            pdfModalTitle.textContent = pdfTitle;
            pdfModalBody.innerHTML = `<embed src="${pdfSrc}" type="application/pdf" style="width:100%; height:100%;" />`;
        });
        pdfModal.addEventListener('hidden.bs.modal', function () {
            pdfModalBody.innerHTML = '';
        });
    });
    </script>
</body>
</html>
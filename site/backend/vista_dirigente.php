<?php
// site/backend/vista_dirigente.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'conexao.php';

// 1. Pega o ID diretamente da URL.
$dirigente_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$dirigente_id) {
    die("<h1>ERRO</h1><p>É necessário especificar o ID de um dirigente na URL. Exemplo: <code>vista_dirigente.php?id=1</code></p>");
}

try {
    // 2. Busca o nome do dirigente para mostrar no título.
    $stmt_user = $pdo->prepare("SELECT nome FROM users WHERE id = ?");
    $stmt_user->execute([$dirigente_id]);
    $dirigente = $stmt_user->fetch();
    if (!$dirigente) {
        http_response_code(404);
        die("<h1>Dirigente com ID $dirigente_id não encontrado.</h1>");
    }
    
    // 3. Busca os mapas atribuídos a este ID que ainda não foram devolvidos.
    $stmt_mapas = $pdo->prepare(
        "SELECT id, identificador, data_entrega 
         FROM mapas 
         WHERE dirigente_id = ? AND data_devolucao IS NULL 
         ORDER BY identificador ASC"
    );
    $stmt_mapas->execute([$dirigente_id]);
    $mapas = $stmt_mapas->fetchAll(PDO::FETCH_ASSOC);

    // 4. Busca todas as quadras para os mapas encontrados.
    $quadras_por_mapa = [];
    if (!empty($mapas)) {
        $mapa_ids = array_column($mapas, 'id');
        $placeholders = implode(',', array_fill(0, count($mapa_ids), '?'));
        
        $stmt_quadras = $pdo->prepare(
            "SELECT id, mapa_id, numero, pessoas_faladas 
             FROM quadras 
             WHERE mapa_id IN ($placeholders) 
             ORDER BY numero ASC"
        );
        $stmt_quadras->execute($mapa_ids);
        $quadras_data = $stmt_quadras->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($quadras_data as $quadra) {
            $quadras_por_mapa[$quadra['mapa_id']][] = $quadra;
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    die("Erro ao conectar ou consultar o banco de dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapas de <?php echo htmlspecialchars($dirigente['nome']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../style/css.css">
    <style>
        .iframe-page { padding: 15px; background-color: var(--content-bg); }
        .quadra-item { border-bottom: 1px solid #eee; }
        .quadra-item:last-child { border-bottom: none; }
    </style>
</head>
<body class="iframe-page">

    <div class="container-fluid">
        <h2 class="mb-4">Mapas de <?php echo htmlspecialchars($dirigente['nome']); ?></h2>
        
        <div id="alert-container"></div>

        <div class="row">
            <?php if (empty($mapas)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>Este dirigente não possui nenhum mapa atribuído no momento.
                    </div>
                </div>
            <?php else: foreach ($mapas as $mapa): ?>
                <div class="col-lg-6 mb-4" id="mapa-card-<?php echo $mapa['id']; ?>">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0"><i class="fas fa-map-pin me-2"></i> <?php echo htmlspecialchars($mapa['identificador']); ?></h5>
                        </div>

                        <?php // ----- INÍCIO DO BLOCO DE PDF RESTAURADO -----
                            $pdf_filename = $mapa['identificador'] . ".pdf";
                            $pdf_path_relative = "pdfs/" . rawurlencode($pdf_filename); // Caminho para o navegador
                            $pdf_path_server = __DIR__ . "/pdfs/" . $pdf_filename;     // Caminho para o PHP verificar se o arquivo existe
                        ?>
                        <?php if (file_exists($pdf_path_server)): ?>
                            <!-- Versão para DESKTOP (embutido que abre modal) -->
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
                            <div class="text-center p-3 text-muted border-bottom"><i class="fas fa-exclamation-triangle me-2"></i> Arquivo do mapa não encontrado.</div>
                        <?php endif; ?>
                        <?php // ----- FIM DO BLOCO DE PDF RESTAURADO ----- ?>

                        <div class="card-body">
                            <form class="form-devolver" data-mapa-id="<?php echo $mapa['id']; ?>">
                                <label class="form-label fw-bold">Registro por Quadra:</label>
                                <div class="list-group mb-3" style="max-height: 250px; overflow-y: auto;">
                                    <?php if (!empty($quadras_por_mapa[$mapa['id']])): foreach ($quadras_por_mapa[$mapa['id']] as $quadra): ?>
                                        <div class="list-group-item quadra-item d-flex justify-content-between align-items-center p-2">
                                            <span>Quadra <strong><?php echo htmlspecialchars($quadra['numero']); ?></strong></span>
                                            <div class="d-flex align-items-center">
                                                <input type="number" class="form-control form-control-sm text-center quadra-input" style="width: 80px;"
                                                    value="<?php echo htmlspecialchars($quadra['pessoas_faladas']); ?>" data-quadra-id="<?php echo $quadra['id']; ?>" min="0">
                                                <small class="text-muted status-save ms-2" id="status_save_q<?php echo $quadra['id']; ?>"></small>
                                            </div>
                                        </div>
                                    <?php endforeach; else: ?>
                                        <div class="list-group-item text-muted">Nenhuma quadra encontrada para este mapa.</div>
                                    <?php endif; ?>
                                </div>
                                <hr>
                                <p class="mb-2"><strong>Recebido em:</strong> <?php echo date('d/m/Y', strtotime($mapa['data_entrega'])); ?></p>
                                <div class="mb-3">
                                    <label for="data_devolucao_<?php echo $mapa['id']; ?>" class="form-label">Data de Devolução:</label>
                                    <input type="date" class="form-control" id="data_devolucao_<?php echo $mapa['id']; ?>" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i> Devolver Mapa Completo</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
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
    document.addEventListener('DOMContentLoaded', () => {
        const alertContainer = document.getElementById('alert-container');
        // A API_BASE_URL deve ser definida em common.js ou aqui
        const API_BASE_URL = '.'; 

        const showAlert = (message, type = 'success') => {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = [
                `<div class="alert alert-${type} alert-dismissible fade show" role="alert">`,
                `   <div>${message}</div>`,
                '   <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>',
                '</div>'
            ].join('');
            alertContainer.innerHTML = ''; // Limpa alertas anteriores
            alertContainer.append(wrapper);
        };

        const saveQuadra = async (quadraId, valor, statusDiv) => {
            statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_quadra', quadra_id: quadraId, pessoas_faladas: valor })
                });
                if (!response.ok) {
                    const errorResult = await response.json();
                    throw new Error(errorResult.message || 'Falha na comunicação com o servidor.');
                }
                statusDiv.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
                setTimeout(() => { statusDiv.innerHTML = ''; }, 2000);
            } catch (error) {
                statusDiv.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';
                console.error('Erro ao salvar quadra:', error);
            }
        };

        document.querySelectorAll('.quadra-input').forEach(input => {
            input.addEventListener('change', (e) => {
                const quadraId = e.target.dataset.quadraId;
                const statusDiv = document.getElementById(`status_save_q${quadraId}`);
                saveQuadra(quadraId, e.target.value, statusDiv);
            });
        });

        document.querySelectorAll('.form-devolver').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const mapaId = e.target.dataset.mapaId;
                const dataDevolucao = document.getElementById(`data_devolucao_${mapaId}`).value;
                if (!confirm('Tem certeza que deseja devolver este mapa? A ação não pode ser desfeita.')) return;
                
                try {
                    const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'devolver', mapa_id: mapaId, data_devolucao: dataDevolucao })
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        throw new Error(result.message || 'Ocorreu um erro desconhecido.');
                    }
                    showAlert(result.message, 'success');
                    document.getElementById(`mapa-card-${mapaId}`).remove();

                    if (document.querySelectorAll('.form-devolver').length === 1) { // se era o último
                        document.querySelector('.row').innerHTML = `
                            <div class="col-12">
                                <div class="alert alert-info text-center">
                                    <i class="fas fa-info-circle me-2"></i>Este dirigente não possui nenhum mapa atribuído no momento.
                                </div>
                            </div>`;
                    }

                } catch (error) {
                    showAlert(`Erro ao devolver o mapa: ${error.message}`, 'danger');
                }
            });
        });

        // ----- SCRIPT DO MODAL DE PDF RESTAURADO -----
        const pdfModal = document.getElementById('pdfModal');
        if (pdfModal) {
            const pdfModalBody = document.getElementById('pdf-modal-body');
            const pdfModalTitle = document.getElementById('pdfModalLabel');
            pdfModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const pdfSrc = button.getAttribute('data-pdf-src');
                const pdfTitle = button.getAttribute('data-pdf-title');
                pdfModalTitle.textContent = "Mapa: " + pdfTitle;
                pdfModalBody.innerHTML = `<embed src="${pdfSrc}" type="application/pdf" style="width:100%; height:100%;" />`;
            });
            pdfModal.addEventListener('hidden.bs.modal', function () {
                pdfModalBody.innerHTML = ''; // Limpa o conteúdo para não consumir memória
            });
        }
    });
    </script>
</body>
</html>
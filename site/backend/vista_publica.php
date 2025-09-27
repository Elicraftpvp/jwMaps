<?php
// site/backend/vista_publica.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'conexao.php';

// Validação do Token
$token = htmlspecialchars($_GET['token'] ?? '');
if (empty($token)) {
    http_response_code(400);
    die("<h1>Acesso Inválido</h1>");
}

try {
    // Busca o dirigente pelo token
    $stmt_user = $pdo->prepare("SELECT id, nome FROM users WHERE token_acesso = ? AND status = 'ativo' AND cargo = 'dirigente'");
    $stmt_user->execute([$token]);
    $dirigente = $stmt_user->fetch();
    if (!$dirigente) {
        http_response_code(403);
        die("<h1>Acesso Negado</h1><p>Link inválido ou expirado.</p>");
    }
    $dirigente_id = $dirigente['id'];
    
    // Puxar o novo campo 'gdrive_file_id' do banco de dados
    $stmt_mapas = $pdo->prepare("SELECT id, identificador, data_entrega, gdrive_file_id FROM mapas WHERE dirigente_id = ? AND data_devolucao IS NULL");
    $stmt_mapas->execute([$dirigente_id]);
    $mapas = $stmt_mapas->fetchAll();

    $quadras_por_mapa = [];
    if (!empty($mapas)) {
        $mapa_ids = array_column($mapas, 'id');
        $placeholders = implode(',', array_fill(0, count($mapa_ids), '?'));
        
        $stmt_quadras = $pdo->prepare("SELECT id, mapa_id, numero, pessoas_faladas FROM quadras WHERE mapa_id IN ($placeholders) ORDER BY numero ASC");
        $stmt_quadras->execute($mapa_ids);
        $quadras_data = $stmt_quadras->fetchAll();
        
        foreach ($quadras_data as $quadra) {
            $quadras_por_mapa[$quadra['mapa_id']][] = $quadra;
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    die("Erro no banco de dados.");
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
        .quadra-item { border-bottom: 1px solid #eee; }
        .quadra-item:last-child { border-bottom: none; }
        .no-spinners::-webkit-outer-spin-button,
        .no-spinners::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .no-spinners { -moz-appearance: textfield; }
        
        .pdf-preview-container {
            position: relative;
            height: 300px;
            background-color: #f0f0f0;
            border-bottom: 1px solid #dee2e6;
        }
        .pdf-preview-container iframe { width: 100%; height: 100%; border: none; }
        .pdf-preview-container .btn-expand { position: absolute; top: 8px; right: 8px; z-index: 10; }

        /* MODIFICAÇÃO 1: CSS para expandir o modal e o conteúdo do PDF */
        #pdfModal .modal-dialog {
            max-width: 95%;
            height: 95vh;
            margin-top: 2.5vh;
            margin-bottom: 2.5vh;
        }
        #pdfModal .modal-content {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        #pdfModal .modal-body {
            flex-grow: 1; /* Faz o corpo do modal ocupar todo o espaço restante */
            padding: 0;
            overflow: hidden; /* Evita barras de rolagem duplas */
        }
        #pdfModal iframe { width: 100%; height: 100%; border: none; }

        @media (max-width: 768px) { body { zoom: 1.3; } }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark mb-4 rounded shadow-sm">
        <div class="container-fluid"><span class="navbar-brand"><i class="fas fa-map-marked-alt me-2"></i>Mapas de <?php echo htmlspecialchars($dirigente['nome']); ?></span></div>
    </nav>
    <div class="container-fluid">
        <div id="alert-container"></div>
        <div class="row">
        <?php if (empty($mapas)): ?>
            <div class="col-12"><div class="alert alert-info text-center">Você não possui nenhum mapa atribuído.</div></div>
        <?php else: foreach ($mapas as $mapa): ?>
            <div class="col-lg-6 mb-4" id="mapa-card-<?php echo $mapa['id']; ?>">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-map-pin me-2"></i> <?php echo htmlspecialchars($mapa['identificador']); ?></h5>
                    </div>
                    
                    <?php
                    if (!empty($mapa['gdrive_file_id'])):
                        $pdf_embed_url = "https://drive.google.com/file/d/" . $mapa['gdrive_file_id'] . "/preview";
                    ?>
                        <div class="pdf-preview-container">
                            <iframe src="<?php echo $pdf_embed_url; ?>"></iframe>
                            <button class="btn btn-primary btn-sm btn-expand" data-bs-toggle="modal" data-bs-target="#pdfModal" data-pdf-src="<?php echo $pdf_embed_url; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                                <i class="fas fa-expand-alt me-1"></i> Expandir
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-3 text-muted border-bottom"><i class="fas fa-exclamation-triangle me-2"></i> Mapa não encontrado no Drive.</div>
                    <?php endif; ?>

                    <div class="card-body">
                        <form class="form-devolver" data-mapa-id="<?php echo $mapa['id']; ?>">
                            <label class="form-label fw-bold">Registro por Quadra:</label>
                            <div class="d-flex justify-content-end px-2 pb-1"> <small class="fw-bold text-muted" style="width: 140px; text-align: center;">Nº Pessoas</small> </div>
                            <div class="list-group list-group-flush mb-3 quadra-list" data-mapa-id="<?php echo $mapa['id']; ?>">
                            <?php if (isset($quadras_por_mapa[$mapa['id']])): foreach ($quadras_por_mapa[$mapa['id']] as $quadra): ?>
                                <div class="list-group-item quadra-item d-flex justify-content-between align-items-center p-2">
                                    <span>Quadra <strong><?php echo htmlspecialchars($quadra['numero']); ?></strong></span>
                                    <div class="d-flex align-items-center">
                                        <div class="input-group input-group-sm" style="width: 120px;">
                                            <button class="btn btn-outline-secondary btn-decrement" type="button">-</button>
                                            <input type="number" class="form-control text-center quadra-input no-spinners" value="<?php echo htmlspecialchars($quadra['pessoas_faladas']); ?>" data-quadra-id="<?php echo $quadra['id']; ?>" min="0" aria-label="Pessoas faladas">
                                            <button class="btn btn-outline-secondary btn-increment" type="button">+</button>
                                        </div>
                                        <small class="text-muted status-save ms-2" style="width: 20px;" id="status_save_q<?php echo $quadra['id']; ?>"></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center p-2 border-top fw-bold bg-light"> <span>Total</span> <span class="fs-5" id="total-pessoas-mapa-<?php echo $mapa['id']; ?>">0</span> </div>
                            <?php else: ?>
                                <div class="list-group-item text-muted">Nenhuma quadra para este mapa.</div>
                            <?php endif; ?>
                            </div>
                            <hr>
                            <p class="mb-2"><strong>Recebido em:</strong> <?php echo date('d/m/Y', strtotime($mapa['data_entrega'])); ?></p>
                            <div class="mb-3"><label for="data_devolucao_<?php echo $mapa['id']; ?>" class="form-label">Data de Devolução:</label><input type="date" class="form-control" id="data_devolucao_<?php echo $mapa['id']; ?>" value="<?php echo date('Y-m-d'); ?>" required></div>
                            <div class="d-grid"><button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i> Devolver Mapa Completo</button></div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Modal para visualização do PDF -->
    <div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfModalLabel">Visualizador de Mapa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="pdf-modal-body">
                    <!-- O Iframe será inserido aqui pelo JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../script/common.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const API_BASE_URL = '.'; 
        
        const saveQuadra = async (quadraId, valor, statusDiv) => {
            statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                // MODIFICAÇÃO 2: Voltando a enviar JSON, que é o que a API espera
                const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_quadra', quadra_id: quadraId, pessoas_faladas: parseInt(valor) })
                });

                if (!response.ok) {
                    throw new Error(`Erro na rede: ${response.status} ${response.statusText}`);
                }

                const result = await response.json();
                
                // MODIFICAÇÃO 3: Corrigindo a verificação de sucesso para 'status', como a API retorna
                if (result.status === 'success') {
                    statusDiv.innerHTML = '<i class="fas fa-check text-success"></i>';
                    setTimeout(() => { statusDiv.innerHTML = ''; }, 2000);
                } else { 
                    throw new Error(result.message || 'A API retornou um erro inesperado.'); 
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                showAlert(`Erro ao salvar: ${error.message}`, 'danger');
                statusDiv.innerHTML = '<i class="fas fa-times text-danger"></i>';
                setTimeout(() => { statusDiv.innerHTML = ''; }, 3000);
            }
        };

        const debounce = (func, wait) => { let timeout; return (...args) => { clearTimeout(timeout); timeout = setTimeout(() => func.apply(this, args), wait); }; };
        const debouncedSave = debounce(saveQuadra, 800);
        
        document.querySelectorAll('.quadra-input').forEach(input => {
            input.addEventListener('input', (e) => {
                const quadraId = e.target.dataset.quadraId;
                const statusDiv = document.getElementById(`status_save_q${quadraId}`);
                debouncedSave(quadraId, e.target.value, statusDiv);
                updateTotal(e.target.closest('.quadra-list'));
            });
        });
        
        document.querySelectorAll('.btn-increment').forEach(btn => {
            btn.addEventListener('click', (e) => { const input = e.target.closest('.input-group').querySelector('.quadra-input'); input.value = parseInt(input.value || 0) + 1; input.dispatchEvent(new Event('input', { bubbles: true })); });
        });
        
        document.querySelectorAll('.btn-decrement').forEach(btn => {
            btn.addEventListener('click', (e) => { const input = e.target.closest('.input-group').querySelector('.quadra-input'); const currentValue = parseInt(input.value || 0); if (currentValue > 0) { input.value = currentValue - 1; input.dispatchEvent(new Event('input', { bubbles: true })); } });
        });
        
        const updateTotal = (quadraList) => { const mapaId = quadraList.dataset.mapaId; let total = 0; quadraList.querySelectorAll('.quadra-input').forEach(input => { total += parseInt(input.value) || 0; }); document.getElementById(`total-pessoas-mapa-${mapaId}`).textContent = total; };
        
        document.querySelectorAll('.quadra-list').forEach(list => updateTotal(list));
        
        document.querySelectorAll('.form-devolver').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const mapaId = e.target.dataset.mapaId;
                const dataDevolucao = document.getElementById(`data_devolucao_${mapaId}`).value;
                if (!dataDevolucao) { showAlert('Por favor, selecione a data de devolução.', 'warning'); return; }
                const btn = e.target.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processando...';
                btn.disabled = true;
                try {
                    // MODIFICAÇÃO 2 (Repetida): Voltando a enviar JSON
                    const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'devolver_mapa', mapa_id: mapaId, data_devolucao: dataDevolucao })
                    });
                    
                    if (!response.ok) {
                        const errorData = await response.json().catch(() => null); // Tenta pegar erro da API
                        throw new Error(errorData?.message || `Erro na rede: ${response.statusText}`);
                    }

                    const result = await response.json();

                    // MODIFICAÇÃO 3 (Repetida): Verificando se a resposta contém uma mensagem de sucesso
                    if (result.message) { 
                        showAlert('Mapa devolvido com sucesso!', 'success'); 
                        document.getElementById(`mapa-card-${mapaId}`).remove(); 
                    } else { 
                        throw new Error(result.message || 'A API retornou uma resposta inesperada.'); 
                    }
                } catch (error) { 
                    console.error('Erro ao devolver mapa:', error); 
                    showAlert('Erro ao devolver o mapa: ' + error.message, 'danger'); 
                } finally { 
                    btn.innerHTML = originalText; 
                    btn.disabled = false; 
                }
            });
        });

        const pdfModal = document.getElementById('pdfModal');
        if (pdfModal) {
            pdfModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const pdfSrc = button.getAttribute('data-pdf-src');
                const pdfTitle = button.getAttribute('data-pdf-title');
                
                pdfModal.querySelector('.modal-title').textContent = pdfTitle;
                const modalBody = pdfModal.querySelector('#pdf-modal-body');
                modalBody.innerHTML = ''; 

                if (pdfSrc) {
                    const iframe = document.createElement('iframe');
                    iframe.src = pdfSrc;
                    // O CSS na tag <style> já cuida do tamanho
                    modalBody.appendChild(iframe);
                } else {
                    modalBody.innerHTML = '<div class="alert alert-danger m-3">URL do PDF não encontrada.</div>';
                }
            });
        }
        
        const showAlert = (message, type) => {
            const alertContainer = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            alertContainer.appendChild(alert);
            setTimeout(() => { alert.remove(); }, 5000);
        };
    });
    </script>
</body>
</html>
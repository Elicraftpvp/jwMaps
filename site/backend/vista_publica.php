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
    // Busca o dirigente pelo token e pela permissão de Dirigente (bit 1)
    $stmt_user = $pdo->prepare("SELECT id, nome FROM users WHERE token_acesso = ? AND status = 'ativo' AND (permissoes & 1) = 1");
    
    $stmt_user->execute([$token]);
    $dirigente = $stmt_user->fetch();
    if (!$dirigente) {
        http_response_code(403);
        die("<h1>Acesso Negado</h1><p>Link inválido ou expirado.</p>");
    }
    $dirigente_id = $dirigente['id'];
    
    // Puxar mapas atribuídos e não devolvidos
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
    <link rel="icon" type="image/png" href="../images/map.png">
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
            flex-grow: 1;
            padding: 0;
            overflow: hidden;
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
        <!-- O container de alertas antigo foi removido, agora usamos modais -->
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
                        <form class="form-devolver" data-mapa-id="<?php echo $mapa['id']; ?>" data-mapa-nome="<?php echo htmlspecialchars($mapa['identificador']); ?>">
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
                                        <div class="ms-2 d-flex justify-content-center align-items-center" style="width: 24px; height: 24px;" id="status_save_q<?php echo $quadra['id']; ?>"></div>
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
                <div class="modal-body" id="pdf-modal-body"></div>
            </div>
        </div>
    </div>

    <!-- Modais Gerais (Feedback e Confirmação) - IGUAL AO GERENCIAR MAPAS -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="feedbackModalTitle">Aviso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="feedbackModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmacaoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmacaoModalTitle">Confirmação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmacaoModalBody">Tem certeza?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmarAcao">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../script/common.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const API_BASE_URL = '.'; 
            const saveTimeouts = {};

            // Inicialização dos Modais
            const feedbackModalElement = document.getElementById('feedbackModal');
            const feedbackModal = new bootstrap.Modal(feedbackModalElement);
            const feedbackTitle = document.getElementById('feedbackModalTitle');
            const feedbackBody = document.getElementById('feedbackModalBody');

            const confirmacaoModalElement = document.getElementById('confirmacaoModal');
            const confirmacaoModal = new bootstrap.Modal(confirmacaoModalElement);
            const confirmacaoTitle = document.getElementById('confirmacaoModalTitle');
            const confirmacaoBody = document.getElementById('confirmacaoModalBody');
            const btnConfirmarAcao = document.getElementById('btnConfirmarAcao');

            // --- FUNÇÕES AUXILIARES VISUAIS (IGUAIS AO GERENCIAR_MAPAS.JS) ---

            const mostrarFeedback = (titulo, mensagem, tipo = 'primary') => {
                feedbackTitle.textContent = titulo;
                feedbackBody.innerHTML = mensagem;
                const header = feedbackModalElement.querySelector('.modal-header');
                
                // Limpa classes antigas de bg
                header.className = 'modal-header';
                // Adiciona novas
                header.classList.add(`bg-${tipo}`, 'text-white');
                
                const btnClose = header.querySelector('.btn-close');
                if (tipo !== 'light' && tipo !== 'warning') {
                    btnClose.classList.add('btn-close-white');
                } else {
                    btnClose.classList.remove('btn-close-white');
                }
                feedbackModal.show();
            };

            const mostrarConfirmacao = (titulo, mensagem, callbackConfirmacao) => {
                confirmacaoTitle.textContent = titulo;
                confirmacaoBody.innerHTML = mensagem;
                
                // Define a ação do botão confirmar
                btnConfirmarAcao.onclick = () => {
                    confirmacaoModal.hide();
                    callbackConfirmacao();
                };
                
                confirmacaoModal.show();
            };

            // --- LÓGICA DA PÁGINA ---

            const saveQuadra = async (quadraId, valor, statusDiv) => {
                statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span>';
                try {
                    const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'update_quadra', quadra_id: quadraId, pessoas_faladas: parseInt(valor) })
                    });

                    if (!response.ok) {
                        throw new Error(`Erro na rede: ${response.status} ${response.statusText}`);
                    }

                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        statusDiv.innerHTML = '<i class="fas fa-check text-success"></i>';
                        setTimeout(() => { 
                            if (statusDiv.innerHTML.includes('fa-check')) {
                                statusDiv.innerHTML = ''; 
                            }
                        }, 2000);
                    } else { 
                        throw new Error(result.message || 'Erro inesperado.'); 
                    }
                } catch (error) {
                    console.error('Erro ao salvar:', error);
                    // Usa o novo modal de feedback para erros de salvamento se for crítico, 
                    // ou apenas visual no input para não ser intrusivo demais durante digitação
                    statusDiv.innerHTML = '<i class="fas fa-times text-danger"></i>';
                    // Opcional: mostrarFeedback('Erro ao Salvar', error.message, 'danger');
                }
            };

            document.querySelectorAll('.quadra-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const quadraId = e.target.dataset.quadraId;
                    const statusDiv = document.getElementById(`status_save_q${quadraId}`);
                    const valor = e.target.value;
                    
                    if (saveTimeouts[quadraId]) clearTimeout(saveTimeouts[quadraId]);
                    
                    statusDiv.innerHTML = '<small class="text-muted">...</small>';

                    saveTimeouts[quadraId] = setTimeout(() => {
                        saveQuadra(quadraId, valor, statusDiv);
                    }, 800);

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
                form.addEventListener('submit', (e) => {
                    e.preventDefault(); // Impede envio imediato
                    
                    const mapaId = e.target.dataset.mapaId;
                    const mapaNome = e.target.dataset.mapaNome;
                    const dataDevolucao = document.getElementById(`data_devolucao_${mapaId}`).value;
                    
                    if (!dataDevolucao) { 
                        mostrarFeedback('Atenção', 'Por favor, selecione a data de devolução.', 'warning'); 
                        return; 
                    }

                    // Usa o novo modal de confirmação
                    mostrarConfirmacao(
                        'Confirmar Devolução',
                        `Tem certeza que deseja devolver o mapa <strong>${mapaNome}</strong>?<br><br>Esta ação removerá o mapa da sua lista.`,
                        async () => {
                            // Callback executado ao clicar em "Confirmar" no modal
                            const btn = e.target.querySelector('button[type="submit"]');
                            const originalText = btn.innerHTML;
                            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processando...';
                            btn.disabled = true;

                            try {
                                const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ action: 'devolver', mapa_id: mapaId, data_devolucao: dataDevolucao })
                                });
                                
                                if (!response.ok) {
                                    const errorData = await response.json().catch(() => null);
                                    throw new Error(errorData?.message || `Erro na rede: ${response.statusText}`);
                                }

                                const result = await response.json();

                                if (result.message) { 
                                    mostrarFeedback('Sucesso', 'Mapa devolvido com sucesso!', 'success');
                                    
                                    const card = document.getElementById(`mapa-card-${mapaId}`);
                                    card.style.transition = 'opacity 0.5s';
                                    card.style.opacity = '0';
                                    setTimeout(() => card.remove(), 500);
                                    
                                    setTimeout(() => {
                                        if(document.querySelectorAll('.card').length === 0) location.reload();
                                    }, 600);
                                } else { 
                                    throw new Error(result.message || 'Resposta inesperada.'); 
                                }
                            } catch (error) { 
                                console.error('Erro ao devolver:', error); 
                                mostrarFeedback('Erro', 'Erro ao devolver o mapa: ' + error.message, 'danger');
                                btn.innerHTML = originalText; 
                                btn.disabled = false; 
                            }
                        }
                    );
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
                        modalBody.appendChild(iframe);
                    } else {
                        modalBody.innerHTML = '<div class="alert alert-danger m-3">URL do PDF não encontrada.</div>';
                    }
                });
            }
        });
    </script>
</body>
</html>
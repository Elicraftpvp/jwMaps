<?php
// site/backend/vista_dirigente.php
session_start(); // INICIA A SESSÃO

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'conexao.php';

// 1. Pega o ID do dirigente da SESSÃO.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("<h1>Acesso Negado</h1><p>Você precisa estar logado para ver esta página.</p>");
}
$dirigente_id = $_SESSION['user_id'];

try {
    // 2. Busca o nome do dirigente
    $stmt_user = $pdo->prepare("SELECT nome FROM users WHERE id = ?");
    $stmt_user->execute([$dirigente_id]);
    $dirigente = $stmt_user->fetch();
    if (!$dirigente) {
        http_response_code(404);
        die("<h1>Dirigente com ID $dirigente_id não encontrado.</h1>");
    }
    
    // 3. Busca os mapas do dirigente logado
    // Nota: Mantida a query original do dirigente (focada em mapas pessoais)
    $stmt_mapas = $pdo->prepare(
        "SELECT id, identificador, data_entrega, gdrive_file_id 
         FROM mapas 
         WHERE dirigente_id = ? 
         ORDER BY identificador ASC"
    );
    $stmt_mapas->execute([$dirigente_id]);
    $mapas = $stmt_mapas->fetchAll(PDO::FETCH_ASSOC);

    // 4. Busca todas as quadras para os mapas encontrados
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
        body { padding: 15px; background-color: var(--content-bg); } 
        
        /* Layout Masonry (Colunas estilo Pinterest) */
        .masonry-layout { column-count: 1; column-gap: 1.5rem; }
        @media (min-width: 768px) { .masonry-layout { column-count: 2; } }
        @media (min-width: 1400px) { .masonry-layout { column-count: 3; } }
        .card-container-wrapper { break-inside: avoid; margin-bottom: 1.5rem; }

        .quadra-item { border-bottom: 1px solid #eee; }
        .quadra-item:last-child { border-bottom: none; }
        
        /* Ajustes Inputs Numéricos */
        .no-spinners::-webkit-outer-spin-button, 
        .no-spinners::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .no-spinners { -moz-appearance: textfield; }
        .quadra-input { padding: 0; background-color: #fff !important; font-weight: bold; }

        /* Preview Container */
        .pdf-preview-container { 
            position: relative; 
            height: 300px; 
            background-color: #e9ecef; 
            border-bottom: 1px solid #dee2e6; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            overflow: hidden; 
        }
        .pdf-preview-container iframe { width: 100%; height: 100%; border: none; }
        .pdf-preview-container .btn-expand { position: absolute; top: 8px; right: 8px; z-index: 10; }

        /* Animação e Colapso */
        .card-collapsible-content { overflow: hidden; transition: max-height 0.4s ease, opacity 0.4s ease; max-height: 4000px; opacity: 1; }
        .card.collapsed .card-collapsible-content { max-height: 0; opacity: 0; }
        
        .card.card-interativo .card-header { cursor: pointer; user-select: none; }
        .header-icon { transition: transform 0.3s ease; }
        .card.collapsed .header-icon { transform: rotate(-90deg); }

        /* Modal Fullscreen (Estilo Dark) */
        .modal-fullscreen .modal-content { background-color: black; }
        .modal-fullscreen .modal-header {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.6);
            border-bottom: none;
            z-index: 9999;
            padding: 15px 20px;
        }
        .modal-fullscreen .modal-title { color: white; font-size: 1.1rem; text-shadow: 0 1px 3px rgba(0,0,0,0.8); }
        .btn-close-custom {
            background: none; border: none; color: white; font-size: 1.5rem; opacity: 0.9; transition: transform 0.2s;
        }
        .btn-close-custom:hover { opacity: 1; transform: scale(1.1); color: #fff; }
        
        /* Corpo do modal para iframe */
        .modal-fullscreen .modal-body { padding: 0; height: 100vh; overflow: hidden; }
        .modal-fullscreen iframe { width: 100%; height: 100%; border: none; margin-top: 50px; /* Espaço pro header */ }

        /* Responsividade Mobile */
        @media (max-width: 480px) {
            body { padding: 10px; zoom: 1 !important; }
            .card-title { display: flex; flex-wrap: nowrap; align-items: center; width: 100%; }
            .map-name { font-size: 0.95rem; white-space: normal; line-height: 1.2; margin-right: 5px; }
            .header-icon, .card-title i.fa-map-pin { font-size: 0.9rem; }
        }
    </style>
</head>
<body>

    <div class="container-fluid">
        <h2 class="mb-4">Mapas de <?php echo htmlspecialchars($dirigente['nome']); ?></h2>
        
        <!-- Container Masonry -->
        <div class="masonry-layout">
            <?php if (empty($mapas)): ?>
                <div class="alert alert-info text-center w-100">
                    <i class="fas fa-info-circle me-2"></i>Este dirigente não possui nenhum mapa atribuído no momento.
                </div>
            <?php else: 
                $total_cards = count($mapas);
                foreach ($mapas as $mapa): 
                    // Lógica para determinar se deve iniciar colapsado
                    $soma_pessoas = 0;
                    if (isset($quadras_por_mapa[$mapa['id']])) {
                        foreach ($quadras_por_mapa[$mapa['id']] as $q) {
                            $soma_pessoas += (int)$q['pessoas_faladas'];
                        }
                    }
                    
                    // Só colapsa se houver mais de 1 mapa E a soma for 0
                    $classe_inicial = ($total_cards > 1 && $soma_pessoas == 0) ? 'collapsed' : '';
            ?>
                <div class="card-container-wrapper" id="mapa-card-<?php echo $mapa['id']; ?>">
                    <div class="card shadow-sm <?php echo $classe_inicial; ?>">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 d-flex align-items-center w-100">
                                <i class="fas fa-map-pin me-2 flex-shrink-0"></i> 
                                <span class="map-name flex-grow-1"><?php echo htmlspecialchars($mapa['identificador']); ?></span>
                                <div class="flex-shrink-0">
                                    <i class="fas fa-chevron-down header-icon"></i>
                                </div>
                            </h5>
                        </div>

                        <!-- Wrapper para o conteúdo colapsável -->
                        <div class="card-collapsible-content">
                            <?php
                            // Visualização: Prioriza GDrive (padrão Dirigente) mas com estilo novo
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

                            <!-- === BOTÃO DE DOWNLOAD DO PDF (Mantido e Estilizado) === -->
                            <?php
                                $nome_arquivo_pdf = $mapa['identificador'] . ".pdf";
                                $caminho_local_pdf = __DIR__ . "/pdfs/" . $nome_arquivo_pdf;
                                $url_download_pdf = "pdfs/" . rawurlencode($nome_arquivo_pdf);

                                if (file_exists($caminho_local_pdf)):
                            ?>
                                <div class="px-3 pt-3">
                                    <a href="<?php echo $url_download_pdf; ?>" class="btn btn-outline-dark w-100" download="<?php echo htmlspecialchars($nome_arquivo_pdf); ?>">
                                        <i class="fas fa-file-download me-2"></i> Baixar Mapa em PDF
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="card-body">
                                <form class="form-devolver" data-mapa-id="<?php echo $mapa['id']; ?>" data-mapa-nome="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                                    <label class="form-label fw-bold mt-2">Registro por Quadra:</label>
                                    
                                    <!-- Cabeçalho alinhado (Estilo Novo) -->
                                    <div class="d-flex justify-content-end px-2 pb-1"> 
                                        <div class="d-flex align-items-center">
                                            <small class="fw-bold text-muted text-center" style="width: 150px;">Nº Pessoas</small>
                                            <div style="width: 32px;"></div>
                                        </div>
                                    </div>

                                    <div class="list-group list-group-flush mb-3 quadra-list" data-mapa-id="<?php echo $mapa['id']; ?>">
                                    <?php if (!empty($quadras_por_mapa[$mapa['id']])): foreach ($quadras_por_mapa[$mapa['id']] as $quadra): ?>
                                        <div class="list-group-item quadra-item d-flex justify-content-between align-items-center py-3 px-2">
                                            <span class="fs-5">Quadra <strong><?php echo htmlspecialchars($quadra['numero']); ?></strong></span>
                                            <div class="d-flex align-items-center">
                                                <div class="input-group" style="width: 150px;">
                                                    <button class="btn btn-outline-secondary btn-decrement px-3 fw-bold" type="button" style="font-size: 1.2rem;">-</button>
                                                    <input type="number" class="form-control text-center quadra-input no-spinners fw-bold" 
                                                           style="font-size: 1.1rem;"
                                                           value="<?php echo htmlspecialchars($quadra['pessoas_faladas']); ?>" 
                                                           data-quadra-id="<?php echo $quadra['id']; ?>" 
                                                           min="0" aria-label="Pessoas faladas">
                                                    <button class="btn btn-outline-secondary btn-increment px-3 fw-bold" type="button" style="font-size: 1.2rem;">+</button>
                                                </div>
                                                <div class="ms-2 d-flex align-items-center justify-content-center" style="width: 24px;" id="status_save_q<?php echo $quadra['id']; ?>"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="list-group-item d-flex justify-content-between align-items-center p-2 border-top fw-bold bg-light"> 
                                        <span class="fs-5">Total</span> 
                                        <div class="d-flex align-items-center">
                                            <span class="fs-5 text-center fw-bold" style="width: 150px;" id="total-pessoas-mapa-<?php echo $mapa['id']; ?>"><?php echo $soma_pessoas; ?></span> 
                                            <div style="width: 32px;"></div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                        <div class="list-group-item text-muted">Nenhuma quadra encontrada para este mapa.</div>
                                    <?php endif; ?>
                                    </div>
                                    
                                    <hr>
                                    <p class="mb-2"><strong>Recebido em:</strong> <?php echo date('d/m/Y', strtotime($mapa['data_entrega'])); ?></p>
                                    
                                    <!-- FUNCIONALIDADE ÚNICA DO DIRIGENTE: DATA MANUAL -->
                                    <div class="mb-3">
                                        <label for="data_devolucao_<?php echo $mapa['id']; ?>" class="form-label fw-bold">Data de Devolução:</label>
                                        <input type="date" class="form-control" id="data_devolucao_<?php echo $mapa['id']; ?>" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>

                                    <div class="d-grid mt-3">
                                        <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i> Devolver Mapa Completo</button>
                                    </div>
                                </form>
                            </div>
                        </div> <!-- Fim Card Collapsible -->
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Modal Visualizador de PDF (Estilo Fullscreen Dark) -->
    <div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfModalLabel">Visualizador de Mapa</h5>
                    <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body bg-black" id="pdf-modal-body">
                    <!-- Iframe será injetado aqui via JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modais Gerais (Feedback e Confirmação) -->
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

        // --- FUNÇÕES AUXILIARES VISUAIS ---
        const mostrarFeedback = (titulo, mensagem, tipo = 'primary') => {
            feedbackTitle.textContent = titulo;
            feedbackBody.innerHTML = mensagem;
            const header = feedbackModalElement.querySelector('.modal-header');
            header.className = `modal-header bg-${tipo} text-white`;
            
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
            btnConfirmarAcao.onclick = () => { confirmacaoModal.hide(); callbackConfirmacao(); };
            confirmacaoModal.show();
        };

        // --- LÓGICA DE COLAPSO DOS CARDS (Atualizada) ---
        const gerenciarColapsoCards = () => {
            const wrappers = document.querySelectorAll('.card-container-wrapper');
            const totalMapas = wrappers.length;
            
            wrappers.forEach(wrapper => {
                const card = wrapper.querySelector('.card');
                if (totalMapas > 1) {
                    card.classList.add('card-interativo');
                } else {
                    card.classList.remove('card-interativo', 'collapsed');
                    const icon = card.querySelector('.header-icon');
                    if (icon) icon.style.display = 'none';
                }
            });
        };

        document.addEventListener('click', (e) => {
            const header = e.target.closest('.card-header');
            if (header) {
                const card = header.closest('.card');
                if (card && card.classList.contains('card-interativo')) {
                    card.classList.toggle('collapsed');
                }
            }
        });

        gerenciarColapsoCards();

        // --- LÓGICA DE SALVAMENTO ---
        // Nota: Mantém update_quadra (valor absoluto) para o Dirigente, pois ele edita o valor final.
        const saveQuadra = async (quadraId, valor, statusDiv) => {
            statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span>';
            try {
                const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_quadra', quadra_id: quadraId, pessoas_faladas: parseInt(valor) })
                });

                if (!response.ok) throw new Error('Erro na rede');
                const result = await response.json();
                
                if (result.status === 'success') {
                    statusDiv.innerHTML = '<i class="fas fa-check text-success"></i>';
                    setTimeout(() => { if (statusDiv.innerHTML.includes('fa-check')) statusDiv.innerHTML = ''; }, 2000);
                } else { throw new Error(result.message); }
            } catch (error) {
                console.error('Erro:', error);
                statusDiv.innerHTML = '<i class="fas fa-times text-danger"></i>';
            }
        };

        const updateTotal = (quadraList) => { 
            const mapaId = quadraList.dataset.mapaId; 
            let total = 0; 
            quadraList.querySelectorAll('.quadra-input').forEach(input => { total += parseInt(input.value) || 0; }); 
            const totalEl = document.getElementById(`total-pessoas-mapa-${mapaId}`);
            if(totalEl) totalEl.textContent = total; 
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
        
        // Botões de incremento/decremento (Adaptados para disparar o input event)
        document.querySelectorAll('.btn-increment').forEach(btn => {
            btn.addEventListener('click', (e) => { 
                const input = e.target.closest('.input-group').querySelector('.quadra-input'); 
                input.value = parseInt(input.value || 0) + 1; 
                input.dispatchEvent(new Event('input', { bubbles: true })); 
            });
        });
        
        document.querySelectorAll('.btn-decrement').forEach(btn => {
            btn.addEventListener('click', (e) => { 
                const input = e.target.closest('.input-group').querySelector('.quadra-input'); 
                const currentValue = parseInt(input.value || 0); 
                if (currentValue > 0) { 
                    input.value = currentValue - 1; 
                    input.dispatchEvent(new Event('input', { bubbles: true })); 
                } 
            });
        });
        
        // --- DEVOLUÇÃO DO MAPA ---
        document.querySelectorAll('.form-devolver').forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const mapaId = e.target.dataset.mapaId;
                const mapaNome = e.target.dataset.mapaNome;
                const dataDevolucao = document.getElementById(`data_devolucao_${mapaId}`).value;
                
                if (!dataDevolucao) { mostrarFeedback('Atenção', 'Por favor, selecione a data de devolução.', 'warning'); return; }

                mostrarConfirmacao(
                    'Confirmar Devolução',
                    `Tem certeza que deseja devolver o mapa <strong>${mapaNome}</strong> na data <strong>${dataDevolucao}</strong>?`,
                    async () => {
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
                            
                            const result = await response.json();

                            if (result.message || result.status === 'success') { 
                                mostrarFeedback('Sucesso', 'Mapa devolvido com sucesso!', 'success'); 
                                const cardWrapper = document.getElementById(`mapa-card-${mapaId}`);
                                cardWrapper.style.transition = 'opacity 0.5s';
                                cardWrapper.style.opacity = '0';
                                setTimeout(() => {
                                    cardWrapper.remove();
                                    gerenciarColapsoCards();
                                    if(document.querySelectorAll('.card').length === 0) location.reload();
                                }, 500);
                            } else { throw new Error(result.message); }
                        } catch (error) { 
                            mostrarFeedback('Erro', 'Erro ao devolver o mapa: ' + error.message, 'danger'); 
                            btn.innerHTML = originalText; 
                            btn.disabled = false; 
                        }
                    }
                );
            });
        });

        // --- MODAL PDF (FULLSCREEN) ---
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
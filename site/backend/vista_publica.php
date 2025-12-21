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
            background-color: #e9ecef;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        /* contain garante que a imagem apareça inteira sem cortes no card */
        .pdf-preview-container img { max-width: 100%; max-height: 100%; object-fit: contain; cursor: pointer; }
        .pdf-preview-container .btn-expand { position: absolute; top: 8px; right: 8px; z-index: 10; }

        /* Estilo atualizado para o Modal Fullscreen */
        #pdfModal .modal-dialog {
            margin: 0;
            width: 100%;
            height: 100%;
            max-width: none; 
        }
        #pdfModal .modal-content {
            height: 100%;
            border: none;
            border-radius: 0;
            background-color: #000; 
        }
        #pdfModal .modal-header { 
            position: absolute; 
            top: 0;
            left: 0;
            right: 0;
            z-index: 1055;
            background-color: rgba(0, 0, 0, 0.5); 
            border-bottom: none; 
            padding: 10px 15px;
        }
        #pdfModal .modal-title { color: #fff; font-size: 1.1rem; }
        #pdfModal .btn-close { filter: invert(1); }
        
        #pdfModal .modal-body {
            position: relative;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            width: 100%;
            overflow: auto; 
        }
        
        #pdfModal img { 
            display: block;
            max-width: 100%; 
            max-height: 100%; 
            object-fit: contain; 
            margin: auto;
        }

        /* Estilos de Animação e Colapso */
        .card-collapsible-content {
            overflow: hidden;
            transition: max-height 0.4s ease-in-out, opacity 0.4s ease-in-out;
            max-height: 2000px; 
            opacity: 1;
        }

        .card.collapsed .card-collapsible-content {
            max-height: 0;
            opacity: 0;
        }

        .card.card-interativo .card-header { 
            cursor: pointer; 
            user-select: none; 
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Animação da seta do cabeçalho */
        .header-icon {
            transition: transform 0.3s ease;
        }
        .card.collapsed .header-icon {
            transform: rotate(-90deg);
        }

        /* Masonry Layout via CSS Columns */
        .masonry-layout {
            column-count: 1;
            column-gap: 1.5rem;
        }
        
        /* Ajuste para Tablet e Paisagem Mobile */
        @media (min-width: 768px) {
            .masonry-layout {
                column-count: 2;
            }
        }

        /* Ajuste para Monitores PC */
        @media (min-width: 1400px) {
            .masonry-layout {
                column-count: 3;
            }
        }

        .card-container-wrapper {
            break-inside: avoid; /* Evita que o card quebre entre colunas */
            margin-bottom: 1.5rem; /* Espaço vertical entre cards */
        }

        /* Ajuste para o zoom no mobile conforme solicitado anteriormente */
        @media (max-width: 768px) { body { zoom: 1.1; } }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark mb-4 rounded shadow-sm">
        <div class="container-fluid"><span class="navbar-brand"><i class="fas fa-map-marked-alt me-2"></i>Mapas de <?php echo htmlspecialchars($dirigente['nome']); ?></span></div>
    </nav>
    <div class="container-fluid">
        <!-- Alterado id="container-mapas" para usar classe masonry-layout em vez de row -->
        <div class="masonry-layout" id="container-mapas">
        <?php if (empty($mapas)): ?>
            <div class="alert alert-info text-center w-100">Você não possui nenhum mapa atribuído.</div>
        <?php else: 
            $total_cards = count($mapas);
            foreach ($mapas as $mapa): 
                $soma_pessoas = 0;
                if (isset($quadras_por_mapa[$mapa['id']])) {
                    foreach ($quadras_por_mapa[$mapa['id']] as $q) {
                        $soma_pessoas += (int)$q['pessoas_faladas'];
                    }
                }
                $classe_inicial = ($total_cards > 1 && $soma_pessoas == 0) ? 'collapsed' : '';
        ?>
            <!-- Removido col-lg-6, mantido apenas o wrapper -->
            <div class="card-container-wrapper" id="mapa-card-<?php echo $mapa['id']; ?>">
                <div class="card shadow-sm <?php echo $classe_inicial; ?>">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0 d-flex align-items-center">
                            <i class="fas fa-map-pin me-2"></i> <?php echo htmlspecialchars($mapa['identificador']); ?>
                        </h5>
                        <?php if ($total_cards > 1): ?>
                            <i class="fas fa-chevron-down header-icon"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-collapsible-content">
                        <?php
                        $nome_limpo = $mapa['identificador'];
                        $url_jpg = "pdfs/" . rawurlencode($nome_limpo) . ".jpg";
                        $caminho_local_jpg = __DIR__ . "/pdfs/" . $nome_limpo . ".jpg";

                        if (file_exists($caminho_local_jpg)):
                        ?>
                            <div class="pdf-preview-container">
                                <img src="<?php echo $url_jpg; ?>" alt="Preview Mapa" data-bs-toggle="modal" data-bs-target="#pdfModal" data-img-src="<?php echo $url_jpg; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                                <button class="btn btn-primary btn-sm btn-expand" data-bs-toggle="modal" data-bs-target="#pdfModal" data-img-src="<?php echo $url_jpg; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                                    <i class="fas fa-expand-alt me-1"></i> Expandir
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-3 text-muted border-bottom"><i class="fas fa-image me-2"></i> Imagem do mapa (JPG) não encontrada na pasta.</div>
                        <?php endif; ?>

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
                                <div class="d-flex justify-content-end px-2 pb-1"> <small class="fw-bold text-muted" style="width: 140px; text-align: center;">Nº Pessoas</small> </div>
                                <div class="list-group list-group-flush mb-3 quadra-list" data-mapa-id="<?php echo $mapa['id']; ?>">
                                <?php if (isset($quadras_por_mapa[$mapa['id']])): foreach ($quadras_por_mapa[$mapa['id']] as $quadra): ?>
                                    <div class="list-group-item quadra-item d-flex justify-content-between align-items-center p-2">
                                        <span>Quadra <strong><?php echo htmlspecialchars($quadra['numero']); ?></strong></span>
                                        <div class="d-flex align-items-center">
                                            <div class="input-group input-group-sm" style="width: 120px;">
                                                <button class="btn btn-outline-secondary btn-decrement" type="button">-</button>
                                                <!-- Adicionado data-previous-value para calcular o Delta -->
                                                <input type="number" class="form-control text-center quadra-input no-spinners" 
                                                       value="<?php echo htmlspecialchars($quadra['pessoas_faladas']); ?>" 
                                                       data-quadra-id="<?php echo $quadra['id']; ?>" 
                                                       data-previous-value="<?php echo htmlspecialchars($quadra['pessoas_faladas']); ?>"
                                                       min="0">
                                                <button class="btn btn-outline-secondary btn-increment" type="button">+</button>
                                            </div>
                                            <div class="ms-2 d-flex justify-content-center align-items-center" style="width: 24px; height: 24px;" id="status_save_q<?php echo $quadra['id']; ?>"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-2 border-top fw-bold bg-light"> <span>Total</span> <span class="fs-5" id="total-pessoas-mapa-<?php echo $mapa['id']; ?>"><?php echo $soma_pessoas; ?></span> </div>
                                <?php else: ?>
                                    <div class="list-group-item text-muted">Nenhuma quadra para este mapa.</div>
                                <?php endif; ?>
                                </div>
                                <hr>
                                <p class="mb-2"><strong>Recebido em:</strong> <?php echo date('d/m/Y', strtotime($mapa['data_entrega'])); ?></p>
                                <div class="d-grid"><button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i> Devolver Mapa</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Modais (Fullscreen Imagem, Feedback e Confirmação) -->
    <div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfModalLabel">Visualizador de Mapa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="pdf-modal-body"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="feedbackModalTitle">Aviso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="feedbackModalBody"></div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button></div>
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
            const pendingDeltas = {}; // Objeto para armazenar as mudanças (+1, -1) pendentes

            const feedbackModalElement = document.getElementById('feedbackModal');
            const feedbackModal = new bootstrap.Modal(feedbackModalElement);
            const feedbackTitle = document.getElementById('feedbackModalTitle');
            const feedbackBody = document.getElementById('feedbackModalBody');

            const confirmacaoModalElement = document.getElementById('confirmacaoModal');
            const confirmacaoModal = new bootstrap.Modal(confirmacaoModalElement);
            const confirmacaoTitle = document.getElementById('confirmacaoModalTitle');
            const confirmacaoBody = document.getElementById('confirmacaoModalBody');
            const btnConfirmarAcao = document.getElementById('btnConfirmarAcao');

            const mostrarFeedback = (titulo, mensagem, tipo = 'primary') => {
                feedbackTitle.textContent = titulo;
                feedbackBody.innerHTML = mensagem;
                const header = feedbackModalElement.querySelector('.modal-header');
                header.className = 'modal-header';
                header.classList.add(`bg-${tipo}`, 'text-white');
                feedbackModal.show();
            };

            const mostrarConfirmacao = (titulo, mensagem, callbackConfirmacao) => {
                confirmacaoTitle.textContent = titulo;
                confirmacaoBody.innerHTML = mensagem;
                btnConfirmarAcao.onclick = () => { confirmacaoModal.hide(); callbackConfirmacao(); };
                confirmacaoModal.show();
            };

            const gerenciarColapsoCards = () => {
                const wrappers = document.querySelectorAll('.card-container-wrapper');
                const totalMapas = wrappers.length;
                wrappers.forEach(wrapper => {
                    const card = wrapper.querySelector('.card');
                    if (totalMapas > 1) {
                        card.classList.add('card-interativo');
                    } else {
                        card.classList.remove('card-interativo');
                        card.classList.remove('collapsed'); 
                        // Esconde seta se houver apenas 1 card
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

            // === Lógica de Salvamento com Delta ===
            const saveQuadra = async (quadraId, statusDiv) => {
                // Recupera o delta acumulado e zera o acumulador imediatamente
                // para que novos cliques durante a requisição comecem um novo pacote
                const deltaToSend = pendingDeltas[quadraId];
                if (!deltaToSend || deltaToSend === 0) return;
                
                pendingDeltas[quadraId] = 0; 
                
                statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span>';
                
                try {
                    // Enviamos 'update_quadra_increment' com o delta
                    const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            action: 'update_quadra_increment', 
                            quadra_id: quadraId, 
                            delta: deltaToSend 
                        })
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        statusDiv.innerHTML = '<i class="fas fa-check text-success"></i>';
                        setTimeout(() => { if (statusDiv.innerHTML.includes('fa-check')) statusDiv.innerHTML = ''; }, 2000);
                    } else { throw new Error(result.message); }
                } catch (error) {
                    // Se falhar, devolvemos o delta para o acumulador para tentar na próxima
                    pendingDeltas[quadraId] = (pendingDeltas[quadraId] || 0) + deltaToSend;
                    statusDiv.innerHTML = '<i class="fas fa-times text-danger"></i>';
                }
            };

            document.querySelectorAll('.quadra-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const quadraId = e.target.dataset.quadraId;
                    const statusDiv = document.getElementById(`status_save_q${quadraId}`);
                    
                    // Cálculo do Delta
                    const prevValue = parseInt(e.target.dataset.previousValue) || 0;
                    const currentValue = parseInt(e.target.value) || 0;
                    const diff = currentValue - prevValue;

                    if (diff !== 0) {
                        // Adiciona a diferença ao acumulador pendente
                        pendingDeltas[quadraId] = (pendingDeltas[quadraId] || 0) + diff;
                        
                        // Atualiza o valor anterior para o atual, para o próximo cálculo
                        e.target.dataset.previousValue = currentValue;

                        if (saveTimeouts[quadraId]) clearTimeout(saveTimeouts[quadraId]);
                        statusDiv.innerHTML = '<small class="text-muted">...</small>';
                        
                        // Espera 800ms antes de enviar o acumulado
                        saveTimeouts[quadraId] = setTimeout(() => saveQuadra(quadraId, statusDiv), 800);
                        
                        updateTotal(e.target.closest('.quadra-list'));
                    }
                });
            });
            
            document.querySelectorAll('.btn-increment').forEach(btn => {
                btn.addEventListener('click', (e) => { const input = e.target.closest('.input-group').querySelector('.quadra-input'); input.value = parseInt(input.value || 0) + 1; input.dispatchEvent(new Event('input', { bubbles: true })); });
            });
            
            document.querySelectorAll('.btn-decrement').forEach(btn => {
                btn.addEventListener('click', (e) => { const input = e.target.closest('.input-group').querySelector('.quadra-input'); const currentValue = parseInt(input.value || 0); if (currentValue > 0) { input.value = currentValue - 1; input.dispatchEvent(new Event('input', { bubbles: true })); } });
            });
            
            const updateTotal = (quadraList) => { const mapaId = quadraList.dataset.mapaId; let total = 0; quadraList.querySelectorAll('.quadra-input').forEach(input => { total += parseInt(input.value) || 0; }); document.getElementById(`total-pessoas-mapa-${mapaId}`).textContent = total; };
            
            document.querySelectorAll('.form-devolver').forEach(form => {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const mapaId = e.target.dataset.mapaId;
                    const mapaNome = e.target.dataset.mapaNome;
                    const dataDevolucao = new Date().toISOString().split('T')[0];

                    mostrarConfirmacao('Confirmar Devolução', `Deseja devolver o mapa <strong>${mapaNome}</strong>?`, async () => {
                        const btn = e.target.querySelector('button[type="submit"]');
                        btn.disabled = true;
                        try {
                            const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ action: 'devolver', mapa_id: mapaId, data_devolucao: dataDevolucao })
                            });
                            const result = await response.json();
                            if (result.message) { 
                                mostrarFeedback('Sucesso', 'Mapa devolvido!', 'success');
                                const cardWrapper = document.getElementById(`mapa-card-${mapaId}`);
                                cardWrapper.style.opacity = '0';
                                setTimeout(() => { cardWrapper.remove(); gerenciarColapsoCards(); if(document.querySelectorAll('.card').length === 0) location.reload(); }, 500);
                            }
                        } catch (error) { mostrarFeedback('Erro', 'Falha na devolução.', 'danger'); btn.disabled = false; }
                    });
                });
            });

            // Lógica do Modal de Expansão (JPG centralizado)
            const pdfModal = document.getElementById('pdfModal');
            if (pdfModal) {
                pdfModal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    const imgSrc = button.getAttribute('data-img-src');
                    const pdfTitle = button.getAttribute('data-pdf-title');
                    
                    pdfModal.querySelector('.modal-title').textContent = pdfTitle;
                    const modalBody = pdfModal.querySelector('#pdf-modal-body');
                    modalBody.innerHTML = ''; 

                    if (imgSrc) {
                        const img = document.createElement('img');
                        img.src = imgSrc;
                        modalBody.appendChild(img);
                    }
                });
            }
        });
    </script>
</body>
</html>
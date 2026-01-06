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
    
    // 3. Busca os mapas (Pessoal + Grupos)
    // ATUALIZAÇÃO: Agora busca mapas do usuário OU dos grupos que ele participa
    $sql_mapas = "
        SELECT m.id, m.identificador, m.data_entrega, m.gdrive_file_id, m.grupo_id, g.nome as nome_grupo
        FROM mapas m
        LEFT JOIN grupos g ON m.grupo_id = g.id
        WHERE (m.dirigente_id = ? OR m.grupo_id IN (SELECT grupo_id FROM grupo_membros WHERE user_id = ?))
        AND m.data_devolucao IS NULL -- Apenas mapas ativos
        ORDER BY m.identificador ASC
    ";

    $stmt_mapas = $pdo->prepare($sql_mapas);
    $stmt_mapas->execute([$dirigente_id, $dirigente_id]);
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
        
        /* Layout Masonry */
        .masonry-layout { column-count: 1; column-gap: 1.5rem; }
        @media (min-width: 768px) { .masonry-layout { column-count: 2; } }
        @media (min-width: 1400px) { .masonry-layout { column-count: 3; } }
        .card-container-wrapper { break-inside: avoid; margin-bottom: 1.5rem; }

        .quadra-item { border-bottom: 1px solid #eee; }
        .quadra-item:last-child { border-bottom: none; }
        
        /* Inputs Numéricos */
        .no-spinners::-webkit-outer-spin-button, 
        .no-spinners::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .no-spinners { -moz-appearance: textfield; }
        .quadra-input { padding: 0; background-color: #fff !important; font-weight: bold; }

        /* Estilos de Grupo */
        .card-header-group { background-color: #4190be !important; border-color: #4190be !important; }
        .btn-group-color { background-color: #4190be !important; border-color: #4190be !important; color: white !important; }
        .btn-group-color:hover { background-color: #357a9e !important; }

        /* Preview Container Imagem */
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
        .pdf-preview-container img { max-width: 100%; max-height: 100%; object-fit: contain; cursor: pointer; }
        .pdf-preview-container .btn-expand { position: absolute; top: 8px; right: 8px; z-index: 10; }

        /* Animação e Colapso */
        .card-collapsible-content { overflow: hidden; transition: max-height 0.4s ease, opacity 0.4s ease; max-height: 4000px; opacity: 1; }
        .card.collapsed .card-collapsible-content { max-height: 0; opacity: 0; }
        .card.card-interativo .card-header { cursor: pointer; user-select: none; }
        .header-icon { transition: transform 0.3s ease; }
        .card.collapsed .header-icon { transform: rotate(-90deg); }

        /* Modal Fullscreen Imagem */
        .modal-fullscreen .modal-content { background-color: black; }
        .modal-fullscreen .modal-header {
            position: absolute; top: 0; left: 0; width: 100%;
            background: rgba(0, 0, 0, 0.6); border-bottom: none; z-index: 9999;
            padding: 15px 20px;
        }
        .modal-fullscreen .modal-title { color: white; font-size: 1.1rem; text-shadow: 0 1px 3px rgba(0,0,0,0.8); }
        .btn-close-custom { background: none; border: none; color: white; font-size: 1.5rem; opacity: 0.9; transition: transform 0.2s; }
        .btn-close-custom:hover { opacity: 1; transform: scale(1.1); color: #fff; }
        .modal-fullscreen .modal-body { padding: 0; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .modal-fullscreen img { max-width: 100%; max-height: 100%; object-fit: contain; }

        /* Mobile */
        @media (max-width: 480px) {
            body { padding: 10px; zoom: 1 !important; }
            .card-title { display: flex; flex-wrap: nowrap; align-items: center; width: 100%; }
            .map-name { font-size: 0.95rem; white-space: normal; line-height: 1.2; margin-right: 5px; }
            .group-tag { font-size: 0.6rem !important; max-width: 80px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .header-icon, .card-title i.fa-map-pin, .card-title i.fa-users { font-size: 0.9rem; }
        }
    </style>
</head>
<body>

    <div class="container-fluid">
        <h2 class="mb-4">Mapas de <?php echo htmlspecialchars($dirigente['nome']); ?></h2>
        
        <div class="masonry-layout">
            <?php if (empty($mapas)): ?>
                <div class="alert alert-info text-center w-100">
                    <i class="fas fa-info-circle me-2"></i>Você não possui mapas atribuídos (pessoais ou de grupo).
                </div>
            <?php else: 
                $total_cards = count($mapas);
                foreach ($mapas as $mapa): 
                    $isGroup = !empty($mapa['grupo_id']);
                    $soma_pessoas = 0;
                    if (isset($quadras_por_mapa[$mapa['id']])) {
                        foreach ($quadras_por_mapa[$mapa['id']] as $q) {
                            $soma_pessoas += (int)$q['pessoas_faladas'];
                        }
                    }
                    $classe_inicial = ($total_cards > 1 && $soma_pessoas == 0) ? 'collapsed' : '';
            ?>
                <div class="card-container-wrapper" id="mapa-card-<?php echo $mapa['id']; ?>">
                    <div class="card shadow-sm <?php echo $classe_inicial; ?>">
                        <div class="card-header <?php echo $isGroup ? 'card-header-group' : 'bg-primary'; ?> text-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 d-flex align-items-center w-100">
                                <i class="fas <?php echo $isGroup ? 'fa-users' : 'fa-map-pin'; ?> me-2 flex-shrink-0"></i> 
                                <span class="map-name flex-grow-1"><?php echo htmlspecialchars($mapa['identificador']); ?></span>
                                
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <?php if($isGroup): ?>
                                        <span class="badge bg-white text-dark group-tag" style="opacity: 0.9;"><?php echo htmlspecialchars($mapa['nome_grupo']); ?></span>
                                    <?php endif; ?>
                                    <i class="fas fa-chevron-down header-icon"></i>
                                </div>
                            </h5>
                        </div>

                        <div class="card-collapsible-content">
                            <?php
                            // ATUALIZAÇÃO: Lógica de Imagem (igual ao vista_publica)
                            $nome_identificador = $mapa['identificador'];
                            $url_jpg = "pdfs/" . rawurlencode($nome_identificador) . ".jpg";
                            $url_pdf = "pdfs/" . rawurlencode($nome_identificador) . ".pdf";
                            $caminho_local_jpg = __DIR__ . "/pdfs/" . $nome_identificador . ".jpg";
                            $caminho_local_pdf = __DIR__ . "/pdfs/" . $nome_identificador . ".pdf";

                            // Se existir imagem, mostra ela. Se não, tenta mostrar iframe do drive (fallback)
                            if (file_exists($caminho_local_jpg)):
                            ?>
                                <div class="pdf-preview-container">
                                    <img src="<?php echo $url_jpg; ?>" data-bs-toggle="modal" data-bs-target="#imgModal" data-img-src="<?php echo $url_jpg; ?>" data-map-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                                    <button class="btn <?php echo $isGroup ? 'btn-group-color' : 'btn-primary'; ?> btn-sm btn-expand" data-bs-toggle="modal" data-bs-target="#imgModal" data-img-src="<?php echo $url_jpg; ?>" data-map-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                                        <i class="fas fa-expand-alt me-1"></i> Expandir
                                    </button>
                                </div>
                            <?php elseif (!empty($mapa['gdrive_file_id'])): 
                                $pdf_embed_url = "https://drive.google.com/file/d/" . $mapa['gdrive_file_id'] . "/preview";
                            ?>
                                <!-- Fallback para Drive se não tiver imagem JPG local -->
                                <div class="pdf-preview-container">
                                    <iframe src="<?php echo $pdf_embed_url; ?>"></iframe>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-3 text-muted border-bottom"><i class="fas fa-image me-2"></i> Imagem do mapa não disponível.</div>
                            <?php endif; ?>

                            <!-- Botão de Download PDF -->
                            <?php if (file_exists($caminho_local_pdf)): ?>
                                <div class="px-3 pt-3">
                                    <a href="<?php echo $url_pdf; ?>" class="btn btn-outline-dark w-100" download="<?php echo htmlspecialchars($nome_identificador . '.pdf'); ?>">
                                        <i class="fas fa-file-download me-2"></i> Baixar Mapa em PDF
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="card-body">
                                <form class="form-devolver" data-mapa-id="<?php echo $mapa['id']; ?>" data-mapa-nome="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                                    <label class="form-label fw-bold mt-2">Registro por Quadra:</label>
                                    
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
                                                           min="0">
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
                                    <?php endif; ?>
                                    </div>
                                    
                                    <hr>
                                    <p class="mb-2"><strong>Recebido em:</strong> <?php echo date('d/m/Y', strtotime($mapa['data_entrega'])); ?></p>
                                    
                                    <!-- FUNCIONALIDADE ÚNICA MANTIDA: DATA MANUAL -->
                                    <div class="mb-3">
                                        <label for="data_devolucao_<?php echo $mapa['id']; ?>" class="form-label fw-bold">Data de Devolução:</label>
                                        <input type="date" class="form-control" id="data_devolucao_<?php echo $mapa['id']; ?>" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>

                                    <div class="d-grid mt-3">
                                        <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i> Devolver Mapa Completo</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Modal Visualizador de Imagem (Fullscreen) -->
    <div class="modal fade" id="imgModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imgModalTitle">Visualizador</h5>
                    <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body bg-black">
                    <img id="modal-img-content" src="" alt="Mapa">
                </div>
            </div>
        </div>
    </div>

    <!-- Modais Gerais -->
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

        const feedbackModalElement = document.getElementById('feedbackModal');
        const feedbackModal = new bootstrap.Modal(feedbackModalElement);
        const confirmacaoModalElement = document.getElementById('confirmacaoModal');
        const confirmacaoModal = new bootstrap.Modal(confirmacaoModalElement);
        const btnConfirmarAcao = document.getElementById('btnConfirmarAcao');

        // Helpers
        const mostrarFeedback = (titulo, mensagem, tipo = 'primary') => {
            document.getElementById('feedbackModalTitle').textContent = titulo;
            document.getElementById('feedbackModalBody').innerHTML = mensagem;
            feedbackModalElement.querySelector('.modal-header').className = `modal-header bg-${tipo} text-white`;
            feedbackModal.show();
        };

        const mostrarConfirmacao = (titulo, mensagem, callback) => {
            document.getElementById('confirmacaoModalTitle').textContent = titulo;
            document.getElementById('confirmacaoModalBody').innerHTML = mensagem;
            btnConfirmarAcao.onclick = () => { confirmacaoModal.hide(); callback(); };
            confirmacaoModal.show();
        };

        // Colapso Cards
        const gerenciarColapsoCards = () => {
            const wrappers = document.querySelectorAll('.card-container-wrapper');
            wrappers.forEach(wrapper => {
                const card = wrapper.querySelector('.card');
                if (wrappers.length > 1) {
                    card.classList.add('card-interativo');
                } else {
                    card.classList.remove('card-interativo', 'collapsed');
                    if(card.querySelector('.header-icon')) card.querySelector('.header-icon').style.display = 'none';
                }
            });
        };

        document.addEventListener('click', (e) => {
            const header = e.target.closest('.card-header');
            if (header && header.closest('.card').classList.contains('card-interativo')) {
                header.closest('.card').classList.toggle('collapsed');
            }
        });
        gerenciarColapsoCards();

        // Salvar Quadras (Mantida lógica absoluta para Dirigentes)
        const saveQuadra = async (quadraId, valor, statusDiv) => {
            statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span>';
            try {
                const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_quadra', quadra_id: quadraId, pessoas_faladas: parseInt(valor) })
                });
                const result = await response.json();
                if (result.status === 'success') {
                    statusDiv.innerHTML = '<i class="fas fa-check text-success"></i>';
                    setTimeout(() => { if (statusDiv.innerHTML.includes('fa-check')) statusDiv.innerHTML = ''; }, 2000);
                } else throw new Error(result.message);
            } catch (error) {
                console.error(error);
                statusDiv.innerHTML = '<i class="fas fa-times text-danger"></i>';
            }
        };

        const updateTotal = (list) => { 
            let total = 0; 
            list.querySelectorAll('.quadra-input').forEach(i => total += parseInt(i.value)||0);
            document.getElementById(`total-pessoas-mapa-${list.dataset.mapaId}`).textContent = total;
        };

        document.querySelectorAll('.quadra-input').forEach(input => {
            input.addEventListener('input', (e) => {
                const qId = e.target.dataset.quadraId;
                if (saveTimeouts[qId]) clearTimeout(saveTimeouts[qId]);
                document.getElementById(`status_save_q${qId}`).innerHTML = '<small>...</small>';
                saveTimeouts[qId] = setTimeout(() => saveQuadra(qId, e.target.value, document.getElementById(`status_save_q${qId}`)), 800);
                updateTotal(e.target.closest('.quadra-list'));
            });
        });

        document.querySelectorAll('.btn-increment').forEach(btn => btn.onclick = (e) => {
            const input = e.target.closest('.input-group').querySelector('.quadra-input');
            input.value = (parseInt(input.value)||0) + 1;
            input.dispatchEvent(new Event('input'));
        });
        document.querySelectorAll('.btn-decrement').forEach(btn => btn.onclick = (e) => {
            const input = e.target.closest('.input-group').querySelector('.quadra-input');
            if((parseInt(input.value)||0) > 0) {
                input.value = parseInt(input.value) - 1;
                input.dispatchEvent(new Event('input'));
            }
        });

        // Devolução
        document.querySelectorAll('.form-devolver').forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const mapaId = e.target.dataset.mapaId;
                const mapaNome = e.target.dataset.mapaNome;
                const dataDevolucao = document.getElementById(`data_devolucao_${mapaId}`).value;

                if (!dataDevolucao) { mostrarFeedback('Atenção', 'Data obrigatória.', 'warning'); return; }

                mostrarConfirmacao('Confirmar', `Devolver <strong>${mapaNome}</strong> em <strong>${dataDevolucao}</strong>?`, async () => {
                    const btn = e.target.querySelector('button[type="submit"]');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ...';
                    btn.disabled = true;
                    try {
                        const res = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'devolver', mapa_id: mapaId, data_devolucao: dataDevolucao })
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            const card = document.getElementById(`mapa-card-${mapaId}`);
                            card.style.opacity = '0';
                            setTimeout(() => { card.remove(); gerenciarColapsoCards(); if(!document.querySelector('.card')) location.reload(); }, 500);
                        } else throw new Error(json.message);
                    } catch (err) {
                        mostrarFeedback('Erro', err.message, 'danger');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                });
            });
        });

        // Modal de Imagem
        const imgModal = document.getElementById('imgModal');
        if (imgModal) {
            imgModal.addEventListener('show.bs.modal', (event) => {
                const btn = event.relatedTarget;
                const src = btn.getAttribute('data-img-src');
                const title = btn.getAttribute('data-map-title');
                imgModal.querySelector('.modal-title').textContent = title;
                imgModal.querySelector('#modal-img-content').src = src;
            });
        }
    });
    </script>
</body>
</html>
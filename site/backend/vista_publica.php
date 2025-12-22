<?php
// site/backend/vista_publica.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'conexao.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$path = (strpos($_SERVER['REQUEST_URI'], '/jwMaps') !== false) ? "/jwMaps/" : "/";
$baseUrl = $protocol . $domainName . $path;

/**
 * Renderiza a tela de erro com a identidade visual completa e animações originais.
 */
function exibirErroFatal($titulo, $mensagem, $baseUrl) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Aviso de Território</title>
        <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>site/images/map.png">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body { background-color: #f0f2f5; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; margin: 0; }
            .error-card { background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); max-width: 420px; width: 90%; text-align: center; overflow: hidden; animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1); border-top: 5px solid #dc3545; }
            .error-header { padding: 40px 20px 10px 20px; }
            .icon-wrapper { width: 80px; height: 80px; background: #fff5f5; color: #dc3545; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; font-size: 2.5rem; animation: pulse 2s infinite; }
            .error-body { padding: 10px 30px 40px 30px; }
            .error-title { font-weight: 700; color: #212529; margin-bottom: 10px; font-size: 1.5rem; }
            .error-text { color: #6c757d; font-size: 1rem; line-height: 1.5; }
            @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
            @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); } 70% { box-shadow: 0 0 0 15px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-header">
                <div class="icon-wrapper"><i class="fas fa-link-slash"></i></div>
                <h1 class="error-title"><?php echo $titulo; ?></h1>
            </div>
            <div class="error-body"><p class="error-text"><?php echo $mensagem; ?></p></div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$token = htmlspecialchars($_GET['token'] ?? '');
if (empty($token)) {
    exibirErroFatal("Link Inválido", "Solicite um novo link ao servo de Territórios.", $baseUrl);
}

try {
    $stmt_user = $pdo->prepare("SELECT id, nome FROM users WHERE token_acesso = ? AND status = 'ativo'");
    $stmt_user->execute([$token]);
    $user = $stmt_user->fetch();

    if (!$user) {
        exibirErroFatal("Acesso Não Encontrado", "O link pode ter expirado ou o usuário foi alterado.", $baseUrl);
    }
    
    $user_id = $user['id'];
    
    $sql_mapas = "SELECT m.id, m.identificador, m.data_entrega, m.gdrive_file_id, m.grupo_id, g.nome as nome_grupo
                  FROM mapas m 
                  LEFT JOIN grupos g ON m.grupo_id = g.id
                  WHERE (m.dirigente_id = ? OR m.grupo_id IN (SELECT grupo_id FROM grupo_membros WHERE user_id = ?))
                  AND m.data_devolucao IS NULL";
    
    $stmt_mapas = $pdo->prepare($sql_mapas);
    $stmt_mapas->execute([$user_id, $user_id]);
    $mapas = $stmt_mapas->fetchAll();

    $quadras_por_mapa = [];
    if (!empty($mapas)) {
        $mapa_ids = array_column($mapas, 'id');
        $placeholders = implode(',', array_fill(0, count($mapa_ids), '?'));
        $stmt_quadras = $pdo->prepare("SELECT id, mapa_id, numero, pessoas_faladas FROM quadras WHERE mapa_id IN ($placeholders) ORDER BY numero ASC");
        $stmt_quadras->execute($mapa_ids);
        foreach ($stmt_quadras->fetchAll() as $quadra) {
            $quadras_por_mapa[$quadra['mapa_id']][] = $quadra;
        }
    }
} catch (PDOException $e) {
    exibirErroFatal("Erro no Sistema", "Problema de conexão com o banco de dados.", $baseUrl);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo $baseUrl; ?>site/backend/">
    <title>Mapas de <?php echo htmlspecialchars($user['nome']); ?></title>
    <link rel="icon" type="image/png" href="../images/map.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../style/css.css">
    <style> 
        body { padding: 15px; background-color: var(--content-bg); } 
        .quadra-item { border-bottom: 1px solid #eee; }
        .quadra-item:last-child { border-bottom: none; }
        .no-spinners::-webkit-outer-spin-button, .no-spinners::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .card-header-group { background-color: #4190be !important; border-color: #4190be !important; }
        
        .pdf-preview-container { position: relative; height: 300px; background-color: #e9ecef; border-bottom: 1px solid #dee2e6; display: flex; justify-content: center; align-items: center; overflow: hidden; }
        .pdf-preview-container img { max-width: 100%; max-height: 100%; object-fit: contain; cursor: pointer; }
        .pdf-preview-container .btn-expand { position: absolute; top: 8px; right: 8px; z-index: 10; }

        .card-collapsible-content { overflow: hidden; transition: max-height 0.4s ease, opacity 0.4s ease; max-height: 4000px; opacity: 1; }
        .card.collapsed .card-collapsible-content { max-height: 0; opacity: 0; }
        .card.card-interativo .card-header { cursor: pointer; user-select: none; }
        .header-icon { transition: transform 0.3s ease; }
        .card.collapsed .header-icon { transform: rotate(-90deg); }

        .masonry-layout { column-count: 1; column-gap: 1.5rem; }
        @media (min-width: 768px) { .masonry-layout { column-count: 2; } }
        @media (min-width: 1400px) { .masonry-layout { column-count: 3; } }
        .card-container-wrapper { break-inside: avoid; margin-bottom: 1.5rem; }
        @media (max-width: 768px) { body { zoom: 1.1; } }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark mb-4 rounded shadow-sm">
        <div class="container-fluid"><span class="navbar-brand"><i class="fas fa-map-marked-alt me-2"></i>Mapas de <?php echo htmlspecialchars($user['nome']); ?></span></div>
    </nav>
    <div class="container-fluid">
        <div class="masonry-layout" id="container-mapas">
        <?php if (empty($mapas)): ?>
            <div class="alert alert-info text-center w-100">Nenhum mapa atribuído a você ou seus grupos.</div>
        <?php else: 
            $total_cards = count($mapas);
            foreach ($mapas as $mapa): 
                $isGroup = !empty($mapa['grupo_id']);
                $soma_pessoas = 0;
                if (isset($quadras_por_mapa[$mapa['id']])) {
                    foreach ($quadras_por_mapa[$mapa['id']] as $q) $soma_pessoas += (int)$q['pessoas_faladas'];
                }
                $classe_inicial = ($total_cards > 1 && $soma_pessoas == 0) ? 'collapsed' : '';
        ?>
            <div class="card-container-wrapper" id="mapa-card-<?php echo $mapa['id']; ?>">
                <div class="card shadow-sm <?php echo $classe_inicial; ?>">
                    <div class="card-header <?php echo $isGroup ? 'card-header-group' : 'bg-primary'; ?> text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 d-flex align-items-center">
                            <i class="fas <?php echo $isGroup ? 'fa-users' : 'fa-map-pin'; ?> me-2"></i> 
                            <?php echo htmlspecialchars($mapa['identificador']); ?>
                        </h5>
                        <div class="d-flex align-items-center gap-2">
                            <?php if($isGroup): ?>
                                <span class="badge bg-white text-dark" style="opacity: 0.9; font-size: 0.65rem;">GRUPO: <?php echo htmlspecialchars($mapa['nome_grupo']); ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down header-icon"></i>
                        </div>
                    </div>
                    
                    <div class="card-collapsible-content">
                        <?php
                        $nome_identificador = $mapa['identificador'];
                        $url_jpg = "pdfs/" . rawurlencode($nome_identificador) . ".jpg";
                        $url_pdf = "pdfs/" . rawurlencode($nome_identificador) . ".pdf";
                        $caminho_local_jpg = __DIR__ . "/pdfs/" . $nome_identificador . ".jpg";
                        $caminho_local_pdf = __DIR__ . "/pdfs/" . $nome_identificador . ".pdf";

                        if (file_exists($caminho_local_jpg)):
                        ?>
                            <div class="pdf-preview-container">
                                <img src="<?php echo $url_jpg; ?>" data-bs-toggle="modal" data-bs-target="#pdfModal" data-img-src="<?php echo $url_jpg; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                                <button class="btn btn-primary btn-sm btn-expand" data-bs-toggle="modal" data-bs-target="#pdfModal" data-img-src="<?php echo $url_jpg; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                                    <i class="fas fa-expand-alt me-1"></i> Expandir
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if (file_exists($caminho_local_pdf)): ?>
                            <div class="px-3 pt-3">
                                <a href="<?php echo $url_pdf; ?>" class="btn btn-outline-secondary w-100" download="<?php echo htmlspecialchars($nome_identificador . '.pdf'); ?>">
                                    <i class="fas fa-file-download me-2"></i> Baixar Mapa em PDF
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="card-body">
                            <form class="form-devolver" data-mapa-id="<?php echo $mapa['id']; ?>" data-mapa-nome="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                                <div class="list-group list-group-flush mb-3 quadra-list" data-mapa-id="<?php echo $mapa['id']; ?>">
                                <?php if (isset($quadras_por_mapa[$mapa['id']])): foreach ($quadras_por_mapa[$mapa['id']] as $quadra): ?>
                                    <div class="list-group-item quadra-item d-flex justify-content-between align-items-center p-2">
                                        <span>Quadra <strong><?php echo $quadra['numero']; ?></strong></span>
                                        <div class="d-flex align-items-center">
                                            <div class="input-group input-group-sm" style="width: 120px;">
                                                <button class="btn btn-outline-secondary btn-decrement" type="button">-</button>
                                                <input type="number" class="form-control text-center quadra-input no-spinners" 
                                                       value="<?php echo $quadra['pessoas_faladas']; ?>" 
                                                       data-quadra-id="<?php echo $quadra['id']; ?>" 
                                                       data-previous-value="<?php echo $quadra['pessoas_faladas']; ?>" min="0">
                                                <button class="btn btn-outline-secondary btn-increment" type="button">+</button>
                                            </div>
                                            <div class="ms-2" style="width: 24px;" id="status_save_q<?php echo $quadra['id']; ?>"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-2 border-top fw-bold bg-light"> 
                                    <span>Total</span> 
                                    <span class="fs-5" id="total-pessoas-mapa-<?php echo $mapa['id']; ?>"><?php echo $soma_pessoas; ?></span> 
                                </div>
                                <?php endif; ?>
                                </div>
                                <hr>
                                <p class="mb-2"><strong>Recebido em:</strong> <?php echo date('d/m/Y', strtotime($mapa['data_entrega'])); ?></p>
                                <div class="d-grid mt-3">
                                    <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i> Finalizar e Devolver</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Modais -->
    <div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Visualizador</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0 d-flex justify-content-center bg-black"><img id="modal-img" src="" style="max-width:100%; object-fit:contain;"></div></div></div>
    </div>
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="feedbackModalTitle">Aviso</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="feedbackModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button></div></div></div>
    </div>
    <div class="modal fade" id="confirmacaoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmação</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="confirmacaoModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="btnConfirmarAcao">Confirmar</button></div></div></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../script/common.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const API_BASE_URL = '.'; 
            const saveTimeouts = {};
            const pendingDeltas = {};

            const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
            const confirmacaoModal = new bootstrap.Modal(document.getElementById('confirmacaoModal'));
            const btnConfirmarAcao = document.getElementById('btnConfirmarAcao');

            const mostrarFeedback = (titulo, mensagem, tipo = 'primary') => {
                document.getElementById('feedbackModalTitle').textContent = titulo;
                document.getElementById('feedbackModalBody').innerHTML = mensagem;
                document.querySelector('#feedbackModal .modal-header').className = `modal-header bg-${tipo} text-white`;
                feedbackModal.show();
            };

            const mostrarConfirmacao = (titulo, mensagem, callback) => {
                document.getElementById('confirmacaoModalBody').innerHTML = mensagem;
                btnConfirmarAcao.onclick = () => { confirmacaoModal.hide(); callback(); };
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
                    if (card.classList.contains('card-interativo')) card.classList.toggle('collapsed');
                }
            });

            gerenciarColapsoCards();

            const saveQuadra = async (quadraId, statusDiv) => {
                const delta = pendingDeltas[quadraId];
                if (!delta) return;
                pendingDeltas[quadraId] = 0;
                statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span>';
                try {
                    await fetch(`${API_BASE_URL}/mapas_api.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'update_quadra_increment', quadra_id: quadraId, delta: delta })
                    });
                    statusDiv.innerHTML = '<i class="fas fa-check text-success"></i>';
                    setTimeout(() => { statusDiv.innerHTML = ''; }, 2000);
                } catch (e) { statusDiv.innerHTML = '<i class="fas fa-times text-danger"></i>'; }
            };

            document.querySelectorAll('.quadra-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const qId = e.target.dataset.quadraId;
                    const diff = (parseInt(e.target.value) || 0) - (parseInt(e.target.dataset.previousValue) || 0);
                    if (diff !== 0) {
                        pendingDeltas[qId] = (pendingDeltas[qId] || 0) + diff;
                        e.target.dataset.previousValue = e.target.value;
                        clearTimeout(saveTimeouts[qId]);
                        saveTimeouts[qId] = setTimeout(() => saveQuadra(qId, document.getElementById(`status_save_q${qId}`)), 800);
                        let total = 0;
                        e.target.closest('.quadra-list').querySelectorAll('.quadra-input').forEach(i => total += (parseInt(i.value) || 0));
                        document.getElementById(`total-pessoas-mapa-${e.target.closest('.quadra-list').dataset.mapaId}`).textContent = total;
                    }
                });
            });

            document.querySelectorAll('.btn-increment').forEach(b => b.onclick = (e) => { const i = e.target.closest('.input-group').querySelector('.quadra-input'); i.value = (parseInt(i.value)||0)+1; i.dispatchEvent(new Event('input')); });
            document.querySelectorAll('.btn-decrement').forEach(b => b.onclick = (e) => { const i = e.target.closest('.input-group').querySelector('.quadra-input'); if(parseInt(i.value)>0){ i.value = parseInt(i.value)-1; i.dispatchEvent(new Event('input')); } });

            document.querySelectorAll('.form-devolver').forEach(form => {
                form.onsubmit = (e) => {
                    e.preventDefault();
                    mostrarConfirmacao('Finalizar Mapa', `Deseja devolver <b>${e.target.dataset.mapaNome}</b>?`, async () => {
                        try {
                            await fetch(`${API_BASE_URL}/mapas_api.php`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ action: 'devolver', mapa_id: e.target.dataset.mapaId, data_devolucao: new Date().toISOString().split('T')[0] })
                            });
                            location.reload();
                        } catch (e) { mostrarFeedback('Erro', 'Falha ao devolver.'); }
                    });
                };
            });

            document.getElementById('pdfModal').addEventListener('show.bs.modal', (e) => { 
                const btn = e.relatedTarget;
                document.getElementById('modal-img').src = btn.dataset.imgSrc; 
            });
        });
    </script>
</body>
</html>
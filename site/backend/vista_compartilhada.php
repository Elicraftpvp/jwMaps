<?php
// site/backend/vista_compartilhada.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require_once 'conexao.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$path = (strpos($_SERVER['REQUEST_URI'], '/jwMaps') !== false) ? "/jwMaps/" : "/";
$baseUrl = $protocol . $domainName . $path;

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
        <style>
            body { background-color: #f0f2f5; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; margin: 0; }
            .error-card { background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); max-width: 420px; width: 90%; text-align: center; overflow: hidden; border-top: 5px solid #dc3545; padding: 40px 20px; }
            .error-title { font-weight: 700; color: #212529; margin-bottom: 10px; font-size: 1.5rem; }
        </style>
    </head>
    <body>
        <div class="error-card"><h1 class="error-title"><?php echo $titulo; ?></h1><p><?php echo $mensagem; ?></p></div>
    </body>
    </html>
    <?php
    exit;
}

$share_token = htmlspecialchars($_GET['s'] ?? '');
if (empty($share_token)) exibirErroFatal("Link Inválido", "Acesse através de um link válido.", $baseUrl);

try {
    // 1. Busca os detalhes básicos do compartilhamento
    $stmt_c = $pdo->prepare("SELECT mapa_id, tipo, expira_em FROM compartilhamentos WHERE token = ? AND expira_em > NOW()");
    $stmt_c->execute([$share_token]);
    $c_data = $stmt_c->fetch();

    if (!$c_data) exibirErroFatal("Link Expirado", "Este acesso não é mais válido ou expirou.", $baseUrl);

    $mapa_id = $c_data['mapa_id'];
    $tipo_compartilhamento = $c_data['tipo']; // 'normal' ou 'predio'

    if ($tipo_compartilhamento === 'predio') {
        $stmt_mapa = $pdo->prepare("
            SELECT m.*, u.nome as dirigente_nome, g.nome as nome_grupo
            FROM mapas_predio m
            LEFT JOIN users u ON m.dirigente_id = u.id
            LEFT JOIN grupos g ON m.grupo_id = g.id
            WHERE m.id = ?
        ");
        $stmt_items = $pdo->prepare("SELECT id, numero, pessoas_faladas FROM blocos WHERE mapa_id = ? ORDER BY numero ASC");
        $label_item = "Bloco";
        $api_url = "./mapas_predio_api.php";
        $api_action = "update_bloco_increment";
        $api_id_field = "bloco_id";
    } else {
        $stmt_mapa = $pdo->prepare("
            SELECT m.*, u.nome as dirigente_nome, g.nome as nome_grupo
            FROM mapas m
            LEFT JOIN users u ON m.dirigente_id = u.id
            LEFT JOIN grupos g ON m.grupo_id = g.id
            WHERE m.id = ?
        ");
        $stmt_items = $pdo->prepare("SELECT id, numero, pessoas_faladas FROM quadras WHERE mapa_id = ? ORDER BY numero ASC");
        $label_item = "Quadra";
        $api_url = "./mapas_api.php";
        $api_action = "update_quadra_increment";
        $api_id_field = "quadra_id";
    }

    $stmt_mapa->execute([$mapa_id]);
    $mapa = $stmt_mapa->fetch();

    if (!$mapa) exibirErroFatal("Não Encontrado", "O território associado não foi encontrado.", $baseUrl);

    $stmt_items->execute([$mapa_id]);
    $items = $stmt_items->fetchAll();

    $soma_pessoas = 0;
    foreach ($items as $item) $soma_pessoas += (int)$item['pessoas_faladas'];

    $url_jpg = "pdfs/" . rawurlencode($mapa['identificador']) . ".jpg";
    $url_pdf = "pdfs/" . rawurlencode($mapa['identificador']) . ".pdf";
    $caminho_local_jpg = __DIR__ . "/pdfs/" . $mapa['identificador'] . ".jpg";
    
    $isGroup = !empty($mapa['grupo_id']);
    $agora = new DateTime();
    $expira = new DateTime($c_data['expira_em']);
    $segundos_restantes = $expira->getTimestamp() - $agora->getTimestamp();
    if ($segundos_restantes < 0) $segundos_restantes = 0;

    // Nome de quem compartilhou (Prioriza Dirigente, cai para Grupo se for mapa só de grupo)
    $quem_compartilhou = $mapa['dirigente_nome'] ?? ($mapa['nome_grupo'] ?? 'Território');

} catch (PDOException $e) { exibirErroFatal("Erro", "Falha na conexão: " . $e->getMessage(), $baseUrl); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <base href="<?php echo $baseUrl; ?>site/backend/">
    <title>Compartilhamento - <?php echo htmlspecialchars($mapa['identificador']); ?></title>
    <link rel="icon" type="image/png" href="../images/map.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { padding: 15px; background-color: #f8f9fa; }
        .share-banner { background: rgba(56, 137, 253, 0.1); color: #FFA000; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(56, 137, 253, 0.3); font-weight: 600; text-align: center; }
        .card-header-group { background-color: #4190be !important; border-color: #4190be !important; }
        .bg-custom-share { background-color: #FFA000 !important; color: white !important; }
        .text-custom-share { color: #FFA000 !important; }
        .no-spinners::-webkit-outer-spin-button, .no-spinners::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .no-spinners { -moz-appearance: textfield; }
        .q-input { font-size: 16px !important; }
        .btn-inc, .btn-dec { touch-action: manipulation; }
        .pdf-preview-container { position: relative; height: 250px; background-color: #eee; display: flex; justify-content: center; align-items: center; overflow: hidden; border-bottom: 1px solid #ddd; }
        .pdf-preview-container img { max-width: 100%; max-height: 100%; object-fit: contain; }

        /* Estilos para o campo de Notas */
        .obs-container { margin-top: 1rem; border-top: 1px solid #dee2e6; padding-top: 0.8rem; }
        .btn-obs-toggle { background: #f8f9fa; border: 1px solid #dee2e6; color: #495057; width: 100%; text-align: left; padding: 10px 15px; border-radius: 8px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s; }
        .btn-obs-toggle:hover { background: #e9ecef; }
        .obs-content { display: none; padding: 12px 15px; background: white; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 8px 8px; font-size: 0.95rem; line-height: 1.4; color: #333; }
        .btn-obs-toggle.active { border-radius: 8px 8px 0 0; background: #e9ecef; }
        .obs-content h1, .obs-content h2, .obs-content h3 { font-weight: 700; margin-bottom: 8px; color: #212529; }
        .obs-content h1 { font-size: 1.25rem; }
        .obs-content h2 { font-size: 1.15rem; }
        .obs-content h3 { font-size: 1.05rem; }
        .obs-content ul { padding-left: 20px; margin-bottom: 0; }
        .obs-content li { margin-bottom: 4px; }
        .obs-content li:last-child { margin-bottom: 0; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="share-banner">
            <i class="fas fa-layer-group me-2"></i> 
            <?php echo htmlspecialchars($quem_compartilhou); ?> compartilhou <?php echo htmlspecialchars($mapa['identificador']); ?> 
            restando <span id="timer" class="badge bg-custom-share">--:--</span>
        </div>

        <div class="card shadow-sm mx-auto" style="max-width: 600px;">
            <div class="card-header d-flex justify-content-between align-items-center <?php echo $isGroup ? 'card-header-group' : 'bg-custom-share'; ?> text-white">
                <h5 class="mb-0">
                    <i class="fas <?php echo $isGroup ? 'fa-users' : 'fa-link'; ?> me-2"></i>
                    <?php echo htmlspecialchars($mapa['identificador']); ?>
                </h5>
            </div>
            
            <?php if (file_exists($caminho_local_jpg)): ?>
            <div class="pdf-preview-container">
                <img src="<?php echo $url_jpg; ?>" data-bs-toggle="modal" data-bs-target="#pdfModal">
            </div>
            <?php elseif (!empty($mapa['gdrive_file_id'])): ?>
            <div class="pdf-preview-container">
                <iframe src="https://drive.google.com/file/d/<?php echo $mapa['gdrive_file_id']; ?>/preview" style="width:100%;height:100%;border:none;"></iframe>
            </div>
            <?php endif; ?>

            <div class="card-body">
                <?php if(!empty($mapa['obs'])): ?>
                <div class="obs-container mb-3 mt-0" style="border-top: none; padding-top: 0;">
                    <button type="button" class="btn-obs-toggle" onclick="toggleObs(this)">
                        <span><i class="fas fa-sticky-note me-2 text-warning"></i> Notas</span>
                        <i class="fas fa-plus"></i>
                    </button>
                    <div class="obs-content" data-raw-obs="<?php echo htmlspecialchars($mapa['obs']); ?>"></div>
                </div>
                <?php endif; ?>

                <label class="form-label fw-bold">Pessoas Encontradas:</label>
                <div class="list-group list-group-flush mb-3">
                    <?php foreach ($items as $item): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-1">
                        <span><?php echo $label_item; ?> <b><?php echo $item['numero']; ?></b></span>
                        <div class="d-flex align-items-center">
                            <div class="input-group" style="width: 140px;">
                                <button class="btn btn-outline-secondary btn-dec" type="button">-</button>
                                <input type="number" class="form-control text-center q-input no-spinners fw-bold" 
                                       value="<?php echo $item['pessoas_faladas']; ?>" 
                                       data-id="<?php echo $item['id']; ?>" data-prev="<?php echo $item['pessoas_faladas']; ?>" readonly>
                                <button class="btn btn-outline-secondary btn-inc" type="button">+</button>
                            </div>
                            <div class="ms-1" id="st_<?php echo $item['id']; ?>" style="width: 20px;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center bg-light fw-bold px-1">
                        <span>Total</span>
                        <span style="width: 140px; text-align: center;" id="map-total"><?php echo $soma_pessoas; ?></span>
                    </div>
                </div>
                <?php if (file_exists(__DIR__ . "/pdfs/" . $mapa['identificador'] . ".pdf")): ?>
                    <a href="<?php echo $url_pdf; ?>" class="btn btn-outline-dark w-100" download><i class="fas fa-file-pdf me-2"></i>Baixar PDF</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal PDF -->
    <div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content bg-black">
                <div class="modal-header border-0 bg-transparent text-white" style="position:absolute; z-index:10; width:100%;">
                    <h5 class="modal-title"><?php echo htmlspecialchars($mapa['identificador']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0 d-flex justify-content-center align-items-center"><img src="<?php echo $url_jpg; ?>" style="max-width:100%; max-height:100%;"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Função para parsear a observação
            window.parseObs = (text) => {
                let html = text;
                html = html.replace(/^h1\s+(.*)$/gim, '<h1>$1</h1>');
                html = html.replace(/^h2\s+(.*)$/gim, '<h2>$1</h2>');
                html = html.replace(/^h3\s+(.*)$/gim, '<h3>$1</h3>');
                
                // Convert <"Name"="URL"> or <Name="URL"> to hyperlink
                html = html.replace(/<"([^\"<>]+)"="([^\"<>]+)">/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
                html = html.replace(/<([^=<>\"]+)="([^\"<>]+)">/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
                
                let lines = html.split('\n');
                let inList = false;
                let out = '';
                
                lines.forEach(line => {
                    let trimmed = line.trim();
                    if (trimmed.startsWith('-')) {
                        if (!inList) {
                            out += '<ul>';
                            inList = true;
                        }
                        out += `<li>${trimmed.substring(1).trim()}</li>`;
                    } else {
                        if (inList) {
                            out += '</ul>';
                            inList = false;
                        }
                        
                        if (trimmed.startsWith('<h1') || trimmed.startsWith('<h2') || trimmed.startsWith('<h3')) {
                            out += trimmed;
                        } else if (trimmed === '') {
                            if (out !== '' && !out.endsWith('>') && !out.endsWith('<br>')) {
                                out += '<br>';
                            }
                        } else {
                            if (out !== '' && !out.endsWith('>') && !out.endsWith('<br>')) {
                                out += '<br>';
                            }
                            out += line;
                        }
                    }
                });
                if (inList) out += '</ul>';
                return out;
            };

            window.toggleObs = (btn) => {
                const container = btn.closest('.obs-container');
                const content = container.querySelector('.obs-content');
                const icon = btn.querySelector('i.fa-plus, i.fa-minus');
                const isOpening = content.style.display !== 'block';
                if (isOpening) {
                    if (!content.dataset.parsed) { content.innerHTML = parseObs(content.dataset.rawObs); content.dataset.parsed = "true"; }
                    content.style.display = 'block';
                    btn.classList.add('active');
                    if(icon) { icon.classList.replace('fa-plus', 'fa-minus'); }
                } else {
                    content.style.display = 'none';
                    btn.classList.remove('active');
                    if(icon) { icon.classList.replace('fa-minus', 'fa-plus'); }
                }
            };

            let segundosRestantes = <?php echo $segundos_restantes; ?>;
            const timer = document.getElementById('timer');
            
            const renderTimer = () => {
                const h = Math.floor(segundosRestantes / 3600);
                const m = Math.floor((segundosRestantes % 3600) / 60);
                const s = Math.floor(segundosRestantes % 60);
                timer.textContent = (h > 0 ? h + "h " : "") + m.toString().padStart(2, '0') + ":" + s.toString().padStart(2, '0');
            };
            renderTimer();

            const countdown = setInterval(() => {
                segundosRestantes--;
                if (segundosRestantes < 0) { clearInterval(countdown); timer.textContent = "EXPIRADO"; location.reload(); return; }
                renderTimer();
            }, 1000);

            const pending = {};

            // --- Polling Sincronização Tempo Real ---
            const mapId = <?php echo json_encode($mapa_id); ?>;
            const isPredio = <?php echo $tipo_compartilhamento === 'predio' ? '1' : '0'; ?>;
            const shareToken = <?php echo json_encode($share_token); ?>;
            
            if (shareToken) {
                setInterval(async () => {
                    try {
                        const res = await fetch(`./sync_api.php?share_token=${shareToken}`);
                        const data = await res.json();
                        if (data.error) return;

                        if (data.devolvidos && data.devolvidos.length > 0) {
                            location.reload();
                            return;
                        }

                        const items = isPredio == '1' ? data.blocos : data.quadras;
                        if (items) {
                            let total = 0;
                            Object.keys(items).forEach(id => {
                                const input = document.querySelector(`.q-input[data-id="${id}"]`);
                                if (input) {
                                    if (!pending[id]) {
                                        input.value = items[id];
                                        input.dataset.prev = items[id];
                                    }
                                    total += parseInt(input.value) || 0;
                                }
                            });
                            const tSpan = document.getElementById('map-total');
                            if (tSpan) tSpan.textContent = total;
                        }

                        const obsMapId = isPredio == '1' ? 'p' + mapId : mapId;
                        if (data.obs && data.obs[obsMapId] !== undefined) {
                            const obsTxt = data.obs[obsMapId];
                            const obsContainer = document.querySelector('.obs-content');
                            if (obsContainer && obsContainer.dataset.rawObs !== obsTxt) {
                                obsContainer.dataset.rawObs = obsTxt;
                                obsContainer.dataset.parsed = "";
                                if (obsContainer.style.display === 'block') {
                                    obsContainer.innerHTML = window.parseObs ? window.parseObs(obsTxt) : obsTxt;
                                    obsContainer.dataset.parsed = "true";
                                }
                            }
                        }
                    } catch (e) {
                        console.log('Erro no polling', e);
                    }
                }, 3000);
            }

            const save = async (id, stDiv) => {
                const delta = pending[id]; if (!delta) return; pending[id] = 0;
                stDiv.innerHTML = '<span class="spinner-border spinner-border-sm text-custom-share"></span>';
                try {
                    await fetch('<?php echo $api_url; ?>', { 
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: '<?php echo $api_action; ?>', <?php echo $api_id_field; ?>: id, delta: delta }) 
                    });
                    stDiv.innerHTML = '<i class="fas fa-check text-success"></i>';
                    setTimeout(() => stDiv.innerHTML = '', 1500);
                } catch (e) { stDiv.innerHTML = 'x'; }
            };

            document.querySelectorAll('.q-input').forEach(inp => {
                inp.oninput = () => {
                    const id = inp.dataset.id;
                    const diff = (parseInt(inp.value)||0) - (parseInt(inp.dataset.prev)||0);
                    if (diff !== 0) {
                        pending[id] = (pending[id]||0) + diff; inp.dataset.prev = inp.value;
                        clearTimeout(inp.t); inp.t = setTimeout(() => save(id, document.getElementById('st_'+id)), 800);
                        let tot = 0; document.querySelectorAll('.q-input').forEach(i => tot += (parseInt(i.value)||0));
                        document.getElementById('map-total').textContent = tot;
                    }
                };
            });
            document.querySelectorAll('.btn-inc').forEach(b => b.onclick = () => { const i = b.parentElement.querySelector('input'); i.value = (parseInt(i.value)||0)+1; i.dispatchEvent(new Event('input')); });
            document.querySelectorAll('.btn-dec').forEach(b => b.onclick = () => { const i = b.parentElement.querySelector('input'); if(parseInt(i.value)>0){ i.value = parseInt(i.value)-1; i.dispatchEvent(new Event('input')); } });
        });
    </script>
</body>
</html>
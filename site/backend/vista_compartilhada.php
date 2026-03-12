<?php
// site/backend/vista_compartilhada.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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
    $stmt_share = $pdo->prepare("
        SELECT c.expira_em, u.nome as dirigente_nome, m.*, g.nome as nome_grupo
        FROM compartilhamentos c
        JOIN mapas m ON c.mapa_id = m.id
        JOIN users u ON m.dirigente_id = u.id
        LEFT JOIN grupos g ON m.grupo_id = g.id
        WHERE c.token = ? AND c.expira_em > NOW()
    ");
    $stmt_share->execute([$share_token]);
    $mapa = $stmt_share->fetch();

    if (!$mapa) exibirErroFatal("Link Expirado", "Este acesso não é mais válido ou expirou.", $baseUrl);

    $stmt_quadras = $pdo->prepare("SELECT id, numero, pessoas_faladas FROM quadras WHERE mapa_id = ? ORDER BY numero ASC");
    $stmt_quadras->execute([$mapa['id']]);
    $quadras = $stmt_quadras->fetchAll();

    $soma_pessoas = 0;
    foreach ($quadras as $q) $soma_pessoas += (int)$q['pessoas_faladas'];

    $url_jpg = "pdfs/" . rawurlencode($mapa['identificador']) . ".jpg";
    $url_pdf = "pdfs/" . rawurlencode($mapa['identificador']) . ".pdf";
    $caminho_local_jpg = __DIR__ . "/pdfs/" . $mapa['identificador'] . ".jpg";
} catch (PDOException $e) { exibirErroFatal("Erro", "Falha na conexão.", $baseUrl); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo $baseUrl; ?>site/backend/">
    <title>Compartilhamento - <?php echo htmlspecialchars($mapa['identificador']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { padding: 15px; background-color: #f8f9fa; }
        .share-banner { background: #e7f3ff; color: #0056b3; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #b8daff; font-weight: 600; text-align: center; }
        .card-header { background-color: #007bff; color: white; }
        .no-spinners::-webkit-outer-spin-button, .no-spinners::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .pdf-preview-container { position: relative; height: 250px; background-color: #eee; display: flex; justify-content: center; align-items: center; overflow: hidden; border-bottom: 1px solid #ddd; }
        .pdf-preview-container img { max-width: 100%; max-height: 100%; object-fit: contain; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="share-banner">
            <i class="fas fa-user-friends me-2"></i> 
            <?php echo htmlspecialchars($mapa['dirigente_nome']); ?> compartilhou <?php echo htmlspecialchars($mapa['identificador']); ?> 
            restando <span id="timer" class="badge bg-primary">--:--</span>
        </div>

        <div class="card shadow-sm mx-auto" style="max-width: 600px;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo htmlspecialchars($mapa['identificador']); ?></h5>
            </div>
            
            <?php if (file_exists($caminho_local_jpg)): ?>
            <div class="pdf-preview-container">
                <img src="<?php echo $url_jpg; ?>" data-bs-toggle="modal" data-bs-target="#pdfModal">
            </div>
            <?php endif; ?>

            <div class="card-body">
                <label class="form-label fw-bold">Pessoas Encontradas:</label>
                <div class="list-group list-group-flush mb-3">
                    <?php foreach ($quadras as $q): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-1">
                        <span>Quadra <b><?php echo $q['numero']; ?></b></span>
                        <div class="d-flex align-items-center">
                            <div class="input-group" style="width: 140px;">
                                <button class="btn btn-outline-secondary btn-dec" type="button">-</button>
                                <input type="number" class="form-control text-center q-input no-spinners fw-bold" 
                                       value="<?php echo $q['pessoas_faladas']; ?>" 
                                       data-id="<?php echo $q['id']; ?>" data-prev="<?php echo $q['pessoas_faladas']; ?>" readonly>
                                <button class="btn btn-outline-secondary btn-inc" type="button">+</button>
                            </div>
                            <div class="ms-1" id="st_<?php echo $q['id']; ?>" style="width: 20px;"></div>
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
            const exp = new Date("<?php echo str_replace(' ', 'T', $mapa['expira_em']); ?>").getTime();
            const timer = document.getElementById('timer');
            const countdown = setInterval(() => {
                const now = new Date().getTime();
                const dist = exp - now;
                if (dist < 0) { clearInterval(countdown); timer.textContent = "EXPIRADO"; location.reload(); return; }
                const h = Math.floor(dist / 3600000);
                const m = Math.floor((dist % 3600000) / 60000);
                const s = Math.floor((dist % 60000) / 1000);
                timer.textContent = (h > 0 ? h + "h " : "") + m.toString().padStart(2, '0') + ":" + s.toString().padStart(2, '0');
            }, 1000);

            const pending = {};
            const save = async (id, stDiv) => {
                const delta = pending[id]; if (!delta) return; pending[id] = 0;
                stDiv.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span>';
                try {
                    await fetch('./mapas_api.php', { method: 'POST', body: JSON.stringify({ action: 'update_quadra_increment', quadra_id: id, delta: delta }) });
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
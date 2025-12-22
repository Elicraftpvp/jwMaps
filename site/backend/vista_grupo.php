<?php
// site/backend/vista_grupo.php
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
        <title>Aviso de Grupo</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body { background-color: #f0f2f5; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; margin: 0; }
            .error-card { background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); max-width: 420px; width: 90%; text-align: center; border-top: 5px solid #6c757d; padding: 40px 20px; }
            .icon-wrapper { font-size: 3rem; color: #6c757d; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="icon-wrapper"><i class="fas fa-users-slash"></i></div>
            <h1 class="h4"><?php echo $titulo; ?></h1>
            <p class="text-muted"><?php echo $mensagem; ?></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$token = htmlspecialchars($_GET['token'] ?? '');
if (empty($token)) {
    exibirErroFatal("Link de Grupo Inválido", "O link acessado está incorreto.", $baseUrl);
}

try {
    // Busca o grupo pelo token
    $stmt_grupo = $pdo->prepare("SELECT id, nome FROM grupos WHERE token_acesso = ? AND status = 'ativo'");
    $stmt_grupo->execute([$token]);
    $grupo = $stmt_grupo->fetch();

    if (!$grupo) {
        exibirErroFatal("Grupo não encontrado", "Este link de grupo pode ter sido desativado ou alterado.", $baseUrl);
    }

    $grupo_id = $grupo['id'];
    
    // Puxar apenas mapas vinculados a este GRUPO
    $stmt_mapas = $pdo->prepare("SELECT id, identificador, data_entrega, gdrive_file_id FROM mapas WHERE grupo_id = ?");
    $stmt_mapas->execute([$grupo_id]);
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
    exibirErroFatal("Erro no Sistema", "Falha na conexão.", $baseUrl);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo $baseUrl; ?>site/backend/">
    <title>Mapas do Grupo: <?php echo htmlspecialchars($grupo['nome']); ?></title>
    <link rel="icon" type="image/png" href="../images/map.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../style/css.css">
    <style>
        body { padding: 15px; background-color: #f8f9fa; }
        .masonry-layout { column-count: 1; column-gap: 1.5rem; }
        @media (min-width: 768px) { .masonry-layout { column-count: 2; } }
        .card-container-wrapper { break-inside: avoid; margin-bottom: 1.5rem; }
        .pdf-preview-container { height: 250px; background: #eee; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .pdf-preview-container img { max-height: 100%; cursor: pointer; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-secondary mb-4 rounded shadow-sm">
        <div class="container-fluid"><span class="navbar-brand"><i class="fas fa-layer-group me-2"></i>Mapas do Grupo: <?php echo htmlspecialchars($grupo['nome']); ?></span></div>
    </nav>
    <div class="container-fluid">
        <div class="masonry-layout">
        <?php if (empty($mapas)): ?>
            <div class="alert alert-light text-center w-100 border">Nenhum mapa vinculado a este grupo no momento.</div>
        <?php else: foreach ($mapas as $mapa): 
            $soma = 0;
            if(isset($quadras_por_mapa[$mapa['id']])) {
                foreach($quadras_por_mapa[$mapa['id']] as $q) $soma += (int)$q['pessoas_faladas'];
            }
        ?>
            <div class="card-container-wrapper">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h6 class="mb-0"><?php echo htmlspecialchars($mapa['identificador']); ?></h6>
                    </div>
                    <?php 
                        $url_jpg = "pdfs/" . rawurlencode($mapa['identificador']) . ".jpg";
                        if (file_exists(__DIR__ . "/pdfs/" . $mapa['identificador'] . ".jpg")):
                    ?>
                        <div class="pdf-preview-container">
                            <img src="<?php echo $url_jpg; ?>" data-bs-toggle="modal" data-bs-target="#pdfModal" data-img-src="<?php echo $url_jpg; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="list-group list-group-flush mb-3">
                            <?php if (isset($quadras_por_mapa[$mapa['id']])): foreach ($quadras_por_mapa[$mapa['id']] as $quadra): ?>
                                <div class="list-group-item d-flex justify-content-between p-2">
                                    <small>Quadra <?php echo $quadra['numero']; ?></small>
                                    <span class="badge bg-light text-dark border"><?php echo $quadra['pessoas_faladas']; ?> pessoas</span>
                                </div>
                            <?php endforeach; ?>
                            <div class="list-group-item d-flex justify-content-between bg-light fw-bold">
                                <span>Total</span>
                                <span><?php echo $soma; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Visualização de grupo (Somente Leitura)</small>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Visualizador</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0 d-flex justify-content-center bg-black"><img id="modal-img" src="" style="max-width:100%; object-fit:contain;"></div></div></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('pdfModal').addEventListener('show.bs.modal', (e) => {
            document.getElementById('modal-img').src = e.relatedTarget.getAttribute('data-img-src');
        });
    </script>
</body>
</html>
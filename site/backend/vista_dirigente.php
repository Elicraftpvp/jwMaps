<?php
// site/backend/vista_dirigente.php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'conexao.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$path = (strpos($_SERVER['REQUEST_URI'], '/jwMaps') !== false) ? "/jwMaps/" : "/";
$baseUrl = $protocol . $domainName . $path;

/**
 * Renderiza a tela de erro com a identidade visual completa.
 */
function exibirErroFatal($titulo, $mensagem, $baseUrl) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Aviso do Sistema</title>
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
                <div class="icon-wrapper"><i class="fas fa-exclamation-triangle"></i></div>
                <h1 class="error-title"><?php echo $titulo; ?></h1>
            </div>
            <div class="error-body"><p class="error-text"><?php echo $mensagem; ?></p></div>
            <div class="p-3 bg-light border-top">
                <a href="<?php echo $baseUrl; ?>site/pages/dashboard.html" class="btn btn-primary w-100">Voltar ao Início</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (!isset($_SESSION['user_id'])) {
    exibirErroFatal("Acesso Negado", "Você precisa estar logado para ver esta página.", $baseUrl);
}
$user_id = $_SESSION['user_id'];

/**
 * Função auxiliar para renderizar o card do mapa HTML.
 */
function renderizarCard($mapa, $quadras_por_mapa, $total_cards_geral) {
    $isGroup = !empty($mapa['grupo_id']);
    $soma_pessoas = 0;
    if (isset($quadras_por_mapa[$mapa['id']])) {
        foreach ($quadras_por_mapa[$mapa['id']] as $q) $soma_pessoas += (int)$q['pessoas_faladas'];
    }
    $classe_inicial = ($total_cards_geral > 1 && $soma_pessoas == 0) ? 'collapsed' : '';
    
    $nome_identificador = $mapa['identificador'];
    $url_jpg = "pdfs/" . rawurlencode($nome_identificador) . ".jpg";
    $url_pdf = "pdfs/" . rawurlencode($nome_identificador) . ".pdf";
    $caminho_local_jpg = __DIR__ . "/pdfs/" . $nome_identificador . ".jpg";
    $caminho_local_pdf = __DIR__ . "/pdfs/" . $nome_identificador . ".pdf";
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

                        <button class="btn btn-light btn-sm btn-share-map border-0" 
                                style="background: rgba(255,255,255,0.2); color: white;"
                                data-mapa-id="<?php echo $mapa['id']; ?>" 
                                data-mapa-nome="<?php echo htmlspecialchars($mapa['identificador']); ?>"
                                data-is-group="<?php echo $isGroup ? '1' : '0'; ?>"
                                title="Compartilhar temporariamente">
                            <i class="fas fa-share-alt"></i>
                        </button>

                        <i class="fas fa-chevron-down header-icon"></i>
                    </div>
                </h5>
            </div>
            
            <div class="card-collapsible-content">
                <?php if (file_exists($caminho_local_jpg)): ?>
                    <div class="pdf-preview-container">
                        <img src="<?php echo $url_jpg; ?>" data-bs-toggle="modal" data-bs-target="#pdfModal" data-img-src="<?php echo $url_jpg; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                        <button class="btn <?php echo $isGroup ? 'btn-group-color' : 'btn-primary'; ?> btn-sm btn-expand" data-bs-toggle="modal" data-bs-target="#pdfModal" data-img-src="<?php echo $url_jpg; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                            <i class="fas fa-expand-alt me-1"></i> Expandir
                        </button>
                    </div>
                <?php elseif (!empty($mapa['gdrive_file_id'])): ?>
                    <?php $pdf_embed_url = "https://drive.google.com/file/d/" . $mapa['gdrive_file_id'] . "/preview"; ?>
                    <div class="pdf-preview-container">
                        <iframe src="<?php echo $pdf_embed_url; ?>" style="width:100%;height:100%;border:none;"></iframe>
                        <button class="btn <?php echo $isGroup ? 'btn-group-color' : 'btn-primary'; ?> btn-sm btn-expand" data-bs-toggle="modal" data-bs-target="#pdfModal" data-pdf-src="<?php echo $pdf_embed_url; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
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
                        <?php if(!empty($mapa['obs'])): ?>
                        <div class="obs-container mb-3 mt-0" style="border-top: none; padding-top: 0;">
                            <button type="button" class="btn-obs-toggle" onclick="toggleObs(this)">
                                <span><i class="fas fa-sticky-note me-2 text-warning"></i> Notas</span>
                                <i class="fas fa-plus"></i>
                            </button>
                            <div class="obs-content" data-raw-obs="<?php echo htmlspecialchars($mapa['obs']); ?>"></div>
                        </div>
                        <?php endif; ?>

                        <label class="form-label fw-bold mt-2">Pessoas Encontradas no Território:</label>
                        
                        <div class="d-flex justify-content-end px-2 pb-1"> 
                            <div class="d-flex align-items-center">
                                <small class="fw-bold text-muted text-center" style="width: 150px;">Nº Pessoas</small>
                                <div style="width: 32px;"></div>
                            </div>
                        </div>

                        <div class="list-group list-group-flush mb-3 quadra-list" data-mapa-id="<?php echo $mapa['id']; ?>">
                        <?php if (isset($quadras_por_mapa[$mapa['id']])): foreach ($quadras_por_mapa[$mapa['id']] as $quadra): ?>
                            <div class="list-group-item quadra-item d-flex justify-content-between align-items-center py-3 px-2">
                                <span class="fs-5">Quadra <strong><?php echo $quadra['numero']; ?></strong></span>
                                <div class="d-flex align-items-center">
                                    <div class="input-group" style="width: 150px;">
                                        <button class="btn btn-outline-secondary btn-decrement px-3 fw-bold" type="button" style="font-size: 1.2rem;">-</button>
                                        <input type="number" class="form-control text-center quadra-input no-spinners fw-bold" 
                                               style="font-size: 1.1rem;"
                                               value="<?php echo $quadra['pessoas_faladas']; ?>" 
                                               data-quadra-id="<?php echo $quadra['id']; ?>" 
                                               data-previous-value="<?php echo $quadra['pessoas_faladas']; ?>" min="0" readonly>
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
                        <div class="mb-3">
                            <label for="data_devolucao_<?php echo $mapa['id']; ?>" class="form-label fw-bold">Data de Devolução:</label>
                            <input type="date" class="form-control" id="data_devolucao_<?php echo $mapa['id']; ?>" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>



                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i> Finalizar e Devolver</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza o card de Mapa de Prédio.
 */
function renderizarCardPredio($mapa, $blocos_por_mapa, $total_cards_geral) {
    $isGroup = !empty($mapa['grupo_id']);
    $soma_pessoas = 0;
    if (isset($blocos_por_mapa[$mapa['id']])) {
        foreach ($blocos_por_mapa[$mapa['id']] as $b) $soma_pessoas += (int)$b['pessoas_faladas'];
    }
    $classe_inicial = ($total_cards_geral > 1 && $soma_pessoas == 0) ? 'collapsed' : '';

    $max_apts = '';
    $label_max = '';
    if (isset($mapa['apt_inicio']) && isset($mapa['apt_fim'])) {
        $calc_max = ((int)$mapa['apt_fim'] - (int)$mapa['apt_inicio']) + 1;
        $max_apts = 'max="' . $calc_max . '" data-max-val="' . $calc_max . '"';
        $label_max = ' (Máx: ' . $calc_max . ')';
    }

    $nome_identificador = $mapa['identificador'];
    $url_jpg = "pdfs/" . rawurlencode($nome_identificador) . ".jpg";
    $url_pdf = "pdfs/" . rawurlencode($nome_identificador) . ".pdf";
    $caminho_local_jpg = __DIR__ . "/pdfs/" . $nome_identificador . ".jpg";
    $caminho_local_pdf = __DIR__ . "/pdfs/" . $nome_identificador . ".pdf";
    ?>

    <div class="card-container-wrapper" id="mapa-card-p<?php echo $mapa['id']; ?>">
        <div class="card shadow-sm <?php echo $classe_inicial; ?>">
            <div class="card-header card-header-predio text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 d-flex align-items-center w-100">
                    <i class="fas fa-building me-2 flex-shrink-0"></i>
                    <span class="map-name flex-grow-1"><?php echo htmlspecialchars($mapa['identificador']); ?></span>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <?php if ($isGroup): ?>
                            <span class="badge bg-white text-dark group-tag" style="opacity: 0.9;"><?php echo htmlspecialchars($mapa['nome_grupo']); ?></span>
                        <?php endif; ?>
                        <button class="btn btn-light btn-sm btn-share-map border-0"
                                style="background: rgba(255,255,255,0.2); color: white;"
                                data-mapa-id="<?php echo $mapa['id']; ?>"
                                data-mapa-nome="<?php echo htmlspecialchars($mapa['identificador']); ?>"
                                data-is-group="<?php echo $isGroup ? '1' : '0'; ?>"
                                data-is-predio="1"
                                title="Compartilhar temporariamente">
                            <i class="fas fa-share-alt"></i>
                        </button>
                        <i class="fas fa-chevron-down header-icon"></i>
                    </div>
                </h5>
            </div>

            <div class="card-collapsible-content">
                <?php if (file_exists($caminho_local_jpg)): ?>
                    <div class="pdf-preview-container">
                        <img src="<?php echo $url_jpg; ?>" data-bs-toggle="modal" data-bs-target="#pdfModal" data-img-src="<?php echo $url_jpg; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                        <button class="btn btn-predio-color btn-sm btn-expand" data-bs-toggle="modal" data-bs-target="#pdfModal" data-img-src="<?php echo $url_jpg; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                            <i class="fas fa-expand-alt me-1"></i> Expandir
                        </button>
                    </div>
                <?php elseif (!empty($mapa['gdrive_file_id'])): ?>
                    <?php $pdf_embed_url = "https://drive.google.com/file/d/" . $mapa['gdrive_file_id'] . "/preview"; ?>
                    <div class="pdf-preview-container">
                        <iframe src="<?php echo $pdf_embed_url; ?>" style="width:100%;height:100%;border:none;"></iframe>
                        <button class="btn btn-predio-color btn-sm btn-expand" data-bs-toggle="modal" data-bs-target="#pdfModal" data-pdf-src="<?php echo $pdf_embed_url; ?>" data-pdf-title="<?php echo htmlspecialchars($mapa['identificador']); ?>">
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
                    <form class="form-devolver-predio" data-mapa-id="<?php echo $mapa['id']; ?>" data-mapa-nome="<?php echo htmlspecialchars($mapa['identificador']); ?>">
                        <?php if(!empty($mapa['obs'])): ?>
                        <div class="obs-container mb-3 mt-0" style="border-top: none; padding-top: 0;">
                            <button type="button" class="btn-obs-toggle" onclick="toggleObs(this)">
                                <span><i class="fas fa-sticky-note me-2 text-warning"></i> Notas</span>
                                <i class="fas fa-plus"></i>
                            </button>
                            <div class="obs-content" data-raw-obs="<?php echo htmlspecialchars($mapa['obs']); ?>"></div>
                        </div>
                        <?php endif; ?>

                        <label class="form-label fw-bold mt-2">Pessoas Encontradas por Bloco:</label>

                        <div class="d-flex justify-content-end px-2 pb-1">
                            <div class="d-flex align-items-center">
                                <small class="fw-bold text-muted text-center" style="width: 150px;">Aptos<?php echo $label_max; ?></small>
                                <div style="width: 32px;"></div>
                            </div>
                        </div>

                        <div class="list-group list-group-flush mb-3 bloco-list" data-mapa-id="<?php echo $mapa['id']; ?>">
                        <?php if (isset($blocos_por_mapa[$mapa['id']])): foreach ($blocos_por_mapa[$mapa['id']] as $bloco): ?>
                            <div class="list-group-item quadra-item d-flex justify-content-between align-items-center py-3 px-2">
                                <span class="fs-5">Bloco <strong><?php echo htmlspecialchars($bloco['numero']); ?></strong></span>
                                <div class="d-flex align-items-center">
                                    <div class="input-group" style="width: 150px;">
                                        <button class="btn btn-outline-secondary btn-decrement-bloco px-3 fw-bold" type="button" style="font-size: 1.2rem;">-</button>
                                        <input type="number" class="form-control text-center bloco-input no-spinners fw-bold"
                                               style="font-size: 1.1rem;"
                                               value="<?php echo $bloco['pessoas_faladas']; ?>"
                                               data-bloco-id="<?php echo $bloco['id']; ?>"
                                               data-previous-value="<?php echo $bloco['pessoas_faladas']; ?>"
                                               min="0" <?php echo $max_apts; ?> readonly>
                                        <button class="btn btn-outline-secondary btn-increment-bloco px-3 fw-bold" type="button" style="font-size: 1.2rem;">+</button>
                                    </div>
                                    <div class="ms-2 d-flex align-items-center justify-content-center" style="width: 24px;" id="status_save_b<?php echo $bloco['id']; ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="list-group-item d-flex justify-content-between align-items-center p-2 border-top fw-bold bg-light">
                            <span class="fs-5">Total</span>
                            <div class="d-flex align-items-center">
                                <span class="fs-5 text-center fw-bold" style="width: 150px;" id="total-pessoas-predio-<?php echo $mapa['id']; ?>"><?php echo $soma_pessoas; ?></span>
                                <div style="width: 32px;"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        </div>
                        <hr>
                        <p class="mb-2"><strong>Recebido em:</strong> <?php echo date('d/m/Y', strtotime($mapa['data_entrega'])); ?></p>
                        <div class="mb-3">
                            <label for="data_devolucao_p<?php echo $mapa['id']; ?>" class="form-label fw-bold">Data de Devolução:</label>
                            <input type="date" class="form-control" id="data_devolucao_p<?php echo $mapa['id']; ?>" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>



                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i> Finalizar e Devolver</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}

try {
    $stmt_user = $pdo->prepare("SELECT nome FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch();

    if (!$user) {
        exibirErroFatal("Usuário não encontrado", "Ocorreu um erro ao carregar seu perfil.", $baseUrl);
    }
    
    $sql_mapas = "SELECT m.id, m.identificador, m.data_entrega, m.gdrive_file_id, m.obs, m.grupo_id, g.nome as nome_grupo
                  FROM mapas m 
                  LEFT JOIN grupos g ON m.grupo_id = g.id
                  WHERE (m.dirigente_id = ? OR m.grupo_id IN (SELECT grupo_id FROM grupo_membros WHERE user_id = ?))
                  AND m.data_devolucao IS NULL ORDER BY m.identificador ASC";
    
    $stmt_mapas = $pdo->prepare($sql_mapas);
    $stmt_mapas->execute([$user_id, $user_id]);
    $mapas = $stmt_mapas->fetchAll();

    $mapas_individuais = [];
    $mapas_grupo = [];
    foreach ($mapas as $m) {
        if (!empty($m['grupo_id'])) { $mapas_grupo[] = $m; } else { $mapas_individuais[] = $m; }
    }

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

    $sql_predio = "SELECT m.id, m.identificador, m.data_entrega, m.gdrive_file_id, m.obs, m.grupo_id,
                          g.nome as nome_grupo, m.apt_inicio, m.apt_fim
                   FROM mapas_predio m
                   LEFT JOIN grupos g ON m.grupo_id = g.id
                   WHERE (m.dirigente_id = ? OR m.grupo_id IN (SELECT grupo_id FROM grupo_membros WHERE user_id = ?))
                   AND m.data_devolucao IS NULL ORDER BY m.identificador ASC";
    $stmt_predio = $pdo->prepare($sql_predio);
    $stmt_predio->execute([$user_id, $user_id]);
    $mapas_predio = $stmt_predio->fetchAll();

    $blocos_por_mapa = [];
    if (!empty($mapas_predio)) {
        $mp_ids = array_column($mapas_predio, 'id');
        $pl = implode(',', array_fill(0, count($mp_ids), '?'));
        $stmt_blocos = $pdo->prepare("SELECT id, mapa_id, numero, pessoas_faladas FROM blocos WHERE mapa_id IN ($pl) ORDER BY numero ASC");
        $stmt_blocos->execute($mp_ids);
        foreach ($stmt_blocos->fetchAll() as $b) {
            $blocos_por_mapa[$b['mapa_id']][] = $b;
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
        .no-spinners { -moz-appearance: textfield; }
        .quadra-input, .bloco-input { padding: 0; background-color: #fff !important; }
        .card-header-group { background-color: #4190be !important; border-color: #4190be !important; }
        .btn-group-color { background-color: #4190be !important; border-color: #4190be !important; color: white !important; }
        .btn-group-color:hover { background-color: #357a9e !important; border-color: #357a9e !important; }
        .card-header-predio { background-color: #E91E63 !important; border-color: #E91E63 !important; }
        .btn-predio-color { background-color: #E91E63 !important; border-color: #E91E63 !important; color: white !important; }
        .btn-predio-color:hover { background-color: #D81B60 !important; }
        .pdf-preview-container { position: relative; height: 300px; background-color: #e9ecef; border-bottom: 1px solid #dee2e6; display: flex; justify-content: center; align-items: center; overflow: hidden; }
        .pdf-preview-container img { max-width: 100%; max-height: 100%; object-fit: contain; cursor: pointer; }
        .pdf-preview-container iframe { width: 100%; height: 100%; border: none; }
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
        .section-divider { display: flex; align-items: center; text-align: center; color: #4190be; margin: 2rem 0 1.5rem 0; font-weight: 700; text-transform: uppercase; font-size: 0.9rem; letter-spacing: 1px; }
        .section-divider::before, .section-divider::after { content: ''; flex: 1; border-bottom: 1px solid #bfdcf0; }
        .section-divider:not(:empty)::before { margin-right: .5em; }
        .section-divider:not(:empty)::after { margin-left: .5em; }
        .section-divider-predio { color: #E91E63 !important; }
        .section-divider-predio::before, .section-divider-predio::after { border-bottom: 1px solid #f48fb1 !important; }
        .modal-fullscreen .modal-content { background-color: black; }
        .modal-fullscreen .modal-header { position: absolute; top: 0; left: 0; width: 100%; background: rgba(0, 0, 0, 0.6); border-bottom: none; z-index: 9999; padding: 15px 20px; }
        .modal-fullscreen .modal-title { color: white; font-size: 1.1rem; text-shadow: 0 1px 3px rgba(0,0,0,0.8); }
        .btn-close-custom { background: none; border: none; color: white; font-size: 1.5rem; opacity: 0.9; transition: transform 0.2s; }
        .btn-close-custom:hover { opacity: 1; transform: scale(1.1); color: #fff; }
        @media (max-width: 480px) {
            body { padding: 10px; }
            .card-title { display: flex; flex-wrap: nowrap; align-items: center; width: 100%; }
            .map-name { font-size: 0.95rem; white-space: normal; line-height: 1.2; margin-right: 5px; }
            .group-tag { font-size: 0.6rem !important; max-width: 80px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .header-icon { font-size: 0.9rem; }
            .card-title i.fa-map-pin, .card-title i.fa-users { font-size: 0.9rem; }
        }
        
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
    <nav class="navbar navbar-dark bg-dark mb-4 rounded shadow-sm">
        <div class="container-fluid"><span class="navbar-brand"><i class="fas fa-map-marked-alt me-2"></i>Mapas de <?php echo htmlspecialchars($user['nome']); ?></span></div>
    </nav>
    <div class="container-fluid">
        <?php if (empty($mapas) && empty($mapas_predio)): ?>
            <div class="alert alert-info text-center w-100">Nenhum mapa atribuído a você no momento.</div>
        <?php else: ?>
            <?php if (!empty($mapas_individuais)): ?>
                <div class="masonry-layout" id="container-mapas-individuais">
                    <?php 
                    $total_global = count($mapas) + count($mapas_predio);
                    foreach ($mapas_individuais as $mapa): 
                        renderizarCard($mapa, $quadras_por_mapa, $total_global);
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($mapas_grupo)): ?>
                <div class="section-divider"><i class="fas fa-users me-2"></i> Mapas para Finais de Semana</div>
                <div class="masonry-layout" id="container-mapas-grupo">
                    <?php 
                    $total_global = count($mapas) + count($mapas_predio);
                    foreach ($mapas_grupo as $mapa): 
                        renderizarCard($mapa, $quadras_por_mapa, $total_global);
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($mapas_predio)): ?>
                <div class="section-divider section-divider-predio"><i class="fas fa-building me-2"></i> Mapas de Prédios</div>
                <div class="masonry-layout" id="container-mapas-predio">
                    <?php
                    $total_global = count($mapas) + count($mapas_predio);
                    foreach ($mapas_predio as $mapa):
                        renderizarCardPredio($mapa, $blocos_por_mapa, $total_global);
                    endforeach;
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <!-- Modais -->
    <div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content bg-black">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfModalTitle">Visualizador</h5>
                    <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body p-0 d-flex justify-content-center align-items-center bg-black" id="modal-viewer-body">
                    <!-- Conteúdo dinâmico (imagem ou iframe) -->
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="feedbackModalTitle">Aviso</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="feedbackModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button></div></div></div>
    </div>
    <div class="modal fade" id="confirmacaoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmação</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="confirmacaoModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="btnConfirmarAcao">Confirmar</button></div></div></div>
    </div>
    <div class="modal fade" id="shareModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Compartilhar Mapa <b id="shareMapName"></b></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3 text-muted">Gera um link temporário para que outra pessoa possa acessar esse mapa sem precisar ter conta.</p>
                    <div class="form-group mb-0">
                        <label for="shareDurationSelect" class="form-label fw-bold">Tempo de Validade do Link</label>
                        <select class="form-select" id="shareDurationSelect">
                            <option value="30">30 Minutos</option>
                            <option value="60">1 Hora</option>
                            <option value="90" selected>1 Hora e 30 Minutos</option>
                            <option value="120">2 Horas</option>
                            <option value="180">3 Horas</option>
                            <option value="240">4 Horas</option>
                            <option value="360">6 Horas</option>
                            <option value="720">12 Horas</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btnConfirmShare" class="btn btn-primary">Gerar Link</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../script/common.js"></script>
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

            const API_BASE_URL = '.'; 
            const saveTimeouts = {};
            const pendingDeltas = {};
            const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
            const confirmacaoModal = new bootstrap.Modal(document.getElementById('confirmacaoModal'));
            const shareModalObj = new bootstrap.Modal(document.getElementById('shareModal'));
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
                    if (totalMapas > 1) { card.classList.add('card-interativo'); } else { card.classList.remove('card-interativo', 'collapsed'); const icon = card.querySelector('.header-icon'); if (icon) icon.style.display = 'none'; }
                });
            };
            document.addEventListener('click', (e) => {
                const header = e.target.closest('.card-header');
                const btnShare = e.target.closest('.btn-share-map');
                if (btnShare) {
                    e.stopPropagation();
                    const mapaId = btnShare.dataset.mapaId;
                    const mapaNome = btnShare.dataset.mapaNome;
                    const isGroup = btnShare.dataset.isGroup === '1';
                    const isPredio = btnShare.dataset.isPredio === '1';
                    
                    document.getElementById('shareMapName').textContent = mapaNome;
                    const btnConfirmShare = document.getElementById('btnConfirmShare');
                    btnConfirmShare.className = 'btn ' + (isPredio ? 'btn-predio-color' : (isGroup ? 'btn-group-color' : 'btn-primary'));
                    
                    btnConfirmShare.onclick = async () => {
                        btnConfirmShare.innerHTML = '<span class="spinner-border spinner-border-sm"></span>...';
                        btnConfirmShare.disabled = true;
                        const mins = document.getElementById('shareDurationSelect').value;
                        try {
                            const shareApi = isPredio ? `${API_BASE_URL}/mapas_predio_api.php` : `${API_BASE_URL}/mapas_api.php`;
                            const resp = await fetch(shareApi, { 
                                method: 'POST', 
                                headers: { 'Content-Type': 'application/json' }, 
                                body: JSON.stringify({ action: 'gerar_compartilhamento', mapa_id: mapaId, minutos: mins }) 
                            });
                            const res = await resp.json();
                            if(res.success) {
                                shareModalObj.hide();
                                const currentOrigin = window.location.origin;
                                let shareUrlPath = window.location.pathname;
                                // Ajuste para o path da vista compartilhada
                                shareUrlPath = shareUrlPath.substring(0, shareUrlPath.lastIndexOf('/') + 1) + `vista_compartilhada.php?s=${res.token}`;
                                const shareLink = currentOrigin + shareUrlPath;

                                const copyHtml = `
                                    <div class="mt-2 text-center">
                                        <p class="mb-3 text-muted">Link de acesso temporário:</p>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="copyShareLink" value="${shareLink}" readonly>
                                            <button class="btn btn-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('copyShareLink').value); this.innerHTML='Copiado!';">Copiar</button>
                                        </div>
                                    </div>
                                `;
                                mostrarFeedback('Compartilhamento Gerado!', copyHtml, 'success');
                            }
                        } catch (err) { mostrarFeedback('Erro', 'Falha ao gerar link.'); }
                        finally { btnConfirmShare.innerHTML = 'Gerar Link'; btnConfirmShare.disabled = false; }
                    };
                    shareModalObj.show();
                    return;
                }
                if (header) { const card = header.closest('.card'); if (card.classList.contains('card-interativo')) card.classList.toggle('collapsed'); }
            });
            gerenciarColapsoCards();

            // --- LÓGICA DE SALVAMENTO ---
            const saveItem = async (id, delta, isPredio, statusDiv) => {
                statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span>';
                const api = isPredio ? `${API_BASE_URL}/mapas_predio_api.php` : `${API_BASE_URL}/mapas_api.php`;
                const action = isPredio ? 'update_bloco_increment' : 'update_quadra_increment';
                const key = isPredio ? 'bloco_id' : 'quadra_id';
                try {
                    await fetch(api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, [key]: id, delta }) });
                    statusDiv.innerHTML = '<i class="fas fa-check text-success"></i>';
                    setTimeout(() => { statusDiv.innerHTML = ''; }, 2000);
                } catch (e) { statusDiv.innerHTML = '<i class="fas fa-times text-danger"></i>'; }
            };

            const handleInput = (e, isPredio) => {
                const input = e.target;
                const itemId = isPredio ? input.dataset.blocoId : input.dataset.quadraId;
                const maxVal = input.dataset.maxVal ? parseInt(input.dataset.maxVal) : null;
                let val = parseInt(input.value) || 0;
                if (maxVal !== null && val > maxVal) { val = maxVal; input.value = val; }
                const diff = val - (parseInt(input.dataset.previousValue) || 0);
                if (diff !== 0) {
                    pendingDeltas[itemId] = (pendingDeltas[itemId] || 0) + diff;
                    input.dataset.previousValue = val;
                    clearTimeout(saveTimeouts[itemId]);
                    const statusId = isPredio ? `status_save_b${itemId}` : `status_save_q${itemId}`;
                    saveTimeouts[itemId] = setTimeout(() => {
                        const delta = pendingDeltas[itemId];
                        pendingDeltas[itemId] = 0;
                        saveItem(itemId, delta, isPredio, document.getElementById(statusId));
                    }, 800);
                    let total = 0;
                    const list = input.closest('.list-group');
                    list.querySelectorAll('input').forEach(i => total += (parseInt(i.value) || 0));
                    const totalId = isPredio ? `total-pessoas-predio-${list.dataset.mapaId}` : `total-pessoas-mapa-${list.dataset.mapaId}`;
                    const targetTotal = document.getElementById(totalId);
                    if (targetTotal) targetTotal.textContent = total;
                }
            };

            document.querySelectorAll('.quadra-input').forEach(i => i.addEventListener('input', (e) => handleInput(e, false)));
            document.querySelectorAll('.bloco-input').forEach(i => i.addEventListener('input', (e) => handleInput(e, true)));

            document.querySelectorAll('.btn-increment, .btn-increment-bloco').forEach(b => b.onclick = (e) => { 
                const i = e.target.closest('.input-group').querySelector('input'); 
                const maxVal = i.dataset.maxVal ? parseInt(i.dataset.maxVal) : null;
                if (maxVal === null || (parseInt(i.value)||0) < maxVal) { i.value = (parseInt(i.value)||0)+1; i.dispatchEvent(new Event('input')); }
            });
            document.querySelectorAll('.btn-decrement, .btn-decrement-bloco').forEach(b => b.onclick = (e) => { 
                const i = e.target.closest('.input-group').querySelector('input'); 
                if(parseInt(i.value)>0){ i.value = parseInt(i.value)-1; i.dispatchEvent(new Event('input')); }
            });

            // --- DEVOLUÇÃO ---
            const handleDevolver = (e, isPredio) => {
                e.preventDefault();
                const mapaId = e.target.dataset.mapaId;
                const dataId = isPredio ? `data_devolucao_p${mapaId}` : `data_devolucao_${mapaId}`;
                const dataDev = document.getElementById(dataId).value;
                mostrarConfirmacao('Finalizar Mapa', `Deseja devolver <b>${e.target.dataset.mapaNome}</b>?`, async () => {
                    const api = isPredio ? `${API_BASE_URL}/mapas_predio_api.php` : `${API_BASE_URL}/mapas_api.php`;
                    try {
                        const resp = await fetch(api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'devolver', mapa_id: mapaId, data_devolucao: dataDev }) });
                        const res = await resp.json();
                        if (res.message) location.reload();
                        else mostrarFeedback('Erro', res.error || 'Falha ao devolver.');
                    } catch (e) { mostrarFeedback('Erro', 'Falha ao devolver.'); }
                });
            };
            document.querySelectorAll('.form-devolver').forEach(f => f.onsubmit = (e) => handleDevolver(e, false));
            document.querySelectorAll('.form-devolver-predio').forEach(f => f.onsubmit = (e) => handleDevolver(e, true));

            // --- MODAL VIEWER ---
            document.getElementById('pdfModal').addEventListener('show.bs.modal', (e) => { 
                const btn = e.relatedTarget; 
                const body = document.getElementById('modal-viewer-body');
                body.innerHTML = '';
                if (btn.dataset.imgSrc) {
                    body.innerHTML = `<img src="${btn.dataset.imgSrc}" style="max-width:100%; max-height:100%; object-fit:contain;">`;
                } else if (btn.dataset.pdfSrc) {
                    body.innerHTML = `<iframe src="${btn.dataset.pdfSrc}" style="width:100%;height:100%;border:none;"></iframe>`;
                }
                document.getElementById('pdfModalTitle').textContent = btn.dataset.pdfTitle || 'Visualizador'; 
            });
        });
    </script>
</body>
</html>

<?php
// site/backend/vista_dirigente.php
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once 'conexao.php';
session_start(); // Necessário para verificar o usuário logado

// Validação de segurança básica: verifica se o usuário está logado e se o ID corresponde
$usuarioLogado = $_SESSION['usuarioLogado'] ?? null;
$dirigente_id_get = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$dirigente_id_get || !$usuarioLogado || $usuarioLogado['id'] != $dirigente_id_get) {
    die("<h1>Acesso não autorizado.</h1>");
}
$dirigente_id = $dirigente_id_get;

try {
    $stmt_user = $pdo->prepare("SELECT nome FROM users WHERE id = ? AND cargo = 'dirigente'");
    $stmt_user->execute([$dirigente_id]);
    $dirigente = $stmt_user->fetch();
    if (!$dirigente) { http_response_code(404); die("<h1>Dirigente não encontrado.</h1>"); }
    
    // O resto do código é idêntico ao de vista_publica.php
    $stmt_mapas = $pdo->prepare("SELECT id, identificador, data_entrega FROM mapas WHERE dirigente_id = ? AND data_devolucao IS NULL");
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
} catch (PDOException $e) { http_response_code(500); die("Erro no banco de dados."); }
?>
<!-- O HTML e o SCRIPT são exatamente os mesmos de vista_publica.php -->
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Meus Mapas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../style/css.css">
    <style> .iframe-page { padding: 15px; background-color: var(--content-bg); } .quadra-item { border-bottom: 1px solid #eee; } </style>
</head>
<body class="iframe-page">
    <div class="container-fluid"><div id="alert-container"></div><div class="row">
    <?php if (empty($mapas)): ?>
        <div class="col-12"><div class="alert alert-info text-center">Você não possui nenhum mapa atribuído.</div></div>
    <?php else: foreach ($mapas as $mapa): ?>
        <div class="col-lg-6 mb-4" id="mapa-card-<?php echo $mapa['id']; ?>">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white"><h5 class="card-title mb-0"><i class="fas fa-map-pin me-2"></i> <?php echo htmlspecialchars($mapa['identificador']); ?></h5></div>
            <div class="p-3"><a href="pdfs/<?php echo rawurlencode($mapa['identificador']) . ".pdf"; ?>" target="_blank" class="btn btn-secondary d-block"><i class="fas fa-file-pdf me-2"></i> Visualizar Mapa (PDF)</a></div>
            <div class="card-body">
            <form class="form-devolver" data-mapa-id="<?php echo $mapa['id']; ?>">
                <label class="form-label fw-bold">Registro por Quadra:</label>
                <div class="list-group mb-3" style="max-height: 250px; overflow-y: auto;">
                <?php if (isset($quadras_por_mapa[$mapa['id']])): foreach ($quadras_por_mapa[$mapa['id']] as $quadra): ?>
                    <div class="list-group-item quadra-item d-flex justify-content-between align-items-center">
                        <span>Quadra <strong><?php echo $quadra['numero']; ?></strong></span>
                        <div class="d-flex align-items-center">
                            <input type="number" class="form-control form-control-sm text-center quadra-input" style="width: 80px;"
                                value="<?php echo $quadra['pessoas_faladas']; ?>" data-quadra-id="<?php echo $quadra['id']; ?>" min="0">
                            <small class="text-muted status-save ms-2" id="status_save_q<?php echo $quadra['id']; ?>"></small>
                        </div>
                    </div>
                <?php endforeach; else: ?>
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
    </div></div>
    <script src="../script/common.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const saveQuadra = async (quadraId, valor, statusDiv) => {
            statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                    method: 'PUT', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_quadra', quadra_id: quadraId, pessoas_faladas: valor })
                });
                if (!response.ok) throw new Error('Falha ao salvar');
                statusDiv.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
                setTimeout(() => { statusDiv.innerHTML = ''; }, 2000);
            } catch (error) { statusDiv.innerHTML = '<i class="fas fa-times-circle text-danger"></i>'; }
        };
        document.querySelectorAll('.quadra-input').forEach(input => {
            input.addEventListener('change', (e) => saveQuadra(e.target.dataset.quadraId, e.target.value, document.getElementById(`status_save_q${e.target.dataset.quadraId}`)));
        });
        document.querySelectorAll('.form-devolver').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const mapaId = e.target.dataset.mapaId;
                const dataDevolucao = document.getElementById(`data_devolucao_${mapaId}`).value;
                if (!confirm('Deseja devolver este mapa?')) return;
                try {
                    const response = await fetch(`${API_BASE_URL}/mapas_api.php`, {
                        method: 'PUT', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'devolver', mapa_id: mapaId, data_devolucao: dataDevolucao })
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message);
                    document.getElementById('alert-container').innerHTML = `<div class="alert alert-success">${result.message}</div>`;
                    document.getElementById(`mapa-card-${mapaId}`).remove();
                } catch (error) { alert(`Erro: ${error.message}`); }
            });
        });
    });
    </script>
</body>
</html>
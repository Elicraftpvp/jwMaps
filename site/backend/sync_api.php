<?php
// site/backend/sync_api.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
require_once 'conexao.php';

$token = htmlspecialchars($_GET['token'] ?? '');
$share_token = htmlspecialchars($_GET['share_token'] ?? '');

if (empty($token) && empty($share_token)) {
    echo json_encode(['error' => 'token required']);
    exit;
}

$response = [
    'mapas' => [],
    'mapas_predio' => [],
    'quadras' => [],
    'blocos' => [],
    'obs' => [],
    'devolvidos' => []
];

try {
    if (!empty($share_token)) {
        // Shared Map Logic
        $stmt_c = $pdo->prepare("SELECT mapa_id, tipo FROM compartilhamentos WHERE token = ? AND expira_em > NOW()");
        $stmt_c->execute([$share_token]);
        $c_data = $stmt_c->fetch(PDO::FETCH_ASSOC);

        if (!$c_data) {
            echo json_encode(['error' => 'invalid share token']);
            exit;
        }

        $mapa_id = $c_data['mapa_id'];
        if ($c_data['tipo'] === 'predio') {
            $stmt_map = $pdo->prepare("SELECT obs, data_devolucao FROM mapas_predio WHERE id = ?");
            $stmt_map->execute([$mapa_id]);
            $map = $stmt_map->fetch(PDO::FETCH_ASSOC);
            
            if ($map['data_devolucao']) $response['devolvidos'][] = 'p' . $mapa_id;
            $response['obs']['p' . $mapa_id] = $map['obs'] ?? '';
            
            $stmt_items = $pdo->prepare("SELECT id, pessoas_faladas FROM blocos WHERE mapa_id = ?");
            $stmt_items->execute([$mapa_id]);
            foreach ($stmt_items->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $response['blocos'][$item['id']] = $item['pessoas_faladas'];
            }
        } else {
            $stmt_map = $pdo->prepare("SELECT obs, data_devolucao FROM mapas WHERE id = ?");
            $stmt_map->execute([$mapa_id]);
            $map = $stmt_map->fetch(PDO::FETCH_ASSOC);
            
            if ($map['data_devolucao']) $response['devolvidos'][] = $mapa_id;
            $response['obs'][$mapa_id] = $map['obs'] ?? '';
            
            $stmt_items = $pdo->prepare("SELECT id, pessoas_faladas FROM quadras WHERE mapa_id = ?");
            $stmt_items->execute([$mapa_id]);
            foreach ($stmt_items->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $response['quadras'][$item['id']] = $item['pessoas_faladas'];
            }
        }
    } else {
        // User Logic
        $stmt_user = $pdo->prepare("SELECT id FROM users WHERE token_acesso = ? AND status = 'ativo'");
        $stmt_user->execute([$token]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            echo json_encode(['error' => 'invalid user']);
            exit;
        }
        $user_id = $user['id'];

        // Mapas normais
        $sql_mapas = "SELECT m.id, m.obs, m.data_devolucao FROM mapas m 
                      WHERE (m.dirigente_id = ? OR m.grupo_id IN (SELECT grupo_id FROM grupo_membros WHERE user_id = ?))";
        $stmt_mapas = $pdo->prepare($sql_mapas);
        $stmt_mapas->execute([$user_id, $user_id]);
        $mapas = $stmt_mapas->fetchAll(PDO::FETCH_ASSOC);

        $mapa_ids = [];
        foreach ($mapas as $m) {
            if (!$m['data_devolucao']) {
                $mapa_ids[] = $m['id'];
                $response['mapas'][] = $m['id'];
                $response['obs'][$m['id']] = $m['obs'] ?? '';
            } else {
                $response['devolvidos'][] = $m['id'];
            }
        }

        if (!empty($mapa_ids)) {
            $placeholders = implode(',', array_fill(0, count($mapa_ids), '?'));
            $stmt_quadras = $pdo->prepare("SELECT id, pessoas_faladas FROM quadras WHERE mapa_id IN ($placeholders)");
            $stmt_quadras->execute($mapa_ids);
            foreach ($stmt_quadras->fetchAll(PDO::FETCH_ASSOC) as $q) {
                $response['quadras'][$q['id']] = $q['pessoas_faladas'];
            }
        }

        // Mapas de prédio
        $sql_predio = "SELECT m.id, m.obs, m.data_devolucao FROM mapas_predio m 
                       WHERE (m.dirigente_id = ? OR m.grupo_id IN (SELECT grupo_id FROM grupo_membros WHERE user_id = ?))";
        $stmt_predio = $pdo->prepare($sql_predio);
        $stmt_predio->execute([$user_id, $user_id]);
        $mapas_predio = $stmt_predio->fetchAll(PDO::FETCH_ASSOC);

        $predio_ids = [];
        foreach ($mapas_predio as $m) {
            if (!$m['data_devolucao']) {
                $predio_ids[] = $m['id'];
                $response['mapas_predio'][] = $m['id'];
                $response['obs']['p' . $m['id']] = $m['obs'] ?? '';
            } else {
                $response['devolvidos'][] = 'p' . $m['id'];
            }
        }

        if (!empty($predio_ids)) {
            $pl = implode(',', array_fill(0, count($predio_ids), '?'));
            $stmt_blocos = $pdo->prepare("SELECT id, pessoas_faladas FROM blocos WHERE mapa_id IN ($pl)");
            $stmt_blocos->execute($predio_ids);
            foreach ($stmt_blocos->fetchAll(PDO::FETCH_ASSOC) as $b) {
                $response['blocos'][$b['id']] = $b['pessoas_faladas'];
            }
        }
    }

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['error' => 'db error']);
}
?>

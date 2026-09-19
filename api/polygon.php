<?php
require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT * FROM office_polygons WHERE id = ?");
    $stmt->execute([$id]);
    $polygon = $stmt->fetch();
    if ($polygon) {
        echo json_encode(['id' => $polygon['id'], 'name' => $polygon['name'], 'coordinates' => json_decode($polygon['coordinates'], true), 'color' => $polygon['color']]);
    } else {
        echo json_encode(['error' => 'Polygon not found']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

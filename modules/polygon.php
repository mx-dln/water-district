<?php
requireRole('Administrator');

$polygons = $db->query("SELECT * FROM office_polygons ORDER BY created_at DESC")->fetchAll();
$activePolygon = getActivePolygon();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $name = sanitizeInput($_POST['name'] ?? 'Office Boundary');
        $coordinates = $_POST['coordinates'] ?? '';
        $color = sanitizeInput($_POST['color'] ?? '#2563eb');
        if (!empty($coordinates)) {
            $decoded = json_decode($coordinates, true);
            if ($decoded && count($decoded) >= 3) {
                $stmt = $db->prepare("UPDATE office_polygons SET is_active = 0 WHERE is_active = 1");
                $stmt->execute();
                $stmt = $db->prepare("INSERT INTO office_polygons (name, coordinates, color, description, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $coordinates, $color, sanitizeInput($_POST['description'] ?? ''), $_SESSION['user_id']]);
                logAudit('save_polygon', 'polygon', "Saved office polygon: $name");
                setFlash('Office polygon saved successfully', 'success');
            } else {
                setFlash('Invalid polygon coordinates (need at least 3 points)', 'danger');
            }
        } else {
            setFlash('Please draw a polygon on the map first', 'danger');
        }
        redirect(APP_URL . '/index.php?page=polygon');
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM office_polygons WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('Polygon deleted', 'success');
        redirect(APP_URL . '/index.php?page=polygon');
    }
}

$calendarRanges = [];
$startYear = 2025;
$endYear = date('Y');
for ($y = $startYear; $y <= $endYear + 1; $y++) {
    for ($m = 1; $m <= 12; $m++) {
        $calendarRanges[] = sprintf('%s-%02d', $y, $m);
    }
}
?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Office Polygon Management</h1>
            <p class="text-sm text-gray-500">Draw the exact office boundary for geofencing</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <div id="polygonMap" style="height: 500px;" class="rounded-lg border"></div>
                <div class="flex items-center justify-between mt-3">
                    <div class="flex space-x-2">
                        <button id="startDrawing" class="px-3 py-1.5 bg-blue-700 text-white rounded text-sm hover:bg-blue-800"><i class="fas fa-draw-polygon mr-1"></i>Draw Polygon</button>
                        <button id="clearDrawing" class="px-3 py-1.5 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200"><i class="fas fa-undo mr-1"></i>Clear</button>
                    </div>
                    <div id="coordCount" class="text-xs text-gray-500">No coordinates drawn</div>
                </div>
            </div>
        </div>
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border p-5">
                <h3 class="font-semibold text-gray-800 mb-3">Save Polygon</h3>
                <form method="POST" id="polygonForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="coordinates" id="coordinatesInput" value="">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Boundary Name</label>
                            <input type="text" name="name" value="Office Boundary" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                            <div class="flex items-center space-x-2">
                                <input type="color" name="color" value="#2563eb" class="w-10 h-10 rounded border cursor-pointer">
                                <span class="text-xs text-gray-500">Click to change</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
                            <textarea name="description" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="e.g. Main office building boundary"></textarea>
                        </div>
                        <button type="submit" id="savePolygonBtn" class="w-full py-2.5 bg-blue-700 text-white rounded-lg text-sm font-medium hover:bg-blue-800" disabled><i class="fas fa-save mr-2"></i>Save Boundary</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-5">
                <h3 class="font-semibold text-gray-800 mb-3">Saved Boundaries</h3>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    <?php if (empty($polygons)): ?>
                    <p class="text-sm text-gray-500">No boundaries saved yet. Draw one on the map.</p>
                    <?php else: foreach ($polygons as $p): ?>
                    <div class="flex items-center justify-between p-2 rounded hover:bg-gray-50 <?= $p['is_active'] ? 'bg-blue-50 border border-blue-200' : '' ?>">
                        <div class="flex items-center space-x-2">
                            <span class="w-3 h-3 rounded-full inline-block" style="background:<?= escapeOutput($p['color']) ?>"></span>
                            <div>
                                <p class="text-sm font-medium"><?= escapeOutput($p['name']) ?></p>
                                <p class="text-xs text-gray-500"><?= formatDate($p['created_at']) ?></p>
                            </div>
                        </div>
                        <div class="flex space-x-1">
                            <button onclick="loadPolygon(<?= $p['id'] ?>)" class="text-xs text-blue-600 hover:underline" title="Preview">View</button>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this boundary?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="text-xs text-red-600 hover:underline">Del</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$existingCoords = $activePolygon ? $activePolygon['coordinates'] : '[]';
$additionalScripts = '
<script>
let polygonMap, drawnPolygon, drawingLayer, markerLayer;
let coordinates = [];
let isDrawing = false;

const existingCoords = ' . $existingCoords . ';

document.addEventListener("DOMContentLoaded", function() {
    polygonMap = L.map("polygonMap").setView([16.93, 121.77], 16);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors",
        maxZoom: 19
    }).addTo(polygonMap);

    drawingLayer = L.layerGroup().addTo(polygonMap);
    markerLayer = L.layerGroup().addTo(polygonMap);

    if (existingCoords && existingCoords.length >= 3) {
        drawExistingPolygon(existingCoords);
        coordinates = existingCoords;
        updateCoordDisplay();
    }

    document.getElementById("startDrawing").addEventListener("click", startDrawingMode);
    document.getElementById("clearDrawing").addEventListener("click", clearAll);
});

function startDrawingMode() {
    if (isDrawing) return;
    isDrawing = true;
    clearAll();
    document.getElementById("startDrawing").textContent = "Click map to add points";
    document.getElementById("startDrawing").classList.remove("bg-blue-700", "hover:bg-blue-800");
    document.getElementById("startDrawing").classList.add("bg-green-600", "hover:bg-green-700");
    polygonMap.on("click", onMapClick);
}

function onMapClick(e) {
    coordinates.push({ lat: e.latlng.lat, lng: e.latlng.lng });
    L.circleMarker([e.latlng.lat, e.latlng.lng], {
        radius: 5, color: "#2563eb", fillColor: "#2563eb", fillOpacity: 1
    }).addTo(markerLayer);
    L.marker([e.latlng.lat, e.latlng.lng], {
        icon: L.divIcon({
            className: "coord-label",
            html: "<span style=\"font-size:10px;background:white;padding:2px 4px;border:1px solid #ccc;border-radius:3px;white-space:nowrap;\">(" + coordinates.length + ") " + e.latlng.lat.toFixed(4) + ", " + e.latlng.lng.toFixed(4) + "</span>",
            iconSize: [0, 0]
        })
    }).addTo(markerLayer);
    updatePolygon();
    updateCoordDisplay();
}

function updatePolygon() {
    drawingLayer.clearLayers();
    if (coordinates.length >= 3) {
        drawnPolygon = L.polygon(coordinates.map(c => [c.lat, c.lng]), {
            color: document.querySelector("[name=color]")?.value || "#2563eb",
            fillColor: document.querySelector("[name=color]")?.value || "#2563eb",
            fillOpacity: 0.2,
            weight: 2
        }).addTo(drawingLayer);
        polygonMap.fitBounds(drawnPolygon.getBounds().pad(0.1));
    }
}

function updateCoordDisplay() {
    const count = coordinates.length;
    document.getElementById("coordCount").textContent = count + " coordinate" + (count !== 1 ? "s" : "") + " drawn" + (count >= 3 ? " (polygon ready)" : " (need at least 3)");
    document.getElementById("coordinatesInput").value = JSON.stringify(coordinates);
    document.getElementById("savePolygonBtn").disabled = count < 3;
}

function clearAll() {
    coordinates = [];
    drawingLayer.clearLayers();
    markerLayer.clearLayers();
    isDrawing = false;
    polygonMap.off("click", onMapClick);
    document.getElementById("startDrawing").textContent = "Draw Polygon";
    document.getElementById("startDrawing").classList.remove("bg-green-600", "hover:bg-green-700");
    document.getElementById("startDrawing").classList.add("bg-blue-700", "hover:bg-blue-800");
    updateCoordDisplay();
}

function drawExistingPolygon(coords) {
    drawingLayer.clearLayers();
    markerLayer.clearLayers();
    const latlngs = coords.map(c => [c.lat, c.lng]);
    L.polygon(latlngs, {
        color: "' . ($activePolygon['color'] ?? '#2563eb') . '",
        fillColor: "' . ($activePolygon['color'] ?? '#2563eb') . '",
        fillOpacity: 0.2,
        weight: 2
    }).addTo(drawingLayer);
    coords.forEach((c, i) => {
        L.circleMarker([c.lat, c.lng], { radius: 4, color: "#2563eb", fillColor: "#2563eb", fillOpacity: 1 }).addTo(markerLayer);
    });
    polygonMap.fitBounds(latlngs);
}

function loadPolygon(id) {
    fetch("' . APP_URL . '/api/polygon.php?id=" + id)
        .then(r => r.json())
        .then(data => {
            if (data.coordinates) {
                clearAll();
                coordinates = data.coordinates;
                drawExistingPolygon(data.coordinates);
                updateCoordDisplay();
            }
        });
}

document.querySelector("[name=color]")?.addEventListener("input", function() {
    if (drawnPolygon && coordinates.length >= 3) {
        drawnPolygon.setStyle({ color: this.value, fillColor: this.value });
    }
});
</script>
<style>
.coord-label .leaflet-div-icon { background: transparent; border: none; }
</style>
';
?>

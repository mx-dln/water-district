<?php
requireRole('Administrator');

$polygons = $db->query("SELECT * FROM office_polygons ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$activePolygon = getActivePolygon();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $name = sanitizeInput($_POST['name'] ?? 'Office Boundary');
        $coordinates = $_POST['coordinates'] ?? '';
        $color = sanitizeInput($_POST['color'] ?? '#1d4ed8');
        if (!empty($coordinates)) {
            $decoded = json_decode($coordinates, true);
            if ($decoded && count($decoded) >= 3) {
                $stmt = $db->prepare("UPDATE office_polygons SET is_active = 0 WHERE is_active = 1");
                $stmt->execute();
                $stmt = $db->prepare("INSERT INTO office_polygons (name, coordinates, color, description, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $coordinates, $color, sanitizeInput($_POST['description'] ?? ''), $_SESSION['user_id']]);
                logAudit('save_polygon', 'polygon', "Saved office polygon: $name");
                setFlash('Field geofence boundary saved and activated successfully', 'success');
            } else {
                setFlash('Invalid geofence boundary coordinates (at least 3 vertices required)', 'danger');
            }
        } else {
            setFlash('Please plot polygon boundary points on the interactive map first', 'danger');
        }
        redirect(baseUrl() . '/index.php?page=polygon');
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM office_polygons WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('Polygon boundary deleted', 'success');
        redirect(baseUrl() . '/index.php?page=polygon');
    }
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=dashboard" class="hover:text-blue-600">Home</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold">Geofencing</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">Field Geofence Boundary</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Configure authorized territorial polygon boundaries for GPS-validated field attendance
            </p>
        </div>
        <div class="flex items-center space-x-2">
            <span class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-blue-50 text-blue-800 border border-blue-200/60 text-xs font-bold">
                <i class="fas fa-satellite text-blue-600"></i>
                <span>Ray-Casting Algorithm (JS &bull; PHP)</span>
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Map Canvas (2 cols) -->
        <div class="lg:col-span-2 space-y-4">
            <div class="app-card p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3 pb-3 border-b border-slate-100">
                    <div class="flex items-center space-x-2">
                        <button id="startDrawing" type="button"
                                class="inline-flex items-center space-x-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition-colors">
                            <i class="fas fa-draw-polygon text-xs"></i>
                            <span>Draw Polygon</span>
                        </button>
                        <button id="clearDrawing" type="button"
                                class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/60 rounded-xl text-xs font-bold transition-colors">
                            <i class="fas fa-rotate-left text-xs"></i>
                            <span>Reset Canvas</span>
                        </button>
                    </div>
                    <div id="coordCount" class="text-xs font-semibold text-slate-500 font-mono">
                        No points plotted
                    </div>
                </div>

                <div id="polygonMap" style="height: 520px;" class="rounded-xl border border-slate-200 overflow-hidden shadow-inner"></div>

                <div class="mt-3 flex items-center justify-between text-[11px] text-slate-400">
                    <span>Click on map to place polygon vertices. 3 or more points form a closed zone.</span>
                    <span>Cauayan City Water District Map Grid</span>
                </div>
            </div>
        </div>

        <!-- Sidebar Config & History (1 col) -->
        <div class="space-y-6">
            <!-- Save Form Card -->
            <div class="app-card p-6">
                <h3 class="font-bold text-base text-navy-950 font-heading mb-4 pb-2 border-b border-slate-100 flex items-center space-x-2">
                    <i class="fas fa-floppy-disk text-blue-600"></i>
                    <span>Save Boundary Zone</span>
                </h3>
                <form method="POST" id="polygonForm" class="space-y-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="coordinates" id="coordinatesInput" value="">

                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Boundary Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" value="CCWD Main Complex" required
                               class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Boundary Color</label>
                        <div class="flex items-center space-x-3">
                            <input type="color" name="color" value="#1d4ed8" class="w-10 h-10 rounded-xl border border-slate-200 cursor-pointer p-0.5">
                            <span class="text-xs text-slate-500 font-mono">Theme accent</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Description</label>
                        <textarea name="description" rows="2" placeholder="e.g. Authorized perimeter for outside water meter technicians"
                                  class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></textarea>
                    </div>

                    <button type="submit" id="savePolygonBtn" disabled
                            class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white font-bold rounded-xl text-xs sm:text-sm transition-all shadow-xs flex items-center justify-center space-x-2">
                        <i class="fas fa-check text-xs"></i>
                        <span>Activate & Save Boundary</span>
                    </button>
                </form>
            </div>

            <!-- Saved Boundaries List -->
            <div class="app-card p-6">
                <h3 class="font-bold text-base text-navy-950 font-heading mb-3 pb-2 border-b border-slate-100 flex items-center justify-between">
                    <span>Boundary Presets</span>
                    <span class="text-xs font-bold text-slate-400 font-mono"><?= count($polygons) ?></span>
                </h3>
                <div class="space-y-2.5 max-h-64 overflow-y-auto custom-scrollbar">
                    <?php if (empty($polygons)): ?>
                    <p class="text-xs text-slate-400 text-center py-4">No boundaries saved yet. Draw one on the map canvas.</p>
                    <?php else: foreach ($polygons as $p): ?>
                    <div class="flex items-center justify-between p-3 rounded-xl border transition-all <?= $p['is_active'] ? 'bg-blue-50/80 border-blue-200' : 'bg-slate-50/70 border-slate-200 hover:bg-slate-100/80' ?>">
                        <div class="flex items-center space-x-2.5 min-w-0">
                            <span class="w-3.5 h-3.5 rounded-full shrink-0 shadow-2xs" style="background:<?= escapeOutput($p['color']) ?>"></span>
                            <div class="truncate">
                                <p class="text-xs font-bold text-slate-800 font-heading truncate"><?= escapeOutput($p['name']) ?></p>
                                <p class="text-[10px] text-slate-400"><?= formatDate($p['created_at']) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-1 shrink-0 ml-2">
                            <button onclick="loadPolygon(<?= $p['id'] ?>)" type="button"
                                    class="px-2 py-1 text-[11px] font-bold text-blue-600 hover:bg-blue-100/70 rounded-lg transition-colors">
                                View
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this boundary permanently?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="px-2 py-1 text-[11px] font-bold text-rose-600 hover:bg-rose-100/70 rounded-lg transition-colors">
                                    Del
                                </button>
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
    const btn = document.getElementById("startDrawing");
    btn.innerHTML = "<i class=\"fas fa-crosshairs mr-1\"></i><span>Click Map to Plot</span>";
    btn.classList.remove("bg-blue-600", "hover:bg-blue-700");
    btn.classList.add("bg-emerald-600", "hover:bg-emerald-700");
    polygonMap.on("click", onMapClick);
}

function onMapClick(e) {
    coordinates.push({ lat: e.latlng.lat, lng: e.latlng.lng });
    L.circleMarker([e.latlng.lat, e.latlng.lng], {
        radius: 6, color: "#1d4ed8", fillColor: "#2563eb", fillOpacity: 1, weight: 2
    }).addTo(markerLayer);

    L.marker([e.latlng.lat, e.latlng.lng], {
        icon: L.divIcon({
            className: "coord-label",
            html: "<span style=\"font-size:10px;font-weight:700;background:#0b1d36;color:white;padding:2px 6px;border-radius:6px;box-shadow:0 2px 4px rgba(0,0,0,0.2);white-space:nowrap;\">P" + coordinates.length + "</span>",
            iconSize: [0, 0]
        })
    }).addTo(markerLayer);
    updatePolygon();
    updateCoordDisplay();
}

function updatePolygon() {
    drawingLayer.clearLayers();
    if (coordinates.length >= 3) {
        const polyColor = document.querySelector("[name=color]")?.value || "#1d4ed8";
        drawnPolygon = L.polygon(coordinates.map(c => [c.lat, c.lng]), {
            color: polyColor,
            fillColor: polyColor,
            fillOpacity: 0.18,
            weight: 2.5
        }).addTo(drawingLayer);
        polygonMap.fitBounds(drawnPolygon.getBounds().pad(0.1));
    }
}

function updateCoordDisplay() {
    const count = coordinates.length;
    document.getElementById("coordCount").textContent = count + " point" + (count !== 1 ? "s" : "") + " plotted" + (count >= 3 ? " &bull; Ready" : " (min. 3)");
    document.getElementById("coordinatesInput").value = JSON.stringify(coordinates);
    document.getElementById("savePolygonBtn").disabled = count < 3;
}

function clearAll() {
    coordinates = [];
    drawingLayer.clearLayers();
    markerLayer.clearLayers();
    isDrawing = false;
    polygonMap.off("click", onMapClick);
    const btn = document.getElementById("startDrawing");
    btn.innerHTML = "<i class=\"fas fa-draw-polygon text-xs mr-1\"></i><span>Draw Polygon</span>";
    btn.classList.remove("bg-emerald-600", "hover:bg-emerald-700");
    btn.classList.add("bg-blue-600", "hover:bg-blue-700");
    updateCoordDisplay();
}

function drawExistingPolygon(coords) {
    drawingLayer.clearLayers();
    markerLayer.clearLayers();
    const latlngs = coords.map(c => [c.lat, c.lng]);
    L.polygon(latlngs, {
        color: "' . ($activePolygon['color'] ?? '#1d4ed8') . '",
        fillColor: "' . ($activePolygon['color'] ?? '#1d4ed8') . '",
        fillOpacity: 0.18,
        weight: 2.5
    }).addTo(drawingLayer);
    coords.forEach((c, i) => {
        L.circleMarker([c.lat, c.lng], { radius: 5, color: "#1d4ed8", fillColor: "#2563eb", fillOpacity: 1, weight: 2 }).addTo(markerLayer);
    });
    polygonMap.fitBounds(latlngs);
}

function loadPolygon(id) {
    fetch("' . baseUrl() . '/api/polygon.php?id=" + id)
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

<?php
requireLogin();

$id = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("
    SELECT a.*, e.first_name, e.last_name, e.employee_no, e.position, d.name as department_name
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    setFlash('Attendance record not found', 'danger');
    redirect(baseUrl() . '/index.php?page=attendance');
}

if (isEmployee()) {
    $empId = getEmployeeIdFromUser($_SESSION['user_id']);
    if ($record['employee_id'] != $empId) {
        setFlash('Access denied to requested attendance evidence', 'danger');
        redirect(baseUrl() . '/index.php?page=attendance');
    }
}

$activePolygon = getActivePolygon();
$polygonCoords = $activePolygon ? json_decode($activePolygon['coordinates'], true) : [];

$photoTypes = ['time_in', 'time_out', 'break_in', 'break_out'];
$photos = [];
foreach ($photoTypes as $pt) {
    if (!empty($record[$pt . '_photo'])) {
        $photos[] = [
            'type' => $pt,
            'file' => $record[$pt . '_photo'],
            'lat' => $record[$pt . '_lat'],
            'lng' => $record[$pt . '_lng'],
            'status' => $record[$pt . '_status'] ?? 'pending',
            'time' => $record[$pt]
        ];
    }
}
?>

<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=attendance" class="hover:text-blue-600">Attendance</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold">Evidence Audit #<?= $id ?></span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">Selfie & Geofence Evidence</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Watermarked biometric evidence captured on <span class="font-bold text-slate-800"><?= formatDate($record['date']) ?></span>
            </p>
        </div>
        <div class="flex items-center space-x-2">
            <button onclick="window.print()" type="button"
                    class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition-colors">
                <i class="fas fa-print text-slate-500"></i>
                <span>Print Audit</span>
            </button>
            <a href="<?= baseUrl() ?>/index.php?page=attendance"
               class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-xl bg-navy-950 hover:bg-navy-900 text-white text-xs font-bold shadow-xs transition-colors">
                <i class="fas fa-arrow-left text-xs"></i>
                <span>Back</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Photo Cards (2 cols) -->
        <div class="lg:col-span-2 space-y-6">
            <?php foreach ($photos as $photo): ?>
            <div class="app-card overflow-hidden">
                <div class="px-6 py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                    <div class="flex items-center space-x-2">
                        <div class="w-7 h-7 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h3 class="font-bold text-sm text-navy-950 font-heading capitalize">
                            <?= str_replace('_', ' ', $photo['type']) ?> Evidence
                        </h3>
                    </div>
                    <div><?= getStatusBadge($photo['status']) ?></div>
                </div>
                <div class="p-6">
                    <?php if ($photo['file'] && file_exists(__DIR__ . '/../../' . $photo['file'])): ?>
                    <div class="relative group rounded-xl overflow-hidden border border-slate-200 bg-black/5 shadow-inner">
                        <img loading="lazy" src="<?= baseUrl('/' . $photo['file']) ?>"
                             class="w-full object-contain max-h-[380px] mx-auto rounded-lg"
                             alt="Attendance watermarked photo">
                        <div class="absolute bottom-2 right-2 px-2.5 py-1 rounded-md bg-navy-950/80 backdrop-blur-xs text-white text-[10px] font-mono font-medium">
                            <i class="fas fa-certificate text-cyan-400 mr-1"></i>GD Tamper Watermark
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-slate-400">
                        <i class="fas fa-camera-slash text-3xl mb-2 text-slate-300"></i>
                        <p class="text-xs font-bold text-slate-600">Physical image file missing on disk</p>
                        <p class="text-[11px] font-mono text-slate-400 mt-1"><?= escapeOutput($photo['file'] ?? 'N/A') ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-slate-50/75 border border-slate-200/60 text-xs">
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Timestamp</span>
                            <span class="font-bold text-slate-800 font-mono mt-0.5 block"><?= $photo['time'] ? formatDateTime($photo['time']) : 'N/A' ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Latitude</span>
                            <span class="font-medium text-slate-700 font-mono mt-0.5 block"><?= $photo['lat'] ? number_format($photo['lat'], 6) : 'N/A' ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Longitude</span>
                            <span class="font-medium text-slate-700 font-mono mt-0.5 block"><?= $photo['lng'] ? number_format($photo['lng'], 6) : 'N/A' ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Geofence Status</span>
                            <span class="font-bold text-emerald-700 mt-0.5 block capitalize"><?= escapeOutput($photo['status']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($photos)): ?>
            <div class="app-card p-12 text-center text-slate-400">
                <i class="fas fa-camera-slash text-4xl mb-3 text-slate-300"></i>
                <h4 class="font-bold text-slate-700 text-sm">No selfie photos stored</h4>
                <p class="text-xs text-slate-400 mt-1">This record might have been captured via manual administrator entry or office biometric without camera.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar Info & Map (1 col) -->
        <div class="space-y-6">
            <!-- Employee Info Card -->
            <div class="app-card p-6">
                <h3 class="font-bold text-base text-navy-950 font-heading mb-4 pb-2 border-b border-slate-100 flex items-center space-x-2">
                    <i class="fas fa-id-badge text-blue-600"></i>
                    <span>Employee Profile</span>
                </h3>
                <div class="space-y-3 text-xs sm:text-sm">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Staff Name</span>
                        <p class="font-bold text-slate-800 text-sm mt-0.5"><?= escapeOutput($record['first_name'] . ' ' . $record['last_name']) ?></p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Employee ID</span>
                            <p class="font-mono font-bold text-slate-800 mt-0.5"><?= escapeOutput($record['employee_no']) ?></p>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Department</span>
                            <p class="font-semibold text-blue-700 mt-0.5"><?= escapeOutput($record['department_name'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Official Position</span>
                        <p class="font-medium text-slate-700 mt-0.5"><?= escapeOutput($record['position'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>

            <!-- Attendance Summary -->
            <div class="app-card p-6">
                <h3 class="font-bold text-base text-navy-950 font-heading mb-3 pb-2 border-b border-slate-100">
                    Daily Schedule Summary
                </h3>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-500">Date</span><span class="font-bold text-slate-800"><?= formatDate($record['date']) ?></span></div>
                    <div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-500">Time In</span><span class="font-bold font-mono text-slate-800"><?= $record['time_in'] ? formatTime($record['time_in']) : '--' ?></span></div>
                    <div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-500">Break Out</span><span class="font-mono text-slate-700"><?= $record['break_out'] ? formatTime($record['break_out']) : '--' ?></span></div>
                    <div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-500">Break In</span><span class="font-mono text-slate-700"><?= $record['break_in'] ? formatTime($record['break_in']) : '--' ?></span></div>
                    <div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-500">Time Out</span><span class="font-bold font-mono text-slate-800"><?= $record['time_out'] ? formatTime($record['time_out']) : '--' ?></span></div>
                    <div class="flex justify-between pt-2 items-center"><span class="text-slate-500 font-medium">Overall Status</span><span><?= getStatusBadge($record['time_in_status'] ?? 'pending') ?></span></div>
                </div>
            </div>

            <!-- Geolocation Map -->
            <div class="app-card p-6">
                <h3 class="font-bold text-base text-navy-950 font-heading mb-3 flex items-center space-x-2">
                    <i class="fas fa-map-location-dot text-blue-600"></i>
                    <span>GPS Verification Pin</span>
                </h3>
                <div id="evidenceMap" style="height: 280px;" class="rounded-xl border border-slate-200 overflow-hidden shadow-inner"></div>
                <div id="mapCoords" class="mt-3 space-y-1.5 text-xs text-slate-600 font-mono"></div>
            </div>
        </div>
    </div>
</div>

<?php
$photoData = json_encode($photos);
$polygonData = json_encode($polygonCoords);
$additionalScripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const photos = ' . $photoData . ';
    const polygon = ' . $polygonData . ';
    const map = L.map("evidenceMap").setView([16.93, 121.77], 16);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors", maxZoom: 19
    }).addTo(map);
    const bounds = [];
    if (polygon && polygon.length >= 3) {
        const pLayer = L.polygon(polygon.map(c => [c.lat, c.lng]), {
            color: "#1d4ed8", fillColor: "#2563eb", fillOpacity: 0.12, weight: 2
        }).addTo(map);
        pLayer.bindPopup("<b>CCWD Authorized Geofence</b>");
        bounds.push(...polygon.map(c => [c.lat, c.lng]));
    }
    const coordsHtml = [];
    photos.forEach(p => {
        if (p.lat && p.lng) {
            const isVer = (p.status === "verified");
            const color = isVer ? "#059669" : "#e11d48";
            const marker = L.circleMarker([p.lat, p.lng], {
                radius: 8, color: color, fillColor: color, fillOpacity: 0.85, weight: 2
            }).addTo(map);
            const label = p.type.replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase());
            marker.bindPopup("<b>" + label + "</b><br>" + (p.time || "") + "<br>Lat: " + p.lat + "<br>Lng: " + p.lng);
            bounds.push([p.lat, p.lng]);
            coordsHtml.push("<div class=\"p-2 bg-slate-50 rounded-lg border border-slate-200\"><strong class=\"text-slate-800\">" + label + ":</strong> " + p.lat + ", " + p.lng + "</div>");
        }
    });
    const mapCoordsEl = document.getElementById("mapCoords");
    if (mapCoordsEl) mapCoordsEl.innerHTML = coordsHtml.join("");
    if (bounds.length > 0) map.fitBounds(bounds, { padding: [40, 40] });
});
</script>
';
?>

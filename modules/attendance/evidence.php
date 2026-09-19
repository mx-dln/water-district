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
$record = $stmt->fetch();

if (!$record) {
    setFlash('Attendance record not found', 'danger');
    redirect(APP_URL . '/index.php?page=attendance');
}

if (isEmployee()) {
    $empId = getEmployeeIdFromUser($_SESSION['user_id']);
    if ($record['employee_id'] != $empId) {
        setFlash('Access denied', 'danger');
        redirect(APP_URL . '/index.php?page=attendance');
    }
}

$activePolygon = getActivePolygon();
$polygonCoords = $activePolygon ? json_decode($activePolygon['coordinates'], true) : [];

$photoTypes = ['time_in', 'time_out', 'break_in', 'break_out'];
$photos = [];
foreach ($photoTypes as $pt) {
    if ($record[$pt . '_photo']) {
        $photos[] = [
            'type' => $pt,
            'file' => $record[$pt . '_photo'],
            'lat' => $record[$pt . '_lat'],
            'lng' => $record[$pt . '_lng'],
            'status' => $record[$pt . '_status'],
            'time' => $record[$pt]
        ];
    }
}
?>
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Attendance Evidence</h1>
            <p class="text-sm text-gray-500">Record #<?= $id ?> | <?= formatDate($record['date']) ?></p>
        </div>
        <div class="flex space-x-2">
            <button onclick="window.print()" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-print mr-2"></i>Print</button>
            <a href="<?= APP_URL ?>/index.php?page=attendance" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-arrow-left mr-1"></i>Back</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <?php foreach ($photos as $photo): ?>
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="px-5 py-3 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 capitalize"><?= str_replace('_', ' ', $photo['type']) ?></h3>
                    <span><?= getStatusBadge($photo['status']) ?></span>
                </div>
                <div class="p-4">
                    <?php if ($photo['file'] && file_exists(__DIR__ . '/../../' . $photo['file'])): ?>
                    <img loading="lazy" src="<?= baseUrl('/' . $photo['file']) ?>" class="w-full rounded-lg border" alt="Attendance photo">
                    <?php else: ?>
                    <div class="bg-gray-100 rounded-lg p-8 text-center text-gray-400">
                        <i class="fas fa-image text-4xl mb-2"></i>
                        <p class="text-sm">Image file not found</p>
                        <p class="text-xs mt-1">Path: <?= escapeOutput($photo['file'] ?? 'N/A') ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">Time:</span> <span class="font-medium"><?= $photo['time'] ? formatDateTime($photo['time']) : 'N/A' ?></span></div>
                        <div><span class="text-gray-500">Latitude:</span> <span class="font-medium"><?= $photo['lat'] ?? 'N/A' ?></span></div>
                        <div><span class="text-gray-500">Longitude:</span> <span class="font-medium"><?= $photo['lng'] ?? 'N/A' ?></span></div>
                        <div><span class="text-gray-500">Status:</span> <span class="font-medium"><?= ucfirst($photo['status']) ?></span></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($photos)): ?>
            <div class="bg-white rounded-xl shadow-sm border p-8 text-center">
                <i class="fas fa-camera-slash text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">No attendance photos available for this record</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Employee Information</h3>
                <div class="space-y-3 text-sm">
                    <div><span class="text-gray-500">Name:</span><br><span class="font-medium"><?= escapeOutput($record['first_name'] . ' ' . $record['last_name']) ?></span></div>
                    <div><span class="text-gray-500">Employee No:</span><br><span class="font-medium"><?= escapeOutput($record['employee_no']) ?></span></div>
                    <div><span class="text-gray-500">Department:</span><br><span class="font-medium"><?= escapeOutput($record['department_name'] ?? 'N/A') ?></span></div>
                    <div><span class="text-gray-500">Position:</span><br><span class="font-medium"><?= escapeOutput($record['position'] ?? 'N/A') ?></span></div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-5">
                <h3 class="font-semibold text-gray-800 mb-3">Attendance Summary</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Date</span><span><?= formatDate($record['date']) ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Time In</span><span><?= $record['time_in'] ? formatTime($record['time_in']) : '--' ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Break Out</span><span><?= $record['break_out'] ? formatTime($record['break_out']) : '--' ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Break In</span><span><?= $record['break_in'] ? formatTime($record['break_in']) : '--' ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Time Out</span><span><?= $record['time_out'] ? formatTime($record['time_out']) : '--' ?></span></div>
                    <div class="border-t pt-2 flex justify-between"><span class="text-gray-500">Status</span><span><?= getStatusBadge($record['time_in_status'] ?? 'pending') ?></span></div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-5">
                <h3 class="font-semibold text-gray-800 mb-3">Location Map</h3>
                <div id="evidenceMap" style="height: 300px;" class="rounded-lg border"></div>
                <div id="mapCoords" class="mt-2 space-y-1 text-xs text-gray-500"></div>
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
            color: "#2563eb", fillColor: "#2563eb", fillOpacity: 0.1, weight: 2
        }).addTo(map);
        pLayer.bindPopup("Office Boundary");
        bounds.push(...polygon.map(c => [c.lat, c.lng]));
    }
    const coordsHtml = [];
    photos.forEach(p => {
        if (p.lat && p.lng) {
            const color = p.status === "verified" ? "green" : "red";
            const marker = L.circleMarker([p.lat, p.lng], {
                radius: 8, color: color, fillColor: color, fillOpacity: 0.7
            }).addTo(map);
            const label = p.type.replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase());
            marker.bindPopup("<b>" + label + "</b><br>" + p.time + "<br>Lat: " + p.lat + "<br>Lng: " + p.lng);
            bounds.push([p.lat, p.lng]);
            coordsHtml.push("<div><b>" + label + ":</b> " + p.lat + ", " + p.lng + "</div>");
        }
    });
    document.getElementById("mapCoords").innerHTML = coordsHtml.join("");
    if (bounds.length > 0) map.fitBounds(bounds, { padding: [50, 50] });
});
</script>
';
?>

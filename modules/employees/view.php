<?php
requireRole('Administrator');
$id = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("
    SELECT e.*, d.name as department_name, u.username, u.email as user_email, u.last_login, u.is_active
    FROM employees e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$emp = $stmt->fetch();
if (!$emp) { setFlash('Employee not found', 'danger'); redirect(APP_URL . '/index.php?page=employees'); }

$recentAtt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 5");
$recentAtt->execute([$id]);
$recentAtt = $recentAtt->fetchAll();

$faceTemplates = $db->prepare("SELECT * FROM employee_faces WHERE employee_id = ? ORDER BY created_at DESC");
$faceTemplates->execute([$id]);
$faceTemplates = $faceTemplates->fetchAll();
?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Employee Profile</h1>
        <div class="flex space-x-2">
            <button onclick="openFaceEnrollModal(<?= $id ?>, '<?= addslashes(escapeOutput($emp['first_name'] . ' ' . $emp['last_name'])) ?>')" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 shadow flex items-center"><i class="fas fa-camera mr-2"></i>Face Recognition ID</button>
            <a href="<?= APP_URL ?>/index.php?page=employees-edit&id=<?= $id ?>" class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600"><i class="fas fa-edit mr-2"></i>Edit</a>
            <a href="<?= APP_URL ?>/index.php?page=employees" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-arrow-left mr-1"></i>Back</a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="flex items-start space-x-6">
            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-2xl font-bold text-blue-700"><?= strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)) ?></span>
            </div>
            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase">Employee No</p>
                    <p class="font-medium"><?= escapeOutput($emp['employee_no']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Full Name</p>
                    <p class="font-medium"><?= escapeOutput($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Status</p>
                    <p><?= getStatusBadge($emp['employment_status']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Department</p>
                    <p class="font-medium"><?= escapeOutput($emp['department_name'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Position</p>
                    <p class="font-medium"><?= escapeOutput($emp['position'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Contact</p>
                    <p class="font-medium"><?= escapeOutput($emp['contact_no'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Email</p>
                    <p class="font-medium"><?= escapeOutput($emp['user_email'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Username</p>
                    <p class="font-medium"><?= escapeOutput($emp['username']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Last Login</p>
                    <p class="font-medium"><?= $emp['last_login'] ? formatDateTime($emp['last_login']) : 'Never' ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border">
        <div class="px-5 py-4 border-b"><h3 class="font-semibold text-gray-800">Recent Attendance</h3></div>
        <div class="divide-y">
            <?php if (empty($recentAtt)): ?>
            <p class="p-4 text-sm text-gray-500 text-center">No attendance records</p>
            <?php else: foreach ($recentAtt as $a): ?>
            <div class="px-5 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium"><?= formatDate($a['date']) ?></p>
                    <p class="text-xs text-gray-500">In: <?= $a['time_in'] ? formatTime($a['time_in']) : '--' ?> | Out: <?= $a['time_out'] ? formatTime($a['time_out']) : '--' ?></p>
                </div>
                <div class="flex items-center space-x-2">
                    <?= getStatusBadge($a['time_in_status'] ?? 'pending') ?>
                    <a href="<?= APP_URL ?>/index.php?page=attendance-evidence&id=<?= $a['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm" title="View Evidence"><i class="fas fa-image"></i></a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Face Enrollment Modal -->
<div id="faceEnrollModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b flex items-center justify-between bg-gray-50">
            <div>
                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-id-badge text-blue-600"></i>
                    <span>Face Recognition Enrollment</span>
                </h3>
                <p id="enrollEmployeeName" class="text-xs text-gray-500 font-medium">Employee Name</p>
            </div>
            <button onclick="closeFaceEnrollModal()" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        </div>

        <div class="p-6 overflow-y-auto space-y-4">
            <div class="relative w-full aspect-video bg-black rounded-xl overflow-hidden border">
                <video id="enrollVideo" class="w-full h-full object-cover" autoplay muted playsinline></video>
                <canvas id="enrollCanvas" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>
            </div>

            <div id="enrollStatus" class="text-xs text-center py-2 px-3 bg-gray-50 rounded-lg border font-medium text-gray-600">
                Initializing face detection camera...
            </div>

            <button id="captureFaceBtn" onclick="captureAndEnrollFace()" disabled class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold rounded-xl text-sm transition shadow flex items-center justify-center gap-2">
                <i class="fas fa-camera"></i>
                <span>Register Face Template</span>
            </button>

            <div class="pt-3 border-t">
                <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Registered Face Samples:</h4>
                <div id="existingFacesList" class="space-y-1.5 max-h-36 overflow-y-auto">
                </div>
            </div>
        </div>

        <div class="px-6 py-3 border-t bg-gray-50 flex justify-end">
            <button onclick="location.reload()" class="px-4 py-2 border rounded-lg text-xs font-medium hover:bg-gray-100">
                Done / Refresh Page
            </button>
        </div>
    </div>
</div>

<script>
const ENROLL_CONFIG = {
    faceApiUrl: "<?= baseUrl('/api/face.php') ?>",
    modelsUrl: "<?= baseUrl('/assets/models') ?>",
    csrfTokenName: "<?= CSRF_TOKEN_NAME ?>",
    csrfToken: "<?= generateCSRFToken() ?>"
};
</script>
<script src="<?= baseUrl('/assets/js/face-api.min.js') ?>"></script>
<script src="<?= baseUrl('/assets/js/face-enroll.js?v=' . filemtime(__DIR__ . '/../../assets/js/face-enroll.js')) ?>"></script>
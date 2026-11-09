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
$emp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$emp) {
    setFlash('Employee not found', 'danger');
    redirect(baseUrl() . '/index.php?page=employees');
}

$recentAtt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 6");
$recentAtt->execute([$id]);
$recentAtt = $recentAtt->fetchAll(PDO::FETCH_ASSOC);

$faceTemplates = $db->prepare("SELECT * FROM employee_faces WHERE employee_id = ? ORDER BY created_at DESC");
$faceTemplates->execute([$id]);
$faceTemplates = $faceTemplates->fetchAll(PDO::FETCH_ASSOC);

$initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
?>

<div class="max-w-5xl mx-auto space-y-6">
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=employees" class="hover:text-blue-600">Employees</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold"><?= escapeOutput($emp['employee_no']) ?></span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">Personnel Dossier</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Detailed profile, departmental position, biometric face templates, and attendance log
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button onclick="openFaceEnrollModal(<?= $id ?>, '<?= addslashes(escapeOutput($emp['first_name'] . ' ' . $emp['last_name'])) ?>')"
                    class="inline-flex items-center space-x-2 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-xs transition-colors">
                <i class="fas fa-camera text-xs"></i>
                <span>Biometric Face ID</span>
            </button>
            <a href="<?= baseUrl() ?>/index.php?page=employees-edit&id=<?= $id ?>"
               class="inline-flex items-center space-x-2 px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-xs transition-colors">
                <i class="fas fa-pen-to-square text-xs"></i>
                <span>Edit Profile</span>
            </a>
            <a href="<?= baseUrl() ?>/index.php?page=employees"
               class="inline-flex items-center space-x-2 px-3.5 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold shadow-xs transition-colors">
                <i class="fas fa-arrow-left text-slate-400"></i>
                <span>Back</span>
            </a>
        </div>
    </div>

    <!-- Main Profile Card -->
    <div class="app-card p-6 sm:p-8">
        <div class="flex flex-col md:flex-row items-start gap-6">
            <div class="relative shrink-0 mx-auto md:mx-0">
                <div class="w-24 h-24 rounded-xl bg-blue-700 flex items-center justify-center text-white text-3xl font-bold font-heading shadow-sm">
                    <?= $initials ?>
                </div>
                <div class="absolute -bottom-1 -right-1">
                    <?= getStatusBadge($emp['employment_status']) ?>
                </div>
            </div>

            <div class="flex-1 w-full space-y-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200/60">
                            <?= escapeOutput($emp['department_name'] ?? 'Unassigned Dept') ?>
                        </span>
                        <span class="text-xs font-mono font-bold text-slate-500">ID: <?= escapeOutput($emp['employee_no']) ?></span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading">
                        <?= escapeOutput($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']) ?>
                    </h2>
                    <p class="text-xs sm:text-sm font-semibold text-slate-600"><?= escapeOutput($emp['position'] ?? 'No Position Assigned') ?></p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-4 border-t border-slate-100 text-xs">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Username</span>
                        <span class="font-bold text-slate-800 font-mono mt-0.5 block"><?= escapeOutput($emp['username']) ?></span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Account Email</span>
                        <span class="font-medium text-slate-800 mt-0.5 block"><?= escapeOutput($emp['user_email'] ?? 'None') ?></span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Contact Number</span>
                        <span class="font-medium text-slate-800 font-mono mt-0.5 block"><?= escapeOutput($emp['contact_no'] ?? 'N/A') ?></span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Last Login Session</span>
                        <span class="font-medium text-slate-700 mt-0.5 block"><?= $emp['last_login'] ? formatDateTime($emp['last_login']) : 'No session recorded' ?></span>
                    </div>
                    <div class="sm:col-span-2">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 font-heading">Registered Address</span>
                        <span class="font-medium text-slate-700 mt-0.5 block"><?= escapeOutput($emp['address'] ?? 'No address registered') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Biometric Face Templates Card -->
    <div class="app-card p-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                    <i class="fas fa-camera text-xs"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-navy-950 font-heading">Face Recognition Descriptors</h3>
                    <p class="text-xs text-slate-500">128-dimensional biometric embeddings registered in the neural model</p>
                </div>
            </div>
            <span class="px-2.5 py-1 text-xs font-bold rounded-full <?= count($faceTemplates) > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' ?>">
                <?= count($faceTemplates) ?> Samples Registered
            </span>
        </div>
        <?php if (empty($faceTemplates)): ?>
        <div class="p-8 text-center text-slate-400">
            <i class="fas fa-face-frown text-3xl mb-2 text-slate-300"></i>
            <p class="text-sm font-semibold text-slate-600">No biometric face samples enrolled for this employee.</p>
            <p class="text-xs text-slate-400 mt-1">Click the "Biometric Face ID" button above to capture neural facial descriptors.</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            <?php foreach ($faceTemplates as $idx => $ft): ?>
            <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/70 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-800 font-heading">Sample #<?= $idx + 1 ?></span>
                    <span class="block text-[10px] text-slate-400 mt-0.5"><?= formatDate($ft['created_at']) ?></span>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                    <i class="fas fa-check text-[9px] mr-1"></i>Active
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Attendance Logs Card -->
    <div class="app-card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="font-bold text-base text-navy-950 font-heading">Recent Attendance Logs</h3>
            <a href="<?= baseUrl() ?>/index.php?page=attendance-history&employee_id=<?= $id ?>"
               class="text-xs font-bold text-blue-600 hover:text-blue-800">
                View Full Log &rarr;
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-100/75 text-slate-600 text-[11px] font-bold uppercase tracking-wider font-heading border-b border-slate-200/80">
                    <tr>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-4 py-3">Time In</th>
                        <th class="px-4 py-3">Time Out</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Selfie Evidence</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($recentAtt)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-slate-400 text-sm">
                            No attendance records logged for this employee.
                        </td>
                    </tr>
                    <?php else: foreach ($recentAtt as $a): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-3.5 font-bold font-heading text-slate-800"><?= formatDate($a['date']) ?></td>
                        <td class="px-4 py-3.5 font-bold font-mono text-slate-800"><?= $a['time_in'] ? formatTime($a['time_in']) : '--' ?></td>
                        <td class="px-4 py-3.5 font-bold font-mono text-slate-800"><?= $a['time_out'] ? formatTime($a['time_out']) : '--' ?></td>
                        <td class="px-4 py-3.5"><?= getStatusBadge($a['time_in_status'] ?? 'pending') ?></td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="<?= baseUrl() ?>/index.php?page=attendance-evidence&id=<?= $a['id'] ?>"
                               class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors">
                                <i class="fas fa-camera text-[11px]"></i>
                                <span>Audit</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Face Enrollment Modal -->
<div id="faceEnrollModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-navy-950/70 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-200 flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900 text-white">
            <div>
                <h3 class="font-bold text-sm sm:text-base font-heading flex items-center gap-2">
                    <i class="fas fa-id-badge text-blue-400"></i>
                    <span>Face Recognition Enrollment</span>
                </h3>
                <p id="enrollEmployeeName" class="text-xs text-slate-300 font-medium">Employee Name</p>
            </div>
            <button onclick="closeFaceEnrollModal()" class="text-white/60 hover:text-white text-xl font-bold p-1">&times;</button>
        </div>

        <div class="p-6 overflow-y-auto space-y-4 custom-scrollbar">
            <div class="relative w-full aspect-video bg-black rounded-xl overflow-hidden border border-slate-300 shadow-inner">
                <video id="enrollVideo" class="w-full h-full object-cover" autoplay muted playsinline></video>
                <canvas id="enrollCanvas" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>
            </div>

            <div id="enrollStatus" class="text-xs text-center py-2.5 px-3 bg-slate-50 rounded-xl border border-slate-200 font-medium text-slate-700">
                Initializing face detection camera...
            </div>

            <button id="captureFaceBtn" onclick="captureAndEnrollFace()" disabled
                    class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white font-bold rounded-xl text-xs sm:text-sm transition-all shadow-xs flex items-center justify-center gap-2">
                <i class="fas fa-camera"></i>
                <span>Register Face Descriptor</span>
            </button>

            <div class="pt-3 border-t border-slate-100">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-2">Registered Face Samples:</h4>
                <div id="existingFacesList" class="space-y-1.5 max-h-36 overflow-y-auto custom-scrollbar"></div>
            </div>
        </div>

        <div class="px-6 py-3 border-t border-slate-100 bg-slate-50 flex justify-end">
            <button onclick="location.reload()" class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition-colors">
                Done / Refresh Profile
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

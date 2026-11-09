<?php
requireRole('Administrator');

$action = $_GET['action'] ?? '';
$action = preg_replace('/[^a-z-]/', '', $action);

$actionFile = __DIR__ . '/employees/' . $action . '.php';
if (in_array($action, ['create', 'edit', 'view']) && file_exists($actionFile)) {
    require $actionFile;
    return;
}

$page = max(1, intval($_GET['p'] ?? 1));
$search = sanitizeInput($_GET['search'] ?? '');
$deptFilter = intval($_GET['department'] ?? 0);

$where = 'WHERE e.is_active = 1';
$params = [];
if ($search) {
    $where .= ' AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_no LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($deptFilter > 0) {
    $where .= ' AND e.department_id = ?';
    $params[] = $deptFilter;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM employees e $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = ceil($total / ITEMS_PER_PAGE);
$offset = ($page - 1) * ITEMS_PER_PAGE;

$stmt = $db->prepare("
    SELECT e.*, d.name as department_name, COALESCE(ef.face_count, 0) as face_count
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN (SELECT employee_id, COUNT(*) as face_count FROM employee_faces GROUP BY employee_id) ef ON e.id = ef.employee_id
    $where
    ORDER BY e.last_name ASC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [ITEMS_PER_PAGE, $offset]));
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$depts = $db->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=dashboard" class="hover:text-blue-600">Home</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold">Employees Directory</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">Staff & Personnel Directory</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Manage CCWD workforce records, department assignments, and biometric face enrollment
            </p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="<?= baseUrl() ?>/index.php?page=kiosk" target="_blank"
               class="inline-flex items-center space-x-2 px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors">
                <i class="fas fa-camera"></i>
                <span>Open Office Kiosk</span>
            </a>
            <a href="<?= baseUrl() ?>/index.php?page=employees-create"
               class="inline-flex items-center space-x-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors">
                <i class="fas fa-user-plus text-xs"></i>
                <span>Add Employee</span>
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="app-card p-5">
        <form method="GET" class="flex flex-wrap gap-3 items-center">
            <input type="hidden" name="page" value="employees">
            <div class="flex-1 min-w-[220px]">
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= escapeOutput($search) ?>" placeholder="Search by staff name or employee ID..."
                           class="w-full pl-9 pr-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                </div>
            </div>
            <div class="min-w-[180px]">
                <select name="department"
                        class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    <option value="0">All CCWD Departments</option>
                    <?php foreach ($depts as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>><?= escapeOutput($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center space-x-2">
                <button type="submit"
                        class="px-4 py-2 bg-slate-800 hover:bg-navy-950 text-white rounded-xl text-xs sm:text-sm font-bold transition-colors">
                    Filter
                </button>
                <a href="<?= baseUrl() ?>/index.php?page=employees"
                   class="px-4 py-2 border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-xl text-xs sm:text-sm font-semibold transition-colors">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Employee Table Card -->
    <div class="app-card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center space-x-2">
                <h3 class="font-bold text-base text-navy-950 font-heading">Registered Personnel</h3>
                <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-blue-100 text-blue-800"><?= $total ?></span>
            </div>
            <span class="text-xs text-slate-400">Page <?= $page ?> of <?= max(1, $totalPages) ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-100/75 text-slate-600 text-[11px] font-bold uppercase tracking-wider font-heading border-b border-slate-200/80">
                    <tr>
                        <th class="px-6 py-3">Employee No</th>
                        <th class="px-4 py-3">Full Name</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Position</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-center">Face Biometrics</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-users-slash text-3xl mb-2 text-slate-300"></i>
                            <p class="text-sm font-semibold text-slate-600">No employee records match the filter criteria.</p>
                            <p class="text-xs text-slate-400 mt-1">Try searching a different keyword or resetting filters.</p>
                        </td>
                    </tr>
                    <?php else: foreach ($employees as $emp):
                        $initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
                    ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-3.5 font-bold font-mono text-slate-800">
                            <?= escapeOutput($emp['employee_no']) ?>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-xs shrink-0">
                                    <?= $initials ?>
                                </div>
                                <span class="font-bold text-slate-800 font-heading">
                                    <?= escapeOutput($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-slate-600 font-medium">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700">
                                <?= escapeOutput($emp['department_name'] ?? 'Unassigned') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-slate-600">
                            <?= escapeOutput($emp['position'] ?? 'N/A') ?>
                        </td>
                        <td class="px-4 py-3.5">
                            <?= getStatusBadge($emp['employment_status']) ?>
                        </td>
                        <td class="px-4 py-3.5 text-center" id="faceBadge_<?= $emp['id'] ?>">
                            <?php if ($emp['face_count'] > 0): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                    <i class="fas fa-check-circle text-[10px] mr-1"></i>Enrolled (<?= $emp['face_count'] ?>)
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-500">
                                    <i class="fas fa-minus-circle text-[10px] mr-1"></i>Not Enrolled
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-3.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center space-x-1">
                                <button type="button" onclick="openFaceEnrollModal(<?= $emp['id'] ?>, '<?= addslashes(escapeOutput($emp['first_name'] . ' ' . $emp['last_name'])) ?>')"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-800 transition-colors"
                                        title="Enroll / Manage Face Biometrics">
                                    <i class="fas fa-camera text-xs"></i>
                                </button>
                                <a href="<?= baseUrl() ?>/index.php?page=employees-view&id=<?= $emp['id'] ?>"
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors"
                                   title="View Full Profile">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>
                                <a href="<?= baseUrl() ?>/index.php?page=employees-edit&id=<?= $emp['id'] ?>"
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-amber-600 hover:bg-amber-50 transition-colors"
                                   title="Edit Employee">
                                    <i class="fas fa-pen-to-square text-xs"></i>
                                </a>
                                <button type="button" onclick="confirmDelete(<?= $emp['id'] ?>)"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors"
                                        title="Archive Employee">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t border-slate-100 bg-slate-50/50">
            <?= paginate($page, $totalPages) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<form id="deleteForm" method="POST" action="<?= baseUrl() ?>/api/employee.php" class="hidden">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function confirmDelete(id) {
    if (confirm('Are you sure you want to deactivate this employee record? This action preserves previous logs but marks the profile inactive.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<!-- Face Biometrics Enrollment Modal -->
<div id="faceEnrollModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-navy-950/70 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-200 flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900 text-white">
            <div>
                <h3 class="font-bold text-sm sm:text-base font-heading flex items-center gap-2">
                    <i class="fas fa-id-badge text-blue-400"></i>
                    <span>Biometric Face Recognition Enrollment</span>
                </h3>
                <p id="enrollEmployeeName" class="text-xs text-slate-300 font-medium">Employee Name</p>
            </div>
            <button onclick="closeFaceEnrollModal()" class="text-white/60 hover:text-white text-xl font-bold p-1">&times;</button>
        </div>

        <div class="p-6 overflow-y-auto space-y-4 custom-scrollbar">
            <!-- Video & Canvas container -->
            <div class="relative w-full aspect-video bg-black rounded-xl overflow-hidden border border-slate-300 shadow-inner">
                <video id="enrollVideo" class="w-full h-full object-cover" autoplay muted playsinline></video>
                <canvas id="enrollCanvas" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>
            </div>

            <!-- Real-time Status Guide -->
            <div id="enrollStatus" class="text-xs text-center py-2.5 px-3 bg-slate-50 rounded-xl border border-slate-200 font-medium text-slate-700">
                Initializing face detection camera...
            </div>

            <!-- Capture Button -->
            <button id="captureFaceBtn" onclick="captureAndEnrollFace()" disabled
                    class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white font-bold rounded-xl text-xs sm:text-sm transition-all shadow-xs flex items-center justify-center gap-2">
                <i class="fas fa-camera"></i>
                <span>Register Face Descriptor Template</span>
            </button>

            <!-- Existing Templates -->
            <div class="pt-3 border-t border-slate-100">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-2">Registered Face Samples:</h4>
                <div id="existingFacesList" class="space-y-1.5 max-h-36 overflow-y-auto custom-scrollbar">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>

        <div class="px-6 py-3 border-t border-slate-100 bg-slate-50 flex justify-end">
            <button onclick="closeFaceEnrollModal()" class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition-colors">
                Done / Close
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
<script src="<?= baseUrl('/assets/js/face-enroll.js?v=' . filemtime(__DIR__ . '/../assets/js/face-enroll.js')) ?>"></script>

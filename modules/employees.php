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
$total = $countStmt->fetchColumn();
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
$employees = $stmt->fetchAll();

$depts = $db->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Employees</h1>
            <p class="text-sm text-gray-500"><?= $total ?> total employees</p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="<?= APP_URL ?>/index.php?page=kiosk" target="_blank" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold flex items-center gap-2 shadow transition">
                <i class="fas fa-camera"></i>
                <span>Open Office Kiosk</span>
            </a>
            <a href="<?= APP_URL ?>/index.php?page=employees-create" class="px-4 py-2 bg-blue-700 text-white rounded-lg text-sm hover:bg-blue-800">
                <i class="fas fa-plus mr-2"></i>Add Employee
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border">
        <div class="p-4 border-b">
            <form method="GET" class="flex flex-wrap gap-3">
                <input type="hidden" name="page" value="employees">
                <div class="flex-1 min-w-[200px]">
                    <input type="text" name="search" value="<?= escapeOutput($search) ?>" placeholder="Search name or ID..."
                           class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <select name="department" class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="0">All Departments</option>
                    <?php foreach ($depts as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>><?= escapeOutput($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200"><i class="fas fa-search mr-2"></i>Filter</button>
                <a href="<?= APP_URL ?>/index.php?page=employees" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200"><i class="fas fa-times mr-2"></i>Clear</a>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Employee No</th>
                        <th class="text-left px-4 py-3 font-medium">Name</th>
                        <th class="text-left px-4 py-3 font-medium">Department</th>
                        <th class="text-left px-4 py-3 font-medium">Position</th>
                        <th class="text-left px-4 py-3 font-medium">Status</th>
                        <th class="text-center px-4 py-3 font-medium">Face ID</th>
                        <th class="text-right px-4 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($employees)): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No employees found</td></tr>
                    <?php else: foreach ($employees as $emp): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium"><?= escapeOutput($emp['employee_no']) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-xs font-bold text-blue-700"><?= strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)) ?></span>
                                </div>
                                <span><?= escapeOutput($emp['first_name'] . ' ' . $emp['last_name']) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3"><?= escapeOutput($emp['department_name'] ?? 'N/A') ?></td>
                        <td class="px-4 py-3"><?= escapeOutput($emp['position'] ?? 'N/A') ?></td>
                        <td class="px-4 py-3"><?= getStatusBadge($emp['employment_status']) ?></td>
                        <td class="px-4 py-3 text-center" id="faceBadge_<?= $emp['id'] ?>">
                            <?php if ($emp['face_count'] > 0): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                    <i class="fas fa-check-circle mr-1"></i>Enrolled (<?= $emp['face_count'] ?>)
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                    <i class="fas fa-minus-circle mr-1"></i>Not Enrolled
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" onclick="openFaceEnrollModal(<?= $emp['id'] ?>, '<?= addslashes(escapeOutput($emp['first_name'] . ' ' . $emp['last_name'])) ?>')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-800 mr-1.5 transition" title="Enroll / Manage Face Recognition">
                                <i class="fas fa-camera text-sm"></i>
                            </button>
                            <a href="<?= APP_URL ?>/index.php?page=employees-view&id=<?= $emp['id'] ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-blue-600 hover:bg-blue-50 mr-1" title="View"><i class="fas fa-eye"></i></a>
                            <a href="<?= APP_URL ?>/index.php?page=employees-edit&id=<?= $emp['id'] ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-yellow-600 hover:bg-yellow-50 mr-1" title="Edit"><i class="fas fa-edit"></i></a>
                            <button type="button" onclick="confirmDelete(<?= $emp['id'] ?>)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-red-600 hover:bg-red-50" title="Delete"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t"><?= paginate($page, $totalPages) ?></div>
        <?php endif; ?>
    </div>
</div>

<form id="deleteForm" method="POST" action="<?= APP_URL ?>/api/employee.php" class="hidden">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<!-- Face Enrollment Modal -->
<div id="faceEnrollModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b flex items-center justify-between bg-gray-50">
            <div>
                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-id-badge text-emerald-600"></i>
                    <span>Face Recognition Enrollment</span>
                </h3>
                <p id="enrollEmployeeName" class="text-xs text-gray-500 font-medium">Employee Name</p>
            </div>
            <button onclick="closeFaceEnrollModal()" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        </div>

        <div class="p-6 overflow-y-auto space-y-4">
            <!-- Video & Canvas container -->
            <div class="relative w-full aspect-video bg-black rounded-xl overflow-hidden border">
                <video id="enrollVideo" class="w-full h-full object-cover" autoplay muted playsinline></video>
                <canvas id="enrollCanvas" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>
            </div>

            <!-- Real-time Status Guide -->
            <div id="enrollStatus" class="text-xs text-center py-2 px-3 bg-gray-50 rounded-lg border font-medium text-gray-600">
                Initializing face detection camera...
            </div>

            <!-- Capture Button -->
            <button id="captureFaceBtn" onclick="captureAndEnrollFace()" disabled class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold rounded-xl text-sm transition shadow flex items-center justify-center gap-2">
                <i class="fas fa-camera"></i>
                <span>Register Face Template</span>
            </button>

            <!-- Existing Templates -->
            <div class="pt-3 border-t">
                <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Registered Face Samples:</h4>
                <div id="existingFacesList" class="space-y-1.5 max-h-36 overflow-y-auto">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>

        <div class="px-6 py-3 border-t bg-gray-50 flex justify-end">
            <button onclick="closeFaceEnrollModal()" class="px-4 py-2 border rounded-lg text-xs font-medium hover:bg-gray-100">
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
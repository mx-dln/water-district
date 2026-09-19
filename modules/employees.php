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
    SELECT e.*, d.name as department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
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
        <a href="<?= APP_URL ?>/index.php?page=employees-create" class="px-4 py-2 bg-blue-700 text-white rounded-lg text-sm hover:bg-blue-800">
            <i class="fas fa-plus mr-2"></i>Add Employee
        </a>
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
                        <th class="text-right px-4 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($employees)): ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No employees found</td></tr>
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
                        <td class="px-4 py-3 text-right">
                            <a href="<?= APP_URL ?>/index.php?page=employees-view&id=<?= $emp['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-2" title="View"><i class="fas fa-eye"></i></a>
                            <a href="<?= APP_URL ?>/index.php?page=employees-edit&id=<?= $emp['id'] ?>" class="text-yellow-600 hover:text-yellow-800 mr-2" title="Edit"><i class="fas fa-edit"></i></a>
                            <button onclick="confirmDelete(<?= $emp['id'] ?>)" class="text-red-600 hover:text-red-800" title="Delete"><i class="fas fa-trash"></i></button>
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

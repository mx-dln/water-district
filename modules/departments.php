<?php
requireRole('Administrator');

$action = $_GET['action'] ?? '';
$actionFile = __DIR__ . '/departments/' . $action . '.php';
if (in_array($action, ['create', 'edit']) && file_exists($actionFile)) {
    require $actionFile;
    return;
}

$depts = $db->query("
    SELECT d.*, (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.is_active = 1) as employee_count
    FROM departments d
    ORDER BY d.name
")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Departments</h1>
        <a href="<?= APP_URL ?>/index.php?page=departments-create" class="px-4 py-2 bg-blue-700 text-white rounded-lg text-sm hover:bg-blue-800"><i class="fas fa-plus mr-2"></i>Add Department</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3 font-medium">Code</th>
                    <th class="text-left px-4 py-3 font-medium">Department Name</th>
                    <th class="text-left px-4 py-3 font-medium">Employees</th>
                    <th class="text-left px-4 py-3 font-medium">Status</th>
                    <th class="text-right px-4 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($depts as $d): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs"><?= escapeOutput($d['code'] ?? '--') ?></td>
                    <td class="px-4 py-3 font-medium"><?= escapeOutput($d['name']) ?></td>
                    <td class="px-4 py-3"><?= $d['employee_count'] ?></td>
                    <td class="px-4 py-3"><?= getStatusBadge($d['is_active'] ? 'active' : 'inactive') ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= APP_URL ?>/index.php?page=departments-edit&id=<?= $d['id'] ?>" class="text-yellow-600 hover:text-yellow-800 mr-2"><i class="fas fa-edit"></i></a>
                        <button onclick="deleteDept(<?= $d['id'] ?>)" class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<form id="deleteDeptForm" method="POST" action="<?= APP_URL ?>/api/department.php" class="hidden">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteDeptId">
</form>
<script>
function deleteDept(id) {
    if (confirm('Delete this department? Employees will be unassigned.')) {
        document.getElementById('deleteDeptId').value = id;
        document.getElementById('deleteDeptForm').submit();
    }
}
</script>

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
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=dashboard" class="hover:text-blue-600">Home</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold">Departments</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">CCWD Departments & Offices</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Organizational units and personnel assignment divisions
            </p>
        </div>
        <div>
            <a href="<?= baseUrl() ?>/index.php?page=departments-create"
               class="inline-flex items-center space-x-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors">
                <i class="fas fa-plus text-xs"></i>
                <span>Add Department</span>
            </a>
        </div>
    </div>

    <!-- Table Card -->
    <div class="app-card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="font-bold text-base text-navy-950 font-heading">Active Divisions</h3>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                <?= count($depts) ?> Departments
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-100/75 text-slate-600 text-[11px] font-bold uppercase tracking-wider font-heading border-b border-slate-200/80">
                    <tr>
                        <th class="px-6 py-3">Code</th>
                        <th class="px-4 py-3">Department Name</th>
                        <th class="px-4 py-3">Assigned Staff</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($depts)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-building text-3xl mb-2 text-slate-300"></i>
                            <p class="text-sm font-medium">No departments registered.</p>
                        </td>
                    </tr>
                    <?php else: foreach ($depts as $d): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-3.5 font-bold font-mono text-slate-700">
                            <span class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded text-xs">
                                <?= escapeOutput($d['code'] ?? '--') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-slate-800 font-heading">
                                <?= escapeOutput($d['name']) ?>
                            </div>
                            <?php if (!empty($d['description'])): ?>
                            <p class="text-[11px] text-slate-400 mt-0.5"><?= escapeOutput($d['description']) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200/50">
                                <i class="fas fa-users text-[10px]"></i>
                                <span><?= $d['employee_count'] ?> personnel</span>
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <?= getStatusBadge($d['is_active'] ? 'active' : 'inactive') ?>
                        </td>
                        <td class="px-6 py-3.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center space-x-1">
                                <a href="<?= baseUrl() ?>/index.php?page=departments-edit&id=<?= $d['id'] ?>"
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-amber-600 hover:bg-amber-50 transition-colors"
                                   title="Edit Department">
                                    <i class="fas fa-pen-to-square text-xs"></i>
                                </a>
                                <button onclick="deleteDept(<?= $d['id'] ?>)" type="button"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors"
                                        title="Delete Department">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<form id="deleteDeptForm" method="POST" action="<?= baseUrl() ?>/api/department.php" class="hidden">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteDeptId">
</form>

<script>
function deleteDept(id) {
    if (confirm('Delete this department? Assigned employees will be unassigned.')) {
        document.getElementById('deleteDeptId').value = id;
        document.getElementById('deleteDeptForm').submit();
    }
}
</script>

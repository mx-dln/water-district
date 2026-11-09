<?php
requireRole('Administrator');
$id = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
$stmt->execute([$id]);
$dept = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$dept) {
    setFlash('Department not found', 'danger');
    redirect(baseUrl() . '/index.php?page=departments');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $name = sanitizeInput($_POST['name'] ?? '');
    if (empty($name)) {
        setFlash('Department name is required', 'danger');
    } else {
        try {
            $stmt = $db->prepare("UPDATE departments SET name=?, code=?, description=? WHERE id=?");
            $stmt->execute([$name, sanitizeInput($_POST['code'] ?? ''), sanitizeInput($_POST['description'] ?? ''), $id]);
            logAudit('update_department', 'departments', "Updated department ID: $id");
            setFlash('Department updated successfully', 'success');
            redirect(baseUrl() . '/index.php?page=departments');
        } catch (Exception $e) {
            setFlash('Department name or code already exists', 'danger');
        }
    }
}
?>

<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=departments" class="hover:text-blue-600">Departments</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold">Edit Unit</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">Edit Department</h1>
        </div>
        <a href="<?= baseUrl() ?>/index.php?page=departments"
           class="inline-flex items-center space-x-2 px-3.5 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold shadow-xs transition-colors">
            <i class="fas fa-arrow-left text-slate-400"></i>
            <span>Back</span>
        </a>
    </div>

    <div class="app-card p-6 sm:p-8">
        <form method="POST" class="space-y-5">
            <?= csrfField() ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Department Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="<?= escapeOutput($dept['name']) ?>" required
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Department Code</label>
                    <input type="text" name="code" value="<?= escapeOutput($dept['code'] ?? '') ?>"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm uppercase font-mono focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Description & Scope</label>
                <textarea name="description" rows="3"
                          class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"><?= escapeOutput($dept['description'] ?? '') ?></textarea>
            </div>
            <div class="pt-4 border-t border-slate-100 flex items-center space-x-3">
                <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors flex items-center space-x-2">
                    <i class="fas fa-save text-xs"></i>
                    <span>Update Department</span>
                </button>
                <a href="<?= baseUrl() ?>/index.php?page=departments"
                   class="px-5 py-2.5 border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-xl text-xs sm:text-sm font-semibold transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

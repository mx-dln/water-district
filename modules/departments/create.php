<?php requireRole('Administrator');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $code = sanitizeInput($_POST['code'] ?? '');
    if (empty($name)) { setFlash('Department name is required', 'danger'); }
    else {
        try {
            $stmt = $db->prepare("INSERT INTO departments (name, code, description) VALUES (?, ?, ?)");
            $stmt->execute([$name, $code, sanitizeInput($_POST['description'] ?? '')]);
            logAudit('create_department', 'departments', "Created department: $name");
            setFlash('Department created successfully', 'success');
            redirect(APP_URL . '/index.php?page=departments');
        } catch (Exception $e) { setFlash('Department name already exists', 'danger'); }
    }
}
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Add Department</h1>
        <a href="<?= APP_URL ?>/index.php?page=departments" class="text-sm text-gray-600 hover:text-gray-800"><i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                    <input type="text" name="code" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="e.g. HR, IT, FIN">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex space-x-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-700 text-white rounded-lg text-sm font-medium hover:bg-blue-800"><i class="fas fa-save mr-2"></i>Save</button>
                <a href="<?= APP_URL ?>/index.php?page=departments" class="px-6 py-2.5 border rounded-lg text-sm hover:bg-gray-50">Cancel</a>
            </div>
        </form>
    </div>
</div>

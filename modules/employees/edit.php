<?php
requireRole('Administrator');
$id = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT e.*, u.username, u.email as user_email FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch();
if (!$emp) { setFlash('Employee not found', 'danger'); redirect(APP_URL . '/index.php?page=employees'); }

$depts = $db->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    if (empty($firstName) || empty($lastName)) {
        $error = 'First name and last name are required';
    } else {
        try {
            $stmt = $db->prepare("UPDATE employees SET department_id=?, first_name=?, last_name=?, middle_name=?, position=?, contact_no=?, address=?, employment_status=? WHERE id=?");
            $stmt->execute([
                intval($_POST['department_id'] ?? 0) ?: null,
                $firstName,
                $lastName,
                sanitizeInput($_POST['middle_name'] ?? ''),
                sanitizeInput($_POST['position'] ?? ''),
                sanitizeInput($_POST['contact_no'] ?? ''),
                sanitizeInput($_POST['address'] ?? ''),
                sanitizeInput($_POST['employment_status'] ?? 'regular'),
                $id
            ]);
            $db->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([sanitizeInput($_POST['email'] ?? ''), $emp['user_id']]);
            logAudit('update_employee', 'employees', "Updated employee ID: $id");
            setFlash('Employee updated successfully', 'success');
            redirect(APP_URL . '/index.php?page=employees');
        } catch (Exception $e) {
            $error = 'Error updating employee: ' . $e->getMessage();
        }
    }
}
?>
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Edit Employee</h1>
        <a href="<?= APP_URL ?>/index.php?page=employees" class="text-sm text-gray-600 hover:text-gray-800"><i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <?php if ($error): ?><div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm"><?= escapeOutput($error) ?></div><?php endif; ?>
        <form method="POST" class="space-y-6">
            <?= csrfField() ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employee No</label>
                    <input type="text" value="<?= escapeOutput($emp['employee_no']) ?>" disabled class="w-full px-3 py-2 border rounded-lg text-sm bg-gray-50">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" value="<?= escapeOutput($emp['username']) ?>" disabled class="w-full px-3 py-2 border rounded-lg text-sm bg-gray-50">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= escapeOutput($emp['user_email']) ?>" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="<?= escapeOutput($emp['first_name']) ?>" required class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                    <input type="text" name="middle_name" value="<?= escapeOutput($emp['middle_name'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" value="<?= escapeOutput($emp['last_name']) ?>" required class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <select name="department_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="">Select Department</option>
                        <?php foreach ($depts as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $emp['department_id'] == $d['id'] ? 'selected' : '' ?>><?= escapeOutput($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                    <input type="text" name="position" value="<?= escapeOutput($emp['position'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                    <input type="text" name="contact_no" value="<?= escapeOutput($emp['contact_no'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employment Status</label>
                    <select name="employment_status" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="regular" <?= $emp['employment_status'] === 'regular' ? 'selected' : '' ?>>Regular</option>
                        <option value="probationary" <?= $emp['employment_status'] === 'probationary' ? 'selected' : '' ?>>Probationary</option>
                        <option value="contractual" <?= $emp['employment_status'] === 'contractual' ? 'selected' : '' ?>>Contractual</option>
                        <option value="part-time" <?= $emp['employment_status'] === 'part-time' ? 'selected' : '' ?>>Part-Time</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea name="address" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"><?= escapeOutput($emp['address'] ?? '') ?></textarea>
            </div>
            <div class="flex items-center space-x-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-700 text-white rounded-lg text-sm font-medium hover:bg-blue-800"><i class="fas fa-save mr-2"></i>Update Employee</button>
                <a href="<?= APP_URL ?>/index.php?page=employees" class="px-6 py-2.5 border rounded-lg text-sm hover:bg-gray-50">Cancel</a>
            </div>
        </form>
    </div>
</div>

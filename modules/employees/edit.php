<?php
requireRole('Administrator');
$id = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT e.*, u.username, u.email as user_email FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$emp) {
    setFlash('Employee not found', 'danger');
    redirect(baseUrl() . '/index.php?page=employees');
}

$depts = $db->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

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
            setFlash('Employee record updated successfully', 'success');
            redirect(baseUrl() . '/index.php?page=employees');
        } catch (Exception $e) {
            $error = 'Error updating employee: ' . $e->getMessage();
        }
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=employees" class="hover:text-blue-600">Employees</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold">Edit Record</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">Edit Employee Profile</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Updating details for <strong class="text-slate-800"><?= escapeOutput($emp['first_name'] . ' ' . $emp['last_name']) ?></strong> (<?= escapeOutput($emp['employee_no']) ?>)
            </p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="<?= baseUrl() ?>/index.php?page=employees-view&id=<?= $id ?>"
               class="inline-flex items-center space-x-2 px-3.5 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold shadow-xs transition-colors">
                <i class="fas fa-eye text-blue-600"></i>
                <span>View Profile</span>
            </a>
            <a href="<?= baseUrl() ?>/index.php?page=employees"
               class="inline-flex items-center space-x-2 px-3.5 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold shadow-xs transition-colors">
                <i class="fas fa-arrow-left text-slate-400"></i>
                <span>Back</span>
            </a>
        </div>
    </div>

    <div class="app-card p-6 sm:p-8">
        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl flex items-center space-x-3 text-xs sm:text-sm">
            <i class="fas fa-circle-exclamation text-base text-rose-600 shrink-0"></i>
            <span><?= escapeOutput($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <?= csrfField() ?>

            <!-- Section 1: Identification (Read only) -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-4 pb-2 border-b border-slate-100 flex items-center space-x-2">
                    <i class="fas fa-id-card text-blue-600"></i>
                    <span>System Identification & Login</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Employee No</label>
                        <input type="text" value="<?= escapeOutput($emp['employee_no']) ?>" disabled
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-100/80 text-slate-500 text-xs sm:text-sm font-mono cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Username</label>
                        <input type="text" value="<?= escapeOutput($emp['username']) ?>" disabled
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-100/80 text-slate-500 text-xs sm:text-sm cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Account Email</label>
                        <input type="email" name="email" value="<?= escapeOutput($emp['user_email']) ?>"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                </div>
            </div>

            <!-- Section 2: Personal Details -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-4 pb-2 border-b border-slate-100 flex items-center space-x-2">
                    <i class="fas fa-user-edit text-blue-600"></i>
                    <span>Personal Details</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">First Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="first_name" value="<?= escapeOutput($emp['first_name']) ?>" required
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Middle Name</label>
                        <input type="text" name="middle_name" value="<?= escapeOutput($emp['middle_name'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Last Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="last_name" value="<?= escapeOutput($emp['last_name']) ?>" required
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                </div>
            </div>

            <!-- Section 3: Designation & Employment -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-4 pb-2 border-b border-slate-100 flex items-center space-x-2">
                    <i class="fas fa-building text-blue-600"></i>
                    <span>Department & Status</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Department</label>
                        <select name="department_id"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                            <option value="">Select Department</option>
                            <?php foreach ($depts as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $emp['department_id'] == $d['id'] ? 'selected' : '' ?>><?= escapeOutput($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Official Position</label>
                        <input type="text" name="position" value="<?= escapeOutput($emp['position'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Contact Number</label>
                        <input type="text" name="contact_no" value="<?= escapeOutput($emp['contact_no'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Employment Status</label>
                        <select name="employment_status"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                            <option value="regular" <?= $emp['employment_status'] === 'regular' ? 'selected' : '' ?>>Regular</option>
                            <option value="probationary" <?= $emp['employment_status'] === 'probationary' ? 'selected' : '' ?>>Probationary</option>
                            <option value="contractual" <?= $emp['employment_status'] === 'contractual' ? 'selected' : '' ?>>Contractual</option>
                            <option value="part-time" <?= $emp['employment_status'] === 'part-time' ? 'selected' : '' ?>>Part-Time</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Residential Address</label>
                    <textarea name="address" rows="2"
                              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"><?= escapeOutput($emp['address'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Buttons -->
            <div class="pt-4 border-t border-slate-100 flex items-center space-x-3">
                <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors flex items-center space-x-2">
                    <i class="fas fa-save text-xs"></i>
                    <span>Save Changes</span>
                </button>
                <a href="<?= baseUrl() ?>/index.php?page=employees"
                   class="px-5 py-2.5 border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-xl text-xs sm:text-sm font-semibold transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

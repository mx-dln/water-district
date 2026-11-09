<?php
requireLogin();
$employeeId = getEmployeeIdFromUser($_SESSION['user_id']);

if (isAdmin() && isset($_GET['id'])) {
    $empId = intval($_GET['id']);
} else {
    $empId = $employeeId;
}

$stmt = $db->prepare("
    SELECT e.*, u.username, u.email as user_email, d.name as department_name
    FROM employees e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.id = ?
");
$stmt->execute([$empId]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    setFlash('Employee profile not found', 'danger');
    redirect(baseUrl() . '/index.php?page=dashboard');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $contactNo = sanitizeInput($_POST['contact_no'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');

    $stmt = $db->prepare("UPDATE employees SET contact_no = ?, address = ? WHERE id = ?");
    $stmt->execute([$contactNo, $address, $empId]);

    $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
    $stmt->execute([$email, $emp['user_id']]);

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
            setFlash('Profile picture upload failed (code: ' . $_FILES['profile_pic']['error'] . ')', 'danger');
            redirect(baseUrl() . '/index.php?page=profile');
        }
        $val = validateImageFile($_FILES['profile_pic']);
        if (!$val['valid']) {
            setFlash('Profile photo error: ' . $val['error'], 'danger');
            redirect(baseUrl() . '/index.php?page=profile');
        }
        $filename = 'profile_' . $empId . '_' . time() . '.' . $val['ext'];
        $uploadDir = __DIR__ . '/../uploads/attendance/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $uploadDir . $filename)) {
            compressImage($uploadDir . $filename, 800, 800, 70);
            $stmt = $db->prepare("UPDATE employees SET profile_pic = ? WHERE id = ?");
            $stmt->execute(['uploads/attendance/' . $filename, $empId]);
        } else {
            setFlash('Failed to store avatar to uploads folder', 'danger');
            redirect(baseUrl() . '/index.php?page=profile');
        }
    }

    setFlash('Profile updated successfully', 'success');
    redirect(baseUrl() . '/index.php?page=profile');
}

$initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
?>

<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=dashboard" class="hover:text-blue-600">Home</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold">Account Profile</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">Personal Profile</h1>
        </div>
        <a href="<?= baseUrl() ?>/index.php?page=change-password"
           class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition-colors">
            <i class="fas fa-key text-blue-600"></i>
            <span>Update Password</span>
        </a>
    </div>

    <div class="app-card p-6 sm:p-8">
        <!-- Avatar & Summary Banner -->
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 pb-6 border-b border-slate-100">
            <div class="relative shrink-0">
                <div class="w-20 h-20 rounded-xl bg-blue-700 text-white flex items-center justify-center shadow-sm overflow-hidden text-2xl font-bold font-heading">
                    <?php if (!empty($emp['profile_pic']) && file_exists(__DIR__ . '/../' . $emp['profile_pic'])): ?>
                    <img src="<?= baseUrl('/' . escapeOutput($emp['profile_pic'])) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                    <?= $initials ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-center sm:text-left">
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200/60">
                        <?= escapeOutput($emp['department_name'] ?? 'Staff') ?>
                    </span>
                    <span class="text-xs font-mono font-bold text-slate-500">ID: <?= escapeOutput($emp['employee_no']) ?></span>
                </div>
                <h2 class="text-xl font-extrabold text-navy-950 font-heading">
                    <?= escapeOutput($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5"><?= escapeOutput($emp['position'] ?? 'Employee') ?> &bull; Cauayan City Water District</p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-5 pt-6">
            <?= csrfField() ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Username</label>
                    <input type="text" value="<?= escapeOutput($emp['username']) ?>" disabled
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-100/80 text-slate-500 text-xs sm:text-sm font-mono cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Email Address</label>
                    <input type="email" name="email" value="<?= escapeOutput($emp['user_email'] ?? '') ?>"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Contact Number</label>
                    <input type="text" name="contact_no" value="<?= escapeOutput($emp['contact_no'] ?? '') ?>"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Update Photo</label>
                    <input type="file" name="profile_pic" accept="image/*"
                           class="w-full text-xs text-slate-600 file:mr-4 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Residential Address</label>
                <textarea name="address" rows="2"
                          class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"><?= escapeOutput($emp['address'] ?? '') ?></textarea>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center space-x-3">
                <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors flex items-center space-x-2">
                    <i class="fas fa-save text-xs"></i>
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>
</div>

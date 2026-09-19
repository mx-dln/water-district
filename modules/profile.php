<?php
requireLogin();
$employeeId = getEmployeeIdFromUser($_SESSION['user_id']);

if (isAdmin() && isset($_GET['id'])) {
    $empId = intval($_GET['id']);
} else {
    $empId = $employeeId;
}

$stmt = $db->prepare("
    SELECT e.*, u.username, u.email as user_email
    FROM employees e
    JOIN users u ON e.user_id = u.id
    WHERE e.id = ?
");
$stmt->execute([$empId]);
$emp = $stmt->fetch();

if (!$emp) {
    setFlash('Profile not found', 'danger');
    redirect(APP_URL . '/index.php?page=dashboard');
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
            setFlash('Profile picture upload failed (error code: ' . $_FILES['profile_pic']['error'] . ')', 'danger');
            redirect(APP_URL . '/index.php?page=profile');
        }
        $val = validateImageFile($_FILES['profile_pic']);
        if (!$val['valid']) {
            setFlash('Profile picture: ' . $val['error'], 'danger');
            redirect(APP_URL . '/index.php?page=profile');
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
            setFlash('Failed to save profile picture. Check directory permissions.', 'danger');
            redirect(APP_URL . '/index.php?page=profile');
        }
    }

    setFlash('Profile updated successfully', 'success');
    redirect(APP_URL . '/index.php?page=profile');
}
?>
<div class="max-w-2xl mx-auto">
    <h1 class="text-xl sm:text-2xl font-bold text-gray-800 mb-4 sm:mb-6">My Profile</h1>
    <div class="bg-white rounded-xl shadow-sm border p-4 sm:p-6">
        <div class="flex items-center space-x-4 mb-6">
            <div class="w-16 h-16 rounded-full flex items-center justify-center overflow-hidden <?= $emp['profile_pic'] ? '' : 'bg-blue-100' ?>">
                <?php if ($emp['profile_pic'] && file_exists(__DIR__ . '/../' . $emp['profile_pic'])): ?>
                <img src="/<?= $emp['profile_pic'] ?>" class="w-full h-full object-cover">
                <?php else: ?>
                <span class="text-xl font-bold text-blue-700"><?= strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)) ?></span>
                <?php endif; ?>
            </div>
            <div>
                <h2 class="text-lg font-semibold"><?= escapeOutput($emp['first_name'] . ' ' . $emp['last_name']) ?></h2>
                <p class="text-sm text-gray-500"><?= escapeOutput($emp['employee_no']) ?> | <?= escapeOutput($emp['position'] ?? 'N/A') ?></p>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <?= csrfField() ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" value="<?= escapeOutput($emp['username']) ?>" disabled class="w-full px-3 py-2 border rounded-lg text-sm bg-gray-50">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= escapeOutput($emp['user_email'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                    <input type="text" name="contact_no" value="<?= escapeOutput($emp['contact_no'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Profile Picture</label>
                    <input type="file" name="profile_pic" accept="image/*" class="w-full text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea name="address" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"><?= escapeOutput($emp['address'] ?? '') ?></textarea>
            </div>
            <div class="flex space-x-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-700 text-white rounded-lg text-sm font-medium hover:bg-blue-800"><i class="fas fa-save mr-2"></i>Save Changes</button>
                <a href="<?= APP_URL ?>/index.php?page=change-password" class="px-6 py-2.5 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-key mr-2"></i>Change Password</a>
            </div>
        </form>
    </div>
</div>

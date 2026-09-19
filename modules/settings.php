<?php
requireRole('Administrator');

$logoSettingKey = 'company_logo';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $settings = $_POST['setting'] ?? [];
    foreach ($settings as $key => $value) {
        if ($key === $logoSettingKey) continue; // handled separately
        updateSetting(sanitizeInput($key), sanitizeInput($value));
    }

    // Handle logo upload
    $currentLogo = getSetting($logoSettingKey, '');
    $newLogo = $currentLogo;
    $errors = [];

    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $validation = validateUploadedFile($_FILES['company_logo']);
        if ($validation['valid']) {
            $ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowedExt)) {
                $uploadDir = __DIR__ . '/../uploads/logos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $filename = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $targetPath)) {
                    // Delete old logo file if it exists
                    if ($currentLogo) {
                        $oldPath = __DIR__ . '/../' . $currentLogo;
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    $newLogo = 'uploads/logos/' . $filename;
                } else {
                    $errors[] = 'Failed to upload logo';
                }
            } else {
                $errors[] = 'Logo must be an image file (JPG, PNG, GIF, WEBP)';
            }
        } else {
            $errors[] = $validation['error'];
        }
    }

    updateSetting($logoSettingKey, $newLogo);

    if (empty($errors)) {
        logAudit('update_settings', 'settings', 'System settings updated');
        setFlash('Settings updated successfully', 'success');
    } else {
        setFlash(implode('<br>', $errors), 'danger');
    }
    redirect(APP_URL . '/index.php?page=settings');
}

$allSettings = $db->query("SELECT * FROM settings ORDER BY key_name")->fetchAll();
$currentLogo = getSetting($logoSettingKey, '');
?>
<div class="max-w-3xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">System Settings</h1>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <?= csrfField() ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($allSettings as $s): ?>
                <?php if ($s['key_name'] === $logoSettingKey) continue; ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= escapeOutput(ucwords(str_replace('_', ' ', $s['key_name']))) ?></label>
                    <?php if (in_array($s['key_name'], ['company_address', 'description'])): ?>
                    <textarea name="setting[<?= escapeOutput($s['key_name']) ?>]" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"><?= escapeOutput($s['value'] ?? '') ?></textarea>
                    <?php else: ?>
                    <input type="text" name="setting[<?= escapeOutput($s['key_name']) ?>]" value="<?= escapeOutput($s['value'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <?php endif; ?>
                    <?php if ($s['description']): ?>
                    <p class="text-xs text-gray-400 mt-1"><?= escapeOutput($s['description']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Agency Logo</label>
                <div class="flex items-start gap-4">
                    <div class="w-24 h-24 border rounded-lg flex items-center justify-center bg-gray-50 overflow-hidden">
                        <?php if ($currentLogo): ?>
                        <img src="<?= APP_URL ?>/<?= escapeOutput($currentLogo) ?>" alt="Agency Logo" class="max-w-full max-h-full object-contain">
                        <?php else: ?>
                        <span class="text-xs text-gray-400 text-center">No logo</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <input type="file" name="company_logo" accept="image/*" class="w-full text-sm">
                        <p class="text-xs text-gray-500 mt-1">Upload a square logo image for CS Form 6 and other official prints. JPG, PNG, WEBP (max 5MB).</p>
                    </div>
                </div>
            </div>

            <button type="submit" class="px-6 py-2.5 bg-blue-700 text-white rounded-lg text-sm font-medium hover:bg-blue-800"><i class="fas fa-save mr-2"></i>Save Settings</button>
        </form>
    </div>
</div>

<?php
requireRole('Administrator');

$logoSettingKey = 'company_logo';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $settings = $_POST['setting'] ?? [];
    foreach ($settings as $key => $value) {
        if ($key === $logoSettingKey) continue;
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
                    if ($currentLogo) {
                        $oldPath = __DIR__ . '/../' . $currentLogo;
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    $newLogo = 'uploads/logos/' . $filename;
                } else {
                    $errors[] = 'Failed to save logo file to server destination';
                }
            } else {
                $errors[] = 'Logo must be an image format (JPG, PNG, WEBP)';
            }
        } else {
            $errors[] = $validation['error'];
        }
    }

    updateSetting($logoSettingKey, $newLogo);

    if (empty($errors)) {
        logAudit('update_settings', 'settings', 'System settings updated');
        setFlash('System settings updated successfully', 'success');
    } else {
        setFlash(implode('<br>', $errors), 'danger');
    }
    redirect(baseUrl() . '/index.php?page=settings');
}

$allSettings = $db->query("SELECT * FROM settings ORDER BY key_name")->fetchAll(PDO::FETCH_ASSOC);
$currentLogo = getSetting($logoSettingKey, '');
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=dashboard" class="hover:text-blue-600">Home</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold">Settings</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">System & Agency Configuration</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Configure Cauayan City Water District parameters, official letterhead, and attendance rules
            </p>
        </div>
        <div>
            <span class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-blue-50 text-blue-800 border border-blue-200/60 text-xs font-bold">
                <i class="fas fa-shield-alt text-blue-600"></i>
                <span>Admin Root Parameters</span>
            </span>
        </div>
    </div>

    <div class="app-card p-6 sm:p-8">
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <?= csrfField() ?>

            <!-- Agency Logo Upload Section -->
            <div class="pb-6 border-b border-slate-100">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-4 flex items-center space-x-2">
                    <i class="fas fa-image text-blue-600"></i>
                    <span>Official Agency Seal / Logo</span>
                </h3>
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5">
                    <div class="w-24 h-24 rounded-2xl border-2 border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden shrink-0 shadow-inner p-1">
                        <?php if ($currentLogo && file_exists(__DIR__ . '/../' . $currentLogo)): ?>
                        <img src="<?= baseUrl('/' . escapeOutput($currentLogo)) ?>" alt="Agency Seal" class="max-w-full max-h-full object-contain">
                        <?php else: ?>
                        <img src="<?= baseUrl('/assets/images/logo.png') ?>" alt="Default Logo" class="max-w-full max-h-full object-contain">
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 w-full space-y-2">
                        <label class="block text-xs font-bold text-slate-700 font-heading">Upload New Agency Seal</label>
                        <input type="file" name="company_logo" accept="image/*"
                               class="w-full text-xs text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        <p class="text-[11px] text-slate-400">
                            Square image recommended (min. 256x256). Used on CSC Form 6, Printable Time Cards, and system navigation bar.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Parameters Grid -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-4 flex items-center space-x-2">
                    <i class="fas fa-sliders text-blue-600"></i>
                    <span>System Parameters & Rules</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($allSettings as $s): ?>
                    <?php if ($s['key_name'] === $logoSettingKey) continue; ?>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">
                            <?= escapeOutput(ucwords(str_replace('_', ' ', $s['key_name']))) ?>
                        </label>
                        <?php if (in_array($s['key_name'], ['company_address', 'description'])): ?>
                        <textarea name="setting[<?= escapeOutput($s['key_name']) ?>]" rows="2"
                                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"><?= escapeOutput($s['value'] ?? '') ?></textarea>
                        <?php else: ?>
                        <input type="text" name="setting[<?= escapeOutput($s['key_name']) ?>]" value="<?= escapeOutput($s['value'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                        <?php endif; ?>
                        <?php if ($s['description']): ?>
                        <p class="text-[11px] text-slate-400 mt-1"><?= escapeOutput($s['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center space-x-3">
                <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors flex items-center space-x-2">
                    <i class="fas fa-save text-xs"></i>
                    <span>Save Configuration</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
requireRole('Administrator');

$depts = $db->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $firstName = sanitizeInput($_POST['first_name'] ?? '');
        $lastName = sanitizeInput($_POST['last_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($firstName) || empty($lastName) || empty($username) || empty($password)) {
            $error = 'Required fields are missing';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } else {
            try {
                $db->beginTransaction();
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO users (role_id, username, email, password) VALUES (2, ?, ?, ?)");
                $stmt->execute([$username, $email, $hash]);
                $userId = $db->lastInsertId();

                $empNo = sanitizeInput($_POST['employee_no'] ?? '');
                if (empty($empNo)) {
                    $empNo = 'EMP-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                }

                $stmt = $db->prepare("INSERT INTO employees (user_id, department_id, employee_no, first_name, last_name, middle_name, position, contact_no, email, address, employment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId,
                    intval($_POST['department_id'] ?? 0) ?: null,
                    $empNo,
                    $firstName,
                    $lastName,
                    sanitizeInput($_POST['middle_name'] ?? ''),
                    sanitizeInput($_POST['position'] ?? ''),
                    sanitizeInput($_POST['contact_no'] ?? ''),
                    $email,
                    sanitizeInput($_POST['address'] ?? ''),
                    sanitizeInput($_POST['employment_status'] ?? 'regular')
                ]);
                $db->commit();
                logAudit('create_employee', 'employees', "Created employee: $empNo - $firstName $lastName");
                setFlash('Employee created successfully! Account username: ' . $username, 'success');
                redirect(baseUrl() . '/index.php?page=employees');
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Error creating employee: ' . ($e->getCode() == 23000 ? 'Username or employee number already exists' : $e->getMessage());
            }
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
                <span class="text-slate-700 font-semibold">New Record</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">Register New Employee</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Set up employee profile credentials and CCWD departmental details
            </p>
        </div>
        <a href="<?= baseUrl() ?>/index.php?page=employees"
           class="inline-flex items-center space-x-2 px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold shadow-xs transition-colors">
            <i class="fas fa-arrow-left text-slate-400"></i>
            <span>Back to Directory</span>
        </a>
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

            <!-- Section 1: System Credentials -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-4 pb-2 border-b border-slate-100 flex items-center space-x-2">
                    <i class="fas fa-key text-blue-600"></i>
                    <span>Account Credentials & Identification</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Employee No</label>
                        <input type="text" name="employee_no" placeholder="Auto-generated if blank"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm font-mono focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Username <span class="text-rose-500">*</span></label>
                        <input type="text" name="username" required placeholder="e.g. jdelacruz"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Password <span class="text-rose-500">*</span></label>
                        <input type="password" name="password" required minlength="8" placeholder="Minimum 8 characters"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                </div>
            </div>

            <!-- Section 2: Personal Details -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-4 pb-2 border-b border-slate-100 flex items-center space-x-2">
                    <i class="fas fa-user text-blue-600"></i>
                    <span>Personal Information</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">First Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="first_name" required
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Middle Name</label>
                        <input type="text" name="middle_name"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Last Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="last_name" required
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                </div>
            </div>

            <!-- Section 3: Official Position & Contact -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-4 pb-2 border-b border-slate-100 flex items-center space-x-2">
                    <i class="fas fa-briefcase text-blue-600"></i>
                    <span>Assignment & Designation</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Department</label>
                        <select name="department_id"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                            <option value="">Select Department</option>
                            <?php foreach ($depts as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= escapeOutput($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Official Position Title</label>
                        <input type="text" name="position" placeholder="e.g. Administrative Officer II"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Email Address</label>
                        <input type="email" name="email" placeholder="official@water.district.ph"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Contact Number</label>
                        <input type="text" name="contact_no" placeholder="09xxxxxxxxx"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Employment Status</label>
                        <select name="employment_status"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                            <option value="regular">Regular</option>
                            <option value="probationary">Probationary</option>
                            <option value="contractual">Contractual</option>
                            <option value="part-time">Part-Time</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Residential Address</label>
                    <textarea name="address" rows="2" placeholder="Barangay, City / Municipality, Province"
                              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></textarea>
                </div>
            </div>

            <!-- Submit Button Bar -->
            <div class="pt-4 border-t border-slate-100 flex items-center space-x-3">
                <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors flex items-center space-x-2">
                    <i class="fas fa-check text-xs"></i>
                    <span>Register Employee</span>
                </button>
                <a href="<?= baseUrl() ?>/index.php?page=employees"
                   class="px-5 py-2.5 border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-xl text-xs sm:text-sm font-semibold transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

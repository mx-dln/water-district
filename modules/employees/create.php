<?php
requireRole('Administrator');

$depts = $db->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll();

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
                setFlash('Employee created successfully! Login credentials: Username: ' . $username, 'success');
                redirect(APP_URL . '/index.php?page=employees');
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Error creating employee: ' . ($e->getCode() == 23000 ? 'Username or employee number already exists' : $e->getMessage());
            }
        }
    }
}
?>
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Add Employee</h1>
            <p class="text-sm text-gray-500">Create a new employee account</p>
        </div>
        <a href="<?= APP_URL ?>/index.php?page=employees" class="text-sm text-gray-600 hover:text-gray-800"><i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <?php if ($error): ?><div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm"><?= escapeOutput($error) ?></div><?php endif; ?>
        <form method="POST" class="space-y-6">
            <?= csrfField() ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employee No <span class="text-red-500">*</span></label>
                    <input type="text" name="employee_no" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="Auto-generated if empty">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="Login username">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required minlength="8" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="Min. 8 characters">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                    <input type="text" name="middle_name" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <select name="department_id" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Department</option>
                        <?php foreach ($depts as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= escapeOutput($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                    <input type="text" name="position" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                    <input type="text" name="contact_no" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employment Status</label>
                <select name="employment_status" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="regular">Regular</option>
                    <option value="probationary">Probationary</option>
                    <option value="contractual">Contractual</option>
                    <option value="part-time">Part-Time</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea name="address" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex items-center space-x-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-700 text-white rounded-lg text-sm font-medium hover:bg-blue-800"><i class="fas fa-save mr-2"></i>Save Employee</button>
                <a href="<?= APP_URL ?>/index.php?page=employees" class="px-6 py-2.5 border rounded-lg text-sm hover:bg-gray-50">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
requireLogin();
$employeeId = getEmployeeIdFromUser($_SESSION['user_id']);
if (!$employeeId) { setFlash('Employee profile not found', 'danger'); redirect(APP_URL . '/index.php?page=dashboard'); }

$leaveTypes = $db->query("SELECT * FROM leave_types WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

$typeCodeById = [];
foreach ($leaveTypes as $lt) {
    $typeCodeById[$lt['id']] = strtoupper($lt['code'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $leaveTypeId = intval($_POST['leave_type_id'] ?? 0);
    $dateFrom = $_POST['date_from'] ?? '';
    $dateTo = $_POST['date_to'] ?? '';
    $reason = sanitizeInput($_POST['reason'] ?? '');

    $errors = [];
    if (!$leaveTypeId) $errors[] = 'Select leave type';
    if (!$dateFrom) $errors[] = 'Start date required';
    if (!$dateTo) $errors[] = 'End date required';
    if (!$reason) $errors[] = 'Reason required';
    if ($dateFrom && $dateTo && $dateFrom > $dateTo) $errors[] = 'End date must be after start date';

    $days = 0;
    if ($dateFrom && $dateTo) {
        $start = new DateTime($dateFrom);
        $end = new DateTime($dateTo);
        $days = $start->diff($end)->days + 1;
    }
    $selectedCode = $typeCodeById[$leaveTypeId] ?? '';

    $details = [
        'salary' => sanitizeInput($_POST['salary'] ?? ''),
        'vacation_within' => isset($_POST['vacation_within']),
        'vacation_within_specify' => sanitizeInput($_POST['vacation_within_specify'] ?? ''),
        'vacation_abroad' => isset($_POST['vacation_abroad']),
        'vacation_abroad_specify' => sanitizeInput($_POST['vacation_abroad_specify'] ?? ''),
        'sick_hospital' => isset($_POST['sick_hospital']),
        'sick_hospital_specify' => sanitizeInput($_POST['sick_hospital_specify'] ?? ''),
        'sick_outpatient' => isset($_POST['sick_outpatient']),
        'sick_outpatient_specify' => sanitizeInput($_POST['sick_outpatient_specify'] ?? ''),
        'special_women_illness' => sanitizeInput($_POST['special_women_illness'] ?? ''),
        'study_masters' => isset($_POST['study_masters']),
        'study_bar' => isset($_POST['study_bar']),
        'purpose_monetization' => isset($_POST['purpose_monetization']),
        'purpose_terminal' => isset($_POST['purpose_terminal']),
        'others_specify' => sanitizeInput($_POST['others_specify'] ?? ''),
        'commutation' => ($_POST['commutation'] ?? 'not_requested') === 'requested' ? 'requested' : 'not_requested',
    ];

    if (empty($errors)) {
        $attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $validation = validateUploadedFile($_FILES['attachment']);
            if ($validation['valid']) {
                $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                $filename = 'leave_' . $employeeId . '_' . time() . '.' . $ext;
                $uploadDir = __DIR__ . '/../../uploads/attendance/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $filename);
                $attachment = 'uploads/attendance/' . $filename;
            } else {
                $errors[] = $validation['error'];
            }
        }
        if ($selectedCode === 'SL' && $days > 5 && !$attachment) {
            $errors[] = 'Medical certificate is required for sick leave in excess of five successive days under CSC rules.';
        }

        if (empty($errors)) {
            $stmt = $db->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, date_from, date_to, days, reason, details, attachment) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$employeeId, $leaveTypeId, $dateFrom, $dateTo, $days, $reason, json_encode($details), $attachment]);
            logAudit('submit_leave', 'leaves', "Leave request submitted for $days days");

            $admins = $db->query("SELECT id FROM users WHERE role_id = 1")->fetchAll();
            foreach ($admins as $admin) {
                createNotification($admin['id'], 'New Leave Request', 'A new leave request has been submitted', 'info', APP_URL . '/index.php?page=leaves');
            }

            setFlash('Leave request submitted successfully', 'success');
            redirect(APP_URL . '/index.php?page=leaves');
        }
    }
    if (!empty($errors)) {
        setFlash(implode('<br>', $errors), 'danger');
    }
}
?>
<div class="max-w-3xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Request Leave</h1>
        <div class="flex gap-3">
            <a href="<?= APP_URL ?>/index.php?page=leaves" class="text-sm text-gray-600 hover:text-gray-800 self-start sm:self-auto"><i class="fas fa-arrow-left mr-1"></i>Back</a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" enctype="multipart/form-data" class="space-y-6" id="leaveRequestForm">
            <?= csrfField() ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Leave Type <span class="text-red-500">*</span></label>
                    <select name="leave_type_id" id="leaveType" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="" data-code="">Select leave type</option>
                        <?php foreach ($leaveTypes as $lt): ?>
                        <option value="<?= $lt['id'] ?>" data-code="<?= escapeOutput(strtoupper($lt['code'] ?? '')) ?>"><?= escapeOutput($lt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Salary</label>
                    <input type="text" name="salary" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="e.g. PHP 25,000.00">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date <span class="text-red-500">*</span></label>
                    <input type="date" name="date_from" id="dateFrom" required class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date <span class="text-red-500">*</span></label>
                    <input type="date" name="date_to" id="dateTo" required class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Number of Days</label>
                    <input type="number" name="days" id="leaveDays" readonly class="w-full px-3 py-2 border rounded-lg text-sm bg-gray-50" value="0">
                </div>
            </div>

            <div id="vacationDetails" class="hidden border rounded-lg p-4 bg-gray-50 space-y-3">
                <h3 class="text-sm font-semibold text-gray-800">In case of Vacation / Special Privilege Leave</h3>
                <div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="vacation_within" value="1" class="w-4 h-4 border-gray-300 rounded">
                        Within the Philippines
                    </label>
                    <input type="text" name="vacation_within_specify" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm" placeholder="Specify place (optional)">
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="vacation_abroad" value="1" class="w-4 h-4 border-gray-300 rounded">
                        Abroad
                    </label>
                    <input type="text" name="vacation_abroad_specify" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm" placeholder="Specify country (optional)">
                </div>
            </div>

            <div id="sickDetails" class="hidden border rounded-lg p-4 bg-gray-50 space-y-3">
                <h3 class="text-sm font-semibold text-gray-800">In case of Sick Leave</h3>
                <div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="sick_hospital" value="1" class="w-4 h-4 border-gray-300 rounded">
                        In Hospital
                    </label>
                    <input type="text" name="sick_hospital_specify" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm" placeholder="Specify illness (optional)">
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="sick_outpatient" value="1" class="w-4 h-4 border-gray-300 rounded">
                        Out Patient
                    </label>
                    <input type="text" name="sick_outpatient_specify" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm" placeholder="Specify illness (optional)">
                </div>
            </div>

            <div id="specialWomenDetails" class="hidden border rounded-lg p-4 bg-gray-50 space-y-3">
                <h3 class="text-sm font-semibold text-gray-800">Special Leave Benefits for Women</h3>
                <label class="block text-sm text-gray-700">Specify Illness</label>
                <input type="text" name="special_women_illness" class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>

            <div id="studyDetails" class="hidden border rounded-lg p-4 bg-gray-50 space-y-3">
                <h3 class="text-sm font-semibold text-gray-800">In case of Study Leave</h3>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="study_masters" value="1" class="w-4 h-4 border-gray-300 rounded">
                    Completion of Master's Degree
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="study_bar" value="1" class="w-4 h-4 border-gray-300 rounded">
                    BAR / Board Examination Review
                </label>
            </div>

            <div id="otherPurpose" class="border rounded-lg p-4 bg-gray-50 space-y-3">
                <h3 class="text-sm font-semibold text-gray-800">Other Purpose</h3>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="purpose_monetization" value="1" class="w-4 h-4 border-gray-300 rounded">
                    Monetization of Leave Credits
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="purpose_terminal" value="1" class="w-4 h-4 border-gray-300 rounded">
                    Terminal Leave
                </label>
            </div>

            <div id="othersSpecify" class="hidden border rounded-lg p-4 bg-gray-50 space-y-3">
                <h3 class="text-sm font-semibold text-gray-800">Others</h3>
                <input type="text" name="others_specify" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Please specify">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Commutation</label>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="commutation" value="not_requested" checked class="w-4 h-4 border-gray-300">
                        Not Requested
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="commutation" value="requested" class="w-4 h-4 border-gray-300">
                        Requested
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason <span class="text-red-500">*</span></label>
                <textarea name="reason" required rows="4" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Explain your reason for leave..."></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Attachment (Optional)</label>
                <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
                <p class="text-xs text-gray-500 mt-1">PDF, JPG, or PNG (max 5MB). For Sick Leave in excess of five successive days, attach a medical certificate.</p>
            </div>

            <button type="submit" class="px-6 py-2.5 bg-blue-700 text-white rounded-lg text-sm font-medium hover:bg-blue-800"><i class="fas fa-paper-plane mr-2"></i>Submit Request</button>
        </form>
    </div>
</div>

<script>
    const typeMap = {
        'VL': 'vacation',
        'SPL': 'vacation',
        'SL': 'sick',
        'SLBW': 'women',
        'STL': 'study',
        'OTH': 'others'
    };

    function updateDetails() {
        const select = document.getElementById('leaveType');
        const code = select.options[select.selectedIndex].dataset.code || '';
        const group = typeMap[code] || '';

        document.getElementById('vacationDetails').classList.toggle('hidden', group !== 'vacation');
        document.getElementById('sickDetails').classList.toggle('hidden', group !== 'sick');
        document.getElementById('specialWomenDetails').classList.toggle('hidden', group !== 'women');
        document.getElementById('studyDetails').classList.toggle('hidden', group !== 'study');
        document.getElementById('othersSpecify').classList.toggle('hidden', group !== 'others');
    }

    function calcDays() {
        const from = document.getElementById('dateFrom').value;
        const to = document.getElementById('dateTo').value;
        if (from && to) {
            const d1 = new Date(from), d2 = new Date(to);
            const diff = Math.floor((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
            document.getElementById('leaveDays').value = diff > 0 ? diff : 0;
        }
    }

    document.getElementById('leaveType')?.addEventListener('change', updateDetails);
    document.getElementById('dateFrom')?.addEventListener('change', calcDays);
    document.getElementById('dateTo')?.addEventListener('change', calcDays);
    updateDetails();
</script>

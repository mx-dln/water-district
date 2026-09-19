<?php
requireLogin();
$employeeId = getEmployeeIdFromUser($_SESSION['user_id']);

$action = $_GET['action'] ?? '';
if ($action === 'request') {
    require __DIR__ . '/leaves/request.php';
    return;
}
if ($action === 'manage' && isAdmin()) {
    require __DIR__ . '/leaves/manage.php';
    return;
}

if (isAdmin()):
$leaves = $db->query("
    SELECT lr.*, lt.name as leave_type_name, lt.code as leave_type_code, e.first_name, e.last_name, e.employee_no, e.position, d.name as dept
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY lr.created_at DESC
")->fetchAll();
else:
$leaves = $db->prepare("
    SELECT lr.*, lt.name as leave_type_name, lt.code as leave_type_code
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.employee_id = ?
    ORDER BY lr.created_at DESC
");
$leaves->execute([$employeeId]);
$leaves = $leaves->fetchAll();
endif;

$agencyName = getSetting('company_name', '(Agency Name)');
$agencyAddress = getSetting('company_address', '(Agency Address)');
$agencyLogo = getSetting('company_logo', '');
$logoUrl = '';
if ($agencyLogo) $logoUrl = APP_URL . '/' . $agencyLogo;
?>
<script>
const leaveData = <?= json_encode($leaves) ?>;
const isAdmin = <?= isAdmin() ? 'true' : 'false' ?>;
const logoUrl = '<?= $logoUrl ?>';
const appName = <?= json_encode($agencyName) ?>;
const appAddr = <?= json_encode($agencyAddress) ?>;

function viewLeave(id) {
    const l = leaveData.find(r => String(r.id) === String(id));
    if (!l) return;

    const d = JSON.parse(l.details || '{}');
    const code = (l.leave_type_code || '').toUpperCase();
    const filingDate = l.created_at ? new Date(l.created_at + 'T00:00:00').toLocaleDateString('en-US', {year:'numeric',month:'2-digit',day:'2-digit'}) : '';

    function cb(checked) { return checked ? '<span style="display:inline-block;width:10px;height:10px;border:1px solid #000;vertical-align:middle;margin-right:3px;position:relative;"><span style="position:absolute;left:2px;top:-1px;width:4px;height:7px;border:solid #000;border-width:0 1.5px 1.5px 0;transform:rotate(45deg);"></span></span>' : '<span style="display:inline-block;width:10px;height:10px;border:1px solid #000;vertical-align:middle;margin-right:3px;"></span>'; }

    let logoHtml = logoUrl ? `<img src="${logoUrl}" style="width:22mm;height:22mm;object-contain;">` : '<div style="width:22mm;height:22mm;border:1px dashed #ccc;"></div>';

    let html = `
    <div style="font-family:Arial,sans-serif;font-size:10pt;line-height:1.35;color:#000;background:#fff;padding:6px;border:1px solid #ddd;">

        <div style="display:flex;justify-content:space-between;margin-bottom:3px;">
            <div style="font-size:8pt;font-style:italic;">Civil Service Form No. 6<br>Revised 2020</div>
            <div style="font-size:12pt;font-weight:bold;">ANNEX A</div>
        </div>

        <table style="width:100%;border:none;border-collapse:collapse;margin-bottom:3px;">
        <tr>
        <td style="width:22mm;border:none;text-align:center;vertical-align:top;padding:2px;">${logoHtml}</td>
        <td style="border:none;text-align:center;vertical-align:top;padding:2px;">
            <div style="font-size:9pt;">Republic of the Philippines</div>
            <div style="font-size:11pt;font-weight:bold;">${appName}</div>
            <div style="font-size:8pt;font-style:italic;">${appAddr}</div>
            <div style="font-size:14pt;font-weight:bold;text-transform:uppercase;margin-top:3pt;">APPLICATION FOR LEAVE</div>
        </td>
        <td style="width:30mm;border:none;text-align:right;vertical-align:top;padding:2px;">
            <div style="border:1px solid #000;padding:3px;width:28mm;height:16mm;display:inline-flex;align-items:center;justify-content:center;font-size:7pt;text-align:center;">Stamp of Date<br>of Receipt</div>
        </td>
        </tr></table>

        <div style="border:1px solid #000;margin-bottom:2px;">
        <table style="width:100%;border-collapse:collapse;">
        <tr><td style="width:33%;border-bottom:1px solid #000;border-right:1px solid #000;padding:2px 3px;"><b>1. OFFICE/DEPARTMENT</b><br>${l.dept || 'N/A'}</td>
        <td style="border-bottom:1px solid #000;padding:2px 3px;"><b>2. NAME</b><br>
            <table style="width:100%;border:none;"><tr>
            <td style="border:none;width:33%;font-size:8px;">${l.last_name || ''}<br><span style="font-size:6px;">(Last)</span></td>
            <td style="border:none;width:33%;font-size:8px;">${l.first_name || ''}<br><span style="font-size:6px;">(First)</span></td>
            <td style="border:none;width:33%;font-size:8px;">${l.middle_name || ''}<br><span style="font-size:6px;">(Middle)</span></td>
            </tr></table>
        </td></tr>
        <tr>
        <td style="border-right:1px solid #000;padding:2px 3px;"><b>3. DATE OF FILING</b><br>${filingDate}</td>
        <td style="border-right:1px solid #000;padding:2px 3px;"><b>4. POSITION</b><br>${l.position || 'N/A'}</td>
        <td style="padding:2px 3px;"><b>5. SALARY</b><br>${d.salary || ''}</td>
        </tr></table></div>

        <div style="border:1px solid #000;margin-bottom:2px;">
        <div style="background:#f3f4f6;border-bottom:1px solid #000;text-align:center;font-weight:bold;text-transform:uppercase;font-size:9px;padding:1px;">6. DETAILS OF APPLICATION</div>
        <table style="width:100%;border-collapse:collapse;"><tr>
        <td style="width:50%;border-right:1px solid #000;padding:2px 3px;vertical-align:top;">
            <div style="font-weight:bold;text-transform:uppercase;font-size:8px;margin-bottom:1px;">6.A TYPE OF LEAVE TO BE AVAILED OF</div>
            <div style="font-size:7.5px;line-height:1.4;">
            ${cb(code==='VL')}Vacation Leave <span style="font-size:6px;">(Sec. 51, Rule XVI)</span><br>
            ${cb(code==='MFL')}Mandatory/Forced Leave <span style="font-size:6px;">(Sec. 25, Rule XVI)</span><br>
            ${cb(code==='SL')}Sick Leave <span style="font-size:6px;">(Sec. 43, Rule XVI)</span><br>
            ${cb(code==='ML')}Maternity Leave <span style="font-size:6px;">(R.A. No. 11210)</span><br>
            ${cb(code==='PL')}Paternity Leave <span style="font-size:6px;">(R.A. No. 8187)</span><br>
            ${cb(code==='SPL')}Special Privilege Leave <span style="font-size:6px;">(Sec. 21, Rule XVI)</span><br>
            ${cb(code==='SPLP')}Solo Parent Leave <span style="font-size:6px;">(RA No. 8972)</span><br>
            ${cb(code==='STL')}Study Leave <span style="font-size:6px;">(Sec. 68, Rule XVI)</span><br>
            ${cb(code==='VAWC')}10-Day VAWC Leave <span style="font-size:6px;">(RA No. 9262)</span><br>
            ${cb(code==='RP')}Rehabilitation Privilege <span style="font-size:6px;">(Sec. 55, Rule XVI)</span><br>
            ${cb(code==='SLBW')}Special Leave Benefits for Women <span style="font-size:6px;">(RA No. 9710)</span><br>
            ${cb(code==='SECL')}Special Emergency Leave <span style="font-size:6px;">(CSC MC No. 2)</span><br>
            ${cb(code==='AL')}Adoption Leave <span style="font-size:6px;">(R.A. No. 8552)</span><br>
            ${cb(code==='OTH')}Others (Specify)${d.others_specify ? '<br><span style="margin-left:14px;font-size:7px;">'+d.others_specify+'</span>' : ''}
            </div>
        </td>
        <td style="padding:2px 3px;vertical-align:top;">
            <div style="font-weight:bold;text-transform:uppercase;font-size:8px;margin-bottom:1px;">6.B DETAILS OF LEAVE</div>
            <div style="font-size:7.5px;">
            <div style="font-style:italic;font-weight:600;margin-bottom:0;">In case of Vacation/Special Privilege Leave:</div>
            <div style="margin-left:2px;">${cb(d.vacation_within)}Within the Philippines${d.vacation_within_specify ? '<br><span style="margin-left:14px;font-size:7px;">'+d.vacation_within_specify+'</span>' : ''}</div>
            <div style="margin-left:2px;">${cb(d.vacation_abroad)}Abroad (Specify)${d.vacation_abroad_specify ? '<br><span style="margin-left:14px;font-size:7px;">'+d.vacation_abroad_specify+'</span>' : ''}</div>
            <div style="font-style:italic;font-weight:600;margin-top:2px;">In case of Sick Leave:</div>
            <div style="margin-left:2px;">${cb(d.sick_hospital)}In Hospital (Specify Illness)${d.sick_hospital_specify ? '<br><span style="margin-left:14px;font-size:7px;">'+d.sick_hospital_specify+'</span>' : ''}</div>
            <div style="margin-left:2px;">${cb(d.sick_outpatient)}Out Patient (Specify Illness)${d.sick_outpatient_specify ? '<br><span style="margin-left:14px;font-size:7px;">'+d.sick_outpatient_specify+'</span>' : ''}</div>
            <div style="font-style:italic;font-weight:600;margin-top:2px;">In case of Special Leave Benefits for Women: (Specify Illness)</div>
            ${d.special_women_illness ? '<div style="margin-left:2px;font-size:7px;">'+d.special_women_illness+'</div>' : ''}
            <div style="font-style:italic;font-weight:600;margin-top:2px;">In case of Study Leave:</div>
            <div style="margin-left:2px;">${cb(d.study_masters)}Completion of Master's Degree</div>
            <div style="margin-left:2px;">${cb(d.study_bar)}BAR/Board Examination Review</div>
            <div style="font-style:italic;font-weight:600;margin-top:2px;">Other purpose:</div>
            <div style="margin-left:2px;">${cb(d.purpose_monetization)}Monetization of Leave Credits</div>
            <div style="margin-left:2px;">${cb(d.purpose_terminal)}Terminal Leave</div>
            </div>
        </td></tr></table>

        <table style="width:100%;border-collapse:collapse;border-top:1px solid #000;"><tr>
        <td style="width:50%;border-right:1px solid #000;padding:2px 3px;">
            <div style="font-weight:bold;text-transform:uppercase;font-size:8px;">6.C NUMBER OF WORKING DAYS APPLIED FOR</div>
            <div style="font-size:9px;font-weight:600;">${l.days || 0}</div>
            <div style="font-weight:bold;text-transform:uppercase;font-size:8px;margin-top:1px;">INCLUSIVE DATES</div>
            <div style="font-size:8px;">${l.date_from || ''} &mdash; ${l.date_to || ''}</div>
        </td>
        <td style="padding:2px 3px;">
            <div style="font-weight:bold;text-transform:uppercase;font-size:8px;">6.D COMMUTATION</div>
            <div style="font-size:7.5px;">${cb(d.commutation==='not_requested')}Not Requested</div>
            <div style="font-size:7.5px;">${cb(d.commutation==='requested')}Requested</div>
            <div style="border-top:1px solid #000;margin-top:12px;padding-top:2px;text-align:center;font-size:7px;">(Signature of Applicant)</div>
        </td></tr></table></div>

        <div style="border:1px solid #000;">
        <div style="background:#f3f4f6;border-bottom:1px solid #000;text-align:center;font-weight:bold;text-transform:uppercase;font-size:9px;padding:1px;">7. DETAILS OF ACTION ON APPLICATION</div>
        <table style="width:100%;border-collapse:collapse;"><tr>
        <td style="width:50%;border-right:1px solid #000;padding:2px 3px;vertical-align:top;">
            <div style="font-weight:bold;text-transform:uppercase;font-size:8px;">7.A CERTIFICATION OF LEAVE CREDITS</div>
            <div style="font-size:7px;">As of _______________</div>
            <table style="width:100%;border-collapse:collapse;font-size:7px;"><tr><th style="border:1px solid #000;padding:1px 2px;"></th><th style="border:1px solid #000;padding:1px 2px;">Vacation Leave</th><th style="border:1px solid #000;padding:1px 2px;">Sick Leave</th></tr>
            <tr><td style="border:1px solid #000;padding:1px 2px;font-style:italic;">Total Earned</td><td style="border:1px solid #000;padding:1px 2px;"></td><td style="border:1px solid #000;padding:1px 2px;"></td></tr>
            <tr><td style="border:1px solid #000;padding:1px 2px;font-style:italic;">Less this Application</td><td style="border:1px solid #000;padding:1px 2px;"></td><td style="border:1px solid #000;padding:1px 2px;"></td></tr>
            <tr><td style="border:1px solid #000;padding:1px 2px;font-style:italic;">Balance</td><td style="border:1px solid #000;padding:1px 2px;"></td><td style="border:1px solid #000;padding:1px 2px;"></td></tr></table>
            <div style="border-top:1px solid #000;margin-top:10px;padding-top:2px;text-align:center;font-size:7px;">(Authorized Officer)</div>
        </td>
        <td style="padding:2px 3px;vertical-align:top;">
            <div style="font-weight:bold;text-transform:uppercase;font-size:8px;">7.B RECOMMENDATION</div>
            <div style="font-size:7.5px;">${cb()}For approval</div>
            <div style="font-size:7.5px;">${cb()}For disapproval due to ________________</div>
            <div style="border:1px solid #000;min-height:12px;margin-top:1px;font-size:6px;"></div>
            <div style="border-top:1px solid #000;margin-top:10px;padding-top:2px;text-align:center;font-size:7px;">(Authorized Officer)</div>
        </td></tr></table>

        <table style="width:100%;border-collapse:collapse;border-top:1px solid #000;"><tr>
        <td style="width:50%;border-right:1px solid #000;padding:2px 3px;vertical-align:top;">
            <div style="font-weight:bold;text-transform:uppercase;font-size:8px;">7.C APPROVED FOR:</div>
            <div style="font-size:7.5px;line-height:1.6;">_______ days with pay<br>_______ days without pay<br>_______ others (Specify)</div>
            <div style="border-top:1px solid #000;margin-top:10px;padding-top:2px;text-align:center;font-size:7px;">(Authorized Official)</div>
        </td>
        <td style="padding:2px 3px;vertical-align:top;">
            <div style="font-weight:bold;text-transform:uppercase;font-size:8px;">7.D DISAPPROVED DUE TO:</div>
            <div style="border:1px solid #000;min-height:16px;margin-top:1px;font-size:6px;"></div>
            <div style="border-top:1px solid #000;margin-top:10px;padding-top:2px;text-align:center;font-size:7px;">(Authorized Official)</div>
        </td></tr></table></div>

    </div>`;

    document.getElementById('modalTitle').innerHTML = `<span>Leave Request #${l.id}</span>`;
    document.getElementById('modalBody').innerHTML = html;

    let footer = `<a href="<?= APP_URL ?>/api/leave-pdf.php?id=${l.id}" target="_blank" class="px-4 py-2 bg-blue-700 text-white text-sm rounded-lg hover:bg-blue-800"><i class="fas fa-file-pdf mr-1"></i>View PDF</a>`;
    document.getElementById('modalFooter').innerHTML = footer;

    document.getElementById('leaveModal').classList.remove('hidden');
    document.getElementById('leaveModal').classList.add('flex');
}

function closeLeave() {
    document.getElementById('leaveModal').classList.add('hidden');
    document.getElementById('leaveModal').classList.remove('flex');
}
</script>
<div class="space-y-4 sm:space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800"><?= isAdmin() ? 'Leave Requests' : 'My Leaves' ?></h1>
            <p class="text-sm text-gray-500"><?= isAdmin() ? 'Manage employee leave requests' : 'View and submit leave requests' ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= APP_URL ?>/index.php?page=leaves-request" class="flex-1 sm:flex-none text-center px-4 py-2 bg-blue-700 text-white rounded-lg text-sm hover:bg-blue-800"><i class="fas fa-plus mr-2"></i>Request Leave</a>
            <?php if (isAdmin()): ?>
            <a href="<?= APP_URL ?>/index.php?page=leaves&action=manage" class="flex-1 sm:flex-none text-center px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-tasks mr-2"></i>Manage All</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border">
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <?php if (isAdmin()): ?><th class="text-left px-4 py-3 font-medium">Employee</th><?php endif; ?>
                        <th class="text-left px-4 py-3 font-medium">Type</th>
                        <th class="text-left px-4 py-3 font-medium">From</th>
                        <th class="text-left px-4 py-3 font-medium">To</th>
                        <th class="text-center px-4 py-3 font-medium">Days</th>
                        <th class="text-left px-4 py-3 font-medium">Status</th>
                        <th class="text-left px-4 py-3 font-medium">Filed On</th>
                        <th class="text-center px-4 py-3 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($leaves)): ?>
                    <tr><td colspan="<?= isAdmin() ? 8 : 7 ?>" class="px-4 py-8 text-center text-gray-500">No leave requests</td></tr>
                    <?php else: foreach ($leaves as $l): ?>
                    <tr class="hover:bg-gray-50">
                        <?php if (isAdmin()): ?>
                        <td class="px-4 py-3"><?= escapeOutput($l['first_name'] . ' ' . $l['last_name']) ?><br><span class="text-xs text-gray-500"><?= escapeOutput($l['employee_no']) ?></span></td>
                        <?php endif; ?>
                        <td class="px-4 py-3"><?= escapeOutput($l['leave_type_name']) ?></td>
                        <td class="px-4 py-3"><?= formatDate($l['date_from'], 'M j, Y') ?></td>
                        <td class="px-4 py-3"><?= formatDate($l['date_to'], 'M j, Y') ?></td>
                        <td class="px-4 py-3 text-center font-medium"><?= $l['days'] ?></td>
                        <td class="px-4 py-3"><?= getStatusBadge($l['status']) ?></td>
                        <td class="px-4 py-3"><?= formatDate($l['created_at'], 'M j, Y') ?></td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex justify-center items-center space-x-1">
                                <button onclick="viewLeave(<?= $l['id'] ?>)" class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200" title="View CS Form 6"><i class="fas fa-eye"></i></button>
                                <?php if (isAdmin() && $l['status'] === 'pending'): ?>
                                <form method="POST" action="<?= APP_URL ?>/api/leave.php" class="inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                    <button type="submit" class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200" title="Approve"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="POST" action="<?= APP_URL ?>/api/leave.php" class="inline" onsubmit="return prompt('Rejection reason:')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                    <button type="submit" class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200" title="Reject"><i class="fas fa-times"></i></button>
                                </form>
                                <?php elseif ($l['status'] === 'pending'): ?>
                                <form method="POST" action="<?= APP_URL ?>/api/leave.php" class="inline" onsubmit="return confirm('Cancel this request?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                    <button type="submit" class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded hover:bg-gray-200"><i class="fas fa-ban"></i> Cancel</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="sm:hidden divide-y">
            <?php if (empty($leaves)): ?>
            <p class="p-4 text-sm text-gray-500 text-center">No leave requests</p>
            <?php else: foreach ($leaves as $l): ?>
            <div class="p-4 space-y-2">
                <?php if (isAdmin()): ?>
                <div class="text-sm font-medium"><?= escapeOutput($l['first_name'] . ' ' . $l['last_name']) ?> <span class="text-xs text-gray-500"><?= escapeOutput($l['employee_no']) ?></span></div>
                <?php endif; ?>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium"><?= escapeOutput($l['leave_type_name']) ?></span>
                    <?= getStatusBadge($l['status']) ?>
                </div>
                <div class="grid grid-cols-2 gap-1 text-xs text-gray-600">
                    <div><span class="text-gray-400">From:</span> <?= formatDate($l['date_from'], 'M j, Y') ?></div>
                    <div><span class="text-gray-400">To:</span> <?= formatDate($l['date_to'], 'M j, Y') ?></div>
                    <div><span class="text-gray-400">Days:</span> <?= $l['days'] ?></div>
                    <div><span class="text-gray-400">Filed:</span> <?= formatDate($l['created_at'], 'M j, Y') ?></div>
                </div>
                <div class="text-right">
                    <button onclick="viewLeave(<?= $l['id'] ?>)" class="text-xs px-3 py-1.5 bg-blue-100 text-blue-700 rounded hover:bg-blue-200"><i class="fas fa-eye mr-1"></i>View</button>
                    <?php if (isAdmin() && $l['status'] === 'pending'): ?>
                    <form method="POST" action="<?= APP_URL ?>/api/leave.php" class="inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                        <button type="submit" class="text-xs px-3 py-1.5 bg-green-100 text-green-700 rounded hover:bg-green-200"><i class="fas fa-check mr-1"></i>Approve</button>
                    </form>
                    <form method="POST" action="<?= APP_URL ?>/api/leave.php" class="inline ml-1">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                        <button type="submit" class="text-xs px-3 py-1.5 bg-red-100 text-red-700 rounded hover:bg-red-200"><i class="fas fa-times mr-1"></i>Reject</button>
                    </form>
                    <?php elseif ($l['status'] === 'pending'): ?>
                    <form method="POST" action="<?= APP_URL ?>/api/leave.php" class="inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                        <button type="submit" class="text-xs px-3 py-1.5 bg-gray-100 text-gray-600 rounded hover:bg-gray-200"><i class="fas fa-ban mr-1"></i>Cancel</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<div id="leaveModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" onclick="if(event.target===this)closeLeave()">
    <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[95vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b">
            <h3 class="font-semibold text-gray-800" id="modalTitle">CS Form 6</h3>
            <button onclick="closeLeave()" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <div class="p-4" id="modalBody"></div>
        <div class="flex justify-end gap-2 p-4 border-t" id="modalFooter"></div>
    </div>
</div>

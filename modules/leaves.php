<?php
requireLogin();
$employeeId = getEmployeeIdFromUser($_SESSION['user_id']);
$creditYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
if ($creditYear < 2000 || $creditYear > 2100) $creditYear = (int) date('Y');

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

// Leave Credit Summary for employee
$creditSummary = $employeeId ? getEmployeeLeaveCreditSummary($employeeId, $creditYear) : null;
?>
<script>
const leaveData = <?= json_encode($leaves) ?>;
const isAdmin = <?= isAdmin() ? 'true' : 'false' ?>;
const logoUrl = '<?= $logoUrl ?>';
const appName = <?= json_encode($agencyName) ?>;
const appAddr = <?= json_encode($agencyAddress) ?>;
const creditSummary = <?= json_encode($creditSummary) ?>;

function viewLeave(id) {
    const l = leaveData.find(r => String(r.id) === String(id));
    if (!l) return;

    const d = JSON.parse(l.details || '{}');
    const code = (l.leave_type_code || '').toUpperCase();
    const filingDate = l.created_at ? new Date(l.created_at + 'T00:00:00').toLocaleDateString('en-US', {year:'numeric',month:'2-digit',day:'2-digit'}) : '';

    function cb(checked) { return checked ? '<span style="display:inline-block;width:10px;height:10px;border:1px solid #000;vertical-align:middle;margin-right:3px;position:relative;"><span style="position:absolute;left:2px;top:-1px;width:4px;height:7px;border:solid #000;border-width:0 1.5px 1.5px 0;transform:rotate(45deg);"></span></span>' : '<span style="display:inline-block;width:10px;height:10px;border:1px solid #000;vertical-align:middle;margin-right:3px;"></span>'; }

    let logoHtml = logoUrl ? `<img src="${logoUrl}" style="width:22mm;height:22mm;object-contain;">` : '<div style="width:22mm;height:22mm;border:1px dashed #ccc;"></div>';

    // Certification of Leave Credits in CS Form 6
    let vlEarnedStr = '';
    let slEarnedStr = '';
    let vlLessStr = '';
    let slLessStr = '';
    let vlBalStr = '';
    let slBalStr = '';
    let asOfStr = filingDate ? `As of ${filingDate}` : 'As of _______________';

    if (creditSummary && creditSummary.vl && creditSummary.sl) {
        vlEarnedStr = Number(creditSummary.vl.earned).toFixed(3);
        slEarnedStr = Number(creditSummary.sl.earned).toFixed(3);
        vlLessStr = (code === 'VL' ? Number(l.days || 0).toFixed(3) : '0.000');
        slLessStr = (code === 'SL' ? Number(l.days || 0).toFixed(3) : '0.000');
        vlBalStr = Number(creditSummary.vl.balance).toFixed(3);
        slBalStr = Number(creditSummary.sl.balance).toFixed(3);
    }

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
            <div style="font-size:7px;">${asOfStr}</div>
            <table style="width:100%;border-collapse:collapse;font-size:7px;"><tr><th style="border:1px solid #000;padding:1px 2px;"></th><th style="border:1px solid #000;padding:1px 2px;">Vacation Leave</th><th style="border:1px solid #000;padding:1px 2px;">Sick Leave</th></tr>
            <tr><td style="border:1px solid #000;padding:1px 2px;font-style:italic;">Total Earned</td><td style="border:1px solid #000;padding:1px 2px;text-align:right;">${vlEarnedStr}</td><td style="border:1px solid #000;padding:1px 2px;text-align:right;">${slEarnedStr}</td></tr>
            <tr><td style="border:1px solid #000;padding:1px 2px;font-style:italic;">Less this Application</td><td style="border:1px solid #000;padding:1px 2px;text-align:right;">${vlLessStr}</td><td style="border:1px solid #000;padding:1px 2px;text-align:right;">${slLessStr}</td></tr>
            <tr><td style="border:1px solid #000;padding:1px 2px;font-style:italic;">Balance</td><td style="border:1px solid #000;padding:1px 2px;text-align:right;font-weight:bold;">${vlBalStr}</td><td style="border:1px solid #000;padding:1px 2px;text-align:right;font-weight:bold;">${slBalStr}</td></tr></table>
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
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=dashboard" class="hover:text-blue-600">Home</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold"><?= isAdmin() ? 'Leave Management' : 'My Leave Records' ?></span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight"><?= isAdmin() ? 'CSC Form 6 Leave Requests' : 'My CSC Leave Portfolio' ?></h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                <?= isAdmin() ? 'Review, approve, and track employee statutory leave applications' : 'Inspect Civil Service leave balances, deductions, and submit CSC Form 6 requests' ?>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= baseUrl() ?>/index.php?page=leaves-request"
               class="inline-flex items-center space-x-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors">
                <i class="fas fa-file-circle-plus text-xs"></i>
                <span>File Leave Request</span>
            </a>
            <?php if (isAdmin()): ?>
            <a href="<?= baseUrl() ?>/index.php?page=leaves&action=manage"
               class="inline-flex items-center space-x-2 px-3.5 py-2.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors">
                <i class="fas fa-tasks text-blue-600"></i>
                <span>Manage All</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($creditSummary): ?>
    <!-- ========================================================================= -->
    <!-- Leave Credit Summary (Earned, Used, Deducted, and Remaining Balances)     -->
    <!-- CSC MC No. 41 s. 1998 / CSC MC No. 14 s. 1999 Civil Service Guidelines   -->
    <!-- ========================================================================= -->
    <div class="space-y-4" x-data="{ showAllTypes: false }">
        <!-- Summary Section Header Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-1 border-b border-gray-200">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm shadow-sm">
                    <i class="fas fa-wallet"></i>
                </div>
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2">
                        Leave Credit Summary
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                            Calendar Year <?= $creditYear ?>
                        </span>
                    </h2>
                    <p class="text-xs text-gray-500">Civil Service Commission · Earned, used, deducted & remaining balances</p>
                </div>
            </div>

            <form method="GET" class="flex items-center space-x-2 self-start sm:self-auto text-xs">
                <input type="hidden" name="page" value="leaves">
                <label for="creditYearSelect" class="text-gray-500 font-medium">Calendar Year:</label>
                <select id="creditYearSelect" name="year" onchange="this.form.submit()" class="bg-white border border-gray-300 rounded-lg px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <?php 
                    $curYear = (int) date('Y');
                    for ($y = $curYear; $y >= $curYear - 3; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $creditYear ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>

        <!-- Top Highlight Metric Cards (4 Cards Grid) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <!-- Vacation Leave Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 border-t-4 border-t-blue-500 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Vacation Leave</span>
                        <span class="text-[11px] text-gray-400">1.25 days/mo accrual</span>
                    </div>
                    <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-sm">
                        <i class="fas fa-umbrella-beach"></i>
                    </div>
                </div>
                <div class="mt-2.5 flex items-baseline gap-1.5">
                    <span class="text-2xl sm:text-3xl font-black text-gray-900"><?= number_format($creditSummary['vl']['balance'], 3) ?></span>
                    <span class="text-xs text-gray-500 font-medium">days left</span>
                </div>
                <div class="mt-3 pt-2.5 border-t border-gray-100 grid grid-cols-2 gap-1.5 text-xs text-gray-600">
                    <div><span class="text-gray-400 text-[11px]">Earned:</span> <strong class="text-gray-800"><?= number_format($creditSummary['vl']['earned'], 3) ?></strong></div>
                    <div><span class="text-gray-400 text-[11px]">Used:</span> <strong class="text-gray-800"><?= number_format($creditSummary['vl']['used'], 3) ?></strong></div>
                    <div class="col-span-2 flex items-center justify-between text-[11px]">
                        <span class="text-gray-400">Deductions:</span>
                        <span class="font-bold <?= $creditSummary['vl']['deducted'] > 0 ? 'text-rose-600' : 'text-gray-700' ?>">
                            -<?= number_format($creditSummary['vl']['deducted'], 3) ?>
                            <?php if ($creditSummary['deficiency_minutes'] > 0): ?>
                            <span class="text-[10px] font-normal text-rose-500">(<?= (int)$creditSummary['deficiency_minutes'] ?>m)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($creditSummary['vl']['pending'] > 0): ?>
                    <div class="col-span-2 text-[11px] text-amber-600 flex items-center justify-between">
                        <span>Pending approval:</span>
                        <span class="font-bold"><?= number_format($creditSummary['vl']['pending'], 3) ?> day(s)</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sick Leave Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 border-t-4 border-t-emerald-500 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Sick Leave</span>
                        <span class="text-[11px] text-gray-400">1.25 days/mo accrual</span>
                    </div>
                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                        <i class="fas fa-heart-pulse"></i>
                    </div>
                </div>
                <div class="mt-2.5 flex items-baseline gap-1.5">
                    <span class="text-2xl sm:text-3xl font-black text-gray-900"><?= number_format($creditSummary['sl']['balance'], 3) ?></span>
                    <span class="text-xs text-gray-500 font-medium">days left</span>
                </div>
                <div class="mt-3 pt-2.5 border-t border-gray-100 grid grid-cols-2 gap-1.5 text-xs text-gray-600">
                    <div><span class="text-gray-400 text-[11px]">Earned:</span> <strong class="text-gray-800"><?= number_format($creditSummary['sl']['earned'], 3) ?></strong></div>
                    <div><span class="text-gray-400 text-[11px]">Used:</span> <strong class="text-gray-800"><?= number_format($creditSummary['sl']['used'], 3) ?></strong></div>
                    <div class="col-span-2 flex items-center justify-between text-[11px]">
                        <span class="text-gray-400">Deductions:</span>
                        <span class="font-medium text-gray-500">0.000 (Protected)</span>
                    </div>
                    <?php if ($creditSummary['sl']['pending'] > 0): ?>
                    <div class="col-span-2 text-[11px] text-amber-600 flex items-center justify-between">
                        <span>Pending approval:</span>
                        <span class="font-bold"><?= number_format($creditSummary['sl']['pending'], 3) ?> day(s)</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Special Privilege Leave Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 border-t-4 border-t-purple-500 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Special Privilege</span>
                        <span class="text-[11px] text-gray-400">Annual 3-day personal leave</span>
                    </div>
                    <div class="w-9 h-9 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center text-sm">
                        <i class="fas fa-star"></i>
                    </div>
                </div>
                <div class="mt-2.5 flex items-baseline gap-1.5">
                    <span class="text-2xl sm:text-3xl font-black text-gray-900"><?= number_format($creditSummary['spl']['balance'], 1) ?></span>
                    <span class="text-xs text-gray-500 font-medium">days left</span>
                </div>
                <div class="mt-3 pt-2.5 border-t border-gray-100 grid grid-cols-2 gap-1.5 text-xs text-gray-600">
                    <div><span class="text-gray-400 text-[11px]">Allowed:</span> <strong class="text-gray-800"><?= number_format($creditSummary['spl']['earned'], 1) ?></strong></div>
                    <div><span class="text-gray-400 text-[11px]">Used:</span> <strong class="text-gray-800"><?= number_format($creditSummary['spl']['used'], 1) ?></strong></div>
                    <div class="col-span-2 flex items-center justify-between text-[11px]">
                        <span class="text-gray-400">Non-cumulative:</span>
                        <span class="text-purple-700 font-medium">Use within <?= $creditYear ?></span>
                    </div>
                    <?php if ($creditSummary['spl']['pending'] > 0): ?>
                    <div class="col-span-2 text-[11px] text-amber-600 flex items-center justify-between">
                        <span>Pending approval:</span>
                        <span class="font-bold"><?= number_format($creditSummary['spl']['pending'], 1) ?> day(s)</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Total Combined Balance Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 border-t-4 border-t-indigo-600 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Total Available</span>
                        <span class="text-[11px] text-gray-400">Cumulative (VL + SL)</span>
                    </div>
                    <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm">
                        <i class="fas fa-scale-balanced"></i>
                    </div>
                </div>
                <div class="mt-2.5 flex items-baseline gap-1.5">
                    <span class="text-2xl sm:text-3xl font-black text-indigo-900"><?= number_format($creditSummary['totals']['balance'], 3) ?></span>
                    <span class="text-xs text-gray-500 font-medium">days</span>
                </div>
                <div class="mt-3 pt-2.5 border-t border-gray-100 grid grid-cols-2 gap-1.5 text-xs text-gray-600">
                    <div><span class="text-gray-400 text-[11px]">Total Earned:</span> <strong class="text-gray-800"><?= number_format($creditSummary['totals']['earned'], 3) ?></strong></div>
                    <div><span class="text-gray-400 text-[11px]">Total Used:</span> <strong class="text-gray-800"><?= number_format($creditSummary['totals']['used'], 3) ?></strong></div>
                    <div class="col-span-2 flex items-center justify-between text-[11px]">
                        <span class="text-gray-400">Total Deducted:</span>
                        <span class="font-bold <?= $creditSummary['totals']['deducted'] > 0 ? 'text-rose-600' : 'text-gray-700' ?>">-<?= number_format($creditSummary['totals']['deducted'], 3) ?></span>
                    </div>
                    <?php if ($creditSummary['totals']['pending'] > 0): ?>
                    <div class="col-span-2 text-[11px] text-amber-600 flex items-center justify-between">
                        <span>Total in Review:</span>
                        <span class="font-bold"><?= number_format($creditSummary['totals']['pending'], 3) ?> day(s)</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Leave Credit Summary Breakdown Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 sm:px-5 py-3.5 border-b border-gray-100 bg-gray-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h3 class="font-bold text-gray-800 text-sm sm:text-base flex items-center gap-2">
                        <i class="fas fa-table-list text-blue-600"></i>
                        Leave Credit Balance Sheet
                    </h3>
                    <p class="text-xs text-gray-500">Itemized statement of earned, used, deducted, and net remaining leave balances</p>
                </div>
                <button type="button" @click="showAllTypes = !showAllTypes" class="self-start sm:self-auto text-xs px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium transition flex items-center gap-1.5 shadow-sm">
                    <i class="fas fa-filter text-[10px]"></i>
                    <span x-text="showAllTypes ? 'Show Core Leaves (VL, SL, MFL, SPL)' : 'View All Statutory & Special Leaves'"></span>
                </button>
            </div>

            <!-- Desktop / Tablet Table View -->
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-sm text-left border-collapse">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase font-bold tracking-wider border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3">Leave Type</th>
                            <th class="px-3 py-3 text-center">Code</th>
                            <th class="px-4 py-3 text-right">Earned / Allowed</th>
                            <th class="px-4 py-3 text-right">Used (Approved)</th>
                            <th class="px-4 py-3 text-right">Deducted</th>
                            <th class="px-3 py-3 text-center">Pending</th>
                            <th class="px-4 py-3 text-right font-extrabold text-gray-900">Remaining Balance</th>
                            <th class="px-3 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php 
                        $coreCodes = ['VL', 'SL', 'MFL', 'SPL'];
                        foreach ($creditSummary['items'] as $code => $item):
                            $isCore = in_array($code, $coreCodes, true);
                            $hasActivity = ($item['used'] > 0 || $item['pending'] > 0);
                        ?>
                        <tr class="hover:bg-blue-50/40 transition-colors" x-show="<?= ($isCore || $hasActivity) ? 'true' : 'showAllTypes' ?>">
                            <td class="px-4 py-3">
                                <span class="font-bold text-gray-800"><?= escapeOutput($item['name']) ?></span>
                                <?php if (!empty($item['description'])): ?>
                                <span class="block text-xs text-gray-400"><?= escapeOutput($item['description']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-gray-100 text-gray-600 border border-gray-200">
                                    <?= escapeOutput($code) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-700">
                                <?= number_format($item['earned'], 3) ?>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-700">
                                <?= number_format($item['used'], 3) ?>
                            </td>
                            <td class="px-4 py-3 text-right font-medium">
                                <?php if ($item['deducted'] > 0): ?>
                                    <span class="text-rose-600 font-bold">-<?= number_format($item['deducted'], 3) ?></span>
                                    <?php if (!empty($item['deduction_note'])): ?>
                                    <span class="block text-[10px] text-rose-500"><?= escapeOutput($item['deduction_note']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400">0.000</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <?php if ($item['pending'] > 0): ?>
                                    <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold border border-amber-200">
                                        <i class="fas fa-clock text-[9px]"></i> <?= number_format($item['pending'], 3) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-base font-black <?= $item['balance'] > 0 ? 'text-gray-900' : 'text-gray-400' ?>">
                                    <?= number_format($item['balance'], 3) ?>
                                </span>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <?php if ($item['balance'] > 5.0): ?>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">Available</span>
                                <?php elseif ($item['balance'] > 0): ?>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 border border-amber-200">Low Balance</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-500 border border-gray-200">Depleted</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Total Row (VL + SL Combined) -->
                        <tr class="bg-gray-50/90 font-bold border-t-2 border-gray-300">
                            <td class="px-4 py-3.5 text-gray-900" colspan="2">
                                <div class="flex items-center gap-1.5">
                                    <i class="fas fa-calculator text-blue-600"></i>
                                    <span>Total Cumulative Credits (VL + SL)</span>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-right text-gray-900"><?= number_format($creditSummary['totals']['earned'], 3) ?></td>
                            <td class="px-4 py-3.5 text-right text-gray-900"><?= number_format($creditSummary['totals']['used'], 3) ?></td>
                            <td class="px-4 py-3.5 text-right text-rose-600">
                                <?= $creditSummary['totals']['deducted'] > 0 ? '-'.number_format($creditSummary['totals']['deducted'], 3) : '0.000' ?>
                            </td>
                            <td class="px-3 py-3.5 text-center">
                                <?php if ($creditSummary['totals']['pending'] > 0): ?>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold">
                                    <?= number_format($creditSummary['totals']['pending'], 3) ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3.5 text-right font-black text-indigo-900 text-base">
                                <?= number_format($creditSummary['totals']['balance'], 3) ?>
                            </td>
                            <td class="px-3 py-3.5 text-center">
                                <span class="text-xs text-indigo-700 font-bold">Active</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile View (Stacked Cards) -->
            <div class="sm:hidden divide-y divide-gray-100">
                <?php 
                foreach ($creditSummary['items'] as $code => $item):
                    $isCore = in_array($code, $coreCodes, true);
                    $hasActivity = ($item['used'] > 0 || $item['pending'] > 0);
                ?>
                <div class="p-3.5 space-y-2.5" x-show="<?= ($isCore || $hasActivity) ? 'true' : 'showAllTypes' ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-bold text-gray-900 text-sm"><?= escapeOutput($item['name']) ?></span>
                            <span class="text-xs font-mono font-bold text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded ml-1.5"><?= escapeOutput($code) ?></span>
                        </div>
                        <?php if ($item['balance'] > 5.0): ?>
                            <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">Available</span>
                        <?php elseif ($item['balance'] > 0): ?>
                            <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-amber-50 text-amber-700 border border-amber-200">Low</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-gray-100 text-gray-500 border border-gray-200">Depleted</span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-4 gap-2 text-center bg-gray-50/70 p-2.5 rounded-lg border border-gray-100 text-xs">
                        <div>
                            <span class="text-gray-400 text-[10px] uppercase font-bold block">Earned</span>
                            <span class="font-semibold text-gray-800"><?= number_format($item['earned'], 2) ?></span>
                        </div>
                        <div>
                            <span class="text-gray-400 text-[10px] uppercase font-bold block">Used</span>
                            <span class="font-semibold text-gray-800"><?= number_format($item['used'], 2) ?></span>
                        </div>
                        <div>
                            <span class="text-gray-400 text-[10px] uppercase font-bold block">Deducted</span>
                            <span class="font-semibold <?= $item['deducted'] > 0 ? 'text-rose-600' : 'text-gray-400' ?>"><?= $item['deducted'] > 0 ? '-'.number_format($item['deducted'], 2) : '0.00' ?></span>
                        </div>
                        <div>
                            <span class="text-gray-400 text-[10px] uppercase font-bold block">Balance</span>
                            <span class="font-black text-gray-900"><?= number_format($item['balance'], 2) ?></span>
                        </div>
                    </div>

                    <?php if ($item['deducted'] > 0 && !empty($item['deduction_note'])): ?>
                    <div class="text-[11px] text-rose-600 flex items-center gap-1">
                        <i class="fas fa-exclamation-circle text-[10px]"></i>
                        <span>Deducted for <?= escapeOutput($item['deduction_note']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($item['pending'] > 0): ?>
                    <div class="text-[11px] text-amber-700 flex items-center gap-1">
                        <i class="fas fa-clock text-[10px]"></i>
                        <span><?= number_format($item['pending'], 2) ?> day(s) currently awaiting approval</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- CSC Legal / Policy Callout Footer -->
            <div class="px-4 sm:px-5 py-3 border-t border-gray-100 bg-gray-50/50 text-xs text-gray-500 flex items-start gap-2">
                <i class="fas fa-info-circle text-blue-600 mt-0.5 flex-shrink-0"></i>
                <div class="leading-relaxed">
                    <strong>Civil Service Policy (CSC MC No. 41, s. 1998):</strong> Regular employees earn 1.250 days Vacation Leave and 1.250 days Sick Leave per full month of actual service (15 days annually each). Tardiness and undertime hours from daily attendance are converted to days (480 minutes = 1 working day) and deducted against Vacation Leave credits. Sick Leave is protected from tardiness deductions.
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- Leave Requests History Table                                              -->
    <!-- ========================================================================= -->
    <div class="space-y-3">
        <div class="flex items-center justify-between pt-2">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-navy-950 font-heading flex items-center gap-2">
                    <i class="fas fa-file-signature text-blue-600"></i>
                    <?= isAdmin() ? 'All Staff Leave Applications' : 'My Filed Applications' ?>
                </h2>
                <p class="text-xs text-slate-500">Official Civil Service Form No. 6 filing log</p>
            </div>
            <span class="text-xs font-bold text-blue-700 bg-blue-50 border border-blue-200/60 px-2.5 py-1 rounded-full"><?= count($leaves) ?> record(s)</span>
        </div>

        <div class="app-card overflow-hidden">
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-xs sm:text-sm text-left">
                    <thead class="bg-slate-100/75 text-slate-600 text-[11px] font-bold uppercase tracking-wider font-heading border-b border-slate-200/80">
                        <tr>
                            <?php if (isAdmin()): ?><th class="px-6 py-3">Employee</th><?php endif; ?>
                            <th class="px-4 py-3">Leave Type</th>
                            <th class="px-4 py-3">From</th>
                            <th class="px-4 py-3">To</th>
                            <th class="px-4 py-3 text-center">Days</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Filed On</th>
                            <th class="px-6 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($leaves)): ?>
                        <tr><td colspan="<?= isAdmin() ? 8 : 7 ?>" class="px-6 py-12 text-center text-slate-400">No leave requests recorded</td></tr>
                        <?php else: foreach ($leaves as $l): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <?php if (isAdmin()): ?>
                            <td class="px-6 py-3.5">
                                <span class="font-bold text-slate-800 font-heading block"><?= escapeOutput($l['first_name'] . ' ' . $l['last_name']) ?></span>
                                <span class="text-[11px] text-slate-400 font-mono"><?= escapeOutput($l['employee_no']) ?></span>
                            </td>
                            <?php endif; ?>
                            <td class="px-4 py-3.5">
                                <span class="font-bold text-slate-800 font-heading block"><?= escapeOutput($l['leave_type_name']) ?></span>
                                <span class="text-[10px] font-mono text-slate-400 uppercase font-bold"><?= escapeOutput($l['leave_type_code']) ?></span>
                            </td>
                            <td class="px-4 py-3.5 font-mono text-slate-700"><?= formatDate($l['date_from'], 'M j, Y') ?></td>
                            <td class="px-4 py-3.5 font-mono text-slate-700"><?= formatDate($l['date_to'], 'M j, Y') ?></td>
                            <td class="px-4 py-3.5 text-center font-bold font-mono text-blue-700"><?= $l['days'] ?></td>
                            <td class="px-4 py-3.5"><?= getStatusBadge($l['status']) ?></td>
                            <td class="px-4 py-3.5 text-slate-500 font-mono text-xs"><?= formatDate($l['created_at'], 'M j, Y') ?></td>
                            <td class="px-6 py-3.5 text-center">
                                <div class="inline-flex items-center space-x-1.5">
                                    <button onclick="viewLeave(<?= $l['id'] ?>)" type="button" class="px-2.5 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-xs font-bold transition-colors" title="View Official CS Form 6"><i class="fas fa-eye mr-1"></i>View</button>
                                    <?php if (isAdmin() && $l['status'] === 'pending'): ?>
                                    <form method="POST" action="<?= baseUrl() ?>/api/leave.php" class="inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="px-2 py-1 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg text-xs font-bold transition-colors" title="Approve"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" action="<?= baseUrl() ?>/api/leave.php" class="inline" onsubmit="return prompt('Enter disapproval reason:')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="px-2 py-1 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-lg text-xs font-bold transition-colors" title="Disapprove"><i class="fas fa-times"></i></button>
                                    </form>
                                    <?php elseif ($l['status'] === 'pending'): ?>
                                    <form method="POST" action="<?= baseUrl() ?>/api/leave.php" class="inline" onsubmit="return confirm('Cancel this leave application?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="px-2.5 py-1 bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg text-xs font-bold transition-colors"><i class="fas fa-ban mr-1"></i>Cancel</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="sm:hidden divide-y divide-slate-100">
                <?php if (empty($leaves)): ?>
                <p class="p-6 text-sm text-slate-400 text-center">No leave requests found</p>
                <?php else: foreach ($leaves as $l): ?>
                <div class="p-4 space-y-2">
                    <?php if (isAdmin()): ?>
                    <div class="text-xs font-bold text-slate-800"><?= escapeOutput($l['first_name'] . ' ' . $l['last_name']) ?> <span class="font-mono text-slate-400">(<?= escapeOutput($l['employee_no']) ?>)</span></div>
                    <?php endif; ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-navy-950 font-heading"><?= escapeOutput($l['leave_type_name']) ?></span>
                        <?= getStatusBadge($l['status']) ?>
                    </div>
                    <div class="grid grid-cols-2 gap-1 text-xs text-slate-600">
                        <div><span class="text-slate-400">From:</span> <?= formatDate($l['date_from'], 'M j, Y') ?></div>
                        <div><span class="text-slate-400">To:</span> <?= formatDate($l['date_to'], 'M j, Y') ?></div>
                        <div><span class="text-slate-400">Days:</span> <strong class="text-slate-800"><?= $l['days'] ?></strong></div>
                        <div><span class="text-slate-400">Filed:</span> <?= formatDate($l['created_at'], 'M j, Y') ?></div>
                    </div>
                    <div class="text-right pt-2 border-t border-slate-100">
                        <button onclick="viewLeave(<?= $l['id'] ?>)" class="text-xs px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg font-bold"><i class="fas fa-eye mr-1"></i>View Form</button>
                        <?php if (isAdmin() && $l['status'] === 'pending'): ?>
                        <form method="POST" action="<?= baseUrl() ?>/api/leave.php" class="inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="text-xs px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg font-bold"><i class="fas fa-check mr-1"></i>Approve</button>
                        </form>
                        <form method="POST" action="<?= baseUrl() ?>/api/leave.php" class="inline ml-1">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="text-xs px-3 py-1.5 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-lg font-bold"><i class="fas fa-times mr-1"></i>Disapprove</button>
                        </form>
                        <?php elseif ($l['status'] === 'pending'): ?>
                        <form method="POST" action="<?= baseUrl() ?>/api/leave.php" class="inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="text-xs px-3 py-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg font-bold"><i class="fas fa-ban mr-1"></i>Cancel</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="leaveModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-navy-950/70 backdrop-blur-sm p-4" onclick="if(event.target===this)closeLeave()">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[95vh] overflow-hidden flex flex-col border border-slate-200">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800 bg-slate-900 text-white">
            <h3 class="font-bold text-sm sm:text-base font-heading flex items-center space-x-2" id="modalTitle">
                <i class="fas fa-file-invoice text-blue-400"></i>
                <span>Civil Service Form No. 6</span>
            </h3>
            <button onclick="closeLeave()" class="text-white/60 hover:text-white text-xl font-bold">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto flex-1 custom-scrollbar" id="modalBody"></div>
        <div class="flex justify-end gap-2 px-6 py-3 border-t border-slate-100 bg-slate-50" id="modalFooter"></div>
    </div>
</div>
        <div class="flex items-center justify-between p-4 border-b">
            <h3 class="font-semibold text-gray-800" id="modalTitle">CS Form 6</h3>
            <button onclick="closeLeave()" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        </div>
        <div class="p-4" id="modalBody"></div>
        <div class="flex justify-end gap-2 p-4 border-t" id="modalFooter"></div>
    </div>
</div>
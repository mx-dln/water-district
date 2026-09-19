<?php
require_once __DIR__ . '/../../config/app.php';
requireRole('Administrator');

$leave = null;
$employee = null;
$departmentName = '';
$leaveTypeCode = '';
$leaveTypeName = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $leaveId = intval($_GET['id']);
    $empId = isAdmin() ? null : getEmployeeIdFromUser($_SESSION['user_id']);

    $sql = "
        SELECT lr.*,
               e.first_name, e.last_name, e.middle_name, e.employee_no,
               e.position, e.department_id, e.user_id,
               d.name AS department_name,
               lt.name AS leave_type_name, lt.code AS leave_type_code
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.id = ?
    ";
    $params = [$leaveId];
    if ($empId !== null) {
        $sql .= " AND lr.employee_id = ?";
        $params[] = $empId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $leave = $stmt->fetch();

    if ($leave) {
        $employee = $leave;
        $departmentName = $leave['department_name'] ?? '';
        $leaveTypeCode = strtolower($leave['leave_type_code'] ?? '');
        $leaveTypeName = $leave['leave_type_name'] ?? '';
    }
}

if (!$employee) {
    $employee = [
        'last_name' => '',
        'first_name' => '',
        'middle_name' => '',
        'employee_no' => '',
        'position' => '',
    ];
}

$agencyName = getSetting('company_name', '(Agency Name)');
$agencyAddress = getSetting('company_address', '(Agency Address)');
$agencyLogo = getSetting('company_logo', '');

$details = json_decode($leave['details'] ?? '{}', true);
if (!is_array($details)) $details = [];

function dval($details, $key, $default = '') {
    return isset($details[$key]) ? $details[$key] : $default;
}
function isDetailChecked($details, $key) {
    return !empty($details[$key]) ? 'checked' : '';
}

function isTypeChecked($code, $leaveTypeCode) {
    return strtolower($code) === $leaveTypeCode ? 'checked' : '';
}

$dateFrom = $leave['date_from'] ?? '';
$dateTo = $leave['date_to'] ?? '';
$days = $leave['days'] ?? '';
$reason = $leave['reason'] ?? '';
$today = date('Y-m-d');
$filingDate = $leave ? formatDate($leave['created_at'] ?? $today, 'Y-m-d') : $today;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CS Form No. 6 - Application for Leave</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #e5e7eb; font-family: Arial, Helvetica, sans-serif; font-size: 11px; line-height: 1.3; }

        .page-wrap { width: 210mm; margin: 10px auto; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .page-inner { padding: 10mm 10mm 8mm 10mm; }

        .form-checkbox { appearance: none; -webkit-appearance: none; width: 11px; height: 11px; border: 1.2px solid #000; background: #fff; display: inline-block; vertical-align: middle; margin-right: 4px; cursor: pointer; position: relative; flex-shrink: 0; }
        .form-checkbox:checked::after { content: ''; position: absolute; left: 2px; top: -1px; width: 5px; height: 8px; border: solid #000; border-width: 0 1.8px 1.8px 0; transform: rotate(45deg); }

        .uline { border: none; border-bottom: 1px solid #000; background: transparent; width: 100%; font-size: 11px; line-height: 14px; padding: 0 2px; outline: none; font-family: inherit; }
        .uline:focus { border-bottom-width: 1.5px; }
        .iline { border: none; border-bottom: 1px solid #000; background: transparent; width: 100%; font-size: 10px; padding: 0 2px; outline: none; font-family: inherit; }

        .shdr { background: #f3f4f6; border: 1.2px solid #000; text-align: center; font-weight: 700; text-transform: uppercase; font-size: 11px; padding: 2px 4px; }
        .sshdr { font-weight: 700; text-transform: uppercase; font-size: 10px; margin-bottom: 1px; }
        .flbl { font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .fnum { font-weight: 700; margin-right: 2px; }
        .lref { font-size: 8px; font-weight: 400; font-style: normal; }

        .sig { border-top: 1px solid #000; margin-top: 14px; padding-top: 2px; text-align: center; font-size: 9px; }

        .ltbl { width: 100%; border-collapse: collapse; font-size: 10px; }
        .ltbl th, .ltbl td { border: 1px solid #000; padding: 1px 3px; text-align: center; }
        .ltbl th { font-weight: 700; }
        .ltbl td:first-child { text-align: left; font-style: italic; }

        .fbox { border: 1.2px solid #000; }

        .cb-label { display: flex; align-items: flex-start; gap: 3px; font-size: 10px; line-height: 1.2; cursor: pointer; }
        .cb-label .form-checkbox { margin-top: 1px; }
        .field-block { margin-bottom: 2px; }
        .field-block p { font-size: 9px; font-style: italic; font-weight: 600; margin-bottom: 1px; }
        .field-block .iline { font-size: 9px; }

        .no-print { display: block; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; margin: 0; padding: 0; overflow: hidden; }
            .page-wrap { box-shadow: none; margin: 0; width: 100%; overflow: hidden; }
            .page-inner {
                padding: 3mm;
                transform: scale(0.55);
                transform-origin: top left;
                width: 182%;
            }
            @page { size: A4 portrait; margin: 5mm; }
            input, textarea { border-bottom: 1px solid #000 !important; }
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @media (max-width: 768px) {
            .page-wrap { width: 100%; }
            .page-inner { padding: 4mm; }
        }
    </style>
</head>
<body>

<div class="no-print fixed top-3 right-3 z-50 flex gap-2">
    <a href="<?= APP_URL ?>/index.php?page=leaves" class="flex items-center gap-1.5 px-4 py-2 bg-white text-gray-700 border border-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 shadow-lg transition">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<div class="page-wrap">
<div class="page-inner">

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2px;">
        <div style="font-size:9px;font-style:italic;">Civil Service Form No. 6<br>Revised 2020</div>
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;">ANNEX A</div>
    </div>

    <table style="width:100%;border:none;border-collapse:collapse;margin-bottom:0;">
    <tr>
    <td style="width:18%;border:none;text-align:center;vertical-align:top;padding:4px;">
        <?php if ($agencyLogo): ?>
        <img src="<?= APP_URL ?>/<?= escapeOutput($agencyLogo) ?>" style="width:15mm;height:15mm;object-contain;">
        <?php else: ?>
        <div style="width:18mm;height:18mm;border:1px dashed #999;display:flex;align-items:center;justify-content:center;font-size:8px;color:#999;">AGENCY LOGO</div>
        <?php endif; ?>
    </td>
    <td style="width:64%;border:none;text-align:center;vertical-align:top;padding:4px;">
        <div style="font-size:10px;">Republic of the Philippines</div>
        <div style="font-size:12px;font-weight:700;line-height:1.2;"><?= escapeOutput($agencyName) ?></div>
        <div style="font-size:9px;font-style:italic;line-height:1.2;"><?= escapeOutput($agencyAddress) ?></div>
        <div style="font-size:14px;font-weight:700;text-transform:uppercase;margin-top:3px;letter-spacing:0.5px;">Application for Leave</div>
    </td>
    <td style="width:18%;border:none;text-align:right;vertical-align:top;padding:4px;">
        <div style="border:1px solid #000;padding:4px;width:28mm;height:16mm;display:inline-flex;align-items:center;justify-content:center;font-size:8px;text-align:center;">Stamp of Date of Receipt</div>
    </td>
    </tr>
    </table>

    <div class="fbox" style="margin-top:2px;">
        <div style="display:grid;grid-template-columns:4fr 8fr;border-bottom:1px solid #000;">
            <div style="padding:2px 4px;border-right:1px solid #000;">
                <span class="flbl"><span class="fnum">1.</span>Office/Department</span><br>
                <input type="text" class="uline" value="<?= escapeOutput($departmentName) ?>" readonly style="margin-top:1px;">
            </div>
            <div style="padding:2px 4px;">
                <span class="flbl"><span class="fnum">2.</span>Name</span>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px;margin-top:1px;">
                    <div><input type="text" class="uline" value="<?= escapeOutput($employee['last_name']) ?>" readonly><div style="text-align:center;font-size:8px;">(Last)</div></div>
                    <div><input type="text" class="uline" value="<?= escapeOutput($employee['first_name']) ?>" readonly><div style="text-align:center;font-size:8px;">(First)</div></div>
                    <div><input type="text" class="uline" value="<?= escapeOutput($employee['middle_name']) ?>" readonly><div style="text-align:center;font-size:8px;">(Middle)</div></div>
                </div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:3fr 5fr 4fr;">
            <div style="padding:2px 4px;border-right:1px solid #000;">
                <span class="flbl"><span class="fnum">3.</span>Date of Filing</span><br>
                <input type="text" class="uline" value="<?= escapeOutput($filingDate) ?>" readonly style="margin-top:1px;">
            </div>
            <div style="padding:2px 4px;border-right:1px solid #000;">
                <span class="flbl"><span class="fnum">4.</span>Position</span><br>
                <input type="text" class="uline" value="<?= escapeOutput($employee['position']) ?>" readonly style="margin-top:1px;">
            </div>
            <div style="padding:2px 4px;">
                <span class="flbl"><span class="fnum">5.</span>Salary</span><br>
                <input type="text" class="uline" value="<?= escapeOutput(dval($details, 'salary')) ?>" readonly style="margin-top:1px;">
            </div>
        </div>
    </div>

    <div class="fbox" style="margin-top:2px;">
        <div class="shdr">6. Details of Application</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;border-top:1px solid #000;">
            <div style="padding:2px 4px;border-right:1px solid #000;">
                <div class="sshdr">6.A Type of Leave to be Availed Of</div>
                <div style="line-height:1.3;">
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('VL', $leaveTypeCode) ?> disabled>Vacation Leave <span class="lref">(Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('MFL', $leaveTypeCode) ?> disabled>Mandatory/Forced Leave <span class="lref">(Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('SL', $leaveTypeCode) ?> disabled>Sick Leave <span class="lref">(Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('ML', $leaveTypeCode) ?> disabled>Maternity Leave <span class="lref">(R.A. No. 11210 / IRR issued by CSC, DOLE and SSS)</span></label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('PL', $leaveTypeCode) ?> disabled>Paternity Leave <span class="lref">(R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended)</span></label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('SPL', $leaveTypeCode) ?> disabled>Special Privilege Leave <span class="lref">(Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('SPLP', $leaveTypeCode) ?> disabled>Solo Parent Leave <span class="lref">(RA No. 8972 / CSC MC No. 8, s. 2004)</span></label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('STL', $leaveTypeCode) ?> disabled>Study Leave <span class="lref">(Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('VAWC', $leaveTypeCode) ?> disabled>10-Day VAWC Leave <span class="lref">(RA No. 9262 / CSC MC No. 15, s. 2005)</span></label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('RP', $leaveTypeCode) ?> disabled>Rehabilitation Privilege <span class="lref">(Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('SLBW', $leaveTypeCode) ?> disabled>Special Leave Benefits for Women <span class="lref">(RA No. 9710 / CSC MC No. 25, s. 2010)</span></label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('SECL', $leaveTypeCode) ?> disabled>Special Emergency (Calamity) Leave <span class="lref">(CSC MC No. 2, s. 2012, as amended)</span></label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('AL', $leaveTypeCode) ?> disabled>Adoption Leave <span class="lref">(R.A. No. 8552)</span></label>
                    <label class="cb-label" style="margin-top:1px;"><input type="checkbox" class="form-checkbox" <?= isTypeChecked('OTH', $leaveTypeCode) ?> disabled>Others <span class="lref">(Specify)</span></label>
                    <?php if (dval($details, 'others_specify')): ?>
                    <div style="font-size:9px;margin-left:16px;"><?= escapeOutput(dval($details, 'others_specify')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="padding:2px 4px;">
                <div class="sshdr">6.B Details of Leave</div>
                <div class="field-block">
                    <p>In case of Vacation/Special Privilege Leave:</p>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isDetailChecked($details, 'vacation_within') ?> disabled>Within the Philippines</label>
                    <?php if (dval($details, 'vacation_within_specify')): ?><div style="font-size:8px;margin-left:16px;"><?= escapeOutput(dval($details, 'vacation_within_specify')) ?></div><?php endif; ?>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isDetailChecked($details, 'vacation_abroad') ?> disabled>Abroad (Specify)</label>
                    <?php if (dval($details, 'vacation_abroad_specify')): ?><div style="font-size:8px;margin-left:16px;"><?= escapeOutput(dval($details, 'vacation_abroad_specify')) ?></div><?php endif; ?>
                </div>
                <div class="field-block">
                    <p>In case of Sick Leave:</p>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isDetailChecked($details, 'sick_hospital') ?> disabled>In Hospital (Specify Illness)</label>
                    <?php if (dval($details, 'sick_hospital_specify')): ?><div style="font-size:8px;margin-left:16px;"><?= escapeOutput(dval($details, 'sick_hospital_specify')) ?></div><?php endif; ?>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isDetailChecked($details, 'sick_outpatient') ?> disabled>Out Patient (Specify Illness)</label>
                    <?php if (dval($details, 'sick_outpatient_specify')): ?><div style="font-size:8px;margin-left:16px;"><?= escapeOutput(dval($details, 'sick_outpatient_specify')) ?></div><?php endif; ?>
                </div>
                <div class="field-block">
                    <p>In case of Special Leave Benefits for Women: (Specify Illness)</p>
                    <?php if (dval($details, 'special_women_illness')): ?><div style="font-size:9px;"><?= escapeOutput(dval($details, 'special_women_illness')) ?></div><?php endif; ?>
                </div>
                <div class="field-block">
                    <p>In case of Study Leave:</p>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isDetailChecked($details, 'study_masters') ?> disabled>Completion of Master's Degree</label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isDetailChecked($details, 'study_bar') ?> disabled>BAR/Board Examination Review</label>
                </div>
                <div class="field-block">
                    <p>Other purpose:</p>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isDetailChecked($details, 'purpose_monetization') ?> disabled>Monetization of Leave Credits</label>
                    <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= isDetailChecked($details, 'purpose_terminal') ?> disabled>Terminal Leave</label>
                </div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;border-top:1px solid #000;">
            <div style="padding:2px 4px;border-right:1px solid #000;">
                <div class="sshdr">6.C Number of Working Days Applied For</div>
                <div style="font-size:11px;font-weight:600;margin:2px 0;"><?= escapeOutput($days) ?></div>
                <div class="sshdr" style="margin-top:2px;">Inclusive Dates</div>
                <div style="display:flex;gap:8px;margin-top:1px;font-size:10px;">
                    <span><?= escapeOutput($dateFrom) ?></span>
                    <span>&mdash;</span>
                    <span><?= escapeOutput($dateTo) ?></span>
                </div>
            </div>
            <div style="padding:2px 4px;">
                <div class="sshdr">6.D Commutation</div>
                <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= (dval($details, 'commutation', 'not_requested') === 'not_requested') ? 'checked' : '' ?> disabled>Not Requested</label>
                <label class="cb-label"><input type="checkbox" class="form-checkbox" <?= (dval($details, 'commutation', 'not_requested') === 'requested') ? 'checked' : '' ?> disabled>Requested</label>
                <div class="sig">(Signature of Applicant)</div>
            </div>
        </div>
    </div>

    <div class="fbox" style="margin-top:2px;">
        <div class="shdr">7. Details of Action on Application</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;border-top:1px solid #000;">
            <div style="padding:2px 4px;border-right:1px solid #000;">
                <div class="sshdr">7.A Certification of Leave Credits</div>
                <div style="font-size:9px;margin-bottom:2px;">As of _______________</div>
                <table class="ltbl">
                    <tr><th></th><th>Vacation Leave</th><th>Sick Leave</th></tr>
                    <tr><td>Total Earned</td><td></td><td></td></tr>
                    <tr><td>Less this Application</td><td></td><td></td></tr>
                    <tr><td>Balance</td><td></td><td></td></tr>
                </table>
                <div class="sig" style="margin-top:18px;">(Authorized Officer)</div>
            </div>
            <div style="padding:2px 4px;">
                <div class="sshdr">7.B Recommendation</div>
                <label class="cb-label"><input type="checkbox" class="form-checkbox" disabled>For approval</label>
                <label class="cb-label"><input type="checkbox" class="form-checkbox" disabled>For disapproval due to ___________________</label>
                <div style="border:1px solid #000;min-height:18px;margin-top:2px;padding:2px;font-size:8px;"></div>
                <div class="sig" style="margin-top:18px;">(Authorized Officer)</div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;border-top:1px solid #000;">
            <div style="padding:2px 4px;border-right:1px solid #000;">
                <div class="sshdr">7.C Approved For:</div>
                <div style="font-size:10px;line-height:1.5;">
                    _______ days with pay<br>
                    _______ days without pay<br>
                    _______ others (Specify)
                </div>
                <div class="sig" style="margin-top:18px;">(Authorized Official)</div>
            </div>
            <div style="padding:2px 4px;">
                <div class="sshdr">7.D Disapproved Due To:</div>
                <div style="border:1px solid #000;min-height:30px;margin-top:2px;padding:2px;font-size:8px;"></div>
                <div class="sig" style="margin-top:18px;">(Authorized Official)</div>
            </div>
        </div>
    </div>

</div>
</div>

</body>
</html>

<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
requireRole('Administrator');

use Dompdf\Dompdf;
use Dompdf\Options;

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setFlash('Invalid leave request', 'danger');
    redirect(APP_URL . '/index.php?page=leaves');
}

$stmt = $db->prepare("
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
");
$stmt->execute([$id]);
$leave = $stmt->fetch();

if (!$leave) {
    setFlash('Leave request not found', 'danger');
    redirect(APP_URL . '/index.php?page=leaves');
}

$employee = $leave;
$departmentName = $leave['department_name'] ?? '';
$leaveTypeCode = strtolower($leave['leave_type_code'] ?? '');
$details = json_decode($leave['details'] ?? '{}', true);
if (!is_array($details)) $details = [];

$agencyName = getSetting('company_name', '(Agency Name)');
$agencyAddress = getSetting('company_address', '(Agency Address)');
$agencyLogo = getSetting('company_logo', '');

function dpval($details, $key, $default = '') {
    return isset($details[$key]) ? $details[$key] : $default;
}
function dpchecked($details, $key) {
    return !empty($details[$key]);
}
function dptypechecked($code, $leaveTypeCode) {
    return strtolower($code) === $leaveTypeCode;
}

$dateFrom = $leave['date_from'] ?? '';
$dateTo = $leave['date_to'] ?? '';
$days = $leave['days'] ?? '';
$filingDate = date('Y-m-d', strtotime($leave['created_at'] ?? 'now'));

$logoData = '';
if ($agencyLogo) {
    $logoPath = __DIR__ . '/../' . $agencyLogo;
    if (file_exists($logoPath)) {
        $mime = mime_content_type($logoPath);
        $data = file_get_contents($logoPath);
        $logoData = 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}

function cbHtml($checked) {
    if ($checked) {
        return '<span style="display:inline-block;width:10pt;height:10pt;border:0.5pt solid #000;vertical-align:middle;margin-right:3pt;position:relative;"><span style="position:absolute;left:2pt;top:0pt;width:4pt;height:7pt;border:solid #000;border-width:0 1pt 1pt 0;transform:rotate(45deg);"></span></span>';
    }
    return '<span style="display:inline-block;width:10pt;height:10pt;border:0.5pt solid #000;vertical-align:middle;margin-right:3pt;"></span>';
}

$html = '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; line-height: 1.3; color: #000; padding: 10mm 12mm 10mm 12mm; }
table.fmain { width: 100%; border-collapse: collapse; }
table.fmain > tbody > tr > td { vertical-align: top; }
.cb { display: inline-block; width: 10pt; height: 10pt; border: 0.6pt solid #000; vertical-align: middle; margin-right: 3pt; position: relative; }
.cb.ck::after { content: ""; position: absolute; left: 2pt; top: -1pt; width: 4pt; height: 8pt; border: solid #000; border-width: 0 1.2pt 1.2pt 0; transform: rotate(45deg); }
.sig { border-top: 0.6pt solid #000; margin-top: 16pt; padding-top: 2pt; text-align: center; font-size: 7.5pt; }
.fbox { border: 0.6pt solid #000; margin-top: 3pt; }
.shdr { background: #f0f0f0; border: 0.6pt solid #000; text-align: center; font-weight: bold; text-transform: uppercase; font-size: 9.5pt; padding: 2pt 3pt; }
.sshdr { font-weight: bold; text-transform: uppercase; font-size: 8.5pt; margin-bottom: 1pt; }
.cbr { font-size: 8.5pt; line-height: 1.35; margin-bottom: 0; }
.lref { font-size: 6.5pt; }
.fp { font-size: 8pt; font-style: italic; font-weight: 600; margin-bottom: 0; }
.dv { font-size: 8pt; margin-left: 14pt; }
table.dtbl { width: 100%; border-collapse: collapse; }
table.dtbl td, table.dtbl th { border: 0.6pt solid #000; padding: 1.5pt 3pt; font-size: 7.5pt; }
table.dtbl th { font-weight: bold; }
table.dtbl td:first-child { text-align: left; font-style: italic; }
</style></head><body>';

$html .= '<table style="width:100%;border:none;border-collapse:collapse;">';
$html .= '<tr>';
$html .= '<td style="width:20mm;border:none;text-align:center;vertical-align:top;padding:2pt;">';
if ($logoData) {
    $html .= '<img src="' . $logoData . '" style="width:18mm;height:18mm;">';
} else {
    $html .= '<div style="width:20mm;height:20mm;border:0.5pt dashed #999;margin:0 auto;"></div>';
}
$html .= '</td>';
$html .= '<td style="border:none;text-align:center;vertical-align:top;padding:2pt;">';
$html .= '<div style="font-size:8pt;">Republic of the Philippines</div>';
$html .= '<div style="font-size:10pt;font-weight:bold;">' . htmlspecialchars($agencyName) . '</div>';
$html .= '<div style="font-size:7.5pt;font-style:italic;">' . htmlspecialchars($agencyAddress) . '</div>';
$html .= '<div style="font-size:12pt;font-weight:bold;text-transform:uppercase;margin-top:2pt;">APPLICATION FOR LEAVE</div>';
$html .= '</td>';
$html .= '<td style="width:28mm;border:none;text-align:right;vertical-align:top;padding:2pt;">';
$html .= '<div style="border:0.6pt solid #000;padding:2pt;width:24mm;height:14mm;display:inline-flex;align-items:center;justify-content:center;font-size:7pt;text-align:center;">Stamp of Date<br>of Receipt</div>';
$html .= '</td>';
$html .= '</tr></table>';

$html .= '<div class="fbox">';
$html .= '<table style="width:100%;border-collapse:collapse;">';
$html .= '<tr>';
$html .= '<td style="width:35%;border-bottom:0.5pt solid #000;border-right:0.5pt solid #000;padding:3pt 4pt;"><b>1. OFFICE/DEPARTMENT</b><br><span style="font-size:8pt;">' . htmlspecialchars($departmentName) . '</span></td>';
$html .= '<td style="border-bottom:0.5pt solid #000;padding:3pt 4pt;"><b>2. NAME</b><br>';
$html .= '<table style="width:100%;border:none;border-collapse:collapse;"><tr>';
$html .= '<td style="border:none;width:33%;"><span style="font-size:8pt;">' . htmlspecialchars($employee['last_name']) . '</span><br><span style="font-size:6pt;text-align:center;display:block;">(Last)</span></td>';
$html .= '<td style="border:none;width:33%;"><span style="font-size:8pt;">' . htmlspecialchars($employee['first_name']) . '</span><br><span style="font-size:6pt;text-align:center;display:block;">(First)</span></td>';
$html .= '<td style="border:none;width:33%;"><span style="font-size:8pt;">' . htmlspecialchars($employee['middle_name'] ?? '') . '</span><br><span style="font-size:6pt;text-align:center;display:block;">(Middle)</span></td>';
$html .= '</tr></table></td>';
$html .= '</tr></table>';

$html .= '<table style="width:100%;border-collapse:collapse;">';
$html .= '<tr>';
$html .= '<td style="width:28%;border-right:0.5pt solid #000;padding:3pt 4pt;"><b>3. DATE OF FILING</b><br><span style="font-size:8pt;">' . htmlspecialchars($filingDate) . '</span></td>';
$html .= '<td style="width:42%;border-right:0.5pt solid #000;padding:3pt 4pt;"><b>4. POSITION</b><br><span style="font-size:8pt;">' . htmlspecialchars($employee['position']) . '</span></td>';
$html .= '<td style="padding:3pt 4pt;"><b>5. SALARY</b><br><span style="font-size:8pt;">' . htmlspecialchars(dpval($details, 'salary')) . '</span></td>';
$html .= '</tr></table></div>';

$html .= '<div class="fbox">';
$html .= '<div class="shdr">6. DETAILS OF APPLICATION</div>';

$html .= '<table style="width:100%;border-collapse:collapse;border-top:0.5pt solid #000;"><tr>';
$html .= '<td style="width:50%;border-right:0.5pt solid #000;padding:3pt 4pt;vertical-align:top;">';
$html .= '<div class="sshdr">6.A TYPE OF LEAVE TO BE AVAILED OF</div>';

$leaveTypes = [
    ['VL', 'Vacation Leave', 'Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
    ['MFL', 'Mandatory/Forced Leave', 'Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
    ['SL', 'Sick Leave', 'Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
    ['ML', 'Maternity Leave', 'R.A. No. 11210 / IRR issued by CSC, DOLE and SSS'],
    ['PL', 'Paternity Leave', 'R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended'],
    ['SPL', 'Special Privilege Leave', 'Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
    ['SPLP', 'Solo Parent Leave', 'RA No. 8972 / CSC MC No. 8, s. 2004'],
    ['STL', 'Study Leave', 'Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
    ['VAWC', '10-Day VAWC Leave', 'RA No. 9262 / CSC MC No. 15, s. 2005'],
    ['RP', 'Rehabilitation Privilege', 'Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
    ['SLBW', 'Special Leave Benefits for Women', 'RA No. 9710 / CSC MC No. 25, s. 2010'],
    ['SECL', 'Special Emergency (Calamity) Leave', 'CSC MC No. 2, s. 2012, as amended'],
    ['AL', 'Adoption Leave', 'R.A. No. 8552'],
    ['OTH', 'Others (Specify)', ''],
];

foreach ($leaveTypes as $lt) {
    $ck = dptypechecked($lt[0], $leaveTypeCode);
    $html .= '<div class="cbr">' . cbHtml($ck) . htmlspecialchars($lt[1]);
    if ($lt[2]) $html .= ' <span class="lref">(' . htmlspecialchars($lt[2]) . ')</span>';
    $html .= '</div>';
}

if (dpval($details, 'others_specify')) {
    $html .= '<div class="dv">' . htmlspecialchars(dpval($details, 'others_specify')) . '</div>';
}

$html .= '</td>';

$html .= '<td style="padding:3pt 4pt;vertical-align:top;">';
$html .= '<div class="sshdr">6.B DETAILS OF LEAVE</div>';

$html .= '<div class="fp">In case of Vacation/Special Privilege Leave:</div>';
$html .= '<div class="cbr">' . cbHtml(dpchecked($details, 'vacation_within')) . 'Within the Philippines</div>';
if (dpval($details, 'vacation_within_specify')) $html .= '<div class="dv">' . htmlspecialchars(dpval($details, 'vacation_within_specify')) . '</div>';
$html .= '<div class="cbr">' . cbHtml(dpchecked($details, 'vacation_abroad')) . 'Abroad (Specify)</div>';
if (dpval($details, 'vacation_abroad_specify')) $html .= '<div class="dv">' . htmlspecialchars(dpval($details, 'vacation_abroad_specify')) . '</div>';

$html .= '<div class="fp" style="margin-top:2pt;">In case of Sick Leave:</div>';
$html .= '<div class="cbr">' . cbHtml(dpchecked($details, 'sick_hospital')) . 'In Hospital (Specify Illness)</div>';
if (dpval($details, 'sick_hospital_specify')) $html .= '<div class="dv">' . htmlspecialchars(dpval($details, 'sick_hospital_specify')) . '</div>';
$html .= '<div class="cbr">' . cbHtml(dpchecked($details, 'sick_outpatient')) . 'Out Patient (Specify Illness)</div>';
if (dpval($details, 'sick_outpatient_specify')) $html .= '<div class="dv">' . htmlspecialchars(dpval($details, 'sick_outpatient_specify')) . '</div>';

$html .= '<div class="fp" style="margin-top:2pt;">In case of Special Leave Benefits for Women: (Specify Illness)</div>';
if (dpval($details, 'special_women_illness')) $html .= '<div class="dv">' . htmlspecialchars(dpval($details, 'special_women_illness')) . '</div>';

$html .= '<div class="fp" style="margin-top:2pt;">In case of Study Leave:</div>';
$html .= '<div class="cbr">' . cbHtml(dpchecked($details, 'study_masters')) . 'Completion of Master\'s Degree</div>';
$html .= '<div class="cbr">' . cbHtml(dpchecked($details, 'study_bar')) . 'BAR/Board Examination Review</div>';

$html .= '<div class="fp" style="margin-top:2pt;">Other purpose:</div>';
$html .= '<div class="cbr">' . cbHtml(dpchecked($details, 'purpose_monetization')) . 'Monetization of Leave Credits</div>';
$html .= '<div class="cbr">' . cbHtml(dpchecked($details, 'purpose_terminal')) . 'Terminal Leave</div>';

$html .= '</td></tr></table>';

$html .= '<table style="width:100%;border-collapse:collapse;border-top:0.5pt solid #000;"><tr>';
$html .= '<td style="width:50%;border-right:0.5pt solid #000;padding:3pt 4pt;">';
$html .= '<div class="sshdr">6.C NUMBER OF WORKING DAYS APPLIED FOR</div>';
$html .= '<div style="font-size:9pt;font-weight:600;">' . htmlspecialchars($days) . '</div>';
$html .= '<div class="sshdr" style="margin-top:3pt;">INCLUSIVE DATES</div>';
$html .= '<div style="font-size:8pt;">' . htmlspecialchars($dateFrom) . ' &mdash; ' . htmlspecialchars($dateTo) . '</div>';
$html .= '</td>';

$html .= '<td style="padding:3pt 4pt;">';
$html .= '<div class="sshdr">6.D COMMUTATION</div>';
$html .= '<div class="cbr">' . cbHtml(dpval($details, 'commutation', 'not_requested') === 'not_requested') . 'Not Requested</div>';
$html .= '<div class="cbr">' . cbHtml(dpval($details, 'commutation', 'not_requested') === 'requested') . 'Requested</div>';
$html .= '<div class="sig">(Signature of Applicant)</div>';
$html .= '</td></tr></table></div>';

$html .= '<div class="fbox">';
$html .= '<div class="shdr">7. DETAILS OF ACTION ON APPLICATION</div>';

$html .= '<table style="width:100%;border-collapse:collapse;border-top:0.5pt solid #000;"><tr>';
$html .= '<td style="width:50%;border-right:0.5pt solid #000;padding:3pt 4pt;vertical-align:top;">';
$html .= '<div class="sshdr">7.A CERTIFICATION OF LEAVE CREDITS</div>';
$html .= '<div style="font-size:7.5pt;">As of _______________</div>';
$html .= '<table class="dtbl" style="margin-top:2pt;"><tr><th style="width:40%;"></th><th style="width:30%;">Vacation Leave</th><th style="width:30%;">Sick Leave</th></tr>';
$html .= '<tr><td>Total Earned</td><td></td><td></td></tr>';
$html .= '<tr><td>Less this Application</td><td></td><td></td></tr>';
$html .= '<tr><td>Balance</td><td></td><td></td></tr></table>';
$html .= '<div class="sig">(Authorized Officer)</div>';
$html .= '</td>';

$html .= '<td style="padding:3pt 4pt;vertical-align:top;">';
$html .= '<div class="sshdr">7.B RECOMMENDATION</div>';
$html .= '<div class="cbr">' . cbHtml(false) . 'For approval</div>';
$html .= '<div class="cbr">' . cbHtml(false) . 'For disapproval due to ________________</div>';
$html .= '<div style="border:0.5pt solid #000;min-height:15pt;margin-top:3pt;font-size:7pt;"></div>';
$html .= '<div class="sig">(Authorized Officer)</div>';
$html .= '</td></tr></table>';

$html .= '<table style="width:100%;border-collapse:collapse;border-top:0.5pt solid #000;"><tr>';
$html .= '<td style="width:50%;border-right:0.5pt solid #000;padding:3pt 4pt;vertical-align:top;">';
$html .= '<div class="sshdr">7.C APPROVED FOR:</div>';
$html .= '<div style="font-size:8pt;line-height:1.8;">_______ days with pay<br>_______ days without pay<br>_______ others (Specify)</div>';
$html .= '<div class="sig">(Authorized Official)</div>';
$html .= '</td>';

$html .= '<td style="padding:3pt 4pt;vertical-align:top;">';
$html .= '<div class="sshdr">7.D DISAPPROVED DUE TO:</div>';
$html .= '<div style="border:0.5pt solid #000;min-height:20pt;margin-top:3pt;font-size:7pt;"></div>';
$html .= '<div class="sig">(Authorized Official)</div>';
$html .= '</td></tr></table></div>';

$html .= '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');
$options->set('isFontSubsettingEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'CS-Form-6_' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $employee['last_name'] ?? 'employee') . '_' . $leave['id'] . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;

<?php
// The office face kiosk is an administrative workstation and must not be
// accessible using an employee account, even when the URL is entered directly.
requireRole('Administrator');

$companyName = getSetting('company_name', 'Cauayan City Water District');
$companyLogo = getSetting('company_logo', '');
$enrolledCount = $db->query("SELECT COUNT(DISTINCT employee_id) FROM employee_faces")->fetchColumn();
$totalEmployees = $db->query("SELECT COUNT(*) FROM employees WHERE is_active = 1")->fetchColumn();

// Get today's recent face punches
$today = date('Y-m-d');
$recentPunches = $db->prepare("
    SELECT 
        a.id, a.employee_id, a.date, a.time_in, a.time_out, a.break_in, a.break_out,
        a.time_in_method, a.time_out_method, a.break_in_method, a.break_out_method,
        e.employee_no, e.first_name, e.last_name, e.profile_pic,
        d.name as department_name,
        a.updated_at
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE a.date = ? 
      AND (a.time_in_method = 'face' OR a.time_out_method = 'face' OR a.break_in_method = 'face' OR a.break_out_method = 'face')
    ORDER BY a.updated_at DESC
    LIMIT 10
");
$recentPunches->execute([$today]);
$recentRows = $recentPunches->fetchAll();

$parsedRecent = [];
foreach ($recentRows as $r) {
    $latestLabel = 'Morning In';
    $latestTime = $r['time_in'];
    $color = 'green';

    if ($r['time_out_method'] === 'face' && $r['time_out']) {
        $latestLabel = 'Afternoon Out'; $latestTime = $r['time_out']; $color = 'red';
    } elseif ($r['break_in_method'] === 'face' && $r['break_in']) {
        $latestLabel = 'Afternoon In'; $latestTime = $r['break_in']; $color = 'blue';
    } elseif ($r['break_out_method'] === 'face' && $r['break_out']) {
        $latestLabel = 'Morning Out'; $latestTime = $r['break_out']; $color = 'yellow';
    } elseif ($r['time_in_method'] === 'face' && $r['time_in']) {
        $latestLabel = 'Morning In'; $latestTime = $r['time_in']; $color = 'green';
    }

    $parsedRecent[] = [
        'name' => $r['first_name'] . ' ' . $r['last_name'],
        'employee_no' => $r['employee_no'],
        'punch_label' => $latestLabel,
        'color' => $color,
        'time' => date('h:i A', strtotime($latestTime)),
        'profile_pic' => $r['profile_pic'] ? baseUrl('/' . $r['profile_pic']) : null,
    ];
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Office Attendance Kiosk - <?= escapeOutput($companyName) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- face-api.js library -->
    <script src="<?= baseUrl('/assets/js/face-api.min.js') ?>"></script>
    <script>
        if (typeof faceapi === 'undefined') {
            document.write('<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"><\/script>');
        }
    </script>
    <style>
        :root {
            --kiosk-ink: #0f172a;
            --kiosk-blue: #2563eb;
            --kiosk-sky: #eff6ff;
            --kiosk-line: #dbe4f0;
        }
        body.kiosk-shell {
            background: #f5f8fc;
            color: var(--kiosk-ink);
        }
        .kiosk-header {
            background: linear-gradient(115deg, #0f2748 0%, #123b68 55%, #17617a 100%);
            border-bottom: 0;
            color: white;
            box-shadow: 0 10px 28px rgba(15, 39, 72, .16);
        }
        .kiosk-header h1, .kiosk-header .text-gray-900, .kiosk-header .text-gray-800 { color: white !important; }
        .kiosk-header .text-gray-500 { color: #bfdbfe !important; }
        .kiosk-header .border-gray-200 { border-color: rgba(255,255,255,.18) !important; }
        .kiosk-header .bg-gray-100 { background: rgba(255,255,255,.12) !important; color: white !important; border-color: rgba(255,255,255,.16) !important; }
        .kiosk-header .bg-gray-100:hover { background: rgba(255,255,255,.2) !important; color: white !important; }
        .kiosk-main { max-width: 100%; width: 100%; }
        .kiosk-card {
            border-color: var(--kiosk-line) !important;
            box-shadow: 0 10px 30px rgba(30, 64, 100, .06) !important;
        }
        .sequence-card { background: linear-gradient(135deg, #ffffff 0%, #f7fbff 100%); }
        .sequence-step { transition: transform .2s ease, box-shadow .2s ease; }
        .sequence-step:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(37,99,235,.08); }
        .camera-card { background: #ffffff; }
        .camera-stage {
            background: #071523 !important;
            border: 6px solid #e7eef7 !important;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.08), 0 14px 30px rgba(15, 39, 72, .12) !important;
        }
        .camera-stage:after {
            content: 'FACE VERIFICATION';
            position: absolute; left: 18px; top: 16px; z-index: 20;
            padding: 6px 10px; border: 1px solid rgba(255,255,255,.22); border-radius: 999px;
            color: #dbeafe; background: rgba(7,21,35,.62); backdrop-filter: blur(8px);
            font-size: 10px; font-weight: 800; letter-spacing: .14em;
        }
        .camera-footer { background: #f8fbff; border-radius: 12px; padding: 10px 12px; }
        .side-card { background: rgba(255,255,255,.96); }
        .metric-number { color: #123b68; letter-spacing: -.04em; }
        .live-empty { min-height: 240px; display: flex; align-items: center; justify-content: center; flex-direction: column; }
        @media (max-width: 1023px) {
            body.kiosk-shell { overflow: auto; }
            .kiosk-header { position: sticky; top: 0; }
            .camera-stage { min-height: 420px !important; }
        }
        #video, #overlay {
            object-fit: cover;
        }
        @keyframes pulseBorder {
            0%, 100% { border-color: rgba(16, 185, 129, 0.4); }
            50% { border-color: rgba(16, 185, 129, 0.9); }
        }
        .guide-target {
            animation: pulseBorder 2.5s ease-in-out infinite;
        }
    </style>
</head>
<body class="kiosk-shell h-full text-gray-800 flex flex-col overflow-hidden select-none font-sans">

    <!-- Top Navigation Bar (Matches GeoSnap Header Style) -->
    <header class="kiosk-header px-6 py-3 flex items-center justify-between flex-shrink-0 z-20">
        <div class="flex items-center space-x-3.5">
            <?php if ($companyLogo && file_exists(__DIR__ . '/../../' . $companyLogo)): ?>
                <img src="<?= baseUrl('/' . $companyLogo) ?>" alt="Logo" class="h-10 w-10 object-contain rounded-lg border p-1 bg-white">
            <?php else: ?>
                <div class="h-10 w-10 rounded-xl bg-blue-700 flex items-center justify-center text-white shadow">
                    <i class="fas fa-camera text-lg"></i>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="text-base sm:text-lg font-bold tracking-tight text-gray-900 flex items-center gap-2">
                    <?= escapeOutput($companyName) ?>
                    <span class="text-[11px] font-semibold uppercase px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-full">Office Kiosk</span>
                </h1>
                <div class="flex items-center space-x-2 text-xs text-gray-500">
                    <span id="statusDot" class="w-2.5 h-2.5 rounded-full bg-yellow-400 animate-pulse"></span>
                    <span id="kioskStatusText" class="truncate max-w-md font-medium">Initializing AI models & camera...</span>
                </div>
            </div>
        </div>

        <!-- Clock & Controls -->
        <div class="flex items-center space-x-4">
            <div class="text-right hidden sm:block">
                <div id="kioskClock" class="text-2xl font-bold tracking-tight text-gray-800 font-mono leading-none">--:--:-- --</div>
                <div id="kioskDate" class="text-xs text-gray-500 font-medium mt-1">--------, -- ----</div>
            </div>

            <div class="flex items-center space-x-2 border-l pl-4 border-gray-200">
                <button id="toggleSoundBtn" title="Toggle Sound" class="w-9 h-9 rounded-lg border border-gray-200 hover:bg-gray-100 text-gray-600 flex items-center justify-center transition">
                    <i class="fas fa-volume-up text-gray-700"></i>
                </button>
                <button id="fullscreenBtn" title="Toggle Fullscreen" class="w-9 h-9 rounded-lg border border-gray-200 hover:bg-gray-100 text-gray-600 flex items-center justify-center transition">
                    <i class="fas fa-expand text-gray-700"></i>
                </button>
                <a href="<?= baseUrl('/index.php?page=attendance') ?>" title="Exit Kiosk" class="px-3.5 py-1.5 rounded-lg bg-gray-100 hover:bg-red-50 text-gray-700 hover:text-red-700 border border-gray-200 hover:border-red-200 text-xs font-semibold flex items-center gap-1.5 transition">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Grid (12-Column Responsive Split Screen) -->
    <div class="kiosk-main flex-1 grid grid-cols-1 lg:grid-cols-12 gap-5 p-4 sm:p-5 w-full max-w-[1750px] mx-auto overflow-hidden">

        <!-- Left Column: Camera Stage & 4-Stage Sequence (7 Columns) -->
        <div class="lg:col-span-7 xl:col-span-7 flex flex-col space-y-3.5 h-full overflow-hidden">

            <!-- 4-Stage Automatic Punch Indicator -->
            <div class="kiosk-card sequence-card rounded-2xl border p-3.5 sm:p-4 flex-shrink-0">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-700 flex items-center gap-1.5">
                        <i class="fas fa-bolt text-amber-500"></i> Automatic Attendance Sequencing
                    </span>
                    <span class="text-[11px] text-gray-500 bg-gray-100/90 border border-gray-200 px-2.5 py-0.5 rounded-full font-medium">Hands-free &bull; No touching required</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
                    <div class="sequence-step p-2 rounded-xl border border-green-200 bg-green-50/70 text-green-800">
                        <div class="text-[10px] font-bold uppercase text-green-600 tracking-wider">1st Scan</div>
                        <div class="text-xs sm:text-sm font-bold flex items-center justify-center gap-1 mt-0.5">
                            <i class="fas fa-sign-in-alt text-xs"></i> Morning In
                        </div>
                    </div>
                    <div class="sequence-step p-2 rounded-xl border border-amber-200 bg-amber-50/70 text-amber-800">
                        <div class="text-[10px] font-bold uppercase text-amber-600 tracking-wider">2nd Scan</div>
                        <div class="text-xs sm:text-sm font-bold flex items-center justify-center gap-1 mt-0.5">
                            <i class="fas fa-utensils text-xs"></i> Morning Out
                        </div>
                    </div>
                    <div class="sequence-step p-2 rounded-xl border border-blue-200 bg-blue-50/70 text-blue-800">
                        <div class="text-[10px] font-bold uppercase text-blue-600 tracking-wider">3rd Scan</div>
                        <div class="text-xs sm:text-sm font-bold flex items-center justify-center gap-1 mt-0.5">
                            <i class="fas fa-briefcase text-xs"></i> Afternoon In
                        </div>
                    </div>
                    <div class="sequence-step p-2 rounded-xl border border-rose-200 bg-rose-50/70 text-rose-800">
                        <div class="text-[10px] font-bold uppercase text-rose-600 tracking-wider">4th Scan</div>
                        <div class="text-xs sm:text-sm font-bold flex items-center justify-center gap-1 mt-0.5">
                            <i class="fas fa-sign-out-alt text-xs"></i> Afternoon Out
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webcam Viewport Card -->
            <div class="kiosk-card camera-card rounded-2xl border p-3.5 sm:p-4 flex-1 min-h-0 flex flex-col">
                <div class="camera-stage relative flex-1 min-h-[360px] sm:min-h-[440px] rounded-xl overflow-hidden flex items-center justify-center">
                    <!-- Video Element -->
                    <video id="video" class="w-full h-full absolute inset-0 z-0" autoplay muted playsinline></video>
                    <!-- Canvas HUD Overlay -->
                    <canvas id="overlay" class="w-full h-full absolute inset-0 z-10 pointer-events-none"></canvas>

                    <!-- Guide Target Oval -->
                    <div class="absolute inset-0 pointer-events-none z-10 flex items-center justify-center opacity-60">
                        <div class="w-60 h-72 sm:w-72 sm:h-84 rounded-[3rem] border-2 border-dashed guide-target relative">
                            <!-- Center horizontal alignment line -->
                            <div class="absolute inset-x-0 top-1/2 h-px bg-emerald-400/30"></div>
                        </div>
                    </div>
                </div>

                <!-- Camera Controls & System Status Footer -->
                <div class="camera-footer mt-3 flex items-center justify-between text-xs text-gray-500 flex-shrink-0">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-video text-blue-600"></i>
                        <select id="cameraSelect" class="bg-white border border-gray-200 text-gray-700 text-xs rounded-lg px-2.5 py-1 focus:ring-1 focus:ring-blue-500 focus:outline-none">
                            <option value="">Default Webcam</option>
                        </select>
                        <button id="flipCameraBtn" type="button" title="Flip / Invert camera view" class="flex items-center gap-1.5 px-2.5 py-1 bg-white hover:bg-gray-100 border border-gray-200 text-gray-700 text-xs rounded-lg transition font-medium">
                            <i class="fas fa-right-left text-gray-500"></i>
                            <span id="flipBtnText">Normal View</span>
                        </button>
                    </div>
                    <div class="flex items-center space-x-3 text-xs">
                        <span><i class="fas fa-shield-alt text-emerald-600 mr-1"></i>1-Minute Duplicate Lock</span>
                        <span class="text-gray-300">|</span>
                        <span>Enrolled Staff: <strong class="text-gray-800" id="enrolledCount"><?= (int)$enrolledCount ?></strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Attended Employee Showcase & Live Activity (5 Columns) -->
        <div class="lg:col-span-5 xl:col-span-5 flex flex-col space-y-3.5 h-full overflow-hidden">

            <!-- Dynamic Showcase Card: Attended Employee or Standby Mode -->
            <div id="showcaseContainer" class="kiosk-card rounded-2xl border bg-white p-4 sm:p-5 flex-shrink-0 transition-all duration-300">
                
                <!-- State A: Active Attended / Recognized Employee Showcase -->
                <div id="attendedEmployeeShowcase" class="hidden">
                    <!-- Top Status Bar & 5s Countdown Timer -->
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-3">
                        <div class="flex items-center space-x-2">
                            <span id="showcaseBadge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shadow-sm animate-pulse">
                                <i class="fas fa-check-circle text-emerald-600"></i>
                                <span id="showcaseBadgeText">Attendance Recorded</span>
                            </span>
                            <span id="showcaseMethodBadge" class="text-[10px] font-semibold uppercase px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-md">
                                Face Verified
                            </span>
                        </div>
                        
                        <!-- 5s Auto-dismiss countdown -->
                        <div class="flex items-center space-x-1.5 text-xs text-gray-500">
                            <span class="text-[11px] font-medium text-gray-400">Clears in</span>
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-50 text-blue-700 font-bold font-mono text-xs border border-blue-200" id="showcaseTimerSec">5</span>
                        </div>
                    </div>

                    <!-- Animated Progress Line (5s countdown) -->
                    <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden mb-3.5">
                        <div id="showcaseProgressBar" class="bg-blue-600 h-full rounded-full transition-all duration-1000 ease-linear w-full"></div>
                    </div>

                    <!-- Employee Profile Header -->
                    <div class="flex items-center space-x-3.5 mb-3.5">
                        <div id="showcaseAvatarWrap" class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold flex items-center justify-center text-xl flex-shrink-0 shadow-md border-2 border-white ring-2 ring-blue-100 overflow-hidden">
                            <span id="showcaseAvatarInitials">--</span>
                            <img id="showcaseAvatarImg" src="" class="w-full h-full object-cover hidden" alt="Avatar">
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 id="showcaseName" class="text-base sm:text-lg font-black text-gray-900 truncate">Employee Name</h2>
                            <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                <span id="showcaseEmpNo" class="text-xs font-mono font-bold text-gray-600 bg-gray-100 px-2 py-0.5 rounded-md">EMP-000</span>
                                <span id="showcaseDept" class="text-xs text-gray-500 font-medium truncate">Department</span>
                            </div>
                        </div>
                    </div>

                    <!-- Most Recent Punch Highlight Banner -->
                    <div id="showcasePunchHighlight" class="p-3 rounded-xl bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 border border-emerald-200 mb-3 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 block">Recent Office Punch</span>
                            <span id="showcasePunchLabel" class="text-sm sm:text-base font-extrabold text-emerald-900 mt-0.5 block">Morning In</span>
                        </div>
                        <div class="text-right">
                            <span id="showcasePunchTime" class="text-xl sm:text-2xl font-black text-emerald-700 font-mono leading-none">--:-- --</span>
                            <span class="text-[10px] font-semibold text-emerald-600 block mt-1"><i class="fas fa-check-circle"></i> Verified</span>
                        </div>
                    </div>

                    <!-- Cooldown / Notice Alert Banner (Shown when employee is already logged in within 1 minute) -->
                    <div id="showcaseNotice" class="hidden mb-3 p-3 bg-amber-50 border-2 border-amber-300 rounded-xl text-amber-900 text-xs font-bold flex items-center gap-2.5 shadow-sm">
                        <div class="w-8 h-8 rounded-lg bg-amber-500 text-white flex items-center justify-center flex-shrink-0 text-sm shadow">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="text-[10px] uppercase font-black tracking-wider text-amber-700 block">Attendance Status</span>
                            <span id="showcaseNoticeText" class="text-xs sm:text-sm font-black text-amber-900 block leading-tight">You are already logged in. Please come back after 1 min.</span>
                        </div>
                    </div>

                    <!-- Today's 4 Attendance Punches Status Grid -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-bold uppercase text-gray-500 tracking-wider">Today's Attendance Punches</span>
                            <span id="showcasePunchCount" class="text-[10px] font-semibold text-gray-400">0 of 4 Recorded</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-center">
                            <!-- Morning In -->
                            <div id="punchBox_time_in" class="p-2 rounded-xl border border-gray-200 bg-gray-50/70 transition">
                                <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">1. Morning In</span>
                                <span id="punchVal_time_in" class="text-xs sm:text-sm font-bold text-gray-700 font-mono mt-0.5 block">--:--</span>
                            </div>
                            <!-- Morning Out -->
                            <div id="punchBox_break_out" class="p-2 rounded-xl border border-gray-200 bg-gray-50/70 transition">
                                <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">2. Morning Out</span>
                                <span id="punchVal_break_out" class="text-xs sm:text-sm font-bold text-gray-700 font-mono mt-0.5 block">--:--</span>
                            </div>
                            <!-- Afternoon In -->
                            <div id="punchBox_break_in" class="p-2 rounded-xl border border-gray-200 bg-gray-50/70 transition">
                                <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">3. Afternoon In</span>
                                <span id="punchVal_break_in" class="text-xs sm:text-sm font-bold text-gray-700 font-mono mt-0.5 block">--:--</span>
                            </div>
                            <!-- Afternoon Out -->
                            <div id="punchBox_time_out" class="p-2 rounded-xl border border-gray-200 bg-gray-50/70 transition">
                                <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">4. Afternoon Out</span>
                                <span id="punchVal_time_out" class="text-xs sm:text-sm font-bold text-gray-700 font-mono mt-0.5 block">--:--</span>
                            </div>
                        </div>
                    </div>

                    <!-- Current Week / Past History Attendance Sheet -->
                    <div class="mt-3.5 pt-3 border-t border-gray-100">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-bold uppercase text-gray-700 tracking-wider flex items-center gap-1.5">
                                <i class="fas fa-calendar-week text-blue-600"></i>
                                <span id="weekLogTitle">This Week's Attendance</span>
                            </span>
                            <div class="flex items-center space-x-1 text-[10px]">
                                <button id="tabThisWeekBtn" type="button" class="px-2 py-0.5 rounded-md font-semibold bg-blue-100 text-blue-700 transition">This Week</button>
                                <button id="tabPastDaysBtn" type="button" class="px-2 py-0.5 rounded-md font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition">Past History</button>
                            </div>
                        </div>

                        <!-- Table of Days -->
                        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-gray-50/50 max-h-[175px] overflow-y-auto">
                            <table class="w-full text-left text-[11px] border-collapse">
                                <thead class="sticky top-0 bg-gray-100 text-gray-600 border-b border-gray-200 text-[10px] uppercase font-bold tracking-wider z-10">
                                    <tr>
                                        <th class="py-1.5 px-2 font-bold">Day</th>
                                        <th class="py-1.5 px-1 text-center">AM In</th>
                                        <th class="py-1.5 px-1 text-center">AM Out</th>
                                        <th class="py-1.5 px-1 text-center">PM In</th>
                                        <th class="py-1.5 px-1 text-center">PM Out</th>
                                        <th class="py-1.5 px-2 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="weekAttendanceTableBody" class="divide-y divide-gray-100 bg-white">
                                    <!-- Populated dynamically by kiosk.js -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- State B: Standby / Welcome Mode (Visible when idle) -->
                <div id="standbyShowcase" class="flex flex-col items-center justify-center text-center py-6 px-4">
                    <div class="w-14 h-14 rounded-2xl bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center text-2xl mb-3 shadow-inner">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <h3 class="text-base font-bold text-gray-900">Ready for Face Attendance</h3>
                    <p class="text-xs text-gray-500 max-w-xs mt-1 mb-3.5">
                        Please step in front of the camera. The system will recognize your face and record your attendance automatically.
                    </p>
                    <div class="flex items-center gap-2 text-xs">
                        <div class="px-2.5 py-1 rounded-lg bg-gray-50 border border-gray-200 text-gray-600 font-medium flex items-center gap-1.5">
                            <i class="fas fa-users text-blue-600"></i>
                            <span>Enrolled: <strong><?= (int)$enrolledCount ?></strong> / <?= (int)$totalEmployees ?></span>
                        </div>
                        <div class="px-2.5 py-1 rounded-lg bg-gray-50 border border-gray-200 text-gray-600 font-medium flex items-center gap-1.5">
                            <i class="fas fa-shield-alt text-emerald-600"></i>
                            <span>Anti-Duplicate Lock</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Card: Today's Office Activity Live Feed -->
            <div class="kiosk-card rounded-2xl border bg-white p-4 sm:p-5 flex-1 min-h-0 flex flex-col overflow-hidden shadow-sm">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-3 flex-shrink-0">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 flex items-center gap-1.5">
                        <i class="fas fa-history text-blue-600"></i> Today's Office Punches
                    </h3>
                    <span class="text-[10px] bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 rounded-full font-semibold">Live Activity</span>
                </div>

                <!-- Recent punches list -->
                <div id="recentPunchesList" class="flex-1 space-y-2.5 overflow-y-auto pr-1">
                    <?php if (empty($parsedRecent)): ?>
                        <div id="noRecentPunches" class="live-empty text-center py-10 text-gray-400 text-xs">
                            <i class="fas fa-user-clock text-3xl mb-2 text-gray-300 block"></i>
                            No face attendance recorded yet today.
                        </div>
                    <?php else: ?>
                        <?php foreach ($parsedRecent as $p): ?>
                            <div class="flex items-center justify-between p-2.5 bg-gray-50/80 rounded-xl border border-gray-100">
                                <div class="flex items-center space-x-2.5 min-w-0">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center flex-shrink-0 text-xs overflow-hidden border">
                                        <?php if ($p['profile_pic']): ?>
                                            <img src="<?= $p['profile_pic'] ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <?= strtoupper(substr($p['name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-gray-800 truncate"><?= escapeOutput($p['name']) ?></p>
                                        <p class="text-[10px] text-gray-500"><?= escapeOutput($p['punch_label']) ?></p>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0 pl-2">
                                    <span class="text-xs font-bold text-gray-800"><?= $p['time'] ?></span>
                                    <span class="block text-[9px] text-emerald-600 font-medium"><i class="fas fa-check-circle"></i> Verified</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Instructions Footer -->
                <div class="mt-3 pt-2.5 border-t border-gray-100 text-[11px] text-gray-500 bg-gray-50/80 -mx-4 -mb-4 p-3 rounded-b-xl flex items-start space-x-2 flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-600 text-xs mt-0.5 flex-shrink-0"></i>
                    <span>Look straight at the camera. The kiosk recognizes your face and records your punch in sequence automatically.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Punch Result Modal Popup (Light Theme to match GeoSnap) -->
    <div id="punchModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
        <div id="modalContent" class="bg-white rounded-2xl max-w-sm w-full overflow-hidden shadow-2xl border border-gray-100 animate-scaleUp">
            <!-- Dynamically populated by kiosk.js -->
        </div>
    </div>

    <!-- Configuration for JS -->
    <script>
        const KIOSK_CONFIG = {
            faceApiUrl: "<?= baseUrl('/api/face.php') ?>",
            attendanceApiUrl: "<?= baseUrl('/api/attendance.php') ?>",
            modelsUrl: "<?= baseUrl('/assets/models') ?>",
            csrfTokenName: "<?= CSRF_TOKEN_NAME ?>",
            csrfToken: "<?= generateCSRFToken() ?>",
            companyName: "<?= escapeOutput($companyName) ?>"
        };
    </script>
    <script src="<?= baseUrl('/assets/js/kiosk.js?v=' . filemtime(__DIR__ . '/../../assets/js/kiosk.js')) ?>"></script>
</body>
</html>

<?php
requireLogin();
$employeeId = getEmployeeIdFromUser($_SESSION['user_id']);
if (!$employeeId) {
    setFlash('Employee profile not found', 'danger');
    redirect(APP_URL . '/index.php?page=dashboard');
}

$emp = getEmployeeData($employeeId);
$type = $type ?? ($_GET['action'] ?? 'time-in');
$type = str_replace('-', '_', $type);
$today = date('Y-m-d');

$existing = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
$existing->execute([$employeeId, $today]);
$existing = $existing->fetch();

if ($type === 'time_in' && $existing && $existing['time_in']) {
    setFlash('You have already timed in today', 'warning');
    redirect(APP_URL . '/index.php?page=attendance');
}
if ($type === 'time_out' && (!$existing || !$existing['time_in'])) {
    setFlash('You must time in first', 'warning');
    redirect(APP_URL . '/index.php?page=attendance');
}
if ($type === 'time_out' && $existing && $existing['time_out']) {
    setFlash('You have already timed out today', 'warning');
    redirect(APP_URL . '/index.php?page=attendance');
}
if ($type === 'break_out' && $existing && $existing['break_out']) {
    setFlash('Break out already recorded', 'warning');
    redirect(APP_URL . '/index.php?page=attendance');
}
if ($type === 'break_in' && (!$existing || !$existing['break_out'])) {
    if (!$existing || !$existing['time_in']) {
        setFlash('You must time in first', 'warning');
        redirect(APP_URL . '/index.php?page=attendance');
    }
}
if ($type === 'break_in' && $existing && $existing['break_in']) {
    setFlash('Break in already recorded', 'warning');
    redirect(APP_URL . '/index.php?page=attendance');
}

$activePolygon = getActivePolygon();
$polygonCoords = $activePolygon ? json_decode($activePolygon['coordinates'], true) : [];
$hasPolygon = !empty($polygonCoords) && count($polygonCoords) >= 3;

if (!$hasPolygon) {
    setFlash('No active office boundary set. Please configure the polygon first.', 'danger');
    redirect(APP_URL . '/index.php?page=attendance');
}

$label = ucwords(str_replace('_', ' ', $type));
?>
<style>
body { overflow: hidden !important; background: #f9fafb !important; }
nav, aside { display: none !important; }
#mainContent { padding: 0 !important; margin: 0 !important; min-height: 100vh !important; max-height: 100vh !important; overflow: hidden !important; }
#mainContent > div { padding: 0 !important; height: 100vh !important; }
</style>
<div class="min-h-screen bg-gray-50 flex flex-col fixed inset-0 z-50">
    <div class="flex-1 flex flex-col max-w-lg mx-auto w-full px-3 py-3">
        <div class="bg-white rounded-xl shadow-sm border flex-1 flex flex-col overflow-hidden">
            <div class="text-center px-3 pt-3 pb-1">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-1">
                    <i class="fas fa-camera text-blue-600 text-lg"></i>
                </div>
                <h2 class="text-base font-bold text-gray-800"><?= $label ?></h2>
                <p class="text-xs text-gray-500"><?= escapeOutput($emp['first_name'] . ' ' . $emp['last_name']) ?> <span class="text-gray-400">(<?= escapeOutput($emp['employee_no']) ?>)</span></p>
            </div>

            <div class="flex-1 flex flex-col px-3 pb-3 space-y-2">
                <div id="step-gps" class="p-2 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="flex items-center space-x-2">
                        <div class="w-7 h-7 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-satellite text-white text-xs"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-blue-800">Getting GPS...</p>
                            <p class="text-[10px] text-blue-600 truncate" id="gpsStatus">Allow location access</p>
                        </div>
                        <div id="gpsSpinner" class="w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin flex-shrink-0"></div>
                    </div>
                </div>

                <div id="step-camera" class="hidden flex-1 flex flex-col">
                    <div class="flex items-center space-x-2 mb-1">
                        <div class="w-7 h-7 bg-green-600 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-camera text-white text-xs"></i>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-green-800">Take a Selfie</p>
                            <p class="text-[10px] text-green-600">Tap the camera area below</p>
                        </div>
                    </div>
                    <div class="flex-1 flex flex-col">
                        <label id="cameraLabel" for="photoInput" class="flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors p-4">
                            <i class="fas fa-camera text-xl text-gray-400 mb-1"></i>
                            <span class="text-[11px] font-medium text-gray-600">Tap to open camera</span>
                        </label>
                        <input id="photoInput" type="file" accept="image/*" capture="environment" class="hidden">
                        <div id="capturedPreview" class="hidden mt-1">
                            <img id="previewImg" loading="lazy" class="w-full rounded-lg border-2 border-green-300" style="max-height:200px;object-fit:cover;">
                            <div class="flex gap-2 mt-1">
                                <button id="submitBtn" class="flex-1 py-2 bg-blue-700 text-white rounded-lg text-xs font-medium hover:bg-blue-800">
                                    <i class="fas fa-check mr-1"></i>Submit
                                </button>
                                <button id="retakeBtn" class="py-2 px-3 border rounded-lg text-xs text-gray-600 hover:bg-gray-50">
                                    <i class="fas fa-redo"></i> Retake
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="step-processing" class="hidden text-center py-3">
                    <div class="w-8 h-8 border-3 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                    <p class="text-xs font-medium text-gray-700">Processing...</p>
                    <div id="retryStatus" class="hidden mt-1 p-1 bg-yellow-50 border border-yellow-200 rounded text-[10px] text-yellow-700">
                        <i class="fas fa-redo mr-1"></i>Retrying... (<span id="retryCount">1</span>/<span id="retryMax">3</span>)
                    </div>
                </div>

                <div id="step-result" class="hidden">
                    <div id="resultSuccess" class="hidden text-center py-2">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-1">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <h3 class="text-base font-bold text-green-700">Recorded!</h3>
                        <p id="resultMessage" class="text-xs text-gray-600 mt-1"></p>
                        <div id="resultMap" style="height:120px;" class="rounded-lg border mt-1"></div>
                        <div id="resultDetails" class="mt-1 space-y-0.5 text-xs"></div>
                        <a href="<?= APP_URL ?>/index.php?page=attendance" class="block text-center mt-2 py-2 bg-blue-700 text-white rounded-lg text-xs font-medium hover:bg-blue-800">
                            <i class="fas fa-arrow-left mr-1"></i>Back
                        </a>
                    </div>
                    <div id="resultError" class="hidden text-center py-2">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-1">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                        <h3 class="text-base font-bold text-red-700">Rejected</h3>
                        <p id="errorMessage" class="text-xs text-gray-600 mt-1"></p>
                        <div id="errorMap" style="height:120px;" class="rounded-lg border mt-1"></div>
                        <div class="mt-2 space-y-1">
                            <a href="<?= APP_URL ?>/index.php?page=attendance&action=<?= str_replace('_', '-', $type) ?>" class="block text-center py-2 bg-blue-700 text-white rounded-lg text-xs font-medium hover:bg-blue-800">
                                <i class="fas fa-redo mr-1"></i>Try Again
                            </a>
                            <a href="<?= APP_URL ?>/index.php?page=attendance" class="block text-center py-2 border rounded-lg text-xs hover:bg-gray-50">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$typeJs = str_replace('_', '-', $type);
$additionalScripts = '
<script>
const ATTENDANCE_TYPE = "' . $typeJs . '";
const EMPLOYEE_ID = ' . $employeeId . ';
const EMPLOYEE_NAME = "' . escapeOutput($emp['first_name'] . ' ' . $emp['last_name']) . '";
const EMPLOYEE_NO = "' . escapeOutput($emp['employee_no']) . '";
const HAS_POLYGON = ' . ($hasPolygon ? 'true' : 'false') . ';
const POLYGON_COORDS = ' . json_encode($polygonCoords) . ';
const APP_URL = "' . APP_URL . '";
const CSRF_TOKEN_NAME = "csrf_token";
const CSRF_TOKEN = "' . generateCSRFToken() . '";
</script>
<script src="' . APP_URL . '/assets/js/camera.js"></script>
<script src="' . APP_URL . '/assets/js/gps.js"></script>
<script src="' . APP_URL . '/assets/js/attendance.js"></script>
';
?>

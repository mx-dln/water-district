/**
 * GeoSnap Office Attendance - Automatic Face Recognition Kiosk
 * Powered by face-api.js
 * Automatic 4-Punch Sequencing (Morning In -> Morning Out -> Afternoon In -> Afternoon Out)
 * 1-Minute Cooldown & Anti-Duplicate Protection
 */

let videoEl, canvasEl, ctx;
let faceMatcher = null;
let enrolledEmployees = [];
let isModelLoaded = false;
let isProcessingPunch = false;
let currentStream = null;

// Track last punch time per employee (in milliseconds)
// Enforces 60-second cooldown so employee cannot punch in/out within 1 minute
const employeeLastPunch = {};
const COOLDOWN_MS = 60000; // 1 minute (60 seconds)

let soundEnabled = true;

// Camera Orientation State (Default to Normal / Uninverted view)
let isCameraFlipped = localStorage.getItem('kiosk_camera_flipped') === 'true';

function applyCameraFlip() {
    const video = document.getElementById('video');
    const flipBtnText = document.getElementById('flipBtnText');
    const flipBtn = document.getElementById('flipCameraBtn');
    if (video) {
        video.style.transform = isCameraFlipped ? 'scaleX(-1)' : 'none';
        video.style.webkitTransform = isCameraFlipped ? 'scaleX(-1)' : 'none';
    }
    if (flipBtnText) {
        flipBtnText.textContent = isCameraFlipped ? 'Mirrored' : 'Normal View';
    }
    if (flipBtn) {
        if (isCameraFlipped) {
            flipBtn.classList.add('bg-blue-50', 'text-blue-700', 'border-blue-200');
            flipBtn.classList.remove('bg-gray-50', 'text-gray-700', 'border-gray-200');
        } else {
            flipBtn.classList.remove('bg-blue-50', 'text-blue-700', 'border-blue-200');
            flipBtn.classList.add('bg-gray-50', 'text-gray-700', 'border-gray-200');
        }
    }
}

// Web Audio API chimes
let audioCtx = null;
function getAudioContext() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    return audioCtx;
}

function playSuccessChime() {
    if (!soundEnabled) return;
    try {
        const ctx = getAudioContext();
        if (ctx.state === 'suspended') ctx.resume();
        const now = ctx.currentTime;
        
        const osc1 = ctx.createOscillator();
        const osc2 = ctx.createOscillator();
        const gain = ctx.createGain();

        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, now); // D5
        osc1.frequency.exponentialRampToValueAtTime(880, now + 0.15); // A5

        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(880, now + 0.15);
        osc2.frequency.exponentialRampToValueAtTime(1174.66, now + 0.35); // D6

        gain.gain.setValueAtTime(0.2, now);
        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.5);

        osc1.connect(gain);
        osc2.connect(gain);
        gain.connect(ctx.destination);

        osc1.start(now);
        osc1.stop(now + 0.2);
        osc2.start(now + 0.15);
        osc2.stop(now + 0.5);
    } catch (e) {
        console.warn('Audio playback error:', e);
    }
}

function playWarningChime() {
    if (!soundEnabled) return;
    try {
        const ctx = getAudioContext();
        if (ctx.state === 'suspended') ctx.resume();
        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(280, now);
        osc.frequency.setValueAtTime(220, now + 0.15);

        gain.gain.setValueAtTime(0.15, now);
        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.3);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(now);
        osc.stop(now + 0.3);
    } catch (e) {
        console.warn('Audio playback error:', e);
    }
}

async function loadModels() {
    const statusText = document.getElementById('kioskStatusText');
    const statusDot = document.getElementById('statusDot');
    
    if (statusText) statusText.textContent = 'Loading neural recognition models...';
    if (statusDot) statusDot.className = 'w-2.5 h-2.5 rounded-full bg-yellow-400 animate-pulse';

    const localModelsPath = KIOSK_CONFIG.modelsUrl;
    const cdnModelsPath = 'https://cdn.jsdelivr.net/gh/cgarciagl/face-api.js@master/weights/';

    let loaded = false;

    // Try loading from local assets first
    try {
        console.log('Loading face-api models from:', localModelsPath);
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(localModelsPath),
            faceapi.nets.faceLandmark68Net.loadFromUri(localModelsPath),
            faceapi.nets.faceLandmark68TinyNet.loadFromUri(localModelsPath),
            faceapi.nets.faceRecognitionNet.loadFromUri(localModelsPath)
        ]);
        loaded = true;
        console.log('Face models successfully loaded from local assets.');
    } catch (localErr) {
        console.warn('Local model load failed, falling back to CDN:', localErr);
        try {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(cdnModelsPath),
                faceapi.nets.faceLandmark68Net.loadFromUri(cdnModelsPath),
                faceapi.nets.faceLandmark68TinyNet.loadFromUri(cdnModelsPath),
                faceapi.nets.faceRecognitionNet.loadFromUri(cdnModelsPath)
            ]);
            loaded = true;
            console.log('Face models successfully loaded from CDN.');
        } catch (cdnErr) {
            console.error('All model loading attempts failed:', cdnErr);
            if (statusText) statusText.textContent = 'Failed to load neural models. Please check network/assets.';
            if (statusDot) statusDot.className = 'w-2.5 h-2.5 rounded-full bg-red-500';
            return false;
        }
    }

    isModelLoaded = loaded;
    return loaded;
}

async function loadFaceDatabase() {
    const statusText = document.getElementById('kioskStatusText');
    if (statusText) statusText.textContent = 'Syncing enrolled employee face database...';

    try {
        const res = await fetch(KIOSK_CONFIG.faceApiUrl + '?action=get_enrolled');
        const json = await res.json();

        if (!json.success || !json.employees) {
            throw new Error(json.message || 'Failed to fetch enrolled employees');
        }

        enrolledEmployees = json.employees;
        // Populate cooldown timestamps from today's attendance records
        enrolledEmployees.forEach(emp => {
            if (emp.last_punch_ts) {
                employeeLastPunch[emp.employee_id] = emp.last_punch_ts;
            }
        });

        const totalFaces = enrolledEmployees.reduce((sum, e) => sum + e.descriptors.length, 0);

        console.log(`Loaded ${enrolledEmployees.length} enrolled employees with ${totalFaces} face descriptors.`);

        const enrolledCountEl = document.getElementById('enrolledCount');
        if (enrolledCountEl) enrolledCountEl.textContent = enrolledEmployees.length;

        if (enrolledEmployees.length === 0) {
            faceMatcher = null;
            if (statusText) statusText.textContent = 'Ready (No employees enrolled yet. Please register faces in Admin).';
            return;
        }

        // Build LabeledFaceDescriptors for FaceMatcher
        const labeledDescriptors = enrolledEmployees.map(emp => {
            const floatDescriptors = emp.descriptors.map(d => new Float32Array(d));
            return new faceapi.LabeledFaceDescriptors(String(emp.employee_id), floatDescriptors);
        });

        // Euclidean distance threshold: 0.45 (strict confidence to avoid false positives)
        faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, 0.45);

        if (statusText) statusText.textContent = 'Ready — Step in front of camera to record attendance';
        const statusDot = document.getElementById('statusDot');
        if (statusDot) statusDot.className = 'w-2.5 h-2.5 rounded-full bg-green-500';

    } catch (err) {
        console.error('Failed to load face database:', err);
        if (statusText) statusText.textContent = 'Error connecting to face database.';
    }
}

async function startCamera(deviceId = null) {
    videoEl = document.getElementById('video');
    canvasEl = document.getElementById('overlay');
    if (!videoEl) return;

    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
    }

    const constraints = {
        video: {
            width: { ideal: 1280 },
            height: { ideal: 720 },
            facingMode: 'user'
        },
        audio: false
    };

    if (deviceId) {
        constraints.video.deviceId = { exact: deviceId };
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        currentStream = stream;
        videoEl.srcObject = stream;
        await new Promise(resolve => {
            videoEl.onloadedmetadata = () => resolve();
        });
        videoEl.play();
        setupVideoCanvas();
        applyCameraFlip();
        startDetectionLoop();
        populateCameras();
    } catch (err) {
        console.error('Camera access error:', err);
        const statusText = document.getElementById('kioskStatusText');
        if (statusText) statusText.textContent = 'Camera access denied or unavailable: ' + err.message;
        const statusDot = document.getElementById('statusDot');
        if (statusDot) statusDot.className = 'w-2.5 h-2.5 rounded-full bg-red-500';
    }
}

async function populateCameras() {
    const select = document.getElementById('cameraSelect');
    if (!select) return;

    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(d => d.kind === 'videoinput');
        
        select.innerHTML = '';
        videoDevices.forEach((device, idx) => {
            const opt = document.createElement('option');
            opt.value = device.deviceId;
            opt.textContent = device.label || `Camera ${idx + 1}`;
            select.appendChild(opt);
        });

        select.onchange = () => {
            startCamera(select.value);
        };
    } catch (e) {
        console.warn('Could not enumerate cameras:', e);
    }
}

function setupVideoCanvas() {
    if (!videoEl || !canvasEl) return;
    canvasEl.width = videoEl.videoWidth || 640;
    canvasEl.height = videoEl.videoHeight || 480;
    ctx = canvasEl.getContext('2d');
}

// Continuous Face Detection & Recognition Loop
let isDetecting = false;
async function startDetectionLoop() {
    if (isDetecting) return;
    isDetecting = true;

    const options = new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 });

    let matchStreakEmpId = null;
    let matchStreakCount = 0;
    let matchStreakFirstTime = 0;
    const REQUIRED_STREAK_FRAMES = 3;
    const REQUIRED_STREAK_MS = 250;

    async function scanFrame() {
        if (!videoEl || videoEl.paused || videoEl.ended || !isModelLoaded) {
            requestAnimationFrame(scanFrame);
            return;
        }

        if (isProcessingPunch) {
            requestAnimationFrame(scanFrame);
            return;
        }

        if (canvasEl.width !== videoEl.videoWidth || canvasEl.height !== videoEl.videoHeight) {
            canvasEl.width = videoEl.videoWidth;
            canvasEl.height = videoEl.videoHeight;
        }

        ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

        try {
            const detection = await faceapi.detectSingleFace(videoEl, options)
                .withFaceLandmarks(true)
                .withFaceDescriptor();

            if (detection) {
                const resized = faceapi.resizeResults(detection, {
                    width: canvasEl.width,
                    height: canvasEl.height
                });

                const rawBox = resized.detection.box;
                const box = isCameraFlipped ? {
                    x: canvasEl.width - (rawBox.x + rawBox.width),
                    y: rawBox.y,
                    width: rawBox.width,
                    height: rawBox.height
                } : rawBox;

                let matchLabel = 'Face Detected';
                let matchedEmp = null;
                let boxColor = '#3b82f6'; // blue default

                if (!faceMatcher) {
                    matchLabel = 'No Enrolled Faces (Register in Admin)';
                    boxColor = '#64748b';
                    matchStreakEmpId = null;
                    matchStreakCount = 0;
                } else if (detection.descriptor) {
                    const bestMatch = faceMatcher.findBestMatch(detection.descriptor);
                    if (bestMatch.label !== 'unknown') {
                        const empId = parseInt(bestMatch.label);
                        matchedEmp = enrolledEmployees.find(e => e.employee_id === empId);
                        if (matchedEmp) {
                            const empId = matchedEmp.employee_id;
                            const nowTime = Date.now();

                            // Track multi-frame stability streak
                            if (matchStreakEmpId === empId) {
                                matchStreakCount++;
                            } else {
                                matchStreakEmpId = empId;
                                matchStreakCount = 1;
                                matchStreakFirstTime = nowTime;
                            }

                            // Show showcase if:
                            // 1) A different face is detected
                            // 2) OR if showcase is closed and not just dismissed for this same employee in last 4s
                            const isDifferentEmp = (currentShowcaseEmpId !== null && currentShowcaseEmpId !== empId);
                            const isFreshScan = (currentShowcaseEmpId === null && (empId !== lastDismissedEmpId || (nowTime - lastDismissedTime > 4000)));

                            const lastPunch = employeeLastPunch[empId] || 0;
                            const elapsed = nowTime - lastPunch;
                            const isCooldown = (elapsed < COOLDOWN_MS);

                            if (isDifferentEmp || isFreshScan) {
                                const initialNotice = isCooldown 
                                    ? `You are already logged in. Please come back after 1 minute (${Math.ceil((COOLDOWN_MS - elapsed) / 1000)}s left).`
                                    : null;
                                fetchAndDisplayEmployeeShowcase(matchedEmp, null, initialNotice);
                            }

                            const confidence = Math.round((1 - bestMatch.distance) * 100);

                            if (isCooldown) {
                                const waitSec = Math.ceil((COOLDOWN_MS - elapsed) / 1000);
                                matchLabel = `${matchedEmp.name} (Already logged in. Come back in ${waitSec}s)`;
                                boxColor = '#f59e0b'; // amber for cooldown

                                // Live status bar update
                                const statusText = document.getElementById('kioskStatusText');
                                if (statusText) statusText.textContent = `${matchedEmp.name}: You are already logged in. Please come back after 1 min (${waitSec}s left).`;
                                const statusDot = document.getElementById('statusDot');
                                if (statusDot) statusDot.className = 'w-2.5 h-2.5 rounded-full bg-amber-500';

                                // Live showcase notice banner update
                                const noticeWrap = document.getElementById('showcaseNotice');
                                const noticeText = document.getElementById('showcaseNoticeText');
                                if (noticeWrap && noticeText) {
                                    noticeText.textContent = `You are already logged in. Please come back after 1 minute (${waitSec}s left).`;
                                    noticeWrap.classList.remove('hidden');
                                }
                            } else {
                                matchLabel = `${matchedEmp.name} (${confidence}%)`;
                                boxColor = '#10b981'; // green for recognized

                                // Trigger automatic sequential punch only when held steadily for consecutive frames
                                if (matchStreakCount >= REQUIRED_STREAK_FRAMES && (nowTime - matchStreakFirstTime >= REQUIRED_STREAK_MS)) {
                                    matchStreakCount = 0; // reset
                                    checkAndTriggerAutomaticPunch(matchedEmp, detection, bestMatch.distance);
                                }
                            }
                        }
                    } else {
                        matchStreakEmpId = null;
                        matchStreakCount = 0;
                        matchLabel = 'Unknown Face';
                        boxColor = '#ef4444'; // red for unknown
                    }
                }

                // Render clean HUD tag
                drawCleanHUD(box, matchLabel, boxColor);
            } else {
                matchStreakEmpId = null;
                matchStreakCount = 0;
            }
        } catch (err) {
            console.error('Scan error:', err);
        }

        requestAnimationFrame(scanFrame);
    }

    requestAnimationFrame(scanFrame);
}

function drawCleanHUD(box, label, color) {
    if (!ctx) return;

    const { x, y, width, height } = box;
    const corner = Math.min(22, width / 4);

    ctx.save();
    ctx.strokeStyle = color;
    ctx.lineWidth = 3;

    // Corner brackets
    // Top-Left
    ctx.beginPath();
    ctx.moveTo(x, y + corner);
    ctx.lineTo(x, y);
    ctx.lineTo(x + corner, y);
    ctx.stroke();

    // Top-Right
    ctx.beginPath();
    ctx.moveTo(x + width - corner, y);
    ctx.lineTo(x + width, y);
    ctx.lineTo(x + width, y + corner);
    ctx.stroke();

    // Bottom-Left
    ctx.beginPath();
    ctx.moveTo(x, y + height - corner);
    ctx.lineTo(x, y + height);
    ctx.lineTo(x + corner, y + height);
    ctx.stroke();

    // Bottom-Right
    ctx.beginPath();
    ctx.moveTo(x + width - corner, y + height);
    ctx.lineTo(x + width, y + height);
    ctx.lineTo(x + width, y + height - corner);
    ctx.stroke();

    // Clean Pill Badge above head
    ctx.font = 'bold 14px system-ui, -apple-system, sans-serif';
    const textWidth = ctx.measureText(label).width;
    const badgeW = textWidth + 24;
    const badgeH = 28;
    const badgeX = x + (width - badgeW) / 2;
    const badgeY = Math.max(10, y - badgeH - 8);

    ctx.fillStyle = 'rgba(255, 255, 255, 0.95)';
    roundRect(ctx, badgeX, badgeY, badgeW, badgeH, 6);
    ctx.fill();

    ctx.strokeStyle = color;
    ctx.lineWidth = 1.5;
    roundRect(ctx, badgeX, badgeY, badgeW, badgeH, 6);
    ctx.stroke();

    ctx.fillStyle = '#1e293b';
    ctx.textBaseline = 'middle';
    ctx.fillText(label, badgeX + 12, badgeY + (badgeH / 2));

    ctx.restore();
}

function roundRect(ctx, x, y, width, height, radius) {
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + width - radius, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
    ctx.lineTo(x + width, y + height - radius);
    ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
    ctx.lineTo(x + radius, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();
}

// Capture current webcam frame as JPEG Blob
function captureWebcamFrame() {
    return new Promise(resolve => {
        const offCanvas = document.createElement('canvas');
        offCanvas.width = videoEl.videoWidth || 640;
        offCanvas.height = videoEl.videoHeight || 480;
        const offCtx = offCanvas.getContext('2d');
        if (isCameraFlipped) {
            offCtx.translate(offCanvas.width, 0);
            offCtx.scale(-1, 1);
        }
        offCtx.drawImage(videoEl, 0, 0, offCanvas.width, offCanvas.height);
        offCanvas.toBlob(blob => {
            resolve(blob);
        }, 'image/jpeg', 0.85);
    });
}

// Automatically trigger punch with 1-minute anti-duplicate lock
async function checkAndTriggerAutomaticPunch(employee, detection, distance) {
    const now = Date.now();
    const empId = employee.employee_id;

    // Strict 1-Minute Cooldown Check
    if (employeeLastPunch[empId] && (now - employeeLastPunch[empId] < COOLDOWN_MS)) {
        return;
    }

    // Require stable face (at least 75px wide)
    if (detection.detection.box.width < 75) {
        return;
    }

    // Lock cooldown immediately
    employeeLastPunch[empId] = now;
    isProcessingPunch = true;

    showLiveScanningFeedback(employee);

    // Capture snapshot
    const photoBlob = await captureWebcamFrame();

    // Prepare FormData: type is 'auto' so server sequentially chooses:
    // 1st -> Morning In, 2nd -> Morning Out, 3rd -> Afternoon In, 4th -> Afternoon Out
    const formData = new FormData();
    formData.append('action', 'face_record');
    formData.append('mode', 'office_face');
    formData.append('type', 'auto');
    formData.append('employee_id', empId);
    formData.append('confidence', (1 - distance).toFixed(4));
    formData.append('photo', photoBlob, `kiosk_${empId}_${Date.now()}.jpg`);
    formData.append(KIOSK_CONFIG.csrfTokenName, KIOSK_CONFIG.csrfToken);

    try {
        const res = await fetch(KIOSK_CONFIG.attendanceApiUrl, {
            method: 'POST',
            body: formData
        });

        const data = await res.json();

        if (data.success) {
            playSuccessChime();
            // Display attended employee's weekly attendance showcase
            fetchAndDisplayEmployeeShowcase(employee, data.data, null);
            addRecentPunchRow(data.data);
        } else {
            playWarningChime();
            // Show current week attendance with cooldown or completed notice
            fetchAndDisplayEmployeeShowcase(employee, null, data.message);
        }
    } catch (netErr) {
        console.error('Punch request failed:', netErr);
        playWarningChime();
        fetchAndDisplayEmployeeShowcase(employee, null, 'Communication error. Please try again.');
    } finally {
        isProcessingPunch = false;
    }
}

function showLiveScanningFeedback(employee) {
    const statusText = document.getElementById('kioskStatusText');
    if (statusText) statusText.textContent = `Recording attendance for ${employee.name}...`;
    const statusDot = document.getElementById('statusDot');
    if (statusDot) statusDot.className = 'w-2.5 h-2.5 rounded-full bg-blue-600 animate-pulse';
}

// ========================================================
// Attended Employee Showcase & Weekly Attendance Manager
// ========================================================
let currentShowcaseEmpId = null;
let showcaseTimer = null;
let showcaseInterval = null;
let currentEmployeeAttendanceData = null;
let currentTabMode = 'this_week'; // 'this_week' or 'past_days'
let lastDismissedEmpId = null;
let lastDismissedTime = 0;

async function fetchAndDisplayEmployeeShowcase(employee, punchOverride = null, noticeMessage = null) {
    if (!employee) return;
    const empId = employee.employee_id || employee.id;
    currentShowcaseEmpId = empId;

    // Reveal showcase container and hide standby card
    const showcaseCard = document.getElementById('attendedEmployeeShowcase');
    const standbyCard = document.getElementById('standbyShowcase');
    if (standbyCard) standbyCard.classList.add('hidden');
    if (showcaseCard) showcaseCard.classList.remove('hidden');

    // Fill employee basic info
    const nameEl = document.getElementById('showcaseName');
    const empNoEl = document.getElementById('showcaseEmpNo');
    const deptEl = document.getElementById('showcaseDept');
    const initialsEl = document.getElementById('showcaseAvatarInitials');
    const imgEl = document.getElementById('showcaseAvatarImg');

    const empName = employee.name || `${employee.first_name || ''} ${employee.last_name || ''}`;
    if (nameEl) nameEl.textContent = empName;
    if (empNoEl) empNoEl.textContent = employee.employee_no || '';
    if (deptEl) deptEl.textContent = employee.department || 'General Administration';

    if (employee.profile_pic) {
        if (imgEl) { imgEl.src = employee.profile_pic; imgEl.classList.remove('hidden'); }
        if (initialsEl) initialsEl.classList.add('hidden');
    } else {
        const initial = empName.charAt(0).toUpperCase();
        if (initialsEl) { initialsEl.textContent = initial; initialsEl.classList.remove('hidden'); }
        if (imgEl) imgEl.classList.add('hidden');
    }

    // Notice message banner
    const noticeWrap = document.getElementById('showcaseNotice');
    const noticeText = document.getElementById('showcaseNoticeText');
    if (noticeMessage) {
        if (noticeText) noticeText.textContent = noticeMessage;
        if (noticeWrap) noticeWrap.classList.remove('hidden');
    } else {
        if (noticeWrap) noticeWrap.classList.add('hidden');
    }

    // Start 5-second countdown timer immediately
    startShowcaseTimer(5);

    // Fetch full week attendance records
    try {
        const res = await fetch(`${KIOSK_CONFIG.faceApiUrl}?action=get_employee_attendance&employee_id=${empId}`);
        const data = await res.json();
        if (data.success) {
            // Guard against race conditions if face switched
            if (currentShowcaseEmpId !== empId) return;

            currentEmployeeAttendanceData = data;
            renderShowcaseHighlight(data, punchOverride);
            renderShowcaseTodayBoxes(data.today_punches);
            renderShowcaseWeekTable();
        }
    } catch (err) {
        console.warn('Failed to fetch employee attendance log:', err);
    }
}

function renderShowcaseHighlight(data, punchOverride = null) {
    const badgeText = document.getElementById('showcaseBadgeText');
    const punchLabel = document.getElementById('showcasePunchLabel');
    const punchTime = document.getElementById('showcasePunchTime');
    const badgeEl = document.getElementById('showcaseBadge');

    const empId = data.employee ? data.employee.id : currentShowcaseEmpId;
    const lastPunch = employeeLastPunch[empId] || 0;
    const elapsed = Date.now() - lastPunch;
    const isCooldown = (elapsed < COOLDOWN_MS);

    if (punchOverride) {
        if (badgeText) badgeText.textContent = 'Attendance Recorded';
        if (badgeEl) badgeEl.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shadow-sm animate-pulse';
        if (punchLabel) punchLabel.textContent = punchOverride.punch_label || punchOverride.type;
        if (punchTime) punchTime.textContent = punchOverride.time || '--:--';
    } else if (isCooldown) {
        const waitSec = Math.ceil((COOLDOWN_MS - elapsed) / 1000);
        if (badgeText) badgeText.textContent = `Already Logged In (${waitSec}s)`;
        if (badgeEl) badgeEl.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300 shadow-sm';
        if (data.latest_punch) {
            if (punchLabel) punchLabel.textContent = data.latest_punch.label;
            if (punchTime) punchTime.textContent = data.latest_punch.time;
        }
    } else if (data.latest_punch) {
        if (badgeText) badgeText.textContent = 'Recent Attendance';
        if (badgeEl) badgeEl.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200 shadow-sm';
        if (punchLabel) punchLabel.textContent = data.latest_punch.label;
        if (punchTime) punchTime.textContent = data.latest_punch.time;
    } else {
        if (badgeText) badgeText.textContent = 'Employee Recognized';
        if (badgeEl) badgeEl.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-800 border border-gray-200';
        if (punchLabel) punchLabel.textContent = 'No punches yet today';
        if (punchTime) punchTime.textContent = '--:--';
    }
}

function renderShowcaseTodayBoxes(punches) {
    const punchBoxes = {
        'time_in': { box: 'punchBox_time_in', val: 'punchVal_time_in' },
        'break_out': { box: 'punchBox_break_out', val: 'punchVal_break_out' },
        'break_in': { box: 'punchBox_break_in', val: 'punchVal_break_in' },
        'time_out': { box: 'punchBox_time_out', val: 'punchVal_time_out' }
    };

    let count = 0;
    for (const [key, els] of Object.entries(punchBoxes)) {
        const timeStr = punches ? punches[key] : null;
        const bEl = document.getElementById(els.box);
        const vEl = document.getElementById(els.val);

        if (vEl) vEl.textContent = timeStr || '—';

        if (bEl) {
            if (timeStr) {
                count++;
                bEl.className = 'p-2 rounded-xl border border-emerald-300 bg-emerald-50 text-emerald-800 transition font-medium';
            } else {
                bEl.className = 'p-2 rounded-xl border border-gray-200 bg-gray-50/70 text-gray-400 transition';
            }
        }
    }

    const countEl = document.getElementById('showcasePunchCount');
    if (countEl) countEl.textContent = `${count} of 4 Recorded`;
}

function renderShowcaseWeekTable() {
    const tbody = document.getElementById('weekAttendanceTableBody');
    if (!tbody || !currentEmployeeAttendanceData) return;

    const list = currentTabMode === 'this_week' 
        ? (currentEmployeeAttendanceData.week_days || [])
        : (currentEmployeeAttendanceData.past_history || []);

    if (list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-400 text-xs">No attendance records found.</td></tr>';
        return;
    }

    tbody.innerHTML = list.map(row => {
        const isToday = row.is_today;
        const rowClass = isToday ? 'bg-blue-50/70 font-semibold' : 'hover:bg-gray-50/80';
        const dayBadge = isToday 
            ? `<span class="px-1.5 py-0.5 rounded bg-blue-600 text-white text-[9px] font-bold">Today</span>` 
            : `<span class="text-gray-500 text-[10px] font-semibold">${row.day_short}</span>`;

        const formatTime = (t) => t ? `<span class="font-mono text-gray-800 font-bold">${t}</span>` : `<span class="text-gray-300">—</span>`;

        let statusPill = '';
        if (row.punch_count >= 4) {
            statusPill = '<span class="text-[9px] px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold">Complete</span>';
        } else if (row.punch_count > 0) {
            statusPill = `<span class="text-[9px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 font-bold">${row.punch_count}/4</span>`;
        } else if (isToday) {
            statusPill = '<span class="text-[9px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 font-bold">In Progress</span>';
        } else {
            statusPill = `<span class="text-[9px] text-gray-400">${row.status || '—'}</span>`;
        }

        return `
            <tr class="${rowClass} transition">
                <td class="py-1.5 px-2">
                    <div class="flex items-center space-x-1.5">
                        ${dayBadge}
                        <span class="text-gray-700 text-[11px]">${row.formatted || row.date}</span>
                    </div>
                </td>
                <td class="py-1.5 px-1 text-center">${formatTime(row.time_in)}</td>
                <td class="py-1.5 px-1 text-center">${formatTime(row.break_out)}</td>
                <td class="py-1.5 px-1 text-center">${formatTime(row.break_in)}</td>
                <td class="py-1.5 px-1 text-center">${formatTime(row.time_out)}</td>
                <td class="py-1.5 px-2 text-right">${statusPill}</td>
            </tr>
        `;
    }).join('');
}

function startShowcaseTimer(durationSec = 5) {
    if (showcaseTimer) clearTimeout(showcaseTimer);
    if (showcaseInterval) clearInterval(showcaseInterval);

    let remaining = durationSec;
    const timerSecEl = document.getElementById('showcaseTimerSec');
    const progressBar = document.getElementById('showcaseProgressBar');

    if (timerSecEl) timerSecEl.textContent = remaining;
    if (progressBar) {
        progressBar.style.transition = 'none';
        progressBar.style.width = '100%';
        void progressBar.offsetWidth; // Force CSS reflow
        progressBar.style.transition = `width ${durationSec}s linear`;
        progressBar.style.width = '0%';
    }

    showcaseInterval = setInterval(() => {
        remaining--;
        if (timerSecEl) timerSecEl.textContent = Math.max(0, remaining);
        if (remaining <= 0) {
            clearInterval(showcaseInterval);
            showcaseInterval = null;
        }
    }, 1000);

    showcaseTimer = setTimeout(() => {
        dismissEmployeeShowcase();
    }, durationSec * 1000);
}

function dismissEmployeeShowcase() {
    if (showcaseTimer) { clearTimeout(showcaseTimer); showcaseTimer = null; }
    if (showcaseInterval) { clearInterval(showcaseInterval); showcaseInterval = null; }

    lastDismissedEmpId = currentShowcaseEmpId;
    lastDismissedTime = Date.now();
    currentShowcaseEmpId = null;
    currentEmployeeAttendanceData = null;

    const showcaseCard = document.getElementById('attendedEmployeeShowcase');
    const standbyCard = document.getElementById('standbyShowcase');

    if (showcaseCard) showcaseCard.classList.add('hidden');
    if (standbyCard) standbyCard.classList.remove('hidden');

    isProcessingPunch = false;
    const statusText = document.getElementById('kioskStatusText');
    if (statusText) statusText.textContent = 'Ready — Step in front of camera to record attendance';
    const statusDot = document.getElementById('statusDot');
    if (statusDot) statusDot.className = 'w-2.5 h-2.5 rounded-full bg-green-500';
}

function addRecentPunchRow(data) {
    const list = document.getElementById('recentPunchesList');
    if (!list) return;

    const noPunches = document.getElementById('noRecentPunches');
    if (noPunches) noPunches.remove();

    const row = document.createElement('div');
    row.className = 'flex items-center justify-between p-2.5 bg-gray-50/90 rounded-xl border border-gray-100 shadow-sm transition-all duration-300';
    row.innerHTML = `
        <div class="flex items-center space-x-2.5 min-w-0">
            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center flex-shrink-0 text-xs overflow-hidden border">
                ${data.profile_pic ? `<img src="${data.profile_pic}" class="w-full h-full object-cover">` : data.employee_name.charAt(0)}
            </div>
            <div class="min-w-0">
                <p class="text-xs font-bold text-gray-800 truncate">${data.employee_name}</p>
                <p class="text-[10px] text-gray-500">${data.punch_label}</p>
            </div>
        </div>
        <div class="text-right flex-shrink-0 pl-2">
            <span class="text-xs font-bold text-gray-800">${data.time}</span>
            <span class="block text-[9px] text-emerald-600 font-medium"><i class="fas fa-check-circle"></i> Verified</span>
        </div>
    `;

    list.insertBefore(row, list.firstChild);

    // Keep only last 10
    while (list.children.length > 10) {
        list.removeChild(list.lastChild);
    }
}

// Clock updates
function updateClock() {
    const clockEl = document.getElementById('kioskClock');
    const dateEl = document.getElementById('kioskDate');
    if (!clockEl && !dateEl) return;

    const now = new Date();
    if (clockEl) {
        clockEl.textContent = now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
    }
    if (dateEl) {
        dateEl.textContent = now.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
}

// Fullscreen toggle
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
            console.warn('Error attempting fullscreen:', err);
        });
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', async () => {
    updateClock();
    setInterval(updateClock, 1000);

    // Sound toggle
    const soundBtn = document.getElementById('toggleSoundBtn');
    if (soundBtn) {
        soundBtn.addEventListener('click', () => {
            soundEnabled = !soundEnabled;
            soundBtn.innerHTML = soundEnabled 
                ? '<i class="fas fa-volume-up text-gray-700"></i>' 
                : '<i class="fas fa-volume-mute text-gray-400"></i>';
        });
    }

    // Fullscreen toggle
    const fsBtn = document.getElementById('fullscreenBtn');
    if (fsBtn) {
        fsBtn.addEventListener('click', toggleFullscreen);
    }

    // Camera Flip View toggle
    const flipBtn = document.getElementById('flipCameraBtn');
    if (flipBtn) {
        flipBtn.addEventListener('click', () => {
            isCameraFlipped = !isCameraFlipped;
            localStorage.setItem('kiosk_camera_flipped', isCameraFlipped ? 'true' : 'false');
            applyCameraFlip();
        });
    }
    applyCameraFlip();

    // Weekly Attendance Log Tabs
    const tabWeek = document.getElementById('tabThisWeekBtn');
    const tabPast = document.getElementById('tabPastDaysBtn');
    const weekTitle = document.getElementById('weekLogTitle');

    if (tabWeek && tabPast) {
        tabWeek.addEventListener('click', () => {
            currentTabMode = 'this_week';
            tabWeek.className = 'px-2 py-0.5 rounded-md font-semibold bg-blue-100 text-blue-700 transition';
            tabPast.className = 'px-2 py-0.5 rounded-md font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition';
            if (weekTitle) weekTitle.textContent = "This Week's Attendance";
            renderShowcaseWeekTable();
        });

        tabPast.addEventListener('click', () => {
            currentTabMode = 'past_days';
            tabPast.className = 'px-2 py-0.5 rounded-md font-semibold bg-blue-100 text-blue-700 transition';
            tabWeek.className = 'px-2 py-0.5 rounded-md font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition';
            if (weekTitle) weekTitle.textContent = "Past Attendance History";
            renderShowcaseWeekTable();
        });
    }

    // Load AI models and face database in parallel
    const modelsReady = await loadModels();
    if (modelsReady) {
        await loadFaceDatabase();
        await startCamera();
    }
});

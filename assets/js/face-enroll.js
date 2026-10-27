/**
 * Face Enrollment Component for Administrator Employee Management
 * Powered by face-api.js
 */

let enrollVideo = null;
let enrollCanvas = null;
let enrollCtx = null;
let enrollStream = null;
let enrollEmployeeId = null;
let isEnrollModelsLoaded = false;
let isDetectingEnroll = false;
let currentEnrollDetection = null;

async function initEnrollModels() {
    if (isEnrollModelsLoaded) return true;
    const statusEl = document.getElementById('enrollStatus');
    if (statusEl) statusEl.textContent = 'Loading Face AI models...';

    const localModels = ENROLL_CONFIG.modelsUrl;
    const cdnModels = 'https://cdn.jsdelivr.net/gh/cgarciagl/face-api.js@master/weights/';

    try {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(localModels),
            faceapi.nets.faceLandmark68Net.loadFromUri(localModels),
            faceapi.nets.faceLandmark68TinyNet.loadFromUri(localModels),
            faceapi.nets.faceRecognitionNet.loadFromUri(localModels)
        ]);
        isEnrollModelsLoaded = true;
    } catch (e) {
        console.warn('Local models failed, trying CDN:', e);
        try {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(cdnModels),
                faceapi.nets.faceLandmark68Net.loadFromUri(cdnModels),
                faceapi.nets.faceLandmark68TinyNet.loadFromUri(cdnModels),
                faceapi.nets.faceRecognitionNet.loadFromUri(cdnModels)
            ]);
            isEnrollModelsLoaded = true;
        } catch (cdnErr) {
            console.error('Failed to load models:', cdnErr);
            if (statusEl) statusEl.textContent = 'Error loading face models.';
            return false;
        }
    }
    return true;
}

async function openFaceEnrollModal(employeeId, employeeName) {
    enrollEmployeeId = employeeId;
    const modal = document.getElementById('faceEnrollModal');
    const nameEl = document.getElementById('enrollEmployeeName');
    const statusEl = document.getElementById('enrollStatus');
    const captureBtn = document.getElementById('captureFaceBtn');
    
    if (nameEl) nameEl.textContent = employeeName;
    if (captureBtn) captureBtn.disabled = true;
    if (statusEl) statusEl.textContent = 'Preparing camera...';
    if (modal) modal.classList.remove('hidden');

    // Load existing face templates for this employee
    loadExistingEmployeeFaces(employeeId);

    // Initialize models
    const ready = await initEnrollModels();
    if (!ready) return;

    // Start video stream
    enrollVideo = document.getElementById('enrollVideo');
    enrollCanvas = document.getElementById('enrollCanvas');
    if (!enrollVideo || !enrollCanvas) return;

    enrollCtx = enrollCanvas.getContext('2d');

    try {
        enrollStream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
            audio: false
        });
        enrollVideo.srcObject = enrollStream;
        await new Promise(r => { enrollVideo.onloadedmetadata = () => r(); });
        enrollVideo.play();
        startEnrollDetectionLoop();
    } catch (err) {
        console.error('Camera error:', err);
        if (statusEl) statusEl.textContent = 'Camera error: ' + err.message;
    }
}

function closeFaceEnrollModal() {
    isDetectingEnroll = false;
    if (enrollStream) {
        enrollStream.getTracks().forEach(track => track.stop());
        enrollStream = null;
    }
    const modal = document.getElementById('faceEnrollModal');
    if (modal) modal.classList.add('hidden');
    currentEnrollDetection = null;
}

async function loadExistingEmployeeFaces(employeeId) {
    const list = document.getElementById('existingFacesList');
    if (!list) return;
    list.innerHTML = '<p class="text-xs text-gray-400">Loading enrolled templates...</p>';

    try {
        const res = await fetch(`${ENROLL_CONFIG.faceApiUrl}?action=get_employee_faces&employee_id=${employeeId}`);
        const data = await res.json();
        if (data.success && data.faces && data.faces.length > 0) {
            list.innerHTML = data.faces.map(f => `
                <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg border text-xs">
                    <div class="flex items-center space-x-2">
                        ${f.photo_url ? `<img src="${f.photo_url}" class="w-8 h-8 rounded-full object-cover border">` : '<i class="fas fa-id-badge text-gray-400 text-lg"></i>'}
                        <div>
                            <span class="font-medium text-gray-700">Template #${f.id}</span>
                            <span class="block text-[10px] text-gray-400">${new Date(f.created_at).toLocaleDateString()}</span>
                        </div>
                    </div>
                    <button onclick="deleteFaceTemplate(${f.id})" class="text-red-500 hover:text-red-700 p-1" title="Delete template">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `).join('');
        } else {
            list.innerHTML = '<p class="text-xs text-amber-600 bg-amber-50 p-2 rounded border border-amber-200">No face templates enrolled yet.</p>';
        }
    } catch (e) {
        list.innerHTML = '<p class="text-xs text-red-500">Failed to load templates</p>';
    }
}

async function deleteFaceTemplate(faceId) {
    if (!confirm('Are you sure you want to delete this face template?')) return;
    const formData = new FormData();
    formData.append('action', 'delete_face');
    formData.append('face_id', faceId);
    formData.append(ENROLL_CONFIG.csrfTokenName, ENROLL_CONFIG.csrfToken);

    try {
        const res = await fetch(ENROLL_CONFIG.faceApiUrl, { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            loadExistingEmployeeFaces(enrollEmployeeId);
            // Also refresh employee list if on list page
            const badge = document.getElementById(`faceBadge_${enrollEmployeeId}`);
            if (badge) badge.innerHTML = '<span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-500">Not Enrolled</span>';
        } else {
            alert(json.message || 'Failed to delete template');
        }
    } catch (err) {
        alert('Server error');
    }
}

async function startEnrollDetectionLoop() {
    isDetectingEnroll = true;
    const statusEl = document.getElementById('enrollStatus');
    const captureBtn = document.getElementById('captureFaceBtn');
    const options = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 });

    async function scan() {
        if (!isDetectingEnroll || !enrollVideo || enrollVideo.paused || enrollVideo.ended) {
            return;
        }

        if (enrollCanvas.width !== enrollVideo.videoWidth || enrollCanvas.height !== enrollVideo.videoHeight) {
            enrollCanvas.width = enrollVideo.videoWidth;
            enrollCanvas.height = enrollVideo.videoHeight;
        }

        enrollCtx.clearRect(0, 0, enrollCanvas.width, enrollCanvas.height);

        try {
            const detection = await faceapi.detectSingleFace(enrollVideo, options)
                .withFaceLandmarks(true)
                .withFaceDescriptor();

            if (detection) {
                currentEnrollDetection = detection;
                const resized = faceapi.resizeResults(detection, {
                    width: enrollCanvas.width,
                    height: enrollCanvas.height
                });

                // Draw bounding box
                const box = resized.detection.box;
                enrollCtx.strokeStyle = '#10b981';
                enrollCtx.lineWidth = 3;
                enrollCtx.strokeRect(box.x, box.y, box.width, box.height);

                if (box.width > 80) {
                    if (statusEl) statusEl.innerHTML = '<span class="text-green-600 font-semibold"><i class="fas fa-check-circle mr-1"></i>Face detected! Click "Register Face" below.</span>';
                    if (captureBtn) captureBtn.disabled = false;
                } else {
                    if (statusEl) statusEl.innerHTML = '<span class="text-amber-600"><i class="fas fa-search-plus mr-1"></i>Move a bit closer to the camera.</span>';
                    if (captureBtn) captureBtn.disabled = true;
                }
            } else {
                currentEnrollDetection = null;
                if (statusEl) statusEl.innerHTML = '<span class="text-gray-500"><i class="fas fa-user mr-1"></i>Position your face inside the camera view.</span>';
                if (captureBtn) captureBtn.disabled = true;
            }
        } catch (e) {
            console.error('Enroll scan error:', e);
        }

        if (isDetectingEnroll) {
            requestAnimationFrame(scan);
        }
    }

    requestAnimationFrame(scan);
}

async function captureAndEnrollFace() {
    if (!currentEnrollDetection || !enrollEmployeeId) return;
    const captureBtn = document.getElementById('captureFaceBtn');
    const statusEl = document.getElementById('enrollStatus');

    if (captureBtn) {
        captureBtn.disabled = true;
        captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Registering...';
    }

    // Capture snapshot
    const offCanvas = document.createElement('canvas');
    offCanvas.width = enrollVideo.videoWidth || 640;
    offCanvas.height = enrollVideo.videoHeight || 480;
    const offCtx = offCanvas.getContext('2d');
    offCtx.drawImage(enrollVideo, 0, 0, offCanvas.width, offCanvas.height);

    offCanvas.toBlob(async blob => {
        const descriptorArray = Array.from(currentEnrollDetection.descriptor);

        const formData = new FormData();
        formData.append('action', 'enroll');
        formData.append('employee_id', enrollEmployeeId);
        formData.append('descriptor', JSON.stringify(descriptorArray));
        formData.append('photo', blob, `enroll_${enrollEmployeeId}.jpg`);
        formData.append(ENROLL_CONFIG.csrfTokenName, ENROLL_CONFIG.csrfToken);

        try {
            const res = await fetch(ENROLL_CONFIG.faceApiUrl, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                if (statusEl) statusEl.innerHTML = `<span class="text-green-600 font-bold"><i class="fas fa-check-double mr-1"></i>${data.message}</span>`;
                loadExistingEmployeeFaces(enrollEmployeeId);

                // Update badge in table if present
                const badge = document.getElementById(`faceBadge_${enrollEmployeeId}`);
                if (badge) {
                    badge.innerHTML = '<span class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800 font-medium"><i class="fas fa-check mr-1"></i>Enrolled</span>';
                }

                setTimeout(() => {
                    if (captureBtn) {
                        captureBtn.disabled = false;
                        captureBtn.innerHTML = '<i class="fas fa-camera mr-1"></i>Add Another Sample';
                    }
                }, 1000);
            } else {
                alert(data.message || 'Failed to enroll face');
                if (captureBtn) {
                    captureBtn.disabled = false;
                    captureBtn.innerHTML = '<i class="fas fa-camera mr-1"></i>Register Face';
                }
            }
        } catch (err) {
            alert('Server error occurred during enrollment.');
            if (captureBtn) {
                captureBtn.disabled = false;
                captureBtn.innerHTML = '<i class="fas fa-camera mr-1"></i>Register Face';
            }
        }
    }, 'image/jpeg', 0.85);
}

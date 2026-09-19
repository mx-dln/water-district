document.addEventListener('DOMContentLoaded', async function() {
    const stepGPS = document.getElementById('step-gps');
    const stepCamera = document.getElementById('step-camera');
    const stepProcessing = document.getElementById('step-processing');
    const stepResult = document.getElementById('step-result');
    const gpsStatus = document.getElementById('gpsStatus');
    const gpsSpinner = document.getElementById('gpsSpinner');
    const previewImg = document.getElementById('previewImg');
    const capturedPreview = document.getElementById('capturedPreview');
    const retakeBtn = document.getElementById('retakeBtn');
    const submitBtn = document.getElementById('submitBtn');

    let currentLat = null, currentLng = null;
    let currentFile = null;
    const MAX_RETRIES = 3;
    let retryCount = 0;

    // Step 1: Get GPS location
    try {
        gpsStatus.textContent = 'Detecting your location...';
        const pos = await getCurrentPosition();
        currentLat = pos.lat;
        currentLng = pos.lng;

        let accuracyNote = '';
        if (pos.accuracy > 50) {
            accuracyNote = ' (low accuracy - move to open area for better results)';
        } else if (pos.accuracy > 20) {
            accuracyNote = ' (moderate accuracy)';
        }

        gpsStatus.textContent = `Location found! (${pos.lat.toFixed(6)}, ${pos.lng.toFixed(6)}) Accuracy: ${pos.accuracy.toFixed(0)}m${accuracyNote}`;
        gpsSpinner.classList.remove('animate-spin');
        gpsSpinner.innerHTML = '<i class="fas fa-check text-green-600"></i>';
        stepGPS.classList.remove('bg-blue-50', 'border-blue-200');
        stepGPS.classList.add('bg-green-50', 'border-green-200');

        if (HAS_POLYGON) {
            const inside = pointInPolygon(currentLat, currentLng, POLYGON_COORDS);
            if (!inside) {
                showError('You appear to be outside the office boundary. Try moving closer to the office or wait for GPS accuracy to improve.');
                showErrorMap(currentLat, currentLng);
                return;
            }
            gpsStatus.textContent += ' | Inside office boundary';
        } else {
            gpsStatus.textContent += ' | Geofence not configured';
        }

        setTimeout(() => {
            stepGPS.classList.add('hidden');
            stepCamera.classList.remove('hidden');
        }, 500);

    } catch (err) {
        showError(err.message || 'Failed to get GPS location. Please enable location services.');
        return;
    }

    // Setup file input
    setupFileInput('photoInput', 'previewImg', function(file) {
        currentFile = file;
        capturedPreview.classList.remove('hidden');
        document.getElementById('cameraLabel').classList.add('hidden');
    });

    // Submit button
    submitBtn.addEventListener('click', function() {
        if (!currentFile) return;
        retryCount = 0;
        submitAttendance(currentFile);
    });

    // Retake button
    retakeBtn.addEventListener('click', function() {
        capturedPreview.classList.add('hidden');
        resetFileInput('photoInput');
        currentFile = null;
        document.getElementById('cameraLabel').classList.remove('hidden');
    });

    async function submitAttendance(file) {
        stepCamera.classList.add('hidden');
        stepProcessing.classList.remove('hidden');
        updateRetryStatus();

        const formData = new FormData();
        formData.append('action', 'record');
        formData.append('type', ATTENDANCE_TYPE);
        formData.append('employee_id', EMPLOYEE_ID);
        formData.append('latitude', currentLat);
        formData.append('longitude', currentLng);
        formData.append('photo', file, 'selfie_' + Date.now() + '.jpg');
        formData.append(CSRF_TOKEN_NAME, CSRF_TOKEN);

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 60000);

            const url = '/api/attendance.php';
            console.log('Fetching:', url);
            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                signal: controller.signal
            });
            clearTimeout(timeoutId);

            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers.get('content-type'));

            const text = await response.text();
            console.log('Raw response:', text);

            let result;
            try {
                result = JSON.parse(text);
            } catch (parseErr) {
                console.error('JSON parse error:', text);
                throw new Error('Invalid response: ' + text.substring(0, 100));
            }

            if (result.success) {
                retryCount = 0;
                stepProcessing.classList.add('hidden');
                stepResult.classList.remove('hidden');
                document.getElementById('resultSuccess').classList.remove('hidden');
                document.getElementById('resultMessage').textContent = result.message || `${ATTENDANCE_TYPE.replace('-', ' ').toUpperCase()} recorded successfully`;
                document.getElementById('resultDetails').innerHTML = `
                    <div class="flex justify-between p-2 bg-gray-50 rounded"><span class="text-gray-500">Type</span><span class="font-medium">${result.data?.type || ATTENDANCE_TYPE}</span></div>
                    <div class="flex justify-between p-2 bg-gray-50 rounded"><span class="text-gray-500">Time</span><span class="font-medium">${result.data?.time || new Date().toLocaleTimeString()}</span></div>
                    <div class="flex justify-between p-2 bg-gray-50 rounded"><span class="text-gray-500">Location</span><span class="font-medium">${currentLat.toFixed(6)}, ${currentLng.toFixed(6)}</span></div>
                    <div class="flex justify-between p-2 bg-gray-50 rounded"><span class="text-gray-500">Status</span><span class="font-medium text-green-600">Verified</span></div>
                `;
                showResultMap(currentLat, currentLng, 'success');
            } else {
                retryCount = 0;
                stepProcessing.classList.add('hidden');
                stepResult.classList.remove('hidden');
                document.getElementById('resultError').classList.remove('hidden');
                document.getElementById('errorMessage').textContent = result.message || 'Failed to record attendance';
                showResultMap(currentLat, currentLng, 'error');
            }
        } catch (err) {
            console.error('Attendance error:', err);
            console.error('Error name:', err.name);
            console.error('Error message:', err.message);
            retryCount++;

            let debugInfo = '';
            if (err.name === 'TypeError') {
                debugInfo = ' (URL: /api/attendance.php, Type: ' + err.constructor.name + ')';
            } else if (err.message) {
                debugInfo = ' (' + err.message.substring(0, 80) + ')';
            }

            if (retryCount <= MAX_RETRIES) {
                document.getElementById('retryStatus').classList.remove('hidden');
                document.getElementById('retryCount').textContent = retryCount;
                document.getElementById('retryMax').textContent = MAX_RETRIES;

                await new Promise(r => setTimeout(r, 2000));
                submitAttendance(file);
            } else {
                retryCount = 0;
                document.getElementById('retryStatus').classList.add('hidden');
                stepProcessing.classList.add('hidden');
                stepResult.classList.remove('hidden');
                document.getElementById('resultError').classList.remove('hidden');
                document.getElementById('errorMessage').textContent = 'Error after ' + MAX_RETRIES + ' attempts.' + debugInfo;
                showResultMap(currentLat, currentLng, 'error');
            }
        }
    }

    function updateRetryStatus() {
        if (retryCount > 0) {
            document.getElementById('retryStatus').classList.remove('hidden');
            document.getElementById('retryCount').textContent = retryCount;
            document.getElementById('retryMax').textContent = MAX_RETRIES;
        } else {
            document.getElementById('retryStatus').classList.add('hidden');
        }
    }

    function showError(msg) {
        const el = document.getElementById('errorMessage');
        if (el) el.textContent = msg;
        [stepGPS, stepCamera, stepProcessing].forEach(s => { if (s) s.classList.add('hidden'); });
        stepResult.classList.remove('hidden');
        document.getElementById('resultError').classList.remove('hidden');
        document.querySelector('#resultSuccess')?.classList.add('hidden');
    }

    function showResultMap(lat, lng, type) {
        const mapId = type === 'success' ? 'resultMap' : 'errorMap';
        const el = document.getElementById(mapId);
        if (!el) return;
        const map = L.map(el).setView([lat, lng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
        }).addTo(map);
        const color = type === 'success' ? 'green' : 'red';
        L.circleMarker([lat, lng], {
            radius: 8, color: color, fillColor: color, fillOpacity: 0.5
        }).addTo(map).bindPopup(type === 'success' ? 'Your Location' : 'Your Location').openPopup();
        if (HAS_POLYGON && POLYGON_COORDS.length >= 3) {
            L.polygon(POLYGON_COORDS.map(c => [c.lat, c.lng]), {
                color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.1
            }).addTo(map);
        }
        setTimeout(() => map.invalidateSize(), 500);
    }

    function showErrorMap(lat, lng) {
        showResultMap(lat, lng, 'error');
    }
});

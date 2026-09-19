let gpsData = null;

function getCurrentPosition() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Geolocation is not supported by this browser'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (position) => {
                gpsData = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                    timestamp: position.timestamp
                };
                resolve(gpsData);
            },
            (error) => {
                let msg = 'Failed to get location';
                switch(error.code) {
                    case error.PERMISSION_DENIED: msg = 'Location permission denied. Please enable GPS.'; break;
                    case error.POSITION_UNAVAILABLE: msg = 'GPS signal unavailable. Try moving to an open area.'; break;
                    case error.TIMEOUT: msg = 'GPS request timed out. Please try again.'; break;
                }
                reject(new Error(msg));
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 30000
            }
        );
    });
}

function pointInPolygon(lat, lng, polygon) {
    if (!polygon || polygon.length < 3) return false;
    const buf = 0.000045;
    const checks = [
        [lat, lng],
        [lat + buf, lng],
        [lat - buf, lng],
        [lat, lng + buf],
        [lat, lng - buf],
        [lat + buf, lng + buf],
        [lat - buf, lng - buf],
    ];
    for (const [clat, clng] of checks) {
        let inside = false;
        let j = polygon.length - 1;
        for (let i = 0; i < polygon.length; i++) {
            const xi = polygon[i].lat, yi = polygon[i].lng;
            const xj = polygon[j].lat, yj = polygon[j].lng;
            if (((yi > clng) !== (yj > clng)) && (clat < (xj - xi) * (clng - yi) / (yj - yi) + xi)) {
                inside = !inside;
            }
            j = i;
        }
        if (inside) return true;
    }
    return false;
}

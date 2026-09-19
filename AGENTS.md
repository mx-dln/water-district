# GeoSnap Workforce Management System

## Project Context
- **System:** GeoSnap Workforce Management System for Cauayan City Water District
- **Stack:** PHP 8+, MySQL, Tailwind CSS, Leaflet.js, OpenStreetMap
- **Key Feature:** Polygon-based geofencing with selfie verification attendance
- **No frameworks used:** Pure PHP, no Laravel/CodeIgniter/React/Vue/Bootstrap/jQuery

## Database
- **Host:** localhost
- **User:** root
- **Password:** (none)
- **Database:** water_district
- **Schema:** database/schema.sql

## Default Credentials
- **Admin:** admin / admin123

## Key Architecture
- **Front controller:** index.php routes via ?page= parameter
- **Auth:** Session-based with Remember Me cookie
- **Security:** PDO prepared statements, CSRF tokens, XSS protection, password hashing
- **Attendance flow:** GPS → Camera → Polygon check → Watermark → Save
- **Point-in-Polygon:** Ray-casting algorithm in JS (client) and PHP (server)

## Important Files
- `index.php` - Entry point and router
- `config/database.php` - Database connection
- `includes/functions.php` - Core helpers (pointInPolygon, watermarkImage, etc.)
- `includes/security.php` - Security functions (CSRF, validation)
- `includes/auth.php` - Authentication
- `api/attendance.php` - Attendance recording API
- `assets/js/attendance.js` - Main attendance capture client logic
- `assets/js/camera.js` - Camera module
- `assets/js/gps.js` - GPS and polygon checking
- `modules/polygon.php` - Polygon drawing interface (Leaflet.js)

## Development Notes
- Tailwind CSS loaded via CDN
- Leaflet.js and OpenStreetMap via CDN
- Chart.js for dashboard charts
- Alpine.js for UI interactivity
- Attendance photos stored in uploads/attendance/YYYY/MM/
- Watermark uses GD library (imagestring)

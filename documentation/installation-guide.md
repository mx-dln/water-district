# GeoSnap Workforce Management System - Installation Guide

## Cauayan City Water District

---

## System Requirements

- **Web Server:** Apache 2.4+ (XAMPP Recommended)
- **PHP:** 8.0 or higher
- **MySQL:** 5.7 or higher (or MariaDB 10.3+)
- **Extensions:** PDO, MySQLi, GD, FileInfo, JSON, Session
- **Browser:** Chrome, Firefox, Edge, Safari (latest versions)
- **Device:** Smartphone with camera and GPS (for employees)

---

## Step 1: Install XAMPP

1. Download XAMPP from https://www.apachefriends.org/
2. Install XAMPP with Apache and MySQL components
3. Start Apache and MySQL from the XAMPP Control Panel

## Step 2: Setup the Project

1. Navigate to `C:\xampp\htdocs\` (or your XAMPP installation directory)
2. Create a folder named `water-district-attendance`
3. Copy all project files into this folder

Alternatively, if the project files are elsewhere, create a symlink or copy:
```
cp -r /path/to/water-district-attendance C:\xampp\htdocs\water-district-attendance
```

## Step 3: Create the Database

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Click "New" on the left sidebar
3. Enter database name: `water_district`
4. Choose collation: `utf8mb4_unicode_ci`
5. Click "Create"
6. Click on the new `water_district` database
7. Click "Import" tab
8. Click "Choose File" and select `database/schema.sql`
9. Click "Go" to import

**Alternative via command line:**
```
mysql -u root -p < database/schema.sql
```

## Step 4: Configure the Application

1. Open `config/database.php` and verify connection settings:
   - Host: localhost
   - Database: water_district
   - Username: root
   - Password: (empty)

2. `APP_URL` is detected automatically from the current host, port, and subfolder. For special deployments behind a proxy, set the `APP_URL` environment variable instead of editing `config/constants.php`.

## Step 5: Set Directory Permissions

Ensure the following directories are writable:
- `uploads/attendance/`

On Windows, these should already be writable. On Linux/macOS:
```
chmod -R 755 uploads/
chmod -R 755 uploads/attendance/
```

## Step 6: Access the Application

1. Open your browser and navigate to:
   ```
   http://localhost/water-district-attendance/
   ```

2. Login with default credentials:
   - **Username:** admin
   - **Password:** admin123

## Step 7: Initial Configuration

1. **Draw Office Polygon:** Go to "Office Polygon" in the sidebar and draw your office boundary on the map.
2. **Add Departments:** Create departments under "Departments" menu.
3. **Add Employees:** Create employee accounts under "Employees" menu. Each employee gets their own login credentials.
4. **Configure Settings:** Update system settings under "Settings" menu.

---

## First-Time Setup Checklist

- [ ] Database imported successfully
- [ ] Application opens correctly from the current PC/browser URL
- [ ] Office polygon drawn on the map
- [ ] At least one department created
- [ ] Employee accounts created
- [ ] Employees can access the system via their smartphones

---

## Default Accounts

| Role        | Username | Password  |
|-------------|----------|-----------|
| Admin       | admin    | admin123  |

**Important:** Change the default admin password immediately after first login.

---

## Troubleshooting

### Blank Page / White Screen
- Check PHP error logs in `C:\xampp\php\logs\php_error_log`
- Ensure all PHP extensions are enabled (PDO, GD, FileInfo)

### Database Connection Error
- Verify MySQL is running in XAMPP Control Panel
- Check database credentials in `config/database.php`
- Ensure the database `water_district` exists

### Camera Not Working
- Use HTTPS in production (camera requires secure context)
- On localhost, camera works with most browsers (Chrome, Firefox)
- Ensure browser permissions for camera and location are allowed

### GPS / Location Not Working
- Ensure location services are enabled on the device
- Use a device with actual GPS (not just WiFi triangulation)
- On desktop, location may be less accurate

### Map Not Displaying
- Internet connection required for OpenStreetMap tiles
- Check browser console for JavaScript errors
- Ensure Leaflet.js CDN URLs are accessible

### "Outside Office Boundary" Error
- Redraw the office polygon more accurately
- Ensure the polygon covers the actual office area
- Check GPS accuracy (should be < 20m for reliable results)

---

## Production Deployment

For production deployment:

1. **Use HTTPS:** Enable SSL/TLS for secure camera and GPS access
2. **Change Default Password:** Immediately change the admin password
3. **Stronger Passwords:** Enforce strong password policy
4. **Backup Database:** Set up regular database backups
5. **Set APP_URL when needed:** Use an `APP_URL` environment variable for proxy or fixed-domain deployments
6. **Disable Error Display:** Set `display_errors = Off` in php.ini
7. **Set Strong Cookie Parameters:** Update session security settings

---

## File Structure

```
water-district-attendance/
├── index.php              # Main entry point / router
├── .htaccess              # Apache security rules
├── config/
│   ├── database.php       # Database connection class
│   ├── app.php            # Application bootstrap
│   └── constants.php      # Application constants
├── database/
│   └── schema.sql         # Complete database schema
├── includes/
│   ├── header.php         # HTML head and CSS includes
│   ├── footer.php         # JS includes and closing tags
│   ├── sidebar.php        # Navigation sidebar
│   ├── navbar.php         # Top navigation bar
│   ├── functions.php      # Core helper functions
│   ├── security.php       # Security functions
│   └── auth.php           # Authentication functions
├── modules/
│   ├── auth/              # Login, logout, password management
│   ├── dashboard/         # Admin and employee dashboards
│   ├── attendance/        # Time in/out, break, history, evidence
│   ├── employees/         # Employee CRUD
│   ├── departments/       # Department CRUD
│   ├── leaves/            # Leave management
│   ├── polygon/           # Polygon geofencing (via main)
│   └── reports/           # Reporting module
├── api/                   # AJAX API endpoints
│   ├── attendance.php     # Attendance recording
│   ├── employee.php       # Employee operations
│   ├── department.php     # Department operations
│   ├── polygon.php        # Polygon data retrieval
│   └── leave.php          # Leave operations
├── assets/
│   ├── css/app.css        # Custom styles
│   └── js/                # JavaScript modules
│       ├── app.js         # Main app JS
│       ├── camera.js      # Camera capture module
│       ├── gps.js         # GPS and geofencing module
│       └── attendance.js  # Attendance capture logic
├── uploads/attendance/    # Attendance photo storage
└── documentation/         # Project documentation
```

---

## Support

For issues and support, contact the system administrator or the development team.

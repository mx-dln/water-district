$ftp = "ftpupload.net"
$user = "if0_42405032"
$pass = "LGeHdI3ukkd7ia"

$base = "C:\Users\karen\OneDrive\Documents\Sites\Lab-management"

$files = @(
    @("$base\public\migrate.php", "/htdocs/public/migrate.php"),
    @("$base\resources\views\attendance\index.php", "/htdocs/resources/views/attendance/index.php"),
    @("$base\resources\views\attendance\today.php", "/htdocs/resources/views/attendance/today.php"),
    @("$base\resources\views\attendance\student-history.php", "/htdocs/resources/views/attendance/student-history.php"),
    @("$base\resources\views\attendance\student-dashboard.php", "/htdocs/resources/views/attendance/student-dashboard.php"),
    @("$base\app\Controllers\AttendanceController.php", "/htdocs/app/Controllers/AttendanceController.php"),
    @("$base\resources\views\attendance\scan.php", "/htdocs/resources/views/attendance/scan.php"),
    @("$base\routes\web.php", "/htdocs/routes/web.php")
)

foreach ($f in $files) {
    try {
        $r = [System.Net.FtpWebRequest]::Create("ftp://$ftp$($f[1])")
        $r.Credentials = New-Object System.Net.NetworkCredential($user, $pass)
        $r.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $r.UseBinary = $true
        $b = [System.IO.File]::ReadAllBytes($f[0])
        $r.ContentLength = $b.Length
        $s = $r.GetRequestStream()
        $s.Write($b, 0, $b.Length)
        $s.Close()
        $r.GetResponse().Close()
        Write-Host "[OK] $($f[1])" -ForegroundColor Green
    } catch {
        Write-Host "[FAIL] $($f[1]) : $_" -ForegroundColor Red
    }
}

Write-Host "`nDone! Run migration: https://uphs-lab.great-site.net/public/migrate.php"

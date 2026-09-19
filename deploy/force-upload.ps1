$ftp = "ftpupload.net"
$user = "if0_42405032"
$pass = "LGeHdI3ukkd7ia"
$root = "/htdocs"

$files = @(
    "app/Controllers/AttendanceController.php",
    "resources/views/attendance/scan.php",
    "resources/views/attendance/student-dashboard.php",
    "routes/web.php",
    "public/index.php",
    "public/debug.php"
)

function Upload-File($local, $remote) {
    $fullRemote = "/htdocs/$remote"
    $req = [System.Net.FtpWebRequest]::Create("ftp://ftpupload.net$fullRemote")
    $req.Credentials = New-Object System.Net.NetworkCredential("if0_42405032", "LGeHdI3ukkd7ia")
    $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $req.UseBinary = $true
    $b = [System.IO.File]::ReadAllBytes($local)
    $req.ContentLength = $b.Length
    $s = $req.GetRequestStream()
    $s.Write($b, 0, $b.Length)
    $s.Close()
    $req.GetResponse().Close()
    Write-Host "[OK] $remote" -ForegroundColor Green
}

$base = "C:\Users\karen\OneDrive\Documents\Sites\Lab-management"
Upload-File "$base\app\Controllers\AttendanceController.php" "app/Controllers/AttendanceController.php"
Upload-File "$base\resources\views\attendance\scan.php" "resources/views/attendance/scan.php"
Upload-File "$base\resources\views\attendance\student-dashboard.php" "resources/views/attendance/student-dashboard.php"
Upload-File "$base\routes\web.php" "routes/web.php"
Upload-File "$base\public\index.php" "public/index.php"
Upload-File "$base\public\debug.php" "public/debug.php"

Write-Host "`nDone. Visit: https://uphs-lab.great-site.net/public/debug.php"

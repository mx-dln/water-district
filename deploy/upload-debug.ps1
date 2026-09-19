$ftp = "ftpupload.net"
$user = "if0_42405032"
$pass = "LGeHdI3ukkd7ia"

# Upload AttendanceController
$p = "C:\Users\karen\OneDrive\Documents\Sites\Lab-management\app\Controllers\AttendanceController.php"
$r = "/htdocs/app/Controllers/AttendanceController.php"
$req = [System.Net.FtpWebRequest]::Create("ftp://$ftp$r")
$req.Credentials = New-Object System.Net.NetworkCredential($user, $pass)
$req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
$req.UseBinary = $true
$b = [System.IO.File]::ReadAllBytes($p)
$req.ContentLength = $b.Length
$s = $req.GetRequestStream()
$s.Write($b, 0, $b.Length)
$s.Close()
$req.GetResponse().Close()
Write-Host "[OK] AttendanceController.php"

# Upload view-log.php
$p2 = "C:\Users\karen\OneDrive\Documents\Sites\Lab-management\public\view-log.php"
$r2 = "/htdocs/public/view-log.php"
$req2 = [System.Net.FtpWebRequest]::Create("ftp://$ftp$r2")
$req2.Credentials = New-Object System.Net.NetworkCredential($user, $pass)
$req2.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
$req2.UseBinary = $true
$b2 = [System.IO.File]::ReadAllBytes($p2)
$req2.ContentLength = $b2.Length
$s2 = $req2.GetRequestStream()
$s2.Write($b2, 0, $b2.Length)
$s2.Close()
$req2.GetResponse().Close()
Write-Host "[OK] view-log.php"

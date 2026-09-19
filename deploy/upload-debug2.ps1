$p = "C:\Users\karen\OneDrive\Documents\Sites\Lab-management\public\debug.php"
$r = "/htdocs/public/debug.php"
$req = [System.Net.FtpWebRequest]::Create("ftp://ftpupload.net$r")
$req.Credentials = New-Object System.Net.NetworkCredential("if0_42405032", "LGeHdI3ukkd7ia")
$req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
$req.UseBinary = $true
$b = [System.IO.File]::ReadAllBytes($p)
$req.ContentLength = $b.Length
$s = $req.GetRequestStream()
$s.Write($b, 0, $b.Length)
$s.Close()
$req.GetResponse().Close()
Write-Host "[OK] uploaded"

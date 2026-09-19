$localPath = "C:\Users\karen\OneDrive\Documents\Sites\Lab-management\routes\web.php"
$remoteFile = "/htdocs/routes/web.php"
try {
    $req = [System.Net.FtpWebRequest]::Create("ftp://ftpupload.net$remoteFile")
    $req.Credentials = New-Object System.Net.NetworkCredential("if0_42405032", "LGeHdI3ukkd7ia")
    $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $req.UseBinary = $true
    $fileBytes = [System.IO.File]::ReadAllBytes($localPath)
    $req.ContentLength = $fileBytes.Length
    $stream = $req.GetRequestStream()
    $stream.Write($fileBytes, 0, $fileBytes.Length)
    $stream.Close()
    $req.GetResponse().Close()
    Write-Host "[OK] routes/web.php" -ForegroundColor Green
} catch { Write-Host "[FAIL] $_" -ForegroundColor Red }

$projectDir = "C:\Users\karen\OneDrive\Documents\Sites\Lab-management"
$ftpHost = "ftpupload.net"
$ftpUser = "if0_42405032"
$ftpPass = "LGeHdI3ukkd7ia"
$remotePath = "/htdocs"

$files = @(
    "resources\views\auth\login.php",
    "resources\views\layouts\sidebar.php",
    "resources\views\layouts\navbar.php",
    "resources\views\attendance\student-login.php",
    "resources\views\attendance\student-dashboard.php",
    "resources\views\attendance\student-scan.php",
    "resources\views\attendance\student-history.php",
    "resources\views\attendance\student-profile.php"
)

foreach ($file in $files) {
    $localPath = "$projectDir\$file"
    $remoteFile = "$remotePath/$($file -replace '\\', '/')"
    $remoteDir = [System.IO.Path]::GetDirectoryName($remoteFile)

    try {
        $dirs = $remoteDir.Split('/') | Where-Object { $_ }
        $currentPath = ""
        foreach ($d in $dirs) {
            $currentPath += "/$d"
            try {
                $req = [System.Net.FtpWebRequest]::Create("ftp://$ftpHost$currentPath")
                $req.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
                $req.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
                $req.GetResponse().Close()
            } catch {}
        }

        $req = [System.Net.FtpWebRequest]::Create("ftp://$ftpHost$remoteFile")
        $req.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $req.UseBinary = $true
        $fileBytes = [System.IO.File]::ReadAllBytes($localPath)
        $req.ContentLength = $fileBytes.Length
        $stream = $req.GetRequestStream()
        $stream.Write($fileBytes, 0, $fileBytes.Length)
        $stream.Close()
        $req.GetResponse().Close()
        Write-Host "  [OK] $file" -ForegroundColor Green
    } catch {
        Write-Host "  [FAIL] $file : $_" -ForegroundColor Red
    }
}
Write-Host "Done!" -ForegroundColor Green

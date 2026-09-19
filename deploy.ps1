$localRoot = "C:\Users\karen\OneDrive\Documents\Sites\water-district-attendance"
$ftpHost = "ftp://ftpupload.net/htdocs/"
$user = "if0_42429935"
$pass = "9lfqC7wnGqvge"

$skipDirs = @("config", ".git", "node_modules")
$skipFiles = @("deploy.ps1")

function FtpUpload($localPath, $remotePath) {
    try {
        $req = [System.Net.FtpWebRequest]::Create($remotePath)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $req.Credentials = New-Object System.Net.NetworkCredential($user, $pass)
        $req.UsePassive = $true
        $req.UseBinary = $true
        $req.ContentLength = (Get-Item $localPath).Length
        $reqStream = $req.GetRequestStream()
        $fileStream = [System.IO.File]::OpenRead($localPath)
        $fileStream.CopyTo($reqStream)
        $fileStream.Close()
        $reqStream.Close()
        $resp = $req.GetResponse()
        $resp.Close()
        return $true
    } catch {
        Write-Output "  FAIL: $($_.Exception.Message)"
        return $false
    }
}

function FtpMkdir($remotePath) {
    try {
        $req = [System.Net.FtpWebRequest]::Create($remotePath)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
        $req.Credentials = New-Object System.Net.NetworkCredential($user, $pass)
        $req.UsePassive = $true
        $resp = $req.GetResponse()
        $resp.Close()
        return $true
    } catch {
        return $false
    }
}

function FtpDirExists($remotePath) {
    try {
        $req = [System.Net.FtpWebRequest]::Create($remotePath)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::ListDirectory
        $req.Credentials = New-Object System.Net.NetworkCredential($user, $pass)
        $req.UsePassive = $true
        $resp = $req.GetResponse()
        $resp.Close()
        return $true
    } catch {
        return $false
    }
}

function DeployDir($localDir, $remoteDir) {
    $items = Get-ChildItem $localDir
    foreach ($item in $items) {
        $localPath = $item.FullName
        $remotePath = $remoteDir + $item.Name

        if ($item.PSIsContainer) {
            if ($skipDirs -contains $item.Name) { continue }
            if (-not (FtpDirExists($remotePath + "/"))) {
                Write-Output "Creating dir: $remotePath"
                FtpMkdir($remotePath)
            }
            DeployDir $localPath ($remotePath + "/")
        } else {
            if ($skipFiles -contains $item.Name) { continue }
            Write-Output "Uploading: $remotePath"
            FtpUpload $localPath $remotePath
        }
    }
}

Write-Output "=== GeoSnap FTP Deploy ==="
Write-Output "Source: $localRoot"
Write-Output "Target: $ftpHost"
Write-Output ""

DeployDir $localRoot $ftpHost

Write-Output ""
Write-Output "=== Deploy complete ==="

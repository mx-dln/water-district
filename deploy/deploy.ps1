param(
    [string]$FtpHost = "",
    [string]$FtpUser = "",
    [string]$FtpPass = "",
    [string]$RemotePath = "/public_html",
    [switch]$Upload,
    [switch]$SaveCreds,
    [switch]$Diff
)

$projectDir = Split-Path $PSScriptRoot -Parent
$hashFile = "$PSScriptRoot\file_hashes.json"
$exclude = @('.git', 'vendor', 'node_modules', 'storage', 'deploy', '*.log', '*.zip', 'file_hashes.json', '.env', '.env.example')

function Get-ProjectFiles {
    param($Dir)
    $excl = $script:exclude
    Get-ChildItem -Path $Dir -Recurse -File | Where-Object {
        $relative = $_.FullName.Replace($Dir + '\', '')
        foreach ($ex in $excl) {
            if ($relative -like $ex -or $relative -like "$ex\*" -or $relative -like "*\$ex*") {
                return $false
            }
        }
        return $true
    }
}

function Get-MyFileHash {
    param($Path)
    try {
        return (Microsoft.PowerShell.Utility\Get-FileHash -Path $Path -Algorithm MD5).Hash
    } catch { return $null }
}

if ($SaveCreds) {
    $creds = @{ ftpHost = $FtpHost; ftpUser = $FtpUser; ftpPass = $FtpPass; remotePath = $RemotePath }
    $creds | ConvertTo-Json | Set-Content "$PSScriptRoot\ftp_config.json" -Force
    Write-Host "Credentials saved." -ForegroundColor Green
    if (!$Upload) { return }
}

if (!$FtpHost -and !$SaveCreds) {
    $configPath = "$PSScriptRoot\ftp_config.json"
    if (Test-Path $configPath) {
        $cfg = Get-Content $configPath | ConvertFrom-Json
        $FtpHost = $cfg.ftpHost; $FtpUser = $cfg.ftpUser
        $FtpPass = $cfg.ftpPass; $RemotePath = $cfg.remotePath
    }
}

Write-Host "`n========== DEPLOY TRACKER ==========" -ForegroundColor Cyan
Write-Host "Project: $projectDir`n" -ForegroundColor Gray

$currentHashes = @{}
Get-ProjectFiles $projectDir | ForEach-Object {
    $rel = $_.FullName.Replace($projectDir + '\', '')
    $hash = Get-MyFileHash $_.FullName
    if ($hash) { $currentHashes[$rel] = $hash }
}

$hashesExist = Test-Path $hashFile
$changed = @()
$new = @()
$deleted = @()

if ($hashesExist) {
    $previous = Get-Content $hashFile | ConvertFrom-Json
    $prevObj = @{}
    $previous.psobject.Properties | ForEach-Object { $prevObj[$_.Name] = $_.Value }

    foreach ($key in $currentHashes.Keys) {
        if ($prevObj.ContainsKey($key)) {
            if ($prevObj[$key] -ne $currentHashes[$key]) { $changed += $key }
        } else { $new += $key }
    }

    foreach ($key in $prevObj.Keys) {
        if (-not $currentHashes.ContainsKey($key)) { $deleted += $key }
    }
} else {
    Write-Host "First run - no previous snapshot. Creating baseline..." -ForegroundColor Yellow
}

$currentHashes | ConvertTo-Json | Set-Content $hashFile -Force

if ($hashesExist) {
    if ($changed.Count -eq 0 -and $new.Count -eq 0 -and $deleted.Count -eq 0) {
        Write-Host "`nNo changes detected. Project is up to date.`n" -ForegroundColor Green
        return
    }

    Write-Host "CHANGED FILES:" -ForegroundColor Yellow
    if ($changed.Count -eq 0) { Write-Host "  None" -ForegroundColor Green }
    else { $changed | ForEach-Object { Write-Host "  [+] $_" -ForegroundColor Yellow } }

    Write-Host "`nNEW FILES:" -ForegroundColor Green
    if ($new.Count -eq 0) { Write-Host "  None" }
    else { $new | ForEach-Object { Write-Host "  [+] $_" -ForegroundColor Green } }

    Write-Host "`nDELETED FILES:" -ForegroundColor Red
    if ($deleted.Count -eq 0) { Write-Host "  None" }
    else { $deleted | ForEach-Object { Write-Host "  [-] $_" -ForegroundColor Red } }

    $all = $changed + $new + $deleted
    Write-Host "`nTotal: $($changed.Count) changed, $($new.Count) new, $($deleted.Count) deleted" -ForegroundColor Cyan

    # Diff mode - show actual line changes like git diff
    if ($Diff -and $changed.Count -gt 0) {
        Write-Host "`n========== DIFF ==========" -ForegroundColor Cyan
        $changed | ForEach-Object {
            $file = $_
            Write-Host "`n--- a/$file" -ForegroundColor Gray
            Write-Host "+++ b/$file" -ForegroundColor Gray
            $fullPath = Join-Path $projectDir $file
            if (Test-Path $fullPath) {
                $lines = Get-Content $fullPath
                $totalLines = $lines.Count
                $start = [Math]::Max(0, $totalLines - 20)
                Write-Host "@@ -$($totalLines - 20),20 +$totalLines,20 @@"
                for ($i = $start; $i -lt $totalLines; $i++) {
                    Write-Host " $($lines[$i])"
                }
            }
        }
    }
}

# Upload via FTP
if ($Upload -and $hashesExist -and ($changed.Count -gt 0 -or $new.Count -gt 0)) {
    if (!$FtpHost) {
        Write-Host "`nFTP credentials required." -ForegroundColor Red
        return
    }

    $toUpload = $changed + $new
    Write-Host "`nUploading $($toUpload.Count) files to $FtpHost..." -ForegroundColor Cyan

    $total = $toUpload.Count
    $i = 0
    foreach ($file in $toUpload) {
        $i++
        $localPath = "$projectDir\$file"
        $remoteFile = "$RemotePath/$($file -replace '\\', '/')"
        $remoteDir = [System.IO.Path]::GetDirectoryName($remoteFile)

        Write-Progress -Activity "Uploading..." -Status "$file" -PercentComplete (($i / $total) * 100)

        try {
            $dirs = $remoteDir.Split('/') | Where-Object { $_ }
            $currentPath = ""
            foreach ($d in $dirs) {
                $currentPath += "/$d"
                try {
                    $req = [System.Net.FtpWebRequest]::Create("ftp://$FtpHost$currentPath")
                    $req.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
                    $req.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
                    $req.GetResponse().Close()
                } catch {}
            }

            $req = [System.Net.FtpWebRequest]::Create("ftp://$FtpHost$remoteFile")
            $req.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
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
    Write-Progress -Activity "Uploading..." -Completed
    Write-Host "`nUpload complete!" -ForegroundColor Green
} elseif ($Upload -and !$hashesExist) {
    Write-Host "`nRun without -Upload first to create baseline." -ForegroundColor Yellow
}

$projectRoot = Split-Path $PSScriptRoot -Parent
$source = Get-Content (Join-Path $projectRoot 'deploy.ps1') -Raw

function Read-DeployValue([string]$name) {
    $match = [regex]::Match($source, '(?m)^\$' + [regex]::Escape($name) + '\s*=\s*"([^"]+)"')
    if (-not $match.Success) { throw "Missing deployment value: $name" }
    return $match.Groups[1].Value
}

$ftpRoot = Read-DeployValue 'ftpHost'
$ftpUser = Read-DeployValue 'user'
$ftpPass = Read-DeployValue 'pass'
$files = @(
    'config/app.php',
    'includes/csc_attendance.php',
    'modules/reports.php',
    'database/migrations/002_add_csc_attendance_settings.sql',
    'database/sample_data_august_2026.sql'
)

function Ensure-FtpDirectory([string]$uri) {
    try {
        $request = [System.Net.FtpWebRequest]::Create($uri)
        $request.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
        $request.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
        $request.UsePassive = $true
        $request.GetResponse().Close()
    } catch {
        # Existing directories normally return an FTP error and are safe to ignore.
    }
}

$failures = 0
foreach ($relative in $files) {
    $local = Join-Path $projectRoot ($relative -replace '/', '\')
    $parts = $relative.Split('/')
    $directory = $ftpRoot.TrimEnd('/')
    for ($i = 0; $i -lt $parts.Length - 1; $i++) {
        $directory += '/' + $parts[$i]
        Ensure-FtpDirectory $directory
    }

    $remote = $ftpRoot.TrimEnd('/') + '/' + $relative
    try {
        $bytes = [System.IO.File]::ReadAllBytes($local)
        $request = [System.Net.FtpWebRequest]::Create($remote)
        $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $request.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
        $request.UsePassive = $true
        $request.UseBinary = $true
        $request.ContentLength = $bytes.Length
        $stream = $request.GetRequestStream()
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
        $request.GetResponse().Close()
        Write-Host "[OK] $relative"
    } catch {
        $failures++
        Write-Host "[FAIL] $relative : $($_.Exception.Message)"
    }
}

if ($failures -gt 0) { exit 1 }
Write-Host 'Water-district CSC deployment complete.'

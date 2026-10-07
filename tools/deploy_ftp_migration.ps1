# Deploy domain-migration fixes to edexcel.college via FTP.
# Run in PowerShell on the same PC where FileZilla works:
#   powershell -ExecutionPolicy Bypass -File tools\deploy_ftp_migration.ps1

$ErrorActionPreference = 'Stop'
$HostName = '169.58.123.255'
$User = 'admin_rott'
$Pass = 'Jlipnmkl@1'
# FileZilla shows home /home/edexcel.college — site files are in public_html
$RemoteRoot = '/home/edexcel.college/public_html'
$LocalRoot = Join-Path $PSScriptRoot '..' | Resolve-Path

Write-Host "Local:  $LocalRoot"
Write-Host "Remote: ftp://${HostName}${RemoteRoot}"
Write-Host "User:   $User"

function Ftp-Upload([string]$LocalPath, [string]$RemotePath) {
    if (-not (Test-Path -LiteralPath $LocalPath)) {
        Write-Host "SKIP missing $LocalPath"
        return $false
    }
    $bytes = [System.IO.File]::ReadAllBytes($LocalPath)
    $uri = "ftp://${HostName}$RemotePath"
    $req = [System.Net.FtpWebRequest]::Create($uri)
    $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $req.Credentials = New-Object System.Net.NetworkCredential($User, $Pass)
    $req.UsePassive = $true
    $req.EnableSsl = $false
    $req.KeepAlive = $false
    $req.Timeout = 120000
    $req.ReadWriteTimeout = 120000
    $req.ContentLength = $bytes.Length
    $stream = $req.GetRequestStream()
    $stream.Write($bytes, 0, $bytes.Length)
    $stream.Close()
    $resp = $req.GetResponse()
    $resp.Close()
    Write-Host ("OK  {0} ({1} bytes)" -f $RemotePath, $bytes.Length)
    return $true
}

function Ftp-EnsureDir([string]$RemoteDir) {
    $parts = $RemoteDir.Trim('/').Split('/') | Where-Object { $_ }
    $cur = ''
    foreach ($p in $parts) {
        $cur += '/' + $p
        try {
            $req = [System.Net.FtpWebRequest]::Create("ftp://${HostName}$cur")
            $req.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
            $req.Credentials = New-Object System.Net.NetworkCredential($User, $Pass)
            $req.UsePassive = $true
            $req.EnableSsl = $false
            $req.Timeout = 15000
            $resp = $req.GetResponse()
            $resp.Close()
        } catch {
            # exists or not creatable — ignore
        }
    }
}

# Login check
Write-Host "`n=== Login check ==="
$pwdReq = [System.Net.FtpWebRequest]::Create("ftp://${HostName}/")
$pwdReq.Method = [System.Net.WebRequestMethods+Ftp]::PrintWorkingDirectory
$pwdReq.Credentials = New-Object System.Net.NetworkCredential($User, $Pass)
$pwdReq.UsePassive = $true
$pwdReq.EnableSsl = $false
$pwdReq.Timeout = 20000
try {
    $pwdResp = $pwdReq.GetResponse()
    Write-Host ("Logged in: " + $pwdResp.StatusDescription.Trim())
    $pwdResp.Close()
} catch {
    Write-Host "LOGIN FAILED: $($_.Exception.Message)"
    Write-Host 'Open FileZilla, reconnect with a freshly typed password, then update $Pass in this script.'
    exit 1
}

# Probe: prove this account maps to https://edexcel.college
$probeName = '__deploy_probe.txt'
$probeLocal = Join-Path $env:TEMP $probeName
$probeText = 'edexcel-deploy-' + (Get-Date -Format 'yyyyMMddHHmmss')
[System.IO.File]::WriteAllText($probeLocal, $probeText)
Ftp-Upload $probeLocal "$RemoteRoot/$probeName" | Out-Null
try {
    $probeUrl = "https://edexcel.college/$probeName"
    $body = (Invoke-WebRequest -Uri $probeUrl -UseBasicParsing -TimeoutSec 20).Content.Trim()
    if ($body -eq $probeText) {
        Write-Host "MAP OK: $probeUrl serves this FTP tree"
    } else {
        Write-Host "MAP WARN: $probeUrl returned unexpected body"
    }
} catch {
    Write-Host "MAP FAIL: could not fetch https://edexcel.college/$probeName - wrong remotePath?"
    Write-Host $_.Exception.Message
    exit 2
}

$files = @(
    'config/load_env.php',
    'config/bunny.php',
    'config/evolution.php',
    'config/classroom.php',
    'config/campus.php',
    'includes/college_contact.php',
    'includes/legal_page.php',
    'includes/lesson_pay_panel.php',
    'src/Services/OnePayService.php',
    'src/Services/WaitlistOfferService.php',
    'src/Services/SystemHealthService.php',
    'src/Services/LiveKitRoomService.php',
    'src/Services/OfficialExamService.php',
    'src/Services/MetaEmbeddedSignupService.php',
    'teachers/index.php',
    'teachers/teacher_profile.php',
    'admissions/apply.php',
    'admissions/enquire.php',
    'refund.php',
    'terms.php',
    'privacy.php',
    'data-deletion.php',
    'sitemap.xml',
    'robots.txt',
    'admin/settings.php',
    'admin/whatsapp_connect.php',
    'admin/whatsapp/index.php',
    'api/sms/webhook.php',
    'deploy/livekit/livekit.yaml',
    'docker-compose.yml',
    'docs/openapi.yaml',
    'assets/icons/icon-192.png',
    'assets/icons/icon-512.png',
    'assets/icons/favicon.ico',
    'SYSTEM.md',
    'DEPLOYMENT_NOTES.md'
)

Write-Host "`n=== Uploading $($files.Count) files ==="
$ok = 0
$fail = 0
foreach ($rel in $files) {
    $local = Join-Path $LocalRoot ($rel -replace '/', [IO.Path]::DirectorySeparatorChar)
    $remote = "$RemoteRoot/" + ($rel -replace '\\', '/')
    $dir = ($remote -replace '/[^/]+$', '')
    if ($dir -match 'assets/icons$') { Ftp-EnsureDir $dir }
    try {
        if (Ftp-Upload $local $remote) { $ok++ } else { $fail++ }
    } catch {
        Write-Host ("FAIL {0}: {1}" -f $rel, $_.Exception.Message)
        $fail++
    }
}

# Best-effort: set production APP_URL on remote .env without uploading local .env
Write-Host "`n=== Patch remote .env APP_URL/APP_ENV ==="
try {
    $envRemote = "$RemoteRoot/.env"
    $get = [System.Net.FtpWebRequest]::Create("ftp://${HostName}$envRemote")
    $get.Method = [System.Net.WebRequestMethods+Ftp]::DownloadFile
    $get.Credentials = New-Object System.Net.NetworkCredential($User, $Pass)
    $get.UsePassive = $true
    $get.EnableSsl = $false
    $get.Timeout = 30000
    $gresp = $get.GetResponse()
    $gr = New-Object IO.StreamReader($gresp.GetResponseStream())
    $envText = $gr.ReadToEnd()
    $gr.Close(); $gresp.Close()
    if ($envText -match '(?m)^APP_ENV=') {
        $envText = $envText -replace '(?m)^APP_ENV=.*$', 'APP_ENV=production'
    } else {
        $envText += "`nAPP_ENV=production`n"
    }
    if ($envText -match '(?m)^APP_URL=') {
        $envText = $envText -replace '(?m)^APP_URL=.*$', 'APP_URL=https://edexcel.college'
    } else {
        $envText += "`nAPP_URL=https://edexcel.college`n"
    }
    $envBytes = [Text.Encoding]::UTF8.GetBytes($envText)
    $put = [System.Net.FtpWebRequest]::Create("ftp://${HostName}$envRemote")
    $put.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $put.Credentials = New-Object System.Net.NetworkCredential($User, $Pass)
    $put.UsePassive = $true
    $put.EnableSsl = $false
    $put.ContentLength = $envBytes.Length
    $ps = $put.GetRequestStream(); $ps.Write($envBytes, 0, $envBytes.Length); $ps.Close()
    $pr = $put.GetResponse(); $pr.Close()
    Write-Host 'OK patched remote .env'
} catch {
    Write-Host ("WARN .env patch skipped: " + $_.Exception.Message)
}

# cleanup probe
try {
    $del = [System.Net.FtpWebRequest]::Create("ftp://${HostName}$RemoteRoot/$probeName")
    $del.Method = [System.Net.WebRequestMethods+Ftp]::DeleteFile
    $del.Credentials = New-Object System.Net.NetworkCredential($User, $Pass)
    $del.UsePassive = $true
    $del.EnableSsl = $false
    $dresp = $del.GetResponse(); $dresp.Close()
    Write-Host 'OK removed probe file'
} catch {}

Write-Host "`nDONE ok=$ok fail=$fail"
if ($fail -gt 0) { exit 3 }

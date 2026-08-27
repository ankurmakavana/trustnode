param(
    [Parameter(Mandatory=$true)]
    [string]$Target,

    [Parameter(Mandatory=$true)]
    [string]$InstallDir
)

$fullPath = $Target
if (-not [System.IO.Path]::IsPathRooted($Target)) {
    $fullPath = [System.IO.Path]::GetFullPath((Join-Path (pwd).Path $Target))
}

# 1. Validate existence
if (-not (Test-Path -Path $fullPath -PathType Container)) {
    Write-Host "Error: Local directory not found:" -ForegroundColor Red
    Write-Host $fullPath
    exit 1
}

# 2. Prevent scanning roots
$rootPath = [System.IO.Path]::GetPathRoot($fullPath)
if ($fullPath -eq $rootPath -or $fullPath -eq "C:\" -or $fullPath -eq "C:/") {
    Write-Host "Error: Refusing to scan filesystem root." -ForegroundColor Red
    Write-Host "Specify a project directory instead."
    exit 1
}

Write-Host "Scanning local directory:"
Write-Host $fullPath
Write-Host ""
Write-Host "Preparing source..."

# 3. Enforce limits and exclude directories
$excludeDirs = @('.git', 'node_modules', 'vendor', 'reports', 'storage\logs', 'bootstrap\cache', 'storage/logs', 'bootstrap/cache')
$maxFiles = 50000
$maxSizeMB = 200

$files = @()
$totalSize = 0

Get-ChildItem -Path $fullPath -Recurse -File -Force -ErrorAction SilentlyContinue | ForEach-Object {
    $file = $_
    $skip = $false
    foreach ($ex in $excludeDirs) {
        $escaped = [regex]::Escape($ex)
        if ($file.FullName -match "\\$escaped\\") {
            $skip = $true
            break
        }
    }
    if (-not $skip) {
        $files += $file
        $totalSize += $file.Length
        if ($files.Count -gt $maxFiles) {
            Write-Host "Error: Local scan exceeds safety limits." -ForegroundColor Red
            Write-Host ""
            Write-Host "Files: $($files.Count)+"
            Write-Host "Size: $([math]::Round($totalSize / 1MB, 2)) MB"
            Write-Host ""
            Write-Host "Use --exclude or scan a smaller project directory."
            exit 1
        }
    }
}

$sizeMB = [math]::Round($totalSize / 1MB, 2)
if ($sizeMB -gt $maxSizeMB) {
    Write-Host "Error: Local scan exceeds safety limits." -ForegroundColor Red
    Write-Host ""
    Write-Host "Files: $($files.Count)"
    Write-Host "Size: $sizeMB MB"
    Write-Host ""
    Write-Host "Use --exclude or scan a smaller project directory."
    exit 1
}

Write-Host "Files: $($files.Count)"
Write-Host "Size: $sizeMB MB"
Write-Host ""

# 4. Create temporary archive
$tempZip = [System.IO.Path]::GetTempFileName() + ".zip"

$tempDir = Join-Path ([System.IO.Path]::GetTempPath()) ([guid]::NewGuid().ToString())
New-Item -ItemType Directory -Path $tempDir | Out-Null

foreach ($file in $files) {
    $rel = $file.FullName.Substring($fullPath.Length).TrimStart('\')
    $dest = Join-Path $tempDir $rel
    $destDir = [System.IO.Path]::GetDirectoryName($dest)
    if (-not (Test-Path $destDir)) {
        New-Item -ItemType Directory -Path $destDir -Force | Out-Null
    }
    Copy-Item -Path $file.FullName -Destination $dest -Force
}

Compress-Archive -Path "$tempDir\*" -DestinationPath $tempZip -Force
Remove-Item -Path $tempDir -Recurse -Force

$maxArchiveSizeMB = 100
$zipFileInfo = Get-Item $tempZip
$zipSizeMB = [math]::Round($zipFileInfo.Length / 1MB, 2)

if ($zipSizeMB -gt $maxArchiveSizeMB) {
    Write-Host "Error: Local scan archive exceeds the maximum allowed size." -ForegroundColor Red
    Write-Host ""
    Write-Host "Archive size: $zipSizeMB MB"
    Write-Host "Maximum allowed: $maxArchiveSizeMB MB"
    Write-Host ""
    Write-Host "Try scanning a smaller directory or exclude unnecessary files."
    Remove-Item $tempZip -Force
    exit 1
}

# 5. Upload archive to TrustNode API
Write-Host "Uploading source..."

# Use Docker cp to copy the file into the PHP container
# We assume the container name is trustnode-php-1 or we can use docker compose cp
# Let's use docker compose cp.
$dockerComposeFile = Join-Path $InstallDir "compose.dev.yaml"
$tempZipLinux = "/tmp/scan_archive_$([guid]::NewGuid().ToString()).zip"

$cpProcess = Start-Process -FilePath "docker" -ArgumentList "compose", "-f", $dockerComposeFile, "cp", $tempZip, "php:$tempZipLinux" -Wait -NoNewWindow -PassThru
if ($cpProcess.ExitCode -ne 0) {
    Write-Host "Error uploading archive to Docker container." -ForegroundColor Red
    Remove-Item $tempZip -Force
    exit 1
}

# Now run the CLI command inside the container
$execArgs = @("compose", "-f", $dockerComposeFile, "exec", "-T", "-e", "TRUSTNODE_API_URL=http://nginx", "php", "php", "cli/bin/trustnode", "scan:local-upload", $tempZipLinux, $fullPath)
$execProcess = Start-Process -FilePath "docker" -ArgumentList $execArgs -Wait -NoNewWindow -PassThru

if ($execProcess.ExitCode -ne 0) {
    Write-Host "Error: Scan upload command failed." -ForegroundColor Red
}

# Clean up
Remove-Item $tempZip -Force
# We could clean up the Linux temp zip, but /tmp clears anyway.

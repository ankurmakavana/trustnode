$ErrorActionPreference = "Stop"
$VerbosePreference = "Continue"

Write-Host "==============================================="
Write-Host " TrustNode One-Command Installer (Windows) "
Write-Host "==============================================="

$LICENSE_KEY = Read-Host -Prompt "Enter your TrustNode License Key"

if ([string]::IsNullOrWhiteSpace($LICENSE_KEY)) {
    Write-Host "ERROR: License Key is required for installation." -ForegroundColor Red
    exit 1
}

# 1. Detect OS & Permissions
if (!([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "WARNING: You are not running as Administrator. Some actions (like modifying PATH) might fail." -ForegroundColor Yellow
}

# 2. Verify Docker
Write-Host "`n[*] Verifying Docker..."
try {
    $dockerVersion = docker --version 2>&1
    if ($LASTEXITCODE -ne 0) { throw }
    Write-Host "Found: $dockerVersion" -ForegroundColor Green
} catch {
    Write-Host "ERROR: Docker is not installed or not running. Please install Docker Desktop and start it before continuing." -ForegroundColor Red
    exit 1
}

try {
    $dockerComposeVersion = docker compose version 2>&1
    if ($LASTEXITCODE -ne 0) { throw }
} catch {
    Write-Host "ERROR: Docker Compose is required. Please ensure it's installed." -ForegroundColor Red
    exit 1
}

# 3 & 4. Installation Directory
$installDir = "$env:USERPROFILE\trustnode-app"
Write-Host "`n[*] Setting up installation directory at $installDir"
if (-not (Test-Path $installDir)) {
    New-Item -ItemType Directory -Path $installDir | Out-Null
}
Set-Location $installDir

Write-Host "`n[*] Authenticating installation..."
$machineId = (New-Guid).ToString()
$hostname = [System.Net.Dns]::GetHostName()

$activationData = @{
    license_key = $LICENSE_KEY
    installation_id = $machineId
    installation_fingerprint = $machineId
    installation_name = "Windows Installation"
    hostname = $hostname
}

$PLATFORM_URL = "https://trustnode.in"

try {
    $activationResponse = Invoke-RestMethod -Uri "$PLATFORM_URL/api/v1/licenses/activate" -Method Post -Body ($activationData | ConvertTo-Json) -ContentType "application/json"
} catch {
    Write-Host "ERROR: Failed to activate license. Check your license key or contact support." -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

$token = $activationResponse.data.installation_token

Write-Host "`n[*] Fetching latest release..."
try {
    $releaseResponse = Invoke-RestMethod -Uri "$PLATFORM_URL/api/v1/releases/latest" -Method Get -Headers @{ "Authorization" = "Bearer $token" }
} catch {
    Write-Host "ERROR: Failed to fetch latest release metadata." -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

$downloadUrl = $releaseResponse.download_url
$tempZip = "$env:TEMP\trustnode-release.zip"

Write-Host "`n[*] Downloading TrustNode release artifact..."
Invoke-WebRequest -Uri $downloadUrl -OutFile $tempZip

Write-Host "`n[*] Extracting artifact..."
Expand-Archive -Path $tempZip -DestinationPath $installDir -Force
Remove-Item -Path $tempZip -Force

# 5. Environment configuration
Write-Host "`n[*] Configuring environment..."
if (-not (Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
    } else {
        New-Item -ItemType File -Path ".env" | Out-Null
    }
    Write-Host "Created .env file."
} else {
    Write-Host "Using existing .env file."
}

# Save token to .env securely
$envContent = Get-Content ".env" -Raw
if ($envContent -notmatch "TRUSTNODE_INSTALLATION_TOKEN") {
    Add-Content -Path ".env" -Value "`nTRUSTNODE_INSTALLATION_TOKEN=$token"
} else {
    $envContent = $envContent -replace 'TRUSTNODE_INSTALLATION_TOKEN=.*', "TRUSTNODE_INSTALLATION_TOKEN=$token"
    Set-Content -Path ".env" -Value $envContent
}

# 7. Start Docker services
Write-Host "`n[*] Starting Docker services..."
docker compose -f compose.dev.yaml up -d --build

# 8. Wait for services
Write-Host "Waiting for database to be ready..."
Start-Sleep -Seconds 15

# 6 & 9 & 10. Generate secrets, initialize database, run migrations
Write-Host "`n[*] Initializing application..."
docker compose -f compose.dev.yaml exec -T php composer install --no-interaction --prefer-dist
$envContent = Get-Content ".env" -Raw
if ($envContent -notmatch "APP_KEY=base64:") {
    docker compose -f compose.dev.yaml exec -T php php artisan key:generate --force
}
docker compose -f compose.dev.yaml exec -T php php artisan migrate --force

Write-Host "`n[*] Configuring CLI authentication..."
$cliTokenContent = @"
<?php
require __DIR__ . '/vendor/autoload.php';
`$app = require_once __DIR__ . '/bootstrap/app.php';
`$kernel = `$app->make(Illuminate\Contracts\Console\Kernel::class);
`$kernel->bootstrap();
`$user = \App\Models\User::firstOrCreate(['email'=>'cli@trustnode.local'], ['name'=>'CLI System', 'password'=>bcrypt('secret'), 'role_id'=>1]);
`$user->tokens()->where('name', 'CLI Token')->delete();
echo `$user->createToken('CLI Token')->plainTextToken;
"@
Set-Content -Path "cli-token.php" -Value $cliTokenContent
$cliTokenRaw = docker compose -f compose.dev.yaml exec -T php php cli-token.php
if ([string]::IsNullOrWhiteSpace($cliTokenRaw)) {
    Write-Host "ERROR: Failed to generate CLI token. Is the PHP container running?" -ForegroundColor Red
    exit 1
}
$cliToken = $cliTokenRaw.Trim()
Remove-Item "cli-token.php" -Force -ErrorAction SilentlyContinue

# Save CLI token to .env securely
$envContent = Get-Content ".env" -Raw
if ($envContent -notmatch "TRUSTNODE_API_TOKEN") {
    Add-Content -Path ".env" -Value "`nTRUSTNODE_API_TOKEN=$cliToken"
} else {
    $envContent = $envContent -replace 'TRUSTNODE_API_TOKEN=.*', "TRUSTNODE_API_TOKEN=$cliToken"
    Set-Content -Path ".env" -Value $envContent
}

# 11. Build frontend assets
Write-Host "`n[*] Building frontend assets..."
docker compose -f compose.dev.yaml exec -T node npm install
docker compose -f compose.dev.yaml exec -T node npm run build

# 12. CLI Installation
Write-Host "`n[*] Installing TrustNode CLI..."
# We will create a wrapper script for the CLI in a folder added to PATH, or just the installDir
$cliWrapperPath = "$installDir\trustnode.cmd"
$cliWrapperContent = "@echo off`r`ndocker compose -f ""$installDir\compose.dev.yaml"" exec -e TRUSTNODE_API_URL=http://nginx php php cli/bin/trustnode %*"
Set-Content -Path $cliWrapperPath -Value $cliWrapperContent

$userPath = [Environment]::GetEnvironmentVariable("PATH", "User")
if ($userPath -notlike "*$installDir*") {
    [Environment]::SetEnvironmentVariable("PATH", "$userPath;$installDir", "User")
    $env:PATH = "$env:PATH;$installDir"
    Write-Host "Added $installDir to user PATH." -ForegroundColor Green
    Write-Host "Note: You may need to restart your terminal for PATH changes to take effect." -ForegroundColor Yellow
}

# 13. Verify installation
Write-Host "`n[*] Verifying installation..."
$statusCheck = docker compose -f compose.dev.yaml exec -T -e TRUSTNODE_API_URL=http://nginx php php cli/bin/trustnode status 2>&1
Write-Host $statusCheck

# 14. Final success
Write-Host "`n==============================================="
Write-Host " TrustNode installed successfully! " -ForegroundColor Green
Write-Host " Run 'trustnode --help' to get started."
Write-Host "==============================================="

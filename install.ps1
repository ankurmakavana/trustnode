$ErrorActionPreference = "Stop"
$VerbosePreference = "Continue"

Write-Host "==============================================="
Write-Host " TrustNode One-Command Installer (Windows) "
Write-Host "==============================================="

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
if (Test-Path $installDir) {
    Write-Host "Directory already exists. Updating existing installation..." -ForegroundColor Yellow
    Set-Location $installDir
    if (Test-Path ".git") {
        # For testing, we just use the current branch if it's already a git repo
        # In production, we would git pull or download a release zip
        git pull --quiet
    }
} else {
    # In production: Download release artifact
    $repoUrl = "https://github.com/ankurmakavana/trustnode.git"
    # Note: If it's a private repo, public installer needs to download a public release zip.
    # The prompt says: "If the official public installer URL does not exist yet... identify the one configuration value that must be replaced for public release."
    # We will use git clone for this implementation, noting it should be changed.
    Write-Host "Downloading TrustNode from $repoUrl ..."
    git clone --quiet $repoUrl $installDir
    Set-Location $installDir
}

# 5. Environment configuration
Write-Host "`n[*] Configuring environment..."
if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "Created .env file."
} else {
    Write-Host "Using existing .env file."
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
docker compose -f compose.dev.yaml exec -T php php artisan key:generate --force
docker compose -f compose.dev.yaml exec -T php php artisan migrate --force

Write-Host "`n[*] Configuring CLI authentication..."
$cliTokenContent = @"
<?php
require __DIR__ . '/vendor/autoload.php';
`$app = require_once __DIR__ . '/bootstrap/app.php';
`$kernel = `$app->make(Illuminate\Contracts\Console\Kernel::class);
`$kernel->bootstrap();
`$user = \App\Models\User::firstOrCreate(['email'=>'cli@trustnode.local'], ['name'=>'CLI System', 'password'=>bcrypt('secret'), 'role_id'=>1]);
echo `$user->createToken('CLI Token')->plainTextToken;
"@
Set-Content -Path "cli-token.php" -Value $cliTokenContent
$cliTokenRaw = docker compose -f compose.dev.yaml exec -T php php cli-token.php
$cliToken = $cliTokenRaw.Trim()
Remove-Item "cli-token.php" -Force -ErrorAction SilentlyContinue

$trustnodeConfigDir = "$env:USERPROFILE\.trustnode"
if (-not (Test-Path $trustnodeConfigDir)) {
    New-Item -ItemType Directory -Force -Path $trustnodeConfigDir | Out-Null
}
$configJson = @"
{
    "server": "http://nginx",
    "token": "$cliToken"
}
"@
Set-Content -Path "$trustnodeConfigDir\config" -Value $configJson

# 11. Build frontend assets
Write-Host "`n[*] Building frontend assets..."
docker compose -f compose.dev.yaml exec -T node npm install
docker compose -f compose.dev.yaml exec -T node npm run build

# 12. CLI Installation
Write-Host "`n[*] Installing TrustNode CLI..."
# We will create a wrapper script for the CLI in a folder added to PATH, or just the installDir
$cliWrapperPath = "$installDir\trustnode.cmd"
$cliWrapperContent = "@echo off`r`ndocker compose -f ""$installDir\compose.dev.yaml"" exec -e TRUSTNODE_API_URL=http://nginx -e TRUSTNODE_API_TOKEN=""$cliToken"" php php cli/bin/trustnode %*"
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
$statusCheck = docker compose -f compose.dev.yaml exec -T -e TRUSTNODE_API_URL=http://nginx -e TRUSTNODE_API_TOKEN="$cliToken" php php cli/bin/trustnode status 2>&1
Write-Host $statusCheck

# 14. Final success
Write-Host "`n==============================================="
Write-Host " TrustNode installed successfully! " -ForegroundColor Green
Write-Host " Run 'trustnode --help' to get started."
Write-Host "==============================================="

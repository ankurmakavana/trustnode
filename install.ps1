$ErrorActionPreference = "Stop"
$VerbosePreference = "Continue"

$logDir = "$env:USERPROFILE\trustnode\logs"
$logFile = "$logDir\install.log"
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

$global:currentStep = "Initialization"

function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $timestamp = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    $logMessage = "[$timestamp] [$Level] $Message"
    Add-Content -Path $logFile -Value $logMessage
    if ($Level -eq "ERROR") {
        Write-Host $Message -ForegroundColor Red
    } elseif ($Level -eq "WARN") {
        Write-Host $Message -ForegroundColor Yellow
    } elseif ($Level -eq "SUCCESS") {
        Write-Host $Message -ForegroundColor Green
    } else {
        Write-Host $Message
    }
}

function Handle-Error {
    param([System.Management.Automation.ErrorRecord]$ErrorRecord)
    
    Write-Host ""
    Write-Host "TRUSTNODE INSTALLATION FAILED" -ForegroundColor Red
    Write-Host "===============================================" -ForegroundColor Red
    Write-Host "Step: $global:currentStep" -ForegroundColor Red
    
    if ($ErrorRecord) {
        Write-Host "Error: $($ErrorRecord.Exception.Message)" -ForegroundColor Red
        $logMsg = "FAILED at step: $global:currentStep. Error: $($ErrorRecord.Exception.Message). Details: $($ErrorRecord.ScriptStackTrace)"
        Add-Content -Path $logFile -Value "[$((Get-Date).ToString("yyyy-MM-dd HH:mm:ss"))] [ERROR] $logMsg"
    } else {
        Write-Host "Error: Unknown error or exit called." -ForegroundColor Red
        $logMsg = "FAILED at step: $global:currentStep. Error: Unknown."
        Add-Content -Path $logFile -Value "[$((Get-Date).ToString("yyyy-MM-dd HH:mm:ss"))] [ERROR] $logMsg"
    }
    
    Write-Host "Log:`n$logFile" -ForegroundColor Yellow
    
    Write-Host "`nPress Enter to exit..."
    [System.Console]::ReadLine() | Out-Null
    
    [Environment]::Exit(1)
}

trap {
    Handle-Error $_
}

try {
    Write-Log "==============================================="
    Write-Log " TrustNode One-Command Installer (Windows) "
    Write-Log "==============================================="

    $global:currentStep = "Prompting for License Key"
    $LICENSE_KEY = Read-Host -Prompt "Enter your TrustNode License Key"

    if ([string]::IsNullOrWhiteSpace($LICENSE_KEY)) {
        throw "License Key is required for installation."
    }

    # 1. Detect OS & Permissions
    $global:currentStep = "Checking Permissions"
    if (!([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        Write-Log "WARNING: You are not running as Administrator. Some actions (like modifying PATH) might fail." "WARN"
    }

    # 2. Verify Docker
    $global:currentStep = "Verifying Docker"
    Write-Log "`n[*] Verifying Docker..."
    try {
        $dockerVersion = docker --version 2>&1
        if ($LASTEXITCODE -ne 0) { throw "Docker command failed with exit code $LASTEXITCODE" }
        Write-Log "Found: $dockerVersion" "SUCCESS"
    } catch {
        throw "Docker is not installed or not running. Please install Docker Desktop and start it before continuing."
    }

    try {
        $dockerComposeVersion = docker compose version 2>&1
        if ($LASTEXITCODE -ne 0) { throw "Docker compose command failed with exit code $LASTEXITCODE" }
    } catch {
        throw "Docker Compose is required. Please ensure it's installed."
    }

    # 3 & 4. Installation Directory
    $global:currentStep = "Setting up installation directory"
    $installDir = "$env:USERPROFILE\trustnode-app"
    Write-Log "`n[*] Setting up installation directory at $installDir"
    if (-not (Test-Path $installDir)) {
        New-Item -ItemType Directory -Path $installDir -Force | Out-Null
    }
    Set-Location $installDir

    $global:currentStep = "Authenticating installation"
    Write-Log "`n[*] Authenticating installation..."
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
        throw "Failed to activate license. Check your license key or contact support. Details: $_"
    }

    $token = $activationResponse.data.installation_token

    $global:currentStep = "Fetching latest release"
    Write-Log "`n[*] Fetching latest release..."
    try {
        $releaseResponse = Invoke-RestMethod -Uri "$PLATFORM_URL/api/v1/releases/latest" -Method Get -Headers @{ "Authorization" = "Bearer $token" }
    } catch {
        throw "Failed to fetch latest release metadata. Details: $_"
    }

    $downloadUrl = $releaseResponse.download_url
    $tempZip = "$env:TEMP\trustnode-release.zip"

    $global:currentStep = "Downloading release artifact"
    Write-Log "`n[*] Downloading TrustNode release artifact..."
    Invoke-WebRequest -Uri $downloadUrl -OutFile $tempZip -ErrorAction Stop

    $global:currentStep = "Extracting artifact"
    Write-Log "`n[*] Extracting artifact..."
    Expand-Archive -Path $tempZip -DestinationPath $installDir -Force -ErrorAction Stop
    Remove-Item -Path $tempZip -Force -ErrorAction SilentlyContinue

    # 5. Environment configuration
    $global:currentStep = "Configuring environment"
    Write-Log "`n[*] Configuring environment..."
    if (-not (Test-Path ".env")) {
        if (Test-Path ".env.example") {
            Copy-Item ".env.example" ".env" -Force
        } else {
            New-Item -ItemType File -Path ".env" -Force | Out-Null
        }
        Write-Log "Created .env file."
    } else {
        Write-Log "Using existing .env file."
    }

    # Save token to .env securely
    $envContent = Get-Content ".env" -Raw
    if ($envContent -notmatch "TRUSTNODE_INSTALLATION_TOKEN") {
        Add-Content -Path ".env" -Value "`nTRUSTNODE_INSTALLATION_TOKEN=$token"
    } else {
        $envContent = $envContent -replace 'TRUSTNODE_INSTALLATION_TOKEN=.*', "TRUSTNODE_INSTALLATION_TOKEN=$token"
        Set-Content -Path ".env" -Value $envContent -Encoding UTF8
    }

    # 7. Start Docker services
    $global:currentStep = "Starting Docker services"
    Write-Log "`n[*] Starting Docker services..."
    $dockerUp = docker compose -f compose.dev.yaml up -d --build 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Failed to start docker services: $dockerUp" }

    # 8. Wait for services
    $global:currentStep = "Waiting for services"
    Write-Log "Waiting for database to be ready..."
    Start-Sleep -Seconds 15

    # 6 & 9 & 10. Generate secrets, initialize database, run migrations
    $global:currentStep = "Initializing application"
    Write-Log "`n[*] Initializing application..."
    $composerInstall = docker compose -f compose.dev.yaml exec -T php composer install --no-interaction --prefer-dist 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Composer install failed: $composerInstall" }

    $envContent = Get-Content ".env" -Raw
    if ($envContent -notmatch "APP_KEY=base64:") {
        $keyGen = docker compose -f compose.dev.yaml exec -T php php artisan key:generate --force 2>&1
        if ($LASTEXITCODE -ne 0) { throw "Key generation failed: $keyGen" }
    }
    
    $migrate = docker compose -f compose.dev.yaml exec -T php php artisan migrate --force 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Migration failed: $migrate" }

    $global:currentStep = "Configuring CLI authentication"
    Write-Log "`n[*] Configuring CLI authentication..."
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
    Set-Content -Path "cli-token.php" -Value $cliTokenContent -Encoding UTF8
    $cliTokenRaw = docker compose -f compose.dev.yaml exec -T php php cli-token.php
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($cliTokenRaw)) {
        throw "Failed to generate CLI token. Is the PHP container running? Output: $cliTokenRaw"
    }
    $cliToken = $cliTokenRaw.Trim()
    Remove-Item "cli-token.php" -Force -ErrorAction SilentlyContinue

    # Save CLI token to .env securely
    $envContent = Get-Content ".env" -Raw
    if ($envContent -notmatch "TRUSTNODE_API_TOKEN") {
        Add-Content -Path ".env" -Value "`nTRUSTNODE_API_TOKEN=$cliToken"
    } else {
        $envContent = $envContent -replace 'TRUSTNODE_API_TOKEN=.*', "TRUSTNODE_API_TOKEN=$cliToken"
        Set-Content -Path ".env" -Value $envContent -Encoding UTF8
    }

    # 11. Build frontend assets
    $global:currentStep = "Building frontend assets"
    Write-Log "`n[*] Building frontend assets..."
    $npmInstall = docker compose -f compose.dev.yaml exec -T node npm install 2>&1
    if ($LASTEXITCODE -ne 0) { throw "npm install failed: $npmInstall" }
    
    $npmBuild = docker compose -f compose.dev.yaml exec -T node npm run build 2>&1
    if ($LASTEXITCODE -ne 0) { throw "npm build failed: $npmBuild" }

    # 12. CLI Installation
    $global:currentStep = "Installing TrustNode CLI"
    Write-Log "`n[*] Installing TrustNode CLI..."
    $cliWrapperPath = "$installDir\trustnode.cmd"
    $cliWrapperContent = "@echo off`r`ndocker compose -f ""$installDir\compose.dev.yaml"" exec -e TRUSTNODE_API_URL=http://nginx php php cli/bin/trustnode %*"
    Set-Content -Path $cliWrapperPath -Value $cliWrapperContent -Encoding UTF8

    $userPath = [Environment]::GetEnvironmentVariable("PATH", "User")
    if ($userPath -notlike "*$installDir*") {
        [Environment]::SetEnvironmentVariable("PATH", "$userPath;$installDir", "User")
        $env:PATH = "$env:PATH;$installDir"
        Write-Log "Added $installDir to user PATH." "SUCCESS"
        Write-Log "Note: You may need to restart your terminal for PATH changes to take effect." "WARN"
    }

    # 13. Verify installation
    $global:currentStep = "Verifying installation"
    Write-Log "`n[*] Verifying installation..."
    $statusCheck = docker compose -f compose.dev.yaml exec -T -e TRUSTNODE_API_URL=http://nginx php php cli/bin/trustnode status 2>&1
    Write-Log $statusCheck

    # 14. Final success
    $global:currentStep = "Finished"
    Write-Log "`n==============================================="
    Write-Log " TrustNode installed successfully! " "SUCCESS"
    Write-Log " Run 'trustnode --help' to get started."
    Write-Log "==============================================="

} catch {
    Handle-Error $_
}

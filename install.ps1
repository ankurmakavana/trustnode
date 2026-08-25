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
`$inst = \App\Services\License\InstallationIdentityService::class;
`$idService = `$app->make(`$inst);
`$instId = `$idService->getInstallationId();
`$installation = \App\Models\LicenseInstallation::first();
if (`$installation) {
    `$installation->update(['installation_token' => '$token', 'license_status' => 'active', 'validated_at' => now(), 'grace_expires_at' => now()->addHours(72)]);
}
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
    $cliWrapperContent = @"
@echo off
setlocal EnableDelayedExpansion

docker info >nul 2>&1
if !ERRORLEVEL! NEQ 0 (
    echo [TrustNode CLI] Error: Docker is not running or not accessible.
    echo Please start Docker Desktop/Engine before using the CLI.
    exit /b 1
)

set "CMD=%~1"
set "CMD_ARG2=%~2"
set "INSTALL_DIR=%~dp0"
set "INSTALL_DIR=!INSTALL_DIR:~0,-1!"

if /I "!CMD!"=="start" (
    echo Starting TrustNode...
    echo.
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" up -d
    if !ERRORLEVEL! NEQ 0 (
        echo.
        echo [ERROR] Unable to start TrustNode services.
        exit /b 1
    )
    echo.
    echo [OK] Services started
    echo [OK] TrustNode is running
    exit /b 0
)
if /I "!CMD!"=="stop" (
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" stop
    exit /b !ERRORLEVEL!
)
if /I "!CMD!"=="restart" (
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" restart
    exit /b !ERRORLEVEL!
)
if /I "!CMD!"=="status" (
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" ps
    exit /b !ERRORLEVEL!
)
if /I "!CMD!"=="logs" (
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" logs -f !CMD_ARG2!
    exit /b !ERRORLEVEL!
)
if /I "!CMD!"=="update" (
    echo TrustNode Updater
    echo =================
    echo.
    
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" ps | findstr "php" >nul
    if !ERRORLEVEL! NEQ 0 (
        echo Starting required TrustNode services...
        docker compose -f "!INSTALL_DIR!\compose.dev.yaml" up -d
        if !ERRORLEVEL! NEQ 0 (
            echo.
            echo [ERROR] Unable to start TrustNode services.
            exit /b 1
        )
        echo Waiting for services to initialize...
        ping 127.0.0.1 -n 6 >nul
    )
    
    echo Checking for updates...
    set "TOKEN="
    if exist "!INSTALL_DIR!\.env" (
        for /f "usebackq tokens=1,* delims==" %%A in ("!INSTALL_DIR!\.env") do (
            if "%%A"=="TRUSTNODE_API_TOKEN" set "TOKEN=%%B"
        )
    )
    if "!TOKEN!"=="" (
        echo [ERROR] Unable to authenticate with the local TrustNode installation.
        echo.
        echo Run:
        echo.
        echo     trustnode doctor
        exit /b 1
    )
    set "PS_CMD=$ErrorActionPreference='Stop'; $metaRaw = docker compose -f '!INSTALL_DIR!\compose.dev.yaml' exec -T php curl -s -X GET 'http://nginx/api/system/update/metadata' -H 'Authorization: Bearer !TOKEN!' -H 'Accept: application/json'; if (-not $metaRaw) { Write-Host '[ERROR] Unable to check for updates.'; Write-Host 'Please try again later.'; exit 1 }; $meta = $metaRaw | ConvertFrom-Json; if ($meta.available -ne $true) { if ($meta.up_to_date) { Write-Host '[OK] TrustNode is already up to date.'; Write-Host ('Version: ' + $meta.latest_version); exit 0 }; if ($meta.error_code -eq 'update_service_unavailable') { Write-Host '[ERROR] Unable to check for updates.'; Write-Host 'Please try again later.'; exit 1 }; if ($meta.error_code -eq 'release_not_authorized') { Write-Host '[ERROR] This release requires an authorized TrustNode license.'; exit 1 }; Write-Host '[ERROR] Unable to verify update information.'; Write-Host 'Please try again later.'; exit 1 }; Write-Host ('Current version: ' + $meta.current_version); Write-Host ('Latest version:  ' + $meta.version); Write-Host ''; Write-Host 'Downloading update...'; Invoke-WebRequest -Uri $meta.download_url -OutFile '!INSTALL_DIR!\update.zip'; Write-Host 'Verifying package...'; if ($meta.sha256) { $hash = (Get-FileHash '!INSTALL_DIR!\update.zip' -Algorithm SHA256).Hash; if ($hash.ToLower() -ne $meta.sha256.ToLower()) { throw 'SHA-256 verification failed.' } }; Write-Host 'Installing update...'; Expand-Archive -Path '!INSTALL_DIR!\update.zip' -DestinationPath '!INSTALL_DIR!' -Force; Remove-Item '!INSTALL_DIR!\update.zip' -Force; Set-Content -Path '!INSTALL_DIR!\.update_version' -Value $meta.version"
    powershell -NoProfile -Command "!PS_CMD!"
    if !ERRORLEVEL! NEQ 0 (
        exit /b 1
    )
    
    echo Running database migrations...
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" up -d --build >nul 2>&1
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" exec -T php php artisan migrate --force >nul 2>&1
    
    echo Restarting services...
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" restart >nul 2>&1
    
    echo.
    echo [OK] TrustNode updated successfully.
    if exist "!INSTALL_DIR!\.update_version" (
        set /p NEW_VER=<"!INSTALL_DIR!\.update_version"
        echo Version: !NEW_VER!
        del "!INSTALL_DIR!\.update_version"
    )
    exit /b 0
)
if /I "!CMD!"=="doctor" (
    echo Running TrustNode Diagnostics...
    echo.
    echo Checking Docker...
    docker info >nul 2>&1
    if !ERRORLEVEL! EQU 0 ( echo [OK] Docker is running ) else ( echo [FAIL] Docker is not running )
    
    echo Checking Environment...
    if exist "!INSTALL_DIR!\.env" ( echo [OK] .env configuration found ) else ( echo [FAIL] .env is missing )
    
    echo Checking Services...
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" ps | findstr "php" >nul
    if !ERRORLEVEL! EQU 0 ( echo [OK] Services are running ) else ( echo [FAIL] Services are stopped )
    echo.
    echo Run 'trustnode repair' to attempt safe automated fixes.
    exit /b 0
)
if /I "!CMD!"=="repair" (
    echo Attempting safe repair operations...
    if not exist "!INSTALL_DIR!\.env" (
        echo [TrustNode CLI] Error: .env file is missing. Please re-run the full installer.
        exit /b 1
    )
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" up -d
    echo Repair completed. Run 'trustnode doctor' to verify.
    exit /b 0
)

if /I "!CMD!"=="uninstall" (
    if /I "!CMD_ARG2!"=="--purge" (
        echo TrustNode Uninstaller
        echo =====================
        echo.
        echo WARNING: This will permanently delete ALL TrustNode data including:
        echo - Database data
        echo - Redis data
        echo - Reports and caches
        echo.
        set /p CONFIRM="Type DELETE to permanently remove all TrustNode data: "
        if NOT "!CONFIRM!"=="DELETE" (
            echo Uninstall cancelled.
            exit /b 0
        )
        docker compose -f "!INSTALL_DIR!\compose.dev.yaml" down -v
        exit /b !ERRORLEVEL!
    ) else (
        echo TrustNode Uninstaller
        echo =====================
        echo.
        echo This will stop and remove TrustNode containers.
        echo Your configuration and persistent data will be preserved.
        echo.
        echo Installation directory: !INSTALL_DIR!
        echo.
        set /p CONFIRM="Continue? [y/N]: "
        if /I NOT "!CONFIRM!"=="y" (
            echo Uninstall cancelled.
            exit /b 0
        )
        docker compose -f "!INSTALL_DIR!\compose.dev.yaml" down
        exit /b !ERRORLEVEL!
    )
)

if /I "!CMD!"=="" set CMD=help
if /I "!CMD!"=="--help" set CMD=help
if /I "!CMD!"=="help" (
    echo TrustNode CLI
    echo.
    echo Usage:
    echo   trustnode ^<command^> [options]
    echo.
    echo Lifecycle:
    echo   start
    echo   stop
    echo   restart
    echo   logs [service]
    echo   update
    echo   uninstall [--purge]
    echo   doctor
    echo   repair
    echo.
    echo Security:
    echo   scan ^<repository^>
    echo   scan status ^<id^>
    echo   repositories
    echo   findings
    echo   report ^<scan-id^>
    echo   report status ^<scan-id^>
    echo   report download ^<scan-id^>
    echo   status
    echo   activate ^<license-key^>
    echo   license
    exit /b 0
)

docker compose -f "!INSTALL_DIR!\compose.dev.yaml" ps | findstr "php" >nul
if !ERRORLEVEL! NEQ 0 (
    echo.
    echo [ERROR] TrustNode is not running.
    echo.
    echo Start it with:
    echo.
    echo   trustnode start
    exit /b 1
)

set "TTY_ARGS= "
if /I "!CMD!"=="scan" set "TTY_ARGS=-it"
if /I "!CMD!"=="repair" set "TTY_ARGS=-it"

docker compose -f "!INSTALL_DIR!\compose.dev.yaml" exec !TTY_ARGS! -e TRUSTNODE_API_URL=http://nginx -e TRUSTNODE_HOST_DIR="!INSTALL_DIR!" php php cli/bin/trustnode %*
set "EXIT_CODE=!ERRORLEVEL!"

exit /b !EXIT_CODE!
"@
    Set-Content -Path $cliWrapperPath -Value $cliWrapperContent -Encoding Ascii

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

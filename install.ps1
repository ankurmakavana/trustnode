param(
    [string]$Mode = "install"
)

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

    if ($Mode -eq "install") {
        $global:currentStep = "Prompting for License Key"
        $LICENSE_KEY = Read-Host -Prompt "Enter your TrustNode License Key"

        if ([string]::IsNullOrWhiteSpace($LICENSE_KEY)) {
            throw "License Key is required for installation."
        }
    } else {
        Write-Log "Running TrustNode in UPDATE mode" "INFO"
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
    if ($Mode -eq "update" -and -not (Test-Path $installDir)) {
        throw "Existing TrustNode installation not found at $installDir. Cannot update."
    }
    if (-not (Test-Path $installDir)) {
        New-Item -ItemType Directory -Path $installDir -Force | Out-Null
    }
    Set-Location $installDir

    if ($Mode -eq "install") {
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
    }

    $global:currentStep = "Fetching latest release"
    Write-Log "`n[*] Fetching latest release..."
    try {
        $releaseResponse = Invoke-RestMethod -Uri "https://trustnode.in/api/v1/releases/core/latest" -Method Get
    } catch {
        throw "Failed to fetch latest release metadata from core/latest API. Details: $_"
    }

    $artifactUrl = $releaseResponse.download_url
    if ([string]::IsNullOrWhiteSpace($artifactUrl)) {
        throw "Release API did not return a valid download_url."
    }

    $global:currentStep = "Downloading TrustNode Release"
    Write-Log "`n[*] Downloading TrustNode release artifact..."
    $artifactPath = "$installDir\trustnode.zip"

    # Temporary directory for extracting the update
    $tempExtractDir = "$installDir\temp_update_extract"
    
    Invoke-WebRequest -Uri $artifactUrl -OutFile $artifactPath

    Write-Log "[*] Extracting release artifact..."
    if (Test-Path $tempExtractDir) {
        Remove-Item -Path $tempExtractDir -Recurse -Force
    }
    New-Item -ItemType Directory -Path $tempExtractDir -Force | Out-Null
    Expand-Archive -Path $artifactPath -DestinationPath $tempExtractDir -Force
    Remove-Item $artifactPath

    Write-Log "[*] Applying update files..."
    
    # We copy the new files over the existing ones, preserving existing data.
    Copy-Item -Path "$tempExtractDir\*" -Destination $installDir -Recurse -Force
    
    Remove-Item -Path $tempExtractDir -Recurse -Force -ErrorAction SilentlyContinue

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

    if ($Mode -eq "install") {
        $envFile = "$installDir\.env"
        # Update environment variables
        Write-Log "[*] Configuring environment..."
        
        $env:TRUSTNODE_API_URL = "https://trustnode.in"
        $env:TRUSTNODE_INSTALLATION_TOKEN = $token

        $envContent = Get-Content $envFile -Raw
        $envContent = $envContent -replace '(?m)^TRUSTNODE_API_URL=.*$', "TRUSTNODE_API_URL=$env:TRUSTNODE_API_URL"
        $envContent = $envContent -replace '(?m)^TRUSTNODE_INSTALLATION_TOKEN=.*$', "TRUSTNODE_INSTALLATION_TOKEN=$env:TRUSTNODE_INSTALLATION_TOKEN"
        
        if (-not ($envContent -match "^TRUSTNODE_API_URL=")) { $envContent += "`nTRUSTNODE_API_URL=$env:TRUSTNODE_API_URL" }
        if (-not ($envContent -match "^TRUSTNODE_INSTALLATION_TOKEN=")) { $envContent += "`nTRUSTNODE_INSTALLATION_TOKEN=$env:TRUSTNODE_INSTALLATION_TOKEN" }

        Set-Content -Path $envFile -Value $envContent
    }

    $envContent = Get-Content ".env" -Raw
    if ($envContent -notmatch "APP_KEY=base64:") {
        $keyGen = docker compose -f compose.dev.yaml exec -T php php artisan key:generate --force 2>&1
        if ($LASTEXITCODE -ne 0) { throw "Key generation failed: $keyGen" }
    }
    
    $migrate = docker compose -f compose.dev.yaml exec -T php php artisan migrate --force 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Migration failed: $migrate" }

    # 11. Build frontend assets
    $global:currentStep = "Building frontend assets"
    if ($Mode -eq "install") {
        docker compose -f "$installDir\compose.dev.yaml" down -v --remove-orphans >$null 2>&1
    }
    Write-Log "`n[*] Building frontend assets..."
    $npmInstall = docker compose -f compose.dev.yaml exec -T node npm install 2>&1
    if ($LASTEXITCODE -ne 0) { throw "npm install failed: $npmInstall" }
    
    $npmBuild = docker compose -f compose.dev.yaml exec -T node npm run build 2>&1
    if ($LASTEXITCODE -ne 0) { throw "npm build failed: $npmBuild" }

    # 12. CLI Installation
    $global:currentStep = "Generating CLI Wrapper"
    $cliWrapperPath = "$installDir\trustnode.cmd"
    Write-Log "`n[*] Generating CLI wrapper at $cliWrapperPath..."

    $cliWrapperContent = @"
@echo off
setlocal EnableDelayedExpansion

set "INSTALL_DIR=$installDir"
set "CMD=%~1"
set "CMD_ARG2=%~2"

if /I "!CMD!"=="update" (
    echo TrustNode Updater
    echo =================
    powershell -NoProfile -Command "`$script = irm https://trustnode.in/install.ps1; & ([scriptblock]::Create(`$script)) -Mode update"
    exit /b !ERRORLEVEL!
)

if /I "!CMD!"=="start" (
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" up -d
    exit /b !ERRORLEVEL!
)
if /I "!CMD!"=="stop" (
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" stop
    exit /b !ERRORLEVEL!
)
if /I "!CMD!"=="down" (
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" down
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

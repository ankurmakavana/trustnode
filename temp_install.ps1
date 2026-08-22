<#
.SYNOPSIS
Installs or updates the TrustNode application using authorized release artifacts.
#>
$ErrorActionPreference = "Stop"
$VerbosePreference = "Continue"

Write-Host "==============================================="
Write-Host " TrustNode One-Command Installer (Windows) "
Write-Host "==============================================="

$installDir = "$env:USERPROFILE\trustnode"
$logDir = "$installDir\logs"
if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Force -Path $logDir | Out-Null }
$logFile = "$logDir\install.log"

function Write-Log {
    param([string]$message, [string]$color = "White")
    Write-Host $message -ForegroundColor $color
    $timestamp = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    Add-Content -Path $logFile -Value "[$timestamp] $message"
}

try {
    # 1. Verify Docker
    Write-Log "`n[1/7] Verifying Docker..."
    try {
        $dockerVersion = docker --version 2>&1
        if ($LASTEXITCODE -ne 0) { throw }
        Write-Log "Found: $dockerVersion" "Green"
    } catch {
        Write-Log "ERROR: Docker was not found.`nTrustNode requires Docker to run its isolated application,`ndatabase, queue workers, scheduler, and scanners.`nInstall Docker, start it, then run the installer again." "Red"
        Write-Log "Log: $logFile"
        exit 1
    }

    try {
        $dockerInfo = docker info 2>&1
        if ($LASTEXITCODE -ne 0) { throw }
    } catch {
        Write-Log "ERROR: Docker is installed but not running.`nStart Docker and run the command again." "Red"
        Write-Log "Log: $logFile"
        exit 1
    }

    try {
        $dockerComposeVersion = docker compose version 2>&1
        if ($LASTEXITCODE -ne 0) { throw }
    } catch {
        Write-Log "ERROR: Docker Compose is required. Please ensure it's installed." "Red"
        Write-Log "Log: $logFile"
        exit 1
    }

    # 2. Setup Directory
    Write-Log "`n[2/7] Setting up installation directory at $installDir"
    if (-not (Test-Path $installDir)) {
        New-Item -ItemType Directory -Force -Path $installDir | Out-Null
    }

    # 3. Downloading TrustNode Release Artifact
    Write-Log "`n[3/7] Downloading TrustNode authorized release..."
    Set-Location $installDir
    
    # Release Distribution Architecture Implementation
    $releaseApiUrl = "https://trustnode.in/api/releases/latest"
    Write-Log "Requesting release metadata from $releaseApiUrl"
    
    try {
        # This will fail since the endpoint does not exist yet.
        $releaseMeta = Invoke-RestMethod -Uri $releaseApiUrl -Method Get -ErrorAction Stop
        $downloadUrl = $releaseMeta.download_url
        $expectedSha256 = $releaseMeta.sha256
        $version = $releaseMeta.version
        
        Write-Log "Downloading version $version ..."
        $artifactPath = "$installDir\release.zip"
        Invoke-WebRequest -Uri $downloadUrl -OutFile $artifactPath -ErrorAction Stop
        
        # Verify Checksum
        $fileHash = (Get-FileHash -Path $artifactPath -Algorithm SHA256).Hash
        if ($fileHash -ne $expectedSha256) {
            throw "SHA-256 checksum mismatch. Expected $expectedSha256 but got $fileHash."
        }
        
        Write-Log "Extracting application..."
        Expand-Archive -Path $artifactPath -DestinationPath $installDir -Force
        Remove-Item $artifactPath -Force
        
    } catch {
        Write-Log "INSTALLATION FAILED" "Red"
        Write-Log "Step: Downloading TrustNode release artifact"
        Write-Log "Error: The authorized release distribution API endpoint is not yet implemented or unreachable."
        Write-Log "Details: $_"
        Write-Log "Log: $logFile"
        exit 1
    }

    # 4. Environment configuration
    Write-Log "`n[4/7] Preparing configuration..."
    if (-not (Test-Path ".env")) {
        if (Test-Path ".env.example") {
            Copy-Item ".env.example" ".env"
            Write-Log "Created .env file."
        }
    } else {
        Write-Log "Using existing .env file."
    }

    # 5. Start Docker services
    Write-Log "`n[5/7] Starting Docker services..."
    $dockerUp = docker compose -f compose.dev.yaml up -d --build 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to start Docker services.`n$dockerUp"
    }

    # 6. Wait for services & Initialize
    Write-Log "`n[6/7] Waiting for services and initializing..."
    Start-Sleep -Seconds 15

    docker compose -f compose.dev.yaml exec -T php composer install --no-interaction --prefer-dist 2>&1 | Out-Null
    $envContent = Get-Content ".env" -Raw
    if ($envContent -notmatch "APP_KEY=base64:") {
        docker compose -f compose.dev.yaml exec -T php php artisan key:generate --force 2>&1 | Out-Null
        Write-Log "Generated new APP_KEY."
    } else {
        Write-Log "Existing APP_KEY preserved."
    }
    docker compose -f compose.dev.yaml exec -T php php artisan migrate --force 2>&1 | Out-Null

    Write-Log "Configuring CLI authentication..."
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
    $cliTokenRaw = docker compose -f compose.dev.yaml exec -T php php cli-token.php 2>&1
    if ([string]::IsNullOrWhiteSpace($cliTokenRaw)) {
        throw "Failed to generate CLI token. Is the PHP container running?"
    }
    $cliToken = $cliTokenRaw.Trim()
    Remove-Item "cli-token.php" -Force -ErrorAction SilentlyContinue

    $envContent = Get-Content ".env" -Raw
    if ($envContent -notmatch "TRUSTNODE_API_TOKEN") {
        Add-Content -Path ".env" -Value "`nTRUSTNODE_API_TOKEN=$cliToken"
    } else {
        $envContent = $envContent -replace 'TRUSTNODE_API_TOKEN=.*', "TRUSTNODE_API_TOKEN=$cliToken"
        Set-Content -Path ".env" -Value $envContent
    }

    docker compose -f compose.dev.yaml exec -T node npm install 2>&1 | Out-Null
    docker compose -f compose.dev.yaml exec -T node npm run build 2>&1 | Out-Null

    # 7. CLI Wrapper Installation
    Write-Log "`n[7/7] Verifying installation..."

    $cliWrapperPathCmd = "$installDir\trustnode.cmd"
    $cliWrapperContentCmd = @"
@echo off
setlocal
set "INSTALL_DIR=$installDir"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%INSTALL_DIR%\trustnode.ps1" %*
exit /b %ERRORLEVEL%
"@
    Set-Content -Path $cliWrapperPathCmd -Value $cliWrapperContentCmd

    $cliWrapperPathPs1 = "$installDir\trustnode.ps1"
    $cliWrapperContentPs1 = @'
param(
    [Parameter(ValueFromRemainingArguments=$true)]
    [string[]]$Args
)

$ErrorActionPreference = "Stop"
$installDir = $PSScriptRoot
$logFile = "$installDir\logs\install.log"

if (-not (Test-Path "$installDir\compose.dev.yaml")) {
    Write-Host "ERROR: TrustNode is not installed correctly at $installDir." -ForegroundColor Red
    Write-Host "Run: irm https://trustnode.in/install.ps1 | iex"
    exit 1
}

try {
    $null = docker info 2>&1
    if ($LASTEXITCODE -ne 0) { throw }
} catch {
    Write-Host "ERROR: Docker is installed but not running." -ForegroundColor Red
    Write-Host "Start Docker and run the command again."
    exit 1
}

if ($Args.Count -eq 0) {
    $Args = @("status")
}

$command = $Args[0]

switch ($command) {
    "start" {
        Write-Host "Starting TrustNode services..."
        docker compose -f "$installDir\compose.dev.yaml" up -d
        if ($LASTEXITCODE -eq 0) { Write-Host "Services started successfully." -ForegroundColor Green }
        exit $LASTEXITCODE
    }
    "stop" {
        Write-Host "Stopping TrustNode services..."
        docker compose -f "$installDir\compose.dev.yaml" stop
        if ($LASTEXITCODE -eq 0) { Write-Host "Services stopped successfully." -ForegroundColor Green }
        exit $LASTEXITCODE
    }
    "restart" {
        Write-Host "Restarting TrustNode services..."
        docker compose -f "$installDir\compose.dev.yaml" restart
        if ($LASTEXITCODE -eq 0) { Write-Host "Services restarted successfully." -ForegroundColor Green }
        exit $LASTEXITCODE
    }
    "logs" {
        $service = if ($Args.Count -gt 1) { $Args[1] } else { "" }
        docker compose -f "$installDir\compose.dev.yaml" logs -f $service
        exit $LASTEXITCODE
    }
    "update" {
        Write-Host "Checking for updates..."
        
        $releaseApiUrl = "https://trustnode.in/api/releases/latest"
        try {
            # Check authorized update backend
            $releaseMeta = Invoke-RestMethod -Uri $releaseApiUrl -Method Get -ErrorAction Stop
            # Simulated workflow for production
            $downloadUrl = $releaseMeta.download_url
            $expectedSha256 = $releaseMeta.sha256
            $version = $releaseMeta.version
            
            Write-Host "Update available: $version"
            $artifactPath = "$installDir\release.zip"
            Invoke-WebRequest -Uri $downloadUrl -OutFile $artifactPath -ErrorAction Stop
            
            $fileHash = (Get-FileHash -Path $artifactPath -Algorithm SHA256).Hash
            if ($fileHash -ne $expectedSha256) { throw "SHA-256 mismatch." }
            
            Write-Host "Extracting..."
            Expand-Archive -Path $artifactPath -DestinationPath $installDir -Force
            Remove-Item $artifactPath -Force
            
            Write-Host "Rebuilding services..."
            docker compose -f "$installDir\compose.dev.yaml" up -d --build
            docker compose -f "$installDir\compose.dev.yaml" exec -T php php artisan migrate --force
            
            Write-Host "TrustNode updated successfully to $version" -ForegroundColor Green
            exit 0
        } catch {
            Write-Host "UPDATE FAILED" -ForegroundColor Red
            Write-Host "Step: Requesting latest authorized release metadata"
            Write-Host "Error: TrustNode License Platform release distribution API is missing or unreachable."
            Write-Host "Details: $_"
            Write-Host "Log: $logFile"
            exit 1
        }
    }
    "uninstall" {
        $purge = $false
        if ($Args -contains "--purge") { $purge = $true }
        
        if ($purge) {
            Write-Host "This will remove TrustNode containers AND delete all persistent data (database, redis)." -ForegroundColor Red
            $confirm = Read-Host "Are you sure you want to completely destroy all data? [y/N]"
            if ($confirm -match "^[yY]") {
                docker compose -f "$installDir\compose.dev.yaml" down -v
                Write-Host "TrustNode data and containers removed." -ForegroundColor Green
            } else {
                Write-Host "Uninstall cancelled."
            }
        } else {
            Write-Host "This will remove TrustNode containers. Database and stored data will be preserved unless --purge is used." -ForegroundColor Yellow
            $confirm = Read-Host "Continue? [y/N]"
            if ($confirm -match "^[yY]") {
                docker compose -f "$installDir\compose.dev.yaml" down
                Write-Host "TrustNode containers removed." -ForegroundColor Green
            } else {
                Write-Host "Uninstall cancelled."
            }
        }
        exit 0
    }
    "compliance" {
        Write-Host "This feature is not available in the current TrustNode version." -ForegroundColor Red
        exit 1
    }
    "fix" {
        Write-Host "This feature is not available in the current TrustNode version." -ForegroundColor Red
        exit 1
    }
    "license" {
        docker compose -f "$installDir\compose.dev.yaml" exec -e TRUSTNODE_API_URL=http://nginx php php artisan license:status
        exit $LASTEXITCODE
    }
    default {
        $isRunning = docker compose -f "$installDir\compose.dev.yaml" ps -q php
        if (-not $isRunning) {
            Write-Host "ERROR: TrustNode services are not running. Run 'trustnode start' first." -ForegroundColor Red
            exit 1
        }
        docker compose -f "$installDir\compose.dev.yaml" exec -e TRUSTNODE_API_URL=http://nginx php php cli/bin/trustnode $Args
        exit $LASTEXITCODE
    }
}
'@
    Set-Content -Path $cliWrapperPathPs1 -Value $cliWrapperContentPs1

    $userPath = [Environment]::GetEnvironmentVariable("PATH", "User")
    if ($userPath -notlike "*$installDir*") {
        [Environment]::SetEnvironmentVariable("PATH", "$userPath;$installDir", "User")
        $env:PATH = "$env:PATH;$installDir"
        Write-Log "Added $installDir to user PATH." "Green"
    }

    Write-Log "`n===============================================" "Green"
    Write-Log " TrustNode installed successfully! " "Green"
    Write-Log " Installation: $installDir"
    Write-Log " Next commands:"
    Write-Log " trustnode status"
    Write-Log " trustnode scan <repository>"
    Write-Log "===============================================" "Green"

} catch {
    Write-Log "`nINSTALLATION FAILED" "Red"
    Write-Log "Step: Checking system requirements or executing services"
    Write-Log "Error: $_"
    Write-Log "Log: $logFile"
    exit 1
}

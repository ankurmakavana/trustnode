@echo off
setlocal EnableDelayedExpansion

set "INSTALL_DIR=C:\Users\Groot\trustnode-app"
set "CMD=%~1"
set "CMD_ARG2=%~2"

if /I "!CMD!"=="update" (
    echo TrustNode Updater
    echo =================
    powershell -NoProfile -ExecutionPolicy Bypass -File "!INSTALL_DIR!\install.ps1" -Mode update
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
if /I "!CMD!"=="restart" (
    docker compose -f "!INSTALL_DIR!\compose.dev.yaml" restart
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
if /I "!CMD!"=="scan" (
    set "TTY_ARGS=-it"
    if /I NOT "!CMD_ARG2!"=="" (
        if /I NOT "!CMD_ARG2!"=="list" (
            if /I NOT "!CMD_ARG2!"=="status" (
                if exist "!CMD_ARG2!\" (
                    powershell -NoProfile -ExecutionPolicy Bypass -File "!INSTALL_DIR!\local_scan.ps1" -Target "!CMD_ARG2!" -InstallDir "!INSTALL_DIR!"
                    exit /b !ERRORLEVEL!
                )
            )
        )
    )
)
if /I "!CMD!"=="repair" set "TTY_ARGS=-it"

docker compose -f "!INSTALL_DIR!\compose.dev.yaml" exec !TTY_ARGS! -e TRUSTNODE_API_URL=http://nginx -e TRUSTNODE_HOST_DIR="!INSTALL_DIR!" php php cli/bin/trustnode %*
set "EXIT_CODE=!ERRORLEVEL!"

exit /b !EXIT_CODE!

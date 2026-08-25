#!/bin/bash
set -e

LOG_DIR="$HOME/.trustnode/logs"
LOG_FILE="$LOG_DIR/install.log"
mkdir -p "$LOG_DIR"

CURRENT_STEP="Initialization"

write_log() {
    local message="$1"
    local level="${2:-INFO}"
    local timestamp=$(date "+%Y-%m-%d %H:%M:%S")
    echo "[$timestamp] [$level] $message" >> "$LOG_FILE"
    
    if [ "$level" = "ERROR" ]; then
        echo -e "\e[31m$message\e[0m" >&2
    elif [ "$level" = "WARN" ]; then
        echo -e "\e[33m$message\e[0m"
    elif [ "$level" = "SUCCESS" ]; then
        echo -e "\e[32m$message\e[0m"
    else
        echo -e "$message"
    fi
}

handle_error() {
    local exit_code=$?
    echo ""
    echo -e "\e[31mTRUSTNODE INSTALLATION FAILED\e[0m"
    echo -e "\e[31m===============================================\e[0m"
    echo -e "\e[31mStep: $CURRENT_STEP\e[0m"
    
    local err_msg="FAILED at step: $CURRENT_STEP with exit code $exit_code."
    write_log "$err_msg" "ERROR"
    
    echo -e "\e[33mLog:\n$LOG_FILE\e[0m"
    echo -e "\nPress Enter to exit..."
    read -r
    exit $exit_code
}

trap 'handle_error' ERR

MODE="install"

while [[ "$#" -gt 0 ]]; do
    case $1 in
        --mode) MODE="$2"; shift ;;
        *) echo "Unknown parameter passed: $1"; exit 1 ;;
    esac
    shift
done

echo "==============================================="
echo " TrustNode One-Command Installer (Linux/macOS) "
echo "==============================================="

if [ "$MODE" = "install" ]; then
    read -p "Enter your TrustNode License Key: " LICENSE_KEY

    if [ -z "$LICENSE_KEY" ]; then
        echo "License Key is required for installation."
        exit 1
    fi
else
    echo "Running TrustNode in UPDATE mode"
fi

# 1. Detect OS
CURRENT_STEP="Checking OS"
OS="$(uname -s)"
write_log "\n[*] Detected OS: $OS"

# 2. Verify Docker
CURRENT_STEP="Verifying Docker"
write_log "[*] Verifying Docker..."
if ! command -v docker &> /dev/null; then
    write_log "Docker is not installed or not running. Please install Docker Desktop or Docker Engine and start it before continuing." "ERROR"
    false
fi

docker_version=$(docker --version)
write_log "Found: $docker_version" "SUCCESS"

if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    write_log "Docker Compose is required. Please ensure it's installed." "ERROR"
    false
fi

if ! command -v jq &> /dev/null; then
    write_log "jq is required to parse API responses. Please install jq and run again." "ERROR"
    false
fi

if ! command -v unzip &> /dev/null; then
    write_log "unzip is required to extract artifacts. Please install unzip and run again." "ERROR"
    false
fi

# 3 & 4. Installation Directory
CURRENT_STEP="Setting up installation directory"
INSTALL_DIR="$HOME/trustnode-app"
echo ""
echo "[*] Setting up installation directory at $INSTALL_DIR"

if [ "$MODE" = "update" ] && [ ! -d "$INSTALL_DIR" ]; then
    echo "Existing TrustNode installation not found at $INSTALL_DIR. Cannot update."
    exit 1
fi

if [ ! -d "$INSTALL_DIR" ]; then
    mkdir -p "$INSTALL_DIR"
fi
cd "$INSTALL_DIR"

CURRENT_STEP="Authenticating installation"
write_log "\n[*] Authenticating installation..."
PLATFORM_URL="https://trustnode.in"

if [ "$MODE" = "install" ]; then
    MACHINE_ID=$(uuidgen 2>/dev/null || echo $RANDOM-$RANDOM-$RANDOM)
    HOSTNAME=$(hostname)

    ACTIVATION_PAYLOAD=$(cat <<EOF
{
  "license_key": "$LICENSE_KEY",
  "installation_id": "$MACHINE_ID",
  "installation_fingerprint": "$MACHINE_ID",
  "installation_name": "Linux/macOS Installation",
  "hostname": "$HOSTNAME"
}
EOF
)

    ACTIVATION_RESPONSE=$(curl -s -X POST "$PLATFORM_URL/api/v1/licenses/activate" \
      -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      -d "$ACTIVATION_PAYLOAD")

    if ! echo "$ACTIVATION_RESPONSE" | jq -e '.success' > /dev/null; then
        write_log "Failed to activate license." "ERROR"
        echo "$ACTIVATION_RESPONSE" | jq -r '.error.message' >&2
        false
    fi

    INSTALLATION_TOKEN=$(echo "$ACTIVATION_RESPONSE" | jq -r '.data.installation_token')
fi

CURRENT_STEP="Fetching latest release"
write_log "\n[*] Fetching latest release..."
RELEASE_RESPONSE=$(curl -s -X GET "$PLATFORM_URL/api/v1/releases/core/latest" \
  -H "Accept: application/json")

if ! echo "$RELEASE_RESPONSE" | jq -e '.download_url' > /dev/null; then
    write_log "Failed to fetch latest release metadata from core API." "ERROR"
    echo "$RELEASE_RESPONSE" | jq -r '.error.message // empty' >&2
    false
fi

DOWNLOAD_URL=$(echo "$RELEASE_RESPONSE" | jq -r '.download_url')
TEMP_ZIP=$(mktemp)

CURRENT_STEP="Downloading release artifact"
write_log "\n[*] Downloading TrustNode release artifact..."
curl -sL -o "$TEMP_ZIP" "$DOWNLOAD_URL"

CURRENT_STEP="Extracting artifact"
write_log "\n[*] Extracting artifact..."
unzip -q -o "$TEMP_ZIP" -d "$INSTALL_DIR"
rm -f "$TEMP_ZIP"

# 5. Environment configuration
CURRENT_STEP="Configuring environment"
ENV_FILE="$INSTALL_DIR/.env"
write_log "\n[*] Configuring environment..."
if [ ! -f "$ENV_FILE" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
    else
        touch .env
    fi
    write_log "Created .env file."
else
    write_log "Using existing .env file."
fi

# 7. Start Docker services
CURRENT_STEP="Starting Docker services"
write_log "\n[*] Starting Docker services..."
docker compose -f compose.dev.yaml up -d --build

# 8. Wait for services
CURRENT_STEP="Waiting for services"
write_log "Waiting for database to be ready..."
sleep 15

# 6 & 9 & 10. Initialize database and migrations
CURRENT_STEP="Initializing application"
write_log "\n[*] Initializing application..."
docker compose -f compose.dev.yaml exec -T php composer install --no-interaction --prefer-dist
if ! grep -q "APP_KEY=base64:" .env; then
    docker compose -f compose.dev.yaml exec -T php php artisan key:generate --force
fi
if [ "$MODE" = "install" ]; then
    docker compose -f compose.dev.yaml exec -T php php artisan migrate --force
fi

CURRENT_STEP="Configuring CLI authentication"
if [ "$MODE" = "install" ]; then
    write_log "\n[*] Configuring CLI authentication..."
    cat > cli-token.php << 'EOF'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = \App\Models\User::firstOrCreate(['email'=>'cli@trustnode.local'], ['name'=>'CLI System', 'password'=>bcrypt('secret'), 'role_id'=>1]);
$user->tokens()->where('name', 'CLI Token')->delete();
$inst = \App\Services\License\InstallationIdentityService::class;
$idService = $app->make($inst);
$instId = $idService->getInstallationId();
$installation = \App\Models\LicenseInstallation::first();
if ($installation) {
    $installation->update(['installation_token' => getenv('INSTALLATION_TOKEN'), 'license_status' => 'active', 'validated_at' => now(), 'grace_expires_at' => now()->addHours(72)]);
}
echo $user->createToken('CLI Token')->plainTextToken;
EOF
    CLI_TOKEN=$(docker compose -f compose.dev.yaml exec -T -e INSTALLATION_TOKEN="$INSTALLATION_TOKEN" php php cli-token.php | tr -d '\r\n')
    rm cli-token.php

    if [ -z "$CLI_TOKEN" ]; then
        write_log "Failed to generate CLI token." "ERROR"
        false
    fi

    if grep -q "TRUSTNODE_API_TOKEN" .env; then
        sed -i.bak "s/^TRUSTNODE_API_TOKEN=.*/TRUSTNODE_API_TOKEN=$CLI_TOKEN/" .env
        rm -f .env.bak
    else
        echo -e "\nTRUSTNODE_API_TOKEN=$CLI_TOKEN" >> .env
    fi
fi

# 11. Build frontend assets
CURRENT_STEP="Building frontend assets"
write_log "\n[*] Building frontend assets..."
docker compose -f compose.dev.yaml exec -T node npm install
docker compose -f compose.dev.yaml exec -T node npm run build

# 12. CLI Installation
CURRENT_STEP="Installing TrustNode CLI"
write_log "\n[*] Installing TrustNode CLI..."
mkdir -p "$HOME/.local/bin"
CLI_WRAPPER="$HOME/.local/bin/trustnode"

cat > "$CLI_WRAPPER" << EOF
#!/bin/bash
if [ "\$1" = "update" ]; then
    cd "$INSTALL_DIR"
    bash install.sh --mode update
    exit \$?
fi
if ! docker info >/dev/null 2>&1; then
    echo "[TrustNode CLI] Error: Docker is not running."
    exit 1
fi
CMD="\$1"
CMD_ARG2="\$2"
if [ "\$CMD" = "start" ]; then
    docker compose -f "$INSTALL_DIR/compose.dev.yaml" up -d
elif [ "\$CMD" = "stop" ]; then
    docker compose -f "$INSTALL_DIR/compose.dev.yaml" stop
elif [ "\$CMD" = "restart" ]; then
    docker compose -f "$INSTALL_DIR/compose.dev.yaml" restart
elif [ "\$CMD" = "status" ]; then
    docker compose -f "$INSTALL_DIR/compose.dev.yaml" ps
elif [ "\$CMD" = "logs" ]; then
    docker compose -f "$INSTALL_DIR/compose.dev.yaml" logs -f "\$CMD_ARG2"
    echo "[OK] TrustNode updated successfully."
    echo "Version: \$LATEST_VERSION"
    exit 0
elif [ "\$CMD" = "doctor" ]; then
    echo "Running TrustNode Diagnostics..."
    echo ""
    echo "Checking Docker..."
    if docker info >/dev/null 2>&1; then echo "[OK] Docker is running"; else echo "[FAIL] Docker is not running"; fi
    echo "Checking Environment..."
    if [ -f "$INSTALL_DIR/.env" ]; then echo "[OK] .env configuration found"; else echo "[FAIL] .env is missing"; fi
    echo "Checking Services..."
    if docker compose -f "$INSTALL_DIR/compose.dev.yaml" ps | grep -q "php"; then echo "[OK] Services are running"; else echo "[FAIL] Services are stopped"; fi
    echo ""
    echo "Run 'trustnode repair' to attempt safe automated fixes."
    exit 0
elif [ "\$CMD" = "repair" ]; then
    echo "Attempting safe repair operations..."
    if [ ! -f "$INSTALL_DIR/.env" ]; then
        echo "[TrustNode CLI] Error: .env file is missing. Please re-run the full installer."
        exit 1
    fi
    docker compose -f "$INSTALL_DIR/compose.dev.yaml" up -d
    echo "Repair completed. Run 'trustnode doctor' to verify."
    exit 0
elif [ "\$CMD" = "uninstall" ]; then
    if [ "\$CMD_ARG2" = "--purge" ]; then
        echo "TrustNode Uninstaller"
        echo "====================="
        echo ""
        echo "WARNING: This will permanently delete ALL TrustNode data including:"
        echo "- Database data"
        echo "- Redis data"
        echo "- Reports and caches"
        echo ""
        read -p "Type DELETE to permanently remove all TrustNode data: " CONFIRM
        if [ "\$CONFIRM" != "DELETE" ]; then
            echo "Uninstall cancelled."
            exit 0
        fi
        docker compose -f "$INSTALL_DIR/compose.dev.yaml" down -v
        exit \$?
    else
        echo "TrustNode Uninstaller"
        echo "====================="
        echo ""
        echo "This will stop and remove TrustNode containers."
        echo "Your configuration and persistent data will be preserved."
        echo ""
        echo "Installation directory: $INSTALL_DIR"
        echo ""
        read -p "Continue? [y/N]: " CONFIRM
        if [ "\${CONFIRM,,}" != "y" ]; then
            echo "Uninstall cancelled."
            exit 0
        fi
        docker compose -f "$INSTALL_DIR/compose.dev.yaml" down
        exit \$?
    fi
fi

if [ -z "\$CMD" ] || [ "\$CMD" = "--help" ] || [ "\$CMD" = "help" ]; then
    echo "TrustNode CLI"
    echo ""
    echo "Usage:"
    echo "  trustnode <command> [options]"
    echo ""
    echo "Lifecycle:"
    echo "  start"
    echo "  stop"
    echo "  restart"
    echo "  logs [service]"
    echo "  update"
    echo "  uninstall [--purge]"
    echo "  doctor"
    echo "  repair"
    echo ""
    echo "Security:"
    echo "  scan <repository>"
    echo "  scan status <id>"
    echo "  repositories"
    echo "  findings"
    echo "  report <scan-id>"
    echo "  report status <scan-id>"
    echo "  report download <scan-id>"
    echo "  status"
    echo "  activate <license-key>"
    echo "  license"
    exit 0
fi

TTY_ARGS=""
if [ -t 0 ] && [ -t 1 ]; then
    if [[ "\$CMD" == "scan" || "\$CMD" == "repair" ]]; then
        TTY_ARGS="-it"
    fi
fi

docker compose -f "$INSTALL_DIR/compose.dev.yaml" ps | grep -q "php"
if [ \$? -ne 0 ]; then
    echo ""
    echo "[ERROR] TrustNode is not running."
    echo ""
    echo "Start it with:"
    echo ""
    echo "  trustnode start"
    exit 1
fi

docker compose -f "$INSTALL_DIR/compose.dev.yaml" exec \$TTY_ARGS -e TRUSTNODE_API_URL=http://nginx -e TRUSTNODE_HOST_DIR="$INSTALL_DIR" php php cli/bin/trustnode "\$@"
EXIT_CODE=\$?

exit \$EXIT_CODE
EOF
chmod +x "$CLI_WRAPPER"

# Add to PATH if not present
if [[ ":$PATH:" != *":$HOME/.local/bin:"* ]]; then
    write_log "Adding $HOME/.local/bin to PATH in ~/.bashrc and ~/.zshrc" "SUCCESS"
    echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.bashrc 2>/dev/null || true
    echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.zshrc 2>/dev/null || true
    export PATH="$HOME/.local/bin:$PATH"
    write_log "Note: You may need to restart your terminal or run 'source ~/.bashrc' for PATH changes to take effect." "WARN"
fi

# 13. Verify installation
CURRENT_STEP="Verifying installation"
write_log "\n[*] Verifying installation..."
"$CLI_WRAPPER" status

# 14. Final success
CURRENT_STEP="Finished"
write_log "\n==============================================="
write_log " TrustNode installed successfully! " "SUCCESS"
write_log " Run 'trustnode --help' to get started."
write_log "==============================================="

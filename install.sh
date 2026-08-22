#!/bin/bash
set -e

echo "==============================================="
echo " TrustNode One-Command Installer (macOS/Linux) "
echo "==============================================="

read -p "Enter your TrustNode License Key: " LICENSE_KEY
if [ -z "$LICENSE_KEY" ]; then
    echo "ERROR: License Key is required for installation." >&2
    exit 1
fi

# 1. Detect OS
OS="$(uname -s)"
echo -e "\n[*] Detected OS: $OS"

# 2. Verify Docker
echo "[*] Verifying Docker..."
if ! command -v docker &> /dev/null; then
    echo "ERROR: Docker is not installed or not running. Please install Docker Desktop or Docker Engine and start it before continuing." >&2
    exit 1
fi

docker_version=$(docker --version)
echo "Found: $docker_version"

if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo "ERROR: Docker Compose is required. Please ensure it's installed." >&2
    exit 1
fi

if ! command -v jq &> /dev/null; then
    echo "ERROR: jq is required to parse API responses. Please install jq and run again." >&2
    exit 1
fi

if ! command -v unzip &> /dev/null; then
    echo "ERROR: unzip is required to extract artifacts. Please install unzip and run again." >&2
    exit 1
fi

# 3 & 4. Installation Directory
INSTALL_DIR="$HOME/.trustnode"
echo -e "\n[*] Setting up installation directory at $INSTALL_DIR"

if [ ! -d "$INSTALL_DIR" ]; then
    mkdir -p "$INSTALL_DIR"
fi
cd "$INSTALL_DIR"

echo -e "\n[*] Authenticating installation..."
MACHINE_ID=$(uuidgen 2>/dev/null || echo $RANDOM-$RANDOM-$RANDOM)
HOSTNAME=$(hostname)
PLATFORM_URL="https://trustnode.in"

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
    echo "ERROR: Failed to activate license." >&2
    echo "$ACTIVATION_RESPONSE" | jq -r '.error.message' >&2
    exit 1
fi

INSTALLATION_TOKEN=$(echo "$ACTIVATION_RESPONSE" | jq -r '.data.installation_token')

echo -e "\n[*] Fetching latest release..."
RELEASE_RESPONSE=$(curl -s -X GET "$PLATFORM_URL/api/v1/releases/latest" \
  -H "Authorization: Bearer $INSTALLATION_TOKEN" \
  -H "Accept: application/json")

if ! echo "$RELEASE_RESPONSE" | jq -e '.download_url' > /dev/null; then
    echo "ERROR: Failed to fetch latest release metadata." >&2
    echo "$RELEASE_RESPONSE" | jq -r '.error.message // empty' >&2
    exit 1
fi

DOWNLOAD_URL=$(echo "$RELEASE_RESPONSE" | jq -r '.download_url')
TEMP_ZIP=$(mktemp)

echo -e "\n[*] Downloading TrustNode release artifact..."
curl -sL -o "$TEMP_ZIP" "$DOWNLOAD_URL"

echo -e "\n[*] Extracting artifact..."
unzip -q -o "$TEMP_ZIP" -d "$INSTALL_DIR"
rm -f "$TEMP_ZIP"

# 5. Environment configuration
echo -e "\n[*] Configuring environment..."
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
    else
        touch .env
    fi
    echo "Created .env file."
else
    echo "Using existing .env file."
fi

# Save token to .env securely
if grep -q "TRUSTNODE_INSTALLATION_TOKEN" .env; then
    sed -i.bak "s/^TRUSTNODE_INSTALLATION_TOKEN=.*/TRUSTNODE_INSTALLATION_TOKEN=$INSTALLATION_TOKEN/" .env
    rm -f .env.bak
else
    echo -e "\nTRUSTNODE_INSTALLATION_TOKEN=$INSTALLATION_TOKEN" >> .env
fi

# 7. Start Docker services
echo -e "\n[*] Starting Docker services..."
docker compose -f compose.dev.yaml up -d --build

# 8. Wait for services
echo "Waiting for database to be ready..."
sleep 15

# 6 & 9 & 10. Initialize database and migrations
echo -e "\n[*] Initializing application..."
docker compose -f compose.dev.yaml exec -T php composer install --no-interaction --prefer-dist
if ! grep -q "APP_KEY=base64:" .env; then
    docker compose -f compose.dev.yaml exec -T php php artisan key:generate --force
fi
docker compose -f compose.dev.yaml exec -T php php artisan migrate --force

echo -e "\n[*] Configuring CLI authentication..."
cat > cli-token.php << 'EOF'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = \App\Models\User::firstOrCreate(['email'=>'cli@trustnode.local'], ['name'=>'CLI System', 'password'=>bcrypt('secret'), 'role_id'=>1]);
$user->tokens()->where('name', 'CLI Token')->delete();
echo $user->createToken('CLI Token')->plainTextToken;
EOF
CLI_TOKEN=$(docker compose -f compose.dev.yaml exec -T php php cli-token.php | tr -d '\r\n')
rm cli-token.php

if [ -z "$CLI_TOKEN" ]; then
    echo "ERROR: Failed to generate CLI token. Is the PHP container running?" >&2
    exit 1
fi

# Save CLI token to .env securely
if grep -q "TRUSTNODE_API_TOKEN" .env; then
    sed -i.bak "s/^TRUSTNODE_API_TOKEN=.*/TRUSTNODE_API_TOKEN=$CLI_TOKEN/" .env
    rm -f .env.bak
else
    echo -e "\nTRUSTNODE_API_TOKEN=$CLI_TOKEN" >> .env
fi

# 11. Build frontend assets
echo -e "\n[*] Building frontend assets..."
docker compose -f compose.dev.yaml exec -T node npm install
docker compose -f compose.dev.yaml exec -T node npm run build

# 12. CLI Installation
echo -e "\n[*] Installing TrustNode CLI..."
mkdir -p "$HOME/.local/bin"
CLI_WRAPPER="$HOME/.local/bin/trustnode"

cat > "$CLI_WRAPPER" << EOF
#!/bin/bash
exec docker compose -f "$INSTALL_DIR/compose.dev.yaml" exec -T -e TRUSTNODE_API_URL=http://nginx php php cli/bin/trustnode "\$@"
EOF
chmod +x "$CLI_WRAPPER"

# Add to PATH if not present
if [[ ":$PATH:" != *":$HOME/.local/bin:"* ]]; then
    echo "Adding $HOME/.local/bin to PATH in ~/.bashrc and ~/.zshrc"
    echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.bashrc 2>/dev/null || true
    echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.zshrc 2>/dev/null || true
    export PATH="$HOME/.local/bin:$PATH"
    echo "Note: You may need to restart your terminal or run 'source ~/.bashrc' for PATH changes to take effect."
fi

# 13. Verify installation
echo -e "\n[*] Verifying installation..."
"$CLI_WRAPPER" status

# 14. Final success
echo -e "\n==============================================="
echo " TrustNode installed successfully! "
echo " Run 'trustnode --help' to get started."
echo "==============================================="

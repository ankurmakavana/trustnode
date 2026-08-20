#!/bin/bash
set -e

echo "==============================================="
echo " TrustNode One-Command Installer (macOS/Linux) "
echo "==============================================="

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

# 3 & 4. Installation Directory
INSTALL_DIR="$HOME/.trustnode"
echo -e "\n[*] Setting up installation directory at $INSTALL_DIR"

if [ -d "$INSTALL_DIR" ]; then
    echo "Directory already exists. Updating existing installation..."
    cd "$INSTALL_DIR"
    if [ -d ".git" ]; then
        git pull --quiet
    fi
else
    # In production: Download release artifact
    # For testing: clone repo. Must change for public release.
    REPO_URL="https://github.com/ankurmakavana/trustnode.git"
    echo "Downloading TrustNode from $REPO_URL ..."
    git clone --quiet "$REPO_URL" "$INSTALL_DIR"
    cd "$INSTALL_DIR"
fi

# 5. Environment configuration
echo -e "\n[*] Configuring environment..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "Created .env file."
else
    echo "Using existing .env file."
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
docker compose -f compose.dev.yaml exec -T php php artisan key:generate --force
docker compose -f compose.dev.yaml exec -T php php artisan migrate --force

echo -e "\n[*] Configuring CLI authentication..."
cat > cli-token.php << 'EOF'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = \App\Models\User::firstOrCreate(['email'=>'cli@trustnode.local'], ['name'=>'CLI System', 'password'=>bcrypt('secret'), 'role_id'=>1]);
echo $user->createToken('CLI Token')->plainTextToken;
EOF
CLI_TOKEN=$(docker compose -f compose.dev.yaml exec -T php php cli-token.php | tr -d '\r\n')
rm cli-token.php

mkdir -p "$HOME/.trustnode"
cat > "$HOME/.trustnode/config" << EOF
{
    "server": "http://nginx",
    "token": "$CLI_TOKEN"
}
EOF

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
exec docker compose -f "$INSTALL_DIR/compose.dev.yaml" exec -T -e TRUSTNODE_API_URL=http://nginx -e TRUSTNODE_API_TOKEN="$CLI_TOKEN" php php cli/bin/trustnode "\$@"
EOF
chmod +x "$CLI_WRAPPER"

# Add to PATH if not present
if [[ ":$PATH:" != *":$HOME/.local/bin:"* ]]; then
    echo "Adding $HOME/.local/bin to PATH in ~/.bashrc and ~/.zshrc"
    echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.bashrc
    echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.zshrc
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

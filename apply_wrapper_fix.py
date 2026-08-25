import re

# install.ps1
with open(r'c:\xampp\htdocs\trustnode\install.ps1', 'r', encoding='utf-8') as f:
    ps1 = f.read()

# Replace the giant if /I "!CMD!"=="update" block
ps1 = re.sub(
    r'if /I "!CMD!"=="update" \([\s\S]+?echo Restarting services\.\.\.[\s\S]+?exit /b 0\r?\n\)',
    r'''if /I "!CMD!"=="update" (
    echo TrustNode Updater
    echo =================
    powershell -NoProfile -Command "$script = irm https://trustnode.in/install.ps1; & ([scriptblock]::Create($script)) -Mode update"
    exit /b !ERRORLEVEL!
)''',
    ps1
)
with open(r'c:\xampp\htdocs\trustnode\install.ps1', 'w', encoding='utf-8') as f:
    f.write(ps1)

# install.sh
with open(r'c:\xampp\htdocs\trustnode\install.sh', 'r', encoding='utf-8') as f:
    sh = f.read()

sh = re.sub(
    r'elif \[ "\$CMD" = "update" \]; then[\s\S]+?echo "\[OK\] TrustNode updated successfully\."[\s\S]+?exit 0',
    r'''elif [ "$CMD" = "update" ]; then
    echo "TrustNode Updater"
    echo "================="
    curl -fsSL https://trustnode.in/install.sh | bash -s -- --mode update
    exit $?''',
    sh
)
with open(r'c:\xampp\htdocs\trustnode\install.sh', 'w', encoding='utf-8') as f:
    f.write(sh)

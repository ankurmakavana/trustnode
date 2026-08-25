import re

path = r"C:\Users\Groot\trustnode-app\trustnode.cmd"
with open(path, "r", encoding="utf-8") as f:
    c = f.read()

replacement = """if /I "!CMD!"=="update" (
    echo TrustNode Updater
    echo =================
    powershell -NoProfile -Command "$script = irm https://trustnode.in/install.ps1; & ([scriptblock]::Create($script)) -Mode update"
    exit /b !ERRORLEVEL!
)

if /I "!CMD!"=="doctor\""""

c = re.sub(r'if /I "!CMD!"=="doctor"', replacement, c)

with open(path, "w", encoding="utf-8") as f:
    f.write(c)

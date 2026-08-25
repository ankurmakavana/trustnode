with open(r'c:\xampp\htdocs\trustnode\install.ps1', 'r', encoding='utf-8') as f:
    ps1 = f.read()

# Extract the block
import re
match = re.search(r'\$cliWrapperContent = @"\n(.*?)\n"@', ps1, re.DOTALL)
if match:
    content = match.group(1)
    with open(r'C:\Users\Groot\trustnode-app\trustnode.cmd', 'w', encoding='utf-8') as f:
        f.write(content)
    print("Successfully generated trustnode.cmd from install.ps1")
else:
    print("Could not find the wrapper content in install.ps1")

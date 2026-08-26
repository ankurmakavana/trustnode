import io
content = io.open('install.ps1', 'r', encoding='utf-8').read()
start = content.find('    $cliWrapperContent = @"')
end = content.find('    Set-Content -Path $cliWrapperPath -Value $cliWrapperContent') + len('    Set-Content -Path $cliWrapperPath -Value $cliWrapperContent -Encoding Ascii')
wrapper_code = "$installDir = 'C:\\Users\\Groot\\trustnode-app'\n$cliWrapperPath = 'C:\\Users\\Groot\\trustnode-app\\trustnode.cmd'\n" + content[start:end]
io.open('regen.ps1', 'w', encoding='utf-8').write(wrapper_code)

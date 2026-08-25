$content = Get-Content 'C:\Users\Groot\trustnode-app\trustnode.cmd' -Raw
$content = $content -replace '(?s)if /I "!CMD!"=="update" \(.*?\n\)', ''
Set-Content 'C:\Users\Groot\trustnode-app\trustnode.cmd' -Value $content

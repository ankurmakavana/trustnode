$c = Get-Content 'install.ps1' -Raw
$m = [regex]::Match($c, '(?s)\$cliWrapperContent = @"\r?\n(.*?)\r?\n"@')
if($m.Success) {
    [IO.File]::WriteAllText('C:\Users\Groot\trustnode-app\trustnode.cmd', $m.Groups[1].Value)
}

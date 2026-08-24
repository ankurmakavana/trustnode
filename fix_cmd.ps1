$content = Get-Content 'install.ps1' -Raw
$startStr = '@"' + "`r`n" + '@echo off'
$start = $content.IndexOf($startStr) + 4
$end = $content.IndexOf("`r`n`"@", $start)
$cmdText = $content.Substring($start, $end - $start)
$cmdText = $cmdText.Replace('`$', '$').Replace('`"', '"').Replace("`r`n", "`n").Replace("`n", "`r`n")
Set-Content 'C:\Users\Groot\trustnode-app\trustnode.cmd' $cmdText -Encoding Ascii

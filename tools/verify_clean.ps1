$ErrorActionPreference = 'Stop'

$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$failures = 0

function Stage([string]$name, [scriptblock]$action) {
    Write-Host ("[{0}]" -f $name)
    try {
        & $action
        Write-Host ("OK: {0}" -f $name) -ForegroundColor Green
    } catch {
        $script:failures++
        Write-Host ("FAIL: {0}: {1}" -f $name, $_.Exception.Message) -ForegroundColor Red
    }
}

function Invoke-Checked([string]$file, [string[]]$arguments) {
    & $file @arguments
    if ($LASTEXITCODE -ne 0) {
        throw ("{0} exited with status {1}" -f $file, $LASTEXITCODE)
    }
}

Stage 'PHP and extensions' {
    $php = Get-Command php -ErrorAction Stop
    $versionLines = & $php.Source --version
    $versionExit = $LASTEXITCODE
    $version = (($versionLines | Select-Object -First 1 | Out-String).Trim())
    if ($versionExit -ne 0 -or $version -notlike 'PHP *') { throw "PHP is not executable ($version)" }
    $modules = @(& $php.Source -m | ForEach-Object { $_.ToString().Trim() })
    foreach ($extension in @('PDO', 'pdo_mysql')) {
        if ($modules -notcontains $extension) { throw "Missing PHP extension: $extension" }
    }
}

Stage 'Secrets and local-only files' {
    $forbiddenNames = @('config.local.php', '.env', '.env.local', 'credentials.json')
    $files = Get-ChildItem -LiteralPath $root -File -Recurse | Where-Object { $_.FullName -notmatch '\\.git\\' }
    $badNames = $files | Where-Object { $forbiddenNames -contains $_.Name }
    if ($badNames) { throw ('Secret-bearing file present: ' + (($badNames | ForEach-Object FullName) -join ', ')) }
    $secretPattern = '(?i)(AKIA[0-9A-Z]{16}|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|password\s*[:=]\s*["'']?[^\s"'']{8,})'
    foreach ($file in ($files | Where-Object { $_.Extension -in @('.php', '.ps1', '.sh', '.sql', '.md', '.json') })) {
        $text = Get-Content -LiteralPath $file.FullName -Raw
        if ($text -match $secretPattern -and $file.Name -notin @('AGENTS.md', 'README.md')) {
            throw "Possible secret in $($file.FullName)"
        }
    }
}

Stage 'Forbidden dependencies' {
    $base = Join-Path $root 'clinic-base'
    if (Test-Path (Join-Path $base 'vendor')) { throw 'clinic-base/vendor is forbidden' }
    $files = Get-ChildItem -LiteralPath $base -File -Recurse
    $patterns = @('jquery', 'bootstrap', 'tailwind', 'foundation', 'composer', 'XMLHttpRequest', '\bfetch\s*\(\s*["'']https?://', '<iframe', 'https://')
    foreach ($file in $files) {
        $text = Get-Content -LiteralPath $file.FullName -Raw
        foreach ($pattern in $patterns) { if ($text -match $pattern) { throw "Forbidden dependency or transport '$pattern' in $($file.FullName)" } }
    }
}

Stage 'PHP lint' {
    $phpFiles = Get-ChildItem -LiteralPath (Join-Path $root 'clinic-base') -File -Recurse -Filter '*.php'
    foreach ($file in $phpFiles) { Invoke-Checked 'php' @('-l', $file.FullName) | Out-Host }
}

Stage 'Isolated database reset' { Invoke-Checked 'php' @((Join-Path $root 'tools/db_reset.php')) | Out-Host }
Stage 'Full test suite' { Invoke-Checked 'php' @((Join-Path $root 'tests/run.php')) | Out-Host }

Stage 'Route smoke tests' {
    $port = 18080 + (Get-Random -Minimum 0 -Maximum 500)
    $log = Join-Path ([IO.Path]::GetTempPath()) ('clinic-verify-' + [guid]::NewGuid().ToString('N'))
    $stdout = $log + '.out'
    $stderr = $log + '.err'
    $server = Start-Job -ScriptBlock { param($directory, $listenPort) Set-Location $directory; php -S "127.0.0.1:$listenPort" -t (Join-Path $directory 'clinic-base') } -ArgumentList $root, $port
    try {
        Start-Sleep -Milliseconds 500
        foreach ($route in @('/doctor.php?id=1', '/assets/img/clinic-logo.svg')) {
            $client = [Net.Sockets.TcpClient]::new('127.0.0.1', $port)
            $stream = $client.GetStream()
            $request = [Text.Encoding]::ASCII.GetBytes("GET $route HTTP/1.1`r`nHost: localhost`r`nConnection: close`r`n`r`n")
            $stream.Write($request, 0, $request.Length)
            $reader = [IO.StreamReader]::new($stream)
            $statusLine = $reader.ReadLine()
            if ($statusLine -notmatch '^HTTP/\S+ 200 ') { throw "Route $route returned $statusLine" }
            $reader.Dispose()
            $client.Dispose()
        }
    } finally {
        if ($server) { Stop-Job -Job $server -ErrorAction SilentlyContinue; Remove-Job -Job $server -Force -ErrorAction SilentlyContinue }
        Remove-Item -LiteralPath $stdout, $stderr -Force -ErrorAction SilentlyContinue
    }
}

if ($failures -gt 0) {
    Write-Host ("FAILED: {0} stage(s) failed" -f $failures) -ForegroundColor Red
    exit 1
}
Write-Host 'OK: clean-checkout verification complete' -ForegroundColor Green
exit 0

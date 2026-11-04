# Sobe o ambiente local sem Docker, usando os binários do Laragon.
# Uso: powershell -ExecutionPolicy Bypass -File scripts\dev.ps1
$root = Split-Path $PSScriptRoot -Parent
$php = 'C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe'
$mysql = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64'
$data = 'C:\laragon\data\mysql-8.4'

if (-not (Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue)) {
    Start-Process "$mysql\bin\mysqld.exe" -WindowStyle Hidden -ArgumentList @(
        '--no-defaults', "--basedir=$mysql", "--datadir=$data", '--port=3306', '--bind-address=127.0.0.1',
        '--character-set-server=utf8mb4', '--collation-server=utf8mb4_0900_ai_ci'
    )
}

Start-Process $php -WorkingDirectory "$root\apps\api" -ArgumentList 'artisan', 'serve', '--host=127.0.0.1', '--port=8000'
Start-Process $php -WorkingDirectory "$root\apps\api" -ArgumentList 'artisan', 'queue:work', '--tries=3'
Start-Process npm.cmd -WorkingDirectory "$root\apps\web" -ArgumentList 'run', 'dev'

Write-Output 'Plataforma: http://localhost:5173'

# Sobe o ambiente local sem Docker, usando os binários do Laragon.
# Uso: powershell -ExecutionPolicy Bypass -File scripts\dev.ps1
# Os processos ficam independentes deste terminal. O que já estiver rodando é mantido.
$root = Split-Path $PSScriptRoot -Parent
$api = "$root\apps\api"
$php = 'C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe'
$mysql = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64'
$data = 'C:\laragon\data\mysql-8.4'

function Test-Listening($port) {
    [bool](Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue)
}

function Test-Artisan($command) {
    [bool](Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object { $_.CommandLine -like "*$command*" })
}

if (-not (Test-Listening 3306)) {
    Start-Process "$mysql\bin\mysqld.exe" -WindowStyle Hidden -ArgumentList @(
        '--no-defaults', "--basedir=$mysql", "--datadir=$data", '--port=3306', '--bind-address=127.0.0.1',
        '--character-set-server=utf8mb4', '--collation-server=utf8mb4_0900_ai_ci'
    )
}

# A fila e o agendador falham se o banco ainda não estiver aceitando conexões.
for ($i = 0; $i -lt 30 -and -not (Test-Listening 3306); $i++) { Start-Sleep -Seconds 1 }

if (-not (Test-Listening 8000)) {
    Start-Process $php -WindowStyle Hidden -WorkingDirectory $api -ArgumentList 'artisan', 'serve', '--host=127.0.0.1', '--port=8000'
}

if (-not (Test-Artisan 'queue:work')) {
    Start-Process $php -WindowStyle Hidden -WorkingDirectory $api -ArgumentList 'artisan', 'queue:work', '--tries=3', '--timeout=300'
}

if (-not (Test-Artisan 'schedule:work')) {
    Start-Process $php -WindowStyle Hidden -WorkingDirectory $api -ArgumentList 'artisan', 'schedule:work'
}

if (-not (Test-Listening 5173)) {
    Start-Process cmd.exe -WindowStyle Hidden -WorkingDirectory "$root\apps\web" -ArgumentList '/c', 'npm run dev'
}

Write-Output 'Plataforma: http://localhost:5173'

# Gera o APK do Olavo TV+ e o coloca em apps/web/public/downloads.
# Uso: powershell -ExecutionPolicy Bypass -File scripts\build-apk.ps1 -Url https://plataforma.exemplo.com.br
param(
    [Parameter(Mandatory = $true)][string]$Url
)

$ErrorActionPreference = 'Stop'
$web = Join-Path (Split-Path $PSScriptRoot -Parent) 'apps\web'
$android = "$web\android"

if (-not $env:ANDROID_HOME) { $env:ANDROID_HOME = "$env:LOCALAPPDATA\Android\Sdk" }
if (-not (Test-Path "$env:ANDROID_HOME\platforms")) {
    throw 'SDK do Android não encontrado. Abra o Android Studio e conclua a instalação padrão.'
}

if (-not $env:JAVA_HOME) { $env:JAVA_HOME = 'C:\Program Files\Android\Android Studio\jbr' }
$keytool = "$env:JAVA_HOME\bin\keytool.exe"
if (-not (Test-Path $keytool)) { throw "Java não encontrado em $env:JAVA_HOME." }

# A chave assina todas as versões: sem ela, quem tem o app não consegue atualizar.
$keystore = "$android\olavo-tv-plus.keystore"
$properties = "$android\keystore.properties"
if (-not (Test-Path $keystore)) {
    $password = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 32 | ForEach-Object { [char]$_ })
    & $keytool -genkeypair -v -keystore $keystore -alias olavo -keyalg RSA -keysize 2048 -validity 10000 -storepass $password -keypass $password -dname 'CN=Olavo TV+, O=Olavo Pre-Vestibular, C=BR'
    "storeFile=../olavo-tv-plus.keystore`nstorePassword=$password`nkeyAlias=olavo`nkeyPassword=$password" | Set-Content -Encoding ascii $properties
    Write-Host 'Chave criada. Faça uma cópia de android\olavo-tv-plus.keystore e android\keystore.properties em local seguro.'
}

Set-Location $web
$env:CAP_SERVER_URL = $Url
npm run build
if ($LASTEXITCODE -ne 0) { throw 'Falha no build do site.' }
npx cap sync android
if ($LASTEXITCODE -ne 0) { throw 'Falha ao sincronizar o Android.' }

Set-Location $android
.\gradlew.bat assembleRelease --no-daemon
if ($LASTEXITCODE -ne 0) { throw 'Falha ao gerar o APK.' }

$downloads = "$web\public\downloads"
New-Item -ItemType Directory -Force $downloads | Out-Null
Copy-Item "$android\app\build\outputs\apk\release\app-release.apk" "$downloads\olavo-tv-plus.apk" -Force
Copy-Item "$downloads\olavo-tv-plus.apk" "$web\dist\downloads\olavo-tv-plus.apk" -Force -ErrorAction SilentlyContinue
Write-Host "APK pronto: $downloads\olavo-tv-plus.apk"

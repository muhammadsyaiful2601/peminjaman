# Skrip: unduh & siapkan PHP portable Windows untuk dibundel ke installer.
# Jalankan SEKALI sebelum build:  powershell -ExecutionPolicy Bypass -File scripts\prepare-php.ps1
$ErrorActionPreference = 'Stop'

# PowerShell 5.1 default belum mengaktifkan TLS 1.2
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$dest = Join-Path $PSScriptRoot '..\resources\php'
New-Item -ItemType Directory -Force -Path $dest | Out-Null

# PHP 8.4 NTS x64 (build vs17 / Visual Studio 2022) dari server resmi php.net.
# Versi pinned agar build dapat direproduksi; link "latest" juga tersedia di
# https://windows.php.net/downloads/releases/latest/php-8.4-nts-Win32-vs17-x64-latest.zip
$url = 'https://windows.php.net/downloads/releases/php-8.4.25-nts-Win32-vs17-x64.zip'
$zip = Join-Path $env:TEMP 'php-8.4-nts-Win32-vs17-x64.zip'

Write-Host "Mengunduh PHP dari $url ..."
Invoke-WebRequest -Uri $url -OutFile $zip -UseBasicParsing

Write-Host "Ekstrak ke $dest ..."
Expand-Archive -Path $zip -DestinationPath $dest -Force

# Pasang php.ini khusus desktop
$iniSrc = Join-Path $PSScriptRoot '..\assets\php.ini'
Copy-Item -Path $iniSrc -Destination (Join-Path $dest 'php.ini') -Force

# Hapus file yang tidak perlu untuk runtime
@('php.ini-development', 'php.ini-production', 'php8ts.dll', 'phpdbg.exe') | ForEach-Object {
    $f = Join-Path $dest $_
    if (Test-Path $f) { Remove-Item $f -Force }
}

$phpExe = Join-Path $dest 'php.exe'
if (-not (Test-Path $phpExe)) { throw "php.exe tidak ditemukan setelah ekstraksi!" }

Write-Host "Verifikasi:"
& $phpExe -v
Write-Host ""
Write-Host "PHP portable siap di: $dest"

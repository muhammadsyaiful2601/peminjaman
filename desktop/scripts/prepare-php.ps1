# Skrip: unduh & siapkan PHP portable Windows untuk dibundel ke installer.
# Jalankan SEKALI sebelum build:  powershell -ExecutionPolicy Bypass -File scripts\prepare-php.ps1
$ErrorActionPreference = 'Stop'
# PowerShell 5.1 default belum mengaktifkan TLS 1.2
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

# WAJIB: tanpa ini, Invoke-WebRequest menggambar progress bar per-byte
# dan unduhan ~30MB bisa memakan 20-40 menit di runner CI.
$ProgressPreference = 'SilentlyContinue'

# PowerShell 5.1 default belum mengaktifkan TLS 1.2
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$dest = Join-Path $PSScriptRoot '..\resources\php'
New-Item -ItemType Directory -Force -Path $dest | Out-Null

# PHP 8.4 NTS x64 (build vs17 / Visual Studio 2022) dari server resmi php.net.
# Kandidat dicoba berurutan: versi pinned (reproduktif) -> arsip versi pinned ->
# "latest" yang selalu tersedia. Berkas versi pinned dipindahkan server ke folder
# arsip setelah rilis baru terbit (pernah membuat langkah CI gagal 404), jadi
# unduhan wajib punya cadangan + percobaan ulang.
$candidates = @(
    'https://windows.php.net/downloads/releases/php-8.4.25-nts-Win32-vs17-x64.zip',
    'https://windows.php.net/downloads/releases/archives/php-8.4.25-nts-Win32-vs17-x64.zip',
    'https://windows.php.net/downloads/releases/latest/php-8.4-nts-Win32-vs17-x64-latest.zip'
)
$zip = Join-Path $env:TEMP 'php-8.4-nts-Win32-vs17-x64.zip'

$downloaded = $false
foreach ($url in $candidates) {
    for ($attempt = 1; $attempt -le 2; $attempt++) {
        Write-Host "Mengunduh PHP dari $url (percobaan $attempt) ..."
        try {
            Invoke-WebRequest -Uri $url -OutFile $zip -UseBasicParsing
            $downloaded = $true
            break
        } catch {
            Write-Warning "Gagal mengunduh dari $url : $($_.Exception.Message)"
            Start-Sleep -Seconds 5
        }
    }

    if ($downloaded) { break }
}

if (-not $downloaded) {
    throw 'PHP portable tidak dapat diunduh dari semua URL kandidat.'
}

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

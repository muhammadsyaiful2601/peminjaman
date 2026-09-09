# Unduh runtime cloudflared (Cloudflare Quick Tunnel).
# Dipakai aplikasi desktop untuk membuka URL publik sementara saat komputer
# petugas online, sehingga tautan unduh bukti peminjaman di email berfungsi
# tanpa VPS/hosting. Jalankan: npm run prepare-cloudflared
$ErrorActionPreference = 'Stop'

$destDir = Join-Path $PSScriptRoot '..\resources\cloudflared'
New-Item -ItemType Directory -Force -Path $destDir | Out-Null
$dest = Join-Path $destDir 'cloudflared.exe'

if (Test-Path $dest -PathType Leaf) {
    $size = (Get-Item $dest).Length
    if ($size -gt 20MB) {
        Write-Host "[cloudflared] Runtime sudah ada ($([math]::Round($size / 1MB, 1)) MB). Lewati unduhan."
        exit 0
    }
    Remove-Item $dest -Force
}

$url = 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe'
Write-Host "[cloudflared] Mengunduh $url ..."
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
Invoke-WebRequest -Uri $url -OutFile $dest -UseBasicParsing

$size = (Get-Item $dest).Length
if ($size -lt 20MB) {
    Remove-Item $dest -Force -ErrorAction SilentlyContinue
    throw "[cloudflared] Ukuran file tidak wajar ($size byte). Unduhan gagal."
}

Write-Host "[cloudflared] Berhasil ($([math]::Round($size / 1MB, 1)) MB) -> $dest"
Write-Host '[cloudflared] Catatan: jika folder resources/cloudflared kosong saat build, fitur tautan unduh publik dinonaktifkan secara otomatis (email tetap mengirim lampiran PDF).'

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; padding: 40px; }
        .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px solid #06b6d4; }
        .header h1 { font-size: 28px; color: #0f172a; margin-bottom: 5px; }
        .header p { font-size: 14px; color: #64748b; }
        .loan-code-box { text-align: center; margin: 20px 0; }
        .loan-code { display: inline-block; background: #0f172a; color: #06b6d4; padding: 10px 30px; border-radius: 8px; font-size: 20px; font-weight: 700; letter-spacing: 2px; font-family: monospace; }
        .section { margin-bottom: 25px; }
        .section-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 12px; padding-bottom: 6px; border-bottom: 2px solid #e2e8f0; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 8px 12px; font-size: 14px; border-bottom: 1px solid #e2e8f0; }
        .info-table td.label { color: #64748b; width: 35%; font-weight: 500; }
        .info-table td.value { color: #0f172a; font-weight: 600; }
        .photo-section { text-align: center; margin: 20px 0; }
        .photo-section img { width: 200px; height: 200px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0; }
        .photo-label { font-size: 12px; color: #64748b; margin-top: 8px; }
        .qr-section { text-align: center; margin: 30px 0; }
        .qr-box { display: inline-block; padding: 20px; border: 2px dashed #cbd5e1; border-radius: 12px; }
        .qr-uuid { font-family: monospace; font-size: 11px; color: #64748b; margin-top: 10px; word-break: break-all; }
        .status-badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 4px 16px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #e2e8f0; text-align: center; }
        .footer p { font-size: 12px; color: #94a3b8; line-height: 1.6; }
        .instructions { background: #ecfeff; border-left: 4px solid #06b6d4; padding: 16px; border-radius: 4px; margin: 20px 0; }
        .instructions p { font-size: 13px; color: #0e7490; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📋 PinjamBarang</h1>
        <p>Sistem Peminjaman Barang Kampus</p>
    </div>

    <div class="loan-code-box">
        <p style="font-size: 12px; color: #64748b; margin-bottom: 8px;">KODE PEMINJAMAN</p>
        <span class="loan-code">{{ $loan->loan_code }}</span>
    </div>

    <div class="section">
        <div class="section-title">Data Peminjam</div>
        <table class="info-table">
            <tr>
                <td class="label">Nama Lengkap</td>
                <td class="value">{{ $loan->borrower_name }}</td>
            </tr>
            <tr>
                <td class="label">Email</td>
                <td class="value">{{ $loan->borrower_email }}</td>
            </tr>
            @if($loan->borrower_phone)
            <tr>
                <td class="label">No. Telepon</td>
                <td class="value">{{ $loan->borrower_phone }}</td>
            </tr>
            @endif
            @if($loan->borrower_student_id)
            <tr>
                <td class="label">NIM / NIP</td>
                <td class="value">{{ $loan->borrower_student_id }}</td>
            </tr>
            @endif
        </table>
    </div>

    @if($photoDataUri)
    <div class="photo-section">
        <img src="{{ $photoDataUri }}" alt="Foto Peminjam">
        <p class="photo-label">Foto Verifikasi Peminjam</p>
    </div>
    @endif

    <div class="section">
        <div class="section-title">Data Barang Pinjaman</div>
        <table class="info-table">
            <tr>
                <td class="label">Nama Barang</td>
                <td class="value">{{ $loan->item->name }}</td>
            </tr>
            <tr>
                <td class="label">Kode Barang</td>
                <td class="value">{{ $loan->item->item_code }}</td>
            </tr>
            <tr>
                <td class="label">Kategori</td>
                <td class="value">{{ $loan->item->category }}</td>
            </tr>
            <tr>
                <td class="label">Jumlah Dipinjam</td>
                <td class="value">{{ $loan->qty }} unit</td>
            </tr>
            <tr>
                <td class="label">Tanggal Peminjaman</td>
                <td class="value">{{ $loan->created_at->format('d M Y, H:i') }} WIB</td>
            </tr>
            <tr>
                <td class="label">Status</td>
                <td class="value">
                    @if($loan->status === 'returned')
                        <span class="status-badge" style="background:#d1fae5;color:#065f46;">Dikembalikan</span>
                    @else
                        <span class="status-badge" style="background:#d1fae5;color:#065f46;">Dipinjam</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="qr-section">
        <div class="qr-box">
            <img src="{{ $qrDataUri }}" alt="QR Code" style="width: 250px; height: 250px;">
        </div>
        <p class="qr-uuid">{{ $loan->uuid }}</p>
    </div>

    <div class="instructions">
        <p>
            <strong>📌 Petunjuk:</strong><br>
            1. Barang telah diserahkan kepada peminjam (status: Dipinjam).<br>
            2. Saat mengembalikan, tunjukkan QR Code atau kode peminjaman kepada petugas.<br>
            3. Petugas akan memverifikasi pengembalian dan kondisi barang.
        </p>
    </div>

    <div class="footer">
        <p>
            Dokumen ini diterbitkan oleh Sistem Peminjaman Barang Kampus.<br>
            Tanggal Cetak: {{ now()->format('d M Y, H:i') }} WIB
        </p>
    </div>
</body>
</html>
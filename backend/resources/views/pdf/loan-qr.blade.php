<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; padding: 40px 40px 95px; }
        .header { margin-bottom: 30px; padding-bottom: 16px; border-bottom: 3px solid #06b6d4; }
        .header-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .header-table td { border: 0; vertical-align: middle; }
        .header-logo { width: 70px; height: 70px; object-fit: contain; }
        .header-left { width: 20%; text-align: left; }
        .header-center { width: 60%; text-align: center; }
        .header-right { width: 20%; text-align: right; }
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
        .items-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .items-table th { background: #0f172a; color: #ffffff !important; padding: 10px 12px; font-size: 12px; text-align: left; }
        .items-table td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid #e2e8f0; vertical-align: top; word-wrap: break-word; }
        .items-table tbody tr:nth-child(even) { background: #f8fafc; }
        .items-table .item-name { width: 35%; font-weight: 600; color: #0f172a; }
        .items-table .item-code { width: 20%; color: #475569; font-family: monospace; font-size: 12px; }
        .items-table .item-category { width: 30%; color: #475569; }
        .items-table .item-qty { width: 15%; color: #0f172a; font-weight: 700; text-align: center; }
        .photo-section { text-align: center; margin: 20px 0; }
        .photo-section img { width: 200px; height: 200px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0; }
        .photo-label { font-size: 12px; color: #64748b; margin-top: 8px; }
        .qr-section { text-align: center; margin: 30px 0; }
        .qr-box { display: inline-block; padding: 20px; border: 2px dashed #cbd5e1; border-radius: 12px; }
        .qr-uuid { font-family: monospace; font-size: 11px; color: #64748b; margin-top: 10px; word-break: break-all; }
        .status-badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 4px 16px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .footer { position: fixed; bottom: 25px; left: 40px; right: 40px; padding-top: 14px; border-top: 2px solid #e2e8f0; text-align: center; }
        .footer p { font-size: 12px; color: #94a3b8; line-height: 1.6; }
        .instructions { background: #ecfeff; border-left: 4px solid #06b6d4; padding: 16px; border-radius: 4px; margin: 20px 0; }
        .instructions p { font-size: 13px; color: #0e7490; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <img class="header-logo" src="{{ public_path('images/logo_kampus.png') }}" alt="Logo Kampus">
                </td>
                <td class="header-center">
                    <h1>PinjamBarang</h1>
                    <p>Sistem Peminjaman Barang Kampus</p>
                </td>
                <td class="header-right">
                    <img class="header-logo" src="{{ public_path('images/si.png') }}" alt="Logo Sistem Informasi">
                </td>
            </tr>
        </table>
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
        <table class="items-table">
            <thead>
                <tr>
                    <th class="item-name">Nama Barang</th>
                    <th class="item-code">Kode</th>
                    <th class="item-category">Kategori</th>
                    <th class="item-qty">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($loan->loanItems->isNotEmpty() ? $loan->loanItems : collect([(object) ['item' => $loan->item, 'qty' => $loan->qty]]) as $loanItem)
                <tr>
                    <td class="item-name">{{ $loanItem->item->name }}</td>
                    <td class="item-code">{{ $loanItem->item->item_code }}</td>
                    <td class="item-category">{{ $loanItem->item->category }}</td>
                    <td class="item-qty">{{ $loanItem->qty }} unit</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <table class="info-table" style="margin-top: 12px;">
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
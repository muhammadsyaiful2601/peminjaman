<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10px; margin: 28px 34px; }
        .header { margin-bottom: 0; padding-bottom: 0; border-bottom: 0; }
        .header-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .header-table td { border: 0; vertical-align: middle; }
        .header-logo { width: 66px; height: 70px; object-fit: contain; }
        .header-left { width: 78px; text-align: left; }
        .header-center { text-align: center; }
        .header-right { width: 78px; text-align: right; }
        .header h1 { font-size: 17px; color: #111827; margin: 0 0 3px; }
        .header h2 { font-size: 13px; margin: 0 0 2px; }
        .header p { font-size: 11px; font-weight: bold; margin: 0; }
        .header .address { font-size: 8px; font-weight: normal; margin-top: 3px; }
        .rule { border-top: 2px solid #111827; border-bottom: 1px solid #111827; height: 4px; margin: 10px 0 18px; }
        .document-title { text-align: center; margin-bottom: 14px; }
        .document-title h3 { font-size: 14px; margin: 0; }
        .document-title p { margin: 3px 0 0; }
        .loan-code-box { text-align: center; margin: 0 0 14px; }
        .loan-code { font-weight: 700; font-family: monospace; letter-spacing: 1px; }
        .section { margin-bottom: 18px; }
        .section-title { font-size: 11px; font-weight: 700; color: #111827; margin-bottom: 6px; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 5px; font-size: 10px; border: 1px solid #374151; }
        .info-table td.label { color: #111827; width: 35%; font-weight: 500; }
        .info-table td.value { color: #111827; font-weight: 600; }
        .items-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .items-table th { background: #dbe4ee; color: #111827 !important; padding: 6px 5px; font-size: 10px; text-align: center; border: 1px solid #374151; }
        .items-table td { padding: 6px 5px; font-size: 10px; border: 1px solid #374151; vertical-align: top; word-wrap: break-word; }
        .items-table .item-name { width: 35%; font-weight: 600; color: #0f172a; }
        .items-table .item-code { width: 20%; color: #475569; font-family: monospace; font-size: 12px; }
        .items-table .item-category { width: 30%; color: #475569; }
        .items-table .item-qty { width: 15%; color: #0f172a; font-weight: 700; text-align: center; }
        .photo-section { text-align: center; margin: 12px 0; }
        .photo-section img { width: 120px; height: 120px; object-fit: cover; border: 1px solid #374151; }
        .photo-label { font-size: 8px; color: #64748b; margin-top: 4px; }
        .qr-section { text-align: center; margin: 22px 0; page-break-inside: avoid; }
        .qr-box { display: inline-block; padding: 12px; border: 1px solid #374151; }
        .qr-uuid { font-family: monospace; font-size: 8px; color: #64748b; margin-top: 6px; word-break: break-all; }
        .status-badge { display: inline-block; background: #d1fae5; color: #065f46; padding: 3px 10px; font-size: 9px; font-weight: 600; }
        .footer { position: fixed; bottom: 18px; left: 34px; right: 34px; padding-top: 8px; border-top: 1px solid #d1d5db; text-align: center; }
        .footer p { font-size: 8px; color: #94a3b8; line-height: 1.5; }
        .instructions { background: #ecfeff; border-left: 4px solid #06b6d4; padding: 10px; margin: 14px 0; }
        .instructions p { font-size: 9px; color: #0e7490; line-height: 1.6; }
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
                    <h1>POLITEKNIK NEGERI PADANG</h1>
                    <h2>JURUSAN TEKNOLOGI INFORMASI</h2>
                    <p>PROGRAM STUDI SISTEM INFORMASI</p>
                    <p class="address">Kampus Politeknik Negeri Padang, Tanah Datar</p>
                </td>
                <td class="header-right">
                    <img class="header-logo" src="{{ public_path('images/si.png') }}" alt="Logo Sistem Informasi">
                </td>
            </tr>
        </table>
    </div>
    <div class="rule"></div>

    <div class="document-title">
        <h3>BUKTI PEMINJAMAN BARANG</h3>
        <p>Kode Peminjaman: <span class="loan-code">{{ $loan->loan_code }}</span></p>
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
            Dokumen ini diterbitkan oleh Politeknik Negeri Padang.<br>
            Tanggal Cetak: {{ now()->format('d M Y, H:i') }} WIB
        </p>
    </div>
</body>
</html>
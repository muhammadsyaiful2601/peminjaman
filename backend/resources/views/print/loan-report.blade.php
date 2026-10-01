<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Peminjaman Barang</title>
    {{--
        Dokumen berdiri sendiri untuk dicetak. Sengaja tidak memakai kerangka
        aplikasi agar hasil cetak tidak terpotong atau kosong karena sidebar
        tetap, footer tetap, dan offset `md:pl-*` yang ikut aktif pada lebar
        kertas A4.
    --}}
    <style>
        @page {
            size: A4;
            margin: 14mm 12mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            color: #111827;
            font-size: 10px;
            line-height: 1.45;
            margin: 0;
            background: #ffffff;
        }
        .letterhead { display: table; width: 100%; text-align: center; }
        .logo-cell { display: table-cell; width: 78px; vertical-align: middle; text-align: left; }
        .logo { width: 66px; height: 70px; object-fit: contain; }
        .identity { display: table-cell; vertical-align: middle; }
        .identity h1, .identity h2, .identity p, .title h3, .title p { margin: 0; }
        .identity h1 { font-size: 15px; font-weight: 700; text-transform: uppercase; }
        .identity h2 { font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .identity p { font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .identity .address { font-size: 8px; font-weight: 400; margin-top: 3px; text-transform: none; }
        .rule { border-top: 2px solid #111827; border-bottom: 1px solid #111827; height: 4px; margin: 10px 0 16px; }
        .title { text-align: center; margin-bottom: 12px; }
        .title h3 { font-size: 14px; }
        .title p { margin-top: 3px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .meta td:last-child { text-align: right; }
        table.report { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report th, .report td { border: 1px solid #374151; padding: 5px 5px; vertical-align: top; word-wrap: break-word; }
        .report th { background: #dbe4ee; text-align: center; font-weight: bold; }
        .report th:nth-child(1) { width: 5%; } .report th:nth-child(2) { width: 14%; }
        .report th:nth-child(3) { width: 19%; } .report th:nth-child(4) { width: 24%; }
        .report th:nth-child(5) { width: 7%; } .report th:nth-child(6) { width: 12%; } .report th:nth-child(7) { width: 19%; }
        .report thead { display: table-header-group; }
        .report tr { page-break-inside: avoid; }
        .signature-page { page-break-inside: avoid; margin-top: 32px; }
        .signature { width: 34%; margin-left: 66%; line-height: 1.5; }
        .signature p { margin: 0; }
        .signature .role { margin-top: 24px; }
        .signature .space { height: 46px; }
        .signature .name { font-weight: 700; }
        /* Tanda tangan digital menggantikan ruang kosong tanda tangan. */
        .signature .signature-image { display: block; margin: 4px 0 2px; width: 130px; height: auto; }
    </style>
</head>
<body>
    <div class="letterhead">
        <div class="logo-cell"><img class="logo" src="{{ $branding['letterhead_logo_path'] }}" alt="{{ $branding['organization_name'] }}"></div>
        <div class="identity">
            <h1>{{ $branding['organization_ministry'] }}</h1>
            <h2>{{ $branding['organization_unit'] }}</h2>
            <p>{{ $branding['organization_name'] }}</p>
            <p class="address">{{ $branding['organization_address'] }}</p>
            <p>{{ $branding['organization_department'] }}</p>
        </div>
    </div>
    <div class="rule"></div>
    <div class="title">
        <h3>LAPORAN PEMINJAMAN BARANG</h3>
        <p>Periode: {{ $startDate || $endDate ? ($startDate ?: 'Awal') . ' - ' . ($endDate ?: 'Sekarang') : 'Seluruh periode' }}</p>
    </div>
    <table class="meta">
        <tr>
            <td>Dicetak pada: {{ now()->format('d/m/Y H:i') }}</td>
            <td>Jumlah transaksi: {{ $loans->count() }}</td>
        </tr>
    </table>
    <table class="report">
        <thead>
            <tr>
                <th>No.</th>
                <th>Kode transaksi</th>
                <th>Peminjam</th>
                <th>Barang</th>
                <th>Jumlah</th>
                <th>Status</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
        @forelse($loans as $index => $loan)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $loan->loan_code }}</td>
                <td>{{ $loan->borrower_name }}<br>{{ $loan->borrower_student_id ?: $loan->borrower_email }}</td>
                <td>{{ $loan->loanItems->count() ? $loan->loanItems->map(fn ($loanItem) => $loanItem->item?->name)->filter()->join(', ') : $loan->item?->name }}</td>
                <td>{{ $loan->loanItems->count() ? $loan->loanItems->sum('qty') : $loan->qty }}</td>
                <td>{{ ['borrowed' => 'Dipinjam', 'returned' => 'Dikembalikan', 'pending' => 'Menunggu', 'rejected' => 'Ditolak'][$loan->status] ?? $loan->status }}</td>
                <td>{{ $loan->created_at->format('d/m/Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Tidak ada transaksi pada filter yang dipilih.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="signature-page">
        <div class="signature">
            <p>Tanah Datar, {{ now()->format('d F Y') }}</p>
            <p class="role">Teknisi</p>
            @if(! empty($signatorySignature))
                <img class="signature-image" src="{{ $signatorySignature }}" alt="Tanda tangan {{ $signatoryName ?: 'teknisi' }}">
            @else
                <p class="space"></p>
            @endif
            <p class="name">{{ $signatoryName ?: '____________________________' }}</p>
            <p>NIP. {{ $signatoryNip ?: '________________________' }}</p>
        </div>
    </div>
</body>
</html>



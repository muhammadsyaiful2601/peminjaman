<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10px; margin: 28px 34px; }
        .letterhead { display: table; width: 100%; min-height: 72px; text-align: center; }
        .logo-cell { display: table-cell; width: 78px; vertical-align: middle; text-align: left; }
        .logo { width: 66px; height: 70px; object-fit: contain; }
        .identity { display: table-cell; vertical-align: middle; }
        .identity h1, .identity h2, .identity p, .title h3, .title p { margin: 0; }
        .identity h1 { font-size: 17px; }
        .identity h2 { font-size: 13px; }
        .identity p { font-size: 11px; font-weight: bold; }
        .identity .address { font-size: 8px; font-weight: normal; margin-top: 3px; }
        .rule { border-top: 2px solid #111827; border-bottom: 1px solid #111827; height: 4px; margin: 10px 0 18px; }
        .title { text-align: center; margin-bottom: 14px; }
        .title h3 { font-size: 14px; }
        .title p { margin-top: 3px; }
        .meta { width: 100%; margin-bottom: 6px; }
        .meta td:last-child { text-align: right; }
        table.report { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report th, .report td { border: 1px solid #374151; padding: 6px 5px; vertical-align: top; word-wrap: break-word; }
        .report th { background: #dbe4ee; text-align: center; font-weight: bold; }
        .report th:nth-child(1) { width: 5%; } .report th:nth-child(2) { width: 14%; }
        .report th:nth-child(3) { width: 19%; } .report th:nth-child(4) { width: 24%; }
        .report th:nth-child(5) { width: 7%; } .report th:nth-child(6) { width: 12%; } .report th:nth-child(7) { width: 19%; }
        .signature { width: 32%; margin-left: 68%; margin-top: 38px; line-height: 1.5; }
        .signature p { margin: 0; }
        .signature .role { margin-top: 28px; }
        .signature .space { height: 48px; }
        .signature .name { font-weight: bold; }
    </style>
</head>
<body>
    <div class="letterhead">
        <div class="logo-cell"><img class="logo" src="{{ public_path('images/logo_kampus.png') }}" alt="Logo PNP"></div>
        <div class="identity">
            <h1>POLITEKNIK NEGERI PADANG</h1>
            <h2>JURUSAN TEKNOLOGI INFORMASI</h2>
            <p>PROGRAM STUDI SISTEM INFORMASI</p>
            <p class="address">Kampus Politeknik Negeri Padang, Limau Manis, Padang</p>
        </div>
    </div>
    <div class="rule"></div>
    <div class="title">
        <h3>LAPORAN PEMINJAMAN BARANG</h3>
        <p>Periode: {{ $startDate || $endDate ? ($startDate ?: 'Awal') . ' - ' . ($endDate ?: 'Sekarang') : 'Seluruh periode' }}</p>
    </div>
    <table class="meta">
        <tr><td>Dicetak pada: {{ now()->format('d/m/Y H:i') }}</td><td>Jumlah transaksi: {{ $loans->count() }}</td></tr>
    </table>
    <table class="report">
        <thead><tr><th>No.</th><th>Kode transaksi</th><th>Peminjam</th><th>Barang</th><th>Jumlah</th><th>Status</th><th>Tanggal</th></tr></thead>
        <tbody>
        @foreach($loans as $index => $loan)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $loan->loan_code }}</td>
                <td>{{ $loan->borrower_name }}<br>{{ $loan->borrower_student_id ?: $loan->borrower_email }}</td>
                <td>{{ $loan->loanItems->count() ? $loan->loanItems->map(fn ($loanItem) => $loanItem->item->name)->join(', ') : $loan->item->name }}</td>
                <td>{{ $loan->loanItems->count() ? $loan->loanItems->sum('qty') : $loan->qty }}</td>
                <td>{{ ['borrowed' => 'Dipinjam', 'returned' => 'Dikembalikan', 'pending' => 'Menunggu', 'rejected' => 'Ditolak'][$loan->status] ?? $loan->status }}</td>
                <td>{{ $loan->created_at->format('d/m/Y') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="signature">
        <p>Padang, {{ now()->format('d F Y') }}</p>
        <p>Mengetahui,</p>
        <p class="role">Ketua Program Studi Sistem Informasi</p>
        <p class="space"></p>
        <p class="name">{{ $signatoryName ?: '____________________________' }}</p>
        <p>NIP. {{ $signatoryNip ?: '________________________' }}</p>
    </div>
</body>
</html>
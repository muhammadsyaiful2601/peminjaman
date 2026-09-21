<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; margin: 34px 42px; line-height: 1.5; }
        .letterhead { display: table; width: 100%; text-align: center; }
        .logo-cell { display: table-cell; width: 82px; vertical-align: middle; text-align: left; }
        .logo { width: 70px; height: 74px; object-fit: contain; }
        .identity { display: table-cell; vertical-align: middle; }
        .identity h1, .identity h2, .identity p, .title h3, .title p { margin: 0; }
        .identity h1 { font-size: 15px; font-weight: 700; text-transform: uppercase; }
        .identity h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .identity p { font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .identity .address { font-size: 9px; font-weight: normal; text-transform: none; }
        .rule { border-top: 2px solid #111827; border-bottom: 1px solid #111827; height: 4px; margin: 10px 0 26px; }
        .title { text-align: center; margin-bottom: 20px; }
        .title h3 { font-size: 16px; text-decoration: underline; }
        .title p { margin-top: 3px; }
        .intro, .closing { text-align: justify; margin: 0 0 14px; }
        .details { width: 100%; border-collapse: collapse; margin: 8px 0 16px; }
        .details td { padding: 3px 0; vertical-align: top; }
        .details td:first-child { width: 26%; }
        .details td:nth-child(2) { width: 3%; }
        table.items { width: 100%; border-collapse: collapse; margin: 10px 0 16px; }
        .items th, .items td { border: 1px solid #374151; padding: 7px; }
        .items th { background: #dbe4ee; text-align: center; }
        .items td:first-child, .items td:nth-child(3), .items td:nth-child(5) { text-align: center; }
        .note { font-size: 9px; color: #374151; margin: 0 0 10px; }
        .signatures { display: table; width: 100%; margin-top: 30px; }
        .signature { display: table-cell; width: 50%; text-align: center; vertical-align: top; }
        .signature p { margin: 0; } .signature .space { height: 58px; }
        .signature .name { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>
    @php
        $labName = $laboratory !== '' ? $laboratory : 'Laboratorium';
        $letterDate = \Illuminate\Support\Carbon::parse($letterDate);
    @endphp
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
        <h3>{{ $eligible ? 'SURAT KETERANGAN BEBAS LABORATORIUM' : 'SURAT KETERANGAN TANGGUNGAN PEMINJAMAN LABORATORIUM' }}</h3>
        <p>Nomor: {{ $letterNumber !== '' ? $letterNumber : '.......... /BEBAS-LAB/...... /' . $letterDate->format('Y') }}</p>
    </div>
    <p class="intro">Yang bertanda tangan di bawah ini, petugas {{ $labName }}, dengan ini menerangkan bahwa berdasarkan data peminjaman barang pada Sistem Peminjaman Barang {{ $branding['organization_name'] }}:</p>
    <table class="details">
        <tr><td>Nama peminjam</td><td>:</td><td>{{ $borrower['name'] }}</td></tr>
        <tr><td>NIM / NIP</td><td>:</td><td>{{ $borrower['student_id'] !== '' ? $borrower['student_id'] : '-' }}</td></tr>
        <tr><td>Email</td><td>:</td><td>{{ $borrower['email'] }}</td></tr>
        <tr><td>Jumlah transaksi</td><td>:</td><td>{{ $totals['total_loans'] }} transaksi ({{ $totals['total_qty'] }} unit barang)</td></tr>
        <tr><td>Status tanggungan</td><td>:</td><td>{{ $eligible ? 'Tidak ada (bebas labor)' : 'Ada ' . $totals['outstanding_loans'] . ' transaksi (' . $totals['outstanding_qty'] . ' unit) belum dikembalikan' }}</td></tr>
    </table>
    @if($eligible)
        <p class="intro">Benar bahwa peminjam tersebut <strong>tidak memiliki tanggungan peminjaman barang</strong> pada {{ $labName }}. Seluruh barang yang pernah dipinjam telah dikembalikan, dengan rincian sebagai berikut:</p>
    <table class="items">
        <thead><tr><th>No.</th><th>Kode transaksi</th><th>Jumlah</th><th>Nama barang</th><th>Tanggal kembali</th></tr></thead>
        <tbody>
        @forelse($loans as $index => $loan)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $loan->loan_code }}</td>
                <td>{{ $loan->loanItems->isNotEmpty() ? $loan->loanItems->sum('qty') : $loan->qty }} unit</td>
                <td>{{ $loan->loanItems->isNotEmpty() ? $loan->loanItems->map(fn ($loanItem) => $loanItem->item?->name)->filter()->join(', ') : $loan->item?->name }}</td>
                <td>{{ $loan->returned_at ? $loan->returned_at->locale('id')->translatedFormat('d/m/Y') : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Belum ada transaksi peminjaman yang tercatat.</td></tr>
        @endforelse
        </tbody>
    </table>
    @else
        <p class="intro">Benar bahwa peminjam tersebut <strong>masih memiliki tanggungan peminjaman barang</strong> pada {{ $labName }}, yaitu {{ $totals['outstanding_loans'] }} transaksi ({{ $totals['outstanding_qty'] }} unit barang) yang belum dikembalikan, dengan rincian sebagai berikut:</p>
        <table class="items">
            <thead><tr><th>No.</th><th>Kode transaksi</th><th>Jumlah</th><th>Nama barang</th><th>Tanggal pinjam</th></tr></thead>
            <tbody>
            @foreach($outstanding as $index => $loan)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $loan->loan_code }}</td>
                    <td>{{ $loan->loanItems->isNotEmpty() ? $loan->loanItems->sum('qty') : $loan->qty }} unit</td>
                    <td>{{ $loan->loanItems->isNotEmpty() ? $loan->loanItems->map(fn ($loanItem) => $loanItem->item?->name)->filter()->join(', ') : $loan->item?->name }}</td>
                    <td>{{ $loan->borrowed_at ? $loan->borrowed_at->locale('id')->translatedFormat('d/m/Y') : '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    @if($eligible)
        <p class="closing">Surat keterangan ini dibuat berdasarkan data peminjaman yang tercatat pada sistem dan berlaku selama peminjam tidak memiliki peminjaman baru yang belum diselesaikan. Demikian surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya, khususnya untuk keperluan {{ $purpose }}.</p>
    @else
        <p class="closing">Surat keterangan ini memuat daftar barang laboratorium yang belum dikembalikan dan dibuat berdasarkan data peminjaman yang tercatat pada sistem. Peminjam dimohon segera mengembalikan barang tersebut kepada {{ $labName }} agar dapat memperoleh surat keterangan bebas laboratorium.</p>
    @endif
    <p class="note">Dicetak dari sistem pada {{ now()->locale('id')->translatedFormat('d F Y H:i') }} WIB.</p>
    <div class="signatures">
        <div class="signature"><p>Peminjam / Penanggung Jawab</p><p class="space"></p><p class="name">{{ $borrower['name'] }}</p><p>{{ $borrower['student_id'] !== '' ? 'NIM / NIP. ' . $borrower['student_id'] : 'NIM / NIP. ..........................' }}</p></div>
        <div class="signature"><p>Tanah Datar, {{ $letterDate->locale('id')->translatedFormat('d F Y') }}</p><p>Petugas {{ $labName }}</p><p class="space"></p><p class="name">{{ $signatoryName }}</p><p>NIP. {{ $signatoryNip }}</p></div>
    </div>
</body>
</html>

@php
    $labName = $laboratory !== '' ? $laboratory : 'Laboratorium';
    $parsedLetterDate = \Illuminate\Support\Carbon::parse($letterDate);
    // Peminjam yang belum pernah meminjam tetap memperoleh surat bebas labor.
    // Isinya menerangkan bahwa tidak ada transaksi yang tercatat, dan tabel
    // rincian menuliskan bahwa tidak ada tanggungan.
    $totalLoans = (int) ($totals['total_loans'] ?? 0);
    $hasHistory = $totalLoans > 0;
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
    <p>Nomor: {{ $letterNumber !== '' ? $letterNumber : '.......... /BEBAS-LAB/...... /' . $parsedLetterDate->format('Y') }}</p>
</div>
<p class="intro">Yang bertanda tangan di bawah ini, petugas {{ $labName }}, dengan ini menerangkan bahwa berdasarkan data peminjaman barang pada Sistem Peminjaman Barang {{ $branding['organization_name'] }}:</p>
<table class="details">
    <tr><td>Nama peminjam</td><td>:</td><td>{{ $borrower['name'] }}</td></tr>
    <tr><td>NIM / NIP</td><td>:</td><td>{{ $borrower['student_id'] !== '' ? $borrower['student_id'] : '-' }}</td></tr>
    <tr><td>Email</td><td>:</td><td>{{ $borrower['email'] }}</td></tr>
    <tr><td>Keperluan</td><td>:</td><td>{{ trim((string) $purpose) !== '' ? $purpose : '-' }}</td></tr>
    <tr><td>Jumlah transaksi</td><td>:</td><td>{{ $hasHistory ? $totals['total_loans'] . ' transaksi (' . $totals['total_qty'] . ' unit barang)' : 'Tidak ada transaksi peminjaman' }}</td></tr>
    <tr><td>Status tanggungan</td><td>:</td><td>{{ $eligible ? 'Tidak ada tanggungan (bebas labor)' : 'Ada ' . $totals['outstanding_loans'] . ' transaksi (' . $totals['outstanding_qty'] . ' unit) belum dikembalikan' }}</td></tr>
</table>
@if(! $eligible)
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
@else
    {{-- Bebas labor: surat selalu menyatakan tidak ada tanggungan, baik peminjam
         yang sudah mengembalikan semua barang maupun yang belum pernah meminjam. --}}
    @if($hasHistory)
        <p class="intro">Benar bahwa peminjam tersebut <strong>tidak memiliki tanggungan peminjaman barang</strong> pada {{ $labName }}. Seluruh barang yang pernah dipinjam telah dikembalikan, dengan rincian sebagai berikut:</p>
    @else
        <p class="intro">Benar bahwa peminjam tersebut <strong>tidak memiliki tanggungan peminjaman barang</strong> pada {{ $labName }}. Berdasarkan data peminjaman yang tercatat pada sistem, peminjam tersebut <strong>belum pernah melakukan peminjaman barang</strong> pada laboratorium ini, sehingga tidak ada barang yang perlu dikembalikan. Rincian tanggungan peminjaman barang adalah sebagai berikut:</p>
    @endif
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
            <tr><td colspan="5">Tidak ada tanggungan peminjaman barang yang tercatat.</td></tr>
        @endforelse
        </tbody>
    </table>
@endif
@if($eligible)
    <p class="closing">Surat keterangan ini dibuat berdasarkan data peminjaman yang tercatat pada sistem dan berlaku selama peminjam tidak memiliki peminjaman baru yang belum diselesaikan. Demikian surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya, khususnya untuk keperluan {{ $purpose }}.</p>
@else
    <p class="closing">Surat keterangan ini memuat daftar barang laboratorium yang belum dikembalikan dan dibuat berdasarkan data peminjaman yang tercatat pada sistem. Peminjam dimohon segera mengembalikan barang tersebut kepada {{ $labName }} agar dapat memperoleh surat keterangan bebas laboratorium.</p>
@endif
<div class="signatures">
    <div class="signature"><p>Peminjam / Penanggung Jawab</p><p class="space"></p><p class="name">{{ $borrower['name'] }}</p><p>{{ $borrower['student_id'] !== '' ? 'NIM / NIP. ' . $borrower['student_id'] : 'NIM / NIP. ..........................' }}</p></div>
    <div class="signature">
        <p>Tanah Datar, {{ $parsedLetterDate->locale('id')->translatedFormat('d F Y') }}</p>
        <p>Petugas {{ $labName }}</p>
        @if(! empty($signatorySignature))
            <img class="signature-image" src="{{ $signatorySignature }}" alt="Tanda tangan {{ $signatoryName !== '' ? $signatoryName : 'petugas' }}">
        @else
            <p class="space"></p>
        @endif
        <p class="name">{{ $signatoryName }}</p>
        <p>NIP. {{ $signatoryNip }}</p>
    </div>
</div>

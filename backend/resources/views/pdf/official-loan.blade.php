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
        .identity h1 { font-size: 18px; } .identity h2 { font-size: 14px; }
        .identity p { font-size: 12px; font-weight: bold; }
        .identity .address { font-size: 9px; font-weight: normal; }
        .rule { border-top: 2px solid #111827; border-bottom: 1px solid #111827; height: 4px; margin: 10px 0 26px; }
        .title { text-align: center; margin-bottom: 20px; }
        .title h3 { font-size: 16px; text-decoration: underline; }
        .intro { text-align: justify; margin-bottom: 16px; }
        .details { width: 100%; border-collapse: collapse; margin: 8px 0 18px; }
        .details td { padding: 4px 0; vertical-align: top; }
        .details td:first-child { width: 28%; }
        .details td:nth-child(2) { width: 3%; }
        table.items { width: 100%; border-collapse: collapse; margin: 10px 0 18px; }
        .items th, .items td { border: 1px solid #374151; padding: 7px; }
        .items th { background: #dbe4ee; text-align: center; }
        .items td:first-child, .items td:last-child { text-align: center; }
        .closing { text-align: justify; margin-top: 12px; }
        .signatures { display: table; width: 100%; margin-top: 35px; }
        .signature { display: table-cell; width: 50%; text-align: center; vertical-align: top; }
        .signature p { margin: 0; } .signature .space { height: 58px; }
        .signature .name { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="letterhead">
        <div class="logo-cell"><img class="logo" src="{{ public_path('images/logo_kampus.png') }}" alt="Logo Politeknik Negeri Padang"></div>
        <div class="identity">
            <h1>POLITEKNIK NEGERI PADANG</h1>
            <h2>JURUSAN TEKNOLOGI INFORMASI</h2>
            <p>PROGRAM STUDI SISTEM INFORMASI</p>
            <p class="address">Kampus Politeknik Negeri Padang, Tanah Datar</p>
        </div>
    </div>
    <div class="rule"></div>
    <div class="title"><h3>SURAT PEMINJAMAN BARANG</h3><p>Nomor: {{ $loan->loan_code }}</p></div>
    <p class="intro">Dengan ini menerangkan bahwa barang-barang berikut dipinjam secara resmi untuk mendukung kegiatan peminjam.</p>
    <table class="details">
        <tr><td>Nama peminjam</td><td>:</td><td>{{ $loan->borrower_name }}</td></tr>
        <tr><td>Email</td><td>:</td><td>{{ $loan->borrower_email }}</td></tr>
        <tr><td>NIM mahasiswa</td><td>:</td><td>{{ $loan->borrower_student_id ?: '-' }}</td></tr>
        <tr><td>Keperluan</td><td>:</td><td>{{ $purpose }}</td></tr>
        <tr><td>Periode peminjaman</td><td>:</td><td>{{ date('d/m/Y', strtotime($borrowedDate)) }} sampai {{ date('d/m/Y', strtotime($returnDate)) }}</td></tr>
    </table>
    <table class="items">
        <thead><tr><th>No.</th><th>Nama barang</th><th>Kode barang</th><th>Jumlah</th></tr></thead>
        <tbody>
        @foreach($loan->loanItems as $index => $loanItem)
            <tr><td>{{ $index + 1 }}</td><td>{{ $loanItem->item->name }}</td><td>{{ $loanItem->item->item_code }}</td><td>{{ $loanItem->qty }} unit</td></tr>
        @endforeach
        </tbody>
    </table>
    <p class="closing">Peminjam bertanggung jawab menjaga barang yang dipinjam dan mengembalikannya sesuai periode yang telah ditentukan dalam kondisi baik.</p>
        <div class="signatures">
            <div class="signature"><p>Peminjam / Penanggung Jawab</p><p class="space"></p><p class="name">{{ $signatoryName }}</p><p>NIP / NIM. {{ $signatoryNip }}</p></div>
            <div class="signature"><p>Tanah Datar, {{ date('d F Y') }}</p><p>Petugas Peminjaman</p><p class="space"></p><p class="name">{{ $officerName }}</p><p>NIP. {{ $officerNip }}</p></div>
    </div>
</body>
</html>
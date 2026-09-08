{{--
    Email notification for loan QR Code
    Sent to borrower's email after petugas creates a loan
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Peminjaman Barang</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f1f5f9; color: #1e293b;">

    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">

        <!-- Institutional header -->
        <div style="background: #ffffff; border-radius: 12px 12px 0 0; padding: 24px 30px 18px; border-bottom: 4px solid #0e7490; text-align: center;">
            <img src="cid:logo-kampus@pnp.local" alt="Logo Politeknik Negeri Padang" width="72" height="76" style="display: block; width: 72px; height: 76px; object-fit: contain; margin: 0 auto 10px;">
            <h1 style="color: #0f172a; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: 0.3px;">POLITEKNIK NEGERI PADANG</h1>
            <p style="color: #334155; margin: 5px 0 0; font-size: 13px; font-weight: 700;">JURUSAN TEKNOLOGI INFORMASI</p>
            <p style="color: #64748b; margin: 3px 0 0; font-size: 12px;">PROGRAM STUDI SISTEM INFORMASI</p>
            <p style="color: #94a3b8; margin: 4px 0 0; font-size: 11px;">Kampus Politeknik Negeri Padang, Tanah Datar</p>
        </div>

        <!-- Content -->
        <div style="background: #ffffff; padding: 30px; border-radius: 0 0 12px 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">

            <p style="color: #0e7490; margin: 0 0 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;">Notifikasi Peminjaman</p>
            <h2 style="color: #0f172a; margin: 0 0 16px 0; font-size: 22px;">Bukti Peminjaman Barang</h2>

            <p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
                Halo <strong>{{ $loan->borrower_name }}</strong>,<br><br>
                Peminjaman barang Anda telah dibuat dan barang telah diserahkan kepada Anda
                (status: <strong>Dipinjam</strong>). Unduh bukti peminjaman beserta QR Code menggunakan
                tombol di bawah. Simpan QR Code tersebut dan tunjukkan kepada petugas saat Anda
                mengembalikan barang.
            </p>

            <!-- Loan Details -->
            <div style="background: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h3 style="color: #0f172a; margin: 0 0 16px 0; font-size: 16px;">Detail Peminjaman</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <tr>
                        <td style="padding: 8px 0; color: #64748b; width: 40%; vertical-align: top;">Barang yang dipinjam</td>
                        <td style="padding: 8px 0; color: #0f172a; font-weight: 600;">
                            <ul style="padding-left: 18px; margin: 0;">
                                @foreach($loan->loanItems->isNotEmpty() ? $loan->loanItems : collect([(object) ['item' => $loan->item, 'qty' => $loan->qty]]) as $loanItem)
                                <li style="padding-bottom: 6px;">{{ $loanItem->item->name }} - {{ $loanItem->qty }} unit (Kode: {{ $loanItem->item->item_code }})</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748b;">Tanggal</td>
                        <td style="padding: 8px 0; color: #0f172a; font-weight: 600;">{{ $loan->created_at->format('d M Y, H:i') }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748b;">Status</td>
                        <td style="padding: 8px 0;">
                            <span style="background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Dipinjam</span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Download Button -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $downloadUrl }}" style="display: inline-block; background: #06b6d4; color: #ffffff; text-decoration: none; padding: 16px 36px; border-radius: 8px; font-size: 16px; font-weight: 600; letter-spacing: 0.3px;">
                    ⬇️ Unduh Bukti Peminjaman (PDF)
                </a>
                <p style="color: #94a3b8; font-size: 12px; margin: 12px 0 0 0;">
                    Klik tombol di atas untuk mengunduh PDF berisi QR Code, data diri, dan detail barang.
                </p>
            </div>

            <!-- Instructions -->
            <div style="background: #ecfeff; border-left: 4px solid #06b6d4; border-radius: 4px; padding: 16px; margin: 20px 0;">
                <p style="margin: 0; color: #0e7490; font-size: 14px; line-height: 1.5;">
                    <strong>📌 Petunjuk:</strong><br>
                    1. Unduh bukti peminjaman (PDF) menggunakan tombol di atas.<br>
                    2. Barang telah diserahkan kepada Anda (status: Dipinjam).<br>
                    3. Saat mengembalikan barang, tunjukkan QR Code dari PDF ini kepada petugas untuk verifikasi pengembalian.
                </p>
            </div>

            <div style="border-top: 1px solid #e2e8f0; padding-top: 18px; margin-top: 24px; text-align: center;">
                <p style="color: #64748b; font-size: 12px; margin: 0; line-height: 1.5;">
                    Hormat kami,<br><strong style="color: #0f172a;">Politeknik Negeri Padang</strong>
                </p>
                <p style="color: #94a3b8; font-size: 11px; margin: 8px 0 0; line-height: 1.5;">
                    Email ini dikirim otomatis. Mohon tidak membalas email ini.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
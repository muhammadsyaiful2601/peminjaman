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

        <!-- Header -->
        <div style="background: linear-gradient(135deg, #0f172a 0%, #0e7490 100%); border-radius: 12px 12px 0 0; padding: 30px; text-align: center;">
            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">📋 PinjamBarang</h1>
            <p style="color: #cbd5e1; margin: 8px 0 0 0; font-size: 14px;">Sistem Peminjaman Barang Kampus</p>
        </div>

        <!-- Content -->
        <div style="background: #ffffff; padding: 30px; border-radius: 0 0 12px 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">

            <h2 style="color: #0f172a; margin: 0 0 16px 0; font-size: 20px;">Bukti Peminjaman Barang</h2>

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
                        <td style="padding: 8px 0; color: #64748b; width: 40%;">Barang</td>
                        <td style="padding: 8px 0; color: #0f172a; font-weight: 600;">{{ $loan->item->name }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748b;">Kode Barang</td>
                        <td style="padding: 8px 0; color: #0f172a; font-weight: 600;">{{ $loan->item->item_code }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748b;">Jumlah</td>
                        <td style="padding: 8px 0; color: #0f172a; font-weight: 600;">{{ $loan->qty }} unit</td>
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

            <p style="color: #94a3b8; font-size: 12px; text-align: center; margin: 24px 0 0 0; line-height: 1.5;">
                Email ini dikirim otomatis oleh Sistem Peminjaman Barang Kampus.<br>
                Mohon tidak membalas email ini.
            </p>
        </div>
    </div>
</body>
</html>
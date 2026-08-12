{{--
    Return confirmation email (bukti barang diterima)
    Sent to borrower's email after staff processes the return with a proof photo.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Pengembalian Barang</title>
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

            <h2 style="color: #0f172a; margin: 0 0 16px 0; font-size: 20px;">Bukti Barang Diterima</h2>

            <p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
                Halo <strong>{{ $loan->borrower_name }}</strong>,<br><br>

                Petugas telah menerima kembali barang pinjaman Anda. Berikut adalah <strong>bukti barang diterima</strong>
                beserta detail pengembalian dan kondisi barang yang dikembalikan.
            </p>

            <!-- Loan Details -->
            <div style="background: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h3 style="color: #0f172a; margin: 0 0 16px 0; font-size: 16px;">Detail Peminjaman & Pengembalian</h3>
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
                        <td style="padding: 8px 0; color: #64748b;">Tanggal Pinjam</td>
                        <td style="padding: 8px 0; color: #0f172a; font-weight: 600;">{{ $loan->borrowed_at?->format('d M Y, H:i') ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748b;">Tanggal Kembali</td>
                        <td style="padding: 8px 0; color: #0f172a; font-weight: 600;">{{ $loan->returned_at?->format('d M Y, H:i') ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748b;">Kondisi Pengembalian</td>
                        <td style="padding: 8px 0; color: #0f172a; font-weight: 600;">{{ $loan->condition_on_return ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748b;">Petugas</td>
                        <td style="padding: 8px 0; color: #0f172a; font-weight: 600;">{{ $loan->verifier->name ?? '-' }}</td>
                    </tr>
                </table>
                </div>

            <!-- Instructions -->
            <div style="background: #ecfdf5; border-left: 4px solid #10b981; border-radius: 4px; padding: 16px; margin: 20px 0;">
                <p style="margin: 0; color: #065f46; font-size: 14px; line-height: 1.5;">
                    <strong>📌 Catatan:</strong><br>
                    Barang Anda telah dikembalikan dan diterima oleh petugas. Kondisi barang
                    dikembalikan dengan baik. Jika terdapat ketidaksesuaian kondisi, silakan
                    menghubungi petugas terkait atau cek detail di website.
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

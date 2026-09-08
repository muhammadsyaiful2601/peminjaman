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

            <p style="color: #059669; margin: 0 0 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;">Notifikasi Pengembalian</p>
            <h2 style="color: #0f172a; margin: 0 0 16px 0; font-size: 22px;">Bukti Barang Diterima</h2>

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
                        <td style="padding: 8px 0; color: #64748b; width: 40%; vertical-align: top;">Barang yang dikembalikan</td>
                        <td style="padding: 8px 0; color: #0f172a; font-weight: 600;">
                            <ul style="padding-left: 18px; margin: 0;">
                                @foreach($loan->loanItems->isNotEmpty() ? $loan->loanItems : collect([(object) ['item' => $loan->item, 'qty' => $loan->qty]]) as $loanItem)
                                <li style="padding-bottom: 6px;">{{ $loanItem->item->name }} - {{ $loanItem->qty }} unit (Kode: {{ $loanItem->item->item_code }})</li>
                                @endforeach
                            </ul>
                        </td>
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

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Bebas Laboratorium</title>
    <style>
        @page {
            size: A4;
            margin: 18mm 16mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            color: #111827;
            font-size: 11px;
            line-height: 1.5;
            margin: 0;
            background: #f1f5f9;
        }
        .page {
            background: #ffffff;
            max-width: 210mm;
            margin: 16px auto;
            padding: 20mm 18mm;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            page-break-after: always;
        }
        .page:last-child {
            page-break-after: auto;
        }
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
        .signatures { display: table; width: 100%; margin-top: 30px; }
        .signature { display: table-cell; width: 50%; text-align: center; vertical-align: top; }
        .signature p { margin: 0; } .signature .space { height: 58px; }
        .signature .name { font-weight: bold; text-decoration: underline; }
        /* Tanda tangan digital menggantikan ruang kosong tanda tangan. */
        .signature .signature-image { display: block; margin: 4px auto 0; max-height: 52px; max-width: 170px; }

        @media print {
            body {
                background: none;
            }
            .page {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: 100%;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    @foreach($letters as $letterData)
        <div class="page">
            @include('pdf.partials.clearance-letter-body', $letterData)
        </div>
    @endforeach
</body>
</html>

<?php

// Verifikasi manual: render 4 template PDF lalu periksa font yang ter-embed.
// Jalankan: php verify_pdf_font.php  (dari folder backend/)

use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Support\Branding;
use App\Support\PdfFont;
use App\Support\QrPng;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Smalot\PdfParser\Parser;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Skrip ini hanya perlu tabel app_settings (tempat pilihan font disimpan),
// jadi Pakai SQLite in-memory agar tidak bergantung pada MySQL lokal.
config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => ':memory:',
]);

Schema::create('app_settings', function ($table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->timestamps();
});

$item = new Item;
$item->forceFill(['item_code' => 'ITM-001', 'name' => 'Proyektor Test', 'category' => 'Elektronik']);

$loan = new Loan;
$loan->forceFill([
    'uuid' => '11111111-2222-3333-4444-555555555555',
    'qr_token' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
    'loan_code' => 'PJM-2026-0001',
    'borrower_name' => 'Budi Tester',
    'borrower_email' => 'budi@example.com',
    'borrower_student_id' => '2211082001',
    'status' => 'borrowed',
    'qty' => 1,
    'created_at' => now(),
]);
$loan->setRelation('item', $item);
$loan->setRelation('loanItems', new Collection([
    (new LoanItem(['qty' => 1]))->setRelation('item', $item),
]));

$data = [
    'branding' => Branding::DEFAULTS,
    'loan' => $loan,
    'loans' => new Collection([$loan]),
    'item' => $item,
    'signatoryName' => 'Nofa Hendrayana, S.T.',
    'signatoryNip' => '197907182025211025',
    'signatorySignature' => null,
    'officerName' => 'Nofa Hendrayana, S.T.',
    'officerNip' => '197907182025211025',
    'officerSignature' => null,
    'purpose' => 'Persyaratan pengambilan bebas pustaka',
    'borrowedDate' => now()->subDays(5)->toDateString(),
    'returnDate' => now()->addDays(5)->toDateString(),
    'startDate' => null,
    'endDate' => null,
    'qrDataUri' => 'data:image/png;base64,'.base64_encode(QrPng::generate((string) $loan->uuid, 120)),
    'photoDataUri' => null,
    'eligible' => true,
    'letterNumber' => '001/BEBAS-LAB/PNP/I/2026',
    'letterDate' => now()->toDateString(),
    'laboratory' => 'Laboratorium Komputer',
    'borrower' => ['name' => 'Budi Tester', 'student_id' => '2211082001', 'email' => 'budi@example.com'],
    'totals' => ['total_loans' => 1, 'total_qty' => 1, 'outstanding_loans' => 0, 'outstanding_qty' => 0],
    'outstanding' => new Collection,
];

$views = [
    'pdf.clearance-letter',
    'pdf.official-loan',
    'pdf.loan-report',
    'pdf.loan-qr',
];

echo 'Font dipilih   : '.PdfFont::currentKey()."\n";
echo 'Font family    : '.PdfFont::family()."\n\n";

$outDir = __DIR__.'/storage/app/pdf-font-check';
if (! is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

// Setiap font diuji pada seluruh template: nama font yang benar-benar ter-embed
// dibandingkan dengan keluarga font yang dipilih. Nama font pada PDF bisa
// berbeda bentuk (mis. "DejaVuSans" tanpa spasi, atau "Times-Roman"), jadi
// pencocokan dilakukan dengan mengabaikan spasi dan tanda baca.
$normalize = static fn (string $name): string => strtolower((string) preg_replace('/[^a-z0-9]/i', '', $name));

$allOk = true;

// Kembalikan pilihan font awal supaya skrip tidak mengubah pengaturan.
$original = PdfFont::currentKey();

foreach (PdfFont::options() as $option) {
    if (! PdfFont::select($option['key'])) {
        echo sprintf("%-18s GAGAL: font tidak bisa dipilih\n", $option['key']);
        $allOk = false;

        continue;
    }

    $expected = $normalize($option['family']);
    $rows = [];

    foreach ($views as $view) {
        $content = Pdf::loadView($view, $data)->output();
        $file = $outDir.'/'.$option['key'].'-'.str_replace(['pdf.', '.'], '', $view).'.pdf';
        file_put_contents($file, $content);

        $fonts = (new Parser)->parseContent($content)->getFonts();
        $names = array_map(static fn ($f) => (string) $f->getName(), array_values($fonts));

        $matched = (bool) array_filter(
            $names,
            static fn (string $name): bool => str_contains($normalize($name), $expected)
        );
        $allOk = $allOk && $matched;

        $rows[] = sprintf(
            '%s %s',
            str_replace('pdf.', '', $view),
            $matched ? 'OK' : 'GAGAL('.implode('/', $names).')'
        );
    }

    printf("%-18s %-16s %s\n", $option['key'], $option['family'], implode('  ', $rows));
}

// Kembalikan pilihan awal (mis. saat diuji pada instalasi sungguhan).
PdfFont::select($original);

echo "\nBerkas PDF tersimpan di: {$outDir}\n";
echo $allOk
    ? "HASIL: semua font terpilih benar-benar ter-embed pada seluruh template.\n"
    : "HASIL: ada font yang tidak berhasil dirender.\n";

exit($allOk ? 0 : 1);

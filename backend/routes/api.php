<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\ClearanceController;
use App\Http\Controllers\Api\HybridController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\PdfFontController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SystemResetController;
use App\Http\Controllers\Api\TechnicianController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/branding', [BrandingController::class, 'show']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Public QR download (borrower doesn't need login, UUID acts as security token)
Route::get('/loans/qr/{uuid}/download', [LoanController::class, 'downloadQr']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationNotification']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/branding', [BrandingController::class, 'update']);
        // Hanya admin yang boleh mengganti font PDF.
        Route::post('/pdf-font', [PdfFontController::class, 'update']);
    });

    // Daftar font PDF dibaca semua petugas (mis. untuk pratinjau), sehingga
    // route ini berada di luar penjaga role admin.
    Route::get('/pdf-font', [PdfFontController::class, 'index']);

    // Items - all authenticated users can view
    Route::get('/items', [ItemController::class, 'index']);
    Route::get('/items/{item}', [ItemController::class, 'show']);

    // Items management - admin & assistant
    Route::middleware('role:admin,assistant')->group(function () {
        Route::post('/items', [ItemController::class, 'store']);
        Route::put('/items/{item}', [ItemController::class, 'update']);
        Route::delete('/items/{item}', [ItemController::class, 'destroy']);
    });

    // Loans - all staff can view
    Route::get('/loans', [LoanController::class, 'index']);
    Route::get('/loans/report/download', [LoanController::class, 'downloadReport']);
    Route::get('/loans/report/print', [LoanController::class, 'printReport']);

    // Bebas labor: data peminjaman per peminjam + kelayakan surat
    Route::get('/loans/clearance/borrowers', [ClearanceController::class, 'borrowers']);
    Route::get('/loans/clearance/detail', [ClearanceController::class, 'detail']);

    Route::get('/loans/{loan}', [LoanController::class, 'show']);
    Route::get('/loans/qr/{uuid}', [LoanController::class, 'showByUuid']);
    Route::get('/technicians', [TechnicianController::class, 'index']);
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/import/source', [StudentController::class, 'importSource']);
    // Loan management - admin & assistant only (petugas creates & verifies)
    Route::middleware('role:admin,assistant')->group(function () {
        // Webhook Google Apps Script: menulis balik Jabatan / Unit Kerja yang
        // diubah di aplikasi ke spreadsheet. Hanya petugas yang boleh
        // mengonfigurasi dan mengujinya.
        Route::post('/students/webhook', [StudentController::class, 'saveWebhook']);
        Route::post('/students/webhook/test', [StudentController::class, 'testWebhook']);
        Route::post('/loans', [LoanController::class, 'store']);
        Route::post('/loans/official/download', [LoanController::class, 'downloadOfficialLoan']);
        Route::post('/loans/clearance/download', [ClearanceController::class, 'download']);
        Route::post('/loans/clearance/print', [ClearanceController::class, 'printLetters']);

        Route::post('/loans/{loan}/return', [LoanController::class, 'returnItem']);
        Route::patch('/loans/{loan}/items/{item}/quantity', [LoanController::class, 'updateItemQuantity']);

        // Verify by code and upload PDF
        Route::get('/loans/code/{code}', [LoanController::class, 'showByCode']);
        Route::post('/loans/upload-pdf', [LoanController::class, 'uploadPdf']);
    });

    // User management - admin only
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/backups/status', [BackupController::class, 'status']);
        Route::post('/system-reset', [SystemResetController::class, 'reset']);
        Route::get('/backups/reset-download/{filename}', [SystemResetController::class, 'downloadBackup']);

        // Urutan penting: rute khusus didaftarkan sebelum /backups/{type}
        // agar "full" dan "restore" tidak tertangkap sebagai jenis backup.
        Route::post('/backups/full', [BackupController::class, 'download'])->defaults('type', 'full');
        Route::post('/backups/restore', [BackupController::class, 'restore']);
        Route::post('/backups/reminder/snooze', [BackupController::class, 'snoozeReminder']);
        Route::post('/backups/{type}', [BackupController::class, 'download']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/technicians', [TechnicianController::class, 'store']);
        Route::put('/technicians/{technician}', [TechnicianController::class, 'update']);
        Route::delete('/technicians/{technician}', [TechnicianController::class, 'destroy']);
    });

    Route::middleware('role:admin,assistant')->group(function () {
        // Data mahasiswa dapat dikelola seluruh petugas peminjaman.
        Route::post('/students', [StudentController::class, 'store']);
        Route::post('/students/import', [StudentController::class, 'import']);
        Route::post('/students/import/csv-url', [StudentController::class, 'importFromPublishedCsv']);
        Route::post('/students/import/source', [StudentController::class, 'saveImportSource']);
        Route::get('/students/import/template', [StudentController::class, 'downloadTemplate']);
        Route::put('/students/{student}', [StudentController::class, 'update']);
        Route::delete('/students/{student}', [StudentController::class, 'destroy']);
    });
});

// ---------------------------------------------------------------------
// Mode Hybrid: cermin data SQLite lokal ke MySQL hosting (popup gear
// di sudut kanan bawah aplikasi desktop). Dilindungi X-Desktop-Key
// karena server lokal bisa terekspos internet melalui tunnel.
// ---------------------------------------------------------------------
Route::middleware('desktop.key')->prefix('hybrid')->group(function () {
    Route::get('/status', [HybridController::class, 'status']);
    Route::post('/config', [HybridController::class, 'saveConfig']);
    Route::post('/test', [HybridController::class, 'testConfig']);
    Route::post('/migrate', [HybridController::class, 'migrate']);
    Route::post('/sync', [HybridController::class, 'syncNow']);
    Route::post('/sync-due', [HybridController::class, 'syncDue']);
    Route::post('/toggle', [HybridController::class, 'toggle']);
});

// ---------------------------------------------------------------------
// Branding untuk wizard konfigurasi awal aplikasi desktop: pengguna dapat
// mengatur nama & logo aplikasi sebelum admin login (first-run), sehingga
// tidak bisa memakai sesi Sanctum + role admin seperti POST /branding.
// Dilindungi X-Desktop-Key seperti endpoint hybrid.
// ---------------------------------------------------------------------
Route::middleware('desktop.key')->prefix('desktop')->group(function () {
    Route::post('/branding', [BrandingController::class, 'desktopUpdate']);

    // Impor mahasiswa dari CSV terpublikasi (Google Sheets) oleh aplikasi
    // desktop: main process Electron memanggil endpoint ini tanpa sesi
    // Sanctum, jadi dilindungi X-Desktop-Key seperti endpoint desktop lain.
    Route::post('/students/import-csv-url', [StudentController::class, 'importFromPublishedCsv']);
});

// ---------------------------------------------------------------------
// Endpoint khusus aplikasi desktop (Electron).
// Dilindungi secret key (X-Desktop-Key) karena bila server lokal diekspos
// ke internet melalui tunnel, endpoint ini tidak boleh dipakai orang lain.
// ---------------------------------------------------------------------
Route::post('/desktop/mail-test', function (Request $request) {
    $expectedKey = (string) config('app.desktop_key');
    $providedKey = (string) $request->headers->get('X-Desktop-Key');

    if ($expectedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
        abort(404);
    }

    $data = $request->validate([
        'to' => ['required', 'email'],
    ]);

    if (config('mail.default') !== 'smtp') {
        return response()->json([
            'ok' => false,
            'message' => 'Mailer aktif bukan SMTP. Isi konfigurasi email pada wizard konfigurasi awal.',
        ]);
    }

    try {
        Mail::raw(
            'Ini adalah email percobaan dari aplikasi desktop Peminjaman Barang PNP. Konfigurasi email Anda sudah benar.',
            function ($message) use ($data) {
                $message->to($data['to'])->subject('Email Percobaan — Peminjaman Barang PNP');
            },
        );

        return response()->json([
            'ok' => true,
            'message' => 'Email percobaan berhasil dikirim ke '.$data['to'].'. Silakan cek inbox atau folder spam.',
        ]);
    } catch (Throwable $e) {
        Log::warning('Desktop mail test gagal: '.$e->getMessage());

        return response()->json([
            'ok' => false,
            'message' => 'Gagal mengirim email: '.$e->getMessage(),
        ]);
    }
});

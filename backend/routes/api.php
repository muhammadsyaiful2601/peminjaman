<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TechnicianController;
use Illuminate\Support\Facades\Route;

// Public routes
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
    Route::get('/loans/{loan}', [LoanController::class, 'show']);
    Route::get('/loans/qr/{uuid}', [LoanController::class, 'showByUuid']);
    Route::get('/technicians', [TechnicianController::class, 'index']);

    // Loan management - admin & assistant only (petugas creates & verifies)
    Route::middleware('role:admin,assistant')->group(function () {
        Route::post('/loans', [LoanController::class, 'store']);
        Route::post('/loans/official/download', [LoanController::class, 'downloadOfficialLoan']);
        Route::post('/loans/{loan}/return', [LoanController::class, 'returnItem']);

        // Verify by code and upload PDF
        Route::get('/loans/code/{code}', [LoanController::class, 'showByCode']);
        Route::post('/loans/upload-pdf', [LoanController::class, 'uploadPdf']);
    });

    // User management - admin only
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/technicians', [TechnicianController::class, 'store']);
        Route::put('/technicians/{technician}', [TechnicianController::class, 'update']);
        Route::delete('/technicians/{technician}', [TechnicianController::class, 'destroy']);
    });
});

// ---------------------------------------------------------------------
// Endpoint khusus aplikasi desktop (Electron).
// Aman tanpa auth karena server hanya berjalan di 127.0.0.1 pada komputer
// yang sama dan hanya diakses oleh jendela aplikasi sendiri.
// ---------------------------------------------------------------------
Route::post('/desktop/mail-test', function (Request $request) {
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

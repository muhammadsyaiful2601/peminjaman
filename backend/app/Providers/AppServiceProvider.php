<?php

namespace App\Providers;

use App\Support\Hybrid;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Mode hybrid: daftarkan koneksi MySQL hosting bila konfigurasi ada.
        // Koneksi bernama "hybrid_mysql" hanya dipakai sinkronisasi/migrasi;
        // database utama aplikasi tetap SQLite lokal.
        try {
            Hybrid::applyConnection();
        } catch (\Throwable) {
            // Abaikan — storage belum siap (mis. saat composer/package discovery).
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return rtrim(config('app.frontend_url'), '/').'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HybridSyncService;
use App\Support\Hybrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HybridController extends Controller
{
    public function __construct(private HybridSyncService $syncService)
    {
    }

    /** Status mode hybrid (dipakai popup gear di aplikasi desktop). */
    public function status(): JsonResponse
    {
        $config = Hybrid::readConfig();

        return response()->json([
            'configured' => Hybrid::isConfigured(),
            'enabled' => $config['enabled'] ?? false,
            'due' => Hybrid::isDue(),
            'interval_days' => Hybrid::INTERVAL_DAYS,
            'host' => $config['host'] ?? '',
            'port' => $config['port'] ?? 3306,
            'database' => $config['database'] ?? '',
            'username' => $config['username'] ?? '',
            'has_password' => $config !== null && $config['password'] !== '',
            'last_sync_at' => $config['last_sync_at'] ?? null,
            'last_sync_ok' => $config['last_sync_ok'] ?? null,
            'last_sync_message' => $config['last_sync_message'] ?? null,
        ]);
    }

    /** Simpan konfigurasi hosting, lalu otomatis tes koneksi. */
    public function saveConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $current = Hybrid::readConfig();
        $password = array_key_exists('password', $data) && $data['password'] !== null && $data['password'] !== ''
            ? $data['password']
            : ($current['password'] ?? '');

        $config = [
            'enabled' => (bool) ($data['enabled'] ?? $current['enabled'] ?? false),
            'host' => trim($data['host']),
            'port' => (int) ($data['port'] ?? 3306),
            'database' => trim($data['database']),
            'username' => trim($data['username']),
            'password' => $password,
            'last_sync_at' => $current['last_sync_at'] ?? null,
            'last_sync_ok' => $current['last_sync_ok'] ?? null,
            'last_sync_message' => $current['last_sync_message'] ?? null,
        ];

        Hybrid::writeConfig($config);

        $test = $this->syncService->testConnection();

        return response()->json([
            'ok' => true,
            'test_ok' => $test['ok'],
            'message' => $test['ok']
                ? 'Konfigurasi tersimpan. '.$test['message']
                : 'Konfigurasi tersimpan, tetapi '.$test['message'],
        ]);
    }

    /** Tes koneksi tanpa menyimpan (menggunakan data form). */
    public function testConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $test = $this->syncService->testConnection([
            'enabled' => true,
            'host' => trim($data['host']),
            'port' => (int) ($data['port'] ?? 3306),
            'database' => trim($data['database']),
            'username' => trim($data['username']),
            'password' => (string) ($data['password'] ?? ''),
            'last_sync_at' => null,
            'last_sync_ok' => null,
            'last_sync_message' => null,
        ]);

        return response()->json([
            'ok' => $test['ok'],
            'message' => $test['message'],
        ]);
    }

    /** Migrasi awal: buat skema + salin seluruh data lokal ke hosting. */
    public function migrate(): JsonResponse
    {
        @set_time_limit(300);

        $result = $this->syncService->migrateToRemote();

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'summary' => $result['summary'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    /** Sinkronisasi dua arah manual. */
    public function syncNow(): JsonResponse
    {
        @set_time_limit(300);

        $result = $this->syncService->syncBidirectional();

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'summary' => $result['summary'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    /** Dipicu Electron berkala: sinkron bila mingguan jatuh tempo. */
    public function syncDue(): JsonResponse
    {
        @set_time_limit(300);

        $result = $this->syncService->runIfDue();

        return response()->json($result);
    }

    /** Aktifkan/nonaktifkan sinkronisasi otomatis mingguan. */
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        if (! Hybrid::isConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'Isi konfigurasi hosting terlebih dahulu.',
            ], 422);
        }

        $config = Hybrid::readConfig() ?? [];
        $config['enabled'] = (bool) $data['enabled'];
        Hybrid::writeConfig($config);

        return response()->json([
            'ok' => true,
            'enabled' => $config['enabled'],
            'message' => $config['enabled']
                ? 'Sinkronisasi otomatis mingguan diaktifkan.'
                : 'Sinkronisasi otomatis dinonaktifkan.',
        ]);
    }
}

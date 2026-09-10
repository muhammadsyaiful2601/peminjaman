<?php

namespace App\Console\Commands;

use App\Services\HybridSyncService;
use Illuminate\Console\Command;

class HybridSyncCommand extends Command
{
    protected $signature = 'hybrid:sync
        {--due : Hanya jalankan bila sinkronisasi mingguan jatuh tempo}
        {--force : Jalankan meskipun sinkronisasi otomatis nonaktif}';

    protected $description = 'Sinkronisasi dua arah SQLite lokal <-> MySQL hosting (mode hybrid)';

    public function handle(HybridSyncService $service): int
    {
        if ($this->option('due')) {
            $result = $service->runIfDue();
            if (! $result['ran']) {
                $this->line($result['message']);

                return self::SUCCESS;
            }

            $this->info($result['message']);

            return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $result = $service->syncBidirectional();
        $this->line($result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}

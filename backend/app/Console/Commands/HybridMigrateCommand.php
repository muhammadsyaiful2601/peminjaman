<?php

namespace App\Console\Commands;

use App\Services\HybridSyncService;
use Illuminate\Console\Command;

class HybridMigrateCommand extends Command
{
    protected $signature = 'hybrid:migrate';

    protected $description = 'Buat skema MySQL hosting dan salin seluruh data lokal (migrasi awal mode hybrid)';

    public function handle(HybridSyncService $service): int
    {
        $this->info('Menjalankan migrasi awal ke hosting...');

        $result = $service->migrateToRemote();

        $this->line($result['message']);
        if (! empty($result['summary'])) {
            foreach ($result['summary'] as $table => $count) {
                $this->line(sprintf('  - %-12s %s', $table, $count));
            }
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}

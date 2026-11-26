<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'grisapay:backup {--label=auto : label berkas} {--keep=14 : jumlah cadangan "auto" yang disimpan}';

    protected $description = 'Buat cadangan database (pg_dump) dan hapus cadangan otomatis yang lama';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            $r = $backup->create((string) $this->option('label'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Cadangan dibuat: '.$r['name'].' ('.number_format($r['size'] / 1048576, 2).' MB)');

        if ($this->option('label') === 'auto' && ($n = $backup->prune((int) $this->option('keep'))) > 0) {
            $this->line("Cadangan lama dihapus: {$n}");
        }

        return self::SUCCESS;
    }
}

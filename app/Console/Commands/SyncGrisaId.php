<?php

namespace App\Console\Commands;

use App\Models\SyncRun;
use App\Services\GrisaId\GrisaIdException;
use App\Services\GrisaId\GrisaIdSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class SyncGrisaId extends Command
{
    protected $signature = 'grisapay:sync-grisaid {--trigger=manual : manual atau scheduled}';

    protected $description = 'Salin data master (tahun ajaran, kelas, siswa, wali) dari GrisaID ke database lokal';

    public function handle(GrisaIdSync $sync): int
    {
        // Satu sinkronisasi pada satu waktu.
        $lock = Cache::lock('grisapay.sync-grisaid', 900);

        if (! $lock->get()) {
            $this->warn('Sinkronisasi sedang berjalan. Coba lagi beberapa menit lagi.');

            return self::FAILURE;
        }

        $run = SyncRun::create([
            'status' => 'running',
            'trigger' => $this->option('trigger') === 'scheduled' ? 'scheduled' : 'manual',
            'started_at' => now(),
        ]);

        $started = microtime(true);

        try {
            $stats = $sync->run();
            $stats['durasi_detik'] = (int) round(microtime(true) - $started);

            $run->update(['status' => 'success', 'finished_at' => now(), 'stats' => $stats]);

            $this->info('Sinkronisasi selesai.');
            $this->table(['Data', 'Jumlah'], collect($stats)->map(fn ($value, $key) => [str_replace('_', ' ', $key), $value])->values()->all());

            return self::SUCCESS;
        } catch (GrisaIdException $e) {
            $message = $e->getMessage();
        } catch (Throwable $e) {
            report($e);
            $message = 'Kesalahan tak terduga saat sinkronisasi: '.Str::limit($e->getMessage(), 200);
        } finally {
            $lock->release();
        }

        $run->update(['status' => 'failed', 'finished_at' => now(), 'error' => Str::limit($message, 500)]);
        $this->error('[GAGAL] '.$message);

        return self::FAILURE;
    }
}

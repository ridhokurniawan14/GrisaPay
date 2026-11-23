<?php

namespace App\Console\Commands;

use App\Services\GrisaId\GrisaIdClient;
use App\Services\GrisaId\GrisaIdException;
use Illuminate\Console\Command;

class GrisaIdCheck extends Command
{
    protected $signature = 'grisapay:grisaid-check';

    protected $description = 'Uji koneksi GrisaPay ke API GrisaID (token, tahun ajaran aktif, kelas, jumlah siswa)';

    public function handle(GrisaIdClient $client): int
    {
        $this->line('GrisaID: ' . rtrim((string) config('grisaid.url'), '/'));

        try {
            $client->ping();
            $this->info('[OK] Token diterima dan API merespons');

            $code = (string) ($client->currentAcademicYear()['code'] ?? '-');
            $this->info("[OK] Tahun ajaran aktif: {$code}");

            $this->info('[OK] Kelas pada tahun ajaran aktif: ' . count($client->classes($code)));
            $this->info('[OK] Siswa berstatus aktif: ' . $client->studentsTotal(['status' => 'aktif']));
        } catch (GrisaIdException $e) {
            $this->error('[GAGAL] ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

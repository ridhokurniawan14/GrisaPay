<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Cadangan & pemulihan database PostgreSQL lewat pg_dump / pg_restore (format custom, terkompresi). */
class DatabaseBackup
{
    private const NAME = '/^grisapay_\d{8}_\d{6}_[a-z0-9-]+\.dump$/';

    public function dir(): string
    {
        $dir = storage_path('app/backups');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function bin(string $tool): string
    {
        $folder = config('grisapay.pg_bin');
        $exe = PHP_OS_FAMILY === 'Windows' ? $tool.'.exe' : $tool;

        return $folder ? rtrim((string) $folder, '\\/').DIRECTORY_SEPARATOR.$exe : $exe;
    }

    /** @return array<string, mixed> */
    private function conn(): array
    {
        return config('database.connections.'.config('database.default'));
    }

    /** @param list<string> $args */
    private function process(string $tool, array $args, int $timeout = 900): Process
    {
        $c = $this->conn();
        $cmd = array_merge([$this->bin($tool)], $args);
        $p = new Process($cmd, null, ['PGPASSWORD' => (string) ($c['password'] ?? ''), 'PGCLIENTENCODING' => 'UTF8']);
        $p->setTimeout($timeout);

        return $p;
    }

    /** @return list<string> */
    private function connArgs(): array
    {
        $c = $this->conn();

        return ['-h', (string) ($c['host'] ?? '127.0.0.1'), '-p', (string) ($c['port'] ?? 5432), '-U', (string) ($c['username'] ?? 'postgres')];
    }

    /** @return array{ok: bool, version: ?string, error: ?string} */
    public function check(): array
    {
        try {
            $p = $this->process('pg_dump', ['--version'], 15);
            $p->run();

            if (! $p->isSuccessful()) {
                return ['ok' => false, 'version' => null, 'error' => trim($p->getErrorOutput()) ?: 'pg_dump gagal dijalankan.'];
            }

            return ['ok' => true, 'version' => trim($p->getOutput()), 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'version' => null, 'error' => 'pg_dump tidak ditemukan. Isi PG_BIN_PATH di .env dengan folder bin PostgreSQL.'];
        }
    }

    /** @return array{name: string, size: int} */
    public function create(string $label = 'manual'): array
    {
        set_time_limit(0);
        $label = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-') ?: 'manual';
        $name = 'grisapay_'.now()->format('Ymd_His').'_'.$label.'.dump';
        $path = $this->dir().DIRECTORY_SEPARATOR.$name;

        $p = $this->process('pg_dump', [...$this->connArgs(), '-Fc', '--no-owner', '--no-privileges', '-f', $path, (string) $this->conn()['database']]);
        $p->run();

        if (! $p->isSuccessful() || ! is_file($path) || filesize($path) === 0) {
            @unlink($path);

            throw new RuntimeException('Cadangan gagal dibuat: '.($this->clean($p->getErrorOutput()) ?: 'pg_dump tidak menghasilkan berkas.'));
        }

        return ['name' => $name, 'size' => (int) filesize($path)];
    }

    /** @return list<array{name: string, size: int, at: \Illuminate\Support\Carbon, label: string}> */
    public function list(): array
    {
        $out = [];

        foreach (glob($this->dir().DIRECTORY_SEPARATOR.'grisapay_*.dump') ?: [] as $f) {
            $n = basename($f);

            if (! preg_match(self::NAME, $n)) {
                continue;
            }

            $out[] = ['name' => $n, 'size' => (int) filesize($f), 'at' => \Illuminate\Support\Carbon::createFromTimestamp(filemtime($f), config('app.timezone')), 'label' => (string) preg_replace('/^grisapay_\d{8}_\d{6}_|\.dump$/', '', $n)];
        }

        usort($out, fn ($a, $b) => $b['at']->timestamp <=> $a['at']->timestamp);

        return $out;
    }

    public function path(string $name): string
    {
        if (! preg_match(self::NAME, $name) || ! is_file($this->dir().DIRECTORY_SEPARATOR.$name)) {
            throw new RuntimeException('Berkas cadangan tidak ditemukan.');
        }

        return $this->dir().DIRECTORY_SEPARATOR.$name;
    }

    public function delete(string $name): void
    {
        @unlink($this->path($name));
    }

    /** Simpan hanya $keep cadangan terbaru (khusus yang berlabel "auto"). */
    public function prune(int $keep): int
    {
        $auto = array_values(array_filter($this->list(), fn ($b) => $b['label'] === 'auto'));
        $n = 0;

        foreach (array_slice($auto, max(1, $keep)) as $b) {
            @unlink($this->dir().DIRECTORY_SEPARATOR.$b['name']);
            $n++;
        }

        return $n;
    }

    /** Pulihkan database dari berkas. Satu transaksi: bila gagal, database tidak berubah. */
    public function restore(string $name): void
    {
        set_time_limit(0);
        $path = $this->path($name);

        $v = $this->process('pg_restore', ['-l', $path], 60);
        $v->run();

        if (! $v->isSuccessful()) {
            throw new RuntimeException('Berkas cadangan tidak valid: '.($this->clean($v->getErrorOutput()) ?: 'tidak dapat dibaca.'));
        }

        $this->create('sebelum-restore');

        $p = $this->process('pg_restore', [...$this->connArgs(), '-d', (string) $this->conn()['database'], '--clean', '--if-exists', '--no-owner', '--no-privileges', '--single-transaction', '--exit-on-error', $path], 1800);
        $p->run();

        if (! $p->isSuccessful()) {
            throw new RuntimeException('Pemulihan gagal (database tidak diubah): '.($this->clean($p->getErrorOutput()) ?: 'pg_restore error.'));
        }
    }

    private function clean(string $s): string
    {
        return \Illuminate\Support\Str::limit(trim(str_replace((string) ($this->conn()['password'] ?? "\0"), '***', $s)), 300);
    }
}

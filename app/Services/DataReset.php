<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Reset data transaksi GrisaPay (pembayaran, kas, tagihan) agar mulai dari nol.
 * Data induk (siswa, kelas, tahun ajaran, tarif, pengguna, hak akses) TIDAK pernah disentuh.
 */
class DataReset
{
    public const MODE_PAYMENTS = 'payments';

    public const MODE_ALL = 'all';

    /** Tabel yang tidak boleh ikut terhapus apa pun yang terjadi. */
    private const PROTECTED = [
        'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        'students', 'student_enrollments', 'student_guardians', 'academic_years', 'classrooms',
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'failed_jobs', 'activity_log',
    ];

    /** @return list<string> tabel akar yang benar-benar ada */
    private function roots(string $mode, bool $withDiscounts): array
    {
        $roots = ['payments', 'cash_shifts'];

        if ($mode === self::MODE_ALL) {
            $roots = array_merge($roots, ['bills', 'arrear_imports']);

            if ($withDiscounts) {
                $roots[] = 'discounts';
            }
        }

        return array_values(array_filter($roots, fn (string $t): bool => Schema::hasTable($t)));
    }

    /** Tabel yang bergantung (FK) pada $table, rekursif. @return list<string> */
    private function dependents(string $table): array
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE deps(t) AS (
                SELECT c.conrelid::regclass::text FROM pg_constraint c WHERE c.contype = 'f' AND c.confrelid = ?::regclass
                UNION
                SELECT c.conrelid::regclass::text FROM pg_constraint c JOIN deps d ON c.confrelid = d.t::regclass WHERE c.contype = 'f'
            )
            SELECT DISTINCT t FROM deps
            SQL, [$table]);

        return array_map(fn ($r): string => trim((string) $r->t, '"'), $rows);
    }

    /** @return list<array{table: string, rows: int}> */
    public function plan(string $mode, bool $withDiscounts = false): array
    {
        $tables = [];

        foreach ($this->roots($mode, $withDiscounts) as $root) {
            $tables[] = $root;
            array_push($tables, ...$this->dependents($root));
        }

        $tables = array_values(array_unique(array_map(fn ($t) => preg_replace('/^public\./', '', $t), $tables)));
        $blocked = array_values(array_intersect($tables, self::PROTECTED));

        if ($blocked !== []) {
            throw new RuntimeException('Reset dibatalkan: akan ikut menghapus tabel yang dilindungi ('.implode(', ', $blocked).').');
        }

        return array_map(fn (string $t): array => ['table' => $t, 'rows' => (int) DB::table($t)->count()], $tables);
    }

    /** @return list<array{table: string, rows: int}> yang dihapus */
    public function run(string $mode, bool $withDiscounts = false): array
    {
        $plan = $this->plan($mode, $withDiscounts);

        if ($plan === []) {
            return [];
        }

        $list = implode(', ', array_map(fn (array $p): string => '"'.$p['table'].'"', $plan));
        DB::statement("TRUNCATE TABLE {$list} RESTART IDENTITY CASCADE");

        return $plan;
    }
}

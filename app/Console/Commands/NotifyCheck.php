<?php

namespace App\Console\Commands;

use App\Enums\PermissionName;
use App\Support\PendingNotifier;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Diagnosa lonceng: siapa penerima, apa isi tabel notifications, dan uji kirim tanpa menelan error. */
class NotifyCheck extends Command
{
    protected $signature = 'grisapay:notify-check {--send : Kirim notifikasi tes ke semua penerima verifikasi}';

    protected $description = 'Periksa kenapa notifikasi lonceng tidak muncul';

    public function handle(): int
    {
        foreach ([PermissionName::PaymentsVerify, PermissionName::PaymentsVoidApprove, PermissionName::DiscountsApprove] as $perm) {
            $users = PendingNotifier::recipients($perm);
            $this->line($perm->value.' → '.($users->isEmpty() ? '(TIDAK ADA PENERIMA)' : $users->map(fn ($u) => "{$u->name} (id {$u->id})")->implode(', ')));
        }

        $this->newLine();
        $this->line('Isi tabel notifications:');
        $rows = DatabaseNotification::query()->latest()->limit(10)->get();

        if ($rows->isEmpty()) {
            $this->warn('Kosong. Notifikasi tidak pernah tersimpan.');
        }

        foreach ($rows as $n) {
            $this->line(sprintf(' #%s untuk user %s · %s · %s · %s', substr($n->id, 0, 8), $n->notifiable_id, $n->data['title'] ?? '-', $n->read_at ? 'dibaca' : 'BELUM dibaca', $n->created_at));
        }

        if ($this->option('send')) {
            $users = PendingNotifier::recipients(PermissionName::PaymentsVerify);
            Notification::make()->title('Tes lonceng')->body('Jika ini muncul di lonceng, notifikasi berfungsi.')->sendToDatabase($users);
            $this->info('Notifikasi tes dikirim ke '.$users->count().' pengguna. Muat ulang halaman lalu lihat lonceng.');
        }

        $this->newLine();
        $this->line('QUEUE_CONNECTION = '.config('queue.default'));

        foreach (['jobs' => 'Antrean (jobs) menunggu', 'failed_jobs' => 'Antrean gagal (failed_jobs)', 'notifications' => 'Total notifikasi tersimpan'] as $table => $label) {
            try {
                $this->line($label.': '.DB::table($table)->count());
            } catch (Throwable) {
                $this->line($label.': (tabel '.$table.' tidak ada)');
            }
        }

        return self::SUCCESS;
    }
}

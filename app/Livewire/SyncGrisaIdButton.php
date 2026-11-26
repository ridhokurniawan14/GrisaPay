<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Models\SyncRun;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

/** Tombol "Sinkron GrisaID" di header panel, lengkap dengan waktu sinkron terakhir agar tidak terlupa. */
class SyncGrisaIdButton extends Component
{
    public function sync(): void
    {
        abort_unless(auth()->user()?->can(PermissionName::SyncRun->value), 403);

        set_time_limit(300);

        $before = (int) SyncRun::query()->max('id');
        Artisan::call('grisapay:sync-grisaid', ['--trigger' => 'manual']);
        $run = SyncRun::query()->where('id', '>', $before)->latest('id')->first();

        if ($run === null) {
            Notification::make()->warning()->title('Sinkronisasi sedang berjalan')
                ->body('Coba lagi beberapa menit lagi.')->duration(8000)->send();

            return;
        }

        if ($run->status === 'success') {
            $s = $run->stats ?? [];

            Notification::make()->success()
                ->title('Sinkronisasi GrisaID selesai')
                ->body(sprintf('Siswa %s · Kelas %s · Penempatan %s · Wali %s',
                    $s['siswa'] ?? '-', $s['kelas'] ?? '-', $s['penempatan_kelas'] ?? '-', $s['wali'] ?? '-'))
                ->duration(8000)->send();
        } else {
            Notification::make()->danger()
                ->title('Sinkronisasi GrisaID gagal')
                ->body((string) $run->error)
                ->duration(12000)->send();
        }
    }

    public function render()
    {
        $last = SyncRun::query()->where('status', '!=', 'running')->latest('id')->first();
        $lastSuccess = SyncRun::query()->where('status', 'success')->latest('id')->first();

        // Peringatan halus: belum pernah sinkron, gagal terakhir, atau sudah lebih dari 24 jam.
        $state = match (true) {
            $lastSuccess === null => 'never',
            $last?->status === 'failed' => 'failed',
            $lastSuccess->finished_at->lt(now()->subDay()) => 'stale',
            default => 'ok',
        };

        return view('livewire.sync-grisaid-button', [
            'allowed' => (bool) auth()->user()?->can(PermissionName::SyncRun->value),
            'state' => $state,
            'lastSuccess' => $lastSuccess,
        ]);
    }
}

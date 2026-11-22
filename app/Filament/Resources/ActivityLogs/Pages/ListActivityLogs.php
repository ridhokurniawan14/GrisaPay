<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Spatie\Activitylog\Models\Activity;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    private const PERMISSION = 'activity-log.prune';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prune')
                ->label('Bersihkan log')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn(): bool => auth()->user()?->can(self::PERMISSION) ?? false)
                ->modalHeading('Bersihkan log aktivitas')
                ->modalDescription('Log yang lebih lama dari jumlah yang dipertahankan akan DIHAPUS PERMANEN dan tidak bisa dikembalikan. Penghapusan ini sendiri akan dicatat di log.')
                ->modalSubmitActionLabel('Ya, hapus log lama')
                ->schema([
                    TextInput::make('keep')
                        ->label('Jumlah log terbaru yang dipertahankan')
                        ->numeric()
                        ->default(500)
                        ->minValue(100)
                        ->maxValue(1000000)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->can(self::PERMISSION) ?? false, 403);

                    $keep = max(100, (int) $data['keep']);

                    // ID log ke-N dari yang terbaru: semua yang ID-nya lebih kecil dihapus.
                    $threshold = Activity::query()->orderByDesc('id')->offset($keep - 1)->limit(1)->value('id');

                    if ($threshold === null) {
                        Notification::make()
                            ->warning()
                            ->title('Tidak ada yang dihapus')
                            ->body("Jumlah log belum melebihi {$keep}.")
                            ->send();

                        return;
                    }

                    $deleted = Activity::query()->where('id', '<', $threshold)->delete();

                    activity('log')
                        ->causedBy(auth()->user())
                        ->withProperties(['dihapus' => $deleted, 'disisakan' => $keep])
                        ->log('log-dibersihkan');

                    Notification::make()
                        ->success()
                        ->title("{$deleted} log lama dihapus")
                        ->body("{$keep} log terbaru dipertahankan.")
                        ->send();
                }),
        ];
    }
}

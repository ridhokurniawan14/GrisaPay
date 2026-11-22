<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\User;
use App\Support\ActivityDetail;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogsTable
{
    private const LOG_NAMES = [
        'auth' => 'Masuk / keluar',
        'user' => 'Pengguna',
        'role' => 'Peran & hak akses',
        'institution-setting' => 'Pengaturan lembaga',
        'log' => 'Pemeliharaan log',
    ];

    private const ACTIONS = [
        'created' => 'Dibuat',
        'updated' => 'Diubah',
        'deleted' => 'Dihapus',
        'restored' => 'Dipulihkan',
        'login' => 'Masuk',
        'logout' => 'Keluar',
        'login-gagal' => 'Gagal masuk',
        'peran-diubah' => 'Peran diubah',
        'hak-akses-diubah' => 'Hak akses diubah',
        'password-diubah' => 'Password diubah',
        'log-dibersihkan' => 'Log dibersihkan',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Oleh')
                    ->placeholder('Sistem')
                    ->searchable(),
                TextColumn::make('log_name')
                    ->label('Data')
                    ->badge()
                    ->formatStateUsing(fn($state): string => self::LOG_NAMES[$state] ?? (string) $state),
                TextColumn::make('description')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn($state): string => self::ACTIONS[$state] ?? (string) $state)
                    ->color(fn($state): string => match ($state) {
                        'created', 'login' => 'success',
                        'updated', 'peran-diubah', 'hak-akses-diubah', 'password-diubah' => 'warning',
                        'deleted', 'login-gagal', 'log-dibersihkan' => 'danger',
                        'restored' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('subject_label')
                    ->label('Objek')
                    ->state(fn(Activity $record): string => self::subjectLabel($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';

                        $userIds = User::query()
                            ->where(fn(Builder $user) => $user->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like))
                            ->limit(200)
                            ->pluck('id');

                        return $query->where(function (Builder $q) use ($like, $userIds): void {
                            $q->whereRaw('attribute_changes::text ilike ?', [$like])
                                ->orWhereRaw('properties::text ilike ?', [$like])
                                ->orWhere(fn(Builder $s) => $s->where('subject_type', User::class)->whereIn('subject_id', $userIds));
                        });
                    }),
                TextColumn::make('changes')
                    ->label('Ringkasan')
                    ->state(fn(Activity $record): array => ActivityDetail::summary($record->attribute_changes, $record->properties))
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Jenis data')
                    ->options(self::LOG_NAMES),
                SelectFilter::make('description')
                    ->label('Aksi')
                    ->options(self::ACTIONS),
                SelectFilter::make('period')
                    ->label('Periode')
                    ->options(['today' => 'Hari ini', '7d' => '7 hari terakhir', '30d' => '30 hari terakhir'])
                    ->query(fn(Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'today' => $query->where('created_at', '>=', now()->startOfDay()),
                        '7d' => $query->where('created_at', '>=', now()->subDays(7)),
                        '30d' => $query->where('created_at', '>=', now()->subDays(30)),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->slideOver()
                    ->modalHeading('Detail aktivitas')
                    ->modalContent(fn(Activity $record) => view('filament.activity-detail', [
                        'meta' => [
                            'Waktu' => $record->created_at?->format('d M Y H:i:s'),
                            'Oleh' => $record->causer?->name ?? 'Sistem',
                            'Data' => self::LOG_NAMES[$record->log_name] ?? (string) $record->log_name,
                            'Aksi' => self::ACTIONS[$record->description] ?? (string) $record->description,
                            'Objek' => self::subjectLabel($record),
                        ],
                        'sections' => ActivityDetail::sections($record->attribute_changes, $record->properties),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ]);
    }

    private static function subjectLabel(Activity $record): string
    {
        $subject = $record->subject;

        if ($subject !== null) {
            return (string) ($subject->name ?? $subject->email ?? ('#' . $record->subject_id));
        }

        if ($record->subject_type === null) {
            return '-';
        }

        // Data sudah dihapus: ambil nama dari catatan perubahan atau properti jika ada.
        $changes = $record->attribute_changes;
        $changes = is_object($changes) && method_exists($changes, 'toArray') ? $changes->toArray() : (array) $changes;

        $name = data_get($changes, 'attributes.name')
            ?? data_get($changes, 'old.name')
            ?? data_get($record->properties, 'peran');

        return filled($name) ? (string) $name : class_basename($record->subject_type) . ' #' . $record->subject_id;
    }
}

<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Enums\PermissionName;
use App\Filament\Pages\Kasir;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentVoid;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    /** Bila diisi (?siswa=ID), daftar hanya menampilkan riwayat pembayaran satu siswa. */
    #[Url(as: 'siswa')]
    public ?string $studentId = null;

    public function getTabs(): array
    {
        return [
            'hari-ini' => Tab::make('Hari ini')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('paid_at', today())),
            'menunggu' => Tab::make('Menunggu verifikasi')
                ->badge(Payment::query()->where('status', Payment::PENDING)->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Payment::PENDING)),
            'void' => Tab::make('Void menunggu')
                ->badge(PaymentVoid::query()->where('status', PaymentVoid::REQUESTED)->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('voids', fn ($q) => $q->where('status', PaymentVoid::REQUESTED))),
            'semua' => Tab::make('Semua'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return filled($this->studentId) ? 'semua' : 'hari-ini';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (blank($this->studentId)) {
            return null;
        }

        $student = Student::query()->find((int) $this->studentId);

        return $student ? 'Riwayat pembayaran: '.$student->name.' · NISN '.($student->nisn ?? '-') : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('allStudents')
                ->label('Tampilkan semua siswa')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->url(fn (): string => PaymentResource::getUrl('index'))
                ->visible(fn (): bool => filled($this->studentId)),
            Action::make('kasir')
                ->label('Buka Kasir')
                ->icon('heroicon-o-calculator')
                ->url(fn (): string => Kasir::getUrl())
                ->visible(fn (): bool => (bool) auth()->user()?->can(PermissionName::PaymentsCreate->value)),
        ];
    }
}

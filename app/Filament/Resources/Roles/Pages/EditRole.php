<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Support\AccessAudit;
use App\Support\PermissionGroups;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /** @var list<string> */
    protected array $permissionsBefore = [];

    /** @var list<string>|null NULL = form izin tidak terkirim (mis. Super Admin), izin tidak diubah */
    protected ?array $selectedPermissions = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn(): bool => ! RoleResource::canDelete($this->getRecord())),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return RoleResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return "Peran {$this->getRecord()->name} berhasil diperbarui";
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Isi daftar centang tiap kelompok dari izin yang sudah dimiliki peran.
        return $data + PermissionGroups::stateFromGranted($this->getRecord()->permissions()->pluck('name')->all());
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Kolom per-kelompok bukan kolom tabel peran: dipisahkan dulu, disimpan lewat relasi sesudahnya.
        [$this->selectedPermissions, $rest] = PermissionGroups::extract($data);

        return $rest;
    }

    protected function beforeSave(): void
    {
        $this->permissionsBefore = $this->getRecord()->permissions()->pluck('name')->all();
    }

    protected function afterSave(): void
    {
        if ($this->selectedPermissions === null) {
            return;
        }

        // Izin di luar katalog (jika ada) dipertahankan; yang diatur dari form hanyalah izin katalog.
        $preserved = array_values(array_diff($this->permissionsBefore, PermissionGroups::catalog()));
        $after = array_values(array_unique([...$this->selectedPermissions, ...$preserved]));

        $this->getRecord()->syncPermissions($after);

        // Cache izin dibuang agar hak akses langsung berlaku.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AccessAudit::rolePermissionsChanged($this->getRecord(), $this->permissionsBefore, $after);
    }
}

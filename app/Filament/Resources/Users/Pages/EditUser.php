<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\AccessAudit;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    // Sengaja tanpa tombol hapus: akun cukup dinonaktifkan agar jejak audit tetap utuh.

    /** @var list<string> */
    protected array $rolesBefore = [];

    protected bool $passwordChanged = false;

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return "Pengguna {$this->getRecord()->name} berhasil diperbarui";
    }

    protected function beforeSave(): void
    {
        $this->rolesBefore = $this->getRecord()->roles()->pluck('name')->all();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Kolom password hanya ada di $data bila diisi (lihat UserForm), jadi ini penanda ganti password.
        $this->passwordChanged = filled($data['password'] ?? null);

        return $data;
    }

    protected function afterSave(): void
    {
        AccessAudit::userRolesChanged(
            $this->getRecord(),
            $this->rolesBefore,
            $this->getRecord()->roles()->pluck('name')->all(),
        );

        if ($this->passwordChanged) {
            AccessAudit::passwordChanged($this->getRecord());
        }
    }
}

<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Support\AccessAudit;
use App\Support\PermissionGroups;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /** @var list<string>|null */
    protected ?array $selectedPermissions = null;

    protected function getRedirectUrl(): string
    {
        return RoleResource::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Kolom per-kelompok bukan kolom tabel peran: dipisahkan dulu, disimpan lewat relasi sesudahnya.
        [$this->selectedPermissions, $rest] = PermissionGroups::extract($data);

        return $rest;
    }

    protected function afterCreate(): void
    {
        $names = $this->selectedPermissions ?? [];

        $this->getRecord()->syncPermissions($names);

        // Cache izin dibuang agar hak akses langsung berlaku.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AccessAudit::rolePermissionsChanged($this->getRecord(), [], $names);
    }
}

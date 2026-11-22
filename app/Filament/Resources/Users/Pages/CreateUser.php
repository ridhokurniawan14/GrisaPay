<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\AccessAudit;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return "Pengguna {$this->getRecord()->name} berhasil dibuat";
    }

    protected function afterCreate(): void
    {
        AccessAudit::userRolesChanged(
            $this->getRecord(),
            [],
            $this->getRecord()->roles()->pluck('name')->all(),
        );
    }
}

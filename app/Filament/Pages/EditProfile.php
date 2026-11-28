<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as DbSchema;
use SensitiveParameter;

/** Profil akun: ganti nama, username, email, dan password (+ 2FA bila diaktifkan di panel). */
class EditProfile extends BaseEditProfile
{
    private function hasUsername(): bool
    {
        try {
            return DbSchema::hasColumn('users', 'username');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function getUsernameFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->required()
            ->minLength(4)
            ->maxLength(50)
            ->regex('/^[A-Za-z0-9._-]+$/')
            ->validationMessages(['regex' => 'Hanya huruf, angka, titik, garis bawah, dan strip (tanpa spasi).'])
            ->unique(ignoreRecord: true)
            ->helperText('Dipakai untuk masuk. Ganti dengan hati-hati.');
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        // Minta password saat ini juga ketika username diubah.
        return parent::getCurrentPasswordFormComponent()->visible(fn(Get $get): bool => filled($get('password'))
            || ($get('email') !== $this->getUser()->getAttributeValue('email'))
            || ($this->hasUsername() && $get('username') !== $this->getUser()->getAttributeValue('username')));
    }

    /** Setelah menyimpan, kembali ke dasbor (notifikasi "Tersimpan" tetap tampil). */
    protected function getRedirectUrl(): ?string
    {
        return filament()->getUrl();
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('back')->label('Kembali ke dasbor')->url(filament()->getUrl())->color('gray');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(array_values(array_filter([
            $this->getNameFormComponent(),
            $this->hasUsername() ? $this->getUsernameFormComponent() : null,
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ])));
    }

    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        // `username` tidak termasuk $fillable di model User, jadi diisi eksplisit.
        if (array_key_exists('username', $data)) {
            $record->forceFill(['username' => $data['username']]);
            unset($data['username']);
        }

        return parent::handleRecordUpdate($record, $data);
    }
}

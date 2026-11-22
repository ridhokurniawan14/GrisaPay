<?php

namespace App\Filament\Pages;

use App\Models\InstitutionSetting;
use App\Support\ThemeColor;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Support\StaleUpload;
use Illuminate\Support\Facades\Storage;
use App\Support\SettingsAudit;
use Illuminate\Support\Arr;

/**
 * Halaman kustom TIDAK otomatis memakai policy, jadi izin diperiksa sendiri
 * (saat membuka halaman dan saat menyimpan): hanya yang punya hak "settings.manage".
 *
 * @property-read Schema $form
 */
class PengaturanLembaga extends Page
{
    protected string $view = 'filament.pages.pengaturan-lembaga';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Kolom gambar => disk penyimpanannya. */
    private const FILE_FIELDS = [
        'logo_path' => 'public',
        'principal_signature_path' => 'local',
        'treasurer_signature_path' => 'local',
        'qris_image_path' => 'local',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Pengaturan Lembaga';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Pengaturan';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public function getTitle(): string
    {
        return 'Pengaturan Lembaga';
    }

    public function mount(): void
    {
        $this->form->fill(InstitutionSetting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Identitas Lembaga')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nama lembaga')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('short_name')
                                ->label('Nama singkat')
                                ->maxLength(50),
                            TextInput::make('npsn')
                                ->label('NPSN')
                                ->maxLength(20),
                            TextInput::make('city')
                                ->label('Kota (untuk tanggal pada tanda tangan)')
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->label('Telepon')
                                ->tel()
                                ->maxLength(30),
                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->maxLength(255),
                            TextInput::make('website')
                                ->label('Website')
                                ->url()
                                ->maxLength(255),
                            Textarea::make('address')
                                ->label('Alamat')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Section::make('Logo dan Tampilan')
                        ->schema([
                            // SVG sengaja tidak diizinkan (bisa memuat skrip).
                            FileUpload::make('logo_path')
                                ->label('Logo')
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                ->maxSize(1024)
                                ->disk('public')
                                ->directory('institution')
                                ->visibility('public')
                                ->helperText('PNG, JPG, atau WebP, maksimal 1 MB. Tampil di header panel dan kop dokumen.'),
                            ColorPicker::make('primary_color')
                                ->label('Warna tema')
                                ->required()
                                ->regex(ThemeColor::PATTERN)
                                ->helperText('Berlaku untuk seluruh panel setelah disimpan.'),
                        ])
                        ->columns(2),
                    Section::make('Penandatangan Dokumen')
                        ->description('Muncul pada kwitansi dan laporan.')
                        ->schema([
                            TextInput::make('principal_name')
                                ->label('Nama kepala sekolah')
                                ->maxLength(255),
                            TextInput::make('principal_nip')
                                ->label('NIP / NUPTK kepala sekolah')
                                ->maxLength(30),
                            FileUpload::make('principal_signature_path')
                                ->label('Tanda tangan kepala sekolah')
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                ->maxSize(1024)
                                ->disk('local')
                                ->directory('institution')
                                ->visibility('private')
                                ->columnSpanFull(),
                            TextInput::make('treasurer_name')
                                ->label('Nama bendahara')
                                ->maxLength(255),
                            TextInput::make('treasurer_nip')
                                ->label('NIP / NUPTK bendahara')
                                ->maxLength(30),
                            FileUpload::make('treasurer_signature_path')
                                ->label('Tanda tangan bendahara')
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                ->maxSize(1024)
                                ->disk('local')
                                ->directory('institution')
                                ->visibility('private')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Section::make('Pembayaran')
                        ->description('Rekening dan QRIS adalah data sensitif: hanya ubah bila benar-benar diperlukan.')
                        ->schema([
                            TextInput::make('bank_name')
                                ->label('Nama bank')
                                ->maxLength(255),
                            TextInput::make('bank_account_no')
                                ->label('Nomor rekening')
                                ->maxLength(50),
                            TextInput::make('bank_account_name')
                                ->label('Atas nama rekening')
                                ->maxLength(255),
                            FileUpload::make('qris_image_path')
                                ->label('Gambar QRIS statis')
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                ->maxSize(2048)
                                ->disk('local')
                                ->directory('institution')
                                ->visibility('private')
                                ->helperText('QRIS resmi dari bank atau penyedia pembayaran. Penggantian gambar harus diperiksa bendahara.'),
                            Textarea::make('receipt_footer')
                                ->label('Teks kaki kwitansi')
                                ->rows(2)
                                ->maxLength(120)
                                ->helperText('Maksimal 120 karakter (kertas kwitansi sempit).')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Simpan pengaturan')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->record(InstitutionSetting::current())
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        // Kolom yang dikosongkan disimpan sebagai NULL, bukan teks kosong.
        $data = array_map(fn($value) => $value === '' ? null : $value, $this->form->getState());

        $data['primary_color'] = ThemeColor::sanitize($data['primary_color'] ?? null);

        // Kolom gambar selalu satu berkas (teks) atau kosong.
        foreach (array_keys(self::FILE_FIELDS) as $field) {
            $value = $data[$field] ?? null;
            $data[$field] = is_array($value) ? (reset($value) ?: null) : $value;
        }

        $setting = InstitutionSetting::current();
        $isNew = ! $setting->exists;
        $fields = $setting->getFillable();
        $before = Arr::only($setting->getAttributes(), $fields);

        $previous = [];
        foreach (self::FILE_FIELDS as $field => $disk) {
            $previous[$field] = $setting->{$field};
        }

        $setting->fill($data)->save();

        // Catat perubahan (nilai lama -> baru) secara eksplisit.
        SettingsAudit::log($setting, SettingsAudit::lines($before, Arr::only($setting->getAttributes(), $fields)), $isNew);

        // Hapus berkas lama yang diganti atau dihapus, supaya tidak menumpuk di disk server.
        foreach (self::FILE_FIELDS as $field => $disk) {
            if (StaleUpload::shouldDelete($previous[$field], $setting->{$field})) {
                Storage::disk($disk)->delete($previous[$field]);
            }
        }

        Notification::make()
            ->success()
            ->title('Pengaturan lembaga disimpan')
            ->send();

        // Muat ulang halaman supaya warna tema dan logo yang baru langsung terlihat.
        $this->redirect(static::getUrl());
    }
}

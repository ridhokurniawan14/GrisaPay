<?php

namespace App\Models;

use App\Support\ThemeColor;
use Illuminate\Database\Eloquent\Model;

/** Pengaturan lembaga: selalu satu baris. Gunakan InstitutionSetting::current(). */
class InstitutionSetting extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'npsn',
        'address',
        'city',
        'phone',
        'email',
        'website',
        'logo_path',
        'primary_color',
        'principal_name',
        'principal_nip',
        'principal_signature_path',
        'treasurer_name',
        'treasurer_nip',
        'treasurer_signature_path',
        'bank_name',
        'bank_account_no',
        'bank_account_name',
        'qris_image_path',
        'receipt_footer',
    ];

    protected $attributes = [
        'name' => 'Nama Sekolah',
        'primary_color' => ThemeColor::DEFAULT,
    ];

    private static ?self $cached = null;

    protected static function booted(): void
    {
        static::saved(fn() => self::$cached = null);
    }

    /** Baris pengaturan; belum tersimpan (berisi nilai bawaan) bila belum pernah disimpan. */
    public static function current(): self
    {
        return self::$cached ??= static::query()->first() ?? new static;
    }
}

<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Mencatat siapa mengubah apa (nilai lama & baru) pada model. Nama log otomatis dari nama model:
 * InstitutionSetting => "institution-setting". Model dengan kolom rahasia (mis. User) JANGAN memakai
 * trait ini, tetapi mendefinisikan getActivitylogOptions() sendiri dengan daftar kolom eksplisit.
 */
trait LogsChanges
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(Str::kebab(class_basename($this)))
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}

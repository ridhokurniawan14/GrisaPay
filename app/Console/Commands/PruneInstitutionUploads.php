<?php

namespace App\Console\Commands;

use App\Models\InstitutionSetting;
use App\Support\StaleUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneInstitutionUploads extends Command
{
    protected $signature = 'grisapay:prune-uploads
        {--delete : Hapus berkas yatim. Tanpa opsi ini hanya ditampilkan}';

    protected $description = 'Cari (dan hapus) berkas gambar lembaga yang tidak lagi dipakai pengaturan';

    /** disk => kolom pengaturan yang memakai disk itu */
    private const TARGETS = [
        'public' => ['logo_path'],
        'local' => ['principal_signature_path', 'treasurer_signature_path', 'qris_image_path'],
    ];

    public function handle(): int
    {
        $setting = InstitutionSetting::current();
        $count = 0;
        $bytes = 0;

        foreach (self::TARGETS as $disk => $fields) {
            $storage = Storage::disk($disk);
            $referenced = array_values(array_filter(array_map(fn(string $field) => $setting->{$field}, $fields)));

            foreach ($storage->files(rtrim(StaleUpload::DIRECTORY, '/')) as $file) {
                if (in_array($file, $referenced, true)) {
                    continue;
                }

                $size = $storage->size($file);
                $count++;
                $bytes += $size;
                $this->line(sprintf('[%s] %s (%s KB)', $disk, $file, number_format($size / 1024, 1)));

                if ($this->option('delete')) {
                    $storage->delete($file);
                }
            }
        }

        if ($count === 0) {
            $this->info('Tidak ada berkas yatim.');

            return self::SUCCESS;
        }

        $this->option('delete')
            ? $this->info(sprintf('%d berkas dihapus, %s KB dibebaskan.', $count, number_format($bytes / 1024, 1)))
            : $this->comment(sprintf('%d berkas yatim (%s KB). Jalankan lagi dengan --delete untuk menghapusnya.', $count, number_format($bytes / 1024, 1)));

        return self::SUCCESS;
    }
}

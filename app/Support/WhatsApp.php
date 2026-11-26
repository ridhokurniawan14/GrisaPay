<?php

namespace App\Support;

use App\Models\Student;

/** Tautan WhatsApp (wa.me) ke wali murid; bila nomor wali kosong, ke nomor siswa. */
final class WhatsApp
{
    /** Urutan pilihan wali bila tidak ada kontak utama. */
    private const RELATION_ORDER = ['ayah' => 1, 'ibu' => 2, 'wali' => 3];

    /** Ubah 08xx / +62 8xx / 8xx menjadi 628xx (hanya angka). Null bila tidak masuk akal. */
    public static function normalize(?string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $raw);

        if ($d === '' || $d === null) {
            return null;
        }

        $d = match (true) {
            str_starts_with($d, '62') => $d,
            str_starts_with($d, '0') => '62'.substr($d, 1),
            str_starts_with($d, '8') => '62'.$d,
            default => $d,
        };

        return strlen($d) >= 10 && strlen($d) <= 15 ? $d : null;
    }

    /** @return array{number: string, label: string, name: string}|null */
    public static function target(Student $student): ?array
    {
        $guardians = $student->relationLoaded('guardians') ? $student->guardians : $student->guardians()->get();

        $candidates = $guardians
            ->filter(fn ($g): bool => self::normalize($g->whatsapp) !== null)
            ->sortBy(fn ($g): int => ($g->is_primary_contact ? 0 : 10) + (self::RELATION_ORDER[$g->relation] ?? 9))
            ->values();

        if ($candidates->isNotEmpty()) {
            $g = $candidates->first();

            return ['number' => self::normalize($g->whatsapp), 'label' => ucfirst((string) $g->relation).($g->name ? ' · '.$g->name : ''), 'name' => (string) ($g->name ?? '')];
        }

        $own = self::normalize($student->phone);

        return $own !== null ? ['number' => $own, 'label' => 'Siswa (nomor wali belum ada)', 'name' => ''] : null;
    }

    public static function link(string $number, string $text): string
    {
        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }

    public static function arrearsMessage(Student $student, string $className, int $remaining, ?string $school = null): string
    {
        $school ??= (string) config('grisapay.school_name', 'SMK PGRI 1 Giri Banyuwangi');
        $rp = 'Rp '.number_format($remaining, 0, ',', '.');

        return "Assalamu'alaikum Bapak/Ibu wali murid dari {$student->name} (kelas {$className}).\n\n"
            ."Kami dari {$school} menginformasikan bahwa masih ada tagihan sekolah yang belum dilunasi sebesar {$rp}. "
            ."Mohon kesediaannya menyelesaikan pembayaran di kasir sekolah.\n\n"
            ."Jika sudah membayar, mohon abaikan pesan ini. Terima kasih.";
    }
}

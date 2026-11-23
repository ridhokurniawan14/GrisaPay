<?php

namespace App\Services\GrisaId;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/** Semua kegagalan komunikasi dengan GrisaID. Pesannya tidak pernah memuat client secret atau token. */
final class GrisaIdException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('GrisaID belum dikonfigurasi: isi GRISAID_URL, GRISAID_CLIENT_ID, dan GRISAID_CLIENT_SECRET di file .env, lalu jalankan php artisan config:clear.');
    }

    public static function tokenRequest(Response $response): self
    {
        $hint = $response->status() === 401 ? ' Periksa GRISAID_CLIENT_ID dan GRISAID_CLIENT_SECRET.' : '';

        return new self("GrisaID menolak permintaan token (HTTP {$response->status()}" . self::detail($response) . ").{$hint}");
    }

    public static function request(string $path, Response $response): self
    {
        $hint = match ($response->status()) {
            401 => ' Token ditolak.',
            403 => ' Client tidak memiliki scope untuk endpoint ini.',
            404 => ' Data tidak ditemukan.',
            422 => ' Parameter tidak valid.',
            default => '',
        };

        return new self("Permintaan ke GrisaID gagal: {$path} (HTTP {$response->status()}" . self::detail($response) . ").{$hint}");
    }

    public static function unreachable(string $url, Throwable $previous): self
    {
        return new self("GrisaID tidak dapat dihubungi di {$url}. Pastikan GrisaID berjalan dan alamatnya benar.", 0, $previous);
    }

    private static function detail(Response $response): string
    {
        $message = $response->json('error_description') ?? $response->json('message') ?? $response->json('error');

        return is_string($message) && $message !== '' ? ': ' . mb_strimwidth($message, 0, 120, '…') : '';
    }
}

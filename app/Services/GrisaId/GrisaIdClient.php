<?php

namespace App\Services\GrisaId;

use Generator;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Klien API GrisaID (data master). Memakai OAuth2 client credentials: token disimpan sementara dan
 * diambil ulang otomatis bila ditolak (satu kali, tanpa pengulangan tak terbatas). Semua kegagalan
 * menjadi GrisaIdException dengan pesan yang jelas dan TANPA rahasia.
 */
final class GrisaIdClient
{
    private const TOKEN_CACHE_KEY = 'grisaid.access_token';

    /** Token tidak disimpan lebih lama dari ini (detik), walau server memberi masa berlaku yang jauh lebih panjang. */
    private const MAX_TOKEN_TTL = 3600;

    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
    ) {}

    /** @return array<string, mixed> */
    public function ping(): array
    {
        return $this->get('/api/v1/ping');
    }

    /** @return array<string, mixed> */
    public function currentAcademicYear(): array
    {
        return $this->get('/api/v1/academic-years/current')['data'] ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function academicYears(): array
    {
        return $this->get('/api/v1/academic-years')['data'] ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function classes(?string $academicYear = null): array
    {
        return $this->get('/api/v1/classes', array_filter(['academic_year' => $academicYear]))['data'] ?? [];
    }

    /** @param array<string, mixed> $filters */
    public function studentsTotal(array $filters = []): int
    {
        return (int) ($this->get('/api/v1/students', array_merge($filters, ['per_page' => 1]))['meta']['total'] ?? 0);
    }

    /**
     * Seluruh siswa yang cocok dengan filter, halaman demi halaman (hemat memori).
     *
     * @param  array<string, mixed>  $filters
     * @return Generator<int, array<string, mixed>>
     */
    public function students(array $filters = [], int $perPage = 200): Generator
    {
        $page = 1;

        do {
            $payload = $this->get('/api/v1/students', array_merge($filters, ['per_page' => $perPage, 'page' => $page]));

            foreach ($payload['data'] ?? [] as $student) {
                yield $student;
            }

            $lastPage = (int) ($payload['meta']['last_page'] ?? $page);
            $page++;
        } while ($page <= $lastPage);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $response = $this->send($path, $query);

        if ($response->status() === 401) {
            // Token ditolak (kedaluwarsa atau dicabut): buang, lalu coba SEKALI lagi dengan token baru.
            $this->cache->forget(self::TOKEN_CACHE_KEY);
            $response = $this->send($path, $query);
        }

        if ($response->failed()) {
            throw GrisaIdException::request($path, $response);
        }

        return $response->json() ?? [];
    }

    /** @param array<string, mixed> $query */
    private function send(string $path, array $query): Response
    {
        try {
            return $this->client()->withToken($this->token())->get($path, $query);
        } catch (ConnectionException $e) {
            throw GrisaIdException::unreachable($this->baseUrl(), $e);
        }
    }

    private function token(): string
    {
        $cached = $this->cache->get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $clientId = (string) config('grisaid.client_id');
        $clientSecret = (string) config('grisaid.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw GrisaIdException::notConfigured();
        }

        $response = $this->client()->asForm()->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => implode(' ', (array) config('grisaid.scopes', [])),
        ]);

        $token = $response->json('access_token');

        if ($response->failed() || ! is_string($token) || $token === '') {
            throw GrisaIdException::tokenRequest($response);
        }

        $ttl = min(self::MAX_TOKEN_TTL, max(60, (int) $response->json('expires_in', 3600) - 120));
        $this->cache->put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    private function client(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout((int) config('grisaid.timeout', 15))
            ->connectTimeout(5);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('grisaid.url'), '/');
    }
}

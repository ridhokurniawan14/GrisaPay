<?php

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use App\Support\BillActor;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menentukan pemanggil API tagihan dari token Bearer:
 *  1) token GrisaPay (staf)      -> divalidasi Passport lokal, wajib scope bills:read + hak bills.view
 *  2) token GrisaID (siswa/guru) -> divalidasi GrisaID lewat GET /api/v1/me (jawaban di-cache 60 dtk), wajib scope bills:read
 * Token client-credentials tidak punya pengguna sehingga selalu ditolak (token HARUS milik pengguna).
 */
class ResolveBillActor
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_if($token === null || $token === '', 401, 'Token tidak ada.');

        $actor = $this->isGrisaIdToken($token) ? $this->fromGrisaId($token) : $this->fromLocal();
        abort_if($actor === null, 401, 'Token tidak valid atau tidak berhak.');

        $request->attributes->set('bill_actor', $actor);

        return $next($request);
    }

    /** Token JWT dari GrisaID dikenali dari "aud" (ID klien aplikasi pengguna, mis. GrisaKAD) — hanya untuk memilih jalur; keabsahan tetap diperiksa. */
    private function isGrisaIdToken(string $jwt): bool
    {
        $parts = explode('.', $jwt);
        $payload = isset($parts[1]) ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true) : null;
        $aud = is_array($payload) ? (array) ($payload['aud'] ?? []) : [];
        $clients = array_filter(array_map('trim', explode(',', (string) config('grisaid.user_client_ids', ''))));

        return array_intersect($aud, $clients) !== [];
    }

    private function fromLocal(): ?BillActor
    {
        $user = Auth::guard('api')->user();

        if ($user === null || ! $user->token()?->can('bills:read') || ! $user->can(PermissionName::BillsView->value)) {
            return null;
        }

        return new BillActor('staff', name: $user->name);
    }

    private function fromGrisaId(string $token): ?BillActor
    {
        $me = Cache::get($key = 'bill-actor:'.hash('sha256', $token));

        if ($me === null) {
            try {
                $res = Http::baseUrl((string) config('grisaid.url'))->acceptJson()->withToken($token)
                    ->timeout((int) config('grisaid.timeout', 15))->get('/api/v1/me');
            } catch (ConnectionException) {
                abort(503, 'GrisaID tidak dapat dihubungi.');
            }

            if (! $res->ok()) {
                return null; // ditolak GrisaID (kedaluwarsa/dicabut/scope kurang) — tidak di-cache
            }

            $me = $res->json('data');
            Cache::put($key, $me, 60);
        }

        if (! is_array($me) || ! in_array('bills:read', (array) ($me['scopes'] ?? []), true)) {
            return null;
        }

        return match ($me['type'] ?? null) {
            'student' => ($me['student']['id'] ?? null) ? new BillActor('student', studentUuid: $me['student']['id'], name: $me['name'] ?? null) : null,
            'teacher' => new BillActor('teacher', classroomUuids: array_values(array_map(fn ($c) => (string) $c['id'], (array) ($me['classrooms'] ?? []))), name: $me['name'] ?? null),
            default => null, // staf lewat token GrisaPay, bukan token GrisaID
        };
    }
}

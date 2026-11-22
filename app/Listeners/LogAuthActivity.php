<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Mencatat login, logout, dan percobaan login gagal (dengan IP). Password tidak pernah dicatat. */
class LogAuthActivity
{
    public function handleLogin(Login $event): void
    {
        $this->record('login', $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        $this->record('logout', $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $this->record('login-gagal', $event->user, ['email' => (string) ($event->credentials['email'] ?? '-')]);
    }

    /** @param array<string, string> $extra */
    private function record(string $description, ?Authenticatable $user, array $extra = []): void
    {
        $activity = activity('auth')->withProperties([
            'ip' => request()->ip(),
            'perangkat' => Str::limit((string) request()->userAgent(), 120),
            ...$extra,
        ]);

        if ($user instanceof Model) {
            $activity->causedBy($user);
        }

        $activity->log($description);
    }
}

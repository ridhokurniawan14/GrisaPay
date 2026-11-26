<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Enums\RoleName;
use Illuminate\Support\Facades\Gate;
use App\Support\AccessAudit;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Services\YearLock::register();
        \Laravel\Passport\Passport::tokensCan(['bills:read' => 'Membaca status tagihan siswa']);
        // Super Admin lolos semua izin. Peran lain mengikuti hak akses dari halaman Peran.
        Gate::before(fn($user, string $ability) => $user->hasRole(RoleName::SuperAdmin->value) ? true : null);

        // Pembuatan/penghapusan peran dicatat. Dilewati di konsol (seeder) agar log tidak berisi derau.
        Role::created(function (Role $role): void {
            if (! app()->runningInConsole()) {
                AccessAudit::roleEvent($role, 'created');
            }
        });

        Role::deleted(function (Role $role): void {
            if (! app()->runningInConsole()) {
                AccessAudit::roleEvent($role, 'deleted');
            }
        });
    }
}

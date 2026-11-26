<?php

namespace App\Providers\Filament;

use App\Models\InstitutionSetting;
use App\Support\ThemeColor;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => self::primaryColor(),
            ])
            ->brandName('GrisaPay')
            // Urutan menu: pekerjaan harian di atas, pengaturan satu kali & sistem di bawah.
            ->navigation(function (NavigationBuilder $builder) use ($panel): NavigationBuilder {
                $layout = [
                    'Transaksi' => ['Kasir', 'Pembayaran', 'Shift Kas'],
                    'Tagihan' => ['Tagihan Siswa', 'Diskon', 'Riwayat Impor Tunggakan'],
                    'Laporan & Kas' => ['Rekap Kas', 'Buku Kas', 'Pengeluaran'],
                    'Master Tarif' => ['Tarif', 'Jenis Diskon'],
                    'Pengaturan' => ['Pengguna', 'Peran & Hak Akses', 'Pengaturan Lembaga', 'Tutup Buku', 'Log Aktivitas', 'Cadangan Data'],
                ];

                $all = collect([...$panel->getResources(), ...$panel->getPages()])
                    ->filter(fn(string $c): bool => ! method_exists($c, 'shouldRegisterNavigation') || $c::shouldRegisterNavigation())
                    ->flatMap(fn(string $c): array => $c::getNavigationItems())
                    ->filter(fn($item): bool => $item->isVisible());

                $take = function (array $labels) use (&$all): array {
                    $picked = [];

                    foreach ($labels as $label) {
                        $found = $all->first(fn($i): bool => (string) $i->getLabel() === $label);

                        if ($found !== null) {
                            $picked[] = $found;
                            $all = $all->reject(fn($i): bool => $i === $found);
                        }
                    }

                    return $picked;
                };

                $builder->items($take(['Dasbor']));
                $groups = [];

                foreach ($layout as $title => $labels) {
                    if (($items = $take($labels)) !== []) {
                        $groups[] = NavigationGroup::make($title)->items($items);
                    }
                }

                // Menu baru yang belum dipetakan di atas tetap muncul, di bawah.
                foreach ($all->groupBy(fn($i): string => (string) ($i->getGroup() ?: 'Lainnya')) as $title => $items) {
                    $groups[] = NavigationGroup::make($title)->items($items->values()->all());
                }

                return $builder->groups($groups);
            })
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('15s')
            // Sisi kanan atas, urutan dari kiri: Buka Kasir, info sinkron, tombol sinkron, lonceng, avatar user.
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): string => Blade::render(<<<'BLADE'
                    <div style="display:flex; align-items:center; gap:.75rem; margin-inline-end:.75rem;">
                        @can(\App\Enums\PermissionName::PaymentsCreate->value)
                            <div class="hidden md:block">
                                <x-filament::button tag="a" :href="\App\Filament\Pages\Kasir::getUrl()" icon="heroicon-o-calculator" size="sm" wire:navigate>
                                    Buka Kasir
                                </x-filament::button>
                            </div>
                        @endcan
                        @livewire(\App\Livewire\SyncGrisaIdButton::class)
                    </div>
                BLADE),
            )
            // Kotak pencarian menu di sidebar (tekan "/" atau Ctrl+K untuk fokus).
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn(): string => view('filament.menu-search')->render())
            // Ringkasan shift di sisi kiri tombol "Tutup shift" (hanya halaman Kasir).
            ->renderHook(
                PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
                fn(): string => view('filament.kasir-shift-chip')->render(),
                scopes: \App\Filament\Pages\Kasir::class,
            )
            ->brandLogo(fn() => view('filament.brand'))
            ->brandLogoHeight('2.5rem')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /** Dibungkus try/catch agar perintah artisan (mis. migrate) tidak gagal sebelum tabel pengaturan ada. */
    private static function primaryColor(): string
    {
        try {
            return ThemeColor::sanitize(InstitutionSetting::current()->primary_color);
        } catch (\Throwable) {
            return ThemeColor::DEFAULT;
        }
    }
}

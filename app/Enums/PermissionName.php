<?php

namespace App\Enums;

/**
 * Katalog hak akses. Pemetaan peran -> hak akses diatur dari halaman Peran (RBAC);
 * katalog ini hanya mendaftarkan hak akses apa saja yang ada, plus nilai bawaan peran bawaan.
 */
enum PermissionName: string
{
    case PanelAccess = 'panel.access';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case SettingsManage = 'settings.manage';
    case ActivityLogView = 'activity-log.view';
    case ActivityLogPrune = 'activity-log.prune';
    case StudentsView = 'students.view';
    case SyncRun = 'sync.run';
    case TariffsManage = 'tariffs.manage';
    case BillsView = 'bills.view';
    case BillsManage = 'bills.manage';
    case DiscountsRequest = 'discounts.request';
    case DiscountsApprove = 'discounts.approve';
    case PaymentsCreate = 'payments.create';
    case PaymentsView = 'payments.view';
    case PaymentsPrint = 'payments.print';
    case PaymentsVerify = 'payments.verify';
    case PaymentsVoidRequest = 'payments.void.request';
    case PaymentsVoidApprove = 'payments.void.approve';
    case CashSessionsManage = 'cash-sessions.manage';
    case CashSessionsReview = 'cash-sessions.review';
    case ReportsDaily = 'reports.daily';
    case ReportsPeriodic = 'reports.periodic';
    case DashboardTreasurer = 'dashboard.treasurer';
    case DashboardPrincipal = 'dashboard.principal';
    case PortalStudent = 'portal.student';
    case PortalHomeroom = 'portal.homeroom';

    /** @var array<string, array{0: string, 1: string}> [nilai => [kelompok, keterangan]] */
    private const META = [
        'panel.access' => ['Akses', 'Masuk panel admin'],
        'users.view' => ['Pengguna', 'Melihat daftar pengguna'],
        'users.manage' => ['Pengguna', 'Mengelola pengguna'],
        'roles.manage' => ['Pengguna', 'Mengelola peran dan hak akses'],
        'settings.manage' => ['Pengaturan', 'Mengubah pengaturan lembaga dan tema'],
        'activity-log.view' => ['Pengaturan', 'Melihat log aktivitas'],
        'activity-log.prune' => ['Pengaturan', 'Membersihkan log aktivitas'],
        'students.view' => ['Data siswa', 'Melihat data siswa'],
        'sync.run' => ['Data siswa', 'Menjalankan sinkronisasi dari GrisaID'],
        'tariffs.manage' => ['Tarif & tagihan', 'Mengelola tarif'],
        'bills.view' => ['Tarif & tagihan', 'Melihat tagihan'],
        'bills.manage' => ['Tarif & tagihan', 'Membuat dan menyesuaikan tagihan'],
        'discounts.request' => ['Tarif & tagihan', 'Mengajukan diskon'],
        'discounts.approve' => ['Tarif & tagihan', 'Menyetujui diskon'],
        'payments.create' => ['Pembayaran', 'Menerima pembayaran'],
        'payments.view' => ['Pembayaran', 'Melihat pembayaran'],
        'payments.print' => ['Pembayaran', 'Mencetak kwitansi'],
        'payments.verify' => ['Pembayaran', 'Memverifikasi transfer dan QRIS'],
        'payments.void.request' => ['Pembayaran', 'Mengajukan pembatalan'],
        'payments.void.approve' => ['Pembayaran', 'Menyetujui pembatalan'],
        'cash-sessions.manage' => ['Kas', 'Membuka dan menutup kas sendiri'],
        'cash-sessions.review' => ['Kas', 'Memeriksa dan menerima setoran kas'],
        'reports.daily' => ['Laporan', 'Rekap harian'],
        'reports.periodic' => ['Laporan', 'Rekap berkala'],
        'dashboard.treasurer' => ['Laporan', 'Dashboard bendahara'],
        'dashboard.principal' => ['Laporan', 'Dashboard kepala sekolah'],
        'portal.student' => ['Portal', 'Portal siswa (tanggungan sendiri)'],
        'portal.homeroom' => ['Portal', 'Portal wali kelas (tanggungan kelasnya)'],
    ];

    public function group(): string
    {
        return self::META[$this->value][0];
    }

    /** Contoh: "Pembayaran: Menerima pembayaran" */
    public function label(): string
    {
        return self::META[$this->value][0] . ': ' . self::META[$this->value][1];
    }

    /** Hanya bagian aksinya, mis. "Menerima pembayaran" (judul kartu sudah menyebut kelompoknya). */
    public function shortLabel(): string
    {
        return self::META[$this->value][1];
    }

    /**
     * Hak akses per kelompok, urutan kelompok mengikuti urutan kemunculan di katalog.
     *
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $case) {
            $groups[$case->group()][] = $case;
        }

        return $groups;
    }

    /**
     * Pilihan satu kelompok untuk daftar centang: [nama izin => teks pendek].
     *
     * @return array<string, string>
     */
    public static function optionsFor(string $group): array
    {
        $options = [];

        foreach (self::grouped()[$group] ?? [] as $case) {
            $options[$case->value] = $case->shortLabel();
        }

        return $options;
    }

    /** Label untuk nama izin mentah; izin yang tidak dikenal ditampilkan apa adanya. */
    public static function labelFor(string $name): string
    {
        return self::tryFrom($name)?->label() ?? $name;
    }

    /**
     * Hak akses bawaan peran bawaan. Superadmin tidak perlu daftar: ia lolos semua izin.
     *
     * @return list<self>
     */
    public static function defaultsFor(RoleName $role): array
    {
        return match ($role) {
            RoleName::SuperAdmin => [],
            RoleName::Treasurer => [
                self::PanelAccess,
                self::StudentsView,
                self::SyncRun,
                self::TariffsManage,
                self::BillsView,
                self::BillsManage,
                self::DiscountsApprove,
                self::PaymentsView,
                self::PaymentsVerify,
                self::PaymentsVoidApprove,
                self::CashSessionsReview,
                self::ReportsDaily,
                self::ReportsPeriodic,
                self::DashboardTreasurer,
                self::ActivityLogView,
            ],
            RoleName::Cashier => [
                self::StudentsView,
                self::BillsView,
                self::DiscountsRequest,
                self::PaymentsCreate,
                self::PaymentsView,
                self::PaymentsPrint,
                self::PaymentsVoidRequest,
                self::CashSessionsManage,
                self::ReportsDaily,
            ],
            RoleName::Principal => [
                self::PanelAccess,
                self::StudentsView,
                self::BillsView,
                self::PaymentsView,
                self::ReportsDaily,
                self::ReportsPeriodic,
                self::DashboardPrincipal,
                self::ActivityLogView,
            ],
            RoleName::HomeroomTeacher => [self::PortalHomeroom],
            RoleName::Student => [self::PortalStudent],
        };
    }
}

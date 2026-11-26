<?php

namespace App\Filament\Pages;

use App\Enums\PermissionName;
use App\Models\AcademicYear;
use App\Models\CashShift;
use App\Models\Payment;
use App\Models\Student;
use App\Services\BillSummary;
use App\Services\CashShiftService;
use App\Services\PaymentPrintService;
use App\Services\PaymentService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\RawJs;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use UnitEnum;

class Kasir extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Kasir';

    protected static ?string $title = 'Kasir';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.kasir';

    public string $search = '';

    public ?int $studentId = null;

    public string $focus = '';

    public string $method = Payment::CASH;

    public string $amount = '';

    public string $tendered = '';

    public bool $samePayer = true;

    public string $payerName = '';

    public string $reference = '';

    public string $note = '';

    public ?int $lastPaymentId = null;

    /** Dibuat sekali per pengiriman form; dipakai server untuk menolak pembayaran ganda. */
    public string $submissionKey = '';

    public bool $confirmDuplicate = false;

    /** Modal awal saat membuka shift. */
    public string $openingCash = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(PermissionName::PaymentsCreate->value);
    }

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    public function mount(): void
    {
        $this->submissionKey = (string) Str::uuid();
    }

    public function selectStudent(int $id): void
    {
        $this->studentId = $id;
        $this->search = '';
        $this->resetForm();
        $this->lastPaymentId = null;
        $this->submissionKey = (string) Str::uuid();
    }

    public function clearStudent(): void
    {
        $this->studentId = null;
        $this->resetForm();
        $this->lastPaymentId = null;
        $this->submissionKey = (string) Str::uuid();
    }

    // Kolom jumlah tampil berformat (100.000) lewat mask di browser. Nilai yang dipakai selalu hanya digit (100000).
    public function updatedAmount(): void
    {
        $this->confirmDuplicate = false;
    }

    public function updatedMethod(): void
    {
        $this->confirmDuplicate = false;
    }

    public function updatedFocus(): void
    {
        $this->amount = '';
        $this->tendered = '';
    }

    /** Pintasan jumlah: "all" = seluruh sisa menurut fokus. */
    public function fillAll(): void
    {
        $this->amount = (string) $this->max;
    }

    #[Computed]
    public function results(): array
    {
        $term = trim($this->search);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return Student::query()
            ->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('nisn', 'like', $like)->orWhere('nis', 'like', $like))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('bills')->whereColumn('bills.student_id', 'students.id'))
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (Student $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'nisn' => $s->nisn,
                'status' => $s->status,
                'classroom' => $s->enrollments()->with('classroom')->latest('academic_year_id')->first()?->classroom?->name ?? '-',
            ])
            ->all();
    }

    #[Computed]
    public function student(): ?Student
    {
        return $this->studentId ? Student::query()->find($this->studentId) : null;
    }

    #[Computed]
    public function summary(): ?array
    {
        $year = AcademicYear::current();

        return ($this->student && $year) ? BillSummary::forStudent($this->student, $year) : null;
    }

    /** Kelas siswa: tahun ajaran aktif; bila tidak ada (mis. alumni) kelas terakhirnya. */
    #[Computed]
    public function classroom(): string
    {
        if (! $this->student) {
            return '-';
        }

        $year = AcademicYear::current();
        $base = $this->student->enrollments()->with('classroom');
        $enrollment = $year ? (clone $base)->where('academic_year_id', $year->id)->first() : null;
        $enrollment ??= (clone $base)->latest('academic_year_id')->first();

        return $enrollment?->classroom?->name ?? '-';
    }

    /**
     * Pilihan "Dibayarkan untuk": kelompok per kategori (dan tunggakan), tiap kelompok berisi
     * pilihan "semua" dan rincian satu per satu. Hanya yang masih punya sisa.
     *
     * @return list<array{label: string, all: array{value: string, label: string}, items: list<array{value: string, label: string}>}>
     */
    #[Computed]
    public function focusOptions(): array
    {
        if (! $this->student) {
            return [];
        }

        $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
        $groups = [];

        foreach (app(PaymentService::class)->outstandingDetail($this->student, null) as $row) {
            if ($row['carry']) {
                $key = 'carry';
                $label = 'Tunggakan sebelumnya';
                $allValue = 'carry';
                $itemLabel = $row['name'].' · TA '.$row['year'];
            } elseif ($row['current']) {
                $key = 'cat:'.$row['category_id'];
                $label = $row['category'];
                $allValue = $key;
                $itemLabel = $row['name'];
            } else {
                continue;
            }

            $groups[$key] ??= ['label' => $label, 'all_value' => $allValue, 'total' => 0, 'items' => []];
            $groups[$key]['total'] += $row['remaining'];
            $groups[$key]['items'][] = ['value' => 'item:'.$row['id'], 'label' => $itemLabel.' — sisa '.$rp($row['remaining'])];
        }

        return array_values(array_map(fn (array $g): array => [
            'label' => $g['label'],
            'all' => ['value' => $g['all_value'], 'label' => 'Semua '.$g['label'].' — sisa '.$rp($g['total'])],
            'items' => $g['items'],
        ], $groups));
    }

    #[Computed]
    public function max(): int
    {
        return $this->student ? app(PaymentService::class)->outstandingTotal($this->student, $this->focus ?: null) : 0;
    }

    #[Computed]
    public function amountInt(): int
    {
        return self::digits($this->amount);
    }

    #[Computed]
    public function preview(): ?array
    {
        return ($this->student && $this->amountInt > 0)
            ? app(PaymentService::class)->preview($this->student, $this->focus ?: null, $this->amountInt)
            : null;
    }

    #[Computed]
    public function amountError(): ?string
    {
        if ($this->amountInt > 0 && $this->amountInt > $this->max) {
            return 'Melebihi sisa tagihan (Rp '.number_format($this->max, 0, ',', '.').'). Kurangi jumlahnya.';
        }

        if ($this->student && $this->max === 0) {
            return 'Tidak ada tagihan yang belum terbayar untuk pilihan ini.';
        }

        return null;
    }

    /** Pembayaran serupa (siswa, jumlah, metode sama) dalam 10 menit terakhir: kemungkinan terinput dua kali. */
    #[Computed]
    public function duplicateWarning(): ?Payment
    {
        if (! $this->student || $this->amountInt < 1) {
            return null;
        }

        return Payment::query()
            ->where('student_id', $this->student->id)
            ->where('amount', $this->amountInt)
            ->where('method', $this->method)
            ->whereIn('status', [Payment::PAID, Payment::PENDING])
            ->where('paid_at', '>=', now()->subMinutes(10))
            ->latest('paid_at')
            ->first();
    }

    #[Computed]
    public function shift(): ?CashShift
    {
        return app(CashShiftService::class)->current(auth()->user());
    }

    #[Computed]
    public function shiftRequired(): bool
    {
        return app(CashShiftService::class)->isRequiredFor(auth()->user());
    }

    /** @return array{cash: int, count: int, expected: int} */
    #[Computed]
    public function shiftStats(): array
    {
        $shift = $this->shift;

        if ($shift === null) {
            return ['cash' => 0, 'count' => 0, 'expected' => 0];
        }

        $cash = $shift->cashIn();

        return ['cash' => $cash, 'count' => $shift->payments()->count(), 'expected' => $shift->opening_cash + $cash];
    }

    public function openShift(): void
    {
        abort_unless(auth()->user()?->can(PermissionName::CashSessionsManage->value), 403);

        try {
            app(CashShiftService::class)->open(auth()->user(), self::digits($this->openingCash));
        } catch (DomainException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        $this->openingCash = '';
        $this->forgetComputed();
        Notification::make()->success()->title('Shift dibuka')->duration(3000)->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('closeShift')
                ->label('Tutup shift')
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->visible(fn (): bool => $this->shift !== null)
                ->modalHeading('Tutup shift kas')
                ->modalDescription('Hitung uang tunai di laci, lalu isi jumlahnya. Selisih terhadap catatan sistem dilaporkan ke bendahara.')
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Tutup shift')
                ->schema([
                    View::make('filament.kasir-close-info')
                        ->viewData(function (Get $get): array {
                            $stats = $this->shiftStats;
                            $raw = preg_replace('/\\D+/', '', (string) ($get('counted') ?? ''));

                            return [
                                'opening' => (int) $this->shift?->opening_cash,
                                'cash' => $stats['cash'],
                                'count' => $stats['count'],
                                'expected' => $stats['expected'],
                                'counted' => $raw === '' ? null : (int) $raw,
                            ];
                        }),
                    TextInput::make('counted')
                        ->live(debounce: 400)
                        ->label('Uang tunai di laci (hasil hitung)')
                        ->prefix('Rp')
                        ->required()
                        ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                        ->stripCharacters('.')
                        ->numeric(),
                    Textarea::make('note')->label('Catatan (opsional)')->rows(2),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->can(PermissionName::CashSessionsManage->value), 403);

                    $shift = $this->shift;

                    if ($shift === null) {
                        return;
                    }

                    try {
                        $closed = app(CashShiftService::class)->close($shift, auth()->user(), (int) $data['counted'], $data['note'] ?? null);
                    } catch (DomainException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return;
                    }

                    $this->forgetComputed();
                    $diff = (int) $closed->difference;

                    Notification::make()
                        ->color($diff === 0 ? 'success' : 'warning')
                        ->title($diff === 0 ? 'Shift ditutup · kas cocok' : 'Shift ditutup · ada selisih Rp '.number_format(abs($diff), 0, ',', '.').($diff < 0 ? ' (kurang)' : ' (lebih)'))
                        ->body('Dilaporkan ke bendahara untuk ditinjau.')
                        ->duration(8000)
                        ->send();
                }),
        ];
    }

    #[Computed]
    public function canSubmit(): bool
    {
        return $this->student !== null
            && ($this->shift !== null || ! $this->shiftRequired)
            && $this->amountInt >= 1
            && $this->amountError === null
            && ($this->samePayer || trim($this->payerName) !== '')
            && ($this->duplicateWarning === null || $this->confirmDuplicate);
    }

    #[Computed]
    public function change(): ?int
    {
        if ($this->method !== Payment::CASH || self::digits($this->tendered) === 0 || $this->amountInt < 1) {
            return null;
        }

        return self::digits($this->tendered) - $this->amountInt;
    }

    #[Computed]
    public function history()
    {
        return $this->student
            ? Payment::query()->where('student_id', $this->student->id)->with('prints')->latest('paid_at')->limit(8)->get()
            : collect();
    }

    #[Computed]
    public function lastPayment(): ?Payment
    {
        return $this->lastPaymentId ? Payment::query()->with('prints')->find($this->lastPaymentId) : null;
    }

    public function submit(): void
    {
        abort_unless(auth()->user()?->can(PermissionName::PaymentsCreate->value), 403);

        if (! $this->canSubmit) {
            return;
        }

        try {
            $payment = app(PaymentService::class)->record([
                'student_id' => $this->studentId,
                'focus' => $this->focus ?: null,
                'method' => $this->method,
                'amount' => $this->amountInt,
                'reference' => $this->reference,
                'payer_name' => $this->samePayer ? $this->student->name : $this->payerName,
                'note' => $this->note,
                'idempotency_key' => $this->submissionKey,
            ], auth()->user());
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Gagal menyimpan')->body($e->getMessage())->duration(8000)->send();

            return;
        }

        $this->forgetComputed();
        $this->submissionKey = (string) Str::uuid(); // kunci baru untuk pembayaran berikutnya
        $this->confirmDuplicate = false;
        $this->lastPaymentId = $payment->id;
        $this->amount = $this->tendered = $this->reference = $this->note = '';
        $this->focus = '';
        $this->samePayer = true;
        $this->payerName = '';

        if ($payment->status === Payment::PAID) {
            Notification::make()->success()->title("Tersimpan · {$payment->receipt_no}")->duration(4000)->send();

            if (auth()->user()->can(PermissionName::PaymentsPrint->value)) {
                $this->printReceipt($payment->id);
            }
        } else {
            Notification::make()->warning()->title('Tersimpan, menunggu verifikasi bendahara')
                ->body('Struk dicetak setelah pembayaran diverifikasi.')->duration(7000)->send();
        }
    }

    public function printReceipt(int $paymentId): void
    {
        abort_unless(auth()->user()?->can(PermissionName::PaymentsPrint->value), 403);

        $payment = Payment::query()->findOrFail($paymentId);

        try {
            $print = app(PaymentPrintService::class)->record($payment, auth()->user());
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Tidak bisa mencetak')->body($e->getMessage())->duration(8000)->send();

            return;
        }

        $url = URL::temporarySignedRoute('receipt.show', now()->addMinutes(10), ['print' => $print->id]);

        $this->dispatch('print-receipt', url: $url);
    }

    /** "1.500.000" -> 1500000 (titik/koma pemisah tidak pernah ikut tersimpan). */
    private static function digits(string $value): int
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        return $digits === '' ? 0 : (int) $digits;
    }

    /** Computed dipakai ulang selama satu permintaan; hapus agar tampilan memakai data setelah simpan. */
    private function forgetComputed(): void
    {
        unset(
            $this->summary, $this->max, $this->amountInt, $this->preview, $this->amountError,
            $this->canSubmit, $this->duplicateWarning, $this->change, $this->history, $this->lastPayment, $this->student,
            $this->shift, $this->shiftRequired, $this->shiftStats,
        );
    }

    private function resetForm(): void
    {
        $this->focus = '';
        $this->method = Payment::CASH;
        $this->amount = $this->tendered = $this->reference = $this->note = $this->payerName = '';
        $this->samePayer = true;
        $this->confirmDuplicate = false;
    }
}

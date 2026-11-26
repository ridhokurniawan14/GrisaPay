<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Discount;
use App\Models\DiscountAllocation;
use App\Models\User;
use App\Support\PendingNotifier;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alur diskon: diajukan -> (disetujui | ditolak); disetujui -> dicabut.
 * Potongan nyata disimpan sebagai alokasi per item, lalu bill_items.discount_amount dihitung ulang dari alokasi,
 * sehingga beberapa diskon bisa bertumpuk tanpa pernah melewati nilai item, dan pencabutan selalu bersih.
 */
final class DiscountService
{
    /** @param array<string, mixed> $data bill_id, discount_type_id, scope, fee_category_id?, bill_item_id?, mode, value, reason */
    public function request(array $data, User $by, bool $notify = true): Discount
    {
        $bill = Bill::query()->findOrFail($data['bill_id']);

        if ($data['scope'] === Discount::SCOPE_ITEM && ! BillItem::query()->where('bill_id', $bill->id)->whereKey($data['bill_item_id'] ?? 0)->exists()) {
            throw new DomainException('Rincian yang dipilih bukan bagian dari tagihan siswa ini.');
        }

        if ($data['scope'] === Discount::SCOPE_CATEGORY && ! BillItem::query()->where('bill_id', $bill->id)->where('fee_category_id', $data['fee_category_id'] ?? 0)->exists()) {
            throw new DomainException('Tagihan siswa ini tidak memiliki kategori yang dipilih.');
        }

        if ($data['mode'] === Discount::MODE_PERCENT && ($data['value'] < 1 || $data['value'] > 100)) {
            throw new DomainException('Persentase diskon harus 1 sampai 100.');
        }

        $discount = Discount::query()->create([
            'bill_id' => $bill->id,
            'discount_type_id' => $data['discount_type_id'],
            'scope' => $data['scope'],
            'fee_category_id' => $data['scope'] === Discount::SCOPE_CATEGORY ? $data['fee_category_id'] : null,
            'bill_item_id' => $data['scope'] === Discount::SCOPE_ITEM ? $data['bill_item_id'] : null,
            'mode' => $data['mode'],
            'value' => (int) $data['value'],
            'reason' => $data['reason'],
            'status' => Discount::REQUESTED,
            'requested_by' => $by->id,
        ]);

        $this->log($discount, $by, 'Diskon diajukan');

        if ($notify) {
            DB::afterCommit(fn () => PendingNotifier::discountsRequested(1, $discount->type->name, $by));
        }

        return $discount;
    }

    /**
     * Pengajuan massal: satu pengajuan per siswa dengan isian yang sama (mis. juara 1 tiap kelas).
     * Untuk cakupan "rincian", rincian dicari per siswa lewat NAMA (item_name). Siswa yang tidak cocok
     * atau sudah punya pengajuan sama yang masih berlaku dilewati dan dilaporkan.
     *
     * @param  array<string, mixed>  $data  bill_ids (list), discount_type_id, scope, fee_category_id?, item_name?, mode, value, reason
     * @return array{created: int, skipped: list<string>}
     */
    public function requestMany(array $data, User $by): array
    {
        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($data, $by, &$created, &$skipped): void {
            $bills = Bill::query()->with('student')->whereIn('id', $data['bill_ids'])->get();

            foreach ($bills as $bill) {
                $label = "{$bill->student->name} ({$bill->classroom_name})";
                $itemId = null;

                if ($data['scope'] === Discount::SCOPE_ITEM) {
                    $itemId = BillItem::query()->where('bill_id', $bill->id)->where('name', $data['item_name'] ?? '')->value('id');

                    if ($itemId === null) {
                        $skipped[] = "{$label}: tidak punya rincian \"{$data['item_name']}\"";

                        continue;
                    }
                }

                $categoryId = $data['scope'] === Discount::SCOPE_CATEGORY ? ($data['fee_category_id'] ?? null) : null;

                $duplicate = Discount::query()
                    ->where('bill_id', $bill->id)
                    ->where('discount_type_id', $data['discount_type_id'])
                    ->where('scope', $data['scope'])
                    ->where('fee_category_id', $categoryId)
                    ->where('bill_item_id', $itemId)
                    ->whereIn('status', [Discount::REQUESTED, Discount::APPROVED])
                    ->exists();

                if ($duplicate) {
                    $skipped[] = "{$label}: sudah ada pengajuan yang sama";

                    continue;
                }

                try {
                    $this->request([
                        'bill_id' => $bill->id,
                        'discount_type_id' => $data['discount_type_id'],
                        'scope' => $data['scope'],
                        'fee_category_id' => $categoryId,
                        'bill_item_id' => $itemId,
                        'mode' => $data['mode'],
                        'value' => (int) $data['value'],
                        'reason' => $data['reason'],
                    ], $by, notify: false);
                    $created++;
                } catch (DomainException $e) {
                    $skipped[] = "{$label}: {$e->getMessage()}";
                }
            }
        });

        if ($created > 0) {
            $typeName = \App\Models\DiscountType::query()->whereKey($data['discount_type_id'])->value('name') ?? 'Diskon';
            DB::afterCommit(fn () => PendingNotifier::discountsRequested($created, $typeName, $by));
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function approve(Discount $discount, User $by, ?string $note = null): Discount
    {
        return DB::transaction(function () use ($discount, $by, $note): Discount {
            $locked = Discount::query()->lockForUpdate()->findOrFail($discount->id);

            if ($locked->status !== Discount::REQUESTED) {
                throw new DomainException('Pengajuan ini sudah diputuskan.');
            }

            $items = $this->targetItems($locked);
            $paid = BillSummary::paidByItem($items->pluck('id')->all());

            $bases = [];

            foreach ($items as $item) {
                $bases[$item->id] = max(0, (int) $item->amount - (int) $item->discount_amount - (int) ($paid[$item->id] ?? 0));
            }

            $allocation = DiscountCalculator::allocate($bases, $locked->mode, $locked->value);

            if ($allocation === []) {
                throw new DomainException('Tidak ada nilai yang bisa dipotong (sudah lunas atau sudah terdiskon penuh).');
            }

            foreach ($allocation as $itemId => $amount) {
                DiscountAllocation::query()->create(['discount_id' => $locked->id, 'bill_item_id' => $itemId, 'amount' => $amount]);
            }

            $this->recompute(array_keys($allocation));

            $locked->update([
                'status' => Discount::APPROVED,
                'granted_amount' => array_sum($allocation),
                'decided_by' => $by->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            $this->log($locked, $by, 'Diskon disetujui', ['potongan' => 'Rp '.number_format(array_sum($allocation), 0, ',', '.')]);

            return $locked;
        });
    }

    public function reject(Discount $discount, User $by, ?string $note = null): Discount
    {
        return DB::transaction(function () use ($discount, $by, $note): Discount {
            $locked = Discount::query()->lockForUpdate()->findOrFail($discount->id);

            if ($locked->status !== Discount::REQUESTED) {
                throw new DomainException('Pengajuan ini sudah diputuskan.');
            }

            $locked->update(['status' => Discount::REJECTED, 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note]);
            $this->log($locked, $by, 'Diskon ditolak');

            return $locked;
        });
    }

    public function revoke(Discount $discount, User $by, ?string $note = null): Discount
    {
        return DB::transaction(function () use ($discount, $by, $note): Discount {
            $locked = Discount::query()->lockForUpdate()->findOrFail($discount->id);

            if ($locked->status !== Discount::APPROVED) {
                throw new DomainException('Hanya diskon yang sudah disetujui yang bisa dicabut.');
            }

            $itemIds = $locked->allocations()->pluck('bill_item_id')->all();
            $locked->allocations()->delete();
            $this->recompute($itemIds);

            $locked->update(['status' => Discount::REVOKED, 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note]);
            $this->log($locked, $by, 'Diskon dicabut');

            return $locked;
        });
    }

    /** @return Collection<int, BillItem> */
    private function targetItems(Discount $discount): Collection
    {
        $query = BillItem::query()->where('bill_id', $discount->bill_id)->orderBy('sort_order')->orderBy('id')->lockForUpdate();

        return match ($discount->scope) {
            Discount::SCOPE_CATEGORY => $query->where('fee_category_id', $discount->fee_category_id)->get(),
            Discount::SCOPE_ITEM => $query->whereKey($discount->bill_item_id)->get(),
            default => $query->get(),
        };
    }

    /** discount_amount tiap item = jumlah seluruh alokasinya. @param list<int> $itemIds */
    private function recompute(array $itemIds): void
    {
        $sums = DiscountAllocation::query()
            ->whereIn('bill_item_id', $itemIds)
            ->selectRaw('bill_item_id, SUM(amount) as total')
            ->groupBy('bill_item_id')
            ->pluck('total', 'bill_item_id');

        foreach ($itemIds as $id) {
            BillItem::query()->whereKey($id)->update(['discount_amount' => (int) ($sums[$id] ?? 0)]);
        }
    }

    /** @param array<string, mixed> $extra */
    private function log(Discount $discount, User $by, string $message, array $extra = []): void
    {
        $discount->loadMissing(['bill.student', 'type']);

        activity('diskon')
            ->causedBy($by)
            ->performedOn($discount)
            ->withProperties([
                'siswa' => $discount->bill->student->name,
                'kelas' => $discount->bill->classroom_name,
                'jenis' => $discount->type->name,
                'cakupan' => $discount->scopeText(),
                'nilai' => $discount->valueText(),
                'alasan' => $discount->reason,
            ] + $extra)
            ->log($message);
    }
}

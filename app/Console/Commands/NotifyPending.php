<?php

namespace App\Console\Commands;

use App\Support\PendingNotifier;
use Illuminate\Console\Command;

class NotifyPending extends Command
{
    protected $signature = 'grisapay:notify-pending';

    protected $description = 'Kirim notifikasi lonceng untuk pembayaran/void/diskon yang sudah menunggu sebelum fitur notifikasi dipasang';

    public function handle(): int
    {
        PendingNotifier::prune();
        $this->info('Notifikasi dikirim: '.PendingNotifier::backfill());

        return self::SUCCESS;
    }
}

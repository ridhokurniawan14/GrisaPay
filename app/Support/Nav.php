<?php

namespace App\Support;

/** Memuat ulang halaman dengan mulus setelah keputusan, agar lencana angka di sidebar langsung benar. */
final class Nav
{
    public static function refresh(object $livewire): void
    {
        PendingNotifier::prune(); // lonceng ikut bersih dari urusan yang sudah selesai

        $livewire->js('try { Livewire.navigate(window.location.href) } catch (e) { window.location.reload() }');
    }
}

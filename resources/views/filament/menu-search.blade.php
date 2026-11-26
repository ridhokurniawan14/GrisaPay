<style>
    /* Scrollbar tipis & halus (sidebar, tabel, modal, halaman) */
    * {
        scrollbar-width: thin;
        scrollbar-color: color-mix(in srgb, var(--gray-500) 45%, transparent) transparent;
    }

    *::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    *::-webkit-scrollbar-track {
        background: transparent;
    }

    *::-webkit-scrollbar-thumb {
        background: color-mix(in srgb, var(--gray-500) 40%, transparent);
        border-radius: 999px;
        border: 2px solid transparent;
        background-clip: content-box;
    }

    *::-webkit-scrollbar-thumb:hover {
        background: color-mix(in srgb, var(--primary-500) 70%, transparent);
        background-clip: content-box;
    }

    *::-webkit-scrollbar-button {
        display: none;
        width: 0;
        height: 0;
    }

    *::-webkit-scrollbar-corner {
        background: transparent;
    }
</style>

@php
    $menuItems = [];

    try {
        foreach (\Filament\Facades\Filament::getNavigation() as $group) {
            $groupLabel = method_exists($group, 'getLabel') ? $group->getLabel() : null;

            foreach ($group->getItems() as $item) {
                $menuItems[] = [
                    'label' => (string) $item->getLabel(),
                    'group' => (string) $groupLabel,
                    'url' => (string) $item->getUrl(),
                ];

                foreach ($item->getChildItems() as $child) {
                    $menuItems[] = [
                        'label' => (string) $child->getLabel(),
                        'group' => trim($groupLabel . ' › ' . $item->getLabel(), ' ›'),
                        'url' => (string) $child->getUrl(),
                    ];
                }
            }
        }
    } catch (\Throwable $e) {
        $menuItems = [];
    }
@endphp

@if (count($menuItems) > 0)
    <div x-data="{
        q: '',
        sel: 0,
        items: @js($menuItems),
        get results() {
            const s = this.q.trim().toLowerCase();
            if (!s) return [];
            return this.items.filter(i => (i.label + ' ' + i.group).toLowerCase().includes(s)).slice(0, 8);
        },
        go() {
            const r = this.results[this.sel];
            if (!r) return;
            this.q = '';
            window.Livewire?.navigate ? window.Livewire.navigate(r.url) : (window.location = r.url);
        },
    }"
        x-on:keydown.window="
            if (($event.key === '/' && ! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName) && ! document.activeElement.isContentEditable) || (($event.ctrlKey || $event.metaKey) && $event.key.toLowerCase() === 'k')) {
                $event.preventDefault(); if (! $store.sidebar.isOpen) { $store.sidebar.open(); } $nextTick(() => { $refs.input.focus(); $refs.input.select(); });
            }"
        x-show="$store.sidebar.isOpen" class="ms-wrap">
        <style>
            .ms-wrap {
                padding: .25rem 0 .75rem;
            }

            .ms-box {
                position: relative;
            }

            .ms-input {
                width: 100%;
                box-sizing: border-box;
                padding: .5rem 2.25rem .5rem 2.1rem;
                font-size: .875rem;
                border-radius: .625rem;
                border: 1px solid var(--gray-200);
                background: #fff;
                color: var(--gray-950);
                outline: none;
            }

            .dark .ms-input {
                background: var(--gray-900);
                border-color: var(--gray-700);
                color: #fff;
            }

            .ms-input:focus {
                border-color: var(--primary-500);
                box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary-500) 25%, transparent);
            }

            .ms-ico {
                position: absolute;
                left: .65rem;
                top: 50%;
                transform: translateY(-50%);
                width: 1rem;
                height: 1rem;
                color: var(--gray-400);
                pointer-events: none;
            }

            .ms-key {
                position: absolute;
                right: .55rem;
                top: 50%;
                transform: translateY(-50%);
                font-size: .6875rem;
                line-height: 1;
                padding: .2rem .4rem;
                border-radius: .35rem;
                border: 1px solid var(--gray-200);
                color: var(--gray-500);
                pointer-events: none;
            }

            .dark .ms-key {
                border-color: var(--gray-700);
            }

            .ms-list {
                margin-top: .375rem;
                border-radius: .625rem;
                border: 1px solid var(--gray-200);
                overflow: hidden;
                background: #fff;
            }

            .dark .ms-list {
                background: var(--gray-900);
                border-color: var(--gray-700);
            }

            .ms-item {
                display: block;
                padding: .45rem .75rem;
                font-size: .875rem;
                text-decoration: none;
                color: inherit;
                cursor: pointer;
            }

            .ms-item small {
                display: block;
                font-size: .6875rem;
                color: var(--gray-500);
            }

            .ms-item.on,
            .ms-item:hover {
                background: color-mix(in srgb, var(--primary-500) 12%, transparent);
            }

            .ms-empty {
                padding: .6rem .75rem;
                font-size: .8125rem;
                color: var(--gray-500);
            }
        </style>
        <div class="ms-box">
            <svg class="ms-ico" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd"
                    d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.58 3.58a.75.75 0 1 1-1.06 1.06l-3.58-3.58A7 7 0 0 1 2 9Z"
                    clip-rule="evenodd" />
            </svg>
            <input x-ref="input" x-model="q" type="text" class="ms-input" placeholder="Cari menu…"
                autocomplete="off" x-on:input="sel = 0"
                x-on:keydown.arrow-down.prevent="sel = Math.min(sel + 1, results.length - 1)"
                x-on:keydown.arrow-up.prevent="sel = Math.max(sel - 1, 0)" x-on:keydown.enter.prevent="go()"
                x-on:keydown.escape="q = ''; $refs.input.blur()">
            <span class="ms-key" x-show="! q">/</span>
        </div>
        <div class="ms-list" x-show="q.trim() !== ''" x-cloak>
            <template x-for="(r, i) in results" :key="r.url + r.label">
                <a :href="r.url" class="ms-item" :class="{ 'on': i === sel }"
                    x-on:click.prevent="sel = i; go()" x-on:mouseenter="sel = i">
                    <span x-text="r.label"></span>
                    <small x-show="r.group" x-text="r.group"></small>
                </a>
            </template>
            <div class="ms-empty" x-show="results.length === 0">Menu tidak ditemukan.</div>
        </div>
    </div>
@endif

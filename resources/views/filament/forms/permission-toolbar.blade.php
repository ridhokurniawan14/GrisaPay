<div x-data="{
    query: '',
    selected: 0,
    total: 0,
    scope() { return this.$el.closest('form') ?? document; },
    boxes() { return Array.from(this.scope().querySelectorAll('input[type=checkbox]')); },
    row(box) { return box.closest('label') ?? box.parentElement; },
    refresh() {
        const boxes = this.boxes();
        this.total = boxes.length;
        this.selected = boxes.filter((box) => box.checked).length;
    },
    filter() {
        const term = this.query.trim().toLowerCase();
        const cards = new Map();

        this.boxes().forEach((box) => {
            const row = this.row(box);
            const match = term === '' || (row?.textContent ?? '').toLowerCase().includes(term);

            if (row) row.style.display = match ? '' : 'none';

            const card = box.closest('section, .fi-section');
            if (card) cards.set(card, (cards.get(card) ?? false) || match);
        });

        cards.forEach((hasMatch, card) => { card.style.display = hasMatch ? '' : 'none'; });
    },
    setAll(checked) {
        this.boxes().forEach((box) => {
            const row = this.row(box);

            if (row && row.style.display === 'none') return;
            if (box.checked !== checked) box.click();
        });

        this.refresh();
    },
}" x-init="setTimeout(() => refresh(), 500)" x-on:change.window="refresh()"
    style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
    <div style="flex: 1 1 16rem; min-width: 14rem;">
        <x-filament::input.wrapper>
            <x-filament::input type="text" placeholder="Cari hak akses…" x-model="query" x-on:input="filter()"
                x-on:keydown.enter.prevent />
        </x-filament::input.wrapper>
    </div>

    <x-filament::button type="button" color="gray" size="sm" x-on:click="setAll(true)">
        Pilih semua
    </x-filament::button>

    <x-filament::button type="button" color="gray" size="sm" x-on:click="setAll(false)">
        Kosongkan semua
    </x-filament::button>

    <span style="font-size: 0.875rem; opacity: 0.7;" x-text="selected + ' dari ' + total + ' hak akses dipilih'"></span>
</div>

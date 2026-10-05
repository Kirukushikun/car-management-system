<section>
    <x-page-header title="Category Matrix" subtitle="Response and CA implementation timelines (days) per category — drives the automatic deadlines">
        <div style="display:flex; gap:10px;">
            <button type="button" class="btn btn-secondary" wire:click="discard">Discard</button>
            <button type="button" class="btn btn-accent" wire:click="save">Save changes</button>
        </div>
    </x-page-header>

    <div class="content">
        @if ($notice)
            <div class="flash" wire:key="notice">{{ $notice }}</div>
        @endif

        @foreach ($lines as $line)
            <div class="card" style="margin-bottom:14px;" wire:key="line-{{ $line->id }}">
                <div style="padding:14px 18px 4px;"><div class="section-title" style="margin:0;">{{ $line->name }}</div></div>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr><th>Category / sub-categories</th><th>Response (days)</th><th>CA implementation (days)</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($line->categories as $category)
                                <tr wire:key="category-{{ $category->id }}">
                                    <td>
                                        <strong>{{ $category->name }}</strong>
                                        <div style="font-size:10.5px; color:var(--text3); margin-top:2px;">{{ $category->subcategories->pluck('name')->implode(' · ') }}</div>
                                    </td>
                                    <td class="field">
                                        <input type="number" min="1" max="60" wire:model="days.{{ $category->id }}.response" aria-label="{{ $line->name }} {{ $category->name }} response days" style="width:70px; min-height:30px; padding:4px 8px;">
                                        @error("days.{$category->id}.response") <div class="error-text">{{ $message }}</div> @enderror
                                    </td>
                                    <td class="field">
                                        <input type="number" min="1" max="90" wire:model="days.{{ $category->id }}.implementation" aria-label="{{ $line->name }} {{ $category->name }} implementation days" style="width:70px; min-height:30px; padding:4px 8px;">
                                        @error("days.{$category->id}.implementation") <div class="error-text">{{ $message }}</div> @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <footer class="note">Seeded from the 8.19.26 requirements matrices. Each CAR's deadlines are frozen when it is issued, so editing this matrix only affects CARs issued afterwards.</footer>
    </div>
</section>

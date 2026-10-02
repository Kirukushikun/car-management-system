<section>
    <x-page-header title="Category Matrix" subtitle="Response and CA implementation timelines (days) per category — drives the automatic deadlines">
        <button type="button" class="btn btn-accent" wire:click="save">Save changes</button>
    </x-page-header>

    <div class="content">
        @if ($notice)
            <div class="flash">{{ $notice }}</div>
        @endif

        @foreach ($matrix as $line => $categories)
            <div class="card" style="margin-bottom:14px;" wire:key="line-{{ $line }}">
                <div style="padding:14px 18px 4px;"><div class="section-title" style="margin:0;">{{ $line }}</div></div>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr><th>Category / sub-categories</th><th>Response (days)</th><th>CA implementation (days)</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category => $timeline)
                                <tr wire:key="{{ $line }}-{{ $category }}">
                                    <td>
                                        <strong>{{ $category }}</strong>
                                        <div style="font-size:10.5px; color:var(--text3); margin-top:2px;">{{ implode(' · ', $timeline['subcategories']) }}</div>
                                    </td>
                                    <td class="field"><input type="number" min="0" value="{{ $timeline['response'] }}" style="width:70px; min-height:30px; padding:4px 8px;"></td>
                                    <td class="field"><input type="number" min="0" value="{{ $timeline['implementation'] }}" style="width:70px; min-height:30px; padding:4px 8px;"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <footer class="note">Values come from the 8.19.26 requirements matrices. In production, each CAR's deadlines are frozen at its issued date, so editing this matrix never moves deadlines on CARs already issued.</footer>
    </div>
</section>

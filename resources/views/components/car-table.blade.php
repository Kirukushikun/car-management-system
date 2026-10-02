@props(['cars', 'empty' => 'Nothing here right now.'])

@use('App\Services\ScaffoldData')

<table>
    @if (count($cars) === 0)
        <tbody><tr><td class="empty-row">{{ $empty }}</td></tr></tbody>
    @else
        <thead>
            <tr>
                <th>Reference</th><th>Farm / unit</th><th>Category</th><th>Status</th><th>Owner</th><th>Deadline</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cars as $car)
                @php
                    $deadline = ScaffoldData::activeDeadline($car);
                    $owner = ScaffoldData::owner($car);
                    $url = route('cars.show', $car['ref']);
                @endphp
                <tr class="row-click" wire:key="car-{{ $car['ref'] }}" x-data x-on:click="Livewire.navigate('{{ $url }}')">
                    <td class="tnum"><a href="{{ $url }}" wire:navigate style="color:inherit; text-decoration:none;">{{ $car['ref'] }}</a></td>
                    <td>{{ $car['farm'] }}<div style="font-size:10px;color:var(--text3)">{{ $car['unit'] }}</div></td>
                    <td>{{ $car['category'] }}<div style="font-size:10px;color:var(--text3)">{{ $car['subcategory'] }}</div></td>
                    <td>
                        <x-pill :tone="ScaffoldData::statusTone($car['status'])">{{ $car['status'] }}</x-pill>
                        @if (ScaffoldData::isOverdue($car))
                            <x-pill tone="red" style="margin-left:6px;">overdue</x-pill>
                        @endif
                    </td>
                    <td>
                        @if ($owner['role'])
                            <x-pill :tone="$owner['role']->tone()">{{ $owner['label'] }}</x-pill>
                        @else
                            <x-pill tone="slate">—</x-pill>
                        @endif
                    </td>
                    <td class="tnum">{{ $deadline['date']->format('M j, Y') }}<div style="font-size:10px;color:var(--text3)">{{ $deadline['label'] }}</div></td>
                </tr>
            @endforeach
        </tbody>
    @endif
</table>

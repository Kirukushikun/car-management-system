@props(['cars', 'empty' => 'Nothing here right now.'])

<table>
    @if ($cars->isEmpty())
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
                    $url = route('cars.show', $car);
                    $dueOn = $car->activeDueOn();
                    $ownerRole = $car->status->ownerRole();
                @endphp
                <tr class="row-click" wire:key="car-{{ $car->id }}" x-data x-on:click="Livewire.navigate('{{ $url }}')">
                    <td class="tnum"><a href="{{ $url }}" wire:navigate style="color:inherit; text-decoration:none;">{{ $car->reference }}</a></td>
                    <td>{{ $car->farm->name }}<div style="font-size:10px;color:var(--text3)">{{ $car->issuedToUnit->name }}</div></td>
                    <td>{{ $car->category->name }}<div style="font-size:10px;color:var(--text3)">{{ $car->subcategory->name }}</div></td>
                    <td>
                        <x-pill :tone="$car->status->tone()">{{ $car->status->label() }}</x-pill>
                        @if ($car->isOverdue())
                            <x-pill tone="red" style="margin-left:6px;">overdue</x-pill>
                        @endif
                    </td>
                    <td>
                        @if ($ownerRole)
                            <x-pill :tone="$ownerRole->tone()">{{ $car->ownerLabel() }}</x-pill>
                        @else
                            <x-pill tone="slate">—</x-pill>
                        @endif
                    </td>
                    <td class="tnum">
                        @if ($dueOn)
                            {{ $dueOn->format('M j, Y') }}
                            <div style="font-size:10px;color:var(--text3)">{{ $car->status->phase() === 1 ? 'Response deadline' : ($car->revised_due_on ? 'Revised end date' : 'Implementation deadline') }}</div>
                        @else
                            <span style="color:var(--text3)">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    @endif
</table>

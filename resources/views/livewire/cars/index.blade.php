<section>
    <x-page-header :title="$heading" :subtitle="$subtitle">
        <div style="display:flex; gap:8px;">
        <a href="{{ route('cars.export', $view ? ['view' => $view] : []) }}" class="btn btn-secondary" style="text-decoration:none;"><x-icon name="download" /> Export CSV</a>
        @can('create-cars')
            <a href="{{ route('cars.create') }}" wire:navigate class="btn btn-accent" style="text-decoration:none;"><x-icon name="plus" /> New CAR</a>
        @endcan
        </div>
    </x-page-header>

    <div class="content">
        <div class="card">
            <div class="table-scroll">
                <x-car-table :cars="$cars" />
            </div>
            <x-table-footer :paginator="$cars" />
        </div>
    </div>
</section>

<section>
    <x-page-header :title="$heading" :subtitle="$subtitle">
        @can('create-cars')
            <a href="{{ route('cars.create') }}" wire:navigate class="btn btn-accent" style="text-decoration:none;">+ New CAR</a>
        @endcan
    </x-page-header>

    <div class="content">
        <div class="card">
            <div class="table-scroll">
                <x-car-table :cars="$cars" />
            </div>
        </div>
    </div>
</section>

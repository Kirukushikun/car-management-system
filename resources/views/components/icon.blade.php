@props(['name'])

@php
    $paths = [
        'dashboard' => 'M3 13h4v8H3zM10 3h4v18h-4zM17 8h4v13h-4z',
        'list' => 'M4 6h16M4 12h16M4 18h9',
        'plus' => 'M12 5v14M5 12h14',
        'alert' => 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
        'inbox' => 'M4 12h4.3l1.2 2.5h5l1.2-2.5H20M5 12 4 5h16l-1 7M4 12v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6',
        'users' => 'M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        'sliders' => 'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6',
        'logout' => 'M16 17l5-5-5-5M21 12H9M13 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8',
        'bell' => 'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0',
        'check' => 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
    ];
@endphp

<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $paths[$name] }}"/>
</svg>

@props(['name'])

{{--
    One place for the system's icons, drawn with Font Awesome (loaded from cdnjs in the layouts).
    Callers use our own names, so swapping an icon later is a one-line change here.
--}}
@php
    $icons = [
        'dashboard' => 'fa-solid fa-chart-column',
        'list' => 'fa-solid fa-list',
        'plus' => 'fa-solid fa-plus',
        'alert' => 'fa-solid fa-triangle-exclamation',
        'inbox' => 'fa-solid fa-inbox',
        'users' => 'fa-solid fa-users',
        'sliders' => 'fa-solid fa-sliders',
        'sidebar' => 'fa-solid fa-bars-staggered',
        'maintenance' => 'fa-solid fa-screwdriver-wrench',
        'logout' => 'fa-solid fa-right-from-bracket',
        'bell' => 'fa-regular fa-bell',
        'check' => 'fa-regular fa-circle-check',
        'check-double' => 'fa-solid fa-check-double',
        'info' => 'fa-solid fa-circle-info',
        'close' => 'fa-solid fa-xmark',
        'chevron-down' => 'fa-solid fa-chevron-down',
        'chevron-right' => 'fa-solid fa-chevron-right',
        'arrow-left' => 'fa-solid fa-arrow-left',
        'arrow-right' => 'fa-solid fa-arrow-right',
        'print' => 'fa-solid fa-print',
        'download' => 'fa-solid fa-file-arrow-down',
        'refresh' => 'fa-solid fa-arrows-rotate',
        'file' => 'fa-regular fa-file-lines',
        'image' => 'fa-regular fa-image',
        'video' => 'fa-solid fa-film',
        'eye' => 'fa-regular fa-eye',
        'eye-slash' => 'fa-regular fa-eye-slash',
        'compare' => 'fa-solid fa-code-compare',
        'reset' => 'fa-solid fa-rotate-left',
        'first' => 'fa-solid fa-angles-left',
        'previous' => 'fa-solid fa-angle-left',
        'next' => 'fa-solid fa-angle-right',
        'last' => 'fa-solid fa-angles-right',
    ];
@endphp

<i {{ $attributes->class([$icons[$name] ?? 'fa-solid fa-circle-question', 'icon']) }} aria-hidden="true"></i>

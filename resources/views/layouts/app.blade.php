<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ isset($title) ? $title.' — ' : '' }}{{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        @php
            $user = auth()->user();
        @endphp

        <div class="shell">
            <aside class="sidebar">
                <div class="brand">
                    <h1>CAR Management System</h1>
                    <p>Brookside Group of Companies</p>
                    <span class="tag">Table Egg &amp; DOP</span>
                </div>

                <nav>
                    @foreach ($user->role->navigation() as $item)
                        @php
                            $isActive = request()->routeIs($item['route'])
                                && request()->query('view') === ($item['params']['view'] ?? null);
                            $count = $item['count'] ? count(\App\Services\ScaffoldData::carsFor($item['count'], $user)) : null;
                        @endphp
                        <a href="{{ route($item['route'], $item['params']) }}" wire:navigate
                           @class(['nav-item', 'active' => $isActive])>
                            <x-icon :name="$item['icon']" />
                            {{ $item['label'] }}
                            @if ($count !== null)
                                <span @class(['nav-count', 'warn' => $item['count'] === 'overdue' && $count > 0])>{{ $count }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="sidebar-foot">
                    <x-role-avatar :user="$user" class="avatar" />
                    <div class="id-block">
                        <div class="who">{{ $user->name }}</div>
                        <div class="role">{{ $user->roleWithScope() }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="switch-btn" title="{{ app()->isLocal() ? 'Sign out / switch role' : 'Sign out' }}">
                            <x-icon name="logout" />
                        </button>
                    </form>
                </div>
            </aside>

            <main class="main">
                <div class="scaffold-banner">UI scaffold — CAR data is sample data from the mockup and workflow buttons are stubs (development plan, Phase 0).</div>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>

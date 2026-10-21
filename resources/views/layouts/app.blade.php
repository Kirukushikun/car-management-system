<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <title>{{ isset($title) ? $title.' — ' : '' }}{{ config('app.name') }}</title>

        <meta name="debug-error-page" content="{{ app()->isLocal() && config('app.debug') ? '1' : '0' }}">
        @fonts
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- Apply the remembered sidebar state before the first paint, so it does not flicker open. --}}
        <script>
            (() => {
                const apply = () => { try { document.documentElement.classList.toggle('sidebar-collapsed', localStorage.getItem('sidebar-collapsed') === '1'); } catch (e) {} };
                apply();
                document.addEventListener('livewire:navigated', apply);
            })();
        </script>
    </head>
    <body>
        @php
            $user = auth()->user();
        @endphp

        <div class="shell">
            <aside class="sidebar" aria-label="Main menu">
                <div class="brand">
                    <div class="brand-text">
                        <h1>CAR Management System</h1>
                        <p>Brookside Group of Companies</p>
                        <span class="tag">Table Egg &amp; DOP</span>
                    </div>
                    <div class="brand-mark" aria-hidden="true">CAR</div>
                    <button type="button" class="sidebar-toggle" aria-label="Collapse or expand the menu" title="Collapse / expand menu"
                            onclick="const collapsed = document.documentElement.classList.toggle('sidebar-collapsed'); try { localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0'); } catch (e) {}">
                        <x-icon name="sidebar" />
                    </button>
                </div>

                <nav>
                    @foreach ($user->role->navigation() as $item)
                        @php
                            $isActive = request()->routeIs($item['route'])
                                && request()->query('view') === ($item['params']['view'] ?? null);
                            $count = $item['count'] ? \App\Models\Car::forView($item['count'], $user)->count() : null;
                        @endphp
                        <a href="{{ route($item['route'], $item['params']) }}" wire:navigate title="{{ $item['label'] }}"
                           @class(['nav-item', 'active' => $isActive]) @if ($isActive) aria-current="page" @endif>
                            <x-icon :name="$item['icon']" />
                            <span class="nav-text">{{ $item['label'] }}</span>
                            @if ($count !== null)
                                <span @class(['nav-count', 'warn' => $item['count'] === 'overdue' && $count > 0])>{{ $count }}</span>
                            @endif
                        </a>
                    @endforeach

                    @php
                        $unread = $user->unreadNotifications()->count();
                    @endphp
                    <div class="nav-label" style="margin-top:10px;">You</div>
                    <a href="{{ route('notifications') }}" wire:navigate title="Notifications" @class(['nav-item', 'active' => request()->routeIs('notifications')])>
                        <x-icon name="bell" />
                        <span class="nav-text">Notifications</span>
                        @if ($unread > 0)
                            <span class="nav-count warn">{{ $unread }}</span>
                        @endif
                    </a>
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
                <div class="scaffold-banner">Pre-release build — the full CAR workflow, notifications and dashboard are live; awaiting user acceptance testing.</div>
                {{ $slot }}
            </main>
        </div>

        <x-confirm-dialog />

        <x-request-error-toast />
    </body>
</html>

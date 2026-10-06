<section>
    <x-page-header title="Notifications" :subtitle="$unreadCount ? $unreadCount.' unread' : 'You are all caught up'">
        @if ($unreadCount)
            <button type="button" class="btn btn-secondary" wire:click="markAllRead">Mark all as read</button>
        @endif
    </x-page-header>

    <div class="content">
        <div class="card">
            @forelse ($notifications as $notification)
                <button type="button" wire:key="notification-{{ $notification->id }}" wire:click="open('{{ $notification->id }}')"
                        style="display:flex; gap:12px; align-items:flex-start; width:100%; text-align:left; padding:12px 16px; cursor:pointer;
                               background:{{ $notification->read_at ? 'transparent' : 'var(--accent-bg)' }}; border:0; border-top:{{ $loop->first ? '0' : '.5px solid var(--border)' }};">
                    <span style="width:8px; height:8px; border-radius:50%; margin-top:6px; flex-shrink:0; background:{{ $notification->read_at ? 'transparent' : 'var(--accent)' }};"></span>
                    <span style="flex:1; min-width:0;">
                        <span style="display:flex; justify-content:space-between; gap:10px;">
                            <strong style="font-size:12.5px;">
                                <span class="tnum">{{ $notification->data['reference'] ?? '' }}</span> — {{ $notification->data['message'] ?? 'Update' }}
                            </strong>
                            <span class="tnum" style="font-size:10.5px; color:var(--text3); flex-shrink:0;">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                        <span style="display:block; font-size:11.5px; color:var(--text2); margin-top:2px;">{{ $notification->data['detail'] ?? '' }}</span>
                    </span>
                </button>
            @empty
                <div class="empty-row">No notifications yet. You will be flagged here whenever a CAR needs you.</div>
            @endforelse
        </div>

        <div style="margin-top:12px;">{{ $notifications->links() }}</div>
    </div>
</section>

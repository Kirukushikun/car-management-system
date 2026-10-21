<?php

namespace App\Livewire\Notifications;

use App\Livewire\Concerns\FlexiblePagination;
use App\Models\Car;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The in-app flags: every CAR hand-off and deadline reminder addressed to the signed-in user.
 */
#[Title('Notifications')]
class Index extends Component
{
    use FlexiblePagination;

    #[Url]
    public int $perPage = 25;

    /**
     * Mark the flag read and jump to its CAR.
     */
    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $car = Car::find($notification->data['car_id'] ?? null);

        $car
            ? $this->redirectRoute('cars.show', $car, navigate: true)
            : $this->redirectRoute('notifications', navigate: true);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        return view('livewire.notifications.index', [
            'notifications' => auth()->user()->notifications()->latest()->paginate($this->rowsPerPage()),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}

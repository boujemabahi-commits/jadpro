<?php

namespace App\Livewire\Notifications;

use App\Models\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

class Bell extends Component
{
    /** Any module that creates a notification dispatches this so the badge updates at once. */
    #[On('notification-created')]
    public function refresh(): void
    {
    }

    public function markRead(int $id): void
    {
        $user = auth()->user();
        Notification::markReadFor($user, Notification::visibleTo($user)->whereKey($id)->pluck('notifications.id')->all());
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notifications.bell', [
            'items' => Notification::visibleTo($user)->latest()->limit(5)->get(),
            'unread' => Notification::visibleTo($user)->unreadBy($user)->count(),
        ]);
    }
}

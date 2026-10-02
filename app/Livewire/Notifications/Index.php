<?php

namespace App\Livewire\Notifications;

use App\Models\Notification;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'unread', history: true)]
    public bool $unreadOnly = false;

    public function updatingUnreadOnly(): void
    {
        $this->resetPage();
    }

    public function markRead(int $id): void
    {
        $user = auth()->user();
        Notification::markReadFor($user, Notification::visibleTo($user)->whereKey($id)->pluck('notifications.id')->all());
    }

    public function markAllRead(): void
    {
        $user = auth()->user();
        $count = Notification::markReadFor($user, Notification::visibleTo($user)->unreadBy($user)->pluck('notifications.id')->all());
        $this->dispatch('toast', message: $count ? __('تم تعليم الكل كمقروء') : __('لا توجد إشعارات غير مقروءة'));
    }

    public function render()
    {
        $user = auth()->user();

        $notifications = Notification::visibleTo($user)
            ->when($this->unreadOnly, fn ($q) => $q->unreadBy($user))
            ->latest()->latest('id')
            ->paginate(20);

        return view('livewire.notifications.index', [
            'notifications' => $notifications,
            'unread' => Notification::visibleTo($user)->unreadBy($user)->count(),
        ])->extends('layouts.app')->section('content')->title(__('الإشعارات'));
    }
}

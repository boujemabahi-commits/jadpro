<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest', ['title' => 'نسيت كلمة المرور؟'])] class extends Component
{
    public string $email = '';

    public function sendResetLink(): void
    {
        $this->email = mb_strtolower(trim($this->email));
        $this->validate(['email' => ['required', 'string', 'email']]);

        Password::sendResetLink($this->only('email'));

        // Always show the same message, whether or not the email exists,
        // so this form can't be used to find out who has an account.
        session()->flash('status', __('إذا كان هذا البريد مسجلاً لدينا، ستصلك رسالة تحتوي على رابط لإعادة تعيين كلمة المرور.'));

        $this->reset('email');
    }
}; ?>

<div>
    <h1 class="text-lg font-bold text-ink-900 mb-1">{{ __('نسيت كلمة المرور؟') }}</h1>
    <p class="text-sm text-ink-500 mb-6">{{ __('أدخل بريدك الإلكتروني وسنرسل لك رابطاً لإعادة تعيين كلمة المرور') }}</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendResetLink" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-semibold text-ink-700 mb-1.5">{{ __('البريد الإلكتروني') }}</label>
            <div class="relative">
                <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-ink-400">
                    <x-icon name="mail" class="w-4 h-4" />
                </span>
                <input wire:model="email" id="email" type="email" name="email" required autofocus
                    class="input" placeholder="you@example.com" />
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-xs text-red-600" />
        </div>

        <button type="submit" class="btn-primary w-full justify-center" wire:loading.attr="disabled" wire:target="sendResetLink">
            <span wire:loading.remove wire:target="sendResetLink">{{ __('إرسال رابط إعادة التعيين') }}</span>
            <span wire:loading wire:target="sendResetLink">{{ __('جارٍ الإرسال...') }}</span>
        </button>
    </form>

    <p class="mt-5 text-center text-xs text-ink-400">
        {{ __('تذكرت كلمة المرور؟') }} <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ __('تسجيل الدخول') }}</a>
    </p>
</div>

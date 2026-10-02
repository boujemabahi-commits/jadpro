<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        // Phones often capitalise the first letter or add a trailing space after the email.
        $this->form->email = mb_strtolower(trim($this->form->email));

        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        // Platform admins live in /admin; everyone else in the tenant app.
        $home = auth()->user()->isPlatformAdmin() ? route('admin.signups', absolute: false) : route('dashboard', absolute: false);

        $this->redirectIntended(default: $home);
    }
}; ?>

<div>
    <h1 class="text-lg font-bold text-ink-900 mb-1">{{ __('تسجيل الدخول') }}</h1>
    <p class="text-sm text-ink-500 mb-6">{{ __('أدخل بيانات حسابك للوصول إلى لوحة التحكم') }}</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-semibold text-ink-700 mb-1.5">{{ __('البريد الإلكتروني') }}</label>
            <div class="relative">
                <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-ink-400">
                    <x-icon name="mail" class="w-4 h-4" />
                </span>
                <input wire:model="form.email" id="email" type="email" name="email" required autofocus autocomplete="username"
                    class="input" placeholder="you@example.com" />
            </div>
            <x-input-error :messages="$errors->get('form.email')" class="mt-1.5 text-xs text-red-600" />
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-sm font-semibold text-ink-700">{{ __('كلمة المرور') }}</label>
                <a href="{{ route('password.request') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">{{ __('نسيت كلمة المرور؟') }}</a>
            </div>
            <div class="relative">
                <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-ink-400">
                    <x-icon name="lock" class="w-4 h-4" />
                </span>
                <input wire:model="form.password" id="password" type="password" name="password" required autocomplete="current-password"
                    class="input" placeholder="••••••••" />
            </div>
            <x-input-error :messages="$errors->get('form.password')" class="mt-1.5 text-xs text-red-600" />
        </div>

        <label for="remember" class="flex items-center gap-2 cursor-pointer">
            <input wire:model="form.remember" id="remember" type="checkbox" name="remember"
                class="rounded border-ink-300 text-brand-600 focus:ring-brand-500" />
            <span class="text-sm text-ink-600">{{ __('تذكرني') }}</span>
        </label>

        <button type="submit" class="btn-primary w-full justify-center" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">{{ __('تسجيل الدخول') }}</span>
            <span wire:loading wire:target="login">{{ __('جارٍ الدخول...') }}</span>
        </button>
    </form>

    <p class="mt-5 text-center text-xs text-ink-400">{{ __('مركز جديد؟') }} <a href="{{ route('register-center') }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ __('سجّل مركزك') }}</a></p>
</div>

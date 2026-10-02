<form wire:submit="save">
    <h3 class="font-bold text-ink-800 mb-5">{{ __('الحساب الشخصي') }}</h3>
    <div class="flex items-center gap-4 mb-6">
        <x-avatar :name="$name ?: (auth()->user()->name ?? '')" size="xl" />
        <div>
            <p class="font-semibold text-ink-800">{{ $name }}</p>
            <p class="text-xs text-ink-400">{{ auth()->user()->roleLabel() }} · {{ auth()->user()->tenant?->name }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-ink-700 mb-1.5">{{ __('الاسم الكامل') }}</label>
            <input type="text" wire:model="name" class="input ps-3" autocomplete="name" />
            @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-ink-700 mb-1.5">{{ __('البريد الإلكتروني') }}</label>
            <input type="email" wire:model="email" class="input ps-3" autocomplete="email" />
            @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="mt-6 pt-6 border-t border-ink-100">
        <h4 class="text-sm font-bold text-ink-800 mb-1">{{ __('تغيير كلمة المرور') }}</h4>
        <p class="text-xs text-ink-400 mb-4">{{ __('اترك الحقول فارغة إذا كنت لا تريد تغييرها. تغيير كلمة المرور أو البريد الإلكتروني يتطلب إدخال كلمة المرور الحالية.') }}</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1.5">{{ __('كلمة المرور الحالية') }}</label>
                <input type="password" wire:model="current_password" class="input ps-3" placeholder="••••••••" autocomplete="current-password" />
                @error('current_password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1.5">{{ __('كلمة المرور الجديدة') }}</label>
                <input type="password" wire:model="password" class="input ps-3" placeholder="••••••••" autocomplete="new-password" />
                @error('password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1.5">{{ __('تأكيد كلمة المرور') }}</label>
                <input type="password" wire:model="password_confirmation" class="input ps-3" placeholder="••••••••" autocomplete="new-password" />
            </div>
        </div>
    </div>

    <div class="flex justify-end mt-6">
        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
            <x-icon name="check" class="w-4 h-4" /> {{ __('حفظ التغييرات') }}
        </button>
    </div>
</form>

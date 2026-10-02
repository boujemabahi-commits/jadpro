<header class="sticky top-0 z-30 h-16 sm:h-20 flex items-center gap-3 bg-white/80 backdrop-blur border-b border-ink-100 px-4 sm:px-6">
    <button type="button" class="btn-icon lg:hidden" x-on:click="$store.ui.sidebarOpen = true" aria-label="{{ __('فتح القائمة') }}">
        <x-icon name="menu" class="w-5 h-5" />
    </button>

    <button
        type="button"
        x-on:click="$dispatch('open-search-modal')"
        class="hidden sm:flex items-center gap-2.5 w-full max-w-sm rounded-xl border border-ink-200 bg-ink-50 px-3.5 py-2.5 text-sm text-ink-400 hover:border-ink-300 hover:bg-white transition-colors focus-ring"
    >
        <x-icon name="search" class="w-4 h-4" />
        <span class="flex-1 text-start">{{ __('بحث عن طالب، دورة، أستاذ...') }}</span>
        <kbd class="ltr-nums text-[10px] font-mono border border-ink-200 rounded px-1.5 py-0.5 bg-white">Ctrl K</kbd>
    </button>

    <button type="button" class="btn-icon sm:hidden ms-auto" x-on:click="$dispatch('open-search-modal')" aria-label="{{ __('بحث') }}">
        <x-icon name="search" class="w-5 h-5" />
    </button>

    <div class="flex items-center gap-1.5 sm:gap-2 ms-auto">
        @can('manage-students')
            <a href="/students?add=1" class="btn-primary hidden md:inline-flex">
                <x-icon name="plus" class="w-4 h-4" />
                {{ __('إضافة طالب') }}
            </a>
        @endcan

        <livewire:notifications.bell />

        <div class="w-px h-6 bg-ink-200 hidden sm:block"></div>

        <!-- User menu -->
        <div class="relative" x-data="{ open: false }">
            <button type="button" x-on:click="open = !open" class="flex items-center gap-2.5 rounded-xl px-1.5 py-1 hover:bg-ink-100 transition-colors">
                <x-avatar :name="auth()->user()?->name ?? ''" size="sm" />
                <span class="hidden sm:flex flex-col items-start leading-tight">
                    <span class="text-sm font-semibold text-ink-800">{{ auth()->user()?->name }}</span>
                    <span class="text-xs text-ink-400">{{ auth()->user()?->roleLabel() }}</span>
                </span>
                <x-icon name="chevron-down" class="w-4 h-4 text-ink-400 hidden sm:block" />
            </button>
            <x-dropdown-panel align="end" width="w-52">
                <x-menu-item icon="user-round-plus" href="/settings">{{ __('الملف الشخصي') }}</x-menu-item>
                <x-menu-item icon="settings" href="/settings">{{ __('الإعدادات') }}</x-menu-item>
                <div class="my-1 border-t border-ink-100"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-2.5 px-3.5 py-2.5 text-sm font-medium transition-colors text-red-600 hover:bg-red-50">
                        <x-icon name="log-out" class="w-4 h-4" />
                        <span>{{ __('تسجيل الخروج') }}</span>
                    </button>
                </form>
            </x-dropdown-panel>
        </div>
    </div>
</header>

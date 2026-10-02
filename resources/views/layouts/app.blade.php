<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locales::direction() }}" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="theme-color" content="#059669" />
    <title>@yield('title', $title ?? __('الرئيسية')) · JadPro</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    @if (session('toast'))
        <script>window.__flashToast = @json(session('toast'));</script>
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-ink-50 font-sans text-ink-800 antialiased" x-data>

    <x-sidebar />

    <div class="lg:ps-72 min-h-full flex flex-col">
        <x-header />

        <main class="flex-1 w-full max-w-[1600px] mx-auto p-4 sm:p-6 lg:p-8">
            @yield('content')
        </main>

        <footer class="px-4 sm:px-6 lg:p-8 pb-6 text-center text-xs text-ink-400">
            © {{ date('Y') }} <span class="font-semibold text-ink-500">JadPro</span> — {{ auth()->user()?->tenant?->name }}. {{ __('جميع الحقوق محفوظة') }}.
            <span class="block sm:inline sm:ms-2">{{ __('صُنع بواسطة') }} <span class="font-semibold text-ink-500">IAM Agency</span></span>
        </footer>
    </div>

    <x-toast-container />

    <x-modal id="search-modal" :title="__('البحث الشامل')" max-width="lg">
        {{-- البحث يفتح قائمة الطلاب مفلترة بالاسم/الهاتف (كان الحقل لا يفعل شيئاً) --}}
        @can('manage-students')
            <form method="GET" action="/students" class="relative mb-4">
                <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-ink-400"><x-icon name="search" class="w-4 h-4" /></span>
                <input type="search" name="q" autofocus class="input" placeholder="{{ __('ابحث عن طالب بالاسم أو الهاتف...') }}" />
            </form>
        @endcan
        <p class="text-xs font-semibold text-ink-400 mb-2">{{ __('روابط سريعة') }}</p>
        <div class="space-y-1">
            @can('manage-students') <x-menu-item icon="users" href="/students">{{ __('الطلاب') }}</x-menu-item> @endcan
            @can('manage-courses-groups-teachers') <x-menu-item icon="graduation-cap" href="/teachers">{{ __('الأساتذة') }}</x-menu-item> @endcan
            @can('manage-courses-groups-teachers') <x-menu-item icon="book-open" href="/courses">{{ __('الدورات') }}</x-menu-item> @endcan
            @can('manage-schedule') <x-menu-item icon="calendar-days" href="/schedule">{{ __('الجدول') }}</x-menu-item> @endcan
            @can('manage-payments') <x-menu-item icon="wallet" href="/payments">{{ __('أداءات الطلاب') }}</x-menu-item> @endcan
            @can('manage-expenses') <x-menu-item icon="receipt" href="/expenses">{{ __('المصاريف') }}</x-menu-item> @endcan
            <x-menu-item icon="settings" href="/settings">{{ __('الإعدادات') }}</x-menu-item>
        </div>
    </x-modal>

    <script>
        // Ctrl+K / ⌘K opens the global search (the shortcut was shown in the header but did nothing).
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                window.dispatchEvent(new CustomEvent('open-search-modal'));
            }
        });
    </script>
    <script defer src="/vendor/chart.js"></script>
    @livewireScripts
    @yield('scripts')
</body>
</html>

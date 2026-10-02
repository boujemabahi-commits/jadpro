@php
    // Items carrying a 'can' key are hidden from anyone without that permission
    // (the routes enforce the same permission, so this is purely navigation).
    $user = auth()->user();
    $sections = [
        [
            'label' => __('إدارة المركز'),
            'items' => [
                ['label' => __('الطلاب'), 'href' => '/students', 'icon' => 'users', 'can' => 'manage-students'],
                ['label' => __('الأساتذة'), 'href' => '/teachers', 'icon' => 'graduation-cap', 'can' => 'manage-courses-groups-teachers'],
                ['label' => __('الدورات'), 'href' => '/courses', 'icon' => 'book-open', 'can' => 'manage-courses-groups-teachers'],
                ['label' => __('المجموعات'), 'href' => '/groups', 'icon' => 'users-round', 'can' => 'manage-courses-groups-teachers'],
                ['label' => __('التسجيلات'), 'href' => '/enrollments', 'icon' => 'clipboard-list', 'can' => 'manage-enrollments'],
                ['label' => __('الباقات'), 'href' => '/packages', 'icon' => 'package', 'can' => 'manage-enrollments'],
                ['label' => __('الحضور'), 'href' => '/attendance', 'icon' => 'calendar-check', 'can' => 'manage-attendance'],
                ['label' => __('الجدول'), 'href' => '/schedule', 'icon' => 'calendar-days', 'can' => 'manage-schedule'],
            ],
        ],
        [
            'label' => __('المالية'),
            'items' => [
                ['label' => __('أداءات الطلاب'), 'href' => '/payments', 'icon' => 'wallet', 'can' => 'manage-payments'],
                ['label' => __('المصاريف'), 'href' => '/expenses', 'icon' => 'receipt', 'can' => 'manage-expenses'],
                ['label' => __('أجور الأساتذة'), 'href' => '/salaries', 'icon' => 'banknote', 'can' => 'manage-salaries'],
            ],
        ],
        [
            'label' => __('التقارير'),
            'items' => [
                ['label' => __('التقارير'), 'href' => '/reports', 'icon' => 'bar-chart-3', 'can' => 'view-reports'],
                ['label' => __('الإحصائيات'), 'href' => '/statistics', 'icon' => 'line-chart', 'can' => 'view-reports'],
            ],
        ],
        [
            'label' => __('النظام'),
            'items' => [
                ['label' => __('الإشعارات'), 'href' => '/notifications', 'icon' => 'bell'],
                ['label' => __('الإعدادات'), 'href' => '/settings', 'icon' => 'settings'],
            ],
        ],
    ];
@endphp

<!-- Mobile backdrop -->
<div
    x-show="$store.ui.sidebarOpen"
    x-cloak
    x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    x-on:click="$store.ui.sidebarOpen = false"
    class="fixed inset-0 z-40 bg-ink-950/50 lg:hidden"
    style="display:none;"
></div>

<aside
    class="fixed inset-y-0 start-0 z-50 w-72 flex flex-col bg-ink-950 text-white transition-transform duration-300 lg:translate-x-0"
    :class="$store.ui.sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
>
    <!-- Brand -->
    <div class="flex items-center gap-3 px-5 h-20 border-b border-white/10 shrink-0">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-brand-500 text-white font-extrabold text-lg shrink-0">{{ mb_substr(auth()->user()?->tenant?->name ?? 'T', 0, 1) }}</span>
        <div class="leading-tight min-w-0">
            <p class="font-extrabold text-base truncate">{{ auth()->user()?->tenant?->name ?? 'JadPro' }}</p>
            <p class="text-[11px] text-ink-400 truncate">{{ auth()->user()?->tenant?->setting('tagline') ?? 'منصة JadPro' }}</p>
        </div>
        <button type="button" class="btn-icon text-ink-300 hover:text-white hover:bg-white/10 ms-auto lg:hidden" x-on:click="$store.ui.sidebarOpen = false" aria-label="{{ __('إغلاق القائمة') }}">
            <x-icon name="x" class="w-5 h-5" />
        </button>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        <a href="/dashboard" class="nav-link {{ is_active_route('/dashboard') ? 'active' : '' }}">
            <x-icon name="layout-dashboard" class="w-[18px] h-[18px]" />
            {{ __('الرئيسية') }}
        </a>

        @foreach ($sections as $section)
            @php $visible = array_filter($section['items'], fn ($i) => empty($i['can']) || $user?->can($i['can'])); @endphp
            @continue (empty($visible))
            <div>
                <p class="px-3 mb-1.5 text-[11px] font-bold uppercase tracking-wider text-ink-500">{{ $section['label'] }}</p>
                <div class="space-y-1">
                    @foreach ($visible as $item)
                        <a href="{{ $item['href'] }}" class="nav-link {{ is_active_route($item['href']) ? 'active' : '' }}">
                            <x-icon :name="$item['icon']" class="w-[18px] h-[18px]" />
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <!-- Footer / user -->
    <div class="border-t border-white/10 p-4 shrink-0">
        <p class="text-[11px] text-ink-500 px-1 mb-2.5">{{ auth()->user()?->tenant?->name }}</p>
        <div class="flex items-center gap-3">
            <x-avatar :name="auth()->user()?->name ?? ''" size="md" />
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-white truncate">{{ auth()->user()?->name }}</p>
                <p class="text-xs text-ink-400 truncate">{{ auth()->user()?->roleLabel() }}</p>
            </div>
            <button type="button" class="btn-icon text-ink-400 hover:text-white hover:bg-white/10" onclick="document.getElementById('sidebar-logout-form').submit()" aria-label="{{ __('تسجيل الخروج') }}">
                <x-icon name="log-out" class="w-[18px] h-[18px]" />
            </button>
            <form id="sidebar-logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
                @csrf
            </form>
        </div>
    </div>
</aside>

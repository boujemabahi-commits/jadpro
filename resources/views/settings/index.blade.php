@extends('layouts.app')

@section('title', __('الإعدادات'))

@section('content')

<x-page-header :title="__('الإعدادات')" :subtitle="__('إدارة إعدادات المركز والحساب والنظام')" />

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6" x-data="{ tab: 'center' }">
    <!-- Side nav -->
    <div class="lg:col-span-1">
        <div class="card p-2 flex lg:flex-col gap-1 overflow-x-auto">
            @foreach ([
                ['key' => 'center', 'label' => __('معلومات المركز'), 'icon' => 'building-2'],
                ['key' => 'account', 'label' => __('الحساب'), 'icon' => 'user-round-plus'],
                ['key' => 'users', 'label' => __('المستخدمون'), 'icon' => 'users', 'can' => 'manage-users'],
                ['key' => 'roles', 'label' => __('الصلاحيات'), 'icon' => 'shield-check', 'can' => 'manage-users'],
                ['key' => 'notifications', 'label' => __('الإشعارات'), 'icon' => 'bell'],
                ['key' => 'language', 'label' => __('اللغة'), 'icon' => 'languages'],
                ['key' => 'appearance', 'label' => __('المظهر'), 'icon' => 'palette'],
            ] as $item)
                @continue (isset($item['can']) && ! auth()->user()->can($item['can']))
                <button
                    type="button"
                    x-on:click="tab = '{{ $item['key'] }}'"
                    :class="tab === '{{ $item['key'] }}' ? 'bg-brand-50 text-brand-700' : 'text-ink-600 hover:bg-ink-100'"
                    class="flex items-center gap-2.5 whitespace-nowrap px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-colors w-full"
                >
                    <x-icon :name="$item['icon']" class="w-4 h-4" />
                    {{ $item['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Panels -->
    <div class="lg:col-span-3 space-y-6">
        <!-- معلومات المركز -->
        <div x-show="tab === 'center'" class="card p-6">
            <livewire:settings.center-profile />
        </div>

        <!-- الحساب -->
        <div x-show="tab === 'account'" class="card p-6">
            <livewire:settings.account />
        </div>

        @can('manage-users')
            <!-- المستخدمون -->
            <div x-show="tab === 'users'" class="card overflow-hidden">
                <livewire:settings.team />
            </div>

            <!-- الصلاحيات -->
            <div x-show="tab === 'roles'" class="card overflow-hidden">
                <livewire:settings.roles />
            </div>
        @endcan

        <!-- الإشعارات -->
        <div x-show="tab === 'notifications'" class="card p-6">
            <h3 class="font-bold text-ink-800 mb-1">{{ __('الإشعارات') }}</h3>
            <p class="text-xs text-ink-400 mb-5">{{ __('تصل الإشعارات تلقائياً، وكل مستخدم يرى فقط ما يخص صلاحياته.') }}</p>
            <div class="divide-y divide-ink-100">
                @foreach ([
                    ['label' => __('تسجيل طالب جديد'), 'desc' => __('عند كل تسجيل جديد في دورة'), 'can' => 'manage-enrollments'],
                    ['label' => __('استلام دفعة'), 'desc' => __('عند تسجيل دفعة جديدة من طالب'), 'can' => 'manage-payments'],
                    ['label' => __('مصروف جديد'), 'desc' => __('عند تسجيل مصروف جديد'), 'can' => 'manage-expenses'],
                    ['label' => __('أجور الأساتذة'), 'desc' => __('عند صرف أجرة أستاذ'), 'can' => 'manage-salaries'],
                ] as $pref)
                    @php $receives = auth()->user()->can($pref['can']); @endphp
                    <div class="flex items-center justify-between gap-3 py-3.5">
                        <div>
                            <p class="text-sm font-semibold text-ink-800">{{ $pref['label'] }}</p>
                            <p class="text-xs text-ink-400 mt-0.5">{{ $pref['desc'] }}</p>
                        </div>
                        <x-status-badge :label="$receives ? __('تصلك') : __('لا تخص صلاحياتك')" :tone="$receives ? 'success' : 'neutral'" />
                    </div>
                @endforeach
            </div>
            <a href="{{ route('notifications.index') }}" class="btn-secondary mt-5">{{ __('عرض كل الإشعارات') }}</a>
        </div>

        <!-- اللغة -->
        <div x-show="tab === 'language'" class="card p-6">
            <h3 class="font-bold text-ink-800 mb-5">{{ __('اللغة والمنطقة') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach (\App\Support\Locales::LABELS as $code => $label)
                    <a href="{{ route('lang.switch', $code) }}"
                        class="rounded-xl border-2 p-4 text-center {{ app()->getLocale() === $code ? 'border-brand-500 bg-brand-50' : 'border-ink-200 hover:border-ink-300' }}">
                        <p class="font-bold {{ app()->getLocale() === $code ? 'text-ink-800' : 'text-ink-700' }}">{{ $label }}</p>
                        <p class="text-xs mt-1 {{ app()->getLocale() === $code ? 'text-brand-600' : 'text-ink-400' }}">
                            {{ app()->getLocale() === $code ? __('مفعّلة') : __('اختيار') }}
                        </p>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- المظهر -->
        <div x-show="tab === 'appearance'" class="card p-6">
            <h3 class="font-bold text-ink-800 mb-5">{{ __('المظهر') }}</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <button type="button" class="rounded-xl border-2 border-brand-500 p-3 text-center" data-toast="{{ __('تم اختيار المظهر الفاتح') }}">
                    <div class="h-14 rounded-lg bg-white border border-ink-200 mb-2"></div>
                    <p class="text-sm font-semibold text-ink-800">{{ __('فاتح') }}</p>
                </button>
                <button type="button" class="rounded-xl border border-ink-200 p-3 text-center hover:border-ink-300" data-toast="{{ __('سيتم دعم المظهر الداكن قريباً') }}">
                    <div class="h-14 rounded-lg bg-ink-900 mb-2"></div>
                    <p class="text-sm font-semibold text-ink-700">{{ __('داكن (قريباً)') }}</p>
                </button>
                <button type="button" class="rounded-xl border border-ink-200 p-3 text-center hover:border-ink-300" data-toast="{{ __('سيتم دعم المظهر التلقائي قريباً') }}">
                    <div class="h-14 rounded-lg bg-gradient-to-br from-white to-ink-900 mb-2"></div>
                    <p class="text-sm font-semibold text-ink-700">{{ __('تلقائي (قريباً)') }}</p>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

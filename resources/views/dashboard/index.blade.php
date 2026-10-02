@extends('layouts.app')

@section('title', __('الرئيسية'))

@section('content')
@php
    $statusTone = ['مكتمل' => 'success', 'جزئي' => 'warning', 'غير مؤدي' => 'danger'];
@endphp

<x-page-header :title="__('مرحباً بك مجدداً، :name 👋', ['name' => auth()->user()->name])" :subtitle="__('إليك نظرة سريعة على نشاط مركزك اليوم — :date', ['date' => ar_date()])">
    @can('manage-students') <a href="/students?add=1" class="btn-secondary"><x-icon name="plus" class="w-4 h-4" /> {{ __('إضافة طالب') }}</a> @endcan
    @can('manage-enrollments') <a href="/enrollments" class="btn-primary"><x-icon name="clipboard-list" class="w-4 h-4" /> {{ __('تسجيل جديد') }}</a> @endcan
</x-page-header>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 {{ count($stats) >= 6 ? 'xl:grid-cols-6' : 'xl:grid-cols-5' }} gap-4 mb-6">
    @foreach ($stats as $s)
        <x-stat-card :icon="$s['icon']" :label="$s['label']" :value="$s['value']" :tone="$s['tone']" :trend="$s['trend']" />
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    @if ($revenue)
        <div class="lg:col-span-2">
            <x-chart-container id="dashboardRevenueChart" :title="__('نظرة عامة على الإيرادات')" :subtitle="__('الإيرادات والمصاريف — آخر 6 أشهر')">
                <span class="text-xs font-semibold text-ink-400">MAD</span>
            </x-chart-container>
        </div>
    @endif
    <div class="{{ $revenue ? '' : 'lg:col-span-3' }}">
        <x-chart-container id="dashboardGrowthChart" :title="__('نمو الطلاب')" :subtitle="__('آخر 6 أشهر')" />
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Recent enrollments -->
    @if ($recentEnrollments !== null)
    <div class="lg:col-span-2 card overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-ink-100">
            <h3 class="text-sm font-bold text-ink-800">{{ __('أحدث التسجيلات') }}</h3>
            @can('manage-enrollments') <a href="/enrollments" class="text-xs font-semibold text-brand-600 hover:text-brand-700">{{ __('عرض الكل') }}</a> @endcan
        </div>
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full">
                <thead class="bg-ink-50 border-b border-ink-100">
                    <tr>
                        <th class="table-head-cell">{{ __('الطالب') }}</th>
                        <th class="table-head-cell">{{ __('الدورة') }}</th>
                        <th class="table-head-cell">{{ __('المجموعة') }}</th>
                        <th class="table-head-cell">{{ __('التاريخ') }}</th>
                        <th class="table-head-cell">{{ __('الحالة') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($recentEnrollments as $r)
                        <tr class="hover:bg-ink-50/70 transition-colors">
                            <td class="table-cell">
                                <a @can('manage-students') href="/students/{{ $r->student_id }}" @endcan class="flex items-center gap-2.5 group">
                                    <x-avatar :name="$r->student?->name ?? '—'" size="sm" />
                                    <span class="font-semibold text-ink-800 group-hover:text-brand-700">{{ $r->student?->name ?? '—' }}</span>
                                </a>
                            </td>
                            <td class="table-cell">{{ $r->course?->name ?? '—' }}</td>
                            <td class="table-cell">{{ $r->group?->name ?? '—' }}</td>
                            <td class="table-cell ltr-nums">{{ $r->date->format('Y-m-d') }}</td>
                            <td class="table-cell"><x-status-badge :label="$r->status" :tone="$statusTone[$r->status] ?? 'neutral'" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-cell text-center text-ink-400 py-8">{{ __('لا توجد تسجيلات بعد.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="sm:hidden divide-y divide-ink-100">
            @forelse ($recentEnrollments as $r)
                <a @can('manage-students') href="/students/{{ $r->student_id }}" @endcan class="p-4 flex items-center gap-3">
                    <x-avatar :name="$r->student?->name ?? '—'" size="sm" />
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-ink-800 truncate">{{ $r->student?->name ?? '—' }}</p>
                        <p class="text-xs text-ink-400 truncate">{{ $r->course?->name ?? '—' }} · {{ $r->group?->name ?? '—' }}</p>
                    </div>
                    <x-status-badge :label="$r->status" :tone="$statusTone[$r->status] ?? 'neutral'" />
                </a>
            @empty
                <p class="p-6 text-center text-sm text-ink-400">{{ __('لا توجد تسجيلات بعد.') }}</p>
            @endforelse
        </div>
    </div>

    @endif

    <!-- Upcoming classes -->
    <div class="card overflow-hidden {{ $recentEnrollments === null ? 'lg:col-span-3' : '' }}">
        <div class="flex items-center justify-between px-5 py-4 border-b border-ink-100">
            <h3 class="text-sm font-bold text-ink-800">{{ __('الحصص القادمة اليوم') }}</h3>
            @can('manage-schedule') <a href="/schedule" class="text-xs font-semibold text-brand-600 hover:text-brand-700">{{ __('الجدول') }}</a> @endcan
        </div>
        <div class="divide-y divide-ink-100">
            @forelse ($upcoming as $class)
                <div class="flex items-center gap-3 px-5 py-3.5">
                    <div class="text-center shrink-0 w-14">
                        <p class="ltr-nums text-xs font-bold text-brand-700">{{ trim(explode('-', $class->time)[0]) }}</p>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-ink-800 truncate">{{ $class->course?->name ?? '—' }}</p>
                        <p class="text-xs text-ink-400 truncate">{{ $class->teacher?->name ?? '—' }} · {{ $class->room }}</p>
                    </div>
                </div>
            @empty
                <x-empty-state icon="calendar-days" :title="__('لا توجد حصص اليوم')" :description="__('تحقق من الجدول الأسبوعي لمعرفة الحصص القادمة.')" />
            @endforelse
        </div>
    </div>
</div>

<!-- Quick actions -->
<div class="card p-5">
    <h3 class="text-sm font-bold text-ink-800 mb-4">{{ __('إجراءات سريعة') }}</h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        @can('manage-students') <x-quick-action-card icon="user-round-plus" :label="__('إضافة طالب')" href="/students?add=1" tone="brand" /> @endcan
        @can('manage-courses-groups-teachers') <x-quick-action-card icon="graduation-cap" :label="__('إضافة أستاذ')" href="/teachers" tone="blue" /> @endcan
        @can('manage-courses-groups-teachers') <x-quick-action-card icon="book-open" :label="__('إنشاء دورة')" href="/courses" tone="violet" /> @endcan
        @can('manage-courses-groups-teachers') <x-quick-action-card icon="users-round" :label="__('إنشاء مجموعة')" href="/groups" tone="amber" /> @endcan
        @can('manage-enrollments') <x-quick-action-card icon="clipboard-list" :label="__('تسجيل طالب')" href="/enrollments" tone="rose" /> @endcan
        @can('manage-schedule') <x-quick-action-card icon="calendar-check" :label="__('إضافة حصة')" href="/schedule" tone="teal" /> @endcan
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) return;
    Chart.defaults.font.family = 'Cairo';

    @if ($revenue)
    const months = {!! json_encode($revenue['labels'], JSON_UNESCAPED_UNICODE) !!};

    new Chart(document.getElementById('dashboardRevenueChart'), {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                { label: {!! json_encode(__('الإيرادات'), JSON_UNESCAPED_UNICODE) !!}, data: {!! json_encode($revenue['revenue']) !!}, backgroundColor: '#10b981', borderRadius: 6, maxBarThickness: 28 },
                { label: {!! json_encode(__('المصاريف'), JSON_UNESCAPED_UNICODE) !!}, data: {!! json_encode($revenue['expenses']) !!}, backgroundColor: '#e5e7eb', borderRadius: 6, maxBarThickness: 28 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true }, x: { reverse: {{ \App\Support\Locales::direction() === 'rtl' ? 'true' : 'false' }} } },
        },
    });

    @endif

    new Chart(document.getElementById('dashboardGrowthChart'), {
        type: 'line',
        data: {
            labels: {!! json_encode($growth['labels'], JSON_UNESCAPED_UNICODE) !!},
            datasets: [{ label: {!! json_encode(__('إجمالي الطلاب'), JSON_UNESCAPED_UNICODE) !!}, data: {!! json_encode($growth['total']) !!}, borderColor: '#059669', backgroundColor: 'rgba(5,150,105,.12)', fill: true, tension: 0.35 }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { reverse: {{ \App\Support\Locales::direction() === 'rtl' ? 'true' : 'false' }} } },
        },
    });
});
</script>
@endsection

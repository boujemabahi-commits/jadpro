@extends('layouts.app')

@section('title', __('التقارير'))

@section('content')
@php
    $toneClasses = [
        'brand' => 'bg-brand-50 text-brand-600', 'blue' => 'bg-blue-50 text-blue-600', 'violet' => 'bg-violet-50 text-violet-600',
        'rose' => 'bg-rose-50 text-rose-600', 'amber' => 'bg-amber-50 text-amber-600', 'teal' => 'bg-teal-50 text-teal-600',
    ];
@endphp

<x-page-header :title="__('التقارير')" :subtitle="__('تقارير شاملة حول جميع أنشطة المركز')">
    <button type="button" class="btn-secondary" data-toast="{{ __('جاري تصدير التقرير... (تجريبي)') }}">
        <x-icon name="download" class="w-4 h-4" /> {{ __('تصدير PDF') }}
    </button>
</x-page-header>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    @foreach ($sections as $s)
        <div class="card p-5 flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl {{ $toneClasses[$s['tone']] }}">
                    <x-icon :name="$s['icon']" class="w-5 h-5" />
                </span>
                <h3 class="font-bold text-ink-800">{{ $s['title'] }}</h3>
            </div>
            <div class="space-y-2">
                @foreach ($s['stats'] as $label => $value)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-500">{{ $label }}</span>
                        <span class="ltr-nums font-semibold text-ink-800">{{ $value }}</span>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn-secondary justify-center mt-1" data-toast="{{ __('فتح التقرير التفصيلي (تجريبي)') }}">
                {{ __('عرض التفاصيل') }}
                <x-icon name="arrow-left" class="w-4 h-4" />
            </button>
        </div>
    @endforeach
</div>

<x-chart-container id="reportsRevenueChart" :title="__('الإيرادات والمصاريف')" :subtitle="__('آخر 6 أشهر')">
    <span class="text-xs text-ink-400">MAD</span>
</x-chart-container>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('reportsRevenueChart');
    if (!ctx || !window.Chart) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($revenue['labels'], JSON_UNESCAPED_UNICODE) !!},
            datasets: [
                { label: {!! json_encode(__('الإيرادات'), JSON_UNESCAPED_UNICODE) !!}, data: {!! json_encode($revenue['revenue']) !!}, backgroundColor: '#10b981', borderRadius: 6 },
                { label: {!! json_encode(__('المصاريف'), JSON_UNESCAPED_UNICODE) !!}, data: {!! json_encode($revenue['expenses']) !!}, backgroundColor: '#f43f5e', borderRadius: 6 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', rtl: {{ \App\Support\Locales::direction() === 'rtl' ? 'true' : 'false' }}, labels: { font: { family: 'Cairo' } } } },
            scales: { y: { beginAtZero: true }, x: { reverse: {{ \App\Support\Locales::direction() === 'rtl' ? 'true' : 'false' }} } },
        },
    });
});
</script>
@endsection

@extends('layouts.app')

@section('title', $student->name)

@section('content')
@php
    $enrollment = $student->enrollments()->with(['course', 'group'])->latest('date')->latest('id')->first();
    $attendanceHistory = $student->attendanceRecords()->latest('date')->limit(8)->get();
    $financeTone = ['مؤدي' => 'success', 'جزئي' => 'warning', 'غير مؤدي' => 'danger'];
    $statusTone = ['نشط' => 'success', 'متوقف' => 'neutral'];
    $stateTone = ['حاضر' => 'success', 'متأخر' => 'warning', 'غائب' => 'danger'];
@endphp

<div class="mb-6 flex items-center gap-2 text-sm text-ink-500">
    <a href="{{ route('students.index') }}" class="hover:text-brand-600 font-medium">{{ __('الطلاب') }}</a>
    <x-icon name="chevron-left" class="w-3.5 h-3.5" />
    <span class="text-ink-700 font-medium">{{ $student->name }}</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Profile card -->
    <div class="lg:col-span-1">
        <div class="card p-6 flex flex-col items-center text-center">
            <x-avatar :name="$student->name" size="xl" />
            <h1 class="mt-4 text-lg font-bold text-ink-900">{{ $student->name }}</h1>
            <p class="text-sm text-ink-400">{{ $student->course?->name ?? '—' }} · {{ $student->group?->name ?? '—' }}</p>
            <div class="flex items-center gap-1.5 mt-3">
                <x-status-badge :label="$student->enrollment_status" :tone="$statusTone[$student->enrollment_status] ?? 'neutral'" />
                <x-status-badge :label="$student->financial_status" :tone="$financeTone[$student->financial_status] ?? 'neutral'" />
            </div>

            <div class="w-full mt-6 pt-6 border-t border-ink-100 space-y-3 text-start">
                <div class="flex items-center gap-3 text-sm">
                    <x-icon name="phone" class="w-4 h-4 text-ink-400" />
                    <span class="ltr-nums text-ink-700">{{ $student->phone }}</span>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <x-icon name="mail" class="w-4 h-4 text-ink-400" />
                    <span class="text-ink-700 truncate">{{ $student->email ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <x-icon name="map-pin" class="w-4 h-4 text-ink-400" />
                    <span class="text-ink-700">{{ $student->city ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <x-icon name="calendar-days" class="w-4 h-4 text-ink-400" />
                    <span class="ltr-nums text-ink-700">{{ __('تاريخ التسجيل') }}: {{ $student->registered_at->format('Y-m-d') }}</span>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <x-icon name="phone" class="w-4 h-4 text-ink-400" />
                    <span class="ltr-nums text-ink-700">{{ __('ولي الأمر') }}: {{ $student->guardian_phone ?? '—' }}</span>
                </div>
            </div>

            <div class="w-full mt-6 grid grid-cols-2 gap-2">
                <a href="{{ route('enrollments.index', ['student' => $student->id]) }}" class="btn-primary col-span-2 justify-center"><x-icon name="clipboard-list" class="w-4 h-4" /> {{ __('تسجيل في دورة') }}</a>
                <a href="{{ route('students.index', ['edit' => $student->id]) }}" class="btn-secondary"><x-icon name="pencil" class="w-4 h-4" /> {{ __('تعديل') }}</a>
                <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('{{ __('هل أنت متأكد من حذف هذا الطالب؟') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger w-full"><x-icon name="trash-2" class="w-4 h-4" /> {{ __('حذف') }}</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="lg:col-span-2" x-data="{ tab: 'info' }">
        <div class="card p-1.5 flex items-center gap-1 mb-5 overflow-x-auto">
            <button type="button" x-on:click="tab = 'info'" :class="tab === 'info' ? 'bg-brand-600 text-white' : 'text-ink-500 hover:bg-ink-100'" class="flex-1 whitespace-nowrap px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors">{{ __('المعلومات الشخصية') }}</button>
            <button type="button" x-on:click="tab = 'enrollments'" :class="tab === 'enrollments' ? 'bg-brand-600 text-white' : 'text-ink-500 hover:bg-ink-100'" class="flex-1 whitespace-nowrap px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors">{{ __('التسجيلات') }}</button>
            <button type="button" x-on:click="tab = 'attendance'" :class="tab === 'attendance' ? 'bg-brand-600 text-white' : 'text-ink-500 hover:bg-ink-100'" class="flex-1 whitespace-nowrap px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors">{{ __('الحضور') }}</button>
            @can('manage-payments')
            <button type="button" x-on:click="tab = 'payments'" :class="tab === 'payments' ? 'bg-brand-600 text-white' : 'text-ink-500 hover:bg-ink-100'" class="flex-1 whitespace-nowrap px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors">{{ __('المدفوعات') }}</button>
            @endcan
            <button type="button" x-on:click="tab = 'activity'" :class="tab === 'activity' ? 'bg-brand-600 text-white' : 'text-ink-500 hover:bg-ink-100'" class="flex-1 whitespace-nowrap px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors">{{ __('النشاط') }}</button>
        </div>

        <!-- المعلومات الشخصية (real data) -->
        <div x-show="tab === 'info'" class="card p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div><p class="text-xs text-ink-400 mb-1">{{ __('الاسم الكامل') }}</p><p class="text-sm font-semibold text-ink-800">{{ $student->name }}</p></div>
            <div><p class="text-xs text-ink-400 mb-1">{{ __('الجنس') }}</p><p class="text-sm font-semibold text-ink-800">{{ match ($student->gender) { 'male' => __('ذكر'), 'female' => __('أنثى'), default => '—' } }}</p></div>
            <div><p class="text-xs text-ink-400 mb-1">{{ __('رقم الهاتف') }}</p><p class="ltr-nums text-sm font-semibold text-ink-800">{{ $student->phone }}</p></div>
            <div><p class="text-xs text-ink-400 mb-1">{{ __('البريد الإلكتروني') }}</p><p class="text-sm font-semibold text-ink-800">{{ $student->email ?? '—' }}</p></div>
            <div><p class="text-xs text-ink-400 mb-1">{{ __('المدينة') }}</p><p class="text-sm font-semibold text-ink-800">{{ $student->city ?? '—' }}</p></div>
            <div><p class="text-xs text-ink-400 mb-1">{{ __('هاتف ولي الأمر') }}</p><p class="ltr-nums text-sm font-semibold text-ink-800">{{ $student->guardian_phone ?? '—' }}</p></div>
            <div><p class="text-xs text-ink-400 mb-1">{{ __('الدورة الحالية') }}</p><p class="text-sm font-semibold text-ink-800">{{ $student->course?->name ?? '—' }}</p></div>
            <div><p class="text-xs text-ink-400 mb-1">{{ __('المجموعة') }}</p><p class="text-sm font-semibold text-ink-800">{{ $student->group?->name ?? '—' }}</p></div>
        </div>

        <!-- التسجيلات (real — latest enrollment) -->
        <div x-show="tab === 'enrollments'" class="card overflow-hidden">
            @if ($enrollment)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-ink-50 border-b border-ink-100">
                            <tr>
                                <th class="table-head-cell">{{ __('الدورة') }}</th>
                                <th class="table-head-cell">{{ __('المجموعة') }}</th>
                                <th class="table-head-cell">{{ __('الباقة') }}</th>
                                <th class="table-head-cell">{{ __('السعر') }}</th>
                                <th class="table-head-cell">{{ __('الخصم') }}</th>
                                <th class="table-head-cell">{{ __('المتبقي') }}</th>
                                <th class="table-head-cell">{{ __('الحالة') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            <tr>
                                <td class="table-cell">{{ $enrollment->course?->name ?? '—' }}</td>
                                <td class="table-cell">{{ $enrollment->group?->name ?? '—' }}</td>
                                <td class="table-cell">{{ $enrollment->pack_label }}</td>
                                <td class="table-cell ltr-nums">{{ mad($enrollment->price) }}</td>
                                <td class="table-cell ltr-nums">{{ mad($enrollment->discount) }}</td>
                                <td class="table-cell ltr-nums">{{ mad($enrollment->remaining) }}</td>
                                <td class="table-cell"><x-status-badge :label="$enrollment->status" :tone="['مكتمل' => 'success', 'جزئي' => 'warning', 'غير مؤدي' => 'danger'][$enrollment->status] ?? 'neutral'" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @else
                <x-empty-state icon="clipboard-list" :title="__('لا توجد تسجيلات')" />
            @endif
        </div>

        <!-- الحضور (real — last 8 sessions) -->
        <div x-show="tab === 'attendance'" class="card overflow-hidden">
            <div class="divide-y divide-ink-100">
                @forelse ($attendanceHistory as $h)
                    <div class="flex items-center justify-between px-5 py-3.5">
                        <span class="ltr-nums text-sm text-ink-600">{{ $h->date->format('Y-m-d') }}</span>
                        <x-status-badge :label="$h->state" :tone="$stateTone[$h->state] ?? 'neutral'" />
                    </div>
                @empty
                    <x-empty-state icon="calendar-check" :title="__('لا يوجد سجل حضور بعد')" />
                @endforelse
            </div>
        </div>

        @can('manage-payments')
        <!-- المدفوعات (real — enrollment summary + latest payments with receipts) -->
        <div x-show="tab === 'payments'" class="card overflow-hidden">
            @if ($enrollment)
                <div class="p-5 grid grid-cols-3 gap-4 border-b border-ink-100">
                    <div><p class="text-xs text-ink-400 mb-1">{{ __('السعر الإجمالي') }}</p><p class="ltr-nums font-bold text-ink-800">{{ mad($enrollment->net) }}</p></div>
                    <div><p class="text-xs text-ink-400 mb-1">{{ __('المؤدى') }}</p><p class="ltr-nums font-bold text-emerald-600">{{ mad($enrollment->paid) }}</p></div>
                    <div><p class="text-xs text-ink-400 mb-1">{{ __('المتبقي') }}</p><p class="ltr-nums font-bold text-red-600">{{ mad($enrollment->remaining) }}</p></div>
                </div>
            @endif
            <div class="p-5">
                <a href="{{ route('payments.index', ['student' => $student->id]) }}" class="btn-primary w-full justify-center">
                    <x-icon name="wallet" class="w-4 h-4" /> {{ __('تسجيل دفعة جديدة') }}
                </a>
                @php $studentPayments = $student->payments()->latest('date')->latest('id')->limit(5)->get(); @endphp
                @if ($studentPayments->isNotEmpty())
                    <div class="mt-4 divide-y divide-ink-100 border-t border-ink-100">
                        @foreach ($studentPayments as $pay)
                            <div class="flex items-center justify-between py-2.5 text-sm">
                                <span class="ltr-nums text-ink-500">{{ $pay->date->format('Y-m-d') }} · {{ __($pay->method) }}</span>
                                <span class="flex items-center gap-2">
                                    <span class="ltr-nums font-semibold text-emerald-700">{{ mad($pay->amount) }}</span>
                                    <a href="{{ route('payments.receipt', $pay) }}" target="_blank" class="btn-icon !p-1" aria-label="{{ __('إيصال') }}" title="{{ __('طباعة الإيصال') }}"><x-icon name="download" class="w-3.5 h-3.5" /></a>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        @endcan

        <!-- النشاط: the student's real history (registration, enrollments, payments, absences), newest first -->
        @php
            $activity = collect();
            if ($student->registered_at) {
                $activity->push(['date' => $student->registered_at, 'tone' => 'bg-amber-500 ring-amber-100', 'title' => __('إضافة الطالب إلى النظام'), 'meta' => null]);
            }
            foreach ($student->enrollments()->with(['course' => fn ($q) => $q->withTrashed(), 'package'])->get() as $en) {
                $activity->push(['date' => $en->date, 'tone' => 'bg-brand-500 ring-brand-100', 'title' => __('تم تسجيل الطالب في :course', ['course' => $en->course?->name ?? '—']), 'meta' => $en->pack_label]);
            }
            if (auth()->user()->can('manage-payments')) {
                foreach ($student->payments()->latest('date')->limit(20)->get() as $pay) {
                    $activity->push(['date' => $pay->date, 'tone' => 'bg-emerald-500 ring-emerald-100', 'title' => __('أداء :amount', ['amount' => mad($pay->amount)]), 'meta' => $pay->method]);
                }
            }
            foreach ($student->attendanceRecords()->where('state', '!=', 'حاضر')->latest('date')->limit(20)->get() as $rec) {
                $activity->push(['date' => $rec->date, 'tone' => $rec->state === 'غائب' ? 'bg-red-500 ring-red-100' : 'bg-blue-500 ring-blue-100', 'title' => __($rec->state), 'meta' => null]);
            }
            $activity = $activity->filter(fn ($a) => $a['date'])->sortByDesc(fn ($a) => $a['date']->timestamp)->take(25)->values();
        @endphp
        <div x-show="tab === 'activity'" class="card p-5">
            @if ($activity->isEmpty())
                <p class="text-sm text-ink-400 text-center py-6">{{ __('لا يوجد نشاط بعد') }}</p>
            @else
                <ol class="relative border-e-2 border-ink-100 me-3 space-y-6">
                    @foreach ($activity as $a)
                        <li class="relative pe-6">
                            <span class="absolute -end-[9px] top-0 w-4 h-4 rounded-full ring-4 {{ $a['tone'] }}"></span>
                            <p class="text-sm font-semibold text-ink-800">{{ $a['title'] }}</p>
                            <p class="text-xs text-ink-400 mt-0.5"><span class="ltr-nums">{{ $a['date']->format('Y-m-d') }}</span>@if ($a['meta']) · {{ __($a['meta']) }}@endif</p>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>
</div>
@endsection

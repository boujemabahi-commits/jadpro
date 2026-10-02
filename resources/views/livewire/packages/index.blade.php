@php
    $statusTone = ['مكتمل' => 'success', 'جزئي' => 'warning', 'غير مؤدي' => 'danger'];
@endphp
<div>
    <x-page-header :title="__('الباقات')" :subtitle="__('باقات الاشتراك متعددة الأشهر، ومن يدفع بالباقة ومن يدفع شهرياً')">
        <button type="button" class="btn-primary" wire:click="openCreate">
            <x-icon name="plus" class="w-4 h-4" /> {{ __('باقة جديدة') }}
        </button>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <button type="button" class="text-start" wire:click="showStudents('pack')">
            <x-stat-card icon="package" :label="__('طلاب مسجلون في باقات')" :value="$stats['pack']" tone="violet" />
        </button>
        <button type="button" class="text-start" wire:click="showStudents('monthly')">
            <x-stat-card icon="calendar-days" :label="__('طلاب بالدفع الشهري')" :value="$stats['monthly']" tone="blue" />
        </button>
        <x-stat-card icon="layers" :label="__('باقات مفعّلة')" :value="$stats['packages']" tone="brand" />
        <x-stat-card icon="triangle-alert" :label="__('باقات تنتهي خلال 15 يوماً')" :value="$stats['ending']" tone="amber" />
    </div>

    <div class="flex items-center gap-1 mb-5 border-b border-ink-100">
        <button type="button" wire:click="$set('tab', 'packages')"
            class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors {{ $tab === 'packages' ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-700' }}">
            {{ __('الباقات') }} <span class="ltr-nums text-xs text-ink-400">({{ $packages->count() }})</span>
        </button>
        <button type="button" wire:click="$set('tab', 'students')"
            class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors {{ $tab === 'students' ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-700' }}">
            {{ __('الطلاب حسب نوع الاشتراك') }}
        </button>
    </div>

    @if ($tab === 'packages')
        @if ($packages->isEmpty())
            <div class="card">
                <x-empty-state icon="package" :title="__('لا توجد باقات بعد')" :description="__('أنشئ باقة (مثلاً: 3 أشهر بتخفيض 10%) لتظهر عند تسجيل الطلاب.')" />
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($packages as $p)
                    <div class="card p-5 flex flex-col {{ $p->is_active ? '' : 'opacity-60' }}" wire:key="pk-{{ $p->id }}">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <h3 class="font-bold text-ink-900 truncate">{{ $p->name }}</h3>
                                <p class="text-xs text-ink-400 mt-0.5">{{ $p->duration_label }} · {{ $p->course?->name ?? __('كل الدورات') }}</p>
                            </div>
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-violet-50 text-violet-600 shrink-0"><x-icon name="package" class="w-5 h-5" /></span>
                        </div>

                        <div class="mb-3">
                            @if ($p->price !== null)
                                <p class="text-2xl font-extrabold text-ink-900 ltr-nums">{{ mad($p->price) }}</p>
                                <p class="text-xs text-ink-400">{{ __('سعر إجمالي ثابت') }} · {{ __('≈ :amount شهرياً', ['amount' => mad((int) round($p->price / max(1, $p->duration_months)))]) }}</p>
                            @else
                                <p class="text-2xl font-extrabold text-ink-900 ltr-nums">-{{ $p->discount_percent }}%</p>
                                <p class="text-xs text-ink-400">
                                    {{ __('تخفيض على السعر الشهري × :months', ['months' => $p->duration_months]) }}
                                    @if ($p->course) · {{ mad($p->priceFor($p->course)) }} @endif
                                </p>
                            @endif
                        </div>

                        @if ($p->description)
                            <p class="text-sm text-ink-500 mb-3">{{ $p->description }}</p>
                        @endif

                        <div class="mt-auto flex items-center justify-between gap-2 pt-3 border-t border-ink-100">
                            <button type="button" class="text-sm font-semibold text-brand-700 hover:text-brand-800" wire:click="showStudents('{{ $p->id }}')">
                                {{ trans_choice('{1} :count طالب|[0,*] :count طالب', $p->students_count) }}
                            </button>
                            <div class="flex items-center gap-1">
                                @unless ($p->is_active) <span class="text-[11px] font-semibold text-ink-400 me-1">{{ __('متوقفة') }}</span> @endunless
                                <button type="button" class="btn-icon" wire:click="openEdit({{ $p->id }})" aria-label="{{ __('تعديل') }}" title="{{ __('تعديل') }}"><x-icon name="pencil" class="w-4 h-4" /></button>
                                <button type="button" class="btn-icon {{ $p->is_active ? 'text-amber-600' : 'text-emerald-600' }}" wire:click="toggleActive({{ $p->id }})"
                                    aria-label="{{ $p->is_active ? __('إيقاف') : __('تفعيل') }}" title="{{ $p->is_active ? __('إيقاف') : __('تفعيل') }}">
                                    <x-icon :name="$p->is_active ? 'pause' : 'play'" class="w-4 h-4" />
                                </button>
                                <button type="button" class="btn-icon text-red-600" wire:click="confirmDelete({{ $p->id }})" aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}"><x-icon name="trash-2" class="w-4 h-4" /></button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        <x-filter-bar>
            <div class="relative flex-1">
                <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-ink-400"><x-icon name="search" class="w-4 h-4" /></span>
                <input type="text" wire:model.live.debounce.400ms="search" class="input" placeholder="{{ __('البحث باسم الطالب...') }}" />
            </div>
            <select class="select sm:w-52" wire:model.live="planFilter">
                <option value="">{{ __('كل أنواع الاشتراك') }}</option>
                <option value="monthly">{{ __('دفع شهري') }}</option>
                <option value="pack">{{ __('كل الباقات') }}</option>
                @foreach ($packages as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
        </x-filter-bar>

        <div class="card overflow-hidden relative">
            <div wire:loading.delay class="absolute inset-0 bg-white/60 z-10 flex items-center justify-center">
                <x-icon name="loader-circle" class="w-6 h-6 text-brand-600 animate-spin" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-ink-50 border-b border-ink-100">
                        <tr>
                            <th class="table-head-cell">{{ __('الطالب') }}</th>
                            <th class="table-head-cell">{{ __('الدورة') }}</th>
                            <th class="table-head-cell">{{ __('نوع الاشتراك') }}</th>
                            <th class="table-head-cell">{{ __('البداية') }}</th>
                            <th class="table-head-cell">{{ __('ينتهي / الاستحقاق') }}</th>
                            <th class="table-head-cell">{{ __('المبلغ') }}</th>
                            <th class="table-head-cell">{{ __('المتبقي') }}</th>
                            <th class="table-head-cell">{{ __('الحالة') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @forelse ($enrollments as $r)
                            @php $daysLeft = $r->due_date ? today()->diffInDays($r->due_date, false) : null; @endphp
                            <tr class="hover:bg-ink-50/70 transition-colors" wire:key="en-{{ $r->id }}">
                                <td class="table-cell whitespace-nowrap">
                                    @if ($r->student)
                                        <a @can('manage-students') href="{{ route('students.show', $r->student) }}" @endcan class="flex items-center gap-3 group">
                                            <x-avatar :name="$r->student->name" size="sm" />
                                            <span class="font-semibold text-ink-800 group-hover:text-brand-700">{{ $r->student->name }}</span>
                                        </a>
                                    @else
                                        <span class="text-ink-400">{{ __('طالب محذوف') }}</span>
                                    @endif
                                </td>
                                <td class="table-cell">{{ $r->course?->name ?? '—' }}</td>
                                <td class="table-cell whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $r->is_pack ? 'bg-violet-50 text-violet-700' : 'bg-blue-50 text-blue-700' }}">
                                        <x-icon :name="$r->is_pack ? 'package' : 'calendar-days'" class="w-3 h-3" />
                                        {{ $r->is_pack ? $r->pack_label : __('دفع شهري') }}
                                    </span>
                                </td>
                                <td class="table-cell ltr-nums whitespace-nowrap">{{ $r->date->format('Y-m-d') }}</td>
                                <td class="table-cell whitespace-nowrap">
                                    @if ($r->due_date)
                                        <span class="ltr-nums {{ $daysLeft < 0 ? 'text-red-600 font-semibold' : ($daysLeft <= 15 ? 'text-amber-600 font-semibold' : '') }}">{{ $r->due_date->format('Y-m-d') }}</span>
                                        <span class="block text-[11px] {{ $daysLeft < 0 ? 'text-red-500' : 'text-ink-400' }}">
                                            {{ $daysLeft < 0 ? __('منتهية منذ :days يوم', ['days' => abs($daysLeft)]) : __('بعد :days يوم', ['days' => $daysLeft]) }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="table-cell ltr-nums whitespace-nowrap">{{ mad($r->net) }}</td>
                                <td class="table-cell ltr-nums whitespace-nowrap">{{ mad($r->remaining) }}</td>
                                <td class="table-cell whitespace-nowrap"><x-status-badge :label="$r->status" :tone="$statusTone[$r->status] ?? 'neutral'" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-empty-state icon="users" :title="__('لا يوجد طلاب')" :description="__('لا يوجد تسجيل مطابق لهذا النوع من الاشتراك.')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $enrollments->links() }}</div>
    @endif

    @if ($showModal)
        <div x-data x-init="$el.querySelector('input')?.focus()" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-ink-950/50" wire:click="closeModal"></div>
            <div class="relative w-full max-w-md bg-white rounded-2xl shadow-popover overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-ink-100">
                    <h2 class="text-base font-bold text-ink-900">{{ $editingId ? __('تعديل الباقة') : __('باقة جديدة') }}</h2>
                    <button type="button" class="btn-icon" wire:click="closeModal" aria-label="{{ __('إغلاق') }}"><x-icon name="x" class="w-4 h-4" /></button>
                </div>
                <form wire:submit="save" class="p-5 max-h-[75vh] overflow-y-auto space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1.5">{{ __('اسم الباقة') }}</label>
                        <input type="text" wire:model="name" class="input ps-3" placeholder="{{ __('مثال: باقة الفصل الدراسي') }}" />
                        @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-1.5">{{ __('المدة') }}</label>
                            <select class="select" wire:model.live="duration_months">
                                @foreach ($durations as $m => $label)
                                    <option value="{{ $m }}">{{ __($label) }}</option>
                                @endforeach
                            </select>
                            @error('duration_months') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-1.5">{{ __('الدورة') }}</label>
                            <select class="select" wire:model.live="course_id">
                                <option value="">{{ __('كل الدورات') }}</option>
                                @foreach ($courses as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @error('course_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1.5">{{ __('طريقة التسعير') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 rounded-xl border px-3 py-2.5 cursor-pointer {{ $pricing === 'discount' ? 'border-brand-500 bg-brand-50/50' : 'border-ink-200' }}">
                                <input type="radio" wire:model.live="pricing" value="discount" class="text-brand-600" /> <span class="text-sm">{{ __('تخفيض %') }}</span>
                            </label>
                            <label class="flex items-center gap-2 rounded-xl border px-3 py-2.5 cursor-pointer {{ $pricing === 'fixed' ? 'border-brand-500 bg-brand-50/50' : 'border-ink-200' }}">
                                <input type="radio" wire:model.live="pricing" value="fixed" class="text-brand-600" /> <span class="text-sm">{{ __('سعر ثابت') }}</span>
                            </label>
                        </div>
                    </div>

                    @if ($pricing === 'discount')
                        <div wire:key="pricing-discount">
                            <label class="block text-sm font-medium text-ink-700 mb-1.5">{{ __('نسبة التخفيض (%)') }}</label>
                            <input type="number" min="0" max="100" wire:model.live.debounce.400ms="discount_percent" class="input ps-3 ltr-nums" />
                            <p class="text-[11px] text-ink-400 mt-1">{{ __('السعر = سعر الدورة الشهري × عدد الأشهر − التخفيض') }}</p>
                            @error('discount_percent') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div wire:key="pricing-fixed">
                            <label class="block text-sm font-medium text-ink-700 mb-1.5">{{ __('السعر الإجمالي للباقة (MAD)') }}</label>
                            <input type="number" min="0" wire:model.live.debounce.400ms="price" class="input ps-3 ltr-nums" />
                            @error('price') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if ($preview)
                        <div class="rounded-xl bg-ink-50 p-3 text-sm">
                            <div class="flex justify-between"><span class="text-ink-500">{{ __('بالدفع الشهري') }}</span><span class="ltr-nums line-through text-ink-400">{{ mad($preview['full']) }}</span></div>
                            <div class="flex justify-between font-bold text-ink-900"><span>{{ __('ثمن الباقة') }}</span><span class="ltr-nums">{{ mad($preview['total']) }}</span></div>
                            <div class="flex justify-between text-xs text-ink-500"><span>{{ __('ما يعادل شهرياً') }}</span><span class="ltr-nums">{{ mad($preview['monthly']) }}</span></div>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1.5">{{ __('وصف (اختياري)') }}</label>
                        <input type="text" wire:model="description" class="input ps-3" placeholder="{{ __('مثال: يشمل حصص المراجعة') }}" />
                        @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-ink-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-ink-300 text-brand-600" /> {{ __('باقة مفعّلة (تظهر عند تسجيل الطلاب)') }}
                    </label>
                    @if ($editingId)
                        <p class="text-[11px] text-ink-400">{{ __('تغيير السعر لا يمس الطلاب المسجلين سابقاً، بل التسجيلات الجديدة فقط.') }}</p>
                    @endif

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary" wire:click="closeModal">{{ __('إلغاء') }}</button>
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <x-icon name="check" class="w-4 h-4" /> {{ $editingId ? __('حفظ التغييرات') : __('إضافة') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-ink-950/50" wire:click="cancelDelete"></div>
            <div class="relative w-full max-w-sm bg-white rounded-2xl shadow-popover overflow-hidden p-5 text-center">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-50 text-red-600 mx-auto mb-3"><x-icon name="trash-2" class="w-5 h-5" /></span>
                <h2 class="text-base font-bold text-ink-900 mb-1.5">{{ __('حذف الباقة') }}</h2>
                <p class="text-sm text-ink-500 mb-5">{{ __('لن تظهر في التسجيلات الجديدة. الطلاب المسجلون فيها يبقون كما هم.') }}</p>
                <div class="flex items-center justify-center gap-2">
                    <button type="button" class="btn-secondary" wire:click="cancelDelete">{{ __('إلغاء') }}</button>
                    <button type="button" class="btn-danger" wire:click="delete"><x-icon name="trash-2" class="w-4 h-4" /> {{ __('حذف') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>

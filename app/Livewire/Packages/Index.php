<?php

namespace App\Livewire\Packages;

use App\Livewire\Concerns\DropsDeletedReferences;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Package;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * الباقات: the center's subscription packages, and who pays how — students on a package vs
 * students paying month by month. Tenant-scoped through BelongsToTenant on every model.
 */
class Index extends Component
{
    use DropsDeletedReferences, WithPagination;

    /** packages | students */
    #[Url(history: true)]
    public string $tab = 'packages';

    /** '' all · monthly · pack · a package id */
    #[Url(as: 'plan', history: true)]
    public string $planFilter = '';

    #[Url(as: 'q', history: true)]
    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $confirmingDeleteId = null;

    // Form fields
    public string $name = '';

    public $duration_months = 3;

    public $course_id = '';

    /** fixed = a total price · discount = % off the monthly price × months */
    public string $pricing = 'discount';

    public $price = '';

    public $discount_percent = 10;

    public string $description = '';

    public bool $is_active = true;

    protected function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'duration_months' => ['required', 'integer', 'min:2', 'max:12'],
            'course_id' => ['nullable', Rule::exists('courses', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'pricing' => ['required', Rule::in(['fixed', 'discount'])],
            'price' => ['exclude_unless:pricing,fixed', 'required', 'integer', 'min:0', 'max:10000000'],
            'discount_percent' => ['exclude_unless:pricing,discount', 'required', 'integer', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('اسم الباقة'),
            'duration_months' => __('المدة'),
            'course_id' => __('الدورة'),
            'price' => __('السعر الإجمالي'),
            'discount_percent' => __('نسبة التخفيض'),
            'description' => __('وصف'),
        ];
    }

    public function updatingTab(): void
    {
        $this->resetPage();
    }

    public function updatingPlanFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /** Clicking a package's "students" count opens the students tab filtered to it. */
    public function showStudents(string $plan): void
    {
        $this->tab = 'students';
        $this->planFilter = $plan;
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $p = Package::findOrFail($id);
        $this->resetForm();
        $this->editingId = $p->id;
        $this->name = $p->name;
        $this->duration_months = $p->duration_months;
        $this->course_id = (string) ($this->existingId(Course::class, $p->course_id) ?? '');
        $this->pricing = $p->price !== null ? 'fixed' : 'discount';
        $this->price = $p->price ?? '';
        $this->discount_percent = $p->discount_percent;
        $this->description = (string) $p->description;
        $this->is_active = $p->is_active;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    protected function resetForm(): void
    {
        $this->reset(['name', 'duration_months', 'course_id', 'pricing', 'price', 'discount_percent', 'description', 'is_active']);
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $data = $this->validate();

        $attributes = [
            'name' => $data['name'],
            'duration_months' => (int) $data['duration_months'],
            'course_id' => $data['course_id'] ?: null,
            'price' => $data['pricing'] === 'fixed' ? (int) $data['price'] : null,
            'discount_percent' => $data['pricing'] === 'discount' ? (int) $data['discount_percent'] : 0,
            'description' => trim((string) $data['description']) ?: null,
            'is_active' => (bool) $data['is_active'],
        ];

        if ($this->editingId) {
            // Existing enrollments keep the price they were sold at; only new ones use the change.
            Package::findOrFail($this->editingId)->update($attributes);
            $this->dispatch('toast', message: __('تم تحديث الباقة بنجاح'));
        } else {
            Package::create($attributes);
            $this->dispatch('toast', message: __('تمت إضافة الباقة بنجاح'));
        }

        $this->closeModal();
    }

    public function toggleActive(int $id): void
    {
        $p = Package::findOrFail($id);
        $p->is_active = ! $p->is_active;
        $p->save();
        $this->dispatch('toast', message: $p->is_active ? __('تم تفعيل الباقة') : __('تم إيقاف الباقة (لن تظهر في التسجيلات الجديدة)'));
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        if ($this->confirmingDeleteId) {
            // Soft delete: students already on it keep showing its name.
            Package::findOrFail($this->confirmingDeleteId)->delete();
            $this->dispatch('toast', message: __('تم حذف الباقة'));
        }
        $this->confirmingDeleteId = null;
    }

    public function render()
    {
        Enrollment::rolloverDue();

        $packages = Package::with('course')
            ->withCount(['enrollments as students_count'])
            ->orderByDesc('is_active')->orderBy('duration_months')->orderBy('name')
            ->get();

        $soon = today()->addDays(15);
        $stats = [
            'monthly' => Enrollment::monthly()->count(),
            'pack' => Enrollment::pack()->count(),
            'packages' => $packages->where('is_active', true)->count(),
            'ending' => Enrollment::pack()->whereNotNull('due_date')
                ->whereDate('due_date', '>=', today())->whereDate('due_date', '<=', $soon)->count(),
        ];

        $enrollments = null;
        if ($this->tab === 'students') {
            $enrollments = Enrollment::query()
                ->planFilter($this->planFilter ?: null)
                ->search($this->search)
                ->with(['student', 'course', 'group', 'package'])
                ->orderByRaw('due_date IS NULL')->orderBy('due_date')
                ->paginate(15);
        }

        $previewCourse = $this->course_id ? Course::find($this->course_id) : null;
        $preview = null;
        if ($this->showModal) {
            $draft = new Package([
                'duration_months' => max(1, (int) $this->duration_months),
                'price' => $this->pricing === 'fixed' && $this->price !== '' ? (int) $this->price : null,
                'discount_percent' => $this->pricing === 'discount' ? (int) $this->discount_percent : 0,
            ]);
            $preview = $previewCourse ? [
                'total' => $draft->priceFor($previewCourse),
                'monthly' => $draft->monthlyEquivalentFor($previewCourse),
                'full' => (int) $previewCourse->price * max(1, (int) $this->duration_months),
            ] : null;
        }

        return view('livewire.packages.index', [
            'packages' => $packages,
            'stats' => $stats,
            'enrollments' => $enrollments,
            'courses' => Course::orderBy('name')->get(['id', 'name', 'price']),
            'durations' => array_filter(Enrollment::PACKS, fn ($m) => $m > 1, ARRAY_FILTER_USE_KEY),
            'preview' => $preview,
        ])->extends('layouts.app')->section('content')->title(__('الباقات'));
    }
}

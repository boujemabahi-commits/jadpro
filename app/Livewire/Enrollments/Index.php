<?php

namespace App\Livewire\Enrollments;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Notification;
use App\Models\Package;
use App\Models\Student;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'course', history: true)]
    public string $courseFilter = '';

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    /** '' all · monthly · pack · a package id */
    #[Url(as: 'plan', history: true)]
    public string $planFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $confirmingDeleteId = null;

    // Form fields — remaining/status are derived from price, discount and paid.
    // Flow: course → group → student (the student list depends on the course).
    public string $studentSearch = '';

    /** Set when the modal was opened from a student (?student=ID): the student is fixed. */
    public ?int $pinnedStudentId = null;

    public ?int $student_id = null;

    public ?int $course_id = null;

    public ?int $group_id = null;

    public string $date = '';

    public string $due_date = '';

    public $price = 0;

    public $discount = 0;

    public $paid = 0;

    public $duration_months = 1;

    /**
     * Subscription type chosen in the form: 'm' = monthly, 'p{id}' = one of the center's
     * packages, 'd{n}' = a custom n-month package. Drives duration_months / package_id / price.
     */
    public string $plan = 'm';

    public ?int $package_id = null;

    protected function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'course_id' => ['required', Rule::exists('courses', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'student_id' => [
                'required',
                Rule::exists('students', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
                // One enrollment per student per course.
                Rule::unique('enrollments', 'student_id')
                    ->where('tenant_id', $tenantId)
                    ->where('course_id', $this->course_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->editingId),
            ],
            'group_id' => [
                'nullable',
                Rule::exists('groups', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')
                    ->when($this->course_id, fn ($rule) => $rule->where('course_id', $this->course_id)),
            ],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', function ($attribute, $value, $fail) {
                if ($value && $this->date && $value < $this->date) {
                    $fail(__('يجب أن يكون تاريخ الاستحقاق بعد تاريخ التسجيل أو مساوياً له.'));
                }
            }],
            'price' => ['required', 'integer', 'min:0'],
            'discount' => ['required', 'integer', 'min:0', 'lte:price'],
            'paid' => ['required', 'integer', 'min:0'],
            'duration_months' => ['required', 'integer', Rule::in(array_keys(Enrollment::PACKS))],
            'package_id' => ['nullable', Rule::exists('packages', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'student_id' => __('الطالب'),
            'course_id' => __('الدورة'),
            'group_id' => __('المجموعة'),
            'date' => __('تاريخ التسجيل'),
            'due_date' => __('تاريخ الاستحقاق'),
            'price' => __('السعر'),
            'discount' => __('الخصم'),
            'paid' => __('المبلغ المؤدى'),
            'duration_months' => __('نوع الاشتراك'),
        ];
    }

    public function mount(): void
    {
        $studentId = request()->integer('student');
        if ($studentId && ($student = Student::find($studentId))) {
            $this->openCreate();
            $this->pinnedStudentId = $student->id;
            $this->student_id = $student->id;
        }
    }

    public function unpinStudent(): void
    {
        $this->pinnedStudentId = null;
        $this->student_id = null;
    }

    /** Default the due date to the end of the chosen package while creating. */
    public function updatedDate($value): void
    {
        if (! $this->editingId && $value) {
            $this->due_date = \Carbon\Carbon::parse($value)->addMonths($this->duration_months ?: 1)->toDateString();
        }
    }

    /** Choosing a subscription type sets the duration, the package and the suggested price/due date. */
    public function updatedPlan($value): void
    {
        $this->applyPlan((string) $value, recomputeDates: ! $this->editingId);
    }

    protected function applyPlan(string $plan, bool $recomputeDates = true): void
    {
        $package = null;
        if (str_starts_with($plan, 'p') && ctype_digit(substr($plan, 1))) {
            $package = Package::find((int) substr($plan, 1));
        }

        if ($package) {
            $this->package_id = $package->id;
            $this->duration_months = $package->duration_months;
        } elseif (str_starts_with($plan, 'd') && ctype_digit(substr($plan, 1))) {
            $this->package_id = null;
            $this->duration_months = max(1, min(12, (int) substr($plan, 1)));
        } else {
            $this->plan = 'm';
            $this->package_id = null;
            $this->duration_months = 1;
        }

        $this->suggestPrice();

        if ($recomputeDates && $this->date) {
            $this->due_date = \Carbon\Carbon::parse($this->date)->addMonths(max(1, (int) $this->duration_months))->toDateString();
        }
    }

    /** Suggested price: the package's price for the course, else monthly price × months. */
    protected function suggestPrice(): void
    {
        $course = $this->course_id ? Course::find($this->course_id) : null;
        $package = $this->package_id ? Package::find($this->package_id) : null;

        if ($package) {
            $this->price = $package->priceFor($course);
        } elseif ($course) {
            $this->price = (int) $course->price * max(1, (int) $this->duration_months);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCourseFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPlanFilter(): void
    {
        $this->resetPage();
    }

    /** Course drives the group list, the eligible students and the default price. */
    public function updatedCourseId($value): void
    {
        $course = $value ? Course::find($value) : null;

        if ($this->group_id && (! $course || ! Group::where('id', $this->group_id)->where('course_id', $course->id)->exists())) {
            $this->group_id = null;
        }

        // A package reserved for another course no longer applies.
        if ($this->package_id && ($p = Package::find($this->package_id)) && $p->course_id && $p->course_id !== ($course?->id)) {
            $this->plan = 'm';
            $this->package_id = null;
            $this->duration_months = 1;
        }

        if (! $this->editingId) {
            if (! $this->pinnedStudentId) {
                $this->student_id = null;
            }
            $this->studentSearch = '';
            $this->suggestPrice();
        }
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->date = now()->toDateString();
        $this->due_date = now()->addMonth()->toDateString();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $enrollment = Enrollment::findOrFail($id);
        $this->editingId = $enrollment->id;
        $this->student_id = $enrollment->student_id;
        $this->course_id = $enrollment->course_id;
        $this->group_id = $enrollment->group_id;
        $this->date = $enrollment->date->toDateString();
        $this->due_date = $enrollment->due_date?->toDateString() ?? '';
        $this->price = (int) $enrollment->price;
        $this->discount = (int) $enrollment->discount;
        $this->paid = $enrollment->paid;
        $this->duration_months = (int) $enrollment->duration_months;
        $this->package_id = $enrollment->package_id;
        $this->plan = $enrollment->package_id ? 'p'.$enrollment->package_id : ((int) $enrollment->duration_months > 1 ? 'd'.(int) $enrollment->duration_months : 'm');
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
        $this->reset(['studentSearch', 'pinnedStudentId', 'student_id', 'course_id', 'group_id', 'date', 'due_date', 'price', 'discount', 'paid', 'duration_months', 'plan', 'package_id']);
    }

    public function save(): void
    {
        $data = $this->validate();

        $attributes = [
            'student_id' => $data['student_id'],
            'course_id' => $data['course_id'],
            'group_id' => $data['group_id'] ?: null,
            'date' => $data['date'],
            'due_date' => $data['due_date'] ?: null,
            'price' => (int) $data['price'],
            'discount' => (int) $data['discount'],
            'duration_months' => (int) $data['duration_months'],
            'package_id' => $data['package_id'] ?: null,
        ] + Enrollment::settle((int) $data['price'], (int) $data['discount'], (int) $data['paid']);

        if ($this->editingId) {
            $enrollment = Enrollment::findOrFail($this->editingId);

            // Settling a period that is due (or overdue) in full moves the
            // deadline to the end of the paid package.
            $dueDate = $attributes['due_date'] ? \Carbon\Carbon::parse($attributes['due_date']) : null;
            if ($attributes['status'] === 'مكتمل' && $enrollment->status !== 'مكتمل' && $dueDate && $dueDate->lte(today())) {
                $attributes['due_date'] = $dueDate->addMonths(max(1, $attributes['duration_months']))->toDateString();
            }

            $enrollment->update($attributes);
            $this->dispatch('toast', message: __('تم تحديث التسجيل بنجاح'));
        } else {
            $enrollment = Enrollment::create($attributes);
            $enrollment->load(['student', 'course']);
            Notification::notify([
                'title' => __('تم تسجيل طالب جديد'),
                'body' => __('تم تسجيل :student في دورة :course.', ['student' => $enrollment->student?->name, 'course' => $enrollment->course?->name]),
                'icon' => 'user-round-plus',
                'tone' => 'brand',
                'category' => __('التسجيلات'),
            ]);
            $this->dispatch('notification-created');
            $this->dispatch('toast', message: __('تم تسجيل الطالب بنجاح'));
        }

        $enrollment->syncStudent();

        $this->closeModal();
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
            Enrollment::findOrFail($this->confirmingDeleteId)->delete();
            $this->dispatch('toast', message: __('تم حذف التسجيل بنجاح'));
        }
        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    public function render()
    {
        Enrollment::rolloverDue();

        $enrollments = Enrollment::query()
            ->search($this->search)
            ->courseFilter($this->courseFilter ? (int) $this->courseFilter : null)
            ->when($this->statusFilter === 'overdue',
                fn ($q) => $q->overdue(),
                fn ($q) => $q->statusFilter($this->statusFilter))
            ->planFilter($this->planFilter ?: null)
            ->with(['student', 'course', 'group', 'package'])
            ->latest('date')->latest('id')
            ->paginate(12);

        $stats = [
            'total' => Enrollment::count(),
            'this_month' => Enrollment::whereMonth('date', now()->month)->whereYear('date', now()->year)->count(),
            'total_value' => (int) Enrollment::selectRaw('COALESCE(SUM(price - discount), 0) as v')->value('v'),
            'remaining' => (int) Enrollment::sum('remaining'),
            'overdue' => Enrollment::overdue()->count(),
        ];

        $courses = Course::with('teacher')->orderBy('name')->get();

        $groups = $this->course_id
            ? Group::with('teacher')->where('course_id', $this->course_id)->orderBy('name')->get()
            : collect();

        $selectedCourse = $this->course_id ? $courses->firstWhere('id', (int) $this->course_id) : null;
        $selectedGroup = $this->group_id ? $groups->firstWhere('id', (int) $this->group_id) : null;

        // Students become selectable once a course is chosen: everyone not already
        // enrolled in that course (a student may be in several courses), narrowed by
        // the name filter. When editing, the enrollment's own student stays listed.
        $pinnedStudent = $this->pinnedStudentId ? Student::find($this->pinnedStudentId) : null;

        $students = $this->course_id
            ? Student::orderBy('name')
                ->when($this->studentSearch, fn ($q) => $q->where('name', 'like', "%{$this->studentSearch}%"))
                ->where(function ($w) {
                    $w->whereDoesntHave('enrollments', fn ($e) => $e->where('course_id', $this->course_id));
                    if ($this->editingId) {
                        $w->orWhere('id', $this->student_id);
                    }
                })
                ->get(['id', 'name', 'phone'])
            : collect();

        $preview = Enrollment::settle((int) $this->price, (int) $this->discount, (int) $this->paid);

        return view('livewire.enrollments.index', [
            'enrollments' => $enrollments,
            'stats' => $stats,
            'courses' => $courses,
            'students' => $students,
            'groups' => $groups,
            'selectedCourse' => $selectedCourse,
            'selectedGroup' => $selectedGroup,
            'pinnedStudent' => $pinnedStudent,
            'preview' => $preview,
            'statuses' => Enrollment::STATUSES,
            'packOptions' => Enrollment::PACKS,
            // The center's active packages usable with the chosen course (+ the one already on
            // the enrollment being edited, even if it was deactivated since).
            'packages' => Package::query()
                ->where(fn ($q) => $q->where(fn ($a) => $a->active()->forCourse($this->course_id ? (int) $this->course_id : null))
                    ->when($this->package_id, fn ($q) => $q->orWhere('id', $this->package_id)))
                ->orderBy('duration_months')->get(),
            'allPackages' => Package::orderBy('duration_months')->get(['id', 'name']),
        ])->extends('layouts.app')->section('content')->title(__('التسجيلات'));
    }
}

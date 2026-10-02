<?php

namespace App\Livewire\Courses;

use App\Livewire\Concerns\DropsDeletedReferences;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Package;
use App\Models\ScheduleSlot;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use DropsDeletedReferences, WithPagination;

    public const STATUSES = ['نشط', 'جديد', 'متوقف مؤقتاً'];

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'level', history: true)]
    public string $levelFilter = '';

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $confirmingDeleteId = null;

    // Form fields (named after the actual columns)
    public string $name = '';

    public string $level = '';

    public ?int $teacher_id = null;

    public $price = 0;

    public string $status = 'نشط';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'level' => ['nullable', 'string', 'max:255'],
            'teacher_id' => ['nullable', Rule::exists('teachers', 'id')->where('tenant_id', auth()->user()->tenant_id)->whereNull('deleted_at')],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('اسم الدورة'),
            'level' => __('المستوى'),
            'teacher_id' => __('الأستاذ'),
            'price' => __('السعر'),
            'status' => __('الحالة'),
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingLevelFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
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
        $course = Course::findOrFail($id);
        $this->editingId = $course->id;
        $this->name = $course->name;
        $this->level = (string) $course->level;
        $this->teacher_id = $this->existingId(Teacher::class, $course->teacher_id);
        $this->price = (int) $course->price;
        $this->status = $course->status;
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
        $this->reset(['name', 'level', 'teacher_id', 'price', 'status']);
        $this->status = 'نشط';
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Course::findOrFail($this->editingId)->update($data);
            $this->dispatch('toast', message: __('تم تحديث بيانات الدورة بنجاح'));
        } else {
            Course::create($data);
            $this->dispatch('toast', message: __('تم إنشاء الدورة بنجاح'));
        }

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
            $course = Course::findOrFail($this->confirmingDeleteId);

            // A course still holding students, groups or enrollments can't go: their
            // records would point at nothing and stop being editable.
            $inUse = Student::where('course_id', $course->id)->exists()
                || Group::where('course_id', $course->id)->exists()
                || Enrollment::where('course_id', $course->id)->exists();
            if ($inUse) {
                $this->confirmingDeleteId = null;
                $this->dispatch('toast', message: __('لا يمكن حذف هذه الدورة لأنها مرتبطة بطلاب أو مجموعات أو تسجيلات. انقل الطلاب أو غيّر حالتها إلى «متوقف مؤقتاً» بدلاً من الحذف.'));

                return;
            }

            ScheduleSlot::where('course_id', $course->id)->delete();
            Package::where('course_id', $course->id)->update(['is_active' => false]);
            $course->delete();
            $this->dispatch('toast', message: __('تم حذف الدورة بنجاح'));
        }
        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    public function render()
    {
        $courses = Course::query()
            ->search($this->search)
            ->level($this->levelFilter)
            ->status($this->statusFilter)
            ->with('teacher')
            ->withCount(['groups', 'students'])
            ->orderBy('name')
            ->paginate(9);

        $total = Course::count();
        $stats = [
            'total' => $total,
            'groups' => Group::count(),
            'students' => Student::whereNotNull('course_id')->count(),
            'avg_price' => $total ? mad((int) round(Course::avg('price'))) : mad(0),
        ];

        $levels = Course::whereNotNull('level')->distinct()->orderBy('level')->pluck('level');
        $teachers = Teacher::orderBy('name')->get(['id', 'name']);

        return view('livewire.courses.index', [
            'courses' => $courses,
            'stats' => $stats,
            'levels' => $levels,
            'teachers' => $teachers,
            'statuses' => self::STATUSES,
        ])->extends('layouts.app')->section('content')->title(__('الدورات'));
    }
}

<?php

namespace App\Livewire\Groups;

use App\Livewire\Concerns\DropsDeletedReferences;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
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

    #[Url(as: 'course', history: true)]
    public string $courseFilter = '';

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $confirmingDeleteId = null;

    // Form fields (named after the actual columns)
    public string $name = '';

    public ?int $course_id = null;

    public ?int $teacher_id = null;

    public $capacity = 18;

    public string $room = '';

    public string $schedule = '';

    public string $status = 'نشط';

    protected function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'course_id' => ['nullable', Rule::exists('courses', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'teacher_id' => ['nullable', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'room' => ['nullable', 'string', 'max:255'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('اسم المجموعة'),
            'course_id' => __('الدورة'),
            'teacher_id' => __('الأستاذ'),
            'capacity' => __('السعة'),
            'room' => __('القاعة'),
            'schedule' => __('التوقيت'),
            'status' => __('الحالة'),
        ];
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

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $group = Group::findOrFail($id);
        $this->editingId = $group->id;
        $this->name = $group->name;
        $this->course_id = $this->existingId(Course::class, $group->course_id);
        $this->teacher_id = $this->existingId(Teacher::class, $group->teacher_id);
        $this->capacity = (int) $group->capacity;
        $this->room = (string) $group->room;
        $this->schedule = (string) $group->schedule;
        $this->status = $group->status;
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
        $this->reset(['name', 'course_id', 'teacher_id', 'capacity', 'room', 'schedule', 'status']);
        $this->capacity = 18;
        $this->status = 'نشط';
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Group::findOrFail($this->editingId)->update($data);
            $this->dispatch('toast', message: __('تم تحديث بيانات المجموعة بنجاح'));
        } else {
            Group::create($data);
            $this->dispatch('toast', message: __('تم إنشاء المجموعة بنجاح'));
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
            $group = Group::findOrFail($this->confirmingDeleteId);

            if (Student::where('group_id', $group->id)->exists() || Enrollment::where('group_id', $group->id)->exists()) {
                $this->confirmingDeleteId = null;
                $this->dispatch('toast', message: __('لا يمكن حذف هذه المجموعة لأن فيها طلاباً. انقلهم إلى مجموعة أخرى أولاً، أو غيّر حالتها إلى «متوقف مؤقتاً».'));

                return;
            }

            ScheduleSlot::where('group_id', $group->id)->delete();
            $group->delete();
            $this->dispatch('toast', message: __('تم حذف المجموعة بنجاح'));
        }
        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    public function render()
    {
        $groups = Group::query()
            ->search($this->search)
            ->course($this->courseFilter ? (int) $this->courseFilter : null)
            ->status($this->statusFilter)
            ->with(['course', 'teacher'])
            ->withCount('students')
            ->orderBy('name')
            ->paginate(9);

        $total = Group::count();
        $stats = [
            'total' => $total,
            'active' => Group::where('status', 'نشط')->count(),
            'avg_students' => $total ? (int) round(Student::whereNotNull('group_id')->count() / $total) : 0,
            'rooms' => Group::whereNotNull('room')->distinct()->count('room'),
        ];

        $courses = Course::orderBy('name')->get(['id', 'name']);
        $teachers = Teacher::orderBy('name')->get(['id', 'name']);

        return view('livewire.groups.index', [
            'groups' => $groups,
            'stats' => $stats,
            'courses' => $courses,
            'teachers' => $teachers,
            'statuses' => self::STATUSES,
        ])->extends('layouts.app')->section('content')->title(__('المجموعات'));
    }
}

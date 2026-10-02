<?php

namespace App\Livewire\Teachers;

use App\Models\Course;
use App\Models\Group;
use App\Models\ScheduleSlot;
use App\Models\Teacher;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public const STATUSES = ['نشط', 'في إجازة', 'متوقف'];

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'specialty', history: true)]
    public string $specialtyFilter = '';

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $confirmingDeleteId = null;

    // Form fields (named after the actual columns)
    public string $name = '';

    public string $specialty = '';

    public string $phone = '';

    public $hours = 0;

    public string $salary_type = 'ثابت';

    public $fixed_salary = null;

    public $commission_rate = null;

    public string $status = 'نشط';

    protected function rules(): array
    {
        $isFixed = $this->salary_type === 'ثابت';

        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'hours' => ['required', 'integer', 'min:0', 'max:200'],
            'salary_type' => ['required', 'in:'.implode(',', Teacher::SALARY_TYPES)],
            'fixed_salary' => $isFixed ? ['required', 'integer', 'min:0', 'max:10000000'] : ['nullable'],
            'commission_rate' => $isFixed ? ['nullable'] : ['required', 'integer', 'between:0,100'],
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('اسم الأستاذ'),
            'specialty' => __('التخصص'),
            'phone' => __('رقم الهاتف'),
            'hours' => __('ساعات التدريس'),
            'salary_type' => __('نوع الأجر'),
            'fixed_salary' => __('الراتب الثابت'),
            'commission_rate' => __('نسبة العمولة'),
            'status' => __('الحالة'),
        ];
    }

    public function updatedSalaryType(): void
    {
        $this->resetValidation(['fixed_salary', 'commission_rate']);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSpecialtyFilter(): void
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
        $teacher = Teacher::findOrFail($id);
        $this->editingId = $teacher->id;
        $this->name = $teacher->name;
        $this->specialty = (string) $teacher->specialty;
        $this->phone = (string) $teacher->phone;
        $this->hours = (int) $teacher->hours;
        $this->salary_type = $teacher->salary_type ?? 'ثابت';
        $this->fixed_salary = $teacher->fixed_salary;
        $this->commission_rate = $teacher->commission_rate;
        $this->status = $teacher->status;
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
        $this->reset(['name', 'specialty', 'phone', 'hours', 'salary_type', 'fixed_salary', 'commission_rate', 'status']);
        $this->status = 'نشط';
        $this->salary_type = 'ثابت';
    }

    public function save(): void
    {
        $data = $this->validate();

        // Only the amount matching the chosen salary type is stored.
        if ($data['salary_type'] === 'ثابت') {
            $data['commission_rate'] = null;
        } else {
            $data['fixed_salary'] = null;
        }

        if ($this->editingId) {
            Teacher::findOrFail($this->editingId)->update($data);
            $this->dispatch('toast', message: __('تم تحديث بيانات الأستاذ بنجاح'));
        } else {
            Teacher::create($data);
            $this->dispatch('toast', message: __('تمت إضافة الأستاذ بنجاح'));
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
            $teacher = Teacher::findOrFail($this->confirmingDeleteId);

            // Unassign rather than leave courses, groups and sessions pointing at a deleted teacher.
            foreach ([Course::class, Group::class, ScheduleSlot::class] as $model) {
                $model::where('teacher_id', $teacher->id)->update(['teacher_id' => null]);
            }
            $teacher->delete();
            $this->dispatch('toast', message: __('تم حذف الأستاذ بنجاح'));
        }
        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    public function render()
    {
        $teachers = Teacher::query()
            ->search($this->search)
            ->specialty($this->specialtyFilter)
            ->status($this->statusFilter)
            ->withCount(['groups', 'students'])
            ->orderBy('name')
            ->paginate(10);

        $stats = [
            'total' => Teacher::count(),
            'active' => Teacher::where('status', 'نشط')->count(),
            'on_leave' => Teacher::where('status', 'في إجازة')->count(),
            'total_hours' => (int) Teacher::sum('hours'),
        ];

        $specialties = Teacher::whereNotNull('specialty')
            ->distinct()->orderBy('specialty')->pluck('specialty');

        return view('livewire.teachers.index', [
            'teachers' => $teachers,
            'stats' => $stats,
            'specialties' => $specialties,
            'statuses' => self::STATUSES,
            'salaryTypes' => Teacher::SALARY_TYPES,
        ])->extends('layouts.app')->section('content')->title(__('الأساتذة'));
    }
}

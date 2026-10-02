<?php

namespace App\Livewire\Students;

use App\Models\Course;
use App\Models\Enrollment;
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

    #[Url(history: true)]
    public string $course = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $financial = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $confirmingDeleteId = null;

    /** After adding a student, offer to enrol them right away. */
    public ?array $enrollPrompt = null;

    // Form fields
    public string $name = '';

    public string $phone = '';

    public string $city = '';

    public string $guardian_phone = '';

    public ?int $course_id = null;

    public string $enrollment_status = 'نشط';

    public string $financial_status = 'غير مؤدي';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            // Only a course of the signed-in user's own center (never another center's id).
            'course_id' => ['nullable', Rule::exists('courses', 'id')->where('tenant_id', auth()->user()->tenant_id)->whereNull('deleted_at')],
            'enrollment_status' => ['required', 'in:نشط,متوقف'],
            'financial_status' => ['required', 'in:مؤدي,جزئي,غير مؤدي'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('الاسم الكامل'),
            'phone' => __('رقم الهاتف'),
            'city' => __('المدينة'),
            'guardian_phone' => __('هاتف ولي الأمر'),
            'course_id' => __('الدورة'),
            'enrollment_status' => __('حالة التسجيل'),
            'financial_status' => __('الحالة المالية'),
        ];
    }

    public function mount(): void
    {
        $editId = request()->integer('edit');
        if ($editId) {
            $this->openEdit($editId);
        } elseif (request()->boolean('add')) {
            // "إضافة طالب" buttons (header, dashboard) open the form directly.
            $this->openCreate();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCourse(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFinancial(): void
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
        $student = Student::findOrFail($id);
        $this->editingId = $student->id;
        $this->name = $student->name;
        $this->phone = $student->phone;
        $this->city = (string) $student->city;
        $this->guardian_phone = (string) $student->guardian_phone;
        $this->course_id = $student->course_id;
        $this->enrollment_status = $student->enrollment_status;
        $this->financial_status = $student->financial_status;
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
        $this->reset([
            'name', 'phone', 'city',
            'guardian_phone', 'course_id', 'enrollment_status', 'financial_status',
        ]);
        $this->enrollment_status = 'نشط';
        $this->financial_status = 'غير مؤدي';
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $student = Student::findOrFail($this->editingId);
            $student->update($data);
            $this->dispatch('toast', message: __('تم تحديث بيانات الطالب بنجاح'));
        } else {
            $data['registered_at'] = now()->toDateString();
            $student = Student::create($data);
            $this->enrollPrompt = ['id' => $student->id, 'name' => $student->name];
            $this->dispatch('toast', message: __('تمت إضافة الطالب بنجاح'));
        }

        $this->closeModal();
    }

    public function dismissEnrollPrompt(): void
    {
        $this->enrollPrompt = null;
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
            Student::findOrFail($this->confirmingDeleteId)->delete();
            $this->dispatch('toast', message: __('تم حذف الطالب بنجاح'));
        }
        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    public function render()
    {
        Enrollment::rolloverDue();

        $students = Student::query()
            ->search($this->search)
            ->enrollmentStatus($this->status)
            ->financialStatus($this->financial)
            ->when($this->course, fn ($q) => $q->where('course_id', $this->course))
            ->with(['course', 'group'])
            ->latest('registered_at')
            ->paginate(10);

        $stats = [
            'total' => Student::count(),
            'active' => Student::where('enrollment_status', 'نشط')->count(),
            'paused' => Student::where('enrollment_status', 'متوقف')->count(),
            'unpaid' => Student::where('financial_status', 'غير مؤدي')->count(),
        ];

        $courses = Course::orderBy('name')->get(['id', 'name']);

        return view('livewire.students.index', [
            'students' => $students,
            'stats' => $stats,
            'courses' => $courses,
        ])->extends('layouts.app')->section('content')->title(__('الطلاب'));
    }
}

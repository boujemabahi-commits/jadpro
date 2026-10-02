<?php

namespace App\Livewire\Payments;

use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Payment;
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

    #[Url(as: 'method', history: true)]
    public string $methodFilter = '';

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    public bool $showModal = false;

    // Form fields (named after the actual columns)
    public ?int $student_id = null;

    public $amount = 0;

    public string $method = 'نقداً';

    public string $date = '';

    protected function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;
        $max = $this->outstandingFor($this->student_id);

        return [
            'student_id' => ['required', Rule::exists('students', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'amount' => ['required', 'integer', 'min:1', 'max:'.max(1, $max)],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'date' => ['required', 'date', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'student_id' => __('الطالب'),
            'amount' => __('المبلغ'),
            'method' => __('طريقة الدفع'),
            'date' => __('التاريخ'),
        ];
    }

    protected function messages(): array
    {
        return ['amount.max' => __('المبلغ يتجاوز المتبقي على الطالب (:max MAD).')];
    }

    public function mount(): void
    {
        $studentId = request()->integer('student');
        if ($studentId && Student::whereKey($studentId)->exists()) {
            $this->openCreate();
            $this->student_id = $studentId;
            $this->amount = $this->outstandingFor($studentId);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingMethodFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /** Picking a student pre-fills the amount with what they still owe. */
    public function updatedStudentId($value): void
    {
        $this->amount = $value ? $this->outstandingFor((int) $value) : 0;
    }

    protected function outstandingFor(?int $studentId): int
    {
        if (! $studentId) {
            return 0;
        }

        return (int) (Enrollment::where('student_id', $studentId)->latest('date')->latest('id')->value('remaining') ?? 0);
    }

    public function openCreate(): void
    {
        $this->reset(['student_id', 'amount', 'method', 'date']);
        $this->method = 'نقداً';
        $this->date = now()->toDateString();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        $enrollment = Enrollment::where('student_id', $data['student_id'])->latest('date')->latest('id')->first();
        if (! $enrollment) {
            $this->addError('student_id', __('هذا الطالب ليس لديه تسجيل لتسديد رسومه.'));

            return;
        }

        $enrollment->applyPayment((int) $data['amount']);

        Payment::create([
            'student_id' => $data['student_id'],
            'amount' => (int) $data['amount'],
            'method' => $data['method'],
            'date' => $data['date'],
            'status' => $enrollment->remaining === 0 ? 'مؤدي بالكامل' : 'دفعة جزئية',
        ]);

        Notification::notify([
            'title' => __('تم استلام دفعة جديدة'),
            'body' => __('دفعة بقيمة :amount من الطالب :student.', ['amount' => mad((int) $data['amount']), 'student' => $enrollment->student?->name]),
            'icon' => 'wallet',
            'tone' => 'blue',
            'category' => __('المالية'),
            'permission' => 'manage-payments',
        ]);

        $this->dispatch('notification-created');

        $this->dispatch('toast', message: __('تم تسجيل الدفعة بنجاح'));
        $this->closeModal();
    }

    public function render()
    {
        Enrollment::rolloverDue();

        $payments = Payment::query()
            ->search($this->search)
            ->methodFilter($this->methodFilter)
            ->statusFilter($this->statusFilter)
            ->with('student')
            ->latest('date')->latest('id')
            ->paginate(12);

        $stats = [
            'total_revenue' => (int) Payment::sum('amount'),
            'paid_this_month' => (int) Payment::whereMonth('date', now()->month)->whereYear('date', now()->year)->sum('amount'),
            'remaining' => (int) Enrollment::sum('remaining'),
            'unpaid_students' => Student::where('financial_status', 'غير مؤدي')->count(),
        ];

        // Only students who still owe something on their current enrollment.
        $debtors = Student::with('currentEnrollment')
            ->whereHas('currentEnrollment', fn ($q) => $q->where('remaining', '>', 0))
            ->orderBy('name')
            ->get();

        return view('livewire.payments.index', [
            'payments' => $payments,
            'stats' => $stats,
            'debtors' => $debtors,
            'methods' => Payment::METHODS,
            'statuses' => Payment::STATUSES,
            'outstanding' => $this->outstandingFor($this->student_id),
        ])->extends('layouts.app')->section('content')->title(__('أداءات الطلاب'));
    }
}

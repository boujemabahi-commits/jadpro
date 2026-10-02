<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Models\Notification;
use App\Support\Analytics;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'category', history: true)]
    public string $categoryFilter = '';

    #[Url(as: 'method', history: true)]
    public string $methodFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $confirmingDeleteId = null;

    // Form fields (named after the actual columns)
    public string $label = '';

    public string $category = 'أخرى';

    public $amount = 0;

    public string $date = '';

    public string $method = 'نقداً';

    protected function rules(): array
    {
        return [
            'label' => ['required', 'string', 'min:2', 'max:255'],
            'category' => ['required', Rule::in(Expense::CATEGORIES)],
            'amount' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'method' => ['required', Rule::in(Expense::METHODS)],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'label' => __('البيان'),
            'category' => __('الفئة'),
            'amount' => __('المبلغ'),
            'date' => __('التاريخ'),
            'method' => __('طريقة الدفع'),
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingMethodFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->date = now()->toDateString();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $expense = Expense::findOrFail($id);
        $this->editingId = $expense->id;
        $this->label = $expense->label;
        $this->category = $expense->category;
        $this->amount = (int) $expense->amount;
        $this->date = $expense->date->toDateString();
        $this->method = $expense->method;
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
        $this->reset(['label', 'category', 'amount', 'date', 'method']);
        $this->category = 'أخرى';
        $this->method = 'نقداً';
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Expense::findOrFail($this->editingId)->update($data);
            $this->dispatch('toast', message: __('تم تحديث المصروف بنجاح'));
        } else {
            Expense::create($data);
            Notification::notify([
                'title' => __('مصروف جديد تم تسجيله'),
                'body' => __('إضافة مصروف ":label" بقيمة :amount.', ['label' => $data['label'], 'amount' => mad((int) $data['amount'])]),
                'icon' => 'receipt',
                'tone' => 'rose',
                'category' => __('المالية'),
                'permission' => 'manage-expenses',
            ]);
            $this->dispatch('notification-created');
            $this->dispatch('toast', message: __('تمت إضافة المصروف بنجاح'));
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
            Expense::findOrFail($this->confirmingDeleteId)->delete();
            $this->dispatch('toast', message: __('تم حذف المصروف بنجاح'));
        }
        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    public function render()
    {
        $expenses = Expense::query()
            ->search($this->search)
            ->categoryFilter($this->categoryFilter)
            ->methodFilter($this->methodFilter)
            ->latest('date')->latest('id')
            ->paginate(12);

        // Category tiles + summary are shared with the Reports page.
        ['categories' => $categories, 'stats' => $stats] = Analytics::expensesThisMonth();

        return view('livewire.expenses.index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'stats' => $stats,
            'categoryNames' => Expense::CATEGORIES,
            'methods' => Expense::METHODS,
        ])->extends('layouts.app')->section('content')->title(__('المصاريف'));
    }
}

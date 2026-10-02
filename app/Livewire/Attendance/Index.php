<?php

namespace App\Livewire\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Group;
use App\Models\Student;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url(as: 'group', history: true)]
    public string $groupId = '';

    #[Url(history: true)]
    public string $date = '';

    /** student_id => 'حاضر' | 'متأخر' | 'غائب' for the current group/date, edited in memory until save(). */
    public array $states = [];

    public function mount(): void
    {
        if (! $this->validDate($this->date)) {
            $this->date = today()->toDateString();
        }

        if (! $this->groupId || ! Group::whereKey($this->groupId)->exists()) {
            $this->groupId = (string) (Group::orderBy('name')->value('id') ?? '');
        }

        $this->loadStates();
    }

    public function updatedGroupId(): void
    {
        $this->loadStates();
    }

    public function updatedDate(): void
    {
        if (! $this->validDate($this->date)) {
            $this->date = today()->toDateString();
        }
        $this->loadStates();
    }

    public function setState(int $studentId, string $state): void
    {
        if (array_key_exists($studentId, $this->states) && in_array($state, AttendanceRecord::STATES, true)) {
            $this->states[$studentId] = $state;
        }
    }

    public function markAll(string $state): void
    {
        if (in_array($state, AttendanceRecord::STATES, true)) {
            $this->states = array_fill_keys(array_keys($this->states), $state);
        }
    }

    protected function validDate(?string $date): bool
    {
        return (bool) $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false;
    }

    public function save(): void
    {
        if (! $this->groupId || ! $this->date) {
            return;
        }

        // Never trust the client-side state array: only this center's group, a real
        // date, the group's own students, and the three known states are saved.
        if (! Group::whereKey($this->groupId)->exists() || ! $this->validDate($this->date)) {
            return;
        }

        $tenantId = auth()->user()->tenant_id;
        $roster = Student::where('group_id', $this->groupId)->pluck('id')->all();
        $states = array_filter(
            array_intersect_key($this->states, array_flip($roster)),
            fn ($state) => in_array($state, AttendanceRecord::STATES, true),
        );

        foreach ($states as $studentId => $state) {
            // The date cast stores a full datetime, so match on the date part —
            // a plain updateOrCreate(['date' => ...]) would miss and hit the unique index.
            $record = AttendanceRecord::where('student_id', $studentId)
                ->whereDate('date', $this->date)
                ->first() ?? new AttendanceRecord(['student_id' => $studentId, 'date' => $this->date]);

            $record->fill(['tenant_id' => $tenantId, 'group_id' => (int) $this->groupId, 'state' => $state])->save();
        }

        $this->dispatch('toast', message: __('تم حفظ الحضور بنجاح'));
    }

    protected function roster()
    {
        return $this->groupId
            ? Student::where('group_id', $this->groupId)->orderBy('name')->get()
            : collect();
    }

    /** Seed $states from saved records; students without one default to حاضر (the UI default). */
    protected function loadStates(): void
    {
        $roster = $this->roster();

        $saved = $this->groupId && $this->date
            ? AttendanceRecord::whereIn('student_id', $roster->pluck('id'))
                ->whereDate('date', $this->date)
                ->pluck('state', 'student_id')
            : collect();

        $this->states = $roster->mapWithKeys(fn ($s) => [$s->id => $saved[$s->id] ?? 'حاضر'])->all();
    }

    public function render()
    {
        $groups = Group::with(['teacher', 'course'])->orderBy('name')->get();
        $group = $this->groupId ? $groups->firstWhere('id', (int) $this->groupId) : null;
        $roster = $this->roster();

        $counts = array_count_values($this->states);
        $summary = [
            'total' => count($this->states),
            'present' => $counts['حاضر'] ?? 0,
            'late' => $counts['متأخر'] ?? 0,
            'absent' => $counts['غائب'] ?? 0,
        ];

        $savedCount = $this->groupId && $this->date
            ? AttendanceRecord::whereIn('student_id', $roster->pluck('id'))->whereDate('date', $this->date)->count()
            : 0;

        return view('livewire.attendance.index', [
            'groups' => $groups,
            'group' => $group,
            'roster' => $roster,
            'summary' => $summary,
            'savedCount' => $savedCount,
        ])->extends('layouts.app')->section('content')->title(__('الحضور'));
    }
}

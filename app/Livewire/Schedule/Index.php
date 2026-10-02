<?php

namespace App\Livewire\Schedule;

use App\Livewire\Concerns\DropsDeletedReferences;
use App\Models\Course;
use App\Models\Group;
use App\Models\ScheduleSlot;
use App\Models\Teacher;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    use DropsDeletedReferences;

    #[Url(as: 'teacher', history: true)]
    public string $teacherFilter = '';

    #[Url(as: 'room', history: true)]
    public string $roomFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $confirmingDeleteId = null;

    // Form fields (named after the actual columns)
    public string $day = 'الاثنين';

    public string $time = '';

    public ?int $course_id = null;

    public ?int $group_id = null;

    public ?int $teacher_id = null;

    public string $room = '';

    protected function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'day' => ['required', Rule::in(ScheduleSlot::DAYS)],
            'time' => ['required', 'string', 'max:50', 'regex:/^\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2}$/u'],
            'course_id' => ['required', Rule::exists('courses', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'group_id' => [
                'nullable',
                Rule::exists('groups', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')
                    ->when($this->course_id, fn ($rule) => $rule->where('course_id', $this->course_id)),
            ],
            'teacher_id' => ['nullable', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'room' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'day' => __('اليوم'),
            'time' => __('التوقيت'),
            'course_id' => __('الدورة'),
            'group_id' => __('المجموعة'),
            'teacher_id' => __('الأستاذ'),
            'room' => __('القاعة'),
        ];
    }

    protected function messages(): array
    {
        return ['time.regex' => __('اكتب التوقيت بالشكل 14:00 - 15:30.')];
    }

    /** Course narrows the group list and suggests the teacher. */
    public function updatedCourseId($value): void
    {
        $course = $value ? Course::find($value) : null;

        if ($this->group_id && (! $course || ! Group::where('id', $this->group_id)->where('course_id', $course->id)->exists())) {
            $this->group_id = null;
        }

        if ($course && ! $this->teacher_id) {
            $this->teacher_id = $course->teacher_id;
        }
    }

    /** The group's teacher and room are the natural defaults. */
    public function updatedGroupId($value): void
    {
        $group = $value ? Group::find($value) : null;
        if (! $group) {
            return;
        }

        if ($group->teacher_id) {
            $this->teacher_id = $group->teacher_id;
        }
        if (! $this->room && $group->room) {
            $this->room = $group->room;
        }
    }

    public function openCreate(?string $day = null): void
    {
        $this->resetForm();
        $this->editingId = null;
        if ($day && in_array($day, ScheduleSlot::DAYS, true)) {
            $this->day = $day;
        }
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $slot = ScheduleSlot::findOrFail($id);
        $this->editingId = $slot->id;
        $this->day = $slot->day;
        $this->time = $slot->time;
        $this->course_id = $this->existingId(Course::class, $slot->course_id);
        $this->group_id = $this->existingId(Group::class, $slot->group_id);
        $this->teacher_id = $this->existingId(Teacher::class, $slot->teacher_id);
        $this->room = (string) $slot->room;
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
        $this->reset(['day', 'time', 'course_id', 'group_id', 'teacher_id', 'room']);
        $this->day = 'الاثنين';
    }

    public function save(): void
    {
        $data = $this->validate();
        $data['time'] = preg_replace('/\s*-\s*/u', ' - ', trim($data['time']));
        $data['group_id'] = $data['group_id'] ?: null;
        $data['teacher_id'] = $data['teacher_id'] ?: null;
        $data['room'] = $data['room'] ?: null;

        if ($this->hasConflicts($data)) {
            return;
        }

        if ($this->editingId) {
            ScheduleSlot::findOrFail($this->editingId)->update($data);
            $this->dispatch('toast', message: __('تم تحديث الحصة بنجاح'));
        } else {
            ScheduleSlot::create($data);
            $this->dispatch('toast', message: __('تمت إضافة الحصة بنجاح'));
        }

        $this->closeModal();
    }

    /**
     * A room can't host two classes at overlapping times on the same day, and a
     * teacher can't be in two classes at once. Room and teacher are checked
     * independently; either conflict blocks the save.
     */
    protected function hasConflicts(array $data): bool
    {
        if (! $data['room'] && ! $data['teacher_id']) {
            return false;
        }

        $range = ScheduleSlot::parseTimeRange($data['time']);

        $candidates = ScheduleSlot::with(['course', 'group'])
            ->where('day', $data['day'])
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->where(function ($q) use ($data) {
                if ($data['room']) {
                    $q->orWhere('room', $data['room']);
                }
                if ($data['teacher_id']) {
                    $q->orWhere('teacher_id', $data['teacher_id']);
                }
            })
            ->get()
            ->filter(fn ($slot) => ScheduleSlot::rangesOverlap($range, ScheduleSlot::parseTimeRange($slot->time)));

        $label = fn ($slot) => trim(($slot->course?->name ?? '').($slot->group ? ' / '.$slot->group->name : ''));
        $found = false;

        if ($data['room'] && ($hit = $candidates->firstWhere('room', $data['room']))) {
            $this->addError('room', __(':room محجوزة بالفعل من :time يوم :day (:label).', [
                'room' => $data['room'], 'time' => $hit->time, 'day' => __($data['day']), 'label' => $label($hit),
            ]));
            $found = true;
        }

        if ($data['teacher_id'] && ($hit = $candidates->firstWhere('teacher_id', (int) $data['teacher_id']))) {
            $where = $hit->room ? __(' في :room', ['room' => $hit->room]) : '';
            $this->addError('teacher_id', __('الأستاذ لديه حصة أخرى من :time يوم :day (:label):where.', [
                'time' => $hit->time, 'day' => __($data['day']), 'label' => $label($hit), 'where' => $where,
            ]));
            $found = true;
        }

        return $found;
    }

    /** slot id => 'room' | 'teacher' | 'both' for every slot overlapping another on the same day. */
    protected function detectConflicts(): array
    {
        $flags = [];
        $all = ScheduleSlot::get(['id', 'day', 'time', 'room', 'teacher_id'])->groupBy('day');

        foreach ($all as $daySlots) {
            $parsed = $daySlots->map(fn ($s) => [$s, ScheduleSlot::parseTimeRange($s->time)])->values();
            foreach ($parsed as $i => [$a, $ra]) {
                foreach ($parsed as $j => [$b, $rb]) {
                    if ($j <= $i || ! ScheduleSlot::rangesOverlap($ra, $rb)) {
                        continue;
                    }
                    $room = $a->room && $a->room === $b->room;
                    $teacher = $a->teacher_id && $a->teacher_id === $b->teacher_id;
                    if ($room || $teacher) {
                        foreach ([$a->id, $b->id] as $id) {
                            $flags[$id][] = $room ? 'room' : 'teacher';
                            if ($room && $teacher) {
                                $flags[$id][] = 'teacher';
                            }
                        }
                    }
                }
            }
        }

        return array_map(fn ($kinds) => count(array_unique($kinds)) > 1 ? 'both' : $kinds[0], $flags);
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
            ScheduleSlot::findOrFail($this->confirmingDeleteId)->delete();
            $this->dispatch('toast', message: __('تم حذف الحصة بنجاح'));
        }
        $this->confirmingDeleteId = null;
    }

    public function render()
    {
        $slots = ScheduleSlot::with(['course', 'group', 'teacher'])
            ->teacherFilter($this->teacherFilter ? (int) $this->teacherFilter : null)
            ->roomFilter($this->roomFilter)
            ->get()
            ->sortBy('time', SORT_NATURAL);

        // Same shape the Phase 1 view consumed from Mock\Schedule::week(): day => [slots]
        $week = collect(ScheduleSlot::DAYS)
            ->mapWithKeys(fn ($day) => [$day => $slots->where('day', $day)->values()])
            ->all();

        // Flag slots that already double-book a room or a teacher (e.g. legacy data
        // saved before the conflict check existed) so they stand out on the grid.
        $conflicts = $this->detectConflicts();

        $teachers = Teacher::orderBy('name')->get(['id', 'name']);
        $courses = Course::orderBy('name')->get(['id', 'name']);
        $groups = $this->course_id
            ? Group::where('course_id', $this->course_id)->orderBy('name')->get(['id', 'name'])
            : collect();

        $rooms = collect(ScheduleSlot::ROOMS)
            ->merge(ScheduleSlot::whereNotNull('room')->distinct()->pluck('room'))
            ->unique()->values();

        return view('livewire.schedule.index', [
            'week' => $week,
            'days' => ScheduleSlot::DAYS,
            'teachers' => $teachers,
            'courses' => $courses,
            'groups' => $groups,
            'rooms' => $rooms,
            'totalSlots' => $slots->count(),
            'conflicts' => $conflicts,
        ])->extends('layouts.app')->section('content')->title(__('الجدول'));
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const STATUSES = ['مكتمل', 'جزئي', 'غير مؤدي'];

    /** Predefined subscription packages: months → display label. */
    public const PACKS = [
        1 => 'شهري (شهر واحد)',
        2 => 'باقة شهرين',
        3 => 'باقة 3 أشهر',
        4 => 'باقة 4 أشهر',
        5 => 'باقة 5 أشهر',
        6 => 'باقة 6 أشهر',
        7 => 'باقة 7 أشهر',
        8 => 'باقة 8 أشهر',
        9 => 'باقة 9 أشهر',
        10 => 'باقة 10 أشهر',
        11 => 'باقة 11 شهراً',
        12 => 'باقة سنة كاملة',
    ];

    /** Enrollment payment status → Student financial_status. */
    public const FINANCIAL_STATUS_MAP = [
        'مكتمل' => 'مؤدي',
        'جزئي' => 'جزئي',
        'غير مؤدي' => 'غير مؤدي',
    ];

    protected $fillable = [
        'tenant_id',
        'student_id',
        'course_id',
        'group_id',
        'date',
        'due_date',
        'price',
        'discount',
        'duration_months',
        'package_id',
        'remaining',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function getPackLabelAttribute(): string
    {
        // A named package shows its own name; otherwise the generic duration label.
        if ($this->package_id && $this->package) {
            return $this->package->name;
        }

        return __(self::PACKS[(int) $this->duration_months] ?? 'باقة :months أشهر', ['months' => $this->duration_months]);
    }

    /** Paid as a package (named package or any multi-month duration), not month by month. */
    public function getIsPackAttribute(): bool
    {
        return $this->package_id !== null || (int) $this->duration_months > 1;
    }

    public function package()
    {
        return $this->belongsTo(Package::class)->withTrashed();
    }

    public function scopeMonthly(Builder $query): Builder
    {
        return $query->whereNull('package_id')->where('duration_months', '<=', 1);
    }

    public function scopePack(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNotNull('package_id')->orWhere('duration_months', '>', 1));
    }

    /** Filter: '' all · 'monthly' · 'pack' · a package id (numeric string). */
    public function scopePlanFilter(Builder $query, ?string $plan): Builder
    {
        return match (true) {
            $plan === 'monthly' => $query->monthly(),
            $plan === 'pack' => $query->pack(),
            $plan !== null && ctype_digit($plan) => $query->where('package_id', (int) $plan),
            default => $query,
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date !== null
            && $this->due_date->lt(today())
            && $this->status !== 'مكتمل';
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->where('status', '!=', 'مكتمل');
    }

    /**
     * Monthly rollover: a fully paid enrollment whose due date has passed owes
     * the next month, so it becomes unpaid again (full net amount outstanding)
     * and the student's financial badge follows. Idempotent and cheap, so it is
     * run lazily on page load as well as by the daily scheduled command.
     */
    public static function rolloverDue(): int
    {
        $due = static::with('student')
            ->where('status', 'مكتمل')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today())
            ->get();

        foreach ($due as $enrollment) {
            $enrollment->forceFill([
                'status' => 'غير مؤدي',
                'remaining' => $enrollment->net,
            ])->save();
            $enrollment->syncStudent();
        }

        return $due->count();
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function getNetAttribute(): int
    {
        return max(0, (int) $this->price - (int) $this->discount);
    }

    public function getPaidAttribute(): int
    {
        return max(0, $this->net - (int) $this->remaining);
    }

    /** Derive remaining + status from what was charged and what was paid. */
    public static function settle(int $price, int $discount, int $paid): array
    {
        $net = max(0, $price - $discount);
        $paid = min(max(0, $paid), $net);
        $remaining = $net - $paid;

        return [
            'remaining' => $remaining,
            'status' => match (true) {
                $remaining === 0 => 'مكتمل',
                $paid > 0 => 'جزئي',
                default => 'غير مؤدي',
            },
        ];
    }

    /**
     * Record money received against this enrollment: lower the balance, derive
     * the status, and push the result onto the student's financial badge.
     */
    public function applyPayment(int $amount): void
    {
        $paid = $this->paid + max(0, $amount);
        $settled = self::settle((int) $this->price, (int) $this->discount, $paid);

        // Settling a due/overdue period in full moves the deadline to the end of
        // the paid package (1 month for a monthly plan, more for a multi-month
        // pack) — otherwise the rollover would flip it straight back to unpaid.
        if ($settled['status'] === 'مكتمل' && $this->status !== 'مكتمل' && $this->due_date && $this->due_date->lte(today())) {
            $settled['due_date'] = $this->due_date->copy()->addMonths(max(1, (int) $this->duration_months));
        }

        $this->forceFill($settled)->save();
        $this->syncStudent();
    }

    /** Enrollment is the source of truth for the financial side of a registration. */
    public function syncStudent(): void
    {
        $student = $this->student;
        if (! $student) {
            return;
        }

        $student->course_id = $this->course_id;
        $student->group_id = $this->group_id;
        $student->financial_status = self::FINANCIAL_STATUS_MAP[$this->status] ?? $student->financial_status;
        $student->save();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->whereHas('student', fn (Builder $s) => $s->where('name', 'like', "%{$term}%"));
    }

    public function scopeCourseFilter(Builder $query, ?int $courseId): Builder
    {
        return $courseId ? $query->where('course_id', $courseId) : $query;
    }

    public function scopeStatusFilter(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }
}

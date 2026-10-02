<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Group;
use App\Models\Payment;
use App\Models\SalaryPayment;
use App\Models\ScheduleSlot;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-scoped aggregates shared by the Dashboard, Reports and Statistics
 * pages (and the Expenses tiles). Every query goes through the models, so the
 * TenantScope applies; month buckets are built in PHP so nothing here depends
 * on SQLite/MySQL date functions.
 */
class Analytics
{
    /** Month name in the current UI language (Moroccan Arabic month names in ar). */
    public static function monthLabel(Carbon $month): string
    {
        $locale = app()->getLocale() === 'ar' ? 'ar_MA' : app()->getLocale();

        return $month->copy()->locale($locale)->translatedFormat('F');
    }

    /** The last $months calendar months, oldest first, each as its first day. */
    public static function monthWindow(int $months): Collection
    {
        $months = max(1, min(24, $months));

        return collect(range($months - 1, 0))
            ->map(fn (int $back) => now()->startOfMonth()->subMonths($back));
    }

    /** Sum $column of $rows (date, $column) per "Y-m" bucket of the window. */
    protected static function bucketSum(Collection $rows, Collection $window, string $column): array
    {
        $sums = $rows->groupBy(fn ($r) => $r->date->format('Y-m'))->map(fn ($g) => (int) $g->sum($column));

        return $window->map(fn (Carbon $m) => (int) ($sums[$m->format('Y-m')] ?? 0))->values()->all();
    }

    protected static function pctChange(int|float $from, int|float $to): ?int
    {
        if ($from <= 0) {
            return null;
        }

        return (int) round(($to - $from) / $from * 100);
    }

    // ─── Dashboard ─────────────────────────────────────────────────────────

    /** The six dashboard stat cards. Trends only where last month is a fair baseline. */
    public static function stats(): array
    {
        $lastMonthEnd = now()->startOfMonth()->subSecond();

        $students = Student::count();
        $studentsLastMonth = Student::whereDate('registered_at', '<=', $lastMonthEnd)->count();

        $enrollmentsThisMonth = Enrollment::whereYear('date', now()->year)->whereMonth('date', now()->month)->count();
        $prev = now()->subMonthNoOverflow();
        $enrollmentsLastMonth = Enrollment::whereYear('date', $prev->year)->whereMonth('date', $prev->month)->count();

        return [
            ['label' => __('إجمالي الطلاب'), 'value' => $students, 'icon' => 'users', 'tone' => 'brand', 'trend' => self::pctChange($studentsLastMonth, $students)],
            ['label' => __('الأساتذة'), 'value' => Teacher::count(), 'icon' => 'graduation-cap', 'tone' => 'blue', 'trend' => null],
            ['label' => __('الدورات'), 'value' => Course::count(), 'icon' => 'book-open', 'tone' => 'violet', 'trend' => null],
            ['label' => __('المجموعات'), 'value' => Group::count(), 'icon' => 'users-round', 'tone' => 'amber', 'trend' => null],
            ['key' => 'unpaid', 'label' => __('الطلاب غير المؤدين'), 'value' => Student::where('financial_status', 'غير مؤدي')->count(), 'icon' => 'triangle-alert', 'tone' => 'rose', 'trend' => null],
            ['label' => __('التسجيلات هذا الشهر'), 'value' => $enrollmentsThisMonth, 'icon' => 'clipboard-list', 'tone' => 'brand', 'trend' => self::pctChange($enrollmentsLastMonth, $enrollmentsThisMonth)],
        ];
    }

    /** labels / revenue (payments) / expenses per month, oldest first. */
    public static function revenueByMonth(int $months = 6): array
    {
        $window = self::monthWindow($months);
        $start = $window->first()->toDateString();

        $payments = Payment::whereDate('date', '>=', $start)->get(['date', 'amount']);
        $expenses = Expense::whereDate('date', '>=', $start)->get(['date', 'amount']);

        return [
            'labels' => $window->map(fn ($m) => self::monthLabel($m))->values()->all(),
            'revenue' => self::bucketSum($payments, $window, 'amount'),
            'expenses' => self::bucketSum($expenses, $window, 'amount'),
        ];
    }

    /** labels / new (registered that month) / total (cumulative at month end). */
    public static function studentGrowthByMonth(int $months = 6): array
    {
        $window = self::monthWindow($months);
        $start = $window->first();

        $base = Student::whereDate('registered_at', '<', $start->toDateString())->count();
        $perMonth = Student::whereDate('registered_at', '>=', $start->toDateString())
            ->get(['registered_at'])
            ->groupBy(fn ($s) => $s->registered_at->format('Y-m'))
            ->map->count();

        $new = [];
        $total = [];
        $running = $base;
        foreach ($window as $m) {
            $n = (int) ($perMonth[$m->format('Y-m')] ?? 0);
            $running += $n;
            $new[] = $n;
            $total[] = $running;
        }

        return [
            'labels' => $window->map(fn ($m) => self::monthLabel($m))->values()->all(),
            'new' => $new,
            'total' => $total,
        ];
    }

    /** New enrollments per month, oldest first. */
    public static function enrollmentsByMonth(int $months = 6): array
    {
        $window = self::monthWindow($months);
        $rows = Enrollment::whereDate('date', '>=', $window->first()->toDateString())
            ->get(['date'])
            ->groupBy(fn ($e) => $e->date->format('Y-m'))
            ->map->count();

        return [
            'labels' => $window->map(fn ($m) => self::monthLabel($m))->values()->all(),
            'count' => $window->map(fn ($m) => (int) ($rows[$m->format('Y-m')] ?? 0))->values()->all(),
        ];
    }

    /** Paid vs outstanding across all current enrollments (net = price − discount). */
    public static function collectionRate(): array
    {
        $net = (int) Enrollment::sum(DB::raw('price - discount'));
        $remaining = (int) Enrollment::sum('remaining');
        $paid = max(0, $net - $remaining);

        return [
            'net' => $net,
            'paid' => $paid,
            'remaining' => $remaining,
            'rate' => $net > 0 ? (int) round($paid / $net * 100) : 0,
        ];
    }

    /** @return array<int, array{name: string, students: int}> */
    public static function topCourses(int $limit = 6): array
    {
        return Course::withCount('students')
            ->orderByDesc('students_count')->orderBy('name')
            ->limit($limit)->get()
            ->map(fn ($c) => ['name' => $c->name, 'students' => (int) $c->students_count])
            ->all();
    }

    /** @return array<int, array{name: string, hours: int}> */
    public static function topTeachers(int $limit = 6): array
    {
        return Teacher::orderByDesc('hours')->orderBy('name')
            ->limit($limit)->get()
            ->map(fn ($t) => ['name' => $t->name, 'hours' => (int) $t->hours])
            ->all();
    }

    public static function recentEnrollments(int $limit = 5): Collection
    {
        return Enrollment::with(['student', 'course', 'group'])
            ->latest('date')->latest('id')
            ->limit($limit)->get();
    }

    /**
     * Today's remaining classes: slots on today's weekday whose end time hasn't
     * passed, earliest first.
     */
    public static function upcomingClassesToday(int $limit = 4): Collection
    {
        $today = ScheduleSlot::DAYS[now()->dayOfWeekIso - 1] ?? ScheduleSlot::DAYS[0];
        $nowMinutes = now()->hour * 60 + now()->minute;

        return ScheduleSlot::with(['course', 'teacher'])
            ->where('day', $today)
            ->get()
            ->map(function (ScheduleSlot $slot) {
                [$start, $end] = ScheduleSlot::parseTimeRange($slot->time);
                $slot->setAttribute('starts_at', $start);
                $slot->setAttribute('ends_at', $end);

                return $slot;
            })
            ->filter(fn ($slot) => $slot->ends_at > $nowMinutes)
            ->sortBy('starts_at')
            ->take($limit)
            ->values();
    }

    // ─── Reports ───────────────────────────────────────────────────────────

    /** Share of students still active (نشط) among all students. */
    public static function retentionRate(): int
    {
        $total = Student::count();

        return $total > 0
            ? (int) round(Student::where('enrollment_status', 'نشط')->count() / $total * 100)
            : 0;
    }

    public static function averageEnrollmentValue(): int
    {
        return (int) round((float) Enrollment::avg(DB::raw('price - discount')));
    }

    /**
     * Current-month expense tiles + summary, shared by the Expenses page and the
     * Reports card. Every category is listed, even at 0.
     */
    public static function expensesThisMonth(): array
    {
        $sums = Expense::thisMonth()->selectRaw('category, SUM(amount) as total')->groupBy('category')->pluck('total', 'category');

        $categories = collect(Expense::CATEGORIES)->map(fn ($name) => [
            'name' => $name,
            'amount' => (int) ($sums[$name] ?? 0),
            'icon' => Expense::CATEGORY_META[$name]['icon'],
            'tone' => Expense::CATEGORY_META[$name]['tone'],
        ]);

        $total = $categories->sum('amount');
        $biggest = $categories->where('amount', '>', 0)->sortByDesc('amount')->first();

        return [
            'categories' => $categories,
            'stats' => [
                'total_this_month' => $total,
                'categories_count' => count(Expense::CATEGORIES),
                'biggest_category' => $biggest['name'] ?? '—',
                'avg_daily' => (int) round($total / max(1, now()->day)),
            ],
        ];
    }

    /** Refreshes every teacher's running balance (as the Salaries page does) and sums it. */
    public static function salaryTotals(): array
    {
        Teacher::with('students')->get()->each(fn (Teacher $t) => SalaryPayment::refreshFor($t));

        $rows = SalaryPayment::whereHas('teacher')->get();

        return [
            'total' => (int) $rows->sum('salary'),
            'paid' => (int) $rows->sum('paid'),
            'remaining' => (int) $rows->sum('remaining'),
        ];
    }

    /** Attendance rate (present + late over all records), absences and lateness this month. */
    public static function attendanceThisMonth(): array
    {
        $counts = AttendanceRecord::whereYear('date', now()->year)->whereMonth('date', now()->month)
            ->selectRaw('state, COUNT(*) as n')->groupBy('state')->pluck('n', 'state');

        $present = (int) ($counts['حاضر'] ?? 0);
        $late = (int) ($counts['متأخر'] ?? 0);
        $absent = (int) ($counts['غائب'] ?? 0);
        $total = $present + $late + $absent;

        return [
            'rate' => $total > 0 ? (int) round(($present + $late) / $total * 100) : 0,
            'absences' => $absent,
            'lateness' => $late,
            'total' => $total,
        ];
    }

    public static function revenueThisMonth(): int
    {
        return (int) Payment::whereYear('date', now()->year)->whereMonth('date', now()->month)->sum('amount');
    }

    // ─── Statistics ────────────────────────────────────────────────────────

    /** Percent change between the first and last month of a series (null when the first is 0). */
    public static function trend(array $series): ?int
    {
        if (count($series) < 2) {
            return null;
        }

        return self::pctChange($series[0], $series[count($series) - 1]);
    }
}

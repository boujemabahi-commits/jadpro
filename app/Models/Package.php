<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A center's subscription package (باقة): N months, optionally for one course, priced at a fixed
 * total or as a percentage off the course's monthly price × N.
 */
class Package extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'duration_months',
        'course_id',
        'price',
        'discount_percent',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'duration_months' => 'integer',
            'price' => 'integer',
            'discount_percent' => 'integer',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Packages usable for a course: the ones for that course plus the ones for any course. */
    public function scopeForCourse(Builder $query, ?int $courseId): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('course_id')->when($courseId, fn ($q) => $q->orWhere('course_id', $courseId)));
    }

    /** Total price of this package for a given course (fixed price wins over the discount). */
    public function priceFor(?Course $course): int
    {
        if ($this->price !== null) {
            return (int) $this->price;
        }

        $monthly = (int) ($course?->price ?? $this->course?->price ?? 0);
        $full = $monthly * max(1, $this->duration_months);

        return (int) round($full * (100 - min(100, max(0, $this->discount_percent))) / 100);
    }

    /** What the package costs per month, for comparison with the monthly plan. */
    public function monthlyEquivalentFor(?Course $course): int
    {
        return (int) round($this->priceFor($course) / max(1, $this->duration_months));
    }

    public function getDurationLabelAttribute(): string
    {
        return __(Enrollment::PACKS[$this->duration_months] ?? 'باقة :months أشهر', ['months' => $this->duration_months]);
    }
}

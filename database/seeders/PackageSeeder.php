<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/** Demo packages per center; seeded multi-month enrollments are attached to the matching one. */
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::all()->each(function (Tenant $tenant) {
            $byMonths = [];
            foreach ([
                ['name' => 'باقة الفصل (3 أشهر)', 'duration_months' => 3, 'discount_percent' => 5, 'description' => 'ثلاثة أشهر بتخفيض 5%'],
                ['name' => 'باقة نصف السنة', 'duration_months' => 6, 'discount_percent' => 10, 'description' => 'ستة أشهر بتخفيض 10%'],
                ['name' => 'باقة السنة الكاملة', 'duration_months' => 12, 'discount_percent' => 15, 'description' => 'سنة كاملة بتخفيض 15%'],
            ] as $p) {
                $byMonths[$p['duration_months']] = Package::create($p + ['tenant_id' => $tenant->id, 'is_active' => true])->id;
            }

            foreach ($byMonths as $months => $packageId) {
                Enrollment::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                    ->where('duration_months', $months)->update(['package_id' => $packageId]);
            }
        });
    }
}

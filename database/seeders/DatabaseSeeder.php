<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            TenantSeeder::class,
            StaffUserSeeder::class,
            ReferenceDataSeeder::class,
            StudentSeeder::class,
            EnrollmentSeeder::class,
            PackageSeeder::class,
            ScheduleSlotSeeder::class,
            AttendanceSeeder::class,
            PaymentSeeder::class,
            ExpenseSeeder::class,
            SalaryPaymentSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}

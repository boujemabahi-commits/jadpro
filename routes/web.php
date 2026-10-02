<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\StudentController;
use App\Livewire\Admin\SignupRequests;
use App\Livewire\Attendance\Index as AttendanceIndex;
use App\Livewire\Courses\Index as CoursesIndex;
use App\Livewire\Enrollments\Index as EnrollmentsIndex;
use App\Livewire\Expenses\Index as ExpensesIndex;
use App\Livewire\Groups\Index as GroupsIndex;
use App\Livewire\Notifications\Index as NotificationsIndex;
use App\Livewire\Packages\Index as PackagesIndex;
use App\Livewire\Payments\Index as PaymentsIndex;
use App\Livewire\Public\CenterSignup;
use App\Livewire\Salaries\Index as SalariesIndex;
use App\Livewire\Schedule\Index as ScheduleIndex;
use App\Livewire\Students\Index as StudentsIndex;
use App\Livewire\Teachers\Index as TeachersIndex;
use App\Support\Locales;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Switches the UI language for the current visitor: guests keep it for the
// session, signed-in users get it saved on their account too.
Route::get('/lang/{locale}', function (string $locale) {
    abort_unless(Locales::isSupported($locale), 404);

    session(['locale' => $locale]);

    if (Auth::check()) {
        Auth::user()->forceFill(['locale' => $locale])->save();
    }

    return redirect()->back();
})->name('lang.switch');

// Public: a prospective center asks to join. Creates a request only (see Phase 10).
Route::get('/register-center', CenterSignup::class)->middleware('guest')->name('register-center');

// Platform back office (the SaaS operator). Separate from the tenant app below.
Route::middleware(['auth', 'platform-admin'])->prefix('admin')->group(function () {
    Route::redirect('/', '/admin/signups');
    Route::get('/signups', SignupRequests::class)->name('admin.signups');
});

// The tenant app: signed-in users that belong to a center. A platform admin is
// redirected to /admin here and can never render a tenant page.
Route::middleware(['auth', 'tenant-user'])->group(function () {
    // Open to every signed-in staff member.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/notifications', NotificationsIndex::class)->name('notifications.index');
    Route::get('/settings', fn () => view('settings.index'))->name('settings.index');

    // Everything else is gated by a permission (see App\Support\Permissions).
    Route::middleware('permission:manage-students')->group(function () {
        Route::get('/students', StudentsIndex::class)->name('students.index');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    });

    Route::middleware('permission:manage-courses-groups-teachers')->group(function () {
        Route::get('/teachers', TeachersIndex::class)->name('teachers.index');
        Route::get('/courses', CoursesIndex::class)->name('courses.index');
        Route::get('/groups', GroupsIndex::class)->name('groups.index');
    });

    Route::get('/enrollments', EnrollmentsIndex::class)->middleware('permission:manage-enrollments')->name('enrollments.index');
    Route::get('/packages', PackagesIndex::class)->middleware('permission:manage-enrollments')->name('packages.index');
    Route::get('/attendance', AttendanceIndex::class)->middleware('permission:manage-attendance')->name('attendance.index');
    Route::get('/schedule', ScheduleIndex::class)->middleware('permission:manage-schedule')->name('schedule.index');

    Route::middleware('permission:manage-payments')->group(function () {
        Route::get('/payments', PaymentsIndex::class)->name('payments.index');
        Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    });

    Route::get('/expenses', ExpensesIndex::class)->middleware('permission:manage-expenses')->name('expenses.index');
    Route::get('/salaries', SalariesIndex::class)->middleware('permission:manage-salaries')->name('salaries.index');

    Route::middleware('permission:view-reports')->group(function () {
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');
    });
});

require __DIR__.'/auth.php';

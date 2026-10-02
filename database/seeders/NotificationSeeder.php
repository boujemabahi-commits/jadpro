<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Phase 1's Mock\Notifications sample feed per tenant. The mock's display
 * strings ('منذ 10 دقائق', 'أمس، 18:20', …) become real created_at offsets so
 * diffForHumans() renders the same impression.
 */
class NotificationSeeder extends Seeder
{
    protected array $items = [
        ['title' => 'تم تسجيل طالب جديد', 'body' => 'قامت فاطمة العلوي بتسجيل ابنها في دورة English A1.', 'icon' => 'user-round-plus', 'tone' => 'brand', 'ago' => '10 minutes', 'read' => false, 'category' => 'الطلاب'],
        ['title' => 'تم استلام دفعة جديدة', 'body' => 'دفعة بقيمة 900 MAD من الطالب يوسف العلوي.', 'icon' => 'wallet', 'tone' => 'blue', 'ago' => '32 minutes', 'read' => false, 'category' => 'المالية'],
        ['title' => 'لديك طلاب لم يؤدوا الشهر الحالي', 'body' => 'يوجد 18 طالباً لم يؤدوا رسوم الاشتراك لهذا الشهر.', 'icon' => 'triangle-alert', 'tone' => 'amber', 'ago' => '3 hours', 'read' => false, 'category' => 'المالية'],
        ['title' => 'لديك حصة قادمة', 'body' => 'حصة English B1 - A تبدأ بعد 30 دقيقة في القاعة 2.', 'icon' => 'calendar-check', 'tone' => 'violet', 'ago' => '5 hours', 'read' => false, 'category' => 'الجدول'],
        ['title' => 'تسجيل جديد في IELTS', 'body' => 'انضم الطالب طارق بناني إلى مجموعة IELTS - A.', 'icon' => 'clipboard-list', 'tone' => 'brand', 'ago' => '1 day', 'read' => true, 'category' => 'التسجيلات'],
        ['title' => 'تحديث في جدول الأستاذ كريم العلوي', 'body' => 'تم تعديل توقيت مجموعة Français A1 - B.', 'icon' => 'calendar-days', 'tone' => 'blue', 'ago' => '1 day 7 hours', 'read' => true, 'category' => 'الجدول'],
        ['title' => 'تم دفع أجرة الأستاذة سلمى برادة', 'body' => 'تم صرف 3520 MAD مقابل ساعات شتنبر.', 'icon' => 'banknote', 'tone' => 'brand', 'ago' => '2 days', 'read' => true, 'category' => 'المالية'],
        ['title' => 'مصروف جديد تم تسجيله', 'body' => 'إضافة مصروف "كراء المحل - شتنبر" بقيمة 12,000 MAD.', 'icon' => 'receipt', 'tone' => 'rose', 'ago' => '3 days', 'read' => true, 'category' => 'المالية'],
        ['title' => 'انخفاض في نسبة الحضور', 'body' => 'نسبة حضور مجموعة Mathématiques - B أقل من 80% هذا الأسبوع.', 'icon' => 'calendar-check', 'tone' => 'amber', 'ago' => '4 days', 'read' => true, 'category' => 'الحضور'],
        ['title' => 'مجموعة جديدة تم إنشاؤها', 'body' => 'تم إنشاء مجموعة Espagnol A1 - A بسعة 18 طالباً.', 'icon' => 'users-round', 'tone' => 'violet', 'ago' => '1 week', 'read' => true, 'category' => 'المجموعات'],
    ];

    public function run(): void
    {
        Tenant::all()->each(function (Tenant $tenant) {
            foreach ($this->items as $item) {
                $at = now()->sub($item['ago']);

                $n = Notification::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => null,
                    'title' => $item['title'],
                    'body' => $item['body'],
                    'icon' => $item['icon'],
                    'tone' => $item['tone'],
                    'category' => $item['category'],
                    'permission' => ['wallet' => 'manage-payments', 'banknote' => 'manage-salaries', 'receipt' => 'manage-expenses'][$item['icon']] ?? null,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                if ($item['read']) {
                    $userIds = \App\Models\User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('id')->all();
                    \Illuminate\Support\Facades\DB::table('notification_reads')->insertOrIgnore(
                        array_map(fn ($uid) => ['notification_id' => $n->id, 'user_id' => $uid, 'read_at' => $at], $userIds)
                    );
                }
            }
        });
    }
}

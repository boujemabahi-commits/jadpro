<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications in a multi-user center:
 *  - "read" was one flag shared by the whole center, so when one employee opened a
 *    notification it was marked read for everyone (the owner included). Reads are now
 *    per user (notification_reads).
 *  - A notification can require a permission (e.g. salaries → manage-salaries), so money
 *    amounts are only shown to staff allowed to see them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('permission', 60)->nullable()->after('category');
        });

        Schema::create('notification_reads', function (Blueprint $table) {
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();
            $table->primary(['notification_id', 'user_id']);
        });

        // Keep today's state: whatever was already read stays read for every member of that center.
        $rows = DB::table('notifications')
            ->join('users', 'users.tenant_id', '=', 'notifications.tenant_id')
            ->where('notifications.read', true)
            ->where(fn ($q) => $q->whereNull('notifications.user_id')->orWhereColumn('notifications.user_id', 'users.id'))
            ->select('notifications.id as notification_id', 'users.id as user_id')
            ->get();
        foreach ($rows->chunk(500) as $chunk) {
            DB::table('notification_reads')->insertOrIgnore($chunk->map(fn ($r) => (array) $r)->all());
        }

        // Money notifications already in the feed: only for staff who handle money.
        DB::table('notifications')->where('icon', 'wallet')->update(['permission' => 'manage-payments']);
        DB::table('notifications')->where('icon', 'banknote')->update(['permission' => 'manage-salaries']);
        DB::table('notifications')->where('icon', 'receipt')->update(['permission' => 'manage-expenses']);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('permission');
        });
    }
};

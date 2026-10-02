<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Existing accounts: store emails lowercase and trimmed, matching what the app now does on
 * every save and on login. Skips an email whose lowercase form already belongs to another
 * account (never merges or breaks accounts).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('users')->select('id', 'email')->get() as $u) {
            $clean = mb_strtolower(trim((string) $u->email));
            if ($clean === $u->email || $clean === '') {
                continue;
            }
            $taken = DB::table('users')->where('id', '!=', $u->id)->whereRaw('LOWER(TRIM(email)) = ?', [$clean])->exists();
            if (! $taken) {
                DB::table('users')->where('id', $u->id)->update(['email' => $clean]);
            }
        }

        if (DB::getSchemaBuilder()->hasTable('center_signup_requests')) {
            DB::table('center_signup_requests')->update(['owner_email' => DB::raw('LOWER(TRIM(owner_email))')]);
        }
    }

    public function down(): void
    {
        // Lowercasing is not reversible (the original casing is not kept).
    }
};

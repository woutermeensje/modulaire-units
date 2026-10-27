<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Makes sure the ADMIN_EMAIL address exists as an administrator, so the
 * password can be set via /admin/wachtwoord-reset/.
 */
return new class extends Migration
{
    public function up(): void
    {
        $email = config('app.admin_email');

        if (blank($email)) {
            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $user->name ?: 'Beheerder';
        $user->password = $user->password ?: Str::password(32);
        $user->forceFill(['is_admin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
    }

    public function down(): void
    {
        //
    }
};

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('site:make-admin {email} {--name=Beheerder} {--password= : Laat leeg voor een willekeurig wachtwoord}')]
#[Description('Maak een beheerder aan (of maak een bestaande gebruiker beheerder) voor het CMS op /admin')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $password = $this->option('password') ?: Str::password(16);

        $user = User::query()->firstOrNew(['email' => $this->argument('email')]);
        $user->name = $user->name ?: $this->option('name');
        $user->password = $password;
        $user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

        $this->components->info("Beheerder {$user->email} is klaar. Log in via /inloggen/.");
        $this->components->twoColumnDetail('Wachtwoord', $password);

        return self::SUCCESS;
    }
}

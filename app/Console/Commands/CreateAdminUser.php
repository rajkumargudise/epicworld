<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {email} {--name=Admin}';

    protected $description = 'Create an administrator with a random one-time password (printed once). Change it after first login.';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));

        if (User::where('email', $email)->exists()) {
            $this->error("A user with {$email} already exists.");

            return self::FAILURE;
        }

        $password = Str::password(20, symbols: false);

        User::create([
            'name' => $this->option('name'),
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->info("Administrator {$email} created.");
        $this->line("One-time password: {$password}");
        $this->warn('Log in and change it immediately at /admin/account/password.');

        return self::SUCCESS;
    }
}

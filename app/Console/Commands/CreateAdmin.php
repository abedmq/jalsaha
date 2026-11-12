<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'jalsaha:admin {email?}';

    protected $description = 'Create the first administrator without default credentials';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Admin email');
        $name = $this->ask('Admin name', 'Administrator');
        $password = $this->secret('Password (at least 12 characters)');
        validator(compact('email', 'name', 'password'), ['email' => 'required|email|unique:users,email', 'name' => 'required|max:100', 'password' => 'required|min:12'])->validate();
        User::create(compact('email', 'name', 'password') + ['is_admin' => true, 'is_active' => true]);
        $this->info('Administrator created. Sign in at /login.');

        return self::SUCCESS;
    }
}

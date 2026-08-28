<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create {email} {--name=CafeOn 관리자} {--password=}';

    protected $description = 'Create or promote a CafeOn SUPER_ADMIN account';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $password = (string) ($this->option('password') ?: Str::password(20));

        $user = User::withTrashed()->where('email', $email)->first() ?? new User;
        $user->fill([
            'name' => (string) $this->option('name'),
            'email' => $email,
            'password' => $password,
            'role' => 'SUPER_ADMIN',
            'is_active' => true,
        ]);
        $user->deleted_at = null;
        $user->save();

        $this->info('SUPER_ADMIN account is ready.');
        $this->line('Email: '.$email);
        $this->line('Password: '.$password);

        return self::SUCCESS;
    }
}
